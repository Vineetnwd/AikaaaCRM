<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/config.php';

$db = \Core\Database::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE leads");
print_r($stmt->fetchAll());
