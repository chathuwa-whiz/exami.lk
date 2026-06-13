<?php
// Drop and recreate the entire ceylonstudyhub database, then recreate tables and seed subjects.
// WARNING: Destroys all data in this database.
require_once __DIR__ . '/src/config.php';

function pdo_root(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

try {
    $root = pdo_root();
    $db = DB_NAME;
    $root->exec("DROP DATABASE IF EXISTS `$db`");
    $root->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    require_once __DIR__ . '/reset_schema.php';
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
