<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
// Import Sinhala font early
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;700&display=swap" rel="stylesheet">';
require_login();
$user = current_user();
// Release session lock early to avoid session timing issues during long exam attempts
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if ($user['user_type'] !== 'student') { http_response_code(403); echo 'Forbidden'; exit; }
$paperId = (int)($_GET['paper_id'] ?? 0);
if (!$paperId) { echo 'Missing paper'; exit; }
$pdo = db();
$accessStmt = $pdo->prepare('SELECT 1 FROM paper_access WHERE user_id=? AND paper_id=?');
$accessStmt->execute([$user['id'], $paperId]);
if (!$accessStmt->fetch()) {
  // Gracefully grant access if eligible: mapped student and either free or paid
  $eligStmt = $pdo->prepare('SELECT p.fee_cents, p.is_published, p.title,
    EXISTS(SELECT 1 FROM payments pay WHERE pay.paper_id=p.id AND pay.user_id=? AND pay.status="completed") AS paid,
    EXISTS(SELECT 1 FROM teacher_student ts WHERE ts.teacher_id=p.teacher_id AND ts.student_id=?) AS mapped
    FROM papers p WHERE p.id=?');
  $eligStmt->execute([$user['id'], $user['id'], $paperId]);
  $elig = $eligStmt->fetch();
  
  if (!$elig) {
    echo 'Paper not found'; exit;
  }
  
  if (!$elig['is_published']) {
    echo 'This paper is not published yet. Please contact your teacher.'; exit;
  }
  
  if (!$elig['mapped']) {
    echo 'You are not assigned to this teacher. Please register with the correct teacher code or ask your teacher to assign you.'; exit;
  }
  
  if ((int)$elig['fee_cents'] > 0 && !$elig['paid']) {
    header('Location: ' . app_href('student/pay.php?paper_id=' . $paperId));
    exit;
  }
  
  // Auto-grant access since all checks passed
  $grant = $pdo->prepare('INSERT IGNORE INTO paper_access (user_id,paper_id) VALUES (?,?)');
  $grant->execute([$user['id'], $paperId]);
  
  // Redirect to same page to reload with access granted
  header('Location: ' . $_SERVER['REQUEST_URI']);
  exit;
}
$pStmt = $pdo->prepare('SELECT title,time_limit_seconds FROM papers WHERE id=?');
$pStmt->execute([$paperId]);
$paper = $pStmt->fetch();
if (!$paper) { echo 'Paper not found'; exit; }
// Ensure UTF-8 encoding for Sinhala text retrieval
$pdo->exec("SET NAMES utf8mb4");
$qStmt = $pdo->prepare('SELECT id, question_text, marks, image_path FROM questions WHERE paper_id=? ORDER BY position');
$qStmt->execute([$paperId]);
$questions = $qStmt->fetchAll();
$optsStmt = $pdo->prepare('SELECT id, option_text, is_correct, attachment_path FROM answer_options WHERE question_id=?');
$attemptStmt = $pdo->prepare('SELECT id, started_at, submitted_at FROM attempts WHERE student_id=? AND paper_id=?');
$attemptStmt->execute([$user['id'], $paperId]);
$attempt = $attemptStmt->fetch();
if (!$attempt) {
    $insert = $pdo->prepare('INSERT INTO attempts (student_id,paper_id) VALUES (?,?)');
    $insert->execute([$user['id'], $paperId]);
    $attemptId = $pdo->lastInsertId();
    $attempt = ['id' => $attemptId, 'started_at' => date('Y-m-d H:i:s'), 'submitted_at' => null];
} else {
    // If attempt already submitted, redirect to result page
    if ($attempt['submitted_at']) {
        header('Location: ' . app_href('student/result.php?attempt_id=' . $attempt['id']));
        exit;
    }
    $attemptId = $attempt['id'];
}
$timeLimit = (int)$paper['time_limit_seconds'];
$startedAt = strtotime($attempt['started_at']);
$deadline = $startedAt + $timeLimit;
$now = time();
$remaining = max(0, $deadline - $now);
$expired = $remaining === 0;

// If time is up and attempt not submitted, auto-submit with current responses
// (skip during POST to allow manual submit to process)
if ($expired && !$attempt['submitted_at'] && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Before auto-submit, save any form data from POST (in case page reloaded with form data)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($questions as $q) {
            $qid = $q['id'];
            $selected = $_POST['q_' . $qid] ?? null;
            if ($selected !== null) {
                $respStmt = $pdo->prepare('INSERT INTO responses (attempt_id,question_id,selected_option_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id)');
                $respStmt->execute([$attemptId, $qid, (int)$selected]);
            }
        }
    }
    
    // Calculate score based on all saved responses (from auto-save + any form data)
    $score = 0;
    foreach ($questions as $q) {
        $qid = $q['id'];
        $respGet = $pdo->prepare('SELECT selected_option_id FROM responses WHERE attempt_id=? AND question_id=?');
        $respGet->execute([$attemptId, $qid]);
        $resp = $respGet->fetch();
        if ($resp && $resp['selected_option_id']) {
            $optCheck = $pdo->prepare('SELECT is_correct FROM answer_options WHERE id=?');
            $optCheck->execute([$resp['selected_option_id']]);
            $oc = $optCheck->fetch();
            if ($oc && $oc['is_correct']) { 
                $score += (int)$q['marks']; 
            }
        }
    }
    // Mark as submitted
    $upd = $pdo->prepare('UPDATE attempts SET submitted_at=NOW(), score=? WHERE id=?');
    $upd->execute([$score, $attemptId]);
    
    // Redirect to result page
    header('Location: ' . app_href('student/result.php?attempt_id=' . $attemptId));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save progress when requested - allow even if expired for background auto-save
    if (isset($_POST['save_progress'])) {
      foreach ($questions as $q) {
        $qid = $q['id'];
        $selected = $_POST['q_' . $qid] ?? null;
        if ($selected !== null) {
          $respStmt = $pdo->prepare('INSERT INTO responses (attempt_id,question_id,selected_option_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id)');
          $respStmt->execute([$attemptId, $qid, (int)$selected]);
        }
        // Do NOT overwrite with NULL during auto-save
      }
      // Return JSON for AJAX requests
      if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Progress saved']);
        exit;
      }
    }

    // If final submit was pressed, ensure we persist any posted answers regardless of expiry,
    // then compute the score and mark the attempt submitted.
    if (isset($_POST['final_submit'])) {
        // DEBUG: Log all POST data for any form submission
        file_put_contents(__DIR__ . '/debug_post_log.txt', date('Y-m-d H:i:s') . "\n" . print_r($_POST, true) . "\n\n", FILE_APPEND);
      // Persist all answers, including unanswered (set NULL for unanswered)
      foreach ($questions as $q) {
        $qid = $q['id'];
        $selected = $_POST['q_' . $qid] ?? null;
        if ($selected !== null) {
          $respStmt = $pdo->prepare('INSERT INTO responses (attempt_id,question_id,selected_option_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id)');
          $respStmt->execute([$attemptId, $qid, (int)$selected]);
        } else {
          // Ensure a row exists for unanswered questions
          $respStmt = $pdo->prepare('INSERT INTO responses (attempt_id,question_id,selected_option_id) VALUES (?,?,NULL) ON DUPLICATE KEY UPDATE selected_option_id=NULL');
          $respStmt->execute([$attemptId, $qid]);
        }
      }

      $score = 0;
      foreach ($questions as $q) {
        $qid = $q['id'];
        $respGet = $pdo->prepare('SELECT selected_option_id FROM responses WHERE attempt_id=? AND question_id=?');
        $respGet->execute([$attemptId, $qid]);
        $resp = $respGet->fetch();
        if ($resp && $resp['selected_option_id']) {
          $optCheck = $pdo->prepare('SELECT is_correct FROM answer_options WHERE id=?');
          $optCheck->execute([$resp['selected_option_id']]);
          $oc = $optCheck->fetch();
          if ($oc && $oc['is_correct']) { $score += (int)$q['marks']; }
        }
      }
      $upd = $pdo->prepare('UPDATE attempts SET submitted_at=NOW(), score=? WHERE id=?');
      $upd->execute([$score, $attemptId]);
      header('Location: ' . app_href('student/result.php?attempt_id=' . $attemptId));
      exit;
    }
}
render_header('Attempt Paper');
?>
<style>
  /* Import and apply Sinhala font globally */
  * {
    font-family: 'Noto Sans Sinhala', 'Segoe UI', sans-serif;
  }
  
  /* Preserve Sinhala spacing and apply Sinhala-capable font for rendered questions */
  .question-text-render { 
    white-space: pre-wrap;
    font-family: 'Noto Sans Sinhala', 'Segoe UI', sans-serif;
    font-weight: 400;
  }
  
  /* Force MathJax CHTML output to use Sinhala-capable font */
  mjx-container, .MathJax, .mjx-chtml { font-family: 'Noto Sans Sinhala','Segoe UI',sans-serif !important; }
  mjx-container * { font-family: 'Noto Sans Sinhala','Segoe UI',sans-serif !important; font-style: normal !important; }
  mjx-mtext, .mjx-math, mjx-mi, mjx-mo, mjx-mn, mjx-utext,
  .mjx-mtext, .mjx-math, .mjx-mi, .mjx-mo, .mjx-mn, .mjx-utext {
    font-family: inherit !important;
    font-style: normal !important;
  }

  /* Mobile responsiveness for exam page */
  @media (max-width: 575.98px) {
    section.mb-5.py-4.py-md-5 {
      padding: 1rem !important;
      border-radius: 12px;
    }
    #sticky-info-bar {
      padding: 0.3rem 0.15rem !important;
    }
    /* Keep time and progress in one row on mobile */
    #sticky-info-bar .row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.1rem !important;
    }
    #sticky-info-bar .row > :nth-child(1),
    #sticky-info-bar .row > :nth-child(2) {
      flex: 0 0 calc(50% - 0.15rem) !important;
      max-width: calc(50% - 0.15rem) !important;
    }
    #sticky-info-bar .row > :nth-child(3) {
      flex: 0 0 100% !important;
      max-width: 100% !important;
    }
    #sticky-info-bar .app-card {
      padding: 0.5rem 0.6rem !important;
      border-radius: 8px !important;
      border-left-width: 3px !important;
    }
    #sticky-info-bar .d-flex {
      gap: 0.5rem !important;
    }
    #sticky-info-bar h3 {
      font-size: 1rem !important;
    }
    #sticky-info-bar p {
      font-size: 0.6rem !important;
      line-height: 1.2;
    }
    #sticky-info-bar [style*="width: 36px"],
    #sticky-info-bar [style*="width: 40px"] {
      width: 28px !important;
      height: 28px !important;
      font-size: 0.9rem !important;
    }
    #sticky-info-bar [style*="height: 6px"] {
      height: 4px !important;
    }
    #questions-section {
      padding: 1rem !important;
      border-radius: 12px;
    }
    .question-block {
      padding: 0.9rem !important;
      margin-bottom: 0.75rem !important;
    }
    .question-block legend {
      font-size: 0.95rem !important;
    }
    .question-block .badge {
      font-size: 0.8rem !important;
      padding: 0.3rem 0.6rem !important;
    }
    .question-text-render {
      font-size: 0.95rem !important;
      line-height: 1.4;
      margin: 0.75rem 0 !important;
    }
    .question-block label {
      padding: 0.7rem !important;
      min-height: 48px !important;
      align-items: flex-start !important;
      gap: 0.7rem !important;
    }
    .question-block input.form-check-input {
      width: 18px;
      height: 18px;
      min-width: 18px;
      min-height: 18px;
    }
    .app-card {
      border-radius: 12px;
    }
  }
