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

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Invalid CSRF token'; }
    $title = trim($_POST['title'] ?? '');
    $fee = (int)($_POST['fee_cents'] ?? 0);
    $timeLimit = (int)($_POST['time_limit_minutes'] ?? 0) * 60;
    $questions = $_POST['questions'] ?? [];
    $options = $_POST['options'] ?? [];
    $correct = $_POST['correct'] ?? [];
    $publish = isset($_POST['publish']) ? 1 : 0;

    if (!$title || $timeLimit <= 0) { $errors[] = 'Title and time limit required.'; }
    if (empty($questions)) { $errors[] = 'At least one question required.'; }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO papers (teacher_id,title,fee_cents,time_limit_seconds,is_published) VALUES (?,?,?,?,?)');
            $stmt->execute([$user['id'], $title, $fee, $timeLimit, $publish]);
            $paperId = $pdo->lastInsertId();
            foreach ($questions as $i => $qText) {
                $qText = trim($qText);
                if ($qText === '') { continue; }
                $marks = (int)($_POST['marks'][$i] ?? 1);
                if ($marks < 1) $marks = 1;
                
                // Handle image upload for question
                $imagePath = null;
                if (isset($_FILES['question_images']) && isset($_FILES['question_images']['tmp_name'][$i]) && $_FILES['question_images']['tmp_name'][$i]) {
                    $file = $_FILES['question_images'];
                    $tmpFile = $file['tmp_name'][$i];
                    $fileName = $file['name'][$i];
                    $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
                    
                    // Validate image and PDF
                    if (in_array(strtolower($fileType), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
                        $newFileName = 'q_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileType;
                        $uploadDir = __DIR__ . '/uploads/questions/';
                        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                        
                        $destFile = $uploadDir . $newFileName;
                        if (move_uploaded_file($tmpFile, $destFile)) {
                            $imagePath = 'uploads/questions/' . $newFileName;
                        }
                    }
                }
                
                $stmtQ = $pdo->prepare('INSERT INTO questions (paper_id,question_text,marks,position,image_path) VALUES (?,?,?,?,?)');
                $stmtQ->execute([$paperId, $qText, $marks, $i + 1, $imagePath]);
                $questionId = $pdo->lastInsertId();
                $opts = $options[$i] ?? [];
                foreach ($opts as $j => $optText) {
                    $stmtO = $pdo->prepare('INSERT INTO answer_options (question_id,option_text,is_correct) VALUES (?,?,?)');
                    $stmtO->execute([$questionId, trim($optText), ($correct[$i] ?? -1) == $j ? 1 : 0]);
                }
            }
            $pdo->commit();
            $success = 'Paper created (ID ' . $paperId . ').';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}
render_header('Create Paper');
?>

<!-- Load jQuery first for MathQuill -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- MathQuill CSS & JS for visual math editor -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mathquill/0.10.1/mathquill.min.css" crossorigin="anonymous" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/mathquill/0.10.1/mathquill.min.js" crossorigin="anonymous"></script>

<!-- Load KaTeX for math rendering -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" />
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

<script>
  // Initialize KaTeX rendering when script loads
  function renderMath() {
    if (window.renderMathInElement) {
      try {
        window.renderMathInElement(document.body, {
          delimiters: [
            {left: '$$', right: '$$', display: true},
            {left: '$', right: '$', display: false},
            {left: '\\[', right: '\\]', display: true},
            {left: '\\(', right: '\\)', display: false}
          ],
          throwOnError: false
        });
      } catch(err) {
        console.log('KaTeX render error:', err.message);
      }
    }
  }
  
  // Render when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderMath);
  } else {
    renderMath();
  }
</script>

<!-- Hero Section -->
<section style="background: white; border-top: 4px solid teal; padding: 2rem 0; margin-bottom: 2rem;">
  <div class="container">
    <h1 style="font-size: 2rem; font-weight: 400; color: #333; margin: 0;">Create New Paper</h1>
  </div>
</section>



