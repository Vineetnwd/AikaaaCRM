<?php
$_SERVER['HTTP_HOST'] = 'localhost:8000';
require_once 'config/config.php';
require_once 'core/Database.php';

use Core\Database;

$db = Database::getInstance();

echo "Starting migration...\n";

try {
    $db->query("ALTER TABLE quotations ADD COLUMN is_gst_enabled TINYINT(1) DEFAULT 1");
    echo "Added is_gst_enabled to quotations\n";
} catch (Exception $e) {
    echo "Quotations column might exist: " . $e->getMessage() . "\n";
}

try {
    $db->query("ALTER TABLE invoices ADD COLUMN is_gst_enabled TINYINT(1) DEFAULT 1");
    echo "Added is_gst_enabled to invoices\n";
} catch (Exception $e) {
    echo "Invoices column might exist: " . $e->getMessage() . "\n";
}

echo "Migration finished.\n";
