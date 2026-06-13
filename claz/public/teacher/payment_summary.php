<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();

$user = current_user();
if ($user['user_type'] !== 'teacher') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$pdo = db();

$summaryStmt = $pdo->prepare('
  SELECT
    COALESCE(SUM(p.amount_cents), 0) AS total_collected_cents,
    COUNT(*) AS total_payments,
    COALESCE(SUM(CASE WHEN p.status = "completed" THEN p.amount_cents ELSE 0 END), 0) AS completed_cents
  FROM payments p
  INNER JOIN papers pa ON pa.id = p.paper_id
  WHERE pa.teacher_id = ?
');
$summaryStmt->execute([$user['id']]);
$summary = $summaryStmt->fetch();

$totalCollected = ($summary['total_collected_cents'] ?? 0) / 100;
$totalPayments = (int)($summary['total_payments'] ?? 0);
$completedCollected = ($summary['completed_cents'] ?? 0) / 100;
$teacherShare = $completedCollected * 0.80;

$payoutStmt = $pdo->prepare('SELECT COALESCE(SUM(amount_cents), 0) AS total_paid_cents FROM payout_requests WHERE teacher_id = ? AND status IN ("approved", "completed")');
$payoutStmt->execute([$user['id']]);
$payoutStats = $payoutStmt->fetch();
$totalPaidOut = ($payoutStats['total_paid_cents'] ?? 0) / 100;

$availableBalance = $teacherShare - $totalPaidOut;

$paymentsStmt = $pdo->prepare('
    SELECT p.id, p.order_id, p.transaction_id, p.amount_cents, p.status, p.paid_at, p.created_at, pa.title
    FROM payments p
    INNER JOIN papers pa ON pa.id = p.paper_id
    WHERE pa.teacher_id = ?
    ORDER BY p.created_at DESC
    LIMIT 20
');
$paymentsStmt->execute([$user['id']]);
$payments = $paymentsStmt->fetchAll();

render_header('Payment Summary');
?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-cash-stack"></i></div>
    <div>
      <p class="stat-card-label">Gross Collected</p>
      <p class="stat-card-value">Rs. <?= number_format($totalCollected, 2) ?></p>
      <p class="stat-card-sub">All payments (any status)</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-pie-chart"></i></div>
    <div>
      <p class="stat-card-label">Your Share (80%)</p>
      <p class="stat-card-value">Rs. <?= number_format($teacherShare, 2) ?></p>
      <p class="stat-card-sub">Based on completed payments</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon yellow"><i class="bi bi-wallet2"></i></div>
    <div>
      <p class="stat-card-label">Available Balance</p>
      <p class="stat-card-value">Rs. <?= number_format($availableBalance, 2) ?></p>
      <p class="stat-card-sub">After payouts</p>
    </div>
  </div>
</div>

<div class="app-card">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-receipt text-primary"></i> Recent Payments</h2>
    <div class="d-flex align-items-center gap-2">
      <span class="badge-soft badge-soft-gray">Latest <?= min($totalPayments, 20) ?></span>
      <a href="<?= htmlspecialchars(app_href('teacher/payouts.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Payouts</a>
    </div>
  </div>
  <?php if (empty($payments)): ?>
    <div class="app-card-body">
      <div class="text-center py-4">
        <i class="bi bi-inbox" style="font-size:2rem;color:var(--muted-light);display:block;margin-bottom:10px;"></i>
        <p style="color:var(--on-surface-muted);">No payments recorded yet.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="app-card-body p-0">
      <div class="data-table table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Order</th>
              <th>Paper</th>
              <th>Status</th>
              <th>Amount</th>
              <th>Paid At</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p):
              $statusMap = ['completed' => 'success', 'pending' => 'warning', 'failed' => 'danger'];
              $badge = $statusMap[$p['status']] ?? 'gray';
            ?>
              <tr>
                <td><code style="font-size:12px;background:var(--surface-subtle);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($p['order_id']) ?></code></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><span class="badge-soft badge-soft-<?= $badge ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                <td><strong>Rs. <?= number_format($p['amount_cents'] / 100, 2) ?></strong></td>
                <td style="font-size:13px;color:var(--on-surface-muted);"><?= $p['paid_at'] ? date('Y-m-d H:i', strtotime($p['paid_at'])) : '<span style="color:var(--muted-light);">—</span>' ?></td>
                <td style="font-size:13px;color:var(--on-surface-muted);"><?= date('Y-m-d H:i', strtotime($p['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
