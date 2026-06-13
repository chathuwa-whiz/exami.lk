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

<!-- Hero Section -->
<section style="background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-left: 4px solid #3b82f6;">
  <div class="position-relative">
    <h1 class="mb-2" style="font-size: 2rem; font-weight: 700; color: #1f2937;">
      <i class="bi bi-speedometer2 me-2" style="color: #3b82f6;"></i>Admin Dashboard
    </h1>
    <p style="color: #6b7280; font-size: 1rem; margin: 0;">Manage teachers, students, papers and revenue</p>
  </div>
</section>

<!-- Key Metrics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <!-- Teachers Card -->
  <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-bottom: 3px solid #6b7280;">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;"><i class="bi bi-people me-1"></i>Teachers</p>
    <h2 style="margin: 0 0 0.5rem 0; font-weight: 700; color: #1f2937; font-size: 1.8rem;"><?= $teacherCount ?></h2>
    <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
      <a href="<?= app_href('admin/teachers.php') ?>" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Manage →</a>
    </p>
  </div>

  <!-- Students Card -->
  <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-bottom: 3px solid #6b7280;">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;"><i class="bi bi-person me-1"></i>Students</p>
    <h2 style="margin: 0 0 0.5rem 0; font-weight: 700; color: #1f2937; font-size: 1.8rem;"><?= $studentCount ?></h2>
    <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
      <a href="<?= app_href('admin/index.php') ?>" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Manage →</a>
    </p>
  </div>

  <!-- Papers Card -->
  <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-bottom: 3px solid #6b7280;">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;"><i class="bi bi-file-text me-1"></i>Papers</p>
    <h2 style="margin: 0 0 0.5rem 0; font-weight: 700; color: #1f2937; font-size: 1.8rem;"><?= $paperCount ?></h2>
    <p style="margin: 0; color: #6b7280; font-size: 0.85rem;"><?= $attemptCount ?> attempts</p>
  </div>

  <!-- Revenue Card -->
  <div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border-bottom: 3px solid #6b7280;">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;"><i class="bi bi-wallet2 me-1"></i>Revenue</p>
    <h2 style="margin: 0 0 0.5rem 0; font-weight: 700; color: #1f2937; font-size: 1.8rem;">Rs. <?= number_format($totalRevenue, 2) ?></h2>
    <p style="margin: 0; color: #6b7280; font-size: 0.85rem;">
      <a href="<?= app_href('admin/payouts.php') ?>" style="color: #3b82f6; text-decoration: none; font-weight: 600;">View payouts →</a>
    </p>
  </div>
</div>

<!-- Admin Navigation -->
<section style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 2rem;">
  <h3 style="font-weight: 700; margin-bottom: 1.5rem; font-size: 1.1rem; color: #1f2937;">Admin Tools</h3>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
    <!-- Teachers Management -->
    <a href="<?= app_href('admin/teachers.php') ?>" style="display: block; padding: 1rem; background: white; color: #1f2937; border-radius: 8px; text-decoration: none; transition: all 0.2s; border: 1px solid #e5e7eb; border-left: 4px solid #3b82f6;">
      <div style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #3b82f6;"><i class="bi bi-people-fill"></i></div>
      <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">Teachers</div>
      <div style="font-size: 0.8rem; color: #6b7280;">Manage accounts</div>
    </a>

    <!-- Preapproved Students -->
    <a href="<?= app_href('admin/index.php') ?>" style="display: block; padding: 1rem; background: white; color: #1f2937; border-radius: 8px; text-decoration: none; transition: all 0.2s; border: 1px solid #e5e7eb; border-left: 4px solid #3b82f6;">
      <div style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #3b82f6;"><i class="bi bi-person-check-fill"></i></div>
      <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">Students</div>
      <div style="font-size: 0.8rem; color: #6b7280;">Approved list (<?= $preapprovedCount ?>)</div>
    </a>

    <!-- Payouts -->
    <a href="<?= app_href('admin/payouts.php') ?>" style="display: block; padding: 1rem; background: white; color: #1f2937; border-radius: 8px; text-decoration: none; transition: all 0.2s; border: 1px solid #e5e7eb; border-left: 4px solid #3b82f6;">
      <div style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #3b82f6;"><i class="bi bi-wallet2"></i></div>
      <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">Payouts</div>
      <div style="font-size: 0.8rem; color: #6b7280;">Teacher payments</div>
    </a>

    <!-- Audit Logs -->
    <a href="<?= app_href('admin/logs.php') ?>" style="display: block; padding: 1rem; background: white; color: #1f2937; border-radius: 8px; text-decoration: none; transition: all 0.2s; border: 1px solid #e5e7eb; border-left: 4px solid #3b82f6;">
      <div style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #3b82f6;"><i class="bi bi-file-text-fill"></i></div>
      <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">Audit Logs</div>
      <div style="font-size: 0.8rem; color: #6b7280;">Activity log</div>
    </a>

    <!-- Export Students -->
    <a href="<?= app_href('admin/export_preapproved.php') ?>" style="display: block; padding: 1rem; background: white; color: #1f2937; border-radius: 8px; text-decoration: none; transition: all 0.2s; border: 1px solid #e5e7eb; border-left: 4px solid #3b82f6;">
      <div style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #3b82f6;"><i class="bi bi-download"></i></div>
      <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">Export</div>
      <div style="font-size: 0.8rem; color: #6b7280;">Download data</div>
    </a>
  </div>
</section>

<!-- Recent Activity -->
<section style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
  <h3 style="font-weight: 700; margin-bottom: 1.5rem; font-size: 1.1rem; color: #1f2937;">Recent Activity</h3>
  <div style="overflow-x: auto;">
    <table class="table table-sm table-borderless" style="margin-bottom: 0;">
      <thead style="border-bottom: 2px solid #e5e7eb;">
        <tr>
          <th style="color: #666; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">Action</th>
          <th style="color: #666; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">User ID</th>
          <th style="color: #666; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">Details</th>
          <th style="color: #666; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentLogs)): ?>
          <tr>
            <td colspan="4" style="text-align: center; color: #999; padding: 2rem;">No activity yet</td>
          </tr>
        <?php else: ?>
          <?php foreach ($recentLogs as $log): ?>
            <tr style="border-bottom: 1px solid #e9ecef;">
              <td><span style="display: inline-block; padding: 0.4rem 0.8rem; background: #f0f0f0; border-radius: 6px; font-size: 0.85rem; font-weight: 600; color: #333;"><?= htmlspecialchars($log['action']) ?></span></td>
              <td style="color: #666;"><?= htmlspecialchars($log['user_id']) ?></td>
              <td style="color: #999; font-size: 0.9rem;"><?= htmlspecialchars(substr($log['details'], 0, 50)) ?>...</td>
              <td style="color: #999; font-size: 0.9rem;"><?= date('M d, Y H:i', strtotime($log['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php render_footer(); ?>