<!-- Form Section -->
<section style="background: white; padding: 0; margin-bottom: 2rem;">
  <div style="padding: 2rem; max-width: 900px; margin: 0 auto;">
    <?php foreach ($errors as $e): ?>
      <div style="background: #fee; border-left: 4px solid #dc3545; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; color: #dc3545;">
        <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($e) ?>
      </div>
    <?php endforeach; ?>
    <?php if ($success): ?>
      <div style="background: #efe; border-left: 4px solid #00d084; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; color: #00d084;">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    
    <form method="post" id="paperForm" accept-charset="UTF-8" enctype="multipart/form-data">
      <?= csrf_field(); ?>
      
      <!-- Paper Basics -->
      <div style="background: #f4f4f4; border-top: 4px solid teal; padding: 2rem; margin-bottom: 1rem;">
        <input class="form-control" name="title" placeholder="Untitled form" required style="border: none; background: transparent; padding: 0.5rem 0; font-size: 1.8rem; font-weight: 400; border-bottom: 1px solid #e0e0e0; margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #666; font-size: 0.9rem;">Fee (cents)</label>
            <input type="number" class="form-control" name="fee_cents" value="0" min="0" style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.75rem; font-size: 0.95rem;">
          </div>
          <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #666; font-size: 0.9rem;">Time Limit (minutes)</label>
            <input type="number" class="form-control" name="time_limit_minutes" id="timeLimit" value="30" min="1" required style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.75rem; font-size: 0.95rem;">
          </div>
        </div>
      </div>

      <!-- Publish Option -->
      <div style="background: #f4f4f4; padding: 1.5rem 2rem; margin-bottom: 1rem;">
        <label class="d-flex align-items-center gap-2" style="cursor: pointer; margin: 0;">
          <input type="checkbox" class="form-check-input" id="publishNow" name="publish" value="1" style="width: 18px; height: 18px; cursor: pointer;">
          <span style="font-weight: 500; color: #333; font-size: 0.95rem;">Publish immediately</span>
        </label>
      </div>

      <!-- Questions Section Header -->
      <div style="margin-bottom: 1rem;"></div>

      <!-- Questions Container -->
      <div id="questions" style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;" aria-live="polite"></div>
      
      <!-- Add Question Buttons -->
      <div style="text-align: center; margin-bottom: 2rem; display: flex; flex-direction: column; gap: 0.5rem; align-items: center;">
        <div style="color: #666; font-size: 0.9rem;">Add new question</div>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
          <button type="button" class="btn" style="background: #eef3ff; color: #4b5fc0; border: 2px solid #c5d1ff; font-weight: 500; padding: 0.6rem 1.5rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5rem;" onclick="addQuestion(null, 'choice')">
            <i class="bi bi-check-circle"></i> Choice
          </button>
          <button type="button" class="btn" style="background: #eef3ff; color: #4b5fc0; border: 2px solid #c5d1ff; font-weight: 500; padding: 0.6rem 1.5rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.5rem;" onclick="addQuestion(null, 'text')">
            <i class="bi bi-input-cursor-text"></i> Text
          </button>
        </div>
      </div>

      <!-- Form Actions -->
      <div style="display: flex; flex-wrap: wrap; gap: 1rem; padding-top: 2rem; border-top: 1px solid #e0e0e0;">
        <button type="submit" class="btn" style="background: #0078d4; color: white; border: none; font-weight: 500; padding: 0.75rem 2rem; border-radius: 4px;">
          <i class="bi bi-save me-1"></i>Save Paper
        </button>
        <a class="btn" href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>" style="background: white; color: #666; border: 1px solid #ccc; font-weight: 500; padding: 0.75rem 2rem; border-radius: 4px; text-decoration: none;">
          Cancel
        </a>
      </div>
    </form>
  </div>
</section>
<script>
let qIndex = 0;
const draftKey = 'paper_form_draft_v4';
let saveDraftTimer;

function debounceSaveDraft(){
  clearTimeout(saveDraftTimer);
  saveDraftTimer = setTimeout(saveDraft, 400);
}

