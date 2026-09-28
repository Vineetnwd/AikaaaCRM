<?php
// Environment Detection
$host = explode(':', $_SERVER['HTTP_HOST'])[0];
$is_local = in_array($host, ['localhost', '127.0.0.1']);

if ($is_local) {
    // Local Settings
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'aikaa_crm');

    // Dynamic local URL includes port if present
    define('APP_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/aikocrm');
} else {
    // Live Settings (aikocrm.com)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u138788005_aikocrm'); // Updated to reflect live DB name usually matches user
    define('DB_PASS', 'AikoCrm@2026');
    define('DB_NAME', 'u138788005_aikocrm');
    define('APP_URL', 'https://aikocrm.com/crm');
}

// App Config
define('APP_NAME', 'Aikaa CRM');
date_default_timezone_set('Asia/Kolkata');
// Security
define('JWT_SECRET', 'your-very-secure-secret-key-123456');
?>