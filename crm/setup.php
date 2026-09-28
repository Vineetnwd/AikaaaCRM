<?php
require_once __DIR__ . '/config/config.php';

// Temporarily connect without DB name to create it
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Creating Database: " . DB_NAME . "...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);

    echo "Running Schema...\n";
    $schema = file_get_contents(__DIR__ . '/scripts/schema.sql');
    $pdo->exec($schema);

    echo "Seeding Data...\n";
    $seed = file_get_contents(__DIR__ . '/scripts/seed.sql');
    // Remove USE statements from seed if they exist to prevent errors
    $seed = preg_replace('/USE \w+;/', '', $seed);
    $pdo->exec($seed);

    echo "✓ Setup Complete! You can now log in with admin@aikocrm.com / admin123\n";
} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
