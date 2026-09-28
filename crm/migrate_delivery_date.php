<?php
$_SERVER['HTTP_HOST'] = 'localhost:8000';
require_once 'config/config.php';
require_once 'core/Database.php';

use Core\Database;

$db = Database::getInstance();

try {
    $db->query("ALTER TABLE leads ADD COLUMN expected_delivery_date DATE DEFAULT NULL");
    echo "Added expected_delivery_date to leads table\n";
} catch (Exception $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}
