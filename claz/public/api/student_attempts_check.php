<?php
require_once __DIR__ . '/../../src/config.php';
header('Content-Type: application/json');
session_start();
$user = current_user();
if (!$user || $user['user_type'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$attemptIds = $data['attempt_ids'] ?? [];

if (!is_array($attemptIds) || empty($attemptIds)) {
    echo json_encode(['submitted_ids' => []]);
    exit;
}

$pdo = db();
$placeholders = implode(',', array_fill(0, count($attemptIds), '?'));
$stmt = $pdo->prepare("SELECT id FROM attempts WHERE id IN ($placeholders) AND student_id=? AND submitted_at IS NOT NULL");
$stmt->execute(array_merge($attemptIds, [$user['id']]));
$submitted = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(['submitted_ids' => $submitted]);
