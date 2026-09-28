<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Lead.php';

session_start();
$_SESSION['user'] = ['id' => 1, 'company_id' => 1, 'role' => 'admin'];
Core\Auth::check();

try {
    $leadModel = new Core\Lead();
    $filters = ['month' => '4', 'year' => '2026'];
    $data = $leadModel->all($filters);
    echo "SUCCESS: " . count($data) . " leads found\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}
