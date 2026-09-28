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

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $isExecutive = Auth::isExecutive();
    $executiveEmployeeId = Auth::employeeId();
    $userId = Auth::userId();

    $whereClause = "l.company_id = ? AND (l.assigned_employee_id IS NOT NULL OR l.status = 'won') AND l.status != 'lost'";
    $params = [$company_id];

    if ($isExecutive) {
        $whereClause .= " AND (l.assigned_employee_id = ? OR l.assigned_to = ?)";
        $params[] = $executiveEmployeeId ?: 0;
        $params[] = $userId;
    }

    $month = $_GET['month'] ?? '';
    $year = $_GET['year'] ?? '';
    if (!empty($month)) {
        $whereClause .= " AND l.month = ?";
        $params[] = $month;
    }
    if (!empty($year)) {
        $whereClause .= " AND l.year = ?";
        $params[] = $year;
    }

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $whereClause .= " AND (l.name LIKE ? OR l.mobile LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql = "SELECT l.*, 
                   e.name as assigned_employee_name,
                   u.name as assigned_to_name,
                   reqs.requirement_names
            FROM leads l
            LEFT JOIN employees e ON l.assigned_employee_id = e.id
            LEFT JOIN users u ON l.assigned_to = u.id
            LEFT JOIN (
                SELECT lr.lead_id, GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as requirement_names
                FROM lead_requirements lr
                JOIN requirements r ON lr.requirement_id = r.id
                GROUP BY lr.lead_id
            ) reqs ON l.id = reqs.lead_id
            WHERE $whereClause
            ORDER BY l.id DESC";

    $tasks = $db->fetchAll($sql, $params);
    echo json_encode($tasks);
    exit;
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null; // This is the lead ID
    $status = $input['status'] ?? null;
    $statusMap = [
        'pending' => 'not_started',
        'done' => 'work_done',
        'delay' => 'work_pending',
    ];
    $status = $statusMap[$status] ?? $status;
    $validStatuses = ['not_started', 'work_in_progress', 'work_pending', 'work_done'];

    if (!$id || !$status || !in_array($status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid ID and Status required.']);
        exit;
    }

    if ($status === 'work_pending' && trim($input['remark'] ?? '') === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Pending reason is required.']);
        exit;
    }

    try {
        if (Auth::isExecutive()) {
            $allowed = $db->fetchOne(
                "SELECT id FROM leads WHERE id = ? AND company_id = ? AND assigned_employee_id = ?",
                [$id, $company_id, Auth::employeeId() ?: 0]
            );

            if (!$allowed) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
        }

        $completed_at = null;
        $delivery_date = !empty($input['expected_delivery_date']) ? $input['expected_delivery_date'] : null;
        
        if ($status === 'work_done') {
            $completed_at = date('Y-m-d H:i:s');
            $db->query("UPDATE leads SET task_status = ?, task_completed_at = ?, expected_delivery_date = ? WHERE id = ? AND company_id = ?", [$status, $completed_at, $delivery_date, $id, $company_id]);
        } else {
            $db->query("UPDATE leads SET task_status = ?, task_completed_at = NULL, expected_delivery_date = ? WHERE id = ? AND company_id = ?", [$status, $delivery_date, $id, $company_id]);
        }

        // Add to history (lead followups)
        $statusLabel = [
            'not_started' => 'NOT STARTED',
            'work_in_progress' => 'WORK IN PROGRESS',
            'work_pending' => 'WORK PENDING',
            'work_done' => 'WORK DONE',
        ][$status];
        $remark = $input['remark'] ?? 'Status updated to ' . $statusLabel;
        $db->insert('lead_followups', [
            'lead_id' => $id,
            'company_id' => $company_id,
            'user_id' => Auth::user()['id'],
            'follow_up_date' => date('Y-m-d'),
            'follow_up_time' => date('H:i:s'),
            'remark' => "Task Status changed to " . $statusLabel . ": " . $remark,
            'status' => 'completed'
        ]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
