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

use Core\Auth;
use Core\Database;
use Core\Mailer;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$invoice_id = intval($input['invoice_id'] ?? $_GET['invoice_id'] ?? 0);

if (!$invoice_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invoice ID is required']);
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();

// Fetch invoice and lead details
$invoice = $db->fetchOne(
    "SELECT i.*, l.name as client_name, l.email as client_email, l.mobile as client_mobile 
     FROM invoices i
     LEFT JOIN leads l ON i.lead_id = l.id
     WHERE i.id = ? AND i.company_id = ?",
    [$invoice_id, $company_id]
);

if (!$invoice) {
    http_response_code(404);
    echo json_encode(['error' => 'Invoice not found or unauthorized']);
    exit;
}

if (empty($invoice['client_email'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Client email is not available for this invoice']);
    exit;
}

// Fetch company details
$company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$company_id]);
if (!$company) {
    http_response_code(404);
    echo json_encode(['error' => 'Company details not found']);
    exit;
}

$company_name = htmlspecialchars($company['name'] ?? 'Aikaa CRM Client');
$client_name = htmlspecialchars($invoice['client_name'] ?? 'Valued Customer');
$invoice_num = htmlspecialchars($invoice['invoice_number']);
$invoice_date = date('d M, Y', strtotime($invoice['invoice_date']));
$due_date = !empty($invoice['due_date']) ? date('d M, Y', strtotime($invoice['due_date'])) : 'N/A';
$total_amount = '₹' . number_format($invoice['total_amount'], 2);
$paid_amount = '₹' . number_format($invoice['paid_amount'], 2);
$due_amount = '₹' . number_format($invoice['due_amount'], 2);

$logo_url = !empty($company['logo_path']) ? APP_URL . '/public/' . $company['logo_path'] : '';

// Email design styling
$email_body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
        <tr>
            <td align="center" style="padding: 40px 0 20px 0;">
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid #e2e8f0;">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 32px 40px; color: #ffffff;">
                            ' . ($logo_url ? '<img src="' . $logo_url . '" alt="' . $company_name . '" style="height: 50px; max-width: 150px; object-fit: contain; margin-bottom: 12px; border-radius: 6px;">' : '<div style="font-size: 24px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 8px;">' . $company_name . '</div>') . '
                            <div style="font-size: 11px; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.1em;">Payment Reminder</div>
                        </td>
                    </tr>
                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 40px; color: #334155; font-size: 15px; line-height: 1.6;">
                            <h2 style="margin-top: 0; color: #0f172a; font-size: 20px; font-weight: 700; letter-spacing: -0.01em;">Dear ' . $client_name . ',</h2>
                            <p style="margin-bottom: 24px; color: #475569;">We hope this email finds you well. This is a friendly reminder regarding the outstanding balance on invoice <strong>#' . $invoice_num . '</strong>. Below is the current billing status summary:</p>
                            
                            <!-- Invoice Summary Card -->
                            <div style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 28px;">
                                <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px;">Billing Details</div>
                                
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 14px;">
                                    <tr>
                                        <td style="color: #64748b; padding: 6px 0; font-weight: 500;">Invoice Reference</td>
                                        <td align="right" style="color: #0f172a; padding: 6px 0; font-weight: 700;">#' . $invoice_num . '</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; padding: 6px 0; font-weight: 500;">Issue Date</td>
                                        <td align="right" style="color: #0f172a; padding: 6px 0; font-weight: 600;">' . $invoice_date . '</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; padding: 6px 0; font-weight: 500;">Due Date</td>
                                        <td align="right" style="color: #ef4444; padding: 6px 0; font-weight: 700;">' . $due_date . '</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="border-top: 1px dashed #e2e8f0; padding: 10px 0 0 0; margin-top: 10px;"></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; padding: 8px 0; font-weight: 500;">Total Amount</td>
                                        <td align="right" style="color: #0f172a; padding: 8px 0; font-weight: 600;">' . $total_amount . '</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #64748b; padding: 8px 0; font-weight: 500;">Amount Paid</td>
                                        <td align="right" style="color: #10b981; padding: 8px 0; font-weight: 600;">' . $paid_amount . '</td>
                                    </tr>
                                    <tr style="font-size: 16px;">
                                        <td style="color: #0f172a; padding: 8px 0; font-weight: 700;">Outstanding Dues</td>
                                        <td align="right" style="color: #ef4444; padding: 8px 0; font-weight: 800;">' . $due_amount . '</td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin-bottom: 24px; color: #475569;">Please arrange for the remittance of <strong>' . $due_amount . '</strong> at your earliest convenience. If you have already made this payment, please accept our sincere thanks and disregard this notification.</p>
                            
                            <p style="margin-bottom: 0; color: #475569;">Should you require any assistance, have questions regarding the billing statement, or need payment details, please contact us.</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 32px 40px; text-align: center; color: #64748b; font-size: 12px; line-height: 1.6;">
                            <div style="font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 4px;">' . $company_name . '</div>
                            ' . (!empty($company['address']) ? '<div style="margin-bottom: 12px;">' . nl2br(htmlspecialchars($company['address'])) . '</div>' : '') . '
                            <div style="font-size: 11px; color: #94a3b8;">This is an automated security reminder. Please reply directly to our official billing channels if you have questions.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
';

$to = $invoice['client_email'];
$subject = "Payment Reminder: Invoice #" . $invoice_num . " - " . $company_name;

$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: " . $company_name . " <noreply@aikocrm.com>" . "\r\n";
$headers .= "Reply-To: noreply@aikocrm.com" . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$has_smtp = !empty($company['smtp_host']) && !empty($company['smtp_email']);

if ($has_smtp) {
    $smtp = [
        'smtp_host' => $company['smtp_host'],
        'smtp_port' => intval($company['smtp_port'] ?? 587),
        'smtp_email' => $company['smtp_email'],
        'smtp_password' => $company['smtp_password'] ?? '',
        'smtp_secure' => $company['smtp_secure'] ?? 'tls',
        'company_name' => $company['name'] ?? 'Aikaa CRM'
    ];
    
    try {
        Mailer::send($to, $subject, $email_body, $smtp);
        echo json_encode([
            'success' => true,
            'message' => 'Reminder email sent successfully to ' . $to . ' (via SMTP)'
        ]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'SMTP Delivery failed: ' . $e->getMessage()]);
    }
} else {
    $is_local = in_array(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0], ['localhost', '127.0.0.1']);

    if ($is_local) {
        // On local sandbox environment, simulate successful email dispatch
        echo json_encode([
            'success' => true,
            'message' => '[Sandbox Mode] Reminder email simulated successfully for ' . $to
        ]);
    } else {
        if (mail($to, $subject, $email_body, $headers)) {
            echo json_encode([
                'success' => true,
                'message' => 'Reminder email sent successfully to ' . $to
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to dispatch email. Please configure SMTP Settings or check server sendmail config.']);
        }
    }
}
?>
