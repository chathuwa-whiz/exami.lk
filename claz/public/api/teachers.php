<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user=current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
if($user['user_type']!=='admin'){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
rate_limit_assert('api:teachers:'.$user['id'], 120, 60);
$q=trim($_GET['q']??'');
$page=max(1,(int)($_GET['page']??1));
$perPage=min(100,max(1,(int)($_GET['perPage']??25)));
$allowedSort=['name','teacher_code','created_at'];
$sort=$_GET['sort']??'name'; if(!in_array($sort,$allowedSort,true)) $sort='name';
$dir=strtolower($_GET['dir']??'asc'); if(!in_array($dir,['asc','desc'],true)) $dir='asc';
$pdo=db();
$params=[]; $where='WHERE user_type="teacher"';
if($q!==''){ $where.=' AND (name LIKE ? OR teacher_code LIKE ?)'; $like='%'.$q.'%'; $params[]=$like; $params[]=$like; }
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM users $where"); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage)); if($page>$totalPages){$page=$totalPages;}
$offset=($page-1)*$perPage;
$sql="SELECT id,name,teacher_code,email,created_at FROM users $where ORDER BY $sort $dir, id ASC LIMIT $offset,$perPage";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
echo json_encode(['page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'data'=>$rows]);
