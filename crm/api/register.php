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
require_once __DIR__ . '/../core/Employee.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Since we are using FormData (multipart/form-data), we use $_POST and $_FILES
$companyId = intval($_POST['company_id'] ?? 0);
$adminName = trim($_POST['admin_name'] ?? '');
$adminEmail = trim($_POST['admin_email'] ?? '');
$adminMobile = trim($_POST['mobile'] ?? '');
$empType = $_POST['type'] ?? 'permanent';
$adminPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);

if (empty($companyId) || empty($adminName) || empty($adminEmail) || empty($adminMobile)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

$db = Database::getInstance();

// Check if email or mobile already exists
$existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? OR mobile = ?", [$adminEmail, $adminMobile]);
if ($existingUser) {
    http_response_code(400);
    echo json_encode(['error' => 'Email or Mobile already registered']);
    exit;
}

// Handle Photo Upload
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

    // 1. Create Employee
    $empData = [
        'company_id' => $companyId,
        'name' => $adminName,
        'email' => $adminEmail,
        'mobile' => $adminMobile,
        'type' => $empType,
        'photo' => $photoPath,
        'designation' => 'Executive',
        'status' => 'inactive', // Default to inactive as requested
        'created_at' => date('Y-m-d H:i:s')
    ];
    $empId = $db->insert('employees', $empData);

    // 2. Create User account (default to executive for employee signup)
    $userData = [
        'company_id' => $companyId,
        'name' => $adminName,
        'email' => $adminEmail,
        'mobile' => $adminMobile, // Added mobile to users table
        'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
        'role' => 'executive',
        'type' => $empType,
        'status' => 'inactive', // Default to inactive as requested
        'photo' => $photoPath,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Link to employee (both columns used in different parts of the system)
    $userData['employee_id'] = $empId;
    $userData['emp_id'] = $empId;

    $db->insert('users', $userData);

    $conn->commit();

    // Send email with credentials
    $loginUrl = APP_URL . '/public/index.php/login';
    $subject = "Your Aikaa CRM Employee Credentials";
    $message = "Hello $adminName,\n\nYour account has been successfully created and is pending approval.\n\nOnce approved, you can log in with the following credentials:\nEmail: $adminEmail\nPassword: $adminPassword\n\nYou can log in here: $loginUrl\n\nBest regards,\nAikaa CRM Team";
    $headers = "From: noreply@" . parse_url(APP_URL, PHP_URL_HOST) . "\r\n";
    mail($adminEmail, $subject, $message, $headers);

    echo json_encode(['success' => true, 'message' => 'Registration successful. Login credentials have been sent to your email. Your account is pending approval by admin.']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
}
