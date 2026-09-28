<?php
header('Content-Type: application/json');
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "FATAL EXCEPTION: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine()]);
    exit;
});
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "SHUTDOWN ERROR: " . $error['message'] . " at " . $error['file'] . ":" . $error['line']]);
        exit;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('Core\Auth')) {
    require_once __DIR__ . '/../config/config.php';
    spl_autoload_register(function ($class) {
        $prefix = 'Core\\';
        $base_dir = __DIR__ . '/../core/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) require $file;
    });
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Invoice.php';

use Core\Auth;
use Core\Invoice;

function creditCommissionForPayment($invoice_id, $payment_id, $amountPaid)
{
    $db = Core\Database::getInstance();
    $company_id = Auth::companyId();

    // Fetch fresh invoice data
    $inv = $db->fetchOne("SELECT total_amount, paid_amount, lead_id, subtotal, invoice_date FROM invoices WHERE id = ?", [$invoice_id]);
    if (!$inv || $inv['total_amount'] <= 0)
        return;

    $lead = $db->fetchOne(
        "SELECT l.assigned_to, l.commission_percent 
         FROM leads l WHERE l.id = ? AND l.company_id = ?",
        [$inv['lead_id'], $company_id]
    );

    if ($lead && intval($lead['assigned_to']) > 0) {
        $assignedToUser = intval($lead['assigned_to']);
        $winner = $db->fetchOne("SELECT email FROM users WHERE id = ?", [$assignedToUser]);
        if ($winner) {
            $emp = $db->fetchOne(
                "SELECT id, target, commission_type, commission_rate, post_target_commission_rate FROM employees WHERE company_id = ? AND email = ? LIMIT 1",
                [$company_id, $winner['email']]
            );

            if ($emp) {
                $empId = $emp['id'];
                $invoiceBase = floatval($inv['total_amount']);
                $rate = floatval($emp['commission_rate']);
                $postRate = floatval($emp['post_target_commission_rate'] ?? 0);
                $target = floatval($emp['target'] ?? 0);
                $total_expected_commission = 0;

                if ($rate > 0 || $postRate > 0) {
                    if ($emp['commission_type'] === 'fixed') {
                        $usePostRate = false;
                        if ($target > 0 && $postRate > 0) {
                            $month = date('n', strtotime($inv['invoice_date']));
                            $year = date('Y', strtotime($inv['invoice_date']));
                            $priorSales = $db->fetchOne("
                                SELECT COALESCE(SUM(i.total_amount), 0) as total 
                                FROM invoices i 
                                JOIN leads l ON i.lead_id = l.id 
                                WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?)
                                AND i.company_id = ? 
                                AND i.payment_status != 'cancelled'
                                AND i.id != ?
                                AND MONTH(i.invoice_date) = ? 
                                AND YEAR(i.invoice_date) = ?
                            ", [$empId, $assignedToUser, $company_id, $invoice_id, $month, $year])['total'];
                            
                            if ($priorSales >= $target) {
                                $usePostRate = true;
                            }
                        }
                        $total_expected_commission = $usePostRate ? $postRate : $rate;
                    } else {
                        if ($target > 0 && $postRate > 0) {
                            $month = date('n', strtotime($inv['invoice_date']));
                            $year = date('Y', strtotime($inv['invoice_date']));
                            $priorSales = $db->fetchOne("
                                SELECT COALESCE(SUM(i.total_amount), 0) as total 
                                FROM invoices i 
                                JOIN leads l ON i.lead_id = l.id 
                                WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?)
                                AND i.company_id = ? 
                                AND i.payment_status != 'cancelled'
                                AND i.id != ?
                                AND MONTH(i.invoice_date) = ? 
                                AND YEAR(i.invoice_date) = ?
                            ", [$empId, $assignedToUser, $company_id, $invoice_id, $month, $year])['total'];

                            if ($priorSales >= $target) {
                                $total_expected_commission = ($invoiceBase * $postRate) / 100;
                            } elseif ($priorSales + $invoiceBase <= $target) {
                                $total_expected_commission = ($invoiceBase * $rate) / 100;
                            } else {
                                $underTarget = $target - $priorSales;
                                $overTarget = $invoiceBase - $underTarget;
                                $total_expected_commission = (($underTarget * $rate) / 100) + (($overTarget * $postRate) / 100);
                            }
                        } else {
                            $total_expected_commission = ($invoiceBase * $rate) / 100;
                        }
                    }
                } elseif (floatval($lead['commission_percent']) > 0) {
                    $total_expected_commission = ($invoiceBase * floatval($lead['commission_percent'])) / 100;
                }

                if ($total_expected_commission > 0) {
                    // Ratio of this payment to total invoice value
                    $ratio = floatval($amountPaid) / floatval($inv['total_amount']);
                    $commissionAmt = round($total_expected_commission * $ratio, 2);

                    if ($commissionAmt > 0) {
                        $db->query(
                            "UPDATE employees SET total_commission_earned = total_commission_earned + ? WHERE id = ? AND company_id = ?",
                            [$commissionAmt, $empId, $company_id]
                        );
                        $db->query(
                            "UPDATE leads SET commission_amount = commission_amount + ? WHERE id = ? AND company_id = ?",
                            [$commissionAmt, $inv['lead_id'], $company_id]
                        );

                        $db->insert('employee_commission_earnings', [
                            'company_id' => $company_id,
                            'employee_id' => $empId,
                            'lead_id' => $inv['lead_id'],
                            'invoice_id' => $invoice_id,
                            'invoice_payment_id' => $payment_id,
                            'amount' => $commissionAmt
                        ]);
                    }
                }
            }
        }
    }
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$invoiceModel = new Invoice();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'export_xlsx') {
        require_once __DIR__ . '/../core/SimpleXLSXWriter.php';

        $company_id = Auth::companyId();
        $db = Core\Database::getInstance();
        $isExecutive = Auth::isExecutive();
        $execEmpId = Auth::employeeId();

        $joinClause = "";
        $execFilter = "";
        $execParams = [];

        if ($isExecutive) {
            $joinClause = " LEFT JOIN leads exec_l ON i.lead_id = exec_l.id ";
            $execFilter = " AND (exec_l.assigned_employee_id = ? OR exec_l.assigned_to = ?) ";
            $execParams[] = $execEmpId;
            $execParams[] = Auth::userId();
        }

        $conditions = ["i.company_id = ?"];
        $queryParams = [$company_id];

        // Specific IDs (Selected rows)
        if (!empty($_GET['ids'])) {
            $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $conditions[] = "i.id IN ($placeholders)";
                $queryParams = array_merge($queryParams, $ids);
            }
        } else {
            // Scope / Date Range
            $scope = $_GET['scope'] ?? 'filter';
            if ($scope === 'month' && !empty($_GET['month']) && !empty($_GET['year'])) {
                $m = str_pad((int)$_GET['month'], 2, '0', STR_PAD_LEFT);
                $y = (int)$_GET['year'];
                $start = "$y-$m-01";
                $end = date('Y-m-t', strtotime($start));
                $conditions[] = "i.invoice_date BETWEEN ? AND ?";
                $queryParams[] = $start;
                $queryParams[] = $end;
            } elseif ($scope === 'all') {
                // No date restriction
            } else {
                $start = $_GET['start'] ?? null;
                $end = $_GET['end'] ?? null;
                if ($start && $end) {
                    $conditions[] = "i.invoice_date BETWEEN ? AND ?";
                    $queryParams[] = $start;
                    $queryParams[] = $end;
                }
            }

            // Status filter
            $status = $_GET['status_filter'] ?? 'all';
            if ($status === 'partial') {
                $conditions[] = "i.payment_status = 'partial'";
            } elseif ($status === 'dues' || $status === 'due') {
                $conditions[] = "i.due_amount > 0";
            } elseif ($status === 'paid') {
                $conditions[] = "i.payment_status = 'paid'";
            } elseif ($status === 'unpaid') {
                $conditions[] = "i.payment_status NOT IN ('paid', 'cancelled')";
            }

            // Search query
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $conditions[] = "(i.invoice_number LIKE ? OR l.name LIKE ? OR l.mobile LIKE ?)";
                $term = "%$search%";
                $queryParams[] = $term;
                $queryParams[] = $term;
                $queryParams[] = $term;
            }
        }

        $allParams = array_merge($queryParams, $execParams);
        $whereSql = implode(' AND ', $conditions) . $execFilter;

        $sql = "SELECT i.*, l.name as client_name, l.mobile as client_mobile, l.email as client_email
                FROM invoices i
                LEFT JOIN leads l ON i.lead_id = l.id
                $joinClause
                WHERE $whereSql
                ORDER BY i.invoice_date DESC, i.id DESC";

        $records = $db->fetchAll($sql, $allParams);

        $headers = [
            'S.No',
            'Invoice No',
            'Invoice Date',
            'Due Date',
            'Client Name',
            'Client Mobile',
            'Client Email',
            'Services / Items',
            'Subtotal (INR)',
            'Discount (INR)',
            'CGST (INR)',
            'SGST (INR)',
            'IGST (INR)',
            'Total Amount (INR)',
            'Paid Amount (INR)',
            'Due Amount (INR)',
            'Payment Status',
            'GST Number'
        ];

        $rows = [];
        $sno = 1;
        foreach ($records as $r) {
            $itemsText = '';
            if (!empty($r['description'])) {
                $decoded = json_decode($r['description'], true);
                if (is_array($decoded)) {
                    $itemParts = [];
                    foreach ($decoded as $it) {
                        $name = $it['name'] ?? '';
                        $qty = $it['qty'] ?? 1;
                        $rate = $it['rate'] ?? 0;
                        if ($name) {
                            $itemParts[] = "$name (Qty: $qty @ Rs.$rate)";
                        }
                    }
                    $itemsText = implode('; ', $itemParts);
                } else {
                    $itemsText = (string)$r['description'];
                }
            }

            $rows[] = [
                $sno++,
                $r['invoice_number'],
                $r['invoice_date'] ? date('d-m-Y', strtotime($r['invoice_date'])) : '',
                $r['due_date'] ? date('d-m-Y', strtotime($r['due_date'])) : '',
                $r['client_name'] ?? 'N/A',
                $r['client_mobile'] ?? '',
                $r['client_email'] ?? '',
                $itemsText,
                floatval($r['subtotal'] ?? 0),
                floatval($r['discount'] ?? 0),
                floatval($r['cgst'] ?? 0),
                floatval($r['sgst'] ?? 0),
                floatval($r['igst'] ?? 0),
                floatval($r['total_amount'] ?? 0),
                floatval($r['paid_amount'] ?? 0),
                floatval($r['due_amount'] ?? 0),
                strtoupper($r['payment_status'] ?? 'DUE'),
                $r['gst_number'] ?? ''
            ];
        }

        $xlsxContent = \Core\SimpleXLSXWriter::create($headers, $rows, 'Invoices');

        header_remove('Content-Type');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Invoices_Export_' . date('Y-m-d') . '.xlsx"');
        header('Content-Length: ' . strlen($xlsxContent));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        echo $xlsxContent;
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'search_leads') {
        $q = trim($_GET['q'] ?? '');
        $company_id = Auth::companyId();
        $db = Core\Database::getInstance();

        $sql = "SELECT l.id, l.name, l.mobile, l.email,
                       u.name as manager_name,
                       (SELECT GROUP_CONCAT(CONCAT(r.name, '::', COALESCE(r.fee, 0)) SEPARATOR '||') 
                        FROM requirements r 
                        JOIN lead_requirements lr ON r.id = lr.requirement_id 
                        WHERE lr.lead_id = l.id) as requirement_details
                FROM leads l 
                LEFT JOIN users u ON l.assigned_to = u.id
                WHERE l.company_id = ?";
        $params = [$company_id];

        if ($q !== '') {
            $sql .= " AND (l.name LIKE ? OR l.mobile LIKE ? OR l.email LIKE ?)";
            $term = "%$q%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (Auth::isExecutive()) {
            $sql .= " AND (l.assigned_to = ? OR l.assigned_employee_id = ?)";
            $params[] = Auth::userId();
            $params[] = Auth::employeeId();
        }

        $sql .= " ORDER BY l.name ASC LIMIT 30";
        $leads = $db->fetchAll($sql, $params);
        echo json_encode($leads ?: []);
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'payments') {
        $invoice_id = $_GET['id'] ?? null;
        if ($invoice_id) {
            $db = Core\Database::getInstance();
            $payments = $db->fetchAll("SELECT * FROM invoice_payments WHERE invoice_id = ? AND company_id = ? ORDER BY payment_date DESC, created_at DESC", [$invoice_id, Auth::companyId()]);
            echo json_encode($payments);
        } else {
            echo json_encode([]);
        }
        exit;
    }

    $id = $_GET['id'] ?? null;
    if ($id) {
        $db = Core\Database::getInstance();
        $invoice = $db->fetchOne("
            SELECT i.*, l.name as customer_name, l.mobile as customer_mobile, l.address as customer_address
            FROM invoices i 
            LEFT JOIN leads l ON i.lead_id = l.id 
            WHERE i.id = ? AND i.company_id = ?
        ", [$id, Auth::companyId()]);
        
        if ($invoice && Auth::isExecutive() && !empty($invoice['lead_id'])) {
            $ld = $db->fetchOne("SELECT assigned_employee_id, assigned_to FROM leads WHERE id = ? AND company_id = ?", [$invoice['lead_id'], Auth::companyId()]);
            $empId = Auth::employeeId();
            $userId = Auth::userId();
            if (intval($ld['assigned_employee_id'] ?? 0) !== ($empId ?: 0) && intval($ld['assigned_to'] ?? 0) !== ($userId ?: 0)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
        }
        
        if ($invoice) {
            // Also fetch items correctly from description field
            $items = [];
            if (!empty($invoice['description'])) {
                $decoded = json_decode($invoice['description'], true);
                if (is_array($decoded)) {
                    $items = $decoded;
                }
            }
            echo json_encode(['invoice' => $invoice, 'items' => $items]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Invoice not found']);
        }
        exit;
    }

    if (isset($_GET['next_num'])) {
        echo json_encode(['next_number' => $invoiceModel->getNextInvoiceNumber()]);
    } else {
        $company_id = Auth::companyId();
        $isExecutive = Auth::isExecutive();
        $execEmpId = Auth::employeeId();

        $joinClause = "";
        $execFilter = "";
        $execParams = [];

        if ($isExecutive) {
            $joinClause = " LEFT JOIN leads exec_l ON i.lead_id = exec_l.id ";
            $execFilter = " AND (exec_l.assigned_employee_id = ? OR exec_l.assigned_to = ?) ";
            $execParams[] = $execEmpId;
            $execParams[] = Auth::userId();
        }

        $conditions = ["i.company_id = ?"];
        $queryParams = [$company_id];

        if (isset($_GET['start']) && isset($_GET['end'])) {
            $conditions[] = "i.invoice_date BETWEEN ? AND ?";
            $queryParams[] = $_GET['start'];
            $queryParams[] = $_GET['end'];
        }

        $search = trim($_GET['search'] ?? '');
        if (!empty($search)) {
            $conditions[] = "(i.invoice_number LIKE ? OR l.name LIKE ? OR l.mobile LIKE ?)";
            $term = "%$search%";
            $queryParams[] = $term;
            $queryParams[] = $term;
            $queryParams[] = $term;
        }

        $allParams = array_merge($queryParams, $execParams);
        $whereSql = implode(' AND ', $conditions) . $execFilter;

        $db = Core\Database::getInstance();
        $sql = "SELECT i.*, l.name as customer_name, l.mobile as customer_mobile, l.email as customer_email
                FROM invoices i
                LEFT JOIN leads l ON i.lead_id = l.id
                $joinClause
                WHERE $whereSql
                ORDER BY i.created_at DESC";

        $invoices = $db->fetchAll($sql, $allParams);
        echo json_encode(['invoices' => $invoices]);
        exit;
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['invoice_number']) || empty($input['total_amount']) || empty($input['lead_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice number, total amount, and client (lead) are required']);
        exit;
    }

    try {
        $id = $invoiceModel->create($input);
        
        try {
            \Core\WhatsApp::sendInvoiceAlert($id, $input['lead_id'], $input['invoice_number'], $input['total_amount']);
        } catch (\Throwable $e) {}

        if (isset($input['paid_amount']) && floatval($input['paid_amount']) > 0) {
            $payment_id = Core\Database::getInstance()->insert('invoice_payments', [
                'company_id' => Auth::companyId(),
                'invoice_id' => $id,
                'amount' => floatval($input['paid_amount']),
                'payment_date' => $input['invoice_date'] ?? date('Y-m-d'),
                'payment_mode' => $input['payment_mode'] ?? 'cash',
                'remarks' => 'Initial payment upon invoice creation'
            ]);

            // Credit Commission for this initial payment
            try {
                creditCommissionForPayment($id, $payment_id, $input['paid_amount']);
            } catch (\Throwable $e) {
                // Ignore commission errors to not break invoice creation
            }
            
            try {
                \Core\WhatsApp::sendPaymentAlert($payment_id, $id, $input['paid_amount']);
            } catch (\Throwable $e) {}
        }

        // Automatically mark lead as 'won' when an invoice is created
        if (!empty($input['lead_id'])) {
            Core\Database::getInstance()->query(
                "UPDATE leads SET status = 'won' WHERE id = ? AND company_id = ?",
                [$input['lead_id'], Auth::companyId()]
            );
        }

        echo json_encode(['success' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        http_response_code(400);
        $err = ['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()];
        file_put_contents(__DIR__ . '/../error_log.txt', print_r($err, true) . "\n", FILE_APPEND);
        echo json_encode($err);
    }
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $amount = $input['payment_amount'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID is required']);
        exit;
    }

    try {
        $db = Core\Database::getInstance();
        $inv = $db->fetchOne("SELECT total_amount, paid_amount, lead_id, subtotal FROM invoices WHERE id = ?", [$id]);

        $new_paid = $inv['paid_amount'] + $amount;
        $new_due = $inv['total_amount'] - $new_paid;

        $status = 'partial';
        if ($new_due <= 0) {
            $status = 'paid';
            $new_due = 0;
        }

        $db->query("UPDATE invoices SET paid_amount = ?, due_amount = ?, payment_status = ? WHERE id = ?", [
            $new_paid,
            $new_due,
            $status,
            $id
        ]);

        $payment_id = $db->insert('invoice_payments', [
            'company_id' => Auth::companyId(),
            'invoice_id' => $id,
            'amount' => floatval($amount),
            'payment_date' => $input['payment_date'] ?? date('Y-m-d'),
            'payment_mode' => $input['payment_mode'] ?? 'cash',
            'reference_number' => $input['reference_number'] ?? null,
            'remarks' => $input['remarks'] ?? null
        ]);

        // ── Credit Commission to Assigned Employee on Payment ──────────────
        try {
            creditCommissionForPayment($id, $payment_id, $amount);
        } catch (\Throwable $e) {
            // Ignore commission errors
        }
        
        try {
            \Core\WhatsApp::sendPaymentAlert($payment_id, $id, $amount);
        } catch (\Throwable $e) {}

        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'DELETE') {
    if (!Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Only administrators can cancel invoices']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? ($_GET['id'] ?? null);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID is required']);
        exit;
    }

    try {
        $db = Core\Database::getInstance();
        $company_id = Auth::companyId();

        $inv = $db->fetchOne("SELECT id FROM invoices WHERE id = ? AND company_id = ?", [$id, $company_id]);
        if (!$inv) {
            http_response_code(404);
            echo json_encode(['error' => 'Invoice not found']);
            exit;
        }

        // Revert commissions
        $earnings = $db->fetchAll("SELECT * FROM employee_commission_earnings WHERE invoice_id = ?", [$id]);
        foreach ($earnings as $earning) {
            $db->query(
                "UPDATE employees SET total_commission_earned = total_commission_earned - ? WHERE id = ? AND company_id = ?",
                [$earning['amount'], $earning['employee_id'], $company_id]
            );
            $db->query(
                "UPDATE leads SET commission_amount = commission_amount - ? WHERE id = ? AND company_id = ?",
                [$earning['amount'], $earning['lead_id'], $company_id]
            );
        }

        // Delete earnings
        $db->query("DELETE FROM employee_commission_earnings WHERE invoice_id = ?", [$id]);

        // Delete payments
        $db->query("DELETE FROM invoice_payments WHERE invoice_id = ?", [$id]);

        // Cancel invoice
        $db->query("UPDATE invoices SET payment_status = 'cancelled', due_amount = 0, paid_amount = 0 WHERE id = ?", [$id]);

        echo json_encode(['success' => true]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}