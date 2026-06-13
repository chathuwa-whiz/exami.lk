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
      <div><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success" role="alert">
    <?= htmlspecialchars($success) ?>
  </div>
<?php endif; ?>

<!-- Hero Header Section -->
<section class="mb-4 mb-md-5" style="background: white; border-radius: 12px; padding: 2rem; border-left: 4px solid #3b82f6; position: relative; overflow: hidden;">
  <div class="position-relative">
    <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3 mb-3">
      <div style="position: relative; width: 80px; height: 80px; min-width: 80px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 2rem; overflow: hidden; cursor: pointer;" id="profileImageContainer" title="Click to upload profile image">
        <?php if (!empty($teacher['profile_image'])): ?>
          <img id="profileImage" src="<?= app_href($teacher['profile_image'] . '?t=' . time()) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Profile Image">
        <?php else: ?>
          <i class="bi bi-person-workspace" style="color: #6b7280;"></i>
        <?php endif; ?>
        <input type="file" id="imageInput" accept="image/*" style="display: none;">
      </div>
      <div class="flex-grow-1">
        <h1 class="mb-2" style="font-size: 2.5rem; font-weight: 700; color: #1f2937;"><?= htmlspecialchars($teacher['name']) ?></h1>
        <p class="mb-0 small" style="color: #6b7280;"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($teacher['email']) ?></p>
      </div>
    </div>
    
    <!-- Subjects in Hero Section -->
    <div class="mt-3 pt-3" style="border-top: 1px solid #e5e7eb;">
      <h5 class="mb-3" style="color: #1f2937;"><i class="bi bi-book-half me-2"></i>Your Subjects</h5>
      <?php if (empty($subjects)): ?>
        <p class="small mb-0" style="color: #6b7280;">No subjects assigned yet.</p>
      <?php else: ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($subjects as $s): ?>
            <span class="badge" style="background: #f3f4f6; color: #374151;"><?= htmlspecialchars($s['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Profile Completion -->
<?php
$completionFields = [
  'first_name' => $teacher['first_name'] ?? '',
  'second_name' => $teacher['second_name'] ?? '',
  'birth_date' => $teacher['birth_date'] ?? '',
  'sexuality' => $teacher['sexuality'] ?? '',
  'nic_no' => $teacher['nic_no'] ?? '',
  'school_name' => $teacher['school_name'] ?? '',
  'grade' => $teacher['grade'] ?? '',
  'school_category' => $teacher['school_category'] ?? '',
  'main_subject' => $teacher['main_subject'] ?? '',
  'first_appointment_date' => $teacher['first_appointment_date'] ?? '',
  'profile_image' => $teacher['profile_image'] ?? ''
];
$filledFields = count(array_filter($completionFields, fn($v) => !empty($v)));
$totalFields = count($completionFields);
$completionPercentage = round(($filledFields / $totalFields) * 100);
$bgColor = $completionPercentage >= 80 ? '#dcfce7' : '#fef3c7';
$borderColor = $completionPercentage >= 80 ? '#10b981' : '#f59e0b';
$textColor = $completionPercentage >= 80 ? '#166534' : '#92400e';
?>
<section class="mb-4">
  <div class="card" style="border-radius: 12px; border: 1px solid #e5e7eb; border-bottom: 3px solid <?= $borderColor ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
    <div class="card-body p-4">
      <div class="d-flex align-items-center gap-3">
        <div style="width: 60px; height: 60px; min-width: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: <?= $bgColor ?>; color: <?= $textColor ?>;">
          <i class="bi bi-<?= $completionPercentage >= 80 ? 'check-circle-fill' : 'info-circle-fill' ?>"></i>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0 fw-bold" style="color: #1f2937;"><?= htmlspecialchars($teacher['name']) ?></h5>
            <span class="badge" style="background: <?= $bgColor ?>; color: <?= $textColor ?>; font-size: 0.9rem; padding: 0.4rem 0.8rem;"><?= $completionPercentage ?>%</span>
          </div>
          <p class="mb-2 small" style="color: #6b7280;">Teacher Profile</p>
          <?php if ($completionPercentage < 100): ?>
            <a href="#profileForm" class="text-decoration-none fw-semibold" style="color: <?= $borderColor ?>; font-size: 0.9rem;">
              <i class="bi bi-pencil-square me-1"></i>Complete your profile
            </a>
          <?php else: ?>
            <p class="mb-0 small" style="color: #10b981;"><i class="bi bi-check-circle-fill me-1"></i>Profile completed</p>
          <?php endif; ?>
          <div class="progress mt-3" style="height: 8px; border-radius: 10px; background: #e5e7eb;">
            <div class="progress-bar" role="progressbar" style="width: <?= $completionPercentage ?>%; background: <?= $borderColor ?>; border-radius: 10px;" aria-valuenow="<?= $completionPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Profile Info Cards -->
<section class="mb-4 mb-md-5">
  <div class="row g-3">
    <!-- Teacher Code Card -->
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="card" style="border: 1px solid #e5e7eb; border-left: 4px solid #3b82f6; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <div class="card-body p-3 p-md-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div style="width: 40px; height: 40px; min-width: 40px; border-radius: 10px; background: #dbeafe; display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.2rem;">
              <i class="bi bi-key-fill"></i>
            </div>
          </div>
          <p class="text-uppercase small" style="color: #6b7280; letter-spacing: 0.05em; font-weight: 600; font-size: 0.75rem; margin-bottom: 0.5rem;">Teacher Code</p>
          <h3 class="mb-2" style="font-family: 'Courier New', monospace; font-size: clamp(1.3rem, 4vw, 1.8rem); font-weight: 700; color: #3b82f6; word-break: break-all;">
            <?= htmlspecialchars($teacher['teacher_code'] ?? 'N/A') ?>
          </h3>
          <p class="mb-3" style="color: #6b7280; font-size: 0.85rem;">Share this code with students</p>
          <button class="btn btn-sm btn-outline-primary w-100" onclick="copyToClipboard('<?= htmlspecialchars($teacher['teacher_code'] ?? '') ?>')">
            <i class="bi bi-files"></i> Copy Code
          </button>
        </div>
      </div>
    </div>

    <!-- Account Created Card -->
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="card" style="border: 1px solid #e5e7eb; border-bottom: 3px solid #9ca3af; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <div class="card-body p-3 p-md-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div style="width: 40px; height: 40px; min-width: 40px; border-radius: 10px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #6b7280; font-size: 1.2rem;">
              <i class="bi bi-calendar-event-fill"></i>
            </div>
          </div>
          <p class="text-uppercase small" style="color: #6b7280; letter-spacing: 0.05em; font-weight: 600; font-size: 0.75rem; margin-bottom: 0.5rem;">Member Since</p>
          <h3 class="mb-2" style="font-size: clamp(1.3rem, 4vw, 1.8rem); font-weight: 700; color: #1f2937;">
            <?= htmlspecialchars(date('M d, Y', strtotime($teacher['created_at']))) ?>
          </h3>
          <p style="color: #6b7280; font-size: 0.85rem;">
            <i class="bi bi-clock-history"></i> 
            Joined <?= htmlspecialchars(date('F Y', strtotime($teacher['created_at']))) ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Subjects Count Card -->
    <div class="col-12 col-sm-6 col-lg-4">
      <div class="card" style="border: 1px solid #e5e7eb; border-bottom: 3px solid #3b82f6; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
        <div class="card-body p-3 p-md-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div style="width: 40px; height: 40px; min-width: 40px; border-radius: 10px; background: #dbeafe; display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.2rem;">
              <i class="bi bi-book-fill"></i>
            </div>
          </div>
          <p class="text-uppercase small" style="color: #6b7280; letter-spacing: 0.05em; font-weight: 600; font-size: 0.75rem; margin-bottom: 0.5rem;">Subjects</p>
          <h3 class="mb-2" style="font-size: clamp(1.3rem, 4vw, 1.8rem); font-weight: 700; color: #3b82f6;">
            <?= count($subjects) ?>
          </h3>
          <p style="color: #6b7280; font-size: 0.85rem;">
            <i class="bi bi-check-circle-fill" style="color: #3b82f6;"></i> 
            <?= count($subjects) === 1 ? 'Subject' : 'Subjects' ?> assigned
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Profile Details Form -->
<section class="mb-4 mb-md-5" id="profileForm">
  <div class="card shadow-sm" style="border-radius: 12px; border: none;">
    <div class=\"card-header\" style=\"background: white; border-bottom: 2px solid #3b82f6; border-radius: 12px 12px 0 0; padding: 1rem 1.5rem;\">\n      <h5 class=\"mb-0 h6 h5-md\" style=\"color: #1f2937;\"><i class=\"bi bi-person-lines-fill me-2\"></i>Profile Information</h5>\n    </div>
    <div class="card-body p-3 p-md-4">
      <form method="POST">
        <?= csrf_field() ?>
        
        <h6 class="fw-bold mb-3 text-muted" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="bi bi-person me-2"></i>Personal Information</h6>
        <div class="row g-3 mb-4">
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($teacher['first_name'] ?? '') ?>" placeholder="Enter first name">
          </div>
          
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">Second Name</label>
            <input type="text" name="second_name" class="form-control" value="<?= htmlspecialchars($teacher['second_name'] ?? '') ?>" placeholder="Enter second name">
          </div>
          
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">Birth Date</label>
            <input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($teacher['birth_date'] ?? '') ?>">
          </div>
          
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">Gender</label>
            <select name="sexuality" class="form-select">
              <option value="">Select Gender</option>
              <option value="Male" <?= ($teacher['sexuality'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($teacher['sexuality'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= ($teacher['sexuality'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          
          <div class="col-12">
            <label class="form-label fw-semibold small">NIC Number</label>
            <input type="text" name="nic_no" class="form-control" value="<?= htmlspecialchars($teacher['nic_no'] ?? '') ?>" placeholder="Enter NIC number">
          </div>
        </div>

        <hr class="my-4">
        
        <h6 class="fw-bold mb-3 text-muted" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="bi bi-briefcase me-2"></i>Professional Details</h6>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold small">School Name</label>
            <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars($teacher['school_name'] ?? '') ?>" placeholder="Enter school name">
          </div>
          
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">School Category</label>
            <select name="school_category" class="form-select">
              <option value="">Select Category</option>
              <option value="Government" <?= ($teacher['school_category'] ?? '') == 'Government' ? 'selected' : '' ?>>Government</option>
              <option value="Private" <?= ($teacher['school_category'] ?? '') == 'Private' ? 'selected' : '' ?>>Private</option>
            </select>
          </div>
          
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">Grade</label>
            <input type="text" name="grade" class="form-control" placeholder="e.g., Grade 10, 11" value="<?= htmlspecialchars($teacher['grade'] ?? '') ?>">
          </div>
          
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">Main Subject</label>
            <input type="text" name="main_subject" class="form-control" value="<?= htmlspecialchars($teacher['main_subject'] ?? '') ?>" placeholder="Enter main subject">
          </div>
          
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">First Appointment Date</label>
            <input type="date" name="first_appointment_date" class="form-control" value="<?= htmlspecialchars($teacher['first_appointment_date'] ?? '') ?>">
          </div>
        </div>

        <div class="mt-4 d-grid d-sm-block">
          <button type="submit" name="update_profile" class="btn btn-primary px-4 py-2">
            <i class="bi bi-check-circle me-2"></i>Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- Action Buttons Section -->
<section class="mt-4 mb-4">
  <div class="row g-2 g-md-3">
    <div class="col-12 col-sm-6">
      <a class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2" style="font-weight: 600; padding: 0.875rem 1.5rem;" href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>">
        <i class="bi bi-plus-circle-fill"></i> 
        <span>Manage Papers</span>
      </a>
    </div>
    <div class="col-12 col-sm-6">
      <a class="btn btn-lg btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2" style="font-weight: 600; padding: 0.875rem 1.5rem;" href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>">
        <i class="bi bi-files-fill"></i> 
        <span>View My Papers</span>
      </a>
    </div>
  </div>
</section>

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
