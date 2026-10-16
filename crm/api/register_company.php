<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Company.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Database;
use Core\Company;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$companyName = trim($_POST['company_name'] ?? '');
$subdomain = trim($_POST['subdomain'] ?? '');
$adminName = trim($_POST['admin_name'] ?? '');
$adminEmail = trim($_POST['admin_email'] ?? '');
$adminMobile = trim($_POST['mobile'] ?? '');
$adminPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

if (empty($companyName) || empty($subdomain) || empty($adminName) || empty($adminEmail) || empty($adminMobile)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

// Clean subdomain to keep it alphanumeric
$subdomain = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $subdomain));
if (empty($subdomain)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid subdomain prefix']);
    exit;
}

$db = Database::getInstance();

// Check if subdomain is already taken
$existingCompany = $db->fetchOne("SELECT id FROM companies WHERE subdomain = ?", [$subdomain]);
if ($existingCompany) {
    http_response_code(400);
    echo json_encode(['error' => 'Subdomain is already taken']);
    exit;
}

// Check if email or mobile already exists
$existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? OR mobile = ?", [$adminEmail, $adminMobile]);
if ($existingUser) {
    http_response_code(400);
    echo json_encode(['error' => 'Email or Mobile already registered']);
    exit;
}

// Handle Photo Upload (optional)
$photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../public/uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . time() . '_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
        $photoPath = $filename;
    }
}

try {
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // 1. Create Company
    $companyModel = new Company();
    $companyId = $companyModel->create([
        'name' => $companyName,
        'subdomain' => $subdomain,
        'plan' => 'trial',
        'status' => 'active'
    ]);

    // 2. Create Employee record for the Admin
    $empData = [
        'company_id' => $companyId,
        'name' => $adminName,
        'email' => $adminEmail,
        'mobile' => $adminMobile,
        'type' => 'permanent',
        'photo' => $photoPath,
        'designation' => 'Administrator',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    $empId = $db->insert('employees', $empData);

    // 3. Create Primary Admin User account
    $userData = [
        'company_id' => $companyId,
        'name' => $adminName,
        'email' => $adminEmail,
        'mobile' => $adminMobile,
        'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
        'role' => 'admin',
        'type' => 'permanent',
        'status' => 'active',
        'photo' => $photoPath,
        'created_at' => date('Y-m-d H:i:s'),
        'employee_id' => $empId,
        'emp_id' => $empId
    ];
    $db->insert('users', $userData);

    $conn->commit();

    // Send email with credentials
    $loginUrl = APP_URL . '/public/index.php/login';
    $subject = "Your Aikaaa CRM Account Credentials";
    
    // Logo setup
    $baseAppUrl = defined('APP_URL') ? APP_URL : "https://" . $_SERVER['HTTP_HOST'];
    $logoHtml = '<div style="font-size: 28px; font-weight: 800; color: #4f46e5; margin-bottom: 15px;">AIKAAA CRM</div>';
    
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to Aikaaa CRM</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: \'Inter\', Helvetica, Arial, sans-serif; background-color: #f4f7fa; color: #333333;">
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f7fa; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05); overflow: hidden;">
                        <!-- Header -->
                        <tr>
                            <td align="center" style="padding: 40px 30px 20px 30px; background-color: #ffffff;">
                                ' . $logoHtml . '
                                <h1 style="margin: 0; color: #1e293b; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">Welcome to Aikaaa CRM!</h1>
                            </td>
                        </tr>
                        
                        <!-- Body -->
                        <tr>
                            <td style="padding: 20px 40px 30px 40px; background-color: #ffffff;">
                                <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #475569;">
                                    Dear <strong>' . htmlspecialchars($adminName) . '</strong>,
                                </p>
                                <p style="margin: 0 0 25px 0; font-size: 16px; line-height: 1.6; color: #475569;">
                                    Your company account has been successfully created. You can now log in to your administrative dashboard and begin managing your business.
                                </p>
                                
                                <!-- Credentials Box -->
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 30px;">
                                    <tr>
                                        <td style="padding: 25px;">
                                            <h3 style="margin: 0 0 15px 0; color: #0f172a; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Your Login Credentials</h3>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                <tr>
                                                    <td width="100" style="padding: 8px 0; color: #64748b; font-size: 15px; font-weight: 500;">Email:</td>
                                                    <td style="padding: 8px 0; color: #0f172a; font-size: 15px; font-weight: 600;">' . htmlspecialchars($adminEmail) . '</td>
                                                </tr>
                                                <tr>
                                                    <td width="100" style="padding: 8px 0; color: #64748b; font-size: 15px; font-weight: 500;">Password:</td>
                                                    <td style="padding: 8px 0; color: #0f172a; font-size: 15px; font-weight: 600;">' . htmlspecialchars($adminPassword) . '</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- Call to Action -->
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td align="center">
                                            <a href="' . htmlspecialchars($loginUrl) . '" style="display: inline-block; padding: 14px 30px; background-color: #4f46e5; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.25);">Log In Now</a>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p style="margin: 30px 0 0 0; font-size: 15px; line-height: 1.6; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                                    If you experience any issues accessing your account, please contact our support team.
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td align="center" style="padding: 20px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0;">
                                <p style="margin: 0; font-size: 14px; color: #94a3b8;">
                                    &copy; ' . date('Y') . ' Aikaaa CRM. All rights reserved.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Aikaaa CRM <noreply@" . parse_url(APP_URL, PHP_URL_HOST) . ">\r\n";
    mail($adminEmail, $subject, $htmlBody, $headers);

    echo json_encode(['success' => true, 'message' => 'Company registered successfully. Login credentials have been sent to your email.']);
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Company registration failed: ' . $e->getMessage()]);
}
