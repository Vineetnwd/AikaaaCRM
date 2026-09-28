<?php
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';

use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();

// Handle Date Range
$range = $_GET['range'] ?? 'month';
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

if ($range === 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($range === 'month_year') {
    $start_date = "$selected_year-$selected_month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
} elseif ($range === 'all') {
    $start_date = '2020-01-01'; 
    $end_date = date('Y-m-d', strtotime('+10 years'));
}

$sql = "SELECT 
            l.id as lead_id,
            l.name,
            l.mobile,
            l.address,
            l.category,
            l.is_whatsapp,
            l.is_call,
            l.status,
            l.deal_value as work_confirm,
            l.created_at as entry_date,
            i.id as invoice_id,
            i.total_amount,
            i.due_amount,
            (SELECT lf.remark FROM lead_followups lf WHERE lf.lead_id = l.id ORDER BY lf.id DESC LIMIT 1) as last_response,
            (SELECT lf.call_status FROM lead_followups lf WHERE lf.lead_id = l.id ORDER BY lf.id DESC LIMIT 1) as latest_call_status,
            (SELECT u.name FROM lead_followups lf JOIN users u ON lf.user_id = u.id WHERE lf.lead_id = l.id ORDER BY lf.id DESC LIMIT 1) as staff_name,
            (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') 
             FROM requirements r 
             JOIN lead_requirements lr ON r.id = lr.requirement_id 
             WHERE lr.lead_id = l.id) as work_type
        FROM leads l
        LEFT JOIN invoices i ON l.id = i.lead_id
        WHERE l.company_id = ? 
          AND l.created_at BETWEEN ? AND ?
        ORDER BY l.created_at DESC";

$data = $db->fetchAll($sql, [$company_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

// Summary Totals
$total_confirmed = 0;
$total_collected = 0;
$total_rest = 0;

foreach ($data as $row) {
    $confirmed = floatval($row['work_confirm'] ?: 0);
    $due = floatval($row['due_amount'] ?: 0);
    $collected = ($row['invoice_id']) ? ($confirmed - $due) : 0;
    
    $total_confirmed += $confirmed;
    $total_collected += $collected;
    $total_rest += $due;
}

$month_name = date('F', strtotime($start_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Management Report | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --lead-red: #ff4d4d;
            --lead-blue: #0000ff;
            --lead-green: #00ff00;
            --lead-dark-green: #006400;
            --lead-yellow: #ffff00;
        }

        .report-header-banner {
            background: var(--lead-red);
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 1.5rem;
            font-weight: 800;
            text-transform: uppercase;
            border: 2px solid black;
            margin-bottom: 10px;
            font-family: 'Comic Sans MS', cursive, sans-serif; /* Matching screenshot style roughly */
        }

        .lead-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            background: white;
            border: 2px solid black;
        }

        .lead-table th {
            background: var(--lead-blue);
            color: white;
            padding: 8px 4px;
            border: 1px solid black;
            text-transform: uppercase;
            font-weight: 800;
            text-align: center;
        }

        .lead-table td {
            border: 1px solid black;
            padding: 6px 4px;
            font-weight: 700;
            text-align: center;
            color: black;
        }

        /* Summary Sections */
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            height: 100%;
        }

        .summary-box {
            border: 1px solid black;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px;
            font-weight: 800;
            color: white;
            text-align: center;
        }

        .box-confirm { background: var(--lead-green); color: black; }
        .box-collection { background: var(--lead-dark-green); }
        .box-target { background: var(--lead-blue); }

        .target-date-box {
            background: var(--lead-yellow);
            color: white;
            text-shadow: 2px 2px 2px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.2rem;
            border: 2px solid black;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            @page { size: landscape; margin: 5mm; }
        }

        .status-row-red { background: #fee2e2 !important; }
        .status-row-yellow { background: #fef3c7 !important; }
        .status-row-white { background: #ffffff !important; }
        
        .cat-bg-red { background: #fee2e2 !important; color: #000 !important; }
        .cat-bg-red td, .cat-bg-red div { color: #000 !important; }
        
        .cat-bg-green { background: #d1fae5 !important; color: #000 !important; }
        .cat-bg-green td, .cat-bg-green div { color: #000 !important; }
        
        .cat-bg-yellow { background: #fef3c7 !important; color: #000 !important; }
        .cat-bg-yellow td, .cat-bg-yellow div { color: #000 !important; }

        .cat-bg-blue { background: #dbeafe !important; }
        
        .badge-red { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; border: 1px solid #fecaca; }
        .badge-green { background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0; }
        .badge-blue { background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 4px; border: 1px solid #bfdbfe; }
    </style>
</head>
<body style="background: #f1f5f9;">
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
        <?php include 'partials/topbar.php'; ?>
            <div class="no-print" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <form method="GET" style="display: flex; gap: 10px;">
                    <select name="range" class="form-input" onchange="this.form.submit()">
                        <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="month_year" <?= $range == 'month_year' ? 'selected' : '' ?>>Specific Month</option>
                        <option value="all" <?= $range == 'all' ? 'selected' : '' ?>>All Time</option>
                    </select>
                    <?php if ($range == 'month_year'): ?>
                        <select name="month" class="form-input" onchange="this.form.submit()">
                            <?php for($m=1; $m<=12; $m++): $mv = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mv ?>" <?= $selected_month == $mv ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>
                </form>
                <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Report</button>
            </div>

            <div class="report-header-banner">
                LEAD MANAGEMENT <?= strtoupper($month_name) ?>
            </div>

            <div style="display: grid; grid-template-columns: 3fr 1fr; border: 2px solid black; margin-bottom: -2px;">
                <div style="overflow-x: auto;">
                    <table class="lead-table" style="border: none;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Number</th>
                                <th>Category</th>
                                <th>Staff</th>
                                <th>Status</th>
                                <th>Call Status</th>
                                <th>Latest Response</th>
                                <th>Work Type</th>
                                <th style="background: var(--lead-blue); border-bottom: none;">Target.</th>
                            </tr>
                            <tr style="background: #0000ff; color: white; font-size: 0.65rem;">
                                <td colspan="10" style="border: none;"></td>
                                <td style="border: 1px solid black; border-top: none;">for Company Capital</td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $index => $row): 
                                $cat_class = 'cat-bg-' . strtolower($row['category'] ?: 'white');
                                if ($row['status'] === 'lost') $cat_class = 'status-row-red';
                                if ($row['status'] === 'won') $cat_class = 'status-row-yellow';
                            ?>
                            <tr class="<?= $cat_class ?>">
                                <td style="font-size: 0.65rem; white-space: nowrap;"><?= date('d-M', strtotime($row['entry_date'])) ?></td>
                                <td style="text-align: left; padding-left: 5px; font-weight: 800;"><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['mobile']) ?></td>
                                <td><span class="badge-<?= strtolower($row['category']) ?>"><?= strtoupper($row['category']) ?></span></td>
                                <td style="color: var(--accent-hover, #7c3aed); font-weight: 800;"><?= htmlspecialchars($row['staff_name'] ?: '—') ?></td>
                                <td><?= strtoupper(str_replace('_', ' ', $row['status'])) ?></td>
                                <td style="font-size: 0.65rem; color: #059669;"><?= strtoupper(str_replace('_', ' ', $row['latest_call_status'] ?: '—')) ?></td>
                                <td style="font-size: 0.65rem; text-align: left; padding: 5px;"><?= htmlspecialchars($row['last_response'] ?: '—') ?></td>
                                <td style="font-size: 0.7rem; font-style: italic;"><?= htmlspecialchars($row['work_type'] ?: '—') ?></td>
                                <td style="background: white;"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="display: grid; grid-template-rows: 1fr 1.5fr;">
                    <div class="summary-grid">
                        <div class="summary-box box-confirm">
                            <span style="font-size: 0.6rem;">WORK CONFIRM</span>
                            <div style="font-size: 1rem; color: red; margin-top: 5px;"><?= number_format($total_confirmed, 0) ?></div>
                        </div>
                        <div class="summary-box box-collection">
                            <span style="font-size: 0.6rem;">COLLECTION</span>
                            <div style="font-size: 1rem; margin-top: 5px;"><?= number_format($total_collected, 0) ?></div>
                        </div>
                        <div class="summary-box box-target">
                            <span style="font-size: 0.6rem;">REST TARGET</span>
                            <div style="font-size: 1rem; margin-top: 5px;"><?= number_format($total_rest, 0) ?></div>
                        </div>
                    </div>
                    <div class="target-date-box">
                        <div style="font-size: 1.5rem;">Target</div>
                        <div style="font-size: 1.5rem;">Date</div>
                        <div style="font-size: 1rem; margin-top: 10px;"><?= date('d-M-Y', strtotime('+1 month')) ?></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
