<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'teacher') { http_response_code(403); echo 'Forbidden'; exit; }

$pdo = db();
$errors = [];
$success = '';

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
        $school_name = trim($_POST['school_name'] ?? '');
        $grade = trim($_POST['grade'] ?? '');
        $school_category = trim($_POST['school_category'] ?? '');
        $main_subject = trim($_POST['main_subject'] ?? '');
        $first_appointment_date = trim($_POST['first_appointment_date'] ?? '');

        $stmt = $pdo->prepare('UPDATE users SET 
            first_name = ?, second_name = ?, birth_date = ?, sexuality = ?, 
            nic_no = ?, school_name = ?, grade = ?, school_category = ?,
            main_subject = ?, first_appointment_date = ?
            WHERE id = ?');
        $stmt->execute([
            $first_name ?: null, 
            $second_name ?: null, 
            $birth_date ?: null, 
            $sexuality ?: null,
            $nic_no ?: null, 
            $school_name ?: null, 
            $grade ?: null, 
            $school_category ?: null,
            $main_subject ?: null,
            $first_appointment_date ?: null,
            $user['id']
        ]);
        $success = 'Profile updated successfully!';
    }
}

$stmt = $pdo->prepare('SELECT id, name, email, teacher_code, created_at, profile_image,
  first_name, second_name, birth_date, sexuality, nic_no, school_name, 
  grade, school_category, main_subject, first_appointment_date FROM users WHERE id=?');
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

// Fetch teacher's subjects
$subjectStmt = $pdo->prepare('SELECT s.id, s.name FROM subjects s 
  INNER JOIN teacher_subject ts ON ts.subject_id = s.id 
  WHERE ts.teacher_id = ? ORDER BY s.name');
$subjectStmt->execute([$user['id']]);
$subjects = $subjectStmt->fetchAll();

// Fetch papers count
$paperStmt = $pdo->prepare('SELECT COUNT(*) AS total, 
  SUM(CASE WHEN is_published=1 THEN 1 ELSE 0 END) AS published FROM papers WHERE teacher_id=?');
$paperStmt->execute([$user['id']]);
$paperStats = $paperStmt->fetch();

// Fetch payment statistics for this teacher's papers
$paymentStmt = $pdo->prepare('
  SELECT 
    COALESCE(SUM(p.amount_cents), 0) AS total_collected_cents,
    COUNT(DISTINCT p.id) AS total_payments
  FROM payments p
  INNER JOIN papers pa ON pa.id = p.paper_id
  WHERE pa.teacher_id = ? AND p.status = "completed"
');
$paymentStmt->execute([$user['id']]);
$paymentStats = $paymentStmt->fetch();

$totalCollected = ($paymentStats['total_collected_cents'] ?? 0) / 100;
$teacherShare = $totalCollected * 0.80;
$totalPayments = (int)($paymentStats['total_payments'] ?? 0);

render_header('My Profile');
?>

<?php if ($errors): ?>
  <div class="alert alert-danger" role="alert">
    <?php foreach ($errors as $error): ?>
      <div><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success" role="status"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Profile Header -->
<div class="app-card mb-4">
  <div class="app-card-body">
    <div class="d-flex align-items-center gap-4 flex-wrap">
      <!-- Avatar -->
      <div id="profileImageContainer" title="Click to upload photo"
           style="position:relative;width:80px;height:80px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;overflow:hidden;cursor:pointer;flex-shrink:0;border:3px solid var(--primary-border);">
        <?php if (!empty($teacher['profile_image'])): ?>
          <img id="profileImage" src="<?= app_href($teacher['profile_image'] . '?t=' . time()) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Photo">
        <?php else: ?>
          <span><?= strtoupper(substr($teacher['name'], 0, 1)) ?></span>
        <?php endif; ?>
        <input type="file" id="imageInput" accept="image/*" style="display:none;">
        <div style="position:absolute;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s;" class="image-overlay">
          <i class="bi bi-cloud-upload text-white" style="font-size:1.4rem;"></i>
        </div>
      </div>
      <!-- Info -->
      <div class="flex-grow-1">
        <h1 class="page-title mb-0"><?= htmlspecialchars($teacher['name']) ?></h1>
        <p class="mb-2" style="font-size:13px;color:var(--muted);"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($teacher['email']) ?></p>
        <?php
          $completionFields = [
            'first_name' => $teacher['first_name'] ?? '', 'second_name' => $teacher['second_name'] ?? '',
            'birth_date' => $teacher['birth_date'] ?? '', 'sexuality' => $teacher['sexuality'] ?? '',
            'nic_no' => $teacher['nic_no'] ?? '', 'school_name' => $teacher['school_name'] ?? '',
            'grade' => $teacher['grade'] ?? '', 'school_category' => $teacher['school_category'] ?? '',
            'main_subject' => $teacher['main_subject'] ?? '', 'first_appointment_date' => $teacher['first_appointment_date'] ?? '',
            'profile_image' => $teacher['profile_image'] ?? ''
          ];
          $filledFields = count(array_filter($completionFields, fn($v) => !empty($v)));
          $completionPercentage = round(($filledFields / count($completionFields)) * 100);
          $barColor = $completionPercentage >= 80 ? 'var(--success)' : 'var(--warning)';
        ?>
        <div class="d-flex align-items-center gap-3">
          <div class="flex-grow-1" style="background:var(--border);border-radius:999px;height:6px;overflow:hidden;">
            <div style="width:<?= $completionPercentage ?>%;background:<?= $barColor ?>;height:100%;border-radius:999px;transition:width 0.6s ease;"></div>
          </div>
          <span class="badge-soft <?= $completionPercentage >= 80 ? 'badge-soft-success' : 'badge-soft-warning' ?>"><?= $completionPercentage ?>% complete</span>
        </div>
        <?php if ($completionPercentage < 100): ?>
          <a href="#profileForm" class="text-decoration-none" style="font-size:12.5px;color:var(--primary);font-weight:600;"><i class="bi bi-pencil-square me-1"></i>Complete your profile</a>
        <?php else: ?>
          <span style="font-size:12.5px;color:var(--success);font-weight:600;"><i class="bi bi-check-circle-fill me-1"></i>Profile completed!</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-key-fill"></i></div>
    <div>
      <p class="stat-card-label">Teacher Code</p>
      <p class="stat-card-value" style="font-size:18px;letter-spacing:0.06em;"><?= htmlspecialchars($teacher['teacher_code'] ?? 'N/A') ?></p>
      <button class="btn btn-outline-primary btn-sm mt-1" onclick="copyToClipboard('<?= htmlspecialchars($teacher['teacher_code'] ?? '') ?>')"><i class="bi bi-files"></i> Copy</button>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-calendar-event-fill"></i></div>
    <div>
      <p class="stat-card-label">Member Since</p>
      <p class="stat-card-value" style="font-size:18px;"><?= htmlspecialchars(date('M Y', strtotime($teacher['created_at']))) ?></p>
      <p class="stat-card-sub"><?= htmlspecialchars(date('d M Y', strtotime($teacher['created_at']))) ?></p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-book-fill"></i></div>
    <div>
      <p class="stat-card-label">Subjects</p>
      <p class="stat-card-value"><?= count($subjects) ?></p>
      <div class="d-flex flex-wrap gap-1 mt-1">
        <?php foreach ($subjects as $s): ?>
          <span class="badge-soft badge-soft-primary" style="font-size:10px;"><?= htmlspecialchars($s['name']) ?></span>
        <?php endforeach; ?>
        <?php if (empty($subjects)): ?>
          <span class="stat-card-sub">No subjects yet</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-journal-text"></i></div>
    <div>
      <p class="stat-card-label">Papers</p>
      <p class="stat-card-value"><?= (int)($paperStats['total'] ?? 0) ?></p>
      <p class="stat-card-sub"><?= (int)($paperStats['published'] ?? 0) ?> published</p>
    </div>
  </div>
</div>

<!-- Profile Form -->
<div class="app-card" id="profileForm">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-person-lines-fill text-primary"></i> Profile Details</h2>
  </div>
  <div class="app-card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <h6 style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:14px;"><i class="bi bi-person me-2"></i>Personal Information</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($teacher['first_name'] ?? '') ?>" placeholder="First name">
        </div>
        <div class="col-md-6">
          <label class="form-label">Second Name</label>
          <input type="text" name="second_name" class="form-control" value="<?= htmlspecialchars($teacher['second_name'] ?? '') ?>" placeholder="Second name">
        </div>
        <div class="col-md-6">
          <label class="form-label">Birth Date</label>
          <input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($teacher['birth_date'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Gender</label>
          <select name="sexuality" class="form-select">
            <option value="">Select</option>
            <option value="Male"   <?= ($teacher['sexuality'] ?? '') == 'Male'   ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= ($teacher['sexuality'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
            <option value="Other"  <?= ($teacher['sexuality'] ?? '') == 'Other'  ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">NIC Number</label>
          <input type="text" name="nic_no" class="form-control" value="<?= htmlspecialchars($teacher['nic_no'] ?? '') ?>" placeholder="NIC number">
        </div>
      </div>

      <hr style="border-color:var(--border);margin:24px 0;">
      <h6 style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:14px;"><i class="bi bi-briefcase me-2"></i>Professional Details</h6>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">School Name</label>
          <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($teacher['school_name'] ?? '') ?>" placeholder="School name">
        </div>
        <div class="col-md-6">
          <label class="form-label">School Category</label>
          <select name="school_category" class="form-select">
            <option value="">Select</option>
            <option value="Government" <?= ($teacher['school_category'] ?? '') == 'Government' ? 'selected' : '' ?>>Government</option>
            <option value="Private"    <?= ($teacher['school_category'] ?? '') == 'Private'    ? 'selected' : '' ?>>Private</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Grade</label>
          <input type="text" name="grade" class="form-control" placeholder="e.g., Grade 10, 11" value="<?= htmlspecialchars($teacher['grade'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Main Subject</label>
          <input type="text" name="main_subject" class="form-control" value="<?= htmlspecialchars($teacher['main_subject'] ?? '') ?>" placeholder="Main subject">
        </div>
        <div class="col-md-6">
          <label class="form-label">First Appointment Date</label>
          <input type="date" name="first_appointment_date" class="form-control" value="<?= htmlspecialchars($teacher['first_appointment_date'] ?? '') ?>">
        </div>
      </div>

      <div class="mt-4 d-flex gap-2 flex-wrap">
        <button type="submit" name="update_profile" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Changes</button>
        <a href="<?= app_href('teacher/manage_papers.php') ?>" class="btn btn-outline-primary"><i class="bi bi-journal-text"></i> Manage Papers</a>
      </div>
    </form>
  </div>
</div>

<script>
function copyToClipboard(text) {
  if (!text) return;
  navigator.clipboard.writeText(text).then(() => {
    alert('Teacher code copied to clipboard!');
  }).catch(() => {
    alert('Failed to copy. Code: ' + text);
  });
}

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

<?php render_footer(); ?>
