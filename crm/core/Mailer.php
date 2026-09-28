<?php
namespace Core;

class Mailer {
    private static $logs = [];

    /**
     * Sends an HTML email via raw SMTP socket connection.
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $body HTML email content body
     * @param array $smtp SMTP credentials: ['smtp_host', 'smtp_port', 'smtp_email', 'smtp_password', 'smtp_secure', 'company_name']
     * @return bool True on success, throws Exception on failure
     */
    public static function send($to, $subject, $body, $smtp) {
        self::$logs = [];
        self::$logs[] = "Initializing connection sequence...";

        $host = $smtp['smtp_host'] ?? '';
        $port = intval($smtp['smtp_port'] ?? 587);
        $username = $smtp['smtp_email'] ?? '';
        $password = $smtp['smtp_password'] ?? '';
        $secure = strtolower($smtp['smtp_secure'] ?? 'tls'); // 'tls', 'ssl', or 'none'
        $company_name = $smtp['company_name'] ?? 'Aikaa CRM';

        if (empty($host) || empty($username)) {
            throw new \Exception("SMTP configuration details are incomplete (Host and Email Username are required)");
        }

        $socketHost = $host;
        if ($secure === 'ssl') {
            $socketHost = 'ssl://' . $host;
        }

        self::$logs[] = "Connecting to $socketHost:$port...";
        // Establish socket connection (15s timeout)
        $socket = @fsockopen($socketHost, $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new \Exception("SMTP connection failed to $socketHost:$port. Error: $errstr ($errno)");
        }

        // 1. Read Welcome banner
        $response = self::read($socket);
        self::verifyResponse($response, '220', "Failed to connect to SMTP mail server");

        // 2. Handshake
        self::write($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $response = self::read($socket);
        self::verifyResponse($response, '250', "EHLO handshake failed");

        // 3. Upgrade to TLS if requested
        if ($secure === 'tls') {
            self::write($socket, "STARTTLS");
            $response = self::read($socket);
            self::verifyResponse($response, '220', "STARTTLS upgrade request rejected by SMTP server");

            self::$logs[] = "Enabling stream crypto (TLS)...";
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                @fclose($socket);
                throw new \Exception("Secure stream encryption (TLS) handshake failed");
            }

            // 4. Re-handshake over secure stream
            self::write($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $response = self::read($socket);
            self::verifyResponse($response, '250', "Secure EHLO handshake failed");
        }

        // 5. Authentication
        if (!empty($username) && !empty($password)) {
            self::write($socket, "AUTH LOGIN");
            $response = self::read($socket);
            self::verifyResponse($response, '334', "AUTH LOGIN command rejected");

            self::write($socket, base64_encode($username));
            $response = self::read($socket);
            self::verifyResponse($response, '334', "SMTP username login rejected");

            self::write($socket, base64_encode($password));
            $response = self::read($socket);
            self::verifyResponse($response, '235', "SMTP Authentication credentials failed");
        }

        // 6. Sender
        self::write($socket, "MAIL FROM: <$username>");
        $response = self::read($socket);
        self::verifyResponse($response, '250', "Sender address rejected by server");

        // 7. Recipient
        self::write($socket, "RCPT TO: <$to>");
        $response = self::read($socket);
        self::verifyResponse($response, '250', "Recipient address rejected by server");

        // 8. Mail Data Content
        self::write($socket, "DATA");
        $response = self::read($socket);
        self::verifyResponse($response, '354', "DATA start command rejected");

        // Build proper SMTP transaction payload headers
        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "To: <$to>",
            "From: " . htmlspecialchars($company_name) . " <$username>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "X-Mailer: PHP-SocketSMTP/" . phpversion(),
            "Message-ID: <" . md5(uniqid(microtime(), true)) . "@" . $host . ">"
        ];

        // Combine headers & body, ensuring boundary endings are handled properly
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
        
        self::$logs[] = "Writing email headers & content payload...";
        fwrite($socket, $payload);
        $response = self::read($socket);
        self::verifyResponse($response, '250', "Failed to deliver email message payload");

        // 9. Quit
        self::write($socket, "QUIT");
        self::read($socket);
        @fclose($socket);

        self::$logs[] = "Email sent successfully!";
        return true;
    }

    /**
     * Retrieve the transaction logs generated during the last connection.
     * 
     * @return array
     */
    public static function getLogs() {
        return self::$logs;
    }

    private static function write($socket, $cmd) {
        self::$logs[] = "> " . preg_replace('/AUTH LOGIN|EHLO|MAIL FROM|RCPT TO|DATA|QUIT/i', '$0', $cmd);
        fwrite($socket, $cmd . "\r\n");
    }

    private static function read($socket) {
        $response = "";
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // SMTP specifications state multi-line responses have a dash on line index 3, 
            // and the final response line has a space at index 3.
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        $cleanResponse = trim($response);
        self::$logs[] = "< " . $cleanResponse;
        return $cleanResponse;
    }

    private static function verifyResponse($response, $expectedCode, $errMessage) {
        if (strpos($response, $expectedCode) === false) {
            throw new \Exception("$errMessage. SMTP response details: '$response'");
        }
    }
}
?>
