<?php
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';

use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();
$isExecutive = Auth::isExecutive();
$executiveEmployeeId = Auth::employeeId();

// Handle Date Range
$range = $_GET['range'] ?? 'month';
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

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

// Build Query
$sql = "SELECT 
            e.id AS employee_id,
            e.name AS employee_name,
            COUNT(l.id) AS total_tasks,
            SUM(IF(l.task_status IN ('work_done', 'done'), 1, 0)) AS completed_tasks,
            SUM(IF(l.task_status = 'work_in_progress', 1, 0)) AS in_progress_tasks,
            SUM(IF(l.task_status IN ('work_pending', 'delay'), 1, 0)) AS pending_tasks,
            SUM(IF(l.task_status IN ('not_started', 'pending', '') OR l.task_status IS NULL, 1, 0)) AS not_started_tasks,
            SUM(IF(
                l.task_status NOT IN ('work_done', 'done') 
                AND COALESCE(l.expected_delivery_date, l.follow_up_date) < CURRENT_DATE()
                AND COALESCE(l.expected_delivery_date, l.follow_up_date) IS NOT NULL, 1, 0
            )) AS overdue_tasks
        FROM employees e
        LEFT JOIN leads l ON e.id = l.assigned_employee_id 
            AND l.company_id = e.company_id
            AND l.created_at BETWEEN ? AND ?
            AND l.status != 'lost'
        WHERE e.company_id = ? AND e.status = 'active'";

$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $company_id];

if ($isExecutive && $executiveEmployeeId) {
    $sql .= " AND e.id = ?";
    $params[] = $executiveEmployeeId;
}

$sql .= " GROUP BY e.id, e.name ORDER BY e.name";

$data = $db->fetchAll($sql, $params);

// Calculate Totals
$totals = [
    'total_tasks' => 0,
    'completed_tasks' => 0,
    'in_progress_tasks' => 0,
    'pending_tasks' => 0,
    'not_started_tasks' => 0,
    'overdue_tasks' => 0
];

foreach ($data as $row) {
    $totals['total_tasks'] += $row['total_tasks'];
    $totals['completed_tasks'] += $row['completed_tasks'];
    $totals['in_progress_tasks'] += $row['in_progress_tasks'];
    $totals['pending_tasks'] += $row['pending_tasks'];
    $totals['not_started_tasks'] += $row['not_started_tasks'];
    $totals['overdue_tasks'] += $row['overdue_tasks'];
}

$totals['completion_rate'] = $totals['total_tasks'] > 0 
    ? round(($totals['completed_tasks'] / $totals['total_tasks']) * 100, 1) 
    : 0;

