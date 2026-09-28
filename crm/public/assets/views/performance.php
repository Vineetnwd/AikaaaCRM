<?php
use Core\Database;
use Core\Auth;

if (!Auth::check()) {
    header("Location: " . APP_URL . "/public/index.php/login");
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();

// If user is admin/manager, they can view any employee. If executive, only themselves.
$is_admin = Auth::role() === 'admin' || Auth::role() === 'manager';
$employees = $db->fetchAll("SELECT id, name FROM employees WHERE company_id = ? AND status = 'active'", [$company_id]);

$selected_emp_id = $_GET['employee_id'] ?? (Auth::isExecutive() ? Auth::employeeId() : ($employees[0]['id'] ?? null));
$view_type = $_GET['view_type'] ?? 'daily';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_month = $_GET['month'] ?? date('Y-m');

if ($view_type === 'monthly') {
    $start_date = $selected_month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    $date_label = date('F Y', strtotime($start_date));
} else {
    $start_date = $selected_date;
    $end_date = $selected_date;
    $date_label = date('d M Y', strtotime($selected_date));
}

if (!$selected_emp_id) {
    die("No active employees found.");
}

$employee = $db->fetchOne("SELECT * FROM employees WHERE id = ? AND company_id = ?", [$selected_emp_id, $company_id]);
$linkedUser = $db->fetchOne("SELECT id FROM users WHERE (employee_id = ? OR emp_id = ?) AND company_id = ?", [$selected_emp_id, $selected_emp_id, $company_id]);
$linkedUserId = $linkedUser['id'] ?? null;

// Calculate Metrics for selected period
$metrics = [
    'conversion_rate' => 0,
    'task_completion_rate' => 0,
    'revenue_generated' => 0,
    'followups_count' => 0,
    'quotations_count' => 0
];

// Leads Metrics
$leads_data = $db->fetchOne("
    SELECT COUNT(*) as total, SUM(IF(status = 'won', 1, 0)) as won
    FROM leads 
    WHERE (assigned_employee_id = ? OR assigned_to = ?) AND company_id = ?
    " . ($view_type === 'monthly' ? " AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'" : " AND DATE(created_at) = '$start_date'") . "
", [$selected_emp_id, $linkedUserId, $company_id]);

if (($leads_data['total'] ?? 0) > 0) {
    $metrics['conversion_rate'] = round(($leads_data['won'] / $leads_data['total']) * 100, 1);
}

// Tasks Metrics
$tasks_data = $db->fetchOne("
    SELECT COUNT(*) as total, SUM(IF(task_status IN ('work_done', 'done'), 1, 0)) as done
    FROM leads 
    WHERE (assigned_employee_id = ? OR assigned_to = ?) AND company_id = ?
    " . ($view_type === 'monthly' ? " AND DATE(task_completed_at) BETWEEN '$start_date' AND '$end_date'" : " AND DATE(task_completed_at) = '$start_date'") . "
", [$selected_emp_id, $linkedUserId, $company_id]);

if (($tasks_data['total'] ?? 0) > 0) {
    $metrics['task_completion_rate'] = round(($tasks_data['done'] / $tasks_data['total']) * 100, 1);
}

// Revenue Generated (Total Payments Collected)
$revenue_data = $db->fetchOne("
    SELECT SUM(p.amount) as revenue
    FROM invoice_payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN leads l ON i.lead_id = l.id
    WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?) AND p.company_id = ? AND i.payment_status != 'cancelled'
    " . ($view_type === 'monthly' ? " AND DATE(p.payment_date) BETWEEN '$start_date' AND '$end_date'" : " AND DATE(p.payment_date) = '$start_date'") . "
", [$selected_emp_id, $linkedUserId, $company_id]);
$metrics['revenue_generated'] = $revenue_data['revenue'] ?? 0;

// Sales Value (Total Billed Invoices for Selected Period)
$sales_data = $db->fetchOne("
    SELECT SUM(i.total_amount) as sales
    FROM invoices i
    JOIN leads l ON i.lead_id = l.id
    WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?) AND i.company_id = ? AND i.payment_status != 'cancelled'
    " . ($view_type === 'monthly' ? " AND DATE(i.invoice_date) BETWEEN '$start_date' AND '$end_date'" : " AND DATE(i.invoice_date) = '$start_date'") . "
", [$selected_emp_id, $linkedUserId, $company_id]);
$metrics['monthly_sales'] = $sales_data['sales'] ?? 0;
$metrics['target_amount'] = $employee['target'] ?? 0;
$metrics['target_achievement'] = $metrics['target_amount'] > 0 ? round(($metrics['monthly_sales'] / $metrics['target_amount']) * 100, 1) : 0;

// Followups and Quotations Counts
if ($linkedUserId) {
    $metrics['followups_count'] = $db->fetchOne("SELECT COUNT(*) as count FROM lead_followups WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?", [$linkedUserId, $start_date, $end_date])['count'] ?? 0;
}
$metrics['quotations_count'] = $db->fetchOne("SELECT COUNT(*) as count FROM quotations q JOIN leads l ON q.lead_id = l.id WHERE l.assigned_employee_id = ? AND DATE(q.created_at) BETWEEN ? AND ?", [$selected_emp_id, $start_date, $end_date])['count'] ?? 0;

// Detailed Activities for the selected period
$activities = [];

// 1. Followups
if ($linkedUserId) {
    $fups = $db->fetchAll("
        SELECT 'followup' as type, CONCAT('Follow-up with ', l.name, ': ', f.remark) as description, f.created_at as time, f.status
        FROM lead_followups f
        JOIN leads l ON f.lead_id = l.id
        WHERE f.user_id = ? AND DATE(f.created_at) BETWEEN ? AND ?
    ", [$linkedUserId, $start_date, $end_date]);
    foreach ($fups as $f)
        $activities[] = $f;
}

// 2. Quotations
$quots = $db->fetchAll("
    SELECT 'quotation' as type, CONCAT('Created Quotation ', q.quotation_number, ' for ', l.name) as description, q.created_at as time, q.total_amount
    FROM quotations q
    JOIN leads l ON q.lead_id = l.id
    WHERE l.assigned_employee_id = ? AND DATE(q.created_at) BETWEEN ? AND ?
", [$selected_emp_id, $start_date, $end_date]);
foreach ($quots as $q)
    $activities[] = $q;

// 3. New Leads Added
$new_leads = $db->fetchAll("
    SELECT 'lead_added' as type, CONCAT('Added New Lead: ', name) as description, created_at as time
    FROM leads
    WHERE (assigned_employee_id = ? OR assigned_to = ?) AND DATE(created_at) BETWEEN ? AND ?
", [$selected_emp_id, $linkedUserId, $start_date, $end_date]);
foreach ($new_leads as $l)
    $activities[] = $l;

// 4. Tasks Completed
$tasks_done = $db->fetchAll("
    SELECT 'task_done' as type, CONCAT('Completed Task: ', requirement) as description, task_completed_at as time
    FROM leads
    WHERE (assigned_employee_id = ? OR assigned_to = ?) AND task_status IN ('work_done', 'done') AND DATE(task_completed_at) BETWEEN ? AND ?
", [$selected_emp_id, $linkedUserId, $start_date, $end_date]);
foreach ($tasks_done as $t)
    $activities[] = $t;

// 5. Leads Won
$won = $db->fetchAll("
    SELECT 'won' as type, CONCAT('Closed Deal: Won lead ', name) as description, updated_at as time
    FROM leads
    WHERE (assigned_employee_id = ? OR assigned_to = ?) AND status = 'won' AND DATE(updated_at) BETWEEN ? AND ?
", [$selected_emp_id, $linkedUserId, $start_date, $end_date]);
foreach ($won as $w)
    $activities[] = $w;

// 6. Payments
$payments = $db->fetchAll("
    SELECT 'payment' as type, CONCAT('Collected Payment ₹', p.amount, ' (Invoice: ', i.invoice_number, ')') as description, p.created_at as time
    FROM invoice_payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN leads l ON i.lead_id = l.id
    WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?) AND DATE(p.payment_date) BETWEEN ? AND ?
", [$selected_emp_id, $linkedUserId, $start_date, $end_date]);
foreach ($payments as $p)
    $activities[] = $p;

// Sort by time
usort($activities, function ($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Performance | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .performance-header {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem 2rem;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .emp-profile-mini {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .emp-avatar-big {
            width: 64px;
            height: 64px;
            border-radius: 1rem;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            transition: all 0.2s;
            cursor: pointer;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .metric-val {
            font-size: 1.5rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
        }

        .metric-lab {
            font-size: 0.7rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.5rem;
        }

        .timeline-container {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.75rem;
        }

        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 1px;
            background: #e2e8f0;
        }

        .activity-item {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .activity-dot {
            position: absolute;
            left: -2rem;
            top: 4px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            box-shadow: 0 0 0 1px #e2e8f0;
        }

        .activity-dot i {
            font-size: 0.5rem;
            color: white;
        }

        .activity-content {
            padding: 0.5rem 0.75rem;
            border-radius: 0.75rem;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .activity-content:hover {
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .activity-time {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 0.15rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-desc {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.4;
        }

        .export-btn-group {
            display: flex;
            align-items: center;
            gap: 0;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .export-btn-group button {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: white;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.15s;
        }

        .export-btn-group button:hover {
            background: #f1f5f9;
            color: var(--primary-hover, #4f46e5);
        }

        .export-btn-group button.export-pdf {
            border-right: 1px solid #e2e8f0;
        }

        .export-btn-group button.export-pdf:hover { color: #dc2626; }
        .export-btn-group button.export-csv:hover { color: #059669; }

        @media print {
            .app-container > aside,
            header.header,
            .performance-header form,
            .export-btn-group,
            #detailsModal {
                display: none !important;
            }

            body { background: white !important; }
            .main-content { padding: 0 !important; }
            .performance-header { box-shadow: none; }
            .metric-card { break-inside: avoid; }
        }

        @media (max-width: 1200px) {
            .metrics-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: 1fr 1fr;
            }

            .performance-header {
                flex-direction: column;
                gap: 1.5rem;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div>
                    <h1 class="page-title">Performance Insight</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Tracking daily
                        productivity and success</p>
                </div>
            </header>

            <div class="performance-header">
                <div class="emp-profile-mini">
                    <div class="emp-avatar-big">
                        <?php if ($employee['photo']): ?>
                            <img src="<?= (strpos($employee['photo'], 'http') === 0) ? $employee['photo'] : APP_URL . '/public/uploads/profiles/' . $employee['photo'] ?>"
                                style="width:100%;height:100%;object-fit:cover;border-radius:1rem;">
                        <?php else: ?>
                            <?= strtoupper(substr($employee['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 900; color: #0f172a; margin-bottom: 0.25rem;">
                            <?= htmlspecialchars($employee['name']) ?></h2>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <span
                                style="font-size:0.7rem; font-weight:800; background:#ede9fe; color:var(--accent-hover, #7c3aed); padding:3px 8px; border-radius:6px; text-transform:uppercase;"><?= htmlspecialchars($employee['designation'] ?: 'Member') ?></span>
                            <span
                                style="font-size:0.8125rem; color:#64748b; font-weight:600;"><?= htmlspecialchars($employee['employee_id'] ?: '#ID-TBD') ?></span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center;">
                    <form id="filterForm" style="display: flex; gap: 1rem;">
                        <?php if ($is_admin): ?>
                            <select name="employee_id" class="form-input" style="width: 180px; margin:0;"
                                onchange="this.form.submit()">
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $selected_emp_id == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <select name="view_type" class="form-input" style="width: 120px; margin:0;" onchange="toggleViewType(this.value); this.form.submit()">
                            <option value="daily" <?= $view_type === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="monthly" <?= $view_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        </select>
                        
                        <input type="date" name="date" id="dateInput" value="<?= $selected_date ?>" class="form-input"
                            style="width: 140px; margin:0; display: <?= $view_type === 'daily' ? 'block' : 'none' ?>;" onchange="this.form.submit()">
                            
                        <input type="month" name="month" id="monthInput" value="<?= $selected_month ?>" class="form-input"
                            style="width: 140px; margin:0; display: <?= $view_type === 'monthly' ? 'block' : 'none' ?>;" onchange="this.form.submit()">
                    </form>

                    <script>
                        function toggleViewType(type) {
                            document.getElementById('dateInput').style.display = type === 'daily' ? 'block' : 'none';
                            document.getElementById('monthInput').style.display = type === 'monthly' ? 'block' : 'none';
                        }
                    </script>

                    <!-- Export Buttons -->
                    <div class="export-btn-group">
                        <button class="export-pdf" onclick="exportPDF()" title="Export as PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="export-csv" onclick="exportCSV()" title="Export as CSV">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Metrics -->
            <div class="metrics-grid">
                <div class="metric-card" onclick="showDetails('leads')">
                    <div class="metric-icon" style="background: #eef2ff; color: var(--primary-hover, #4f46e5);"><i
                            class="fas fa-bullseye"></i></div>
                    <div class="metric-val"><?= $metrics['conversion_rate'] ?>%</div>
                    <div class="metric-lab">Win Rate</div>
                </div>
                <div class="metric-card" onclick="showDetails('tasks')">
                    <div class="metric-icon" style="background: #ecfdf5; color: #059669;"><i class="fas fa-tasks"></i>
                    </div>
                    <div class="metric-val"><?= $metrics['task_completion_rate'] ?>%</div>
                    <div class="metric-lab">Task Ratio</div>
                </div>
                <div class="metric-card" onclick="showDetails('sales')">
                    <div class="metric-icon" style="background: #fdf4ff; color: #a855f7;"><i class="fas fa-chart-line"></i></div>
                    <div class="metric-val">₹<?= number_format($metrics['monthly_sales'], 0) ?></div>
                    <div class="metric-lab">Sales (MTD)</div>
                    <div style="font-size: 0.6rem; color: #64748b; margin-top: 4px; font-weight: 700;">
                        Target: ₹<?= number_format($metrics['target_amount'], 0) ?>
                    </div>
                </div>
                <div class="metric-card" onclick="showDetails('achievement')">
                    <div class="metric-icon" style="background: #fffbeb; color: #d97706;"><i class="fas fa-trophy"></i></div>
                    <div class="metric-val"><?= $metrics['target_achievement'] ?>%</div>
                    <div class="metric-lab">Achievement</div>
                    <div style="width: 100%; background: #f1f5f9; height: 4px; border-radius: 2px; margin-top: 6px; overflow: hidden;">
                        <div style="width: <?= min(100, $metrics['target_achievement']) ?>%; background: #d97706; height: 100%;"></div>
                    </div>
                </div>
                <div class="metric-card" onclick="showDetails('revenue')">
                    <div class="metric-icon" style="background: #f0fdf4; color: #16a34a;"><i class="fas fa-coins"></i>
                    </div>
                    <div class="metric-val">₹<?= number_format($metrics['revenue_generated'], 0) ?></div>
                    <div class="metric-lab"><?= $view_type === 'monthly' ? 'Collected (MTD)' : 'Collected Today' ?></div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="timeline-container">
                <div class="timeline-header">
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 800; color: #0f172a;">
                            <?= $view_type === 'monthly' ? 'Monthly Activity Feed' : 'Daily Activity Feed' ?>
                        </h3>
                        <p style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Chronological report for
                            <?= $date_label ?></p>
                    </div>
                    <div
                        style="background: #f1f5f9; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; color: #475569;">
                        <?= count($activities) ?> Activities
                    </div>
                </div>

                <?php if (empty($activities)): ?>
                    <div style="text-align:center; padding: 5rem 2rem;">
                        <img src="https://illustrations.popsy.co/amber/no-messages-for-you.svg"
                            style="width: 200px; margin-bottom: 2rem; opacity: 0.6;">
                        <h4 style="font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">No activity found</h4>
                        <p style="color: #64748b; font-size: 0.875rem;">No work records were found for this employee on this
                            date.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($activities as $act):
                            $icon = 'fa-dot-circle';
                            $color = '#94a3b8';
                            if ($act['type'] == 'lead_added') {
                                $icon = 'fa-plus-circle';
                                $color = '#3b82f6';
                            }
                            if ($act['type'] == 'followup') {
                                $icon = 'fa-phone';
                                $color = '#ef4444';
                            }
                            if ($act['type'] == 'quotation') {
                                $icon = 'fa-file-invoice';
                                $color = '#f97316';
                            }
                            if ($act['type'] == 'task_done') {
                                $icon = 'fa-check-circle';
                                $color = 'var(--primary-hover, #4f46e5)';
                            }
                            if ($act['type'] == 'won') {
                                $icon = 'fa-trophy';
                                $color = '#059669';
                            }
                            if ($act['type'] == 'payment') {
                                $icon = 'fa-wallet';
                                $color = '#16a34a';
                            }
                            ?>
                            <div class="activity-item">
                                <div class="activity-dot" style="background: <?= $color ?>;"><i class="fas <?= $icon ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-time">
                                        <i class="far fa-clock"></i> <?= date('h:i A', strtotime($act['time'])) ?>
                                        <span
                                            style="margin-left:auto; text-transform:uppercase; font-size:0.6rem; background:rgba(0,0,0,0.05); padding:2px 6px; border-radius:4px;"><?= $act['type'] ?></span>
                                    </div>
                                    <div class="activity-desc"><?= htmlspecialchars($act['description']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Reuse the existing modal logic -->
    <div id="detailsModal" class="modal-overlay"
        onclick="if(event.target.id=='detailsModal') this.style.display='none'">
        <div class="modal-content" style="max-width: 800px; width: 95%;">
            <div
                style="padding:1.5rem; border-bottom: 1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                <h2 id="modalTitle" style="font-size: 1.125rem; font-weight: 800;">Metrics Details</h2>
                <button onclick="document.getElementById('detailsModal').style.display='none'" class="btn-ghost"><i
                        class="fas fa-times"></i></button>
            </div>
            <div id="modalContent" style="padding:1.5rem;"></div>
        </div>
    </div>

    <script>
        // ── Export Helpers ──────────────────────────────────────────────────────
        function exportPDF() {
            window.print();
        }

        function exportCSV() {
            const empName   = <?= json_encode($employee['name']) ?>;
            const empId     = <?= json_encode($employee['employee_id'] ?: 'N/A') ?>;
            const date      = <?= json_encode($selected_date) ?>;
            const metrics   = <?= json_encode($metrics) ?>;
            const activities = <?= json_encode(array_map(function($a) {
                return [
                    'time'        => date('h:i A', strtotime($a['time'])),
                    'type'        => $a['type'],
                    'description' => $a['description']
                ];
            }, $activities)) ?>;

            // Build CSV rows
            const rows = [];

            rows.push(['Employee Performance Report']);
            rows.push(['Employee', empName, 'ID', empId, 'Date', date]);
            rows.push([]);

            rows.push(['--- METRICS SUMMARY ---']);
            rows.push(['Win Rate (%)', metrics.conversion_rate]);
            rows.push(['Task Completion Rate (%)', metrics.task_completion_rate]);
            rows.push(['Follow-ups', metrics.followups_count]);
            rows.push(['Quotes Sent', metrics.quotations_count]);
            rows.push(['Total Revenue (₹)', metrics.revenue_generated]);
            rows.push([]);

            rows.push(['--- DAILY ACTIVITY FEED ---']);
            rows.push(['Time', 'Type', 'Description']);
            activities.forEach(a => rows.push([a.time, a.type, a.description]));

            const csvContent = rows.map(r =>
                r.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
            ).join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = `performance_${empName.replace(/\s+/g,'_')}_${date}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }

        // ── Details Modal ───────────────────────────────────────────────────────
        async function showDetails(type) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('modalContent');
            const title = document.getElementById('modalTitle');
            const empId = '<?= $selected_emp_id ?>';

            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

            const titles = { leads: 'Lead Performance', tasks: 'Task Completion', followups: 'Daily Follow-ups', quotations: 'Quotations Sent', revenue: 'Revenue Details' };
            title.textContent = titles[type] || 'Details';

            try {
                const res = await fetch(`<?= APP_URL ?>/public/index.php/api/performance_details.php?employee_id=${empId}&type=${type}`);
                const data = await res.json();

                if (data.length === 0) {
                    content.innerHTML = '<div style="text-align:center; padding:2rem; color:#94a3b8;">No records found.</div>';
                    return;
                }

                let html = '<table class="details-table" style="width:100%; border-collapse:collapse;"><thead><tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">';

                if (type === 'leads') {
                    html += '<th style="text-align:left; padding:12px;">Lead</th><th style="text-align:left; padding:12px;">Status</th><th style="text-align:left; padding:12px;">Category</th></tr></thead><tbody>';
                    data.forEach(r => html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:12px; font-weight:700;">${r.name}</td><td style="padding:12px;">${r.status.toUpperCase()}</td><td style="padding:12px;">${r.category}</td></tr>`);
                } else if (type === 'tasks') {
                    html += '<th style="text-align:left; padding:12px;">Lead</th><th style="text-align:left; padding:12px;">Requirement</th><th style="text-align:left; padding:12px;">Status</th></tr></thead><tbody>';
                    data.forEach(r => html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:12px; font-weight:700;">${r.name}</td><td style="padding:12px; font-size:0.8rem;">${r.requirement || '—'}</td><td style="padding:12px;">${(r.task_status || 'pending').toUpperCase()}</td></tr>`);
                } else if (type === 'followups') {
                    html += '<th style="text-align:left; padding:12px;">Lead</th><th style="text-align:left; padding:12px;">Remark</th><th style="text-align:left; padding:12px;">Time</th></tr></thead><tbody>';
                    data.forEach(r => html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:12px; font-weight:700;">${r.lead_name}</td><td style="padding:12px; font-size:0.8rem;">${r.remark || '—'}</td><td style="padding:12px;">${r.follow_up_time}</td></tr>`);
                } else if (type === 'quotations') {
                    html += '<th style="text-align:left; padding:12px;">Number</th><th style="text-align:left; padding:12px;">Lead</th><th style="text-align:left; padding:12px;">Amount</th></tr></thead><tbody>';
                    data.forEach(r => html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:12px; font-weight:700;">${r.quotation_number}</td><td style="padding:12px;">${r.lead_name}</td><td style="padding:12px;">₹${parseFloat(r.total_amount).toLocaleString()}</td></tr>`);
                } else if (type === 'revenue') {
                    html += '<th style="text-align:left; padding:12px;">Client</th><th style="text-align:left; padding:12px;">Invoice</th><th style="text-align:left; padding:12px;">Amount</th></tr></thead><tbody>';
                    data.forEach(r => html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:12px; font-weight:700;">${r.client_name}</td><td style="padding:12px;">${r.invoice_number}</td><td style="padding:12px; color:#16a34a; font-weight:800;">₹${parseFloat(r.amount).toLocaleString()}</td></tr>`);
                }

                html += '</tbody></table>';
                content.innerHTML = html;
            } catch (err) {
                content.innerHTML = '<div style="color:red; text-align:center; padding:2rem;">Error loading data.</div>';
            }
        }
    </script>
</body>

</html>