<?php
use Core\Database;
use Core\Auth;

if (!Auth::check()) {
    header("Location: " . APP_URL . "/public/index.php/login");
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();
$is_admin = Auth::role() === 'admin' || Auth::role() === 'manager';
$is_executive = Auth::role() === 'executive';

// Date range & filter variables
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';
$status_filter = $_GET['status'] ?? 'all'; // all, due, partial
$aging_filter = $_GET['aging'] ?? 'all'; // all, 0-30, 31-60, 61+
$search_query = $_GET['search'] ?? '';

// Build base conditions for metrics (not affected by filters, only by role assigned leads)
$stats_params = [$company_id];
$stats_exec_cond = "";
if ($is_executive) {
    $stats_exec_cond = " AND lead_id IN (SELECT id FROM leads WHERE assigned_to = ?)";
    $stats_params[] = Auth::userId();
}

// 1. Total Outstanding Dues Stats
$total_dues_row = $db->fetchOne(
    "SELECT SUM(due_amount) as total, COUNT(*) as count 
     FROM invoices 
     WHERE company_id = ? AND due_amount > 0 AND payment_status IN ('due', 'partial')" . $stats_exec_cond,
    $stats_params
);
$total_outstanding_amount = floatval($total_dues_row['total'] ?? 0);
$total_dues_count = intval($total_dues_row['count'] ?? 0);

// 2. Overdue Dues Stats (due date is in the past)
$overdue_row = $db->fetchOne(
    "SELECT SUM(due_amount) as total, COUNT(*) as count 
     FROM invoices 
     WHERE company_id = ? AND due_amount > 0 AND payment_status IN ('due', 'partial') AND due_date < CURDATE()" . $stats_exec_cond,
    $stats_params
);
$total_overdue_amount = floatval($overdue_row['total'] ?? 0);
$total_overdue_count = intval($overdue_row['count'] ?? 0);

// 3. Average Delay Days (days since due date for overdue invoices)
$avg_delay_row = $db->fetchOne(
    "SELECT AVG(DATEDIFF(CURDATE(), due_date)) as avg_delay 
     FROM invoices 
     WHERE company_id = ? AND due_amount > 0 AND payment_status IN ('due', 'partial') AND due_date < CURDATE()" . $stats_exec_cond,
    $stats_params
);
$avg_delay_days = round(floatval($avg_delay_row['avg_delay'] ?? 0), 1);

// Build parameterized query for the main table data list
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit_val = $_GET['limit'] ?? '15';
$limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
$offset = ($page - 1) * $limit;

$queryBase = "FROM invoices i LEFT JOIN leads l ON i.lead_id = l.id WHERE i.company_id = ? AND i.due_amount > 0 AND i.payment_status IN ('due', 'partial')";
$params = [$company_id];

if ($is_executive) {
    $queryBase .= " AND l.assigned_to = ?";
    $params[] = Auth::userId();
}

if (!empty($start_date) && !empty($end_date)) {
    $queryBase .= " AND i.invoice_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($status_filter === 'due') {
    $queryBase .= " AND i.payment_status = 'due'";
} elseif ($status_filter === 'partial') {
    $queryBase .= " AND i.payment_status = 'partial'";
}

if (!empty($search_query)) {
    $queryBase .= " AND (i.invoice_number LIKE ? OR l.name LIKE ? OR l.email LIKE ? OR l.mobile LIKE ?)";
    $search = "%$search_query%";
    $params = array_merge($params, [$search, $search, $search, $search]);
}

if ($aging_filter !== 'all') {
    if ($aging_filter === '0-30') {
        $queryBase .= " AND DATEDIFF(CURDATE(), i.due_date) BETWEEN 0 AND 30";
    } elseif ($aging_filter === '31-60') {
        $queryBase .= " AND DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60";
    } elseif ($aging_filter === '61+') {
        $queryBase .= " AND DATEDIFF(CURDATE(), i.due_date) >= 61";
    }
}

// Fetch company details for WhatsApp / CSV header
$company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$company_id]);

