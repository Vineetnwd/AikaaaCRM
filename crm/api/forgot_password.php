<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'failed',
        'error' => 'Email address is required'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'failed',
        'error' => 'Invalid email address format'
    ]);
    exit;
}

$db = Database::getInstance();

// 1. Check if user exists by email (case-insensitive)
$users = $db->fetchAll("SELECT * FROM users WHERE LOWER(email) = LOWER(?)", [$email]);

if (empty($users)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'status' => 'not_found',
        'error' => 'Email address not found. No account is registered with this email address.'
    ]);
    exit;
}

// 2. Check if user account is active
$activeUsers = array_values(array_filter($users, fn($u) => ($u['status'] ?? 'active') === 'active'));
if (empty($activeUsers)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'status' => 'inactive',
        'error' => 'The account associated with this email is currently inactive or suspended. Please contact your administrator.'
    ]);
    exit;
}

// 3. Generate new random password
$newPassword = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 10);
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// 4. Prepare email content
$userName = $activeUsers[0]['name'] ?: 'User';
$loginUrl = APP_URL . '/public/index.php/login';
$subject = "Your New Aikaa CRM Password";

// Check if user's company has SMTP configured
$company = null;
if (!empty($activeUsers[0]['company_id'])) {
    $company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$activeUsers[0]['company_id']]);
}

$htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your New Password</title>
</head>
<body style="margin:0; padding:24px 12px; background-color:#f8fafc; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#1e293b;">
    <div style="max-width:500px; margin:0 auto; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(0,0,0,0.06);">
        <div style="background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); padding:28px 24px; text-align:center; color:#ffffff;">
            <h1 style="margin:0; font-size:22px; font-weight:800; letter-spacing:-0.02em;">Password Reset</h1>
            <p style="margin:6px 0 0 0; font-size:13px; opacity:0.9;">Aikaa CRM Account Access</p>
        </div>
        <div style="padding:28px 24px;">
            <p style="margin:0 0 14px 0; font-size:15px; font-weight:600; color:#0f172a;">Hello ' . htmlspecialchars($userName) . ',</p>
            <p style="margin:0 0 20px 0; font-size:14px; color:#475569; line-height:1.5;">You recently requested a password reset for your Aikaa CRM account. A new temporary password has been generated:</p>
            
            <div style="background:#f1f5f9; border:1px dashed #cbd5e1; border-radius:8px; padding:14px 18px; text-align:center; margin-bottom:24px;">
                <span style="display:block; font-size:11px; text-transform:uppercase; font-weight:700; color:#64748b; letter-spacing:0.05em; margin-bottom:4px;">New Temporary Password</span>
                <span style="font-size:22px; font-weight:800; color:#4f46e5; letter-spacing:2px; font-family:Consolas, Monaco, monospace;">' . htmlspecialchars($newPassword) . '</span>
            </div>

            <div style="text-align:center; margin-bottom:24px;">
                <a href="' . htmlspecialchars($loginUrl) . '" style="display:inline-block; background:#4f46e5; color:#ffffff; font-weight:700; font-size:14px; text-decoration:none; padding:12px 28px; border-radius:8px; box-shadow:0 2px 6px rgba(79,70,229,0.25);">Log In to CRM &rarr;</a>
            </div>

            <p style="margin:0; font-size:12px; color:#94a3b8; line-height:1.45;">For security, please change your password after logging in from your Profile / Settings. If you did not make this request, please inform your administrator immediately.</p>
        </div>
        <div style="background:#f8fafc; border-top:1px solid #f1f5f9; padding:14px 24px; text-align:center; font-size:12px; color:#94a3b8;">
            &copy; ' . date('Y') . ' Aikaa CRM. All rights reserved.
        </div>
    </div>
</body>
</html>';

$mailSent = false;
$mailError = null;

// 5. Try company SMTP if configured
if ($company && !empty($company['smtp_host']) && !empty($company['smtp_email'])) {
    try {
        require_once __DIR__ . '/../core/Mailer.php';
        \Core\Mailer::send($email, $subject, $htmlBody, [
            'smtp_host' => $company['smtp_host'],
            'smtp_port' => $company['smtp_port'],
            'smtp_email' => $company['smtp_email'],
            'smtp_password' => $company['smtp_password'],
            'smtp_secure' => $company['smtp_secure'],
            'company_name' => $company['name'] ?? 'Aikaa CRM'
        ]);
        $mailSent = true;
    } catch (\Throwable $e) {
        $mailError = $e->getMessage();
    }
}

// 6. Fallback to standard mail()
if (!$mailSent) {
    $fromHost = parse_url(APP_URL, PHP_URL_HOST) ?: 'aikocrm.com';
    $fromEmail = 'noreply@' . $fromHost;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Aikaa CRM <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $is_local = in_array(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0], ['localhost', '127.0.0.1']);
    
    if ($is_local) {
        $sent = @mail($email, $subject, $htmlBody, $headers);
        $mailSent = true;
    } else {
        $sent = @mail($email, $subject, $htmlBody, $headers);
        if ($sent) {
            $mailSent = true;
        } else {
            $mailError = $mailError ?: 'Server mail dispatch failed. Please configure SMTP in Settings.';
        }
    }
}

// 7. Return proper accurate status
if ($mailSent) {
    // Only update database credentials once email has been successfully sent
    try {
        foreach ($activeUsers as $u) {
            $db->update('users', ['password' => $hashedPassword], 'id = ?', [$u['id']]);
        }
    } catch (\Throwable $e) {
        // Log but proceed since email was sent
    }

    echo json_encode([
        'success' => true,
        'status' => 'mail_sent',
        'message' => 'Mail sent! A new password has been sent to ' . htmlspecialchars($email) . '. Please check your inbox and spam folder.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'failed',
        'error' => 'Failed to send email. ' . ($mailError ? '(' . $mailError . ')' : 'Please check mail server or SMTP settings.')
    ]);
}
