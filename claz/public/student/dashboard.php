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
$title = $isStudent ? 'Welcome back' : 'Dashboard';
$subtitle = $isStudent ? 'Your classes, teachers, and papers at a glance.' : 'Overview';

render_header($title, [], $user);
?>

<?php render_welcome_banner($user); ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
  <!-- Teachers Section -->
  <div>
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.75rem;">
      <i class="bi bi-people me-2"></i>Teachers
    </h2>
    <?php
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
    if (!$teachers): ?>
      <div style="padding: 2rem; background: #f3f4f6; border-radius: 8px; text-align: center; color: #6b7280;">
        <i class="bi bi-info-circle me-2"></i>No teachers assigned yet
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php foreach ($teachers as $t): ?>
          <div style="padding: 1rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
            <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: #1f2937;">
              <?= htmlspecialchars($t['name']) ?>
            </p>
            <p style="margin: 0 0 1rem 0; color: #6b7280; font-size: 0.9rem;">
              <?= htmlspecialchars($t['email']) ?>
            </p>
            <a href="mailto:<?= htmlspecialchars($t['email']) ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-envelope me-1"></i>Contact
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Papers Section -->
  <div>
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.75rem;">
      <i class="bi bi-file-text me-2"></i>Your Papers
    </h2>
    <?php
    // Fetch published papers for mapped teachers
    $pSql = "SELECT p.id, p.title, p.time_limit_seconds, u.name AS teacher_name
             FROM papers p
             JOIN users u ON u.id = p.teacher_id
             JOIN teacher_student ts ON ts.teacher_id = p.teacher_id AND ts.student_id = ?
             WHERE p.is_published = 1";
    $pStmt = $pdo->prepare($pSql);
    $pStmt->execute([$user['id']]);
    $allPapers = $pStmt->fetchAll();

    // Fetch attempts for this student
    $aStmt = $pdo->prepare('SELECT id, paper_id, score, submitted_at, started_at FROM attempts WHERE student_id = ?');
    $aStmt->execute([$user['id']]);
    $attemptsByPaper = [];
    foreach ($aStmt->fetchAll() as $a) { $attemptsByPaper[$a['paper_id']] = $a; }

    $completed = [];
    $inprogress = [];
    $unstarted = [];
    foreach ($allPapers as $p) {
      if (isset($attemptsByPaper[$p['id']]) && $attemptsByPaper[$p['id']]['submitted_at']) {
        $completed[] = [
          'paper' => $p,
          'attempt' => $attemptsByPaper[$p['id']]
        ];
      } elseif (isset($attemptsByPaper[$p['id']]) && !$attemptsByPaper[$p['id']]['submitted_at']) {
        $inprogress[] = [
          'paper' => $p,
          'attempt' => $attemptsByPaper[$p['id']]
        ];
      } else {
        $unstarted[] = $p;
      }
    }
    
    if (empty($allPapers)): ?>
      <div style="padding: 2rem; background: #f3f4f6; border-radius: 8px; text-align: center; color: #6b7280;">
        <i class="bi bi-inbox me-2"></i>No papers available
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php 
        foreach ($completed as $c): 
          $p = $c['paper']; 
          $a = $c['attempt'];
        ?>
          <div style="padding: 1rem; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 6px;">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <p style="margin: 0; font-weight: 600; color: #1f2937;">
                <?= htmlspecialchars(substr($p['title'], 0, 40)) ?>
              </p>
              <span style="background: #dcfce7; color: #166534; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">
                ✓ Done
              </span>
            </div>
            <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">
              <?= htmlspecialchars($p['teacher_name']) ?>
            </p>
          </div>
        <?php endforeach; ?>
        
        <?php 
        foreach ($inprogress as $c): 
          $p = $c['paper'];
        ?>
          <div style="padding: 1rem; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 6px;">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <p style="margin: 0; font-weight: 600; color: #1f2937;">
                <?= htmlspecialchars(substr($p['title'], 0, 40)) ?>
              </p>
              <span style="background: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">
                ⟳ In Progress
              </span>
            </div>
            <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">
              <?= htmlspecialchars($p['teacher_name']) ?>
            </p>
          </div>
        <?php endforeach; ?>
        
        <?php 
        foreach ($unstarted as $p): 
        ?>
          <div style="padding: 1rem; background: #f3f4f6; border-left: 4px solid #9ca3af; border-radius: 6px;">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <p style="margin: 0; font-weight: 600; color: #1f2937;">
                <?= htmlspecialchars(substr($p['title'], 0, 40)) ?>
              </p>
              <span style="background: #e5e7eb; color: #4b5563; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">
                ○ Start
              </span>
            </div>
            <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">
              <?= htmlspecialchars($p['teacher_name']) ?>
            </p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>


<!-- Quick Stats Section -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
  <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
      <i class="bi bi-check-circle me-1" style="color: #10b981;"></i>Completed
    </p>
    <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #10b981;">
      <?= count($completed) ?>
    </p>
  </div>
  <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
      <i class="bi bi-play-circle me-1" style="color: #f59e0b;"></i>In Progress
    </p>
    <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #f59e0b;">
      <?= count($inprogress) ?>
    </p>
  </div>
  <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #6b7280; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
      <i class="bi bi-clock me-1" style="color: #9ca3af;"></i>Not Started
    </p>
    <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #6b7280;">
      <?= count($unstarted) ?>
    </p>
  </div>
</div>



<?php render_footer(); ?>