// CSV Export Trigger
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_invoices = $db->fetchAll("SELECT i.*, l.name as client_name, l.email as client_email, l.mobile as client_mobile, DATEDIFF(CURDATE(), i.due_date) as aging_days " . $queryBase . " ORDER BY i.due_date ASC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="outstanding_dues_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice Number', 'Client Name', 'Client Email', 'Client Mobile', 'Invoice Date', 'Due Date', 'Overdue Days', 'Total Amount', 'Paid Amount', 'Due Amount', 'Status']);

    foreach ($export_invoices as $inv) {
        $status_label = ($inv['payment_status'] === 'partial') ? 'Partially Paid' : 'Unpaid (Due)';
        fputcsv($output, [
            $inv['invoice_number'],
            $inv['client_name'] ?? 'N/A',
            $inv['client_email'] ?? 'N/A',
            $inv['client_mobile'] ?? 'N/A',
            $inv['invoice_date'],
            $inv['due_date'],
            $inv['aging_days'] > 0 ? $inv['aging_days'] . ' Days Overdue' : 'Due in ' . abs($inv['aging_days']) . ' Days',
            $inv['total_amount'],
            $inv['paid_amount'],
            $inv['due_amount'],
            $status_label
        ]);
    }
    fclose($output);
    exit;
}

$countQuery = "SELECT COUNT(*) as total " . $queryBase;
$total_items = $db->fetchOne($countQuery, $params)['total'] ?? 0;
$total_pages = max(1, ceil($total_items / $limit));

