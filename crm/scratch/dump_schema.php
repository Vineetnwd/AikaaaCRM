<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';


$db = Core\Database::getInstance();
$res = $db->fetchAll("DESCRIBE leads");
print_r($res);
