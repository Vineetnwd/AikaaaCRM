<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Employee.php';

use Core\Database;
use Core\Auth;
use Core\Employee;

if (!Auth::check()) {
    header("Location: " . APP_URL . "/public/index.php/login");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) die("Employee ID required");

$employeeModel = new Employee();
$employee = $employeeModel->find($id);
if (!$employee) die("Employee not found");

$db = Database::getInstance();
$recentLeads = $db->fetchAll("
    SELECT * FROM leads 
    WHERE assigned_employee_id = ? AND company_id = ? 
    ORDER BY created_at DESC LIMIT 8
", [$id, Auth::companyId()]);

$winRate = $employee['total_leads'] > 0 ? round(($employee['won_leads'] / $employee['total_leads']) * 100, 1) : 0;

// Photo URL helper — matches the same logic used in the employees list
$photoUrl = '';
if (!empty($employee['photo'])) {
    if (strpos($employee['photo'], 'http') === 0) {
        $photoUrl = $employee['photo'];
    } elseif (strpos($employee['photo'], '/') !== false) {
        // stored as a relative path like "uploads/profiles/file.jpg"
        $photoUrl = APP_URL . '/' . $employee['photo'];
    } else {
        // stored as just a filename
        $photoUrl = APP_URL . '/public/uploads/profiles/' . $employee['photo'];
    }
}

function infoRow($label, $value, $icon = '') {
    $v = htmlspecialchars($value ?: '—');
    $ico = $icon ? "<i class=\"fas fa-{$icon}\" style=\"color:var(--primary, #6366f1);width:14px;\"></i> " : '';
    return "<div class=\"info-row\"><div class=\"info-label\">{$label}</div><div class=\"info-value\">{$ico}{$v}</div></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($employee['name']) ?> | Employee Profile</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── Profile Header ── */
        .profile-hero {
            background: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--primary-hover, #4f46e5) 50%, var(--accent-hover, #7c3aed) 100%);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.75rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }
        .profile-hero::after {
            content: '';
            position: absolute;
            bottom: -60px; left: 30%;
            width: 280px; height: 280px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }
        .hero-avatar {
            width: 90px; height: 90px;
            border-radius: 1.25rem;
            background: rgba(255,255,255,0.2);
            border: 3px solid rgba(255,255,255,0.4);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.25rem; font-weight: 900;
            flex-shrink: 0; overflow: hidden;
            backdrop-filter: blur(10px);
            position: relative; z-index: 1;
        }
        .hero-info { position: relative; z-index: 1; flex: 1; }
        .hero-name { font-size: 1.625rem; font-weight: 900; letter-spacing: -0.02em; margin-bottom: 0.375rem; }
        .hero-meta { display: flex; gap: 0.625rem; flex-wrap: wrap; align-items: center; }
        .hero-badge {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.2rem 0.625rem;
            border-radius: 20px;
            font-size: 0.7rem; font-weight: 700;
            backdrop-filter: blur(10px);
        }
        .hero-actions { display: flex; gap: 0.5rem; flex-shrink: 0; position: relative; z-index: 1; }
        .hero-actions .btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white; height: 34px; padding: 0 1rem;
            font-size: 0.75rem; border-radius: 8px;
            display: flex; align-items: center; gap: 0.375rem;
            cursor: pointer; text-decoration: none; font-weight: 600;
            transition: background 0.2s;
        }
        .hero-actions .btn:hover { background: rgba(255,255,255,0.25); }

        /* ── Stat Cards ── */
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-pill {
            background: white;
            border-radius: 0.875rem;
            border: 1px solid var(--border);
            padding: 1rem 1.25rem;
            display: flex; align-items: center; gap: 0.875rem;
        }
        .stat-pill-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .stat-pill-label { font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
        .stat-pill-value { font-size: 1.35rem; font-weight: 900; color: #0f172a; line-height: 1.1; }

        /* ── Info Cards ── */
        .section-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
        }
        .section-title {
            font-size: 0.7rem; font-weight: 800;
            color: #475569; text-transform: uppercase;
            letter-spacing: 0.05em; margin-bottom: 1rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .section-title i { color: var(--primary, #6366f1); }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
        .info-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 1rem; }

        .info-row { padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.15rem; }
        .info-value { font-size: 0.8125rem; font-weight: 700; color: #1e293b; }

        /* ── Main layout ── */
        .profile-layout { display: grid; grid-template-columns: 340px 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }

        /* ── Leads list ── */
        .lead-list { list-style: none; padding: 0; margin: 0; }
        .lead-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.625rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .lead-item:last-child { border-bottom: none; }
        .lead-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .lead-status {
            font-size: 0.6rem; font-weight: 800; text-transform: uppercase;
            padding: 0.15rem 0.5rem; border-radius: 20px;
            white-space: nowrap; flex-shrink: 0;
        }

        /* ── Status Badge ── */
        .emp-status {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.25rem 0.625rem; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700;
        }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .status-on_leave { background: #fef3c7; color: #92400e; }

        /* ── Notes ── */
        .notes-box {
            background: #f8fafc; border-radius: 0.625rem;
            padding: 0.75rem; font-size: 0.8125rem; font-weight: 500;
            color: #334155; line-height: 1.6; min-height: 48px;
            border: 1px solid #e2e8f0; white-space: pre-wrap;
        }

        /* ── Doc items ── */
        .doc-row {
            display: flex; gap: 0.625rem; align-items: center;
            padding: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 0.5rem; margin-bottom: 0.375rem;
        }
        .doc-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.625rem 0.75rem; border: 1px solid #e2e8f0;
            border-radius: 0.625rem; margin-bottom: 0.375rem; background: white;
        }
        .doc-item-icon {
            width: 36px; height: 36px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .doc-item-name { flex: 1; font-size: 0.8125rem; font-weight: 700; color: #1e293b; }
        .doc-item-date { font-size: 0.7rem; color: #94a3b8; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">

        <!-- Header -->
        <header class="header">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <a href="<?= APP_URL ?>/public/index.php/employees" class="btn-ghost" style="padding:0.5rem;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;">
                    <i class="fas fa-arrow-left" style="font-size:0.875rem;"></i>
                </a>
                <div>
                    <h1 class="page-title">Employee Profile</h1>
                    <p style="font-size:0.75rem;color:var(--text-muted);font-weight:500;margin:0;"><?= htmlspecialchars($employee['name']) ?></p>
                </div>
            </div>
            <div class="header-actions">
                <a href="<?= APP_URL ?>/public/index.php/employee_commissions?id=<?= $employee['id'] ?>" class="btn btn-secondary" style="height:36px;font-size:0.8rem;">
                    <i class="fas fa-coins"></i> Commission History
                </a>
            </div>
        </header>

        <!-- Hero Banner -->
        <div class="profile-hero">
            <div class="hero-avatar">
                <?php if ($photoUrl): ?>
                    <img src="<?= $photoUrl ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= strtoupper(substr($employee['name'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="hero-info">
                <div class="hero-name"><?= htmlspecialchars($employee['name']) ?></div>
                <div class="hero-meta">
                    <?php if ($employee['designation']): ?>
                        <span class="hero-badge"><i class="fas fa-briefcase"></i> <?= htmlspecialchars($employee['designation']) ?></span>
                    <?php endif; ?>
                    <?php if ($employee['department']): ?>
                        <span class="hero-badge"><i class="fas fa-building"></i> <?= htmlspecialchars($employee['department']) ?></span>
                    <?php endif; ?>
                    <?php if ($employee['employee_id']): ?>
                        <span class="hero-badge"><i class="fas fa-id-badge"></i> #<?= htmlspecialchars($employee['employee_id']) ?></span>
                    <?php endif; ?>
                    <span class="hero-badge" style="background:<?= $employee['status'] === 'active' ? 'rgba(16,185,129,0.25)' : ($employee['status'] === 'on_leave' ? 'rgba(245,158,11,0.25)' : 'rgba(239,68,68,0.25)') ?>">
                        <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                        <?= ucfirst(str_replace('_', ' ', $employee['status'] ?? 'active')) ?>
                    </span>
                </div>
            </div>
            <div class="hero-actions">
                <?php if ($employee['mobile']): ?>
                    <a href="tel:<?= htmlspecialchars($employee['mobile']) ?>" class="btn" title="Call">
                        <i class="fas fa-phone"></i> Call
                    </a>
                <?php endif; ?>
                <?php if ($employee['email']): ?>
                    <a href="mailto:<?= htmlspecialchars($employee['email']) ?>" class="btn" title="Email">
                        <i class="fas fa-envelope"></i> Email
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Strip -->
        <div class="stat-strip">
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background:#ede9fe;color:var(--accent-hover, #7c3aed);"><i class="fas fa-user-tie"></i></div>
                <div>
                    <div class="stat-pill-label">Total Leads</div>
                    <div class="stat-pill-value"><?= intval($employee['total_leads'] ?? 0) ?></div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background:#d1fae5;color:#059669;"><i class="fas fa-trophy"></i></div>
                <div>
                    <div class="stat-pill-label">Won Leads</div>
                    <div class="stat-pill-value" style="color:#059669;"><?= intval($employee['won_leads'] ?? 0) ?></div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fas fa-percent"></i></div>
                <div>
                    <div class="stat-pill-label">Win Rate</div>
                    <div class="stat-pill-value" style="color:#0284c7;"><?= $winRate ?>%</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-rupee-sign"></i></div>
                <div>
                    <div class="stat-pill-label">Deal Value</div>
                    <div class="stat-pill-value" style="font-size:1rem;">₹<?= number_format($employee['total_deal_value'] ?? 0, 0) ?></div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-pill-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-wallet"></i></div>
                <div>
                    <div class="stat-pill-label">Salary</div>
                    <div class="stat-pill-value" style="font-size:1rem;">₹<?= number_format($employee['salary'] ?? 0, 0) ?></div>
                </div>
            </div>
        </div>

        <!-- Main info sections -->
        <div>

            <!-- LEFT COLUMN -->
            <div>
                <!-- Contact & Personal -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-user-circle"></i> Personal Information</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1.5rem;">
                        <?= infoRow('Email', $employee['email'] ?? '') ?>
                        <?= infoRow('Mobile', $employee['mobile'] ?? '') ?>
                        <?= infoRow('Gender', ucfirst($employee['gender'] ?? '')) ?>
                        <?= infoRow('Date of Birth', !empty($employee['date_of_birth']) ? date('d M Y', strtotime($employee['date_of_birth'])) : '') ?>
                        <?= infoRow('Date of Joining', !empty($employee['date_of_joining']) ? date('d M Y', strtotime($employee['date_of_joining'])) : '') ?>
                        <?= infoRow('Address', $employee['address'] ?? '') ?>
                    </div>
                </div>

                <!-- Work & Performance -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-chart-line"></i> Work & Performance</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1.5rem;">
                        <div class="info-row">
                            <div class="info-label">Attendance</div>
                            <div class="info-value">
                                <?php
                                $att = $employee['attendance'] ?? 'Absent';
                                $attColors = ['Present' => '#059669', 'Absent' => '#dc2626', 'Half Day' => '#d97706', 'On Leave' => 'var(--accent-hover, #7c3aed)', 'Late' => '#f59e0b'];
                                $attBg = ['Present' => '#d1fae5', 'Absent' => '#fee2e2', 'Half Day' => '#fef3c7', 'On Leave' => '#ede9fe', 'Late' => '#fef9c3'];
                                $c = $attColors[$att] ?? '#64748b';
                                $bg = $attBg[$att] ?? '#f1f5f9';
                                ?>
                                <span style="background:<?= $bg ?>;color:<?= $c ?>;padding:0.2rem 0.625rem;border-radius:20px;font-size:0.7rem;font-weight:700;"><?= htmlspecialchars($att) ?></span>
                            </div>
                        </div>
                        <?= infoRow('Monthly Target', !empty($employee['target']) ? '₹' . number_format($employee['target'], 0) : '') ?>
                        <?= infoRow('Daily Work Status', $employee['daily_work_status'] ?? '') ?>
                    </div>
                    <?php if (!empty($employee['daily_work_history'])): ?>
                    <div class="info-row">
                        <div class="info-label">Work History / Notes</div>
                        <div class="notes-box" style="margin-top:0.25rem;"><?= htmlspecialchars($employee['daily_work_history']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Commission -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-hand-holding-usd"></i> Commission Settings</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1.5rem;">
                        <?= infoRow('Commission Type', ucfirst($employee['commission_type'] ?? 'Percentage')) ?>
                        <?= infoRow('Standard Rate', !empty($employee['commission_rate']) ? $employee['commission_rate'] . ($employee['commission_type'] === 'fixed' ? ' ₹' : '%') : '0%') ?>
                        <?= infoRow('Post-Target Rate', !empty($employee['post_target_commission_rate']) ? $employee['post_target_commission_rate'] . '%' : '—') ?>
                        <?= infoRow('Total Earned', '₹' . number_format($employee['total_commission_earned'] ?? 0, 2)) ?>
                    </div>
                </div>

                <!-- Banking -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-university"></i> Banking Information</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 1.5rem;">
                        <?= infoRow('Account Holder', $employee['account_holder_name'] ?? '') ?>
                        <?= infoRow('Bank Name', $employee['bank_name'] ?? '') ?>
                        <?= infoRow('Account Number', $employee['account_number'] ?? '') ?>
                        <?= infoRow('IFSC Code', $employee['ifsc_code'] ?? '') ?>
                        <?= infoRow('UPI ID', $employee['upi_id'] ?? '') ?>
                    </div>
                </div>

                <?php if (!empty($employee['notes'])): ?>
                <!-- Notes -->
                <div class="section-card">
                    <div class="section-title"><i class="fas fa-sticky-note"></i> Additional Notes</div>
                    <div class="notes-box"><?= htmlspecialchars($employee['notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <div class="section-title" style="margin-bottom:0;"><i class="fas fa-folder-open"></i> Documents & Resume</div>
                <button onclick="addDocRow()" class="btn btn-primary" style="height:32px;padding:0 0.875rem;font-size:0.75rem;">
                    <i class="fas fa-plus"></i> Add Document
                </button>
            </div>
            <div id="docUploadRows"></div>
            <div id="docsList" style="margin-top:0.75rem;"></div>
        </div>

        <!-- Recent Leads (Full Width) -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-users"></i> Recent Leads</div>
            <?php if (empty($recentLeads)): ?>
                <div style="text-align:center;padding:2rem;color:#94a3b8;font-size:0.8125rem;">
                    <i class="fas fa-user-slash" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:0.5rem;"></i>
                    No leads assigned yet.
                </div>
            <?php else: ?>
                <ul class="lead-list">
                    <?php
                    $statusColors = [
                        'new'       => ['#3b82f6', '#eff6ff'],
                        'contacted' => ['var(--accent, #8b5cf6)', '#f5f3ff'],
                        'follow_up' => ['#f59e0b', '#fffbeb'],
                        'won'       => ['#10b981', '#d1fae5'],
                        'lost'      => ['#ef4444', '#fee2e2'],
                        'qualified' => ['#06b6d4', '#ecfeff'],
                    ];
                    foreach ($recentLeads as $lead):
                        $st = strtolower($lead['status'] ?? 'new');
                        [$sc, $sbg] = $statusColors[$st] ?? ['#64748b', '#f1f5f9'];
                    ?>
                        <li class="lead-item">
                            <div class="lead-dot" style="background:<?= $sc ?>;"></div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:700;color:#1e293b;font-size:0.8125rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($lead['name']) ?></div>
                                <div style="font-size:0.7rem;color:#94a3b8;"><?= !empty($lead['mobile']) ? htmlspecialchars($lead['mobile']) : '—' ?> &bull; <?= date('d M Y', strtotime($lead['created_at'])) ?></div>
                            </div>
                            <?php if (!empty($lead['deal_value']) && $lead['deal_value'] > 0): ?>
                                <div style="font-size:0.75rem;font-weight:700;color:#059669;">₹<?= number_format($lead['deal_value'], 0) ?></div>
                            <?php endif; ?>
                            <span class="lead-status" style="color:<?= $sc ?>;background:<?= $sbg ?>;"><?= ucfirst(str_replace('_', ' ', $lead['status'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- Image Lightbox -->
<div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:9999;align-items:center;justify-content:center;" onclick="closeLightbox()">
    <img id="lightboxImg" src="" style="max-width:92vw;max-height:92vh;border-radius:10px;box-shadow:0 30px 60px rgba(0,0,0,0.6);">
    <button onclick="closeLightbox()" style="position:absolute;top:1.25rem;right:1.25rem;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:white;width:38px;height:38px;border-radius:50%;font-size:1.25rem;cursor:pointer;backdrop-filter:blur(10px);">&times;</button>
</div>

<script>
    const EMP_ID  = <?= intval($employee['id']) ?>;
    const APP_URL = '<?= APP_URL ?>';
    const DOCS_API = APP_URL + '/public/index.php/api/employee_docs.php';

    function addDocRow() {
        const row = document.createElement('div');
        row.className = 'doc-row';
        row.innerHTML = `
            <input type="text" class="form-input" placeholder="Document name (e.g. Resume, Aadhar, PAN)" style="flex:0 0 220px;height:32px;font-size:0.8125rem;">
            <input type="file" class="form-input" accept="image/*,.pdf" style="flex:1;height:32px;padding:0.2rem;font-size:0.8rem;">
            <button type="button" onclick="uploadDoc(this)" class="btn btn-primary" style="height:32px;padding:0 0.875rem;font-size:0.75rem;white-space:nowrap;">
                <i class="fas fa-upload"></i> Upload
            </button>
            <button type="button" onclick="this.closest('.doc-row').remove()" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1.125rem;padding:0.25rem;line-height:1;">&times;</button>
        `;
        document.getElementById('docUploadRows').appendChild(row);
        row.querySelector('input[type=text]').focus();
    }

    async function uploadDoc(btn) {
        const row = btn.closest('.doc-row');
        const docName = row.querySelector('input[type=text]').value.trim();
        const fileInput = row.querySelector('input[type=file]');
        if (!docName) { alert('Please enter a document name.'); return; }
        if (!fileInput.files || !fileInput.files[0]) { alert('Please select a file.'); return; }

        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const form = new FormData();
        form.append('employee_id', EMP_ID);
        form.append('doc_name', docName);
        form.append('doc_file', fileInput.files[0]);

        try {
            const res = await fetch(DOCS_API, { method: 'POST', body: form });
            const data = await res.json();
            if (data.success) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => { row.remove(); loadDocs(); }, 300);
            } else {
                alert(data.error || 'Upload failed');
                btn.disabled = false; btn.innerHTML = origHtml;
            }
        } catch (err) {
            alert('Error: ' + err.message);
            btn.disabled = false; btn.innerHTML = origHtml;
        }
    }

    async function loadDocs() {
        const container = document.getElementById('docsList');
        container.innerHTML = '<div style="color:#94a3b8;font-size:0.8rem;padding:0.5rem;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        try {
            const res = await fetch(DOCS_API + '?employee_id=' + EMP_ID);
            const docs = await res.json();
            if (!Array.isArray(docs) || docs.length === 0) {
                container.innerHTML = `<div style="text-align:center;padding:1.75rem;color:#94a3b8;font-size:0.8125rem;">
                    <i class="fas fa-file-alt" style="font-size:2.5rem;opacity:0.2;display:block;margin-bottom:0.625rem;"></i>
                    No documents uploaded yet. Click <strong>+ Add Document</strong> to upload one.
                </div>`;
                return;
            }
            container.innerHTML = docs.map(doc => {
                const isPdf = doc.file_type === 'pdf';
                const icon  = isPdf ? 'fa-file-pdf' : 'fa-file-image';
                const iconBg= isPdf ? '#fee2e2' : '#ede9fe';
                const iconCl= isPdf ? '#dc2626' : 'var(--accent-hover, #7c3aed)';
                const fileUrl = APP_URL + '/public/' + doc.file_path;
                const viewAct = isPdf ? `window.open('${fileUrl}', '_blank')` : `openLightbox('${fileUrl}')`;
                return `
                <div class="doc-item">
                    <div class="doc-item-icon" style="background:${iconBg};color:${iconCl};">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="doc-item-name">${escHtml(doc.doc_name)}</div>
                        <div class="doc-item-date">${doc.file_type.toUpperCase()} &bull; ${formatDate(doc.created_at)}</div>
                    </div>
                    <button onclick="${viewAct}" class="btn btn-ghost" style="height:28px;padding:0 0.625rem;font-size:0.7rem;gap:0.25rem;" title="View">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <a href="${fileUrl}" download class="btn btn-ghost" style="height:28px;padding:0 0.625rem;font-size:0.7rem;" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <button onclick="deleteDoc(${doc.id}, this)" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:0.875rem;padding:0.375rem;" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>`;
            }).join('');
        } catch (err) {
            container.innerHTML = '<div style="color:#ef4444;font-size:0.8rem;padding:0.5rem;">Failed to load documents.</div>';
        }
    }

    async function deleteDoc(id, btn) {
        if (!confirm('Delete this document? This cannot be undone.')) return;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const res = await fetch(DOCS_API + '?id=' + id, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) {
                btn.closest('.doc-item').style.opacity = '0';
                btn.closest('.doc-item').style.transition = 'opacity 0.3s';
                setTimeout(loadDocs, 300);
            } else {
                alert(data.error || 'Failed to delete');
                btn.innerHTML = '<i class="fas fa-trash"></i>';
            }
        } catch (err) {
            alert('Error: ' + err.message);
            btn.innerHTML = '<i class="fas fa-trash"></i>';
        }
    }

    function openLightbox(src) {
        const lb = document.getElementById('lightbox');
        document.getElementById('lightboxImg').src = src;
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        document.getElementById('lightbox').style.display = 'none';
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

    function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function formatDate(d) { return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); }

    loadDocs();
</script>
</body>
</html>