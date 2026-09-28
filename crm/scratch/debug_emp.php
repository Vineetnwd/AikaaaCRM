<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Employee.php';

use Core\Database;


$db = Database::getInstance();
$user = $db->fetchOne("SELECT id, name, role, employee_id FROM users WHERE id = 9");
print_r($user);
