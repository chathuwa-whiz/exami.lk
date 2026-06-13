<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/refresh.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
// Hybrid limit by IP only (no user yet) 30/min
$ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
rate_limit_assert('api:auth_refresh_ip:'.$ip, 30, 60);
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['error'=>'method_not_allowed']); exit; }
$raw = file_get_contents('php://input');
$data = json_decode($raw,true);
$rt = isset($data['refresh_token']) ? trim($data['refresh_token']) : '';
if($rt===''){ http_response_code(400); echo json_encode(['error'=>'refresh_token_required']); exit; }
$row = verify_refresh_token($rt);
if(!$row){ http_response_code(401); echo json_encode(['error'=>'invalid_refresh']); exit; }
// Rate limit token issuance per user: 20/min
rate_limit_assert('api:auth_refresh_user:'.$row['user_id'], 20, 60);
$ttl = 3600; // fixed 1h access token
$access = jwt_sign(['uid'=>$row['user_id'],'ut'=>$row['user_type']], $ttl);
// Rotate refresh token
$newRefresh = rotate_refresh_token($rt, (int)$row['user_id'], 30);
// Audit log
$pdo = db();
$log = $pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
$log->execute([$row['user_id'],'refresh_jwt', json_encode(['old_token'=>$rt])]);
echo json_encode(['token'=>$access,'expires_in'=>$ttl,'refresh_token'=>$newRefresh]);
