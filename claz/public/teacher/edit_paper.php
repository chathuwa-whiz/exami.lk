<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'teacher') { http_response_code(403); echo 'Forbidden'; exit; }

$paperId = (int)($_GET['paper_id'] ?? 0);
if (!$paperId) { echo 'Missing paper'; exit; }
$pdo = db();

// Fetch paper and questions
$pStmt = $pdo->prepare('SELECT id, title, fee_cents, time_limit_seconds, is_published FROM papers WHERE id=? AND teacher_id=?');
$pStmt->execute([$paperId, $user['id']]);
$paper = $pStmt->fetch();
if (!$paper) { echo 'Paper not found'; exit; }

$qStmt = $pdo->prepare('SELECT id, question_text, marks, position, image_path FROM questions WHERE paper_id=? ORDER BY position');
$qStmt->execute([$paperId]);
$questions = $qStmt->fetchAll();
$oStmt = $pdo->prepare('SELECT id, option_text, is_correct, attachment_path FROM answer_options WHERE question_id=? ORDER BY id');
foreach ($questions as &$q) {
    $oStmt->execute([$q['id']]);
    $q['options'] = $oStmt->fetchAll();
}
unset($q);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $isAutosave = (($_POST['autosave'] ?? '') === '1');
  if (!csrf_verify()) {
    if ($isAutosave) {
      header('Content-Type: application/json');
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token', 'csrf' => csrf_token()]);
      exit;
    }
    $errors[] = 'Invalid CSRF token';
  }
    $title = trim($_POST['title'] ?? '');
    $fee = (int)($_POST['fee_cents'] ?? 0);
    $timeLimit = (int)($_POST['time_limit_minutes'] ?? 0) * 60;
    $publish = isset($_POST['publish']) ? 1 : 0;
    $questionsIn = $_POST['questions'] ?? [];
    $optionsIn = $_POST['options'] ?? [];
    $correctIn = $_POST['correct'] ?? [];
    $marksIn = $_POST['marks'] ?? [];

    if (!$isAutosave) {
      if (!$title || $timeLimit <= 0) { $errors[] = 'Title and time limit required.'; }
      if (empty($questionsIn)) { $errors[] = 'At least one question required.'; }
    } else {
      if ($timeLimit <= 0) { $timeLimit = 60; }
    }
         
    if (!$errors) {
        try {
            $pdo->beginTransaction();
        if ($isAutosave) {
          $u = $pdo->prepare('UPDATE papers SET title=?, fee_cents=?, time_limit_seconds=? WHERE id=? AND teacher_id=?');
          $u->execute([$title, $fee, $timeLimit, $paperId, $user['id']]);
        } else {
          $u = $pdo->prepare('UPDATE papers SET title=?, fee_cents=?, time_limit_seconds=?, is_published=? WHERE id=? AND teacher_id=?');
          $u->execute([$title, $fee, $timeLimit, $publish, $paperId, $user['id']]);
        }

        if (!$isAutosave) {
          $pdo->prepare('DELETE FROM questions WHERE paper_id=?')->execute([$paperId]);
          $questionsToInsert = [];
          $positionsIn = $_POST['insert_position'] ?? [];
          
          foreach ($questionsIn as $i => $qText) {
            $qText = trim($qText);
            if ($qText === '') { continue; }
            $marks = (int)($marksIn[$i] ?? 1); if ($marks < 1) { $marks = 1; }
            $requestedPos = isset($positionsIn[$i]) ? (int)$positionsIn[$i] : count($questionsToInsert) + 1;
            
            $existingImages = $_POST['existing_images'] ?? [];
            $imagePath = trim((string)($existingImages[$i] ?? '')) ?: null;
            if (isset($_FILES['question_images']) && isset($_FILES['question_images']['tmp_name'][$i]) && $_FILES['question_images']['tmp_name'][$i]) {
              $file = $_FILES['question_images'];
              $tmpFile = $file['tmp_name'][$i];
              $fileName = $file['name'][$i];
              $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
              if (in_array(strtolower($fileType), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
                $newFileName = 'q_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileType;
                $uploadDir = __DIR__ . '/uploads/questions/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                $destFile = $uploadDir . $newFileName;
                if (move_uploaded_file($tmpFile, $destFile)) { $imagePath = 'uploads/questions/' . $newFileName; }
              }
            }
            
            $optFiles = $_FILES['option_attachments'] ?? null;
            $existingOptAttachments = $_POST['existing_option_attachments'] ?? [];
            $optTexts = $optionsIn[$i] ?? [];
            $optsForQuestion = [];
            foreach ($optTexts as $j => $optText) {
              $optText = (string)$optText;
              $optAttachmentPath = trim((string)($existingOptAttachments[$i][$j] ?? '')) ?: null;
              if ($optFiles && isset($optFiles['tmp_name'][$i][$j]) && $optFiles['tmp_name'][$i][$j]) {
                $tmpFile = $optFiles['tmp_name'][$i][$j];
                $fileName = $optFiles['name'][$i][$j] ?? '';
                $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
                if (in_array(strtolower($fileType), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
                  $newFileName = 'opt_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileType;
                  $uploadDir = __DIR__ . '/uploads/options/';
                  if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                  $destFile = $uploadDir . $newFileName;
                  if (move_uploaded_file($tmpFile, $destFile)) { $optAttachmentPath = 'uploads/options/' . $newFileName; }
                }
              }
              $optsForQuestion[] = ['text' => $optText, 'attachment' => $optAttachmentPath, 'index' => $j];
            }

            $questionsToInsert[] = [
              'text' => $qText, 'marks' => $marks, 'position' => $requestedPos,
              'image' => $imagePath, 'options' => $optsForQuestion, 'correct' => $correctIn[$i] ?? ''
            ];
          }
          
          usort($questionsToInsert, function($a, $b) { return $a['position'] - $b['position']; });
          
          $qIns = $pdo->prepare('INSERT INTO questions (paper_id, question_text, marks, position, image_path) VALUES (?,?,?,?,?)');
          $oIns = $pdo->prepare('INSERT INTO answer_options (question_id, option_text, is_correct, attachment_path) VALUES (?,?,?,?)');
          
          foreach ($questionsToInsert as $idx => $q) {
            $qIns->execute([$paperId, $q['text'], $q['marks'], $idx + 1, $q['image']]);
            $newQid = $pdo->lastInsertId();
            foreach ($q['options'] as $opt) {
              $isCorrect = ((string)$q['correct'] === (string)$opt['index']) ? 1 : 0;
              $oIns->execute([$newQid, trim((string)$opt['text']), $isCorrect, $opt['attachment']]);
            }
          }
        }

            $pdo->commit();
        if ($isAutosave) {
          header('Content-Type: application/json');
          echo json_encode(['ok' => true, 'paper_id' => $paperId, 'csrf' => csrf_token()]);
          exit;
        } else {
          $success = 'Paper updated.';
          header('Location: ' . app_href('teacher/edit_paper.php?paper_id=' . $paperId . '&saved=1'));
          exit;
        }
        } catch (Throwable $e) {
            $pdo->rollBack();
        if ($isAutosave) {
          header('Content-Type: application/json');
          http_response_code(500);
          echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage(), 'csrf' => csrf_token()]);
          exit;
        } else {
          $errors[] = 'Error updating paper: ' . $e->getMessage();
        }
        }
    }
}

