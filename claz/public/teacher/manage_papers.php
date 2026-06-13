<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';

require_login();
$user = current_user();
if ($user['user_type'] !== 'teacher') {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$pdo = db();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify()) {
    $errors[] = 'Bad CSRF';
  } else {
    $paperId = (int)($_POST['paper_id'] ?? 0);
    if (isset($_POST['toggle_publish'])) {
      $stmt = $pdo->prepare('UPDATE papers SET is_published=1-is_published WHERE id=? AND teacher_id=?');
      $stmt->execute([$paperId, $user['id']]);
      $success = 'Toggled publish state.';
    } elseif (isset($_POST['delete_paper'])) {
      try {
        $pdo->beginTransaction();
        // Delete answer options
        $pdo->prepare('DELETE FROM answer_options WHERE question_id IN (SELECT id FROM questions WHERE paper_id=?)')->execute([$paperId]);
        // Delete questions
        $pdo->prepare('DELETE FROM questions WHERE paper_id=?')->execute([$paperId]);
        // Delete paper
        $stmt = $pdo->prepare('DELETE FROM papers WHERE id=? AND teacher_id=?');
        $stmt->execute([$paperId, $user['id']]);
        $pdo->commit();
        $success = 'Paper deleted successfully.';
      } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Error deleting paper: ' . $e->getMessage();
      }
    }
  }
}

$stmt = $pdo->prepare('SELECT id,title,is_published,fee_cents,time_limit_seconds FROM papers WHERE teacher_id=? ORDER BY id DESC');
$stmt->execute([$user['id']]);
$papers = $stmt->fetchAll();
$publishedCount = count(array_filter($papers, fn($p) => $p['is_published']));
$draftCount = count($papers) - $publishedCount;

render_header('Manage Papers');
?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-journal-text"></i></div>
    <div>
      <p class="stat-card-label">Total Papers</p>
      <p class="stat-card-value"><?= count($papers) ?></p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-eye-fill"></i></div>
    <div>
      <p class="stat-card-label">Published</p>
      <p class="stat-card-value"><?= $publishedCount ?></p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-pencil"></i></div>
    <div>
      <p class="stat-card-label">Drafts</p>
      <p class="stat-card-value"><?= $draftCount ?></p>
    </div>
  </div>
</div>

<!-- Papers Card -->
<div class="app-card">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-file-earmark text-primary"></i> Your Papers</h2>
    <a href="<?= app_href('teacher/create_paper.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Create New</a>
  </div>
  <div class="app-card-body">
    <?php if (empty($papers)): ?>
      <div class="text-center py-5">
        <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--muted-light);display:block;margin-bottom:12px;"></i>
        <p class="fw-semibold mb-1" style="color:var(--on-surface);">No papers yet</p>
        <a href="<?= app_href('teacher/create_paper.php') ?>" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus"></i> Create your first paper</a>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($papers as $p): ?>
        <div class="col-md-6 col-lg-4">
          <div class="item-card h-100 d-flex flex-column" style="gap:0;">
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <p class="fw-semibold mb-0" style="font-size:14px;color:var(--on-surface);flex:1;padding-right:8px;"><?= htmlspecialchars(substr($p['title'], 0, 45)) ?></p>
                <?php if ($p['is_published']): ?>
                  <span class="badge-soft badge-soft-success flex-shrink-0"><i class="bi bi-check2"></i> Live</span>
                <?php else: ?>
                  <span class="badge-soft badge-soft-gray flex-shrink-0">Draft</span>
                <?php endif; ?>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge-soft badge-soft-gray"><i class="bi bi-hourglass"></i> <?= intval($p['time_limit_seconds'] / 60) ?> min</span>
                <?php if ($p['fee_cents'] > 0): ?>
                  <span class="badge-soft badge-soft-primary"><i class="bi bi-tag"></i> Rs <?= number_format($p['fee_cents'] / 100, 2) ?></span>
                <?php else: ?>
                  <span class="badge-soft badge-soft-success"><i class="bi bi-check-circle"></i> Free</span>
                <?php endif; ?>
                <span class="badge-soft badge-soft-gray" style="font-size:10.5px;">ID: <?= $p['id'] ?></span>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 pt-3" style="border-top:1px solid var(--border);">
              <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(app_href('teacher/edit_paper.php?paper_id=' . $p['id'])) ?>"><i class="bi bi-pencil"></i> Edit</a>
              <a class="btn btn-outline-success btn-sm" href="<?= htmlspecialchars(app_href('teacher/test_paper.php?paper_id=' . $p['id'])) ?>"><i class="bi bi-play-fill"></i> Test</a>
              <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(app_href('teacher/assign.php?paper_id=' . $p['id'])) ?>"><i class="bi bi-people"></i> Assign</a>
              <form method="post" class="d-inline">
                <?= csrf_field(); ?>
                <input type="hidden" name="paper_id" value="<?= $p['id'] ?>">
                <button name="toggle_publish" class="btn btn-sm btn-outline-secondary">
                  <i class="bi <?= $p['is_published'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i> <?= $p['is_published'] ? 'Unpub' : 'Pub' ?>
                </button>
              </form>
              <form method="post">
                <?= csrf_field(); ?>
                <input type="hidden" name="paper_id" value="<?= $p['id'] ?>">
                <button name="delete_paper" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this paper? Cannot be undone.');">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
