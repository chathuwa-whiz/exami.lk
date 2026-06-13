<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/jwt.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
session_start();
$user = current_user();
if(!$user){ $user = auth_from_bearer(); }
if(!$user){ http_response_code(401); echo json_encode(['error'=>'unauthenticated']); exit; }
if($user['user_type']!=='student'){ http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
rate_limit_assert('api:papers_student:'.$user['id'], 120, 60);
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['perPage'] ?? 25)));
$allowedSort = ['created_at','title'];
$sort = $_GET['sort'] ?? 'created_at'; if(!in_array($sort,$allowedSort,true)) $sort='created_at';
$dir = strtolower($_GET['dir'] ?? 'desc'); if(!in_array($dir,['asc','desc'],true)) $dir='desc';
$pdo = db();
$params = [$user['id']];
$titleFilter='';
if($q!==''){ $titleFilter=' AND p.title LIKE ?'; $params[]='%'.$q.'%'; }
// Accessible = have paper_access + paper published
$countSql = "SELECT COUNT(*) FROM papers p JOIN paper_access pa ON pa.paper_id=p.id WHERE pa.user_id=? AND p.is_published=1$titleFilter";
$countStmt = $pdo->prepare($countSql); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn();
$totalPages = max(1,(int)ceil($total/$perPage)); if($page>$totalPages){$page=$totalPages;}
$offset = ($page-1)*$perPage;
$sql = "SELECT p.id,p.title,p.fee_cents,p.time_limit_seconds,p.is_published,p.created_at FROM papers p JOIN paper_access pa ON pa.paper_id=p.id WHERE pa.user_id=? AND p.is_published=1$titleFilter ORDER BY $sort $dir, p.id ASC LIMIT $offset,$perPage";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
echo json_encode(['page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'data'=>$rows]);
