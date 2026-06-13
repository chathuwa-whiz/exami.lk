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

// Aggregate payments for this teacher's papers
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

// Total paid out
$payoutStmt = $pdo->prepare('SELECT COALESCE(SUM(amount_cents), 0) AS total_paid_cents FROM payout_requests WHERE teacher_id = ? AND status IN ("approved", "completed")');
$payoutStmt->execute([$user['id']]);
$payoutStats = $payoutStmt->fetch();
$totalPaidOut = ($payoutStats['total_paid_cents'] ?? 0) / 100;

$availableBalance = $teacherShare - $totalPaidOut;

// Recent payments
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

<div class="mb-4">
  <a href="<?= htmlspecialchars(app_href('teacher/payouts.php')) ?>" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Back to Payouts
  </a>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="app-card p-4 shadow-sm" style="border-top: 4px solid #6759ff;">
      <p class="text-muted small mb-2">Gross Collected</p>
      <h2 class="mb-0" style="color: #6759ff; font-weight: 700;">Rs. <?= number_format($totalCollected, 2) ?></h2>
      <p class="text-muted small mt-2 mb-0">All payments (any status)</p>
    </div>
  </div>
  <div class="col-md-4">
    <div class="app-card p-4 shadow-sm" style="border-top: 4px solid #14b8a6;">
      <p class="text-muted small mb-2">Your Share (80%)</p>
      <h2 class="mb-0" style="color: #14b8a6; font-weight: 700;">Rs. <?= number_format($teacherShare, 2) ?></h2>
      <p class="text-muted small mt-2 mb-0">Based on completed payments</p>
    </div>
  </div>
  <div class="col-md-4">
    <div class="app-card p-4 shadow-sm" style="border-top: 4px solid #f59e0b;">
      <p class="text-muted small mb-2">Available Balance</p>
      <h2 class="mb-0" style="color: #f59e0b; font-weight: 700;">Rs. <?= number_format($availableBalance, 2) ?></h2>
      <p class="text-muted small mt-2 mb-0">After payouts</p>
    </div>
  </div>
</div>

<div class="app-card p-4 shadow-sm">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="h5 mb-0 d-flex align-items-center gap-2"><i class="bi bi-receipt text-primary"></i> Recent Payments</h3>
    <span class="text-muted small">Showing latest 20</span>
  </div>
  <?php if (empty($payments)): ?>
    <div class="alert alert-info mb-0">No payments recorded yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
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
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><code><?= htmlspecialchars($p['order_id']) ?></code></td>
              <td><?= htmlspecialchars($p['title']) ?></td>
              <td>
                <?php
                  $status = $p['status'];
                  $badge = [
                    'completed' => 'success',
                    'pending'   => 'warning',
                    'failed'    => 'danger',
                  ][$status] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $badge ?> text-capitalize"><?= htmlspecialchars($status) ?></span>
              </td>
              <td><strong>Rs. <?= number_format($p['amount_cents'] / 100, 2) ?></strong></td>
              <td><?= $p['paid_at'] ? date('Y-m-d H:i', strtotime($p['paid_at'])) : '-' ?></td>
              <td><?= date('Y-m-d H:i', strtotime($p['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
