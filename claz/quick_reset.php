<?php
require_once __DIR__ . '/src/config.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "Disabling foreign key checks...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    
    echo "Dropping database if exists...\n";
    $pdo->exec('DROP DATABASE IF EXISTS ceylonstudyhub');
    
    echo "Creating database...\n";
    $pdo->exec('CREATE DATABASE ceylonstudyhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    
    echo "Database reset successfully.\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}