</style>

<!-- Load jQuery (needed elsewhere on the page) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- MathJax v3 configuration and loader (CHTML output so Sinhala fonts apply) -->
<script id="MathJax-script">
  // Configure MathJax using the startup object before loading
  window.MathJax = {
    tex: {
      inlineMath: [['$', '$'], ['\\(', '\\)']],
      displayMath: [['$$', '$$'], ['\\[', '\\]']],
      processEscapes: true,
      processEnvironments: true
    },
    chtml: {
      matchFontHeight: false,
      mtextInheritFont: true,
      merrorInheritFont: true
    },
    startup: {
      pageReady() {
        return window.MathJax.startup.defaultPageReady();
      }
    }
  };
</script>
<script async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>

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

<!-- Exam Integrity Warning Modal (Sinhala) -->
<div class="modal" id="integrityWarningModal" tabindex="-1" role="dialog" style="display: block; background: rgba(0,0,0,0.5);">
  <div class="modal-dialog modal-lg" role="document" style="margin-top: 50px;">
    <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
      <!-- Header -->
      <div style="background: white; border-bottom: 3px solid #dc2626; padding: 2rem; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 700; font-size: 1.3rem; display: flex; align-items: center; gap: 0.5rem; color: #1f2937;">
          <i class="bi bi-exclamation-triangle-fill" style="color: #dc2626;"></i>
          පරීක්ෂණ විධිමත් නීතිය
        </h4>
        <p style="margin: 0.5rem 0 0 0; opacity: 0.9; font-size: 0.95rem; color: #6b7280;">Exam Integrity Warning</p>
      </div>
      
      <!-- Body -->
      <div style="padding: 2rem; background: #f8f9fa;">
        <div style="background: white; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #dc2626; margin-bottom: 1.5rem;">
          <p style="margin: 0 0 1rem 0; color: #1f2937; line-height: 1.6; font-size: 0.95rem;">
            <strong>⚠️ වැදගත් දැනුම්දීම (Important Notice):</strong>
          </p>
          <ul style="margin: 0; padding-left: 1.5rem; color: #4b5563; line-height: 1.8; font-size: 0.9rem;">
            <li>ඔබ පරීක්ෂණ විධිමත් නීතිවලට භාජනය වේ (You are bound by exam integrity rules)</li>
            <li>ටැබ් මාරු කිරීම සහ පිටුවෙන් ඉවත් වීම අනාවරණය වී එය සටහන් වනු ඇත. (Tab switching and leaving the page will be detected and logged)</li>
            <li>අනුපිටපත් කිරීම,රයිට් කිලික් කිරීමට ඉඩ නොලැබේ. (Copy, paste, and right-click are disabled)</li>
            <li>ඩෙවලපර් මෙවලම් සහ අනෙක් මෙවලම් බාවිත කල නොහැකියි. (Developer tools and inspect element are blocked)</li>
            <li>සියලු සැක සහගත කටයුතු දෙයක් වේනම් ඔබගේ ගුරුවරුන්ට එ පිලිබද වාර්තාවක් ලැබෙ. (All suspicious activities will be reported to your teacher)</li>
            <li>බාහිර ආධාරක හෝ වෙනත් උපකරණයක් භාවිතා කිරීමට උත්සාහ නොකරන්න. (Do not attempt to use external aids or other devices)</li>
          </ul>
        </div>
        
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
          <p style="margin: 0; color: #856404; font-size: 0.9rem;">
            <strong>🎯 සාදාරන විභාග ප්‍රතිපත්තිය. (Fair Exam Policy):</strong> ඔබ සාදාරන ලෙස පරික්ශනයට ඉදිරිපත් විය යුතුයි.සැක සහිත යමක් සිදු කිරීමෙන් ඔබේ ලකුනු අහිමි වේ.
          </p>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: white; border-radius: 6px;">
          <input type="checkbox" id="agreeCheckbox" style="width: 20px; height: 20px; cursor: pointer;">
          <label for="agreeCheckbox" style="margin: 0; cursor: pointer; color: #333; font-size: 0.95rem;">
            <strong>මම ඉහත නීතිවලට එකඟ වෙමි</strong> (I agree to the above terms)
          </label>
        </div>
      </div>
      
      <!-- Footer -->
      <div style="padding: 1.5rem; background: #f8f9fa; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; display: flex; gap: 1rem;">
        <a href="<?= app_href('student/papers.php') ?>" class="btn btn-outline-secondary" style="flex: 1;">
          <i class="bi bi-arrow-left me-2"></i>ආපසු යන්න (Go Back)
        </a>
        <button type="button" id="proceedBtn" class="btn btn-danger" style="flex: 1; opacity: 0.5; cursor: not-allowed;" disabled>
          <i class="bi bi-check-circle me-2"></i>පරීක්ෂණය ආරම්භ කරන්න (Start Exam)
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Main Exam Content (Hidden until accepted) -->
<div id="examContent" style="display: none;">

