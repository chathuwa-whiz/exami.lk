<?php
require_once __DIR__ . '/src/config.php';

$tables = [
    'teacher_student','teacher_subject','subjects','users',
    'rate_limits','refresh_tokens','audit_logs','preapproved_student_teachers','preapproved_students',
    'responses','attempts','paper_access','payments','answer_options','questions','papers'
];

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $t) {
    try {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
        echo "Dropped $t (if existed)\n";
    } catch (Throwable $e) {
        echo "Drop failed for $t: {$e->getMessage()}\n";
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
