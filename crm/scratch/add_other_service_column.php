<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Database;

$db = Database::getInstance();
try {
    $db->query("ALTER TABLE leads ADD COLUMN other_service_name VARCHAR(255) DEFAULT NULL AFTER commission_amount");
    echo "Column other_service_name added successfully!\n";
} catch (Exception $e) {
    echo "Error or column already exists: " . $e->getMessage() . "\n";
}