<!-- Hero Header Section -->
<section class="mb-5 py-4 py-md-5" style="background: white; border-radius: 12px; padding: 2rem; padding-md: 2.5rem; border-left: 4px solid #3b82f6; position: relative; overflow: hidden;">
  <div style="position: absolute; top: 0; right: 0; font-size: 8rem; opacity: 0.05; color: #3b82f6; position: relative;">
    <i class="bi bi-pencil-square"></i>
  </div>
  <div class="position-relative">
    <h1 class="mb-2" style="font-size: 2rem; font-weight: 700; color: #1f2937;">
      <i class="bi bi-pencil-fill me-2" style="color: #3b82f6;"></i><?= htmlspecialchars($paper['title']) ?>
    </h1>
    <p style="opacity: 0.95; margin: 0;">Ready to take the test? Click through the questions and submit when done.</p>
  </div>
</section>

<!-- Fixed Info Bar Container (Hidden until exam starts) -->
<div id="sticky-info-bar" style="position: fixed; top: 56px; left: 0; right: 0; z-index: 999; background: white; padding: 0.5rem 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s; display: none;">
  <div class="container-fluid px-2">
    <!-- Compact Info Cards for Sticky View -->
    <div class="row g-2 align-items-center">
      <!-- Timer - Most Important -->
      <div class="col-12 col-md-4 col-lg-3">
        <div class="app-card p-2 shadow-sm" style="border-left: 4px solid <?= $remaining > 600 ? '#00d084' : ($remaining > 300 ? '#ffc107' : '#dc3545') ?>; border-radius: 8px; background: white;">
          <div class="d-flex align-items-center gap-2">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, <?= $remaining > 600 ? '#00d084 0%, #13c084' : ($remaining > 300 ? '#ffc107 0%, #ff9800' : '#dc3545 0%, #c82333') ?> 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.1rem; flex-shrink: 0;">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <div style="flex: 1;">
              <p class="text-uppercase small text-muted mb-0" style="letter-spacing: 0.05em; font-weight: 600; font-size: 0.65rem;">Time Left</p>
              <h3 id="time" style="font-size: 1.2rem; font-weight: 700; color: <?= $remaining > 600 ? '#00d084' : ($remaining > 300 ? '#ff9800' : '#dc3545') ?>; margin: 0; line-height: 1;" aria-live="polite">
                <?= intval($remaining / 60) ?>m <?= $remaining % 60 ?>s
              </h3>
            </div>
          </div>
        </div>
      </div>

      <!-- Progress - Second Most Important -->
      <div class="col-12 col-md-5 col-lg-6">
        <div class="app-card p-2 shadow-sm" style="border-left: 4px solid #667eea; border-radius: 8px; background: white;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <p class="text-uppercase small text-muted mb-0" style="letter-spacing: 0.05em; font-weight: 600; font-size: 0.65rem;">
              <i class="bi bi-graph-up me-1" style="color: #667eea;"></i>Progress
            </p>
            <span style="background: #667eea; color: white; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
              <span id="answered-count">0</span> / <?= count($questions) ?>
            </span>
          </div>
          <div style="background: #e0e0e0; height: 6px; border-radius: 3px; overflow: hidden;">
            <div id="progress-bar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
          </div>
        </div>
      </div>

      <!-- Quick Info -->
      <div class="col-12 col-md-3 col-lg-3">
        <div class="app-card p-2 shadow-sm" style="border-left: 4px solid #4facfe; border-radius: 8px; background: white;">
          <div class="d-flex align-items-center gap-2">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.1rem; flex-shrink: 0;">
              <i class="bi bi-file-earmark"></i>
            </div>
            <div style="flex: 1;">
              <p class="text-uppercase small text-muted mb-0" style="letter-spacing: 0.05em; font-weight: 600; font-size: 0.65rem;">Questions</p>
              <h3 style="font-size: 1.2rem; font-weight: 700; color: #4facfe; margin: 0; line-height: 1;">
                <?= count($questions) ?>
              </h3>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Questions Form -->
