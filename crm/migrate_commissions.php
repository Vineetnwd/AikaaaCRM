<?php
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

echo "<h3 style='font-family:monospace;'>Running Commission Migrations...</h3>";

runSQL($db, 'Create employee_commission_payouts table', "CREATE TABLE IF NOT EXISTS `employee_commission_payouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `employee_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payout_date` DATE NOT NULL,
  `reference_note` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

runSQL($db, 'Add total_commission_paid to employees', "ALTER TABLE `employees` ADD COLUMN `total_commission_paid` DECIMAL(15,2) DEFAULT 0.00 AFTER `total_commission_earned`;");

echo "<br><b>Done!</b>";
