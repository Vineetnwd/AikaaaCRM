<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();
$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    header("Location: " . APP_URL . "/public/index.php/invoices");
    exit;
}

// Fetch invoice and client details
$invoice = $db->fetchOne("
    SELECT i.*, l.name as client_name, l.mobile as client_mobile, l.email as client_email 
    FROM invoices i 
    LEFT JOIN leads l ON i.lead_id = l.id 
    WHERE i.id = ? AND i.company_id = ?
", [$invoice_id, $company_id]);

if (!$invoice) {
    die("Invoice not found or access denied.");
}

// Fetch payments
$payments = $db->fetchAll("
    SELECT * 
    FROM invoice_payments 
    WHERE invoice_id = ? AND company_id = ? 
    ORDER BY payment_date DESC, created_at DESC
", [$invoice_id, $company_id]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Ledger | <?= $invoice['invoice_number'] ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
                        <a href="<?= APP_URL ?>/public/index.php/invoices" style="color: var(--text-muted); text-decoration: none;">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <h1 class="page-title">Payment Ledger</h1>
                        <?php if ($invoice['payment_status'] == 'cancelled'): ?>
                            <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.7rem; font-weight: 800; margin-left: 0.5rem;">CANCELLED</span>
                        <?php endif; ?>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">History for <?= $invoice['invoice_number'] ?> - <?= htmlspecialchars($invoice['client_name']) ?></p>
                </div>
            </header>

            <div class="stats-grid">
                <div class="card stat-card">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="stat-label">TOTAL INVOICE</span>
                        <div style="width: 28px; height: 28px; border-radius: 6px; background: #e0f2fe; color: #0ea5e9; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                    <span class="stat-value">₹<?= number_format($invoice['total_amount'], 2) ?></span>
                </div>
                <div class="card stat-card">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="stat-label" style="color: var(--success);">TOTAL PAID</span>
                        <div style="width: 28px; height: 28px; border-radius: 6px; background: #d1fae5; color: var(--success); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check-circle" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                    <span class="stat-value">₹<?= number_format($invoice['paid_amount'], 2) ?></span>
                </div>
                <div class="card stat-card">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="stat-label" style="color: var(--danger);">BALANCE DUE</span>
                        <div style="width: 28px; height: 28px; border-radius: 6px; background: #fee2e2; color: var(--danger); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                    <span class="stat-value">₹<?= number_format($invoice['due_amount'], 2) ?></span>
                </div>
            </div>

            <div class="card" style="padding: 1.5rem;">
                <h2 style="font-size: 1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-list-ul" style="color: var(--primary);"></i> Transaction History
                </h2>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Date</th>
                                <th style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Payment Mode</th>
                                <th style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Reference / Remarks</th>
                                <th style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; text-align: right;">Amount</th>
                                <th style="padding: 0.75rem 0.5rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                        <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 1rem; color: #cbd5e1;"></i>
                                        <div style="font-weight: 600;">No payments recorded yet.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr style="border-bottom: 1px solid #f8fafc;">
                                        <td style="padding: 1rem 0.5rem;">
                                            <div style="font-size: 0.8125rem; font-weight: 700; color: #334155;">
                                                <?= date('d M Y', strtotime($payment['payment_date'])) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem 0.5rem;">
                                            <span class="badge" style="background: #e0e7ff; color: var(--primary, #4338ca); font-size: 0.65rem; font-weight: 800;">
                                                <?= strtoupper(str_replace('_', ' ', $payment['payment_mode'])) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem 0.5rem;">
                                            <?php if ($payment['reference_number']): ?>
                                                <div style="font-size: 0.75rem; font-weight: 700; color: #0f172a;">Ref: <?= htmlspecialchars($payment['reference_number']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($payment['remarks']): ?>
                                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 0.2rem;"><?= htmlspecialchars($payment['remarks']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!$payment['reference_number'] && !$payment['remarks']): ?>
                                                <span style="color: #94a3b8; font-size: 0.75rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem 0.5rem; text-align: right;">
                                            <div style="font-size: 0.9rem; font-weight: 800; color: var(--success);">
                                                ₹<?= number_format($payment['amount'], 2) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem 0.5rem; text-align: right;">
                                            <a href="<?= APP_URL ?>/public/index.php/invoice_receipt?payment_id=<?= $payment['id'] ?>" target="_blank" class="btn btn-ghost" style="padding: 0.375rem 0.75rem; font-size: 0.7rem; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 0.4rem; color: var(--primary, #6366f1);">
                                                <i class="fas fa-print"></i> Print Receipt
                                            </a>
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
