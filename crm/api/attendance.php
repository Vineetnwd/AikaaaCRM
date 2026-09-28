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
$employee_id = Auth::employeeId();

if (!$employee_id) {
    echo json_encode(['error' => 'This account is not linked to an employee record.']);
    exit;
}

$action = $_GET['action'] ?? '';
$today = date('Y-m-d');

try {
    if ($action === 'status') {
        $log = $db->fetchOne("
            SELECT * FROM attendance_logs 
            WHERE employee_id = ? AND work_date = ? 
            ORDER BY id DESC LIMIT 1
        ", [$employee_id, $today]);

        echo json_encode([
            'checked_in' => ($log && !$log['check_out']),
            'log' => $log
        ]);
        exit;
    }

    if ($action === 'check_in') {
        // Check if any record exists for today
        $existing = $db->fetchOne("
            SELECT id, check_out FROM attendance_logs 
            WHERE employee_id = ? AND work_date = ?
        ", [$employee_id, $today]);

        if ($existing) {
            if ($existing['check_out']) {
                echo json_encode(['success' => false, 'error' => 'You have already completed your attendance for today.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'You are already checked in.']);
            }
            exit;
        }

        $db->query("
            INSERT INTO attendance_logs (company_id, employee_id, work_date, check_in) 
            VALUES (?, ?, ?, NOW())
        ", [$company_id, $employee_id, $today]);

        $db->query("UPDATE employees SET attendance = 'Present' WHERE id = ?", [$employee_id]);

        echo json_encode(['success' => true, 'message' => 'Checked in successfully']);
        exit;
    }

    if ($action === 'check_out') {
        $log = $db->fetchOne("
            SELECT id FROM attendance_logs 
            WHERE employee_id = ? AND work_date = ? AND check_out IS NULL
            ORDER BY id DESC LIMIT 1
        ", [$employee_id, $today]);

        if (!$log) {
            echo json_encode(['success' => false, 'error' => 'Not checked in']);
            exit;
        }

        $db->query("
            UPDATE attendance_logs SET check_out = NOW() 
            WHERE id = ?
        ", [$log['id']]);

        $db->query("UPDATE employees SET attendance = 'Absent' WHERE id = ?", [$employee_id]);

        echo json_encode(['success' => true, 'message' => 'Checked out successfully']);
        exit;
    }

    if ($action === 'report') {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        $emp_id = $_GET['employee_id'] ?? null;

        if (!$is_admin) {
            $emp_id = $employee_id; // Forced to self
        }

        $query = "SELECT l.*, e.name as employee_name, e.employee_id as emp_code 
                  FROM attendance_logs l 
                  JOIN employees e ON l.employee_id = e.id 
                  WHERE l.company_id = ? AND MONTH(l.work_date) = ? AND YEAR(l.work_date) = ?";
        $params = [$company_id, $month, $year];

        if ($emp_id) {
            $query .= " AND l.employee_id = ?";
            $params[] = $emp_id;
        }

        $query .= " ORDER BY l.work_date DESC, l.check_in ASC";
        $logs = $db->fetchAll($query, $params);

        echo json_encode($logs);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
