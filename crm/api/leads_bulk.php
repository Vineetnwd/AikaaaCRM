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

use Core\Database;
use Core\Auth;
use Core\Lead;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$leadModel = new Lead();
$company_id = Auth::companyId();
$action = $_GET['action'] ?? '';

try {
    if ($action === 'template') {
        // Export blank template as requested
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="leads_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Mobile', 'Email', 'Address', 'Status', 'Category', 'Source', 'Requirement', 'Deal Value', 'Task Status']);
        fclose($output);
        exit;
    }

    if ($action === 'export') {
        $scope = $_GET['scope'] ?? '';
        $all = (isset($_GET['all']) && ($_GET['all'] == '1' || $_GET['all'] === 'true')) || $scope === 'all';
        $format = strtolower($_GET['format'] ?? 'csv');
        $rawIds = $_GET['ids'] ?? '';
        $ids = [];
        if (!empty($rawIds)) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $rawIds))));
        }

        $isExecutive = Auth::isExecutive();
        $userId = Auth::userId();
        $empId = Auth::employeeId() ?: $userId;

        $query = "SELECT l.*, 
                         u.name as assigned_to_name, 
                         e.name as assigned_employee_name,
                         reqs.requirement_names
                  FROM leads l
                  LEFT JOIN users u ON l.assigned_to = u.id
                  LEFT JOIN employees e ON l.assigned_employee_id = e.id
                  LEFT JOIN (
                      SELECT lr.lead_id, GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as requirement_names
                      FROM lead_requirements lr
                      JOIN requirements r ON lr.requirement_id = r.id
                      GROUP BY lr.lead_id
                  ) reqs ON l.id = reqs.lead_id
                  WHERE l.company_id = ?";
        $params = [$company_id];

        if ($isExecutive) {
            $query .= " AND (l.assigned_to = ? OR l.assigned_employee_id = ?)";
            $params[] = $userId;
            $params[] = $empId;
        }

        if ($scope === 'selected' && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query .= " AND l.id IN ($placeholders)";
            foreach ($ids as $id) {
                $params[] = $id;
            }
            $fileSuffix = "selected_" . date('Y-m-d');
        } elseif ($all) {
            $fileSuffix = "all_" . date('Y-m-d');
        } else {
            $month = $_GET['month'] ?? date('n');
            $year = $_GET['year'] ?? date('Y');
            $query .= " AND l.month = ? AND l.year = ?";
            $params[] = (int)$month;
            $params[] = (int)$year;
            $fileSuffix = "{$year}_{$month}";
        }

        $query .= " ORDER BY l.id DESC";
        $leads = $db->fetchAll($query, $params);

        $headers = ['Name', 'Mobile', 'Email', 'Address', 'Status', 'Category', 'Source', 'Requirement', 'Deal Value', 'Task Status', 'Lead Manager', 'Task Staff', 'Created At'];
        $rows = [];
        foreach ($leads as $lead) {
            $req = !empty($lead['requirement']) ? $lead['requirement'] : ($lead['requirement_names'] ?? '');
            $rows[] = [
                $lead['name'] ?? '',
                $lead['mobile'] ?? '',
                $lead['email'] ?? '',
                $lead['address'] ?? '',
                ucfirst($lead['status'] ?? ''),
                strtoupper($lead['category'] ?? ''),
                ucfirst($lead['source'] ?? ''),
                $req,
                $lead['deal_value'] ?? '0.00',
                ucwords(str_replace('_', ' ', $lead['task_status'] ?? '')),
                $lead['assigned_to_name'] ?? 'Unassigned',
                $lead['assigned_employee_name'] ?? 'Unassigned',
                $lead['created_at'] ?? ''
            ];
        }

        if ($format === 'xlsx') {
            require_once __DIR__ . '/../core/SimpleXLSXWriter.php';
            $filename = "leads_export_{$fileSuffix}.xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            echo \Core\SimpleXLSXWriter::create($headers, $rows, 'Leads');
            exit;
        } else {
            $filename = "leads_export_{$fileSuffix}.csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
    }

    if ($action === 'export_tasks') {
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        
        // Tasks are leads where status is NOT lost and (assigned or won)
        $query = "SELECT l.*, e.name as assigned_to_name 
                  FROM leads l 
                  LEFT JOIN employees e ON l.assigned_employee_id = e.id 
                  WHERE l.company_id = ? 
                  AND (l.assigned_employee_id IS NOT NULL OR l.status = 'won') 
                  AND l.status != 'lost'
                  AND l.month = ? AND l.year = ? 
                  ORDER BY l.id DESC";
        $tasks = $db->fetchAll($query, [$company_id, (int)$month, (int)$year]);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tasks_export_' . $year . '_' . $month . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Client Name', 'Mobile', 'Service/Requirement', 'Task Status', 'Assigned To', 'Expected Delivery', 'Deal Value', 'Created At']);
        
        foreach ($tasks as $task) {
            fputcsv($output, [
                $task['name'],
                $task['mobile'],
                $task['requirement'],
                $task['task_status'],
                $task['assigned_to_name'] ?: 'Unassigned',
                $task['expected_delivery_date'],
                $task['deal_value'],
                $task['created_at']
            ]);
        }
        fclose($output);
        exit;
    }

    if ($action === 'import') {
        if (!isset($_FILES['file'])) {
            throw new Exception('No file uploaded');
        }

        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');
        // Create a date string for the chosen month/year
        // We'll use the 1st of the month at noon to avoid timezone edge cases
        $createdAt = "$year-$month-01 12:00:00";

        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        // Skip header
        $header = fgetcsv($handle);
        
        $successCount = 0;
        $errorCount = 0;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (empty($data[0]) || empty($data[1])) {
                $errorCount++;
                continue;
            }

            try {
                $payload = [
                    'name' => $data[0],
                    'mobile' => preg_replace('/[^0-9]/', '', $data[1]),
                    'email' => $data[2] ?? '',
                    'address' => $data[3] ?? '',
                    'status' => $data[4] ?? 'new',
                    'category' => $data[5] ?? '',
                    'source' => $data[6] ?? 'direct',
                    'requirement' => $data[7] ?? '',
                    'deal_value' => $data[8] ?? 0.00,
                    'task_status' => $data[9] ?? 'not_started',
                    'created_at' => $createdAt,
                    'month' => $month,
                    'year' => $year
                ];

                // Validate mobile
                if (strlen($payload['mobile']) !== 10) {
                    $errorCount++;
                    continue;
                }
                
                $id = $leadModel->create($payload);
                if ($id) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                $errorCount++;
            }
        }
        
        fclose($handle);
        echo json_encode(['success' => true, 'imported' => $successCount, 'skipped' => $errorCount]);
        exit;
    }

    if ($action === 'bulk_set_category') {
        if (Auth::isExecutive()) {
            throw new Exception('Executives are not authorized to perform bulk category updates.');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];
        $category = $input['category'] ?? '';
        
        if (empty($ids)) {
            throw new Exception('No items selected');
        }

        foreach ($ids as $id) {
            $leadModel->update($id, ['category' => $category]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'bulk_assign') {
        if (Auth::isExecutive()) {
            throw new Exception('Executives are not authorized to perform bulk assignments.');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $ids = $input['ids'] ?? [];
        $employee_id = $input['employee_id'] ?? null;
        $user_id = $input['user_id'] ?? null;
        $userId = Auth::userId();
        
        if (empty($ids)) {
            throw new Exception('No items selected');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        if ($employee_id !== null) {
            $params = array_merge([$employee_id, $company_id], $ids);
            $db->query("UPDATE leads SET assigned_employee_id = ? WHERE company_id = ? AND id IN ($placeholders)", $params);
            
            // Get employee name for logging
            $emp = $db->fetchOne("SELECT name FROM employees WHERE id = ? AND company_id = ?", [$employee_id, $company_id]);
            $empName = $emp ? $emp['name'] : 'Unknown';
            
            foreach ($ids as $id) {
                $db->insert('lead_followups', [
                    'lead_id' => $id,
                    'company_id' => $company_id,
                    'user_id' => $userId,
                    'follow_up_date' => date('Y-m-d'),
                    'follow_up_time' => date('H:i:s'),
                    'remark' => "Task Bulk Reassigned to: " . $empName,
                    'status' => 'completed'
                ]);
            }
        }

        if ($user_id !== null) {
            $params = array_merge([$user_id, $company_id], $ids);
            $db->query("UPDATE leads SET assigned_to = ? WHERE company_id = ? AND id IN ($placeholders)", $params);
            
            // Get user name for logging
            $user = $db->fetchOne("SELECT name FROM users WHERE id = ? AND company_id = ?", [$user_id, $company_id]);
            $userName = $user ? $user['name'] : 'Unknown';
            
            foreach ($ids as $id) {
                $db->insert('lead_followups', [
                    'lead_id' => $id,
                    'company_id' => $company_id,
                    'user_id' => $userId,
                    'follow_up_date' => date('Y-m-d'),
                    'follow_up_time' => date('H:i:s'),
                    'remark' => "Lead Ownership Bulk Reassigned to: " . $userName,
                    'status' => 'completed'
                ]);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'bulk_transfer') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids = $input['ids'] ?? [];
        $targetEmployeeId = intval($input['target_employee_id'] ?? 0);
        $remark = trim($input['remark'] ?? '');

        if (empty($ids)) {
            throw new Exception('No leads selected');
        }

        if (!$targetEmployeeId) {
            throw new Exception('Target employee is required');
        }

        $transferredCount = 0;
        $failedCount = 0;

        foreach ($ids as $id) {
            try {
                $leadModel->transfer(intval($id), $targetEmployeeId, $remark);
                $transferredCount++;
            } catch (Exception $ex) {
                $failedCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'transferred' => $transferredCount,
            'failed' => $failedCount
        ]);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
