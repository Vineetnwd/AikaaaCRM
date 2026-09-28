<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

$db = Database::getInstance();
$table = 'employees';
$columns = $db->fetchAll("DESCRIBE $table");

echo "Columns in $table table:\n";
foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}
