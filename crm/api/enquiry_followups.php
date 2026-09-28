<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Auth;
use Core\Database;

if (!Auth::check() || !Auth::isSuperAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Auto-create table if not exists
$conn->exec("CREATE TABLE IF NOT EXISTS `enquiry_followups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `enquiry_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `remark` text NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($method === 'GET') {
    $enquiry_id = $_GET['enquiry_id'] ?? null;
    if (!$enquiry_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Enquiry ID required']);
        exit;
    }

    $followups = $db->fetchAll(
        "SELECT f.*, u.name as user_name FROM enquiry_followups f 
         LEFT JOIN users u ON f.user_id = u.id 
         WHERE f.enquiry_id = ? ORDER BY f.created_at DESC", 
        [$enquiry_id]
    );
    echo json_encode($followups);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['enquiry_id']) || empty($input['remark'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Enquiry ID and Remark are required']);
        exit;
    }

    try {
        $db->insert('enquiry_followups', [
            'enquiry_id' => $input['enquiry_id'],
            'user_id' => Auth::userId(),
            'remark' => $input['remark']
        ]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
