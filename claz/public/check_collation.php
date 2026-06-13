<?php
require_once __DIR__ . '/../src/config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    echo "Filesystem encoding: " . mb_internal_encoding() . "\n";
    echo "HTTP Output encoding: " . mb_http_output() . "\n";
    
    $tables = ['questions', 'answer_options', 'papers', 'users'];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SHOW CREATE TABLE $t");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\n--- $t ---\n";
        echo $row['Create Table'] . "\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
