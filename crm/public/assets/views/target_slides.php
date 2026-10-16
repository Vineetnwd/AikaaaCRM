<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();

// Fetch company info for branding
$company = Auth::company();
$company_name = $company['name'] ?? 'Aikaaa CRM';
$company_logo = (!empty($company['logo_path'])) ? APP_URL . '/public/' . $company['logo_path'] : '';

// Fetch employees with target slide enabled
$sql = "SELECT e.id, e.name, e.photo, e.target, IFNULL(e.daily_work_status, '') as status,
               (SELECT id FROM users WHERE (employee_id = e.id OR emp_id = e.id) AND company_id = e.company_id LIMIT 1) as linked_user_id
        FROM employees e
        WHERE e.company_id = ? AND e.show_in_target_slide = 1 ORDER BY e.name ASC";
$employees = $db->fetchAll($sql, [$company_id]);

$billing_map = [];
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

foreach ($employees as $emp) {
    $emp_id = $emp['id'];
    $user_id = $emp['linked_user_id'];

    $billing_sql = "
        SELECT COALESCE(SUM(i.total_amount), 0) as total_billing
        FROM invoices i JOIN leads l ON i.lead_id = l.id
        WHERE i.company_id = ? AND i.payment_status != 'cancelled'
          AND DATE(i.invoice_date) BETWEEN ? AND ?
          AND (l.assigned_employee_id = ?" . ($user_id ? " OR l.assigned_to = ?" : "") . ")
    ";
    $params_b = $user_id ? [$company_id, $month_start, $month_end, $emp_id, $user_id] : [$company_id, $month_start, $month_end, $emp_id];
    $b = $db->fetchOne($billing_sql, $params_b);

    $collection_sql = "
        SELECT COALESCE(SUM(p.amount), 0) as total_collection
        FROM invoice_payments p JOIN invoices i ON p.invoice_id = i.id JOIN leads l ON i.lead_id = l.id
        WHERE p.company_id = ? AND i.payment_status != 'cancelled'
          AND DATE(p.payment_date) BETWEEN ? AND ?
          AND (l.assigned_employee_id = ?" . ($user_id ? " OR l.assigned_to = ?" : "") . ")
    ";
    $params_c = $user_id ? [$company_id, $month_start, $month_end, $emp_id, $user_id] : [$company_id, $month_start, $month_end, $emp_id];
    $c = $db->fetchOne($collection_sql, $params_c);

    $billing_map[$emp_id] = [
        'billing' => $b['total_billing'] ?? 0,
        'collection' => $c['total_collection'] ?? 0,
    ];
}

