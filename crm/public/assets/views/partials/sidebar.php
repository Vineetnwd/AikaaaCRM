<?php
$current_path = basename($_SERVER['REQUEST_URI']);
if ($current_path == 'public' || $current_path == 'index.php' || $current_path == '') {
    $current_path = 'dashboard';
}
$isExecutive = \Core\Auth::isExecutive();
$isRealSuperAdmin = \Core\Auth::isSuperAdmin() && !isset($_SESSION['original_user']);

// Determine which path is active
$path = $path ?? '';
?>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
    }

    // Apply initial state immediately to avoid flicker
    (function () {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.write('<style>#mainSidebar.collapsed { width: 80px !important; padding: 1.5rem 0.75rem !important; } #mainSidebar.collapsed .sidebar-logo span, #mainSidebar.collapsed .nav-link span, #mainSidebar.collapsed .nav-section-title { display: none !important; } #mainSidebar.collapsed .sidebar-logo, #mainSidebar.collapsed .nav-link { justify-content: center !important; }</style>');
        }
    })();
</script>

<aside class="sidebar" id="mainSidebar">
    <?php
    $company = \Core\Auth::company();
    $logo = ($company && !empty($company['logo_path'])) ? APP_URL . '/public/' . $company['logo_path'] : '';
    $company_name = $company['name'] ?? '';

    try {
        $dbConn = \Core\Database::getInstance()->getConnection();
        if (!$logo) {
            $logo = $dbConn->query("SELECT setting_value FROM website_settings WHERE setting_key='logo_url'")->fetchColumn();
        }
        if (!$company_name) {
            $company_name = $dbConn->query("SELECT setting_value FROM website_settings WHERE setting_key='site_name'")->fetchColumn();
        }
        $favicon_url = $dbConn->query("SELECT setting_value FROM website_settings WHERE setting_key='favicon_url'")->fetchColumn();
        $theme_color = $dbConn->query("SELECT setting_value FROM website_settings WHERE setting_key='theme_color_hex'")->fetchColumn();
        $theme_secondary = $dbConn->query("SELECT setting_value FROM website_settings WHERE setting_key='theme_color_secondary_hex'")->fetchColumn();
    } catch (Exception $e) {
        $favicon_url = '';
        $theme_color = '';
        $theme_secondary = '';
    }

    $dynamic_css = "";
    if ($theme_color) {
        $hex = ltrim($theme_color, '#');
        $r = hexdec(strlen($hex) == 3 ? substr($hex,0,1).substr($hex,0,1) : substr($hex,0,2));
        $g = hexdec(strlen($hex) == 3 ? substr($hex,1,1).substr($hex,1,1) : substr($hex,2,2));
        $b = hexdec(strlen($hex) == 3 ? substr($hex,2,1).substr($hex,2,1) : substr($hex,4,2));
        $dynamic_css .= "
                --primary: {$theme_color} !important;
                --primary-hover: rgba({$r}, {$g}, {$b}, 0.9) !important;
                --primary-soft: rgba({$r}, {$g}, {$b}, 0.1) !important;";
    }
    if ($theme_secondary) {
        $hex2 = ltrim($theme_secondary, '#');
        $r2 = hexdec(strlen($hex2) == 3 ? substr($hex2,0,1).substr($hex2,0,1) : substr($hex2,0,2));
        $g2 = hexdec(strlen($hex2) == 3 ? substr($hex2,1,1).substr($hex2,1,1) : substr($hex2,2,2));
        $b2 = hexdec(strlen($hex2) == 3 ? substr($hex2,2,1).substr($hex2,2,1) : substr($hex2,4,2));
        $dynamic_css .= "
                --accent: {$theme_secondary} !important;
                --accent-hover: rgba({$r2}, {$g2}, {$b2}, 0.9) !important;
                --accent-soft: rgba({$r2}, {$g2}, {$b2}, 0.1) !important;";
    }

    if ($dynamic_css) {
        echo "<style>:root { {$dynamic_css} }</style>";
    }

    if (!$company_name) {
        $company_name = 'Aikaa CRM';
    }
    ?>
    <?php if (!empty($favicon_url)): ?>
        <script>
            (function () {
                var link = document.querySelector("link[rel*='icon']") || document.createElement('link');
                link.type = 'image/x-icon';
                link.rel = 'shortcut icon';
                link.href = '<?= htmlspecialchars($favicon_url) ?>';
                document.getElementsByTagName('head')[0].appendChild(link);
            })();
        </script>
    <?php endif; ?>
    <div
        style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; padding: 0 0.5rem;">
        <a href="<?= APP_URL ?>/public/index.php/dashboard" class="sidebar-logo"
            style="margin: 0; padding: 0; border: none; background: none; display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: white;">
            <?php if ($logo): ?>
                <img src="<?= $logo ?>"
                    style="height: 32px; width: 32px; min-width: 32px; object-fit: contain; border-radius: 4px;">
            <?php else: ?>
                <i class="fas fa-rocket" style="font-size: 1.5rem; color: var(--primary);"></i>
            <?php endif; ?>
            <span style="font-weight: 800; font-size: 1.125rem; letter-spacing: -0.02em;"><?= $company_name ?></span>
        </a>
        <button onclick="toggleSidebar()" class="sidebar-toggle-btn"
            style="color: rgba(255,255,255,0.5); background: rgba(255,255,255,0.05); width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
            <i class="fas fa-bars-staggered"></i>
        </button>
    </div>

    <?php if (isset($_SESSION['original_user'])): ?>
        <div
            style="margin: 0 1rem 1.5rem 1rem; padding: 0.75rem; background: rgba(255, 255, 255, 0.1); border-radius: 0.5rem; text-align: center; border: 1px dashed rgba(255,255,255,0.2);">
            <div
                style="font-size: 0.7rem; font-weight: 700; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                Impersonating</div>
            <div
                style="font-size: 0.85rem; font-weight: 700; color: white; margin-bottom: 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($company_name) ?>
            </div>
            <a href="<?= APP_URL ?>/public/index.php/revert_impersonation" class="btn btn-primary"
                style="width: 100%; padding: 0.35rem; font-size: 0.75rem; background: white; color: var(--primary);">
                <i class="fas fa-sign-out-alt"></i> Exit
            </a>
        </div>
    <?php endif; ?>

    <nav class="nav-menu">
        <!-- MAIN SECTION -->
        <div class="nav-section-title">Main</div>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/public/index.php/dashboard"
                class="nav-link <?= $path == 'dashboard' || $path == '' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> <span>Dashboard</span>
            </a>
        </div>
        <?php if (!$isRealSuperAdmin): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/leads" class="nav-link <?= $path == 'leads' ? 'active' : '' ?>">
                    <i class="fas fa-user-plus"></i> <span>Leads</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/customers"
                    class="nav-link <?= $path == 'customers' ? 'active' : '' ?>">
                    <i class="fas fa-address-book"></i> <span>Customers</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/tasks" class="nav-link <?= $path == 'tasks' ? 'active' : '' ?>">
                    <i class="fas fa-list-check"></i> <span>Tasks</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- SERVICES SECTION -->
        <?php if (!$isRealSuperAdmin): ?>
            <div class="nav-section-title">Services</div>
            <?php if (!$isExecutive): ?>
                <div class="nav-item">
                    <a href="<?= APP_URL ?>/public/index.php/requirements"
                        class="nav-link <?= $path == 'requirements' ? 'active' : '' ?>">
                        <i class="fas fa-concierge-bell"></i> <span>Requirements</span>
                    </a>
                </div>
            <?php endif; ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/service_search"
                    class="nav-link <?= $path == 'service_search' ? 'active' : '' ?>">
                    <i class="fas fa-search"></i> <span>Service Search</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- FINANCE SECTION -->
        <?php if (!$isRealSuperAdmin): ?>
            <div class="nav-section-title">Finance</div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/quotations"
                    class="nav-link <?= $path == 'quotations' ? 'active' : '' ?>">
                    <i class="fas fa-file-contract"></i> <span>Quotations</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/invoices"
                    class="nav-link <?= $path == 'invoices' ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i> <span>Invoices</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/dues_report"
                    class="nav-link <?= $path == 'dues_report' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> <span>Dues Report</span>
                </a>
            </div>
            <?php if (!$isExecutive): ?>
                <div class="nav-item">
                    <a href="<?= APP_URL ?>/public/index.php/commissions"
                        class="nav-link <?= $path == 'commissions' ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-usd"></i> <span>Commissions</span>
                    </a>
                </div>
            <?php endif; ?>
            <?php if ($isExecutive): ?>
                <div class="nav-item">
                    <a href="<?= APP_URL ?>/public/index.php/employee_commissions?id=<?= (int) (\Core\Auth::employeeId() ?: 0) ?>"
                        class="nav-link <?= $path == 'employee_commissions' ? 'active' : '' ?>">
                        <i class="fas fa-coins"></i> <span>My Earnings</span>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ANALYTICS SECTION -->
        <?php if (!$isRealSuperAdmin): ?>
            <div class="nav-section-title">Reports</div>
            <?php if (!$isExecutive): ?>
                <div class="nav-item">
                    <a href="<?= APP_URL ?>/public/index.php/reports"
                        class="nav-link <?= $path == 'reports' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> <span>Analytics</span>
                    </a>
                </div>
            <?php endif; ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/performance"
                    class="nav-link <?= $path == 'performance' ? 'active' : '' ?>">
                    <i class="fas fa-bolt"></i> <span>Performance</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/attendance_report"
                    class="nav-link <?= $path == 'attendance_report' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> <span>Attendance Report</span>
                </a>
            </div>
            <?php if (!$isExecutive): ?>
                <div class="nav-item">
                    <a href="<?= APP_URL ?>/public/index.php/lead_report"
                        class="nav-link <?= $path == 'lead_report' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i> <span>Lead Report</span>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SYSTEM SECTION -->
        <div class="nav-section-title">System</div>
        <?php if (!$isRealSuperAdmin && (\Core\Auth::isAdmin() || \Core\Auth::isSuperAdmin())): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/users" class="nav-link <?= $path == 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> <span>Manage Users</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if (\Core\Auth::isSuperAdmin()): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/companies"
                    class="nav-link <?= $path == 'companies' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i> <span>Manage Companies</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/website_settings"
                    class="nav-link <?= $path == 'website_settings' ? 'active' : '' ?>">
                    <i class="fas fa-globe"></i> <span>Website Settings</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/plan_management"
                    class="nav-link <?= $path == 'plan_management' ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i> <span>Plan Management</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/enquiries"
                    class="nav-link <?= $path == 'enquiries' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> <span>Enquiries</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if (!$isRealSuperAdmin && !\Core\Auth::isExecutive()): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/employees"
                    class="nav-link <?= $path == 'employees' ? 'active' : '' ?>">
                    <i class="fas fa-id-badge"></i> <span>Employees</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if (!$isRealSuperAdmin): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/settings"
                    class="nav-link <?= $path == 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> <span>Settings</span>
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <!-- Logout Button at absolute bottom -->
    <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
        <div class="nav-item">
            <a href="<?= APP_URL ?>/public/index.php/logout" class="nav-link" style="color: #f87171 !important;">
                <i class="fas fa-sign-out-alt" style="color: #f87171 !important;"></i> <span>Logout</span>
            </a>
        </div>
    </div>
</aside>

<script>
    // Final backup script to ensure class is applied after full DOM load
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('mainSidebar');
        if (sidebar && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    });
</script>