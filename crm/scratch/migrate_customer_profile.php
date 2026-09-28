<?php
require_once __DIR__ . '/../core/Database.php';
use Core\Database;

$db = Database::getInstance();

try {
    // Add photo column to customers if not exists
    $columns = $db->fetchAll("SHOW COLUMNS FROM customers LIKE 'photo'");
    if (empty($columns)) {
        $db->query("ALTER TABLE customers ADD COLUMN photo VARCHAR(255) NULL AFTER address");
        echo "Added 'photo' column to 'customers' table.\n";
    }

    // Create customer_doc_ledger table
    $db->query("CREATE TABLE IF NOT EXISTS customer_doc_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        doc_name VARCHAR(255) NOT NULL,
        attachment VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (customer_id)
    )");
    echo "Ensured 'customer_doc_ledger' table exists.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
