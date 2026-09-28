<?php
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';

use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();

$isExecutive = Auth::isExecutive();
$execEmpId = Auth::employeeId();
$execUserId = Auth::userId();

$leadFilterSql = "";
$leadFilterParams = [$company_id];

if ($isExecutive) {
    $leadFilterSql = " AND (l.assigned_to = ? OR l.assigned_employee_id = ?)";
    $leadFilterParams[] = $execUserId;
    $leadFilterParams[] = $execEmpId;
}

// Fetch all leads with their selected requirements
$leads = $db->fetchAll(
    "SELECT l.id, l.name, l.mobile, l.requirement,
            (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') 
             FROM requirements r 
             JOIN lead_requirements lr ON r.id = lr.requirement_id 
             WHERE lr.lead_id = l.id) as requirement_names
     FROM leads l 
     WHERE l.company_id = ?" . $leadFilterSql . "
     ORDER BY l.name",
    $leadFilterParams
);

// Fetch IDs of leads that already have a quotation or invoice
$occupied_leads = $db->fetchAll(
    "SELECT DISTINCT lead_id FROM quotations WHERE company_id = ? AND lead_id IS NOT NULL
     UNION
     SELECT DISTINCT lead_id FROM invoices WHERE company_id = ? AND lead_id IS NOT NULL",
    [$company_id, $company_id]
);
$occupied_ids = array_column($occupied_leads, 'lead_id');

// Fetch Quotations with full lead detail and Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit_val = $_GET['limit'] ?? '15';
$limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
$offset = ($page - 1) * $limit;

$whereClause = "q.company_id = ?";
$params = [$company_id];

