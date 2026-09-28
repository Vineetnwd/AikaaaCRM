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
    $invoice_id = $_GET['id'] ?? null;
    $token = $_GET['token'] ?? null;
    if ($invoice_id && $token && $token === md5($invoice_id . JWT_SECRET)) {
        $is_public = true;
    } else {
        header("Location: " . APP_URL . "/public/index.php/login");
        exit;
    }
} else {
    $is_public = false;
}

$db = Database::getInstance();
$invoice_id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? '';

if ($type === 'quotation' && $invoice_id) {
    $tokenParam = isset($_GET['token']) ? ('&token=' . urlencode($_GET['token'])) : '';
    header("Location: " . APP_URL . "/public/assets/views/quotation_print.php?id=" . urlencode($invoice_id) . $tokenParam);
    exit;
}

if (!$invoice_id) {
    die("Invoice ID required");
}

$query = "
    SELECT i.*, l.name as client_name, l.mobile as client_mobile, l.email as client_email, l.requirement as lead_remark,
           (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') 
            FROM requirements r 
            JOIN lead_requirements lr ON r.id = lr.requirement_id 
            WHERE lr.lead_id = l.id) as requirement_names,
           (SELECT GROUP_CONCAT(COALESCE(r.description, '') SEPARATOR '|||') 
            FROM requirements r 
            JOIN lead_requirements lr ON r.id = lr.requirement_id 
            WHERE lr.lead_id = l.id) as requirement_descriptions,
           i.description as invoice_desc
    FROM invoices i 
    LEFT JOIN leads l ON i.lead_id = l.id 
    WHERE i.id = ?";
    
$params = [$invoice_id];

if (!$is_public) {
    $query .= " AND i.company_id = ?";
    $params[] = Auth::companyId();
}

$invoice = $db->fetchOne($query, $params);

if (!$invoice) {
    // If not an invoice, check if the ID corresponds to a quotation
    $quo = $db->fetchOne("SELECT id FROM quotations WHERE id = ?", [$invoice_id]);
    if ($quo) {
        $tokenParam = isset($_GET['token']) ? ('&token=' . urlencode($_GET['token'])) : '';
        header("Location: " . APP_URL . "/public/assets/views/quotation_print.php?id=" . $quo['id'] . $tokenParam);
        exit;
    }
    die("Invoice not found");
}

$company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$invoice['company_id']]);

$payments = $db->fetchAll("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date ASC", [$invoice_id]);

