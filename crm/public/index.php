<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Autoloading classes
spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    $base_dir = __DIR__ . '/../core/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0)
        return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file))
        require $file;
});

use Core\Auth;
use Core\Database;

// Robust Dynamic Routing
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = $_SERVER['SCRIPT_NAME'];

// Clean segments by removing empty ones caused by redundant slashes
$request_parts = array_values(array_filter(explode('/', $request_uri), 'strlen'));
$script_parts = array_values(array_filter(explode('/', $script_name), 'strlen'));

$path_parts = [];
$i = 0;
// Skip identical parts at the beginning (the base directory prefix)
while ($i < count($request_parts) && $i < count($script_parts) && $request_parts[$i] === $script_parts[$i]) {
    $i++;
}

// Any remaining parts in the request URI are the actual path
for (; $i < count($request_parts); $i++) {
    // Also skip 'index.php' if it appears in the request URI
    if ($request_parts[$i] !== 'index.php') {
        $path_parts[] = $request_parts[$i];
    }
}

$path = implode('/', $path_parts);
$path = trim($path, '/');

// Authentication Middleware
if ($path !== 'login' && $path !== 'register' && $path !== 'forgot_password' && $path !== 'quotation/print' && $path !== 'invoice/print' && $path !== 'invoice/receipt' && strpos($path, 'api/') === false && !Auth::check()) {
    header('Location: ' . APP_URL . '/public/index.php/login');
    exit;
}

// Global Subscription Expiration Check for authenticated normal users
if (Auth::check() && !Auth::isSuperAdmin()) {
    $company = Auth::company();
    if ($company) {
        $today = date('Y-m-d');
        $expired = false;
        
        if ($company['plan'] === 'trial') {
            if (!empty($company['trial_ends_at']) && $company['trial_ends_at'] < $today) {
                $expired = true;
            }
        } else {
            if (!empty($company['subscription_ends_at']) && $company['subscription_ends_at'] < $today) {
                $expired = true;
            }
        }
        
        if ($expired) {
            Auth::logout();
            if (strpos($path, 'api/') === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Your company subscription has expired. Please contact your service provider to renew your services.']);
            } else {
                header('Location: ' . APP_URL . '/public/index.php/login?expired=1');
            }
            exit;
        }
    }
}

if (Auth::check() && Auth::isExecutive() && strpos($path, 'api/') !== 0) {
    $executiveAllowed = [
        '',
        'dashboard',
        'executive_dashboard',
        'leads',
        'tasks',
        'employee_commissions',
        'customers',
        'quotations',
        'quotation/print',
        'invoices',
        'invoice_ledger',
        'invoice_receipt',
        'task_performance_report',
        'lead_ledger',
        'customer_profile',
        'service_search',
        'profile',
        'performance',
        'attendance_report',
        'dues_report',
        'logout'
    ];
    if (!in_array($path, $executiveAllowed, true)) {
        header('Location: ' . APP_URL . '/public/index.php/leads');
        exit;
    }
}

