<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user = current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
if($user['user_type']!=='teacher'){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
// Rate limit creation: 10 per hour per teacher
$ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
rate_limit_user_ip_assert($user['id'], $ip, 10, 30, 3600); // 10/hour per user, 30/hour per IP
if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['error'=>'method_not_allowed']); exit; }
// Expect JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw,true);
if(!is_array($data)) { echo json_encode(['error'=>'invalid_json']); exit; }
$title = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$fee_cents = (int)($data['fee_cents'] ?? 0);
$time_limit_seconds = (int)($data['time_limit_seconds'] ?? 0);
$is_published = (int)($data['is_published'] ?? 0) ? 1 : 0;
$errors = [];
if($title==='') $errors[]='title required';
if($time_limit_seconds < 30) $errors[]='time_limit_seconds must be >= 30';
if($fee_cents < 0) $errors[]='fee_cents must be >= 0';
if($errors){ http_response_code(422); echo json_encode(['error'=>'validation','messages'=>$errors]); exit; }
$pdo = db();
$stmt = $pdo->prepare('INSERT INTO papers (teacher_id,title,description,fee_cents,time_limit_seconds,is_published) VALUES (?,?,?,?,?,?)');
$stmt->execute([$user['id'],$title,$description,$fee_cents,$time_limit_seconds,$is_published]);
$paperId = (int)$pdo->lastInsertId();
// Audit log
$log = $pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
$log->execute([$user['id'],'api_create_paper', json_encode(['paper_id'=>$paperId])]);
echo json_encode(['ok'=>true,'paper_id'=>$paperId]);
