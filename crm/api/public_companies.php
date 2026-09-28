<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Database;

$db = Database::getInstance();
$companies = $db->fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name ASC");

echo json_encode($companies);