if ($isExecutive) {
    $whereClause .= " AND (l.assigned_to = ? OR l.assigned_employee_id = ?)";
    $params[] = $execUserId;
    $params[] = $execEmpId;
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $whereClause .= " AND (q.quotation_number LIKE ? OR l.name LIKE ? OR l.mobile LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countQuery = "SELECT COUNT(*) as total FROM quotations q LEFT JOIN leads l ON q.lead_id = l.id WHERE $whereClause";
$total_items = $db->fetchOne($countQuery, $params)['total'] ?? 0;
$total_pages = max(1, ceil($total_items / $limit));

$sql = "
    SELECT q.*, l.name as client_name, l.mobile as client_mobile, l.email as client_email, l.requirement as client_requirement,
           (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') 
            FROM requirements r 
            JOIN lead_requirements lr ON r.id = lr.requirement_id 
            WHERE lr.lead_id = l.id) as client_services
    FROM quotations q
    LEFT JOIN leads l ON q.lead_id = l.id
    WHERE $whereClause
    ORDER BY q.created_at DESC
    LIMIT $limit OFFSET $offset
";

$quotations = $db->fetchAll($sql, $params);

// Fetch all requirements for datalist
$requirements = $db->fetchAll("SELECT id, name, fee, description FROM requirements WHERE company_id = ? ORDER BY name", [$company_id]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotations | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1.5rem;
            backdrop-filter: blur(6px);
        }

        .modal-container {
            background: white;
            border-radius: 1.25rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: modalSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            letter-spacing: 0.05em;
        }

        /* Searchable Select Styles */
        .custom-select-container {
            position: relative;
            width: 100%;
        }

        .custom-select-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 5px;
        }

        .lead-option:hover {
            background: #f8fafc;
        }

        .lead-option.selected {
            background: #eff6ff;
            color: var(--primary);
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>
            <header class="header">
                <div>
                    <h1 class="page-title">Sales Quotations</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Draft and send
                        proposals to prospects</p>
                </div>
                <div class="header-actions" style="display: flex; align-items: center; gap: 0.75rem;">
                    <form method="GET" style="position:relative; margin:0;" onsubmit="event.preventDefault(); window.location.href = '?search=' + encodeURIComponent(this.search.value)">
                        <i class="fas fa-search"
                            style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size: 0.75rem; color:#94a3b8;"></i>
                        <input type="text" name="search" id="quoSearch" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search quotations..." class="form-input"
                            style="padding-left: 2.25rem; width: 240px; font-size: 0.75rem; height: 36px; margin:0;">
                    </form>
                    <button class="btn btn-ghost" onclick="openExportModal()"
                        style="height: 36px; border: 1px solid var(--border); margin:0; display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; font-weight: 700; font-size: 0.75rem;">
                        <i class="fas fa-file-excel" style="color: #10b981;"></i> Bulk Export
                    </button>
                    <button class="btn btn-primary" onclick="toggleModal('quotationModal')"
                        style="height: 36px; padding: 0 1rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; margin:0;">
                        <i class="fas fa-file-contract" style="font-size: 0.75rem;"></i> Create Quotation
                    </button>
                </div>
            </header>

            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem;">
                    <div id="bulkActionsContainer" style="display: none; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <button onclick="exportSelectedQuotationsXLSX()" class="btn btn-ghost" style="padding: 0.5rem 1rem; border: 1.5px solid #10b981; color: #047857; background: #ecfdf5; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:6px; font-size: 0.8rem; font-weight:700;">
                            <i class="fas fa-file-excel" style="color: #10b981;"></i> Export XLSX (<span id="bulkExportCount">0</span>)
                        </button>
                        <button onclick="sendBulkWhatsApp()" class="btn btn-primary" style="padding: 0.5rem 1rem; background: #25d366; color: white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:5px; font-size: 0.8rem;">
                            <i class="fab fa-whatsapp"></i> Bulk WhatsApp (<span id="bulkCount">0</span>)
                        </button>
                        <button onclick="sendBulkEmail()" class="btn btn-primary" style="padding: 0.5rem 1rem; background: #3b82f6; color: white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:5px; font-size: 0.8rem;">
                            <i class="fas fa-envelope"></i> Bulk Email (<span id="bulkEmailCount">0</span>)
                        </button>
                    </div>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0;">
                            <th style="padding: 1rem; width: 30px;">
                                <input type="checkbox" id="selectAllQuotations" onchange="toggleAllQuotations(this)">
                            </th>
                            <th
                                style="text-align: left; padding: 1rem; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Quotation #</th>
                            <th
                                style="text-align: left; padding: 1rem; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Client</th>
                            <th
                                style="text-align: left; padding: 1rem; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Services</th>
                            <th
                                style="text-align: left; padding: 1rem; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Total Value</th>
                            <th
                                style="text-align: left; padding: 1rem; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Status</th>
                            <th
                                style="text-align: right; padding: 1rem; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quotations)): ?>
                            <tr>
                                <td colspan="6" style="padding: 4rem; text-align: center; color: #94a3b8;">
                                    <i class="fas fa-file-invoice"
                                        style="font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: 0.2;"></i>
                                    <p style="font-size: 0.875rem; font-weight: 600;">No quotations drafted yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($quotations as $q): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;"
                                    onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                                    <td style="padding: 1rem; text-align: center;">
                                        <input type="checkbox" class="quotation-checkbox" value="<?= $q['id'] ?>" data-email="<?= htmlspecialchars($q['client_email'] ?? '') ?>" onchange="updateBulkActions()">
                                    </td>
                                    <td style="padding: 1rem; font-weight: 700; color: var(--primary); font-size: 0.875rem;">
                                        <?= $q['quotation_number'] ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div style="font-weight: 800; color: #1e293b; font-size: 0.875rem;">
                                            <?= htmlspecialchars($q['client_name'] ?? '—') ?>
                                        </div>
                                        <?php if (!empty($q['client_mobile'])): ?>
                                            <div style="font-size: 0.72rem; color: #64748b; font-weight:600;"><i
                                                    class="fas fa-phone-alt"></i> <?= htmlspecialchars($q['client_mobile']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($q['client_email'])): ?>
                                            <div style="font-size: 0.72rem; color: #64748b; font-weight:600; margin-top:2px;"><i
                                                    class="fas fa-envelope"></i> <?= htmlspecialchars($q['client_email']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($q['client_requirement'])): ?>
                                            <div style="font-size: 0.7rem; color: #64748b; margin-top:2px; font-style:italic;"
                                                title="<?= htmlspecialchars($q['client_requirement']) ?>">
                                                <i class="fas fa-comment-dots"></i>
                                                <?= mb_strimwidth(htmlspecialchars($q['client_requirement']), 0, 40, '…') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php
                                        $itemNames = [];
                                        if (!empty($q['description'])) {
                                            $items = json_decode($q['description'], true) ?: [];
                                            $itemNames = array_column($items, 'name');
                                        }

                                        $displayServices = !empty($itemNames) ? implode(', ', $itemNames) : ($q['client_services'] ?? '');
                                        ?>
                                        <?php if (!empty($displayServices)): ?>
                                            <div style="font-size: 0.75rem; color: var(--primary); font-weight:700;">
                                                <i class="fas fa-concierge-bell"></i> <?= htmlspecialchars($displayServices) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1; font-size: 0.75rem;">—</span>
                                        <?php endif; ?>
                                        <div style="font-size: 0.7rem; color: #94a3b8; margin-top:4px;">
                                            Date: <?= date('d M, Y', strtotime($q['quotation_date'])) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 800; color: #1e293b;">
                                        ₹<?= number_format($q['total_amount'], 2) ?></td>
                                    <td style="padding: 1rem;">
                                        <span class="badge"
                                            style="background: <?= $q['status'] == 'invoiced' ? '#dcfce7; color: #166534' : '#fef3c7; color: #92400e' ?>; font-size: 0.65rem; font-weight: 800; padding: 0.25rem 0.625rem; border-radius: 20px; text-transform: uppercase;">
                                            <?= $q['status'] ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <?php if ($q['status'] != 'invoiced'): ?>
                                                <?php
                                                $canManage = true;
                                                if ($isExecutive) {
                                                    // allow only if lead is assigned to this executive
                                                    $leadAssignedEmp = $q['lead_id'] ? ($db->fetchOne('SELECT assigned_employee_id, assigned_to FROM leads WHERE id = ? AND company_id = ?', [$q['lead_id'], $company_id]) ?: []) : [];
                                                    $assignedEmpId = intval($leadAssignedEmp['assigned_employee_id'] ?? 0);
                                                    $assignedUserId = intval($leadAssignedEmp['assigned_to'] ?? 0);
                                                    $canManage = ($assignedEmpId === ($execEmpId ?: 0) || $assignedUserId === (\Core\Auth::userId() ?: 0));
                                                }
                                                ?>
                                                <?php if ($canManage): ?>
                                                    <button onclick="toggleConvertModal(<?= $q['id'] ?>)" class="btn btn-primary"
                                                        style="padding: 0.375rem 0.75rem; font-size: 0.7rem; height: 32px; background: #10b981; border: none;">
                                                        <i class="fas fa-exchange-alt"></i> Convert to Invoice
                                                    </button>
                                                    <button onclick="editQuotation(<?= $q['id'] ?>)" class="btn btn-ghost"
                                                        style="padding: 0.375rem 0.75rem; font-size: 0.75rem; border: 1px solid #e2e8f0; height: 32px; color: #64748b;"
                                                        title="Edit Quotation">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Executive cannot manage this quotation because lead not assigned -->
                                                <?php endif; ?>
                                            <?php endif; ?>
                                                <button onclick="openEmailModal(<?= $q['id'] ?>, '<?= htmlspecialchars($q['quotation_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($q['client_name'] ?? 'Client', ENT_QUOTES) ?>', '<?= htmlspecialchars($q['client_email'] ?? '', ENT_QUOTES) ?>')"
                                                    class="btn btn-ghost"
                                                    style="padding: 0.375rem 0.75rem; font-size: 0.75rem; border: 1px solid #e2e8f0; height: 32px; color: #3b82f6;"
                                                    title="Email Quotation">
                                                    <i class="fas fa-envelope"></i>
                                                </button>

                                                <?php if ($q['client_mobile']): ?>
                                                    <?php
                                                    $publicQuotationUrl = APP_URL . "/public/index.php/quotation/print?id=" . $q['id'];
                                                    $waRecordData = [
                                                        'name' => trim($q['client_name']),
                                                        'service' => 'Quotation ' . $q['quotation_number'],
                                                        'status' => ucfirst($q['status']),
                                                        'value' => $q['total_amount'],
                                                        'link' => $publicQuotationUrl,
                                                        'invoice_number' => $q['quotation_number'],
                                                        'msg' => "Here is your project proposal and quotation " . $q['quotation_number'] . " for the amount of ₹" . number_format($q['total_amount'], 2) . ".",
                                                        'msg2' => "We look forward to working with you. Please let us know if you have any questions."
                                                    ];
                                                    ?>
                                                    <button
                                                        onclick='openWAModal("quotation", <?= $q['id'] ?>, <?= json_encode($waRecordData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                        class="btn btn-ghost"
                                                        style="padding: 0.375rem 0.75rem; font-size: 0.75rem; border: 1px solid #e2e8f0; height: 32px; color: #22c55e;"
                                                        title="Share on WhatsApp">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <a href="<?= APP_URL ?>/public/index.php/quotation/print?id=<?= $q['id'] ?>&print=true"
                                                target="_blank" class="btn btn-ghost"
                                                style="padding: 0.375rem 0.75rem; font-size: 0.75rem; border: 1px solid #e2e8f0; height: 32px;"
                                                title="Print Quotation">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: white;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing <?= count($quotations) ?> of <?= $total_items ?> quotations</span>
                        <form method="GET" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <?php foreach($_GET as $k => $v): if($k !== 'limit' && $k !== 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                            <?php endif; endforeach; ?>
                            <select name="limit" onchange="this.form.submit()" style="padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #475569; background: #f8fafc; cursor: pointer;">
                                <option value="15" <?= $limit_val == '15' ? 'selected' : '' ?>>15 per page</option>
                                <option value="25" <?= $limit_val == '25' ? 'selected' : '' ?>>25 per page</option>
                                <option value="50" <?= $limit_val == '50' ? 'selected' : '' ?>>50 per page</option>
                                <option value="100" <?= $limit_val == '100' ? 'selected' : '' ?>>100 per page</option>
                                <option value="all" <?= $limit_val === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </form>
                    </div>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($end_page - $start_page < 4) {
                        if ($start_page == 1) {
                            $end_page = min($total_pages, 5);
                        } elseif ($end_page == $total_pages) {
                            $start_page = max(1, $total_pages - 4);
                        }
                    }
                    $urlParams = "&search=" . urlencode($_GET['search'] ?? '') . "&limit=" . urlencode($limit_val);
                    ?>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $urlParams ?>" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: #0f172a;"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?= $i ?><?= $urlParams ?>" 
                               class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?>" 
                               style="padding: 0.5rem 0.8rem; border: 1px solid <?= $i == $page ? 'var(--primary)' : '#e2e8f0' ?>; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: <?= $i == $page ? 'white' : '#0f172a' ?>; font-weight: 700; <?= $i == $page ? 'background: var(--primary);' : '' ?>">
                               <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $urlParams ?>" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: #0f172a;"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
        <!-- Create Quotation Modal -->
        <div id="quotationModal" class="modal-overlay"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; overflow-y:auto; padding: 2rem 0;">
            <div class="modal-content"
                style="max-width: 700px; background:white; margin: auto; border-radius: 1rem; position:relative;">
                <div
                    style="padding: 1.5rem; border-bottom: 1px solid var(--border); display:flex; justify-content: space-between; align-items: center; position:sticky; top:0; background:white; z-index:10; border-radius: 1rem 1rem 0 0;">
                    <div>
                        <h2 style="font-size: 1.125rem; font-weight: 800; letter-spacing: -0.02em;">Draft New Quotation
                        </h2>
                        <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Set your proposal
                            terms
                            for the prospect</p>
                    </div>
                    <button onclick="toggleModal('quotationModal')" class="btn-ghost"
                        style="width: 32px; height: 32px; border-radius: 50%; display:flex; align-items:center; justify-content:center; padding:0;">
                        <i class="fas fa-times" style="font-size: 0.875rem;"></i>
                    </button>
                </div>

                <form id="quotationForm" style="padding: 1.5rem;">
                    <input type="hidden" name="id" id="quo_id_input">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label>Quotation #</label>
                            <input type="text" name="quotation_number" id="quotation_number_input" required
                                class="form-input" readonly
                                style="background: #f8fafc; cursor: not-allowed; border-style: dashed;">
                        </div>
                        <div>
                            <label>Select Lead</label>
                            <div id="leadSearchContainer" class="custom-select-container" style="position:relative;">
                                <div id="leadSelectedDisplay" class="form-input"
                                    style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; background:#fff;"
                                    onclick="toggleLeadDropdown()">
                                    <span id="selectedLeadText">-- Choose Lead --</span>
                                    <i class="fas fa-chevron-down" style="font-size:0.75rem; color:#94a3b8;"></i>
                                </div>
                                <div id="leadDropdown" class="custom-select-dropdown" style="display:none;">
                                    <div
                                        style="padding:0.75rem; border-bottom:1px solid #f1f5f9; position:sticky; top:0; background:white;">
                                        <div style="position:relative;">
                                            <i class="fas fa-search"
                                                style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size: 0.75rem; color:#94a3b8;"></i>
                                            <input type="text" id="leadSearchInput"
                                                placeholder="Search by name or mobile..." class="form-input"
                                                style="padding-left: 2.25rem; font-size: 0.75rem; height: 32px; margin-bottom: 0;"
                                                oninput="filterLeadDropdown(this.value)">
                                        </div>
                                    </div>
                                    <div id="leadOptionsList">
                                        <div class="lead-option"
                                            style="padding:0.75rem 1rem; cursor:pointer; font-size:0.875rem; border-bottom:1px solid #f8fafc;"
                                            onclick="selectLead('', '-- Choose Lead --', '')">-- Choose Lead --
                                        </div>
                                        <?php foreach ($leads as $lead): ?>
                                            <div class="lead-option" data-id="<?= $lead['id'] ?>"
                                                data-search="<?= strtolower(htmlspecialchars($lead['name'] . ' ' . ($lead['mobile'] ?? '') . ' ' . ($lead['requirement_names'] ?? ''))) ?>"
                                                data-requirements="<?= htmlspecialchars($lead['requirement_names'] ?? '') ?>"
                                                style="padding:0.75rem 1rem; cursor:pointer; font-size:0.875rem; border-bottom:1px solid #f8fafc;"
                                                onclick="selectLead('<?= $lead['id'] ?>', '<?= addslashes(htmlspecialchars($lead['name'])) ?> [<?= htmlspecialchars($lead['mobile'] ?? '') ?>]', '<?= addslashes(htmlspecialchars($lead['requirement_names'] ?? '')) ?>')">
                                                <div style="font-weight:600; color:#1e293b;">
                                                    <?= htmlspecialchars($lead['name']) ?>
                                                </div>
                                                <div style="display:flex; gap:1rem; align-items:center;">
                                                    <div style="font-size:0.75rem; color:#64748b;"><i
                                                            class="fas fa-phone-alt"></i>
                                                        <?= htmlspecialchars($lead['mobile'] ?? 'N/A') ?></div>
                                                    <?php if (!empty($lead['requirement_names'])): ?>
                                                        <div style="font-size:0.75rem; color:var(--primary); font-weight:600;">
                                                            <i class="fas fa-layer-group" style="font-size:0.65rem;"></i>
                                                            <?= htmlspecialchars($lead['requirement_names']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="lead_id" id="lead_id_hidden" required>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                        <div>
                            <label>Quotation Date</label>
                            <input type="date" name="quotation_date" id="quotation_date" value="<?= date('Y-m-d') ?>"
                                required class="form-input">
                        </div>
                        <div>
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                <label style="margin:0;">Enable GST</label>
                                <label class="switch">
                                    <input type="checkbox" name="is_gst_enabled" id="is_gst_enabled_quo" value="1"
                                        checked onchange="toggleGstEnable()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div id="gst_options_container" style="display:flex; gap:1rem;">
                                <label style="font-size:0.75rem; cursor:pointer;"><input type="radio" name="gst_type"
                                        value="intra" checked onclick="toggleTaxFields('intra')"> Local</label>
                                <label style="font-size:0.75rem; cursor:pointer;"><input type="radio" name="gst_type"
                                        value="inter" onclick="toggleTaxFields('inter')"> Interstate</label>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div
                        style="background: white; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; margin-bottom:1.25rem;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
                            <span style="font-weight: 800; color: #1e293b;"><i class="fas fa-list-ul"></i> Services /
                                Items</span>
                            <button type="button" class="btn btn-ghost" onclick="addQuotationItem()"
                                style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border: 1px solid #e2e8f0;">
                                <i class="fas fa-plus"></i> Add Service
                            </button>
                        </div>
                        <table style="width: 100%; border-collapse: collapse;" id="quo_items_table">
                            <thead>
                                <tr style="border-bottom: 2px solid #e2e8f0;">
                                    <th
                                        style="text-align: left; padding: 0.5rem 0.25rem; font-size: 0.7rem; color: #64748b; text-transform:uppercase;">
                                        Description</th>
                                    <th
                                        style="text-align: right; padding: 0.5rem 0.25rem; font-size: 0.7rem; color: #64748b; width: 60px;">
                                        Qty</th>
                                    <th
                                        style="text-align: right; padding: 0.5rem 0.25rem; font-size: 0.7rem; color: #64748b; width: 100px;">
                                        Rate</th>
                                    <th
                                        style="text-align: right; padding: 0.5rem 0.25rem; font-size: 0.7rem; color: #64748b; width: 100px;">
                                        Amount</th>
                                    <th style="width: 30px;"></th>
                                </tr>
                            </thead>
                            <tbody id="quo_items_body"></tbody>
                        </table>
                        <datalist id="services_list">
                            <?php foreach ($requirements as $req): ?>
                                <option value="<?= htmlspecialchars($req['name']) ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div
                        style="background: #f8fafc; padding: 1.25rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
                        <div
                            style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div>
                                <label>Base Subtotal (₹)</label>
                                <input type="number" step="0.01" name="subtotal" id="subtotal_quo" readonly
                                    class="form-input" style="background:#f1f5f9; font-weight:700;">
                            </div>
                            <div>
                                <label>Tax Rate (%)</label>
                                <input type="number" step="0.01" name="tax_percent" id="tax_quo" value="18"
                                    class="form-input">
                            </div>
                            <div>
                                <label>Discount</label>
                                <div style="display:flex; gap:0.25rem;">
                                    <select id="discount_type" class="form-input"
                                        style="width:60px; padding:0 4px; appearance:auto;">
                                        <option value="fixed">₹</option>
                                        <option value="percent">%</option>
                                    </select>
                                    <input type="number" step="0.01" id="discount_input" value="0.00" class="form-input"
                                        style="flex:1;">
                                </div>
                                <input type="hidden" name="discount" id="final_discount_amount" value="0">
                            </div>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; padding-top: 0.75rem; border-top: 1.5px solid #e2e8f0;">
                            <span style="font-weight: 700; color: #64748b;">TOTAL PROPOSAL VALUE:</span>
                            <span id="quo_total_display"
                                style="font-weight: 800; color: var(--primary); font-size: 1.25rem;">₹0.00</span>
                        </div>
                    </div>

                    <div style="display:flex; justify-content: flex-end; gap: 0.75rem;">
                        <button type="button" class="btn btn-ghost"
                            onclick="toggleModal('quotationModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.625rem 1.75rem;">
                            <i class="fas fa-save"></i> Save Quotation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <!-- Conversion Modal -->
    <div id="convertModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2
                    style="font-size: 1.125rem; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-file-invoice" style="color: var(--primary, #6366f1);"></i> Finalize Invoice
                </h2>
                <button onclick="toggleConvertModal()"
                    style="border:none; background:none; color:#94a3b8; cursor:pointer; font-size: 1.25rem; transition: color 0.2s;"
                    onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div
                    style="background: #eef2ff; border: 1px solid #e0e7ff; padding: 1rem; border-radius: 0.75rem; display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-info-circle" style="color: var(--primary, #6366f1); margin-top: 0.25rem;"></i>
                    <p style="font-size: 0.875rem; color: var(--primary, #4338ca); margin: 0; line-height: 1.5; font-weight: 500;">
                        Convert this quotation into a live invoice and set the delivery timeline for your service team.
                    </p>
                </div>
                <div>
                    <label class="form-label">Estimated Delivery Date</label>
                    <input type="date" id="est_delivery_date" class="form-input"
                        value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                        style="width: 100%; height: 48px; font-weight: 600; border-radius: 0.5rem; border: 1.5px solid #e2e8f0;">
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="toggleConvertModal()" class="btn btn-ghost"
                    style="font-weight: 700; color: #64748b;">Cancel</button>
                <button onclick="confirmConversion()" class="btn btn-primary"
                    style="padding: 0.75rem 1.5rem; font-weight: 800; background: #10b981; border: none; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);">
                    Confirm & Convert <i class="fas fa-arrow-right"
                        style="margin-left: 0.5rem; font-size: 0.75rem;"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Send Quotation Email Modal -->
    <div id="emailQuotationModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; z-index: 10000;">
        <div class="modal-container" style="max-width: 500px; width: 90%; background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow: hidden;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                <h2 style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fas fa-envelope" style="color: #3b82f6;"></i> Send Quotation Email
                </h2>
                <button type="button" onclick="closeEmailModal()"
                    style="border:none; background:none; color:#94a3b8; cursor:pointer; font-size: 1.25rem; transition: color 0.2s;"
                    onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="emailQuotationForm" onsubmit="submitEmailQuotation(event)">
                <input type="hidden" id="emailModalQuoId" value="">
                <div class="modal-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                    <div style="background: #eff6ff; border: 1px solid #dbeafe; padding: 0.85rem 1rem; border-radius: 8px; display: flex; gap: 0.75rem; align-items: center;">
                        <i class="fas fa-file-contract" style="color: #3b82f6; font-size: 1.2rem;"></i>
                        <div>
                            <div style="font-size: 0.85rem; font-weight: 800; color: #1e40af;" id="emailModalQuoNumber">QUO-000</div>
                            <div style="font-size: 0.75rem; color: #3b82f6;" id="emailModalClientName">Client</div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Recipient Email <span style="color: #ef4444;">*</span>
                        </label>
                        <div style="position: relative;">
                            <i class="fas fa-at" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem;"></i>
                            <input type="email" id="emailModalRecipient" required class="form-input" placeholder="client@example.com"
                                style="width: 100%; height: 40px; padding-left: 2.25rem; font-size: 0.85rem; border-radius: 6px; border: 1.5px solid #e2e8f0; box-sizing: border-box;">
                        </div>
                    </div>

                    <div>
                        <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Subject
                        </label>
                        <input type="text" id="emailModalSubject" class="form-input" placeholder="Project Quotation Proposal"
                            style="width: 100%; height: 40px; font-size: 0.85rem; border-radius: 6px; border: 1.5px solid #e2e8f0; box-sizing: border-box;">
                    </div>

                    <div>
                        <label class="form-label" style="display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">
                            Personal Message / Note (Optional)
                        </label>
                        <textarea id="emailModalNote" class="form-input" rows="3" placeholder="Add an optional message or note for the client..."
                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.85rem; border-radius: 6px; border: 1.5px solid #e2e8f0; resize: vertical; box-sizing: border-box;"></textarea>
                    </div>

                    <div style="font-size: 0.75rem; color: #64748b; line-height: 1.4; background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 6px;">
                        <i class="fas fa-info-circle" style="color: #3b82f6;"></i>
                        The email will include an itemized quotation breakdown and a direct secure link for the client to view and download their proposal.
                    </div>
                </div>
                <div class="modal-footer" style="padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeEmailModal()" class="btn btn-ghost" style="font-weight: 700; color: #64748b; height: 38px;">Cancel</button>
                    <button type="submit" id="emailModalSubmitBtn" class="btn btn-primary"
                        style="padding: 0 1.25rem; height: 38px; font-weight: 700; background: #3b82f6; border: none; display: flex; align-items: center; gap: 0.5rem; color: white; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-paper-plane"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Export Quotations Modal -->
    <div id="quotationExportModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; z-index: 10000;">
        <div class="modal-container" style="max-width: 480px; width: 90%; background: #ffffff; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow: hidden;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 38px; height: 38px; border-radius: 8px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: #0f172a;">Bulk Export Quotations</h2>
                        <p style="margin: 0; font-size: 0.75rem; color: #64748b;">Download quotations in Microsoft Excel (.xlsx) format</p>
                    </div>
                </div>
                <button type="button" onclick="closeExportModal()" class="btn-ghost" style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #94a3b8; border: none; background: none; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; text-transform: uppercase;">Export Scope</label>
                    <select id="exportScopeSelect" class="form-input" style="appearance: auto; width: 100%; height: 40px; font-size: 0.8125rem; font-weight: 600;" onchange="handleExportScopeChange(this.value)">
                        <option value="filter">Current Filter & Search (<?= $total_items ?> quotations)</option>
                        <option value="selected" id="exportScopeSelectedOpt" style="display:none;">Selected Quotations (<span id="modalSelectedCount">0</span> selected)</option>
                        <option value="month">Specific Month & Year</option>
                        <option value="custom">Custom Date Range</option>
                        <option value="all">All Quotations (All Time)</option>
                    </select>
                </div>

                <!-- Monthly Selector -->
                <div id="exportMonthBox" style="display: none; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">Month</label>
                        <select id="modalExportMonth" class="form-input" style="appearance: auto; height: 38px; font-size: 0.8125rem; width: 100%;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= date('n') == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">Year</label>
                        <select id="modalExportYear" class="form-input" style="appearance: auto; height: 38px; font-size: 0.8125rem; width: 100%;">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Custom Date Range -->
                <div id="exportCustomDateBox" style="display: none; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">From Date</label>
                        <input type="date" id="modalExportStart" value="<?= date('Y-m-01') ?>" class="form-input" style="height: 38px; font-size: 0.8125rem; width: 100%; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">To Date</label>
                        <input type="date" id="modalExportEnd" value="<?= date('Y-m-d') ?>" class="form-input" style="height: 38px; font-size: 0.8125rem; width: 100%; box-sizing: border-box;">
                    </div>
                </div>

                <!-- Status Filter -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem; text-transform: uppercase;">Quotation Status</label>
                    <select id="modalExportStatus" class="form-input" style="appearance: auto; width: 100%; height: 40px; font-size: 0.8125rem;">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending / Draft</option>
                        <option value="invoiced">Converted to Invoice</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" onclick="closeExportModal()" class="btn btn-ghost" style="padding: 0.6rem 1.25rem; font-size: 0.8125rem; font-weight: 700;">Cancel</button>
                    <button type="button" onclick="executeXLSXExport()" class="btn btn-primary" style="background: #10b981; border: none; padding: 0.6rem 1.5rem; font-size: 0.8125rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: white; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-file-excel"></i> Download XLSX
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const requirementFees = {
            <?php foreach ($requirements as $req): ?>
                        "<?= addslashes($req['name']) ?>": {
                    fee: <?= (float) $req['fee'] ?>,
                    desc: <?= json_encode($req['description'] ?? '') ?>
                },
            <?php endforeach; ?>
        };

        function toggleModal(id) {
            const modal = document.getElementById(id);
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
                document.getElementById('quotationForm').reset();
                document.getElementById('quo_id_input').value = '';
                document.querySelector('#quotationModal h2').textContent = 'Draft New Quotation';
                document.getElementById('quo_items_body').innerHTML = '';
                document.getElementById('selectedLeadText').innerText = '-- Choose Lead --';
                document.getElementById('lead_id_hidden').value = '';
                document.getElementById('quotation_number_input').value = 'QUO-' + Date.now();
                updateCalculations();
            } else {
                modal.style.display = 'flex';
                if (!document.getElementById('quo_id_input').value) {
                    document.getElementById('quotation_number_input').value = 'QUO-' + Date.now();
                    addQuotationItem();
                }
            }
        }

        function toggleLeadDropdown() {
            const dropdown = document.getElementById('leadDropdown');
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
            if (!isVisible) document.getElementById('leadSearchInput').focus();
        }

        function filterLeadDropdown(query) {
            query = query.toLowerCase();
            document.querySelectorAll('.lead-option').forEach(opt => {
                const search = opt.dataset.search || '';
                opt.style.display = search.includes(query) ? 'block' : 'none';
            });
        }

        function selectLead(id, text, requirements) {
            document.getElementById('lead_id_hidden').value = id;
            document.getElementById('selectedLeadText').innerText = text;
            document.getElementById('leadDropdown').style.display = 'none';

            const itemsBody = document.getElementById('quo_items_body');
            itemsBody.innerHTML = '';

            if (requirements) {
                requirements.split(', ').forEach(name => {
                    addQuotationItem(name, 1, requirementFees[name] || '');
                });
            } else {
                addQuotationItem();
            }
        }

        function addQuotationItem(name = '', qty = 1, rate = '', note = '') {
            const tbody = document.getElementById('quo_items_body');
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #f1f5f9';
            tr.innerHTML = `
                <td style="padding:0.5rem 0.25rem;">
                    <input type="text" class="form-input item-name" list="services_list" value="${name}" required style="margin:0; font-size:0.8rem; font-weight:700;" oninput="handleItemNameInput(this)">
                    <input type="text" class="item-note" value="${note}" placeholder="Add detailed note/description..." style="width:100%; border:none; background:transparent; font-size:0.7rem; color:#64748b; margin-top:2px; padding:2px 0; outline:none;">
                </td>
                <td style="padding:0.5rem 0.25rem;">
                    <input type="number" class="form-input item-qty" value="${qty}" min="1" required style="margin:0; text-align:right; font-size:0.8rem;" oninput="updateCalculations()">
                </td>
                <td style="padding:0.5rem 0.25rem;">
                    <input type="number" class="form-input item-rate" value="${rate}" step="0.01" required style="margin:0; text-align:right; font-size:0.8rem;" oninput="updateCalculations()">
                </td>
                <td style="padding:0.5rem 0.25rem; text-align:right; font-weight:700; font-size:0.8rem;" class="item-amount">₹0.00</td>
                <td style="padding:0.5rem 0.25rem; text-align:center;">
                    <button type="button" class="btn-ghost" onclick="this.closest('tr').remove(); updateCalculations();" style="color:var(--danger); padding:0.25rem;"><i class="fas fa-times"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
            updateCalculations();
        }

        function handleItemNameInput(input) {
            const name = input.value.trim();
            if (requirementFees[name]) {
                const tr = input.closest('tr');
                tr.querySelector('.item-rate').value = requirementFees[name].fee;
                tr.querySelector('.item-note').value = requirementFees[name].desc;
                updateCalculations();
            }
        }

        function toggleGstEnable() {
            document.getElementById('gst_options_container').style.display = document.getElementById('is_gst_enabled_quo').checked ? 'flex' : 'none';
            updateCalculations();
        }

        function toggleTaxFields(type) { updateCalculations(); }

        function updateCalculations() {
            let subtotal = 0;
            document.querySelectorAll('#quo_items_body tr').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
                const amt = qty * rate;
                row.querySelector('.item-amount').innerText = '₹' + amt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                subtotal += amt;
            });
            document.getElementById('subtotal_quo').value = subtotal.toFixed(2);

            const isGst = document.getElementById('is_gst_enabled_quo').checked;
            const taxPct = isGst ? (parseFloat(document.getElementById('tax_quo').value) || 0) : 0;
            const taxAmt = (subtotal * taxPct) / 100;

            const discVal = parseFloat(document.getElementById('discount_input').value) || 0;
            const discType = document.getElementById('discount_type').value;
            let discount = (discType === 'percent') ? ((subtotal + taxAmt) * discVal / 100) : discVal;

            document.getElementById('final_discount_amount').value = discount.toFixed(2);
            const total = (subtotal + taxAmt) - discount;
            document.getElementById('quo_total_display').innerText = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        }

        ['tax_quo', 'discount_input', 'discount_type'].forEach(id => {
            document.getElementById(id).addEventListener('input', updateCalculations);
        });

        async function editQuotation(id) {
            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/quotations.php?id=' + id);
                if (!response.ok) throw new Error('Network response was not ok');
                const q = await response.json();

                document.querySelector('#quotationModal h2').textContent = 'Edit Quotation';
                document.getElementById('quo_id_input').value = q.id;
                document.getElementById('quotation_number_input').value = q.quotation_number;
                document.getElementById('quotation_date').value = q.quotation_date;
                document.getElementById('lead_id_hidden').value = q.lead_id;
                document.getElementById('selectedLeadText').innerText = q.client_name;

                document.getElementById('is_gst_enabled_quo').checked = q.is_gst_enabled == 1;
                toggleGstEnable();

                const itemsBody = document.getElementById('quo_items_body');
                itemsBody.innerHTML = '';
                if (q.description) {
                    const items = JSON.parse(q.description);
                    items.forEach(it => addQuotationItem(it.name, it.qty, it.rate, it.note || ''));
                } else {
                    addQuotationItem();
                }

                document.getElementById('discount_input').value = q.discount; // Simplified
                document.getElementById('discount_type').value = 'fixed';

                updateCalculations();
                document.getElementById('quotationModal').style.display = 'flex';
            } catch (e) { console.error(e); }
        }

        document.getElementById('quotationForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const items = [];
            document.querySelectorAll('#quo_items_body tr').forEach(row => {
                items.push({
                    name: row.querySelector('.item-name').value,
                    note: row.querySelector('.item-note').value,
                    qty: parseFloat(row.querySelector('.item-qty').value) || 0,
                    rate: parseFloat(row.querySelector('.item-rate').value) || 0
                });
            });

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.description = JSON.stringify(items);
            data.is_gst_enabled = document.getElementById('is_gst_enabled_quo').checked ? 1 : 0;
            data.gst_type = document.querySelector('input[name="gst_type"]:checked')?.value || 'intra';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/quotations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) window.location.reload();
                else alert(result.error);
            } catch (e) { console.error(e); }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', e => {
            if (!document.getElementById('leadSearchContainer').contains(e.target)) {
                document.getElementById('leadDropdown').style.display = 'none';
            }
        });

        let currentQuoIdToConvert = null;

        function toggleConvertModal(id = null) {
            if (id !== null) {
                currentQuoIdToConvert = id;
            }
            const el = document.getElementById('convertModal');
            el.style.display = (el.style.display === 'flex') ? 'none' : 'flex';
        }

        async function confirmConversion() {
            const id = currentQuoIdToConvert;
            const deliveryDate = document.getElementById('est_delivery_date').value;

            if (!id) {
                alert('No quotation selected for conversion.');
                return;
            }

            if (!deliveryDate) {
                alert('Please select a delivery date');
                return;
            }

            const btn = document.querySelector('#convertModal .btn-primary');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Converting...';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/quotations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'convert', id: id, expected_delivery_date: deliveryDate })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Successfully converted to Invoice (' + (result.invoice_number || '') + ')!');
                    window.location.href = '<?= APP_URL ?>/public/index.php/invoices';
                } else {
                    alert('Error: ' + (result.error || 'Conversion failed'));
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (error) {
                alert('Logic Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        document.getElementById('quoSearch').addEventListener('input', function (e) {
            const query = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });

        function openEmailModal(id, quoNumber, clientName, defaultEmail) {
            document.getElementById('emailModalQuoId').value = id;
            document.getElementById('emailModalQuoNumber').innerText = quoNumber || ('Quotation #' + id);
            document.getElementById('emailModalClientName').innerText = clientName || 'Client';
            document.getElementById('emailModalRecipient').value = defaultEmail || '';
            document.getElementById('emailModalSubject').value = 'Project Proposal & Quotation ' + (quoNumber || '');
            document.getElementById('emailModalNote').value = '';
            
            const modal = document.getElementById('emailQuotationModal');
            modal.style.display = 'flex';
        }

        function closeEmailModal() {
            const modal = document.getElementById('emailQuotationModal');
            modal.style.display = 'none';
        }

        async function submitEmailQuotation(e) {
            if (e) e.preventDefault();
            const id = document.getElementById('emailModalQuoId').value;
            const recipient = document.getElementById('emailModalRecipient').value.trim();
            const subject = document.getElementById('emailModalSubject').value.trim();
            const note = document.getElementById('emailModalNote').value.trim();

            if (!id) {
                alert('No quotation selected.');
                return;
            }
            if (!recipient) {
                alert('Please enter a recipient email address.');
                document.getElementById('emailModalRecipient').focus();
                return;
            }

            const btn = document.getElementById('emailModalSubmitBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/share.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: parseInt(id),
                        type: 'quotation',
                        email: recipient,
                        subject: subject,
                        note: note
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Success: ' + result.message);
                    closeEmailModal();
                } else {
                    alert('Failed to send email: ' + (result.error || 'Unknown error occurred'));
                }
            } catch (err) {
                console.error(err);
                alert('Network Error: Could not connect to mail service. Please check your SMTP configuration.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        async function shareViaEmail(id, type, btn) {
            openEmailModal(id, 'Quotation #' + id, 'Client', '');
        }

        function toggleAllQuotations(source) {
            const checkboxes = document.querySelectorAll('.quotation-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.quotation-checkbox:checked');
            const container = document.getElementById('bulkActionsContainer');
            const countSpan = document.getElementById('bulkCount');
            const emailCountSpan = document.getElementById('bulkEmailCount');
            const exportCountSpan = document.getElementById('bulkExportCount');
            
            if (checkboxes.length > 0) {
                container.style.display = 'flex';
                countSpan.innerText = checkboxes.length;
                if (emailCountSpan) emailCountSpan.innerText = checkboxes.length;
                if (exportCountSpan) exportCountSpan.innerText = checkboxes.length;
            } else {
                container.style.display = 'none';
                document.getElementById('selectAllQuotations').checked = false;
            }
        }

        function exportSelectedQuotationsXLSX() {
            const checkboxes = document.querySelectorAll('.quotation-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one quotation to export.');
                return;
            }
            const ids = Array.from(checkboxes).map(cb => cb.value);
            window.location.href = `<?= APP_URL ?>/public/index.php/api/quotations.php?action=export_xlsx&ids=${ids.join(',')}`;
        }

        function openExportModal() {
            const checkedCount = document.querySelectorAll('.quotation-checkbox:checked').length;
            const selectedOpt = document.getElementById('exportScopeSelectedOpt');
            const modalCount = document.getElementById('modalSelectedCount');
            const scopeSelect = document.getElementById('exportScopeSelect');

            if (checkedCount > 0) {
                if (selectedOpt) selectedOpt.style.display = '';
                if (modalCount) modalCount.innerText = checkedCount;
                scopeSelect.value = 'selected';
            } else {
                if (selectedOpt) selectedOpt.style.display = 'none';
                if (scopeSelect.value === 'selected') scopeSelect.value = 'filter';
            }
            handleExportScopeChange(scopeSelect.value);
            document.getElementById('quotationExportModal').style.display = 'flex';
        }

        function closeExportModal() {
            document.getElementById('quotationExportModal').style.display = 'none';
        }

        function handleExportScopeChange(scope) {
            const monthBox = document.getElementById('exportMonthBox');
            const customBox = document.getElementById('exportCustomDateBox');
            if (monthBox) monthBox.style.display = (scope === 'month') ? 'grid' : 'none';
            if (customBox) customBox.style.display = (scope === 'custom') ? 'grid' : 'none';
        }

        function executeXLSXExport() {
            const scope = document.getElementById('exportScopeSelect').value;
            const status = document.getElementById('modalExportStatus').value;
            let url = `<?= APP_URL ?>/public/index.php/api/quotations.php?action=export_xlsx&status_filter=${encodeURIComponent(status)}`;

            if (scope === 'selected') {
                const checkboxes = document.querySelectorAll('.quotation-checkbox:checked');
                if (checkboxes.length === 0) {
                    alert('No quotations selected.');
                    return;
                }
                const ids = Array.from(checkboxes).map(cb => cb.value);
                url += `&ids=${ids.join(',')}`;
            } else if (scope === 'month') {
                const month = document.getElementById('modalExportMonth').value;
                const year = document.getElementById('modalExportYear').value;
                url += `&scope=month&month=${month}&year=${year}`;
            } else if (scope === 'custom') {
                const start = document.getElementById('modalExportStart').value;
                const end = document.getElementById('modalExportEnd').value;
                url += `&scope=custom&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
            } else if (scope === 'all') {
                url += `&scope=all`;
            } else {
                // filter: use current page's search
                const searchVal = document.getElementById('quoSearch')?.value || '<?= htmlspecialchars($_GET['search'] ?? '') ?>';
                url += `&scope=filter&search=${encodeURIComponent(searchVal)}`;
            }

            closeExportModal();
            window.location.href = url;
        }

        function sendBulkWhatsApp() {
            const checked = document.querySelectorAll('.quotation-checkbox:checked');
            if (checked.length === 0) return;
            const ids = Array.from(checked).map(cb => parseInt(cb.value));
            openWAModal('quotation', ids);
        }

        function sendBulkEmail() {
            const checked = document.querySelectorAll('.quotation-checkbox:checked');
            if (checked.length === 0) {
                alert('Please select at least one quotation.');
                return;
            }

            const emails = [];
            checked.forEach(cb => {
                const email = (cb.getAttribute('data-email') || '').trim();
                if (email && !emails.includes(email)) {
                    emails.push(email);
                }
            });

            if (emails.length === 0) {
                alert('None of the selected quotations have an associated client email address.');
                return;
            }

            const subject = encodeURIComponent('Quotation Proposal & Project Details');
            const body = encodeURIComponent('Dear Client,\n\nPlease find your project quotation proposal attached/available in your client portal.\n\nBest regards,');
            window.location.href = `mailto:?bcc=${encodeURIComponent(emails.join(','))}&subject=${subject}&body=${body}`;
        }
    </script>
    <?php include 'partials/wa_modal.php'; ?>
</body>

</html>