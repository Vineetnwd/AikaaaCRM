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
    $subject = "Your Aikaa CRM Account Credentials";
    $message = "Hello $adminName,\n\nYour company account has been successfully created.\n\nHere are your login credentials:\nEmail: $adminEmail\nPassword: $adminPassword\n\nYou can log in here: $loginUrl\n\nBest regards,\nAikaa CRM Team";
    $headers = "From: noreply@" . parse_url(APP_URL, PHP_URL_HOST) . "\r\n";
    mail($adminEmail, $subject, $message, $headers);

    echo json_encode(['success' => true, 'message' => 'Company registered successfully. Login credentials have been sent to your email.']);
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Company registration failed: ' . $e->getMessage()]);
}