function saveDraft(){
  const form = document.getElementById('paperForm');
  if (!form) return;

  const data = {
    title: form.querySelector('input[name="title"]')?.value || '',
    fee: form.querySelector('input[name="fee_cents"]')?.value || '0',
    timeLimit: form.querySelector('input[name="time_limit_minutes"]')?.value || '',
    publish: form.querySelector('#publishNow')?.checked || false,
    questions: []
  };

  document.querySelectorAll('.question-card').forEach((card) => {
    const qText = card.querySelector('.question-text')?.value || '';
    const marks = card.querySelector('input[name^="marks["]')?.value || '1';
    const options = [];
    const type = card.dataset.qtype || (card.querySelectorAll('input[name^="options"]').length ? 'choice' : 'text');
    let correct = null;

    card.querySelectorAll('input[name^="options"]').forEach((optInput) => {
      options.push(optInput.value || '');
    });

    const radios = card.querySelectorAll('input[type="radio"][name^="correct["]');
    radios.forEach((r, idx) => { if (r.checked) correct = idx; });

    data.questions.push({ text: qText, marks, options, correct, type });
  });

  try {
    localStorage.setItem(draftKey, JSON.stringify(data));
  } catch (err) {
    console.warn('Could not save draft', err);
  }
}

function clearDraft(){
  try { localStorage.removeItem(draftKey); } catch (err) {}
}

function loadDraft(){
  let raw;
  try {
    raw = localStorage.getItem(draftKey);
  } catch (err) {
    return false;
  }
  if (!raw) return false;

  let data;
  try {
    data = JSON.parse(raw);
  } catch (err) {
    clearDraft();
    return false;
  }

  const form = document.getElementById('paperForm');
  if (!form) return false;

  const titleInput = form.querySelector('input[name="title"]');
  const feeInput = form.querySelector('input[name="fee_cents"]');
  const timeInput = form.querySelector('input[name="time_limit_minutes"]');
  const publishInput = form.querySelector('#publishNow');

  if (titleInput) titleInput.value = data.title || '';
  if (feeInput) feeInput.value = data.fee || '0';
  if (timeInput) timeInput.value = data.timeLimit || '';
  if (publishInput) publishInput.checked = !!data.publish;

  const qWrap = document.getElementById('questions');
  qWrap.innerHTML = '';
  qIndex = 0;

  if (Array.isArray(data.questions) && data.questions.length) {
    data.questions.forEach((q) => addQuestion(q, q.type || 'choice'));
  } else {
    addQuestion(null, 'choice');
  }

  updateQuestionCount();
  updateTimeDisplay();
  return true;
}


function updateQuestionCount(){
  const elem = document.getElementById('questionCount');
  if (elem) elem.textContent = document.querySelectorAll('.question-card').length;
}

function updateTimeDisplay(){
  const mins = document.getElementById('timeLimit').value;
  const elem = document.getElementById('timeLimitDisplay');
  if (elem) elem.textContent = mins + ' min';
}