<section id="questions-section" class="app-card p-5 shadow-sm mb-5" style="margin-top: 0;">
  <form method="post" class="d-flex flex-column gap-4" onchange="updateProgress()">
    <?php foreach ($questions as $idx => $q): ?>
      <fieldset class="question-block app-card" style="border: 2px solid #f0f0f0; border-radius: 12px; padding: 1.5rem; transition: all 0.2s; background: white;" 
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" 
                 onmouseout="this.style.boxShadow='none'" tabindex="-1">
        <div class="d-flex align-items-center justify-content-between mb-3" style="border-bottom: 2px solid #f0f0f0; padding-bottom: 1rem;">
          <legend class="fw-semibold mb-0" style="font-size: 1.1rem; color: #333;">
            <span style="background: #667eea; color: white; width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; margin-right: 0.8rem;">
              <?= $idx + 1 ?>
            </span>
            Question <?= $idx + 1 ?>
          </legend>
          <span class="badge" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 0.4rem 0.8rem; color: white; font-size: 0.9rem;">
            <i class="bi bi-star-fill me-1"></i><?= $q['marks'] ?> mark<?= $q['marks'] == 1 ? '' : 's' ?>
          </span>
        </div>
        
        <p class="question-text-render" style="font-size: 1.05rem; color: #333; margin: 1rem 0; line-height: 1.6; word-break: break-word;">
          <?= $q['question_text'] ?>
        </p>
        
        <!-- Display question image or PDF if present -->
        <?php if (!empty($q['image_path'])): ?>
        <div style="margin: 1.5rem 0; text-align: center;">
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
        
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem;">
          <?php $optsStmt->execute([$q['id']]); $opts = $optsStmt->fetchAll(); foreach ($opts as $oIdx => $o): ?>
            <label style="display: flex; align-items: center; gap: 1rem; padding: 1rem; padding-md: 1.25rem; border: 2px solid #e0e0e0; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: white; min-height: 60px;">
              <input class="form-check-input" type="radio" name="q_<?= $q['id'] ?>" value="<?= $o['id'] ?>" style="width: 22px; height: 22px; cursor: pointer; min-height: 44px; min-width: 44px;" title="Option <?= chr(65 + $oIdx) ?>"> 
              <span style="flex: 1; color: #333; font-size: 0.95rem; word-break: break-word;">
                <strong style="display: block; margin-bottom: 0.2rem; color: #667eea; font-size: 0.9rem;">Option <?= chr(65 + $oIdx) ?></strong>
                <?= htmlspecialchars($o['option_text']) ?>
                <?php if (!empty($o['attachment_path'])): ?>
                  <?php
                    $optFilePath = $o['attachment_path'];
                    $optExt = strtolower(pathinfo($optFilePath, PATHINFO_EXTENSION));
                    $optNorm = ltrim($optFilePath, '/');
                    if (stripos($optNorm, 'public/') === 0) { $optNorm = substr($optNorm, 7); }
                    if (stripos($optNorm, 'uploads/options/') === 0) { $optNorm = 'teacher/' . $optNorm; }
                    if (stripos($optNorm, 'public/teacher/uploads/options/') === 0) {
                      $optNorm = substr($optNorm, strlen('public/'));
                    }
                    $optSrc = app_href($optNorm);
                  ?>
                  <div style="margin-top: 0.5rem;">
                    <?php if ($optExt === 'pdf'): ?>
                      <a href="<?= htmlspecialchars($optSrc) ?>" target="_blank" rel="noopener">View PDF</a>
                    <?php else: ?>
                      <img src="<?= htmlspecialchars($optSrc) ?>" alt="Option attachment" style="max-width: 100%; max-height: 160px; border-radius: 6px; border: 1px solid #e0e0e0;">
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>

    <!-- Action Buttons -->
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; padding-top: 1.5rem; border-top: 2px solid #f0f0f0; margin-top: 2rem;">
      <?php if (!$expired): ?>
        <button type="submit" name="save_progress" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="font-weight: 600; padding: 0.7rem 1.5rem; min-height: 44px; display: none;">
          <i class="bi bi-cloud-check-fill"></i> Save Progress
        </button>
        <button type="submit" name="final_submit" id="finalBtn" class="btn d-inline-flex align-items-center gap-2" style="background: linear-gradient(135deg, #00d084 0%, #13c084 100%); color: white; border: none; font-weight: 600; padding: 0.7rem 1.5rem; min-height: 44px; flex: 1; min-width: 160px;" onclick="return confirm('Are you sure you want to submit? You cannot modify answers after submission.');">
          <i class="bi bi-check-circle-fill"></i> Submit Test
        </button>
      <?php else: ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 w-100" role="alert" aria-live="assertive" style="border-left: 4px solid #dc3545; border-radius: 8px; margin: 0;">
          <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.3rem;"></i>
          <span><strong>Time's up!</strong> You can still view your result.</span>
        </div>
      <?php endif; ?>
      <a href="<?= htmlspecialchars(app_href('student/papers.php')) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="font-weight: 600; padding: 0.7rem 1.5rem; margin-left: auto; min-height: 44px;">
        <i class="bi bi-arrow-left"></i> Exit Test
      </a>
    </div>
  </form>
