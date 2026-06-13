<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$user = current_user();
if (!$user) {
    $user = auth_from_bearer();
}
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

$pdo = db();

function resolve_pair(PDO $pdo, array $user, int $peerId): array {
    if ($peerId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'peer_required']);
        exit;
    }
    // Validate the peer exists and is of opposite role
    $peerStmt = $pdo->prepare('SELECT id, user_type, name FROM users WHERE id = ?');
    $peerStmt->execute([$peerId]);
    $peer = $peerStmt->fetch();
    if (!$peer) {
        http_response_code(404);
        echo json_encode(['error' => 'peer_not_found']);
        exit;
    }

    if ($user['user_type'] === 'student' && $peer['user_type'] !== 'teacher') {
        http_response_code(403);
        echo json_encode(['error' => 'peer_must_be_teacher']);
        exit;
    }
    if ($user['user_type'] === 'teacher' && $peer['user_type'] !== 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'peer_must_be_student']);
        exit;
    }
    if ($user['user_type'] !== 'teacher' && $user['user_type'] !== 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'role_not_supported']);
        exit;
    }

    // Ensure mapping exists
    if ($user['user_type'] === 'student') {
        $chk = $pdo->prepare('SELECT 1 FROM teacher_student WHERE student_id = ? AND teacher_id = ?');
        $chk->execute([$user['id'], $peerId]);
        if (!$chk->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'not_linked']);
            exit;
        }
        return [$peerId, $user['id'], $peer];
    }

    // Teacher
    $chk = $pdo->prepare('SELECT 1 FROM teacher_student WHERE teacher_id = ? AND student_id = ?');
    $chk->execute([$user['id'], $peerId]);
    if (!$chk->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'not_linked']);
        exit;
    }
    return [$user['id'], $peerId, $peer];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $peerId = (int)($_GET['peer_id'] ?? 0);
    [$teacherId, $studentId, $peer] = resolve_pair($pdo, $user, $peerId);
    $sinceId = isset($_GET['since_id']) ? max(0, (int)$_GET['since_id']) : 0;
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 200;

    $sql = 'SELECT id, sender_id, receiver_id, body, attachment_type, attachment_path, is_read, created_at, read_at FROM messages WHERE teacher_id = ? AND student_id = ?';
    $params = [$teacherId, $studentId];
    if ($sinceId > 0) {
        $sql .= ' AND id > ?';
        $params[] = $sinceId;
    }
    $sql .= ' ORDER BY id ASC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Mark received messages as read
    $mark = $pdo->prepare('UPDATE messages SET is_read = 1, read_at = NOW() WHERE receiver_id = ? AND teacher_id = ? AND student_id = ? AND is_read = 0');
    $mark->execute([$user['id'], $teacherId, $studentId]);

    echo json_encode([
        'peer' => ['id' => $peer['id'], 'name' => $peer['name'], 'user_type' => $peer['user_type']],
        'messages' => $messages,
    ]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_json']);
        exit;
    }

    $peerId = (int)($data['peer_id'] ?? 0);
    $body = trim((string)($data['body'] ?? ''));
    $attachmentData = $data['attachment'] ?? null;
    [$teacherId, $studentId, $peer] = resolve_pair($pdo, $user, $peerId);

    if ($body === '' && !$attachmentData) {
        http_response_code(422);
        echo json_encode(['error' => 'validation', 'message' => 'Message or attachment required']);
        exit;
    }
    if (strlen($body) > 2000) {
        http_response_code(422);
        echo json_encode(['error' => 'validation', 'message' => 'Message too long']);
        exit;
    }

    $receiverId = ($user['user_type'] === 'student') ? $teacherId : $studentId;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
    rate_limit_user_ip_assert((int)$user['id'], $ip, 60, 120, 60);

    $attachmentType = null;
    $attachmentPath = null;

    // Handle attachment upload
    if ($attachmentData && is_array($attachmentData)) {
        $type = $attachmentData['type'] ?? '';
        $dataUri = $attachmentData['data'] ?? '';
        
        if (in_array($type, ['image', 'pdf', 'audio']) && $dataUri) {
            // Parse data URI
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $dataUri, $matches)) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
                $decodedData = base64_decode($base64Data);
                
                if ($decodedData) {
                    // Determine file extension
                    $ext = 'bin';
                    if (strpos($mimeType, 'image/') === 0) {
                        $ext = str_replace('image/', '', $mimeType);
                        if ($ext === 'jpeg') $ext = 'jpg';
                    } elseif ($mimeType === 'application/pdf') {
                        $ext = 'pdf';
                    } elseif (strpos($mimeType, 'audio/') === 0) {
                        $ext = str_replace('audio/', '', $mimeType);
                        if ($ext === 'mpeg') $ext = 'mp3';
                        if ($ext === 'webm') $ext = 'webm';
                    }
                    
                    $fileName = 'msg_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $uploadDir = __DIR__ . '/../uploads/messages/';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                    
                    $filePath = $uploadDir . $fileName;
                    if (file_put_contents($filePath, $decodedData)) {
                        $attachmentType = $type;
                        $attachmentPath = 'uploads/messages/' . $fileName;
                    }
                }
            }
        }
    }

    $ins = $pdo->prepare('INSERT INTO messages (teacher_id, student_id, sender_id, receiver_id, body, attachment_type, attachment_path) VALUES (?,?,?,?,?,?,?)');
    $ins->execute([$teacherId, $studentId, $user['id'], $receiverId, $body, $attachmentType, $attachmentPath]);
    $msgId = (int)$pdo->lastInsertId();

    $fetch = $pdo->prepare('SELECT id, sender_id, receiver_id, body, attachment_type, attachment_path, is_read, created_at, read_at FROM messages WHERE id = ?');
    $fetch->execute([$msgId]);
    $message = $fetch->fetch();

    echo json_encode([
        'message' => $message,
        'notification' => 'Message sent',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
