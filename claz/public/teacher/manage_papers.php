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

<div class="app-content">
  <div class="container-xxl">
    <?php render_welcome_banner($user); ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Stats Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
      <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #6b7280; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
          <i class="bi bi-file-text me-1" style="color: #6b7280;"></i>Total Papers
        </p>
        <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #1f2937;">
          <?= count($papers) ?>
        </p>
      </div>
      <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
          <i class="bi bi-eye me-1" style="color: #10b981;"></i>Published
        </p>
        <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #10b981;">
          <?= $publishedCount ?>
        </p>
      </div>
      <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #9ca3af; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
          <i class="bi bi-pencil me-1" style="color: #9ca3af;"></i>Drafts
        </p>
        <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #6b7280;">
          <?= $draftCount ?>
        </p>
      </div>
    </div>

    <!-- Papers Section -->
    <div style="background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1f2937;">
          <i class="bi bi-file-earmark me-2"></i>Your Papers
        </h2>
        <a href="new_paper.php" class="btn btn-primary" style="background-color: #3b82f6; border-color: #3b82f6; border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600; font-size: 0.9rem;">
          <i class="bi bi-plus me-1"></i>Create New
        </a>
      </div>

      <?php if (empty($papers)): ?>
        <div style="padding: 3rem 2rem; background: #f3f4f6; border-radius: 8px; text-align: center; color: #6b7280;">
          <i class="bi bi-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #9ca3af;"></i>
          <p style="margin: 0; font-weight: 600;">No papers created yet</p>
          <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">
            <a href="new_paper.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Create your first paper</a>
          </p>
        </div>
      <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
          <?php foreach ($papers as $p): ?>
            <div style="padding: 1.5rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; transition: all 0.2s;">
              <div style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem; margin-bottom: 0.5rem;">
                  <p style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1f2937; flex: 1;">
                    <?= htmlspecialchars(substr($p['title'], 0, 40)) ?>
                  </p>
                  <span style="background: <?= $p['is_published'] ? '#dcfce7' : '#fef3c7' ?>; color: <?= $p['is_published'] ? '#166534' : '#92400e' ?>; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; white-space: nowrap;">
                    <?= $p['is_published'] ? '✓ Published' : '○ Draft' ?>
                  </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: 0.85rem; color: #6b7280; margin-bottom: 1rem;">
                  <div>
                    <p style="margin: 0 0 0.25rem 0; color: #9ca3af; font-weight: 600; font-size: 0.75rem;">Fee</p>
                    <p style="margin: 0; font-weight: 600; color: #1f2937;">
                      <?php if ($p['fee_cents'] > 0): ?>
                        Rs. <?= number_format($p['fee_cents'] / 100, 2) ?>
                      <?php else: ?>
                        Free
                      <?php endif; ?>
                    </p>
                  </div>
                  <div>
                    <p style="margin: 0 0 0.25rem 0; color: #9ca3af; font-weight: 600; font-size: 0.75rem;">Duration</p>
                    <p style="margin: 0; font-weight: 600; color: #1f2937;">
                      <?= intval($p['time_limit_seconds'] / 60) ?> min
                    </p>
                  </div>
                  <div>
                    <p style="margin: 0 0 0.25rem 0; color: #9ca3af; font-weight: 600; font-size: 0.75rem;">ID</p>
                    <p style="margin: 0; font-weight: 600; color: #1f2937;">
                      <?= $p['id'] ?>
                    </p>
                  </div>
                </div>
              </div>
              <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(app_href('teacher/edit_paper.php?paper_id=' . $p['id'])) ?>" style="flex: 1; min-width: 80px; text-align: center; font-size: 0.85rem;">
                  <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a class="btn btn-sm btn-outline-success" href="<?= htmlspecialchars(app_href('teacher/test_paper.php?paper_id=' . $p['id'])) ?>" style="flex: 1; min-width: 80px; text-align: center; font-size: 0.85rem;">
                  <i class="bi bi-play-fill me-1"></i>Test
                </a>
                <a class="btn btn-sm btn-outline-info" href="<?= htmlspecialchars(app_href('teacher/assign.php?paper_id=' . $p['id'])) ?>" style="flex: 1; min-width: 80px; text-align: center; font-size: 0.85rem;">
                  <i class="bi bi-people me-1"></i>Assign
                </a>
                <form method="post" class="d-inline" style="flex: 1; min-width: 80px;">
                  <?= csrf_field(); ?>
                  <input type="hidden" name="paper_id" value="<?= $p['id'] ?>">
                  <button name="toggle_publish" class="btn btn-sm btn-outline-secondary w-100" style="font-size: 0.85rem;">
                    <i class="bi <?= $p['is_published'] ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i><?= $p['is_published'] ? 'Unpub' : 'Pub' ?>
                  </button>
                </form>
                <form method="post" style="flex: 1; min-width: 80px;">
                  <?= csrf_field(); ?>
                  <input type="hidden" name="paper_id" value="<?= $p['id'] ?>">
                  <button name="delete_paper" class="btn btn-sm btn-outline-danger w-100" style="font-size: 0.85rem;" onclick="return confirm('Delete this paper? This cannot be undone.');">
                    <i class="bi bi-trash me-1"></i>Delete
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php render_footer(); ?>
