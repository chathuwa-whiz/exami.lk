<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'admin') { http_response_code(403); echo 'Forbidden'; exit; }
$pdo = db();
$errors = []; $success = ''; $report = [];
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
          $success='Import processed successfully.';
        } catch(Exception $e){ $pdo->rollBack(); $errors[]='Import error: '.$e->getMessage(); }
      }
    }
  }
}
$hasHeader = $_SERVER['REQUEST_METHOD'] !== 'POST' ? true : isset($_POST['has_header']);
$reportCount = count($report);
render_header('Bulk Import');
?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-cloud-upload"></i></div>
    <div>
      <p class="stat-card-label">Max File Size</p>
      <p class="stat-card-value">2 MB</p>
      <p class="stat-card-sub">CSV files only</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-table"></i></div>
    <div>
      <p class="stat-card-label">Header Row</p>
      <p class="stat-card-value"><?= $hasHeader ? 'Skipped' : 'Included' ?></p>
      <p class="stat-card-sub">Toggle before uploading</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon <?= $reportCount > 0 ? 'yellow' : 'gray' ?>"><i class="bi bi-lightning<?= $reportCount > 0 ? '-fill' : '' ?>"></i></div>
    <div>
      <p class="stat-card-label">Latest Run</p>
      <p class="stat-card-value"><?= $reportCount ?></p>
      <p class="stat-card-sub">Lines processed</p>
    </div>
  </div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger" role="alert" aria-live="assertive"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
  <div class="alert alert-success" role="status" aria-live="polite"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-upload text-primary"></i> Import Preapproved Students</h2>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(app_href('admin/index.php')) ?>"><i class="bi bi-arrow-left"></i> Back to Students</a>
  </div>
  <div class="app-card-body">
    <p style="font-size:13px;color:var(--on-surface-muted);margin-bottom:1.25rem;">Upload a CSV to add students and link teachers in bulk.</p>
    <form method="post" enctype="multipart/form-data" class="row g-3" aria-label="Import students CSV">
      <?= csrf_field(); ?>
      <div class="col-12 col-md-6">
        <label class="form-label" for="csv">CSV File</label>
        <input type="file" class="form-control" id="csv" name="csv" accept=".csv" required>
      </div>
      <div class="col-12 col-md-6 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="1" id="has_header" name="has_header" <?= $hasHeader ? 'checked' : '' ?>>
          <label class="form-check-label" for="has_header">First row contains headers (skip it)</label>
        </div>
      </div>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2"><i class="bi bi-upload"></i> Run Import</button>
        <a href="<?= htmlspecialchars(app_href('admin/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="app-card h-100">
      <div class="app-card-header">
        <h2 class="app-card-title"><i class="bi bi-filetype-csv"></i> CSV Format</h2>
      </div>
      <div class="app-card-body">
        <p style="font-size:13px;color:var(--on-surface-muted);margin-bottom:.75rem;">Three columns: <code>student_id</code>, <code>name</code>, <code>teacher_ids</code> (semicolon-separated teacher IDs).</p>
        <pre style="background:var(--surface-subtle);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;font-size:12.5px;margin:0;overflow-x:auto;">student_id,name,teacher_ids
25C18379,Student One,12;15
25C18380,Student Two,12</pre>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="app-card h-100">
      <div class="app-card-header">
        <h2 class="app-card-title"><i class="bi bi-list-check"></i> Latest Report</h2>
        <?php if ($reportCount > 0): ?>
          <span class="badge-soft badge-soft-warning"><?= $reportCount ?> lines</span>
        <?php endif; ?>
      </div>
      <div class="app-card-body">
        <?php if ($report): ?>
          <ol class="mb-0 small d-flex flex-column gap-2" style="padding-left:1.25rem;">
            <?php foreach ($report as $r): ?>
              <li style="padding:.4rem .75rem;background:var(--surface-subtle);border-radius:var(--radius-sm);border:1px solid var(--border);">
                <?= htmlspecialchars($r) ?>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p style="color:var(--on-surface-muted);font-size:13px;margin:0;">Upload a CSV to view a processing report here.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php render_footer(); ?>
