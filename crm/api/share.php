<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Mailer.php';

use Core\Database;
use Core\Auth;
use Core\Mailer;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = intval($input['id'] ?? 0);
$type = $input['type'] ?? '';
$custom_email = trim($input['email'] ?? '');
$custom_subject = trim($input['subject'] ?? '');
$custom_note = trim($input['note'] ?? '');

if (!$id || !in_array($type, ['invoice', 'quotation'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $db = Database::getInstance();
    $company_id = Auth::companyId();

    // 1. Fetch Company & SMTP Settings
    $company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$company_id]);
    if (!$company) {
        throw new Exception("Company not found");
    }

    $smtp = [
        'smtp_host' => $company['smtp_host'] ?? '',
        'smtp_port' => intval($company['smtp_port'] ?? 587),
        'smtp_email' => $company['smtp_email'] ?? '',
        'smtp_password' => $company['smtp_password'] ?? '',
        'smtp_secure' => $company['smtp_secure'] ?? 'tls',
        'company_name' => $company['name'] ?? 'Aikaa CRM'
    ];

    $has_smtp = (!empty($smtp['smtp_host']) && !empty($smtp['smtp_email']) && !empty($smtp['smtp_password']));

    // 2. Fetch Document Data
    $data = [];
    $to_email = '';
    $subject = '';
    $htmlBody = '';

    if ($type === 'invoice') {
        $invoice = $db->fetchOne("
            SELECT i.*, l.name as client_name, l.email as client_email, l.address as client_address 
            FROM invoices i 
            LEFT JOIN leads l ON i.lead_id = l.id 
            WHERE i.id = ? AND i.company_id = ?
        ", [$id, $company_id]);

        if (!$invoice) throw new Exception("Invoice not found or access denied");
        
        $to_email = !empty($custom_email) ? $custom_email : ($invoice['client_email'] ?? '');
        if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please specify a valid client email address");
        }

        $subject = !empty($custom_subject) ? $custom_subject : ("Invoice " . $invoice['invoice_number'] . " from " . htmlspecialchars($company['name']));
        $clientName = !empty($invoice['client_name']) ? $invoice['client_name'] : 'Valued Client';
        
        // Build items
        $itemsHtml = '';
        if (!empty($invoice['items_json'])) {
            $items = json_decode($invoice['items_json'], true) ?: [];
            foreach ($items as $item) {
                $amount = floatval($item['qty']) * floatval($item['rate']);
                $itemsHtml .= '
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b;">
                        <strong>' . htmlspecialchars($item['name']) . '</strong>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">' . htmlspecialchars($item['description'] ?? '') . '</div>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #475569;">' . htmlspecialchars($item['qty']) . '</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #475569;">₹' . number_format($item['rate'], 2) . '</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #0f172a; font-weight: 600;">₹' . number_format($amount, 2) . '</td>
                </tr>';
            }
        }

        $appUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        // If app resides in a subdirectory, this might need adjustment, but generally we rely on the host. 
        // For local development, relying on the client side URL is safer. 
        $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $base_path = preg_replace('/\/api$/', '', $script_dir);
        $base_path = preg_replace('/\/public$/', '', $base_path);
        $base_path = rtrim($base_path, '/');
        
        // Define APP_URL if not using config
        if (!defined('APP_URL')) {
            define('APP_URL', $appUrl . $base_path);
        }
        
        $printUrl = APP_URL . "/public/assets/views/invoice_print.php?id=" . $invoice['id'] . "&token=" . md5($invoice['id'] . 'your-very-secure-secret-key-123456');

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <body style="font-family: \'Inter\', sans-serif; background-color: #f8fafc; padding: 40px 20px; margin: 0;">
            <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                <!-- Header -->
                <div style="background: #4f46e5; padding: 30px; text-align: center; color: #ffffff;">
                    <h1 style="margin: 0; font-size: 24px; font-weight: 800;">' . htmlspecialchars($company['name']) . '</h1>
                    <p style="margin: 5px 0 0; color: #e0e7ff; font-size: 14px;">Thank you for your business!</p>
                </div>
                
                <!-- Body -->
                <div style="padding: 30px;">
                    <h2 style="font-size: 20px; color: #1e293b; margin-top: 0;">Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</h2>
                    <p style="color: #475569; font-size: 15px; line-height: 1.6;">Dear ' . htmlspecialchars($invoice['client_name']) . ',</p>
                    <p style="color: #475569; font-size: 15px; line-height: 1.6;">Here is the summary of your invoice generated on <strong>' . date('d M, Y', strtotime($invoice['invoice_date'])) . '</strong>.</p>
                    
                    <div style="background: #f1f5f9; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0;">
                        <span style="display: block; font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px;">Amount Due</span>
                        <span style="display: block; font-size: 32px; font-weight: 800; color: #4f46e5;">₹' . number_format($invoice['due_amount'], 2) . '</span>
                        <span style="display: block; font-size: 13px; font-weight: 600; color: #94a3b8; margin-top: 5px;">Total Amount: ₹' . number_format($invoice['total_amount'], 2) . '</span>
                    </div>

                    <div style="text-align: center; margin-bottom: 25px;">
                        <a href="' . $printUrl . '" style="display: inline-block; background: #4f46e5; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">View & Download Invoice</a>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Description</th>
                                <th style="text-align: center; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Qty</th>
                                <th style="text-align: right; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Rate</th>
                                <th style="text-align: right; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $itemsHtml . '
                        </tbody>
                    </table>
                    
                    <p style="color: #475569; font-size: 14px;">If you have any questions about this invoice, please feel free to reach out to us.</p>
                </div>
                
                <!-- Footer -->
                <div style="background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                    <p style="margin: 0; color: #64748b; font-size: 12px;">' . htmlspecialchars($company['name']) . ' &bull; ' . htmlspecialchars($company['address'] ?? '') . '</p>
                </div>
            </div>
        </body>
        </html>';

    } else if ($type === 'quotation') {
        $quotation = $db->fetchOne("
            SELECT q.*, l.name as client_name, l.email as client_email, l.address as client_address 
            FROM quotations q 
            LEFT JOIN leads l ON q.lead_id = l.id 
            WHERE q.id = ? AND q.company_id = ?
        ", [$id, $company_id]);

        if (!$quotation) throw new Exception("Quotation not found or access denied");
        $to_email = !empty($custom_email) ? $custom_email : ($quotation['client_email'] ?? '');
        if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please specify a valid client email address");
        }

        $subject = !empty($custom_subject) ? $custom_subject : ("Quotation " . $quotation['quotation_number'] . " from " . htmlspecialchars($company['name']));
        $clientName = !empty($quotation['client_name']) ? $quotation['client_name'] : 'Valued Client';
        $publicQuotationUrl = APP_URL . "/public/index.php/quotation/print?id=" . $quotation['id'];

        // Build items
        $itemsHtml = '';
        if (!empty($quotation['description'])) {
            $items = json_decode($quotation['description'], true) ?: [];
            foreach ($items as $item) {
                $amount = floatval($item['qty'] ?? 1) * floatval($item['rate'] ?? 0);
                $itemsHtml .= '
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; color: #1e293b;">
                        <strong>' . htmlspecialchars($item['name'] ?? '') . '</strong>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">' . htmlspecialchars($item['description'] ?? '') . '</div>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #475569;">' . htmlspecialchars($item['qty'] ?? 1) . '</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #475569;">₹' . number_format($item['rate'] ?? 0, 2) . '</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #0f172a; font-weight: 600;">₹' . number_format($amount, 2) . '</td>
                </tr>';
            }
        }

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <body style="font-family: \'Inter\', sans-serif; background-color: #f8fafc; padding: 40px 20px; margin: 0;">
            <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                <!-- Header -->
                <div style="background: #10b981; padding: 30px; text-align: center; color: #ffffff;">
                    <h1 style="margin: 0; font-size: 24px; font-weight: 800;">' . htmlspecialchars($company['name']) . '</h1>
                    <p style="margin: 5px 0 0; color: #d1fae5; font-size: 14px;">Project Proposal & Quotation</p>
                </div>
                
                <!-- Body -->
                <div style="padding: 30px;">
                    <h2 style="font-size: 20px; color: #1e293b; margin-top: 0;">Quotation ' . htmlspecialchars($quotation['quotation_number']) . '</h2>
                    <p style="color: #475569; font-size: 15px; line-height: 1.6;">Dear ' . htmlspecialchars($clientName) . ',</p>
                    <p style="color: #475569; font-size: 15px; line-height: 1.6;">Thank you for your interest! Here is the quotation we prepared for you on <strong>' . date('d M, Y', strtotime($quotation['quotation_date'] ?? 'now')) . '</strong>.</p>
                    ' . (!empty($custom_note) ? '<div style="background: #f8fafc; border-left: 4px solid #10b981; padding: 12px 16px; margin: 18px 0; font-size: 14px; color: #334155; line-height: 1.5;">' . nl2br(htmlspecialchars($custom_note)) . '</div>' : '') . '
                    
                    <div style="background: #f1f5f9; border-radius: 8px; padding: 20px; text-align: center; margin: 25px 0;">
                        <span style="display: block; font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px;">Total Value</span>
                        <span style="display: block; font-size: 32px; font-weight: 800; color: #10b981;">₹' . number_format($quotation['total_amount'] ?? 0, 2) . '</span>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Description</th>
                                <th style="text-align: center; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Qty</th>
                                <th style="text-align: right; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Rate</th>
                                <th style="text-align: right; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 12px; text-transform: uppercase;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $itemsHtml . '
                        </tbody>
                    </table>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . htmlspecialchars($publicQuotationUrl) . '" target="_blank" style="background: #10b981; color: white; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; display: inline-block;">View / Print Quotation</a>
                    </div>
                    
                    <p style="color: #475569; font-size: 14px;">We look forward to working with you. Please let us know if you have any questions.</p>
                </div>
                
                <!-- Footer -->
                <div style="background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                    <p style="margin: 0; color: #64748b; font-size: 12px;">' . htmlspecialchars($company['name']) . ' &bull; ' . htmlspecialchars($company['address'] ?? '') . '</p>
                </div>
            </div>
        </body>
        </html>';
    } else if ($type === 'whatsapp') {
        $invoice = $db->fetchOne("
            SELECT i.*, l.name as client_name, l.mobile as client_mobile 
            FROM invoices i 
            LEFT JOIN leads l ON i.lead_id = l.id 
            WHERE i.id = ? AND i.company_id = ?
        ", [$id, $company_id]);

        if (!$invoice) throw new Exception("Invoice not found or access denied");
        if (empty($invoice['client_mobile'])) throw new Exception("Client does not have a mobile number specified");

        if (empty($company['whatsapp_access_token']) || empty($company['whatsapp_number'])) {
            throw new Exception("WhatsApp API settings not configured");
        }

        $mobile = preg_replace('/[^0-9]/', '', $invoice['client_mobile']);
        if (strlen($mobile) == 10) {
            $mobile = '91' . $mobile;
        }

        $appUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $base_path = preg_replace('/\/api$/', '', $script_dir);
        $base_path = preg_replace('/\/public$/', '', $base_path);
        $base_path = rtrim($base_path, '/');
        
        if (!defined('APP_URL')) {
            define('APP_URL', $appUrl . $base_path);
        }
        
        $printUrl = APP_URL . "/public/assets/views/invoice_print.php?id=" . $invoice['id'] . "&token=" . md5($invoice['id'] . 'your-very-secure-secret-key-123456');

        $fromNumber = trim($company['whatsapp_number']);
        if (!empty($fromNumber) && $fromNumber[0] !== '+') {
            $fromNumber = '+' . ltrim($fromNumber, '+');
        }

        $templateName = $company['whatsapp_default_template'];
        if (empty($templateName)) {
            throw new Exception("No default WhatsApp template configured in settings.");
        }
        $mapping = array_map('trim', explode(',', $company['whatsapp_default_mapping'] ?? ''));
        
        $dynamicData = [
            'name' => trim($invoice['client_name']) ?: 'Customer',
            'mobile' => $mobile,
            'email' => 'N/A',
            'address' => 'N/A',
            'service' => 'Invoice ' . $invoice['invoice_number'],
            'status' => 'Due',
            'value' => $invoice['total_amount'],
            'link' => $printUrl,
            'invoice_link' => $printUrl
        ];

        $parameters = [];
        foreach ($mapping as $key) {
            if (!empty($key)) {
                $parameters[] = (string)($dynamicData[$key] ?? $key);
            }
        }

        $payload = [
            "from" => $fromNumber,
            "campaignName" => "invoice-share",
            "to" => "+" . ltrim($mobile, '+'),
            "templateName" => $templateName,
            "type" => "template",
            "components" => [
                "body" => [
                    "params" => $parameters
                ]
            ]
        ];

        if (!empty($company['whatsapp_header_image'])) {
            $payload["components"]["header"] = [
                "type" => "image",
                "image" => [
                    "link" => $company['whatsapp_header_image']
                ]
            ];
        }

        $url = "https://api.aoc-portal.com/v1/whatsapp";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: " . $company['whatsapp_access_token'],
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 200 && $httpcode < 300) {
            echo json_encode(['success' => true, 'message' => 'Invoice sent via WhatsApp API successfully']);
            exit;
        } else {
            throw new Exception("WhatsApp API Error: " . $response);
        }
    }

    // 3. Send Email (only if not whatsapp)
    if ($type !== 'whatsapp') {
        $mailSent = false;
        $errorDetails = '';

        if ($has_smtp) {
            try {
                Mailer::send($to_email, $subject, $htmlBody, $smtp);
                $mailSent = true;
            } catch (\Exception $me) {
                $errorDetails = $me->getMessage();
            }
        }

        if (!$mailSent) {
            // Fallback to PHP mail()
            $fromName = !empty($smtp['company_name']) ? $smtp['company_name'] : ($company['name'] ?? 'Aikaa CRM');
            $fromEmail = !empty($smtp['smtp_email']) ? $smtp['smtp_email'] : 'noreply@aikocrm.com';
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
            $headers .= "Reply-To: " . $fromEmail . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            $mailSent = @mail($to_email, $subject, $htmlBody, $headers);
            if (!$mailSent) {
                if (!empty($errorDetails)) {
                    throw new Exception("Failed to send email: " . $errorDetails);
                } else if (!$has_smtp) {
                    throw new Exception("SMTP settings are not configured and mail() failed. Please configure SMTP in Settings -> Mail Configuration.");
                } else {
                    throw new Exception("Failed to send email. Please verify your mail server settings.");
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . " sent successfully to " . htmlspecialchars($to_email)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
