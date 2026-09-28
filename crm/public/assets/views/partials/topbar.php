<?php
$user = \Core\Auth::user();
$raw_hex = isset($hex) && !empty($hex) ? $hex : '6366f1';
$dp = (!empty($user['photo']))
    ? (strpos($user['photo'], 'http') === 0 ? $user['photo'] : (strpos($user['photo'], '/') !== false ? APP_URL . '/' . $user['photo'] : APP_URL . '/public/uploads/profiles/' . $user['photo']))
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? 'User') . '&background=' . $raw_hex . '&color=ffffff&bold=true';
?>
<style>
    .topbar {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        height: 72px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 2rem;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 1000;
        margin: -1.25rem -1.75rem 2rem -1.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    }

    .topbar-left {
        display: flex;
        align-items: center;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .notification-btn {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        cursor: pointer;
        position: relative;
        transition: all 0.2s;
    }

    .notification-btn:hover {
        background: white;
        color: var(--primary, var(--primary, #6366f1));
        border-color: var(--primary, var(--primary, #6366f1));
        transform: translateY(-1px);
    }

    .topbar-user {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 0.875rem;
        padding: 0.5rem 0.75rem;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        background: transparent;
    }

    .topbar-user:hover {
        background: white;
        border-color: #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .user-info-text {
        text-align: right;
        line-height: 1.3;
    }

    .user-name {
        font-size: 0.875rem;
        font-weight: 800;
        color: #1e293b;
        display: block;
    }

    .user-role {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--primary, var(--primary, #6366f1));
        text-transform: uppercase;
        letter-spacing: 0.025em;
        display: block;
    }

    .avatar-wrapper {
        position: relative;
        width: 42px;
        height: 42px;
        flex-shrink: 0;
    }

    .avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .status-indicator {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 12px;
        height: 12px;
        background: #10b981;
        border: 2.5px solid white;
        border-radius: 50%;
    }

    .user-dropdown-menu {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 240px;
        background: white;
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        overflow: hidden;
    }

    .attendance-btn {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.5rem 1rem;
        border-radius: 12px;
        font-size: 0.8125rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .attendance-btn.checked-out {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .attendance-btn.checked-in {
        background: #d1fae5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .attendance-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
</style>

<?php if (isset($_SESSION['original_user'])): ?>
    <div
        style="background: #1e293b; color: white; padding: 12px 2rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem; font-weight: 700; margin: -1.25rem -1.75rem 0.5rem -1.75rem; border-bottom: 1px solid rgba(255,255,255,0.1); position: sticky; top: 0; z-index: 1001;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-user-shield" style="color: var(--primary, var(--primary, #6366f1));"></i>
            <span>You are currently logged in as <span
                    style="color: var(--primary, var(--primary, #6366f1));"><?= htmlspecialchars($user['name']) ?></span></span>
        </div>
        <button onclick="switchBack()"
            style="background: var(--primary, var(--primary, #6366f1)); color: white; border: none; padding: 4px 12px; border-radius: 6px; cursor: pointer; font-weight: 800; font-size: 0.75rem; transition: all 0.2s;">
            <i class="fas fa-undo-alt"></i> Switch Back
        </button>
    </div>
    <script>
        async function switchBack() {
            if (!confirm("Switch back to your original account?")) return;
            const r = await fetch('<?= APP_URL ?>/api/users.php?action=switch_back');
            const res = await r.json();
            if (res.success) window.location.href = '<?= APP_URL ?>/public/index.php/dashboard';
            else alert(res.error || "Failed to switch back");
        }
    </script>
<?php endif; ?>

<div class="topbar" <?= isset($_SESSION['original_user']) ? 'style="margin-top: 0; top: 41px;"' : '' ?>>
    <div class="topbar-left">
        <div style="font-size: 0.875rem; color: #64748b; font-weight: 600;">
            <span id="greeting">Welcome back,</span> <span
                style="color: #1e293b; font-weight: 800;"><?= htmlspecialchars($user['name'] ?? 'User') ?>!</span>
        </div>
    </div>

    <div class="topbar-right">
        <?php if (\Core\Auth::isExecutive()): ?>
            <div id="attendanceTimer"
                style="font-family: monospace; font-size: 0.875rem; font-weight: 800; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 8px; margin-right: -0.5rem;">
                00:00:00</div>
            <button id="attendanceBtn" class="attendance-btn checked-out">
                <i class="fas fa-sign-in-alt"></i> <span>Check In</span>
            </button>
        <?php endif; ?>

        <!-- <div class="notification-btn">
            <i class="far fa-bell"></i>
            <span
                style="position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: #ef4444; color: white; border-radius: 50%; font-size: 0.65rem; font-weight: 800; display: flex; align-items: center; justify-content: center; border: 2px solid white;">3</span>
        </div> -->

        <div style="position: relative;">
            <div class="topbar-user" id="topbarUserBtn">
                <div class="user-info-text">
                    <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></span>
                    <span class="user-role"><?= htmlspecialchars($user['role'] ?? 'Staff') ?></span>
                </div>
                <div class="avatar-wrapper">
                    <img src="<?= $dp ?>" class="avatar-img">
                    <div class="status-indicator"></div>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 0.7rem; color: #94a3b8;"></i>
            </div>

            <div class="user-dropdown-menu" id="topbarDropdown">
                <div
                    style="padding: 1.25rem; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.75rem;">
                    <img src="<?= $dp ?>" style="width: 44px; height: 44px; border-radius: 12px; object-fit: cover;">
                    <div>
                        <div style="font-size: 0.9375rem; font-weight: 800; color: #0f172a;">
                            <?= htmlspecialchars($user['name'] ?? 'User') ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                            <?= htmlspecialchars($user['email'] ?? '') ?>
                        </div>
                    </div>
                </div>
                <div style="padding: 0.5rem;">
                    <a href="<?= APP_URL ?>/public/index.php/profile"
                        style="display: flex; align-items: center; gap: 0.875rem; padding: 0.875rem 1.25rem; color: #475569; text-decoration: none; font-size: 0.875rem; font-weight: 600; transition: all 0.2s;">
                        <i class="fas fa-user-circle" style="font-size: 1.1rem; color: var(--primary, var(--primary, #6366f1)); width: 20px;"></i> My
                        Profile
                    </a>
                    <a href="<?= APP_URL ?>/public/index.php/settings"
                        style="display: flex; align-items: center; gap: 0.875rem; padding: 0.875rem 1.25rem; color: #475569; text-decoration: none; font-size: 0.875rem; font-weight: 600; transition: all 0.2s;">
                        <i class="fas fa-cog" style="font-size: 1.1rem; color: #64748b; width: 20px;"></i> Settings
                    </a>
                    <div style="height: 1px; background: #f1f5f9; margin: 0.5rem 0.75rem;"></div>
                    <a href="<?= APP_URL ?>/public/index.php/logout"
                        style="display: flex; align-items: center; gap: 0.875rem; padding: 0.875rem 1.25rem; color: #ef4444; text-decoration: none; font-size: 0.875rem; font-weight: 700; transition: all 0.2s;">
                        <i class="fas fa-sign-out-alt" style="font-size: 1.1rem; width: 20px;"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('topbarUserBtn');
        const menu = document.getElementById('topbarDropdown');

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('click', function () {
            menu.style.display = 'none';
        });

        menu.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        const hour = new Date().getHours();
        let greeting = "Good morning,";
        if (hour >= 12 && hour < 17) greeting = "Good afternoon,";
        else if (hour >= 17) greeting = "Good evening,";
        document.getElementById('greeting').textContent = greeting;

        // Attendance Logic
        <?php if (\Core\Auth::isExecutive()): ?>
            const attendanceBtn = document.getElementById('attendanceBtn');
            const attendanceTimer = document.getElementById('attendanceTimer');

            async function updateAttendanceStatus() {
                try {
                    const res = await fetch('<?= APP_URL ?>/public/index.php/api/attendance.php?action=status');
                    const data = await res.json();

                    if (data.checked_in) {
                        attendanceBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> <span>Check Out</span>';
                        attendanceBtn.className = 'attendance-btn checked-in';

                        if (data.log && data.log.check_in) {
                            const checkInTime = new Date(data.log.check_in.replace(/-/g, "/"));
                            setInterval(() => {
                                const now = new Date();
                                const diff = Math.floor((now - checkInTime) / 1000);
                                const h = Math.floor(diff / 3600);
                                const m = Math.floor((diff % 3600) / 60);
                                const s = diff % 60;
                                attendanceTimer.textContent = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                            }, 1000);
                        }
                    } else if (data.log && data.log.check_out) {
                        attendanceBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> <span>Checked Out</span>';
                        attendanceBtn.className = 'attendance-btn checked-out';
                        attendanceBtn.style.opacity = '0.7';
                        attendanceBtn.disabled = true;
                        const outTime = new Date(data.log.check_out.replace(/-/g, "/")).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
                        attendanceTimer.textContent = 'Out: ' + outTime;
                    } else {
                        attendanceBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> <span>Check In</span>';
                        attendanceBtn.className = 'attendance-btn checked-out';
                        attendanceTimer.textContent = '00:00:00';
                    }
                } catch (err) {
                    console.error('Attendance status error:', err);
                }
            }

            attendanceBtn.addEventListener('click', async function () {
                const isCheckedIn = attendanceBtn.classList.contains('checked-in');
                const action = isCheckedIn ? 'check_out' : 'check_in';
                const confirmMsg = isCheckedIn ? "Are you sure you want to Check Out?" : "Do you want to Check In now?";

                if (!confirm(confirmMsg)) return;

                attendanceBtn.disabled = true;
                try {
                    const res = await fetch(`<?= APP_URL ?>/public/index.php/api/attendance.php?action=${action}`);
                    const data = await res.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || "Attendance action failed");
                    }
                } catch (err) {
                    alert("An error occurred");
                } finally {
                    attendanceBtn.disabled = false;
                }
            });

            updateAttendanceStatus();
        <?php endif; ?>
    });
</script>