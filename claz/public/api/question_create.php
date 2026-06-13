<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user = current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
if($user['user_type']!=='teacher' && $user['user_type']!=='admin'){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); echo json_encode(['error'=>'method_not_allowed']); exit; }
$raw = file_get_contents('php://input');
$data = json_decode($raw,true);
if(!is_array($data)){ http_response_code(400); echo json_encode(['error'=>'invalid_json']); exit; }
$paperId = (int)($data['paper_id'] ?? 0);
$qText = trim($data['question_text'] ?? '');
$marks = (int)($data['marks'] ?? 1);
$position = isset($data['position']) ? (int)$data['position'] : null;
$options = $data['options'] ?? [];
$imagePath = trim($data['image_path'] ?? ''); // Base64 or file path
$errors=[];
if($paperId<=0) $errors[]='paper_id required';
if($qText==='') $errors[]='question_text required';
if($marks<=0) $errors[]='marks must be > 0';
if(!is_array($options) || count($options)<1) $errors[]='at least one option required';
if($errors){ http_response_code(422); echo json_encode(['error'=>'validation','messages'=>$errors]); exit; }
$pdo=db();
// ownership check
$own = $pdo->prepare('SELECT teacher_id FROM papers WHERE id=?');
$own->execute([$paperId]);
$p=$own->fetch();
if(!$p){ http_response_code(404); echo json_encode(['error'=>'paper_not_found']); exit; }
if(!($user['user_type']==='admin' || (int)$p['teacher_id']===$user['id'])){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
$ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
rate_limit_user_ip_assert($user['id'], $ip, 100, 200, 60); // user 100/min, ip 200/min

// Handle image upload
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
        
        $fileName = 'q_' . $paperId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $imageType;
        $uploadDir = __DIR__ . '/../uploads/questions/';
        if(!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        
        $filePath = $uploadDir . $fileName;
        if(file_put_contents($filePath, $decodedData)){
            $finalImagePath = 'uploads/questions/' . $fileName;
        }
    } else if(file_exists(__DIR__ . '/../' . ltrim($imagePath, '/'))){
        // Direct file path
        $finalImagePath = $imagePath;
    }
}

if($position===null){
  $posStmt=$pdo->prepare('SELECT COALESCE(MAX(position),0)+1 FROM questions WHERE paper_id=?');
  $posStmt->execute([$paperId]);
  $position=(int)$posStmt->fetchColumn();
} else {
  // Shift existing questions at and after this position
  $shiftStmt=$pdo->prepare('UPDATE questions SET position=position+1 WHERE paper_id=? AND position>=?');
  $shiftStmt->execute([$paperId,$position]);
}
$qStmt=$pdo->prepare('INSERT INTO questions (paper_id,question_text,marks,position,image_path) VALUES (?,?,?,?,?)');
$qStmt->execute([$paperId,$qText,$marks,$position,$finalImagePath]);
$qid=(int)$pdo->lastInsertId();
$oStmt=$pdo->prepare('INSERT INTO answer_options (question_id,option_text,is_correct) VALUES (?,?,?)');
$correctCount=0;
foreach($options as $opt){
  if(!is_array($opt)) continue;
  $ot = trim($opt['option_text'] ?? '');
  $ic = !empty($opt['is_correct']) ? 1 : 0;
  if($ot==='') continue;
  $oStmt->execute([$qid,$ot,$ic]);
  if($ic) $correctCount++;
}
if($correctCount===0){ // enforce at least one correct
  $pdo->prepare('DELETE FROM questions WHERE id=?')->execute([$qid]);
  $pdo->prepare('DELETE FROM answer_options WHERE question_id=?')->execute([$qid]);
  http_response_code(422); echo json_encode(['error'=>'validation','messages'=>['at least one correct option required']]); exit;
}
// Audit log
$log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
$log->execute([$user['id'],'api_question_create', json_encode(['question_id'=>$qid,'paper_id'=>$paperId])]);
http_response_code(201); echo json_encode(['question_id'=>$qid,'image_path'=>$finalImagePath]); exit;
echo json_encode(['ok'=>true,'question_id'=>$qid]);
