<?php
require 'src/config.php';
try {
    $pdo = db();
    $rows = $pdo->query('SELECT id, name, email, user_type FROM users LIMIT 10')->fetchAll();
    echo "Users in DB:\n";
    print_r($rows);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
