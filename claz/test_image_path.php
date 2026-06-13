<?php
require 'src/config.php';
$pdo = db();
$stmt = $pdo->query('SELECT id, question_text, image_path FROM questions WHERE image_path IS NOT NULL LIMIT 5');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "No questions with images found in database.\n";
} else {
    foreach($rows as $r) {
        $preview = substr($r['question_text'], 0, 50);
        echo "ID: {$r['id']} | Path: {$r['image_path']}\n";
    }
}
