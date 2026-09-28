<?php
/**
 * TEMPORARY VERIFICATION — DELETE AFTER USE
 * Upload to: /crm/api/verify_auth.php
 * Visit: https://aikocrm.com/crm/api/verify_auth.php
 */
header('Content-Type: application/json');

// Read the actual Auth.php file on the server
$authFile = __DIR__ . '/../core/Auth.php';
$content = file_get_contents($authFile);

// Check for old vs new validateToken
$hasOldQuery = strpos($content, "LOWER(status) = 'active'") !== false;
$hasNewQuery = strpos($content, 'WHERE id = ? LIMIT 1') !== false;
$hasImpersonatingParam = strpos($content, 'bool $impersonating') !== false;

// Check PHP version
$phpVersion = PHP_VERSION;

// Check if JWT_SECRET is defined
$jwtDefined = defined('JWT_SECRET');
$jwtSecret = $jwtDefined ? substr(JWT_SECRET, 0, 10) . '...' : 'NOT DEFINED (using fallback)';

// Check OPcache
$opcacheEnabled = function_exists('opcache_get_status') && opcache_get_status() !== false;

echo json_encode([
    'php_version' => $phpVersion,
    'auth_php_path' => $authFile,
    'auth_php_exists' => file_exists($authFile),
    'auth_php_size' => filesize($authFile),
    'auth_php_modified' => date('Y-m-d H:i:s', filemtime($authFile)),
    'has_OLD_status_filter' => $hasOldQuery,  // BAD: true means old code
    'has_NEW_no_filter' => $hasNewQuery,       // GOOD: true means new code
    'has_impersonating_param' => $hasImpersonatingParam,
    'jwt_secret_defined' => $jwtDefined,
    'jwt_secret_preview' => $jwtSecret,
    'jwt_fallback' => 'aikaa_crm_secret_key_2026',
    'jwt_matches_fallback' => $jwtDefined && JWT_SECRET === 'aikaa_crm_secret_key_2026',
    'opcache_enabled' => $opcacheEnabled,
    'verdict' => $hasOldQuery ? 'SERVER HAS OLD AUTH.PHP — upload the new one!' : ($hasNewQuery ? 'Server has new Auth.php ✓' : 'UNKNOWN — check manually'),
], JSON_PRETTY_PRINT);
