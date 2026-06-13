<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'teacher') { http_response_code(403); echo 'Forbidden'; exit; }

$testAttemptId = (int)($_GET['test_attempt_id'] ?? 0);
if (!$testAttemptId) { echo 'Missing test attempt'; exit; }

$pdo = db();

// Fetch test attempt and verify ownership
try {
    $testAttemptStmt = $pdo->prepare('SELECT teacher_id, paper_id, started_at, submitted_at FROM teacher_test_attempts WHERE id=?');
    $testAttemptStmt->execute([$testAttemptId]);
    $testAttempt = $testAttemptStmt->fetch();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such table') !== false || strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        echo '<div class="alert alert-danger" style="margin: 2rem;"><strong>Database Setup Required</strong><br>The teacher test tables have not been created yet. Please run the migration script:<br><br><code style="background: #f0f0f0; padding: 1rem; display: block; border-radius: 4px; margin-top: 1rem;">php add_teacher_test_tables.php</code><br><br>Or execute the SQL in phpMyAdmin from <code>add_teacher_test_tables.sql</code></div>';
        exit;
    }
    throw $e;
}

if (!$testAttempt || $testAttempt['teacher_id'] !== $user['id']) { 
    echo 'Test attempt not found'; 
    exit; 
}

$paperId = $testAttempt['paper_id'];

// Fetch paper info
$pStmt = $pdo->prepare('SELECT title FROM papers WHERE id=? AND teacher_id=?');
$pStmt->execute([$paperId, $user['id']]);
$paper = $pStmt->fetch();
if (!$paper) { echo 'Paper not found'; exit; }

// Fetch questions
$qStmt = $pdo->prepare('SELECT id, question_text, marks, image_path FROM questions WHERE paper_id=? ORDER BY position');
$qStmt->execute([$paperId]);
$questions = $qStmt->fetchAll();

// Fetch options for each question
$optsStmt = $pdo->prepare('SELECT id, option_text, is_correct FROM answer_options WHERE question_id=?');
foreach ($questions as &$q) {
    $optsStmt->execute([$q['id']]);
    $q['options'] = $optsStmt->fetchAll();
}
unset($q);

// Fetch responses
$respStmt = $pdo->prepare('SELECT question_id, selected_option_id FROM teacher_test_responses WHERE test_attempt_id=?');
$respStmt->execute([$testAttemptId]);
$responses = [];
while ($r = $respStmt->fetch()) {
    $responses[$r['question_id']] = $r['selected_option_id'];
}

// Calculate score
$score = 0;
$totalMarks = 0;
$questionResults = [];

foreach ($questions as $q) {
    $qid = $q['id'];
    $marks = (int)$q['marks'];
    $totalMarks += $marks;
    
    $isCorrect = false;
    $selectedOptionText = 'Not answered';
    $correctOptionText = '';
    
    if (isset($responses[$qid])) {
        // Find selected and correct options
        foreach ($q['options'] as $opt) {
            if ($opt['id'] == $responses[$qid]) {
                $selectedOptionText = $opt['option_text'];
                if ($opt['is_correct']) {
                    $isCorrect = true;
                    $score += $marks;
                }
            }
            if ($opt['is_correct']) {
                $correctOptionText = $opt['option_text'];
            }
        }
    } else {
        // Find correct option for display
        foreach ($q['options'] as $opt) {
            if ($opt['is_correct']) {
                $correctOptionText = $opt['option_text'];
                break;
            }
        }
    }
    
    $questionResults[] = [
        'question' => $q,
        'isCorrect' => $isCorrect,
        'marksObtained' => $isCorrect ? $marks : 0,
        'selectedOptionText' => $selectedOptionText,
        'correctOptionText' => $correctOptionText
    ];
}

$percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;

render_header('Test Result - ' . htmlspecialchars($paper['title']));
?>

<!-- Load KaTeX for math rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" />
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

