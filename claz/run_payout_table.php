<?php
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    $sql = file_get_contents(__DIR__ . '/create_payout_table.sql');
    $pdo->exec($sql);
    echo "Payout table created successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
