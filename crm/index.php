<?php
/**
 * Aikaa CRM - Root Bridge
 * This file allows the app to run on servers that don't support .htaccess 
 * or when running 'php -S' from the root directory.
 */

// Define that we are entering via the root bridge
define('ROOT_BRIDGE', true);

// Proxy the request to the actual public entry point
require_once __DIR__ . '/public/index.php';
?>
