<?php
require_once __DIR__ . '/../../src/config.php';
header('Content-Type: application/json');

try {
    $pdo = db();
    // Fetch from subjects table, ordered by name
    $rows = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'subjects' => $rows]);
} catch (Throwable $e) {
    // Fallback to demo subjects if table is missing or error
    $rows = [
        ['id' => 1, 'name' => 'Mathematics'],
        ['id' => 2, 'name' => 'English'],
        ['id' => 3, 'name' => 'Science'],
        ['id' => 4, 'name' => 'History'],
    ];
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load subjects from database. Using demo data.', 'subjects' => $rows]);
}
