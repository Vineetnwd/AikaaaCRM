<?php
$_SERVER['HTTP_HOST'] = 'localhost:8000';
require_once 'config/config.php';
require_once 'core/Database.php';

use Core\Database;

$db = Database::getInstance();

try {
    // 1. Prepare IGST columns for invoices
    $db->query("ALTER TABLE invoices ADD COLUMN igst_amount DECIMAL(10,2) DEFAULT 0");
    echo "Added igst_amount to invoices\n";
    $db->query("ALTER TABLE invoices ADD COLUMN igst_percent DECIMAL(10,2) DEFAULT 0");
    echo "Added igst_percent to invoices\n";
} catch (Exception $e) {
    echo "IGST columns might already exist: " . $e->getMessage() . "\n";
}

try {
    // 2. Create Quotations table
    $db->query("CREATE TABLE IF NOT EXISTS quotations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        quotation_number VARCHAR(255) NOT NULL,
        quotation_date DATE NOT NULL,
        lead_id INT NOT NULL,
        subtotal DECIMAL(10,2) DEFAULT 0,
        sgst_amount DECIMAL(10,2) DEFAULT 0,
        cgst_amount DECIMAL(10,2) DEFAULT 0,
        igst_amount DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(10,2) DEFAULT 0,
        status ENUM('pending', 'accepted', 'rejected', 'invoiced') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id) REFERENCES leads(id)
    )");
    echo "Created quotations table\n";
} catch (Exception $e) {
    echo "Error creating quotations table: " . $e->getMessage() . "\n";
}
