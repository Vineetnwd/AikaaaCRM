<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER DEBUGGING
 * Upload to: /crm/api/debug_token.php
 * Call: GET https://aikocrm.com/crm/api/debug_token.php
 * with Authorization: Bearer <your_token>
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Auth;
use Core\Database;

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

$result = [
    'auth_header_present' => !empty($authHeader),
    'auth_header_preview' => $authHeader ? substr($authHeader, 0, 30) . '...' : null,
];

if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $token = $matches[1];
    $parts = explode('.', $token);
    $result['token_parts_count'] = count($parts);

    if (count($parts) === 2) {
        $data = $parts[0];
        $sig  = $parts[1];
        $secret = defined('JWT_SECRET') ? JWT_SECRET : 'aikaa_crm_secret_key_2026';
        $expectedSig = hash_hmac('sha256', $data, $secret);
        $result['signature_valid'] = hash_equals($expectedSig, $sig);

        $payload = json_decode(base64_decode($data), true);
        $result['payload'] = $payload;

        if ($payload && !empty($payload['uid'])) {
            $db = Database::getInstance();
            $user = $db->fetchOne("SELECT id, name, email, role, status, company_id FROM users WHERE id = ? LIMIT 1", [$payload['uid']]);
            $result['user_found'] = !empty($user);
            $result['user'] = $user ?: null;
        }
    }
} else {
    $result['error'] = 'No Bearer token found in Authorization header';
}

$result['auth_check'] = Auth::check();
$result['current_user'] = Auth::user() ? ['id' => Auth::userId(), 'role' => Auth::role()] : null;

echo json_encode($result, JSON_PRETTY_PRINT);
