<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();

// Pre-calculate next invoice number to avoid API fetch
$lastInv = $db->fetchOne("SELECT invoice_number FROM invoices WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$company_id]);
$next_invoice_number = "INV-1001";
if ($lastInv) {
    $clean = preg_replace('/[^0-9]/', '', $lastInv['invoice_number']);
    $num = (int)$clean;
    if ($num > 0) {
        $next_invoice_number = "INV-" . ($num + 1);
    } else {
        $next_invoice_number = "INV-1001";
    }
}

// Handle Date Range
$range = $_GET['range'] ?? 'month';
$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? date('Y-m-d');

if ($range === 'last_month') {
    $start_date = date('Y-m-01', strtotime('first day of last month'));
    $end_date = date('Y-m-t', strtotime('last day of last month'));
} elseif ($range === 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
} elseif ($range === 'all') {
    $start_date = '2020-01-01';
    $end_date = date('Y-m-d', strtotime('+10 years'));
}

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

$params = [$company_id, $start_date, $end_date];
$metricsParams = array_merge($params, $execParams);

// Fetch Metrics
$metrics = $db->fetchOne("
    SELECT 
        COALESCE(SUM(i.total_amount), 0) as total_billed,
        COALESCE(SUM(i.paid_amount), 0) as total_paid,
        COALESCE(SUM(i.due_amount), 0) as total_pending
    FROM invoices i
    $joinClause
    WHERE i.company_id = ? AND i.payment_status != 'cancelled' AND i.invoice_date BETWEEN ? AND ? $execFilter
", $metricsParams);

// Handle Status Filter
$status_filter = $_GET['status_filter'] ?? 'all';
$status_query = "";
if ($status_filter === 'partial') {
    $status_query = " AND i.payment_status = 'partial'";
} elseif ($status_filter === 'dues') {
    $status_query = " AND i.due_amount > 0";
}

// Pagination setup
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit_val = $_GET['limit'] ?? '15';
$limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
$offset = ($page - 1) * $limit;

// Search setup
$search = trim($_GET['search'] ?? '');
$searchQuery = "";
$searchParams = [];
if ($search !== '') {
    $searchQuery = " AND (i.invoice_number LIKE ? OR l.name LIKE ? OR l.mobile LIKE ?)";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

$invoicesParams = array_merge($params, $execParams, $searchParams);

$countQuery = "
    SELECT COUNT(*) as total 
    FROM invoices i 
    LEFT JOIN leads l ON i.lead_id = l.id 
    $joinClause
    WHERE i.company_id = ? AND i.invoice_date BETWEEN ? AND ? $status_query $execFilter $searchQuery
";
$total_items = $db->fetchOne($countQuery, $invoicesParams)['total'] ?? 0;
$total_pages = max(1, ceil($total_items / $limit));

// Fetch Recent Invoices
$invoices = $db->fetchAll("
    SELECT i.*, l.name as client_name, l.mobile as client_mobile, l.email as client_email
    FROM invoices i 
    LEFT JOIN leads l ON i.lead_id = l.id 
    $joinClause
    WHERE i.company_id = ? AND i.invoice_date BETWEEN ? AND ? $status_query $execFilter $searchQuery
    ORDER BY i.created_at DESC
    LIMIT $limit OFFSET $offset
", $invoicesParams);

// Fetch Aging Report (Due Report)
$agingParams = array_merge([$company_id], $execParams);
$aging = $db->fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.invoice_date) <= 30 THEN i.due_amount ELSE 0 END), 0) as 'days_0_30',
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.invoice_date) BETWEEN 31 AND 60 THEN i.due_amount ELSE 0 END), 0) as 'days_31_60',
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.invoice_date) > 60 THEN i.due_amount ELSE 0 END), 0) as 'days_60_plus'
    FROM invoices i
    $joinClause
    WHERE i.company_id = ? AND i.payment_status NOT IN ('paid', 'cancelled') $execFilter
", $agingParams);

// Leads are dynamically searched from DB on demand via API to optimize performance