<script>
  function renderMath() {
    if (window.renderMathInElement) {
      try {
        window.renderMathInElement(document.body, {
          delimiters: [
            {left: '$$', right: '$$', display: true},
            {left: '$', right: '$', display: false}
          ],
          throwOnError: false
        });
      } catch(err) {}
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderMath);
  } else {
    renderMath();
  }
</script>

<div class="app-content">
  <div class="container-lg">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="mb-2"><?= htmlspecialchars($paper['title']) ?></h2>
        <p class="text-muted small mb-0">Test Completed - <?= date('M d, Y H:i', strtotime($testAttempt['submitted_at'])) ?></p>
      </div>
      <a href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Papers
      </a>
    </div>

    <!-- Score Card -->
    <div class=\"app-card p-4 mb-4\" style=\"background: white; border: 1px solid #e5e7eb; border-bottom: 4px solid #3b82f6; color: #1f2937;\">
      <div class="row g-4 text-center">
        <div class="col-md-4">
          <p class="text-muted small mb-2" style="opacity: 0.9;">SCORE</p>
          <h2 class="mb-0" style="font-size: 2.5rem; font-weight: 700; color: #3b82f6;"><?= $score ?>/<?= $totalMarks ?></h2>
        </div>
        <div class="col-md-4">
          <p class="text-muted small mb-2" style="opacity: 0.9;">PERCENTAGE</p>
          <h2 class="mb-0" style="font-size: 2.5rem; font-weight: 700; color: #3b82f6;"><?= $percentage ?>%</h2>
        </div>
        <div class="col-md-4">
          <p class="text-muted small mb-2" style="opacity: 0.9;">PERFORMANCE</p>
          <h3 class="mb-0" style="font-weight: 700; color: #1f2937;">
            <?php
              if ($percentage >= 90) echo '🌟 Excellent';
              elseif ($percentage >= 75) echo '✅ Good';
              elseif ($percentage >= 60) echo '👍 Fair';
              elseif ($percentage >= 40) echo '⚠️ Needs Work';
              else echo '❌ Poor';
            ?>
          </h3>
        </div>
      </div>
    </div>

    <!-- Detailed Results -->
    <h4 class="mb-3">Question-wise Results</h4>
    <?php foreach ($questionResults as $qIdx => $result): ?>
      <?php $q = $result['question']; ?>
      <div class="app-card p-4 mb-3 <?= $result['isCorrect'] ? 'border-left: 4px solid #14b8a6;' : 'border-left: 4px solid #dc3545;' ?>">
        <div class="mb-3">
          <h5 class="mb-2">
            <span style="background: <?= $result['isCorrect'] ? '#14b8a6' : '#dc3545' ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem;">
              Q<?= $qIdx + 1 ?>
            </span>
            <span style="margin-left: 1rem; font-size: 0.9rem; color: #999;">
              <i class="bi bi-star-fill" style="color: #ffc107;"></i> <?= (int)$q['marks'] ?> mark<?= (int)$q['marks'] !== 1 ? 's' : '' ?>
            </span>
            <span style="float: right; font-weight: 700; color: <?= $result['isCorrect'] ? '#14b8a6' : '#dc3545' ?>;">
              <?= $result['marksObtained'] ?>/<?= (int)$q['marks'] ?>
            </span>
          </h5>
        </div>

        <!-- Question Text -->
        <div class="mb-3">
          <p style="line-height: 1.8; word-wrap: break-word; white-space: normal; overflow-wrap: break-word;">
            <?= htmlspecialchars($q['question_text']) ?>
          </p>
        </div>

        <!-- Question Image if exists -->
        <?php if (!empty($q['image_path'])): ?>
          <div class="mb-3" style="text-align: center;">
            <?php 
              $filePath = $q['image_path'];
              $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

              // Normalize stored path to public/teacher/uploads/questions
              $normalized = ltrim($filePath, '/');
              if (stripos($normalized, 'public/') === 0) {
                $normalized = substr($normalized, 7); // drop leading public/
              }
              if (stripos($normalized, 'uploads/questions/') === 0) {
                $normalized = 'teacher/' . $normalized;
              }
              if (stripos($normalized, 'public/teacher/uploads/questions/') === 0) {
                $normalized = substr($normalized, strlen('public/'));
              }

              $fileSrc = app_href($normalized);
              
              // Display PDF or Image
              if ($fileExt === 'pdf'): ?>
                <div style="border: 2px solid #e0e0e0; border-radius: 8px; overflow: hidden; background: #f9f9f9;">
                  <div style="background: #f0f0f0; padding: 1rem; text-align: center; border-bottom: 2px solid #e0e0e0;">
                    <i class="bi bi-file-pdf" style="font-size: 2rem; color: #dc3545;"></i>
                    <p style="margin: 0.5rem 0 0 0; color: #666; font-weight: 600;">PDF Document</p>
                  </div>
                  <iframe src="<?= htmlspecialchars($fileSrc) ?>" style="width: 100%; height: 500px; border: none;"></iframe>
                  <div style="padding: 1rem; text-align: center; background: #f9f9f9; border-top: 2px solid #e0e0e0;">
                    <a href="<?= htmlspecialchars($fileSrc) ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600;">
                      <i class="bi bi-download me-1"></i>Download PDF
                    </a>
                  </div>
                </div>
              <?php else: ?>
                <img src="<?= htmlspecialchars($fileSrc) ?>" alt="Question image" style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
              <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Your Answer -->
        <div class="mb-3" style="background: #f9f9f9; padding: 1rem; border-radius: 6px; border-left: 4px solid #667eea;">
          <p class="text-muted small mb-1"><strong>Your Answer:</strong></p>
          <p class="mb-0">
            <?php if ($result['selectedOptionText'] === 'Not answered'): ?>
              <span style="color: #999; font-style: italic;">Not answered</span>
            <?php else: ?>
              <strong><?= htmlspecialchars($result['selectedOptionText']) ?></strong>
            <?php endif; ?>
          </p>
        </div>

        <!-- Correct Answer (if you got it wrong) -->
        <?php if (!$result['isCorrect'] && $result['selectedOptionText'] !== 'Not answered'): ?>
          <div class="mb-3" style="background: #efe; padding: 1rem; border-radius: 6px; border-left: 4px solid #14b8a6;">
            <p class="text-muted small mb-1"><strong>Correct Answer:</strong></p>
            <p class="mb-0" style="color: #14b8a6; font-weight: 600;">
              <?= htmlspecialchars($result['correctOptionText']) ?>
            </p>
          </div>
        <?php endif; ?>

        <!-- All Options for Reference -->
        <div style="background: #f0f0f0; padding: 1rem; border-radius: 6px;">
          <p class="text-muted small mb-2"><strong>All Options:</strong></p>
          <div style="display: grid; gap: 0.5rem;">
            <?php foreach ($q['options'] as $opt): ?>
              <div style="padding: 0.5rem 0.75rem; background: white; border-radius: 4px; border-left: 4px solid <?= $opt['is_correct'] ? '#14b8a6' : '#ddd' ?>;">
                <span style="font-weight: 600; color: <?= $opt['is_correct'] ? '#14b8a6' : '#333' ?>;">
                  <?php if ($opt['is_correct']): ?>
                    <i class="bi bi-check-circle-fill"></i>
                  <?php endif; ?>
                  <?= htmlspecialchars($opt['option_text']) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Actions -->
    <div class="app-card p-4 bg-light text-center">
      <a href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>" class="btn btn-primary">
        <i class="bi bi-arrow-left"></i> Back to Papers
      </a>
    </div>
  </div>
</div>

<script>
if (window.renderMathInElement) {
  renderMath();
}
</script>

<?php render_footer(); ?>
