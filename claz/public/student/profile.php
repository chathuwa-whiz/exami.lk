<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_once __DIR__ . '/../../src/csrf.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'student') { http_response_code(403); echo 'Forbidden'; exit; }

$pdo = db();
$errors = [];
$success = '';

// Handle teacher links update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teachers'])) {
  if (!csrf_verify()) {
    $errors[] = 'Invalid CSRF token';
  } else {
    $teacherIds = $_POST['teacher_ids'] ?? [];
    // Normalize integer ids
    $tids = [];
    foreach ($teacherIds as $tid) { $tid = (int)$tid; if ($tid>0) $tids[] = $tid; }

    try {
      // Delete existing mappings for this student
      $del = $pdo->prepare('DELETE FROM teacher_student WHERE student_id = ?');
      $del->execute([$user['id']]);
      // Insert new mappings
      $ins = $pdo->prepare('INSERT INTO teacher_student (teacher_id, student_id) VALUES (?,?)');
      foreach ($tids as $tid) { $ins->execute([$tid, $user['id']]); }
      $success = 'Teacher links updated.';
    } catch (Throwable $e) {
      $errors[] = 'Failed to update teachers: ' . $e->getMessage();
    }
  }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!csrf_verify()) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $second_name = trim($_POST['second_name'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $sexuality = trim($_POST['sexuality'] ?? '');
        $nic_no = trim($_POST['nic_no'] ?? '');
        $postal_id_card_no = trim($_POST['postal_id_card_no'] ?? '');
        $school_name = trim($_POST['school_name'] ?? '');
        $grade = trim($_POST['grade'] ?? '');
        $school_category = trim($_POST['school_category'] ?? '');

        $stmt = $pdo->prepare('UPDATE users SET 
            first_name = ?, second_name = ?, birth_date = ?, sexuality = ?, 
            nic_no = ?, postal_id_card_no = ?, school_name = ?, grade = ?, school_category = ?
            WHERE id = ?');
        $stmt->execute([
            $first_name ?: null, 
            $second_name ?: null, 
            $birth_date ?: null, 
            $sexuality ?: null,
            $nic_no ?: null, 
            $postal_id_card_no ?: null, 
            $school_name ?: null, 
            $grade ?: null, 
            $school_category ?: null,
            $user['id']
        ]);
        $success = 'Profile updated successfully!';
    }
}

// Fetch current profile data
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Fetch linked teachers for this student
$ltStmt = $pdo->prepare('SELECT ts.teacher_id, u.name FROM teacher_student ts JOIN users u ON u.id = ts.teacher_id WHERE ts.student_id = ?');
$ltStmt->execute([$user['id']]);
$linkedTeachers = $ltStmt->fetchAll();

render_header('My Profile');
?>

<!-- Hero Header Section -->
<section class="mb-5" style="background: white; border-radius: 12px; padding: 2rem; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-left: 4px solid #3b82f6;">
  <div class="position-relative">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div style="position: relative; width: 80px; height: 80px; border-radius: 50%; background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 2rem; overflow: hidden; cursor: pointer;" id="profileImageContainer" title="Click to upload profile image">
        <?php if (!empty($profile['profile_image'])): ?>
          <img id="profileImage" src="<?= app_href($profile['profile_image'] . '?t=' . time()) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Profile Image">
        <?php else: ?>
          <i class="bi bi-person-badge text-white"></i>
        <?php endif; ?>
        <input type="file" id="imageInput" accept="image/*" style="display: none;">
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s;" class="image-overlay">
          <i class="bi bi-cloud-upload text-white" style="font-size: 1.5rem;"></i>
        </div>
      </div>
      <div>
        <h1 class="mb-1" style="font-size: 2rem; font-weight: 700; color: #1f2937;">
          <i class="bi bi-person-badge me-2" style="color: #3b82f6;"></i><?= htmlspecialchars($user['name']) ?>
        </h1>
        <p class="mb-0" style="color: #6b7280;">Student Profile</p>
      </div>
    </div>
  </div>
</section>

<!-- Profile Completion -->
<?php
$completionFields = [
  'first_name' => $profile['first_name'] ?? '',
  'second_name' => $profile['second_name'] ?? '',
  'birth_date' => $profile['birth_date'] ?? '',
  'sexuality' => $profile['sexuality'] ?? '',
  'nic_no' => $profile['nic_no'] ?? '',
  'postal_id_card_no' => $profile['postal_id_card_no'] ?? '',
  'school_name' => $profile['school_name'] ?? '',
  'grade' => $profile['grade'] ?? '',
  'school_category' => $profile['school_category'] ?? '',
  'profile_image' => $profile['profile_image'] ?? ''
];
$filledFields = count(array_filter($completionFields, fn($v) => !empty($v)));
$totalFields = count($completionFields);
$completionPercentage = round(($filledFields / $totalFields) * 100);
?>
<section class="mb-4">
  <div class="card shadow-sm" style="border-radius: 12px; border-left: 4px solid <?= $completionPercentage >= 80 ? '#10b981' : '#f59e0b' ?>; background: white;">
    <div class="card-body p-4">
      <div class="d-flex align-items-center gap-3">
        <div style="width: 60px; height: 60px; min-width: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: <?= $completionPercentage >= 80 ? '#d1fae5' : '#fef3c7' ?>; color: <?= $completionPercentage >= 80 ? '#10b981' : '#f59e0b' ?>;">
          <i class="bi bi-<?= $completionPercentage >= 80 ? 'check-circle-fill' : 'info-circle-fill' ?>"></i>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0 fw-bold" style="color: #1f2937;"><?= htmlspecialchars($user['name']) ?></h5>
            <span class="badge" style="background: <?= $completionPercentage >= 80 ? '#d1fae5' : '#fef3c7' ?>; color: <?= $completionPercentage >= 80 ? '#10b981' : '#f59e0b' ?>; font-size: 0.9rem; padding: 0.4rem 0.8rem; font-weight: 700;"><?= $completionPercentage ?>%</span>
          </div>
          <p class="text-muted mb-2 small">Student Profile</p>
          <?php if ($completionPercentage < 100): ?>
            <a href="#profileForm" class="text-decoration-none fw-semibold" style="color: #3b82f6; font-size: 0.9rem;">
              <i class="bi bi-pencil-square me-1"></i>Complete your profile
            </a>
          <?php else: ?>
            <p style="color: #10b981; margin-bottom: 0; font-size: 0.85rem; font-weight: 600;"><i class="bi bi-check-circle-fill me-1"></i>Profile completed</p>
          <?php endif; ?>
          <div class="progress mt-3" style="height: 6px; border-radius: 10px; background: #e5e7eb;">
            <div class="progress-bar" role="progressbar" style="width: <?= $completionPercentage ?>%; background: <?= $completionPercentage >= 80 ? '#10b981' : '#f59e0b' ?>; border-radius: 10px;" aria-valuenow="<?= $completionPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($errors): ?>
  <div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $error): ?>
      <div><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success" role="alert">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<!-- Manage Linked Teachers -->
