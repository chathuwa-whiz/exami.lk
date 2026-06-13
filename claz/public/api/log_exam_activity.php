<?php
require_once __DIR__ . '/../../src/config.php';
require_login();

$user = current_user();
if ($user['user_type'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$attemptId = (int)($input['attempt_id'] ?? 0);
$activityType = trim($input['activity_type'] ?? '');
$details = json_encode($input['details'] ?? []);
$tabSwitches = (int)($input['tab_switches'] ?? 0);

if (!$attemptId || !$activityType) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$pdo = db();

// Verify the student owns this attempt
$stmt = $pdo->prepare('SELECT id FROM attempts WHERE id = ? AND student_id = ?');
$stmt->execute([$attemptId, $user['id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Log the suspicious activity
$logStmt = $pdo->prepare('
    INSERT INTO exam_integrity_logs (attempt_id, activity_type, details, tab_switches, logged_at) 
    VALUES (?, ?, ?, ?, NOW())
');

try {
    $logStmt->execute([$attemptId, $activityType, $details, $tabSwitches]);
    
    // Flag attempt if too many suspicious activities
    if ($activityType === 'tab_switch' && $tabSwitches > 5) {
        $updateStmt = $pdo->prepare('UPDATE attempts SET integrity_flagged = 1 WHERE id = ?');
        $updateStmt->execute([$attemptId]);
    }
    
    echo json_encode(['success' => true, 'logged' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to log activity']);
}
?>
