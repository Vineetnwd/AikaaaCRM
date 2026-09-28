<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Invoice.php';


use Core\Invoice;

try {
    // Mock Auth
    $_SESSION['user'] = ['company_id' => 1, 'id' => 1, 'role' => 'admin'];
    
    $model = new Invoice();
    echo $model->getNextInvoiceNumber();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}

?>
