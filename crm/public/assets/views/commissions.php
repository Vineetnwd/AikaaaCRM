<?php
use Core\Auth;
$company_id = Auth::companyId();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissions | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.1;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .card-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .data-table th {
            background: #f8fafc;
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.68rem;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }

        .data-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        @media (max-width: 900px) {
            .stat-cards {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
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
                    <h1 class="page-title">Commission Settlements</h1>
                    <p style="color:var(--text-muted);font-size:0.8125rem;font-weight:500;">Track earnings and record
                        payouts</p>
                </div>
                <div class="header-actions" style="display:flex;gap:0.5rem;">
                    <button class="btn btn-ghost" onclick="document.getElementById('requestsModal').style.display='flex'"
                        style="height:38px;padding:0 1.25rem;display:flex;align-items:center;gap:0.5rem;white-space:nowrap;margin:0;background:#fef08a;color:#854d0e;border:1px solid #fde047;font-weight:700;">
                        <i class="fas fa-exclamation-circle"></i> Payouts Request <span id="reqBadge" style="background:#dc2626;color:white;border-radius:50%;padding:2px 6px;font-size:0.7rem;display:none;">0</span>
                    </button>
                    <button class="btn btn-primary" onclick="openPayoutModal()"
                        style="height:38px;padding:0 1.25rem;display:flex;align-items:center;gap:0.5rem;white-space:nowrap;margin:0;">
                        <i class="fas fa-money-bill-wave"></i> Record Payout
                    </button>
                </div>
            </header>

            <!-- Stats -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#ede9fe;color:var(--accent-hover, #7c3aed);"><i class="fas fa-coins"></i></div>
                    <div>
                        <div class="stat-label">Total Earned</div>
                        <div class="stat-value" id="stat-earned">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#d1fae5;color:#059669;"><i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Paid Out</div>
                        <div class="stat-value" id="stat-paid">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fee2e2;color:#dc2626;"><i
                            class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="stat-label">Pending Balance</div>
                        <div class="stat-value" id="stat-balance">—</div>
                    </div>
                </div>
            </div>

            <!-- Pending Requests (Moved to Modal) -->

            <div class="dashboard-grid">
                <!-- Balances -->
                <div style="background:white; border:1px solid var(--border); border-radius:1rem; overflow:hidden;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-users" style="color:#64748b;"></i> Employee Balances
                        </div>
                    </div>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <table class="data-table">
                            <thead style="position:sticky; top:0;">
                                <tr>
                                    <th>Employee</th>
                                    <th style="text-align:right;">Earned</th>
                                    <th style="text-align:right;">Paid</th>
                                    <th style="text-align:right;">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="balancesTable">
                                <tr>
                                    <td colspan="4" style="text-align:center;padding:2rem;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Payouts -->
                <div style="background:white; border:1px solid var(--border); border-radius:1rem; overflow:hidden;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-history" style="color:#64748b;"></i> Recent Payouts
                        </div>
                    </div>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <table class="data-table">
                            <thead style="position:sticky; top:0;">
                                <tr>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Note</th>
                                    <th style="text-align:right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="payoutsTable">
                                <tr>
                                    <td colspan="4" style="text-align:center;padding:2rem;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Record Payout Modal -->
    <div id="payoutModal" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:450px;width:95%;">
            <div
                style="padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h2 style="font-size:1.125rem;font-weight:800;">Record Commission Payout</h2>
                    <p style="font-size:0.75rem;color:var(--text-muted);font-weight:500;">Settle balances with employees
                    </p>
                </div>
                <button onclick="document.getElementById('payoutModal').style.display='none'" class="btn-ghost"
                    style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="payoutForm" style="padding:1.5rem;">
                <div style="margin-bottom:1rem;">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Select
                        Employee *</label>
                    <select id="p_employee" class="form-input" style="appearance:auto;" required
                        onchange="updateMaxAmount()">
                        <option value="">-- Choose Employee --</option>
                    </select>
                    <div id="p_balance_hint"
                        style="font-size:0.7rem;color:#dc2626;font-weight:700;margin-top:0.3rem;display:none;">Pending
                        Balance: ₹0.00</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div>
                        <label
                            style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Amount
                            (₹) *</label>
                        <input type="number" id="p_amount" class="form-input" step="0.01" min="1" required
                            style="font-weight:800;color:#059669;">
                    </div>
                    <div>
                        <label
                            style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Date
                            *</label>
                        <input type="date" id="p_date" class="form-input" required>
                    </div>
                </div>
                <div style="margin-bottom:1.5rem;">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Reference
                        / Note</label>
                    <input type="text" id="p_note" class="form-input" placeholder="e.g. Bank Transfer TXN-123">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:0.75rem;">
                    <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('payoutModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn" style="padding:0 1.5rem;">
                        <i class="fas fa-check"></i> Settle Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pending Requests Modal -->
    <div class="modal" id="requestsModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:999;">
        <div style="background:white;border-radius:1rem;width:90%;max-width:800px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.1);">
            <div class="card-header" style="background:#fefce8; border-bottom:1px solid #fef08a;display:flex;justify-content:space-between;align-items:center;">
                <div class="card-title"><i class="fas fa-exclamation-circle" style="color:#ca8a04;"></i> Pending Payout Requests</div>
                <button type="button" onclick="document.getElementById('requestsModal').style.display='none'" style="background:none;border:none;font-size:1.25rem;cursor:pointer;color:#94a3b8;"><i class="fas fa-times"></i></button>
            </div>
            <div style="max-height: 400px; overflow-y: auto; padding: 1rem;" id="pendingContainer">
                <table class="data-table">
                    <thead style="position:sticky; top:0; z-index:1;">
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Note</th>
                            <th style="text-align:right;">Amount</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pendingTable">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const API = '<?= APP_URL ?>/api/commissions.php';
        let employeesWithBalance = [];

        function fmtCurr(n) {
            return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function fmtDate(d) {
            return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        async function loadData() {
            try {
                // Stats
                const rs = await fetch(API + '?action=stats');
                const stats = await rs.json();
                if (stats.error) { alert('Stats Error: ' + stats.error); return; }
                document.getElementById('stat-earned').textContent = fmtCurr(stats.total_earned || 0);
                document.getElementById('stat-paid').textContent = fmtCurr(stats.total_paid || 0);
                document.getElementById('stat-balance').textContent = fmtCurr(stats.total_balance || 0);

                // Pending Requests
                const rPending = await fetch(API + '?action=pending');
                const pending = await rPending.json();
                if (pending.error) { alert('Pending Error: ' + pending.error); return; }
                const pTable = document.getElementById('pendingTable');
                const reqBadge = document.getElementById('reqBadge');
                
                if (!pending || pending.length === 0) {
                    reqBadge.style.display = 'none';
                    reqBadge.textContent = '0';
                    pTable.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#94a3b8;">No pending requests found.</td></tr>`;
                } else {
                    reqBadge.style.display = 'inline-block';
                    reqBadge.textContent = pending.length;
                    pTable.innerHTML = pending.map(p => `
                        <tr>
                            <td style="color:#64748b;font-size:0.75rem;font-weight:600;">${fmtDate(p.created_at || p.payout_date)}</td>
                            <td style="font-weight:700;color:#0f172a;">${p.employee_name} <span style="font-size:0.65rem;color:#94a3b8;">${p.emp_code || ''}</span></td>
                            <td style="color:#475569;font-size:0.75rem;">${p.reference_note || '—'}</td>
                            <td style="text-align:right;font-weight:800;color:#ca8a04;">${fmtCurr(p.amount)}</td>
                            <td style="text-align:right;gap:0.5rem;display:flex;justify-content:flex-end;">
                                <button onclick="approveRequest(${p.id})" class="btn btn-primary" style="padding:0.25rem 0.5rem;font-size:0.75rem;margin:0;"><i class="fas fa-check"></i></button>
                                <button onclick="rejectRequest(${p.id})" class="btn btn-ghost" style="padding:0.25rem 0.5rem;font-size:0.75rem;margin:0;color:#dc2626;"><i class="fas fa-times"></i></button>
                            </td>
                        </tr>
                    `).join('');
                }

                // Balances
                const rb = await fetch(API + '?action=balances');
                employeesWithBalance = await rb.json();
                if (employeesWithBalance.error) { alert('Balances Error: ' + employeesWithBalance.error); return; }
                const bTable = document.getElementById('balancesTable');
                if(!employeesWithBalance || employeesWithBalance.length === 0) {
                    bTable.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No commission data found.</td></tr>`;
                } else {
                    bTable.innerHTML = employeesWithBalance.map(e => `
                        <tr>
                            <td style="font-weight:700;color:#0f172a;">
                                <a href="<?= APP_URL ?>/public/index.php/employee_commissions?id=${e.id}" style="color:var(--primary, #6366f1);text-decoration:none;">${e.name}</a>
                                <div style="font-size:0.65rem;color:#94a3b8;font-weight:600;">${e.employee_id || 'No ID'}</div>
                            </td>
                            <td style="text-align:right;color:#64748b;">${fmtCurr(e.total_commission_earned)}</td>
                            <td style="text-align:right;color:#059669;">${fmtCurr(e.total_commission_paid)}</td>
                            <td style="text-align:right;font-weight:800;color:${e.balance > 0 ? '#dc2626' : '#64748b'};">${fmtCurr(e.balance)}</td>
                        </tr>
                    `).join('');
                }

                // Payouts
                const rp = await fetch(API + '?action=payouts');
                const payouts = await rp.json();
                if (payouts.error) { alert('Payouts Error: ' + payouts.error); return; }
                const payoutsTableEl = document.getElementById('payoutsTable');
                if(!payouts || payouts.length === 0) {
                    payoutsTableEl.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No payouts recorded yet.</td></tr>`;
                } else {
                    payoutsTableEl.innerHTML = payouts.map(p => `
                        <tr>
                            <td style="color:#64748b;font-size:0.75rem;font-weight:600;">${fmtDate(p.payout_date)}</td>
                            <td style="font-weight:700;color:#0f172a;">${p.employee_name}</td>
                            <td style="color:#64748b;font-size:0.75rem;">
                                ${p.reference_note || '—'}
                                ${p.status === 'rejected' ? '<span style="color:#dc2626;font-weight:bold;margin-left:5px;">(Rejected)</span>' : ''}
                                ${p.status === 'pending' ? '<span style="color:#ca8a04;font-weight:bold;margin-left:5px;">(Pending)</span>' : ''}
                            </td>
                            <td style="text-align:right;font-weight:800;color:${p.status==='rejected' ? '#94a3b8' : '#059669'}; ${p.status==='rejected' ? 'text-decoration:line-through;' : ''}">${fmtCurr(p.amount)}</td>
                        </tr>
                    `).join('');
                }
            } catch (error) {
                alert("Failed to load data: " + error.message);
            }
        }

        async function approveRequest(id) {
            if (!confirm('Approve this payout request?')) return;
            try {
                const res = await fetch(API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'approve', payout_id: id })
                });
                const result = await res.json();
                if (result.success) {
                    loadData();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Failed to approve request.');
            }
        }

        async function rejectRequest(id) {
            if (!confirm('Reject this payout request?')) return;
            try {
                const res = await fetch(API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reject', payout_id: id })
                });
                const result = await res.json();
                if (result.success) {
                    loadData();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Failed to reject request.');
            }
        }

        function openPayoutModal() {
            const sel = document.getElementById('p_employee');
            sel.innerHTML = '<option value="">-- Choose Employee --</option>';
            employeesWithBalance.filter(e => e.balance > 0).forEach(e => {
                const o = document.createElement('option');
                o.value = e.id;
                o.textContent = `${e.name} (Balance: ${fmtCurr(e.balance)})`;
                sel.appendChild(o);
            });
            document.getElementById('p_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('p_amount').value = '';
            document.getElementById('p_note').value = '';
            document.getElementById('p_balance_hint').style.display = 'none';
            document.getElementById('payoutModal').style.display = 'flex';
        }

        function updateMaxAmount() {
            const id = document.getElementById('p_employee').value;
            const hint = document.getElementById('p_balance_hint');
            const amtInput = document.getElementById('p_amount');

            if (!id) {
                hint.style.display = 'none';
                amtInput.max = '';
                return;
            }

            const emp = employeesWithBalance.find(e => e.id == id);
            if (emp) {
                hint.textContent = 'Pending Balance: ' + fmtCurr(emp.balance);
                hint.style.display = 'block';
                amtInput.max = emp.balance;
                amtInput.value = emp.balance; // auto-fill by default
            }
        }

        document.getElementById('payoutForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true; btn.innerHTML = 'Saving...';

            const payload = {
                employee_id: document.getElementById('p_employee').value,
                amount: document.getElementById('p_amount').value,
                payout_date: document.getElementById('p_date').value,
                reference_note: document.getElementById('p_note').value
            };

            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await res.json();
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Settle Payment';

            if (result.success) {
                document.getElementById('payoutModal').style.display = 'none';
                loadData();
            } else {
                alert('Error: ' + result.error);
            }
        });

        window.onload = loadData;
    </script>
</body>

</html>