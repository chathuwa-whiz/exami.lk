<?php
require_once __DIR__ . '/../../src/config.php';
header('Content-Type: application/json');

$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
if ($subjectId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid subject_id']);
    exit;
}

try {
    $pdo = db();
    // Fetch teachers who teach the specified subject
    $stmt = $pdo->prepare("SELECT u.id, u.name FROM users u 
      INNER JOIN teacher_subject ts ON ts.teacher_id = u.id 
      WHERE ts.subject_id = ? AND u.user_type = 'teacher' 
      ORDER BY u.name");
    $stmt->execute([$subjectId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'teachers' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load teachers']);
}
