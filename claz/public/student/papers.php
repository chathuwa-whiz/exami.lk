<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'student') { http_response_code(403); echo 'Forbidden'; exit; }
$pdo = db();
$sql = "SELECT p.id, p.title, p.fee_cents, p.time_limit_seconds, u.name AS teacher_name,
        EXISTS(SELECT 1 FROM payments pay WHERE pay.paper_id=p.id AND pay.user_id=? AND pay.status='completed') AS paid
        FROM papers p
        JOIN users u ON p.teacher_id = u.id
        JOIN teacher_student ts ON ts.teacher_id = p.teacher_id AND ts.student_id = ?
        WHERE p.is_published=1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id'], $user['id']]);
$papers = $stmt->fetchAll();
$totalPapers = count($papers);
$freePapers = count(array_filter($papers, fn($p) => (int)$p['fee_cents'] === 0));
$paidUnlocked = count(array_filter($papers, fn($p) => $p['fee_cents'] > 0 && $p['paid']));
render_header('My Papers');
?>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-journal-text"></i></div>
    <div>
      <p class="stat-card-label">Total Papers</p>
      <p class="stat-card-value"><?= $totalPapers ?></p>
      <p class="stat-card-sub">From all teachers</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-check-circle-fill"></i></div>
    <div>
      <p class="stat-card-label">Free Attempts</p>
      <p class="stat-card-value"><?= $freePapers ?></p>
      <p class="stat-card-sub">No payment needed</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon yellow"><i class="bi bi-lock-fill"></i></div>
    <div>
      <p class="stat-card-label">Unlocked Paid</p>
      <p class="stat-card-value"><?= $paidUnlocked ?></p>
      <p class="stat-card-sub">Payment completed</p>
    </div>
  </div>
</div>

<!-- Papers List -->
<div class="app-card">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-list-ul text-primary"></i> All Papers</h2>
    <span class="badge-soft badge-soft-gray"><?= $totalPapers ?> total</span>
  </div>
  <div class="app-card-body">
    <?php if (empty($papers)): ?>
      <div class="text-center py-5">
        <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--muted-light);display:block;margin-bottom:12px;"></i>
        <p class="mb-1 fw-semibold" style="color:var(--on-surface);">No papers available yet</p>
        <p class="mb-0" style="font-size:13px;color:var(--muted);">Only teachers linked to you can publish here.</p>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($papers as $p):
          $isFree      = $p['fee_cents'] == 0;
          $isPaid      = $p['paid'];
          $isAccessible = $isFree || $isPaid;
        ?>
        <div class="item-card <?= $isAccessible ? 'item-card-done' : 'item-card-pending' ?>">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="flex-grow-1 min-width-0">
              <p class="mb-1 fw-semibold" style="font-size:14.5px;color:var(--on-surface);"><?= htmlspecialchars($p['title']) ?></p>
              <p class="mb-2" style="font-size:12.5px;color:var(--muted);"><i class="bi bi-person me-1"></i><?= htmlspecialchars($p['teacher_name']) ?></p>
              <div class="d-flex gap-3 flex-wrap">
                <span class="badge-soft badge-soft-gray"><i class="bi bi-hourglass-split"></i> <?= intval($p['time_limit_seconds'] / 60) ?> min</span>
                <?php if ($isFree): ?>
                  <span class="badge-soft badge-soft-success"><i class="bi bi-check-circle-fill"></i> Free</span>
                <?php elseif ($isPaid): ?>
                  <span class="badge-soft badge-soft-primary"><i class="bi bi-lock-fill"></i> Unlocked</span>
                <?php else: ?>
                  <span class="badge-soft badge-soft-warning"><i class="bi bi-tag-fill"></i> Rs <?= number_format($p['fee_cents'] / 100, 2) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex-shrink-0">
              <?php if ($isAccessible): ?>
                <a class="btn btn-sm btn-success" href="<?= htmlspecialchars(app_href('student/attempt.php?paper_id=' . $p['id'])) ?>">
                  <i class="bi bi-play-fill"></i> Start
                </a>
              <?php else: ?>
                <form method="post" action="<?= htmlspecialchars(app_href('student/pay.php')) ?>" class="d-inline">
                  <input type="hidden" name="paper_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-warning">
                    <i class="bi bi-credit-card"></i> Pay
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>