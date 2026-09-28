<?php
$isExecutive = \Core\Auth::isExecutive();
$db = \Core\Database::getInstance();
$company_id = \Core\Auth::companyId();

// Fetch stats for the header
if ($isExecutive) {
    $userId = \Core\Auth::userId();
    $empId = \Core\Auth::employeeId();
    $leadCond = "company_id = ? AND (assigned_to = ? OR assigned_employee_id = ?)";
    $leadParams = [$company_id, $userId, $empId];

    $total_customers = $db->fetchOne("SELECT COUNT(DISTINCT customer_id) as count FROM leads WHERE $leadCond AND customer_id IS NOT NULL", $leadParams)['count'];
    $active_leads = $db->fetchOne("SELECT COUNT(*) as count FROM leads WHERE $leadCond AND status IN ('new', 'interested', 'in_progress', 'follow_up')", $leadParams)['count'];
    $total_billed = $db->fetchOne("SELECT SUM(i.total_amount) as total FROM invoices i JOIN leads l ON i.lead_id = l.id WHERE l.$leadCond", $leadParams)['total'] ?? 0;
    $total_dues = $db->fetchOne("SELECT SUM(i.due_amount) as total FROM invoices i JOIN leads l ON i.lead_id = l.id WHERE l.$leadCond", $leadParams)['total'] ?? 0;
} else {
    $total_customers = $db->fetchOne("SELECT COUNT(*) as count FROM customers WHERE company_id = ?", [$company_id])['count'];
    $active_leads = $db->fetchOne("SELECT COUNT(*) as count FROM leads WHERE company_id = ? AND status IN ('new', 'interested', 'in_progress', 'follow_up')", [$company_id])['count'];
    $total_billed = $db->fetchOne("SELECT SUM(total_amount) as total FROM invoices WHERE company_id = ?", [$company_id])['total'] ?? 0;
    $total_dues = $db->fetchOne("SELECT SUM(due_amount) as total FROM invoices WHERE company_id = ?", [$company_id])['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        .customer-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .customer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .customer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }

        .history-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 800;
            border-bottom: 1px solid #e2e8f0;
        }

        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
        }

        .financial-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        .summary-item label {
            display: block;
            font-size: 0.65rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .summary-item span {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary);
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .btn-ledger:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .registry-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .registry-table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            font-size: 0.7rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }

        .registry-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }

        .btn-ledger {
            padding: 4px 10px;
            background: var(--primary);
            color: white;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .btn-wa, .btn-call {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #fff;
            transition: 0.3s ease;
        }
        
        /* WhatsApp Button */
        .btn-wa {
            background: #25D366;
        }
        .btn-wa:hover {
            background: #1ebe5d;
        }
        
        /* Call Button */
        .btn-call {
            background: #007bff;
        }
        .btn-call:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>
            <header class="header" style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;">
                    <div>
                        <h1 class="page-title" style="margin-bottom: 0.25rem;">Customer Registry</h1>
                        <p style="color: #64748b; font-size: 0.875rem;">View your unique clients and their complete engagement history</p>
                    </div>
                    <div>
                        <div class="search-bar" style="position: relative; width: 300px;">
                            <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.875rem;"></i>
                            <input type="text" id="customerSearch" placeholder="Search by name or mobile..." style="width: 100%; padding: 0.625rem 1rem 0.625rem 2.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.875rem; outline: none; transition: border-color 0.2s;">
                        </div>
                    </div>
                </div>
            </header>

            <!-- Stats Bar -->
            <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #eff6ff; color: #3b82f6; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Customers</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #0f172a;"><?= number_format($total_customers) ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #f0fdf4; color: #22c55e; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Active Leads</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #0f172a;"><?= number_format($active_leads) ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e0f2fe; color: #0ea5e9; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Total Billed</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #0f172a;">₹<?= number_format($total_billed, 0) ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef2f2; color: #ef4444; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-hand-holding-dollar"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase;">Receivables</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #ef4444;">₹<?= number_format($total_dues, 0) ?></div>
                    </div>
                </div>
            </div>

            <div style="background: white; border-radius: 1rem; border: 1px solid var(--border); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width: 40px; padding: 0.5rem 1rem; text-align: center;"><input type="checkbox" id="selectAllCustomers" onchange="toggleAll(this)"></th>
                            <th style="padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Customer</th>
                            <th style="padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Contact</th>
                            <th style="padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Total Leads</th>
                            <th style="padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Total Spent</th>
                            <th style="padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Last Activity</th>
                            <th style="padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #cbd5e1;"></i>
                                <p style="margin-top: 1rem; color: #64748b; font-weight: 500;">Loading customers...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="paginationControls" style="margin-top: 1.25rem; display: none; justify-content: space-between; align-items: center; background: white; padding: 1rem; border-radius: 1rem; border: 1px solid var(--border);"></div>
            <!-- Bulk Action Bar -->
            <?php if (!$isExecutive): ?>
                <div id="bulkActionBar"
                    style="display: none; position: fixed; top: 5.5rem; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 0.75rem 1.75rem; border-radius: 0.75rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); z-index: 1000; align-items: center; gap: 1.25rem; border: 1px solid rgba(255,255,255,0.1); animation: slideDown 0.3s ease;">

                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span id="selectedCount"
                            style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;">0</span>
                        <span style="font-size: 0.8125rem; font-weight: 700;">Selected</span>
                    </div>

                    <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.2);"></div>

                    <button onclick="bulkEmail()" class="btn" style="height: 32px; font-size: 0.75rem; background: #3b82f6; color: white; border: none; padding: 0 1rem; border-radius: 6px; font-weight: 700; display:flex; align-items:center; gap:5px;">
                        <i class="fas fa-envelope"></i> Bulk Email
                    </button>
                    <button onclick="bulkWhatsApp()" class="btn" style="height: 32px; font-size: 0.75rem; background: #25d366; color: white; border: none; padding: 0 1rem; border-radius: 6px; font-weight: 700; display:flex; align-items:center; gap:5px;">
                        <i class="fab fa-whatsapp"></i> Bulk WhatsApp
                    </button>

                    <button onclick="clearSelection()"
                        style="background: transparent; color: rgba(255,255,255,0.5); border: none; cursor: pointer; font-size: 0.9rem; padding: 4px; transition: all 0.2s;"
                        onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='rgba(255,255,255,0.5)'">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
            <?php endif; ?>

        </main>
    </div>

    </div>

    <script>
        let allCustomers = [];

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        let currentPage = 1;
        let currentLimit = '15';
        let searchQuery = '';

        async function fetchCustomers(page = 1) {
            currentPage = page;
            const perPageElem = document.getElementById('perPage');
            if (perPageElem) {
                currentLimit = perPageElem.value;
            }
            
            const params = new URLSearchParams();
            params.append('page', page);
            params.append('limit', currentLimit);
            if (searchQuery) params.append('search', searchQuery);
            
            try {
                const response = await fetch(`<?= APP_URL ?>/api/customers.php?${params.toString()}`);
                if (!response.ok) {
                    throw new Error(`API request failed with status ${response.status}`);
                }

                const raw = await response.text();
                const parsed = JSON.parse(raw);
                if (parsed.data) {
                    allCustomers = parsed.data;
                    renderCustomers(parsed.data);
                    renderPagination(parsed);
                } else if (Array.isArray(parsed)) {
                    allCustomers = parsed;
                    renderCustomers(parsed);
                    const controls = document.getElementById('paginationControls');
                    if (controls) controls.style.display = 'none';
                } else if (parsed && parsed.error) {
                    throw new Error(parsed.error);
                } else {
                    throw new Error('Unexpected response format from customers API');
                }
            } catch (error) {
                console.error('Error fetching customers:', error);
                const tbody = document.getElementById('customerTableBody');
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:#ef4444; font-weight:700;">${escapeHtml(error.message || 'Could not load customers')}</td></tr>`;
            }
        }
        
        function renderPagination(result) {
            const controls = document.getElementById('paginationControls');
            if (!result.total_pages || result.total_pages <= 1) {
                controls.style.display = 'none';
                return;
            }
            controls.style.display = 'flex';
            
            let html = `
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing ${result.data.length} of ${result.total_records} customers</span>
                    <select id="perPage" onchange="fetchCustomers(1)" style="padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #475569; background: #f8fafc; cursor: pointer;">
                        <option value="15" ${currentLimit == '15' ? 'selected' : ''}>15 per page</option>
                        <option value="25" ${currentLimit == '25' ? 'selected' : ''}>25 per page</option>
                        <option value="50" ${currentLimit == '50' ? 'selected' : ''}>50 per page</option>
                        <option value="100" ${currentLimit == '100' ? 'selected' : ''}>100 per page</option>
                        <option value="all" ${currentLimit === 'all' ? 'selected' : ''}>All</option>
                    </select>
                </div>
            `;
            html += `<div style="display: flex; gap: 0.5rem; align-items: center;">`;
            
            if (currentPage > 1) {
                html += `<button onclick="fetchCustomers(${currentPage - 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-left"></i></button>`;
            }
            
            let start_page = Math.max(1, currentPage - 2);
            let end_page = Math.min(result.total_pages, currentPage + 2);
            
            if (end_page - start_page < 4) {
                if (start_page === 1) {
                    end_page = Math.min(result.total_pages, 5);
                } else if (end_page === result.total_pages) {
                    start_page = Math.max(1, result.total_pages - 4);
                }
            }
            
            for (let i = start_page; i <= end_page; i++) {
                let isCurrent = i === currentPage;
                let btnClass = isCurrent ? 'btn-primary' : 'btn-ghost';
                let bgStyle = isCurrent ? `background: var(--primary); border: 1px solid var(--primary); color: white;` : `background: transparent; border: 1px solid #e2e8f0; color: #0f172a;`;
                html += `<button onclick="fetchCustomers(${i})" class="btn ${btnClass}" style="padding: 0.5rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; ${bgStyle}">${i}</button>`;
            }
            
            if (currentPage < result.total_pages) {
                html += `<button onclick="fetchCustomers(${currentPage + 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-right"></i></button>`;
            }
            
            html += `</div>`;
            controls.innerHTML = html;
        }

        function renderCustomers(customers) {
            const tbody = document.getElementById('customerTableBody');
            tbody.innerHTML = '';

            if (!Array.isArray(customers)) {
                console.error('Expected array for customers, got:', customers);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:#ef4444; font-weight:700;">Error: Could not load customers. Please check your connection.</td></tr>';
                return;
            }

            if (customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem; color:#64748b;">No customers found matching your search.</td></tr>';
                return;
            }

            customers.forEach(cust => {
                const name = (cust?.name || '').trim() || 'Unnamed';
                const mobile = (cust?.mobile || '').trim() || 'N/A';
                const leadCount = Number(cust?.lead_count || 0);
                const totalSpent = Number(cust?.total_spent || 0);
                const firstChar = name.charAt(0).toUpperCase();
                const row = document.createElement('tr');

                row.innerHTML = `
                    <td style="padding: 0.5rem 1rem; border-bottom: 1px solid #f1f5f9; text-align: center;">
                        <input type="checkbox" class="customer-checkbox" value="${cust.id}" data-email="${cust.email || ''}" onchange="updateBulkBar()">
                    </td>
                    <td style="padding: 0.5rem 1rem; border-bottom: 1px solid #f1f5f9;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <div style="width: 24px; height: 24px; background: #f1f5f9; border-radius: 6px; display:flex; align-items:center; justify-content:center; color: var(--primary); font-size: 0.7rem; font-weight: 800;">
                                ${escapeHtml(firstChar)}
                            </div>
                            <span style="font-weight: 700; color: #1e293b; font-size: 0.8rem;">${escapeHtml(name)}</span>
                        </div>
                    </td>
                    <td style="padding: 0.5rem 1rem; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #475569; font-size: 0.8rem;">
                        <div style="margin-bottom: ${cust?.email ? '4px' : '0'};"><i class="fas fa-phone" style="font-size: 0.7rem; opacity: 0.5; width: 14px;"></i> ${escapeHtml(mobile)}</div>
                        ${cust?.email ? `<div style="font-size: 0.75rem; color: #64748b; font-weight: 500;"><i class="fas fa-envelope" style="font-size: 0.7rem; opacity: 0.5; width: 14px;"></i> ${escapeHtml(cust.email)}</div>` : ''}
                    </td>
                    <td style="padding: 0.5rem 1rem; border-bottom: 1px solid #f1f5f9;">
                        <span style="background: #f8fafc; padding: 2px 8px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; border: 1px solid #e2e8f0; color: #64748b;">
                            <i class="fas fa-layer-group"></i> ${leadCount}
                        </span>
                    </td>
                    <td style="padding: 0.5rem 1rem; border-bottom: 1px solid #f1f5f9; font-weight: 800; color: var(--primary); font-size: 0.8rem;">₹${totalSpent.toLocaleString('en-IN')}</td>
                    <td style="padding: 0.5rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.75rem; color: #64748b; font-weight: 600;">
                        ${cust?.last_lead_at ? new Date(cust.last_lead_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                    </td>
                    <td style="padding: 0.5rem 1rem; border-bottom: 1px solid #f1f5f9; text-align: right;">
                        <div style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                            <a href="<?= APP_URL ?>/public/index.php/customer_profile?id=${Number(cust?.id || 0)}" class="btn-ledger" style="text-decoration: none; display: inline-flex; background: #64748b;" title="Profile">
                                <i class="fas fa-user-circle"></i>
                            </a>
                            <a href="<?= APP_URL ?>/public/index.php/lead_ledger?id=${Number(cust?.id || 0)}" class="btn-ledger" style="text-decoration: none; display: inline-flex;" title="Ledger">
                                <i class="fas fa-book-open"></i>
                            </a>
                            <button onclick="handleWAClick('customer', ${Number(cust?.id || 0)})" class="btn-wa" title="Send WhatsApp Message" style="border:none; cursor:pointer;">
                                <i class="fab fa-whatsapp"></i>
                            </button>
                            
                            <a href="tel:${cust?.mobile}" 
                               class="btn-call" title="Call">
                                <i class="fas fa-phone-alt"></i>
                            </a>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function toggleModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.toggle('active');
        }

        let searchTimer;
        document.getElementById('customerSearch').addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => fetchCustomers(1), 300);
        });

        function handleWAClick(type, id) {
            const record = allCustomers.find(c => Number(c.id) === Number(id)) || {};
            openWAModal(type, id, record);
        }

        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            updateBulkBar();
        }

        function updateBulkBar() {
            const selected = document.querySelectorAll('.customer-checkbox:checked').length;
            const bar = document.getElementById('bulkActionBar');
            const countSpan = document.getElementById('selectedCount');
            if (!bar) return;

            if (selected > 0) {
                countSpan.textContent = selected;
                bar.style.display = 'flex';
            } else {
                bar.style.display = 'none';
                document.getElementById('selectAllCustomers').checked = false;
            }
        }

        function clearSelection() {
            document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAllCustomers').checked = false;
            updateBulkBar();
        }

        function bulkWhatsApp() {
            const selected = Array.from(document.querySelectorAll('.customer-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) return;
            openWAModal('customer', selected);
        }

        function bulkEmail() {
            const selectedEmails = Array.from(document.querySelectorAll('.customer-checkbox:checked'))
                .map(cb => cb.dataset.email)
                .filter(email => email && email.trim() !== '');
            
            if (selectedEmails.length === 0) {
                alert('None of the selected customers have an email address.');
                return;
            }
            window.location.href = 'mailto:?bcc=' + encodeURIComponent(selectedEmails.join(','));
        }

        // Initialize
        fetchCustomers();
    </script>
    <?php include 'partials/wa_modal.php'; ?>
</body>

</html>