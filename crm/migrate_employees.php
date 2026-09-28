<?php
/**
 * Migration v3: Employees + Commission + Duplicate Detection
 * Run once via browser: http://localhost/aikocrm/migrate_employees.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';

use Core\Database;

$db = Database::getInstance()->getConnection();

function runSQL(PDO $db, string $label, string $sql): void {
    try {
        $db->exec($sql);
        echo "<pre style='color:green;font-family:monospace;margin:2px 0;'>[OK]   $label</pre>";
    } catch (PDOException $e) {
        echo "<pre style='color:orange;font-family:monospace;margin:2px 0;'>[SKIP] $label — " . $e->getMessage() . "</pre>";
    }
}

echo "<h3 style='font-family:monospace;'>Running Migrations...</h3>";

// 1. Create employees table (with all fields)
runSQL($db, 'Create employees table', "CREATE TABLE IF NOT EXISTS `employees` (
  `id`                      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `company_id`              INT UNSIGNED  NOT NULL,
  `employee_id`             VARCHAR(50)   DEFAULT NULL,
  `name`                    VARCHAR(255)  NOT NULL,
  `email`                   VARCHAR(255)  DEFAULT NULL,
  `mobile`                  VARCHAR(20)   DEFAULT NULL,
  `designation`             VARCHAR(150)  DEFAULT NULL,
  `department`              VARCHAR(150)  DEFAULT NULL,
  `date_of_joining`         DATE          DEFAULT NULL,
  `date_of_birth`           DATE          DEFAULT NULL,
  `gender`                  ENUM('male','female','other') DEFAULT NULL,
  `address`                 TEXT          DEFAULT NULL,
  `salary`                  DECIMAL(12,2) DEFAULT 0.00,
  `commission_type`         ENUM('percentage','fixed') DEFAULT 'percentage',
  `commission_rate`         DECIMAL(10,2) DEFAULT 0.00,
  `total_commission_earned` DECIMAL(15,2) DEFAULT 0.00,
  `status`                  ENUM('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  `notes`                   TEXT          DEFAULT NULL,
  `created_at`              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 2. Add commission fields to employees if they didn't exist
runSQL($db, 'employees: add commission_type',           "ALTER TABLE `employees` ADD COLUMN `commission_type` ENUM('percentage','fixed') DEFAULT 'percentage' AFTER `salary`");
runSQL($db, 'employees: add commission_rate',           "ALTER TABLE `employees` ADD COLUMN `commission_rate` DECIMAL(10,2) DEFAULT 0.00 AFTER `commission_type`");
runSQL($db, 'employees: add total_commission_earned',   "ALTER TABLE `employees` ADD COLUMN `total_commission_earned` DECIMAL(15,2) DEFAULT 0.00 AFTER `commission_rate`");

// 3. Add missing columns to leads table
runSQL($db, 'leads: add assigned_employee_id', "ALTER TABLE `leads` ADD COLUMN `assigned_employee_id` INT UNSIGNED DEFAULT NULL AFTER `assigned_to`");
runSQL($db, 'leads: add deal_value',           "ALTER TABLE `leads` ADD COLUMN `deal_value` DECIMAL(12,2) DEFAULT 0.00 AFTER `ref_person`");
runSQL($db, 'leads: add commission_percent',   "ALTER TABLE `leads` ADD COLUMN `commission_percent` DECIMAL(10,2) DEFAULT 0.00 AFTER `deal_value`");
runSQL($db, 'leads: add commission_amount',    "ALTER TABLE `leads` ADD COLUMN `commission_amount` DECIMAL(12,2) DEFAULT 0.00 AFTER `commission_percent`");
runSQL($db, 'leads: add referral_person',      "ALTER TABLE `leads` ADD COLUMN `referral_person` VARCHAR(255) DEFAULT NULL AFTER `ref_person`");
runSQL($db, 'leads: add closed_at',            "ALTER TABLE `leads` ADD COLUMN `closed_at` DATETIME DEFAULT NULL");
runSQL($db, 'users: add employee_id',          "ALTER TABLE `users` ADD COLUMN `employee_id` INT UNSIGNED DEFAULT NULL AFTER `role`");
runSQL($db, 'users: add emp_id',               "ALTER TABLE `users` ADD COLUMN `emp_id` INT UNSIGNED DEFAULT NULL AFTER `employee_id`");
runSQL($db, 'users: allow executive role',     "ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','manager','executive') DEFAULT 'executive'");
runSQL($db, 'users: backfill employee_id',     "UPDATE `users` u JOIN `employees` e ON e.company_id = u.company_id AND e.email = u.email SET u.employee_id = e.id WHERE u.employee_id IS NULL");
runSQL($db, 'users: backfill emp_id',          "UPDATE `users` u JOIN `employees` e ON e.company_id = u.company_id AND e.email = u.email SET u.emp_id = e.id WHERE u.emp_id IS NULL");

// 4. Update leads status ENUM to ensure in_progress is included
runSQL($db, 'leads: update status ENUM', "ALTER TABLE `leads` MODIFY COLUMN `status` ENUM('new','in_progress','won','lost') NOT NULL DEFAULT 'new'");

echo "<hr style='margin:1rem 0;'><b style='font-family:monospace;'>✓ Migration complete. You can delete or disable this file now.</b>";
?>
