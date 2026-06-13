<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
$attemptId = (int)($_GET['attempt_id'] ?? 0);
if (!$attemptId) { echo 'Missing attempt'; exit; }
$pdo = db();
$stmt = $pdo->prepare('SELECT a.id,a.score,a.submitted_at,p.id AS paper_id, p.title FROM attempts a JOIN papers p ON a.paper_id=p.id WHERE a.id=? AND a.student_id=?');
$stmt->execute([$attemptId, $user['id']]);
$attempt = $stmt->fetch();
if (!$attempt) { echo 'Attempt not found'; exit; }

// Get total marks for the paper
$totalStmt = $pdo->prepare('SELECT COALESCE(SUM(marks),0) AS total FROM questions WHERE paper_id=?');
$totalStmt->execute([$attempt['paper_id']]);
$totalRow = $totalStmt->fetch();
$totalMarks = (int)$totalRow['total'];
$score = (int)$attempt['score'];
$percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100) : 0;
render_header('Result');
?>

<!-- MathJax v3 configuration and loader -->
<script>
  // Configure BEFORE loading MathJax
  MathJax = {
    tex: {
      inlineMath: [['$', '$'], ['\\(', '\\)']],
      displayMath: [['$$', '$$'], ['\\[', '\\]']],
      processEscapes: true,
      processEnvironments: true
    },
    svg: {
      fontCache: 'global'
    }
  };
</script>
<script async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>

<script>
  // Robust MathJax typesetting with retry logic
  let typesetAttempts = 0;
  const maxAttempts = 20;
  
  function typesetMath() {
    if (window.MathJax && window.MathJax.typesetPromise) {
      window.MathJax.typesetPromise()
        .then(() => {
          console.log('✓ Math typeset complete');
        })
        .catch(err => {
          console.warn('Math typeset error:', err);
          if (typesetAttempts < maxAttempts) {
            typesetAttempts++;
            setTimeout(typesetMath, 200);
          }
        });
    } else if (typesetAttempts < maxAttempts) {
      typesetAttempts++;
      setTimeout(typesetMath, 200);
    }
  }

  // Wait for DOM to be fully loaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(typesetMath, 100);
    });
  } else {
    setTimeout(typesetMath, 100);
  }
  
  // Also typeset when window loads (after all resources)
  window.addEventListener('load', () => {
    setTimeout(typesetMath, 100);
  });
</script>

<!-- Score Display Hero Section -->
<!-- Score Display Hero Section -->
<section class="mb-5 py-4 py-md-5" style="background: linear-gradient(135deg, <?= $percentage >= 80 ? '#667eea 0%, #764ba2' : ($percentage >= 60 ? '#4facfe 0%, #00f2fe' : '#ffc107 0%, #ff9800') ?> 100%); border-radius: 12px; padding: 2rem 1.5rem 2rem 1.5rem; padding-md: 3rem; color: white; text-align: center; position: relative; overflow: hidden;">
  <div style="position: absolute; top: 0; right: 0; font-size: 10rem; opacity: 0.1;">
    <i class="bi bi-trophy"></i>
  </div>
  <div class="position-relative">
    <h1 class="mb-2" style="font-size: 1.2rem; font-weight: 600; opacity: 0.95;">
      <i class="bi bi-check-circle me-2"></i>Test Completed Successfully!
    </h1>
    <div style="font-size: 2.5rem; font-size-md: 4rem; font-weight: 700; margin: 1rem 0;">
      <?= $percentage ?>%
    </div>
    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">
      <?= $score ?> out of <?= $totalMarks ?> marks
    </div>
    <p style="opacity: 0.9; margin: 0;">
      <i class="bi bi-clock me-1"></i>Submitted on <?= htmlspecialchars(date('M d, Y H:i', strtotime($attempt['submitted_at']))) ?>
    </p>
  </div>
</section>

