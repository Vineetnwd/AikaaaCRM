$current_path = basename($_SERVER['REQUEST_URI']);
if ($current_path == 'public' || $current_path == 'index.php' || $current_path == '') {
    $current_path = 'dashboard';
}
$isExecutive = \Core\Auth::isExecutive();

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
    $logo = ($company && $company['logo_path']) ? APP_URL . '/public/' . $company['logo_path'] : '';
    $company_name = $company['name'] ?? 'Aikaa CRM';
    ?>
    <div
        style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; padding: 0 0.5rem;">
        <a href="<?= APP_URL ?>/public/index.php/dashboard" class="sidebar-logo"
            style="margin: 0; padding: 0; border: none; background: none; display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: white;">
            <?php if ($logo): ?>
                <img src="<?= $logo ?>" style="height: 32px; width: 32px; min-width: 32px; object-fit: contain; border-radius: 4px;">
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

    <nav class="nav-menu">
        <!-- MAIN SECTION -->
        <div class="nav-section-title">Main</div>
        <?php if (!$isExecutive): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/dashboard"
                    class="nav-link <?= $path == 'dashboard' || $path == '' ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i> <span>Dashboard</span>
                </a>
            </div>
        <?php endif; ?>
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

        <!-- SERVICES SECTION -->
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

        <!-- FINANCE SECTION -->
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

        <!-- ANALYTICS SECTION -->
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
        <?php if (!$isExecutive): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/lead_report"
                    class="nav-link <?= $path == 'lead_report' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> <span>Lead Report</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- SYSTEM SECTION -->
        <div class="nav-section-title">System</div>
        <?php if (\Core\Auth::isAdmin() || \Core\Auth::isSuperAdmin()): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/users" class="nav-link <?= $path == 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> <span>Manage Users</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if (\Core\Auth::isSuperAdmin()): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/companies" class="nav-link <?= $path == 'companies' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i> <span>Manage Companies</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if (!\Core\Auth::isExecutive()): ?>
            <div class="nav-item">
                <a href="<?= APP_URL ?>/public/index.php/employees"
                    class="nav-link <?= $path == 'employees' ? 'active' : '' ?>">
                    <i class="fas fa-id-badge"></i> <span>Employees</span>
                </a>
            </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?= APP_URL ?>/public/index.php/settings"
                class="nav-link <?= $path == 'settings' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i> <span>Settings</span>
            </a>
        </div>
    </nav>
</aside>

<script>
    // Final backup script to ensure class is applied after full DOM load
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('mainSidebar');
        if (sidebar && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');