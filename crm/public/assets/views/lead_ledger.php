<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();
$customer_id = $_GET['id'] ?? null;

if (!$customer_id) {
    header("Location: " . APP_URL . "/public/index.php/customers");
    exit;
}

// Fetch customer details
$customer = $db->fetchOne("SELECT * FROM customers WHERE id = ? AND company_id = ?", [$customer_id, $company_id]);

if (!$customer) {
    die("Customer not found or access denied.");
}

// Fetch Lead History with Financials - match by customer_id OR mobile
$history = $db->fetchAll("
    SELECT l.id, l.created_at, l.status, l.task_status, l.task_completed_at, l.expected_delivery_date, l.remark,
    (SELECT remark FROM lead_followups WHERE lead_id = l.id AND remark LIKE 'Task Status changed to %' ORDER BY created_at DESC LIMIT 1) as latest_task_remark,
    (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM requirements r JOIN lead_requirements lr ON r.id = lr.requirement_id WHERE lr.lead_id = l.id) as services,
    (SELECT SUM(total_amount) FROM invoices WHERE lead_id = l.id AND company_id = ?) as total_amount,
    (SELECT SUM(paid_amount) FROM invoices WHERE lead_id = l.id AND company_id = ?) as paid_amount,
    (SELECT SUM(due_amount) FROM invoices WHERE lead_id = l.id AND company_id = ?) as due_amount,
    (SELECT SUM(discount) FROM invoices WHERE lead_id = l.id AND company_id = ?) as total_discount
    FROM leads l
    WHERE (l.customer_id = ? OR (RIGHT(REPLACE(l.mobile, BINARY ' ', BINARY ''), 10) = RIGHT(REPLACE(?, BINARY ' ', BINARY ''), 10))) AND l.company_id = ?
    ORDER BY l.created_at DESC
", [$company_id, $company_id, $company_id, $company_id, $customer_id, $customer['mobile'], $company_id]);

$total_billed = 0;
$total_paid = 0;
$total_dues = 0;

foreach ($history as $item) {
    $total_billed += (float) ($item['total_amount'] ?? 0);
    $total_paid += (float) ($item['paid_amount'] ?? 0);
    $total_dues += (float) ($item['due_amount'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Ledger | <?= htmlspecialchars($customer['name']) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--primary, #4338ca) 100%);
            --success-gradient: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            --info-gradient: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
        }

        .ledger-container {
            padding: 2rem;
            max-width: 95%;
            margin: 0 auto;
        }

        .customer-hero {
            background: white;
            padding: 2.5rem;
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .customer-info h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .customer-meta {
            display: flex;
            gap: 1.5rem;
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .customer-meta i {
            color: var(--primary);
            margin-right: 0.5rem;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1.25rem;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-card.billed::after {
            background: var(--primary);
        }

        .stat-card.paid::after {
            background: var(--success);
        }

        .stat-card.dues::after {
            background: #ef4444;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1e293b;
        }

        .ledger-table-container {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .ledger-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ledger-table th {
            background: #f8fafc;
            padding: 1.25rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        .ledger-table td {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .status-pill {
            padding: 0.375rem 0.75rem;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-pill.new {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-pill.interested {
            background: #ecfdf5;
            color: #059669;
        }

        .status-pill.follow_up {
            background: #fff7ed;
            color: #ea580c;
        }

        .status-pill.completed {
            background: #f0fdf4;
            color: #15803d;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
        }

        .amount-row.total {
            font-weight: 800;
            color: #1e293b;
        }

        .amount-row.paid {
            color: #059669;
            font-weight: 600;
        }

        .amount-row.due {
            color: #ef4444;
            font-weight: 600;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            transition: color 0.2s;
        }

        .btn-back:hover {
            color: var(--primary);
        }
        .btn-ledger:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }
        .btn-ledger {
            padding: 6px 12px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>
            <div class="ledger-container">

                <div class="customer-hero">
                    <div class="customer-info">
                        <h1><?= htmlspecialchars($customer['name']) ?></h1>
                        <div class="customer-meta">
                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($customer['mobile']) ?></span>
                            <?php if ($customer['email']): ?>
                                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($customer['email']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div
                            style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem;">
                            Total Engagement</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary);"><?= count($history) ?>
                            Leads</div>
                    </div>
                    
                    <a href="<?= APP_URL ?>/public/index.php/customer_profile?id=<?=$customer_id?>" class="btn-ledger" style="text-decoration: none; display: inline-flex;">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </div>

                <div class="stats-cards">
                    <div class="stat-card billed">
                        <span class="stat-label">Total Billed</span>
                        <span class="stat-value">₹<?= number_format($total_billed, 2) ?></span>
                    </div>
                    <div class="stat-card paid">
                        <span class="stat-label">Total Paid</span>
                        <span class="stat-value" style="color: #059669;">₹<?= number_format($total_paid, 2) ?></span>
                    </div>
                    <div class="stat-card dues">
                        <span class="stat-label">Total Dues</span>
                        <span class="stat-value" style="color: #ef4444;">₹<?= number_format($total_dues, 2) ?></span>
                    </div>
                </div>

                <div class="ledger-table-container">
                    <table class="ledger-table">
                        <thead>
                            <tr>
                                <th>Date & Status</th>
                                <th>Services & Requirements</th>
                                <th>Task Status</th>
                                <th>Financial Breakdown</th>
                                <th>Timeline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 4rem; color: #94a3b8;">
                                        <i class="fas fa-folder-open"
                                            style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
                                        No leads or history found for this customer.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">
                                                <?= date('d M, Y', strtotime($item['created_at'])) ?>
                                            </div>
                                            <span class="status-pill <?= strtolower($item['status']) ?>">
                                                <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                                                <?= ucfirst(str_replace('_', ' ', $item['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div
                                                style="color: var(--primary); font-weight: 700; font-size: 0.9rem; margin-bottom: 0.25rem;">
                                                <?= htmlspecialchars($item['services'] ?: 'No services specified') ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                                                Lead ID: #<?= $item['id'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-pill"
                                                style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; margin-bottom: 0.5rem;">
                                                <?= ucfirst(str_replace('_', ' ', $item['task_status'])) ?>
                                            </span>
                                            <?php 
                                            $display_remark = $item['remark'];
                                            if (!empty($item['latest_task_remark'])) {
                                                $display_remark = preg_replace('/^Task Status changed to [^:]+:\s*/i', '', $item['latest_task_remark']);
                                            }
                                            ?>
                                            <?php if (!empty($display_remark)): ?>
                                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 500; border-left: 2px solid #e2e8f0; padding-left: 0.5rem; margin-top: 0.25rem;">
                                                    <i class="fas fa-comment-dots" style="font-size: 0.7rem; color: var(--primary);"></i>
                                                    <?= htmlspecialchars($display_remark) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 200px;">
                                            <div class="amount-row total">
                                                <span>Billed:</span>
                                                <span>₹<?= number_format($item['total_amount'] ?: 0, 2) ?></span>
                                            </div>
                                            <?php if (floatval($item['total_discount'] ?? 0) > 0): ?>
                                                <div class="amount-row" style="color: #64748b; font-size: 0.75rem;">
                                                    <span>Discount:</span>
                                                    <span>₹<?= number_format($item['total_discount'], 2) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="amount-row paid">
                                                <span>Paid:</span>
                                                <span>₹<?= number_format($item['paid_amount'] ?: 0, 2) ?></span>
                                            </div>
                                            <div class="amount-row due">
                                                <span>Due:</span>
                                                <span>₹<?= number_format($item['due_amount'] ?: 0, 2) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">
                                                <div style="margin-bottom: 0.25rem;">
                                                    <span
                                                        style="font-size: 0.6rem; text-transform: uppercase; color: #94a3b8; display: block;">Expected
                                                        Delivery</span>
                                                    <?= $item['expected_delivery_date'] ? date('d M, Y', strtotime($item['expected_delivery_date'])) : '—' ?>
                                                </div>
                                                <div>
                                                    <span
                                                        style="font-size: 0.6rem; text-transform: uppercase; color: #94a3b8; display: block;">Completed
                                                        On</span>
                                                    <?= $item['task_completed_at'] ? date('d M, Y', strtotime($item['task_completed_at'])) : '—' ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>