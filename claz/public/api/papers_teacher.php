<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user = current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
if(!in_array($user['user_type'], ['teacher','admin'], true)){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
rate_limit_assert('api:papers_teacher:'.$user['id'], 120, 60);
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['perPage'] ?? 25)));
$allowedSort = ['created_at','title','is_published'];
$sort = $_GET['sort'] ?? 'created_at'; if(!in_array($sort,$allowedSort,true)) $sort='created_at';
$dir = strtolower($_GET['dir'] ?? 'desc'); if(!in_array($dir,['asc','desc'],true)) $dir='desc';
$pdo = db();
$params=[]; $conditions=[];
if($user['user_type']==='teacher'){ $conditions[]='teacher_id=?'; $params[]=$user['id']; }
if($q!==''){ $conditions[]='title LIKE ?'; $params[]='%'.$q.'%'; }
$where = $conditions? ('WHERE '.implode(' AND ',$conditions)) : '';
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM papers $where");
$countStmt->execute($params); $total = (int)$countStmt->fetchColumn();
$totalPages = max(1,(int)ceil($total/$perPage)); if($page>$totalPages){$page=$totalPages;}
$offset = ($page-1)*$perPage;
$sql = "SELECT id,title,fee_cents,time_limit_seconds,is_published,created_at FROM papers $where ORDER BY $sort $dir, id ASC LIMIT $offset,$perPage";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
echo json_encode(['page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'data'=>$rows]);
