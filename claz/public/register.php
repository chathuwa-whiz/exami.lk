<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/layout.php';
$error = '';
$success = '';
// Temporary debug toggle: set to true to surface server-side errors in UI
$debug_mode = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify()) {
    $error = 'Security token mismatch.';
  } else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $type = $_POST['user_type'] ?? 'student';
    $studentId = $type === 'student' ? null : null;
    $subjectIds = $_POST['subject_ids'] ?? [];
    $studentTeacherIds = $type === 'student' ? ($_POST['teacher_ids'] ?? []) : [];
    if ($name && $email && $password && in_array($type, ['student','teacher'], true)) {
      $pdo = db();
      // Student registrations do not require subjects/teachers at signup anymore.
      // Students can manage subjects and teachers from their profile after registering.
      // Teacher registrations require at least one subject
      if ($type === 'teacher' && empty($subjectIds)) {
        $error = 'Please select at least one subject you teach.';
      }
      if (!$error) {
        try {
          // Generate unique teacher code for teachers
          $teacherCode = null;
          if ($type === 'teacher') {
            do {
              $teacherCode = 'T' . strtoupper(bin2hex(random_bytes(3)));
              $codeCheck = $pdo->prepare('SELECT 1 FROM users WHERE teacher_code=?');
              $codeCheck->execute([$teacherCode]);
            } while ($codeCheck->fetch());
          }
          
          $stmt = $pdo->prepare('INSERT INTO users (user_type, student_id, teacher_code, name, email, password_hash) VALUES (?,?,?,?,?,?)');
          $stmt->execute([$type, $studentId, $teacherCode, $name, $email, hash_password($password)]);
          $uid = $pdo->lastInsertId();
          if ($type === 'teacher') {
            // Link teacher to selected subjects
            $subjStmt = $pdo->prepare('INSERT INTO teacher_subject (teacher_id,subject_id) VALUES (?,?) ON DUPLICATE KEY UPDATE subject_id=subject_id');
            foreach ($subjectIds as $sid) {
              $sid = (int)$sid;
              if ($sid > 0) { $subjStmt->execute([$uid, $sid]); }
            }
          }
          if ($type === 'teacher') {
            $success = 'Registered successfully! Your teacher code is: <strong>' . htmlspecialchars($teacherCode) . '</strong>. Please save it. You can log in now.';
          } else {
            $success = 'Registered. You can log in now.';
          }
        } catch (Exception $e) {
          $error = 'Registration failed: ' . $e->getMessage();
          try {
            $logLine = date('c') . " | " . ($user['email'] ?? $email ?? 'n/a') . " | " . $error . "\n";
            @file_put_contents(__DIR__ . '/../logs/register.log', $logLine, FILE_APPEND);
          } catch (Throwable $ignore) {}
        }
      }
    } else {
      $error = 'All required fields must be filled.';
    }
  }
}
?>
<?php render_auth_shell_start('Create your account', 'Join your class, practice past papers and track your scores.'); ?>
<div class="mb-4">
  <h1 class="h3 fw-semibold text-dark mb-1 d-flex align-items-center gap-2"><i class="bi bi-person-plus-fill text-primary"></i> Create an account</h1>
  <p class="text-muted mb-0">Sign up as a student or teacher to get started.</p>
</div>
<div class="d-flex flex-wrap gap-2 mb-4">
  <span class="badge-chip badge-chip-student"><i class="bi bi-emoji-smile"></i> Students: select your subjects and teachers</span>
  <span class="badge-chip badge-chip-teacher"><i class="bi bi-person-video3"></i> Teachers: supply your code</span>
  <span class="badge-chip badge-chip-security"><i class="bi bi-shield-check"></i> All accounts: secure password</span>