<!-- Performance Summary Cards -->
<section class="mb-5">
  <div class="row g-3">
    <!-- Paper Title Card -->
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100 shadow-sm" style="border-left: 4px solid #667eea; border-radius: 12px;">
        <div style="width: 48px; height: 48px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin-bottom: 1rem;">
          <i class="bi bi-file-earmark-text"></i>
        </div>
        <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.05em; font-weight: 600;">Paper Title</p>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: #333; margin: 0;">
          <?= htmlspecialchars($attempt['title']) ?>
        </h3>
      </div>
    </div>

    <!-- Score Card -->
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100 shadow-sm" style="border-left: 4px solid #00d084; border-radius: 12px;">
        <div style="width: 48px; height: 48px; border-radius: 8px; background: linear-gradient(135deg, #00d084 0%, #13c084 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin-bottom: 1rem;">
          <i class="bi bi-star-fill"></i>
        </div>
        <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.05em; font-weight: 600;">Your Score</p>
        <h3 style="font-size: 1.8rem; font-weight: 700; color: #00d084; margin: 0;">
          <?= $score ?> / <?= $totalMarks ?>
        </h3>
        <p class="text-muted small mt-2 mb-0">
          <i class="bi bi-percent"></i> <?= $percentage ?>% Accuracy
        </p>
      </div>
    </div>

    <!-- Status Card -->
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100 shadow-sm" style="border-left: 4px solid #4facfe; border-radius: 12px;">
        <div style="width: 48px; height: 48px; border-radius: 8px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin-bottom: 1rem;">
          <i class="bi bi-calendar-check"></i>
        </div>
        <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.05em; font-weight: 600;">Submission</p>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: #00f2fe; margin: 0;">
          <?= $attempt['submitted_at'] ? 'Submitted' : 'Pending' ?>
        </h3>
        <p class="text-muted small mt-2 mb-0">
          <i class="bi bi-clock-history"></i> <?= htmlspecialchars($attempt['submitted_at']) ?>
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Performance Gauge and Details -->
<section class="app-card p-5 shadow-sm mb-5">
  <div class="d-flex align-items-center gap-2 mb-4" style="border-bottom: 2px solid #f0f0f0; padding-bottom: 1rem;">
    <div style="width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
      <i class="bi bi-graph-up"></i>
    </div>
    <h2 class="h4 mb-0" style="font-weight: 700;">Performance Summary</h2>
  </div>
  
  <?php if ($attempt['submitted_at']): ?>
    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div style="background: linear-gradient(135deg, <?= $percentage >= 80 ? '#667eea 0%, #764ba2' : ($percentage >= 60 ? '#4facfe 0%, #00f2fe' : '#ffc107 0%, #ff9800') ?> 100%); border-radius: 12px; padding: 2rem; color: white; text-align: center;">
          <div style="font-size: 3rem; font-weight: 700; margin-bottom: 0.5rem;">
            <?= $percentage ?>%
          </div>
          <div style="font-size: 1rem;">
            <?php if ($percentage >= 90) { echo '🎯 Excellent Performance'; }
            elseif ($percentage >= 80) { echo '⭐ Very Good'; }
            elseif ($percentage >= 70) { echo '👍 Good'; }
            elseif ($percentage >= 60) { echo '✓ Satisfactory'; }
            else { echo '📚 Keep Practicing'; } ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-4" style="border: 2px solid #f0f0f0; border-radius: 12px;">
          <h5 style="font-weight: 700; color: #333; margin-bottom: 1.5rem;">Breakdown</h5>
          <div class="mb-3">
            <div class="d-flex justify-content-between mb-2">
              <span style="color: #666;">Correct Answers</span>
              <strong style="color: #00d084;"><?= $score ?> marks</strong>
            </div>
            <div style="background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
              <div style="background: linear-gradient(135deg, #00d084 0%, #13c084 100%); height: 100%; width: <?= $totalMarks > 0 ? ($score / $totalMarks) * 100 : 0 ?>%;"></div>
            </div>
          </div>
          <div>
            <div class="d-flex justify-content-between mb-2">
              <span style="color: #666;">Remaining</span>
              <strong style="color: #dc3545;"><?= $totalMarks - $score ?> marks</strong>
            </div>
            <div style="background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
              <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); height: 100%; width: <?= $totalMarks > 0 ? (($totalMarks - $score) / $totalMarks) * 100 : 0 ?>%;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p class="text-muted mb-0" style="font-size: 1.05rem; line-height: 1.6;">
      <i class="bi bi-info-circle me-2" style="color: #667eea;"></i>
      Congratulations on completing this test! Review your answers below to understand what you got right and where you can improve.
    </p>
  <?php else: ?>
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert" style="border-left: 4px solid #ffc107; border-radius: 8px; background-color: #fff8e1;">
      <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.3rem;"></i>
      <span>This test was not submitted yet or time expired without submission.</span>
    </div>
  <?php endif; ?>
