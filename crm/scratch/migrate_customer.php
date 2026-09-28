<?php
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    echo "Starting migration...\n";

    // 1. Create customers table
    $conn->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        email VARCHAR(255),
        address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY company_mobile (company_id, mobile)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'customers' created.\n";

    // 2. Add customer_id to leads
    $checkColumn = $conn->query("SHOW COLUMNS FROM leads LIKE 'customer_id'");
    if (!$checkColumn->fetch()) {
        $conn->exec("ALTER TABLE leads ADD COLUMN customer_id INT AFTER company_id, ADD INDEX (customer_id)");
        echo "Column 'customer_id' added to 'leads'.\n";
    }

    // 3. Populate customers from existing leads
    $leads = $db->fetchAll("SELECT * FROM leads ORDER BY id ASC");
    echo "Processing " . count($leads) . " leads...\n";

    foreach ($leads as $lead) {
        if (empty($lead['mobile'])) continue;

        // Find or create customer
        $customer = $db->fetchOne(
            "SELECT id FROM customers WHERE company_id = ? AND mobile = ?",
            [$lead['company_id'], $lead['mobile']]
        );

        if (!$customer) {
            $customerId = $db->insert('customers', [
                'company_id' => $lead['company_id'],
                'name' => $lead['name'],
                'mobile' => $lead['mobile'],
                'email' => $lead['email'],
                'address' => $lead['address'],
                'created_at' => $lead['created_at']
            ]);
            echo "Created customer for " . $lead['name'] . " (" . $lead['mobile'] . ")\n";
        } else {
            $customerId = $customer['id'];
        }

        // Link lead to customer
        $db->update('leads', ['customer_id' => $customerId], "id = ?", [$lead['id']]);
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