<section class="mb-5">
  <div class="card shadow-sm" style="border-radius:12px; border-left: 4px solid #3b82f6;">
    <div class="card-header" style="background: white; border-radius: 12px 12px 0 0; border-bottom: 1px solid #e5e7eb;">
      <h5 class="mb-0" style="color: #1f2937; font-weight: 700;"><i class="bi bi-people-fill me-2" style="color: #3b82f6;"></i>Linked Teachers</h5>
    </div>
    <div class="card-body p-4">
      <p class="text-muted small">Manage which teachers are linked to your student account. Add or remove teachers as needed.</p>
      <form method="POST" id="teachersForm">
        <?= csrf_field() ?>
        <div id="linked_teachers_list" class="d-flex flex-column gap-2 mb-3">
          <?php foreach ($linkedTeachers as $lt): ?>
            <div class="d-flex align-items-center gap-2">
              <input type="hidden" name="teacher_ids[]" value="<?= (int)$lt['teacher_id'] ?>">
              <div class="flex-grow-1"><?= htmlspecialchars($lt['name']) ?></div>
              <button type="button" class="btn btn-sm btn-outline-danger remove-linked">Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="add_teacher_row_container"></div>
        <div class="d-flex gap-2">
          <button type="button" id="addTeacherBtn" class="btn btn-outline-primary">Add Teacher</button>
          <button type="submit" name="update_teachers" class="btn btn-primary">Save Teachers</button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- Profile Form -->