function amountToWords($number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
        60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety');
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
    return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . ' Only';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=850">
    <title>Tax Invoice - <?= $invoice['invoice_number'] ?></title>
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
        
        .items-table-wrapper {
            overflow-x: auto;
            margin-bottom: 30px;
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

        .payment-made {
            color: #ef4444;
            font-weight: 700;
        }

        .balance-due {
            font-weight: 900;
            font-size: 1.1rem;
            border-top: 2px solid #334155;
            padding-top: 10px !important;
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

        .payment-history {
            margin-top: 20px;
            border: 1px dashed #cbd5e1;
            padding: 10px;
            border-radius: 8px;
        }

        .payment-history-title {
            font-size: 0.7rem;
            font-weight: 900;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 8px;
            letter-spacing: 0.05em;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            padding: 4px 0;
            border-bottom: 1px dotted #e2e8f0;
        }

        @media print {
            @page {
                size: A4;
                margin: 5mm;
            }
            body { background: white; padding: 0; margin: 0; }
            .invoice-wrapper { 
                box-shadow: none; 
                border: none; 
                width: 100%; 
                max-width: 100%; 
                margin: 0;
                padding: 0;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: right; margin-bottom: 20px; max-width: 850px; margin: 0 auto 20px;">
        <button onclick="window.print()" style="background: #1e293b; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Invoice
        </button>
    </div>

    <div class="invoice-wrapper">
        <table class="header-table">
            <tr>
                <td class="logo-box">
                    <?php if (!empty($company['logo_path'])): ?>
                        <img src="<?= APP_URL ?>/public/<?= htmlspecialchars($company['logo_path']) ?>" alt="Logo" style="max-height: 60px; max-width: 180px; object-fit: contain; display: block; margin-bottom: 10px;">
                    <?php else: ?>
                        <div style="font-size: 24px; font-weight: 900; color: var(--primary, #6366f1); border-left: 4px solid var(--primary, #6366f1); padding-left: 10px; margin-bottom: 10px;">
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
                <td class="invoice-title">Tax Invoice</td>
            </tr>
        </table>

        <table style="width: 100%; border-collapse: separate; border-spacing: 20px 0; margin-left: -20px; margin-right: -20px; margin-bottom: 20px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <table class="meta-table" style="margin-bottom: 0; height: 100%;">
                        <tr>
                            <td><span class="meta-label">#</span></td>
                            <td><strong><?= $invoice['invoice_number'] ?></strong></td>
                        </tr>
                        <tr>
                            <td><span class="meta-label">Invoice Date</span></td>
                            <td><strong><?= date('d M Y', strtotime($invoice['invoice_date'])) ?></strong></td>
                        </tr>
                        <tr>
                            <td><span class="meta-label">Terms</span></td>
                            <td><strong>Due on Receipt</strong></td>
                        </tr>
                        <tr>
                            <td><span class="meta-label">Due Date</span></td>
                            <td><strong><?= $invoice['due_date'] ? date('d M Y', strtotime($invoice['due_date'])) : date('d M Y', strtotime($invoice['invoice_date'])) ?></strong></td>
                        </tr>
                    </table>
                </td>

                <td style="width: 50%; vertical-align: top;">
                    <div class="bill-to-bar" style="border: 1px solid #e2e8f0; border-bottom: none;">Bill To</div>
                    <div class="client-box" style="margin-bottom: 0;">
                        <div class="client-name"><?= htmlspecialchars($invoice['client_name']) ?></div>
                        <div style="font-size: 0.8125rem; color: #64748b; margin-top: 4px;">
                            <?= $invoice['client_mobile'] ? '+91 ' . htmlspecialchars($invoice['client_mobile']) : '' ?>
                            <?= $invoice['client_email'] ? ' | ' . htmlspecialchars($invoice['client_email']) : '' ?>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">#</th>
                        <th>Item & Description</th>
                        <th style="width: 80px; text-align: right;">Qty</th>
                        <th style="width: 120px; text-align: right;">Rate</th>
                        <th style="width: 120px; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $items = [];
                    if (!empty($invoice['invoice_desc'])) {
                        $decoded = json_decode($invoice['invoice_desc'], true);
                        if (is_array($decoded)) {
                            $items = $decoded;
                        }
                    }
                    ?>

                    <?php if (!empty($items)): ?>
                        <?php foreach($items as $index => $item): 
                            $qty = isset($item['qty']) ? floatval($item['qty']) : 1.00;
                            $rate = isset($item['rate']) ? floatval($item['rate']) : floatval($item['amount']);
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
                            <td style="text-align: right;"><?= number_format($item['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php 
                        $requirement_names = $invoice['requirement_names'] ?? 'Professional Services';
                        $requirement_descs = explode('|||', $invoice['requirement_descriptions'] ?? '');
                        $services = explode(', ', $requirement_names);
                        foreach($services as $index => $service):
                            $desc = $requirement_descs[$index] ?? '';
                        ?>
                        <tr>
                            <td style="text-align: center;"><?= $index + 1 ?></td>
                            <td>
                                <div style="font-weight: 700; color: #334155;"><?= htmlspecialchars($service) ?></div>
                                <?php if (!empty($desc)): 
                                    $formatted_desc = preg_replace('/([0-9]{1,2}\.)(?!\d)/', '<br>$1 ', htmlspecialchars($desc));
                                    $formatted_desc = preg_replace('/^(?:<br\s*\/?>\s*)+/', '', $formatted_desc);
                                ?>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px; line-height: 1.4;">
                                        <?= $formatted_desc ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($index === 0 && !empty($invoice['lead_remark'])): ?>
                                    <div style="font-size: 0.75rem; color: #64748b; font-style: italic; white-space: pre-line;">
                                        <?= htmlspecialchars($invoice['lead_remark']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">1.00</td>
                            <td style="text-align: right;">
                                <?= ($index === 0) ? number_format($invoice['subtotal'], 2) : '0.00' ?>
                            </td>
                            <td style="text-align: right;">
                                <?= ($index === 0) ? number_format($invoice['subtotal'], 2) : '0.00' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <div class="summary-section">
            <div class="left-notes">
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">Total In Words</div>
                    <div style="font-size: 0.875rem; font-weight: 700; font-style: italic;">Indian Rupee <?= amountToWords($invoice['total_amount']) ?></div>
                </div>

                <div style="margin-bottom: 15px;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 5px;">Notes</div>
                    <div style="font-size: 0.8125rem; color: #64748b;">Thanks for your business.</div>
                </div>

                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 5px;">Terms & Conditions</div>
                    <div style="font-size: 0.8125rem; color: #64748b; white-space: pre-line;">
                        <?= !empty($company['invoice_terms']) ? htmlspecialchars($company['invoice_terms']) : 'Paid amount will be not refundable in any case.' ?>
                    </div>
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
                        <td class="total-label">Sub Total</td>
                        <td><?= number_format($invoice['subtotal'], 2) ?></td>
                    </tr>
                    <?php if ($invoice['is_gst_enabled']): ?>
                        <?php 
                        $igst = floatval($invoice['igst'] ?? 0);
                        $cgst = floatval($invoice['cgst'] ?? 0);
                        $sgst = floatval($invoice['sgst'] ?? 0);
                        
                        // If all are 0 but total > subtotal, assume it's GST and split it
                        if ($igst == 0 && $cgst == 0 && $sgst == 0 && $invoice['total_amount'] > $invoice['subtotal']) {
                            $total_tax = $invoice['total_amount'] - $invoice['subtotal'];
                            $cgst = $total_tax / 2;
                            $sgst = $total_tax / 2;
                        }
                        ?>
                        <?php if ($igst > 0): ?>
                            <tr>
                                <td class="total-label">IGST (18%)</td>
                                <td><?= number_format($igst, 2) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="total-label">CGST (9%)</td>
                                <td><?= number_format($cgst, 2) ?></td>
                            </tr>
                            <tr>
                                <td class="total-label">SGST (9%)</td>
                                <td><?= number_format($sgst, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (floatval($invoice['discount'] ?? 0) > 0): ?>
                        <tr>
                            <td class="total-label">Discount</td>
                            <td style="color: #ef4444;">(-) <?= number_format($invoice['discount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="grand-total">
                        <td class="total-label" style="font-weight: 900; color: #0f172a;">Total</td>
                        <td style="font-weight: 900; color: #0f172a;">₹<?= number_format($invoice['total_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="total-label payment-made">Payment Made</td>
                        <td class="payment-made">(-) <?= number_format($invoice['paid_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="balance-due">
                            <div style="display: flex; justify-content: space-between;">
                                <span>Balance Due</span>
                                <span>₹<?= number_format($invoice['due_amount'], 2) ?></span>
                            </div>
                        </td>
                    </tr>
                </table>

                <?php if (!empty($payments)): ?>
                <div class="payment-history">
                    <div class="payment-history-title">Payment History</div>
                    <?php foreach($payments as $p): ?>
                        <div class="payment-row">
                            <span style="color:#64748b"><?= date('d M Y', strtotime($p['payment_date'])) ?></span>
                            <span style="color:#64748b; font-style:italic;"><?= htmlspecialchars($p['payment_mode']) ?></span>
                            <span style="font-weight: 700; color: #475569;">₹<?= number_format($p['amount'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="signature-box" style="flex-direction: column; align-items: center; justify-content: flex-end;">
                    <?php if (!empty($company['signature_path'])): ?>
                        <img src="<?= APP_URL ?>/public/<?= htmlspecialchars($company['signature_path']) ?>" alt="Signature" style="max-height: 50px; max-width: 150px; object-fit: contain; margin-bottom: 5px;">
                    <?php else: ?>
                        <div style="height: 50px; margin-bottom: 5px;"></div>
                    <?php endif; ?>
                    <div style="border-top: 1px solid #cbd5e1; width: 80%; text-align: center; padding-top: 5px;">
                        Authorized Signature
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
        // Ensure the page stops "spinning" once the print dialog is triggered
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });

        // Some browsers keep the spinner going if print is cancelled, this helps:
        window.onafterprint = function() {
            console.log("Print finished or cancelled");
        };
    </script>
    
</body>
</html>