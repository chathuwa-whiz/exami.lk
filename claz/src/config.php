<?php
// Set internal encoding to UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

// Basic configuration for ceylonstudyhub
const DB_HOST = 'localhost';
const DB_NAME = 'admin_exami';
const DB_USER = 'admin_exami';
const DB_PASS = 'Ravindu2001@';
// Base URL path for the application relative to web root (stable root of /public)
// Previous logic used dirname(SCRIPT_NAME) which caused APP_BASE to vary for nested folders (e.g. /claz/public/student).
// We instead lock APP_BASE to the /public segment so URLs remain consistent regardless of script depth.
if (!defined('APP_BASE')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $publicSeg = '/public/';
    $base = '';
    $pos = strpos($script, $publicSeg);
    if ($pos !== false) {
        $base = substr($script, 0, $pos + strlen('/public'));
    } else {
        // Fallback: use dirname but trim trailing slashes.
        $base = rtrim(dirname($script), '/');
        if ($base === '/' || $base === '\\') { $base = ''; }
    }
    define('APP_BASE', $base); // e.g. '/claz/public' or ''
}

function app_href(string $path): string {
    $base = APP_BASE;
    if ($base === '') { return '/' . ltrim($path,'/'); }
    return $base . '/' . ltrim($path,'/');
}

// JWT secret (change this for production; can load from env)
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'CHANGE_ME_DEVELOPMENT_SECRET');
}

// Gemini API key used for PDF question extraction endpoint
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', 'AIzaSyDFQQ4AOaKuu0G-wx3rvLR_fQzUoOg2kL0');
}

// Timezone
date_default_timezone_set('Asia/Colombo');

// Currency (LKR - Sri Lankan Rupee)
if (!defined('CURRENCY')) {
    define('CURRENCY', 'LKR');
}
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'Rs.');
}

// PayHere Payment Gateway (Sri Lanka)
// Get your keys from https://www.payhere.lk/
const PAYHERE_MERCHANT_ID = 'YOUR_MERCHANT_ID';
const PAYHERE_MERCHANT_SECRET = 'YOUR_MERCHANT_SECRET';
const PAYHERE_MODE = 'sandbox'; // 'sandbox' or 'live'

// Support contact configuration
const SUPPORT_PHONE = '+94-XX-XXXXXXX'; // replace with real number
const SUPPORT_WHATSAPP = 'https://wa.me/94XXXXXXXXX'; // replace with real WhatsApp number

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        // Ensure UTF-8 encoding for this session (belt and suspenders)
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        $pdo->exec("SET character_set_client = utf8mb4");
        $pdo->exec("SET character_set_results = utf8mb4");
        $pdo->exec("SET character_set_connection = utf8mb4");
    }
    return $pdo;
}

function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

function require_login(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . app_href('login.php'));
        exit;
    }
}

function current_user(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, user_type, student_id, teacher_code, name, email, profile_image FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function support_message(): string {
    return 'Not our student. Please call ' . SUPPORT_PHONE . ' or WhatsApp via <a href="' . SUPPORT_WHATSAPP . '" target="_blank">support</a>.';
}
?>