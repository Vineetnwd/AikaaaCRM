<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Database;
use Core\Auth;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();
$employee_id = $_GET['employee_id'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if (!$employee_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee ID required']);
    exit;
}

try {
    $sql = "SELECT 
                l.id,
                l.name, 
                COALESCE(
                    (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM requirements r JOIN lead_requirements lr ON r.id = lr.requirement_id WHERE lr.lead_id = l.id),
                    l.requirement
                ) as requirement, 
                l.task_status, 
                COALESCE(l.expected_delivery_date, l.follow_up_date) as due_date,
                (SELECT remark FROM lead_followups WHERE lead_id = l.id ORDER BY created_at DESC LIMIT 1) as latest_remark
            FROM leads l
            WHERE l.assigned_employee_id = ? 
              AND l.company_id = ?
              AND l.status != 'lost'";

    $params = [$employee_id, $company_id];

    if ($start_date && $end_date) {
        $sql .= " AND l.created_at BETWEEN ? AND ?";
        $params[] = $start_date . ' 00:00:00';
        $params[] = $end_date . ' 23:59:59';
    }

    $sql .= " ORDER BY l.created_at DESC";

    $data = $db->fetchAll($sql, $params);

    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
