<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Company.php';
require_once __DIR__ . '/../core/Mailer.php';

use Core\Auth;
use Core\Company;
use Core\Mailer;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!Auth::isAdmin() && !Auth::isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin required.']);
    exit;
}

$companyModel = new Company();
$companyId = Auth::companyId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        echo json_encode(Auth::company());
    } elseif ($method === 'POST') {
        // Handle Logo, Signature, or QR Upload if present
        foreach (['logo', 'signature', 'payment_qr'] as $fileKey) {
            if (isset($_FILES[$fileKey])) {
                $file = $_FILES[$fileKey];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Upload error code: ' . $file['error']]);
                    exit;
                }

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $allowedExts = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
                
                if (!in_array(strtolower($ext), $allowedExts)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExts)]);
                    exit;
                }

                $newName = $fileKey . '_' . $companyId . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../public/uploads/' . ($fileKey === 'logo' ? 'logos/' : 'assets/');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                    chmod($uploadDir . $newName, 0644);
                    $path = 'uploads/' . ($fileKey === 'logo' ? 'logos/' : 'assets/') . $newName;
                    $column = $fileKey === 'payment_qr' ? 'qr_code_path' : ($fileKey === 'signature' ? 'signature_path' : 'logo_path');
                    $companyModel->update($companyId, [$column => $path]);
                    echo json_encode(['success' => true, $column => $path]);
                } else {
                    http_response_code(500);
                    $reason = "Unknown move error";
                    if (!is_writable($uploadDir)) $reason = "Directory not writable: " . $uploadDir;
                    if (!file_exists($file['tmp_name'])) $reason = "Temp file missing: " . $file['tmp_name'];
                    echo json_encode(['error' => 'Failed to move file: ' . $reason]);
                }
                exit;
            }
        }

        // Handle JSON data update
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input)) {
            // Might be from FormData if we mix them, but usually we send separately.
            $input = $_POST;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'test_smtp') {
            $smtp = [
                'smtp_host' => $input['smtp_host'] ?? '',
                'smtp_port' => intval($input['smtp_port'] ?? 587),
                'smtp_email' => $input['smtp_email'] ?? '',
                'smtp_password' => $input['smtp_password'] ?? '',
                'smtp_secure' => $input['smtp_secure'] ?? 'tls',
                'company_name' => Auth::company()['name'] ?? 'Aikaa CRM'
            ];
            
            $to = $_SESSION['user']['email'] ?? '';
            if (empty($to)) {
                echo json_encode(['success' => false, 'error' => 'Active administrator email session is missing.']);
                exit;
            }
            
            $subject = "SMTP Mailer Connection Test - Aikaa CRM";
            $body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>SMTP Connection Test</title>
            </head>
            <body style="font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; padding: 40px; color: #1e293b;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="font-size: 24px; font-weight: 800; color: #6366f1; margin-bottom: 16px; letter-spacing: -0.02em;">Connection Successful!</div>
                    <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-bottom: 24px;">Congratulations! This test email confirms that your SMTP Configuration in <strong>Aikaa CRM</strong> is fully authenticated and operational.</p>
                    
                    <div style="background-color: #f1f5f9; border-radius: 10px; padding: 16px 20px; font-size: 13px; line-height: 1.5; color: #475569; margin-bottom: 24px;">
                        <strong style="display:block; margin-bottom: 8px; color: #0f172a; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em;">SMTP Configuration Verified:</strong>
                        • Host: <code>' . htmlspecialchars($smtp['smtp_host']) . '</code><br>
                        • Port: <code>' . $smtp['smtp_port'] . '</code><br>
                        • Encryption: <code>' . htmlspecialchars($smtp['smtp_secure']) . '</code><br>
                        • Sender Identity: <code>' . htmlspecialchars($smtp['smtp_email']) . '</code>
                    </div>
                    
                    <p style="font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px; margin: 0;">You can now securely send outstanding dues invoice reminder emails directly from this account.</p>
                </div>
            </body>
            </html>';
            
            try {
                Mailer::send($to, $subject, $body, $smtp);
                echo json_encode([
                    'success' => true,
                    'message' => 'Connection test passed! A verification email has been successfully sent to ' . $to,
                    'logs' => Mailer::getLogs()
                ]);
            } catch (\Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'logs' => Mailer::getLogs()
                ]);
            }
            exit;
        }

        if (!empty($input)) {
            // Only allow updating current company
            $allowed = ['name', 'address', 'gst_number', 'invoice_terms', 'quotation_terms', 'bank_details', 'smtp_host', 'smtp_port', 'smtp_email', 'smtp_password', 'smtp_secure', 'whatsapp_access_token', 'whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_number', 'whatsapp_default_template', 'whatsapp_default_mapping', 'whatsapp_header_image'];
            $data = array_intersect_key($input, array_flip($allowed));
            
            if ($companyModel->update($companyId, $data)) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update company details']);
            }
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
