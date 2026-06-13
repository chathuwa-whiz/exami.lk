<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user=current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
if($user['user_type']!=='teacher' && $user['user_type']!=='admin'){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['error'=>'method_not_allowed']); exit; }
$raw=file_get_contents('php://input');
$data=json_decode($raw,true);
if(!is_array($data)){ http_response_code(400); echo json_encode(['error'=>'invalid_json']); exit; }
$qid=(int)($data['question_id'] ?? 0);
if($qid<=0){ http_response_code(400); echo json_encode(['error'=>'question_id_required']); exit; }
$pdo=db();
$qStmt=$pdo->prepare('SELECT q.id,q.paper_id,p.teacher_id FROM questions q JOIN papers p ON q.paper_id=p.id WHERE q.id=?');
$qStmt->execute([$qid]);
$qRow=$qStmt->fetch();
if(!$qRow){ http_response_code(404); echo json_encode(['error'=>'question_not_found']); exit; }
if(!($user['user_type']==='admin' || (int)$qRow['teacher_id']===$user['id'])){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
$ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
rate_limit_user_ip_assert($user['id'], $ip, 50, 80, 60); // lower limits for deletes
// Hard delete (cascade for options)
$pdo->prepare('DELETE FROM questions WHERE id=?')->execute([$qid]);
// Audit log
$log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
$log->execute([$user['id'],'api_question_delete', json_encode(['question_id'=>$qid])]);
echo json_encode(['ok'=>true]);
