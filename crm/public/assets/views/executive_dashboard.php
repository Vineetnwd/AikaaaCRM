<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();
$empId = Auth::employeeId();
$userId = Auth::userId();

// Executive Dashboard Metrics
$targetAmount = 0;
$totalBilled = 0;
$totalLeads = 0;
$wonLeads = 0;
$lostLeads = 0;
$pendingLeads = 0;

if ($empId) {
    $emp = $db->fetchOne("SELECT target FROM employees WHERE id = ?", [$empId]);
    $targetAmount = floatval($emp['target'] ?? 0);
    
    $month = date('n');
    $year = date('Y');
    
    // Total Billed (from Invoices linked to leads assigned to this executive)
    $billed = $db->fetchOne("
        SELECT SUM(i.total_amount) as total 
        FROM invoices i 
        JOIN leads l ON i.lead_id = l.id 
        WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?)
        AND i.company_id = ? 
        AND i.payment_status != 'cancelled'
        AND MONTH(i.invoice_date) = ? 
        AND YEAR(i.invoice_date) = ?
    ", [$empId, $userId, $company_id, $month, $year]);
    $totalBilled = floatval($billed['total'] ?? 0);

    // Lead Stats for this month
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won,
            SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost,
            SUM(CASE WHEN status NOT IN ('won', 'lost') THEN 1 ELSE 0 END) as pending
        FROM leads l
        WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?)
        AND l.company_id = ?
        AND l.month = ?
        AND l.year = ?
    ", [$empId, $userId, $company_id, $month, $year]);

    $totalLeads = intval($stats['total'] ?? 0);
    $wonLeads = intval($stats['won'] ?? 0);
    $lostLeads = intval($stats['lost'] ?? 0);
    $pendingLeads = intval($stats['pending'] ?? 0);
}

$achievementPercent = $targetAmount > 0 ? round(($totalBilled / $targetAmount) * 100, 1) : 0;

// Monthly Revenue Trend (Last 6 Months)
$monthlyRevenue = $db->fetchAll("
    SELECT DATE_FORMAT(i.invoice_date, '%b') as month, 
           COALESCE(SUM(i.total_amount), 0) as total 
    FROM invoices i
    JOIN leads l ON i.lead_id = l.id
    WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?) 
    AND i.company_id = ? 
    AND i.payment_status != 'cancelled'
    AND i.invoice_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(i.invoice_date, '%Y-%m'), DATE_FORMAT(i.invoice_date, '%b')
    ORDER BY DATE_FORMAT(i.invoice_date, '%Y-%m') ASC
", [$empId, $userId, $company_id]);