</section>

</div><!-- End of examContent -->

<script>
// Handle integrity warning modal
const agreeCheckbox = document.getElementById('agreeCheckbox');
const proceedBtn = document.getElementById('proceedBtn');
const integrityWarningModal = document.getElementById('integrityWarningModal');
const examContent = document.getElementById('examContent');
let examStarted = false;

agreeCheckbox.addEventListener('change', function() {
  if (this.checked) {
    proceedBtn.disabled = false;
    proceedBtn.style.opacity = '1';
    proceedBtn.style.cursor = 'pointer';
  } else {
    proceedBtn.disabled = true;
    proceedBtn.style.opacity = '0.5';
    proceedBtn.style.cursor = 'not-allowed';
  }
});

proceedBtn.addEventListener('click', function(e) {
  e.preventDefault();
  if (!agreeCheckbox.checked) {
    alert('Please check the agreement checkbox before proceeding.');
    return;
  }
  
  // Hide warning modal and show exam
  integrityWarningModal.style.display = 'none';
  examContent.style.display = 'block';
  
  // Show sticky info bar
  const stickyBar = document.getElementById('sticky-info-bar');
  if (stickyBar) {
    // Calculate navbar height dynamically
    const navbar = document.querySelector('nav.navbar, .navbar, header');
    const navbarHeight = navbar ? navbar.offsetHeight : 56;
    
    // Position bar below navbar
    stickyBar.style.top = navbarHeight + 'px';
    stickyBar.style.display = 'block';
    
    // Wait for bar to render, then add padding to questions section
    setTimeout(() => {
      const questionsSection = document.getElementById('questions-section');
      if (questionsSection) {
        const totalOffset = navbarHeight + stickyBar.offsetHeight + 20;
        questionsSection.style.paddingTop = totalOffset + 'px';
      }
    }, 100);
  }
  
  examStarted = true;
  
  // Log acknowledgment
  fetch('<?= app_href('api/log_exam_activity.php') ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      attempt_id: <?= $attemptId ?>,
      activity_type: 'integrity_acknowledged',
      details: {acknowledged: true},
      timestamp: new Date().toISOString(),
      tab_switches: 0
    })
  }).catch(err => console.log('Acknowledgment logged'));
  
  // Start exam
  requestFullscreen();
  showWarning('⚠️ Exam Mode Active', 'This exam is monitored for integrity. Any suspicious activity will be logged.', 'info');
  tick();
  updateProgress();
});
</script>

