<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'teacher') { http_response_code(403); echo 'Forbidden'; exit; }

$paperId = (int)($_GET['paper_id'] ?? 0);
if (!$paperId) { echo 'Missing paper'; exit; }

$pdo = db();

// Verify teacher owns this paper
$pStmt = $pdo->prepare('SELECT id, title, time_limit_seconds FROM papers WHERE id=? AND teacher_id=?');
$pStmt->execute([$paperId, $user['id']]);
$paper = $pStmt->fetch();
if (!$paper) { echo 'Paper not found'; exit; }

// Get all questions and options
$qStmt = $pdo->prepare('SELECT id, question_text, marks, image_path FROM questions WHERE paper_id=? ORDER BY position');
$qStmt->execute([$paperId]);
$questions = $qStmt->fetchAll();

$optsStmt = $pdo->prepare('SELECT id, option_text, is_correct FROM answer_options WHERE question_id=?');
foreach ($questions as &$q) {
    $optsStmt->execute([$q['id']]);
    $q['options'] = $optsStmt->fetchAll();
}
unset($q);

// Create or get teacher's test attempt
try {
    $testAttemptStmt = $pdo->prepare('SELECT id, started_at, submitted_at FROM teacher_test_attempts WHERE teacher_id=? AND paper_id=? ORDER BY id DESC LIMIT 1');
    $testAttemptStmt->execute([$user['id'], $paperId]);
    $testAttempt = $testAttemptStmt->fetch();

    // Create new attempt if:
    // 1. No attempt exists, OR
    // 2. Last attempt was already submitted
    if (!$testAttempt || $testAttempt['submitted_at']) {
        $insert = $pdo->prepare('INSERT INTO teacher_test_attempts (teacher_id, paper_id, started_at) VALUES (?, ?, NOW())');
        $insert->execute([$user['id'], $paperId]);
        $testAttemptId = $pdo->lastInsertId();
        $testAttempt = ['id' => $testAttemptId, 'started_at' => date('Y-m-d H:i:s'), 'submitted_at' => null];
    } else {
        // Reuse in-progress attempt (not yet submitted)
        $testAttemptId = $testAttempt['id'];
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such table') !== false || strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        echo '<div class="alert alert-danger" style="margin: 2rem;"><strong>Database Setup Required</strong><br>The teacher test tables have not been created yet. Please run the migration script:<br><br><code style="background: #f0f0f0; padding: 1rem; display: block; border-radius: 4px; margin-top: 1rem;">php add_teacher_test_tables.php</code><br><br>Or execute the SQL in phpMyAdmin from <code>add_teacher_test_tables.sql</code></div>';
        exit;
    }
    throw $e;
}

$timeLimit = (int)$paper['time_limit_seconds'];
$startedAt = strtotime($testAttempt['started_at']);
$deadline = $startedAt + $timeLimit;
$now = time();
$remaining = max(0, $deadline - $now);
$expired = $remaining === 0;

$score = null;
$totalMarks = 0;
$responses = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save progress
    if (isset($_POST['save_progress'])) {
        foreach ($questions as $q) {
            $qid = $q['id'];
            $selected = $_POST['q_' . $qid] ?? null;
            if ($selected !== null) {
                $respStmt = $pdo->prepare('INSERT INTO teacher_test_responses (test_attempt_id, question_id, selected_option_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id)');
                $respStmt->execute([$testAttemptId, $qid, (int)$selected]);
            }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Progress saved']);
            exit;
        }
    }

    // Final submit
    if (isset($_POST['final_submit'])) {
        foreach ($questions as $q) {
            $qid = $q['id'];
            $selected = $_POST['q_' . $qid] ?? null;
            if ($selected !== null) {
                $respStmt = $pdo->prepare('INSERT INTO teacher_test_responses (test_attempt_id, question_id, selected_option_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id)');
                $respStmt->execute([$testAttemptId, $qid, (int)$selected]);
            }
        }

        // Mark as submitted
        $submitStmt = $pdo->prepare('UPDATE teacher_test_attempts SET submitted_at=NOW() WHERE id=?');
        $submitStmt->execute([$testAttemptId]);

        // Calculate score
        $score = 0;
        foreach ($questions as $q) {
            $totalMarks += (int)$q['marks'];
            
            // Get selected response
            $respStmt = $pdo->prepare('SELECT selected_option_id FROM teacher_test_responses WHERE test_attempt_id=? AND question_id=?');
            $respStmt->execute([$testAttemptId, $q['id']]);
            $resp = $respStmt->fetch();
            
            if ($resp) {
                // Find if selected option is correct
                foreach ($q['options'] as $opt) {
                    if ($opt['id'] == $resp['selected_option_id'] && $opt['is_correct']) {
                        $score += (int)$q['marks'];
                        break;
                    }
                }
            }
        }

        // Redirect to result page
        header('Location: ' . app_href('teacher/test_result.php?test_attempt_id=' . $testAttemptId));
        exit;
    }
}