<section class="mb-5" id="profileForm">
  <div class="card shadow-sm" style="border-radius: 12px; border-left: 4px solid #3b82f6;">
    <div class="card-header" style="background: white; border-radius: 12px 12px 0 0; border-bottom: 1px solid #e5e7eb;">
      <h5 class="mb-0" style="color: #1f2937; font-weight: 700;"><i class="bi bi-person-lines-fill me-2" style="color: #3b82f6;"></i>Student Profile Details</h5>
    </div>
    <div class="card-body p-4">
      <form method="POST">
        <?= csrf_field() ?>
        
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>">
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Second Name</label>
            <input type="text" name="second_name" class="form-control" value="<?= htmlspecialchars($profile['second_name'] ?? '') ?>">
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Birth Date</label>
            <input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($profile['birth_date'] ?? '') ?>">
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Sexuality</label>
            <select name="sexuality" class="form-select">
              <option value="">Select</option>
              <option value="Male" <?= ($profile['sexuality'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($profile['sexuality'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= ($profile['sexuality'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">NIC No <span class="text-muted">(If available)</span></label>
            <input type="text" name="nic_no" class="form-control" value="<?= htmlspecialchars($profile['nic_no'] ?? '') ?>">
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Postal ID Card No <span class="text-muted">(If available)</span></label>
            <input type="text" name="postal_id_card_no" class="form-control" value="<?= htmlspecialchars($profile['postal_id_card_no'] ?? '') ?>">
          </div>
        </div>

        <hr class="my-4">
        
        <h6 class="fw-bold mb-3"><i class="bi bi-building me-2"></i>School Details</h6>
        
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label fw-semibold">School Name</label>
            <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($profile['school_name'] ?? '') ?>">
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Grade</label>
            <input type="text" name="grade" class="form-control" placeholder="e.g., Grade 10" value="<?= htmlspecialchars($profile['grade'] ?? '') ?>">
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">School Category</label>
            <select name="school_category" class="form-select">
              <option value="">Select</option>
              <option value="Government" <?= ($profile['school_category'] ?? '') == 'Government' ? 'selected' : '' ?>>Government</option>
              <option value="Private" <?= ($profile['school_category'] ?? '') == 'Private' ? 'selected' : '' ?>>Private</option>
            </select>
          </div>
        </div>

        <div class="mt-4">
          <button type="submit" name="update_profile" class="btn btn-primary px-4">
            <i class="bi bi-check-circle me-2"></i>Update Profile
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<script>
// Profile image upload handler
document.getElementById('profileImageContainer').addEventListener('click', function() {
  document.getElementById('imageInput').click();
});

document.getElementById('profileImageContainer').addEventListener('mouseover', function() {
  this.querySelector('.image-overlay').style.opacity = '1';
});

document.getElementById('profileImageContainer').addEventListener('mouseout', function() {
  this.querySelector('.image-overlay').style.opacity = '0';
});

document.getElementById('imageInput').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('profile_image', file);

  // Show uploading state
  const container = document.getElementById('profileImageContainer');
  container.style.opacity = '0.6';
  container.style.pointerEvents = 'none';

  fetch('<?= app_href('api/upload_profile_image.php') ?>', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Reload profile image with cache bust
      const img = document.getElementById('profileImage');
      if (img) {
        img.src = data.image_url + '?t=' + Date.now();
      } else {
        location.reload();
      }
      alert('Profile image updated successfully!');
    } else {
      alert('Error: ' + (data.error || 'Failed to upload image'));
    }
  })
  .catch(error => {
    console.error('Upload error:', error);
    alert('Error uploading image: ' + error.message);
  })
  .finally(() => {
    container.style.opacity = '1';
    container.style.pointerEvents = 'auto';
    document.getElementById('imageInput').value = '';
  });
});
</script>

<script>
// Teacher management JS
async function fetchSubjects(){
  try{ const res = await fetch('<?= htmlspecialchars(app_href('api/subjects.php')) ?>'); const data = await res.json(); return Array.isArray(data.subjects) ? data.subjects : []; }catch{return [];}
}
async function fetchTeachers(subjectId){
  try{ const res = await fetch('<?= htmlspecialchars(app_href('api/teachers_by_subject.php')) ?>?subject_id='+encodeURIComponent(subjectId)); const data = await res.json(); return Array.isArray(data.teachers) ? data.teachers : []; }catch{return [];}
}

document.addEventListener('DOMContentLoaded', async ()=>{
  // Add teacher row handler
  document.getElementById('addTeacherBtn').addEventListener('click', async function(){
    const container = document.getElementById('add_teacher_row_container');
    const subjects = await fetchSubjects();
    if(subjects.length===0){
      alert('No subjects available'); return;
    }
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center mb-2';
    row.innerHTML = `
      <select class="form-select pick-subject" style="width:200px;">
        <option value="">Select subject...</option>
        ${subjects.map(s=>`<option value="${s.id}">${s.name}</option>`).join('')}
      </select>
      <select class="form-select pick-teacher" style="width:300px;"><option value="">Select teacher...</option></select>
      <button type="button" class="btn btn-sm btn-primary add-link">Add</button>
      <button type="button" class="btn btn-sm btn-outline-secondary cancel-row">Cancel</button>
    `;
    container.appendChild(row);

    row.querySelector('.pick-subject').addEventListener('change', async function(){
      const sid = this.value; const tsel = row.querySelector('.pick-teacher'); tsel.innerHTML = '<option>Loading...</option>';
      const teachers = sid ? await fetchTeachers(sid) : [];
      tsel.innerHTML = '<option value="">Select teacher...</option>' + teachers.map(t=>`<option value="${t.id}">${t.name}</option>`).join('');
    });

    row.querySelector('.cancel-row').addEventListener('click', ()=> row.remove());

    row.querySelector('.add-link').addEventListener('click', ()=>{
      const tid = row.querySelector('.pick-teacher').value;
      const tname = row.querySelector('.pick-teacher').selectedOptions[0]?.text || '';
      if(!tid){ alert('Select a teacher'); return; }
      const list = document.getElementById('linked_teachers_list');
      const div = document.createElement('div');
      div.className = 'd-flex align-items-center gap-2';
      div.innerHTML = `<input type="hidden" name="teacher_ids[]" value="${tid}"><div class="flex-grow-1">${tname}</div><button type="button" class="btn btn-sm btn-outline-danger remove-linked">Remove</button>`;
      list.appendChild(div);
      row.remove();
    });
  });

  // Remove linked handlers
  document.getElementById('linked_teachers_list').addEventListener('click', function(e){
    if(e.target.classList.contains('remove-linked')){ e.target.closest('div').remove(); }
  });
});
</script>

<?php render_footer(); ?>