</section>

<!-- Answers Review Section -->
<?php if ($attempt['submitted_at']): ?>
  <section class="app-card p-5 shadow-sm">
    <div class="d-flex align-items-center gap-2 mb-4" style="border-bottom: 2px solid #f0f0f0; padding-bottom: 1rem;">
      <div style="width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
        <i class="bi bi-list-check"></i>
      </div>
      <h2 class="h4 mb-0" style="font-weight: 700;">Detailed Answer Review</h2>
    </div>

    <?php
    $qStmt = $pdo->prepare('SELECT id, question_text, marks, image_path FROM questions WHERE paper_id=(SELECT paper_id FROM attempts WHERE id=?) ORDER BY position');
    $qStmt->execute([$attemptId]);
    $questions = $qStmt->fetchAll();
    $rStmt = $pdo->prepare('SELECT selected_option_id FROM responses WHERE attempt_id=? AND question_id=?');
    $optStmt = $pdo->prepare('SELECT id, option_text, is_correct FROM answer_options WHERE question_id=?');
    ?>

    <div class="d-flex flex-column gap-4 mt-4">
      <?php foreach ($questions as $idx => $q): ?>
          <?php $rStmt->execute([$attemptId, $q['id']]); $resp = $rStmt->fetch(); $selectedId = $resp['selected_option_id'] ?? null; ?>
          <?php
          $optStmt->execute([$q['id']]);
          $opts = $optStmt->fetchAll();
          $correct = null; $correctIndex = null;
          foreach ($opts as $i => $o) {
            if ($o['is_correct']) { $correct = $o; $correctIndex = $i; break; }
          }
          $correctLabel = is_null($correctIndex) ? null : chr(ord('A') + $correctIndex);

          // Find the student's selected option and label
          $selectedOption = null; $selectedIndex = null;
          if ($selectedId) {
            foreach ($opts as $i => $o) {
              if ($o['id'] == $selectedId) { $selectedOption = $o; $selectedIndex = $i; break; }
            }
          }
          $selectedLabel = is_null($selectedIndex) ? null : chr(ord('A') + $selectedIndex);
        ?>
        
        <div style="border: 2px solid #f0f0f0; border-radius: 12px; padding: 1.5rem; transition: box-shadow 0.2s;" 
             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" 
             onmouseout="this.style.boxShadow='none'">
          <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="background: #f0f0f0; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #667eea;">
              Q<?= $idx+1 ?>
            </div>
            <div style="flex: 1;">
              <h5 style="margin: 0; font-weight: 700; color: #333;">
                <?= htmlspecialchars($q['question_text']) ?>
              </h5>
              <p style="margin: 0.3rem 0 0 0; color: #999; font-size: 0.9rem;">
                <i class="bi bi-star-fill"></i> <?= $q['marks'] ?> mark<?= $q['marks']==1?'':'s' ?>
              </p>
            </div>
            <div style="background: <?= ($selectedId && $selectedId == ($correct ? $correct['id'] : null)) ? 'linear-gradient(135deg, #00d084 0%, #13c084 100%)' : 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)' ?>; color: white; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; text-align: center; font-size: 0.9rem;">
              <?= ($selectedId && $selectedId == ($correct ? $correct['id'] : null)) ? '<i class="bi bi-check-circle me-1"></i>Correct' : '<i class="bi bi-x-circle me-1"></i>Incorrect' ?>
            </div>
          </div>

          <?php if (!empty($q['image_path'])): ?>
          <div style="margin: 1rem 0; text-align: center;">
            <?php 
              $imgSrc = $q['image_path'];
              $normalized = ltrim($imgSrc, '/');
              if (stripos($normalized, 'public/') === 0) { $normalized = substr($normalized, 7); }
              if (stripos($normalized, 'uploads/questions/') === 0) { $normalized = 'teacher/' . $normalized; }
              if (stripos($normalized, 'public/teacher/uploads/questions/') === 0) { $normalized = substr($normalized, strlen('public/')); }
              $imgSrc = app_href($normalized);
            ?>
            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Question image" style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          </div>
          <?php endif; ?>

          <div style="background: #f9f9f9; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
            <p style="margin: 0 0 0.8rem 0; color: #666; font-weight: 600;">
              <i class="bi bi-question-circle me-1" style="color: #667eea;"></i>Options:
            </p>
              <ul style="list-style: none; padding: 0; margin: 0;">
              <?php foreach ($opts as $i => $o): ?>
                <?php $isSel = ($selectedId && $selectedId == $o['id']); $isCor = (bool)$o['is_correct']; $label = chr(ord('A') + $i); ?>
                <li style="padding: 0.7rem; margin: 0.5rem 0; border-radius: 6px; background: <?= $isCor ? '#d4edda' : ($isSel ? '#f8d7da' : 'white') ?>; border: 2px solid <?= $isCor ? '#28a745' : ($isSel ? '#dc3545' : '#ddd') ?>; display: flex; align-items: flex-start; gap: 0.8rem;">
                  <span style="font-size: 1.2rem; margin-top: -2px;">
                    <?php if ($isCor && $isSel) { echo '✅'; }
                    elseif ($isCor) { echo '✓'; }
                    elseif ($isSel) { echo '❌'; }
                    else { echo '○'; } ?>
                  </span>
                  <span style="flex: 1; color: #333;">
                    <strong style="margin-right:0.6rem; color:#333;"><?= $label ?>.</strong> <?= htmlspecialchars($o['option_text']) ?>
                    <?php if ($isSel) { echo '<span style="color: #dc3545; font-weight: 600;"> (your choice)</span>'; } ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

            <div style="display:flex; gap:1rem; margin-top: 1rem;">
              <div style="flex:1; background: #e7f3ff; border-left: 4px solid #4facfe; padding: 1rem; border-radius: 6px;">
                <p style="margin: 0; color: #0066cc; font-weight: 600;">
                  <i class="bi bi-lightbulb me-1"></i>Your Answer:
                </p>
                <p style="margin: 0.5rem 0 0 0; color: #333;">
                  <?= $selectedOption ? htmlspecialchars(($selectedLabel ? $selectedLabel . '. ' : '') . $selectedOption['option_text']) : 'N/A' ?>
                </p>
              </div>
              <div style="flex:1; background: #f0fff4; border-left: 4px solid #00d084; padding: 1rem; border-radius: 6px;">
                <p style="margin: 0; color: #006600; font-weight: 600;">
                  <i class="bi bi-award me-1"></i>Correct Answer:
                </p>
                <p style="margin: 0.5rem 0 0 0; color: #333;">
                  <?= $correct ? htmlspecialchars(($correctLabel ? $correctLabel . '. ' : '') . $correct['option_text']) : 'N/A' ?>
                </p>
              </div>
            </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- Action Buttons -->
<section class="mt-5 mb-4">
  <div class="d-flex gap-3 flex-wrap">
    <?php if ($attempt['submitted_at']): ?>
      <a href="<?= htmlspecialchars(app_href('student/download_result_pdf.php?attempt_id=' . $attemptId)) ?>" 
         class="btn btn-lg d-inline-flex align-items-center gap-2" 
         style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; font-weight: 600;"
         target="_blank">
        <i class="bi bi-file-pdf-fill"></i> 
        <span>Download PDF</span>
      </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars(app_href('student/papers.php')) ?>" class="btn btn-lg d-inline-flex align-items-center gap-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; font-weight: 600;">
      <i class="bi bi-arrow-left"></i> 
      <span>Back to Papers</span>
    </a>
    <a href="<?= htmlspecialchars(app_href('student/dashboard.php')) ?>" class="btn btn-lg btn-outline-secondary d-inline-flex align-items-center gap-2" style="font-weight: 600;">
      <i class="bi bi-house-fill"></i> 
      <span>Go to Dashboard</span>
    </a>
  </div>
</section>