$month_name = strtoupper(date('F Y'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Performance Target</title>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --red: #e63946;
            --orange: #f4a261;
            --green: #2a9d8f;
            --blue: #457b9d;
            --bg: #f1f3f8;
            --white: #ffffff;
            --dark: #0d1b2a;
            --mid: #4a5568;
            --border: #e2e8f0;
        }

        html,
        body {
            height: 100%;
            width: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            overflow: hidden;
        }

        .app-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        .slideshow-wrapper {
            flex: 1;
            width: 100vw;
            overflow: hidden;
            position: relative;
        }

        /* ── Slideshow ── */
        .slideshow {
            display: flex;
            width: 100vw;
            height: 100%;
            transition: transform 0.8s cubic-bezier(0.7, 0, 0.3, 1);
        }

        .slide {
            flex: 0 0 100vw;
            width: 100vw;
            height: 100%;
            background: var(--bg);
        }

        .slide.active {
        }



        /* Choreographed internal animations */
        .slide .profile-panel {
            transform: translateY(40px);
            opacity: 0;
            transition: all 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) 0.1s;
        }

        .slide .stats-panel {
            transform: translateY(40px);
            opacity: 0;
            transition: all 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) 0.25s;
        }

        .slide .stat-box {
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .slide.active .profile-panel,
        .slide.active .stats-panel {
            transform: translateY(0);
            opacity: 1;
        }

        .slide.active .stat-box {
            transform: scale(1);
            opacity: 1;
        }

        /* Stagger stat boxes individually */
        .slide.active .stat-box:nth-child(1) {
            transition-delay: 0.3s;
        }

        .slide.active .stat-box:nth-child(2) {
            transition-delay: 0.4s;
        }

        .slide.active .stat-box:nth-child(3) {
            transition-delay: 0.5s;
        }


        /* ── Top Bar ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: clamp(0.75rem, 2vw, 1.25rem) clamp(1rem, 3vw, 2.5rem);
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand img {
            height: 36px;
            width: 36px;
            object-fit: contain;
            border-radius: 6px;
        }

        .brand-name {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            font-weight: 800;
            color: var(--dark);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .month-tag {
            background: var(--red);
            color: white;
            padding: 0.4rem 1.25rem;
            border-radius: 9999px;
            font-size: clamp(0.75rem, 1.5vw, 0.9rem);
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .month-tag2 {
            background: var(--red);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            font-size: clamp(1.2rem, 3vw, 2.2rem);
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: center;
        }

        /* ── Main Content ── */
        .main {
            display: grid;
            grid-template-columns: clamp(180px, 25vw, 320px) 1fr;
            gap: clamp(1rem, 2vw, 1.75rem);
            padding: clamp(1rem, 2.5vw, 2rem) clamp(1rem, 3vw, 2.5rem);
            align-items: center;
            height: 100%;
            overflow: hidden;
        }

        /* ── Profile Panel ── */
        .profile-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: clamp(0.75rem, 2vh, 1.5rem);
            text-align: center;
            background: var(--white);
            border-radius: clamp(16px, 2vw, 28px);
            padding: clamp(1.5rem, 3vw, 2.5rem) clamp(1rem, 2vw, 1.5rem);
            border: 1px solid var(--border);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
            height: 100%;
        }

        .avatar-wrap {
            width: clamp(100px, 18vw, 220px);
            height: clamp(100px, 18vw, 220px);
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red), var(--orange));
            padding: 5px;
            box-shadow: 0 12px 40px rgba(230, 57, 70, 0.25);
            flex-shrink: 0;
        }

        .avatar-wrap img,
        .avatar-wrap .avatar-fallback {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
        }

        .avatar-fallback {
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-fallback svg {
            width: 50%;
            height: 50%;
            color: #94a3b8;
        }

        .emp-name {
            font-size: clamp(1.4rem, 3.5vw, 2.6rem);
            font-weight: 900;
            color: var(--dark);
            line-height: 1.1;
            text-transform: capitalize;
        }

        .emp-badge {
            background: #fff1f2;
            color: var(--red);
            padding: 0.4rem 1.25rem;
            border-radius: 9999px;
            font-size: clamp(0.7rem, 1.2vw, 0.85rem);
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* ── Stats Panel ── */
        .stats-panel {
            display: flex;
            flex-direction: column;
            gap: clamp(0.75rem, 1.5vh, 1.25rem);
            height: 100%;
            justify-content: center;
        }

        .section-heading {
            font-size: clamp(1.5rem, 5vw, 3rem);
            font-weight: 900;
            color: var(--dark);
        }

        .section-heading2 {
            font-size: clamp(1.2rem, 3vw, 2.2rem);
            font-weight: 900;
            color: var(--dark);
        }

        .section-heading span {
            color: var(--red);
        }

        /* ── 3-Stat Row ── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: clamp(0.75rem, 1.5vw, 1.25rem);
        }

        .stat-box {
            background: var(--white);
            border-radius: clamp(14px, 1.5vw, 22px);
            padding: clamp(1rem, 2.5vw, 2rem) clamp(0.75rem, 2vw, 1.75rem);
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
        }

        .stat-box::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            border-radius: 22px 22px 0 0;
        }

        .stat-box.target::after {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
        }

        .stat-box.billing::after {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .stat-box.collection::after {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }

        .stat-box-icon {
            font-size: clamp(1.5rem, 3vw, 2.5rem);
            margin-bottom: clamp(0.4rem, 1vh, 0.75rem);
            display: block;
        }

        .stat-box-label {
            font-size: clamp(0.65rem, 1.2vw, 0.85rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #94a3b8;
            margin-bottom: clamp(0.3rem, 0.8vh, 0.5rem);
        }

        .stat-box-value {
            font-size: clamp(1.6rem, 4vw, 3.5rem);
            font-weight: 900;
            color: var(--dark);
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .stat-box.billing .stat-box-value {
            color: #059669;
        }

        .stat-box.collection .stat-box-value {
            color: #2563eb;
        }

        .stat-box.target .stat-box-value {
            color: #6366f1;
        }

        /* ── Progress Card ── */
        .progress-box {
            background: var(--white);
            border-radius: clamp(14px, 1.5vw, 22px);
            padding: clamp(1.25rem, 3vw, 2rem) clamp(1rem, 2.5vw, 2rem);
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        .progress-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: clamp(0.75rem, 1.5vh, 1.25rem);
        }

        .progress-left {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .progress-label {
            font-size: clamp(0.65rem, 1.2vw, 0.85rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #94a3b8;
        }

        .progress-sub {
            font-size: clamp(0.75rem, 1.4vw, 0.95rem);
            font-weight: 600;
            color: #94a3b8;
        }

        .progress-pct {
            font-size: clamp(2.5rem, 6vw, 5rem);
            font-weight: 900;
            background: linear-gradient(135deg, var(--red), var(--orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .track {
            width: 100%;
            height: clamp(14px, 2vh, 22px);
            background: #f1f5f9;
            border-radius: 9999px;
            overflow: hidden;
        }

        .fill {
            height: 100%;
            background: linear-gradient(90deg, var(--red), var(--orange));
            border-radius: 9999px;
            transition: width 1.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 20px rgba(230, 57, 70, 0.3);
        }

        .progress-footer {
            display: flex;
            justify-content: space-between;
            margin-top: clamp(0.4rem, 1vh, 0.75rem);
        }

        .progress-footer span {
            font-size: clamp(0.7rem, 1.2vw, 0.85rem);
            font-weight: 700;
            color: #94a3b8;
        }

        /* ── Bottom Bar ── */
        .bottombar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: clamp(0.5rem, 1.5vw, 1rem) clamp(1rem, 3vw, 2.5rem);
            background: var(--white);
            border-top: 1px solid var(--border);
        }

        .title-badge {
            font-size: clamp(0.85rem, 1.5vw, 1.1rem);
            font-weight: 800;
            color: var(--mid);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .controls {
            display: flex;
            align-items: center;
            gap: clamp(0.4rem, 1vw, 0.75rem);
        }

        .ctrl {
            width: clamp(36px, 4vw, 48px);
            height: clamp(36px, 4vw, 48px);
            border-radius: 50%;
            border: 1px solid var(--border);
            background: #f8fafc;
            cursor: pointer;
            font-size: clamp(0.85rem, 1.5vw, 1.1rem);
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-family: inherit;
            font-weight: 700;
        }

        .ctrl:hover {
            background: var(--red);
            color: white;
            border-color: var(--red);
            transform: scale(1.1);
        }

        .ctrl.exit {
            width: auto;
            padding: 0 clamp(0.75rem, 2vw, 1.5rem);
            border-radius: 9999px;
            background: var(--dark);
            color: white;
            border-color: var(--dark);
            font-size: clamp(0.75rem, 1.2vw, 0.9rem);
            text-decoration: none;
        }

        .ctrl.exit:hover {
            background: #1e293b;
        }

        /* ── Empty ── */
        .empty {
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
            justify-content: center;
            color: var(--mid);
        }

        /* ── Mobile: stack ── */
        @media (max-width: 768px) {
            .main {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
                overflow-y: auto;
                padding-bottom: 5rem;
                align-items: start;
            }

            .profile-panel {
                flex-direction: row;
                text-align: left;
                height: auto;
                padding: 1rem 1.25rem;
                gap: 1rem;
            }

            .avatar-wrap {
                width: 90px;
                height: 90px;
            }

            .stat-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stat-row {
                grid-template-columns: 1fr;
            }
        }

        /* ── View Toggle Button ── */
        .view-toggle {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: #f1f3f8;
            border: 1px solid var(--border);
            border-radius: 9999px;
            padding: 0.35rem 1rem;
            font-size: clamp(0.75rem, 1.3vw, 0.9rem);
            font-weight: 700;
            color: var(--dark);
            cursor: pointer;
            transition: all 0.2s;
        }

        .view-toggle:hover {
            background: var(--dark);
            color: white;
            border-color: var(--dark);
        }

        .view-toggle svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        /* ── Table View ── */
        #tableView {
            display: none;
            position: fixed;
            inset: 0;
            background: var(--bg);
            z-index: 200;
            flex-direction: column;
        }

        #tableView.active {
            display: flex;
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: clamp(0.75rem, 2vw, 1.25rem) clamp(1rem, 3vw, 2.5rem);
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .table-header h2 {
            font-size: clamp(1rem, 2.5vw, 1.4rem);
            font-weight: 900;
            color: var(--dark);
        }

        .table-wrap {
            flex: 1;
            overflow: auto;
            padding: clamp(1rem, 2.5vw, 2rem) clamp(1rem, 3vw, 2.5rem);
        }

        .emp-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .emp-table th {
            background: var(--dark);
            color: white;
            padding: clamp(0.75rem, 2vw, 1.1rem) clamp(0.75rem, 2vw, 1.25rem);
            text-align: left;
            font-size: clamp(0.7rem, 1.3vw, 0.85rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            white-space: nowrap;
        }

        .emp-table td {
            padding: clamp(0.75rem, 2vw, 1.1rem) clamp(0.75rem, 2vw, 1.25rem);
            border-bottom: 1px solid var(--border);
            font-size: clamp(0.8rem, 1.5vw, 1rem);
            vertical-align: middle;
        }

        .emp-table tr:last-child td {
            border-bottom: none;
        }

        .emp-table tr:hover td {
            background: #f8fafc;
        }

        .emp-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
        }

        .emp-avatar-sm-fallback {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--red), var(--orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .pct-badge {
            display: inline-block;
            padding: 0.3rem 0.85rem;
            border-radius: 9999px;
            font-weight: 800;
            font-size: clamp(0.75rem, 1.3vw, 0.9rem);
        }

        .mini-track {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
            margin-top: 0.3rem;
            min-width: 80px;
        }

        .mini-fill {
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, var(--red), var(--orange));
        }
    </style>
</head>

<body>
    <?php if (empty($employees)): ?>
        <div class="empty">
            <div style="font-size:3rem;">📋</div>
            <h2 style="font-size:1.5rem; color:#0d1b2a;">No Employees Selected</h2>
            <p>Tick "Target Slide" on the Employees page.</p>
            <a href="<?= APP_URL ?>/public/index.php/employees"
                style="margin-top:1rem; padding:0.75rem 2rem; background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; border-radius:9999px; text-decoration:none; font-weight:800;">Go
                to Employees →</a>
        </div>
    <?php else: ?>
        <div class="app-container">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="brand">
                    <?php if ($company_logo): ?>
                        <img src="<?= $company_logo ?>" alt="logo">
                    <?php endif; ?>
                    <div class="brand-name"><?= htmlspecialchars($company_name) ?></div>
                </div>
                <div class="topbar-right">
                    <button class="view-toggle" onclick="switchToTable()" title="Switch to Table View">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18M3 14h18M3 18h18" />
                        </svg>
                    </button>
                    <div class="month-tag"><?= $month_name ?></div>
                </div>
            </div>

            <div class="slideshow-wrapper">
                <div class="slideshow">
                    <?php foreach ($employees as $i => $emp):
                        $billing = (float) ($billing_map[$emp['id']]['billing'] ?? 0);
                        $collection = (float) ($billing_map[$emp['id']]['collection'] ?? 0);
                        $target = (float) ($emp['target'] ?? 0);
                        $pct = $target > 0 ? min(100, round(($billing / $target) * 100)) : 0;
                        $motivate = $pct >= 100 ? '🏆 Target Achieved!' : ($pct >= 75 ? '🔥 Almost There!' : ($pct >= 50 ? '💪 Halfway!' : '🚀 Keep Going!'));
                        $photo = $emp['photo']
                            ? (strpos($emp['photo'], 'http') === 0 ? $emp['photo'] : APP_URL . '/public/uploads/profiles/' . $emp['photo'])
                            : null;
                        ?>
                        <div class="slide <?= $i === 0 ? 'active' : '' ?>">

                            <!-- Main Grid -->
                    <div class="main">

                        <!-- Profile -->
                        <div class="profile-panel">
                            <div class="avatar-wrap">
                                <?php if ($photo): ?>
                                    <img src="<?= $photo ?>" alt="<?= htmlspecialchars($emp['name']) ?>"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="avatar-fallback" style="display:none;">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path
                                                d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <div class="avatar-fallback">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path
                                                d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="emp-name"><?= htmlspecialchars($emp['name']) ?></div>
                            <div class="emp-badge">⭐ Star Performer</div>
                        </div>

                        <!-- Stats -->
                        <div class="stats-panel">
                            <div class="month-tag2"><?= $month_name ?></div>
                            <div class="section-heading">Employee <span>Performance</span> <span style='font-size:1rem'> AS ON
                                    <?= date('d M Y') ?></span></div>

                            <div class="stat-row">
                                <div class="stat-box target">
                                    <span class="stat-box-icon">🎯</span>
                                    <div class="stat-box-label">Target Goal</div>
                                    <div class="stat-box-value">₹<?= number_format($target) ?></div>
                                </div>
                                <div class="stat-box billing">
                                    <span class="stat-box-icon">📈</span>
                                    <div class="stat-box-label">Total Billing</div>
                                    <div class="stat-box-value">₹<?= number_format($billing) ?></div>
                                </div>
                                <div class="stat-box collection">
                                    <span class="stat-box-icon">💰</span>
                                    <div class="stat-box-label">Collection</div>
                                    <div class="stat-box-value">₹<?= number_format($collection) ?></div>
                                </div>
                            </div>

                            <div class="progress-box">
                                <div class="progress-head">
                                    <div class="progress-left">
                                        <div class="progress-label">Target Completion</div>
                                        <div class="progress-sub">₹<?= number_format($billing) ?> of
                                            ₹<?= number_format($target) ?> &nbsp;·&nbsp; <?= $motivate ?></div>
                                    </div>
                                    <div class="progress-pct"><?= $pct ?>%</div>
                                </div>
                                <div class="track">
                                    <div class="fill" style="width:0%" data-w="<?= $pct ?>%"></div>
                                </div>
                                <div class="progress-footer">
                                    <span>0%</span><span>50%</span><span>100%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
                </div> <!-- End slideshow -->
            </div> <!-- End slideshow-wrapper -->

            <!-- Bottom Bar -->
            <div class="bottombar">
                <div class="title-badge">Employee Performance Dashboard</div>
                <div class="controls">
                    <button class="ctrl" onclick="prev()">❮</button>
                    <button class="ctrl" id="playBtn" onclick="togglePlay()">⏸</button>
                    <button class="ctrl" onclick="next()">❯</button>
                    <a class="ctrl exit" href="<?= APP_URL ?>/public/index.php/dashboard">Exit</a>
                </div>
            </div>
        </div> <!-- End app-container -->

        <!-- TABLE VIEW -->
        <div id="tableView">
            <!-- Top controls bar -->
            <div
                style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 2rem; background:white; border-bottom:1px solid #e2e8f0;">
                <div class="brand">
                    <?php if ($company_logo): ?>
                        <img src="<?= $company_logo ?>" alt="logo" style="height:32px; border-radius:6px;">
                    <?php endif; ?>
                    <span
                        style="font-weight:800; font-size:1rem; color:#0d1b2a;"><?= htmlspecialchars($company_name) ?></span>
                </div>
                <div style="display:flex; gap:0.75rem; align-items:center;">
                    <button class="view-toggle" onclick="exportPDF()"
                        style="background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; border:none; margin-right: 0.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export PDF
                    </button>
                    <button class="view-toggle" onclick="switchToSlide()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <rect x="3" y="3" width="18" height="14" rx="2" />
                            <path stroke-linecap="round" d="M8 21h8M12 17v4" />
                        </svg>
                        Slide View
                    </button>
                    <a class="view-toggle" href="<?= APP_URL ?>/public/index.php/dashboard"
                        style="text-decoration:none;">Exit</a>
                </div>
            </div>

            <!-- Scoreboard content -->
            <div id="printContent"
                style="flex:1; overflow:auto; background:#fff; padding:clamp(1.5rem,4vw,3rem) clamp(1rem,5vw,5rem);">

                <!-- Title -->
                <div style="text-align:center; margin-bottom:clamp(1.5rem,3vh,2.5rem);">
                    <h1
                        style="font-size:clamp(2rem,5vw,4rem); font-weight:900; color:#0d1b2a; letter-spacing:-0.02em; line-height:1;">
                        EMPLOYEE <span style="color:#e63946;">PERFORMANCE</span>
                    </h1>
                    <div style="display:flex; align-items:center; gap:1rem; justify-content:center; margin-top:1rem;">
                        <div style="height:2px; flex:1; background:linear-gradient(90deg,transparent,#e63946);"></div>
                        <div
                            style="background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding:0.5rem 2rem; border-radius:9999px; font-size:clamp(0.9rem,2vw,1.3rem); font-weight:900; letter-spacing:2px;">
                            <?= $month_name ?></div>
                        <div style="height:2px; flex:1; background:linear-gradient(90deg,#e63946,transparent);"></div>
                    </div>
                </div>

                <!-- Table -->
                <table style="width:100%; border-collapse:collapse; font-family:inherit;">
                    <thead>
                        <tr>
                            <th
                                style="background-color:#e63946 ; color:#ffffff ; padding:0.5rem 1rem; text-align:center; font-size:0.9rem; font-weight:800; border:2px solid white; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                                No.</th>
                            <th
                                style="background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding:0.5rem 1rem; text-align:center; font-size:0.9rem; font-weight:800; border:2px solid white;">
                                Employee Name</th>
                            <th
                                style="background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding:0.5rem 1rem; text-align:center; font-size:0.9rem; font-weight:800; border:2px solid white;">
                                Target (&#8377;)</th>
                            <th
                                style="background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding:0.5rem 1rem; text-align:center; font-size:0.9rem; font-weight:800; border:2px solid white;">
                                Billing (&#8377;)</th>
                            <th
                                style="background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding:0.5rem 1rem; text-align:center; font-size:0.9rem; font-weight:800; border:2px solid white;">
                                Collection (&#8377;)</th>
                            <th
                                style="background-color:#e63946 ; color:#ffffff ; -webkit-print-color-adjust: exact; print-color-adjust: exact; padding:0.5rem 1rem; text-align:center; font-size:0.9rem; font-weight:800; border:2px solid white;">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $i => $emp):
                            $billing = (float) ($billing_map[$emp['id']]['billing'] ?? 0);
                            $collection = (float) ($billing_map[$emp['id']]['collection'] ?? 0);
                            $target = (float) ($emp['target'] ?? 0);
                            $pct = $target > 0 ? round(($billing / $target) * 100, 1) : 0;
                            if ($pct >= 100) {
                                $sbg = '#16a34a';
                            } elseif ($pct >= 50) {
                                $sbg = '#d97706';
                            } else {
                                $sbg = '#dc2626';
                            }
                            $rowBg = $i % 2 === 0 ? '#ffffff' : '#fdf4f4';
                            ?>
                            <tr style="background:<?= $rowBg ?>; border-bottom:2px solid #f1f1f1; page-break-inside: avoid;">
                                <td
                                    style="padding:0.4rem 1rem; text-align:center; font-size:1.2rem; font-weight:900; color:#0d1b2a; border:1px solid #f1f1f1;">
                                    <?= $i + 1 ?></td>
                                <td
                                    style="padding:0.4rem 1rem; text-align:left; font-size:1.2rem; font-weight:900; color:#0d1b2a; border:1px solid #f1f1f1;">
                                    <?= htmlspecialchars($emp['name']) ?></td>
                                <td
                                    style="padding:0.4rem 1rem; text-align:center; font-size:1.1rem; font-weight:700; color:#0d1b2a; border:1px solid #f1f1f1;">
                                    &#8377;<?= number_format($target) ?></td>
                                <td
                                    style="padding:0.4rem 1rem; text-align:center; font-size:1.1rem; font-weight:700; color:#0d1b2a; border:1px solid #f1f1f1;">
                                    &#8377;<?= number_format($billing) ?></td>
                                <td
                                    style="padding:0.4rem 1rem; text-align:center; font-size:1.1rem; font-weight:700; color:#0d1b2a; border:1px solid #f1f1f1;">
                                    &#8377;<?= number_format($collection) ?></td>
                                <td style="padding:0.4rem 1rem; text-align:center; border:1px solid #f1f1f1;">
                                    <div
                                        style="background-color:<?= $sbg ?>; color:#ffffff; border-radius:12px; padding:0.25rem 0.5rem; min-width:90px; margin: 0 auto; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                                        <span style="font-size:1.2rem; font-weight:900; line-height:1;"><?= $pct ?>%</span>
                                        <span
                                            style="font-size:0.75rem; font-weight:700; letter-spacing:0.5px; margin-top:2px;">Completed</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script>
            const slides = document.querySelectorAll('.slide');
            const slideshow = document.querySelector('.slideshow');
            const tableView = document.getElementById('tableView');
            let cur = 0, timer, playing = true;

            function anim(s) {
                const f = s.querySelector('.fill');
                if (!f) return;
                f.style.width = '0%';
                requestAnimationFrame(() => requestAnimationFrame(() => f.style.width = f.dataset.w));
            }
            function show(n) {
                slides[cur].classList.remove('active');
                cur = (n + slides.length) % slides.length;
                slideshow.style.transform = `translateX(-${cur * 100}vw)`;
                slides[cur].classList.add('active');
                anim(slides[cur]);
            }
            function next() { show(cur + 1); reset(); }
            function prev() { show(cur - 1); reset(); }
            function togglePlay() {
                playing = !playing;
                document.getElementById('playBtn').textContent = playing ? '⏸' : '▶';
                reset();
            }
            function reset() { clearInterval(timer); if (playing) timer = setInterval(next, 6000); }

            function switchToTable() {
                clearInterval(timer);
                tableView.classList.add('active');
            }
            function switchToSlide() {
                tableView.classList.remove('active');
                if (playing) timer = setInterval(next, 6000);
            }

            function exportPDF() {
                const element = document.getElementById('printContent');
                const opt = {
                    margin: 0.4,
                    filename: 'Employee_Performance_Scoreboard.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, letterRendering: true, logging: false },
                    jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                html2pdf().set(opt).from(element).save();
            }

            anim(slides[0]);
            if (slides.length > 1) timer = setInterval(next, 6000);
        </script>
    <?php endif; ?>
</body>

</html>