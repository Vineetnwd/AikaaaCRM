<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

$db = Database::getInstance();

try {
    // Add missing columns to employees table
    $db->query("ALTER TABLE employees 
        ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER employee_id,
        ADD COLUMN attendance VARCHAR(50) DEFAULT 'Absent' AFTER status,
        ADD COLUMN target DECIMAL(15,2) DEFAULT 0.00 AFTER attendance,
        ADD COLUMN daily_work_status VARCHAR(255) DEFAULT NULL AFTER target,
        ADD COLUMN daily_work_history TEXT DEFAULT NULL AFTER daily_work_status
    ");
    echo "Migration successful: Added columns to employees table.\n";
} catch (Throwable $e) {
    echo "Migration failed or already applied: " . $e->getMessage() . "\n";
}
