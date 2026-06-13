<?php
require_once __DIR__ . '/src/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n\n";

if (in_array('paper_access', $tables)) {
    $cols = $pdo->query('DESCRIBE paper_access')->fetchAll(PDO::FETCH_ASSOC);
    echo "paper_access columns:\n";
    foreach ($cols as $c) {
        echo '  - ' . $c['Field'] . ' (' . $c['Type'] . ')' . "\n";
    }
} else {
    echo "paper_access table not found!\n";
}
