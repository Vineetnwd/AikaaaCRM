<?php
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
} elseif ($range === 'last_month') {
    $start_date = date('Y-m-01', strtotime('first day of last month'));
    $end_date = date('Y-m-t', strtotime('last day of last month'));
} elseif ($range === 'all') {
    $start_date = '2020-01-01'; 
    $end_date = date('Y-m-d', strtotime('+10 years'));
}

$params = [$company_id, $start_date, $end_date];

// Fetch Metrics
$totalLeads = $db->fetchOne("SELECT COUNT(*) as count FROM leads WHERE company_id = ? AND created_at BETWEEN ? AND ?", $params)['count'] ?? 0;
$wonLeads = $db->fetchOne("SELECT COUNT(*) as count FROM leads WHERE company_id = ? AND status = 'won' AND created_at BETWEEN ? AND ?", $params)['count'] ?? 0;
$conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 1) : 0;
$revenue = $db->fetchOne("SELECT SUM(total_amount - due_amount) as total FROM invoices WHERE company_id = ? AND payment_status != 'cancelled' AND invoice_date BETWEEN ? AND ?", $params)['total'] ?? 0;

// Lead Source Performance
$sourceData = $db->fetchAll("SELECT source, COUNT(*) as count FROM leads WHERE company_id = ? AND created_at BETWEEN ? AND ? GROUP BY source ORDER BY count DESC", $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .report-header {
            background: white;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            margin: -1.25rem -1.75rem 1.75rem -1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f8fafc;
            padding: 0.375rem 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
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
                    <h1 class="page-title" style="margin-bottom: 0.25rem;">Global Intelligence</h1>
                    <p style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Financial & Sales Reporting</p>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <form action="" method="GET" class="filter-group">
                        <i class="fas fa-calendar-alt" style="color: #64748b; font-size: 0.875rem;"></i>
                        <select name="range" class="form-input" style="padding: 0.25rem 0.75rem; border: none; background: transparent; width: 140px; font-weight: 700;" onchange="this.form.submit()">
                            <option value="today" <?= $range == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="last_month" <?= $range == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                            <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>This Month</option>
                            <option value="all" <?= $range == 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="custom" <?= $range == 'custom' ? 'selected' : '' ?>>Custom...</option>
                        </select>
                        <?php if ($range == 'custom'): ?>
                            <input type="date" name="start" value="<?= $start_date ?>" class="form-input" style="width: 130px; border: none; background: transparent;">
                            <input type="date" name="end" value="<?= $end_date ?>" class="form-input" style="width: 130px; border: none; background: transparent;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.7rem;">Apply</button>
                        <?php endif; ?>
                    </form>
                    <button class="btn btn-ghost" onclick="window.print()" style="font-size: 0.8125rem;"><i class="fas fa-print"></i></button>
                </div>
            </div>

            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="card stat-card" style="border-bottom: 3px solid #0ea5e9;">
                    <span class="stat-label">Lead Conversion Rate</span>
                    <div style="display:flex; align-items: baseline; gap: 0.5rem; margin-top: 0.5rem;">
                        <span class="stat-value"><?= $conversionRate ?>%</span>
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--success);"><i class="fas fa-caret-up"></i> Improved</span>
                    </div>
                </div>
                <div class="card stat-card" style="border-bottom: 3px solid var(--success);">
                    <span class="stat-label">Net Sales (Paid)</span>
                    <div style="display:flex; align-items: baseline; gap: 0.5rem; margin-top: 0.5rem;">
                        <span class="stat-value">₹<?= number_format($revenue, 0) ?></span>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b;">(<?= $range ?>)</span>
                    </div>
                </div>
                <div class="card stat-card" style="border-bottom: 3px solid var(--primary);">
                    <span class="stat-label">Total Prospects</span>
                    <div style="display:flex; align-items: baseline; gap: 0.5rem; margin-top: 0.5rem;">
                        <span class="stat-value"><?= $totalLeads ?></span>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Leads generated</span>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
                <h2 style="font-size: 0.9375rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-history" style="color: #ef4444;"></i> Payment Aging Analysis
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <?php
                    $aging_buckets = [
                        ['label' => '0-30 Days', 'query' => 'invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'],
                        ['label' => '31-60 Days', 'query' => 'invoice_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)'],
                        ['label' => '61+ Days', 'query' => 'invoice_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY)']
                    ];
                    foreach ($aging_buckets as $bucket):
                        $due = $db->fetchOne("SELECT SUM(due_amount) as total FROM invoices WHERE company_id = ? AND payment_status != 'paid' AND " . $bucket['query'], [Auth::companyId()])['total'] ?? 0;
                    ?>
                    <div style="padding: 1.25rem; background: #f8fafc; border-radius: 0.75rem; border: 1.5px solid #e2e8f0; text-align: center;">
                        <p style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem;"><?= $bucket['label'] ?></p>
                        <p style="font-size: 1.25rem; font-weight: 800; color: #1e293b;">₹<?= number_format($due, 0) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem;">
                <div class="card" style="padding: 1.5rem;">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h2 style="font-size: 0.9375rem; font-weight: 800;">Acquisition Performance</h2>
                        <span class="badge" style="background:var(--primary-soft); color:var(--primary); font-size: 0.6rem;">Source Analysis</span>
                    </div>
                    
                    <div style="display:flex; flex-direction: column; gap: 1.5rem;">
                        <?php if (empty($sourceData)): ?>
                            <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                                <i class="fas fa-chart-pie" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.2;"></i>
                                <p style="font-size: 0.8125rem;">No data available for this range.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($sourceData as $s): 
                                $p = $totalLeads > 0 ? ($s['count'] / $totalLeads) * 100 : 0;
                            ?>
                            <div>
                                <div style="display:flex; justify-content: space-between; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem;">
                                    <span><?= ucfirst($s['source']) ?></span>
                                    <span><?= $s['count'] ?> Leads</span>
                                </div>
                                <div style="width: 100%; height: 8px; background: #f1f5f9; border-radius: 20px; overflow: hidden;">
                                    <div style="width: <?= $p ?>%; height: 100%; background: var(--primary); border-radius: 20px;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" style="padding: 1.5rem; background: linear-gradient(135deg, white 0%, #f1f5f9 100%);">
                    <h2 style="font-size: 0.9375rem; font-weight: 800; margin-bottom: 1.5rem;">Smart Insights</h2>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="padding: 1rem; background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0;">
                            <p style="font-size: 0.65rem; color: var(--primary); font-weight: 800; text-transform: uppercase; margin-bottom: 0.375rem;">Top Channel</p>
                            <p style="font-size: 0.8125rem; font-weight: 600; color: #1e293b;"><?= $sourceData[0]['source'] ?? 'No data' ?></p>
                        </div>
                        <div style="padding: 1rem; background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0;">
                            <p style="font-size: 0.65rem; color: var(--success); font-weight: 800; text-transform: uppercase; margin-bottom: 0.375rem;">Efficiency</p>
                            <p style="font-size: 0.8125rem; font-weight: 600; color: #1e293b;">Conversion rate is at a healthy <?= $conversionRate ?>%.</p>
                        </div>
                    </div>
                    <div style="margin-top: 2rem; border-top: 1.5px solid #e2e8f0; padding-top: 1.5rem; text-align: center;">
                        <p style="font-size: 0.75rem; color: #64748b; font-weight: 500; margin-bottom: 1.25rem;">Want to generate a deeper CSV analysis?</p>
                        <button class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.625rem;">
                            <i class="fas fa-file-csv" style="font-size: 0.75rem;"></i> Full Export
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
>
    <script>
        document.getElementById('reportSearch').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.performance-grid .card div > div');
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                if (item.parentElement.style) {
                    item.parentElement.style.display = text.includes(query) ? '' : 'none';
                }
            });
        });
    </script>
</body>
</html>
