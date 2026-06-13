<?php
require_once __DIR__ . '/config.php';

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) { $data .= str_repeat('=', 4 - $remainder); }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function jwt_sign(array $payload, int $ttlSeconds = 3600): string {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    if (!isset($payload['iat'])) $payload['iat'] = $now;
    if (!isset($payload['exp'])) $payload['exp'] = $now + $ttlSeconds;
    $segments = [base64url_encode(json_encode($header)), base64url_encode(json_encode($payload))];
    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, JWT_SECRET, true);
    $segments[] = base64url_encode($signature);
    return implode('.', $segments);
}

function jwt_verify(string $token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$h64, $p64, $s64] = $parts;
    $headerJson = base64url_decode($h64);
    $payloadJson = base64url_decode($p64);
    $sig = base64url_decode($s64);
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!$header || !$payload) return false;
    if (($header['alg'] ?? '') !== 'HS256') return false;
    $expected = hash_hmac('sha256', $h64 . '.' . $p64, JWT_SECRET, true);
    if (!hash_equals($expected, $sig)) return false;
    if (isset($payload['exp']) && time() > $payload['exp']) return false;
    return $payload;
}

function get_authorization_header(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') { $h = $v; break; }
        }
    }
    return $h ?: null;
}

function auth_from_bearer(): ?array {
    session_start();
    $hdr = get_authorization_header();
    if (!$hdr || stripos($hdr, 'Bearer ') !== 0) return null;
    $token = trim(substr($hdr, 7));
    if ($token === '') return null;
    $payload = jwt_verify($token);
    if (!$payload) return null;
    if (!isset($payload['uid'])) return null;
    $stmt = db()->prepare('SELECT id,user_type,student_id,teacher_code,name FROM users WHERE id=?');
    $stmt->execute([$payload['uid']]);
    return $stmt->fetch() ?: null;
}