</div>
  <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert" aria-live="assertive">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span class="ms-2"><?= htmlspecialchars($error) ?></span>
    </div>
    <?php if (!empty($debug_mode)): ?>
      <div class="alert alert-warning" role="alert"><strong>Debug info:</strong> Please ensure MySQL is running and tables exist. Check `subjects` list via <code>api/subjects.php</code>.</div>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center" role="status" aria-live="polite">
      <i class="bi bi-check-circle-fill"></i>
      <span class="ms-2"><?= htmlspecialchars($success) ?></span>
    </div>
  <?php endif; ?>
  <form method="post" class="needs-validation" novalidate>
    <?= csrf_field(); ?>
    <div class="row g-3">
      <div class="col-md-6">
        <label for="name" class="form-label text-dark">Name</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input name="name" id="name" class="form-control" required>
          <div class="invalid-feedback">Name required.</div>
        </div>
      </div>
      <div class="col-md-6">
        <label for="reg_email" class="form-label text-dark">Email</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" id="reg_email" class="form-control" required>
          <div class="invalid-feedback">Valid email required.</div>
        </div>
      </div>
      <div class="col-md-6">
        <label for="password" class="form-label text-dark">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" required>
          <div class="invalid-feedback">Password required.</div>
        </div>
      </div>
      <div class="col-md-6">
        <label for="user_type" class="form-label text-dark">User Type</label>
        <select name="user_type" id="user_type" class="form-select" onchange="toggleFields()">
          <option value="student">Student</option>
          <option value="teacher">Teacher</option>
        </select>
      </div>
    </div>
    <!-- Student fields removed: students can manage their subjects/teachers from their profile -->

    <div id="teacher_fields" class="mt-4" style="display:none;">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-light d-flex align-items-center gap-2">
          <i class="bi bi-journal-bookmark text-primary"></i>
          <span class="fw-semibold">Subjects You Teach</span>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">Your teacher code will be auto-generated after registration.</p>
          
          <label class="form-label">Select Subjects</label>
          <div id="teacher_subject_list" class="d-flex flex-column gap-2 mb-3"></div>
          <div class="form-text">Select one or more subjects you teach. This helps students find you.</div>
        </div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap mt-4">
      <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2"><i class="bi bi-person-check"></i> Register</button>
      <a href="<?= htmlspecialchars(app_href('login.php')) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>
  </form>
<p class="consent-note small mt-3 mb-0"><i class="bi bi-stars"></i> By creating an account you agree to our colorful classroom rules.</p>
<script>
function setSectionDisabled(sectionId, disabled){
  const section = document.getElementById(sectionId);
  if(!section) return;
  const controls = section.querySelectorAll('input, select, button');
  controls.forEach(el=>{
    if(disabled){
      el.dataset.wasRequired = el.required ? '1' : '';
      el.required = false;
      el.disabled = true;
    } else {
      if(el.dataset.wasRequired === '1'){ el.required = true; }
      el.disabled = false;
    }
  });
}

async function toggleFields(){
  const type=document.getElementById('user_type').value;
  const studentSection = document.getElementById('student_fields');
  const teacherSection = document.getElementById('teacher_fields');

  if (studentSection) studentSection.style.display = type === 'student' ? 'block' : 'none';
  if (teacherSection) teacherSection.style.display = type === 'teacher' ? 'block' : 'none';

  // Disable hidden section controls to avoid "not focusable" validity errors
  setSectionDisabled('student_fields', type !== 'student');
  setSectionDisabled('teacher_fields', type !== 'teacher');

  // Only initialize student UI if the container exists (we removed it from registration)
  const subjectTeacherContainer = document.getElementById('subject_teacher_list');
  if (type === 'student' && subjectTeacherContainer && subjectTeacherContainer.children.length === 0) {
    await initStudentUI();
  }
  if(type==='teacher' && document.getElementById('teacher_subject_list').children.length===0){
    await initTeacherUI();
  }
}

// Fetch and populate teacher subjects
async function initTeacherUI(){
  const subjects = await fetchSubjects();
  const list = document.getElementById('teacher_subject_list');
  if(subjects.length===0){
    list.innerHTML = '<div class="alert alert-warning small mb-0"><i class="bi bi-info-circle-fill"></i> No subjects available. Please contact support.</div>';
    return;
  }
  subjects.forEach(s=>{
    const div = document.createElement('div');
    div.className = 'form-check';
    div.innerHTML = `<input class="form-check-input" type="checkbox" name="subject_ids[]" value="${s.id}" id="subject_${s.id}"><label class="form-check-label" for="subject_${s.id}">${s.name}</label>`;
    list.appendChild(div);
  });
}

// Student subject/teacher pairing UI
  // student subject/teacher selection removed from registration

