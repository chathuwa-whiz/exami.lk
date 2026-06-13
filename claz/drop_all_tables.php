<?php
require_once __DIR__ . '/src/config.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "Setting foreign key checks to 0...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    
    echo "Dropping all tables in ceylonstudyhub...\n";
    $pdo->exec('USE ceylonstudyhub');
    
    // Get all tables
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "  Dropping table: $table\n";
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    
    echo "All tables dropped successfully.\n";
    
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
