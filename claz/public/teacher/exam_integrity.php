<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();

$user = current_user();
if ($user['user_type'] !== 'teacher') {
    redirect('login.php');
}

$pdo = db();

// Get filter parameters
$filter_paper = $_GET['paper'] ?? '';
$filter_student = $_GET['student'] ?? '';
$filter_activity = $_GET['activity'] ?? '';
$filter_flagged = $_GET['flagged'] ?? '';

// Build query
$query = '
    SELECT 
        eil.id,
        eil.attempt_id,
        eil.activity_type,
        eil.details,
        eil.tab_switches,
        eil.logged_at,
        a.integrity_flagged,
        p.id as paper_id,
        p.title as paper_title,
        u.id as student_id,
        u.name as student_name,
        u.email as student_email
    FROM exam_integrity_logs eil
    JOIN attempts a ON eil.attempt_id = a.id
    JOIN papers p ON a.paper_id = p.id
    JOIN users u ON a.student_id = u.id
    WHERE p.teacher_id = ?
';

$params = [$user['id']];

if ($filter_paper) {
    $query .= ' AND p.id = ?';
    $params[] = $filter_paper;
}

if ($filter_student) {
    $query .= ' AND u.id = ?';
    $params[] = $filter_student;
}

if ($filter_activity) {
    $query .= ' AND eil.activity_type = ?';
    $params[] = $filter_activity;
}

if ($filter_flagged === '1') {
    $query .= ' AND a.integrity_flagged = 1';
} elseif ($filter_flagged === '0') {
    $query .= ' AND a.integrity_flagged = 0';
}

$query .= ' ORDER BY eil.logged_at DESC LIMIT 500';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get teacher's papers for filter dropdown
$papersStmt = $pdo->prepare('SELECT id, title FROM papers WHERE teacher_id = ? ORDER BY title');
$papersStmt->execute([$user['id']]);
$papers = $papersStmt->fetchAll();

