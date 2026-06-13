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
rate_limit_assert('api:audit_logs:'.$user['id'], 60, 60); // lower limit due to potential heavy output
$action=trim($_GET['action']??'');
$actor=(int)($_GET['user_id']??0);
$from=trim($_GET['from']??'');
$to=trim($_GET['to']??'');
$page=max(1,(int)($_GET['page']??1));
$perPage=min(200,max(1,(int)($_GET['perPage']??50)));
$pdo=db();
$whereParts=[]; $params=[];
if($action!==''){ $whereParts[]='action LIKE ?'; $params[]='%'.$action.'%'; }
if($actor>0){ $whereParts[]='user_id=?'; $params[]=$actor; }
if($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $whereParts[]='created_at >= ?'; $params[]=$from.' 00:00:00'; }
if($to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $whereParts[]='created_at <= ?'; $params[]=$to.' 23:59:59'; }
$where=$whereParts?('WHERE '.implode(' AND ',$whereParts)) : '';
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM audit_logs $where"); $countStmt->execute($params); $total=(int)$countStmt->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage)); if($page>$totalPages){$page=$totalPages;}
$offset=($page-1)*$perPage;
$sql="SELECT al.id,al.user_id,u.name,al.action,al.details,al.created_at FROM audit_logs al JOIN users u ON al.user_id=u.id $where ORDER BY al.created_at DESC LIMIT $offset,$perPage";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
echo json_encode(['page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'data'=>$rows]);