<script>
// ===== ANTI-CHEATING MEASURES =====
let tabSwitchCount = 0;
let focusLostTime = null;
let examStartTime = Date.now();
let suspiciousFlags = [];

// 1. FULLSCREEN REQUEST
function requestFullscreen() {
  const elem = document.documentElement;
  if (elem.requestFullscreen) {
    elem.requestFullscreen().catch(err => {
      console.warn('Fullscreen request failed:', err);
    });
  }
}

// 2. TAB SWITCH / FOCUS DETECTION
document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    // Student switched tabs/windows
    tabSwitchCount++;
    focusLostTime = Date.now();
    
    // Show warning
    showWarning('⚠️ Warning', `You switched tabs ${tabSwitchCount} time(s). This may be reported to your teacher.`, 'warning');
    
    // Pause timer
    pauseTimer = true;
    
    // Log the event
    logExamActivity('tab_switch', {count: tabSwitchCount});
  } else {
    // Student returned to exam
    pauseTimer = false;
    const timeAway = Math.round((Date.now() - focusLostTime) / 1000);
    showWarning('✓ Welcome Back', `You were away for ${timeAway} seconds. Your timer is resuming.`, 'info');
  }
});

// 3. WINDOW BLUR/FOCUS
let blurCount = 0;
window.addEventListener('blur', () => {
  blurCount++;
  tabSwitchCount++;
  pauseTimer = true;
  logExamActivity('window_blur', {count: blurCount});
});

window.addEventListener('focus', () => {
  pauseTimer = false;
  logExamActivity('window_focus', {focusTime: new Date().toISOString()});
});

// 4. DISABLE COPY/PASTE
document.addEventListener('copy', (e) => {
  e.preventDefault();
  tabSwitchCount++;
  logExamActivity('copy_attempt', {});
  showWarning('❌ Copy Blocked', 'Copying text is not allowed during the exam.', 'danger');
  return false;
});

document.addEventListener('cut', (e) => {
  e.preventDefault();
  logExamActivity('cut_attempt', {});
  showWarning('❌ Cut Blocked', 'Cutting text is not allowed during the exam.', 'danger');
  return false;
});

document.addEventListener('paste', (e) => {
  e.preventDefault();
  tabSwitchCount++;
  logExamActivity('paste_attempt', {});
  showWarning('❌ Paste Blocked', 'Pasting text is not allowed during the exam.', 'danger');
  return false;
});

// 5. BLOCK RIGHT-CLICK / INSPECT ELEMENT
document.addEventListener('contextmenu', (e) => {
  e.preventDefault();
  tabSwitchCount++;
  logExamActivity('right_click_attempt', {});
  showWarning('❌ Blocked', 'Right-click is disabled during the exam.', 'danger');
  return false;
});