function addQuestion(prefill, type = 'choice'){
  const currentIndex = qIndex;
  const resolvedType = prefill && prefill.type ? prefill.type : type;
  const isChoice = resolvedType === 'choice';
  const wrap = document.createElement('div');
  wrap.className = 'question-card';
  wrap.dataset.qtype = resolvedType;
  wrap.style.cssText = 'padding: 2rem; background: #f4f4f4; border-top: 4px solid teal; border-radius: 0; margin-bottom: 1rem;';
  wrap.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
      <div style="flex: 1;">
        <div style="color: #333; font-size: 0.95rem; margin-bottom: 0.5rem; font-weight: 500;">${currentIndex + 1}. Question</div>
        <textarea class="form-control question-text" name="questions[${currentIndex}]" rows="1" placeholder="Type your question here..." required style="border: none; background: #e8e8e8; border-radius: 2px; padding: 0.75rem; font-size: 0.95rem; resize: none; font-family: 'Noto Sans Sinhala', 'Segoe UI', sans-serif; width: 100%;"></textarea>
      </div>
      <div style="display: flex; gap: 0.5rem; align-items: center; margin-left: 1rem;">
        <button type="button" style="background: none; border: none; cursor: pointer; color: #666; padding: 0.5rem;" title="Add image" onclick="showImageUpload(this, ${currentIndex})">
          <i class="bi bi-card-image" style="font-size: 1.1rem;"></i>
        </button>
        <button type="button" style="background: none; border: none; cursor: pointer; color: #666; padding: 0.5rem;" title="Copy" onclick="copyQuestion(this)">
          <i class="bi bi-files" style="font-size: 1.1rem;"></i>
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
        <img src="../assets/fx-sign.png" alt="Math Symbols" style="width: 16px; height: 16px;" title="Dashboard interface icons created by Freepik - Flaticon">
      </button>
      <div id="mathPalette_${currentIndex}" style="display: none; background: white; border: 1px solid #e0e0e0; padding: 0.75rem; margin-top: 0.5rem; border-radius: 4px; flex-wrap: wrap; gap: 0.25rem;"></div>
    </div>
    
    <div style="display: none; margin-bottom: 1rem;" id="imageUpload_${currentIndex}">
      <input type="file" class="form-control" name="question_images[${currentIndex}]" accept="image/png,image/jpeg,image/gif,image/webp,application/pdf" style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.5rem; font-size: 0.85rem; background: white;">
    </div>

    <div id="opts_section_${currentIndex}" style="margin-bottom: 1rem; ${isChoice ? '' : 'display:none;'}">
      <div id="opts_${currentIndex}" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;"></div>
      <div style="display: flex; gap: 1rem; margin-top: 0.75rem;">
        <button type="button" style="background: none; color: #0078d4; border: none; padding: 0; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;" onclick="addOption(${currentIndex})">
          <i class="bi bi-plus-circle"></i> Add option
        </button>
        <button type="button" style="background: none; color: #0078d4; border: none; padding: 0; font-size: 0.9rem; cursor: pointer;" onclick="addOtherOption(${currentIndex})">
          Add "Other" option
        </button>
      </div>
    </div>

    <div style="border-top: 1px solid #ccc; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; gap: 1rem; align-items: center;">
        <input type="number" class="form-control" name="marks[${currentIndex}]" min="1" value="1" required style="width: 70px; border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.5rem; font-size: 0.9rem;">
        <span style="color: #666; font-size: 0.9rem;">marks</span>
      </div>
      <div style="display: flex; gap: 2rem; align-items: center;">
        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; margin: 0;">
          <span style="color: #666; font-size: 0.9rem;">Long answer</span>
          <div style="position: relative; width: 48px; height: 24px; background: #ccc; border-radius: 12px; transition: background 0.3s;">
            <input type="checkbox" class="long-answer-toggle" style="opacity: 0; width: 0; height: 0; position: absolute;">
            <div class="long-toggle-slider" style="position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: transform 0.3s;"></div>
          </div>
        </label>
        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; margin: 0;">
          <span style="color: #666; font-size: 0.9rem;">Required</span>
          <div style="position: relative; width: 48px; height: 24px; background: #0078d4; border-radius: 12px; transition: background 0.3s;">
            <input type="checkbox" class="required-toggle" checked style="opacity: 0; width: 0; height: 0; position: absolute;">
            <div class="toggle-slider" style="position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: transform 0.3s; transform: translateX(24px);"></div>
          </div>
        </label>
      </div>
    </div>
  `;
  document.getElementById('questions').appendChild(wrap);
  
  const textarea = wrap.querySelector('.question-text');
  let optsWrap = wrap.querySelector('#opts_' + currentIndex);
  const marksInput = wrap.querySelector(`input[name="marks[${currentIndex}]"]`);
  const requiredToggle = wrap.querySelector('.required-toggle');
  const toggleSlider = wrap.querySelector('.toggle-slider');
  const longAnswerToggle = wrap.querySelector('.long-answer-toggle');
  const longToggleSlider = wrap.querySelector('.long-toggle-slider');
  const mathPalette = wrap.querySelector('#mathPalette_' + currentIndex);
  const optsSection = wrap.querySelector('#opts_section_' + currentIndex);

  // Fallback: if options container is missing for any reason, recreate it
  if (!optsWrap) {
    const optsSection = document.createElement('div');
    optsSection.id = 'opts_section_' + currentIndex;
    optsSection.style.cssText = 'margin-bottom: 1rem;';
    optsWrap = document.createElement('div');
    optsWrap.id = 'opts_' + currentIndex;
    optsWrap.style.cssText = 'display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;';
    const actionsRow = document.createElement('div');
    actionsRow.style.cssText = 'display: flex; gap: 1rem; margin-top: 0.75rem;';
    actionsRow.innerHTML = `
      <button type="button" style="background: none; color: #0078d4; border: none; padding: 0; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;" onclick="addOption(${currentIndex})">
        <i class="bi bi-plus-circle"></i> Add option
      </button>
      <button type="button" style="background: none; color: #0078d4; border: none; padding: 0; font-size: 0.9rem; cursor: pointer;" onclick="addOtherOption(${currentIndex})">
        Add "Other" option
      </button>`;
    optsSection.appendChild(optsWrap);
    optsSection.appendChild(actionsRow);
    wrap.insertBefore(optsSection, wrap.querySelector('div[style*="border-top: 1px solid"]'));
  }
  
  const mathSymbols = [
    {char: '+', val: '+'}, {char: '−', val: '−'}, {char: '×', val: '×'}, {char: '÷', val: '÷'},
    {char: '=', val: '='}, {char: '≠', val: '≠'}, {char: '±', val: '±'}, {char: '>', val: '>'},
    {char: '<', val: '<'}, {char: '≥', val: '≥'}, {char: '≤', val: '≤'}, {char: '≈', val: '≈'},
    {char: 'x²', val: '²'}, {char: 'x³', val: '³'}, {char: 'xⁿ', val: 'ⁿ'}, {char: '√', val: '√'},
    {char: 'x₁', val: '₁'}, {char: 'x₂', val: '₂'}, {char: 'xₙ', val: 'ₙ'},
    {char: 'α', val: 'α'}, {char: 'β', val: 'β'}, {char: 'γ', val: 'γ'}, {char: 'θ', val: 'θ'},
    {char: 'π', val: 'π'}, {char: 'Σ', val: 'Σ'}, {char: 'Δ', val: 'Δ'}, {char: 'λ', val: 'λ'},
    {char: '∫', val: '∫'}, {char: '∑', val: '∑'}, {char: '∏', val: '∏'}, {char: '∞', val: '∞'},
    {char: '∂', val: '∂'}, {char: '°', val: '°'}, {char: '∈', val: '∈'}, {char: '∉', val: '∉'},
    {char: '⊂', val: '⊂'}, {char: '∪', val: '∪'}, {char: '∩', val: '∩'}, {char: '∅', val: '∅'},
    {char: '→', val: '→'}, {char: '←', val: '←'}, {char: '⇒', val: '⇒'}, {char: '⇔', val: '⇔'}
  ];
  
  mathSymbols.forEach(symbol => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = symbol.char;
    btn.title = 'Insert ' + symbol.char;
    btn.style.cssText = 'padding: 0.4rem 0.6rem; background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 3px; cursor: pointer; font-size: 0.95rem; min-width: 36px; transition: all 0.15s;';
    btn.onmouseover = () => btn.style.background = '#e8e8e8';
    btn.onmouseout = () => btn.style.background = '#f8f8f8';
    btn.onclick = () => {
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;
      const before = text.substring(0, start);
      const after = text.substring(end);
      textarea.value = before + symbol.val + after;
      textarea.selectionStart = textarea.selectionEnd = start + symbol.val.length;
      textarea.focus();
      textarea.dispatchEvent(new Event('input'));
    };
    mathPalette.appendChild(btn);
  });
  
  // Toggle switches functionality
  requiredToggle.addEventListener('change', function() {
    const parent = this.parentElement;
    if (this.checked) {
      parent.style.background = '#0078d4';
      toggleSlider.style.transform = 'translateX(24px)';
    } else {
      parent.style.background = '#ccc';
      toggleSlider.style.transform = 'translateX(0)';
    }
    debounceSaveDraft();
  });
  
  longAnswerToggle.addEventListener('change', function() {
    const parent = this.parentElement;
    if (this.checked) {
      parent.style.background = '#0078d4';
      longToggleSlider.style.transform = 'translateX(24px)';
      textarea.rows = 4;
    } else {
      parent.style.background = '#ccc';
      longToggleSlider.style.transform = 'translateX(0)';
      textarea.rows = 1;
    }
    debounceSaveDraft();
  });

  if (isChoice) {
    if (prefill && Array.isArray(prefill.options) && prefill.options.length) {
      optsWrap.innerHTML = '';
      prefill.options.forEach((optText, idx) => addOption(currentIndex, optText, prefill.correct === idx));
    } else {
      addOption(currentIndex, 'Option 1');
      addOption(currentIndex, 'Option 2');
    }
  } else {
    // Text question: hide options and default to long answer
    if (optsSection) optsSection.style.display = 'none';
    longAnswerToggle.checked = true;
    longAnswerToggle.dispatchEvent(new Event('change'));
  }

  if (prefill) {
    textarea.value = prefill.text || '';
    marksInput.value = prefill.marks || '1';
  }

  textarea.addEventListener('input', debounceSaveDraft);
  marksInput.addEventListener('input', debounceSaveDraft);

  qIndex++;
  updateQuestionCount();
  debounceSaveDraft();
}

function showImageUpload(btn, index) {
  const uploadDiv = document.getElementById('imageUpload_' + index);
  uploadDiv.style.display = uploadDiv.style.display === 'none' ? 'block' : 'none';
}


function toggleMathPalette(index) {
  const palette = document.getElementById('mathPalette_' + index);
  palette.style.display = palette.style.display === 'none' ? 'flex' : 'none';
}

function addOtherOption(index) {
  addOption(index, 'Other...');
}

function copyQuestion(btn) {
  const card = btn.closest('.question-card');
  const questionText = card.querySelector('.question-text').value;
  const marks = card.querySelector('input[type="number"]').value;
  const type = card.dataset.qtype || 'choice';
  
  // Get options
  const options = [];
  let correctIdx = null;
  const optionInputs = card.querySelectorAll('div[id^="opts_"] input[type="text"]');
  optionInputs.forEach((input, idx) => { options.push(input.value); });
  const radios = card.querySelectorAll('input[type="radio"]');
  radios.forEach((radio, idx) => { if (radio.checked) correctIdx = idx; });
  
  addQuestion({ text: questionText, marks: marks, options: options, correct: correctIdx, type });
}

function moveQuestion(btn, direction) {
  const card = btn.closest('.question-card');
  if (direction === 'up') {
    const next = card.nextElementSibling;
    if (next) card.parentNode.insertBefore(next, card);
  } else {
    const prev = card.previousElementSibling;
    if (prev) card.parentNode.insertBefore(card, prev);
  }
  debounceSaveDraft();
}
function removeQuestion(btn){
  btn.closest('.question-card').remove();
  updateQuestionCount();
  debounceSaveDraft();
}
function addOption(i, value = '', isCorrect = false){
  let oWrap = document.getElementById('opts_' + i);

  // Fallback: if options wrapper is missing (e.g., from an old draft), recreate it
  if (!oWrap) {
    const optsSection = document.getElementById('opts_section_' + i) || document.createElement('div');
    if (!optsSection.id) {
      optsSection.id = 'opts_section_' + i;
      optsSection.style.cssText = 'margin-bottom: 1rem;';
    }
    oWrap = document.createElement('div');
    oWrap.id = 'opts_' + i;
    oWrap.style.cssText = 'display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;';
    const actionsRow = document.createElement('div');
    actionsRow.style.cssText = 'display: flex; gap: 1rem; margin-top: 0.75rem;';
    actionsRow.innerHTML = `
      <button type="button" style="background: none; color: #0078d4; border: none; padding: 0; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem;" onclick="addOption(${i})">
        <i class="bi bi-plus-circle"></i> Add option
      </button>
      <button type="button" style="background: none; color: #0078d4; border: none; padding: 0; font-size: 0.9rem; cursor: pointer;" onclick="addOtherOption(${i})">
        Add "Other" option
      </button>`;
    optsSection.appendChild(oWrap);
    optsSection.appendChild(actionsRow);
    const card = document.querySelectorAll('.question-card')[i];
    if (card) {
      const footer = card.querySelector('div[style*="border-top: 1px solid"]');
      card.insertBefore(optsSection, footer);
    }
  }
  const count = oWrap.querySelectorAll('input[type="text"]').length;
  const inputGroup = document.createElement('div');
  inputGroup.style.cssText = 'display: flex; gap: 0.75rem; align-items: center; padding: 0.5rem; background: white; border-radius: 2px;';
  inputGroup.innerHTML = `
    <input type="radio" name="correct[${i}]" value="${count}" id="correct_${i}_${count}" style="width: 18px; height: 18px; cursor: pointer; margin: 0; flex-shrink: 0;">
    <input type="text" class="form-control" name="options[${i}][${count}]" placeholder="Option ${count + 1}" required style="border: none; padding: 0.25rem; font-size: 0.95rem; flex: 1; background: transparent; outline: none;">
    <button type="button" style="background: none; border: none; cursor: pointer; color: #999; padding: 0.25rem; flex-shrink: 0;" title="Add image" onclick="showOptionImage(this, ${i}, ${count})">
      <i class="bi bi-card-image" style="font-size: 1rem;"></i>
    </button>
    <button type="button" style="background: none; border: none; cursor: pointer; color: #999; padding: 0.25rem; flex-shrink: 0;" title="Remove option">
      <i class="bi bi-x-lg" style="font-size: 0.9rem;"></i>
    </button>
  `;
  oWrap.appendChild(inputGroup);

  const radio = inputGroup.querySelector('input[type="radio"]');
  const textInput = inputGroup.querySelector('input[type="text"]');
  const removeBtn = inputGroup.querySelectorAll('button')[1];

  if (value) textInput.value = value;
  if (isCorrect) radio.checked = true;

  textInput.addEventListener('input', debounceSaveDraft);
  radio.addEventListener('change', debounceSaveDraft);
  removeBtn.addEventListener('click', () => {
    inputGroup.remove();
    debounceSaveDraft();
  });
  debounceSaveDraft();
}

function showOptionImage(btn, questionIndex, optionIndex) {
  alert('Image upload for individual options coming soon!');
}
document.addEventListener('DOMContentLoaded', () => {
  const restored = loadDraft();
  if (!restored) {
    addQuestion();
    updateTimeDisplay();
  }

  const titleInput = document.querySelector('input[name="title"]');
  const feeInput = document.querySelector('input[name="fee_cents"]');
  const timeInput = document.querySelector('input[name="time_limit_minutes"]');
  const publishInput = document.getElementById('publishNow');

  if (titleInput) titleInput.addEventListener('input', debounceSaveDraft);
  if (feeInput) feeInput.addEventListener('input', debounceSaveDraft);
  if (timeInput) timeInput.addEventListener('input', () => { updateTimeDisplay(); debounceSaveDraft(); });
  if (publishInput) publishInput.addEventListener('change', debounceSaveDraft);

  // Keep draft after tab close to allow crash recovery

  // Update time limit display on change
  const timeField = document.getElementById('timeLimit');
  if (timeField) timeField.addEventListener('change', updateTimeDisplay);
  
  // Add event delegation for file inputs
  document.addEventListener('change', function(e) {
    if(e.target.name && e.target.name.startsWith('question_images')) {
      const file = e.target.files[0];
      if(file){
        const reader = new FileReader();
        const match = e.target.name.match(/\[(\d+)\]/);
        const index = match ? match[1] : null;
        if(index){
          reader.onload = function(event) {
            const preview = document.getElementById('img_preview_' + index);
            const img = document.getElementById('img_' + index);
            if(preview && img){
              img.src = event.target.result;
              preview.style.display = 'block';
            }
          };
          reader.readAsDataURL(file);
        }
      }
    }
  });

  // Validate correct answers before submit
  document.getElementById('paperForm').addEventListener('submit', (e) => {
    const questions = document.querySelectorAll('.question-card');
    let hasError = false;
    
    questions.forEach((card, idx) => {
      const radioName = `correct[${idx}]`;
      const radios = document.querySelectorAll(`input[name="${radioName}"]`);
      const isChecked = Array.from(radios).some(r => r.checked);
      
      if (!isChecked && radios.length > 0) {
        hasError = true;
        const alertBox = card.querySelector('div[style*="background: #fffbf0"]');
        if (alertBox) {
          alertBox.style.background = '#fee';
          alertBox.style.borderColor = '#dc3545';
          alertBox.innerHTML = '<p style="margin: 0; color: #dc3545; font-size: 0.9rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>ERROR:</strong> You must select which option is correct!</p>';
        }
      }
    });
    
    if (hasError) {
      e.preventDefault();
      alert('Please select the correct answer for all questions before submitting!');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      clearDraft();
    }
  });
  
  // Trigger KaTeX rendering after page loads
  if (window.renderMathInElement) {
    try {
      window.renderMathInElement(document.body, {
        delimiters: [
          {left: '$$', right: '$$', display: true},
          {left: '$', right: '$', display: false},
          {left: '\\[', right: '\\]', display: true},
          {left: '\\(', right: '\\)', display: false}
        ]
      });
    } catch(err) {
      console.log('Initial KaTeX render:', err.message);
    }
  }
});
</script>
<?php render_footer(); ?>