// Simple response for testing
if (strpos($path, 'api/') === 0) {
    if (Auth::check() && Auth::isExecutive()) {
        $executiveApiAllowed = [
            'api/leads.php',
            'api/lead_followups.php',
            'api/leads_bulk.php',
            'api/tasks.php',
            'api/invoices.php',
            'api/quotations.php',
            'api/requirements.php',
            'api/task_performance_details.php',
            'api/customers.php',
            'api/customer_profile.php',
            'api/service_search.php',
            'api/profile.php',
            'api/attendance.php',
            'api/send_due_reminder.php',
            'api/employee_docs.php',
            'api/share.php',
        ];
        if (!in_array($path, $executiveApiAllowed, true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
    }

    $apiFile = __DIR__ . '/../' . $path;
    if (file_exists($apiFile)) {
        include $apiFile;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found: ' . $path]);
    }
    exit;
}

switch ($path) {
    case 'forgot_password':
        if (Auth::check()) {
            header('Location: ' . APP_URL . '/public/index.php/dashboard');
            exit;
        }
        include __DIR__ . '/../public/assets/views/forgot_password.php';
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $identifier = $_POST['identifier'] ?? $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            if (Auth::login($identifier, $password)) {
                if (!Auth::isSuperAdmin()) {
                    $company = Auth::company();
                    if ($company) {
                        $today = date('Y-m-d');
                        $expired = false;
                        if ($company['plan'] === 'trial') {
                            if (!empty($company['trial_ends_at']) && $company['trial_ends_at'] < $today) {
                                $expired = true;
                            }
                        } else {
                            if (!empty($company['subscription_ends_at']) && $company['subscription_ends_at'] < $today) {
                                $expired = true;
                            }
                        }
                        if ($expired) {
                            Auth::logout();
                            $subscriptionExpired = true;
                            include __DIR__ . '/../public/assets/views/login.php';
                            exit;
                        }
                    }
                }
                header('Location: ' . APP_URL . '/public/index.php/dashboard');
                exit;
            } else {
                $loginError = "Invalid email or password.";
                include __DIR__ . '/../public/assets/views/login.php';
                exit;
            }
        }
        if (Auth::check()) {
            header('Location: ' . APP_URL . '/public/index.php/dashboard');
            exit;
        }
        include __DIR__ . '/../public/assets/views/login.php';
        break;
    case 'register':
        if (Auth::check()) {
            header('Location: ' . APP_URL . '/public/index.php/dashboard');
            exit;
        }
        include __DIR__ . '/../public/assets/views/register.php';
        break;
    case 'logout':
        Auth::logout();
        header('Location: ' . APP_URL . '/public/index.php/login');
        break;
    case '':
    case 'dashboard':
    case 'executive_dashboard':
        if (Auth::isSuperAdmin() && !isset($_SESSION['original_user'])) {
            include __DIR__ . '/../public/assets/views/superadmin_dashboard.php';
        } elseif (Auth::isExecutive()) {
            include __DIR__ . '/../public/assets/views/executive_dashboard.php';
        } else {
            include __DIR__ . '/../public/assets/views/dashboard.php';
        }
        break;
    case 'leads':
        include __DIR__ . '/../public/assets/views/leads.php';
        break;
    case 'customers':
        include __DIR__ . '/../public/assets/views/customers.php';
        break;
    case 'lead_ledger':
        include __DIR__ . '/../public/assets/views/lead_ledger.php';
        break;
    case 'customer_profile':
        include __DIR__ . '/../public/assets/views/customer_profile.php';
        break;
    case 'quotations':
        include __DIR__ . '/../public/assets/views/quotations.php';
        break;
    case 'quotation/print':
        include __DIR__ . '/../public/assets/views/quotation_print.php';
        break;
    case 'invoices':
        include __DIR__ . '/../public/assets/views/invoices.php';
        break;
    case 'invoice/print':
        include __DIR__ . '/../public/assets/views/invoice_print.php';
        break;
    case 'invoice/receipt':
        include __DIR__ . '/../public/assets/views/invoice_receipt.php';
        break;
    case 'invoice_ledger':
        include __DIR__ . '/../public/assets/views/invoice_ledger.php';
        break;
    case 'invoice_receipt': // keeping the old route just in case
        include __DIR__ . '/../public/assets/views/invoice_receipt.php';
        break;
    case 'api/invoices.php':
        include __DIR__ . '/../api/invoices.php';
        exit;
    case 'api/tasks.php':
        include __DIR__ . '/../api/tasks.php';
        exit;
    case 'api/send_due_reminder.php':
        include __DIR__ . '/../api/send_due_reminder.php';
        exit;
    case 'enquiries':
        include __DIR__ . '/../public/assets/views/enquiries.php';
        break;
    case 'employees':
        include __DIR__ . '/../public/assets/views/employees.php';
        break;
    case 'users':
        include __DIR__ . '/../public/assets/views/users.php';
        break;
    case 'commissions':
        include __DIR__ . '/../public/assets/views/commissions.php';
        break;
    case 'employee_profile':
        include __DIR__ . '/../public/assets/views/employee_profile.php';
        break;
    case 'employee_commissions':
        include __DIR__ . '/../public/assets/views/employee_commissions.php';
        break;
    case 'performance':
        include __DIR__ . '/../public/assets/views/performance.php';
        break;
    case 'attendance_report':
        include __DIR__ . '/../public/assets/views/attendance_report.php';
        break;
    case 'tasks':
        include __DIR__ . '/../public/assets/views/tasks.php';
        break;
    case 'reports':
        include __DIR__ . '/../public/assets/views/reports.php';
        break;
    case 'dues_report':
        include __DIR__ . '/../public/assets/views/dues_report.php';
        break;
    case 'report_sheet':
        include __DIR__ . '/../public/assets/views/report_sheet.php';
        break;
    case 'task_performance_report':
        include __DIR__ . '/../public/assets/views/task_performance_report.php';
        break;
    case 'lead_report':
        include __DIR__ . '/../public/assets/views/lead_report.php';
        break;
    case 'settings':
        include __DIR__ . '/../public/assets/views/settings.php';
        break;
    case 'profile':
        include __DIR__ . '/../public/assets/views/profile.php';
        break;
    case 'requirements':
        include __DIR__ . '/../public/assets/views/requirements.php';
        break;
    case 'service_search':
        include __DIR__ . '/../public/assets/views/service_search.php';
        break;
    case 'companies':
        include __DIR__ . '/../public/assets/views/companies.php';
        break;
    case 'plan_management':
        include __DIR__ . '/../public/assets/views/plan_management.php';
        break;
    case 'website_settings':
        include __DIR__ . '/../public/assets/views/website_settings.php';
        break;
    case 'impersonate':
        if (Auth::isSuperAdmin()) {
            $company_id = $_GET['company_id'] ?? null;
            if ($company_id) {
                $db = Database::getInstance();
                $targetUser = $db->fetchOne("SELECT * FROM users WHERE company_id = ? AND role = 'admin' AND status = 'active' LIMIT 1", [$company_id]);
                if ($targetUser) {
                    $_SESSION['original_user'] = $_SESSION['user'];
                    unset($targetUser['password']);
                    $_SESSION['user'] = $targetUser;
                    header('Location: ' . APP_URL . '/public/index.php/dashboard');
                    exit;
                } else {
                    echo "<script>alert('No active admin user found for this company.'); window.location.href='" . APP_URL . "/public/index.php/companies';</script>";
                    exit;
                }
            }
        }
        header('Location: ' . APP_URL . '/public/index.php/dashboard');
        break;
    case 'revert_impersonation':
        if (isset($_SESSION['original_user'])) {
            $_SESSION['user'] = $_SESSION['original_user'];
            unset($_SESSION['original_user']);
            header('Location: ' . APP_URL . '/public/index.php/companies');
            exit;
        }
        header('Location: ' . APP_URL . '/public/index.php/dashboard');
        break;
    case 'api/requirements.php':
        include __DIR__ . '/../api/requirements.php';
        exit;
    default:
        http_response_code(404);
        echo "404 - Page not found: " . $path;
        break;
}
?>