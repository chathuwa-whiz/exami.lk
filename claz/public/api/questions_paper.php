<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user = current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
$paperId = (int)($_GET['paper_id'] ?? 0);
if($paperId<=0){ http_response_code(400); echo json_encode(['error'=>'paper_id_required']); exit; }
$pdo = db();
// Check ownership or admin
$ownStmt = $pdo->prepare('SELECT teacher_id FROM papers WHERE id=?');
$ownStmt->execute([$paperId]);
$paper = $ownStmt->fetch();
if(!$paper){ http_response_code(404); echo json_encode(['error'=>'paper_not_found']); exit; }
if(!($user['user_type']==='admin' || ($user['user_type']==='teacher' && (int)$paper['teacher_id']===$user['id']))){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
rate_limit_assert('api:questions_paper:'.$user['id'], 240, 60);
$qStmt = $pdo->prepare('SELECT id,question_text,marks,position,image_path FROM questions WHERE paper_id=? ORDER BY position ASC, id ASC');
$qStmt->execute([$paperId]);
$questions = $qStmt->fetchAll();
$ids = array_column($questions,'id');
$options = [];
if($ids){
  $in = implode(',', array_fill(0,count($ids),'?'));
  $oStmt = $pdo->prepare("SELECT id,question_id,option_text,is_correct,attachment_path FROM answer_options WHERE question_id IN ($in) ORDER BY id ASC");
  $oStmt->execute($ids);
  $rows = $oStmt->fetchAll();
  foreach($rows as $r){ $options[$r['question_id']][] = $r; }
}
foreach($questions as &$q){ $q['options'] = $options[$q['id']] ?? []; }
echo json_encode(['paper_id'=>$paperId,'questions'=>$questions]);
