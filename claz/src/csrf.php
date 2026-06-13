<?php
session_start();
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
function csrf_verify(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $sent = $_POST['_csrf'] ?? '';
    $valid = hash_equals($_SESSION['csrf_token'] ?? '', $sent);
    if ($valid) { // rotate token after successful POST to reduce replay window
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $valid;
}
?>