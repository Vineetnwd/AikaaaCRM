<?php
header('Content-Type: application/json');

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

use Core\Auth;
use Core\Database;
use Core\Invoice;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'export_xlsx') {
        require_once __DIR__ . '/../core/SimpleXLSXWriter.php';

        $company_id = Auth::companyId();
        $isExecutive = Auth::isExecutive();
        $execEmpId = Auth::employeeId();

        $joinClause = "";
        $execFilter = "";
        $execParams = [];

        if ($isExecutive) {
            $joinClause = " LEFT JOIN leads exec_l ON q.lead_id = exec_l.id ";
            $execFilter = " AND (exec_l.assigned_employee_id = ? OR exec_l.assigned_to = ?) ";
            $execParams[] = $execEmpId;
            $execParams[] = Auth::userId();
        }

        $conditions = ["q.company_id = ?"];
        $queryParams = [$company_id];

        // Specific IDs (Selected rows)
        if (!empty($_GET['ids'])) {
            $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $conditions[] = "q.id IN ($placeholders)";
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
                $conditions[] = "q.quotation_date BETWEEN ? AND ?";
                $queryParams[] = $start;
                $queryParams[] = $end;
            } elseif ($scope === 'all') {
                // No date restriction
            } else {
                $start = $_GET['start'] ?? null;
                $end = $_GET['end'] ?? null;
                if ($start && $end) {
                    $conditions[] = "q.quotation_date BETWEEN ? AND ?";
                    $queryParams[] = $start;
                    $queryParams[] = $end;
                }
            }

            // Status filter
            $status = $_GET['status_filter'] ?? 'all';
            if ($status === 'invoiced') {
                $conditions[] = "q.status = 'invoiced'";
            } elseif ($status === 'pending') {
                $conditions[] = "q.status = 'pending'";
            } elseif ($status === 'draft') {
                $conditions[] = "q.status = 'draft'";
            }

            // Search query
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $conditions[] = "(q.quotation_number LIKE ? OR l.name LIKE ? OR l.mobile LIKE ?)";
                $term = "%$search%";
                $queryParams[] = $term;
                $queryParams[] = $term;
                $queryParams[] = $term;
            }
        }

        $allParams = array_merge($queryParams, $execParams);
        $whereSql = implode(' AND ', $conditions) . $execFilter;

        $sql = "SELECT q.*, l.name as client_name, l.mobile as client_mobile, l.email as client_email,
                       (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') 
                        FROM requirements r 
                        JOIN lead_requirements lr ON r.id = lr.requirement_id 
                        WHERE lr.lead_id = l.id) as client_services
                FROM quotations q
                LEFT JOIN leads l ON q.lead_id = l.id
                $joinClause
                WHERE $whereSql
                ORDER BY q.quotation_date DESC, q.id DESC";

        $records = $db->fetchAll($sql, $allParams);

        $headers = [
            'S.No',
            'Quotation No',
            'Quotation Date',
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
            'Status',
            'Created At'
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
            if (empty($itemsText) && !empty($r['client_services'])) {
                $itemsText = $r['client_services'];
            }

            $rows[] = [
                $sno++,
                $r['quotation_number'],
                $r['quotation_date'] ? date('d-m-Y', strtotime($r['quotation_date'])) : '',
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
                strtoupper($r['status'] ?? 'DRAFT'),
                $r['created_at'] ? date('d-m-Y H:i', strtotime($r['created_at'])) : ''
            ];
        }

        $xlsxContent = \Core\SimpleXLSXWriter::create($headers, $rows, 'Quotations');

        header_remove('Content-Type');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Quotations_Export_' . date('Y-m-d') . '.xlsx"');
        header('Content-Length: ' . strlen($xlsxContent));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        echo $xlsxContent;
        exit;
    }

    $lead_id = $_GET['lead_id'] ?? null;
    $id = $_GET['id'] ?? null;
    if ($id) {
        $quotation = $db->fetchOne("
            SELECT q.*, l.name as customer_name, l.mobile as customer_mobile 
            FROM quotations q 
            LEFT JOIN leads l ON q.lead_id = l.id 
            WHERE q.id = ? AND q.company_id = ?
        ", [$id, Auth::companyId()]);
        
        if ($quotation && Auth::isExecutive() && !empty($quotation['lead_id'])) {
            $ld = $db->fetchOne("SELECT assigned_employee_id, assigned_to, created_by FROM leads WHERE id = ? AND company_id = ?", [$quotation['lead_id'], Auth::companyId()]);
            $empId = Auth::employeeId();
            $userId = Auth::userId();
            if (intval($ld['assigned_employee_id'] ?? 0) !== ($empId ?: 0) && intval($ld['assigned_to'] ?? 0) !== ($userId ?: 0)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
        }
        
        if ($quotation) {
            $items = [];
            if (!empty($quotation['description'])) {
                $decoded = json_decode($quotation['description'], true);
                if (is_array($decoded)) {
                    $items = $decoded;
                }
            }
            echo json_encode(['quotation' => $quotation, 'items' => $items]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        }
        exit;
    }
    if ($lead_id) {
        $quotations = $db->fetchAll("SELECT * FROM quotations WHERE lead_id = ? AND company_id = ? AND status != 'invoiced'", [$lead_id, Auth::companyId()]);
        echo json_encode($quotations);
        exit;
    }

    // Default: fetch all quotations for the company
    $company_id = Auth::companyId();
    $isExecutive = Auth::isExecutive();
    $execEmpId = Auth::employeeId();

    $joinClause = "";
    $execFilter = "";
    $execParams = [];

    if ($isExecutive) {
        $joinClause = " LEFT JOIN leads exec_l ON q.lead_id = exec_l.id ";
        $execFilter = " AND (exec_l.assigned_employee_id = ? OR exec_l.assigned_to = ?) ";
        $execParams[] = $execEmpId;
        $execParams[] = Auth::userId();
    }

    $conditions = ["q.company_id = ?"];
    $queryParams = [$company_id];

    if (isset($_GET['start']) && isset($_GET['end'])) {
        $conditions[] = "q.quotation_date BETWEEN ? AND ?";
        $queryParams[] = $_GET['start'];
        $queryParams[] = $_GET['end'];
    }

    $search = trim($_GET['search'] ?? '');
    if (!empty($search)) {
        $conditions[] = "(q.quotation_number LIKE ? OR l.name LIKE ? OR l.mobile LIKE ?)";
        $term = "%$search%";
        $queryParams[] = $term;
        $queryParams[] = $term;
        $queryParams[] = $term;
    }

    $allParams = array_merge($queryParams, $execParams);
    $whereSql = implode(' AND ', $conditions) . $execFilter;

    $sql = "SELECT q.*, l.name as customer_name, l.mobile as customer_mobile
            FROM quotations q
            LEFT JOIN leads l ON q.lead_id = l.id
            $joinClause
            WHERE $whereSql
            ORDER BY q.quotation_date DESC, q.id DESC";

    $data = $db->fetchAll($sql, $allParams);
    echo json_encode(['quotations' => $data]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    if (isset($input['action']) && $input['action'] === 'convert') {
        try {
            $quo_id = intval($input['id'] ?? 0);
            $company_id = Auth::companyId();

            if (!$quo_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid Quotation ID']);
                exit;
            }

            $quo = $db->fetchOne("SELECT * FROM quotations WHERE id = ? AND company_id = ?", [$quo_id, $company_id]);

            if (!$quo) {
                http_response_code(404);
                echo json_encode(['error' => 'Quotation not found']);
                exit;
            }

            // If executive, ensure they can operate only on assigned leads
            if (Auth::isExecutive() && !empty($quo['lead_id'])) {
                $lead = $db->fetchOne("SELECT assigned_employee_id, assigned_to FROM leads WHERE id = ? AND company_id = ?", [$quo['lead_id'], $company_id]);
                $empId = Auth::employeeId();
                $userId = Auth::userId();
                $assignedEmp = intval($lead['assigned_employee_id'] ?? 0);
                $assignedUser = intval($lead['assigned_to'] ?? 0);
                if ($assignedEmp !== ($empId ?: 0) && $assignedUser !== ($userId ?: 0)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied: lead not assigned to you']);
                    exit;
                }
            }

            // Generate Invoice Number safely using Invoice model
            $invoiceModel = new Invoice();
            $inv_num = $invoiceModel->getNextInvoiceNumber();

            // Create Invoice using Invoice model (handles schema column matching safely)
            $inv_id = $invoiceModel->create([
                'company_id' => $company_id,
                'lead_id' => $quo['lead_id'],
                'invoice_number' => $inv_num,
                'invoice_date' => date('Y-m-d'),
                'description' => $quo['description'] ?? null,
                'subtotal' => $quo['subtotal'] ?? 0,
                'igst' => $quo['igst_amount'] ?? 0,
                'cgst' => $quo['cgst_amount'] ?? 0,
                'sgst' => $quo['sgst_amount'] ?? 0,
                'is_gst_enabled' => $quo['is_gst_enabled'] ?? 1,
                'discount' => $quo['discount'] ?? 0,
                'total_amount' => $quo['total_amount'] ?? 0,
                'due_amount' => $quo['total_amount'] ?? 0,
                'payment_status' => 'due'
            ]);

            // Update Quotation Status
            $db->query("UPDATE quotations SET status = 'invoiced' WHERE id = ? AND company_id = ?", [$quo_id, $company_id]);

            // Update Lead Status to 'won' and set Expected Delivery Date in leads
            if (!empty($quo['lead_id'])) {
                if (!empty($input['expected_delivery_date'])) {
                    $db->query("UPDATE leads SET status = 'won', expected_delivery_date = ? WHERE id = ? AND company_id = ?", [
                        $input['expected_delivery_date'],
                        $quo['lead_id'],
                        $company_id
                    ]);
                } else {
                    $db->query("UPDATE leads SET status = 'won' WHERE id = ? AND company_id = ?", [
                        $quo['lead_id'],
                        $company_id
                    ]);
                }
            }

            // Send WhatsApp Invoice Alert if enabled
            try {
                if (!empty($quo['lead_id'])) {
                    \Core\WhatsApp::sendInvoiceAlert($inv_id, $quo['lead_id'], $inv_num, $quo['total_amount']);
                }
            } catch (\Throwable $e) {}

            echo json_encode(['success' => true, 'invoice_number' => $inv_num, 'id' => $inv_id]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Conversion failed: ' . $e->getMessage()]);
            exit;
        }
    }

    if (isset($input['action']) && $input['action'] === 'delete') {
        try {
            $quo_id = intval($input['id'] ?? 0);
            $company_id = Auth::companyId();

            if (!$quo_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid Quotation ID']);
                exit;
            }

            $quo = $db->fetchOne("SELECT * FROM quotations WHERE id = ? AND company_id = ?", [$quo_id, $company_id]);
            if (!$quo) {
                http_response_code(404);
                echo json_encode(['error' => 'Quotation not found']);
                exit;
            }

            if ($quo['status'] === 'invoiced') {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete an invoiced quotation.']);
                exit;
            }

            if (Auth::isExecutive() && !empty($quo['lead_id'])) {
                $ld = $db->fetchOne("SELECT assigned_employee_id, assigned_to FROM leads WHERE id = ? AND company_id = ?", [$quo['lead_id'], $company_id]);
                $empId = Auth::employeeId();
                $userId = Auth::userId();
                $assignedEmp = intval($ld['assigned_employee_id'] ?? 0);
                $assignedUser = intval($ld['assigned_to'] ?? 0);
                if ($assignedEmp !== ($empId ?: 0) && $assignedUser !== ($userId ?: 0)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied: lead not assigned to you']);
                    exit;
                }
            }

            $db->query("DELETE FROM quotations WHERE id = ? AND company_id = ?", [$quo_id, $company_id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
            exit;
        }
    }

    // CREATE OR UPDATE QUOTATION LOGIC
    try {
    if (!empty($input['id']) && empty($input['action'])) {
            // UPDATE
            $is_gst = !empty($input['is_gst_enabled']) ? 1 : 0;
            $tax_pct = $is_gst ? ($input['tax_percent'] ?? 18) : 0;
            $tax_amt = ($input['subtotal'] * $tax_pct) / 100;
            $discount = floatval($input['discount'] ?? 0);
            $total = ($input['subtotal'] + $tax_amt) - $discount;
            
            $gst_type = $input['gst_type'] ?? 'intra';
            $cgst = ($is_gst && $gst_type === 'intra') ? ($tax_amt / 2) : 0;
            $sgst = ($is_gst && $gst_type === 'intra') ? ($tax_amt / 2) : 0;
            $igst = ($is_gst && $gst_type === 'inter') ? $tax_amt : 0;

            // If executive, ensure they can update only quotations for assigned leads
            if (Auth::isExecutive() && !empty($input['lead_id'])) {
                $ld = $db->fetchOne("SELECT assigned_employee_id, assigned_to FROM leads WHERE id = ? AND company_id = ?", [$input['lead_id'], Auth::companyId()]);
                $empId = Auth::employeeId();
                $userId = Auth::userId();
                $assignedEmp = intval($ld['assigned_employee_id'] ?? 0);
                $assignedUser = intval($ld['assigned_to'] ?? 0);
                if ($assignedEmp !== ($empId ?: 0) && $assignedUser !== ($userId ?: 0)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied: lead not assigned to you']);
                    exit;
                }
            }

            $db->update('quotations', [
                'quotation_number' => $input['quotation_number'],
                'quotation_date' => $input['quotation_date'],
                'lead_id' => $input['lead_id'],
                'description' => $input['description'] ?? null,
                'subtotal' => $input['subtotal'],
                'is_gst_enabled' => $is_gst,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'discount' => $discount,
                'total_amount' => $total
            ], "id = ? AND company_id = ?", [$input['id'], Auth::companyId()]);
            echo json_encode(['success' => true]);
            exit;
        }

        // Prevent multiple quotations for the same lead
        // If executive, ensure they can create quotation only for assigned leads
        if (Auth::isExecutive() && !empty($input['lead_id'])) {
            $ld = $db->fetchOne("SELECT assigned_employee_id, assigned_to FROM leads WHERE id = ? AND company_id = ?", [$input['lead_id'], Auth::companyId()]);
            $empId = Auth::employeeId();
            $userId = Auth::userId();
            $assignedEmp = intval($ld['assigned_employee_id'] ?? 0);
            $assignedUser = intval($ld['assigned_to'] ?? 0);
            if ($assignedEmp !== ($empId ?: 0) && $assignedUser !== ($userId ?: 0)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: lead not assigned to you']);
                exit;
            }
        }

        $existing = $db->fetchOne("SELECT id FROM quotations WHERE lead_id = ? AND company_id = ?", [$input['lead_id'], Auth::companyId()]);
        if ($existing) {
            http_response_code(400);
            echo json_encode(['error' => 'A quotation has already been issued for this lead.']);
            exit;
        }

        $is_gst = !empty($input['is_gst_enabled']) ? 1 : 0;
        $tax_pct = $is_gst ? ($input['tax_percent'] ?? 18) : 0;
        $tax_amt = ($input['subtotal'] * $tax_pct) / 100;
        $discount = floatval($input['discount'] ?? 0);
        $total = ($input['subtotal'] + $tax_amt) - $discount;
        
        $gst_type = $input['gst_type'] ?? 'intra';
        $cgst = ($is_gst && $gst_type === 'intra') ? ($tax_amt / 2) : 0;
        $sgst = ($is_gst && $gst_type === 'intra') ? ($tax_amt / 2) : 0;
        $igst = ($is_gst && $gst_type === 'inter') ? $tax_amt : 0;

        $quo_id = $db->insert('quotations', [
            'company_id' => Auth::companyId(),
            'quotation_number' => $input['quotation_number'],
            'quotation_date' => $input['quotation_date'],
            'lead_id' => $input['lead_id'],
            'description' => $input['description'] ?? null,
            'subtotal' => $input['subtotal'],
            'is_gst_enabled' => $is_gst,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'discount' => $discount,
            'total_amount' => $total,
            'status' => 'pending'
        ]);
        
        try {
            \Core\WhatsApp::sendQuotationAlert($quo_id, $input['lead_id'], $input['quotation_number'], $total);
        } catch (\Throwable $e) { }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>