<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();
$isExecutive = Auth::isExecutive();
$users = $db->fetchAll("SELECT id, name FROM users WHERE company_id = ?", [$company_id]);
$employees = $db->fetchAll("SELECT id, name, designation FROM employees WHERE company_id = ? AND status = 'active' ORDER BY name", [$company_id]);
$requirements = $db->fetchAll("SELECT * FROM requirements WHERE company_id = ? ORDER BY name ASC", [$company_id]);
if ($isExecutive) {
    $userId = \Core\Auth::userId();
    $empId = \Core\Auth::employeeId();
    $customers = $db->fetchAll("SELECT DISTINCT c.id, c.name, c.mobile 
        FROM customers c 
        JOIN leads l ON (l.customer_id = c.id OR (l.mobile != '' AND l.mobile COLLATE utf8mb4_unicode_ci = c.mobile COLLATE utf8mb4_unicode_ci))
        WHERE c.company_id = ? AND l.company_id = ? 
        AND (l.assigned_to = ? OR l.assigned_employee_id = ?) 
        ORDER BY c.name",
        [$company_id, $company_id, $userId, $empId]
    );
} else {
    $customers = $db->fetchAll("SELECT id, name, mobile FROM customers WHERE company_id = ? ORDER BY name", [$company_id]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* ── Google Fonts ────────────────────────────────────── */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        /* ── Pipeline / Kanban ───────────────────────────────── */
        .pipeline-container {
            display: flex;
            gap: 1.125rem;
            overflow-x: auto;
            padding-bottom: 1.5rem;
            height: calc(100vh - 160px);
            align-items: flex-start;
        }

        .pipeline-column {
            flex: 1;
            min-width: 285px;
            max-width: 305px;
            background: #fff;
            border-radius: 1rem;
            padding: 0;
            display: flex;
            flex-direction: column;
            max-height: 100%;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06), 0 0 0 0 transparent;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .pipeline-column:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .pipeline-header {
            font-weight: 800;
            color: #fff;
            margin-bottom: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 0.6rem;
            flex-shrink: 0;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--accent, #8b5cf6) 100%);
        }

        .pipeline-column[data-status="new"] .pipeline-header {
            background: linear-gradient(135deg, var(--primary, #6366f1), #818cf8);
        }

        .pipeline-column[data-status="in_progress"] .pipeline-header {
            background: linear-gradient(135deg, #f59e0b, #fb923c);
        }

        .pipeline-column[data-status="won"] .pipeline-header {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .pipeline-column[data-status="lost"] .pipeline-header {
            background: linear-gradient(135deg, #ef4444, #f87171);
        }

        .pipeline-header .counter {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #fff !important;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 20px;
        }

        .leads-list {
            overflow-y: auto;
            flex-grow: 1;
            padding: 0.875rem;
        }

        .leads-list::-webkit-scrollbar {
            width: 4px;
        }

        .leads-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .lead-card {
            background: #fff;
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            margin-bottom: 0.625rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid #eef1f6;
            border-left: 3px solid #c7d2fe;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            cursor: default;
        }

        .lead-card:hover {
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.14), 0 1px 4px rgba(0, 0, 0, 0.08);
            border-color: #c7d2fe;
            border-left-color: var(--primary);
            transform: translateY(-1px);
        }

        .lead-name {
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            letter-spacing: -0.01em;
        }

        .lead-info {
            font-size: 0.75rem;
            color: #334155;
            margin-bottom: 0.375rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .lead-followup {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #ffedd5;
            margin-top: 0.75rem;
            font-weight: 700;
        }

        .card-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.375rem;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .lead-card:hover .card-actions {
            opacity: 1;
        }

        .action-btn,
        .wa-btn,
        .call-btn {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            cursor: pointer;
            border: 1px solid #e8edf3;
            background: #f8fafc;
            color: #64748b;
            transition: all 0.18s ease;
            text-decoration: none;
        }

        .action-btn:hover {
            background: #eef2ff;
            color: var(--primary);
            border-color: #c7d2fe;
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
            transform: translateY(-1px);
        }

        .action-btn.followup:hover {
            background: #ecfdf5;
            color: #059669;
            border-color: #6ee7b7;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.2);
        }

        .wa-btn:hover {
            background: #f0fdf4;
            color: #25d366;
            border-color: #86efac;
            box-shadow: 0 2px 6px rgba(37, 211, 102, 0.22);
            transform: translateY(-1px);
        }

        .call-btn:hover {
            background: #f0f9ff;
            color: #0284c7;
            border-color: #7dd3fc;
            box-shadow: 0 2px 6px rgba(2, 132, 199, 0.2);
            transform: translateY(-1px);
        }

        /* Category Badges */
        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-green {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Lost Lead Styling */
        .lead-card.lost {
            background: #fff5f5 !important;
            border-color: #fecaca !important;
        }

        .leads-table tr.lost {
            background: #fff5f5 !important;
        }

        /* ── Filter Bar ───────────────────────────────────────── */
        .filter-section {
            background: linear-gradient(135deg, #fff 0%, #fafbff 100%);
            padding: 0.75rem 1.25rem;
            border-radius: 0.875rem;
            margin-bottom: 1.25rem;
            border: 1px solid #e8edf6;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 4px rgba(99, 102, 241, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            flex-wrap: nowrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .filter-group label {
            font-size: 0.6rem;
            font-weight: 700;
            color: #a5b4c8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            white-space: nowrap;
        }

        .filter-select {
            padding: 0.375rem 1.875rem 0.375rem 0.625rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            font-size: 0.74rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.45rem center;
            background-size: 0.78rem;
            min-width: 120px;
        }

        .filter-select:hover {
            border-color: #a5b4fc;
            background-color: #f5f3ff;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
        }

        /* ── View Switcher ─────────────────────────────────────── */
        .view-switcher {
            display: flex;
            background: #f1f5f9;
            padding: 3px;
            border-radius: 8px;
            gap: 2px;
            border: 1px solid #e2e8f0;
        }

        .view-btn {
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.18s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .view-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 1px 4px rgba(99, 102, 241, 0.18), 0 1px 2px rgba(0, 0, 0, 0.08);
        }

        /* ── Pagination ─────────────────────────────────────── */
        .pagination-btn {
            min-width: 32px;
            height: 32px;
            padding: 0 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .pagination-btn:hover {
            border-color: #a5b4fc;
            color: var(--primary);
            background: #eef2ff;
            box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, var(--primary, #6366f1), var(--accent, #8b5cf6));
            color: white;
            border-color: transparent;
            box-shadow: 0 3px 8px rgba(99, 102, 241, 0.35);
        }

        .pagination-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #f8fafc;
        }

        /* ── Data Table ───────────────────────────────────────── */
        .leads-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .leads-table thead {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .leads-table th {
            background: linear-gradient(to bottom, #f5f7ff, #f1f5f9);
            padding: 0.55rem 0.75rem;
            text-align: left;
            font-weight: 700;
            color: #6370a0;
            text-transform: uppercase;
            font-size: 0.6rem;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        .leads-table td {
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.75rem;
        }

        .leads-table tbody tr:hover td {
            background: rgba(99, 102, 241, 0.03);
        }

        .table-lead-name {
            font-weight: 700;
            color: #0f172a;
            display: inline-block;
            margin-bottom: 0;
            font-size: 0.76rem;
        }

        .table-lead-sub {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 500;
        }

        @keyframes floatBarInTop {
            from {
                opacity: 0;
                transform: translate(-50%, -15px) scale(0.97);
            }
            to {
                opacity: 1;
                transform: translate(-50%, 0) scale(1);
            }
        }

        #bulkActionBar {
            display: none;
            position: fixed;
            top: <?= isset($_SESSION['original_user']) ? '7.75rem' : '5.25rem' ?>;
            left: calc(50% + 120px);
            transform: translateX(-50%);
            background: #0f172a;
            color: white;
            padding: 0.55rem 1rem;
            border-radius: 12px;
            box-shadow: 0 20px 35px -5px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.15);
            z-index: 1050;
            align-items: center;
            gap: 0.65rem;
            backdrop-filter: blur(16px);
            animation: floatBarInTop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            max-width: calc(100vw - 280px);
            overflow-x: auto;
            white-space: nowrap;
        }

        #mainSidebar.collapsed ~ .main-content #bulkActionBar {
            left: calc(50% + 40px);
            max-width: calc(100vw - 120px);
        }

        @media (max-width: 1024px) {
            #bulkActionBar {
                left: 50% !important;
                max-width: calc(100vw - 32px);
            }
        }

        #bulkActionBar::-webkit-scrollbar {
            height: 3px;
        }

        #bulkActionBar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        <?php if ($isExecutive): ?>
            .executive-hidden {
                display: none !important;
            }

        <?php endif; ?>
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>
            <header class="header">
                <div>
                    <h1 class="page-title">Sales In Progress</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Manage your active
                        leads and deal conversions</p>
                </div>
                <div class="header-actions" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <?php if (!$isExecutive): ?>
                        <a href="<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=template" class="btn btn-ghost"
                            style="height: 38px; border: 1px solid var(--border); margin:0; display: inline-flex; align-items: center; text-decoration: none; color: inherit; white-space: nowrap; flex-shrink: 0;">
                            <i class="fas fa-file-csv"></i>Template
                        </a>
                        <button class="btn btn-ghost" onclick="openExportModal()"
                            style="height: 38px; border: 1px solid var(--border); margin:0; white-space: nowrap; flex-shrink: 0;">
                            <i class="fas fa-file-export"></i> Bulk Export
                        </button>
                        <button class="btn btn-ghost" onclick="toggleModal('importModal')"
                            style="height: 38px; border: 1px solid var(--border); margin:0; white-space: nowrap; flex-shrink: 0;">
                            <i class="fas fa-file-import"></i> Import
                        </button>
                        <input type="file" id="importFile" accept=".csv" style="display:none;" onchange="importLeads(this)">
                    <?php endif; ?>
                    <div style="position:relative; flex-shrink: 1;">
                        <i class="fas fa-search"
                            style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size: 0.75rem; color:#94a3b8;"></i>
                        <input type="text" id="leadSearch" placeholder="Filter by name or mobile..." class="form-input"
                            style="padding-left: 2.25rem; width: 190px; font-size: 0.75rem; height: 38px; margin:0;">
                    </div>
                    <button class="btn btn-ghost" onclick="toggleTransferredFilter()" id="btnTransferredOnly"
                        style="height: 38px; border: 1.5px solid var(--border); margin:0; display: inline-flex; align-items: center; gap: 0.45rem; color: #475569; font-weight: 700; font-size: 0.75rem; white-space: nowrap; flex-shrink: 0; padding: 0 0.75rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;"
                        title="Toggle to show only transferred leads">
                        <i class="fas fa-right-left" style="color: #6366f1; font-size: 0.75rem;"></i>
                        <span>Transferred</span>
                        <span id="transferredCounter" style="background: #e0e7ff; color: #4338ca; border-radius: 9999px; padding: 2px 7px; font-size: 0.68rem; font-weight: 800; line-height: 1;">0</span>
                    </button>
                    <button class="btn btn-ghost" onclick="showAllLeads()" id="btnShowAllLeads"
                        style="height: 38px; border: 1px solid var(--border); margin:0; display: inline-flex; align-items: center; gap: 0.5rem; color: #475569; font-weight: 600; white-space: nowrap; flex-shrink: 0;">
                        <i class="fas fa-globe-asia" style="color: var(--primary);"></i> Show All
                    </button>
                    <button class="btn btn-primary" onclick="toggleModal('leadModal')"
                        style="height: 38px; padding: 0 1.25rem; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; flex-shrink: 0; margin:0;">
                        <i class="fas fa-plus" style="font-size: 0.75rem;"></i> New Lead
                    </button>
                </div>
            </header>

            <div class="filter-section" style="justify-content: flex-start; overflow-x: auto; white-space: nowrap;">
                <div style="display: flex; gap: 0.875rem; align-items: center; width: max-content; padding-right: 1.5rem;">
                    <div class="filter-group">
                        <label>Month</label>
                        <select id="filterMonth" class="filter-select" style="min-width: 120px;"
                            onchange="allLeadsMode=false; fetchLeads()">
                            <?php
                            $currentMonth = (int) date('n');
                            for ($m = 1; $m <= 12; $m++) {
                                $selected = ($m == $currentMonth) ? 'selected' : '';
                                echo "<option value='$m' $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Year</label>
                        <select id="filterYear" class="filter-select" style="min-width: 100px;"
                            onchange="allLeadsMode=false; fetchLeads()">
                            <?php
                            $currentYear = (int) date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 2; $y--) {
                                $selected = ($y == $currentYear) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Pipeline</label>
                        <select class="filter-select" data-filter="status">
                            <option value="all">All Statuses</option>
                            <option value="new">🆕 New</option>
                            <option value="in_progress">⚡ Interested</option>
                            <option value="won">✅ Client Done</option>
                            <option value="lost">❌ Lost/Wrong Lead</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Transfer</label>
                        <select class="filter-select" data-filter="transfer" id="filterTransfer"
                            style="min-width: 135px;" onchange="handleTransferDropdownChange(this.value)">
                            <option value="all">All Leads</option>
                            <option value="transferred">🔄 Transferred Only</option>
                            <option value="not_transferred">Direct Only</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Per Page</label>
                        <select id="perPage" class="filter-select" style="min-width: 80px;"
                            onchange="currentFilters.page = 1; sessionStorage.setItem('leads_page', 1); renderLeads(allLeads)">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="500">500</option>
                            <option value="1000">1000</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Category</label>
                        <select class="filter-select" data-filter="category" onchange="renderLeads(allLeads)">
                            <option value="all">All Types</option>
                            <option value="red">🔴 Red</option>
                            <option value="green">🟢 Green</option>
                            <option value="yellow">🟡 Yellow</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Source</label>
                        <select class="filter-select" data-filter="source" onchange="renderLeads(allLeads)">
                            <option value="all">All Sources</option>
                            <option value="facebook">Facebook</option>
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                            <option value="ads">Ads</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Pipeline View -->
            <div class="pipeline-container" id="kanbanBoard" style="display: none;">
                <div class="pipeline-column" data-status="new">
                    <div class="pipeline-header">
                        <span>🆕 New</span>
                        <span class="badge counter" style="background:#cbd5e1; color:#334155;">0</span>
                    </div>
                    <div class="leads-list"></div>
                </div>
                <div class="pipeline-column" data-status="in_progress">
                    <div class="pipeline-header">
                        <span>⚡ Interested</span>
                        <span class="badge counter" style="background:#fef3c7; color:#92400e;">0</span>
                    </div>
                    <div class="leads-list"></div>
                </div>
                <div class="pipeline-column" data-status="won">
                    <div class="pipeline-header">
                        <span>✅ Client Done</span>
                        <span class="badge counter" style="background:#d1fae5; color:#065f46;">0</span>
                    </div>
                    <div class="leads-list"></div>
                </div>
                <div class="pipeline-column" data-status="lost">
                    <div class="pipeline-header">
                        <span>❌ Lost/Wrong Lead</span>
                        <span class="badge counter" style="background:#fee2e2; color:#991b1b;">0</span>
                    </div>
                    <div class="leads-list"></div>
                </div>
            </div>

            <!-- Table View -->
            <div id="tableView"
                style="display: block; background: white; border-radius: 1rem; border: 1px solid var(--border); overflow: auto; max-height: calc(100vh - 280px);">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAllLeads"
                                    onchange="toggleSelectAll(this)"></th>
                            <th style="width: 50px;">Sr.</th>
                            <th>Client</th>
                            <th>Service/Requirement</th>
                            <th>Lead Mgr</th>
                            <th>Task Mgr</th>
                            <th>Lead Status</th>
                            <th>Task Status</th>
                            <th>Interaction Follow-up</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

            <!-- Pagination Container -->
            <div id="paginationContainer"
                style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; background: white; padding: 1rem 1.5rem; border-radius: 0.75rem; border: 1px solid var(--border);">
                <div id="paginationInfo" style="font-size: 0.8125rem; color: var(--text-muted); font-weight: 600;">
                    Showing 0 to 0 of 0 leads
                </div>
                <div id="paginationButtons" style="display: flex; gap: 0.25rem;">
                    <!-- Buttons will be injected by JS -->
                </div>
            </div>

            <!-- Bulk Action Bar -->
            <div id="bulkActionBar">
                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; white-space: nowrap;">
                    <span id="selectedCount"
                        style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 800; min-width: 20px; text-align: center;">0</span>
                    <span style="font-size: 0.8rem; font-weight: 700; letter-spacing: 0.01em;">Selected</span>
                </div>

                <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.18);"></div>

                <!-- Bulk Lead Transfer -->
                <div style="display: flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                    <select id="bulkTransferEmployee" class="form-input"
                        style="background: rgba(255,255,255,0.08); color: white; border: 1px solid rgba(255,255,255,0.2); height: 32px; font-size: 0.75rem; width: 135px; appearance: auto; border-radius: 6px; padding: 0 0.5rem;">
                        <option value="" style="color: black;">Transfer to...</option>
                        <?php foreach ($employees as $emp)
                            echo "<option value='{$emp['id']}' style='color: black;'>" . htmlspecialchars($emp['name']) . "</option>"; ?>
                    </select>
                    <button onclick="bulkTransferLeads()" class="btn"
                        style="height: 32px; font-size: 0.75rem; background: #6366f1; color: white; border: none; padding: 0 0.75rem; border-radius: 6px; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-right-left" style="font-size: 0.65rem;"></i> Transfer
                    </button>
                </div>

                <?php if (!$isExecutive): ?>
                    <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.18);"></div>

                    <div style="display: flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                        <select id="bulkAssignUser" class="form-input"
                            style="background: rgba(255,255,255,0.08); color: white; border: 1px solid rgba(255,255,255,0.2); height: 32px; font-size: 0.75rem; width: 120px; appearance: auto; border-radius: 6px; padding: 0 0.5rem;">
                            <option value="" style="color: black;">Lead Mgr...</option>
                            <?php foreach ($users as $u)
                                echo "<option value='{$u['id']}' style='color: black;'>" . htmlspecialchars($u['name']) . "</option>"; ?>
                        </select>

                        <select id="bulkAssignEmployee" class="form-input"
                            style="background: rgba(255,255,255,0.08); color: white; border: 1px solid rgba(255,255,255,0.2); height: 32px; font-size: 0.75rem; width: 120px; appearance: auto; border-radius: 6px; padding: 0 0.5rem;">
                            <option value="" style="color: black;">Task Mgr...</option>
                            <?php foreach ($employees as $emp)
                                echo "<option value='{$emp['id']}' style='color: black;'>" . htmlspecialchars($emp['name']) . "</option>"; ?>
                        </select>

                        <button onclick="bulkAssign()" class="btn btn-primary"
                            style="height: 32px; font-size: 0.75rem; padding: 0 0.75rem; border: none; font-weight: 700; border-radius: 6px; white-space: nowrap;">
                            Apply
                        </button>
                    </div>
                <?php endif; ?>

                <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.18);"></div>

                <div style="display: flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                    <select id="bulkCategory" class="form-input"
                        style="background: rgba(255,255,255,0.08); color: white; border: 1px solid rgba(255,255,255,0.2); height: 32px; font-size: 0.75rem; width: 110px; appearance: auto; border-radius: 6px; padding: 0 0.5rem;">
                        <option value="" style="color: black;">Category...</option>
                        <option value="red" style="color: #ef4444; font-weight: 700;">🔴 Red</option>
                        <option value="green" style="color: #22c55e; font-weight: 700;">🟢 Green</option>
                        <option value="yellow" style="color: #f59e0b; font-weight: 700;">🟡 Yellow</option>
                    </select>
                    <button onclick="bulkSetCategory()" class="btn"
                        style="height: 32px; font-size: 0.75rem; background: #334155; color: white; border: none; padding: 0 0.65rem; border-radius: 6px; font-weight: 700; white-space: nowrap;">
                        Set
                    </button>
                </div>

                <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.18);"></div>

                <button onclick="bulkEmail()" class="btn"
                    style="height: 32px; font-size: 0.75rem; background: #2563eb; color: white; border: none; padding: 0 0.75rem; border-radius: 6px; font-weight: 700; display:inline-flex; align-items:center; gap:5px; white-space: nowrap;">
                    <i class="fas fa-envelope"></i> Email
                </button>

                <button onclick="bulkWhatsApp()" class="btn"
                    style="height: 32px; font-size: 0.75rem; background: #16a34a; color: white; border: none; padding: 0 0.75rem; border-radius: 6px; font-weight: 700; display:inline-flex; align-items:center; gap:5px; white-space: nowrap;">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </button>

                <button onclick="openExportModal()" class="btn"
                    style="height: 32px; font-size: 0.75rem; background: #059669; color: white; border: none; padding: 0 0.75rem; border-radius: 6px; font-weight: 700; display:inline-flex; align-items:center; gap:5px; white-space: nowrap;">
                    <i class="fas fa-file-export"></i> Export
                </button>

                <button onclick="clearSelection()" title="Clear Selection"
                    style="background: transparent; color: #94a3b8; border: none; cursor: pointer; font-size: 0.95rem; padding: 4px 6px; margin-left: 0.2rem; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s;"
                    onmouseover="this.style.color='#ef4444'; this.style.background='rgba(255,255,255,0.1)'"
                    onmouseout="this.style.color='#94a3b8'; this.style.background='transparent'">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
        </main>
    </div>

    <!-- Transfer Lead Modal -->
    <div id="transferLeadModal" class="modal-overlay"
        style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:2000; align-items:center; justify-content:center;">
        <div class="modal-content"
            style="max-width: 480px; width: 90%; background:white; border-radius:12px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
            <div
                style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border, #e2e8f0); display:flex; justify-content: space-between; align-items: center; background:#f8fafc;">
                <div>
                    <h3
                        style="font-size: 1.1rem; font-weight: 800; color: var(--primary, #0f172a); display:flex; align-items:center; gap:8px; margin:0;">
                        <i class="fas fa-right-left" style="color:var(--primary, #3b82f6);"></i> Transfer Lead
                    </h3>
                    <p style="font-size: 0.75rem; color: #64748b; margin-top:2px; margin-bottom:0;">Transfer lead to
                        another employee in organization</p>
                </div>
                <button onclick="closeTransferModal()" class="btn-ghost"
                    style="border:none; background:none; cursor:pointer; font-size:1.2rem; color:#64748b;"><i
                        class="fas fa-times"></i></button>
            </div>
            <form id="transferLeadForm" onsubmit="submitTransferLead(event)"
                style="padding: 1.5rem; display:flex; flex-direction:column; gap:1.25rem;">
                <input type="hidden" id="transferLeadId" name="lead_id" value="">

                <div>
                    <label
                        style="display:block; font-size:0.75rem; font-weight:700; color:#475569; margin-bottom:0.4rem;">Lead
                        Details</label>
                    <div id="transferLeadInfoBox"
                        style="background:#f8fafc; border:1px solid #e2e8f0; padding:0.75rem 1rem; border-radius:8px; font-size:0.8rem; font-weight:600; color:#1e293b;">
                    </div>
                </div>

                <div>
                    <label
                        style="display:block; font-size:0.75rem; font-weight:700; color:#475569; margin-bottom:0.4rem;">Target
                        Employee <span style="color:#ef4444;">*</span></label>
                    <select id="transferTargetEmployee" name="target_employee_id" class="form-input" required
                        style="width:100%; height:40px; appearance:auto; border-radius:6px; border:1px solid #cbd5e1; padding:0 0.75rem; font-size:0.85rem;">
                        <option value="">-- Select Target Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['name']) ?>
                                <?= !empty($emp['designation']) ? ' (' . htmlspecialchars($emp['designation']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label
                        style="display:block; font-size:0.75rem; font-weight:700; color:#475569; margin-bottom:0.4rem;">Transfer
                        Note / Reason (Optional)</label>
                    <textarea id="transferRemark" name="remark" class="form-input" rows="3"
                        placeholder="Enter reason or note for transferring lead..."
                        style="width:100%; font-size:0.85rem; border-radius:6px; border:1px solid #cbd5e1; padding:0.5rem 0.75rem; resize:vertical;"></textarea>
                </div>

                <div
                    style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px solid #f1f5f9; padding-top:1rem; margin-top:0.5rem;">
                    <button type="button" onclick="closeTransferModal()" class="btn btn-secondary"
                        style="padding:0.5rem 1rem; border-radius:6px;">Cancel</button>
                    <button type="submit" id="btnSubmitTransfer" class="btn btn-primary"
                        style="padding:0.5rem 1.25rem; font-weight:700; border-radius:6px; display:flex; align-items:center; gap:6px;"><i
                            class="fas fa-paper-plane"></i> Transfer Lead</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit/Add Lead Modal -->
    <div id="leadModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 900px; width: 95%;">
            <div
                style="padding: 1.5rem; border-bottom: 1px solid var(--border); display:flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 1.125rem; font-weight: 800; letter-spacing: -0.02em;">Lead Command Center</h2>
                    <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Manage detailed client
                        information and requirements</p>
                </div>
                <button onclick="toggleModal('leadModal')" class="btn-ghost"
                    style="width: 32px; height: 32px; border-radius: 50%; display:flex; align-items:center; justify-content:center; padding:0;">
                    <i class="fas fa-times" style="font-size: 0.875rem;"></i>
                </button>
            </div>

            <div style="display: flex; gap: 0;">
                <!-- Full Width: Form -->
                <form id="leadForm" style="flex: 1; padding: 1.5rem; max-height: 75vh; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label>Business Name / Client</label>
                            <input type="text" name="name" class="form-input" placeholder="e.g. John Smith">
                        </div>
                        <div>
                            <label>Mobile Number</label>
                            <input type="tel" name="mobile" id="leadMobile" maxlength="10" required class="form-input"
                                inputmode="numeric" pattern="[0-9]{10}"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                                placeholder="e.g. 9876543210">
                            <div id="duplicateWarning"
                                style="display:none; margin-top:0.5rem; padding:0.5rem 0.75rem; background:#fff7ed; border:1.5px solid #fed7aa; border-radius:0.5rem; font-size:0.72rem; font-weight:700; color:#c2410c;">
                                <i class="fas fa-exclamation-triangle"></i> <span id="duplicateMsg"></span>
                                <a id="duplicateLink" href="#"
                                    style="color:var(--primary); margin-left:0.5rem; text-decoration:underline;">View
                                    Lead →</a>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label>Email ID</label>
                            <input type="email" name="email" class="form-input" placeholder="john@example.com">
                        </div>
                        <div>
                            <label>Address / Location</label>
                            <input type="text" name="address" class="form-input" placeholder="e.g. Buxar, Patna">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="executive-hidden"
                            style="display: flex; gap: 1rem; align-items: center; padding-top: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                <input type="checkbox" name="is_whatsapp" value="1" style="width: 16px; height: 16px;">
                                <span style="font-size: 0.75rem; font-weight: 700;">Whatsapp Available</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                <input type="checkbox" name="is_call" value="1" style="width: 16px; height: 16px;">
                                <span style="font-size: 0.75rem; font-weight: 700;">Call Connected</span>
                            </label>
                        </div>
                        <div>
                            <label>Referral By (Employee)</label>
                            <select name="referral_person" class="form-input"
                                style="appearance: auto; <?= $isExecutive ? 'pointer-events: none; background: #f8fafc; opacity: 0.8;' : '' ?>">
                                <option value="">— None / Walk-in —</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= htmlspecialchars($emp['name']) ?>"
                                        <?= ((\Core\Auth::userName() === $emp['name']) ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($emp['name']) ?>
                                        <?= $emp['designation'] ? '(' . htmlspecialchars($emp['designation']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="executive-hidden"
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display:flex;align-items:center;gap:0.5rem;">
                                Assigned Staff
                            </label>
                            <select name="assigned_employee_id" class="form-input" style="appearance: auto;">
                                <option value="">-- No Employee Assigned --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>">
                                        <?= htmlspecialchars($emp['name']) ?>
                                        <?= $emp['designation'] ? '(' . htmlspecialchars($emp['designation']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Lead Status</label>
                            <select name="status" class="form-input" style="appearance: auto;">
                                <option value="new">🆕 New</option>
                                <option value="in_progress">⚡ Interested</option>
                                <option value="won">✅ Client Done</option>
                                <option value="lost">❌ Lost/Wrong Lead</option>
                            </select>
                        </div>
                    </div>

                    <div class="executive-hidden"
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label>Lead Category</label>
                            <select name="category" class="form-input" style="appearance: auto;">
                                <option value="">Select Category</option>
                                <option value="red">Red</option>
                                <option value="green">Green</option>
                                <option value="yellow">Yellow</option>
                            </select>
                        </div>
                        <div>
                            <label>Task Status</label>
                            <select name="task_status" class="form-input" style="appearance: auto;">
                                <option value="not_started">NOT STARTED</option>
                                <option value="work_in_progress">WORK IN PROGRESS</option>
                                <option value="work_pending">WORK PENDING</option>
                                <option value="work_done">WORK DONE</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label>Source</label>
                            <select name="source" class="form-input" style="appearance: auto;"
                                onchange="toggleReferralInput(this.value)">
                                <option value="facebook">Facebook</option>
                                <option value="website">Website</option>
                                <option value="referral">Referral</option>
                                <option value="ads">Ads</option>
                            </select>
                        </div>
                        <div id="referralCustomerContainer" style="display: none;">
                            <!-- Tab switcher -->
                            <div
                                style="display:flex; gap:0; margin-bottom:0.5rem; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">
                                <button type="button" id="refTabCustomer" onclick="switchRefTab('customer')"
                                    style="flex:1; padding:5px 10px; font-size:0.72rem; font-weight:700; border:none; cursor:pointer; background:var(--primary); color:white; transition:all 0.15s;">
                                    <i class="fas fa-users"></i> Customer
                                </button>
                                <button type="button" id="refTabCustom" onclick="switchRefTab('custom')"
                                    style="flex:1; padding:5px 10px; font-size:0.72rem; font-weight:700; border:none; cursor:pointer; background:#f1f5f9; color:#64748b; transition:all 0.15s;">
                                    <i class="fas fa-pen"></i> Custom Name
                                </button>
                            </div>
                            <label id="refLabelCustomer"
                                style="font-size:0.75rem; font-weight:700; color:#475569;">Referral Customer</label>
                            <label id="refLabelCustom"
                                style="font-size:0.75rem; font-weight:700; color:#475569; display:none;">Referral
                                Name</label>
                            <!-- Customer select -->
                            <div id="refCustomerPanel">
                                <select name="referral_customer_id" class="form-input" style="appearance: auto;">
                                    <option value="">-- Select Customer --</option>
                                    <?php foreach ($customers as $cust): ?>
                                        <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?>
                                            (<?= $cust['mobile'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Custom name input -->
                            <div id="refCustomPanel" style="display:none;">
                                <input type="text" name="referral_custom_name" class="form-input"
                                    placeholder="e.g. Ramesh Gupta, Friend of client...">
                            </div>
                        </div>
                    </div>

                    <div class="executive-hidden"
                        style="background: #f8fafc; padding: 1.25rem; border-radius: 0.75rem; margin-bottom: 1.25rem; border: 1.5px solid #e2e8f0;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label>Deal Value (₹)</label>
                                <input type="number" step="0.01" name="deal_value" class="form-input" placeholder="0.00"
                                    style="font-weight: 700;">
                            </div>
                            <div>
                                <label>Comm. Percent (%)</label>
                                <input type="number" step="0.01" name="commission_percent" class="form-input"
                                    placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label>Remark</label>
                        <textarea name="requirement" class="form-input" style="height: 80px; resize: vertical;"
                            placeholder="Client's specific requirements, product interest, notes..."></textarea>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <label style="font-weight: 800; margin: 0;">Selected Services</label>
                            <div style="position: relative; width: 200px;">
                                <i class="fas fa-search"
                                    style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.75rem;"></i>
                                <input type="text" id="serviceSearch" class="form-input"
                                    placeholder="Search services..."
                                    style="padding-left: 28px; height: 30px; font-size: 0.7rem; border-radius: 20px;">
                            </div>
                        </div>
                        <div id="requirementsSelection"
                            style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; background: #f8fafc; padding: 1rem; border-radius: 0.75rem; border: 1.5px solid #e2e8f0; max-height: 200px; overflow-y: auto;">
                            <?php foreach ($requirements as $req): ?>
                                <label class="service-item"
                                    style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer; font-size: 0.8125rem;">
                                    <input type="checkbox" name="requirement_ids[]" value="<?= $req['id'] ?>"
                                        data-fee="<?= $req['fee'] ?>" class="req-checkbox" style="margin-top: 0.2rem;">
                                    <span>
                                        <strong><?= htmlspecialchars($req['name']) ?></strong><br>
                                        <small style="color: #64748b;">₹<?= number_format($req['fee'], 2) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>

                            <?php if (empty($requirements)): ?>
                                <div
                                    style="grid-column: span 2; text-align: center; color: #94a3b8; font-size: 0.75rem; padding: 1rem;">
                                    No services defined. <a href="<?= APP_URL ?>/public/index.php/requirements"
                                        style="color: var(--primary);">Add now →</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div id="customServicesWrapper"
                            style="margin-top: 1rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                            <label
                                style="font-size: 0.7rem; color: var(--primary); font-weight: 800; display: flex; justify-content: space-between; align-items: center;">
                                MANUALLY ADDED SERVICES
                                <button type="button" onclick="addCustomServiceRow()"
                                    style="background: var(--primary); color: white; border: none; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; cursor: pointer;">
                                    <i class="fas fa-plus"></i> Add Service
                                </button>
                            </label>
                            <div id="customServicesContainer"
                                style="margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                <!-- Rows will be added here -->
                            </div>
                        </div>
                        <div
                            style="margin-top: 0.5rem; font-size: 0.75rem; font-weight: 700; color: var(--primary); text-align: right;">
                            Total Charge: ₹<span id="selectedRequirementsTotal">0.00</span>
                        </div>
                    </div>

                    <div
                        style="display:flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border);">
                        <button type="submit" id="saveLeadBtn" class="btn btn-primary"
                            style="width: 100%; height: 42px;">
                            <i class="fas fa-save" style="font-size: 0.75rem;"></i> Save Lead Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Follow-up & History Modal -->
    <div id="quickFollowModal" class="modal-overlay">
        <div class="modal-content"
            style="max-width: 800px; width: 95%; display: flex; flex-direction: row; overflow: hidden; height: 500px;">
            <!-- Left: Interaction Form -->
            <form id="quickFollowForm"
                style="flex: 1; padding: 1.5rem; border-right: 1px solid var(--border); display: flex; flex-direction: column;">
                <div
                    style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 style="font-size: 1rem; font-weight: 800; color: var(--primary); margin: 0;">Quick Follow-up
                        </h2>
                        <p style="font-size: 0.65rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">For <span
                                id="qf_lead_name" style="color:var(--text-main); font-weight:700;">Client</span></p>
                    </div>
                </div>

                <input type="hidden" name="lead_id" id="qf_lead_id">

                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.65rem;">Next Follow-up Date</label>
                    <input type="date" name="follow_up_date" id="qf_date" required class="form-input"
                        style="height: 36px; font-size: 0.75rem;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.65rem;">Interaction Remark</label>
                    <textarea name="remark" required class="form-input" rows="3"
                        placeholder="e.g. Discussed about NGO registration..."
                        style="font-size: 0.75rem; min-height: 80px;"></textarea>
                </div>

                <div
                    style="margin-bottom: 1.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.75rem;">
                    <label
                        style="font-size:0.65rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; display:block; margin-bottom:0.5rem;">Pipeline
                        Status</label>
                    <select name="lead_status" id="qf_lead_status" class="form-input"
                        style="appearance:auto; font-size:0.75rem; height: 36px;">
                        <option value="">— Keep current status —</option>
                        <option value="new">🆕 New</option>
                        <option value="in_progress">⚡ Interested</option>
                        <option value="won">✅ Client Done</option>
                        <option value="lost">❌ Lost/Wrong Lead</option>
                    </select>
                </div>

                <div style="margin-top: auto; display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('quickFollowModal').style.display='none'"
                        style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1.5;">Save Follow-up</button>
                </div>
            </form>

            <!-- Right: Engagement History -->
            <div style="flex: 1.2; background: #fcfcfd; display: flex; flex-direction: column; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3
                        style="font-size: 0.8rem; font-weight: 800; color: #475569; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-history" style="color: var(--primary);"></i> Engagement History
                    </h3>
                    <button onclick="document.getElementById('quickFollowModal').style.display='none'" class="icon-btn"
                        style="border:none; background:none; cursor:pointer; color:#94a3b8;"><i
                            class="fas fa-times"></i></button>
                </div>
                <div id="followupList" style="flex-grow: 1; overflow-y: auto; padding-right: 0.5rem;">
                    <!-- History items injected here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Remark Detail Modal -->
    <div id="remarkDetailModal" class="modal-overlay">
        <div class="modal-content"
            style="max-width: 500px; width: 95%; border-radius: 1.25rem; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div
                style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary, #4338ca) 100%); padding: 1.5rem; color: white; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h2
                        style="font-size: 1.25rem; font-weight: 800; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-comment-alt"></i> Follow-up Detail
                    </h2>
                    <p id="rd_lead_name"
                        style="font-size: 0.8125rem; opacity: 0.9; margin: 0.25rem 0 0 0; font-weight: 500;"></p>
                </div>
                <button onclick="toggleModal('remarkDetailModal')" class="btn-ghost"
                    style="background: rgba(255,255,255,0.15); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div style="padding: 1.5rem; background: #fff;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div
                        style="background: #f8fafc; padding: 0.75rem; border-radius: 0.75rem; border: 1px solid #e2e8f0;">
                        <span
                            style="display: block; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Interaction
                            Date</span>
                        <span id="rd_interaction_date"
                            style="font-size: 0.8125rem; font-weight: 700; color: #1e293b;"></span>
                    </div>
                    <div
                        style="background: #f8fafc; padding: 0.75rem; border-radius: 0.75rem; border: 1px solid #e2e8f0;">
                        <span
                            style="display: block; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Interacted
                            By</span>
                        <span id="rd_user" style="font-size: 0.8125rem; font-weight: 700; color: #1e293b;"></span>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <span
                        style="display: block; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem; padding-left: 0.25rem;">Full
                        Remark</span>
                    <div id="rd_remark"
                        style="background: #f1f5f9; padding: 1.25rem; border-radius: 1rem; color: #334155; font-size: 0.875rem; line-height: 1.6; border-left: 4px solid var(--primary); font-style: italic;">
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem;">
                    <div id="rd_next_followup"
                        style="flex: 1; background: #fffbeb; padding: 0.75rem; border-radius: 0.75rem; border: 1px solid #fef3c7; display: flex; align-items: center; gap: 0.6rem;">
                        <i class="fas fa-calendar-check" style="color: #d97706;"></i>
                        <div>
                            <span
                                style="display: block; font-size: 0.6rem; font-weight: 700; color: #92400e; text-transform: uppercase;">Next
                                Follow-up</span>
                            <span id="rd_next_date"
                                style="font-size: 0.8125rem; font-weight: 800; color: #92400e;"></span>
                        </div>
                    </div>
                    <div id="rd_call_status"
                        style="flex: 1; padding: 0.75rem; border-radius: 0.75rem; display: flex; align-items: center; gap: 0.6rem;">
                        <i id="rd_status_icon" class="fas fa-phone-alt"></i>
                        <div>
                            <span
                                style="display: block; font-size: 0.6rem; font-weight: 700; opacity: 0.8; text-transform: uppercase;">Status</span>
                            <span id="rd_status_text" style="font-size: 0.8125rem; font-weight: 800;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                style="background: #f8fafc; padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                <button onclick="toggleModal('remarkDetailModal')" class="btn btn-primary"
                    style="height: 36px; padding: 0 1.5rem; font-size: 0.75rem;">Close Details</button>
            </div>
        </div>
    </div>

    <!-- Category Remark Modal (Enhanced) -->
    <div id="categoryRemarkModal" class="modal-overlay"
        style="display:none; align-items:center; justify-content:center; backdrop-filter: blur(4px); background: rgba(15, 23, 42, 0.6);">
        <div class="modal-content"
            style="max-width: 480px; border-radius: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: modalScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <div
                style="padding: 1.75rem; border-bottom: 1px solid #f1f5f9; background: linear-gradient(to bottom right, #ffffff, #f8fafc);border-radius:20px">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div
                        style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--primary, #6366f1)); color: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px -4px rgba(79, 70, 229, 0.3);">
                        <i class="fas fa-pen-nib" style="font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h3
                            style="margin:0; font-size: 1.25rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
                            Category Update</h3>
                        <p style="margin: 0.2rem 0 0 0; font-size: 0.75rem; color: #64748b; font-weight: 500;">Logging
                            interaction history</p>
                    </div>
                </div>
            </div>
            <div style="padding: 1.75rem;">
                <label
                    style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Internal
                    Remark</label>
                <textarea id="cat_remark_text" class="form-input" placeholder="What happened in this interaction?"
                    style="width: 100%; min-height: 140px; padding: 1.25rem; border-radius: 1rem; resize: none; font-size: 0.9375rem; margin-bottom: 1.75rem; border: 1.5px solid #e2e8f0; transition: all 0.2s; line-height: 1.6; background: #fcfdfe;"
                    onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 4px rgba(79, 70, 229, 0.1)'; this.style.background='#fff';"
                    onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'; this.style.background='#fcfdfe';"></textarea>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; align-items: center;">
                    <button onclick="document.getElementById('categoryRemarkModal').style.display='none'"
                        class="btn-ghost"
                        style="height: 48px; padding: 0 1.5rem; font-size: 0.875rem; font-weight: 600; color: #64748b; border-radius: 0.875rem;">Discard</button>
                    <button id="btnSaveCatRemark" class="btn btn-primary"
                        style="height: 48px; padding: 0 2rem; font-size: 0.875rem; font-weight: 700; border-radius: 0.875rem; background: linear-gradient(to bottom, var(--primary), var(--primary, #4338ca)); border: none; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3), 0 4px 6px -4px rgba(79, 70, 229, 0.3); transition: all 0.2s;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 20px 25px -5px rgba(79, 70, 229, 0.4)';"
                        onmouseout="this.style.transform='none'; this.style.boxShadow='0 10px 15px -3px rgba(79, 70, 229, 0.3)';"
                        onmousedown="this.style.transform='scale(0.98)'"
                        onmouseup="this.style.transform='translateY(-2px)'">
                        Confirm Update
                    </button>
                </div>
            </div>
        </div>
    </div>
    <style>
        @keyframes modalScaleUp {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
    </style>

    <!-- Interaction Remark Modal (Enhanced) -->
    <div id="interactionRemarkModal" class="modal-overlay"
        style="display:none; align-items:center; justify-content:center; backdrop-filter: blur(4px); background: rgba(15, 23, 42, 0.6);">
        <div class="modal-content"
            style="max-width: 480px; border-radius: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: modalScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <div
                style="padding: 1.75rem; border-bottom: 1px solid #f1f5f9; background: linear-gradient(to bottom right, #ffffff, #f8fafc); border-radius: 20px;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div
                        style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px -4px rgba(16, 185, 129, 0.3);">
                        <i class="fas fa-comment-dots" style="font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h3
                            style="margin:0; font-size: 1.25rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
                            Interaction Update</h3>
                        <p style="margin: 0.2rem 0 0 0; font-size: 0.75rem; color: #64748b; font-weight: 500;">Logging
                            call outcome</p>
                    </div>
                </div>
            </div>
            <div style="padding: 1.75rem;">
                <label
                    style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Call
                    Remark</label>
                <textarea id="int_remark_text" class="form-input" placeholder="What happened during the call?"
                    style="width: 100%; min-height: 140px; padding: 1.25rem; border-radius: 1rem; resize: none; font-size: 0.9375rem; margin-bottom: 1.75rem; border: 1.5px solid #e2e8f0; transition: all 0.2s; line-height: 1.6; background: #fcfdfe;"
                    onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 4px rgba(16, 185, 129, 0.1)'; this.style.background='#fff';"
                    onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'; this.style.background='#fcfdfe';"></textarea>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; align-items: center;">
                    <button onclick="document.getElementById('interactionRemarkModal').style.display='none'"
                        class="btn-ghost"
                        style="height: 48px; padding: 0 1.5rem; font-size: 0.875rem; font-weight: 600; color: #64748b; border-radius: 0.875rem;">Discard</button>
                    <button id="btnSaveIntRemark" class="btn btn-primary"
                        style="height: 48px; padding: 0 2rem; font-size: 0.875rem; font-weight: 700; border-radius: 0.875rem; background: linear-gradient(to bottom, #10b981, #059669); border: none; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3), 0 4px 6px -4px rgba(16, 185, 129, 0.3); transition: all 0.2s;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 20px 25px -5px rgba(16, 185, 129, 0.4)';"
                        onmouseout="this.style.transform='none'; this.style.boxShadow='0 10px 15px -3px rgba(16, 185, 129, 0.3)';"
                        onmousedown="this.style.transform='scale(0.98)'"
                        onmouseup="this.style.transform='translateY(-2px)'">
                        Save Interaction
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Quick Assign Picker -->
    <div id="assignPickerOverlay"
        style="display:none; position:absolute; z-index:9999; background:white; border:1px solid var(--border); border-radius:0.75rem; box-shadow: var(--shadow-xl); padding:0.8rem; min-width:240px; border: 1.5px solid var(--primary);">
        <div
            style="font-size:0.65rem; font-weight:800; color:var(--primary); padding-bottom:0.6rem; text-transform:uppercase; border-bottom: 1px solid #f1f5f9; margin-bottom: 0.6rem;">
            <i class="fas fa-user-plus"></i> Quick Assign Task
        </div>
        <div style="margin-bottom: 0.6rem;">
            <input type="text" id="assignSearch" placeholder="Search employee..."
                style="width:100%; padding:0.5rem 0.75rem; font-size:0.8rem; border:1.5px solid #e2e8f0; border-radius:0.5rem; outline:none; transition: border-color 0.2s;"
                onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#e2e8f0'">
        </div>
        <div id="assignEmployeeList" style="max-height: 220px; overflow-y: auto; padding-right: 4px;">
            <!-- Filled by JS -->
        </div>
    </div>

    <script>
        const IS_EXECUTIVE = <?= $isExecutive ? 'true' : 'false' ?>;
        let currentEditId = null;

        function getLeadStatusLabel(status) {
            const labels = {
                new: 'New',
                in_progress: 'Interested',
                won: 'Client Done',
                lost: 'Lost / Wrong Lead'
            };
            return labels[status] || String(status || 'New').replace(/_/g, ' ').toUpperCase();
        }

        function normalizeTaskStatus(status) {
            const map = { pending: 'not_started', done: 'work_done', delay: 'work_pending', '': 'not_started' };
            return map[status || ''] || status;
        }

        function getTaskStatusLabel(status) {
            const labels = {
                not_started: 'NOT STARTED',
                work_in_progress: 'WORK IN PROGRESS',
                work_pending: 'WORK PENDING',
                work_done: 'WORK DONE'
            };
            return labels[normalizeTaskStatus(status)] || String(status || 'not_started').replace(/_/g, ' ').toUpperCase();
        }

        function getTaskStatusStyle(status) {
            const styles = {
                not_started: '#f1f5f9; color:#475569;',
                work_in_progress: '#fef3c7; color:#92400e;',
                work_pending: '#fee2e2; color:#991b1b;',
                work_done: '#d1fae5; color:#065f46;'
            };
            return styles[normalizeTaskStatus(status)] || styles.not_started;
        }

        // --- Duplicate Detection & Auto-Fill ---
        function switchRefTab(tab) {
            const customerPanel = document.getElementById('refCustomerPanel');
            const customPanel = document.getElementById('refCustomPanel');
            const tabCustomer = document.getElementById('refTabCustomer');
            const tabCustom = document.getElementById('refTabCustom');
            const labelCustomer = document.getElementById('refLabelCustomer');
            const labelCustom = document.getElementById('refLabelCustom');

            if (tab === 'customer') {
                customerPanel.style.display = 'block';
                customPanel.style.display = 'none';
                labelCustomer.style.display = 'block';
                labelCustom.style.display = 'none';
                tabCustomer.style.background = 'var(--primary)'; tabCustomer.style.color = 'white';
                tabCustom.style.background = '#f1f5f9'; tabCustom.style.color = '#64748b';
                // clear custom name when switching away
                const cn = document.querySelector('[name="referral_custom_name"]');
                if (cn) cn.value = '';
            } else {
                customerPanel.style.display = 'none';
                customPanel.style.display = 'block';
                labelCustomer.style.display = 'none';
                labelCustom.style.display = 'block';
                tabCustom.style.background = 'var(--primary)'; tabCustom.style.color = 'white';
                tabCustomer.style.background = '#f1f5f9'; tabCustomer.style.color = '#64748b';
                // clear customer when switching away
                const sel = document.querySelector('[name="referral_customer_id"]');
                if (sel) sel.value = '';
            }
        }

        function toggleReferralInput(source) {
            const container = document.getElementById('referralCustomerContainer');
            if (source === 'referral') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                const sel = container.querySelector('[name="referral_customer_id"]');
                const txt = container.querySelector('[name="referral_custom_name"]');
                if (sel) sel.value = '';
                if (txt) txt.value = '';
                // reset tabs to default (customer)
                switchRefTab('customer');
            }
        }

        async function checkLeadDuplicates() {
            if (currentEditId) return; // Don't auto-fill or warn during edit

            const mobileInput = document.getElementById('leadMobile');
            const mobile = (mobileInput.value || '').trim();
            const requirement = (document.querySelector('[name="requirement"]')?.value || '').trim();
            const task_status = document.querySelector('[name="task_status"]')?.value || 'not_started';

            const warn = document.getElementById('duplicateWarning');
            const msg = document.getElementById('duplicateMsg');
            const link = document.getElementById('duplicateLink');

            if (!mobile || mobile.length < 10) {
                warn.style.display = 'none';
                return;
            }

            const url = `<?= APP_URL ?>/public/index.php/api/leads.php?action=check_duplicate&mobile=${encodeURIComponent(mobile)}&requirement=${encodeURIComponent(requirement)}&task_status=${encodeURIComponent(task_status)}`;
            const res = await fetch(url);
            const data = await res.json();

            // 1. Auto-fill if match found
            if (data.match && data.lead) {
                const form = document.getElementById('leadForm');
                if (!form.name.value) form.name.value = data.lead.name || '';
                if (!form.email.value) form.email.value = data.lead.email || '';
                if (!form.address.value) form.address.value = data.lead.address || '';
            }

            // 2. Show duplicate warning only if EXACT duplicate (mobile + task + status)
            if (data.duplicate && data.duplicate_lead) {
                msg.textContent = `Duplicate! This client already has a lead for the same task with status "${getTaskStatusLabel(data.duplicate_lead.task_status)}".`;
                link.onclick = (e) => { e.preventDefault(); openEditModal(data.duplicate_lead.id); };
                warn.style.display = 'block';
            } else {
                warn.style.display = 'none';
            }
        }

        document.getElementById('leadMobile').addEventListener('blur', checkLeadDuplicates);
        document.querySelector('[name="requirement"]').addEventListener('blur', checkLeadDuplicates);
        document.querySelector('[name="task_status"]').addEventListener('change', checkLeadDuplicates);

        // Service Search Logic
        document.getElementById('serviceSearch')?.addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.service-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(term) ? 'flex' : 'none';
            });
        });

        // Other Service Toggle Logic
        document.getElementById('otherServiceToggle')?.addEventListener('change', function (e) {
            const box = document.getElementById('otherServiceBox');
            box.style.display = e.target.checked ? 'block' : 'none';
            if (e.target.checked) document.getElementById('otherServiceName').focus();
        });

        function toggleModal(id) {
            const el = document.getElementById(id);
            if (el.style.display === 'flex') {
                el.style.display = 'none';
                if (id === 'leadModal' && !currentEditId) {
                    document.getElementById('leadForm').reset();
                    document.getElementById('selectedRequirementsTotal').textContent = '0.00';
                    document.querySelector('#leadModal h2').textContent = 'New Opportunity';
                    // Reset all checkboxes
                    document.querySelectorAll('.req-checkbox').forEach(cb => {
                        cb.checked = false;
                        cb.disabled = false;
                    });
                    const form = document.getElementById('leadForm');
                    Array.from(form.elements).forEach(el => el.disabled = false);
                    document.getElementById('saveLeadBtn').style.display = 'block';
                }
                currentEditId = null;
                // Reset requirements
                document.querySelectorAll('.req-checkbox').forEach(cb => cb.checked = false);
                updateRequirementsTotal();
            } else {
                el.style.display = 'flex';
            }
        }

        // --- Requirements Total Calculation ---
        function updateRequirementsTotal() {
            let total = 0;
            document.querySelectorAll('.req-checkbox:checked').forEach(cb => {
                total += parseFloat(cb.dataset.fee || 0);
            });
            document.querySelectorAll('.custom-service-price').forEach(input => {
                total += parseFloat(input.value) || 0;
            });

            document.getElementById('selectedRequirementsTotal').textContent = total.toLocaleString('en-IN', { minimumFractionDigits: 2 });

            // Auto-update deal value if it's a new lead or zero
            const dealInput = document.querySelector('[name="deal_value"]');
            if (total > 0 && (!dealInput.value || parseFloat(dealInput.value) === 0)) {
                dealInput.value = total.toFixed(2);
            }
        }

        function addCustomServiceRow(name = '', price = '') {
            const container = document.getElementById('customServicesContainer');
            const row = document.createElement('div');
            row.className = 'custom-service-row';
            row.style = 'display: grid; grid-template-columns: 1fr 100px 30px; gap: 0.5rem; align-items: center;';
            row.innerHTML = `
                <input type="text" class="form-input custom-service-name" placeholder="Service Name" value="${name}" style="height: 32px; font-size: 0.75rem;">
                <input type="number" step="0.01" class="form-input custom-service-price" placeholder="Price" value="${price}" style="height: 32px; font-size: 0.75rem;" oninput="updateRequirementsTotal()">
                <button type="button" onclick="this.parentElement.remove(); updateRequirementsTotal();" style="background: #fee2e2; color: #ef4444; border: none; height: 32px; width: 32px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(row);
        }

        document.querySelectorAll('.req-checkbox').forEach(cb => {
            cb.addEventListener('change', updateRequirementsTotal);
        });

        async function openEditModal(id) {
            // Permission check will be handled by the API response (403 for unauthorized access)

            try {
                const response = await fetch(`<?= APP_URL ?>/public/index.php/api/leads.php?id=${id}`);

                if (!response.ok) {
                    const err = await response.json();
                    alert(err.error || 'You do not have permission to edit this lead.');
                    return;
                }

                const lead = await response.json();

                currentEditId = id;
                document.querySelector('#leadModal h2').textContent = 'Edit Lead Details';

                const form = document.getElementById('leadForm');
                // Ensure form fields are always enabled for editing
                Array.from(form.elements).forEach(el => el.disabled = false);
                document.querySelectorAll('.req-checkbox').forEach(cb => cb.disabled = false);
                document.getElementById('saveLeadBtn').style.display = 'block';

                form.elements['name'].value = lead.name;
                form.elements['mobile'].value = lead.mobile;
                form.elements['email'].value = lead.email || '';
                form.elements['address'].value = lead.address || '';
                form.elements['is_whatsapp'].checked = lead.is_whatsapp == 1;
                form.elements['is_call'].checked = lead.is_call == 1;
                form.elements['category'].value = lead.category || 'warm';
                form.elements['source'].value = lead.source || '';
                form.elements['requirement'].value = lead.requirement || '';
                form.elements['deal_value'].value = lead.deal_value || '0.00';
                form.elements['commission_percent'].value = lead.commission_percent || '0.00';
                form.elements['assigned_employee_id'].value = lead.assigned_employee_id || '';
                form.elements['status'].value = lead.status || 'new';
                form.elements['task_status'].value = normalizeTaskStatus(lead.task_status);

                // Set referral_person select by matching stored name
                const refSelect = form.elements['referral_person'];
                const refVal = lead.referral_person || '';
                let refMatched = false;
                for (let opt of refSelect.options) {
                    if (opt.value === refVal) { opt.selected = true; refMatched = true; break; }
                }
                if (!refMatched) refSelect.value = '';

                // Restore referral source panel (customer vs custom name)
                if (lead.source === 'referral') {
                    document.getElementById('referralCustomerContainer').style.display = 'block';
                    if (lead.referral_custom_name) {
                        switchRefTab('custom');
                        const cn = form.elements['referral_custom_name'];
                        if (cn) cn.value = lead.referral_custom_name;
                    } else {
                        switchRefTab('customer');
                        if (form.elements['referral_customer_id']) {
                            form.elements['referral_customer_id'].value = lead.referral_customer_id || '';
                        }
                    }
                } else {
                    document.getElementById('referralCustomerContainer').style.display = 'none';
                    switchRefTab('customer');
                }

                // Handle Custom Services
                document.getElementById('customServicesContainer').innerHTML = '';
                if (lead.custom_services) {
                    try {
                        const custom = JSON.parse(lead.custom_services);
                        if (Array.isArray(custom)) {
                            custom.forEach(s => addCustomServiceRow(s.name, s.price));
                        }
                    } catch (e) {
                        console.error('Error parsing custom services:', e);
                    }
                }

                // Fetch and check requirements
                const reqRes = await fetch(`<?= APP_URL ?>/api/requirements.php?lead_id=${id}`);
                const leadReqs = await reqRes.json();
                document.querySelectorAll('.req-checkbox').forEach(cb => {
                    cb.checked = leadReqs.some(r => r.id == cb.value);
                });
                updateRequirementsTotal();

                toggleModal('leadModal');
            } catch (error) {
                console.error('Logic Error:', error);
            }
        }

        async function fetchFollowups(lead_id) {
            const list = document.getElementById('followupList');
            list.innerHTML = '<div style="text-align:center; padding: 2rem; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            try {
                const response = await fetch(`<?= APP_URL ?>/public/index.php/api/lead_followups.php?lead_id=${lead_id}`);
                const data = await response.json();

                // In History modal, limit to max 10
                const displayData = data.slice(0, 10);

                if (displayData.length === 0) {
                    list.innerHTML = '<div style="text-align:center; padding:2rem; color:#94a3b8; font-size:0.75rem;"><i class="fas fa-comment-slash" style="display:block; font-size:1.5rem; margin-bottom:0.5rem; opacity:0.3;"></i>No engagement history yet</div>';
                    return;
                }

                list.innerHTML = displayData.map(f => `
                    <div style="background: white; border: 1px solid #f1f5f9; padding: 0.875rem; border-radius: 0.625rem; margin-bottom: 0.75rem; border-left: 3px solid ${f.status === 'pending' ? 'var(--secondary)' : 'var(--success)'};">
                        <div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">
                                ${new Date(f.follow_up_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })} 
                                at 
                                ${new Date(`1970-01-01T${f.follow_up_time.slice(0, 5)}`)
                        .toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit', hour12: true })}
                            </span>
                            <span class="badge" style="font-size: 0.6rem; background: #f0fdf4; color:#166534; border: 1px solid #bbf7d0;">${(f.call_status || 'CONNECTED').toUpperCase()}</span>
                        </div>
                        <p style="font-size: 0.75rem; color: #334155; font-weight: 600; line-height: 1.4; margin: 0;">${f.remark}</p>
                    </div>
                `).join('');
            } catch (error) {
                list.innerHTML = '<div style="text-align:center; color: var(--danger); font-size: 0.75rem;">Failed to load history.</div>';
            }
        }


        async function openQuickFollowup(id, name) {
            document.getElementById('qf_lead_id').value = id;
            document.getElementById('qf_lead_name').textContent = name;
            document.getElementById('qf_date').value = new Date().toISOString().split('T')[0];

            // Load History for this lead
            fetchFollowups(id);

            document.getElementById('quickFollowModal').style.display = 'flex';
        }

        function showRemarkDetail(leadId) {
            const lead = allLeads.find(l => l.id == leadId);
            if (!lead || !lead.latest_remark) return;

            document.getElementById('rd_lead_name').textContent = `For ${lead.name}`;
            document.getElementById('rd_interaction_date').textContent = lead.latest_interaction_at ? new Date(lead.latest_interaction_at).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';
            document.getElementById('rd_user').textContent = lead.interaction_user_name || 'System';
            document.getElementById('rd_remark').textContent = lead.latest_remark;
            document.getElementById('rd_next_date').textContent = lead.latest_next_followup_date ? new Date(lead.latest_next_followup_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'None scheduled';

            // Status Styling
            const statusCfg = {
                'connected': { icon: 'fa-phone-alt', color: '#059669', bg: '#ecfdf5', label: 'Connected' },
                'not_picked': { icon: 'fa-phone-slash', color: '#dc2626', bg: '#fef2f2', label: 'No Pick' },
                'busy': { icon: 'fa-clock', color: '#d97706', bg: '#fff7ed', label: 'Busy' },
                'switched_off': { icon: 'fa-mobile-alt', color: '#4b5563', bg: '#f3f4f6', label: 'Off' },
                'wrong_number': { icon: 'fa-times-circle', color: '#991b1b', bg: '#fee2e2', label: 'Wrong' }
            };
            const cfg = statusCfg[(lead.latest_call_status || '').toLowerCase()] || { icon: 'fa-phone', color: '#64748b', bg: '#f1f5f9', label: (lead.latest_call_status || 'NONE').toUpperCase() };

            const statusDiv = document.getElementById('rd_call_status');
            statusDiv.style.background = cfg.bg;
            statusDiv.style.border = `1px solid ${cfg.color}33`;
            statusDiv.style.color = cfg.color;

            document.getElementById('rd_status_icon').className = `fas ${cfg.icon}`;
            document.getElementById('rd_status_text').textContent = cfg.label;

            toggleModal('remarkDetailModal');
        }

        document.getElementById('quickFollowForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            const triggerDate = new Date(data.follow_up_date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (triggerDate < today) {
                alert("Reminder date cannot be in the past.");
                return;
            }

            const newStatus = data.lead_status || '';
            const leadId = data.lead_id;
            delete data.lead_status; // not a followup field

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/lead_followups.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    // Also update lead pipeline status if changed
                    if (newStatus) {
                        await fetch('<?= APP_URL ?>/public/index.php/api/leads.php', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: leadId, status: newStatus })
                        });
                    }
                    document.getElementById('quickFollowModal').style.display = 'none';
                    this.reset();
                    fetchLeads();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Core Logic Error:', error);
            }
        });

        let allLeads = [];

        let currentFilters = {
            status: 'all',
            category: 'all',
            source: 'all',
            transfer: 'all',
            view: 'table',
            page: parseInt(sessionStorage.getItem('leads_page')) || 1
        };

        // View Switcher Logic
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilters.view = this.dataset.view;

                if (currentFilters.view === 'kanban') {
                    document.getElementById('kanbanBoard').style.display = 'flex';
                    document.getElementById('tableView').style.display = 'none';
                } else {
                    document.getElementById('kanbanBoard').style.display = 'none';
                    document.getElementById('tableView').style.display = 'block';
                }
                renderLeads(allLeads);
            });
        });

        // Initialize Filter Listeners
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function () {
                const filterType = this.dataset.filter;
                const value = this.value;

                // Update State
                currentFilters[filterType] = value;
                currentFilters.page = 1;
                sessionStorage.setItem('leads_page', 1);
                renderLeads(allLeads);
            });
        });



        let allLeadsMode = false;
        function showAllLeads() {
            allLeadsMode = true;
            document.getElementById('leadSearch').value = '';

            // Visual feedback on the button
            const btn = document.getElementById('btnShowAllLeads');
            if (btn) {
                btn.style.borderColor = 'var(--primary)';
                btn.style.background = '#f5f3ff';
            }

            fetchLeads();
        }

        async function fetchLeads() {
            // Reset "Show All" button state if we are back to date filtering
            const btn = document.getElementById('btnShowAllLeads');
            if (btn && !allLeadsMode) {
                btn.style.borderColor = 'var(--border)';
                btn.style.background = 'transparent';
                btn.style.color = '#475569';
            } else if (btn && allLeadsMode) {
                btn.style.borderColor = 'var(--primary)';
                btn.style.background = '#f5f3ff';
                btn.style.color = 'var(--primary)';
            }

            try {
                const month = document.getElementById('filterMonth').value;
                const year = document.getElementById('filterYear').value;
                const searchQuery = document.getElementById('leadSearch').value;

                // Get other filters if they exist
                const statusEl = document.querySelector('[data-filter="status"]');
                const categoryEl = document.querySelector('[data-filter="category"]');
                const sourceEl = document.querySelector('[data-filter="source"]');
                const transferEl = document.querySelector('[data-filter="transfer"]');

                const status = statusEl ? statusEl.value : 'all';
                const category = categoryEl ? categoryEl.value : 'all';
                const source = sourceEl ? sourceEl.value : 'all';
                const transfer = transferEl ? transferEl.value : (currentFilters.transfer || 'all');

                // Sync State
                currentFilters.status = status;
                currentFilters.category = category;
                currentFilters.source = source;
                currentFilters.transfer = transfer;

                const url = new URL('<?= APP_URL ?>/public/index.php/api/leads.php');

                if (searchQuery && searchQuery.length >= 3) {
                    url.searchParams.append('search', searchQuery);
                } else if (typeof allLeadsMode !== 'undefined' && allLeadsMode) {
                    url.searchParams.append('all', '1');
                } else {
                    url.searchParams.append('month', month);
                    url.searchParams.append('year', year);
                }

                url.searchParams.append('status', status);
                url.searchParams.append('category', category);
                url.searchParams.append('source', source);
                if (transfer && transfer !== 'all') {
                    url.searchParams.append('transfer', transfer);
                }

                const response = await fetch(url);
                allLeads = await response.json();
                renderLeads(allLeads);
            } catch (error) {
                console.error('Board Error:', error);
            }
        }

        async function deleteLead(id) {
            if (IS_EXECUTIVE) {
                alert('Executives cannot delete leads.');
                return;
            }

            if (!confirm('Are you sure you want to delete this lead from the pipeline?')) return;
            try {
                const response = await fetch(`<?= APP_URL ?>/public/index.php/api/leads.php?id=${id}`, {
                    method: 'DELETE'
                });
                const result = await response.json();
                if (result.success) fetchLeads();
            } catch (error) {
                console.error('Logic Error:', error);
            }
        }

        function renderLeads(leads) {
            const query = document.getElementById('leadSearch').value.toLowerCase();
            const tableBody = document.getElementById('tableBody');
            const counters = { new: 0, in_progress: 0, won: 0, lost: 0 };

            const isTransferredLead = l => (l.is_transferred == 1 || (l.transfer_count && parseInt(l.transfer_count) > 0));

            // Update transferred counter in toggle button
            const totalTransferred = leads.filter(isTransferredLead).length;
            const countBadge = document.getElementById('transferredCounter');
            if (countBadge) countBadge.innerText = totalTransferred;
            updateTransferredButtonState();

            // Initial filtering for search, status, category, source, transfer
            const filteredLeads = leads.filter(lead => {
                // Apply Filter Selects
                if (currentFilters.status !== 'all' && lead.status !== currentFilters.status) return false;
                if (currentFilters.category !== 'all' && lead.category !== currentFilters.category) return false;
                if (currentFilters.source !== 'all' && lead.source !== currentFilters.source) return false;
                if (currentFilters.transfer === 'transferred' && !isTransferredLead(lead)) return false;
                if (currentFilters.transfer === 'not_transferred' && isTransferredLead(lead)) return false;

                // Search Filter (Client-side secondary filter for very small lists)
                const invText = (lead.invoice_descriptions || '').toLowerCase();
                const reqText = ((lead.requirement || '') + ' ' + (lead.requirement_names || '') + ' ' + invText).toLowerCase();
                const searchQuery = document.getElementById('leadSearch').value.toLowerCase();
                if (searchQuery && searchQuery.length > 0 && searchQuery.length < 3) {
                    if (!lead.name.toLowerCase().includes(searchQuery) && !lead.mobile.includes(searchQuery) && !reqText.includes(searchQuery)) {
                        return false;
                    }
                }

                // Update counters while we're at it
                counters[lead.status || 'new']++;
                return true;
            });

            // Pagination Logic
            const perPage = document.getElementById('perPage').value;
            const limit = perPage === 'all' ? filteredLeads.length : parseInt(perPage);
            const totalPages = Math.ceil(filteredLeads.length / limit) || 1;

            // Ensure current page is valid
            if (currentFilters.page > totalPages) currentFilters.page = totalPages;
            if (currentFilters.page < 1) currentFilters.page = 1;

            const startIdx = (currentFilters.page - 1) * limit;
            const paginatedLeads = filteredLeads.slice(startIdx, startIdx + limit);

            // Clear only necessary parts
            if (currentFilters.view === 'kanban') {
                document.querySelectorAll('.leads-list').forEach(list => list.innerHTML = '');
            } else {
                tableBody.innerHTML = '';
            }

            // Pre-calculate duplicates for efficiency
            const comboCounts = {};
            leads.forEach(l => {
                const key = (l.mobile || '') + '||' + (l.requirement || '').trim().toLowerCase();
                comboCounts[key] = (comboCounts[key] || 0) + 1;
            });
            const duplicateKeys = new Set(Object.keys(comboCounts).filter(k => comboCounts[k] > 1));
            const isDup = l => duplicateKeys.has((l.mobile || '') + '||' + (l.requirement || '').trim().toLowerCase());

            let tableHtml = '';
            const columnHtml = { new: '', in_progress: '', won: '', lost: '' };

            paginatedLeads.forEach((lead, index) => {
                const srNo = startIdx + index + 1;
                const isDuplicate = isDup(lead);

                const callStatusIcons = {
                    'connected': { icon: 'fa-phone-alt', color: '#059669', bg: '#ecfdf5', label: 'Connected' },
                    'not_picked': { icon: 'fa-phone-slash', color: '#dc2626', bg: '#fef2f2', label: 'No Pick' },
                    'busy': { icon: 'fa-clock', color: '#d97706', bg: '#fff7ed', label: 'Busy' },
                    'switched_off': { icon: 'fa-mobile-alt', color: '#4b5563', bg: '#f3f4f6', label: 'Off' },
                    'wrong_number': { icon: 'fa-times-circle', color: '#991b1b', bg: '#fee2e2', label: 'Wrong' }
                };

                function getCallStatusBadge(status, leadId, leadName) {
                    const statusStr = (status || '').toLowerCase();
                    const nameStr = (leadName || 'Lead').replace(/'/g, "\\'");
                    const cfg = callStatusIcons[statusStr] || { icon: 'fa-phone', color: '#64748b', bg: '#f1f5f9', label: (status || 'NONE').toUpperCase() };
                    return `<span class="badge status-clickable" 
                        onclick="event.stopPropagation(); showStatusPicker(event, ${leadId}, '${nameStr}')"
                        style="background:${cfg.bg}; color:${cfg.color}; border:1px solid ${cfg.color}33; display:inline-flex; align-items:center; gap:3px; font-size:0.6rem; cursor:pointer; transition: all 0.2s;">
                        <i class="fas ${cfg.icon}"></i> ${cfg.label} <i class="fas fa-caret-down" style="font-size:0.5rem; opacity:0.5;"></i>
                    </span>`;
                }

                function getCategoryBadge(category, leadId) {
                    const config = {
                        'red': { color: '#f44336', label: 'RED' },
                        'green': { color: '#2dc677', label: 'GREEN' },
                        'yellow': { color: '#ffeb3b', label: 'YELLOW' }
                    };
                    const cat = (category || '').toLowerCase();
                    const cfg = config[cat] || { label: (category || 'NONE').toUpperCase(), color: '#64748b' };
                    const textColor = (cat === 'yellow' || !cat) ? '#000' : '#fff';
                    return `<span class="badge" onclick="event.stopPropagation(); showCategoryPicker(event, ${leadId})" 
                        style="background:${cfg.color}; color:${textColor}; border:1px solid rgba(0,0,0,0.1); font-size:0.55rem; font-weight:900; cursor:pointer; padding:2px 6px; border-radius:4px;">
                        ${cfg.label} <i class="fas fa-caret-down" style="opacity:0.6; font-size:0.5rem;"></i>
                    </span>`;
                }

                const isValidMobile = /^[0-9]{10}$/.test(lead.mobile || '');
                const mobileDisplay = isValidMobile ? lead.mobile : `<span style="color:#ef4444;" title="Invalid Mobile Number">${lead.mobile} <i class="fas fa-exclamation-circle"></i></span>`;

                const servicesArray = [];
                if (lead.requirement_names) servicesArray.push(lead.requirement_names);
                if (lead.custom_services) {
                    try {
                        const custom = JSON.parse(lead.custom_services);
                        if (Array.isArray(custom)) custom.forEach(s => servicesArray.push(s.name));
                    } catch (e) { }
                }
                if (lead.invoice_descriptions) {
                    lead.invoice_descriptions.split('||').forEach(desc => {
                        try {
                            const items = JSON.parse(desc);
                            if (Array.isArray(items)) {
                                items.forEach(item => {
                                    if (typeof item === 'object' && item !== null) {
                                        const name = (item.name || item.service_name || item.title || item.label || '').trim();
                                        if (name) servicesArray.push(name);
                                    } else if (typeof item === 'string' && item.trim()) {
                                        servicesArray.push(item.trim());
                                    }
                                });
                            } else {
                                if (desc.trim()) servicesArray.push(desc.trim());
                            }
                        } catch (e) {
                            if (desc.trim()) servicesArray.push(desc.trim());
                        }
                    });
                }
                const uniqueServices = [...new Set(servicesArray)].filter(s => s);
                const allServices = uniqueServices.join(', ');

                const waMobile = (lead.mobile || '').replace(/\D/g, '');
                const actionsHtml = `<div class="action-btn followup" title="Quick Follow-up" onclick="openQuickFollowup(${lead.id}, '${(lead.name || 'Lead').replace(/'/g, "\\'")}')"><i class="fas fa-calendar-plus"></i></div>
                            <div class="action-btn" title="Transfer Lead" onclick="openTransferModal(${lead.id}, '${(lead.name || 'Lead').replace(/'/g, "\\'")}', '${(lead.assigned_employee_name || lead.assigned_to_name || 'Unassigned').replace(/'/g, "\\'")}')" style="color:var(--primary);"><i class="fas fa-right-left"></i></div>
                            <div class="action-btn" title="Edit Lead Details" onclick="openEditModal(${lead.id})"><i class="fas fa-pen-to-square"></i></div>
                            ${waMobile ? `<button onclick="handleWAClick('lead', ${lead.id})" class="wa-btn" title="Send WhatsApp Message" style="border:none; cursor:pointer; background:#25D366; color:white;"><i class="fab fa-whatsapp"></i></button>` : ''}
                            ${lead.mobile ? `<a href="tel:${lead.mobile}" class="call-btn" title="Call" style="text-decoration:none;"><i class="fas fa-phone-alt"></i></a>` : ''}`;

                if (currentFilters.view === 'kanban') {
                    const catClass = (lead.category || 'none').toLowerCase();
                    columnHtml[lead.status || 'new'] += `
                        <div class="lead-card lead-cat-${catClass} ${lead.status === 'lost' ? 'lost' : ''}">
                            <div class="card-actions">${actionsHtml}</div>
                            <div class="lead-name" style="display:flex; align-items:center; gap:0.35rem; flex-wrap:wrap;">
                                <span>${lead.name}</span>
                                ${isTransferredLead(lead) ? `<span class="badge" style="display:inline-flex; align-items:center; gap:3px; background:#e0e7ff; color:#4338ca; border:1px solid #c7d2fe; border-radius:4px; font-size:0.58rem; font-weight:800; padding:1px 5px; line-height:1.2;" title="${(lead.last_transfer_remark || 'Transferred Lead').replace(/"/g, '&quot;')}"><i class="fas fa-right-left" style="font-size:0.5rem;"></i> TRANSFERRED</span>` : ''}
                                ${isDup(lead) ? `<span style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:0.6rem;font-weight:800;padding:0.1rem 0.4rem;"><i class="fas fa-copy"></i> DUP</span>` : ''}
                            </div>
                            <div class="lead-info"><i class="fas fa-phone" style="width:14px"></i> ${mobileDisplay}</div>
                            ${lead.email ? `<div class="lead-info"><i class="fas fa-envelope" style="width:14px"></i> ${lead.email}</div>` : ''}
                            ${allServices ? `<div class="lead-info" style="color:var(--primary); font-size:0.75rem; font-weight:800; margin-top:2px;"><i class="fas fa-concierge-bell" style="width:14px"></i> ${allServices}</div>` : ''}
                            ${lead.requirement ? `<div class="lead-info" style="color:#64748b; font-size:0.72rem; font-style:italic; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:240px;" title="${lead.requirement.replace(/"/g, '&quot;')}"><i class="fas fa-comment-dots" style="width:14px"></i> ${lead.requirement.length > 50 ? lead.requirement.substring(0, 50) + '…' : lead.requirement}</div>` : ''}
                            <div class="lead-info" id="lead-assign-container-card-${lead.id}" style="margin-top:2px;">
                                ${lead.assigned_to_name ? `<div style="color:var(--primary); font-weight:700;"><span style="font-size:0.6rem; opacity:0.6; text-transform:uppercase;">Lead Assigned:</span> ${lead.assigned_to_name} <i class="fas fa-right-left" style="font-size:0.65rem; cursor:pointer; margin-left:4px; opacity:0.8;" title="Transfer Lead" onclick="openTransferModal(${lead.id}, '${(lead.name || 'Lead').replace(/'/g, "\\'")}', '${lead.assigned_to_name.replace(/'/g, "\\'")}')"></i></div>` : `<button class="btn-ghost" onclick="openTransferModal(${lead.id}, '${(lead.name || 'Lead').replace(/'/g, "\\'")}', 'Unassigned')" style="padding: 2px 8px; font-size: 0.65rem; color: var(--primary); border: 1px dashed var(--primary); border-radius: 4px; font-weight: 800; cursor: pointer;"><i class="fas fa-right-left"></i> Transfer Lead</button>`}
                            </div>
                            <div class="lead-info" id="task-assign-container-card-${lead.id}" style="margin-top:2px;">
                                ${lead.assigned_employee_name && lead.assigned_employee_name !== lead.assigned_to_name ? `<div style="color:var(--accent-hover, #7c3aed); font-weight:700;"><span style="font-size:0.6rem; opacity:0.6; text-transform:uppercase;">Task Assigned:</span> ${lead.assigned_employee_name}</div>` : (!IS_EXECUTIVE ? `<button class="btn-ghost" onclick="openAssignPicker(event, ${lead.id}, 'task')" style="padding: 2px 8px; font-size: 0.65rem; color: var(--accent-hover, #7c3aed); border: 1px dashed var(--accent-hover, #7c3aed); border-radius: 4px; font-weight: 800; cursor: pointer;"><i class="fas fa-id-badge"></i> Assign Task</button>` : '')}
                            </div>
                            <div style="margin-top:0.75rem; border-top: 1px solid #f1f5f9; padding-top: 0.5rem;">
                                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                    ${lead.status !== 'lost' ? `<span class="badge" style="background:${getTaskStatusStyle(lead.task_status)} font-size:0.55rem; font-weight:800; padding: 2px 5px;">TASK: ${getTaskStatusLabel(lead.task_status)}</span>` : '<span></span>'}
                                    <div style="display:flex; gap:4px; align-items:center;">
                                        ${getCallStatusBadge(lead.latest_call_status, lead.id, lead.name)}
                                        <span style="color:#cbd5e1; font-size:0.7rem;">,</span>
                                        ${getCategoryBadge(lead.category, lead.id)}
                                    </div>
                                </div>
                                ${lead.latest_remark ? `<div onclick="showRemarkDetail(${lead.id})" style="font-size:0.65rem; color:#64748b; font-style:italic; line-height:1.2; background:#f8fafc; padding:4px 8px; border-radius:4px; border-left:2px solid var(--primary); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'"><i class="fas fa-comment-alt" style="font-size:0.55rem; opacity:0.6;"></i> ${lead.latest_remark}</div>` : ''}
                            </div>
                            ${lead.follow_up_date ? `<div class="lead-tag lead-followup"><i class="fas fa-clock"></i> Next: ${new Date(lead.follow_up_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })}</div>` : ''}
                        </div>`;
                } else {
                    const catClassTable = (lead.category || 'none').toLowerCase();
                    const isDuplicate = isDup(lead);
                    tableHtml += `
                        <tr class="lead-cat-${catClassTable} ${lead.status === 'lost' ? 'lost' : ''}" ${isDuplicate ? 'style="background: #fff8f8;"' : ''}>
                            <td><input type="checkbox" class="lead-checkbox" value="${lead.id}" data-email="${lead.email || ''}" onchange="updateBulkBar()"></td>
                            <td style="font-weight:700; color:#64748b; font-size:0.72rem;">${srNo}.</td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                                    <span class="table-lead-name" style="margin-bottom:0; display:inline-block; font-size:0.75rem;">${lead.name}</span>
                                    ${isTransferredLead(lead) ? `<span class="badge" style="display:inline-flex; align-items:center; gap:3px; background:#e0e7ff; color:#4338ca; border:1px solid #c7d2fe; border-radius:4px; font-size:0.55rem; font-weight:800; padding:1px 4px; letter-spacing:0.02em; line-height:1.2;" title="${(lead.last_transfer_remark || 'Transferred Lead').replace(/"/g, '&quot;')}"><i class="fas fa-right-left" style="font-size:0.48rem;"></i> TRANSFERRED</span>` : ''}
                                    ${isDuplicate ? `<span style="display:inline-block;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:0.55rem;font-weight:800;padding:1px 4px;letter-spacing:0.04em;"><i class="fas fa-copy"></i> DUP</span>` : ''}
                                </div>
                                <div class="table-lead-sub" style="font-size:0.72rem;color:#000;font-weight:500;margin-top:1px;">
                                    ${mobileDisplay}
                                    ${lead.email ? `<div style="font-size: 0.68rem; color: #64748b; font-weight: 500; margin-top: 1px;"><i class="fas fa-envelope" style="font-size: 0.65rem; opacity: 0.5; width: 14px;"></i> ${lead.email}</div>` : ''}
                                </div>
                            </td>
                            <td style="max-width:200px;">
                                ${allServices ? `<div style="font-weight:800; color:var(--primary); font-size:0.7rem; margin-bottom:1px;">${allServices}</div>` : ''}
                                ${lead.requirement ? `<span style="font-size:0.72rem;color:#000;font-weight:500">${lead.requirement.length > 60 ? lead.requirement.substring(0, 60) + '…' : lead.requirement}</span>` : ''}
                            </td>
                            <td><div id="lead-assign-container-table-${lead.id}" style="font-size:0.72rem;">${lead.assigned_to_name ? `<strong>${lead.assigned_to_name}</strong> <i class="fas fa-right-left" style="font-size:0.6rem; cursor:pointer; margin-left:3px; color:var(--primary);" title="Transfer Lead" onclick="openTransferModal(${lead.id}, '${(lead.name || 'Lead').replace(/'/g, "\\'")}', '${lead.assigned_to_name.replace(/'/g, "\\'")}')"></i>` : `<button class="btn-ghost" onclick="openTransferModal(${lead.id}, '${(lead.name || 'Lead').replace(/'/g, "\\'")}', 'Unassigned')" style="padding: 1px 6px; font-size: 0.62rem; border: 1px dashed var(--primary); border-radius: 4px;"><i class="fas fa-right-left"></i> Transfer</button>`}</div></td>
                            <td><div id="task-assign-container-table-${lead.id}" style="font-size:0.72rem;">${lead.assigned_employee_name ? `<strong>${lead.assigned_employee_name}</strong>` : (!IS_EXECUTIVE ? `<button class="btn-ghost" onclick="openAssignPicker(event, ${lead.id}, 'task')" style="padding: 1px 6px; font-size: 0.62rem; border: 1px dashed var(--accent-hover, #7c3aed); border-radius: 4px;">Assign</button>` : '')}</div></td>
                            <td><span class="badge" style="background:#f1f5f9; color:#475569; font-size:0.62rem; padding: 2px 6px;">${getLeadStatusLabel(lead.status)}</span></td>
                            <td>${lead.status !== 'lost' ? `<span class="badge" style="background:${getTaskStatusStyle(lead.task_status)}; font-size:0.62rem; padding: 2px 6px;">${getTaskStatusLabel(lead.task_status)}</span>` : '—'}</td>
                            <td>
                                <div style="display:flex; gap:3px; align-items:center; margin-bottom: 3px;">
                                    ${getCallStatusBadge(lead.latest_call_status, lead.id, lead.name)} , ${getCategoryBadge(lead.category, lead.id)}
                                </div>
                                ${lead.latest_remark ? `<div onclick="showRemarkDetail(${lead.id})" style="font-size:0.62rem; color:#64748b; font-style:italic; line-height:1.2; background:#f8fafc; padding:3px 6px; border-radius:4px; border-left:2px solid var(--primary); margin-bottom: 3px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'"><i class="fas fa-comment-alt" style="font-size:0.52rem; opacity:0.6;"></i> ${lead.latest_remark.length > 70 ? lead.latest_remark.substring(0, 70) + '...' : lead.latest_remark}</div>` : ''}
                                ${lead.follow_up_date ? `<div style="font-size:0.68rem; font-weight:700; color:var(--primary);"><i class="fas fa-calendar-alt"></i> ${new Date(lead.follow_up_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })}</div>` : ''}
                            </td>
                            <td style="text-align: right;"><div style="display:flex; gap:0.4rem; justify-content: flex-end;">${actionsHtml}</div></td>
                        </tr>`;
                }
            });

            // Batch update DOM
            if (currentFilters.view === 'kanban') {
                Object.keys(columnHtml).forEach(status => {
                    const list = document.querySelector(`.pipeline-column[data-status="${status}"] .leads-list`);
                    if (list) list.innerHTML = columnHtml[status];
                });
            } else {
                tableBody.innerHTML = tableHtml || '<tr><td colspan="10" style="text-align:center; padding:3rem; color:var(--text-muted);">No leads found for the selected filters.</td></tr>';
            }

            // Render Pagination UI
            renderPaginationUI(filteredLeads.length, limit, totalPages);

            // Update counters
            Object.keys(counters).forEach(status => {
                const badge = document.querySelector(`.pipeline-column[data-status="${status}"] .counter`);
                if (badge) badge.textContent = counters[status];
            });
        }

        function renderPaginationUI(total, limit, totalPages) {
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');

            if (total === 0) {
                paginationContainer.style.display = 'none';
                return;
            }
            paginationContainer.style.display = 'flex';

            const start = (currentFilters.page - 1) * limit + 1;
            const end = Math.min(start + limit - 1, total);
            paginationInfo.textContent = `Showing ${start} to ${end} of ${total} leads`;

            let buttonsHtml = '';

            // Previous Button
            buttonsHtml += `<button onclick="changePage(${currentFilters.page - 1})" class="pagination-btn" ${currentFilters.page === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;

            // Page Numbers
            let startPage = Math.max(1, currentFilters.page - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

            for (let i = startPage; i <= endPage; i++) {
                buttonsHtml += `<button onclick="changePage(${i})" class="pagination-btn ${i === currentFilters.page ? 'active' : ''}">${i}</button>`;
            }

            // Next Button
            buttonsHtml += `<button onclick="changePage(${currentFilters.page + 1})" class="pagination-btn" ${currentFilters.page === totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;

            paginationButtons.innerHTML = buttonsHtml;
        }

        function changePage(page) {
            currentFilters.page = page;
            sessionStorage.setItem('leads_page', page);
            renderLeads(allLeads);
            // Scroll to top of table
            document.getElementById('tableView').scrollTop = 0;
        }

        let searchTimeout;
        document.getElementById('leadSearch').addEventListener('input', (e) => {
            const query = e.target.value;
            clearTimeout(searchTimeout);

            // Exit "Show All" mode if search is cleared
            if (query === '') {
                allLeadsMode = false;
            }

            if (query.length >= 3 || query.length === 0) {
                searchTimeout = setTimeout(() => {
                    currentFilters.page = 1;
                    sessionStorage.setItem('leads_page', 1);
                    fetchLeads();
                }, 400);
            } else {
                // If search is too short, we still filter the current list client-side
                renderLeads(allLeads);
            }
        });

        document.getElementById('leadForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            // Handle requirement_ids specifically
            data.requirement_ids = Array.from(document.querySelectorAll('.req-checkbox:checked')).map(cb => cb.value);

            // Collect Custom Services
            const customServices = [];
            document.querySelectorAll('.custom-service-row').forEach(row => {
                const name = row.querySelector('.custom-service-name').value.trim();
                const price = row.querySelector('.custom-service-price').value.trim();
                if (name) {
                    customServices.push({ name, price });
                }
            });
            data.custom_services = JSON.stringify(customServices);

            data.mobile = (data.mobile || '').replace(/[^0-9]/g, '').slice(0, 10);
            const mobileRegex = /^[0-9]{10}$/;
            if (!mobileRegex.test(data.mobile)) {
                alert('Please enter a valid 10-digit mobile number.');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (data.email && !emailRegex.test(data.email)) {
                alert('Please enter a valid email address.');
                return;
            }

            if (currentEditId) {
                data.id = currentEditId;
            }

            try {
                const url = '<?= APP_URL ?>/public/index.php/api/leads.php';
                const response = await fetch(url, {
                    method: currentEditId ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error saving lead:', error);
            }
        });

        async function showStatusPicker(event, leadId, leadName) {
            const picker = document.getElementById('statusPickerOverlay');
            picker.style.display = 'block';
            picker.style.left = `${event.pageX}px`;
            picker.style.top = `${event.pageY}px`;
            picker.dataset.leadId = leadId;

            // Close picker on outside click
            const closePicker = (e) => {
                if (!picker.contains(e.target)) {
                    picker.style.display = 'none';
                    document.removeEventListener('click', closePicker);
                }
            };
            setTimeout(() => document.addEventListener('click', closePicker), 10);
        }

        async function setQuickCallStatus(status) {
            const picker = document.getElementById('statusPickerOverlay');
            const leadId = picker.dataset.leadId;
            picker.style.display = 'none';
            _intUpdateState = { lead_id: leadId, call_status: status };
            document.getElementById('int_remark_text').value = '';
            document.getElementById('interactionRemarkModal').style.display = 'flex';
            return;
        }

        async function showCategoryPicker(event, leadId) {
            const picker = document.getElementById('categoryPickerOverlay');
            picker.style.display = 'block';
            picker.style.left = `${event.pageX}px`;
            picker.style.top = `${event.pageY}px`;
            picker.dataset.leadId = leadId;

            const closePicker = (e) => {
                if (!picker.contains(e.target)) {
                    picker.style.display = 'none';
                    document.removeEventListener('click', closePicker);
                }
            };
            setTimeout(() => document.addEventListener('click', closePicker), 10);
        }

        let _catUpdateState = null;
        async function setQuickCategory(category) {
            const picker = document.getElementById('categoryPickerOverlay');
            const leadId = picker.dataset.leadId;
            picker.style.display = 'none';
            _catUpdateState = { id: leadId, category: category };
            document.getElementById('cat_remark_text').value = '';
            document.getElementById('categoryRemarkModal').style.display = 'flex';
        }

        let _intUpdateState = null;
        // Handle Interaction Remark Submission
        document.addEventListener('click', async function (e) {
            if (e.target && e.target.id === 'btnSaveIntRemark') {
                if (!_intUpdateState) return;
                const remarkInput = document.getElementById('int_remark_text');
                const remark = remarkInput ? remarkInput.value.trim() : '';
                if (!remark) {
                    alert('Please enter a remark.');
                    return;
                }
                const btn = e.target;
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Saving...';
                try {
                    const response = await fetch('<?= APP_URL ?>/public/index.php/api/lead_followups.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            lead_id: _intUpdateState.lead_id,
                            call_status: _intUpdateState.call_status,
                            remark: remark,
                            follow_up_date: new Date().toISOString().split('T')[0],
                            follow_up_time: new Date().toTimeString().split(' ')[0]
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        document.getElementById('interactionRemarkModal').style.display = 'none';
                        fetchLeads();
                    } else {
                        alert('Save failed: ' + result.error);
                    }
                } catch (error) {
                    console.error('Interaction Update Error:', error);
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                    _intUpdateState = null;
                }
            }
        });

        // Handle Category Remark Submission
        document.addEventListener('click', async function (e) {
            if (e.target && e.target.id === 'btnSaveCatRemark') {
                if (!_catUpdateState) return;
                const remark = document.getElementById('cat_remark_text').value.trim();
                if (!remark) {
                    alert('Please enter a remark.');
                    return;
                }
                const btn = e.target;
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Updating...';
                try {
                    const response = await fetch('<?= APP_URL ?>/public/index.php/api/leads.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: _catUpdateState.id,
                            category: _catUpdateState.category,
                            remark: remark
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        document.getElementById('categoryRemarkModal').style.display = 'none';
                        fetchLeads();
                    } else {
                        alert('Update failed: ' + result.error);
                    }
                } catch (error) {
                    console.error('Category Update Error:', error);
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                    _catUpdateState = null;
                }
            }
        });

        const allEmployees = <?= json_encode($employees) ?>;
        const allUsers = <?= json_encode($users) ?>;

        function openAssignPicker(event, leadId, type) {
            if (IS_EXECUTIVE) return;
            event.stopPropagation();
            const picker = document.getElementById('assignPickerOverlay');
            const pickerTitle = picker.querySelector('div');
            picker.style.display = 'block';
            picker.style.left = `${event.pageX}px`;
            picker.style.top = `${event.pageY}px`;
            picker.dataset.leadId = leadId;
            picker.dataset.type = type;

            pickerTitle.innerHTML = type === 'lead' ? '<i class="fas fa-user-tie"></i> Quick Assign Lead' : '<i class="fas fa-user-plus"></i> Quick Assign Task';
            pickerTitle.style.color = type === 'lead' ? 'var(--primary)' : 'var(--accent-hover, #7c3aed)';
            picker.style.borderColor = type === 'lead' ? 'var(--primary)' : 'var(--accent-hover, #7c3aed)';

            renderAssignList('');

            const searchInput = document.getElementById('assignSearch');
            searchInput.value = '';
            setTimeout(() => searchInput.focus(), 50);

            // Close on outside click
            const closePicker = (e) => {
                if (!picker.contains(e.target)) {
                    picker.style.display = 'none';
                    document.removeEventListener('click', closePicker);
                }
            };
            setTimeout(() => document.addEventListener('click', closePicker), 10);
        }

        function renderAssignList(term) {
            const list = document.getElementById('assignEmployeeList');
            const picker = document.getElementById('assignPickerOverlay');
            const type = picker.dataset.type;
            list.innerHTML = '';
            term = (term || '').toLowerCase();

            const items = type === 'lead' ? allUsers : allEmployees;

            items.filter(item => item.name.toLowerCase().includes(term) || (item.designation && item.designation.toLowerCase().includes(term)))
                .forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'status-option';
                    div.style.padding = '0.5rem';
                    div.style.fontSize = '0.75rem';
                    div.style.cursor = 'pointer';
                    div.style.borderRadius = '0.4rem';
                    div.style.borderBottom = '1px solid #f1f5f9';
                    div.innerHTML = `<strong>${item.name}</strong> ${item.designation ? `<span style="font-size:0.6rem; color:var(--text-muted);">${item.designation}</span>` : ''}`;
                    div.onclick = () => performQuickAssign(item.id, item.name);
                    list.appendChild(div);
                });

            if (list.innerHTML === '') {
                list.innerHTML = '<div style="padding:1rem; text-align:center; font-size:0.7rem; color:var(--text-muted);">No matches found</div>';
            }
        }

        document.getElementById('assignSearch').addEventListener('input', (e) => {
            renderAssignList(e.target.value);
        });

        async function performQuickAssign(targetId, targetName) {
            const picker = document.getElementById('assignPickerOverlay');
            const leadId = picker.dataset.leadId;
            const type = picker.dataset.type;
            picker.style.display = 'none';

            const payload = { id: leadId };
            if (type === 'lead') {
                payload.assigned_to = targetId;
            } else {
                payload.assigned_employee_id = targetId;
            }

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/leads.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (result.success) {
                    // Update UI manually to avoid reload
                    if (type === 'lead') {
                        const cardContainer = document.getElementById(`lead-assign-container-card-${leadId}`);
                        const tableContainer = document.getElementById(`lead-assign-container-table-${leadId}`);
                        const html = `<div style="color:var(--primary); font-weight:700;"><i class="fas fa-user-tie" style="width:14px"></i> <span style="font-size:0.6rem; opacity:0.6; text-transform:uppercase;">Lead Assigned:</span> ${targetName}</div>`;
                        const tableHtml = `<div style="font-weight:600; font-size:0.8rem; color:var(--primary); display:flex; align-items:center; gap:4px;" title="Lead Assigned"><i class="fas fa-user-tie" style="font-size:0.7rem; opacity:0.5;"></i> ${targetName}</div>`;
                        if (cardContainer) cardContainer.innerHTML = html;
                        if (tableContainer) tableContainer.innerHTML = tableHtml;
                    } else {
                        const cardContainer = document.getElementById(`task-assign-container-card-${leadId}`);
                        const tableContainer = document.getElementById(`task-assign-container-table-${leadId}`);
                        const html = `<div style="color:var(--accent-hover, #7c3aed); font-weight:700;"><i class="fas fa-id-badge" style="width:14px"></i> <span style="font-size:0.6rem; opacity:0.6; text-transform:uppercase;">Task Assigned:</span> ${targetName}</div>`;
                        const tableHtml = `<div style="font-weight:600; font-size:0.8rem; color:var(--accent-hover, #7c3aed); display:flex; align-items:center; gap:4px;" title="Task Assigned"><i class="fas fa-id-badge" style="font-size:0.7rem; opacity:0.5;"></i> ${targetName}</div>`;
                        if (cardContainer) cardContainer.innerHTML = html;
                        if (tableContainer) tableContainer.innerHTML = tableHtml;
                    }
                } else {
                    alert('Error assigning lead: ' + result.error);
                }
            } catch (error) {
                console.error('Error assigning lead:', error);
            }
        }

        async function bulkAssign() {
            const employeeId = document.getElementById('bulkAssignEmployee').value;
            const userId = document.getElementById('bulkAssignUser').value;

            if (!employeeId && !userId) {
                alert('Please select either an employee or a user to assign to.');
                return;
            }

            const selected = document.querySelectorAll('.lead-checkbox:checked');
            if (selected.length === 0) return;

            const leadIds = Array.from(selected).map(cb => cb.value);

            if (!confirm(`Are you sure you want to assign ${leadIds.length} lead(s)?`)) return;

            try {
                const res = await fetch(`<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=bulk_assign`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lead_ids: leadIds, employee_id: employeeId || null, user_id: userId || null })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('bulkAssignEmployee').value = '';
                    document.getElementById('bulkAssignUser').value = '';
                    clearSelection();
                    fetchLeads();
                } else {
                    alert(data.error || 'Failed to bulk assign leads');
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred during bulk assignment');
            }
        }

        function toggleTransferredFilter() {
            const select = document.getElementById('filterTransfer');
            if (currentFilters.transfer === 'transferred') {
                currentFilters.transfer = 'all';
                if (select) select.value = 'all';
            } else {
                currentFilters.transfer = 'transferred';
                if (select) select.value = 'transferred';
            }
            updateTransferredButtonState();
            currentFilters.page = 1;
            renderLeads(allLeads);
        }

        function handleTransferDropdownChange(val) {
            currentFilters.transfer = val;
            updateTransferredButtonState();
            currentFilters.page = 1;
            renderLeads(allLeads);
        }

        function updateTransferredButtonState() {
            const btn = document.getElementById('btnTransferredOnly');
            const counter = document.getElementById('transferredCounter');
            if (!btn) return;
            if (currentFilters.transfer === 'transferred') {
                btn.style.borderColor = '#6366f1';
                btn.style.background = '#eef2ff';
                btn.style.color = '#4338ca';
                if (counter) {
                    counter.style.background = '#6366f1';
                    counter.style.color = '#ffffff';
                }
            } else {
                btn.style.borderColor = 'var(--border)';
                btn.style.background = 'transparent';
                btn.style.color = '#475569';
                if (counter) {
                    counter.style.background = '#e0e7ff';
                    counter.style.color = '#4338ca';
                }
            }
        }

        function openTransferModal(leadId, leadName, currentAssignee) {
            document.getElementById('transferLeadId').value = leadId;
            document.getElementById('transferLeadInfoBox').innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px;">
                    <div><strong>${leadName}</strong></div>
                    <div style="font-size:0.75rem; color:#64748b;">Current Owner: <span style="color:var(--primary); font-weight:700;">${currentAssignee || 'Unassigned'}</span></div>
                </div>
            `;
            document.getElementById('transferTargetEmployee').value = '';
            document.getElementById('transferRemark').value = '';
            document.getElementById('transferLeadModal').style.display = 'flex';
        }

        function closeTransferModal() {
            document.getElementById('transferLeadModal').style.display = 'none';
        }

        async function submitTransferLead(event) {
            event.preventDefault();
            const leadId = document.getElementById('transferLeadId').value;
            const targetEmployeeId = document.getElementById('transferTargetEmployee').value;
            const remark = document.getElementById('transferRemark').value;

            if (!targetEmployeeId) {
                alert('Please select a target employee.');
                return;
            }

            const btn = document.getElementById('btnSubmitTransfer');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transferring...';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/leads.php?action=transfer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lead_id: leadId,
                        target_employee_id: targetEmployeeId,
                        remark: remark
                    })
                });

                const result = await response.json();
                if (result.success) {
                    closeTransferModal();
                    fetchLeads();
                } else {
                    alert('Transfer failed: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Transfer error:', err);
                alert('An error occurred while transferring the lead.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Transfer Lead';
            }
        }

        async function bulkTransferLeads() {
            const selected = document.querySelectorAll('.lead-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one lead to transfer.');
                return;
            }

            const targetEmployeeId = document.getElementById('bulkTransferEmployee').value;
            if (!targetEmployeeId) {
                alert('Please select a target employee for bulk transfer.');
                return;
            }

            const leadIds = Array.from(selected).map(cb => cb.value);
            const remark = prompt(`Enter an optional note for transferring ${leadIds.length} lead(s):`, '') ?? '';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=bulk_transfer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ids: leadIds,
                        target_employee_id: targetEmployeeId,
                        remark: remark
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert(`Bulk transfer completed. Transferred: ${result.transferred}, Failed: ${result.failed}`);
                    document.getElementById('bulkTransferEmployee').value = '';
                    clearSelection();
                    fetchLeads();
                } else {
                    alert('Bulk transfer failed: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Bulk transfer error:', err);
                alert('An error occurred during bulk transfer.');
            }
        }

        function bulkWhatsApp() {
            const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) return;
            openWAModal('lead', selected);
        }

        function bulkEmail() {
            const selectedEmails = Array.from(document.querySelectorAll('.lead-checkbox:checked'))
                .map(cb => cb.dataset.email)
                .filter(email => email && email.trim() !== '');

            if (selectedEmails.length === 0) {
                alert('None of the selected leads have an email address.');
                return;
            }
            window.location.href = 'mailto:?bcc=' + encodeURIComponent(selectedEmails.join(','));
        }

        function openExportModal() {
            const selected = document.querySelectorAll('.lead-checkbox:checked');
            const selectedOpt = document.getElementById('exportScopeSelectedOpt');
            const selectedCountSpan = document.getElementById('exportModalSelectedCount');
            const scopeSelect = document.getElementById('exportScope');

            if (selected.length > 0) {
                if (selectedOpt) selectedOpt.style.display = 'block';
                if (selectedCountSpan) selectedCountSpan.textContent = selected.length;
                if (scopeSelect) scopeSelect.value = 'selected';
            } else {
                if (selectedOpt) selectedOpt.style.display = 'none';
                if (scopeSelect && scopeSelect.value === 'selected') {
                    scopeSelect.value = 'all';
                }
            }
            handleExportScopeChange(scopeSelect ? scopeSelect.value : 'all');
            toggleModal('exportModal');
        }

        function handleExportScopeChange(val) {
            const box = document.getElementById('exportMonthYearBox');
            if (box) {
                box.style.display = (val === 'month') ? 'grid' : 'none';
            }
        }

        async function exportLeads() {
            const scope = document.getElementById('exportScope') ? document.getElementById('exportScope').value : 'all';
            const format = document.getElementById('exportFormat') ? document.getElementById('exportFormat').value : 'csv';
            let url = `<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=export&scope=${encodeURIComponent(scope)}&format=${encodeURIComponent(format)}`;

            if (scope === 'selected') {
                const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
                if (selected.length === 0) {
                    alert('No leads selected. Please select at least one lead.');
                    return;
                }
                url += `&ids=${selected.join(',')}`;
            } else if (scope === 'month') {
                const month = document.getElementById('exportMonth').value;
                const year = document.getElementById('exportYear').value;
                url += `&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}`;
            } else {
                url += `&all=1`;
            }

            const btn = document.getElementById('btnExecuteExport');
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
                btn.disabled = true;
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }, 2000);
            }

            window.location.href = url;
            toggleModal('exportModal');
        }

        async function importLeads(input) {
            if (!input.files || !input.files[0]) return;

            const month = document.getElementById('importMonth').value;
            const year = document.getElementById('importYear').value;

            const formData = new FormData();
            formData.append('file', input.files[0]);

            const btn = document.getElementById('importSubmitBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;

            try {
                const res = await fetch(`<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=import&month=${month}&year=${year}`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert(`Import successful!\nImported: ${data.imported}\nSkipped (Duplicates): ${data.skipped}`);
                    location.reload();
                } else {
                    alert('Import failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('An error occurred during import');
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                input.value = '';
                toggleModal('importModal');
            }
        }

        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.lead-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateBulkBar();
        }

        function updateBulkBar() {
            const selected = document.querySelectorAll('.lead-checkbox:checked');
            const bar = document.getElementById('bulkActionBar');
            const count = document.getElementById('selectedCount');

            if (selected.length > 0) {
                bar.style.display = 'flex';
                count.textContent = selected.length;
            } else {
                bar.style.display = 'none';
                document.getElementById('selectAllLeads').checked = false;
            }
        }

        function clearSelection() {
            document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAllLeads').checked = false;
            updateBulkBar();
        }

        async function bulkSetCategory() {
            const category = document.getElementById('bulkCategory').value;
            if (!category) {
                alert('Please select a category first.');
                return;
            }

            const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) return;

            if (!confirm(`Are you sure you want to set category to ${category.toUpperCase()} for ${selected.length} leads?`)) return;

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=bulk_set_category', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: selected, category: category })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Categories updated successfully');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }



        fetchLeads();
    </script>

    <!-- Import Modal -->
    <div id="importModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 450px;">
            <div
                style="padding: 1.5rem; border-bottom: 1px solid var(--border); display:flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 1.125rem; font-weight: 800;">Bulk Import Leads</h2>
                <button onclick="toggleModal('importModal')" class="btn-ghost"
                    style="width: 32px; height: 32px; border-radius: 50%;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding: 1.5rem;">
                <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 1.25rem;">Select the month and
                    year these leads belong to, then upload your CSV file.</p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label>Target Month</label>
                        <select id="importMonth" class="form-input" style="appearance: auto;">
                            <?php for ($m = 1; $m <= 12; $m++)
                                echo "<option value='$m' " . (date('n') == $m ? 'selected' : '') . ">" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label>Target Year</label>
                        <select id="importYear" class="form-input" style="appearance: auto;">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--)
                                echo "<option value='$y' " . (date('Y') == $y ? 'selected' : '') . ">$y</option>"; ?>
                        </select>
                    </div>
                </div>
                <button id="importSubmitBtn" onclick="document.getElementById('importFile').click()"
                    class="btn btn-primary" style="width: 100%; height: 42px;">
                    <i class="fas fa-upload"></i> Choose File & Import
                </button>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; z-index: 10000;">
        <div class="modal-content" style="max-width: 380px; width: 92%; padding: 0; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05); overflow: hidden; background: #fff; border: 1px solid var(--border);">
            <!-- Compact Header -->
            <div style="padding: 0.85rem 1.15rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 30px; height: 30px; border-radius: 6px; background: #e0e7ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                        <i class="fas fa-file-export"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 0.95rem; font-weight: 800; margin: 0; color: #0f172a; line-height: 1.2;">Bulk Export Leads</h2>
                        <span style="font-size: 0.7rem; color: #64748b; font-weight: 500;">Export your lead records</span>
                    </div>
                </div>
                <button type="button" onclick="toggleModal('exportModal')" class="btn-ghost"
                    style="width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; background: none; color: #94a3b8; cursor: pointer; padding: 0;">
                    <i class="fas fa-times" style="font-size: 0.8rem;"></i>
                </button>
            </div>

            <!-- Compact Body -->
            <div style="padding: 1rem 1.15rem;">
                <!-- Scope Selection -->
                <div style="margin-bottom: 0.75rem;">
                    <label style="display: block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; margin-bottom: 0.35rem;">
                        Data Range
                    </label>
                    <select id="exportScope" class="form-input" style="appearance: auto; width: 100%; height: 34px; font-size: 0.8rem; padding: 0 0.6rem; border-radius: 6px; font-weight: 600;" onchange="handleExportScopeChange(this.value)">
                        <option value="all" selected>All Leads (All Time)</option>
                        <option value="month">Specific Month & Year</option>
                        <option value="selected" id="exportScopeSelectedOpt" style="display: none;">Selected Leads (<span id="exportModalSelectedCount">0</span>)</option>
                    </select>
                </div>

                <!-- Monthly Selector (Hidden by default when All Leads is selected) -->
                <div id="exportMonthYearBox" style="display: none; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem; background: #f8fafc; padding: 0.5rem 0.6rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <div>
                        <label style="display: block; font-size: 0.68rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem;">Month</label>
                        <select id="exportMonth" class="form-input" style="appearance: auto; width: 100%; height: 32px; font-size: 0.78rem; padding: 0 0.5rem;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= date('n') == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.68rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem;">Year</label>
                        <select id="exportYear" class="form-input" style="appearance: auto; width: 100%; height: 32px; font-size: 0.78rem; padding: 0 0.5rem;">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= date('Y') == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Format Selection -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; margin-bottom: 0.35rem;">
                        Export Format
                    </label>
                    <select id="exportFormat" class="form-input" style="appearance: auto; width: 100%; height: 34px; font-size: 0.8rem; padding: 0 0.6rem; border-radius: 6px;">
                        <option value="csv">CSV Spreadsheet (.csv)</option>
                        <option value="xlsx">Excel Workbook (.xlsx)</option>
                    </select>
                </div>

                <!-- Footer Buttons -->
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                    <button type="button" onclick="toggleModal('exportModal')" class="btn btn-ghost"
                        style="height: 34px; padding: 0 0.85rem; font-size: 0.78rem; font-weight: 600; border-radius: 6px;">
                        Cancel
                    </button>
                    <button type="button" id="btnExecuteExport" onclick="exportLeads()" class="btn btn-primary"
                        style="height: 34px; padding: 0 1rem; font-size: 0.78rem; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Category Picker -->
    <div id="categoryPickerOverlay"
        style="display:none; position:absolute; z-index:9999; background:white; border:1px solid var(--border); border-radius:0.75rem; box-shadow: var(--shadow-lg); padding:0.5rem; min-width:140px;">
        <div
            style="font-size:0.6rem; font-weight:800; color:var(--text-muted); padding:0.25rem 0.5rem; text-transform:uppercase;">
            Set Category</div>
        <div class="status-option" onclick="setQuickCategory('red')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#f44336; font-weight:800;">
            <i class="fas fa-circle"></i> RED
        </div>
        <div class="status-option" onclick="setQuickCategory('green')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#2dc677; font-weight:800;">
            <i class="fas fa-circle"></i> GREEN
        </div>
        <div class="status-option" onclick="setQuickCategory('yellow')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#f59e0b; font-weight:800;">
            <i class="fas fa-circle"></i> YELLOW
        </div>
    </div>

    </div>

    <!-- Floating Status Picker -->
    <div id="statusPickerOverlay"
        style="display:none; position:absolute; z-index:9999; background:white; border:1px solid var(--border); border-radius:0.75rem; box-shadow: var(--shadow-lg); padding:0.5rem; min-width:160px;">
        <div
            style="font-size:0.6rem; font-weight:800; color:var(--text-muted); padding:0.25rem 0.5rem; text-transform:uppercase;">
            Quick Status Update</div>
        <div class="status-option" onclick="setQuickCallStatus('connected')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#059669;">
            Connected
        </div>
        <div class="status-option" onclick="setQuickCallStatus('not_picked')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#dc2626;">
            Not Picked
        </div>
        <div class="status-option" onclick="setQuickCallStatus('busy')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#d97706;">
            Busy
        </div>
        <div class="status-option" onclick="setQuickCallStatus('switched_off')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#4b5563;">
            Switched Off
        </div>
        <div class="status-option" onclick="setQuickCallStatus('wrong_number')"
            style="padding:0.5rem; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:0.5rem; color:#991b1b;">
            Wrong Number
        </div>
    </div>

    <style>
        /* ── Picker Dropdowns ─────────────────────────────────── */
        .status-option {
            transition: background 0.15s, transform 0.12s;
        }

        .status-option:hover {
            background: #f1f5f9;
            border-radius: 0.375rem;
            transform: translateX(2px);
        }

        .status-clickable {
            transition: all 0.15s ease;
        }

        .status-clickable:hover {
            filter: brightness(0.86);
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        /* ── Category Row Accents (table rows) ───────────────── */
        .leads-table tbody tr.lead-cat-red td:first-child {
            border-left: 4px solid #f87171;
        }

        .leads-table tbody tr.lead-cat-green td:first-child {
            border-left: 4px solid #34d399;
        }

        .leads-table tbody tr.lead-cat-yellow td:first-child {
            border-left: 4px solid #fbbf24;
        }

        .leads-table tbody tr.lead-cat-blue td:first-child {
            border-left: 4px solid #60a5fa;
        }

        .leads-table tbody tr.lead-cat-red {
            background: linear-gradient(to right, #fff5f5, #fff);
        }

        .leads-table tbody tr.lead-cat-green {
            background: linear-gradient(to right, #f0fdf6, #fff);
        }

        .leads-table tbody tr.lead-cat-yellow {
            background: linear-gradient(to right, #fffbeb, #fff);
        }

        .leads-table tbody tr.lead-cat-blue {
            background: linear-gradient(to right, #eff6ff, #fff);
        }

        /* ── Kanban Card Category Borders ────────────────────── */
        .lead-card.lead-cat-red {
            border-left: 3px solid #f87171;
            background: linear-gradient(to right, #fff5f5, #fff);
        }

        .lead-card.lead-cat-green {
            border-left: 3px solid #34d399;
            background: linear-gradient(to right, #f0fdf6, #fff);
        }

        .lead-card.lead-cat-yellow {
            border-left: 3px solid #fbbf24;
            background: linear-gradient(to right, #fffbeb, #fff);
        }

        .lead-card.lead-cat-blue {
            border-left: 3px solid #60a5fa;
            background: linear-gradient(to right, #eff6ff, #fff);
        }

        /* Lost row — muted with dashed border accent */
        .leads-table tbody tr.lost {
            opacity: 0.65;
        }

        .leads-table tbody tr.lost td:first-child {
            border-left: 4px dashed #fca5a5;
        }

        .lead-card.lost {
            opacity: 0.75;
            border-left-style: dashed !important;
        }
    </style>
    <script>
        function handleWAClick(type, id) {
            const record = allLeads.find(l => l.id == id) || {};
            openWAModal(type, id, record);
        }
    </script>
    <?php include 'partials/wa_modal.php'; ?>
</body>

</html>