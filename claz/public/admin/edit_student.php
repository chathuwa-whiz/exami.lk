<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'admin') { http_response_code(403); echo 'Forbidden'; exit; }
$pdo = db();
$studentId = trim($_GET['student_id'] ?? '');
if ($studentId === '') { echo 'Missing student_id'; exit; }
$chk = $pdo->prepare('SELECT student_id,name FROM preapproved_students WHERE student_id=?');
$chk->execute([$studentId]);
$student = $chk->fetch();
if (!$student) { echo 'Student not found'; exit; }
$errors = [];$success='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify()) { $errors[] = 'Bad CSRF'; }
  else {
    if (isset($_POST['add_mapping'])) {
      $tid = (int)($_POST['teacher_id'] ?? 0);
      $tchk = $pdo->prepare('SELECT id FROM users WHERE id=? AND user_type="teacher"');
      $tchk->execute([$tid]);
      if (!$tchk->fetch()) { $errors[] = 'Invalid teacher'; }
      else {
        try {
          $ins = $pdo->prepare('INSERT INTO preapproved_student_teachers (student_id,teacher_id) VALUES (?,?)');
          $ins->execute([$studentId,$tid]);
          $log = $pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
          $log->execute([$user['id'],'map_teacher',json_encode(['student_id'=>$studentId,'teacher_id'=>$tid])]);
          $success = 'Teacher mapped.';
        } catch (Exception $e) { $errors[] = 'Map failed: '.$e->getMessage(); }
      }
    }
    if (isset($_POST['remove_mapping'])) {
      $tid = (int)($_POST['teacher_id'] ?? 0);
      $del = $pdo->prepare('DELETE FROM preapproved_student_teachers WHERE student_id=? AND teacher_id=?');
      $del->execute([$studentId,$tid]);
      $log = $pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
      $log->execute([$user['id'],'remove_mapping',json_encode(['student_id'=>$studentId,'teacher_id'=>$tid])]);
      $success = 'Mapping removed.';
    }
  }
}
$map = $pdo->prepare('SELECT u.id,u.name FROM preapproved_student_teachers pst JOIN users u ON pst.teacher_id=u.id WHERE pst.student_id=? ORDER BY u.name');
$map->execute([$studentId]);
$teachers = $map->fetchAll();
$allTeachers = $pdo->query('SELECT id,name FROM users WHERE user_type="teacher" ORDER BY name')->fetchAll();
$mappingCount = count($teachers);
render_header('Student Teacher Mapping');
?>
<section class="mb-4">
  <div class="row g-3">
    <div class="col-md-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Student ID</p>
        <h3 class="mb-0"><?= htmlspecialchars($studentId) ?></h3>
        <p class="muted small mb-0"><?= htmlspecialchars($student['name']) ?></p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Linked teachers</p>
        <h3 class="mb-0"><?= $mappingCount ?></h3>
        <p class="muted small mb-0">Active mappings</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Shortcuts</p>
        <p class="muted small mb-2">Keep the roster in sync with CSV imports.</p>
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(app_href('admin/index.php')) ?>">Back to dashboard</a>
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
    <h2 class="h5 mb-0">Current mappings</h2>
    <span class="badge-soft"><?= $mappingCount ?> linked</span>
  </div>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($teachers as $t): ?>
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded-4 px-3 py-2">
        <div>
          <p class="mb-0 fw-semibold"><?= htmlspecialchars($t['name']) ?></p>
          <span class="muted small">Teacher ID <?= $t['id'] ?></span>
        </div>
        <form method="post" class="d-flex" onsubmit="return confirm('Remove mapping?');">
          <?= csrf_field(); ?>
          <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
          <button name="remove_mapping" class="btn btn-outline-danger btn-sm">Remove</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (empty($teachers)): ?>
      <p class="muted mb-0">No teachers linked yet.</p>
    <?php endif; ?>
  </div>
</section>

<section class="app-card p-4">
  <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
    <div>
      <h2 class="h5 mb-1">Add new mapping</h2>
      <p class="muted small mb-0">Select a teacher to connect with <?= htmlspecialchars($student['name']) ?>.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(app_href('admin/import.php')) ?>">Go to Bulk Import</a>
  </div>
  <form method="post" class="row g-3 align-items-end">
    <?= csrf_field(); ?>
    <div class="col-md-6 col-lg-4">
      <label class="form-label small text-uppercase" for="teacher_id">Teacher</label>
      <select class="form-select" name="teacher_id" id="teacher_id" required>
        <option value="">Select teacher</option>
        <?php foreach ($allTeachers as $at): ?>
          <option value="<?= $at['id'] ?>"><?= htmlspecialchars($at['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 d-flex flex-wrap gap-2">
      <button name="add_mapping" class="btn btn-primary">Add Mapping</button>
      <a href="<?= htmlspecialchars(app_href('admin/index.php')) ?>" class="btn btn-outline-secondary">Back</a>
    </div>
  </form>
</section>
<?php render_footer(); ?>