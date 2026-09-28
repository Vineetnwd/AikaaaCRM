<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Auth;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = \Core\Database::getInstance();
$company_id = Auth::companyId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? null;

    try {
        if ($action === 'payouts') {
            $payouts = $db->fetchAll("
                SELECT p.*, e.name as employee_name, e.employee_id as emp_code
                FROM employee_commission_payouts p
                JOIN employees e ON p.employee_id = e.id
                WHERE p.company_id = ?
                ORDER BY p.payout_date DESC
            ", [$company_id]);
            echo json_encode($payouts);
            exit;
        }

        if ($action === 'pending') {
            $pending = $db->fetchAll("
                SELECT p.*, e.name as employee_name, e.employee_id as emp_code
                FROM employee_commission_payouts p
                JOIN employees e ON p.employee_id = e.id
                WHERE p.company_id = ? AND p.status = 'pending'
                ORDER BY p.payout_date ASC
            ", [$company_id]);
            echo json_encode($pending);
            exit;
        }

        if ($action === 'balances') {
            $employees = $db->fetchAll("
                SELECT id, name, employee_id, total_commission_earned, total_commission_paid,
                       (total_commission_earned - total_commission_paid) as balance
                FROM employees
                WHERE company_id = ? AND total_commission_earned > 0
                ORDER BY balance DESC
            ", [$company_id]);
            echo json_encode($employees);
            exit;
        }

        if ($action === 'stats') {
            $stats = $db->fetchOne("
                SELECT 
                    COALESCE(SUM(total_commission_earned), 0) as total_earned,
                    COALESCE(SUM(total_commission_paid), 0) as total_paid,
                    COALESCE(SUM(total_commission_earned - total_commission_paid), 0) as total_balance
                FROM employees
                WHERE company_id = ?
            ", [$company_id]);
            echo json_encode($stats);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $postAction = $input['action'] ?? 'record';

    if ($postAction === 'approve' || $postAction === 'reject') {
        $payout_id = intval($input['payout_id'] ?? 0);
        if (!$payout_id) {
            http_response_code(400); echo json_encode(['error' => 'Payout ID required']); exit;
        }

        $payout = $db->fetchOne("SELECT * FROM employee_commission_payouts WHERE id = ? AND company_id = ? AND status = 'pending'", [$payout_id, $company_id]);
        if (!$payout) {
            http_response_code(404); echo json_encode(['error' => 'Pending payout not found']); exit;
        }

        try {
            if ($postAction === 'approve') {
                $db->update('employee_commission_payouts', ['status' => 'approved'], "id = ?", [$payout_id]);
                $db->query("UPDATE employees SET total_commission_paid = total_commission_paid + ? WHERE id = ? AND company_id = ?", [$payout['amount'], $payout['employee_id'], $company_id]);
            } else {
                $db->update('employee_commission_payouts', ['status' => 'rejected'], "id = ?", [$payout_id]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($postAction === 'cancel_earning') {
        $earning_id = intval($input['earning_id'] ?? 0);
        $lead_id = intval($input['lead_id'] ?? 0);
        $emp_id = intval($input['emp_id'] ?? 0);
        $type = $input['type'] ?? '';

        if (!$emp_id) {
            http_response_code(400); echo json_encode(['error' => 'Employee ID required']); exit;
        }

        try {
            $amount = 0;
            if ($type === 'earning' && $earning_id) {
                $earning = $db->fetchOne("SELECT amount FROM employee_commission_earnings WHERE id = ? AND company_id = ?", [$earning_id, $company_id]);
                if ($earning) {
                    $amount = floatval($earning['amount']);
                    $db->query("DELETE FROM employee_commission_earnings WHERE id = ?", [$earning_id]);
                }
            } elseif ($type === 'legacy' && $lead_id) {
                $lead = $db->fetchOne("SELECT commission_amount FROM leads WHERE id = ? AND company_id = ?", [$lead_id, $company_id]);
                if ($lead) {
                    $amount = floatval($lead['commission_amount']);
                    $db->update('leads', ['commission_amount' => 0], "id = ?", [$lead_id]);
                }
            }

            if ($amount > 0) {
                $db->query("UPDATE employees SET total_commission_earned = GREATEST(0, total_commission_earned - ?) WHERE id = ? AND company_id = ?", [$amount, $emp_id, $company_id]);
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($postAction === 'cancel_payout') {
        $payout_id = intval($input['payout_id'] ?? 0);
        if (!$payout_id) {
            http_response_code(400); echo json_encode(['error' => 'Payout ID required']); exit;
        }

        $payout = $db->fetchOne("SELECT * FROM employee_commission_payouts WHERE id = ? AND company_id = ? AND status = 'approved'", [$payout_id, $company_id]);
        if (!$payout) {
            http_response_code(404); echo json_encode(['error' => 'Approved payout not found']); exit;
        }

        try {
            $db->update('employee_commission_payouts', ['status' => 'cancelled'], "id = ?", [$payout_id]);
            $db->query("UPDATE employees SET total_commission_paid = GREATEST(0, total_commission_paid - ?) WHERE id = ? AND company_id = ?", [$payout['amount'], $payout['employee_id'], $company_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($postAction === 'request') {
        $amount = floatval($input['amount'] ?? 0);
        $emp_id = intval($input['employee_id'] ?? 0);
        
        if ($amount <= 0 || !$emp_id) {
            http_response_code(400); echo json_encode(['error' => 'Employee and positive Amount are required']); exit;
        }

        $existing = $db->fetchOne("SELECT id FROM employee_commission_payouts WHERE employee_id = ? AND company_id = ? AND status = 'pending'", [$emp_id, $company_id]);
        if ($existing) {
             http_response_code(400); echo json_encode(['error' => 'You already have a pending payout request.']); exit;
        }

        $employee = $db->fetchOne("SELECT (total_commission_earned - total_commission_paid) as balance FROM employees WHERE id = ? AND company_id = ?", [$emp_id, $company_id]);
        if (!$employee || $employee['balance'] < $amount) {
             http_response_code(400); echo json_encode(['error' => 'Requested amount exceeds pending balance.']); exit;
        }

        try {
            $db->insert('employee_commission_payouts', [
                'company_id' => $company_id,
                'employee_id' => $emp_id,
                'amount' => $amount,
                'payout_date' => date('Y-m-d'),
                'status' => 'pending',
                'reference_note' => $input['reference_note'] ?? 'Requested by employee'
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if (empty($input['employee_id']) || empty($input['amount']) || empty($input['payout_date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Employee, Amount, and Date are required']);
        exit;
    }

    try {
        $amount = floatval($input['amount']);
        $emp_id = intval($input['employee_id']);

        $db->insert('employee_commission_payouts', [
            'company_id' => $company_id,
            'employee_id' => $emp_id,
            'amount' => $amount,
            'payout_date' => $input['payout_date'],
            'status' => 'approved',
            'reference_note' => $input['reference_note'] ?? null
        ]);

        $db->query("
            UPDATE employees 
            SET total_commission_paid = total_commission_paid + ? 
            WHERE id = ? AND company_id = ?
        ", [$amount, $emp_id, $company_id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
