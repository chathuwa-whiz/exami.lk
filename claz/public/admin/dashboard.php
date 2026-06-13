<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'admin') { http_response_code(403); echo 'Forbidden'; exit; }

$pdo = db();

// Get statistics
$teacherCount = $pdo->query('SELECT COUNT(*) FROM users WHERE user_type="teacher"')->fetchColumn();
$studentCount = $pdo->query('SELECT COUNT(*) FROM users WHERE user_type="student"')->fetchColumn();
$paperCount = $pdo->query('SELECT COUNT(*) FROM papers')->fetchColumn();
$attemptCount = $pdo->query('SELECT COUNT(*) FROM attempts')->fetchColumn();
$totalRevenue = $pdo->query('SELECT COALESCE(SUM(amount_cents), 0) FROM payments WHERE status="completed"')->fetchColumn() / 100;
$preapprovedCount = $pdo->query('SELECT COUNT(*) FROM preapproved_students WHERE is_deleted=0')->fetchColumn();

// Recent audit logs
$recentLogs = $pdo->query('SELECT user_id, action, details, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 5')->fetchAll();

render_header('Admin Dashboard');
?>

<?php render_welcome_banner($user); ?>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-people-fill"></i></div>
    <div>
      <p class="stat-card-label">Teachers</p>
      <p class="stat-card-value"><?= $teacherCount ?></p>
      <a href="<?= app_href('admin/teachers.php') ?>" class="stat-card-sub text-primary">Manage →</a>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-person-fill"></i></div>
    <div>
      <p class="stat-card-label">Students</p>
      <p class="stat-card-value"><?= $studentCount ?></p>
      <a href="<?= app_href('admin/index.php') ?>" class="stat-card-sub text-primary">Manage →</a>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon yellow"><i class="bi bi-journal-text"></i></div>
    <div>
      <p class="stat-card-label">Total Papers</p>
      <p class="stat-card-value"><?= $paperCount ?></p>
      <p class="stat-card-sub"><?= $attemptCount ?> attempts</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-wallet2"></i></div>
    <div>
      <p class="stat-card-label">Revenue</p>
      <p class="stat-card-value" style="font-size:20px;">Rs. <?= number_format($totalRevenue, 2) ?></p>
      <a href="<?= app_href('admin/payouts.php') ?>" class="stat-card-sub text-primary">View payouts →</a>
    </div>
  </div>
</div>

<div class="row g-4">

  <!-- Quick Access Tools -->
  <div class="col-lg-5">
    <div class="app-card">
      <div class="app-card-header">
        <h2 class="app-card-title"><i class="bi bi-grid-3x3-gap text-primary"></i> Admin Tools</h2>
      </div>
      <div class="app-card-body">
        <div class="row g-3">
          <?php
          $tools = [
            ['admin/teachers.php',          'people-fill',         'Teachers',      'Manage teacher accounts'],
            ['admin/index.php',             'person-check-fill',   'Students',      "Preapproved list ({$preapprovedCount})"],
            ['admin/payouts.php',           'wallet2',             'Payouts',       'Teacher payments'],
            ['admin/logs.php',              'journal-text',        'Audit Logs',    'Activity log'],
            ['admin/import.php',            'upload',              'Import',        'Bulk import data'],
            ['admin/export_preapproved.php','download',            'Export',        'Download data'],
          ];
          foreach ($tools as [$href, $icon, $label, $sub]):
          ?>
          <div class="col-6">
            <a href="<?= app_href($href) ?>" class="item-card text-decoration-none d-block" style="text-align:center;padding:18px 12px;">
              <i class="bi bi-<?= $icon ?>" style="font-size:22px;color:var(--primary);display:block;margin-bottom:8px;"></i>
              <div class="fw-semibold" style="font-size:13px;color:var(--on-surface);margin-bottom:2px;"><?= $label ?></div>
              <div style="font-size:11.5px;color:var(--muted);"><?= $sub ?></div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="col-lg-7">
    <div class="app-card">
      <div class="app-card-header">
        <h2 class="app-card-title"><i class="bi bi-activity text-primary"></i> Recent Activity</h2>
        <a href="<?= app_href('admin/logs.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="app-card-body no-pad">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead style="background:var(--surface-subtle);">
              <tr>
                <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:0.06em;border-bottom:1px solid var(--border);">Action</th>
                <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:0.06em;border-bottom:1px solid var(--border);">User</th>
                <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:0.06em;border-bottom:1px solid var(--border);">Details</th>
                <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);letter-spacing:0.06em;border-bottom:1px solid var(--border);">Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentLogs)): ?>
                <tr><td colspan="4" class="text-center py-5" style="color:var(--muted-light);">No activity yet</td></tr>
              <?php else: ?>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                  <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);">
                    <span class="badge-soft badge-soft-primary"><?= htmlspecialchars($log['action']) ?></span>
                  </td>
                  <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);font-size:13px;color:var(--muted);"><?= htmlspecialchars($log['user_id']) ?></td>
                  <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);font-size:12.5px;color:var(--muted);"><?= htmlspecialchars(substr($log['details'], 0, 45)) ?>...</td>
                  <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);font-size:12px;color:var(--muted-light);white-space:nowrap;"><?= date('M d, H:i', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php render_footer(); ?>
