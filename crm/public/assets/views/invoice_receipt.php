<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';

use Core\Database;
use Core\Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!Auth::check()) {
    header("Location: " . APP_URL . "/public/index.php/login");
    exit;
}

$db = Database::getInstance();
$payment_id = $_GET['payment_id'] ?? null;

if (!$payment_id) {
    die("Payment ID required");
}

$payment = $db->fetchOne("
    SELECT p.*, i.invoice_number, i.total_amount, i.paid_amount, i.due_amount, 
           i.subtotal, i.igst, i.cgst, i.sgst, i.is_gst_enabled,
           l.name as client_name, l.mobile as client_mobile, l.email as client_email
    FROM invoice_payments p
    JOIN invoices i ON p.invoice_id = i.id
    LEFT JOIN leads l ON i.lead_id = l.id
    WHERE p.id = ? AND p.company_id = ?
", [$payment_id, Auth::companyId()]);

if (!$payment) {
    die("Payment record not found");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receipt - <?= htmlspecialchars($payment['invoice_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            color: #1e293b;
            line-height: 1.5;
            padding: 40px;
            background: #f8fafc;
        }

        .receipt-box {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            border-radius: 8px;
            border-top: 6px solid #10b981;
        }

        .flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary, #6366f1);
            border-left: 4px solid var(--primary, #6366f1);
            padding-left: 12px;
        }

        .header {
            margin-bottom: 30px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 20px;
        }

        .title {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
        }

        .receipt-id {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        .amount-banner {
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .amount-banner .label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            color: #059669;
            letter-spacing: 0.05em;
        }

        .amount-banner .value {
            font-size: 36px;
            font-weight: 900;
            color: #047857;
            margin-top: 5px;
            letter-spacing: -0.02em;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item .label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .info-item .value {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .summary-box {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            color: #475569;
        }

        .summary-row.due {
            border-top: 1px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 700;
            color: #ef4444;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .receipt-box {
                box-shadow: none;
                border: 1px solid #e2e8f0;
                border-top: 6px solid #10b981;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="text-align: right; max-width: 600px; margin: 0 auto 20px;">
        <button onclick="window.print()"
            style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-print"></i> Print Receipt
        </button>
    </div>

    <div class="receipt-box">
        <div class="header flex">
            <div>
                <div class="logo">AIKAA CRM</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Smart Business Solutions</div>
            </div>
            <div style="text-align: right;">
                <h1 class="title">Payment Receipt</h1>
                <div class="receipt-id">Receipt #RCPT-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <div class="amount-banner">
            <div class="label"><i class="fas fa-check-circle" style="margin-right: 4px;"></i> Payment Received</div>
            <div class="value">₹<?= number_format($payment['amount'], 2) ?></div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="label">Received From</div>
                <div class="value" style="font-size: 16px; color: #0f172a;"><?= htmlspecialchars($payment['client_name']) ?></div>
                <div style="font-size: 13px; color: #64748b; margin-top: 2px;">+91 <?= htmlspecialchars($payment['client_mobile']) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Payment Details</div>
                <div class="value">Date: <?= date('d M, Y', strtotime($payment['payment_date'])) ?></div>
                <div class="value" style="margin-top: 4px; text-transform: capitalize;">Mode: <?= str_replace('_', ' ', $payment['payment_mode']) ?></div>
                <?php if ($payment['reference_number']): ?>
                    <div class="value" style="margin-top: 4px;">Ref: <?= htmlspecialchars($payment['reference_number']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="summary-box">
            <div class="info-item" style="margin-bottom: 15px;">
                <div class="label">Applied To</div>
                <div class="value" style="font-weight: 800; color: var(--primary, #6366f1);">Invoice <?= htmlspecialchars($payment['invoice_number']) ?></div>
            </div>
            
            <div class="summary-row">
                <span>Base Amount</span>
                <span style="font-weight: 600;">₹<?= number_format($payment['subtotal'], 2) ?></span>
            </div>
            <?php if ($payment['is_gst_enabled']): ?>
                <?php if ($payment['igst'] > 0): ?>
                    <div class="summary-row">
                        <span>IGST (18%)</span>
                        <span style="font-weight: 600;">₹<?= number_format($payment['igst'], 2) ?></span>
                    </div>
                <?php else: ?>
                    <div class="summary-row">
                        <span>CGST (9%)</span>
                        <span style="font-weight: 600;">₹<?= number_format($payment['cgst'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>SGST (9%)</span>
                        <span style="font-weight: 600;">₹<?= number_format($payment['sgst'], 2) ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="summary-row" style="border-top: 1px dashed #e2e8f0; margin-top: 5px; padding-top: 5px;">
                <span>Total Billed</span>
                <span style="font-weight: 700; color: #0f172a;">₹<?= number_format($payment['total_amount'], 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Total Amount Paid</span>
                <span style="font-weight: 600; color: #10b981;">₹<?= number_format($payment['paid_amount'], 2) ?></span>
            </div>
            <div class="summary-row due">
                <span>Remaining Balance</span>
                <span>₹<?= number_format($payment['due_amount'], 2) ?></span>
            </div>
        </div>

        <div class="footer">
            <p>This is a computer-generated receipt and does not require a physical signature.</p>
            <p style="margin-top: 5px;">Thank you for your business!</p>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
