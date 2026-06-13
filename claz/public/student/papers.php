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
render_header('Available Papers');
?>

<!-- Hero Section -->
<section style="background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-left: 4px solid #3b82f6;">
  <div class="position-relative">
    <h1 class="mb-2" style="font-size: 2rem; font-weight: 700; color: #1f2937;">
      <i class="bi bi-file-earmark-text me-2" style="color: #3b82f6;"></i>Available Papers
    </h1>
    <p style="color: #6b7280; font-size: 1rem; margin: 0;">Explore papers from your mapped teachers</p>
  </div>
</section>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <!-- Total Papers Card -->
  <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-bottom: 3px solid #6b7280;">
    <div>
      <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;"><i class="bi bi-file-earmark me-1" style="color: #6b7280;"></i>Total Papers</p>
      <p style="margin: 0; font-weight: 700; color: #1f2937; font-size: 1.8rem;"><?= $totalPapers ?></p>
    </div>
  </div>

  <!-- Free Papers Card -->
  <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-bottom: 3px solid #10b981;">
    <div>
      <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;"><i class="bi bi-check-circle me-1" style="color: #10b981;"></i>Free Attempts</p>
      <p style="margin: 0; font-weight: 700; color: #1f2937; font-size: 1.8rem;"><?= $freePapers ?></p>
    </div>
  </div>

  <!-- Paid & Unlocked Card -->
  <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-bottom: 3px solid #f59e0b;">
    <div>
      <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;"><i class="bi bi-lock-fill me-1" style="color: #f59e0b;"></i>Unlocked Paid</p>
      <p style="margin: 0; font-weight: 700; color: #1f2937; font-size: 1.8rem;"><?= $paidUnlocked ?></p>
    </div>
  </div>
</div>

<!-- Papers List Section -->
<section style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden;">
  <h2 class="h6 mb-3" style="font-weight: 700; color: #1f2937;">
    <i class="bi bi-list-ul me-2" style="color: #3b82f6;"></i>All Papers
  </h2>
  <div>
    <?php if (empty($papers)): ?>
      <div style="text-align: center; padding: 3rem 1rem;">
        <i class="bi bi-inbox" style="font-size: 2.5rem; color: #9ca3af; display: block; margin-bottom: 1rem;"></i>
        <p style="color: #6b7280; font-size: 1rem; margin: 0; font-weight: 600;">No papers available yet</p>
        <p style="color: #9ca3af; font-size: 0.9rem; margin: 0.5rem 0 0 0;">Only teachers linked to you can publish here</p>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php foreach ($papers as $p): 
          $isFree = $p['fee_cents'] == 0;
          $isPaid = $p['paid'];
          $isAccessible = $isFree || $isPaid;
          $bgColor = $isAccessible ? '#f0fdf4' : '#fffbf0';
          $borderColor = $isAccessible ? '#10b981' : '#f59e0b';
        ?>
          <div style="padding: 1.5rem; background: <?= $bgColor ?>; border-left: 4px solid <?= $borderColor ?>; border-radius: 6px; transition: all 0.2s;">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div style="flex: 1;">
                <p style="margin: 0 0 0.5rem 0; font-weight: 700; color: #1f2937; font-size: 1rem;">
                  <?= htmlspecialchars($p['title']) ?>
                </p>
                <p style="margin: 0 0 0.75rem 0; color: #6b7280; font-size: 0.9rem;">
                  <i class="bi bi-person me-1"></i><?= htmlspecialchars($p['teacher_name']) ?>
                </p>
                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.85rem; color: #6b7280;">
                  <p style="margin: 0;">
                    <i class="bi bi-hourglass-split me-1"></i><?= intval($p['time_limit_seconds'] / 60) ?> min
                  </p>
                  <p style="margin: 0;">
                    <?php if ($isFree): ?>
                      <span><i class="bi bi-check-circle me-1" style="color: #10b981;"></i><span style="color: #10b981; font-weight: 600;">Free</span></span>
                    <?php elseif ($isPaid): ?>
                      <span><i class="bi bi-lock me-1" style="color: #3b82f6;"></i><span style="color: #3b82f6; font-weight: 600;">Unlocked</span></span>
                    <?php else: ?>
                      <span><i class="bi bi-tag me-1" style="color: #f59e0b;"></i><span style="color: #f59e0b; font-weight: 600;">Rs <?= number_format($p['fee_cents'] / 100, 2) ?></span></span>
                    <?php endif; ?>
                  </p>
                </div>
              </div>
              <div style="text-align: right; flex-shrink: 0;">
                <?php if ($isAccessible): ?>
                  <a class="btn btn-sm btn-success" style="font-weight: 600; white-space: nowrap;" href="<?= htmlspecialchars(app_href('student/attempt.php?paper_id=' . $p['id'])) ?>">
                    <i class="bi bi-play-fill"></i> Start
                  </a>
                <?php else: ?>
                  <form method="post" action="<?= htmlspecialchars(app_href('student/pay.php')) ?>" class="d-inline">
                    <input type="hidden" name="paper_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-warning" style="font-weight: 600; white-space: nowrap;">
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
</section>
<?php render_footer(); ?>