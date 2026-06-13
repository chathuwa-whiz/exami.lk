<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user = current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
if($user['user_type']!=='admin'){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
rate_limit_assert('api:preapproved_students:'.$user['id'], 120, 60);
$q=trim($_GET['q']??'');
$page=max(1,(int)($_GET['page']??1));
$perPage=min(100,max(1,(int)($_GET['perPage']??25))); // clamp 1-100
$allowedSort=['student_id','name','created_at'];
$sort=$_GET['sort']??'created_at'; if(!in_array($sort,$allowedSort,true)) $sort='created_at';
$dir=strtolower($_GET['dir']??'desc'); if(!in_array($dir,['asc','desc'],true)) $dir='desc';
$pdo=db();
$params=[]; $where='WHERE is_deleted=0';
if($q!==''){ $where.=' AND (student_id LIKE ? OR name LIKE ?)'; $like='%'.$q.'%'; $params[]=$like; $params[]=$like; }
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM preapproved_students $where"); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage)); if($page>$totalPages){$page=$totalPages;}
$offset=($page-1)*$perPage;
$sql="SELECT student_id,name,created_at FROM preapproved_students $where ORDER BY $sort $dir, student_id ASC LIMIT $offset,$perPage";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
echo json_encode(['page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'data'=>$rows]);