// 6. KEYBOARD SHORTCUT BLOCKING
document.addEventListener('keydown', (e) => {
  // Block ESC key (prevents exiting fullscreen)
  if (e.key === 'Escape') {
    e.preventDefault();
    tabSwitchCount++;
    logExamActivity('escape_attempt', {message: 'Student attempted to exit fullscreen with ESC key'});
    showWarning('⚠️ Security Warning', 'Exiting fullscreen is not allowed during the exam. Your activity has been logged.', 'danger');
    // Re-request fullscreen
    requestFullscreen();
    return false;
  }
  
  // Block F12 (Dev Tools)
  if (e.key === 'F12') {
    e.preventDefault();
    logExamActivity('f12_attempt', {});
    showWarning('❌ Dev Tools Blocked', 'Developer tools are disabled during exams.', 'danger');
    return false;
  }
  
  // Block Ctrl+Shift+I, Ctrl+Shift+C (Inspect)
  if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'I' || e.key === 'C')) {
    e.preventDefault();
    logExamActivity('inspect_attempt', {});
    showWarning('❌ Inspect Blocked', 'Inspect element is disabled during exams.', 'danger');
    return false;
  }
  
  // Block Ctrl+U (View Source)
  if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
    e.preventDefault();
    logExamActivity('view_source_attempt', {});
    showWarning('❌ View Source Blocked', 'Viewing page source is disabled during exams.', 'danger');
    return false;
  }
  
  // Block Alt+Tab (Switch applications) - Ctrl+Alt on Mac
  if ((e.ctrlKey && e.altKey) || (e.altKey && e.key === 'Tab')) {
    e.preventDefault();
    tabSwitchCount++;
    logExamActivity('alt_tab_attempt', {});
    showWarning('⚠️ Warning', 'Switching applications is not allowed. This has been logged.', 'warning');
    return false;
  }
  
  // Block Ctrl+W / Cmd+W (Close tab)
  if ((e.ctrlKey || e.metaKey) && e.key === 'w') {
    e.preventDefault();
    tabSwitchCount++;
    logExamActivity('close_tab_attempt', {});
    showWarning('⚠️ Warning', 'You cannot close this tab during the exam.', 'warning');
    return false;
  }
  
  // Block Ctrl+T / Cmd+T (New tab)
  if ((e.ctrlKey || e.metaKey) && e.key === 't') {
    e.preventDefault();
    tabSwitchCount++;
    logExamActivity('new_tab_attempt', {});
    showWarning('⚠️ Warning', 'Opening new tabs is not allowed during the exam.', 'warning');
    return false;
  }
});

// 7. WARNING NOTIFICATION
function showWarning(title, message, type = 'warning') {
  const alertBox = document.createElement('div');
  alertBox.className = `alert alert-${type} alert-dismissible fade show`;
  alertBox.setAttribute('role', 'alert');
  alertBox.innerHTML = `
    <strong>${title}:</strong> ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  `;
  alertBox.style.position = 'fixed';
  alertBox.style.top = '20px';
  alertBox.style.right = '20px';
  alertBox.style.zIndex = '9999';
  alertBox.style.maxWidth = '400px';
  alertBox.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
  document.body.appendChild(alertBox);
  
  setTimeout(() => {
    alertBox.remove();
  }, 5000);
}

// 8. LOG EXAM ACTIVITY
function logExamActivity(activityType, details) {
  // Send to server
  fetch('<?= app_href('api/log_exam_activity.php') ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      attempt_id: <?= $attemptId ?>,
      activity_type: activityType,
      details: details,
      timestamp: new Date().toISOString(),
      tab_switches: tabSwitchCount
    })
  }).catch(err => console.log('Activity logged'));
}

// 9. FULLSCREEN EXIT DETECTION
document.addEventListener('fullscreenchange', () => {
  if (!document.fullscreenElement && examStarted) {
    // Student exited fullscreen
    tabSwitchCount++;
    logExamActivity('fullscreen_exit', {message: 'Student exited fullscreen mode'});
    showWarning('⚠️ Security Violation', 'You exited fullscreen mode. This has been logged as suspicious activity.', 'danger');
    // Try to re-request fullscreen
    setTimeout(() => {
      requestFullscreen();
    }, 500);
  }
});

// 10. WINDOW BLUR/FOCUS (Student switches focus away)
window.addEventListener('blur', () => {
  if (examStarted && !document.hidden) {
    tabSwitchCount++;
    pauseTimer = true;
    logExamActivity('window_blur', {message: 'Student lost focus on exam window'});
    showWarning('⚠️ Warning', 'You switched focus away from the exam window. Your timer has been paused.', 'warning');
  }
});

window.addEventListener('focus', () => {
  if (examStarted) {
    pauseTimer = false;
    showWarning('✓ Welcome Back', 'Your timer has resumed.', 'info');
  }
});

// 11. WARN ON PAGE UNLOAD
window.addEventListener('beforeunload', (e) => {
  if (examStarted && tabSwitchCount > 3) {
    e.preventDefault();
    e.returnValue = 'Warning: Multiple suspicious activities detected. Your exam may be flagged for review.';
    return e.returnValue;
  }
});

// Anti-cheating measures only activate after modal accepted
</script>

<script>
let remaining = <?= $remaining ?>;
let pauseTimer = false;
const timerEl = document.getElementById('time');

function formatTime(seconds) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return m + 'm ' + s.toString().padStart(2, '0') + 's';
}

