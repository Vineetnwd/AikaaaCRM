<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Lead.php';
require_once __DIR__ . '/../core/Requirement.php';

use Core\Auth;
use Core\Lead;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$leadModel = new Lead();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        $action = $_GET['action'] ?? null;

        if ($action === 'check_duplicate') {
            $mobile = trim($_GET['mobile'] ?? '');
            $requirement = trim($_GET['requirement'] ?? '');
            $taskStatus = trim($_GET['task_status'] ?? '');
            $excludeId = intval($_GET['exclude_id'] ?? 0) ?: null;

            if ($mobile) {
                // 1. Check for general match (for auto-fill)
                $anyMatch = $leadModel->findByMobile($mobile, $excludeId);

                // 2. Check for exact duplicate (mobile + task + task_status)
                $isDuplicate = false;
                $duplicateLead = null;

                if ($anyMatch) {
                    $exactMatch = $leadModel->findByMobile($mobile, $excludeId, $requirement, $taskStatus);
                    if ($exactMatch) {
                        $isDuplicate = true;
                        $duplicateLead = $exactMatch;
                    }
                }

                echo json_encode([
                    'match' => (bool) $anyMatch,
                    'lead' => $anyMatch ?: null, // Return lead data for auto-fill
                    'duplicate' => $isDuplicate,
                    'duplicate_lead' => $duplicateLead
                ]);
            } else {
                echo json_encode(['match' => false, 'duplicate' => false]);
            }
            exit;
        }

        if ($id) {
            $lead = $leadModel->find(intval($id));
            if ($lead && Auth::isExecutive() && !$leadModel->canAccess(intval($id))) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            if ($lead) {
                $reqModel = new Core\Requirement();
                $lead['services'] = $reqModel->getLeadRequirements($id);
            }
            echo json_encode($lead);
        } else {
            $filters = [];
            if (isset($_GET['month']))
                $filters['month'] = $_GET['month'];
            if (isset($_GET['year']))
                $filters['year'] = $_GET['year'];
            if (isset($_GET['category']))
                $filters['category'] = $_GET['category'];
            if (isset($_GET['source']))
                $filters['source'] = $_GET['source'];
            if (isset($_GET['search']))
                $filters['search'] = $_GET['search'];
            if (isset($_GET['all']))
                $filters['all'] = $_GET['all'];
            if (isset($_GET['transfer']))
                $filters['transfer'] = $_GET['transfer'];

            if (Auth::isExecutive()) {
                $filters['user_id'] = Auth::userId();
                $employeeId = Auth::employeeId();
                if ($employeeId) {
                    $filters['employee_id'] = $employeeId;
                }
            }
            echo json_encode($leadModel->all($filters));
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $_GET['action'] ?? ($input['action'] ?? null);

        if ($action === 'transfer') {
            $leadId = intval($input['lead_id'] ?? 0);
            $targetEmployeeId = intval($input['target_employee_id'] ?? 0);
            $remark = trim($input['remark'] ?? '');

            if (!$leadId || !$targetEmployeeId) {
                http_response_code(400);
                echo json_encode(['error' => 'Lead ID and Target Employee ID are required']);
                exit;
            }

            $leadModel->transfer($leadId, $targetEmployeeId, $remark);
            echo json_encode(['success' => true, 'message' => 'Lead transferred successfully']);
            exit;
        }

        $input['mobile'] = preg_replace('/[^0-9]/', '', $input['mobile'] ?? '');
        if (!preg_match('/^[0-9]{10}$/', $input['mobile'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid 10-digit mobile number is required']);
            exit;
        }
        $input['name'] = trim($input['name'] ?? '') ?: 'NO NAME';

        if (!isset($input['status']))
            $input['status'] = 'new';
        if (!isset($input['month']))
            $input['month'] = date('n');
        if (!isset($input['year']))
            $input['year'] = date('Y');

        // Track who created the lead
        $input['created_by'] = Auth::userId();

        // Executive restriction: Cannot assign lead to others, auto-assign lead to self.
        // Task staff (assigned_employee_id) is NOT auto-assigned.
        if (Auth::isExecutive()) {
            $input['assigned_to'] = Auth::userId();
        }

        $id = $leadModel->create($input);
        echo json_encode(['success' => true, 'id' => $id]);

    } elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = intval($input['id'] ?? 0);

        if ($id) {
            // Permission check: Executive can only edit their own leads
            if (Auth::isExecutive() && !$leadModel->canAccess($id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }

            // Executive restriction: Can only edit basic details + category + status
            if (Auth::isExecutive()) {
                $allowedFields = ['name', 'mobile', 'email', 'address', 'requirement', 'requirement_ids', 'custom_services', 'category', 'status'];
                $input = array_intersect_key($input, array_flip($allowedFields));

                // Ensure they don't try to change assignment or advanced fields via API
                unset($input['assigned_to']);
                unset($input['assigned_employee_id']);
                unset($input['deal_value']);
                unset($input['commission_percent']);
                unset($input['task_status']);
            }

            unset($input['id']);
            $leadModel->update($id, $input);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Lead ID is required for update']);
        }
    } elseif ($method === 'DELETE') {
        if (Auth::isExecutive()) {
            http_response_code(403);
            echo json_encode(['error' => 'Executives cannot delete leads']);
            exit;
        }

        $id = intval($_GET['id'] ?? 0);
        if ($id) {
            $leadModel->delete($id);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Lead ID is required']);
        }
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
?>