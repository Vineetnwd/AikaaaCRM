<?php
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';

use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$userId = Auth::userId();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

$employee = null;
$empDocs = [];
if (!empty($user['employee_id']) || !empty($user['emp_id'])) {
    $empId = $user['employee_id'] ?: $user['emp_id'];
    $employee = $db->fetchOne("SELECT * FROM employees WHERE id = ?", [$empId]);
    // Fetch uploaded documents for this employee
    try {
        $empDocs = $db->fetchAll("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY created_at DESC", [$empId]);
    } catch (\Exception $e) {
        $empDocs = [];
    }
}

$photo = $user['photo'] ?? '';
$profileImg = (!empty($photo))
    ? ($photo[0] === 'h' ? $photo : (strpos($photo, '/') !== false ? APP_URL . '/' . $photo : APP_URL . '/public/uploads/profiles/' . $photo))
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? 'User') . '&background=' . ($hex ?? '6366f1') . '&color=ffffff&bold=true&size=200';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --profile-bg: #f8fafc;
            --card-bg: #ffffff;
            --primary-gradient: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--primary, #4338ca) 100%);
        }

        body {
            background-color: var(--profile-bg);
        }

        .profile-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            padding-bottom: 3rem;
        }

        .profile-banner {
            min-height: 220px;
            background: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--primary-hover, #4f46e5) 40%, var(--accent-hover, #7c3aed) 100%);
            border-radius: 1.5rem;
            position: relative;
            margin-bottom: 5rem;
            box-shadow: 0 20px 40px -10px rgba(99, 102, 241, 0.4);
            /* NO overflow:hidden — allows avatar to hang below */
        }

        /* Inner clipping layer keeps blobs within rounded corners */
        .profile-banner-inner {
            position: absolute;
            inset: 0;
            border-radius: 1.5rem;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        /* Decorative blobs — inside the inner clip layer */
        .profile-banner-inner::before {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
            top: -80px;
            right: -40px;
        }

        .profile-banner-inner::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
            bottom: -60px;
            left: 38%;
        }

        /* Banner inner content (right side) */
        .banner-info {
            position: absolute;
            top: 50%;
            right: 2.5rem;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
            z-index: 2;
        }

        .banner-badge {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .banner-stats {
            display: flex;
            gap: 0.625rem;
            margin-top: 0.25rem;
        }

        .banner-stat {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.625rem;
            padding: 0.375rem 0.75rem;
            text-align: center;
            backdrop-filter: blur(8px);
        }

        .banner-stat-val {
            font-size: 1rem;
            font-weight: 900;
            color: white;
            line-height: 1.1;
        }

        .banner-stat-lbl {
            font-size: 0.6rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .profile-avatar-stack {
            position: absolute;
            bottom: -55px;
            left: 2.5rem;
            z-index: 2;
        }

        /* Name + badges inside the banner, right of avatar */
        .banner-name-area {
            position: absolute;
            bottom: 18px;
            left: 200px;
            /* after the 140px avatar + gap */
            z-index: 2;
        }

        .profile-dp-container {
            width: 140px;
            height: 140px;
            border-radius: 2rem;
            background: white;
            padding: 6px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            position: relative;
            cursor: pointer;
        }

        .profile-dp-container img {
            width: 100%;
            height: 100%;
            border-radius: 1.6rem;
            object-fit: cover;
        }

        .dp-overlay {
            position: absolute;
            inset: 6px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .profile-dp-container:hover .dp-overlay {
            opacity: 1;
        }

        .profile-meta {
            padding-bottom: 0.5rem;
        }

        .profile-meta h2 {
            font-size: 1.625rem;
            font-weight: 900;
            color: white;
            margin-bottom: 0.35rem;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            letter-spacing: -0.02em;
        }

        .profile-meta-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .profile-meta-badges span {
            font-size: 0.7rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.15);
            padding: 3px 10px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Camera upload hint */
        .dp-hint {
            position: absolute;
            bottom: -22px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.6rem;
            font-weight: 700;
            color: #94a3b8;
            white-space: nowrap;
            letter-spacing: 0.03em;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 2rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 1.25rem;
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title i {
            width: 32px;
            height: 32px;
            background: #f1f5f9;
            color: var(--primary, #6366f1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .form-input {
            width: 100%;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.2s;
        }

        .form-input:focus {
            background: white;
            border-color: var(--primary, #6366f1);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .btn-update {
            background: var(--primary, #6366f1);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 800;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-update:hover {
            background: var(--primary-hover, #4f46e5);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .btn-update:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.875rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.8125rem;
            color: #64748b;
            font-weight: 600;
        }

        .info-value {
            font-size: 0.8125rem;
            color: #1e293b;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .profile-avatar-stack {
                left: 50%;
                transform: translateX(-50%);
            }

            .profile-meta {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>

            <div class="profile-wrapper">
                <div class="profile-banner">
                    <!-- Inner clip layer for blobs (keeps rounded corners without clipping avatar) -->
                    <div class="profile-banner-inner"></div>
                    <div class="profile-avatar-stack">
                        <div style="position:relative;">
                            <div class="profile-dp-container" onclick="document.getElementById('dpInput').click()"
                                title="Click to change photo">
                                <img id="profileDisplay" src="<?= $profileImg ?>" alt="Profile">
                                <div class="dp-overlay">
                                    <i class="fas fa-camera fa-xl"></i>
                                </div>
                            </div>
                            <div class="dp-hint"><i class="fas fa-camera" style="font-size:0.55rem;"></i> Change photo
                            </div>
                        </div>
                    </div>

                    <!-- Name + badges anchored INSIDE the banner -->
                    <div class="banner-name-area">
                        <h2
                            style="font-size:1.625rem;font-weight:900;color:white;margin-bottom:0.4rem;text-shadow:0 2px 8px rgba(0,0,0,0.2);letter-spacing:-0.02em;">
                            <?= htmlspecialchars($user['name']) ?></h2>
                        <div class="profile-meta-badges">
                            <span><i class="fas fa-shield-alt"></i>
                                <?= strtoupper(htmlspecialchars($user['role'])) ?></span>
                            <?php if ($employee && !empty($employee['designation'])): ?>
                                <span><i class="fas fa-briefcase"></i>
                                    <?= htmlspecialchars($employee['designation']) ?></span>
                            <?php endif; ?>
                            <?php if ($employee && !empty($employee['department'])): ?>
                                <span><i class="fas fa-building"></i>
                                    <?= htmlspecialchars($employee['department']) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar-alt"></i> Since
                                <?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>

                    <!-- Stats on the right -->
                    <?php if ($employee): ?>
                        <div class="banner-info">
                            <div class="banner-stats">
                                <div class="banner-stat">
                                    <div class="banner-stat-val"><?= intval($employee['total_leads'] ?? 0) ?></div>
                                    <div class="banner-stat-lbl">Leads</div>
                                </div>
                                <div class="banner-stat">
                                    <div class="banner-stat-val"><?php
                                    $wr = $employee['total_leads'] > 0 ? round(($employee['won_leads'] / $employee['total_leads']) * 100) : 0;
                                    echo $wr . '%';
                                    ?></div>
                                    <div class="banner-stat-lbl">Win Rate</div>
                                </div>
                                <div class="banner-stat">
                                    <div class="banner-stat-val" style="font-size:0.875rem;">
                                        ₹<?= number_format($employee['salary'] ?? 0, 0) ?></div>
                                    <div class="banner-stat-lbl">Salary</div>
                                </div>
                            </div>
                            <?php if (!empty($user['email'])): ?>
                                <div class="banner-badge"><i class="fas fa-envelope"></i>
                                    <?= htmlspecialchars($user['email']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($employee['mobile'])): ?>
                                <div class="banner-badge"><i class="fas fa-phone"></i>
                                    <?= htmlspecialchars($employee['mobile']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-grid">
                    <div class="card">
                        <h3 class="card-title">
                            <i class="fas fa-id-card"></i> Profile Overview
                        </h3>

                        <!-- Account Info -->
                        <div class="info-row">
                            <span class="info-label">Account Status</span>
                            <span class="info-value" style="color:#059669;"><?= ucfirst($user['status']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Current Role</span>
                            <span class="info-value"><?= ucfirst(htmlspecialchars($user['role'])) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Member Since</span>
                            <span class="info-value"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                        </div>

                        <?php if ($employee): ?>
                            <!-- Divider -->
                            <div
                                style="margin:1rem 0 0.75rem;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;">
                                <i class="fas fa-user" style="color:var(--primary, #6366f1);"></i> Personal
                            </div>
                            <div class="info-row">
                                <span class="info-label">Mobile</span>
                                <span class="info-value"><?= htmlspecialchars($employee['mobile'] ?: '—') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Designation</span>
                                <span class="info-value"><?= htmlspecialchars($employee['designation'] ?: '—') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Department</span>
                                <span class="info-value"><?= htmlspecialchars($employee['department'] ?: '—') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Gender</span>
                                <span class="info-value"><?= ucfirst($employee['gender'] ?: '—') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Date of Birth</span>
                                <span
                                    class="info-value"><?= !empty($employee['date_of_birth']) ? date('d M Y', strtotime($employee['date_of_birth'])) : '—' ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Date of Joining</span>
                                <span
                                    class="info-value"><?= !empty($employee['date_of_joining']) ? date('d M Y', strtotime($employee['date_of_joining'])) : '—' ?></span>
                            </div>
                            <?php if (!empty($employee['address'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Address</span>
                                    <span class="info-value"
                                        style="text-align:right;max-width:55%;"><?= htmlspecialchars($employee['address']) ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Work & Salary -->
                            <div
                                style="margin:1rem 0 0.75rem;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;">
                                <i class="fas fa-chart-line" style="color:var(--primary, #6366f1);"></i> Work & Salary
                            </div>
                            <div class="info-row">
                                <span class="info-label">Employee ID</span>
                                <span
                                    class="info-value"><?= !empty($employee['employee_id']) ? '#' . htmlspecialchars($employee['employee_id']) : '—' ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Salary</span>
                                <span class="info-value">₹<?= number_format($employee['salary'] ?? 0, 0) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Attendance</span>
                                <span class="info-value">
                                    <?php
                                    $att = $employee['attendance'] ?? 'Absent';
                                    $attColors = ['Present' => '#059669', 'Absent' => '#dc2626', 'Half Day' => '#d97706', 'On Leave' => 'var(--accent-hover, #7c3aed)', 'Late' => '#f59e0b'];
                                    $attBg = ['Present' => '#d1fae5', 'Absent' => '#fee2e2', 'Half Day' => '#fef3c7', 'On Leave' => '#ede9fe', 'Late' => '#fef9c3'];
                                    $ac = $attColors[$att] ?? '#64748b';
                                    $ab = $attBg[$att] ?? '#f1f5f9';
                                    ?>
                                    <span
                                        style="background:<?= $ab ?>;color:<?= $ac ?>;padding:0.15rem 0.5rem;border-radius:20px;font-size:0.7rem;font-weight:700;"><?= htmlspecialchars($att) ?></span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Monthly Target</span>
                                <span class="info-value">₹<?= number_format($employee['target'] ?? 0, 0) ?></span>
                            </div>
                            <?php if (!empty($employee['daily_work_status'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Work Status</span>
                                    <span class="info-value"><?= htmlspecialchars($employee['daily_work_status']) ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Commission -->
                            <div
                                style="margin:1rem 0 0.75rem;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;">
                                <i class="fas fa-hand-holding-usd" style="color:var(--primary, #6366f1);"></i> Commission
                            </div>
                            <div class="info-row">
                                <span class="info-label">Type</span>
                                <span class="info-value"><?= ucfirst($employee['commission_type'] ?? 'Percentage') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Rate</span>
                                <span
                                    class="info-value"><?= ($employee['commission_rate'] ?? 0) . ($employee['commission_type'] === 'fixed' ? ' ₹' : '%') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Earned</span>
                                <span class="info-value"
                                    style="color:#059669;">₹<?= number_format($employee['total_commission_earned'] ?? 0, 2) ?></span>
                            </div>

                            <!-- Banking -->
                            <?php if (!empty($employee['bank_name']) || !empty($employee['upi_id'])): ?>
                                <div
                                    style="margin:1rem 0 0.75rem;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;">
                                    <i class="fas fa-university" style="color:var(--primary, #6366f1);"></i> Banking
                                </div>
                                <?php if (!empty($employee['account_holder_name'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Account Holder</span>
                                        <span class="info-value"><?= htmlspecialchars($employee['account_holder_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($employee['bank_name'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Bank</span>
                                        <span class="info-value"><?= htmlspecialchars($employee['bank_name']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($employee['account_number'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">Account No.</span>
                                        <span class="info-value"><?= htmlspecialchars($employee['account_number']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($employee['ifsc_code'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">IFSC</span>
                                        <span class="info-value"><?= htmlspecialchars($employee['ifsc_code']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($employee['upi_id'])): ?>
                                    <div class="info-row">
                                        <span class="info-label">UPI ID</span>
                                        <span class="info-value"><?= htmlspecialchars($employee['upi_id']) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                        <?php endif; ?>

                        <div class="info-row" style="margin-top:1.5rem;border:none;padding-top:0;">
                            <p style="font-size:0.75rem;color:#94a3b8;line-height:1.5;font-style:italic;">
                                Your profile information is used for internal CRM identity and communications.
                            </p>
                        </div>

                        <form id="dpForm" style="display:none;">
                            <input type="file" id="dpInput" name="photo" accept="image/*" onchange="updateDP()">
                        </form>
                    </div>

                    <div class="card">
                        <h3 class="card-title">
                            <i class="fas fa-user-edit"></i> Edit Account Details
                        </h3>
                        <form id="profileForm">
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>"
                                        required class="form-input">
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                                        required class="form-input">
                                </div>
                            </div>

                            <?php if ($employee): ?>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label>Mobile Number</label>
                                        <input type="text" name="mobile"
                                            value="<?= htmlspecialchars($employee['mobile']) ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label>Designation</label>
                                        <input type="text" name="designation"
                                            value="<?= htmlspecialchars($employee['designation']) ?>" class="form-input">
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label>Department</label>
                                        <input type="text" name="department"
                                            value="<?= htmlspecialchars($employee['department']) ?>" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label>Gender</label>
                                        <select name="gender" class="form-input" style="appearance:auto;">
                                            <option value="">-- Select --</option>
                                            <option value="male" <?= $employee['gender'] === 'male' ? 'selected' : '' ?>>Male
                                            </option>
                                            <option value="female" <?= $employee['gender'] === 'female' ? 'selected' : '' ?>>
                                                Female</option>
                                            <option value="other" <?= $employee['gender'] === 'other' ? 'selected' : '' ?>>
                                                Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                                    <div class="form-group">
                                        <label>Date of Birth</label>
                                        <input type="date" name="date_of_birth"
                                            value="<?= htmlspecialchars($employee['date_of_birth']) ?>" class="form-input">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea name="address" class="form-input"
                                        style="height:60px;resize:none;"><?= htmlspecialchars($employee['address']) ?></textarea>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 1rem;">
                                <button type="submit" class="btn-update" id="saveProfileBtn">
                                    <i class="fas fa-check"></i> Save Changes
                                </button>
                            </div>
                        </form>

                        <div style="margin: 2.5rem 0; height: 1px; background: #f1f5f9; position: relative;">
                            <span
                                style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:0 1rem; color:#cbd5e1; font-size:0.65rem; font-weight:800; text-transform:uppercase;">Security
                                & Access</span>
                        </div>

                        <h3 class="card-title">
                            <i class="fas fa-shield-alt"></i> Change Password
                        </h3>
                        <form id="passwordForm">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required class="form-input"
                                    placeholder="••••••••">
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" required class="form-input"
                                        placeholder="Min 6 chars">
                                </div>
                                <div class="form-group">
                                    <label>Confirm New</label>
                                    <input type="password" name="confirm_password" required class="form-input"
                                        placeholder="Repeat">
                                </div>
                            </div>
                            <div style="margin-top: 1rem;">
                                <button type="submit" class="btn-update" id="savePasswordBtn"
                                    style="background:#0f172a;">
                                    <i class="fas fa-lock"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($employee): ?>

                    <!-- Banking Details Form -->
                    <div class="card" style="margin-top:1.5rem;">
                        <h3 class="card-title" style="margin-bottom:1.25rem;">
                            <i class="fas fa-university"></i> Banking Details
                        </h3>
                        <form id="bankingForm">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                                <div class="form-group">
                                    <label>Account Holder Name</label>
                                    <input type="text" name="account_holder_name" class="form-input"
                                        value="<?= htmlspecialchars($employee['account_holder_name'] ?? '') ?>"
                                        placeholder="Full name as per bank">
                                </div>
                                <div class="form-group">
                                    <label>Bank Name</label>
                                    <input type="text" name="bank_name" class="form-input"
                                        value="<?= htmlspecialchars($employee['bank_name'] ?? '') ?>"
                                        placeholder="e.g. State Bank of India">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                                <div class="form-group">
                                    <label>Account Number</label>
                                    <input type="text" name="account_number" class="form-input"
                                        value="<?= htmlspecialchars($employee['account_number'] ?? '') ?>"
                                        placeholder="Account number">
                                </div>
                                <div class="form-group">
                                    <label>IFSC Code</label>
                                    <input type="text" name="ifsc_code" class="form-input"
                                        value="<?= htmlspecialchars($employee['ifsc_code'] ?? '') ?>"
                                        placeholder="e.g. SBIN0001234">
                                </div>
                                <div class="form-group">
                                    <label>UPI ID</label>
                                    <input type="text" name="upi_id" class="form-input"
                                        value="<?= htmlspecialchars($employee['upi_id'] ?? '') ?>" placeholder="name@upi">
                                </div>
                            </div>
                            <div style="margin-top:0.5rem;">
                                <button type="submit" class="btn-update" id="saveBankingBtn">
                                    <i class="fas fa-check"></i> Save Banking Details
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Documents Section -->
                    <div class="card" style="margin-top:1.5rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                            <h3 class="card-title" style="margin-bottom:0;">
                                <i class="fas fa-folder-open"></i> My Documents
                            </h3>
                            <button onclick="addDocRow()" class="btn-update"
                                style="padding:0.4rem 0.875rem;font-size:0.75rem;gap:0.375rem;">
                                <i class="fas fa-plus"></i> Add Document
                            </button>
                        </div>

                        <!-- Upload rows -->
                        <div id="docUploadRows" style="margin-bottom:1rem;"></div>

                        <!-- Existing docs list -->
                        <div id="myDocsList">
                            <?php if (!empty($empDocs)): ?>
                                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:0.75rem;"
                                    id="docsGrid">
                                    <?php foreach ($empDocs as $doc): ?>
                                        <?php
                                        $isPdf = $doc['file_type'] === 'pdf';
                                        $icon = $isPdf ? 'fa-file-pdf' : 'fa-file-image';
                                        $iconBg = $isPdf ? '#fee2e2' : '#ede9fe';
                                        $iconCl = $isPdf ? '#dc2626' : 'var(--accent-hover, #7c3aed)';
                                        $fileUrl = APP_URL . '/public/' . $doc['file_path'];
                                        ?>
                                        <div id="doc-<?= $doc['id'] ?>"
                                            style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;border:1px solid #e2e8f0;border-radius:0.75rem;background:#f8fafc;">
                                            <div
                                                style="width:38px;height:38px;border-radius:8px;background:<?= $iconBg ?>;color:<?= $iconCl ?>;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                                                <i class="fas <?= $icon ?>"></i>
                                            </div>
                                            <div style="flex:1;min-width:0;">
                                                <div
                                                    style="font-size:0.8125rem;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                    <?= htmlspecialchars($doc['doc_name']) ?></div>
                                                <div style="font-size:0.7rem;color:#94a3b8;"><?= strtoupper($doc['file_type']) ?>
                                                    &bull; <?= date('d M Y', strtotime($doc['created_at'])) ?></div>
                                            </div>
                                            <?php if ($isPdf): ?>
                                                <a href="<?= $fileUrl ?>" target="_blank"
                                                    style="color:var(--primary, #6366f1);font-size:0.85rem;" title="Open PDF"><i
                                                        class="fas fa-external-link-alt"></i></a>
                                            <?php else: ?>
                                                <button onclick="openImgLightbox('<?= htmlspecialchars($fileUrl) ?>')"
                                                    style="background:none;border:none;color:var(--primary, #6366f1);cursor:pointer;font-size:0.85rem;"
                                                    title="View"><i class="fas fa-eye"></i></button>
                                            <?php endif; ?>
                                            <a href="<?= $fileUrl ?>" download style="color:#64748b;font-size:0.85rem;"
                                                title="Download"><i class="fas fa-download"></i></a>
                                            <?php if (!Auth::isExecutive()): ?>
                                                <button onclick="deleteDoc(<?= $doc['id'] ?>)"
                                                    style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:0.85rem;"
                                                    title="Delete"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div id="docsEmpty" style="text-align:center;padding:2rem;color:#94a3b8;font-size:0.875rem;">
                                    <i class="fas fa-folder-open"
                                        style="font-size:2rem;margin-bottom:0.5rem;display:block;"></i>
                                    No documents uploaded yet. Click <strong>Add Document</strong> to upload.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Image Lightbox -->
                    <div id="imgLightbox" onclick="this.style.display='none'"
                        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:none;align-items:center;justify-content:center;cursor:zoom-out;">
                        <img id="lightboxImg" src=""
                            style="max-width:90%;max-height:90%;border-radius:0.75rem;box-shadow:0 25px 50px rgba(0,0,0,0.5);">
                    </div>

                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        async function updateDP() {
            const input = document.getElementById('dpInput');
            if (!input.files || !input.files[0]) return;

            const formData = new FormData();
            formData.append('photo', input.files[0]);

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/profile.php?action=update_dp', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();

                if (result.success) {
                    document.getElementById('profileDisplay').src = result.image_url;
                    const topAvatars = document.querySelectorAll('.avatar-img');
                    topAvatars.forEach(img => img.src = result.image_url);
                    alert('Profile picture updated successfully');
                } else {
                    alert(result.error);
                }
            } catch (err) {
                alert('Failed to upload image: ' + err.message);
            }
        }

        document.getElementById('profileForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('saveProfileBtn');
            const originalText = btn.innerHTML;

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/profile.php?action=update_profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    alert('Profile updated successfully');
                    window.location.reload();
                } else {
                    alert(result.error);
                }
            } catch (err) {
                alert('An error occurred: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });

        document.getElementById('passwordForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('savePasswordBtn');
            const originalText = btn.innerHTML;

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            if (data.new_password.length < 6) {
                alert('New password must be at least 6 characters long');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/profile.php?action=update_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    alert('Password updated successfully');
                    this.reset();
                } else {
                    alert(result.error);
                }
            } catch (err) {
                alert('An error occurred: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
        <?php if ($employee): ?>
            // Banking Form
            const bankingForm = document.getElementById('bankingForm');
            if (bankingForm) {
                bankingForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const btn = document.getElementById('saveBankingBtn');
                    const originalText = btn.innerHTML;

                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData.entries());

                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                    try {
                        const res = await fetch('<?= APP_URL ?>/public/index.php/api/profile.php?action=update_banking', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        });
                        const result = await res.json();

                        if (result.success) {
                            alert('Banking details updated successfully');
                        } else {
                            alert(result.error || 'Failed to update banking details');
                        }
                    } catch (err) {
                        alert('An error occurred: ' + err.message);
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                });
            }

            // Documents
            let docRowCount = 0;
            const employeeId = <?= $employee['id'] ?>;

            function addDocRow() {
                docRowCount++;
                const rowId = `docRow-${docRowCount}`;
                const rowHtml = `
                <div id="${rowId}" style="display:flex;gap:1rem;align-items:flex-end;background:white;padding:1rem;border-radius:0.75rem;border:1px solid #e2e8f0;margin-bottom:0.75rem;">
                    <div style="flex:1;">
                        <label style="display:block;font-size:0.7rem;font-weight:700;color:#64748b;margin-bottom:0.4rem;text-transform:uppercase;">Document Name</label>
                        <input type="text" id="docName-${docRowCount}" placeholder="e.g. Aadhar Card" class="form-input" style="padding:0.6rem;font-size:0.8rem;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:0.7rem;font-weight:700;color:#64748b;margin-bottom:0.4rem;text-transform:uppercase;">File (Img/PDF)</label>
                        <input type="file" id="docFile-${docRowCount}" accept="image/jpeg,image/png,image/webp,application/pdf" class="form-input" style="padding:0.5rem;font-size:0.8rem;">
                    </div>
                    <div style="display:flex;gap:0.5rem;">
                        <button onclick="uploadDoc(${docRowCount})" class="btn-update" style="padding:0.6rem 1rem;font-size:0.8rem;background:#10b981;" id="uploadBtn-${docRowCount}">Upload</button>
                        <button onclick="document.getElementById('${rowId}').remove()" class="btn-update" style="padding:0.6rem;font-size:0.8rem;background:#f1f5f9;color:#ef4444;border:1px solid #e2e8f0;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
                document.getElementById('docUploadRows').insertAdjacentHTML('beforeend', rowHtml);
            }

            async function uploadDoc(rowNum) {
                const nameInput = document.getElementById(`docName-${rowNum}`);
                const fileInput = document.getElementById(`docFile-${rowNum}`);
                const btn = document.getElementById(`uploadBtn-${rowNum}`);

                const docName = nameInput.value.trim();
                if (!docName) return alert('Please enter document name');
                if (!fileInput.files || !fileInput.files[0]) return alert('Please select a file');

                const file = fileInput.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                if (!['jpg', 'jpeg', 'png', 'webp', 'pdf'].includes(ext)) {
                    return alert('Only JPG, PNG, WEBP, and PDF allowed.');
                }

                const formData = new FormData();
                formData.append('employee_id', employeeId);
                formData.append('doc_name', docName);
                formData.append('doc_file', file);

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const res = await fetch('<?= APP_URL ?>/public/index.php/api/employee_docs.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();

                    if (result.success) {
                        window.location.reload(); // simple reload to show new doc
                    } else {
                        alert(result.error || 'Upload failed');
                        btn.disabled = false;
                        btn.innerHTML = 'Upload';
                    }
                } catch (err) {
                    alert('Error: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Upload';
                }
            }

            async function deleteDoc(docId) {
                if (!confirm('Are you sure you want to delete this document?')) return;

                try {
                    const res = await fetch(`<?= APP_URL ?>/public/index.php/api/employee_docs.php?id=${docId}`, {
                        method: 'DELETE'
                    });
                    const result = await res.json();

                    if (result.success) {
                        document.getElementById(`doc-${docId}`).remove();
                        // If no docs left, reload to show empty state
                        if (document.querySelectorAll('[id^="doc-"]').length === 0) {
                            window.location.reload();
                        }
                    } else {
                        alert(result.error || 'Failed to delete');
                    }
                } catch (err) {
                    alert('Error: ' + err.message);
                }
            }

            function openImgLightbox(url) {
                const lb = document.getElementById('imgLightbox');
                const img = document.getElementById('lightboxImg');
                img.src = url;
                lb.style.display = 'flex';
            }
        <?php endif; ?>
    </script>
</body>

</html>