// Fetch Requirements for items dropdown
$requirements = $db->fetchAll("SELECT * FROM requirements WHERE company_id = ? ORDER BY name ASC", [$company_id]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-bar {
            background: white;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #64748b;
        }

        /* Switch Toggle */
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
            margin: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary);
        }

        input:checked+.slider:before {
            transform: translateX(18px);
        }

        .lead-option:hover {
            background-color: #f1f5f9 !important;
            color: var(--primary);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>
            <header class="header">
                <div>
                    <h1 class="page-title">Invoice System</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Real-time financial
                        performance</p>
                </div>
                <div class="header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                    <form method="GET" style="position:relative; margin:0;"
                        onsubmit="event.preventDefault(); window.location.href = '?search=' + encodeURIComponent(this.search.value) + '&range=<?= $range ?>&status_filter=<?= $status_filter ?>'">
                        <i class="fas fa-search"
                            style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size: 0.75rem; color:#94a3b8;"></i>
                        <input type="text" name="search" id="invoiceSearch"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search invoices..."
                            class="form-input"
                            style="padding-left: 2.25rem; width: 240px; font-size: 0.75rem; height: 36px; margin:0;">
                    </form>
                    <button class="btn btn-ghost" onclick="openExportModal()"
                        style="height: 36px; border: 1px solid var(--border); margin:0; display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; font-weight: 700; font-size: 0.75rem;">
                        <i class="fas fa-file-excel" style="color: #10b981;"></i> Bulk Export
                    </button>
                    <button class="btn btn-primary" onclick="openInvoiceModal()"
                        style="height: 36px; padding: 0 1rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; margin:0;">
                        <i class="fas fa-file-invoice" style="font-size: 0.75rem;"></i> Create Invoice
                    </button>
                </div>
            </header>

            <form action="" method="GET" class="filter-bar">
                <div class="filter-item">
                    <i class="fas fa-calendar-alt"></i>
                    <select name="range" class="form-input" style="padding: 0.4rem; font-size: 0.75rem; width: 120px;"
                        onchange="this.form.submit()">
                        <option value="today" <?= $range == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="last_month" <?= $range == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="all" <?= $range == 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= $range == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                <?php if ($range == 'custom'): ?>
                    <div class="filter-item">
                        <input type="date" name="start" value="<?= $start_date ?>" class="form-input"
                            style="padding: 0.4rem; font-size: 0.75rem;">
                        <span>to</span>
                        <input type="date" name="end" value="<?= $end_date ?>" class="form-input"
                            style="padding: 0.4rem; font-size: 0.75rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.875rem;">Go</button>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="status_filter" value="<?= $status_filter ?>" id="status_filter_input">

                <div class="filter-item" style="margin-left: auto; gap: 0.5rem;">
                    <button type="button" class="btn <?= $status_filter === 'all' ? 'btn-primary' : 'btn-ghost' ?>"
                        onclick="setStatusFilter('all')"
                        style="padding: 0.4rem 0.875rem; font-size: 0.75rem; font-weight: 800; border: 1px solid <?= $status_filter === 'all' ? 'transparent' : '#cbd5e1' ?>;">ALL
                        INVOICES</button>
                    <button type="button" class="btn <?= $status_filter === 'partial' ? 'btn-primary' : 'btn-ghost' ?>"
                        onclick="setStatusFilter('partial')"
                        style="padding: 0.4rem 0.875rem; font-size: 0.75rem; font-weight: 800; border: 1px solid <?= $status_filter === 'partial' ? 'transparent' : '#cbd5e1' ?>;">PARTIAL</button>
                    <button type="button" class="btn <?= $status_filter === 'dues' ? 'btn-primary' : 'btn-ghost' ?>"
                        onclick="setStatusFilter('dues')"
                        style="padding: 0.4rem 0.875rem; font-size: 0.75rem; font-weight: 800; border: 1px solid <?= $status_filter === 'dues' ? 'transparent' : '#cbd5e1' ?>;">DUES</button>
                </div>

                <script>
                    function setStatusFilter(status) {
                        document.getElementById('status_filter_input').value = status;
                        document.querySelector('.filter-bar').submit();
                    }
                </script>
            </form>

            <div class="stats-grid">
                <div class="card stat-card">
                    <div
                        style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="stat-label">TOTAL BILLED</span>
                        <div
                            style="width: 28px; height: 28px; border-radius: 6px; background: #e0f2fe; color: #0ea5e9; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                    <span class="stat-value">₹<?= number_format($metrics['total_billed'], 2) ?></span>
                </div>
                <div class="card stat-card">
                    <div
                        style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="stat-label" style="color: var(--success);">PAID</span>
                        <div
                            style="width: 28px; height: 28px; border-radius: 6px; background: #d1fae5; color: var(--success); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check-circle" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                    <span class="stat-value">₹<?= number_format($metrics['total_paid'], 2) ?></span>
                </div>
                <div class="card stat-card">
                    <div
                        style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="stat-label" style="color: var(--danger);">PENDING</span>
                        <div
                            style="width: 28px; height: 28px; border-radius: 6px; background: #fee2e2; color: var(--danger); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                    <span class="stat-value">₹<?= number_format($metrics['total_pending'], 2) ?></span>
                </div>
            </div>

            <!-- Aging Report -->
            <div
                style="background: white; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 2rem;">
                <div
                    style="display:flex; align-items:center; gap:0.5rem; font-weight:800; color:#475569; font-size:0.8125rem;">
                    <i class="fas fa-chart-pie" style="color:var(--primary);"></i> Due Aging Report:
                </div>
                <div style="display:flex; gap: 2rem;">
                    <div>
                        <div style="font-size:0.65rem; color:#94a3b8; font-weight:700; text-transform:uppercase;">0-30
                            Days</div>
                        <div style="font-weight:800; color:#0f172a; font-size:1rem;">
                            ₹<?= number_format($aging['days_0_30'], 2) ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.65rem; color:#94a3b8; font-weight:700; text-transform:uppercase;">31-60
                            Days</div>
                        <div style="font-weight:800; color:#d97706; font-size:1rem;">
                            ₹<?= number_format($aging['days_31_60'], 2) ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.65rem; color:#94a3b8; font-weight:700; text-transform:uppercase;">> 60
                            Days</div>
                        <div style="font-weight:800; color:#dc2626; font-size:1rem;">
                            ₹<?= number_format($aging['days_60_plus'], 2) ?></div>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 1rem;">
            <div class="card" style="padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1rem; font-weight: 800; margin: 0;">Recent Invoices</h2>
                    <div id="bulkActionsContainer" style="display: none; align-items: center; gap: 0.5rem;">
                        <button onclick="exportSelectedInvoicesXLSX()" class="btn btn-ghost" style="padding: 0.5rem 1rem; border: 1.5px solid #10b981; color: #047857; background: #ecfdf5; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:6px; font-size: 0.8rem; font-weight:700;">
                            <i class="fas fa-file-excel" style="color: #10b981;"></i> Export XLSX (<span id="bulkExportCount">0</span>)
                        </button>
                        <button onclick="sendBulkWhatsApp()" class="btn btn-primary" style="padding: 0.5rem 1rem; background: #25d366; color: white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:5px; font-size: 0.8rem;">
                            <i class="fab fa-whatsapp"></i> Bulk WhatsApp (<span id="bulkCount">0</span>)
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.75rem 0.5rem; width: 30px;">
                                    <input type="checkbox" id="selectAllInvoices" onchange="toggleAllInvoices(this)">
                                </th>
                                <th
                                    style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">
                                    ID & Date</th>
                                <th
                                    style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">
                                    Client Name</th>
                                <th
                                    style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">
                                    Amount</th>
                                <th
                                    style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">No
                                        invoices found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr style="border-bottom: 1px solid #f8fafc;">
                                        <td style="padding: 0.875rem 0.5rem;">
                                            <input type="checkbox" class="invoice-checkbox" value="<?= $invoice['id'] ?>" onchange="updateBulkActions()">
                                        </td>
                                        <td style="padding: 0.875rem 0.5rem;">
                                            <div style="font-size: 0.8125rem; font-weight: 700; color:var(--primary);">
                                                <?= $invoice['invoice_number'] ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.2rem;">
                                                <?= date('d M Y', strtotime($invoice['invoice_date'])) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.875rem 0.5rem;">
                                            <div style="font-size: 0.8125rem; font-weight: 700; color: #1e293b;">
                                                <?= htmlspecialchars($invoice['client_name']) ?>
                                            </div>
                                            <div
                                                style="font-size: 0.7rem; color: #64748b; margin-top: 0.15rem; font-weight: 500;">
                                                <i class="fas fa-phone-alt"
                                                    style="font-size: 0.6rem; margin-right: 3px; opacity: 0.7;"></i>
                                                <?= htmlspecialchars($invoice['client_mobile'] ?: 'No Mobile') ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.875rem 0.5rem;">
                                            <div style="font-size: 0.8125rem; font-weight: 700;">
                                                ₹<?= number_format($invoice['total_amount'], 2) ?></div>
                                            <div
                                                style="font-size: 0.65rem; color: <?= $invoice['due_amount'] > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                                <?= $invoice['due_amount'] > 0 ? 'Due: ₹' . number_format($invoice['due_amount'], 2) : 'Paid Full' ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.875rem 0.5rem;">
                                            <span class="badge"
                                                style="background: <?= $invoice['payment_status'] == 'paid' ? '#d1fae5; color:#059669;' : ($invoice['payment_status'] == 'partial' ? '#fef3c7; color:#d97706;' : ($invoice['payment_status'] == 'cancelled' ? '#f1f5f9; color:#64748b;' : '#fee2e2; color:#dc2626;')) ?>; font-size: 0.65rem; font-weight: 800;">
                                                <?= strtoupper($invoice['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: flex; gap: 0.4rem; justify-content: flex-end; flex-wrap:wrap;">
                                                <?php if (!in_array($invoice['payment_status'], ['paid', 'cancelled'])): ?>
                                                    <button
                                                        onclick="openPaymentModal(<?= $invoice['id'] ?>, <?= $invoice['due_amount'] ?>)"
                                                        class="btn btn-primary"
                                                        style="padding: 0.375rem 0.6rem; font-size: 0.7rem; height: 30px; background: #10b981; border: none; display:flex; align-items:center; gap:0.4rem;"
                                                        title="Record Payment">
                                                        <i class="fas fa-wallet"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="<?= APP_URL ?>/public/index.php/invoice_ledger?id=<?= $invoice['id'] ?>"
                                                    class="btn btn-ghost"
                                                    style="padding: 0.375rem 0.6rem; font-size: 0.7rem; border: 1px solid #e2e8f0; height: 30px; display:flex; align-items:center; gap:0.4rem; color: var(--accent, #8b5cf6);"
                                                    title="Payment Ledger">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                                <a href="<?= APP_URL ?>/public/assets/views/invoice_print.php?id=<?= $invoice['id'] ?>"
                                                    target="_blank" class="btn btn-ghost"
                                                    style="padding: 0.375rem 0.6rem; font-size: 0.7rem; border: 1px solid #e2e8f0; height: 30px; display:flex; align-items:center; gap:0.4rem;">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>

                                                <?php if (\Core\Auth::isAdmin() && $invoice['payment_status'] != 'cancelled'): ?>
                                                    <button onclick="cancelInvoice(<?= $invoice['id'] ?>)" class="btn btn-ghost"
                                                        style="padding: 0.375rem 0.6rem; font-size: 0.7rem; border: 1px solid #e2e8f0; height: 30px; display:flex; align-items:center; gap:0.4rem; color: #ef4444;"
                                                        title="Cancel Invoice">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($invoice['client_email']): ?>
                                                    <button onclick="shareViaEmail(<?= $invoice['id'] ?>, 'invoice', this)"
                                                        class="btn btn-ghost"
                                                        style="padding: 0.375rem 0.6rem; font-size: 0.7rem; border: 1px solid #e2e8f0; height: 30px; display:flex; align-items:center; gap:0.4rem; color: #3b82f6;">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($invoice['client_mobile']): ?>
                                                    <?php
                                                    $publicInvoiceUrl = APP_URL . "/public/assets/views/invoice_print.php?id=" . $invoice['id'] . "&token=" . md5($invoice['id'] . 'your-very-secure-secret-key-123456');
                                                    $waRecordData = [
                                                        'name' => trim($invoice['client_name']),
                                                        'service' => 'Invoice ' . $invoice['invoice_number'],
                                                        'status' => 'Due',
                                                        'value' => $invoice['due_amount'],
                                                        'link' => $publicInvoiceUrl,
                                                        'invoice_number' => $invoice['invoice_number'],
                                                        'msg' => "Here is your invoice " . $invoice['invoice_number'] . " for the amount of ₹" . number_format($invoice['due_amount'], 2) . ".",
                                                        'msg2' => "Please process the payment at your earliest convenience. Thank you for your business!"
                                                    ];
                                                    ?>
                                                    <button
                                                        onclick='openWAModal("invoice", <?= $invoice['id'] ?>, <?= json_encode($waRecordData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                        class="btn btn-ghost"
                                                        style="padding: 0.375rem 0.6rem; font-size: 0.7rem; border: 1px solid #e2e8f0; height: 30px; display:flex; align-items:center; gap:0.4rem; color: #22c55e;">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ($total_pages > 1): ?>
                        <div
                            style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: white;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing
                                    <?= count($invoices) ?> of <?= $total_items ?> invoices</span>
                                <form method="GET" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                    <?php foreach($_GET as $k => $v): if($k !== 'limit' && $k !== 'page'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                                    <?php endif; endforeach; ?>
                                    <select name="limit" onchange="this.form.submit()" style="padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #475569; background: #f8fafc; cursor: pointer;">
                                        <option value="15" <?= $limit_val == '15' ? 'selected' : '' ?>>15 per page</option>
                                        <option value="25" <?= $limit_val == '25' ? 'selected' : '' ?>>25 per page</option>
                                        <option value="50" <?= $limit_val == '50' ? 'selected' : '' ?>>50 per page</option>
                                        <option value="100" <?= $limit_val == '100' ? 'selected' : '' ?>>100 per page</option>
                                        <option value="all" <?= $limit_val === 'all' ? 'selected' : '' ?>>All</option>
                                    </select>
                                </form>
                            </div>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            if ($end_page - $start_page < 4) {
                                if ($start_page == 1) {
                                    $end_page = min($total_pages, 5);
                                } elseif ($end_page == $total_pages) {
                                    $start_page = max(1, $total_pages - 4);
                                }
                            }
                            $urlParams = "&range={$range}&status_filter={$status_filter}&search=" . urlencode($_GET['search'] ?? '') . "&limit=" . urlencode($limit_val);
                            ?>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?><?= $urlParams ?>" class="btn btn-ghost"
                                        style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: #0f172a;"><i
                                            class="fas fa-chevron-left"></i></a>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="?page=<?= $i ?><?= $urlParams ?>"
                                        class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?>"
                                        style="padding: 0.5rem 0.8rem; border: 1px solid <?= $i == $page ? 'var(--primary)' : '#e2e8f0' ?>; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: <?= $i == $page ? 'white' : '#0f172a' ?>; font-weight: 700; <?= $i == $page ? 'background: var(--primary);' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?><?= $urlParams ?>" class="btn btn-ghost"
                                        style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: #0f172a;"><i
                                            class="fas fa-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Bulk Export Invoices Modal -->
    <div id="invoiceExportModal" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width: 480px; border-radius: 1rem; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: #0f172a;">Bulk Export Invoices</h2>
                        <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Download invoices in Microsoft Excel (.xlsx) format</p>
                    </div>
                </div>
                <button type="button" onclick="closeExportModal()" class="btn-ghost" style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; text-transform: uppercase;">Export Scope</label>
                    <select id="exportScopeSelect" class="form-input" style="appearance: auto; width: 100%; height: 40px; font-size: 0.8125rem; font-weight: 600;" onchange="handleExportScopeChange(this.value)">
                        <option value="filter">Current Filter & Search (<?= $total_items ?> invoices)</option>
                        <option value="selected" id="exportScopeSelectedOpt" style="display:none;">Selected Invoices (<span id="modalSelectedCount">0</span> selected)</option>
                        <option value="month">Specific Month & Year</option>
                        <option value="custom">Custom Date Range</option>
                        <option value="all">All Invoices (All Time)</option>
                    </select>
                </div>

                <!-- Monthly Selector -->
                <div id="exportMonthBox" style="display: none; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">Month</label>
                        <select id="modalExportMonth" class="form-input" style="appearance: auto; height: 38px; font-size: 0.8125rem;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= date('n') == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">Year</label>
                        <select id="modalExportYear" class="form-input" style="appearance: auto; height: 38px; font-size: 0.8125rem;">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Custom Date Range -->
                <div id="exportCustomDateBox" style="display: none; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">From Date</label>
                        <input type="date" id="modalExportStart" value="<?= $start_date ?>" class="form-input" style="height: 38px; font-size: 0.8125rem;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">To Date</label>
                        <input type="date" id="modalExportEnd" value="<?= $end_date ?>" class="form-input" style="height: 38px; font-size: 0.8125rem;">
                    </div>
                </div>

                <!-- Payment Status Filter -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; text-transform: uppercase;">Payment Status</label>
                    <select id="modalExportStatus" class="form-input" style="appearance: auto; width: 100%; height: 40px; font-size: 0.8125rem;">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="paid">Paid Full Only</option>
                        <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial Paid Only</option>
                        <option value="dues" <?= $status_filter === 'dues' ? 'selected' : '' ?>>Pending Dues Only</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" onclick="closeExportModal()" class="btn btn-ghost" style="padding: 0.6rem 1.25rem; font-size: 0.8125rem;">Cancel</button>
                    <button type="button" onclick="executeXLSXExport()" class="btn btn-primary" style="background: #10b981; border-color: #10b981; padding: 0.6rem 1.5rem; font-size: 0.8125rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 700;">
                        <i class="fas fa-file-excel"></i> Download XLSX
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="paymentModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px;">
            <div
                style="padding: 1.5rem; border-bottom: 1px solid var(--border); display:flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 1.125rem; font-weight: 800;">Record Payment</h2>
                    <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Update the outstanding
                        balance</p>
                </div>
                <button onclick="document.getElementById('paymentModal').style.display='none'" class="btn-ghost">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="paymentForm" style="padding: 1.5rem;">
                <input type="hidden" name="id" id="payment_inv_id">
                <div style="margin-bottom: 1.25rem;">
                    <label>Remaining Due</label>
                    <div id="payment_due_display"
                        style="font-size: 1.25rem; font-weight: 800; color: var(--danger); margin-bottom: 0.5rem;">₹0.00
                    </div>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label>Payment Amount (₹)</label>
                    <input type="number" step="0.01" name="payment_amount" id="payment_amount_input" required
                        class="form-input" placeholder="0.00"
                        style="font-weight: 800; color: var(--success); font-size: 1rem;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label>Date</label>
                        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="form-input">
                    </div>
                    <div>
                        <label>Mode</label>
                        <select name="payment_mode" required class="form-input" style="appearance: auto;">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer / NEFT</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label>Reference # / UTR (Optional)</label>
                    <input type="text" name="reference_number" class="form-input" placeholder="e.g. UTR123456789">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="form-input" placeholder="Any notes...">
                </div>
                <div style="display:flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('paymentModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.625rem 1.5rem;">
                        <i class="fas fa-check"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Invoice Modal -->
    <div id="invoiceModal" class="modal-overlay"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; overflow-y:auto; padding: 2rem 0;">
        <div class="modal-content"
            style="max-width: 700px; background:white; margin: auto; border-radius: 1rem; position:relative;">
            <div
                style="padding: 1.5rem; border-bottom: 1px solid var(--border); display:flex; justify-content: space-between; align-items: center; position:sticky; top:0; background:white; z-index:10; border-radius: 1rem 1rem 0 0;">
                <div>
                    <h2 style="font-size: 1.125rem; font-weight: 800; letter-spacing: -0.02em;">Create New Invoice</h2>
                    <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Secure financial record
                        generation</p>
                </div>
                <button onclick="closeInvoiceModal()" class="btn-ghost"
                    style="width: 32px; height: 32px; border-radius: 50%; display:flex; align-items:center; justify-content:center; padding:0;">
                    <i class="fas fa-times" style="font-size: 0.875rem;"></i>
                </button>
            </div>

            <form id="invoiceForm" style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label>Invoice Number</label>
                        <input type="text" name="invoice_number" id="invoice_number_input" value="<?= htmlspecialchars($next_invoice_number) ?>" required class="form-input" readonly style="background: #f8fafc; cursor: not-allowed; border-style: dashed;">
                    </div>
                    <div>
                        <label>Client (Lead Name)</label>
                        <div id="leadSearchContainer" class="custom-select-container" style="position:relative;">
                            <div id="leadSelectedDisplay" class="form-input"
                                style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff;"
                                onclick="toggleLeadDropdown()">
                                <span id="selectedLeadText">-- Choose Client --</span>
                                <i class="fas fa-chevron-down" style="font-size:0.75rem; color:#94a3b8;"></i>
                            </div>
                            <div id="leadDropdown" class="custom-select-dropdown"
                                style="display:none; position:absolute; top:100%; left:0; width:100%; background:white; border:1px solid var(--border); border-radius:0.75rem; z-index:1000; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); margin-top:4px; max-height:300px; overflow-y:auto;">
                                <div
                                    style="padding:0.75rem; border-bottom:1px solid #f1f5f9; position:sticky; top:0; background:white; z-index:2;">
                                    <div style="position:relative;">
                                        <i class="fas fa-search"
                                            style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size: 0.75rem; color:#94a3b8;"></i>
                                        <input type="text" id="leadSearchInput"
                                            placeholder="Type client name, mobile or email to search..." class="form-input"
                                            style="padding-left: 2.25rem; font-size: 0.75rem; height: 34px; margin-bottom: 0;"
                                            autocomplete="off"
                                            oninput="filterLeadDropdown(this.value)">
                                    </div>
                                </div>
                                <div id="leadOptionsList">
                                    <div style="padding:1.5rem 1rem; text-align:center; color:#94a3b8; font-size:0.8125rem;">
                                        <i class="fas fa-search" style="display:block; font-size:1.25rem; margin-bottom:6px; opacity:0.4;"></i>
                                        Type client name, mobile or email to search database...
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="lead_id" id="lead_id_hidden" required>
                        </div>
                    </div>
                </div>

                <div id="draft_selector_container"
                    style="display: none; margin-bottom: 1rem; border: 1.5px dashed var(--primary); padding: 1rem; border-radius: 0.75rem; background: #f5f3ff;">
                    <label
                        style="color: var(--primary); font-weight: 800; display:flex; align-items:center; gap:0.5rem; margin-bottom: 0.5rem;">
                        <i class="fas fa-magic"></i> Proposals Found
                    </label>
                    <select id="draft_select" class="form-input" style="appearance: auto;"
                        onchange="applyDraft(this.value)">
                        <option value="">-- Select a Proposal to Auto-Fill --</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label><i class="fas fa-calendar-day" style="margin-right: 4px; font-size: 0.7rem;"></i> Invoice
                            Date</label>
                        <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required class="form-input">
                    </div>
                    <div>
                        <label><i class="fas fa-fingerprint" style="margin-right: 4px; font-size: 0.7rem;"></i> GST
                            Number</label>
                        <input type="text" name="gst_number" class="form-input" placeholder="e.g. 29AAAAA0000A1Z5">
                    </div>
                </div>

                <div
                    style="background: #f8fafc; padding: 1.25rem; border-radius: 0.75rem; margin-bottom: 1.25rem; border: 1px solid #e2e8f0; position:relative;">
                    <div
                        style="margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight:800; color:#1e293b; font-size: 0.8125rem;">Enable GST Tax</span>
                        <label class="switch">
                            <input type="checkbox" name="is_gst_enabled" id="is_gst_enabled_inv" value="1" checked
                                onchange="toggleGstEnable()">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div id="gst_options_container">
                        <div
                            style="margin-bottom: 1rem; display:flex; gap: 1rem; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
                            <label
                                style="margin-bottom:0; cursor:pointer; display:flex; align-items:center; gap:0.5rem;">
                                <input type="radio" name="gst_type" value="gst" checked
                                    onclick="toggleTaxFields('gst')">
                                CGST + SGST
                            </label>
                            <label
                                style="margin-bottom:0; cursor:pointer; display:flex; align-items:center; gap:0.5rem;">
                                <input type="radio" name="gst_type" value="igst" onclick="toggleTaxFields('igst')"> IGST
                                (Inter-state)
                            </label>
                        </div>
                    </div>

                    <div
                        style="margin-bottom: 1.5rem; background: white; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
                            <span style="font-weight: 800; color: #1e293b;"><i class="fas fa-list-ul"></i> Invoice
                                Items</span>
                            <button type="button" class="btn btn-ghost" onclick="addInvoiceItem()"
                                style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border: 1px solid #e2e8f0;">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </div>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 0.5rem;"
                            id="invoice_items_table">
                            <thead>
                                <tr style="border-bottom: 2px solid #e2e8f0;">
                                    <th
                                        style="text-align: left; padding: 0.5rem 0.25rem; font-size: 0.75rem; color: #64748b; text-transform:uppercase;">
                                        Description</th>
                                    <th
                                        style="text-align: right; padding: 0.5rem 0.25rem; font-size: 0.75rem; color: #64748b; width: 70px; text-transform:uppercase;">
                                        Qty</th>
                                    <th
                                        style="text-align: right; padding: 0.5rem 0.25rem; font-size: 0.75rem; color: #64748b; width: 110px; text-transform:uppercase;">
                                        Rate (₹)</th>
                                    <th
                                        style="text-align: right; padding: 0.5rem 0.25rem; font-size: 0.75rem; color: #64748b; width: 110px; text-transform:uppercase;">
                                        Amount</th>
                                    <th style="width: 30px;"></th>
                                </tr>
                            </thead>
                            <tbody id="invoice_items_body">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>

                        <datalist id="services_list">
                            <?php foreach ($requirements as $req): ?>
                                <option value="<?= htmlspecialchars($req['name']) ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                        <div>
                            <label>Base Amount (₹)</label>
                            <input type="number" step="0.01" name="subtotal" id="subtotal_input" required
                                class="form-input" placeholder="0.00"
                                style="font-weight: 700; font-size: 0.9rem; background: #f8fafc; cursor:not-allowed;"
                                readonly>
                        </div>
                        <div id="cgst_sgst_fields" style="display: contents;">
                            <div>
                                <label>SGST (%)</label>
                                <input type="number" step="0.01" name="sgst_percent" id="sgst_percent" value="9"
                                    class="form-input">
                            </div>
                            <div>
                                <label>CGST (%)</label>
                                <input type="number" step="0.01" name="cgst_percent" id="cgst_percent" value="9"
                                    class="form-input">
                            </div>
                        </div>
                        <div id="igst_fields" style="display: none;">
                            <div style="grid-column: span 2;">
                                <label>IGST (%)</label>
                                <input type="number" step="0.01" name="igst_percent" id="igst_percent" value="18"
                                    class="form-input">
                            </div>
                        </div>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1.5px solid #e2e8f0;">
                        <span style="font-size: 0.8125rem; font-weight: 700; color: #64748b;">GRAND TOTAL:</span>
                        <span id="total_display"
                            style="font-size: 1.25rem; font-weight: 800; color: var(--primary); letter-spacing: -0.02em;">₹0.00</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label>Discount</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <select id="discount_type" name="discount_type" class="form-input"
                                style="appearance: auto; width: 100px; font-weight: 700; padding-left: 0.5rem; padding-right: 0.5rem; cursor: pointer;">
                                <option value="fixed">Fixed (₹)</option>
                                <option value="percent">% (Percent)</option>
                            </select>
                            <input type="number" step="0.01" name="discount_value" id="discount_input" value="0.00"
                                class="form-input"
                                style="color: #64748b; font-weight: 700; background: #f8fafc; flex: 1;">
                        </div>
                        <input type="hidden" name="discount" id="final_discount_amount" value="0">
                    </div>
                    <div>
                        <label>Payment Received (₹)</label>
                        <input type="number" step="0.01" name="paid_amount" id="paid_amount_input" value="0.00"
                            class="form-input" style="color: var(--success); font-weight: 700;">
                    </div>
                    <div>
                        <label>Outstanding Balance</label>
                        <input type="text" id="due_display" value="₹0.00" class="form-input" readonly
                            style="background: #fff1f2; border-color: #fecaca; font-weight: 800; color: var(--danger); font-size: 0.9rem;">
                    </div>
                </div>

                <div
                    style="display:flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border);">
                    <button type="button" class="btn btn-ghost" onclick="closeInvoiceModal()"
                        style="font-weight: 600;">Cancel</button>
                    <button type="submit" class="btn btn-primary"
                        style="padding: 0.625rem 1.5rem; font-size: 0.875rem;">
                        <i class="fas fa-check-circle" style="font-size: 0.75rem;"></i> Generate & Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const requirementFees = {
            <?php foreach ($requirements as $req): ?>
                                    "<?= addslashes($req['name']) ?>": {
                    fee: <?= (float) $req['fee'] ?>,
                    desc: <?= json_encode($req['description'] ?? '') ?>
                },
            <?php endforeach; ?>
        };

        let currentLeadDrafts = [];

        async function handleLeadSelect(selectObj) {
            const id = selectObj.value;
            const requirements = selectObj.options[selectObj.selectedIndex].dataset.requirements || '';
            const container = document.getElementById('draft_selector_container');
            const select = document.getElementById('draft_select');
            const baseInput = document.getElementById('subtotal_input');
            const itemsBody = document.getElementById('invoice_items_body');

            // Clear existing items
            itemsBody.innerHTML = '';

            if (!id) {
                container.style.display = 'none';
                addInvoiceItem();
                updateCalculations();
                return;
            }

            // Auto-populate requirements
            if (requirements) {
                const reqArray = requirements.split('||');
                reqArray.forEach(reqStr => {
                    const [name, fee] = reqStr.split('::');
                    addInvoiceItem(name.trim(), 1, fee || '');
                });
            } else {
                addInvoiceItem();
            }

            fetch(`<?= APP_URL ?>/public/index.php/api/leads.php?id=${id}`)
                .then(r => r.json())
                .then(lead => {
                    if (lead && lead.deal_value > 0) {
                        // If we only have one item (default/req), maybe set its rate to deal_value?
                        // But usually, deal_value is the total. Let's not force it if items are present.
                        const firstRateInput = itemsBody.querySelector('.item-rate');
                        if (firstRateInput && itemsBody.children.length === 1 && !firstRateInput.value) {
                            firstRateInput.value = lead.deal_value;
                        }
                        updateCalculations();
                    }
                });

            fetch(`<?= APP_URL ?>/public/index.php/api/quotations.php?lead_id=${id}`)
                .then(r => r.json())
                .then(drafts => {
                    currentLeadDrafts = drafts;
                    if (drafts && drafts.length > 0) {
                        container.style.display = 'block';
                        select.innerHTML = '<option value="">-- Select a Proposal Draft --</option>';
                        drafts.forEach(d => {
                            select.innerHTML += `<option value="${d.id}">${d.quotation_number} - ₹${parseFloat(d.total_amount).toLocaleString('en-IN')}</option>`;
                        });
                        if (drafts.length === 1) {
                            select.value = drafts[0].id;
                            applyDraft(drafts[0].id);
                        }
                    } else {
                        container.style.display = 'none';
                    }
                });
        }

        function applyDraft(id) {
            if (!id) return;
            const draft = currentLeadDrafts.find(d => d.id == id);
            if (draft) {
                const itemsBody = document.getElementById('invoice_items_body');
                itemsBody.innerHTML = '';

                if (draft.description) {
                    try {
                        const items = JSON.parse(draft.description);
                        items.forEach(it => addInvoiceItem(it.name, it.qty, it.rate, it.note || ''));
                    } catch (e) {
                        console.error('Error parsing draft items:', e);
                        addInvoiceItem(draft.requirement_names || 'Consultancy', 1, draft.subtotal);
                    }
                } else {
                    addInvoiceItem(draft.requirement_names || 'Consultancy', 1, draft.subtotal);
                }

                document.getElementById('subtotal_input').value = draft.subtotal;
                document.getElementById('discount_input').value = draft.discount || 0;
                // Note: tax settings might need to be synced too if needed
                updateCalculations();
            }
        }

        function openPaymentModal(id, due) {
            document.getElementById('payment_inv_id').value = id;
            document.getElementById('payment_due_display').textContent = '₹' + due.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            document.getElementById('payment_amount_input').value = due;
            document.getElementById('paymentModal').style.display = 'flex';
        }

        document.getElementById('paymentForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/invoices.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Payment Error:', error);
            }
        });

        function openInvoiceModal() {
            // Open modal immediately to provide feedback
            document.getElementById('invoiceModal').style.display = 'flex';
            document.getElementById('invoice_items_body').innerHTML = '';
            addInvoiceItem();

            // Reset lead selection state
            document.getElementById('lead_id_hidden').value = '';
            document.getElementById('selectedLeadText').innerText = '-- Choose Client --';
            document.getElementById('leadSearchInput').value = '';
            document.getElementById('leadDropdown').style.display = 'none';
            resetLeadOptionsList();

            const dateInput = document.querySelector('input[name="invoice_date"]');
            if (dateInput) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }
            updateCalculations();
        }

        function closeInvoiceModal() {
            document.getElementById('invoiceModal').style.display = 'none';
            document.getElementById('invoiceForm').reset();
            document.getElementById('draft_selector_container').style.display = 'none';
            document.getElementById('invoice_items_body').innerHTML = '';

            // Reset lead selection state
            document.getElementById('lead_id_hidden').value = '';
            document.getElementById('selectedLeadText').innerText = '-- Choose Client --';
            document.getElementById('leadSearchInput').value = '';
            document.getElementById('leadDropdown').style.display = 'none';
            resetLeadOptionsList();

            updateCalculations();
        }

        function addInvoiceItem(name = '', qty = 1, rate = '', note = '') {
            const tbody = document.getElementById('invoice_items_body');
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #f1f5f9';
            tr.innerHTML = `
                <td style="padding: 0.5rem 0.25rem;">
                    <input type="text" class="form-input item-name" list="services_list" placeholder="Enter service..." value="${name}" required style="margin-bottom:0; font-size:0.8125rem; font-weight:700;" oninput="handleItemNameInput(this)">
                    <input type="text" class="item-note" value="${note}" placeholder="Detailed description..." style="width:100%; border:none; background:transparent; font-size:0.7rem; color:#64748b; margin-top:2px; padding:2px 0; outline:none;">
                </td>
                <td style="padding: 0.5rem 0.25rem;">
                    <input type="number" class="form-input item-qty" min="1" step="1" value="${qty}" required style="margin-bottom:0; text-align:right; font-size:0.8125rem; padding-right:0.25rem;" oninput="updateCalculations()">
                </td>
                <td style="padding: 0.5rem 0.25rem;">
                    <input type="number" class="form-input item-rate" min="0" step="0.01" value="${rate}" required style="margin-bottom:0; text-align:right; font-size:0.8125rem; padding-right:0.25rem;" oninput="updateCalculations()">
                </td>
                <td style="padding: 0.5rem 0.25rem; text-align:right; font-weight:700; color:#334155; font-size:0.8125rem;" class="item-amount">
                    ₹0.00
                </td>
                <td style="padding: 0.5rem 0.25rem; text-align:center;">
                    <button type="button" class="btn-ghost" onclick="this.closest('tr').remove(); updateCalculations();" style="color:var(--danger); padding:0.25rem; margin-bottom:0;">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            updateCalculations();
        }

        function handleItemNameInput(input) {
            const name = input.value.trim();
            if (requirementFees[name]) {
                const tr = input.closest('tr');
                tr.querySelector('.item-rate').value = requirementFees[name].fee;
                tr.querySelector('.item-note').value = requirementFees[name].desc;
                updateCalculations();
            }
        }

        function toggleGstEnable() {
            const isGst = document.getElementById('is_gst_enabled_inv').checked;
            document.getElementById('gst_options_container').style.display = isGst ? 'block' : 'none';
            const gstFields = document.getElementById('cgst_sgst_fields');
            const igstFields = document.getElementById('igst_fields');
            const taxRadio = document.querySelector('input[name="gst_type"]:checked');
            const taxType = taxRadio ? taxRadio.value : 'gst';

            if (!isGst) {
                gstFields.style.display = 'none';
                igstFields.style.display = 'none';
            } else {
                if (taxType === 'igst') {
                    gstFields.style.display = 'none';
                    igstFields.style.display = 'block';
                } else {
                    gstFields.style.display = 'contents';
                    igstFields.style.display = 'none';
                }
            }
            updateCalculations();
        }

        function toggleTaxFields(type) {
            const isGst = document.getElementById('is_gst_enabled_inv').checked;
            if (!isGst) return;

            const gstFields = document.getElementById('cgst_sgst_fields');
            const igstFields = document.getElementById('igst_fields');
            if (type === 'igst') {
                gstFields.style.display = 'none';
                igstFields.style.display = 'block';
            } else {
                gstFields.style.display = 'contents';
                igstFields.style.display = 'none';
            }
            updateCalculations();
        }

        function updateCalculations() {
            let subtotal = 0;
            document.querySelectorAll('#invoice_items_body tr').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
                const amount = qty * rate;
                row.querySelector('.item-amount').innerText = '₹' + amount.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                subtotal += amount;
            });
            document.getElementById('subtotal_input').value = subtotal.toFixed(2);

            const paid = parseFloat(document.getElementById('paid_amount_input').value) || 0;
            const isGst = document.getElementById('is_gst_enabled_inv').checked;
            const taxRadio = document.querySelector('input[name="gst_type"]:checked');
            const taxType = taxRadio ? taxRadio.value : 'gst';

            let taxAmt = 0;
            let sgstAmt = 0;
            let cgstAmt = 0;
            let igstAmt = 0;

            if (isGst) {
                if (taxType === 'gst') {
                    const sgstP = parseFloat(document.getElementById('sgst_percent').value) || 0;
                    const cgstP = parseFloat(document.getElementById('cgst_percent').value) || 0;
                    sgstAmt = (subtotal * sgstP) / 100;
                    cgstAmt = (subtotal * cgstP) / 100;
                    taxAmt = sgstAmt + cgstAmt;
                } else {
                    const igstP = parseFloat(document.getElementById('igst_percent').value) || 0;
                    igstAmt = (subtotal * igstP) / 100;
                    taxAmt = igstAmt;
                }
            }

            const discountInputVal = parseFloat(document.getElementById('discount_input').value) || 0;
            const discountType = document.getElementById('discount_type').value;

            let discount = 0;
            if (discountType === 'percent') {
                discount = (subtotal + taxAmt) * (discountInputVal / 100);
            } else {
                discount = discountInputVal;
            }

            document.getElementById('final_discount_amount').value = discount.toFixed(2);

            const total = (subtotal + taxAmt) - discount;
            const due = total - paid;

            document.getElementById('total_display').textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            document.getElementById('due_display').value = '₹' + due.toLocaleString('en-IN', { minimumFractionDigits: 2 });

            return { total, due, sgstAmt, cgstAmt, igstAmt, subtotal, paid, taxType };
        }

        ['subtotal_input', 'sgst_percent', 'cgst_percent', 'igst_percent', 'paid_amount_input', 'discount_input', 'discount_type'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', updateCalculations);
        });

        document.getElementById('invoiceForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const items = [];
            document.querySelectorAll('#invoice_items_body tr').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
                items.push({
                    name: row.querySelector('.item-name').value,
                    note: row.querySelector('.item-note').value,
                    qty: qty,
                    rate: rate,
                    amount: qty * rate
                });
            });

            if (items.length === 0) {
                alert('Please add at least one item to the invoice.');
                return;
            }

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            if (!data.lead_id) {
                alert('Please select a client (lead) for this invoice.');
                return;
            }

            const calc = updateCalculations();

            data.total_amount = calc.total;
            data.due_amount = calc.due;
            data.sgst = calc.sgstAmt;
            data.cgst = calc.cgstAmt;
            data.igst = calc.igstAmt;
            data.subtotal = calc.subtotal;
            data.paid_amount = calc.paid;
            data.gst_type = calc.taxType;
            data.is_gst_enabled = document.getElementById('is_gst_enabled_inv').checked ? 1 : 0;
            data.description = JSON.stringify(items);

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/invoices.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                let result;
                const text = await response.text();
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error("Invalid JSON from server. Response was: " + text);
                }

                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Invoice Error:', error);
                alert('Failed to save invoice. ' + error.message);
            }
        });

        let leadSearchTimer = null;

        function resetLeadOptionsList() {
            const list = document.getElementById('leadOptionsList');
            if (list) {
                list.innerHTML = `
                    <div style="padding:1.5rem 1rem; text-align:center; color:#94a3b8; font-size:0.8125rem;">
                        <i class="fas fa-search" style="display:block; font-size:1.25rem; margin-bottom:6px; opacity:0.4;"></i>
                        Type client name, mobile or email to search database...
                    </div>
                `;
            }
        }

        function toggleLeadDropdown() {
            const dropdown = document.getElementById('leadDropdown');
            const isVisible = dropdown.style.display === 'block';
            if (isVisible) {
                dropdown.style.display = 'none';
            } else {
                dropdown.style.display = 'block';
                const input = document.getElementById('leadSearchInput');
                input.focus();
                if (input.value.trim() !== '') {
                    filterLeadDropdown(input.value);
                }
            }
        }

        function filterLeadDropdown(query) {
            clearTimeout(leadSearchTimer);
            const trimmed = (query || '').trim();
            const list = document.getElementById('leadOptionsList');
            if (!list) return;

            if (!trimmed) {
                resetLeadOptionsList();
                return;
            }

            list.innerHTML = `
                <div style="padding:1.5rem 1rem; text-align:center; color:#64748b; font-size:0.8125rem;">
                    <i class="fas fa-spinner fa-spin" style="margin-right:6px; color:var(--primary);"></i>
                    Searching database...
                </div>
            `;

            leadSearchTimer = setTimeout(async () => {
                try {
                    const response = await fetch(`<?= APP_URL ?>/public/index.php/api/invoices.php?action=search_leads&q=${encodeURIComponent(trimmed)}`);
                    const leads = await response.json();

                    if (!Array.isArray(leads) || leads.length === 0) {
                        list.innerHTML = `
                            <div style="padding:1.5rem 1rem; text-align:center; color:#94a3b8; font-size:0.8125rem;">
                                <i class="fas fa-user-slash" style="display:block; font-size:1.25rem; margin-bottom:6px; opacity:0.4;"></i>
                                No matching clients found in database
                            </div>
                        `;
                        return;
                    }

                    list.innerHTML = '';
                    leads.forEach(lead => {
                        let reqDisplay = '';
                        if (lead.requirement_details) {
                            reqDisplay = lead.requirement_details.split('||').map(item => item.split('::')[0]).filter(Boolean).join(', ');
                        }

                        const opt = document.createElement('div');
                        opt.className = 'lead-option';
                        opt.style.cssText = 'padding:0.75rem 1rem; cursor:pointer; font-size:0.875rem; border-bottom:1px solid #f8fafc; transition:background 0.15s;';

                        let subDetails = [];
                        if (lead.mobile) {
                            subDetails.push(`<span style="font-size:0.75rem; color:#64748b;"><i class="fas fa-phone-alt" style="margin-right:4px;"></i>${escapeHtml(lead.mobile)}</span>`);
                        }
                        if (lead.email) {
                            subDetails.push(`<span style="font-size:0.75rem; color:#64748b;"><i class="fas fa-envelope" style="margin-right:4px;"></i>${escapeHtml(lead.email)}</span>`);
                        }
                        if (reqDisplay) {
                            subDetails.push(`<span style="font-size:0.75rem; color:var(--primary); font-weight:600;"><i class="fas fa-layer-group" style="font-size:0.65rem; margin-right:4px;"></i>${escapeHtml(reqDisplay)}</span>`);
                        }

                        opt.innerHTML = `
                            <div style="font-weight:600; color:#1e293b;">${escapeHtml(lead.name)}</div>
                            <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:center; margin-top:3px;">
                                ${subDetails.join('')}
                            </div>
                        `;

                        opt.addEventListener('click', () => {
                            const displayText = lead.name + (lead.mobile ? ` [${lead.mobile}]` : '');
                            selectLead(lead.id, displayText, lead.requirement_details || '');
                        });

                        list.appendChild(opt);
                    });
                } catch (err) {
                    console.error('Lead search error:', err);
                    list.innerHTML = `
                        <div style="padding:1.5rem 1rem; text-align:center; color:#ef4444; font-size:0.8125rem;">
                            <i class="fas fa-exclamation-triangle" style="display:block; font-size:1.25rem; margin-bottom:6px; opacity:0.6;"></i>
                            Failed to search database. Please try again.
                        </div>
                    `;
                }
            }, 250);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function selectLead(id, text, requirements) {
            document.getElementById('lead_id_hidden').value = id || '';
            document.getElementById('selectedLeadText').innerText = text || '-- Choose Client --';
            document.getElementById('leadDropdown').style.display = 'none';
            document.getElementById('leadSearchInput').value = '';
            resetLeadOptionsList();

            // Trigger the same logic as handleLeadSelect but with direct values
            handleLeadSelectManual(id, requirements);
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!document.getElementById('leadSearchContainer').contains(e.target)) {
                document.getElementById('leadDropdown').style.display = 'none';
            }
        });

        async function handleLeadSelectManual(id, requirements) {
            const container = document.getElementById('draft_selector_container');
            const select = document.getElementById('draft_select');
            const itemsBody = document.getElementById('invoice_items_body');

            // Clear existing items
            itemsBody.innerHTML = '';

            if (!id) {
                container.style.display = 'none';
                addInvoiceItem();
                updateCalculations();
                return;
            }

            // Auto-populate requirements
            if (requirements) {
                const reqArray = requirements.split('||');
                reqArray.forEach(reqStr => {
                    const parts = reqStr.split('::');
                    if (parts.length >= 2) {
                        addInvoiceItem(parts[0].trim(), 1, parts[1] || '', '');
                    }
                });
            } else {
                addInvoiceItem();
            }

            fetch(`<?= APP_URL ?>/public/index.php/api/leads.php?id=${id}`)
                .then(r => r.json())
                .then(lead => {
                    if (lead && lead.deal_value > 0) {
                        const firstRateInput = itemsBody.querySelector('.item-rate');
                        if (firstRateInput && itemsBody.children.length === 1 && !firstRateInput.value) {
                            firstRateInput.value = lead.deal_value;
                        }
                        updateCalculations();
                    }
                });

            fetch(`<?= APP_URL ?>/public/index.php/api/quotations.php?lead_id=${id}`)
                .then(r => r.json())
                .then(drafts => {
                    currentLeadDrafts = drafts;
                    if (drafts && drafts.length > 0) {
                        container.style.display = 'block';
                        select.innerHTML = '<option value="">-- Select a Proposal Draft --</option>';
                        drafts.forEach(d => {
                            select.innerHTML += `<option value="${d.id}">${d.quotation_number} - ₹${parseFloat(d.total_amount).toLocaleString('en-IN')}</option>`;
                        });
                        if (drafts.length === 1) {
                            select.value = drafts[0].id;
                            applyDraft(drafts[0].id);
                        }
                    } else {
                        container.style.display = 'none';
                    }
                });
        }

        document.getElementById('invoiceSearch').addEventListener('input', function (e) {
            const query = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });

        function cancelInvoice(id) {
            if (confirm("Are you sure you want to cancel this invoice? All related payments and commissions will be reverted.")) {
                fetch('<?= APP_URL ?>/public/index.php/api/invoices.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + data.error);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Failed to cancel invoice.');
                    });
            }
        }
    </script>

    <?php include 'partials/wa_modal.php'; ?>

    <script>
        async function shareViaEmail(id, type, btn) {
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/share.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, type: type })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Success: ' + result.message);
                    btn.innerHTML = '<i class="fas fa-check" style="color: #10b981;"></i> Sent';
                    setTimeout(() => { btn.innerHTML = originalHtml; btn.disabled = false; }, 3000);
                } else {
                    alert('Failed: ' + (result.error || 'Unknown error occurred'));
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            } catch (error) {
                alert('Network Error: Could not send email. Make sure SMTP is configured correctly.');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }
    </script>
    <script>
        function toggleAllInvoices(source) {
            const checkboxes = document.querySelectorAll('.invoice-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = source.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
            const container = document.getElementById('bulkActionsContainer');
            const countSpan = document.getElementById('bulkCount');
            const exportCountSpan = document.getElementById('bulkExportCount');
            
            if (checkedCount > 0) {
                container.style.display = 'flex';
                if (countSpan) countSpan.innerText = checkedCount;
                if (exportCountSpan) exportCountSpan.innerText = checkedCount;
            } else {
                container.style.display = 'none';
            }
        }

        function exportSelectedInvoicesXLSX() {
            const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one invoice to export.');
                return;
            }
            const ids = Array.from(checkboxes).map(cb => cb.value);
            window.location.href = `<?= APP_URL ?>/public/index.php/api/invoices.php?action=export_xlsx&ids=${ids.join(',')}`;
        }

        function openExportModal() {
            const checkedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
            const selectedOpt = document.getElementById('exportScopeSelectedOpt');
            const modalCount = document.getElementById('modalSelectedCount');
            const scopeSelect = document.getElementById('exportScopeSelect');

            if (checkedCount > 0) {
                if (selectedOpt) selectedOpt.style.display = '';
                if (modalCount) modalCount.innerText = checkedCount;
                scopeSelect.value = 'selected';
            } else {
                if (selectedOpt) selectedOpt.style.display = 'none';
                if (scopeSelect.value === 'selected') scopeSelect.value = 'filter';
            }
            handleExportScopeChange(scopeSelect.value);
            document.getElementById('invoiceExportModal').style.display = 'flex';
        }

        function closeExportModal() {
            document.getElementById('invoiceExportModal').style.display = 'none';
        }

        function handleExportScopeChange(scope) {
            const monthBox = document.getElementById('exportMonthBox');
            const customBox = document.getElementById('exportCustomDateBox');
            if (monthBox) monthBox.style.display = (scope === 'month') ? 'grid' : 'none';
            if (customBox) customBox.style.display = (scope === 'custom') ? 'grid' : 'none';
        }

        function executeXLSXExport() {
            const scope = document.getElementById('exportScopeSelect').value;
            const status = document.getElementById('modalExportStatus').value;
            let url = `<?= APP_URL ?>/public/index.php/api/invoices.php?action=export_xlsx&status_filter=${encodeURIComponent(status)}`;

            if (scope === 'selected') {
                const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
                if (checkboxes.length === 0) {
                    alert('No invoices selected.');
                    return;
                }
                const ids = Array.from(checkboxes).map(cb => cb.value);
                url += `&ids=${ids.join(',')}`;
            } else if (scope === 'month') {
                const month = document.getElementById('modalExportMonth').value;
                const year = document.getElementById('modalExportYear').value;
                url += `&scope=month&month=${month}&year=${year}`;
            } else if (scope === 'custom') {
                const start = document.getElementById('modalExportStart').value;
                const end = document.getElementById('modalExportEnd').value;
                url += `&scope=custom&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
            } else if (scope === 'all') {
                url += `&scope=all`;
            } else {
                // filter: use current page's filter and search
                const searchVal = document.getElementById('invoiceSearch')?.value || '<?= htmlspecialchars($_GET['search'] ?? '') ?>';
                url += `&scope=filter&start=<?= $start_date ?>&end=<?= $end_date ?>`;
                if (searchVal) {
                    url += `&search=${encodeURIComponent(searchVal)}`;
                }
            }

            window.location.href = url;
            closeExportModal();
        }

        function sendBulkWhatsApp() {
            const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one invoice.');
                return;
            }
            
            const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            // For bulk, we don't pass individual recordData, we let the backend dynamically generate it
            openWAModal('invoice', ids);
        }
    </script>
</body>
</html>