<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Auth;
use Core\Database;

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        if (isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ? AND company_id = ?", [$id, $company_id]);

            if (!$customer) {
                echo json_encode(['error' => 'Customer not found']);
                exit;
            }

            $sql = "SELECT l.id, l.created_at, l.status, l.task_status, l.task_completed_at, l.expected_delivery_date, l.remark,
                (SELECT remark FROM lead_followups WHERE lead_id = l.id AND remark LIKE 'Task Status changed to %' ORDER BY created_at DESC LIMIT 1) as latest_task_remark,
                (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM requirements r JOIN lead_requirements lr ON r.id = lr.requirement_id WHERE lr.lead_id = l.id) as services,
                (SELECT SUM(total_amount) FROM invoices WHERE lead_id = l.id AND company_id = :comp1) as total_amount,
                (SELECT SUM(paid_amount) FROM invoices WHERE lead_id = l.id AND company_id = :comp2) as paid_amount,
                (SELECT SUM(due_amount) FROM invoices WHERE lead_id = l.id AND company_id = :comp3) as due_amount
                FROM leads l
                WHERE (l.customer_id = :cid OR l.mobile = :mob) AND l.company_id = :comp4
                ORDER BY l.created_at DESC";
            $history = $db->fetchAll($sql, [
                'comp1' => $company_id,
                'comp2' => $company_id,
                'comp3' => $company_id,
                'cid' => $id,
                'mob' => $customer['mobile'],
                'comp4' => $company_id
            ]);

            // Process remarks to strip prefix
            foreach ($history as &$item) {
                if (!empty($item['latest_task_remark'])) {
                    $item['remark'] = preg_replace('/^Task Status changed to [^:]+:\s*/i', '', $item['latest_task_remark']);
                }
            }

            $customer['history'] = $history;
            echo json_encode($customer);
        } else {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit_val = $_GET['limit'] ?? '15';
            $limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
            $offset = ($page - 1) * $limit;
            
            $search = $_GET['search'] ?? '';
            $searchCond = '';
            if (!empty($search)) {
                $searchCond = " AND (c.name LIKE :search1 OR c.mobile LIKE :search2 OR c.email LIKE :search3)";
            }

            if (Auth::isExecutive()) {
                $userId = Auth::userId();
                $empId = Auth::employeeId();
                $baseSql = "FROM customers c
                    WHERE c.company_id = :comp6
                    $searchCond
                    AND EXISTS (
                        SELECT 1 FROM leads l 
                        WHERE (l.customer_id = c.id OR (l.mobile != '' AND l.mobile COLLATE utf8mb4_unicode_ci = c.mobile COLLATE utf8mb4_unicode_ci)) 
                        AND l.company_id = :comp7 
                        AND (l.assigned_to = :uid OR l.assigned_employee_id = :eid)
                    )";
                
                $params = [
                    'comp6' => $company_id,
                    'comp7' => $company_id,
                    'uid' => $userId,
                    'eid' => $empId
                ];
            } else {
                $baseSql = "FROM customers c
                    WHERE c.company_id = :comp6 $searchCond";
                $params = [
                    'comp6' => $company_id
                ];
            }
            
            if (!empty($search)) {
                $params['search1'] = "%$search%";
                $params['search2'] = "%$search%";
                $params['search3'] = "%$search%";
            }
            
            $countSql = "SELECT COUNT(*) as total " . $baseSql;
            $total = $db->fetchOne($countSql, $params)['total'] ?? 0;
            $total_pages = max(1, ceil($total / $limit));

            $sql = "SELECT c.*, 
                (SELECT MAX(l.created_at) FROM leads l WHERE (l.customer_id = c.id OR (l.mobile != '' AND l.mobile COLLATE utf8mb4_unicode_ci = c.mobile COLLATE utf8mb4_unicode_ci)) AND l.company_id = :comp1) as last_lead_at,
                (SELECT COUNT(*) FROM leads l WHERE (l.customer_id = c.id OR (l.mobile != '' AND l.mobile COLLATE utf8mb4_unicode_ci = c.mobile COLLATE utf8mb4_unicode_ci)) AND l.company_id = :comp2) as lead_count,
                (SELECT COUNT(DISTINCT lr.requirement_id) FROM lead_requirements lr JOIN leads l ON lr.lead_id = l.id WHERE (l.customer_id = c.id OR (l.mobile != '' AND l.mobile COLLATE utf8mb4_unicode_ci = c.mobile COLLATE utf8mb4_unicode_ci)) AND l.company_id = :comp3) as service_count,
                (SELECT COALESCE(SUM(i.total_amount), 0) FROM invoices i JOIN leads l ON i.lead_id = l.id WHERE (l.customer_id = c.id OR (l.mobile != '' AND l.mobile COLLATE utf8mb4_unicode_ci = c.mobile COLLATE utf8mb4_unicode_ci)) AND l.company_id = :comp4 AND i.company_id = :comp5) as total_spent
                " . $baseSql . "
                ORDER BY last_lead_at DESC LIMIT $limit OFFSET $offset";
            
            $params['comp1'] = $company_id;
            $params['comp2'] = $company_id;
            $params['comp3'] = $company_id;
            $params['comp4'] = $company_id;
            $params['comp5'] = $company_id;

            $customers = $db->fetchAll($sql, $params);

            echo json_encode([
                'data' => $customers,
                'total_records' => $total,
                'total_pages' => $total_pages,
                'current_page' => $page
            ]);
        }
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
