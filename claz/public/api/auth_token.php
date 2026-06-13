<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
require_once __DIR__ . '/../../src/refresh.php';
header('Content-Type: application/json');
require_login();
$user = current_user();
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
// Rate limit token issuance: 5 per minute per user
rate_limit_assert('auth_token:'.$user['id'], 5, 60);
$ttl = (int)($_GET['ttl'] ?? 3600); if($ttl < 300) $ttl = 300; if($ttl > 86400) $ttl = 86400; // clamp 5m - 24h
$includeRefresh = isset($_GET['include_refresh']);
$token = jwt_sign(['uid'=>$user['id'],'ut'=>$user['user_type']], $ttl);
$refreshToken = null;
if($includeRefresh){
	$refreshToken = create_refresh_token($user['id'], 30); // 30 day default
}
// Audit log
$pdo = db();
$log = $pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
$log->execute([$user['id'],'issue_jwt', json_encode(['ttl'=>$ttl,'refresh_issued'=>$includeRefresh?1:0])]);
$resp = ['token'=>$token,'expires_in'=>$ttl];
if($refreshToken){ $resp['refresh_token']=$refreshToken; }
echo json_encode($resp);
