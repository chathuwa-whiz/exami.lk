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
rate_limit_user_ip_assert($user['id'], $ip, 200, 300, 60);
$fields=[]; $params=[];
if(isset($data['question_text'])){ $fields[]='question_text=?'; $params[]=trim($data['question_text']); }
if(isset($data['marks'])){ $m=(int)$data['marks']; if($m<=0){ http_response_code(422); echo json_encode(['error'=>'validation','messages'=>['marks must be > 0']]); exit; } $fields[]='marks=?'; $params[]=$m; }
if(isset($data['position'])){ $p=(int)$data['position']; if($p<1){ http_response_code(422); echo json_encode(['error'=>'validation','messages'=>['position must be >=1']]); exit; } $fields[]='position=?'; $params[]=$p; }

// Handle image_path update
if(isset($data['image_path'])){
  $imagePath = trim($data['image_path']);
  $finalImagePath = null;
  
  if(!empty($imagePath) && $imagePath !== 'null'){
    if(strpos($imagePath, 'data:image/') === 0){
      // Base64 encoded image
      $parts = explode(';', $imagePath);
      $imageData = explode(',', $parts[1]);
      $base64Data = $imageData[1];
      $decodedData = base64_decode($base64Data);
      
      // Get image type
      $imageType = 'png';
      if(strpos($imagePath, 'data:image/jpeg') === 0) $imageType = 'jpg';
      if(strpos($imagePath, 'data:image/gif') === 0) $imageType = 'gif';
      if(strpos($imagePath, 'data:image/webp') === 0) $imageType = 'webp';
      
      $fileName = 'q_' . $qRow['paper_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $imageType;
      $uploadDir = __DIR__ . '/../uploads/questions/';
      if(!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
      
      $filePath = $uploadDir . $fileName;
      if(file_put_contents($filePath, $decodedData)){
        $finalImagePath = 'uploads/questions/' . $fileName;
      }
    } else {
      // Direct path provided
      $finalImagePath = $imagePath;
    }
  }
  
  $fields[]='image_path=?';
  $params[]=$finalImagePath;
}

if($fields){
  $params[]=$qid;
  $upd=$pdo->prepare('UPDATE questions SET '.implode(',', $fields).' WHERE id=?');
  $upd->execute($params);
}
// Option replacement if provided
if(isset($data['options']) && is_array($data['options'])){
  // Delete existing options then reinsert
  $pdo->prepare('DELETE FROM answer_options WHERE question_id=?')->execute([$qid]);
  $oStmt=$pdo->prepare('INSERT INTO answer_options (question_id,option_text,is_correct) VALUES (?,?,?)');
  $correctCount=0;
  foreach($data['options'] as $opt){
    if(!is_array($opt)) continue;
    $ot=trim($opt['option_text'] ?? '');
    $ic=!empty($opt['is_correct'])?1:0;
    if($ot==='') continue;
    $oStmt->execute([$qid,$ot,$ic]);
    if($ic) $correctCount++;
  }
  if($correctCount===0){ // rollback options replacement to avoid zero correct
    http_response_code(422); echo json_encode(['error'=>'validation','messages'=>['at least one correct option required']]); exit;
  }
}
// Audit log
$log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
$log->execute([$user['id'],'api_question_update', json_encode(['question_id'=>$qid])]);
echo json_encode(['ok'=>true,'question_id'=>$qid]);