$filtered_invoices = $db->fetchAll("SELECT i.*, l.name as client_name, l.email as client_email, l.mobile as client_mobile, DATEDIFF(CURDATE(), i.due_date) as aging_days " . $queryBase . " ORDER BY i.due_date ASC LIMIT $limit OFFSET $offset", $params);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dues & Reminders | <?= htmlspecialchars($company['name'] ?? 'Aikaa CRM') ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .report-header {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            margin: -1.25rem -1.75rem 1.75rem -1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-details h4 {
            font-size: 0.7rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 0.375rem 0;
        }

        .stat-details p {
            font-size: 1.5rem;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .filter-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.01);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1.2fr 1.5fr 1.5fr auto;
            gap: 1rem;
            align-items: end;
        }

        .filter-form label {
            display: block;
            font-size: 0.65rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.375rem;
        }

        .table-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .dues-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .dues-table th {
            background: #f8fafc;
            padding: 0.5rem 0.75rem;
            text-align: left;
            font-size: 0.65rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }

        .dues-table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1e293b;
            vertical-align: middle;
        }

        .dues-table tr:last-child td {
            border-bottom: none;
        }

        .dues-table tr:hover td {
            background: #f8fafc;
        }

        /* Badges */
        .aging-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
        }

        .badge-overdue {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .badge-today {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #ffedd5;
        }

        .badge-upcoming {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #d1fae5;
        }

        .client-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .client-name {
            font-weight: 800;
            color: #0f172a;
        }

        .client-sub {
            font-size: 0.7rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .client-sub a {
            color: inherit;
            text-decoration: none;
        }

        .client-sub a:hover {
            color: var(--primary);
        }

        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .toast-card {
            background: white;
            color: #1e293b;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.8125rem;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--primary);
            min-width: 300px;
            pointer-events: auto;
            transform: translateX(120%);
            transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .toast-card.show {
            transform: translateX(0);
        }

        .toast-icon {
            font-size: 1.125rem;
        }

        .toast-success {
            border-left-color: #10b981;
        }

        .toast-error {
            border-left-color: #ef4444;
        }

        /* Interactive Reminder Button styles */
        .btn-reminder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #cbd5e1;
            background: white;
            color: #334155;
            padding: 0;
        }

        .btn-reminder:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .btn-reminder.sending {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn-reminder.success {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }

        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #25d366;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .btn-whatsapp:hover {
            background: #20ba5a;
            transform: scale(1.05);
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>

            <div class="report-header">
                <div>
                    <h1 class="page-title" style="margin-bottom: 0.25rem;">Dues & Aging Reminders</h1>
                    <p
                        style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">
                        Payment Tracking & Automated Client outreach</p>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-ghost"
                        style="font-size: 0.8125rem; border: 1px solid var(--border); background: white;">
                        <i class="fas fa-file-csv" style="color: #10b981; margin-right: 0.375rem;"></i> Export CSV
                    </a>
                </div>
            </div>

            <!-- Global Stats Panel -->
            <div class="stats-grid">
                <!-- 1. Total Outstanding -->
                <div class="stat-card" style="border-bottom: 3px solid var(--primary);">
                    <div class="stat-icon" style="background: #eef2ff; color: var(--primary);"><i
                            class="fas fa-wallet"></i></div>
                    <div class="stat-details">
                        <h4>Total Outstanding</h4>
                        <p>₹<?= number_format($total_outstanding_amount, 2) ?></p>
                    </div>
                </div>
                <!-- 2. Aging Invoices count -->
                <div class="stat-card" style="border-bottom: 3px solid var(--primary, #6366f1);">
                    <div class="stat-icon" style="background: #f0fdf4; color: var(--primary, #6366f1);"><i
                            class="fas fa-file-invoice-dollar"></i></div>
                    <div class="stat-details">
                        <h4>Aging Invoices</h4>
                        <p><?= $total_dues_count ?> Invoices</p>
                    </div>
                </div>
                <!-- 3. Overdue Dues -->
                <div class="stat-card" style="border-bottom: 3px solid #ef4444;">
                    <div class="stat-icon" style="background: #fee2e2; color: #ef4444;"><i
                            class="fas fa-circle-exclamation"></i></div>
                    <div class="stat-details">
                        <h4>Overdue Amount</h4>
                        <p>₹<?= number_format($total_overdue_amount, 2) ?></p>
                    </div>
                </div>
                <!-- 4. Avg Delay Days -->
                <div class="stat-card" style="border-bottom: 3px solid #f59e0b;">
                    <div class="stat-icon" style="background: #fff7ed; color: #f59e0b;"><i
                            class="fas fa-hourglass-half"></i></div>
                    <div class="stat-details">
                        <h4>Avg Delay Days</h4>
                        <p><?= $avg_delay_days ?> Days</p>
                    </div>
                </div>
            </div>

            <!-- Granular Filter Bar -->
            <div class="filter-card">
                <form action="" method="GET" class="filter-form">
                    <div>
                        <label for="search">Search Customer / Invoice</label>
                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($search_query) ?>"
                            placeholder="Search name, phone, invoice..." class="form-input"
                            style="margin: 0; width: 100%;">
                    </div>
                    <div>
                        <label for="status">Dues Status</label>
                        <select name="status" id="status" class="form-input" style="margin: 0; width: 100%;">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Outstanding</option>
                            <option value="due" <?= $status_filter === 'due' ? 'selected' : '' ?>>Fully Unpaid</option>
                            <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partially Unpaid
                            </option>
                        </select>
                    </div>
                    <div>
                        <label for="aging">Aging Bucket</label>
                        <select name="aging" id="aging" class="form-input" style="margin: 0; width: 100%;">
                            <option value="all" <?= $aging_filter === 'all' ? 'selected' : '' ?>>All Days</option>
                            <option value="0-30" <?= $aging_filter === '0-30' ? 'selected' : '' ?>>0 - 30 Days</option>
                            <option value="31-60" <?= $aging_filter === '31-60' ? 'selected' : '' ?>>31 - 60 Days</option>
                            <option value="61+" <?= $aging_filter === '61+' ? 'selected' : '' ?>>61+ Days Overdue</option>
                        </select>
                    </div>
                    <div>
                        <label for="start">Issued From</label>
                        <input type="date" name="start" id="start" value="<?= htmlspecialchars($start_date) ?>"
                            class="form-input" style="margin: 0; width: 100%;">
                    </div>
                    <div>
                        <label for="end">Issued To</label>
                        <input type="date" name="end" id="end" value="<?= htmlspecialchars($end_date) ?>"
                            class="form-input" style="margin: 0; width: 100%;">
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.625rem 1.25rem;"><i
                                class="fas fa-filter"></i> Apply</button>
                        <a href="dues_report" class="btn btn-ghost"
                            style="padding: 0.625rem 1rem; border: 1px solid var(--border); background: white;"><i
                                class="fas fa-undo"></i></a>
                    </div>
                </form>
            </div>

            <!-- Invoices Table list -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div id="bulkActionsContainer" style="display: none;">
                    <button onclick="sendBulkWhatsApp()" class="btn btn-primary"
                        style="padding: 0.5rem 1rem; background: #25d366; color: white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:5px; font-size: 0.8rem;">
                        <i class="fab fa-whatsapp"></i> Bulk WhatsApp (<span id="bulkCount">0</span>)
                    </button>
                </div>
            </div>
            <div class="table-card">
                <?php if (empty($filtered_invoices)): ?>
                    <div style="text-align: center; padding: 4rem 2rem; color: #94a3b8;">
                        <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1.25rem; opacity: 0.2;"></i>
                        <p style="font-size: 0.9375rem; font-weight: 700; color: #475569; margin: 0 0 0.5rem 0;">No
                            Outstanding Dues Found</p>
                        <p style="font-size: 0.8125rem; color: #64748b; margin: 0;">Try relaxing your filters or typing a
                            different search query.</p>
                    </div>
                <?php else: ?>
                    <table class="dues-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><input type="checkbox" id="selectAllInvoices"
                                        onchange="toggleAllInvoices(this)"></th>
                                <th style="width: 90px;">Invoice #</th>
                                <th>Client Details</th>
                                <th style="width: 120px;">Due Date</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Dues</th>
                                <th style="text-align: right; width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_invoices as $inv):
                                $due_amt = floatval($inv['due_amount']);
                                $total_amt = floatval($inv['total_amount']);
                                $paid_amt = floatval($inv['paid_amount']);
                                $aging_days = $inv['aging_days'];

                                // Prefilled text message for WhatsApp
                                $wa_text = "Dear " . ($inv['client_name'] ?? 'Client') . ",\n\nThis is a friendly reminder regarding your outstanding balance of ₹" . number_format($due_amt, 2) . " on invoice #" . $inv['invoice_number'] . " which was due on " . (!empty($inv['due_date']) ? date('d M, Y', strtotime($inv['due_date'])) : 'N/A') . ".\n\nPlease arrange for the payment at your earliest convenience. If you have already processed the payment, please disregard this message.\n\nThank you,\n" . ($company['name'] ?? 'Aikaa CRM');
                                $mobile_num = preg_replace('/[^0-9]/', '', $inv['client_mobile'] ?? '');
                                if (strlen($mobile_num) === 10) {
                                    $mobile_num = '91' . $mobile_num; // Prepends Indian country code as default
                                }
                                $wa_url = "https://wa.me/" . $mobile_num . "?text=" . urlencode($wa_text);
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="invoice-checkbox" value="<?= $inv['id'] ?>"
                                            onchange="updateBulkActions()"></td>
                                    <td style='min-width: 110px;'>
                                        <div style="font-weight: 800; color: var(--primary);">
                                            #<?= htmlspecialchars($inv['invoice_number']) ?></div>
                                        <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; margin-top: 2px;">
                                            <?= date('d M, Y', strtotime($inv['invoice_date'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="client-info">
                                            <span
                                                class="client-name"><?= htmlspecialchars($inv['client_name'] ?? 'Walk-in Customer') ?></span>
                                            <div class="client-sub">
                                                <?php if ($inv['client_email']): ?>
                                                    <span><i class="fas fa-envelope" style="font-size: 0.65rem;"></i> <a
                                                            href="mailto:<?= htmlspecialchars($inv['client_email']) ?>"><?= htmlspecialchars($inv['client_email']) ?></a></span>
                                                <?php endif; ?>
                                                <?php if ($inv['client_mobile']): ?>
                                                    <span><i class="fas fa-phone" style="font-size: 0.65rem;"></i>
                                                        <?= htmlspecialchars($inv['client_mobile']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($aging_days > 0): ?>
                                            <span class="aging-badge badge-overdue">
                                                <i class="fas fa-triangle-exclamation"></i> <?= $aging_days ?> Days Overdue
                                            </span>
                                        <?php elseif ($aging_days === 0): ?>
                                            <span class="aging-badge badge-today">
                                                <i class="fas fa-hourglass"></i> Due Today
                                            </span>
                                        <?php else: ?>
                                            <span class="aging-badge badge-upcoming">
                                                <i class="fas fa-calendar-day"></i> Due in <?= abs($aging_days) ?> Days
                                            </span>
                                        <?php endif; ?>
                                        <div
                                            style="font-size: 0.65rem; color: #64748b; font-weight: 700; margin-top: 4px; padding-left: 6px;">
                                            Due:
                                            <?= !empty($inv['due_date']) ? date('d M, Y', strtotime($inv['due_date'])) : 'N/A' ?>
                                        </div>
                                    </td>
                                    <td>₹<?= number_format($total_amt, 2) ?></td>
                                    <td style="color: #10b981;">₹<?= number_format($paid_amt, 2) ?></td>
                                    <td style="color: #ef4444; font-weight: 800; font-size: 0.875rem;">
                                        ₹<?= number_format($due_amt, 2) ?></td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                            <?php if ($inv['client_email']): ?>
                                                <button onclick="sendReminder(<?= $inv['id'] ?>, this)" class="btn-reminder"
                                                    title="Send Email Reminder">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-reminder" disabled style="opacity: 0.4; cursor: not-allowed;"
                                                    title="Email missing">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($inv['client_mobile']): ?>
                                                <button onclick="openWAModal('invoice', <?= $inv['id'] ?>)" class="btn-whatsapp"
                                                    title="Ping on WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-whatsapp"
                                                    style="opacity: 0.4; cursor: not-allowed; background: #64748b; pointer-events: none;"
                                                    title="Phone missing" disabled>
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($total_items > 0): ?>
                        <div
                            style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: white;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing
                                    <?= count($filtered_invoices) ?> of <?= $total_items ?> invoices</span>
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

                            <?php if ($total_pages > 1): ?>
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
                            $urlParams = "&status={$status_filter}&aging={$aging_filter}&start={$start_date}&end={$end_date}&search=" . urlencode($_GET['search'] ?? '') . "&limit=" . urlencode($limit_val);
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
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Container for dynamic sliding toasts -->
    <div id="toastContainer" class="toast-container"></div>

    <?php include 'partials/wa_modal.php'; ?>

    <script>
        // Sleek notification toast manager
        const toastContainer = document.getElementById('toastContainer');

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-card toast-${type}`;

            let icon = '<i class="fas fa-check-circle" style="color:#10b981;"></i>';
            if (type === 'error') {
                icon = '<i class="fas fa-times-circle" style="color:#ef4444;"></i>';
            }

            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <span style="flex:1;">${message}</span>
            `;

            toastContainer.appendChild(toast);

            // Trigger transition slide-in
            setTimeout(() => toast.classList.add('show'), 10);

            // Auto dismiss toast after 4 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 350);
            }, 4000);
        }

        // Handler for sending email reminders via AJAX
        async function sendReminder(invoiceId, btn) {
            if (btn.classList.contains('sending') || btn.classList.contains('success')) {
                return;
            }

            const originalHTML = btn.innerHTML;
            btn.classList.add('sending');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/send_due_reminder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ invoice_id: invoiceId })
                });

                const result = await response.json();

                if (result.success) {
                    btn.className = 'btn-reminder success';
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    showToast(result.message || 'Reminder email sent successfully!');

                    // Keep success state for 3 seconds, then restore button
                    setTimeout(() => {
                        btn.className = 'btn-reminder';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    }, 3000);
                } else {
                    showToast(result.error || 'Failed to send reminder email.', 'error');
                    btn.classList.remove('sending');
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            } catch (err) {
                showToast('Network error: ' + err.message, 'error');
                btn.classList.remove('sending');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        // Bulk Actions Logic
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

            if (checkedCount > 0) {
                container.style.display = 'block';
                countSpan.innerText = checkedCount;
            } else {
                container.style.display = 'none';
            }
        }

        function sendBulkWhatsApp() {
            const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one invoice.');
                return;
            }

            const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
            openWAModal('invoice', ids);
        }
    </script>
</body>

</html>