<?php
use Core\Database;
use Core\Auth;
$db = Database::getInstance();
$company_id = Auth::companyId();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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

        .history-section {
            margin-bottom: 1.25rem;
        }

        .h-label {
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .h-label i {
            color: var(--primary, #6366f1);
        }

        .h-items {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .h-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #1e293b;
            font-weight: 500;
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

        .emp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .emp-table th {
            background: #f8fafc;
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.68rem;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .emp-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .emp-table tr:hover td {
            background: #fcfcfd;
        }

        .emp-table tr:last-child td {
            border-bottom: none;
        }

        .emp-avatar {
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

        .emp-name {
            font-weight: 800;
            color: #0f172a;
        }

        .emp-id {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 600;
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

        .status-on_leave {
            background: #fef3c7;
            color: #92400e;
        }

        .dept-tag {
            background: #ede9fe;
            color: #5b21b6;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
            display: block;
        }

        .empty-state p {
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Modal */
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.625rem;
        }

        .form-group {
            margin-bottom: 0.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .form-input {
            height: 32px !important;
            padding: 0 0.6rem !important;
            font-size: 0.8125rem !important;
        }

        textarea.form-input {
            height: auto !important;
            padding: 0.4rem 0.6rem !important;
        }

        select.form-input {
            padding: 0 0.5rem !important;
        }

        @media (max-width: 900px) {
            .stat-cards {
                grid-template-columns: 1fr 1fr;
            }

            .modal-grid {
                grid-template-columns: 1fr;
            }
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: flex-start;
            justify-content: center;
            z-index: 1000;
            padding: 0.75rem;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 900px;
            margin: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 12px 12px 0 0;
        }

        .modal-body {
            padding: 1rem 1.25rem;
        }

        .modal-section {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.625rem;
            padding: 0.625rem 0.875rem;
            margin-bottom: 0.625rem;
        }

        .modal-section-title {
            font-size: 0.65rem;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div>
                    <h1 class="page-title">Employees</h1>
                    <p style="color:var(--text-muted);font-size:0.8125rem;font-weight:500;">Manage your team members and
                        HR records</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddModal()"
                        style="height:38px;padding:0 1.25rem;display:flex;align-items:center;gap:0.5rem;white-space:nowrap;margin:0;">
                        <i class="fas fa-plus" style="font-size:0.75rem;"></i> Add Employee
                    </button>
                </div>
            </header>

            <!-- Stat Cards -->
            <div class="stat-cards" id="statCards">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#ede9fe;color:var(--accent-hover, #7c3aed);"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="stat-label">Total</div>
                        <div class="stat-value" id="stat-total">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#d1fae5;color:#059669;"><i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <div class="stat-label">Active</div>
                        <div class="stat-value" id="stat-active">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-user-clock"></i>
                    </div>
                    <div>
                        <div class="stat-label">On Leave</div>
                        <div class="stat-value" id="stat-on_leave">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-user-slash"></i>
                    </div>
                    <div>
                        <div class="stat-label">Inactive</div>
                        <div class="stat-value" id="stat-inactive">—</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#ecfdf5;color:#059669;"><i
                            class="fas fa-hand-holding-usd"></i></div>
                    <div>
                        <div class="stat-label">Total Commission</div>
                        <div class="stat-value" id="stat-commission" style="font-size:1.25rem;">—</div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="empSearch" placeholder="Search name, email, ID..." class="form-input"
                        style="margin:0;">
                </div>
                <select id="filterDept" class="filter-select">
                    <option value="">All Departments</option>
                </select>
                <select id="filterStatus" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>

            <!-- Table -->
            <div
                style="background:white;border-radius:1rem;border:1px solid var(--border);overflow:auto;max-height:calc(100vh - 340px);">
                <table class="emp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th style="width: 50px;">Photo</th>
                            <th style="min-width: 180px;">Name</th>
                            <th style="min-width: 180px;">Contact</th>
                            <th>Salary (₹)</th>
                            <th>Attendance</th>
                            <th>Target (₹)</th>
                            <th style="min-width: 150px;">Work Status</th>
                            <th>History</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="empTableBody">
                        <tr>
                            <td colspan="10">
                                <div class="empty-state"><i class="fas fa-spinner fa-spin"></i>
                                    <p>Loading...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </table>
            </div>

            <div id="paginationControls" style="margin-top: 1.25rem; display: none; justify-content: space-between; align-items: center; background: white; padding: 1rem; border-radius: 1rem; border: 1px solid var(--border);"></div>
        </main>
    </div>

    <!-- Add/Edit Modal -->
    <div id="empModal" class="modal" style="display:none;">
        <div class="modal-content" style="width:95%;">
            <div class="modal-header">
                <div>
                    <h2 id="modalTitle" style="font-size:1rem;font-weight:800;letter-spacing:-0.02em;">Add Employee</h2>
                    <p style="font-size:0.7rem;color:var(--text-muted);font-weight:500;margin:0;">Fill in the details
                        below</p>
                </div>
                <button onclick="closeModal()" class="btn-ghost"
                    style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;padding:0;">
                    <i class="fas fa-times" style="font-size:0.8rem;"></i>
                </button>
            </div>
            <form id="empForm" style="padding:0.875rem 1.25rem 1rem;">
                <input type="hidden" id="empId">

                <!-- Basic Info -->
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-bottom:0.5rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Full Name *</label>
                        <input type="text" id="f_name" class="form-input" placeholder="e.g. Ravi Sharma" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Employee ID</label>
                        <input type="text" id="f_employee_id" class="form-input" placeholder="e.g. EMP-001">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Designation</label>
                        <input type="text" id="f_designation" class="form-input" placeholder="e.g. Sales Manager">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Email</label>
                        <input type="email" id="f_email" class="form-input" placeholder="ravi@example.com">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Mobile</label>
                        <input type="text" id="f_mobile" class="form-input" placeholder="9876543210">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Department</label>
                        <input type="text" id="f_department" class="form-input" placeholder="e.g. Sales">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Date of Joining</label>
                        <input type="date" id="f_date_of_joining" class="form-input">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Date of Birth</label>
                        <input type="date" id="f_date_of_birth" class="form-input">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Gender</label>
                        <select id="f_gender" class="form-input" style="appearance:auto;">
                            <option value="">-- Select --</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Salary (₹)</label>
                        <input type="number" id="f_salary" class="form-input" placeholder="0.00" step="0.01">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Status</label>
                        <select id="f_status" class="form-input" style="appearance:auto;">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Profile Photo</label>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <input type="hidden" id="f_photo">
                            <img id="photoPreview" src=""
                                style="width:30px;height:30px;border-radius:50%;object-fit:cover;display:none;border:1px solid #e2e8f0;flex-shrink:0;">
                            <input type="file" id="photoInput" class="form-input" accept="image/*"
                                style="padding:0.2rem;cursor:pointer;flex:1;height:32px;"
                                onchange="uploadEmployeePhoto(this)">
                            <span id="photoUploadStatus"
                                style="font-size:0.7rem;color:#64748b;display:none;white-space:nowrap;"><i
                                    class="fas fa-spinner fa-spin"></i></span>
                        </div>
                    </div>
                </div>

                <!-- Login Account -->
                <div id="loginFields" class="modal-section" style="background:#eff6ff;border-color:#bfdbfe;">
                    <div class="modal-section-title" style="color:#1d4ed8;"><i class="fas fa-user-shield"></i> Login
                        Account</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Password *</label>
                            <input type="password" id="f_user_password" class="form-input"
                                placeholder="Set login password" minlength="6">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Role *</label>
                            <select id="f_user_role" class="form-input" style="appearance:auto;">
                                <option value="executive">Executive</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Commission + Work in 2 cols -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                    <!-- Commission -->
                    <div class="modal-section" style="background:#f0fdf4;border-color:#bbf7d0;">
                        <div class="modal-section-title" style="color:#15803d;"><i class="fas fa-hand-holding-usd"></i>
                            Commission</div>
                        <div style="display:flex;flex-direction:column;gap:0.4rem;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Type</label>
                                <select id="f_commission_type" class="form-input" style="appearance:auto;"
                                    onchange="updateRateLabel()">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed (₹)</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label id="rateLabel">Standard Rate</label>
                                <input type="number" id="f_commission_rate" class="form-input" placeholder="0.00"
                                    step="0.01" min="0">
                            </div>
                            <div class="form-group" style="margin-bottom:0;"
                                title="Rate applied to sales exceeding monthly target">
                                <label>Post-Target Rate</label>
                                <input type="number" id="f_post_target_commission_rate" class="form-input"
                                    placeholder="0.00" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    <!-- Work & Performance -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="fas fa-chart-line"></i> Work & Performance</div>
                        <div style="display:flex;flex-direction:column;gap:0.4rem;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Attendance</label>
                                <select id="f_attendance" class="form-input" style="appearance:auto;">
                                    <option value="Absent">Absent</option>
                                    <option value="Present">Present</option>
                                    <option value="Half Day">Half Day</option>
                                    <option value="On Leave">On Leave</option>
                                    <option value="Late">Late</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Monthly Target (₹)</label>
                                <input type="number" id="f_target" class="form-input" placeholder="0.00">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Daily Work Status</label>
                                <input type="text" id="f_daily_work_status" class="form-input"
                                    placeholder="Current task/status">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Work History</label>
                                <textarea id="f_daily_work_history" class="form-input" style="height:48px;resize:none;"
                                    placeholder="Recent updates..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Banking Information -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-university"></i> Banking Information</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Account Holder Name</label>
                            <input type="text" id="f_account_holder_name" class="form-input"
                                placeholder="e.g. Ravi Sharma">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Bank Name</label>
                            <input type="text" id="f_bank_name" class="form-input"
                                placeholder="e.g. State Bank of India">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Account Number</label>
                            <input type="text" id="f_account_number" class="form-input"
                                placeholder="e.g. 000000123456789">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>IFSC Code</label>
                            <input type="text" id="f_ifsc_code" class="form-input" placeholder="e.g. SBIN0001234">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>UPI ID</label>
                            <input type="text" id="f_upi_id" class="form-input" placeholder="e.g. user@upi">
                        </div>
                    </div>
                </div>

                <!-- Address & Notes -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.5rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Address</label>
                        <textarea id="f_address" class="form-input" style="height:52px;resize:none;"
                            placeholder="Full address..."></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Notes</label>
                        <textarea id="f_notes" class="form-input" style="height:52px;resize:none;"
                            placeholder="Additional notes..."></textarea>
                    </div>
                </div>

                <div
                    style="display:flex;justify-content:flex-end;gap:0.625rem;padding-top:0.75rem;border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn" style="padding:0 1.25rem;">
                        <i class="fas fa-save" style="font-size:0.75rem;"></i> Save Employee
                    </button>
                </div>
            </form>
        </div>



    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content" style="max-width:500px;">
            <div class="modal-header">
                <h3 id="historyTitle" style="margin:0;">Daily Activity</h3>
                <button class="close-btn" onclick="closeHistoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="historyContent"></div>
            </div>
        </div>
    </div>

    <script>
        const APP_URL = '<?= APP_URL ?>';
        const API = '<?= APP_URL ?>/public/index.php/api/employees.php';
        let allEmployees = [];
        let currentFilters = { search: '', department: '', status: 'active', limit: '15' };

        const avatarColors = ['var(--accent-hover, #7c3aed)', '#0891b2', '#059669', '#d97706', '#dc2626', '#db2777', '#2563eb'];
        function getAvatarColor(name) {
            let h = 0; for (let c of name) h = c.charCodeAt(0) + ((h << 5) - h);
            return avatarColors[Math.abs(h) % avatarColors.length];
        }
        function initials(name) {
            return name.split(' ').slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
        }

        const statusLabel = { active: 'Active', inactive: 'Inactive', on_leave: 'On Leave' };
        const statusClass = { active: 'status-active', inactive: 'status-inactive', on_leave: 'status-on_leave' };
        const statusDot = { active: '#10b981', inactive: '#ef4444', on_leave: '#f59e0b' };

        async function loadStats() {
            try {
                const r = await fetch(API + '?action=stats');
                if (!r.ok) throw new Error('Stats API failed');
                const s = await r.json();
                document.getElementById('stat-total').textContent = s.total || 0;
                document.getElementById('stat-active').textContent = s.active || 0;
                document.getElementById('stat-on_leave').textContent = s.on_leave || 0;
                document.getElementById('stat-inactive').textContent = s.inactive || 0;
                const comm = parseFloat(s.total_commission || 0);
                document.getElementById('stat-commission').textContent = comm > 0 ? '₹' + comm.toLocaleString('en-IN', { maximumFractionDigits: 0 }) : '₹0';
            } catch (e) {
            }
        }

        async function loadDepartments() {
            try {
                const r = await fetch(API + '?action=departments');
                if (!r.ok) throw new Error('Depts API failed');
                const depts = await r.json();
                const sel = document.getElementById('filterDept');
                depts.forEach(d => {
                    const o = document.createElement('option');
                    o.value = d.department; o.textContent = d.department;
                    sel.appendChild(o);
                });
            } catch (e) {
            }
        }

        async function fetchEmployees(page = 1) {
            currentPage = page;
            currentFilters.page = page;
            
            const perPageElem = document.getElementById('perPage');
            if (perPageElem) {
                currentFilters.limit = perPageElem.value;
            }
            
            const params = new URLSearchParams(currentFilters);
            try {
                const r = await fetch(API + '?' + params);
                const result = await r.json();
                
                if (result.data) {
                    allEmployees = result.data;
                    renderEmployees(result.data);
                    renderPagination(result);
                } else {
                    allEmployees = result;
                    renderEmployees(result);
                    const controls = document.getElementById('paginationControls');
                    if (controls) controls.style.display = 'none';
                }
            } catch (err) {
                console.error(err);
                document.getElementById('empTableBody').innerHTML = `<tr><td colspan="10"><div class="empty-state" style="color:#ef4444;"><i class="fas fa-exclamation-circle"></i><p>Failed to load employees.</p></div></td></tr>`;
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
                    <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing ${result.data.length} of ${result.total_records} employees</span>
                    <select id="perPage" onchange="fetchEmployees(1)" style="padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #475569; background: #f8fafc; cursor: pointer;">
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
                html += `<button onclick="fetchEmployees(${currentPage - 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-left"></i></button>`;
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
                html += `<button onclick="fetchEmployees(${i})" class="btn ${btnClass}" style="padding: 0.5rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; ${bgStyle}">${i}</button>`;
            }
            
            if (currentPage < result.total_pages) {
                html += `<button onclick="fetchEmployees(${currentPage + 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-right"></i></button>`;
            }
            
            html += `</div>`;
            controls.innerHTML = html;
        }

        function renderEmployees(employees) {
            const container = document.getElementById('empTableBody');
            if (!employees || employees.length === 0) {
                container.innerHTML = '<tr><td colspan="10"><div class="empty-state"><i class="fas fa-folder-open"></i><p>No employees found matching your criteria.</p></div></td></tr>';
                return;
            }

            const attendanceColors = {
                'Present': '#d1fae5',
                'Absent': '#fee2e2',
                'Half Day': '#fef3c7',
                'On Leave': '#fef3c7',
                'Late': '#ffedd5'
            };
            const attendanceText = {
                'Present': '#065f46',
                'Absent': '#991b1b',
                'Half Day': '#92400e',
                'On Leave': '#92400e',
            };

            try {
                container.innerHTML = employees.map((e, idx) => {
                    const todayTarget = (parseInt(e.today_leads_target || 0) + parseInt(e.today_tasks_assigned || 0) + parseInt(e.today_tasks_delivery || 0));
                    const todayUpdates = (parseInt(e.today_leads_updated || 0) + parseInt(e.today_tasks_updated || 0));

                    return `
        <tr>
            <td style="color:#94a3b8; font-weight:700;">${((currentPage - 1) * 10) + idx + 1}</td>
            <td>
                <div class="emp-avatar" style="background:${e.photo ? 'transparent' : 'var(--primary, #6366f1)'}; overflow:hidden;">
                    ${e.photo ? `<img src="${e.photo.startsWith('http') ? e.photo : (e.photo.includes('/') ? APP_URL + '/public/' + e.photo : APP_URL + '/public/uploads/profiles/' + e.photo)}" style="width:100%;height:100%;object-fit:cover;">` : (e.name ? e.name[0].toUpperCase() : '?')}
                </div>
            </td>
            <td>
                <div class="emp-name">${escHtml(e.name)}</div>
                <div class="emp-id">#${escHtml(e.employee_id || 'ID-TBD')}</div>
            </td>
            <td>
                <div style="font-size:0.8rem;font-weight:600;color:#334155;">${e.mobile ? escHtml(e.mobile) : '—'}</div>
                <div style="font-size:0.72rem;color:#94a3b8;">${e.email ? escHtml(e.email) : ''}</div>
            </td>
            <td style="font-weight:700;color:#0f172a;">${e.salary ? '₹' + Number(e.salary).toLocaleString('en-IN') : '—'}</td>
            <td>
                <span class="status-badge" style="background:${attendanceColors[e.attendance] || '#f1f5f9'}; color:${attendanceText[e.attendance] || '#64748b'};">
                    ${e.attendance || 'Absent'}
                </span>
            </td>
            <td>
                <div style="display:flex; flex-direction:column; gap:0.25rem; cursor:pointer;" onclick="viewTarget(${e.id})" title="Click to view target details">
                    <span style="font-weight:800; color:var(--primary, #6366f1); font-size:1rem;">${todayTarget}</span>
                    ${e.target > 0 ? `<span style="font-size:0.65rem; color:#059669; font-weight:600;">(Plan: ₹${Number(e.target).toLocaleString('en-IN')})</span>` : ''}
                </div>
            </td>
            <td>
                <div style="font-size:0.75rem; font-weight:600; color:#475569;">
                    ${e.daily_work_status ? escHtml(e.daily_work_status) : '<span style="color:#cbd5e1;">Not updated</span>'}
                </div>
            </td>
            <td>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <div style="font-weight:800; color:#0f172a;">${todayUpdates}</div>
                    <button class="icon-btn" onclick="viewHistory(${e.id})" title="View Today's Updates" style="${todayUpdates > 0 ? 'color:var(--primary, #6366f1); border-color:var(--primary, #6366f1);' : ''}">
                        <i class="fas fa-history"></i>
                    </button>
                </div>
            </td>
            <td>
                <div class="row-actions">
                    <a href="${APP_URL}/public/index.php/employee_profile?id=${e.id}" class="icon-btn" title="View Profile" style="color:var(--primary, #6366f1); border-color:#e0e7ff; background:#f5f3ff;"><i class="fas fa-user"></i></a>
                    <a href="${APP_URL}/public/index.php/employee_commissions?id=${e.id}" class="icon-btn" title="View Commission History" style="color:#059669; border-color:#d1fae5; background:#ecfdf5; display:inline-flex; align-items:center; justify-content:center; text-decoration:none;"><i class="fas fa-coins"></i></a>
                    <button class="icon-btn" onclick="openEditModal(${e.id})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="icon-btn danger" onclick="deleteEmployee(${e.id}, '${escHtml(e.name).replace(/'/g, "\\'")}');" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
                </tr>
            `;
                }).join('');
            } catch (renderErr) {
                console.error('Render error:', renderErr);
                container.innerHTML = `<tr><td colspan="9"><div class="empty-state" style="color:#ef4444;"><i class="fas fa-exclamation-circle"></i><p>Rendering error: ${renderErr.message}</p></div></td></tr>`;
            }
        }

        function fmtDate(d) {
            if (!d) return '—';
            const dt = new Date(d);
            return dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        }
        function escHtml(s) {
            const div = document.createElement('div'); div.textContent = s; return div.innerHTML;
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Employee';
            document.getElementById('empId').value = '';
            document.getElementById('empForm').reset();
            document.getElementById('loginFields').style.display = 'block';
            document.getElementById('f_user_password').required = true;
            document.getElementById('f_user_password').value = '';
            document.getElementById('f_user_role').value = 'executive';
            document.getElementById('f_photo').value = '';
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('photoInput').value = '';
            document.getElementById('empModal').style.display = 'flex';
        }

        async function openEditModal(id) {
            const r = await fetch(API + '?id=' + id);
            const e = await r.json();
            document.getElementById('modalTitle').textContent = 'Edit Employee';
            document.getElementById('empId').value = e.id;
            document.getElementById('loginFields').style.display = 'none';
            document.getElementById('f_user_password').required = false;
            document.getElementById('f_user_password').value = '';
            document.getElementById('f_user_role').value = e.user_role || 'executive';
            document.getElementById('f_name').value = e.name || '';
            document.getElementById('f_employee_id').value = e.employee_id || '';
            document.getElementById('f_email').value = e.email || '';
            document.getElementById('f_mobile').value = e.mobile || '';
            document.getElementById('f_designation').value = e.designation || '';
            document.getElementById('f_department').value = e.department || '';
            document.getElementById('f_date_of_joining').value = e.date_of_joining || '';
            document.getElementById('f_date_of_birth').value = e.date_of_birth || '';
            document.getElementById('f_gender').value = e.gender || '';
            document.getElementById('f_salary').value = e.salary || '';
            document.getElementById('f_commission_type').value = e.commission_type || 'percentage';
            document.getElementById('f_commission_rate').value = e.commission_rate || '';
            document.getElementById('f_post_target_commission_rate').value = e.post_target_commission_rate || '';
            document.getElementById('f_status').value = e.status || 'active';
            document.getElementById('f_address').value = e.address || '';
            document.getElementById('f_notes').value = e.notes || '';

            document.getElementById('f_photo').value = e.photo || '';
            const preview = document.getElementById('photoPreview');
            if (e.photo) {
                preview.src = e.photo.startsWith('http') ? e.photo : (e.photo.includes('/') ? APP_URL + '/public/' + e.photo : APP_URL + '/public/uploads/profiles/' + e.photo);
                preview.style.display = 'block';
            } else {
                preview.src = '';
                preview.style.display = 'none';
            }
            document.getElementById('photoInput').value = '';
            document.getElementById('f_attendance').value = e.attendance || 'Absent';
            document.getElementById('f_target').value = e.target || '';
            document.getElementById('f_daily_work_status').value = e.daily_work_status || '';
            document.getElementById('f_daily_work_history').value = e.daily_work_history || '';

            document.getElementById('f_bank_name').value = e.bank_name || '';
            document.getElementById('f_account_holder_name').value = e.account_holder_name || '';
            document.getElementById('f_account_number').value = e.account_number || '';
            document.getElementById('f_ifsc_code').value = e.ifsc_code || '';
            document.getElementById('f_upi_id').value = e.upi_id || '';

            updateRateLabel();
            document.getElementById('empModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('empModal').style.display = 'none';
        }

        function viewHistory(id) {
            try {
                const emp = allEmployees.find(e => e.id == id);
                if (!emp) return;

                const modal = document.getElementById('historyModal');
                if (!modal) return;

                document.getElementById('historyTitle').textContent = `Daily Activity: ${emp.name}`;
                const content = document.getElementById('historyContent');

                let html = '';

                if (emp.today_lead_details || emp.today_task_details) {
                    if (emp.today_lead_details) {
                        html += `<div class="history-section">
                            <div class="h-label"><i class="fas fa-bullseye"></i> Updated Leads</div>
                            <div class="h-items">
                                ${emp.today_lead_details.split(';;').map((row, i) => {
                            const [name, mobile, req] = row.split('||');
                            return `
                                    <div class="h-item" style="display:flex; flex-direction:column; gap:0.25rem;">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <span style="font-weight:700; color:#0f172a;">${i + 1}. ${escHtml(name)}</span>
                                            <span style="font-size:0.75rem; color:var(--primary, #6366f1); font-weight:600;">${escHtml(mobile)}</span>
                                        </div>
                                        ${req ? `<div style="font-size:0.75rem; color:#64748b; background:#f1f5f9; padding:0.4rem; border-radius:4px; margin-top:0.25rem;">
                                            <strong>Requirement:</strong> ${escHtml(req)}
                                        </div>` : ''}
                                    </div>`;
                        }).join('')}
                            </div>
                        </div>`;
                    }
                    if (emp.today_task_details) {
                        html += `<div class="history-section">
                            <div class="h-label"><i class="fas fa-tasks"></i> Updated Tasks</div>
                            <div class="h-items">
                                ${emp.today_task_details.split(';;').map((t, i) => `
                                    <div class="h-item">
                                        <span style="color:#64748b; margin-right:0.5rem;">${i + 1}.</span> ${escHtml(t)}
                                    </div>`).join('')}
                            </div>
                        </div>`;
                    }
                } else {
                    html += `<div style="text-align:center; padding:2rem; color:#94a3b8;"><i class="fas fa-info-circle" style="font-size:2rem; opacity:0.2; margin-bottom:1rem; display:block;"></i> No updates recorded for today</div>`;
                }

                if (emp.daily_work_history) {
                    html += `<div class="history-section" style="border-top:1px dashed #e2e8f0; padding-top:1.25rem; margin-top:1.25rem;">
                        <div class="h-label"><i class="fas fa-edit"></i> Manual Progress Logs</div>
                        <div style="font-size:0.85rem; color:#475569; white-space:pre-wrap; line-height:1.6; background:#fdfdfd; padding:1rem; border-radius:10px; border:1px solid #f1f5f9;">${escHtml(emp.daily_work_history)}</div>
                    </div>`;
                }

                content.innerHTML = html;
                modal.style.display = 'flex';
            } catch (err) {
                console.error('viewHistory error:', err);
            }
        }

        function viewTarget(id) {
            try {
                const emp = allEmployees.find(e => e.id == id);
                if (!emp) return;

                const modal = document.getElementById('historyModal');
                if (!modal) return;

                document.getElementById('historyTitle').textContent = `Targets Today: ${emp.name}`;
                const content = document.getElementById('historyContent');

                let html = '';

                if (emp.today_lead_target_details || emp.today_task_assigned_details || emp.today_task_delivery_details) {
                    if (emp.today_lead_target_details) {
                        html += `<div class="history-section">
                            <div class="h-label"><i class="fas fa-bullseye"></i> Follow-up Leads</div>
                            <div class="h-items">
                                ${emp.today_lead_target_details.split(';;').map((row, i) => {
                            const [name, mobile, req] = row.split('||');
                            return `
                                    <div class="h-item" style="display:flex; flex-direction:column; gap:0.25rem;">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <span style="font-weight:700; color:#0f172a;">${i + 1}. ${escHtml(name)}</span>
                                            <span style="font-size:0.75rem; color:var(--primary, #6366f1); font-weight:600;">${escHtml(mobile)}</span>
                                        </div>
                                        ${req ? `<div style="font-size:0.75rem; color:#64748b; background:#f1f5f9; padding:0.4rem; border-radius:4px; margin-top:0.25rem;">
                                            <strong>Requirement:</strong> ${escHtml(req)}
                                        </div>` : ''}
                                    </div>`;
                        }).join('')}
                            </div>
                        </div>`;
                    }
                    if (emp.today_task_assigned_details) {
                        html += `<div class="history-section">
                            <div class="h-label"><i class="fas fa-plus-circle"></i> Tasks Assigned Today</div>
                            <div class="h-items">
                                ${emp.today_task_assigned_details.split(';;').map((t, i) => `
                                    <div class="h-item">
                                        <span style="color:#64748b; margin-right:0.5rem;">${i + 1}.</span> ${escHtml(t)}
                                    </div>`).join('')}
                            </div>
                        </div>`;
                    }
                    if (emp.today_task_delivery_details) {
                        html += `<div class="history-section">
                            <div class="h-label"><i class="fas fa-truck"></i> Tasks Due Today (Delivery)</div>
                            <div class="h-items">
                                ${emp.today_task_delivery_details.split(';;').map((t, i) => `
                                    <div class="h-item">
                                        <span style="color:#64748b; margin-right:0.5rem;">${i + 1}.</span> ${escHtml(t)}
                                    </div>`).join('')}
                            </div>
                        </div>`;
                    }
                } else {
                    html += `<div style="text-align:center; padding:2rem; color:#94a3b8;"><i class="fas fa-calendar-check" style="font-size:2rem; opacity:0.2; margin-bottom:1rem; display:block;"></i> No targets set for today</div>`;
                }

                content.innerHTML = html;
                modal.style.display = 'flex';
            } catch (err) {
                console.error('viewTarget error:', err);
            }
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        document.getElementById('historyModal').addEventListener('click', function (e) {
            if (e.target === this) closeHistoryModal();
        });

        document.getElementById('empForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const id = document.getElementById('empId').value;
            const payload = {
                name: document.getElementById('f_name').value.trim(),
                employee_id: document.getElementById('f_employee_id').value.trim(),
                email: document.getElementById('f_email').value.trim(),
                mobile: document.getElementById('f_mobile').value.trim(),
                designation: document.getElementById('f_designation').value.trim(),
                department: document.getElementById('f_department').value.trim(),
                date_of_joining: document.getElementById('f_date_of_joining').value,
                date_of_birth: document.getElementById('f_date_of_birth').value,
                gender: document.getElementById('f_gender').value,
                salary: document.getElementById('f_salary').value,
                commission_type: document.getElementById('f_commission_type').value,
                commission_rate: document.getElementById('f_commission_rate').value,
                post_target_commission_rate: document.getElementById('f_post_target_commission_rate').value,
                status: document.getElementById('f_status').value,
                address: document.getElementById('f_address').value.trim(),
                notes: document.getElementById('f_notes').value.trim(),
                // New Fields
                photo: document.getElementById('f_photo').value.trim(),
                attendance: document.getElementById('f_attendance').value,
                target: document.getElementById('f_target').value,
                daily_work_status: document.getElementById('f_daily_work_status').value.trim(),
                daily_work_history: document.getElementById('f_daily_work_history').value.trim(),
                bank_name: document.getElementById('f_bank_name').value.trim(),
                account_holder_name: document.getElementById('f_account_holder_name').value.trim(),
                account_number: document.getElementById('f_account_number').value.trim(),
                ifsc_code: document.getElementById('f_ifsc_code').value.trim(),
                upi_id: document.getElementById('f_upi_id').value.trim()
            };
            if (id) {
                payload.id = id;
            } else {
                payload.user_password = document.getElementById('f_user_password').value;
                payload.user_role = document.getElementById('f_user_role').value;
            }

            const method = id ? 'PUT' : 'POST';
            const res = await fetch(API, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const result = await res.json();

            btn.disabled = false; btn.innerHTML = '<i class="fas fa-save" style="font-size:0.75rem;"></i> Save Employee';

            if (result.success) {
                closeModal();
                loadStats();
                loadDepartments();
                fetchEmployees(currentPage);
            } else {
                alert('Error: ' + (result.error || 'Something went wrong'));
            }
        });

        async function deleteEmployee(id, name) {
            if (!confirm(`Delete "${name}" ? This cannot be undone.`)) return;
            const res = await fetch(API + '?id=' + id, { method: 'DELETE' });
            const result = await res.json();
            if (result.success) { loadStats(); fetchEmployees(1); }
            else alert('Error: ' + result.error);
        }

        // Rate label updater
        function updateRateLabel() {
            const type = document.getElementById('f_commission_type').value;
            document.getElementById('rateLabel').textContent = type === 'fixed' ? 'Fixed Amount per Win (₹)' : 'Commission Rate (%)';
        }

        // Filters
        let searchTimer;
        document.getElementById('empSearch').addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { currentFilters.search = this.value; fetchEmployees(1); }, 300);
        });
        document.getElementById('filterDept').addEventListener('change', function () {
            currentFilters.department = this.value; fetchEmployees(1);
        });
        document.getElementById('filterStatus').addEventListener('change', function () {
            currentFilters.status = this.value; fetchEmployees(1);
        });

        // Close modal on backdrop click
        document.getElementById('empModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });

        // Upload Employee Photo
        async function uploadEmployeePhoto(input) {
            if (!input.files || !input.files[0]) return;
            const status = document.getElementById('photoUploadStatus');
            status.style.display = 'inline-block';
            status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            const formData = new FormData();
            formData.append('employee_photo', input.files[0]);

            try {
                const res = await fetch(API, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('f_photo').value = data.path;
                    const preview = document.getElementById('photoPreview');
                    preview.src = APP_URL + '/public/' + data.path;
                    preview.style.display = 'block';
                    status.innerHTML = '<i class="fas fa-check" style="color: green;"></i>';
                    setTimeout(() => status.style.display = 'none', 2000);
                } else {
                    alert(data.error || 'Upload failed');
                    status.style.display = 'none';
                    input.value = '';
                }
            } catch (err) {
                alert('Error: ' + err.message);
                status.style.display = 'none';
                input.value = '';
            }
        }

        // Init
        loadStats();
        loadDepartments();
        fetchEmployees();
    </script>
</body>

</html>