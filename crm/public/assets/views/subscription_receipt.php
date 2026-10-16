<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Company.php';

use Core\Database;
use Core\Auth;
use Core\Company;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!Auth::check()) {
    header("Location: " . APP_URL . "/public/index.php/login");
    exit;
}

$db = Database::getInstance();
$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
$companyId = Auth::companyId();

if (!$payment_id) {
    // If no payment_id provided, fetch the latest subscription payment for this company
    $payment = $db->fetchOne("SELECT * FROM subscription_payments WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$companyId]);
} else {
    if (Auth::isSuperAdmin()) {
        $payment = $db->fetchOne("SELECT * FROM subscription_payments WHERE id = ?", [$payment_id]);
    } else {
        $payment = $db->fetchOne("SELECT * FROM subscription_payments WHERE id = ? AND company_id = ?", [$payment_id, $companyId]);
    }
}

if (!$payment) {
    die("Subscription payment record not found.");
}

$companyModel = new Company();
$targetCompany = $companyModel->find($payment['company_id']);
$user = $payment['user_id'] ? $db->fetchOne("SELECT * FROM users WHERE id = ?", [$payment['user_id']]) : Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Receipt #RCPT-SUB-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?> | Aikaaa CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            line-height: 1.5;
            padding: 30px 15px;
            background: #f1f5f9;
            margin: 0;
        }

        .receipt-action-bar {
            max-width: 680px;
            margin: 0 auto 1.25rem auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.8125rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-dashboard {
            background: white;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-dashboard:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }
        .btn-print {
            background: #10b981;
            color: white;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
        }
        .btn-print:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .receipt-card {
            max-width: 680px;
            margin: 0 auto;
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .receipt-header-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            padding: 2rem 2.25rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logo-tagline {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 0.25rem;
            font-weight: 500;
        }

        .receipt-title-box {
            text-align: right;
        }
        .receipt-main-title {
            font-size: 1.125rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            color: white;
        }
        .receipt-number {
            font-size: 0.75rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 0.25rem;
            font-family: monospace;
            background: rgba(0, 0, 0, 0.15);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
        }

        .receipt-body {
            padding: 2.25rem;
        }

        .amount-highlight {
            background: #f0fdf4;
            border: 1.5px solid #bbf7d0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .amount-highlight .label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .amount-highlight .val {
            font-size: 1.75rem;
            font-weight: 900;
            color: #15803d;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-block-label {
            font-size: 0.6875rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.375rem;
        }
        .info-block-title {
            font-size: 0.9375rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }
        .info-block-sub {
            font-size: 0.8125rem;
            color: #475569;
            line-height: 1.4;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .details-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            color: #475569;
            font-size: 0.6875rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .details-table td {
            padding: 1rem;
            font-size: 0.8125rem;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .receipt-footer-note {
            text-align: center;
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.5;
        }

        .paid-stamp {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            text-transform: uppercase;
        }

        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .receipt-card { box-shadow: none; border: 1px solid #cbd5e1; border-radius: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="receipt-action-bar no-print">
        <a href="<?= APP_URL ?>/public/index.php/dashboard" class="btn-action btn-dashboard">
            <i class="fas fa-arrow-left"></i> Go to Dashboard
        </a>
        <button onclick="window.print()" class="btn-action btn-print">
            <i class="fas fa-print"></i> Print Receipt
        </button>
    </div>

    <div class="receipt-card">
        <div class="receipt-header-banner">
            <div>
                <div class="logo-text">
                    <i class="fas fa-layer-group"></i> AIKAAA CRM
                </div>
                <div class="logo-tagline">Cloud CRM & Enterprise Business Automation</div>
            </div>
            <div class="receipt-title-box">
                <div class="receipt-main-title">Subscription Receipt</div>
                <div class="receipt-number">#RCPT-SUB-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <div class="receipt-body">
            <div class="amount-highlight">
                <div>
                    <div class="label">
                        <i class="fas fa-check-circle"></i> Payment Status
                    </div>
                    <div style="margin-top: 0.4rem;">
                        <span class="paid-stamp">
                            <i class="fas fa-check"></i> Paid via Razorpay
                        </span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.6875rem; color: #166534; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Total Paid</div>
                    <div class="val">₹<?= number_format($payment['amount'], 2) ?></div>
                </div>
            </div>

            <div class="info-grid">
                <div>
                    <div class="info-block-label">Subscribed Organization</div>
                    <div class="info-block-title"><?= htmlspecialchars($targetCompany['name'] ?? 'Company') ?></div>
                    <div class="info-block-sub">
                        <?php if (!empty($targetCompany['gst_number'])): ?>
                            GST: <strong><?= htmlspecialchars($targetCompany['gst_number']) ?></strong><br>
                        <?php endif; ?>
                        <?php if (!empty($targetCompany['subdomain'])): ?>
                            Domain: <strong><?= htmlspecialchars($targetCompany['subdomain']) ?>.aikocrm.com</strong><br>
                        <?php endif; ?>
                        <?php if (!empty($targetCompany['address'])): ?>
                            <?= nl2br(htmlspecialchars($targetCompany['address'])) ?><br>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div class="info-block-label">Transaction Details</div>
                    <div class="info-block-sub">
                        <strong>Date:</strong> <?= date('d M, Y h:i A', strtotime($payment['created_at'])) ?><br>
                        <strong>Payment ID:</strong> <span style="font-family: monospace; color:#0f172a;"><?= htmlspecialchars($payment['razorpay_payment_id'] ?? 'N/A') ?></span><br>
                        <strong>Order ID:</strong> <span style="font-family: monospace; color:#0f172a;"><?= htmlspecialchars($payment['razorpay_order_id'] ?? 'N/A') ?></span><br>
                        <strong>Billing Contact:</strong> <?= htmlspecialchars($user['name'] ?? 'Admin') ?> (<?= htmlspecialchars($user['email'] ?? '') ?>)
                    </div>
                </div>
            </div>

            <table class="details-table">
                <thead>
                    <tr>
                        <th>Subscription Item</th>
                        <th>Duration</th>
                        <th>Coverage Period</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong style="color: #0f172a; font-size: 0.9rem;"><?= htmlspecialchars($payment['plan_name']) ?></strong>
                            <div style="font-size: 0.72rem; color: #64748b; margin-top: 0.2rem;">Full CRM Platform Access & Cloud Services</div>
                        </td>
                        <td>
                            <?= (int)$payment['duration_value'] ?> <?= htmlspecialchars($payment['duration_unit']) ?>
                        </td>
                        <td>
                            <span style="white-space: nowrap; font-size: 0.75rem; font-weight: 600;">
                                <?= date('d M, Y', strtotime($payment['subscription_starts_at'])) ?> &rarr; <?= date('d M, Y', strtotime($payment['subscription_ends_at'])) ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 800; font-size: 0.9rem; color: #0f172a;">
                            ₹<?= number_format($payment['amount'], 2) ?>
                        </td>
                    </tr>
                    <tr style="background: #f8fafc; font-weight: 800;">
                        <td colspan="3" style="text-align: right; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #475569;">Grand Total:</td>
                        <td style="text-align: right; color: #4f46e5; font-size: 1.05rem;">₹<?= number_format($payment['amount'], 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="receipt-footer-note">
            <p style="margin: 0 0 0.5rem 0;">
                <i class="fas fa-shield-alt" style="color: #10b981; margin-right: 0.25rem;"></i>
                This is an official computer-generated receipt for your records and does not require a physical signature.
            </p>
            <p style="margin: 0; font-size: 0.7rem; color: #94a3b8;">
                Thank you for choosing Aikaaa CRM. For any billing queries, contact support at <strong>support@aikocrm.com</strong>.
            </p>
        </div>
    </div>

</body>
</html>
