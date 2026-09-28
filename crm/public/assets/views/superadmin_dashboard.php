<?php
use Core\Database;
use Core\Auth;

if (!Auth::isSuperAdmin() || isset($_SESSION['original_user'])) {
    header("Location: " . APP_URL . "/public/index.php/dashboard");
    exit;
}

$db = Database::getInstance();
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_companies,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_companies,
        SUM(CASE WHEN plan = 'pro' THEN 1 ELSE 0 END) as pro_plans,
        SUM(CASE WHEN plan = 'trial' THEN 1 ELSE 0 END) as trial_plans
    FROM companies
");

$totalUsers = $db->fetchOne("SELECT COUNT(*) as total FROM users")['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: var(--primary, #6366f1);
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --radius-lg: 14px;
        }
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: var(--radius-lg);
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            color: #fff;
            box-shadow: 0 10px 25px rgba(15,23,42,0.15);
        }
        .hero h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -.02em; }
        .hero p { font-size: 0.85rem; opacity: 0.7; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.2s;
        }
        .stat:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }
        .stat-info { flex: 1; }
        .stat-label { font-size: 0.75rem; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
        .stat-value { font-size: 1.75rem; font-weight: 800; color: var(--text); letter-spacing: -.03em; }

        .quick-actions {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border);
            text-align: center;
        }
        .quick-actions h2 { font-size: 1.125rem; font-weight: 800; margin-bottom: 1.5rem; }
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
                        <h1>Platform Overview</h1>
                        <p>Welcome back, <?= htmlspecialchars(Auth::user()['name']) ?>. Here is your global SaaS summary.</p>
                    </div>
                    <a href="<?= APP_URL ?>/public/index.php/companies" class="btn btn-primary" style="background: white; color: var(--primary);">
                        <i class="fas fa-building"></i> Manage Companies
                    </a>
                </div>

                <div class="stats">
                    <div class="stat">
                        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Total Tenants</div>
                            <div class="stat-value"><?= number_format($stats['total_companies']) ?></div>
                        </div>
                    </div>
                    
                    <div class="stat">
                        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Active Companies</div>
                            <div class="stat-value"><?= number_format($stats['active_companies']) ?></div>
                        </div>
                    </div>

                    <div class="stat">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Total Users</div>
                            <div class="stat-value"><?= number_format($totalUsers) ?></div>
                        </div>
                    </div>

                    <div class="stat">
                        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-label">Pro Plans</div>
                            <div class="stat-value"><?= number_format($stats['pro_plans']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <h2>Ready to manage your SaaS?</h2>
                    <p style="color: var(--muted); margin-bottom: 1.5rem; font-size: 0.9rem;">To help a specific company or configure their limits, head over to the companies management page where you can use the "Login As" feature.</p>
                    <a href="<?= APP_URL ?>/public/index.php/companies" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                        Go to Company Management &#8594;
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