function updateTimerColor() {
  const card = timerEl.closest('.app-card');
  let color, gradientStart, gradientEnd;
  
  if (remaining > 600) {
    color = '#00d084';
    gradientStart = '#00d084';
    gradientEnd = '#13c084';
  } else if (remaining > 300) {
    color = '#ffc107';
    gradientStart = '#ffc107';
    gradientEnd = '#ff9800';
  } else {
    color = '#dc3545';
    gradientStart = '#dc3545';
    gradientEnd = '#c82333';
  }
  
  // Update border color
  if (card) {
    card.style.borderLeftColor = color;
  }
  
  // Update timer text color
  timerEl.style.color = color;
  
  // Update icon background gradient
  const iconDiv = card.querySelector('[style*="background: linear-gradient"]');
  if (iconDiv) {
    iconDiv.style.background = `linear-gradient(135deg, ${gradientStart} 0%, ${gradientEnd} 100%)`;
  }
}

function tick(){
  if(!examStarted) return; // Don't start until modal accepted
  if(!pauseTimer) {
    remaining--;
  }
  
  if(remaining <= 0){
    timerEl.textContent = 'Time Up!';
    timerEl.style.color = '#dc3545';
    document.querySelectorAll('input[type=radio]').forEach(i=>i.disabled=true);
    
    // Auto-save all answers when time expires
    autoSaveAndSubmit();
  } else {
    timerEl.textContent = formatTime(remaining);
    updateTimerColor();
    setTimeout(tick, 1000);
  }
}

// Auto-save answers and submit when time expires
function autoSaveAndSubmit() {
  showWarning('⏰ Time Expired', 'Automatically saving your answers and submitting...', 'warning');
  
  // Collect all selected answers
  const formData = new FormData();
  const radios = document.querySelectorAll('input[type=radio]:checked');
  radios.forEach(radio => {
    formData.append(radio.name, radio.value);
  });
  formData.append('final_submit', '1');
  
  // Send to server
  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (response.ok) {
      // Redirect to result page
      window.location.href = '<?= app_href('student/result.php?attempt_id=' . $attemptId) ?>';
    } else {
      showWarning('❌ Error', 'Failed to submit. Please try manually.', 'danger');
      // Enable manual submit as fallback
      const finalBtn = document.getElementById('finalBtn');
      if(finalBtn) {
        finalBtn.disabled = false;
        finalBtn.textContent = 'Submit Now (Time Expired)';
      }
    }
  })
  .catch(err => {
    console.error('Auto-submit error:', err);
    showWarning('❌ Error', 'Failed to auto-submit. Please submit manually.', 'danger');
    // Enable manual submit as fallback
    const finalBtn = document.getElementById('finalBtn');
    if(finalBtn) {
      finalBtn.disabled = false;
      finalBtn.textContent = 'Submit Now (Time Expired)';
    }
  });
}

function updateProgress() {
  const totalQuestions = <?= count($questions) ?>;
  const answeredInputs = document.querySelectorAll('input[type=radio]:checked');
  const answeredCount = answeredInputs.length;
  
  document.getElementById('answered-count').textContent = answeredCount;
  const percentage = (answeredCount / totalQuestions) * 100;
  document.getElementById('progress-bar').style.width = percentage + '%';
}

// Periodic auto-save function to save answers in background
function periodicAutoSave() {
  if (!examStarted) return; // Only check if exam started, not if time remaining
  
  const formData = new FormData();
  const radios = document.querySelectorAll('input[type=radio]:checked');
  radios.forEach(radio => {
    formData.append(radio.name, radio.value);
  });
  formData.append('save_progress', '1');
  
  fetch(window.location.href, {
    method: 'POST',
    body: formData
  }).catch(err => console.log('Auto-save background error:', err));
}

// Auto-save every 30 seconds
setInterval(periodicAutoSave, 30000);

window.addEventListener('load', () => {
  // Don't auto-start - wait for modal acceptance
  updateProgress(); // But still update progress display
  
  // Add scroll effect to sticky bar
  const stickyBar = document.getElementById('sticky-info-bar');
  let lastScrollTop = 0;
  
  window.addEventListener('scroll', () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop > 50) {
      // Add enhanced shadow when scrolled
      stickyBar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
      stickyBar.style.paddingTop = '0.75rem';
      stickyBar.style.paddingBottom = '0.75rem';
    } else {
      // Lighter shadow at top
      stickyBar.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
      stickyBar.style.paddingTop = '1rem';
      stickyBar.style.paddingBottom = '1rem';
    }
    
    lastScrollTop = scrollTop;
  });
});
</script>

<script>
// Keyboard navigation for questions
(function(){
  const blocks = Array.from(document.querySelectorAll('.question-block'));
  function focusBlock(idx){
    if(idx<0||idx>=blocks.length) return; 
    const b = blocks[idx];
    b.scrollIntoView({behavior: 'smooth', block: 'center'});
    const firstRadio = b.querySelector('input[type=radio]');
    if(firstRadio) { firstRadio.focus(); } else { b.focus(); }
  }
  document.addEventListener('keydown', (e)=>{
    const active = document.activeElement; 
    const currentIndex = blocks.findIndex(b=>b.contains(active));
    if((e.altKey && e.key==='ArrowRight') || (e.altKey && e.key.toLowerCase()==='n')){ 
      e.preventDefault(); 
      focusBlock(currentIndex+1); 
    }
    if((e.altKey && e.key==='ArrowLeft') || (e.altKey && e.key.toLowerCase()==='p')){ 
      e.preventDefault(); 
      focusBlock(currentIndex-1); 
    }
  });
})();
</script>

<?php render_footer(); ?>