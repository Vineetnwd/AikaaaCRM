<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();

/* ================= DATE RANGE ================= */
$range = $_GET['range'] ?? 'month';

switch ($range) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'all':
        $start_date = '2000-01-01';
        $end_date = date('Y-m-d', strtotime('+10 years'));
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

/* IMPORTANT FIX: correct date range */
$params = [$company_id, $start_date, $end_date];

/* ================= SALES ================= */
$sales = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) as won,
        SUM(CASE WHEN status='won' THEN deal_value ELSE 0 END) as revenue
    FROM leads
    WHERE company_id = ?
    AND created_at >= ?
    AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
", $params);

$totalLeads = $sales['total'] ?? 0;
$wonDeals   = $sales['won'] ?? 0;
$revenue    = $sales['revenue'] ?? 0;

/* ================= INVOICES ================= */
$pendingAmt = $db->fetchOne("
    SELECT COALESCE(SUM(due_amount), 0) as total 
    FROM invoices 
    WHERE company_id = ? 
    AND payment_status != 'cancelled'
    AND invoice_date >= ?
    AND invoice_date < DATE_ADD(?, INTERVAL 1 DAY)
", $params)['total'] ?? 0;

/* ================= FUNNEL (FIXED) ================= */
$funnelStats = $db->fetchAll("
    SELECT status, COUNT(*) as count 
    FROM leads 
    WHERE company_id = ?
    AND created_at >= ?
    AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY status
", $params);

/* ================= SOURCE (FIXED) ================= */
$sourceStats = $db->fetchAll("
    SELECT source, COUNT(*) as count 
    FROM leads 
    WHERE company_id = ?
    AND created_at >= ?
    AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY source 
    ORDER BY count DESC
", $params);

/* ================= TASK ================= */
$taskStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
    FROM tasks 
    WHERE company_id = ?
    AND created_at >= ?
    AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
", $params);

$totalTasks = $taskStats['total'] ?? 0;
$completedTasks = $taskStats['completed'] ?? 0;

/* TASK STATUS CHART FIX */
$taskStatusStats = $db->fetchAll("
    SELECT status, COUNT(*) as count 
    FROM tasks 
    WHERE company_id = ?
    AND created_at >= ?
    AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY status
", $params);

/* ================= REVENUE ================= */
/* keep your 6 month chart (UI unchanged) */
$monthlyRevenue = $db->fetchAll(
    "SELECT DATE_FORMAT(invoice_date, '%b') as month, 
            COALESCE(SUM(total_amount), 0) as total 
     FROM invoices 
     WHERE company_id = ? 
     AND payment_status != 'cancelled'
     AND invoice_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month 
     ORDER BY MIN(invoice_date)",
    [$company_id]
);

/* ================= RECENT ================= */
$recentLeads = $db->fetchAll(
    "SELECT l.*, u.name as assignee_name 
     FROM leads l 
     LEFT JOIN users u ON l.assigned_to = u.id 
     WHERE l.company_id = ? 
     ORDER BY l.created_at DESC 
     LIMIT 6",
    [$company_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary: var(--primary, #6366f1);
            --primary-light: #eef2ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --radius: 10px;
            --radius-lg: 14px;
        }

        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            color: #fff;
        }
        .hero h1 { font-size: 1.125rem; font-weight: 800; margin-bottom: 2px; letter-spacing: -.02em; }
        .hero p { font-size: .75rem; opacity: .6; }
        
        .range-select {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            color: #fff;
            border-radius: 8px;
            padding: .4rem .75rem;
            font-size: .8rem;
            cursor: pointer;
            outline: none;
        }
        .range-select option { background: #1e293b; }

        .tabs {
            display: flex;
            gap: .5rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: .4rem;
            margin-bottom: 1.5rem;
            overflow-x: auto;
        }
        .tab {
            padding: .5rem 1rem;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: .4rem;
            transition: .15s;
            border: none;
            background: transparent;
        }
        .tab.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 14px rgba(99, 102, 241, .25);
        }

        .panel { display: none; animation: fadeUp .25s ease; }
        .panel.active { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat {
            background: #fff;
            border-radius: var(--radius);
            padding: 1.1rem 1.25rem;
            border: 1px solid var(--border);
            border-left: 4px solid transparent;
        }
        .stat-label {
            font-size: .6rem;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .35rem;
        }
        .stat-value { font-size: 1.35rem; font-weight: 800; color: var(--text); letter-spacing: -.03em; }
        .stat-sub { font-size: .7rem; color: var(--muted); margin-top: .2rem; }

        .charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .card {
            background: #fff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 1.25rem;
        }
        .card h3 { font-size: .8rem; font-weight: 800; color: var(--text); margin-bottom: 1rem; }
        .chart-wrap { position: relative; height: 250px; width: 100%; }
        
        .chart-legend { display: flex; flex-wrap: wrap; gap: 10px; margin-top: .75rem; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: .7rem; color: var(--muted); }
        .legend-dot { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

        .table-card {
            background: #fff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-top: 1.5rem;
        }
        .table-card-header {
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
        }
        .table-card-header h2 { font-size: .875rem; font-weight: 800; }
        .view-all {
            font-size: .7rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: .25rem;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th {
            padding: .75rem 1.25rem;
            font-size: .6rem;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            text-align: left;
            border-bottom: 1.5px solid var(--border);
        }
        td { padding: .85rem 1.25rem; font-size: .8rem; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }
        .name-cell { font-weight: 700; }
        .sub-cell { font-size: .7rem; color: var(--muted); margin-top: 1px; }
        .badge { display: inline-flex; align-items: center; padding: .25rem .55rem; border-radius: 6px; font-size: .6rem; font-weight: 800; letter-spacing: .04em; }
        .badge-won { background: #d1fae5; color: #059669; }
        .badge-lost { background: #fee2e2; color: #dc2626; }
        .badge-default { background: #f1f5f9; color: #64748b; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>

            <div class="content" style="padding:0;">
                <div class="hero">
                    <div>
                        <h1>Analytics Command Center</h1>
                        <p>Intelligent performance insights for <?= htmlspecialchars(Auth::user()['name']) ?></p>
                    </div>
                    <form action="" method="GET">
                        <select name="range" class="range-select" onchange="this.form.submit()">
                            <option value="today" <?= $range == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="last_month" <?= $range == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>This Month</option>
                            <option value="all" <?= $range == 'all' ? 'selected' : '' ?>>All Time</option>
                        </select>
                    </form>
                </div>

                <div class="tabs">
                    <button class="tab active" onclick="switchTab('sales', this)"><span style="font-size: 14px;">&#9670;</span> Sales Overview</button>
                    <button class="tab" onclick="switchTab('tasks', this)"><span style="font-size: 14px;">&#9632;</span> Task Performance</button>
                    <button class="tab" onclick="switchTab('revenue', this)"><span style="font-size: 14px;">&#9711;</span> Revenue Analytics</button>
                </div>

                <div id="salesPanel" class="panel active">
                    <div class="stats">
                        <div class="stat" style="border-left-color: var(--primary)">
                            <div class="stat-label">Total Leads</div>
                            <div class="stat-value"><?= number_format($totalLeads) ?></div>
                            <div class="stat-sub">Tracked in this period</div>
                        </div>
                        <div class="stat" style="border-left-color: var(--success)">
                            <div class="stat-label">Won Deals</div>
                            <div class="stat-value"><?= number_format($wonDeals) ?></div>
                            <div class="stat-sub">Closed successfully</div>
                        </div>
                        <div class="stat" style="border-left-color: var(--info)">
                            <div class="stat-label">Conversion</div>
                            <div class="stat-value"><?= $totalLeads > 0 ? round(($wonDeals / $totalLeads) * 100, 1) : 0 ?>%</div>
                            <div class="stat-sub">Lead to Win rate</div>
                        </div>
                        <div class="stat" style="border-left-color: var(--warning)">
                            <div class="stat-label">Est. Revenue</div>
                            <div class="stat-value" id="s-rev-val"></div>
                            <div class="stat-sub">Won deal value</div>
                        </div>
                    </div>

                    <div class="charts">
                        <div class="card">
                            <h3>Lead Funnel</h3>
                            <div class="chart-wrap"><canvas id="funnelChart"></canvas></div>
                            <div class="chart-legend" id="funnelLegend"></div>
                        </div>
                        <div class="card">
                            <h3>Source Distribution</h3>
                            <div class="chart-wrap"><canvas id="sourceChart"></canvas></div>
                        </div>
                    </div>
                </div>

                <div id="tasksPanel" class="panel">
                    <div class="stats">
                        <div class="stat" style="border-left-color: var(--accent-hover, #7c3aed)">
                            <div class="stat-label">Total Tasks</div>
                            <div class="stat-value"><?= number_format($totalTasks) ?></div>
                        </div>
                        <div class="stat" style="border-left-color: var(--success)">
                            <div class="stat-label">Completed</div>
                            <div class="stat-value"><?= number_format($completedTasks) ?></div>
                        </div>
                        <div class="stat" style="border-left-color: var(--warning)">
                            <div class="stat-label">Completion Rate</div>
                            <div class="stat-value"><?= $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0 ?>%</div>
                        </div>
                        <div class="stat" style="border-left-color: var(--danger)">
                            <div class="stat-label">Pending</div>
                            <div class="stat-value"><?= number_format($totalTasks - $completedTasks) ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <h3>Task Status Breakdown</h3>
                        <div class="chart-wrap" style="height:300px"><canvas id="taskChart"></canvas></div>
                        <div class="chart-legend" id="taskLegend"></div>
                    </div>
                </div>

                <div id="revenuePanel" class="panel">
                    <div class="stats">
                        <div class="stat" style="border-left-color: #059669">
                            <div class="stat-label">Collected Revenue</div>
                            <div class="stat-value" id="r-col-val"></div>
                        </div>
                        <div class="stat" style="border-left-color: var(--danger)">
                            <div class="stat-label">Due Payments</div>
                            <div class="stat-value" id="r-due-val"></div>
                        </div>
                        <div class="stat" style="border-left-color: #2563eb">
                            <div class="stat-label">Avg Deal Value</div>
                            <div class="stat-value" id="r-avg-val"></div>
                        </div>
                        <div class="stat" style="border-left-color: var(--accent-hover, #7c3aed)">
                            <div class="stat-label">Potential Revenue</div>
                            <div class="stat-value" id="r-pot-val"></div>
                        </div>
                    </div>
                    <div class="card">
                        <h3>Revenue Performance (6 Months)</h3>
                        <div class="chart-wrap" style="height:300px"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>

                <div class="table-card">
                    <div class="table-card-header">
                        <h2>Recent Pipeline Updates</h2>
                        <a href="<?= APP_URL ?>/public/index.php/leads" class="view-all">Pipeline View &#8594;</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Lead Info</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Added On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLeads as $l): ?>
                            <tr>
                                <td>
                                    <div class="name-cell"><?= htmlspecialchars($l['name']) ?></div>
                                    <div class="sub-cell"><?= htmlspecialchars($l['assignee_name'] ?? 'Unassigned') ?></div>
                                </td>
                                <td style="font-weight:700">₹<?= number_format($l['deal_value'] ?? 0) ?></td>
                                <td>
                                    <span class="badge <?= $l['status'] == 'won' ? 'badge-won' : ($l['status'] == 'lost' ? 'badge-lost' : 'badge-default') ?>">
                                        <?= strtoupper($l['status']) ?>
                                    </span>
                                </td>
                                <td style="color:var(--muted)"><?= date('M d, Y', strtotime($l['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        const PHP_DATA = {
            revenue: <?= (float)$revenue ?>,
            pending: <?= (float)$pendingAmt ?>,
            wonDeals: <?= (int)$wonDeals ?>,
            funnel: {
                labels: <?= json_encode(array_column($funnelStats, 'status')) ?>,
                counts: <?= json_encode(array_column($funnelStats, 'count')) ?>
            },
            sources: {
                labels: <?= json_encode(array_map('ucfirst', array_column($sourceStats, 'source'))) ?>,
                counts: <?= json_encode(array_column($sourceStats, 'count')) ?>
            },
            tasks_stat: {
                labels: <?= json_encode(array_map('ucfirst', array_column($taskStatusStats, 'status'))) ?>,
                counts: <?= json_encode(array_column($taskStatusStats, 'count')) ?>
            },
            monthly: <?= json_encode($monthlyRevenue) ?>
        };

        const PALETTE = ['var(--primary, #6366f1)','#10b981','#f59e0b','#ef4444','#94a3b8'];
        const PALETTE2 = ['var(--accent-hover, #7c3aed)','#10b981','#f59e0b','#ef4444'];

        let charts = {};

        function fmt(n) {
            if (n >= 10000000) return '₹' + (n / 10000000).toFixed(1) + 'Cr';
            if (n >= 100000) return '₹' + (n / 100000).toFixed(1) + 'L';
            if (n >= 1000) return '₹' + (n / 1000).toFixed(0) + 'K';
            return '₹' + n;
        }

        function buildLegend(el, labels, colors) {
            el.innerHTML = labels.map((l, i) => `<span class="legend-item"><span class="legend-dot" style="background:${colors[i % colors.length]}"></span>${l}</span>`).join('');
        }

        function initCharts() {
            // Funnel
            charts.funnel = new Chart(document.getElementById('funnelChart'), {
                type: 'doughnut',
                data: {
                    labels: PHP_DATA.funnel.labels,
                    datasets: [{
                        data: PHP_DATA.funnel.counts,
                        backgroundColor: PALETTE,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + c.raw } } }
                }
            });
            buildLegend(document.getElementById('funnelLegend'), PHP_DATA.funnel.labels, PALETTE);

            // Source
            charts.source = new Chart(document.getElementById('sourceChart'), {
                type: 'bar',
                data: {
                    labels: PHP_DATA.sources.labels,
                    datasets: [{
                        label: 'Leads',
                        data: PHP_DATA.sources.counts,
                        backgroundColor: 'var(--primary, #6366f1)',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });

            // Tasks
            charts.task = new Chart(document.getElementById('taskChart'), {
                type: 'pie',
                data: {
                    labels: PHP_DATA.tasks_stat.labels,
                    datasets: [{
                        data: PHP_DATA.tasks_stat.counts,
                        backgroundColor: PALETTE2,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
            buildLegend(document.getElementById('taskLegend'), PHP_DATA.tasks_stat.labels, PALETTE2);

            // Revenue
            charts.revenue = new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: {
                    labels: PHP_DATA.monthly.map(x => x.month),
                    datasets: [{
                        label: 'Revenue',
                        data: PHP_DATA.monthly.map(x => x.total),
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,0.08)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#059669',
                        pointRadius: 4,
                        borderWidth: 2
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 }, callback: v => '₹' + (v / 100000).toFixed(0) + 'L' } },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        function switchTab(id, btn) {
            document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
            document.getElementById(id + 'Panel').classList.add('active');
            btn.classList.add('active');
        }

        function updateCurrencyStats() {
            document.getElementById('s-rev-val').innerHTML = fmt(PHP_DATA.revenue);
            document.getElementById('r-col-val').innerHTML = fmt(PHP_DATA.revenue);
            document.getElementById('r-due-val').innerHTML = fmt(PHP_DATA.pending);
            document.getElementById('r-avg-val').innerHTML = PHP_DATA.wonDeals > 0 ? fmt(Math.round(PHP_DATA.revenue / PHP_DATA.wonDeals)) : '₹0';
            document.getElementById('r-pot-val').innerHTML = fmt(PHP_DATA.revenue + PHP_DATA.pending);
        }

        window.onload = () => {
            initCharts();
            updateCurrencyStats();
        };
    </script>
</body>
</html>
