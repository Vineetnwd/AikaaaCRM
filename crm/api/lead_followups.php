<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Auth;
use Core\Database;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();
$method = $_SERVER['REQUEST_METHOD'];

function canAccessLead(Database $db, int $leadId, int $companyId): bool {
    if (!Auth::isExecutive()) {
        return true;
    }

    $lead = $db->fetchOne(
        "SELECT id FROM leads WHERE id = ? AND company_id = ? AND (assigned_to = ? OR assigned_employee_id = ?)",
        [$leadId, $companyId, Auth::userId(), Auth::employeeId() ?: 0]
    );

    return (bool)$lead;
}

if ($method === 'GET') {
    $lead_id = $_GET['lead_id'] ?? null;
    if (!$lead_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Lead ID required']);
        exit;
    }

    if (!canAccessLead($db, intval($lead_id), $company_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $followups = $db->fetchAll("SELECT f.*, u.name as user_name FROM lead_followups f LEFT JOIN users u ON f.user_id = u.id WHERE f.lead_id = ? AND f.company_id = ? ORDER BY f.created_at DESC", [$lead_id, $company_id]);
    echo json_encode($followups);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['lead_id']) || empty($input['follow_up_date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Lead ID and Date are required']);
        exit;
    }

    if (!canAccessLead($db, intval($input['lead_id']), $company_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    try {
        $db->insert('lead_followups', [
            'lead_id' => $input['lead_id'],
            'company_id' => $company_id,
            'user_id' => Auth::userId(), // Automatically lock to logged-in user
            'follow_up_date' => $input['follow_up_date'],
            'follow_up_time' => $input['follow_up_time'] ?? '10:00:00',
            'remark' => $input['remark'] ?? '',
            'call_status' => $input['call_status'] ?? 'connected',
            'status' => 'pending'
        ]);

        // Also update the main leads table with the LATEST follow_up_date for quick reference
        $db->query("UPDATE leads SET follow_up_date = ? WHERE id = ?", [$input['follow_up_date'], $input['lead_id']]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
