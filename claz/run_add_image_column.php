<?php
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    $sql = file_get_contents(__DIR__ . '/add_question_image.sql');
    $pdo->exec($sql);
    echo "Image column added to questions table\n";
} catch (Exception $e) {
    echo "Info: " . $e->getMessage() . "\n";
}