let subjectsAvailable = true;
async function fetchSubjects(){
  try{
    const res = await fetch('<?= htmlspecialchars(app_href('api/subjects.php')) ?>');
    const data = await res.json();
    const list = Array.isArray(data.subjects) ? data.subjects : [];
    subjectsAvailable = list.length > 0;
    return list;
  }catch{
    subjectsAvailable = false;
    return [];
  }
}
async function fetchTeachers(subjectId){
  try{
    const res = await fetch('<?= htmlspecialchars(app_href('api/teachers_by_subject.php')) ?>?subject_id='+encodeURIComponent(subjectId));
    const data = await res.json();
    return Array.isArray(data.teachers) ? data.teachers : [];
  }catch{ return []; }
}

function buildRow(subjects){
  const row = document.createElement('div');
  row.className = 'row g-3 align-items-end';
  row.innerHTML = `
    <div class="col-md-6">
      <label class="form-label">Subject</label>
      <select name="subject_ids[]" class="form-select subject-select" required>
        <option value="">Select subject...</option>
        ${subjects.map(s=>`<option value="${s.id}">${s.name}</option>`).join('')}
      </select>
      <div class="invalid-feedback">Subject required.</div>
    </div>
    <div class="col-md-5">
      <label class="form-label">Teacher</label>
      <select name="teacher_ids[]" class="form-select teacher-select" required>
        <option value="">Select teacher...</option>
      </select>
      <div class="invalid-feedback">Teacher required.</div>
    </div>
    <div class="col-md-1">
      <button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button>
    </div>
  `;
  subjectTeacherList.appendChild(row);
}

  // student selection event handlers removed

// Bootstrap validation - check for required fields based on user type
(function(){
  const form = document.querySelector('form.needs-validation');
  if(!form) return;
  const submitBtn = form.querySelector('button[type="submit"]');
  form.addEventListener('submit', function(e){
    // Prevent double submits
    if(submitBtn && submitBtn.dataset.submitting){
      e.preventDefault();
      return;
    }

    const userType = document.getElementById('user_type').value;
    let isValid = form.checkValidity();
    
    // Additional checks for type-specific requirements
    // Students are no longer required to select subjects/teachers during registration.
    if(isValid && userType === 'teacher'){
      // Only enforce teacher subject selection if subjects are actually available
      if(subjectsAvailable){
        const subjectCheckboxes = document.querySelectorAll('#teacher_subject_list input[type="checkbox"]:checked');
        if(subjectCheckboxes.length === 0){
          isValid = false;
        }
      }
    }
    
    if(!isValid){
      e.preventDefault();
      e.stopPropagation();
      form.reportValidity();
      // Provide a clear message when custom validation blocks submission
      const msg = (userType==='student')
        ? 'Subjects can be added later from your profile. Registration can proceed without linking subjects.'
        : (subjectsAvailable ? 'Please select at least one subject you teach.' : 'Subjects are currently unavailable; registration can proceed without linking subjects.');
      const alert = document.createElement('div');
      alert.className = 'alert alert-warning mt-2';
      alert.innerHTML = `<i class="bi bi-info-circle-fill"></i> <span class="ms-2">${msg}</span>`;
      // Avoid duplicating alerts
      const existing = form.querySelector('.alert.alert-warning.mt-2');
      if(!existing){ form.appendChild(alert); }
    } else {
      // Mark submitting state to avoid accidental double clicks
      if(submitBtn){
        submitBtn.dataset.submitting = '1';
        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy','true');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Registering...';
      }
    }
    form.classList.add('was-validated');
  }, false);
})();

// Initialize UI on page load
document.addEventListener('DOMContentLoaded', async () => {
  // Trigger initial toggle to show student fields and populate first row
  await toggleFields();
});
</script>
<?php if (!empty($debug_mode)): ?>
<script>
// Additional runtime checks to help diagnose issues
(async function(){
  try {
    const res = await fetch('<?= htmlspecialchars(app_href('api/subjects.php')) ?>');
    const ok = res.ok;
    const ct = res.headers.get('content-type')||'';
    if(!ok){ console.warn('Subjects API not OK:', res.status); }
    if(!ct.includes('application/json')){ console.warn('Subjects API not JSON:', ct); }
  } catch(e){ console.warn('Subjects API fetch failed:', e); }
})();
</script>
<?php endif; ?>
<?php render_auth_shell_end(); ?>