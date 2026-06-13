<?php
require_once __DIR__ . '/../../src/config.php';
require_login();
$user=current_user();
if($user['user_type']!=='admin'){ http_response_code(403); echo 'Forbidden'; exit; }
$pdo=db();
$search=trim($_GET['q']??'');
$allowedSort=['student_id','name','created_at'];
$sort=$_GET['sort']??'created_at';
if(!in_array($sort,$allowedSort,true)) $sort='created_at';
$dir=strtolower($_GET['dir']??'desc');
if(!in_array($dir,['asc','desc'],true)) $dir='desc';
$whereClauses = ['is_deleted=0'];
$params = [];
if($search!==''){
	$whereClauses[] = '(student_id LIKE ? OR name LIKE ?)';
	$like = '%'.$search.'%';
	$params[] = $like;
	$params[] = $like;
}
$whereSql = implode(' AND ', $whereClauses);
$sql = "SELECT student_id,name,created_at FROM preapproved_students WHERE $whereSql ORDER BY $sort $dir, student_id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows=$stmt->fetchAll();
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="preapproved_export.csv"');
$out=fopen('php://output','w');
fputcsv($out,['student_id','name','created_at']);
foreach($rows as $r){ fputcsv($out,[$r['student_id'],$r['name'],$r['created_at']]); }
fclose($out);
exit;