<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';

try {
    $db = Core\Database::getInstance();
    $res = $db->fetchAll("DESCRIBE tasks");
    print_r($res);
    
    $res2 = $db->fetchAll("SELECT * FROM tasks LIMIT 1");
    print_r($res2);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
