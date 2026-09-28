<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$identifier = trim($input['identifier'] ?? $input['email'] ?? $input['mobile'] ?? '');
$password = $input['password'] ?? '';

if (empty($identifier) || empty($password)) {
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

$attempt = Auth::attemptLogin($identifier, $password);

if ($attempt['success']) {
    $user = Auth::user();
    $token = Auth::generateToken($user);
    $company = Auth::company();
    $employeeId = Auth::employeeId();
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => $user,
        'company' => $company,
        'employee_id' => $employeeId,
        'is_executive' => Auth::isExecutive(),
        'is_admin' => Auth::isAdmin(),
        'is_super_admin' => Auth::isSuperAdmin()
    ]);
} else {
    http_response_code(401);
    echo json_encode(['error' => $attempt['error'] ?? 'Invalid credentials or account inactive.']);
}
?>