// CSV Export Logic
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Task_Performance_Report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S/No', 'Employee Name', 'Total Assigned Tasks', 'Work Done', 'Work In Progress', 'Work Pending', 'Not Started', 'Overdue', 'Completion Rate (%)']);

    foreach ($data as $index => $row) {
        $completion_rate = $row['total_tasks'] > 0 ? round(($row['completed_tasks'] / $row['total_tasks']) * 100, 1) : 0;
        fputcsv($output, [
            $index + 1,
            $row['employee_name'],
            $row['total_tasks'],
            $row['completed_tasks'],
            $row['in_progress_tasks'],
            $row['pending_tasks'],
            $row['not_started_tasks'],
            $row['overdue_tasks'],
            $completion_rate . '%'
        ]);
    }
    
    // Add Totals Row
    fputcsv($output, [
        '',
        'TOTAL',
        $totals['total_tasks'],
        $totals['completed_tasks'],
        $totals['in_progress_tasks'],
        $totals['pending_tasks'],
        $totals['not_started_tasks'],
        $totals['overdue_tasks'],
        $totals['completion_rate'] . '%'
    ]);
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Performance Report | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --report-header: var(--primary-hover, #4f46e5);
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .report-table th {
            background: var(--report-header);
            color: white;
            padding: 10px 8px;
            border: 1px solid #3730a3;
            text-transform: uppercase;
            font-weight: 800;
            text-align: center;
        }

        .report-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 8px;
            font-weight: 600;
            color: #1e293b;
            text-align: center;
        }

        .report-table tr:hover {
            background: #f8fafc;
        }
        
        .report-table tr.total-row {
            background: #f1f5f9;
            font-weight: 800;
        }
        
        .report-table tr.total-row td {
            color: #0f172a;
            border-top: 2px solid #cbd5e1;
        }

        .status-done { color: #166534; font-weight: 800; }
        .status-in-progress { color: #b45309; font-weight: 800; }
        .status-pending { color: #b91c1c; font-weight: 800; }
        .status-not-started { color: #475569; }
        .status-overdue { color: #dc2626; font-weight: 800; background: #fee2e2; border-radius: 4px; padding: 2px 6px; }

        .filter-bar {
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
        
        .summary-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .summary-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: 900;
            color: #0f172a;
        }
    </style>
</head>

<body style="background: #f1f5f9;">
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
        <?php include 'partials/topbar.php'; ?>
            <header class="header no-print" style="margin-bottom: 0; padding-bottom: 1rem; border-bottom: none;">
                <div>
                    <h1 class="page-title">Task Performance Report</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">
                        Monitor employee task execution and efficiency
                    </p>
                </div>
            </header>

            <div class="filter-bar no-print">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                        <select name="range" class="form-input" onchange="this.form.submit()"
                            style="font-size: 0.75rem; font-weight: 700; width: 120px;">
                            <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>This Month</option>
                            <option value="today" <?= $range == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="month_year" <?= $range == 'month_year' ? 'selected' : '' ?>>Specific Month</option>
                            <option value="all" <?= $range == 'all' ? 'selected' : '' ?>>All Time</option>
                        </select>

                        <?php if ($range === 'month_year'): ?>
                            <select name="month" class="form-input" onchange="this.form.submit()"
                                style="font-size: 0.75rem; font-weight: 700; width: 110px;">
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $mv = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?= $mv ?>" <?= ($_GET['month'] ?? date('m')) == $mv ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" class="form-input" onchange="this.form.submit()"
                                style="font-size: 0.75rem; font-weight: 700; width: 90px;">
                                <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                                    <option value="<?= $y ?>" <?= ($_GET['year'] ?? date('Y')) == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php endif; ?>
                    </form>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="?action=export&range=<?= $range ?>&month=<?= $_GET['month'] ?? '' ?>&year=<?= $_GET['year'] ?? '' ?>"
                        class="btn btn-ghost"
                        style="border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 0.75rem;">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </a>
                    <button onclick="window.print()" class="btn btn-primary"
                        style="font-weight: 700; font-size: 0.75rem;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;" class="no-print">
                <div class="summary-card">
                    <div class="summary-title">Total Tasks</div>
                    <div class="summary-value"><?= number_format($totals['total_tasks']) ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-title" style="color: #166534;">Work Done</div>
                    <div class="summary-value" style="color: #166534;"><?= number_format($totals['completed_tasks']) ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-title" style="color: #b91c1c;">Pending / Delayed</div>
                    <div class="summary-value" style="color: #b91c1c;"><?= number_format($totals['pending_tasks']) ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-title" style="color: #dc2626;">Overdue</div>
                    <div class="summary-value" style="color: #dc2626;"><?= number_format($totals['overdue_tasks']) ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-title" style="color: var(--primary-hover, #4f46e5);">Completion Rate</div>
                    <div class="summary-value" style="color: var(--primary-hover, #4f46e5);"><?= $totals['completion_rate'] ?>%</div>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">S/No</th>
                            <th style="text-align: left;">Employee Name</th>
                            <th>Total Assigned Tasks</th>
                            <th>Work Done</th>
                            <th>Work In Progress</th>
                            <th>Work Pending</th>
                            <th>Not Started</th>
                            <th>Overdue</th>
                            <th>Completion Rate</th>
                            <th style="width: 60px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $index => $row): 
                            $completion_rate = $row['total_tasks'] > 0 ? round(($row['completed_tasks'] / $row['total_tasks']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td style="text-align: left; font-weight: 700; color: #0f172a;">
                                    <?= htmlspecialchars($row['employee_name']) ?>
                                </td>
                                <td><?= $row['total_tasks'] ?></td>
                                <td class="status-done"><?= $row['completed_tasks'] ?></td>
                                <td class="status-in-progress"><?= $row['in_progress_tasks'] ?></td>
                                <td class="status-pending"><?= $row['pending_tasks'] ?></td>
                                <td class="status-not-started"><?= $row['not_started_tasks'] ?></td>
                                <td>
                                    <?php if ($row['overdue_tasks'] > 0): ?>
                                        <span class="status-overdue"><?= $row['overdue_tasks'] ?></span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <span style="font-weight: 800; color: <?= $completion_rate >= 80 ? '#166534' : ($completion_rate >= 50 ? '#b45309' : '#b91c1c') ?>;">
                                            <?= $completion_rate ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-primary" onclick="showEmployeeDetails(<?= $row['employee_id'] ?>, '<?= htmlspecialchars(addslashes($row['employee_name'])) ?>')" style="padding: 4px 10px; font-size: 0.75rem;">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="9" style="padding: 2rem; color: #94a3b8; font-weight: 600;">
                                    No records found for this period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <!-- Totals Row -->
                            <tr class="total-row">
                                <td colspan="2" style="text-align: right; padding-right: 1rem;">TOTALS</td>
                                <td><?= number_format($totals['total_tasks']) ?></td>
                                <td class="status-done"><?= number_format($totals['completed_tasks']) ?></td>
                                <td class="status-in-progress"><?= number_format($totals['in_progress_tasks']) ?></td>
                                <td class="status-pending"><?= number_format($totals['pending_tasks']) ?></td>
                                <td class="status-not-started"><?= number_format($totals['not_started_tasks']) ?></td>
                                <td>
                                    <?php if ($totals['overdue_tasks'] > 0): ?>
                                        <span class="status-overdue"><?= number_format($totals['overdue_tasks']) ?></span>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 800; color: <?= $totals['completion_rate'] >= 80 ? '#166534' : ($totals['completion_rate'] >= 50 ? '#b45309' : '#b91c1c') ?>;">
                                        <?= $totals['completion_rate'] ?>%
                                    </span>
                                </td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
        <div class="modal-container" style="background: white; border-radius: 1rem; width: 100%; max-width: 900px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div class="modal-header" style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 10;">
                <h2 id="modalTitle" style="font-size: 1.25rem; font-weight: 800; color: #0f172a;">Task Details</h2>
                <button onclick="document.getElementById('detailsModal').style.display='none'" class="btn-ghost" style="padding: 0.5rem;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="modalContent" style="padding: 1.5rem;">
                <!-- Data will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function normalizeTaskStatus(status) {
            const map = { pending: 'not_started', done: 'work_done', delay: 'work_pending', '': 'not_started' };
            return map[status || ''] || status;
        }

        function taskStatusLabel(status) {
            const labels = {
                not_started: 'NOT STARTED',
                work_in_progress: 'IN PROGRESS',
                work_pending: 'PENDING',
                work_done: 'DONE'
            };
            return labels[normalizeTaskStatus(status)] || String(status || 'not_started').replace(/_/g, ' ').toUpperCase();
        }

        function taskStatusBadge(status) {
            const normalized = normalizeTaskStatus(status);
            if (normalized === 'work_done') return '<span style="background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">' + taskStatusLabel(status) + '</span>';
            if (normalized === 'work_in_progress') return '<span style="background: #fef3c7; color: #b45309; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">' + taskStatusLabel(status) + '</span>';
            if (normalized === 'work_pending') return '<span style="background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">' + taskStatusLabel(status) + '</span>';
            return '<span style="background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">' + taskStatusLabel(status) + '</span>';
        }

        function extractRemark(fullRemark) {
            if (!fullRemark) return '—';
            const match = fullRemark.match(/^Task Status changed to [A-Z ]+:\s*(.*)$/i);
            return match ? match[1] : fullRemark;
        }

        async function showEmployeeDetails(employeeId, employeeName) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('modalContent');
            const title = document.getElementById('modalTitle');
            
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin fa-2x"></i><p style="margin-top:1rem;">Loading details...</p></div>';
            title.textContent = `Task Details - ${employeeName}`;

            const startDate = '<?= $start_date ?>';
            const endDate = '<?= $end_date ?>';

            try {
                const res = await fetch(`<?= APP_URL ?>/public/index.php/api/task_performance_details.php?employee_id=${employeeId}&start_date=${startDate}&end_date=${endDate}`);
                const data = await res.json();
                
                if (data.error) {
                    content.innerHTML = `<div style="color:red; text-align:center; padding:2rem;">${data.error}</div>`;
                    return;
                }

                if (data.length === 0) {
                    content.innerHTML = '<div style="text-align:center; padding:2rem; color:#94a3b8; font-weight:600;">No tasks found for this period.</div>';
                    return;
                }

                let html = `
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; text-transform: uppercase; font-weight: 800;">Task Name</th>
                                <th style="text-align: left; padding: 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; text-transform: uppercase; font-weight: 800;">Requirement</th>
                                <th style="text-align: left; padding: 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; text-transform: uppercase; font-weight: 800;">Status</th>
                                <th style="text-align: left; padding: 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; text-transform: uppercase; font-weight: 800;">Due Date</th>
                                <th style="text-align: left; padding: 10px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; text-transform: uppercase; font-weight: 800; width: 35%;">Latest Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.forEach(row => {
                    const remark = extractRemark(row.latest_remark);
                    html += `
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px; font-weight: 700; color: #0f172a;">${row.name}</td>
                            <td style="padding: 10px; color: #64748b;">${row.requirement || '—'}</td>
                            <td style="padding: 10px;">${taskStatusBadge(row.task_status)}</td>
                            <td style="padding: 10px; color: #475569;">${row.due_date ? new Date(row.due_date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}) : '—'}</td>
                            <td style="padding: 10px; color: #334155; font-size: 0.75rem; line-height: 1.4;">${remark}</td>
                        </tr>
                    `;
                });

                html += '</tbody></table>';
                content.innerHTML = html;

            } catch (err) {
                content.innerHTML = `<div style="color:red; text-align:center; padding:2rem;">Error loading task details.</div>`;
            }
        }
    </script>
</body>
</html>
