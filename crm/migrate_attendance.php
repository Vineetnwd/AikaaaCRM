<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';

use Core\Database;

$db = Database::getInstance();

try {
    // Create attendance_logs table
    $db->query("
        CREATE TABLE IF NOT EXISTS attendance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            employee_id INT NOT NULL,
            work_date DATE NOT NULL,
            check_in DATETIME NOT NULL,
            check_out DATETIME DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'Present',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (company_id),
            INDEX (employee_id),
            INDEX (work_date)
        )
    ");

    // Add attendance column to employees if not exists (it already exists but good to be sure)
    $columns = $db->fetchAll("SHOW COLUMNS FROM employees LIKE 'attendance'");
    if (empty($columns)) {
        $db->query("ALTER TABLE employees ADD COLUMN attendance VARCHAR(50) DEFAULT 'Absent'");
    }

    echo "Attendance table created and employees table updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
