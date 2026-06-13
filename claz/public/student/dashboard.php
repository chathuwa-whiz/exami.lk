<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!isset($_SESSION['user_id'])) {
  header('Location: ' . app_href('login.php'));
  exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, email, user_type FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
  header('Location: ' . app_href('login.php'));
  exit;
}

$isStudent = ($user['user_type'] === 'student');
$title = $isStudent ? 'Student Dashboard' : 'Dashboard';

render_header($title, [], $user);
?>

<?php render_welcome_banner($user); ?>

<?php
// Fetch teachers
try {
  $q = $pdo->prepare('SELECT t.id, u.name, u.email
                       FROM teacher_student ts
                       JOIN users u ON u.id = ts.teacher_id
                       JOIN users t ON t.id = ts.student_id
                       WHERE ts.student_id = ? AND u.user_type = "teacher"');
  $q->execute([$user['id']]);
  $teachers = $q->fetchAll();
} catch (Throwable $e) {
  $teachers = [];
}

// Fetch papers
$pSql = "SELECT p.id, p.title, p.time_limit_seconds, u.name AS teacher_name
         FROM papers p
         JOIN users u ON u.id = p.teacher_id
         JOIN teacher_student ts ON ts.teacher_id = p.teacher_id AND ts.student_id = ?
         WHERE p.is_published = 1";
$pStmt = $pdo->prepare($pSql);
$pStmt->execute([$user['id']]);
$allPapers = $pStmt->fetchAll();

$aStmt = $pdo->prepare('SELECT id, paper_id, score, submitted_at, started_at FROM attempts WHERE student_id = ?');
$aStmt->execute([$user['id']]);
$attemptsByPaper = [];
foreach ($aStmt->fetchAll() as $a) { $attemptsByPaper[$a['paper_id']] = $a; }

$completed  = [];
$inprogress = [];
$unstarted  = [];
foreach ($allPapers as $p) {
  if (isset($attemptsByPaper[$p['id']]) && $attemptsByPaper[$p['id']]['submitted_at']) {
    $completed[] = ['paper' => $p, 'attempt' => $attemptsByPaper[$p['id']]];
  } elseif (isset($attemptsByPaper[$p['id']]) && !$attemptsByPaper[$p['id']]['submitted_at']) {
    $inprogress[] = ['paper' => $p, 'attempt' => $attemptsByPaper[$p['id']]];
  } else {
    $unstarted[] = $p;
  }
}
?>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-check-circle-fill"></i></div>
    <div>
      <p class="stat-card-label">Completed</p>
      <p class="stat-card-value"><?= count($completed) ?></p>
      <p class="stat-card-sub">Papers done</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon yellow"><i class="bi bi-play-circle-fill"></i></div>
    <div>
      <p class="stat-card-label">In Progress</p>
      <p class="stat-card-value"><?= count($inprogress) ?></p>
      <p class="stat-card-sub">Resume anytime</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-journal-text"></i></div>
    <div>
      <p class="stat-card-label">Not Started</p>
      <p class="stat-card-value"><?= count($unstarted) ?></p>
      <p class="stat-card-sub">Ready to attempt</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-people-fill"></i></div>
    <div>
      <p class="stat-card-label">Teachers</p>
      <p class="stat-card-value"><?= count($teachers) ?></p>
      <p class="stat-card-sub">Linked to you</p>
    </div>
  </div>
</div>

<!-- Main Grid -->
<div class="row g-4">

  <!-- Papers Column -->
  <div class="col-lg-8">
    <div class="app-card">
      <div class="app-card-header">
        <h2 class="app-card-title"><i class="bi bi-journal-text text-primary"></i> Your Papers</h2>
        <a href="<?= app_href('student/papers.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="app-card-body">
        <?php if (empty($allPapers)): ?>
          <div class="text-center py-5">
            <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--muted-light);display:block;margin-bottom:12px;"></i>
            <p class="mb-1 fw-semibold" style="color:var(--on-surface);">No papers available yet</p>
            <p class="mb-0" style="font-size:13px;color:var(--muted);">Your teachers will publish papers here once assigned.</p>
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">

            <?php foreach ($completed as $c):
              $p = $c['paper']; $a = $c['attempt'];
            ?>
            <a href="<?= app_href('student/result.php') ?>?attempt_id=<?= (int)$a['id'] ?>" class="item-card item-card-done text-decoration-none d-block">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <p class="mb-1 fw-semibold" style="color:var(--on-surface);font-size:14px;"><?= htmlspecialchars(substr($p['title'], 0, 50)) ?></p>
                  <p class="mb-0" style="color:var(--muted);font-size:12px;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($p['teacher_name']) ?></p>
                </div>
                <span class="badge-soft badge-soft-success flex-shrink-0"><i class="bi bi-check-circle-fill"></i> Done</span>
              </div>
            </a>
            <?php endforeach; ?>

            <?php foreach ($inprogress as $c):
              $p = $c['paper'];
            ?>
            <a href="<?= app_href('student/attempt.php') ?>?paper_id=<?= (int)$p['id'] ?>" class="item-card item-card-pending text-decoration-none d-block">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <p class="mb-1 fw-semibold" style="color:var(--on-surface);font-size:14px;"><?= htmlspecialchars(substr($p['title'], 0, 50)) ?></p>
                  <p class="mb-0" style="color:var(--muted);font-size:12px;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($p['teacher_name']) ?></p>
                </div>
                <span class="badge-soft badge-soft-warning flex-shrink-0"><i class="bi bi-play-fill"></i> In Progress</span>
              </div>
            </a>
            <?php endforeach; ?>

            <?php foreach ($unstarted as $p): ?>
            <a href="<?= app_href('student/attempt.php') ?>?paper_id=<?= (int)$p['id'] ?>" class="item-card item-card-open text-decoration-none d-block">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <p class="mb-1 fw-semibold" style="color:var(--on-surface);font-size:14px;"><?= htmlspecialchars(substr($p['title'], 0, 50)) ?></p>
                  <p class="mb-0" style="color:var(--muted);font-size:12px;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($p['teacher_name']) ?></p>
                </div>
                <span class="badge-soft badge-soft-gray flex-shrink-0">Start</span>
              </div>
            </a>
            <?php endforeach; ?>

          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Teachers Column -->
  <div class="col-lg-4">
    <div class="app-card">
      <div class="app-card-header">
        <h2 class="app-card-title"><i class="bi bi-people text-primary"></i> My Teachers</h2>
        <a href="<?= app_href('student/profile.php') ?>" class="btn btn-sm btn-outline-primary">Manage</a>
      </div>
      <div class="app-card-body">
        <?php if (!$teachers): ?>
          <div class="text-center py-4">
            <i class="bi bi-person-x" style="font-size:2rem;color:var(--muted-light);display:block;margin-bottom:10px;"></i>
            <p class="mb-0" style="font-size:13px;color:var(--muted);">No teachers linked yet. Add a teacher from your profile.</p>
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($teachers as $t): ?>
            <div class="d-flex align-items-center gap-3 p-3" style="background:var(--surface-subtle);border-radius:var(--radius);border:1px solid var(--border);">
              <div class="sidebar-avatar" style="flex-shrink:0;"><?= strtoupper(substr($t['name'], 0, 1)) ?></div>
              <div class="min-width-0 flex-1">
                <p class="mb-0 fw-semibold" style="font-size:13.5px;color:var(--on-surface);"><?= htmlspecialchars($t['name']) ?></p>
                <p class="mb-0" style="font-size:11.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($t['email']) ?></p>
              </div>
              <a href="<?= app_href('student/messages.php') ?>" class="topbar-icon-btn flex-shrink-0" title="Message">
                <i class="bi bi-chat-dots"></i>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php render_footer(); ?>
