<?php
use Core\Database;
use Core\Auth;
$db = Database::getInstance();
$company_id = Auth::companyId();

if (Auth::role() !== 'admin') {
    header("Location: " . APP_URL . "/public/index.php/dashboard");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .toolbar {
            background: white;
            padding: 1rem 1.25rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-select {
            height: 38px;
            padding: 0 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #334155;
            background: #f8fafc;
            cursor: pointer;
        }

        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .search-wrap i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.75rem;
        }

        .search-wrap input {
            width: 100%;
            padding-left: 2.25rem;
            height: 38px;
            font-size: 0.8125rem;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .user-table th {
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

        .user-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 800;
            color: #0f172a;
        }

        .user-email {
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 600;
        }

        .role-badge {
            padding: 0.25rem 0.625rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .role-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-super_admin {
            background: #000;
            color: #fff;
        }

        .role-manager {
            background: #e0f2fe;
            color: #075985;
        }

        .role-executive {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.625rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .row-actions {
            display: flex;
            gap: 0.375rem;
            justify-content: flex-end;
        }

        .icon-btn {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .icon-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f1f5f9;
        }

        .icon-btn.danger:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: #fff1f2;
        }

        .icon-btn.primary:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: #eff6ff;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div>
                    <h1 class="page-title">Manage Users</h1>
                    <p style="color:var(--text-muted);font-size:0.8125rem;font-weight:500;">Manage system login accounts
                        and permissions</p>
                </div>
            </header>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="userSearch" placeholder="Search name, email, role..." class="form-input"
                        style="margin:0;">
                </div>
                <select id="filterRole" class="filter-select">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                    <option value="manager">Manager</option>
                    <option value="executive">Executive</option>
                </select>
            </div>

            <!-- Table -->
            <div
                style="background:white;border-radius:1rem;border:1px solid var(--border);overflow:auto;max-height:calc(100vh - 250px);">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th style="min-width: 250px;">User Details</th>
                            <th>Role</th>
                            <th>Linked Employee</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <tr>
                            <td colspan="6">
                                <div class="empty-state"><i class="fas fa-spinner fa-spin"></i>
                                    <p>Loading users...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div id="paginationControls" style="margin-top: 1.25rem; display: none; justify-content: space-between; align-items: center; background: white; padding: 1rem; border-radius: 1rem; border: 1px solid var(--border);"></div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="userModal" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:500px;width:95%;">
            <div
                style="padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h2 style="font-size:1.125rem;font-weight:800;letter-spacing:-0.02em;">Edit User</h2>
                    <p style="font-size:0.75rem;color:var(--text-muted);font-weight:500;">Update account permissions and
                        status</p>
                </div>
                <button onclick="closeModal()" class="btn-ghost"
                    style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;">
                    <i class="fas fa-times" style="font-size:0.875rem;"></i>
                </button>
            </div>
            <form id="userForm" style="padding:1.5rem;">
                <input type="hidden" id="userId">
                <div class="form-group">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Full
                        Name</label>
                    <input type="text" id="f_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Email
                        Address</label>
                    <input type="email" id="f_email" class="form-input" required>
                </div>
                <div class="form-group">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Role</label>
                    <select id="f_role" class="form-input" style="appearance:auto;">
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="executive">Executive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">Status</label>
                    <select id="f_status" class="form-input" style="appearance:auto;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:700;color:#64748b;margin-bottom:0.375rem;text-transform:uppercase;">New
                        Password (leave blank to keep current)</label>
                    <input type="password" id="f_password" class="form-input" placeholder="Min 6 characters">
                </div>
                <div
                    style="display:flex;justify-content:flex-end;gap:0.75rem;padding-top:1rem;border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const APP_URL = '<?= APP_URL ?>';
        const API = '<?= APP_URL ?>/public/index.php/api/users.php';
        let currentFilters = { search: '', role: '', limit: '15' };

        const avatarColors = ['var(--accent-hover, #7c3aed)', '#0891b2', '#059669', '#d97706', '#dc2626', '#db2777', '#2563eb'];
        function getAvatarColor(name) {
            let h = 0; for (let c of name) h = c.charCodeAt(0) + ((h << 5) - h);
            return avatarColors[Math.abs(h) % avatarColors.length];
        }
        function initials(name) {
            return name.split(' ').slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
        }

        let currentPage = 1;
        async function fetchUsers(page = 1) {
            currentPage = page;
            currentFilters.page = page;
            
            const perPageElem = document.getElementById('perPage');
            if (perPageElem) {
                currentFilters.limit = perPageElem.value;
            }
            
            const params = new URLSearchParams(currentFilters);
            const r = await fetch(API + '?' + params);
            const result = await r.json();
            if (result.data) {
                renderTable(result.data);
                renderPagination(result);
            } else {
                renderTable(result);
                const controls = document.getElementById('paginationControls');
                if (controls) controls.style.display = 'none';
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
                    <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing ${result.data.length} of ${result.total_records} users</span>
                    <select id="perPage" onchange="fetchUsers(1)" style="padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #475569; background: #f8fafc; cursor: pointer;">
                        <option value="15" ${currentFilters.limit == '15' ? 'selected' : ''}>15 per page</option>
                        <option value="25" ${currentFilters.limit == '25' ? 'selected' : ''}>25 per page</option>
                        <option value="50" ${currentFilters.limit == '50' ? 'selected' : ''}>50 per page</option>
                        <option value="100" ${currentFilters.limit == '100' ? 'selected' : ''}>100 per page</option>
                        <option value="all" ${currentFilters.limit === 'all' ? 'selected' : ''}>All</option>
                    </select>
                </div>
            `;
            html += `<div style="display: flex; gap: 0.5rem; align-items: center;">`;
            
            if (currentPage > 1) {
                html += `<button onclick="fetchUsers(${currentPage - 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-left"></i></button>`;
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
                html += `<button onclick="fetchUsers(${i})" class="btn ${btnClass}" style="padding: 0.5rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; ${bgStyle}">${i}</button>`;
            }
            
            if (currentPage < result.total_pages) {
                html += `<button onclick="fetchUsers(${currentPage + 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-right"></i></button>`;
            }
            
            html += `</div>`;
            controls.innerHTML = html;
        }

        function renderTable(users) {
            const tbody = document.getElementById('userTableBody');
            if (!users.length) {
                tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><p>No users found</p></div></td></tr>`;
                return;
            }
            tbody.innerHTML = users.map(u => `
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div class="user-avatar" style="background:${u.photo ? 'transparent' : getAvatarColor(u.name)}; overflow:hidden;">
                        ${u.photo ? `<img src="${u.photo.includes('/') ? APP_URL + '/public/' + u.photo : APP_URL + '/public/uploads/profiles/' + u.photo}" style="width:100%;height:100%;object-fit:cover;">` : initials(u.name)}
                    </div>
                    <div>
                        <div class="user-name">${u.name} ${u.id == <?= Auth::userId() ?> ? '<span style="font-size:0.6rem;background:#ede9fe;color:var(--accent-hover, #7c3aed);padding:2px 4px;border-radius:4px;">YOU</span>' : ''}</div>
                        <div class="user-email">${u.email}</div>
                    </div>
                </div>
            </td>
            <td><span class="role-badge role-${u.role}">${u.role}</span></td>
            <td>
                <div style="font-weight:600;color:#334155;">${u.employee_name || 'System Account'}</div>
                <div style="font-size:0.7rem;color:#94a3b8;">${u.emp_code ? '#' + u.emp_code : ''}</div>
            </td>
            <td>
                <span class="status-badge status-${u.status}">
                    <span style="width:6px;height:6px;border-radius:50%;background:${u.status === 'active' ? '#10b981' : '#ef4444'};display:inline-block;"></span>
                    ${u.status.toUpperCase()}
                </span>
            </td>
            <td style="color:#64748b;font-size:0.75rem;">${new Date(u.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</td>
            <td>
                <div class="row-actions">
                    <button class="icon-btn primary" onclick="impersonateUser(${u.id})" title="Login As User"><i class="fas fa-user-shield"></i></button>
                    <button class="icon-btn" onclick="openEditModal(${u.id})" title="Edit User"><i class="fas fa-edit"></i></button>
                    <button class="icon-btn danger" onclick="deleteUser(${u.id}, '${u.name}')" title="Delete User"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
        }

        async function impersonateUser(id) {
            if (!confirm("Are you sure you want to login as this user? You will be redirected to the dashboard.")) return;
            const r = await fetch(API + '?action=impersonate&id=' + id);
            const res = await r.json();
            if (res.success) {
                window.location.href = APP_URL + '/public/index.php/dashboard';
            } else {
                alert(res.error || "Failed to impersonate user");
            }
        }

        async function openEditModal(id) {
            const r = await fetch(API + '?id=' + id);
            const u = await r.json();
            document.getElementById('userId').value = u.id;
            document.getElementById('f_name').value = u.name;
            document.getElementById('f_email').value = u.email;
            document.getElementById('f_role').value = u.role;
            document.getElementById('f_status').value = u.status;
            document.getElementById('f_password').value = '';
            document.getElementById('userModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        document.getElementById('userForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true; btn.textContent = 'Saving...';

            const id = document.getElementById('userId').value;
            const payload = {
                id: id,
                name: document.getElementById('f_name').value,
                email: document.getElementById('f_email').value,
                role: document.getElementById('f_role').value,
                status: document.getElementById('f_status').value,
            };
            const pwd = document.getElementById('f_password').value;
            if (pwd) payload.password = pwd;

            const res = await fetch(API, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();
            btn.disabled = false; btn.textContent = 'Save Changes';

            if (result.success) {
                closeModal();
                fetchUsers();
            } else {
                alert(result.error || "Failed to update user");
            }
        });

        async function deleteUser(id, name) {
            if (id == <?= Auth::userId() ?>) {
                alert("You cannot delete yourself!");
                return;
            }
            if (!confirm(`Delete user "${name}"? This will remove their login access.`)) return;
            const r = await fetch(API + '?id=' + id, { method: 'DELETE' });
            const res = await r.json();
            if (res.success) fetchUsers();
            else alert(res.error || "Failed to delete user");
        }

        let searchTimer;
        document.getElementById('userSearch').addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { currentFilters.search = this.value; fetchUsers(1); }, 300);
        });
        document.getElementById('filterRole').addEventListener('change', function () {
            currentFilters.role = this.value; fetchUsers(1);
        });

        fetchUsers();
    </script>
</body>

</html>