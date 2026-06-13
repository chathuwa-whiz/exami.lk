<?php
require_once __DIR__ . '/config.php';

function create_refresh_token(int $userId, int $ttlDays = 30): string {
    $pdo = db();
    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+'.$ttlDays.' days'));
    $stmt = $pdo->prepare('INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?,?,?)');
    $stmt->execute([$userId, $token, $expires->format('Y-m-d H:i:s')]);
    return $token;
}

function verify_refresh_token(string $token): ?array {
    if($token==='') return null;
    $pdo = db();
    $stmt = $pdo->prepare('SELECT rt.id, rt.user_id, rt.expires_at, rt.revoked, u.user_type, u.name FROM refresh_tokens rt JOIN users u ON rt.user_id=u.id WHERE rt.token=? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if(!$row) return null;
    if((int)$row['revoked']===1) return null;
    if(strtotime($row['expires_at']) < time()) return null;
    return $row; // basic user fields accessible
}

function revoke_refresh_token(string $token): void {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE refresh_tokens SET revoked=1 WHERE token=?');
    $stmt->execute([$token]);
}

function rotate_refresh_token(string $oldToken, int $userId, int $ttlDays = 30): ?string {
    revoke_refresh_token($oldToken);
    return create_refresh_token($userId, $ttlDays);
}
