<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/rate_limit.php';
header('Content-Type: application/json');
// Public-ish endpoint used during registration; rate limit by IP only
$ip = $_SERVER['REMOTE_ADDR'] ?? 'ip';
rate_limit_assert('api:preapproved_teachers:'.$ip, 30, 60);
$studentId = trim($_GET['student_id'] ?? '');
if ($studentId === '') { echo json_encode(['ok'=>false,'error'=>'student_id required']); exit; }
$pdo = db();
// Validate student id exists
$chk = $pdo->prepare('SELECT student_id,name FROM preapproved_students WHERE student_id=?');
$chk->execute([$studentId]);
$student = $chk->fetch();
if (!$student) { echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
// List teachers linked
$tStmt = $pdo->prepare('SELECT u.id,u.name FROM preapproved_student_teachers pst JOIN users u ON pst.teacher_id=u.id WHERE pst.student_id=? ORDER BY u.name');
$tStmt->execute([$studentId]);
$teachers = $tStmt->fetchAll();
echo json_encode(['ok'=>true,'student'=>$student,'teachers'=>$teachers]);
