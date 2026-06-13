<?php
require_once __DIR__ . '/../src/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Prevent directory listing
header('X-Robots-Tag: noindex, nofollow');

$user = current_user();

// Redirect logged-in users to their respective area; otherwise go to login.
if ($user) {
    if ($user['user_type'] === 'teacher') {
        header('Location: ' . app_href('teacher/manage_papers.php'));
        exit;
    }
    if ($user['user_type'] === 'admin') {
        header('Location: ' . app_href('admin/index.php'));
        exit;
    }
    // default to student papers
    header('Location: ' . app_href('student/papers.php'));
    exit;
}

header('Location: ' . app_href('login.php'));
exit;
?>