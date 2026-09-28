<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';

use Core\Database;
use Core\Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$quotation_id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? null;
$is_public = !Auth::check();

$db = Database::getInstance();

if (!$quotation_id) {
    die("Quotation ID required");
}

$query = "
    SELECT q.*, l.name as client_name, l.mobile as client_mobile, l.email as client_email, l.requirement,
           (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') 
            FROM requirements r 
            JOIN lead_requirements lr ON r.id = lr.requirement_id 
            WHERE lr.lead_id = l.id) as requirement_names,
           (SELECT GROUP_CONCAT(COALESCE(r.description, '') SEPARATOR '|||') 
            FROM requirements r 
            JOIN lead_requirements lr ON r.id = lr.requirement_id 
            WHERE lr.lead_id = l.id) as requirement_descriptions
    FROM quotations q 
    LEFT JOIN leads l ON q.lead_id = l.id 
    WHERE q.id = ?";
$params = [$quotation_id];

if (!$is_public) {
    $query .= " AND q.company_id = ?";
    $params[] = Auth::companyId();
}

$quotation = $db->fetchOne($query, $params);

if (!$quotation) {
    die("Quotation not found");
}

$company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$quotation['company_id']]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation - <?= $quotation['quotation_number'] ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
            color: #1e293b;
        }

        .invoice-wrapper {
            background: white;
            max-width: 850px;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .logo-box {
            width: 1%;
            white-space: nowrap;
            vertical-align: top;
            padding-right: 20px;
        }

        .company-info {
            vertical-align: top;
        }

        .company-name {
            font-size: 1.25rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .company-details {
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.3;
        }

        .invoice-title {
            text-align: right;
            vertical-align: middle;
            font-size: 2rem;
            font-weight: 400;
            color: #334155;
            text-transform: uppercase;
        }

        .meta-table {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .meta-table td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            font-size: 0.8125rem;
            width: 25%;
        }

        .meta-label {
            color: #64748b;
            font-weight: 500;
        }

        .bill-to-bar {
            background: #f1f5f9;
            padding: 8px 12px;
            font-weight: 800;
            font-size: 0.8125rem;
            text-transform: uppercase;
            border: 1px solid #e2e8f0;
            border-bottom: none;
        }

        .client-box {
            padding: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .client-name {
            font-size: 1rem;
            font-weight: 900;
            color: #1e293b;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            text-align: left;
        }

        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 12px 10px;
            font-size: 0.875rem;
            vertical-align: top;
        }

        .summary-section {
            display: flex;
            justify-content: space-between;
        }

        .left-notes {
            width: 55%;
        }

        .right-totals {
            width: 40%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 10px;
            font-size: 0.875rem;
            text-align: right;
        }

        .total-label {
            font-weight: 600;
            color: #475569;
        }

        .total-amount {
            font-weight: 700;
            color: #1e293b;
        }

        .grand-total {
            font-weight: 900;
            font-size: 1rem;
            background: #f8fafc;
        }

        .signature-box {
            margin-top: 40px;
            border: 1px solid #e2e8f0;
            height: 80px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
        }

        @media print {
            @page {
                size: A4;
                margin: 5mm;
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .invoice-wrapper {
                box-shadow: none;
                border: none;
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="no-print" style="text-align: right; margin-bottom: 20px; max-width: 850px; margin: 0 auto 20px;">
        <button onclick="window.print()"
            style="background: #1e293b; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                </path>
            </svg>
            Print Quotation
        </button>
    </div>

    <div class="invoice-wrapper">
        <table class="header-table">
            <tr>
                <td class="logo-box">
                    <?php if (!empty($company['logo_path'])): ?>
                        <img src="<?= APP_URL ?>/public/<?= htmlspecialchars($company['logo_path']) ?>" alt="Logo"
                            style="max-height: 60px; max-width: 180px; object-fit: contain; display: block; margin-bottom: 10px;">
                    <?php else: ?>
                        <div
                            style="font-size: 24px; font-weight: 900; color: var(--primary, #6366f1); border-left: 4px solid var(--primary, #6366f1); padding-left: 10px; margin-bottom: 10px;">
                            <?= htmlspecialchars($company['name'] ?? 'AIKAA CRM') ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="company-info">
                    <div class="company-name"><?= htmlspecialchars($company['name'] ?? 'Aikaaa Technologies') ?></div>
                    <div class="company-details">
                        <?php if (!empty($company['gst_number'])): ?>
                            Tax ID: <?= htmlspecialchars($company['gst_number']) ?><br>
                        <?php endif; ?>
                        <?= nl2br(htmlspecialchars($company['address'] ?? "Sanjay Nagar Kali Mandir Road Patna\nBihar, India")) ?>
                    </div>
                </td>
                <td class="invoice-title">PROPOSAL</td>
            </tr>
        </table>

        <div style="display: flex; gap: 20px; margin-bottom: 20px; align-items: stretch;">
            <div style="flex: 1;">
                <table class="meta-table" style="margin-bottom: 0; height: 100%;">
                    <tr>
                        <td><span class="meta-label">#</span></td>
                        <td><strong><?= $quotation['quotation_number'] ?></strong></td>
                    </tr>
                    <tr>
                        <td><span class="meta-label">Quotation Date</span></td>
                        <td><strong><?= date('d M Y', strtotime($quotation['quotation_date'])) ?></strong></td>
                    </tr>
                    <tr>
                        <td><span class="meta-label">Status</span></td>
                        <td style="text-transform: uppercase; color: <?= $quotation['status'] == 'invoiced' ? '#10b981' : '#f59e0b' ?>;">
                            <strong><?= $quotation['status'] ?></strong>
                        </td>
                    </tr>
                    <tr>
                        <td><span class="meta-label">Valid Until</span></td>
                        <td><strong><?= date('d M Y', strtotime($quotation['quotation_date'] . ' + 15 days')) ?></strong></td>
                    </tr>
                </table>
            </div>

            <div style="flex: 1; display: flex; flex-direction: column;">
                <div class="bill-to-bar" style="border: 1px solid #e2e8f0; border-bottom: none;">Prepared For</div>
                <div class="client-box" style="margin-bottom: 0; flex: 1;">
                    <div class="client-name"><?= htmlspecialchars($quotation['client_name']) ?></div>
                    <div style="font-size: 0.8125rem; color: #64748b; margin-top: 4px;">
                        <?= $quotation['client_mobile'] ? '+91 ' . htmlspecialchars($quotation['client_mobile']) : '' ?>
                        <?= $quotation['client_email'] ? ' | ' . htmlspecialchars($quotation['client_email']) : '' ?>
                    </div>
                </div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">#</th>
                    <th>Description</th>
                    <th style="width: 80px; text-align: right;">Qty</th>
                    <th style="width: 120px; text-align: right;">Rate</th>
                    <th style="width: 120px; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $items = json_decode($quotation['description'], true) ?: [];
                if (empty($items)) {
                    $requirement_names = $quotation['requirement_names'] ?? 'Consultancy / Service Charges';
                    $requirement_descs = explode('|||', $quotation['requirement_descriptions'] ?? '');
                    $services = explode(', ', $requirement_names);
                    foreach ($services as $index => $service) {
                        $items[] = [
                            'name' => $service,
                            'note' => $requirement_descs[$index] ?? '',
                            'qty' => 1,
                            'rate' => ($index === 0 ? $quotation['subtotal'] : 0)
                        ];
                    }
                }

                foreach ($items as $index => $item):
                    $qty = isset($item['qty']) ? floatval($item['qty']) : 1.00;
                    $rate = isset($item['rate']) ? floatval($item['rate']) : 0.00;
                    $amt = $qty * $rate;
                    ?>
                    <tr>
                        <td style="text-align: center;"><?= $index + 1 ?></td>
                        <td>
                            <div style="font-weight: 700; color: #334155;"><?= htmlspecialchars($item['name']) ?></div>
                            <?php if (!empty($item['note'])):
                                $formatted_note = preg_replace('/([0-9]{1,2}\.)(?!\d)/', '<br>$1 ', htmlspecialchars($item['note']));
                                $formatted_note = preg_replace('/^(?:<br\s*\/?>\s*)+/', '', $formatted_note);
                                ?>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px; line-height: 1.4;">
                                    <?= $formatted_note ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;"><?= number_format($qty, 2) ?></td>
                        <td style="text-align: right;"><?= number_format($rate, 2) ?></td>
                        <td style="text-align: right;"><?= number_format($amt, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-section">
            <div class="left-notes">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 5px;">Terms &
                        Conditions</div>
                    <?php if (!empty($company['quotation_terms'])): ?>
                        <div style="font-size: 0.8125rem; color: #64748b; white-space: pre-line;">
                            <?= htmlspecialchars($company['quotation_terms']) ?>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 0.8125rem; color: #64748b; white-space: pre-line;">
                            1. The quotation is valid for 15 days from the date of issue.
                            2. Payment must be made according to the agreed upon timeline.
                            3. Any additional services not listed in this quotation will be billed separately.
                            4. Taxes (if applicable) are subject to change based on government regulations at the time of
                            invoicing.
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 15px;">
                    <?php if (!empty($company['bank_details'])): ?>
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 5px;">Bank Details</div>
                        <div style="font-size: 0.8125rem; color: #64748b; white-space: pre-line; padding: 10px; border: 1px dashed #cbd5e1; border-radius: 6px; display: inline-block; min-width: 200px;">
                            <?= htmlspecialchars($company['bank_details']) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($company['qr_code_path'])): ?>
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 5px;">Scan to Pay</div>
                        <img src="<?= APP_URL ?>/public/<?= htmlspecialchars($company['qr_code_path']) ?>" alt="Payment QR Code" style="width: 100px; height: 100px; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px;">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right-totals">
                <table class="totals-table">
                    <tr>
                        <td class="total-label">Subtotal</td>
                        <td><?= number_format($quotation['subtotal'], 2) ?></td>
                    </tr>
                    <?php if ($quotation['is_gst_enabled']): ?>
                        <?php if ($quotation['igst_amount'] > 0): ?>
                            <tr>
                                <td class="total-label">Tax (IGST)</td>
                                <td><?= number_format($quotation['igst_amount'], 2) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="total-label">CGST</td>
                                <td><?= number_format($quotation['cgst_amount'], 2) ?></td>
                            </tr>
                            <tr>
                                <td class="total-label">SGST</td>
                                <td><?= number_format($quotation['sgst_amount'], 2) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($quotation['discount'] > 0): ?>
                        <tr>
                            <td class="total-label">Discount</td>
                            <td style="color: #ef4444;">(-) <?= number_format($quotation['discount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="grand-total">
                        <td class="total-label" style="font-weight: 900; color: #0f172a;">Final Proposal Value</td>
                        <td style="font-weight: 900; color: #0f172a;">
                            ₹<?= number_format($quotation['total_amount'], 2) ?></td>
                    </tr>
                </table>

                <div class="signature-box"
                    style="flex-direction: column; align-items: center; justify-content: flex-end;">
                    <?php if (!empty($company['signature_path'])): ?>
                        <img src="<?= APP_URL ?>/public/<?= htmlspecialchars($company['signature_path']) ?>" alt="Signature"
                            style="max-height: 50px; max-width: 150px; object-fit: contain; margin-bottom: 5px;">
                    <?php else: ?>
                        <div style="height: 50px; margin-bottom: 5px;"></div>
                    <?php endif; ?>
                    <div style="border-top: 1px solid #cbd5e1; width: 80%; text-align: center; padding-top: 5px;">
                        Authorized Signature
                    </div>
                </div>
            </div>
        </div>

        <div
            style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; text-align: center;">
            <p style="margin: 0;"><strong>Note:</strong> This is a quotation, not an invoice. Prices and terms are
                subject to change.</p>
        </div>
    </div>

    <script>
        // Ensure the page stops "spinning" once the print dialog is triggered
        window.addEventListener('load', () => {
            <?php if (isset($_GET['print']) && $_GET['print'] == 'true'): ?>
                setTimeout(() => {
                    window.print();
                }, 500);
            <?php endif; ?>
        });

        // Some browsers keep the spinner going if print is cancelled, this helps:
        window.onafterprint = function () {
            console.log("Print finished or cancelled");
        };
    </script>
</body>

</html>