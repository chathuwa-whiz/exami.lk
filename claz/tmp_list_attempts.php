<?php
require_once __DIR__ . '/src/config.php';
$pdo = db();
$rows = $pdo->query('SELECT id, student_id, paper_id, submitted_at FROM attempts ORDER BY id DESC LIMIT 20')->fetchAll();
foreach ($rows as $r) {
    echo "ID: {$r['id']} student: {$r['student_id']} paper: {$r['paper_id']} submitted: {$r['submitted_at']}\n";
}
