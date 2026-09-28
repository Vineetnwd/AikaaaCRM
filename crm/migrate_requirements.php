<?php
$_SERVER['HTTP_HOST'] = 'localhost:8000';
require_once 'config/config.php';
require_once 'core/Database.php';

use Core\Database;

$db = Database::getInstance();

try {
    // Create Requirements table
    $db->query("CREATE TABLE IF NOT EXISTS requirements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        req_docs TEXT,
        fee DECIMAL(15,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    )");
    echo "Created requirements table\n";
} catch (Exception $e) {
    echo "Error creating requirements table: " . $e->getMessage() . "\n";
}

try {
    // Create Lead Requirements junction table
    $db->query("CREATE TABLE IF NOT EXISTS lead_requirements (
        lead_id INT NOT NULL,
        requirement_id INT NOT NULL,
        PRIMARY KEY (lead_id, requirement_id),
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
        FOREIGN KEY (requirement_id) REFERENCES requirements(id) ON DELETE CASCADE
    )");
    echo "Created lead_requirements junction table\n";
} catch (Exception $e) {
    echo "Error creating lead_requirements table: " . $e->getMessage() . "\n";
}
