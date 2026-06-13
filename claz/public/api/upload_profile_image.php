<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

try {
    require_login();
    
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Check if file was uploaded
    if (!isset($_FILES['profile_image'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['profile_image'];

    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP']);
        exit;
    }

    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['error' => 'File size exceeds 5MB limit']);
        exit;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload error: ' . $file['error']]);
        exit;
    }

    // Create upload directory
    $upload_dir = __DIR__ . '/../uploads/profiles';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $user['id'] . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;
    $relative_path = 'uploads/profiles/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
        exit;
    }

    // Delete old profile image if exists
    $pdo = db();
    $stmt = $pdo->prepare('SELECT profile_image FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $old_user = $stmt->fetch();

    if ($old_user && $old_user['profile_image']) {
        $old_path = __DIR__ . '/../' . $old_user['profile_image'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }

    // Update user profile image in database
    $stmt = $pdo->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
    $stmt->execute([$relative_path, $user['id']]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile image updated successfully',
        'image_url' => app_href($relative_path)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
