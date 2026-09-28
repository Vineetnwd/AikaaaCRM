<?php
require_once __DIR__ . '/../core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "Column Metadata for Customers:\n";
$res = $db->fetchAll("SHOW FULL COLUMNS FROM customers");
print_r($res);

echo "\nColumn Metadata for Leads:\n";
$res = $db->fetchAll("SHOW FULL COLUMNS FROM leads");
print_r($res);