// Get distinct activity types
$activitiesStmt = $pdo->prepare('
    SELECT DISTINCT eil.activity_type 
    FROM exam_integrity_logs eil
    JOIN attempts a ON eil.attempt_id = a.id
    JOIN papers p ON a.paper_id = p.id
    WHERE p.teacher_id = ?
    ORDER BY eil.activity_type
');
$activitiesStmt->execute([$user['id']]);
$activities = $activitiesStmt->fetchAll();

// Get students who took exams from teacher's papers
$studentsStmt = $pdo->prepare('
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN attempts a ON u.id = a.student_id
    JOIN papers p ON a.paper_id = p.id
    WHERE p.teacher_id = ?
    ORDER BY u.name
');
$studentsStmt->execute([$user['id']]);
$students = $studentsStmt->fetchAll();

// Statistics
$statsStmt = $pdo->prepare('
    SELECT 
        COUNT(DISTINCT eil.id) as total_activities,
        COUNT(DISTINCT a.id) as attempts_with_activity,
        COUNT(DISTINCT CASE WHEN a.integrity_flagged = 1 THEN a.id END) as flagged_attempts,
        COUNT(DISTINCT eil.activity_type) as activity_types
    FROM exam_integrity_logs eil
    JOIN attempts a ON eil.attempt_id = a.id
    JOIN papers p ON a.paper_id = p.id
    WHERE p.teacher_id = ?
');
$statsStmt->execute([$user['id']]);
$stats = $statsStmt->fetch();

?>
<?php render_header('Exam Integrity Monitoring'); ?>

<div class="container-fluid py-5">
    <!-- Header -->
    <div class="mb-5">
        <h1 class="mb-2" style="color: #667eea; font-weight: 700;">
            <i class="bi bi-shield-exclamation"></i> Exam Integrity Monitoring
        </h1>
        <p class="text-muted mb-4">Review suspicious activities and flagged attempts from your exams</p>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="app-card p-4 h-100" style="border-left: 4px solid #667eea; border-radius: 12px;">
                <div style="width: 48px; height: 48px; border-radius: 8px; background: #dbeafe; display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.05em; font-weight: 600;">Total Activities</p>
                <h3 style="font-size: 2rem; font-weight: 700; color: #667eea; margin: 0;">
                    <?= number_format($stats['total_activities'] ?? 0) ?>
                </h3>
                <p class="text-muted small mt-2 mb-0">Suspicious activities logged</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card p-4 h-100" style="border-left: 4px solid #dc3545; border-radius: 12px;">
                <div style="width: 48px; height: 48px; border-radius: 8px; background: #fee2e2; display: flex; align-items: center; justify-content: center; color: #dc2626; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class="bi bi-flag-fill"></i>
                </div>
                <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.05em; font-weight: 600;">Flagged Attempts</p>
                <h3 style="font-size: 2rem; font-weight: 700; color: #dc3545; margin: 0;">
                    <?= number_format($stats['flagged_attempts'] ?? 0) ?>
                </h3>
                <p class="text-muted small mt-2 mb-0">Exams flagged for review</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card p-4 h-100" style="border-left: 4px solid #ffc107; border-radius: 12px;">
                <div style="width: 48px; height: 48px; border-radius: 8px; background: #fef3c7; display: flex; align-items: center; justify-content: center; color: #ca8a04; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
                <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.05em; font-weight: 600;">Attempts Monitored</p>
                <h3 style="font-size: 2rem; font-weight: 700; color: #ffc107; margin: 0;">
                    <?= number_format($stats['attempts_with_activity'] ?? 0) ?>
                </h3>
                <p class="text-muted small mt-2 mb-0">Exams with activity logs</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card p-4 h-100" style="border-left: 4px solid #17a2b8; border-radius: 12px;">
                <div style="width: 48px; height: 48px; border-radius: 8px; background: #d1fae5; display: flex; align-items: center; justify-content: center; color: #059669; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class="bi bi-tag-fill"></i>
                </div>
                <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.05em; font-weight: 600;">Activity Types</p>
                <h3 style="font-size: 2rem; font-weight: 700; color: #17a2b8; margin: 0;">
                    <?= number_format($stats['activity_types'] ?? 0) ?>
                </h3>
                <p class="text-muted small mt-2 mb-0">Different violation types</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="app-card p-4 mb-4 shadow-sm" style="border-radius: 12px;">
        <h5 class="mb-3" style="color: #333; font-weight: 600;">
            <i class="bi bi-funnel"></i> Filter Results
        </h5>
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small text-uppercase" style="font-weight: 600; letter-spacing: 0.05em;">Paper</label>
                <select class="form-select" name="paper" style="border-radius: 8px;">
                    <option value="">All Papers</option>
                    <?php foreach ($papers as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_paper == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-uppercase" style="font-weight: 600; letter-spacing: 0.05em;">Student</label>
                <select class="form-select" name="student" style="border-radius: 8px;">
                    <option value="">All Students</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_student == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-uppercase" style="font-weight: 600; letter-spacing: 0.05em;">Activity Type</label>
                <select class="form-select" name="activity" style="border-radius: 8px;">
                    <option value="">All Types</option>
                    <?php foreach ($activities as $a): ?>
                        <option value="<?= htmlspecialchars($a['activity_type']) ?>" <?= $filter_activity == $a['activity_type'] ? 'selected' : '' ?>>
                            <?= ucwords(str_replace('_', ' ', htmlspecialchars($a['activity_type']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-uppercase" style="font-weight: 600; letter-spacing: 0.05em;">Status</label>
                <select class="form-select" name="flagged" style="border-radius: 8px;">
                    <option value="">All Status</option>
                    <option value="1" <?= $filter_flagged === '1' ? 'selected' : '' ?>>Flagged Only</option>
                    <option value="0" <?= $filter_flagged === '0' ? 'selected' : '' ?>>Not Flagged</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 600;">
                    <i class="bi bi-search"></i> Apply Filters
                </button>
                <a href="?" class="btn btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
                    <i class="bi bi-arrow-clockwise"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="app-card shadow-sm" style="border-radius: 12px; overflow: visible;">
        <?php if (empty($logs)): ?>
            <div class="p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem; display: block;"></i>
                <p class="text-muted" style="font-size: 1.1rem;">No suspicious activities found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.95rem;">
                    <thead style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-bottom: 2px solid #dee2e6;">
                        <tr>
                            <th class="px-4 py-3" style="font-weight: 600; color: #333; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">Student</th>
                            <th class="px-4 py-3" style="font-weight: 600; color: #333; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">Paper</th>
                            <th class="px-4 py-3" style="font-weight: 600; color: #333; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">Activity</th>
                            <th class="px-4 py-3" style="font-weight: 600; color: #333; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">Tab Switches</th>
                            <th class="px-4 py-3" style="font-weight: 600; color: #333; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">Time</th>
                            <th class="px-4 py-3" style="font-weight: 600; color: #333; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">Status</th>
                            <th class="px-4 py-3" style="font-weight: 600; color: #333; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td class="px-4 py-3">
                                    <div>
                                        <strong style="color: #333;"><?= htmlspecialchars($log['student_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($log['student_email']) ?></small>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="<?= app_href("teacher/edit_paper.php?id=" . $log['paper_id']) ?>" style="color: #667eea; text-decoration: none; font-weight: 500;">
                                        <?= htmlspecialchars($log['paper_title']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $activityType = $log['activity_type'];
                                    $badgeColor = match($activityType) {
                                        'tab_switch' => '#667eea',
                                        'copy_attempt', 'cut_attempt', 'paste_attempt' => '#dc3545',
                                        'right_click_attempt' => '#fd7e14',
                                        'f12_attempt', 'inspect_attempt', 'view_source_attempt' => '#6f42c1',
                                        'escape_attempt' => '#e83e8c',
                                        'alt_tab_attempt', 'close_tab_attempt', 'new_tab_attempt' => '#20c997',
                                        'window_blur', 'focus' => '#17a2b8',
                                        'integrity_acknowledged' => '#28a745',
                                        default => '#6c757d'
                                    };
                                    ?>
                                    <span class="badge" style="background-color: <?= $badgeColor ?>; padding: 0.5rem 0.8rem; color: white;">
                                        <?= ucwords(str_replace('_', ' ', $activityType)) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($log['tab_switches'] > 0): ?>
                                        <strong style="color: <?= $log['tab_switches'] > 5 ? '#dc3545' : '#ffc107' ?>;">
                                            <?= $log['tab_switches'] ?>
                                        </strong>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <small class="text-muted">
                                        <?= date('M d, Y', strtotime($log['logged_at'])) ?><br>
                                        <?= date('H:i:s', strtotime($log['logged_at'])) ?>
                                    </small>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($log['integrity_flagged']): ?>
                                        <span class="badge bg-danger d-inline-flex align-items-center gap-1" style="padding: 0.5rem 0.8rem;">
                                            <i class="bi bi-flag-fill"></i> Flagged
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success d-inline-flex align-items-center gap-1" style="padding: 0.5rem 0.8rem;">
                                            <i class="bi bi-check-circle-fill"></i> OK
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="openModal('detailsModal<?= $log['id'] ?>')" style="border-radius: 6px;">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Legend -->
    <div class="row g-3 mt-4">
        <div class="col-12">
            <div class="app-card p-4" style="border-radius: 12px; background: #f8f9fa;">
                <h6 class="mb-3" style="font-weight: 600; color: #333;">Activity Type Legend</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <small>
                            <span class="badge" style="background-color: #667eea; padding: 0.4rem 0.6rem; margin-right: 0.5rem;">tab_switch</span>
                            Student switched tabs/windows
                        </small>
                    </div>
                    <div class="col-md-4">
                        <small>
                            <span class="badge" style="background-color: #dc3545; padding: 0.4rem 0.6rem; margin-right: 0.5rem;">copy/cut/paste</span>
                            Attempted to copy, cut, or paste
                        </small>
                    </div>
                    <div class="col-md-4">
                        <small>
                            <span class="badge" style="background-color: #fd7e14; padding: 0.4rem 0.6rem; margin-right: 0.5rem;">right_click</span>
                            Attempted right-click/inspect
                        </small>
                    </div>
                    <div class="col-md-4">
                        <small>
                            <span class="badge" style="background-color: #6f42c1; padding: 0.4rem 0.6rem; margin-right: 0.5rem;">dev_tools</span>
                            Attempted to open developer tools
                        </small>
                    </div>
                    <div class="col-md-4">
                        <small>
                            <span class="badge" style="background-color: #e83e8c; padding: 0.4rem 0.6rem; margin-right: 0.5rem;">escape</span>
                            Attempted to exit fullscreen
                        </small>
                    </div>
                    <div class="col-md-4">
                        <small>
                            <span class="badge" style="background-color: #20c997; padding: 0.4rem 0.6rem; margin-right: 0.5rem;">app_switch</span>
                            Attempted Alt+Tab or new tab
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Render all modals outside main container -->
<?php foreach ($logs as $log): ?>
<div class="modal fade" id="detailsModal<?= $log['id'] ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?= $log['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 1px solid #f0f0f0; background: #f8f9fa;">
                <h5 class="modal-title" id="detailsModalLabel<?= $log['id'] ?>" style="font-weight: 600;">Activity Details</h5>
                <button type="button" class="btn-close" onclick="closeModal('detailsModal<?= $log['id'] ?>')" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-uppercase text-muted" style="font-weight: 600; letter-spacing: 0.05em;">Student</small>
                        <p class="mb-0" style="font-weight: 500; color: #333;">
                            <?= htmlspecialchars($log['student_name']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($log['student_email']) ?></small>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-uppercase text-muted" style="font-weight: 600; letter-spacing: 0.05em;">Paper</small>
                        <p class="mb-0" style="font-weight: 500; color: #333;">
                            <?= htmlspecialchars($log['paper_title']) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-uppercase text-muted" style="font-weight: 600; letter-spacing: 0.05em;">Activity Type</small>
                        <p class="mb-0" style="font-weight: 500; color: #333;">
                            <?= ucwords(str_replace('_', ' ', $log['activity_type'])) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-uppercase text-muted" style="font-weight: 600; letter-spacing: 0.05em;">Timestamp</small>
                        <p class="mb-0" style="font-weight: 500; color: #333;">
                            <?= date('M d, Y H:i:s', strtotime($log['logged_at'])) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-uppercase text-muted" style="font-weight: 600; letter-spacing: 0.05em;">Tab Switches</small>
                        <p class="mb-0" style="font-weight: 500; color: #333;">
                            <?= $log['tab_switches'] ?? 0 ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-uppercase text-muted" style="font-weight: 600; letter-spacing: 0.05em;">Status</small>
                        <p class="mb-0" style="font-weight: 500; color: #333;">
                            <?= $log['integrity_flagged'] ? '<span class="badge bg-danger">Flagged</span>' : '<span class="badge bg-success">OK</span>' ?>
                        </p>
                    </div>
                    <?php if ($log['details']): ?>
                        <div class="col-12">
                            <small class="text-uppercase text-muted" style="font-weight: 600; letter-spacing: 0.05em; display: block; margin-bottom: 0.5rem;">Additional Details</small>
                            <pre style="background: #f8f9fa; padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.85rem; max-height: 200px; overflow-y: auto;"><code><?= htmlspecialchars($log['details']) ?></code></pre>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f0f0f0;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal<?= $log['id'] ?>')" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
    modal.style.display = 'block';
    modal.classList.add('show');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
}
</script>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 9999;
    display: none;
    width: 100%;
    height: 100%;
    overflow-x: hidden;
    overflow-y: auto;
    outline: 0;
}

.modal::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.3);
    z-index: 9998;
}

.modal.show {
    display: block !important;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 800px;
    z-index: 9999;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff !important;
    background-clip: padding-box;
    border: 1px solid rgba(0,0,0,.2);
    outline: 0;
}

body.modal-open {
    overflow: hidden !important;
}
</style>
-
<?php render_footer(); ?>
