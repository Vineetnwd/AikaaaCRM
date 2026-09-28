<?php
use Core\Auth;
if (!Auth::isSuperAdmin()) {
    header("Location: " . APP_URL . "/public/index.php/dashboard");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Companies | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .toolbar { background: white; padding: 1rem 1.25rem; border-radius: 1rem; border: 1px solid var(--border); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-wrap i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.75rem; }
        .search-wrap input { width: 100%; padding-left: 2.25rem; height: 38px; font-size: 0.8125rem; }

        .company-table { width: 100%; min-width: 1050px; border-collapse: collapse; font-size: 0.8125rem; }
        .company-table th { background: #f8fafc; padding: 0.875rem 1rem; text-align: left; font-weight: 800; color: #64748b; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .company-table td { padding: 0.875rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .company-table tr:hover td { background-color: #fafbfd; }
        
        .comp-name { font-weight: 800; color: #0f172a; font-size: 0.9rem; }
        .comp-sub { font-size: 0.72rem; color: var(--primary, #6366f1); font-weight: 700; }

        .plan-badge { padding: 0.25rem 0.625rem; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; white-space: nowrap; }
        .plan-pro    { background: #ede9fe; color: var(--accent-hover, #7c3aed); }
        .plan-basic  { background: #e0f2fe; color: #075985; }
        .plan-trial  { background: #f1f5f9; color: #64748b; }

        .status-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.625rem; border-radius: 20px; font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
        .status-active    { background: #d1fae5; color: #065f46; }
        .status-inactive  { background: #fee2e2; color: #991b1b; }
        .status-suspended { background: #1e293b; color: white; }

        .expiry-tag { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.67rem; font-weight: 700; white-space: nowrap; line-height: 1.2; }
        .expiry-tag.expired { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .expiry-tag.warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .expiry-tag.active  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

        .activity-metric-pill { display: inline-flex; flex-direction: column; padding: 0.35rem 0.65rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.15s ease-in-out; white-space: nowrap; }
        .activity-metric-pill:hover { background: #ffffff; border-color: var(--primary, #6366f1); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(99, 102, 241, 0.08); }
        .act-main { display: flex; align-items: baseline; gap: 0.4rem; line-height: 1.2; }
        .act-month { font-weight: 800; color: #0f172a; font-size: 0.9rem; }
        .act-slash { color: #cbd5e1; font-weight: 400; font-size: 0.8rem; }
        .act-today { font-weight: 700; color: #64748b; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; }
        .act-today.has-today { color: #059669; }
        .act-today-tag { font-size: 0.58rem; text-transform: uppercase; font-weight: 800; background: #e2e8f0; color: #475569; padding: 1px 4px; border-radius: 4px; letter-spacing: 0.03em; }
        .act-today.has-today .act-today-tag { background: #d1fae5; color: #065f46; }
        .act-sub { font-size: 0.68rem; color: #94a3b8; margin-top: 2px; font-weight: 500; }

        .row-actions { display: flex; gap: 0.375rem; justify-content: flex-end; }
        .icon-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .icon-btn:hover { border-color: var(--primary); color: var(--primary); background: #f1f5f9; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'partials/topbar.php'; ?>
        <header class="header">
            <div>
                <h1 class="page-title">Global Company Management</h1>
                <p style="color:var(--text-muted);font-size:0.8125rem;font-weight:500;">Super Admin access to all tenants and organizations</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add New Company</button>
        </header>

        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="compSearch" placeholder="Search company name, subdomain, GST..." class="form-input" style="margin:0;">
            </div>
        </div>

        <div style="background:white;border-radius:1rem;border:1px solid var(--border);overflow-x:auto;">
            <table class="company-table">
                <thead>
                    <tr>
                        <th style="min-width: 190px;">Company Details</th>
                        <th style="min-width: 120px;">Subdomain</th>
                        <th style="min-width: 90px;">Plan</th>
                        <th style="min-width: 100px;">Status</th>
                        <th style="min-width: 155px; white-space: nowrap;">Expiry Date</th>
                        <th style="min-width: 175px; white-space: nowrap;">Activity (Month / Today)</th>
                        <th style="min-width: 120px; white-space: nowrap;">Joined On</th>
                        <th style="text-align:right; min-width: 130px; white-space: nowrap;">Actions</th>
                    </tr>
                </thead>
                <tbody id="compTableBody">
                    <tr><td colspan="8"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading companies...</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal -->
<div id="compModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:600px;width:95%;">
        <div style="padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h2 id="modalTitle" style="font-size:1.125rem;font-weight:800;letter-spacing:-0.02em;">Add New Company</h2>
            </div>
            <button onclick="closeModal()" class="btn-ghost" style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;">
                <i class="fas fa-times" style="font-size:0.875rem;"></i>
            </button>
        </div>
        <form id="compForm" style="padding:1.5rem;">
            <input type="hidden" id="compId">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Company Name</label>
                    <input type="text" id="f_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Subdomain</label>
                    <input type="text" id="f_subdomain" class="form-input" placeholder="e.g. acme">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Plan</label>
                    <select id="f_plan" class="form-input" style="appearance:auto;">
                        <option value="trial">Trial</option>
                        <option value="basic">Basic</option>
                        <option value="pro">Pro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="f_status" class="form-input" style="appearance:auto;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Joined On</label>
                    <input type="date" id="f_created_at" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" id="f_subscription_starts_at" class="form-input">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Expiry Date / Subscription Ends</label>
                    <input type="date" id="f_subscription_ends_at" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">GST Number</label>
                <input type="text" id="f_gst" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea id="f_address" class="form-input" style="height:80px;"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:1rem;border-top:1px solid var(--border);">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">Save Company</button>
            </div>
        </form>
    </div>
</div>

<!-- Logs Modal -->
<div id="logsModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:700px;width:95%;">
        <div style="padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h2 style="font-size:1.125rem;font-weight:800;letter-spacing:-0.02em;">Subscription Logs</h2>
            </div>
            <button onclick="closeLogsModal()" class="btn-ghost" style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;">
                <i class="fas fa-times" style="font-size:0.875rem;"></i>
            </button>
        </div>
        <div style="padding:1.5rem; max-height: 400px; overflow-y: auto;">
            <table class="company-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action By</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Activity Breakdown Modal -->
<div id="activityModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:540px;width:95%;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h2 id="actModalTitle" style="font-size:1.1rem;font-weight:800;letter-spacing:-0.02em;margin:0;">Activity Breakdown</h2>
                <p id="actModalSubtitle" style="font-size:0.75rem;color:#64748b;margin:0.25rem 0 0 0;font-weight:500;"></p>
            </div>
            <button onclick="closeActivityModal()" class="btn-ghost" style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;">
                <i class="fas fa-times" style="font-size:0.875rem;"></i>
            </button>
        </div>
        <div style="padding:1.5rem;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <!-- Today Box -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.875rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">
                        <span style="font-size:0.75rem; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">Today</span>
                        <span id="actTodayTotal" style="font-size:1.25rem; font-weight:900; color:#0f172a;">0</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.6rem; font-size:0.78rem;">
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-phone-alt" style="width:16px; color:#3b82f6;"></i> Follow-ups</span><strong id="actTodayFollowups">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-user-plus" style="width:16px; color:#10b981;"></i> New Leads</span><strong id="actTodayLeads">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-tasks" style="width:16px; color:#8b5cf6;"></i> Tasks</span><strong id="actTodayTasks">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-file-invoice" style="width:16px; color:#f59e0b;"></i> Quotations</span><strong id="actTodayQuotations">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-receipt" style="width:16px; color:#ec4899;"></i> Invoices</span><strong id="actTodayInvoices">0</strong></div>
                    </div>
                </div>

                <!-- Month Box -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.875rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">
                        <span style="font-size:0.75rem; font-weight:800; color:var(--primary, #6366f1); text-transform:uppercase; letter-spacing:0.05em;">Current Month</span>
                        <span id="actMonthTotal" style="font-size:1.25rem; font-weight:900; color:var(--primary, #6366f1);">0</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.6rem; font-size:0.78rem;">
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-phone-alt" style="width:16px; color:#3b82f6;"></i> Follow-ups</span><strong id="actMonthFollowups">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-user-plus" style="width:16px; color:#10b981;"></i> New Leads</span><strong id="actMonthLeads">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-tasks" style="width:16px; color:#8b5cf6;"></i> Tasks</span><strong id="actMonthTasks">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-file-invoice" style="width:16px; color:#f59e0b;"></i> Quotations</span><strong id="actMonthQuotations">0</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span style="color:#64748b;"><i class="fas fa-receipt" style="width:16px; color:#ec4899;"></i> Invoices</span><strong id="actMonthInvoices">0</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const API = '<?= APP_URL ?>/public/index.php/api/companies.php';
let cachedCompanies = [];

async function fetchCompanies() {
    const r = await fetch(API + '?search=' + encodeURIComponent(document.getElementById('compSearch').value));
    const data = await r.json();
    cachedCompanies = Array.isArray(data) ? data : [];
    renderTable(cachedCompanies);
}

function renderTable(data) {
    const tbody = document.getElementById('compTableBody');
    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-building"></i><p>No companies found</p></div></td></tr>`;
        return;
    }
    tbody.innerHTML = data.map(c => {
        // Expiry Date Formatting & Status
        let expiryHtml = '';
        if (c.expiry_date) {
            const expDate = new Date(c.expiry_date);
            const dateFormatted = isNaN(expDate.getTime()) ? c.expiry_date : expDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            if (c.is_expired) {
                const daysAgo = Math.abs(c.days_left ?? 0);
                expiryHtml = `
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start; white-space:nowrap;">
                        <span style="font-weight:700; color:#0f172a; font-size:0.8125rem; white-space:nowrap; letter-spacing:-0.01em;">${dateFormatted}</span>
                        <span class="expiry-tag expired">
                            <i class="fas fa-exclamation-circle" style="font-size:0.62rem;"></i> Expired (${daysAgo}d ago)
                        </span>
                    </div>
                `;
            } else if (c.days_left !== null && c.days_left <= 7) {
                expiryHtml = `
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start; white-space:nowrap;">
                        <span style="font-weight:700; color:#0f172a; font-size:0.8125rem; white-space:nowrap; letter-spacing:-0.01em;">${dateFormatted}</span>
                        <span class="expiry-tag warning">
                            <i class="fas fa-clock" style="font-size:0.62rem;"></i> ${c.days_left === 0 ? 'Expires today' : c.days_left + 'd left'}
                        </span>
                    </div>
                `;
            } else {
                expiryHtml = `
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start; white-space:nowrap;">
                        <span style="font-weight:700; color:#0f172a; font-size:0.8125rem; white-space:nowrap; letter-spacing:-0.01em;">${dateFormatted}</span>
                        <span class="expiry-tag active">
                            <i class="fas fa-check-circle" style="font-size:0.62rem;"></i> ${c.days_left !== null ? c.days_left + 'd left' : 'Active'}
                        </span>
                    </div>
                `;
            }
        } else {
            expiryHtml = `
                <span style="color:#94a3b8; font-size:0.75rem; font-weight:600; display:inline-flex; align-items:center; gap:4px; white-space:nowrap;">
                    <i class="fas fa-infinity" style="font-size:0.65rem;"></i> Lifetime
                </span>
            `;
        }

        // Activity (Month / Today)
        const monthActs = c.month_activities ?? 0;
        const todayActs = c.today_activities ?? 0;
        const monthLeads = c.month_leads ?? 0;
        const monthFollowups = c.month_followups ?? 0;
        const actHtml = `
            <div class="activity-metric-pill" onclick="openActivityModal(${c.id})" title="Click to view detailed activity breakdown">
                <div class="act-main">
                    <span class="act-month" title="Current Month Activity">
                        ${monthActs}
                    </span>
                    <span class="act-slash">/</span>
                    <span class="act-today ${todayActs > 0 ? 'has-today' : ''}" title="Today Activity">
                        ${todayActs}
                        <span class="act-today-tag">today</span>
                    </span>
                </div>
                <div class="act-sub">
                    <span>${monthLeads} leads &bull; ${monthFollowups} follow-ups</span>
                </div>
            </div>
        `;

        return `
            <tr>
                <td>
                    <div class="comp-name">${c.name}</div>
                    <div style="font-size:0.7rem; color:#94a3b8;">${c.gst_number || 'No GST'}</div>
                </td>
                <td style="white-space:nowrap;"><span class="comp-sub">${c.subdomain || '—'}</span></td>
                <td style="white-space:nowrap;"><span class="plan-badge plan-${c.plan}">${c.plan}</span></td>
                <td style="white-space:nowrap;"><span class="status-badge status-${c.status}">${c.status.toUpperCase()}</span></td>
                <td style="white-space:nowrap;">${expiryHtml}</td>
                <td style="white-space:nowrap;">${actHtml}</td>
                <td style="color:#64748b; font-size:0.75rem; white-space:nowrap; font-weight:500;">${new Date(c.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
                <td style="white-space:nowrap;">
                    <div class="row-actions">
                        <button class="icon-btn" onclick="openLogsModal(${c.id})" title="View Logs"><i class="fas fa-history"></i></button>
                        <button class="icon-btn" onclick="openEditModal(${c.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <a href="<?= APP_URL ?>/public/index.php/impersonate?company_id=${c.id}" class="icon-btn" title="Login As" style="color: var(--success);"><i class="fas fa-sign-in-alt"></i></a>
                        <button class="icon-btn" onclick="deleteCompany(${c.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function calculateExpiryDate(plan, startDate) {
    if (!startDate) return '';
    const d = new Date(startDate);
    if (isNaN(d.getTime())) return '';
    if (plan === 'pro') {
        d.setFullYear(d.getFullYear() + 1);
    } else if (plan === 'basic') {
        d.setMonth(d.getMonth() + 1);
    } else { // trial
        d.setDate(d.getDate() + 7);
    }
    return d.toISOString().split('T')[0];
}

document.getElementById('f_plan').addEventListener('change', function() {
    const plan = this.value;
    const start = document.getElementById('f_subscription_starts_at').value || document.getElementById('f_created_at').value || new Date().toISOString().split('T')[0];
    document.getElementById('f_subscription_ends_at').value = calculateExpiryDate(plan, start);
});

document.getElementById('f_subscription_starts_at').addEventListener('change', function() {
    const plan = document.getElementById('f_plan').value;
    const start = this.value;
    if (start) {
        document.getElementById('f_subscription_ends_at').value = calculateExpiryDate(plan, start);
    }
});

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Company';
    document.getElementById('compId').value = '';
    document.getElementById('compForm').reset();
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('f_created_at').value = today;
    document.getElementById('f_subscription_starts_at').value = today;
    document.getElementById('f_plan').value = 'trial';
    document.getElementById('f_subscription_ends_at').value = calculateExpiryDate('trial', today);
    document.getElementById('compModal').style.display = 'flex';
}

async function openEditModal(id) {
    const r = await fetch(API + '?id=' + id);
    const c = await r.json();
    document.getElementById('modalTitle').textContent = 'Edit Company';
    document.getElementById('compId').value = c.id;
    document.getElementById('f_name').value = c.name;
    document.getElementById('f_subdomain').value = c.subdomain || '';
    document.getElementById('f_plan').value = c.plan;
    document.getElementById('f_status').value = c.status;
    document.getElementById('f_created_at').value = c.created_at ? c.created_at.split(' ')[0] : '';
    document.getElementById('f_subscription_starts_at').value = c.subscription_starts_at ? c.subscription_starts_at.split(' ')[0] : (c.created_at ? c.created_at.split(' ')[0] : '');
    
    let exp = c.expiry_date || c.subscription_ends_at;
    if (!exp && c.plan === 'trial') exp = c.trial_ends_at;
    if (!exp) {
        const start = document.getElementById('f_subscription_starts_at').value || new Date().toISOString().split('T')[0];
        exp = calculateExpiryDate(c.plan, start);
    }
    document.getElementById('f_subscription_ends_at').value = (exp || '').split(' ')[0];
    document.getElementById('f_gst').value = c.gst_number || '';
    document.getElementById('f_address').value = c.address || '';
    document.getElementById('compModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('compModal').style.display = 'none';
}

function openActivityModal(id) {
    const c = cachedCompanies.find(item => item.id == id);
    if (!c) return;

    document.getElementById('actModalTitle').textContent = `Activity Breakdown: ${c.name}`;
    document.getElementById('actModalSubtitle').textContent = `Tenant: ${c.subdomain ? c.subdomain + '.' : ''}aikocrm.com | Plan: ${c.plan.toUpperCase()}`;
    
    document.getElementById('actTodayTotal').textContent = c.today_activities ?? 0;
    document.getElementById('actTodayFollowups').textContent = c.today_followups ?? 0;
    document.getElementById('actTodayLeads').textContent = c.today_leads ?? 0;
    document.getElementById('actTodayTasks').textContent = c.today_tasks ?? 0;
    document.getElementById('actTodayQuotations').textContent = c.today_quotations ?? 0;
    document.getElementById('actTodayInvoices').textContent = c.today_invoices ?? 0;

    document.getElementById('actMonthTotal').textContent = c.month_activities ?? 0;
    document.getElementById('actMonthFollowups').textContent = c.month_followups ?? 0;
    document.getElementById('actMonthLeads').textContent = c.month_leads ?? 0;
    document.getElementById('actMonthTasks').textContent = c.month_tasks ?? 0;
    document.getElementById('actMonthQuotations').textContent = c.month_quotations ?? 0;
    document.getElementById('actMonthInvoices').textContent = c.month_invoices ?? 0;

    document.getElementById('activityModal').style.display = 'flex';
}

function closeActivityModal() {
    document.getElementById('activityModal').style.display = 'none';
}

document.getElementById('compForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    
    const id = document.getElementById('compId').value;
    const payload = {
        name: document.getElementById('f_name').value,
        subdomain: document.getElementById('f_subdomain').value,
        plan: document.getElementById('f_plan').value,
        status: document.getElementById('f_status').value,
        created_at: document.getElementById('f_created_at').value,
        subscription_starts_at: document.getElementById('f_subscription_starts_at').value,
        subscription_ends_at: document.getElementById('f_subscription_ends_at').value,
        gst_number: document.getElementById('f_gst').value,
        address: document.getElementById('f_address').value
    };
    if (id) payload.id = id;

    const res = await fetch(API, {
        method: id ? 'PUT' : 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const result = await res.json();
    btn.disabled = false;

    if (result.success) {
        closeModal();
        fetchCompanies();
    } else {
        alert(result.error || "Operation failed");
    }
});

async function deleteCompany(id) {
    if (!confirm("Are you sure? This will permanently remove the company record.")) return;
    const r = await fetch(API + '?id=' + id, { method: 'DELETE' });
    fetchCompanies();
}

async function openLogsModal(id) {
    document.getElementById('logsTableBody').innerHTML = `<tr><td colspan="3"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading logs...</p></div></td></tr>`;
    document.getElementById('logsModal').style.display = 'flex';
    const r = await fetch(API + '?action=logs&id=' + id);
    const logs = await r.json();
    const tbody = document.getElementById('logsTableBody');
    if (!logs || !logs.length) {
        tbody.innerHTML = `<tr><td colspan="3"><div class="empty-state"><p>No subscription logs found.</p></div></td></tr>`;
        return;
    }
    tbody.innerHTML = logs.map(l => {
        let changesStr = '';
        try {
            const ch = typeof l.changes === 'string' ? JSON.parse(l.changes) : l.changes;
            changesStr = Object.keys(ch).map(k => `<b>${k}</b>: <span style="color:var(--danger)">${ch[k].old || 'none'}</span> &rarr; <span style="color:var(--success)">${ch[k].new || 'none'}</span>`).join('<br>');
        } catch(e) {}
        return `<tr>
            <td style="color:#64748b;font-size:0.75rem;white-space:nowrap;">${new Date(l.created_at).toLocaleString()}</td>
            <td style="font-weight:700;font-size:0.75rem;">${l.admin_name || 'System'}</td>
            <td style="font-size:0.75rem;line-height:1.4;">${changesStr}</td>
        </tr>`;
    }).join('');
}

function closeLogsModal() {
    document.getElementById('logsModal').style.display = 'none';
}

function autoCalculateEndDate() {
    const plan = document.getElementById('f_plan').value;
    const startStr = document.getElementById('f_subscription_starts_at').value;
    if (!startStr) return;
    
    const startDate = new Date(startStr);
    if (isNaN(startDate.getTime())) return;
    
    let endDate = new Date(startDate);
    
    if (plan === 'trial') {
        endDate.setDate(endDate.getDate() + 7);
    } else if (plan === 'basic') {
        endDate.setMonth(endDate.getMonth() + 1);
    } else if (plan === 'pro') {
        endDate.setFullYear(endDate.getFullYear() + 1);
    }
    
    const yyyy = endDate.getFullYear();
    const mm = String(endDate.getMonth() + 1).padStart(2, '0');
    const dd = String(endDate.getDate()).padStart(2, '0');
    document.getElementById('f_subscription_ends_at').value = `${yyyy}-${mm}-${dd}`;
}

document.getElementById('f_plan').addEventListener('change', autoCalculateEndDate);
document.getElementById('f_subscription_starts_at').addEventListener('change', autoCalculateEndDate);

document.getElementById('compSearch').addEventListener('input', fetchCompanies);
fetchCompanies();
</script>
</body>
</html>
