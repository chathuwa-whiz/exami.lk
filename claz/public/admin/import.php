<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'admin') { http_response_code(403); echo 'Forbidden'; exit; }
$pdo = db();
$errors = [];$success='';$report=[];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify()) { $errors[] = 'Bad CSRF'; }
  elseif (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Upload failed'; }
  else {
    $tmp = $_FILES['csv']['tmp_name'];
    if (filesize($tmp) > 2*1024*1024) { $errors[] = 'File too large (max 2MB)'; }
    else {
      $fh = fopen($tmp,'r');
      if (!$fh) { $errors[] = 'Cannot read file'; }
      else {
        $lineNum=0; $pdo->beginTransaction();
        try {
          while(($row=fgetcsv($fh))!==false){
            $lineNum++;
            if($lineNum===1 && isset($_POST['has_header'])) { continue; }
            if(count($row)<3){ $report[] = "Line $lineNum: skipped (need 3 columns)"; continue; }
            [$sid,$sname,$teacherList]=$row;
            $sid=trim($sid); $sname=trim($sname); $teacherList=trim($teacherList);
            if($sid===''){ $report[] = "Line $lineNum: empty student_id"; continue; }
            $exists=$pdo->prepare('SELECT 1 FROM preapproved_students WHERE student_id=?');
            $exists->execute([$sid]);
            if(!$exists->fetch()){
              $ins=$pdo->prepare('INSERT INTO preapproved_students (student_id,name) VALUES (?,?)');
              $ins->execute([$sid,$sname]);
              $report[]="Line $lineNum: student added";
              $log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
              $log->execute([$user['id'],'add_preapproved',json_encode(['student_id'=>$sid])]);
            } else { $report[]="Line $lineNum: student exists"; }
            if($teacherList!==''){
              $ids=array_filter(array_map('trim', explode(';',$teacherList)), fn($v)=>$v!=='');
              foreach($ids as $tid){
                $tidInt=(int)$tid;
                $tchk=$pdo->prepare('SELECT id FROM users WHERE id=? AND user_type="teacher"');
                $tchk->execute([$tidInt]);
                if($tchk->fetch()){
                  $map=$pdo->prepare('INSERT INTO preapproved_student_teachers (student_id,teacher_id) VALUES (?,?) ON DUPLICATE KEY UPDATE student_id=student_id');
                  $map->execute([$sid,$tidInt]);
                  $log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
                  $log->execute([$user['id'],'map_teacher',json_encode(['student_id'=>$sid,'teacher_id'=>$tidInt])]);
                } else { $report[]="Line $lineNum: teacher $tid not found"; }
              }
            }
          }
          fclose($fh);
          $pdo->commit();
          $success='Import processed.';
        } catch(Exception $e){ $pdo->rollBack(); $errors[]='Import error: '.$e->getMessage(); }
      }
    }
  }
}
$hasHeader = $_SERVER['REQUEST_METHOD'] !== 'POST' ? true : isset($_POST['has_header']);
$reportCount = count($report);
render_header('Bulk Import');
?>
<section class="mb-4">
  <div class="row g-3">
    <div class="col-md-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Max file size</p>
        <h3 class="mb-0">2 MB</h3>
        <p class="muted small mb-0">CSV only</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Header row</p>
        <h3 class="mb-0"><?= $hasHeader ? 'Skipped' : 'Included' ?></h3>
        <p class="muted small mb-0">Toggle before uploading</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Latest run</p>
        <h3 class="mb-0"><?= $reportCount ?> entries</h3>
        <p class="muted small mb-0">Only updates after upload</p>
      </div>
    </div>
  </div>
</section>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger" role="alert" aria-live="assertive"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
  <div class="alert alert-success" role="status" aria-live="polite"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<section class="app-card p-4 mb-4">
  <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
    <div>
      <h2 class="h5 mb-1">Import preapproved students</h2>
      <p class="muted small mb-0">Upload a CSV to add students and link teachers in bulk.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(app_href('admin/index.php')) ?>">Back to dashboard</a>
  </div>
  <form method="post" enctype="multipart/form-data" class="row g-3" aria-label="Import students CSV">
    <?= csrf_field(); ?>
    <div class="col-12 col-md-6">
      <label class="form-label small text-uppercase" for="csv">CSV file</label>
      <input type="file" class="form-control" id="csv" name="csv" accept=".csv" required>
    </div>
    <div class="col-12 col-md-6 d-flex align-items-center">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1" id="has_header" name="has_header" <?= $hasHeader ? 'checked' : '' ?>>
        <label class="form-check-label" for="has_header">First row contains headers</label>
      </div>
    </div>
    <div class="col-12 d-flex flex-wrap gap-2">
      <button type="submit" class="btn btn-primary">Run import</button>
      <a href="<?= htmlspecialchars(app_href('admin/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</section>

<section class="row g-4">
  <div class="col-lg-6">
    <div class="app-card p-4 h-100">
      <h3 class="h6 text-uppercase text-muted mb-3">CSV format</h3>
      <p class="muted small">Columns: <code>student_id</code>, <code>name</code>, <code>teacher_ids</code> (semicolon separated).</p>
      <pre class="code mb-0">student_id,name,teacher_ids
25C18379,Student One,12;15
25C18380,Student Two,12</pre>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="app-card p-4 h-100">
      <h3 class="h6 text-uppercase text-muted mb-3">Latest report</h3>
      <?php if ($report): ?>
        <ol class="mb-0 small d-flex flex-column gap-2">
          <?php foreach ($report as $r): ?>
            <li class="border rounded-4 px-3 py-2 bg-body-secondary-subtle">
              <?= htmlspecialchars($r) ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <p class="muted mb-0">Upload a CSV to view a processing report.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php render_footer(); ?>