$paperJson = json_encode($paper);
$questionsJson = json_encode($questions);
render_header('Edit Paper');
?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mathquill/0.10.1/mathquill.min.css" crossorigin="anonymous" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/mathquill/0.10.1/mathquill.min.js" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" />
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

<script>
  function renderMath() {
    if (window.renderMathInElement) {
      try {
        window.renderMathInElement(document.body, {
          delimiters: [
            {left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false},
            {left: '\\[', right: '\\]', display: true}, {left: '\\(', right: '\\)', display: false}
          ], throwOnError: false
        });
      } catch(err) { console.log('KaTeX error:', err.message); }
    }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', renderMath); } else { renderMath(); }
</script>

<section style="background: white; border-top: 4px solid teal; padding: 2rem 0; margin-bottom: 2rem;">
  <div class="container" style="display: flex; justify-content: space-between; align-items: center; max-width: 900px;">
    <h1 style="font-size: 2rem; font-weight: 400; color: #333; margin: 0;">Edit Paper: <span id="displayTitle"><?= htmlspecialchars($paper['title']) ?></span></h1>
    <div>
        <span class="badge" style="background: <?= $paper['is_published'] ? '#e8f5e9; color: #2e7d32;' : '#f1f3f4; color: #5f6368;' ?> padding: 0.5rem 1rem; border-radius: 20px; font-weight: normal; margin-right: 1rem;">
            <?= $paper['is_published'] ? 'Published' : 'Draft' ?>
        </span>
        <a href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>" style="color: #666; text-decoration: none; border: 1px solid #ccc; padding: 0.5rem 1rem; border-radius: 4px; font-size: 0.9rem;">Back</a>
    </div>
  </div>
</section>

<section style="background: white; padding: 0; margin-bottom: 2rem;">
  <div style="padding: 0 2rem 2rem 2rem; max-width: 900px; margin: 0 auto;">
    
    <?php if (isset($_GET['saved'])): ?>
      <div style="background: #efe; border-left: 4px solid #00d084; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; color: #00d084;">
        <i class="bi bi-check-circle-fill me-2"></i>Saved successfully.
      </div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div style="background: #fee; border-left: 4px solid #dc3545; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; color: #dc3545;">
        <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($e) ?>
      </div>
    <?php endforeach; ?>
    
    <form method="post" id="editForm" accept-charset="UTF-8" enctype="multipart/form-data">
      <?= csrf_field(); ?>
      
      <div style="background: #f4f4f4; border-top: 4px solid teal; padding: 2rem; margin-bottom: 1rem;">
        <input class="form-control" name="title" id="title" placeholder="Untitled form" required style="border: none; background: transparent; padding: 0.5rem 0; font-size: 1.8rem; font-weight: 400; border-bottom: 1px solid #e0e0e0; margin-bottom: 1.5rem;" oninput="document.getElementById('displayTitle').innerText = this.value || 'Untitled form'">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #666; font-size: 0.9rem;">Fee (cents)</label>
            <input type="number" class="form-control" name="fee_cents" id="fee_cents" min="0" style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.75rem; font-size: 0.95rem;">
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #666; font-size: 0.9rem;">Time Limit (minutes)</label>
            <input type="number" class="form-control" name="time_limit_minutes" id="timeLimit" min="1" required style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.75rem; font-size: 0.95rem;">
          </div>
        </div>
      </div>

      <div style="background: #f4f4f4; padding: 1.5rem 2rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
        <label class="d-flex align-items-center gap-2" style="cursor: pointer; margin: 0;">
          <input type="checkbox" class="form-check-input" id="publishNow" name="publish" value="1" style="width: 18px; height: 18px; cursor: pointer;">
          <span style="font-weight: 500; color: #333; font-size: 0.95rem;">Publish immediately</span>
        </label>
        <span id="autosaveStatus" style="font-size: 0.85rem; color: #999;">Loading...</span>
      </div>

      <div id="questions" style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;" aria-live="polite"></div>
      
      <div style="text-align: center; margin-bottom: 2rem; display: flex; flex-direction: column; gap: 0.5rem; align-items: center;">
        <div style="color: #666; font-size: 0.9rem;">Add new question</div>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
          <button type="button" class="btn" style="background: #eef3ff; color: #4b5fc0; border: 2px solid #c5d1ff; font-weight: 500; padding: 0.6rem 1.5rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5rem;" onclick="addQuestion(null)">
            <i class="bi bi-check-circle"></i> Choice Question
          </button>
        </div>
      </div>

      <div style="display: flex; flex-wrap: wrap; gap: 1rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
        <button type="submit" class="btn" style="background: #0078d4; color: white; border: none; font-weight: 500; padding: 0.75rem 2rem; border-radius: 4px;">
          <i class="bi bi-save me-1"></i>Save Changes
        </button>
      </div>
    </form>
  </div>
</section>

<script>
const paperData = <?= $paperJson ?>;
const questionsData = <?= $questionsJson ?>;
let qIndex = 0;

const draftKey = 'edit_paper_draft_' + (paperData && paperData.id ? paperData.id : 'unknown');
let saveDraftTimer;

function debounceSaveDraft(){
  clearTimeout(saveDraftTimer);
  saveDraftTimer = setTimeout(saveDraft, 500);
}

function saveDraft(){
  try {
    const data = {
      title: document.getElementById('title')?.value || '',
      fee_cents: document.getElementById('fee_cents')?.value || '0',
      time_limit_minutes: document.getElementById('timeLimit')?.value || '',
      publish: document.getElementById('publishNow')?.checked ? 1 : 0,
      questions: []
    };

    document.querySelectorAll('.question-card').forEach((card, idx) => {
      const qText = card.querySelector('.question-text')?.value || '';
      const marks = card.querySelector(`input[name="marks[${idx}]"]`)?.value || '1';
      const existingImgInput = card.querySelector(`input[name="existing_images[${idx}]"]`);
      const image_path = existingImgInput ? existingImgInput.value : '';
      
      const options = [];
      let correctIndex = -1;
      
      card.querySelectorAll('.option-group').forEach((optDiv, j) => {
        const optText = optDiv.querySelector('input[type="text"]')?.value || '';
        const attachment = optDiv.querySelector('input[type="hidden"]')?.value || '';
        const isCorrect = optDiv.querySelector(`input[type="radio"]`)?.checked || false;
        options.push({ option_text: optText, attachment_path: attachment, is_correct: isCorrect });
        if (isCorrect) correctIndex = j;
      });

      data.questions.push({ question_text: qText, marks, options, correctIndex, image_path });
    });

    localStorage.setItem(draftKey, JSON.stringify(data));
    const el = document.getElementById('autosaveStatus');
    if (el) el.textContent = 'Draft saved at ' + new Date().toLocaleTimeString();
  } catch (e) {}
}

function loadDraft(){
  try {
    const raw = localStorage.getItem(draftKey);
    let dataToLoad;
    
    // If we have a draft, ask user if they want to load it (optional, but good practice). 
    // Here we auto-load it if it exists, otherwise fallback to DB data.
    if (raw) {
        dataToLoad = JSON.parse(raw);
    } else {
        // Build data structure from DB JSON
        dataToLoad = {
            title: paperData.title,
            fee_cents: paperData.fee_cents,
            time_limit_minutes: Math.max(1, Math.round((paperData.time_limit_seconds||60)/60)),
            publish: paperData.is_published,
            questions: questionsData
        };
    }

    // Populate paper basics
    if(document.getElementById('title')) document.getElementById('title').value = dataToLoad.title || '';
    if(document.getElementById('fee_cents')) document.getElementById('fee_cents').value = dataToLoad.fee_cents || '0';
    if(document.getElementById('timeLimit')) document.getElementById('timeLimit').value = dataToLoad.time_limit_minutes || '30';
    if(document.getElementById('publishNow')) document.getElementById('publishNow').checked = !!dataToLoad.publish;

    // Populate questions
    const qWrap = document.getElementById('questions');
    qWrap.innerHTML = '';
    qIndex = 0;

    if (Array.isArray(dataToLoad.questions) && dataToLoad.questions.length > 0) {
      dataToLoad.questions.forEach((q) => addQuestion(q));
    } else {
      addQuestion();
    }
    return true;
  } catch (e) {
    return false;
  }
}

function clearDraft(){ try { localStorage.removeItem(draftKey); } catch(e){} }

function updateQuestionCount(){
  document.querySelectorAll('.question-card').forEach((card, i) => {
    const header = card.querySelector('.q-number');
    if(header) header.textContent = `${i + 1}. Question`;
  });
}

function addQuestion(prefill){
  const currentIndex = qIndex;
  const wrap = document.createElement('div');
  wrap.className = 'question-card';
  wrap.style.cssText = 'padding: 2rem; background: #f4f4f4; border-top: 4px solid teal; border-radius: 0; margin-bottom: 1rem;';
  
  wrap.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
      <div style="flex: 1;">
        <div class="q-number" style="color: #333; font-size: 0.95rem; margin-bottom: 0.5rem; font-weight: 500;">${currentIndex + 1}. Question</div>
        <textarea class="form-control question-text" name="questions[${currentIndex}]" rows="2" placeholder="Type your question here..." required style="border: none; background: #e8e8e8; border-radius: 2px; padding: 0.75rem; font-size: 0.95rem; resize: none; font-family: 'Noto Sans Sinhala', 'Segoe UI', sans-serif; width: 100%;"></textarea>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center; margin-left: 1rem;">
        <button type="button" style="background: none; border: none; cursor: pointer; color: #666; padding: 0.5rem;" title="Add image/pdf" onclick="showImageUpload(${currentIndex})">
          <i class="bi bi-card-image" style="font-size: 1.1rem;"></i>
        </button>
        <button type="button" style="background: none; border: none; cursor: pointer; color: #666; padding: 0.5rem;" title="Delete" onclick="removeQuestion(this)">
          <i class="bi bi-trash" style="font-size: 1.1rem;"></i>
        </button>
        <button type="button" style="background: none; border: none; cursor: pointer; color: #666; padding: 0.5rem;" title="Move down" onclick="moveQuestion(this, 'down')">
          <i class="bi bi-arrow-down" style="font-size: 1.1rem;"></i>
        </button>
        <button type="button" style="background: none; border: none; cursor: pointer; color: #666; padding: 0.5rem;" title="Move up" onclick="moveQuestion(this, 'up')">
          <i class="bi bi-arrow-up" style="font-size: 1.1rem;"></i>
        </button>
      </div>
    </div>
    
    <div style="margin-bottom: 0.5rem;">
      <button type="button" class="btn btn-sm" style="background: none; color: #0078d4; border: none; padding: 0.25rem 0; font-size: 0.85rem; text-align: left; display: inline-flex; align-items: center; gap: 0.25rem;" onclick="toggleMathPalette(${currentIndex})">
        <img src="../assets/fx-sign.png" alt="Math Symbols" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' viewBox=\\'0 0 16 16\\'><text y=\\'14\\' font-size=\\'14\\'>∑</text></svg>'" style="width: 16px; height: 16px;">
        Math Symbols
      </button>
      <div id="mathPalette_${currentIndex}" style="display: none; background: white; border: 1px solid #e0e0e0; padding: 0.75rem; margin-top: 0.5rem; border-radius: 4px; flex-wrap: wrap; gap: 0.25rem;"></div>
    </div>
    
    <div style="display: none; margin-bottom: 1rem; background: white; padding: 1rem; border-radius: 4px;" id="imageUpload_${currentIndex}">
      <label style="font-size:0.85rem; color:#666;">Attach Image or PDF (Optional)</label>
      <input type="file" class="form-control" name="question_images[${currentIndex}]" accept="image/png,image/jpeg,image/gif,image/webp,application/pdf" style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.5rem; font-size: 0.85rem; background: white;">
      <input type="hidden" name="existing_images[${currentIndex}]" value="">
      
      <div id="img_preview_${currentIndex}" style="margin-top: 10px; display: none;">
        <img id="img_${currentIndex}" style="max-width: 100%; max-height: 200px; border-radius: 4px; border: 1px solid #ccc;">
      </div>
      <div id="pdf_preview_${currentIndex}" style="margin-top: 10px; display: none;">
        <a id="pdf_link_${currentIndex}" href="#" target="_blank" style="color:#0078d4; font-size:0.9rem;">📄 View Attached PDF</a>
      </div>
    </div>

    <div id="opts_section_${currentIndex}" style="margin-bottom: 1rem;">
      <div id="opts_${currentIndex}" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;"></div>
      <div style="display: flex; gap: 1rem; margin-top: 0.75rem;">
        <button type="button" style="background: none; color: #0078d4; border: none; padding: 0; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;" onclick="addOption(${currentIndex})">
          <i class="bi bi-plus-circle"></i> Add option
        </button>
      </div>
    </div>

    <div class="validation-alert" style="display: none; background: #fee; border-left: 4px solid #dc3545; padding: 0.75rem; border-radius: 4px; margin-top: 1rem;"></div>

    <div style="border-top: 1px solid #ccc; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; gap: 1rem; align-items: center;">
        <input type="number" class="form-control" name="marks[${currentIndex}]" min="1" value="1" required style="width: 70px; border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.5rem; font-size: 0.9rem;">
        <span style="color: #666; font-size: 0.9rem;">marks</span>
      </div>
      <div style="display: flex; align-items: center;">
        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; margin: 0;">
          <span style="color: #666; font-size: 0.9rem;">Required</span>
          <div style="position: relative; width: 48px; height: 24px; background: #0078d4; border-radius: 12px; transition: background 0.3s;">
            <input type="checkbox" checked style="opacity: 0; width: 0; height: 0; position: absolute;">
            <div style="position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: transform 0.3s; transform: translateX(24px);"></div>
          </div>
        </label>
      </div>
    </div>
  `;
  document.getElementById('questions').appendChild(wrap);
  
  const textarea = wrap.querySelector('.question-text');
  const mathPalette = wrap.querySelector('#mathPalette_' + currentIndex);

  // Setup Math Symbols
  const mathSymbols = [
    {char: '+', val: '+'}, {char: '−', val: '−'}, {char: '×', val: '×'}, {char: '÷', val: '÷'},
    {char: '=', val: '='}, {char: '≠', val: '≠'}, {char: '±', val: '±'}, {char: '>', val: '>'},
    {char: '<', val: '<'}, {char: '≥', val: '≥'}, {char: '≤', val: '≤'}, {char: '≈', val: '≈'},
    {char: 'x²', val: '²'}, {char: 'x³', val: '³'}, {char: 'xⁿ', val: 'ⁿ'}, {char: '√', val: '√'},
    {char: 'x₁', val: '₁'}, {char: 'x₂', val: '₂'}, {char: 'xₙ', val: 'ₙ'},
    {char: 'α', val: 'α'}, {char: 'β', val: 'β'}, {char: 'γ', val: 'γ'}, {char: 'θ', val: 'θ'},
    {char: 'π', val: 'π'}, {char: 'Σ', val: 'Σ'}, {char: 'Δ', val: 'Δ'}, {char: 'λ', val: 'λ'},
    {char: '∫', val: '∫'}, {char: '∞', val: '∞'}, {char: '°', val: '°'}, {char: '→', val: '→'}
  ];
  
  mathSymbols.forEach(symbol => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = symbol.char;
    btn.style.cssText = 'padding: 0.4rem 0.6rem; background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 3px; cursor: pointer; font-size: 0.95rem; min-width: 36px;';
    btn.onmouseover = () => btn.style.background = '#e8e8e8';
    btn.onmouseout = () => btn.style.background = '#f8f8f8';
    btn.onclick = () => {
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;
      textarea.value = text.substring(0, start) + symbol.val + text.substring(end);
      textarea.selectionStart = textarea.selectionEnd = start + symbol.val.length;
      textarea.focus();
      textarea.dispatchEvent(new Event('input'));
    };
    mathPalette.appendChild(btn);
  });

  // Prefill Data
  if (prefill) {
    textarea.value = prefill.question_text || '';
    wrap.querySelector(`input[name="marks[${currentIndex}]"]`).value = prefill.marks || '1';
    
    if (prefill.image_path) {
      wrap.querySelector(`input[name="existing_images[${currentIndex}]"]`).value = prefill.image_path;
      document.getElementById('imageUpload_' + currentIndex).style.display = 'block';
      const isPdf = /\.pdf$/i.test(prefill.image_path);
      showAttachmentPreview(currentIndex, prefill.image_path, isPdf);
    }

    if (Array.isArray(prefill.options) && prefill.options.length) {
      prefill.options.forEach((opt) => addOption(currentIndex, opt));
    } else {
      addOption(currentIndex); addOption(currentIndex);
    }
  } else {
    addOption(currentIndex); addOption(currentIndex);
  }

  // Bind Autosave
  textarea.addEventListener('input', debounceSaveDraft);
  wrap.querySelector(`input[name="marks[${currentIndex}]"]`).addEventListener('input', debounceSaveDraft);
  
  // Bind File upload preview
  wrap.querySelector(`input[type="file"]`).addEventListener('change', function(e) {
    const file = e.target.files[0];
    const existingInput = wrap.querySelector(`input[name="existing_images[${currentIndex}]"]`);
    if(existingInput) existingInput.value = ''; // clear old if new uploaded
    if(file){
      const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
      const url = URL.createObjectURL(file);
      showAttachmentPreview(currentIndex, url, isPdf);
    } else {
      showAttachmentPreview(currentIndex, '', false, true);
    }
  });

  qIndex++;
  updateQuestionCount();
  debounceSaveDraft();
}

function showImageUpload(index) {
  const uploadDiv = document.getElementById('imageUpload_' + index);
  uploadDiv.style.display = uploadDiv.style.display === 'none' ? 'block' : 'none';
}

function toggleMathPalette(index) {
  const palette = document.getElementById('mathPalette_' + index);
  palette.style.display = palette.style.display === 'none' ? 'flex' : 'none';
}

function moveQuestion(btn, direction) {
  const card = btn.closest('.question-card');
  if (direction === 'up' && card.previousElementSibling) {
    card.parentNode.insertBefore(card, card.previousElementSibling);
  } else if (direction === 'down' && card.nextElementSibling) {
    card.parentNode.insertBefore(card.nextElementSibling, card);
  }
  updateQuestionCount();
  debounceSaveDraft();
}

function removeQuestion(btn){
  btn.closest('.question-card').remove();
  updateQuestionCount();
  debounceSaveDraft();
}

function addOption(i, prefill){
  const oWrap = document.getElementById('opts_' + i);
  if(!oWrap) return;
  const count = oWrap.querySelectorAll('.option-group').length;
  
  const inputGroup = document.createElement('div');
  inputGroup.className = 'option-group';
  inputGroup.style.cssText = 'display: flex; gap: 0.75rem; align-items: center; padding: 0.5rem; background: white; border-radius: 2px;';
  
  inputGroup.innerHTML = `
    <input type="radio" name="correct[${i}]" value="${count}" id="correct_${i}_${count}" style="width: 18px; height: 18px; cursor: pointer; margin: 0; flex-shrink: 0;">
    <input type="text" class="form-control" name="options[${i}][${count}]" placeholder="Option ${count + 1}" required style="border: none; padding: 0.25rem; font-size: 0.95rem; flex: 1; background: transparent; outline: none; border-bottom: 1px solid #eee;">
    
    <div style="position:relative; flex-shrink:0;">
      <input type="file" name="option_attachments[${i}][${count}]" accept="image/png,image/jpeg,image/gif,image/webp,application/pdf" style="display:none;" id="opt_file_${i}_${count}">
      <input type="hidden" name="existing_option_attachments[${i}][${count}]" value="">
      <button type="button" style="background: none; border: none; cursor: pointer; color: #999; padding: 0.25rem;" title="Add image" onclick="document.getElementById('opt_file_${i}_${count}').click()">
        <i class="bi bi-card-image" style="font-size: 1rem;"></i>
      </button>
    </div>
    
    <button type="button" style="background: none; border: none; cursor: pointer; color: #999; padding: 0.25rem; flex-shrink: 0;" title="Remove option" onclick="this.parentElement.remove(); debounceSaveDraft();">
      <i class="bi bi-x-lg" style="font-size: 0.9rem;"></i>
    </button>
  `;
  oWrap.appendChild(inputGroup);

  const radio = inputGroup.querySelector('input[type="radio"]');
  const textInput = inputGroup.querySelector('input[type="text"]');
  const fileInput = inputGroup.querySelector(`input[type="file"]`);
  const hiddenInput = inputGroup.querySelector(`input[type="hidden"]`);

  if (prefill) {
    if(prefill.option_text) textInput.value = prefill.option_text;
    if(prefill.is_correct) radio.checked = true;
    if(prefill.attachment_path) {
        hiddenInput.value = prefill.attachment_path;
        // Option Image UI indicator (optional minimal UI to show file is attached)
        textInput.style.borderLeft = "3px solid #0078d4";
        textInput.title = "File attached: " + prefill.attachment_path;
    }
  }

  textInput.addEventListener('input', debounceSaveDraft);
  radio.addEventListener('change', debounceSaveDraft);
  
  fileInput.addEventListener('change', (e) => {
      hiddenInput.value = ''; // clear old
      if(e.target.files[0]) {
          textInput.style.borderLeft = "3px solid #00d084";
          textInput.placeholder = "Image attached. Type text...";
      } else {
          textInput.style.borderLeft = "none";
      }
      debounceSaveDraft();
  });
  
  debounceSaveDraft();
}

function showAttachmentPreview(idx, url, isPdf, clearOnly){
  const imgPreview = document.getElementById('img_preview_' + idx);
  const img = document.getElementById('img_' + idx);
  const pdfPreview = document.getElementById('pdf_preview_' + idx);
  const pdfLink = document.getElementById('pdf_link_' + idx);

  if (!imgPreview || !pdfPreview) return;

  if (clearOnly || !url) {
    imgPreview.style.display = 'none';
    pdfPreview.style.display = 'none';
    if(img) img.removeAttribute('src');
    return;
  }

  if (isPdf) {
    imgPreview.style.display = 'none';
    if (pdfLink) pdfLink.href = url;
    pdfPreview.style.display = 'block';
  } else {
    if (img) img.src = url;
    imgPreview.style.display = 'block';
    pdfPreview.style.display = 'none';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Try to load draft/DB data
  loadDraft();

  // Attach autosave events to paper basics
  const titleInput = document.getElementById('title');
  const feeInput = document.getElementById('fee_cents');
  const timeInput = document.getElementById('timeLimit');
  const publishInput = document.getElementById('publishNow');

  if (titleInput) titleInput.addEventListener('input', debounceSaveDraft);
  if (feeInput) feeInput.addEventListener('input', debounceSaveDraft);
  if (timeInput) timeInput.addEventListener('input', debounceSaveDraft);
  if (publishInput) publishInput.addEventListener('change', debounceSaveDraft);

  // Validate on submit
  document.getElementById('editForm').addEventListener('submit', (e) => {
    const questions = document.querySelectorAll('.question-card');
    let hasError = false;
    
    questions.forEach((card, idx) => {
      const radios = card.querySelectorAll(`input[type="radio"]`);
      const isChecked = Array.from(radios).some(r => r.checked);
      const alertBox = card.querySelector('.validation-alert');
      
      if (!isChecked && radios.length > 0) {
        hasError = true;
        if (alertBox) {
          alertBox.style.display = 'block';
          alertBox.innerHTML = '<p style="margin: 0; color: #dc3545; font-size: 0.9rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>ERROR:</strong> You must select which option is correct!</p>';
        }
      } else if (alertBox) {
        alertBox.style.display = 'none';
      }
    });
    
    if (hasError) {
      e.preventDefault();
      alert('Please select the correct answer for all questions before submitting!');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      autosaveEnabled = false; // stop background save
      clearDraft();
    }
  });

  // Background Autosave to Server
  let autosaveEnabled = true;
  async function autosaveToServer() {
    if (document.visibilityState !== 'visible' || !autosaveEnabled) return;
    try {
      const fd = new FormData(document.getElementById('editForm'));
      fd.append('autosave', '1');
      const res = await fetch(window.location.href, { method: 'POST', body: fd });
      const data = await res.json().catch(() => null);
      if (data && typeof data.csrf === 'string') {
        const csrfField = document.querySelector('input[name="_csrf"]');
        if (csrfField) csrfField.value = data.csrf;
      }
      const status = document.getElementById('autosaveStatus');
      if (status && data && data.ok) status.textContent = 'Autosaved to server at ' + new Date().toLocaleTimeString();
    } catch (e) { }
  }
  setInterval(() => { if (document.visibilityState === 'visible') { autosaveToServer(); } }, 60000);
});
</script>

<?php render_footer(); ?>
