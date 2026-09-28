<?php
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';

use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();

// Handle Date Range
$range = $_GET['range'] ?? 'month';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

if ($range === 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($range === 'all') {
    $start_date = '2020-01-01';
    $end_date = date('Y-m-d', strtotime('+10 years'));
} elseif ($range === 'month_year') {
    $m = $_GET['month'] ?? date('m');
    $y = $_GET['year'] ?? date('Y');
    $start_date = "$y-$m-01";
    $end_date = date('Y-m-t', strtotime($start_date));
}

$sql = "SELECT 
            l.id as lead_id,
            l.name,
            l.mobile,
            l.category,
            l.referral_person,
            l.requirement as remark,
            l.task_status,
            l.created_at as entry_date,
            i.id as invoice_id,
            i.total_amount,
            i.due_amount,
            (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') 
             FROM requirements r 
             JOIN lead_requirements lr ON r.id = lr.requirement_id 
             WHERE lr.lead_id = l.id) as work_type
        FROM leads l
        LEFT JOIN invoices i ON l.id = i.lead_id
        WHERE l.company_id = ? 
          AND (l.assigned_employee_id IS NOT NULL OR l.status = 'won')
          AND l.created_at BETWEEN ? AND ?
        ORDER BY l.created_at DESC";

$data = $db->fetchAll($sql, [$company_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);

// Fetch all payments for these invoices
$invoice_ids = array_filter(array_column($data, 'invoice_id'));
$payments = [];
if (!empty($invoice_ids)) {
    $ids_placeholder = implode(',', array_fill(0, count($invoice_ids), '?'));
    $payments_raw = $db->fetchAll(
        "SELECT invoice_id, amount FROM invoice_payments WHERE invoice_id IN ($ids_placeholder) ORDER BY payment_date ASC",
        array_values($invoice_ids)
    );
    foreach ($payments_raw as $p) {
        $payments[$p['invoice_id']][] = $p['amount'];
    }
}

function normalizeReportTaskStatus(?string $status): string
{
    return [
        'done' => 'work_done',
        'delay' => 'work_pending',
        'pending' => 'not_started',
        '' => 'not_started',
    ][$status ?? ''] ?? $status;
}

function reportTaskStatusLabel(?string $status): string
{
    return [
        'work_done' => 'WORK DONE',
        'work_in_progress' => 'WORK IN PROGRESS',
        'work_pending' => 'WORK PENDING',
        'not_started' => 'NOT STARTED',
    ][normalizeReportTaskStatus($status)] ?? strtoupper(str_replace('_', ' ', $status ?? 'not_started'));
}

// Summary Totals
$total_revenue = 0;
$total_received = 0;
$total_dues = 0;
foreach ($data as $row) {
    $total_revenue += floatval($row['total_amount'] ?? 0);
    $total_dues += floatval($row['due_amount'] ?? 0);
    if ($row['invoice_id'] && isset($payments[$row['invoice_id']])) {
        $total_received += array_sum($payments[$row['invoice_id']]);
    }
}

// CSV Export Logic
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Report_Sheet_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/No', 'Client Name', 'Mobile', 'Org Type', 'Work Type', 'Ref By', 'Total Cost', 'Received', 'Dues', 'Remark', 'Date']);

    foreach ($data as $index => $row) {
        $received = ($row['invoice_id'] && isset($payments[$row['invoice_id']])) ? array_sum($payments[$row['invoice_id']]) : 0;
        fputcsv($output, [
            $index + 1,
            $row['name'],
            $row['mobile'],
            $row['category'],
            $row['work_type'],
            $row['referral_person'],
            $row['total_amount'] ?: 0,
            $received,
            $row['due_amount'] ?: 0,
            $row['remark'],
            date('d-m-Y', strtotime($row['entry_date']))
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Report Sheet | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --report-header: #800000;
            /* Maroon */
            --report-row-1: #ccff00;
            /* Neon Yellow/Green */
            --report-row-2: #00ff00;
            /* Green */
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .report-table th {
            background: var(--report-header);
            color: white;
            padding: 8px 4px;
            border: 1px solid #4a0000;
            text-transform: uppercase;
            font-weight: 800;
            text-align: center;
        }

        .report-table td {
            border: 1px solid #000;
            padding: 6px 4px;
            font-weight: 700;
            color: black;
            text-align: center;
        }

        .row-yellow {
            background: var(--report-row-1);
        }

        .row-green {
            background: var(--report-row-2);
        }

        /* Status Colors */
        .status-work-done {
            background: #00ff00 !important;
        }

        .status-work-in-progress {
            background: #ffff00 !important;
        }

        .status-work-pending {
            background: #ff4d4d !important;
            color: white !important;
        }

        .status-work-pending td {
            color: white !important;
            border-color: #800000 !important;
        }

        .summary-header {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .summary-box {
            display: flex;
            align-items: stretch;
            border: 2px solid #000;
            height: 40px;
        }

        .summary-label {
            background: blue;
            color: white;
            padding: 0 10px;
            display: flex;
            align-items: center;
            font-weight: 800;
            font-size: 0.75rem;
        }

        .summary-label.maroon {
            background: var(--report-header);
        }

        .summary-value {
            background: white;
            padding: 0 15px;
            display: flex;
            align-items: center;
            font-weight: 800;
            font-size: 0.9rem;
            color: red;
        }

        .summary-value.blue-text {
            color: blue;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
                padding: 0;
            }

            .report-table {
                box-shadow: none;
            }

            @page {
                size: landscape;
                margin: 10mm;
            }
        }

        .filter-bar {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>

<body style="background: #f1f5f9;">
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
        <?php include 'partials/topbar.php'; ?>
            <div class="filter-bar no-print">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <h2 style="font-weight: 800; font-size: 1rem;">Task Report Sheet</h2>
                    <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                        <select name="range" class="form-input" onchange="this.form.submit()"
                            style="font-size: 0.75rem; font-weight: 700; width: 110px;">
                            <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>This Month</option>
                            <option value="today" <?= $range == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="month_year" <?= $range == 'month_year' ? 'selected' : '' ?>>Specific Month
                            </option>
                            <option value="all" <?= $range == 'all' ? 'selected' : '' ?>>All Time</option>
                        </select>

                        <?php if ($range === 'month_year'): ?>
                            <select name="month" class="form-input" onchange="this.form.submit()"
                                style="font-size: 0.75rem; font-weight: 700; width: 100px;">
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $mv = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= $mv ?>" <?= ($_GET['month'] ?? date('m')) == $mv ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" class="form-input" onchange="this.form.submit()"
                                style="font-size: 0.75rem; font-weight: 700; width: 85px;">
                                <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                                    <option value="<?= $y ?>" <?= ($_GET['year'] ?? date('Y')) == $y ? 'selected' : '' ?>><?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                    </form>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="?action=export&range=<?= $range ?>&month=<?= $_GET['month'] ?? '' ?>&year=<?= $_GET['year'] ?? '' ?>"
                        class="btn btn-ghost"
                        style="border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.75rem;">
                        <i class="fas fa-file-excel"></i> Download Excel
                    </a>
                    <button onclick="window.print()" class="btn btn-primary"
                        style="font-weight: 700; font-size: 0.75rem;">
                        <i class="fas fa-file-pdf"></i> Make PDF
                    </button>
                </div>
            </div>

            <!-- Dashboard Summary Boxes -->
            <div class="summary-header">
                <div class="summary-box">
                    <div class="summary-label">Invoiced</div>
                    <div class="summary-value blue-text"><?= number_format($total_revenue, 0) ?></div>
                </div>
                <div class="summary-box">
                    <div class="summary-label maroon">Received</div>
                    <div class="summary-value"><?= number_format($total_received, 0) ?></div>
                </div>
                <div class="summary-box">
                    <div class="summary-label" style="background: red;">Dues</div>
                    <div class="summary-value blue-text" style="color: blue;"><?= number_format($total_dues, 0) ?></div>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th rowspan="2">S/No</th>
                            <th rowspan="2">ORG NAME/ CONTACT PERSON NAME</th>
                            <th rowspan="2">Mobile No</th>
                            <th rowspan="2">Org. Type</th>
                            <th rowspan="2">Work Type</th>
                            <th rowspan="2">Ref. By</th>
                            <th rowspan="2">Total Cost</th>
                            <th colspan="5">Received History (Rec.)</th>
                            <th rowspan="2">DUES</th>
                            <th rowspan="2">REMARK</th>
                            <th rowspan="2">STAGE/STATUS</th>
                            <th rowspan="2">DATE</th>
                        </tr>
                        <tr>
                            <th>Rec.</th>
                            <th>Rec.</th>
                            <th>Rec.</th>
                            <th>Rec.</th>
                            <th>Rec.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $index => $row):
                            $status = normalizeReportTaskStatus($row['task_status'] ?: 'not_started');
                            $row_class = '';
                            if ($status === 'work_done')
                                $row_class = 'status-work-done';
                            elseif ($status === 'work_in_progress')
                                $row_class = 'status-work-in-progress';
                            elseif ($status === 'work_pending')
                                $row_class = 'status-work-pending';
                        
                            $row_payments = $payments[$row['invoice_id']] ?? [];
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $index + 1 ?></td>
                                <td style="text-align: left; padding-left: 8px;"><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['mobile']) ?></td>
                                <td><?= htmlspecialchars($row['category'] ?: '—') ?></td>
                                <td style="text-align: left; padding-left: 8px;">
                                    <?= htmlspecialchars($row['work_type'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['referral_person'] ?: 'OWN') ?></td>
                                <td style="background: rgba(0,0,0,0.05); font-weight: 800;">
                                    ₹<?= number_format($row['total_amount'] ?: 0, 0) ?></td>

                                <!-- Last 5 Payments -->
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <td><?= isset($row_payments[$i]) ? number_format($row_payments[$i], 0) : '' ?></td>
                                <?php endfor; ?>

                                <td style="color: red;">
                                    <?= $row['due_amount'] > 0 ? number_format($row['due_amount'], 0) : '0' ?></td>
                                <td><?= htmlspecialchars($row['remark'] ?: '') ?></td>
                                <td>
                                    <span
                                        style="font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; background: rgba(0,0,0,0.1); text-transform: uppercase;">
                                        <?= htmlspecialchars(reportTaskStatusLabel($row['task_status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['entry_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="15" style="padding: 20px; background: white; color: #94a3b8;">No records found
                                    for this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>

</html>
