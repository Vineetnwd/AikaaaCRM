<?php
namespace Core;

// Ensure config is loaded so JWT_SECRET is always defined
if (!defined('JWT_SECRET') && file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

class Auth {
    private static $currentUser = null;
    private static $currentCompany = null;

    public static function attemptLogin($identifier, $password) {
        $db = Database::getInstance();
        $raw = trim((string)$identifier);
        
        if (empty($raw) || empty($password)) {
            return ['success' => false, 'error' => 'Email/Mobile and password are required.'];
        }

        // Extract clean 10-digit mobile if phone number provided
        $digits = preg_replace('/[^0-9]/', '', $raw);
        $cleanMobile10 = null;
        if (strlen($digits) === 10) {
            $cleanMobile10 = $digits;
        } elseif (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
            $cleanMobile10 = substr($digits, 2);
        } elseif (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
            $cleanMobile10 = substr($digits, 1);
        }

        // Search for user record matching email or any mobile variant
        $params = [strtolower($raw), $raw];
        $sql = "SELECT * FROM users WHERE (LOWER(TRIM(email)) = ? OR TRIM(mobile) = ?";
        
        if ($cleanMobile10) {
            $sql .= " OR TRIM(mobile) = ? OR TRIM(mobile) = ? OR TRIM(mobile) = ? OR TRIM(mobile) = ?";
            $params[] = $cleanMobile10;
            $params[] = '+91' . $cleanMobile10;
            $params[] = '91' . $cleanMobile10;
            $params[] = '0' . $cleanMobile10;
        }
        $sql .= ") LIMIT 1";

        $user = $db->fetchOne($sql, $params);

        if (!$user) {
            return ['success' => false, 'error' => 'No account found with this email or mobile number.'];
        }

        // Check user account status
        $userStatus = strtolower(trim((string)($user['status'] ?? 'active')));
        if ($userStatus !== '' && !in_array($userStatus, ['active', '1', 'enabled'], true)) {
            return ['success' => false, 'error' => 'Your account is ' . ($user['status'] ?? 'inactive') . '. Please contact your CRM administrator to activate it.'];
        }

        // Verify password with support for bcrypt, md5 legacy, and plain-text fallback
        $passwordValid = false;
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        } elseif (!empty($user['password']) && $user['password'] === md5($password)) {
            $passwordValid = true;
            try {
                $db->query("UPDATE users SET password = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            } catch (\Throwable $e) {}
        } elseif (!empty($user['password']) && $user['password'] === $password) {
            $passwordValid = true;
            try {
                $db->query("UPDATE users SET password = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            } catch (\Throwable $e) {}
        }

        if (!$passwordValid) {
            return ['success' => false, 'error' => 'Incorrect password. Please verify and try again.'];
        }

        // Check company status if applicable
        if (!empty($user['company_id'])) {
            $company = $db->fetchOne("SELECT * FROM companies WHERE id = ? LIMIT 1", [$user['company_id']]);
            if ($company) {
                $compStatus = strtolower(trim((string)($company['status'] ?? 'active')));
                if ($compStatus !== '' && !in_array($compStatus, ['active', '1'], true)) {
                    return ['success' => false, 'error' => 'Company account is ' . ($company['status'] ?? 'inactive') . '. Please contact support.'];
                }
            }
        }

        unset($user['password']);
        $_SESSION['user'] = $user;
        self::$currentUser = $user;
        return ['success' => true, 'user' => $user];
    }

    public static function login($identifier, $password) {
        $result = self::attemptLogin($identifier, $password);
        return $result['success'];
    }

    public static function check() {
        if (isset($_SESSION['user'])) {
            self::$currentUser = $_SESSION['user'];
            return true;
        }

        // Check for Bearer token in Authorization header for mobile app requests
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        } elseif (!empty($_GET['auth_token'])) {
            $token = $_GET['auth_token'];
        }

        if (isset($token)) {
            $user = self::validateToken($token);
            if ($user) {
                self::$currentUser = $user;
                // If it's a webview loading a page, we might want to set the session
                if (!isset($_SESSION['user']) && strpos($_SERVER['REQUEST_URI'], '/api/') === false) {
                    $_SESSION['user'] = $user;
                }
                return true;
            }
        }

        return false;
    }

    public static function generateToken($user, bool $impersonating = false) {
        $secret = defined('JWT_SECRET') ? JWT_SECRET : 'aikaa_crm_secret_key_2026';
        $payload = [
            'uid' => $user['id'],
            'cid' => $user['company_id'],
            'time' => time(),
            'exp' => time() + (365 * 86400) // 1 year validity
        ];
        if ($impersonating) {
            $payload['imp'] = true;
        }
        $data = base64_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $data, $secret);
        return $data . '.' . $sig;
    }

    public static function validateToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 2) return null;
        list($data, $sig) = $parts;
        $secret = defined('JWT_SECRET') ? JWT_SECRET : 'aikaa_crm_secret_key_2026';
        $expectedSig = hash_hmac('sha256', $data, $secret);
        if (!hash_equals($expectedSig, $sig)) return null;

        $payload = json_decode(base64_decode($data), true);
        if (!$payload || empty($payload['uid']) || (isset($payload['exp']) && $payload['exp'] < time())) {
            return null;
        }

        $db = Database::getInstance();
        // Security is guaranteed by the HMAC signature above — only this server
        // can mint valid tokens. Account status is enforced at login time.
        // Do NOT filter by status here: impersonation tokens may target users
        // whose status isn't 'active', and that is intentional.
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$payload['uid']]);
        if (!$user) return null;

        unset($user['password']);
        return $user;
    }

    public static function user() {
        return self::$currentUser;
    }

    public static function userId() {
        return self::$currentUser ? self::$currentUser['id'] : null;
    }

    public static function userName() {
        return self::$currentUser ? self::$currentUser['name'] : 'Admin User';
    }

    public static function companyId() {
        return self::$currentUser ? self::$currentUser['company_id'] : null;
    }

    public static function company() {
        if (self::$currentCompany) return self::$currentCompany;
        $id = self::companyId();
        if (!$id) return null;

        $db = Database::getInstance();
        self::$currentCompany = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$id]);
        return self::$currentCompany;
    }

    public static function role() {
        return self::$currentUser ? (self::$currentUser['role'] ?? '') : '';
    }

    public static function isExecutive() {
        return self::role() === 'executive';
    }

    public static function isManager() {
        return self::role() === 'manager';
    }

    public static function isAdmin() {
        return self::role() === 'admin';
    }

    public static function isSuperAdmin() {
        $r = strtolower(self::role());
        return $r === 'super_admin' || $r === 'superadmin';
    }

    public static function employeeId() {
        if (!self::$currentUser) return null;
        if (!empty(self::$currentUser['employee_id'])) return self::$currentUser['employee_id'];
        if (!empty(self::$currentUser['emp_id'])) return self::$currentUser['emp_id'];

        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ? LIMIT 1", [self::$currentUser['id']]);
        if (!empty($user['employee_id'])) return $user['employee_id'];
        if (!empty($user['emp_id'])) return $user['emp_id'];

        $employee = $db->fetchOne(
            "SELECT id FROM employees WHERE company_id = ? AND email = ? LIMIT 1",
            [self::$currentUser['company_id'], self::$currentUser['email'] ?? '']
        );

        return $employee['id'] ?? null;
    }

    public static function logout() {
        unset($_SESSION['user']);
        self::$currentUser = null;
        session_destroy();
    }
}
?>