// Recent Activities
$recentLeads = $db->fetchAll("
    SELECT l.* FROM leads l
    WHERE (l.assigned_employee_id = ? OR l.assigned_to = ?)
    AND l.company_id = ?
    ORDER BY l.created_at DESC
    LIMIT 5
", [$empId, $userId, $company_id]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');
        
        :root {
            --primary: var(--primary-hover, #4f46e5);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-body: #f8fafc;
        }

        body { font-family: 'Outfit', sans-serif; background: var(--bg-body); }

        .dashboard-container { padding: 2rem; }

        .welcome-header {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .welcome-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.03em;
        }

        .welcome-header p {
            color: #64748b;
            margin: 0.25rem 0 0 0;
            font-weight: 500;
        }

        /* Highlight Cards */
        .highlight-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .achievement-card {
            background: linear-gradient(135deg, var(--primary-hover, #4f46e5) 0%, var(--accent-hover, #7c3aed) 100%);
            color: white;
            border: none;
        }

        .card-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }

        .card-value {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin-bottom: 1rem;
        }

        .progress-container {
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            margin-top: 1.5rem;
        }

        .progress-bar {
            height: 100%;
            background: white;
            border-radius: 5px;
            transition: width 1s ease-out;
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card .card-value {
            font-size: 1.75rem;
            color: #0f172a;
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1.25rem;
        }

        /* Secondary Grid */
        .secondary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .mini-card {
            background: white;
            padding: 1.25rem;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mini-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .mini-info h4 { margin: 0; font-size: 0.7rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .mini-info p { margin: 0; font-size: 1.1rem; font-weight: 700; color: #0f172a; }

        /* Chart & Table */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .activity-item:last-child { border-bottom: none; }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animated { animation: slideUp 0.5s ease-out forwards; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>

            <div class="dashboard-container">
                <header class="welcome-header animated">
                    <div>
                        <h1>Hello, <?= explode(' ', Auth::userName())[0] ?>!</h1>
                        <p>Here's how your performance looks for <?= date('F Y') ?>.</p>
                    </div>
                    <div style="background: white; padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 0.875rem; color: #475569;">
                        <i class="far fa-calendar-alt" style="margin-right: 0.5rem; color: var(--primary);"></i>
                        <?= date('M d, Y') ?>
                    </div>
                </header>

                <!-- High Impact Stats -->
                <div class="highlight-grid animated" style="animation-delay: 0.1s;">
                    <div class="card achievement-card">
                        <div class="card-label">Target Achievement</div>
                        <div class="card-value"><?= $achievementPercent ?>%</div>
                        <div style="font-weight: 600; font-size: 0.9rem;">
                            You've achieved ₹<?= number_format($totalBilled) ?> out of your ₹<?= number_format($targetAmount) ?> goal.
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?= min(100, $achievementPercent) ?>%;"></div>
                        </div>
                        <i class="fas fa-rocket" style="position: absolute; right: -20px; bottom: -20px; font-size: 8rem; opacity: 0.1; transform: rotate(-15deg);"></i>
                    </div>

                    <div class="card stat-card">
                        <div class="icon-box" style="background: #ecfdf5; color: #10b981;">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="card-label" style="color: #64748b;">Total Billed</div>
                        <div class="card-value">₹<?= number_format($totalBilled) ?></div>
                        <div style="font-size: 0.75rem; color: #10b981; font-weight: 600;">
                            <i class="fas fa-arrow-up"></i> Current Month
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="icon-box" style="background: #fffbeb; color: #f59e0b;">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div class="card-label" style="color: #64748b;">Monthly Target</div>
                        <div class="card-value">₹<?= number_format($targetAmount) ?></div>
                        <div style="font-size: 0.75rem; color: #f59e0b; font-weight: 600;">
                            Keep Pushing!
                        </div>
                    </div>
                </div>

                <!-- Secondary Stats -->
                <div class="secondary-grid animated" style="animation-delay: 0.2s;">
                    <div class="mini-card">
                        <div class="mini-icon" style="background: #eef2ff; color: var(--primary, #6366f1);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="mini-info">
                            <h4>Total Leads</h4>
                            <p><?= $totalLeads ?></p>
                        </div>
                    </div>
                    <div class="mini-card">
                        <div class="mini-icon" style="background: #ecfdf5; color: #10b981;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="mini-info">
                            <h4>Won</h4>
                            <p><?= $wonLeads ?></p>
                        </div>
                    </div>
                    <div class="mini-card">
                        <div class="mini-icon" style="background: #fff1f2; color: #f43f5e;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="mini-info">
                            <h4>Lost</h4>
                            <p><?= $lostLeads ?></p>
                        </div>
                    </div>
                    <div class="mini-card">
                        <div class="mini-icon" style="background: #f0f9ff; color: #0ea5e9;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="mini-info">
                            <h4>Pending</h4>
                            <p><?= $pendingLeads ?></p>
                        </div>
                    </div>
                </div>

                <div class="content-grid animated" style="animation-delay: 0.3s;">
                    <div class="card">
                        <h3 class="section-title">
                            <i class="fas fa-chart-area" style="color: var(--primary);"></i>
                            Revenue Performance
                        </h3>
                        <div style="height: 300px;">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <h3 class="section-title">
                            <i class="fas fa-history" style="color: var(--warning);"></i>
                            Recent Leads
                        </h3>
                        <ul class="activity-list">
                            <?php foreach ($recentLeads as $l): ?>
                            <li class="activity-item">
                                <div class="status-dot" style="background: <?= $l['status'] == 'won' ? 'var(--success)' : ($l['status'] == 'lost' ? 'var(--danger)' : 'var(--warning)') ?>;"></div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.9rem; color: #1e293b;"><?= htmlspecialchars($l['name']) ?></div>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($l['mobile']) ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 700; font-size: 0.85rem; color: #0f172a;">₹<?= number_format($l['deal_value'] ?? 0) ?></div>
                                    <div style="font-size: 0.65rem; color: #94a3b8;"><?= date('d M', strtotime($l['created_at'])) ?></div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?= APP_URL ?>/public/index.php/leads" style="display: block; text-align: center; margin-top: 1.5rem; font-size: 0.8rem; font-weight: 700; color: var(--primary); text-decoration: none;">View All Leads <i class="fas fa-arrow-right" style="margin-left: 0.5rem;"></i></a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('revenueTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthlyRevenue, 'month')) ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?= json_encode(array_column($monthlyRevenue, 'total')) ?>,
                    borderColor: 'var(--primary-hover, #4f46e5)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: 'var(--primary-hover, #4f46e5)',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function(value) {
                                if (value >= 100000) return '₹' + (value/100000).toFixed(1) + 'L';
                                if (value >= 1000) return '₹' + (value/1000).toFixed(0) + 'k';
                                return '₹' + value;
                            }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>
