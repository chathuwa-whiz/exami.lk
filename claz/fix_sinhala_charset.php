<?php
require_once __DIR__ . '/src/config.php';

$pdo = db();

// Convert the questions table to UTF-8
$pdo->exec("ALTER TABLE questions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "✓ Questions table charset converted to utf8mb4\n";

// Verify the change
$result = $pdo->query("SHOW CREATE TABLE questions")->fetch();
echo "\nTable definition:\n";
echo $result['Create Table'] . "\n";