// Load saved responses for display
$savedRespStmt = $pdo->prepare('SELECT question_id, selected_option_id FROM teacher_test_responses WHERE test_attempt_id=?');
$savedRespStmt->execute([$testAttemptId]);
while ($r = $savedRespStmt->fetch()) {
    $responses[$r['question_id']] = $r['selected_option_id'];
}

// Calculate total marks
foreach ($questions as $q) {
    $totalMarks += (int)$q['marks'];
}

render_header('Test Paper - ' . htmlspecialchars($paper['title']));
?>

<!-- Load jQuery and KaTeX for math rendering -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
      } catch(err) {
        console.log('KaTeX error:', err.message);
      }
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
        <p class="text-muted small mb-1">TESTING MODE</p>
        <h2 class="mb-0"><?= htmlspecialchars($paper['title']) ?></h2>
      </div>
      <a href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
      </a>
    </div>

    <!-- Timer and Info -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="app-card p-3 text-center">
          <p class="text-muted small mb-2">Time Remaining</p>
          <h3 id="timer" class="mb-0" style="color: #667eea; font-family: monospace;">
            <?php
              $mins = intval($remaining / 60);
              $secs = $remaining % 60;
              echo str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
            ?>
          </h3>
        </div>
      </div>
      <div class="col-md-4">
        <div class="app-card p-3 text-center">
          <p class="text-muted small mb-2">Total Marks</p>
          <h3 class="mb-0" style="color: #14b8a6;"><?= $totalMarks ?></h3>
        </div>
      </div>
      <div class="col-md-4">
        <div class="app-card p-3 text-center">
          <p class="text-muted small mb-2">Questions</p>
          <h3 class="mb-0" style="color: #ff9800;"><?= count($questions) ?></h3>
        </div>
      </div>
    </div>

    <!-- Questions -->
    <form method="post" id="testForm">
      <?php foreach ($questions as $qIdx => $q): ?>
        <div class="app-card p-4 mb-3">
          <div class="mb-3">
            <h5 class="mb-2">
              <span style="background: #667eea; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem;">
                Q<?= $qIdx + 1 ?>
              </span>
              <span style="margin-left: 1rem; font-size: 0.9rem; color: #999;">
                <i class="bi bi-star-fill" style="color: #ffc107;"></i> <?= (int)$q['marks'] ?> mark<?= (int)$q['marks'] !== 1 ? 's' : '' ?>
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

          <!-- Options -->
          <div class="mb-3">
            <?php foreach ($q['options'] as $oIdx => $opt): ?>
              <label class="form-check mb-2">
                <input 
                  type="radio" 
                  class="form-check-input" 
                  name="q_<?= $q['id'] ?>" 
                  value="<?= $opt['id'] ?>"
                  <?= isset($responses[$q['id']]) && $responses[$q['id']] == $opt['id'] ? 'checked' : '' ?>
                >
                <span class="form-check-label ms-2">
                  <?= htmlspecialchars($opt['option_text']) ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Buttons -->
      <div class="app-card p-4 bg-light" style="border-left: 4px solid #667eea;">
        <div class="d-flex gap-3 flex-wrap">
          <button type="submit" name="save_progress" class="btn btn-outline-primary" value="1">
            <i class="bi bi-save"></i> Save Progress
          </button>
          <button type="submit" name="final_submit" class="btn btn-success" value="1" onclick="return confirm('Are you sure you want to submit? You cannot change answers after submission.')">
            <i class="bi bi-check-circle"></i> Submit & See Result
          </button>
          <a href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i> Cancel
          </a>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// Timer countdown
let remaining = <?= $remaining ?>;
const timerEl = document.getElementById('timer');
const testForm = document.getElementById('testForm');

function updateTimer() {
  if (remaining <= 0) {
    testForm.innerHTML += '<input type="hidden" name="final_submit" value="1">';
    testForm.submit();
    return;
  }
  
  const mins = Math.floor(remaining / 60);
  const secs = remaining % 60;
  timerEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  
  if (remaining <= 60) {
    timerEl.parentElement.style.color = '#dc3545';
  }
  
  remaining--;
}

setInterval(updateTimer, 1000);

// Auto-save every 30 seconds
setInterval(() => {
  const formData = new FormData(testForm);
  formData.append('save_progress', '1');
  
  fetch(window.location.href, {
    method: 'POST',
    body: formData,
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  }).catch(err => console.log('Auto-save:', err));
}, 30000);

// Render math on load
if (window.renderMathInElement) {
  renderMath();
}
</script>

<?php render_footer(); ?>
