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
$error = '';
$success = '';

$checkStmt = $pdo->prepare('SELECT id, amount_cents, status, requested_at FROM payout_requests WHERE teacher_id = ? AND status = "pending" LIMIT 1');
$checkStmt->execute([$user['id']]);
$pendingRequest = $checkStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pendingRequest) {
    $amount = floatval($_POST['amount'] ?? 0);
    $bankDetails = trim($_POST['bank_details'] ?? '');

    if ($amount < 1000) {
        $error = 'Minimum payout request is 1000 LKR.';
    } elseif (empty($bankDetails)) {
        $error = 'Please provide your bank account details.';
    } else {
        $paymentStmt = $pdo->prepare('
            SELECT COALESCE(SUM(p.amount_cents), 0) AS total_collected_cents
            FROM payments p
            INNER JOIN papers pa ON pa.id = p.paper_id
            WHERE pa.teacher_id = ? AND p.status = "completed"
        ');
        $paymentStmt->execute([$user['id']]);
        $paymentStats = $paymentStmt->fetch();
        $totalCollected = ($paymentStats['total_collected_cents'] ?? 0) / 100;
        $availableBalance = $totalCollected * 0.80;

        $payoutStmt = $pdo->prepare('SELECT COALESCE(SUM(amount_cents), 0) AS total_paid_cents FROM payout_requests WHERE teacher_id = ? AND status IN ("approved", "completed")');
        $payoutStmt->execute([$user['id']]);
        $payoutStats = $payoutStmt->fetch();
        $totalPaidOut = ($payoutStats['total_paid_cents'] ?? 0) / 100;

        $remainingBalance = $availableBalance - $totalPaidOut;

        if ($amount > $remainingBalance) {
            $error = 'Insufficient balance. Available: ' . number_format($remainingBalance, 2) . ' LKR';
        } else {
            try {
                $insertStmt = $pdo->prepare('INSERT INTO payout_requests (teacher_id, amount_cents, bank_details, status) VALUES (?, ?, ?, "pending")');
                $insertStmt->execute([$user['id'], (int)($amount * 100), $bankDetails]);
                $success = 'Payout request submitted. Payment will be credited within 3 working days after approval.';
                $checkStmt->execute([$user['id']]);
                $pendingRequest = $checkStmt->fetch();
            } catch (Exception $e) {
                $error = 'Failed to submit payout request: ' . $e->getMessage();
            }
        }
    }
}

$historyStmt = $pdo->prepare('SELECT id, amount_cents, status, requested_at, processed_at, admin_notes FROM payout_requests WHERE teacher_id = ? ORDER BY requested_at DESC LIMIT 10');
$historyStmt->execute([$user['id']]);
$payoutHistory = $historyStmt->fetchAll();

$paymentStmt = $pdo->prepare('
    SELECT COALESCE(SUM(p.amount_cents), 0) AS total_collected_cents
    FROM payments p
    INNER JOIN papers pa ON pa.id = p.paper_id
    WHERE pa.teacher_id = ? AND p.status = "completed"
');
$paymentStmt->execute([$user['id']]);
$paymentStats = $paymentStmt->fetch();
$totalCollected = ($paymentStats['total_collected_cents'] ?? 0) / 100;
$teacherShare = $totalCollected * 0.80;

$payoutStmt = $pdo->prepare('SELECT COALESCE(SUM(amount_cents), 0) AS total_paid_cents FROM payout_requests WHERE teacher_id = ? AND status IN ("approved", "completed")');
$payoutStmt->execute([$user['id']]);
$payoutStats = $payoutStmt->fetch();
$totalPaidOut = ($payoutStats['total_paid_cents'] ?? 0) / 100;

$availableBalance = $teacherShare - $totalPaidOut;

render_header('Payout Requests');
?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-wallet2"></i></div>
    <div>
      <p class="stat-card-label">Available Balance</p>
      <p class="stat-card-value"><?= number_format($availableBalance, 2) ?></p>
      <p class="stat-card-sub">LKR · Min withdrawal 1,000</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-cash-stack"></i></div>
    <div>
      <p class="stat-card-label">Total Earned</p>
      <p class="stat-card-value"><?= number_format($teacherShare, 2) ?></p>
      <p class="stat-card-sub">LKR · 80% revenue share</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-arrow-up-circle"></i></div>
    <div>
      <p class="stat-card-label">Total Withdrawn</p>
      <p class="stat-card-value"><?= number_format($totalPaidOut, 2) ?></p>
      <p class="stat-card-sub">LKR · <?= count($payoutHistory) ?> request<?= count($payoutHistory) !== 1 ? 's' : '' ?></p>
    </div>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success d-flex align-items-center gap-2" role="status"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($pendingRequest): ?>
<div class="app-card mb-4" style="border-left:4px solid var(--warning);">
  <div class="app-card-body">
    <div class="d-flex align-items-start gap-3">
      <div class="stat-card-icon yellow flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <p class="fw-semibold mb-1" style="color:var(--on-surface);">Pending Payout Request</p>
        <p class="mb-1 small">Amount: <strong><?= number_format($pendingRequest['amount_cents'] / 100, 2) ?> LKR</strong></p>
        <p class="mb-1 small" style="color:var(--on-surface-muted);">Requested on <?= date('M d, Y', strtotime($pendingRequest['requested_at'])) ?></p>
        <p class="mb-0 small" style="color:var(--on-surface-muted);"><i class="bi bi-info-circle me-1"></i>Payment credited within 3 working days after approval.</p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!$pendingRequest && $availableBalance >= 1000): ?>
<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-send text-success"></i> Request Payout</h2>
  </div>
  <div class="app-card-body">
    <form method="post" class="needs-validation row g-3" novalidate>
      <div class="col-md-6">
        <label for="amount" class="form-label">Amount (LKR)</label>
        <input type="number" class="form-control" id="amount" name="amount"
               min="1000" max="<?= $availableBalance ?>" step="0.01" required>
        <div class="form-text">Minimum 1,000 LKR &mdash; Available: <?= number_format($availableBalance, 2) ?> LKR</div>
      </div>
      <div class="col-12">
        <label for="bank_details" class="form-label">Bank Account Details</label>
        <textarea class="form-control" id="bank_details" name="bank_details" rows="4" required
                  placeholder="Bank Name:&#10;Account Number:&#10;Account Holder Name:&#10;Branch:"></textarea>
        <div class="form-text">Provide complete bank details for transfer</div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-2">
          <i class="bi bi-send-fill"></i> Submit Request
        </button>
      </div>
    </form>
  </div>
</div>
<?php elseif (!$pendingRequest): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
  <i class="bi bi-exclamation-circle-fill"></i>
  <span>You need at least 1,000 LKR to request a payout. Current balance: <strong><?= number_format($availableBalance, 2) ?> LKR</strong></span>
</div>
<?php endif; ?>

<?php if (!empty($payoutHistory)): ?>
<div class="app-card">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-clock-history text-primary"></i> Payout History</h2>
    <a href="<?= htmlspecialchars(app_href('teacher/payment_summary.php')) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt"></i> Payment Summary</a>
  </div>
  <div class="app-card-body p-0">
    <div class="data-table table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Date Requested</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Processed</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payoutHistory as $req):
            $badges = ['pending' => 'warning', 'approved' => 'info', 'completed' => 'success', 'rejected' => 'danger'];
            $badgeClass = $badges[$req['status']] ?? 'gray';
          ?>
          <tr>
            <td><?= date('M d, Y', strtotime($req['requested_at'])) ?></td>
            <td><strong><?= number_format($req['amount_cents'] / 100, 2) ?> LKR</strong></td>
            <td><span class="badge-soft badge-soft-<?= $badgeClass ?>"><?= ucfirst($req['status']) ?></span></td>
            <td><?= $req['processed_at'] ? date('M d, Y', strtotime($req['processed_at'])) : '<span style="color:var(--muted-light);">—</span>' ?></td>
            <td style="font-size:13px;color:var(--on-surface-muted);">
              <?php if ($req['status'] === 'completed'): ?>
                <span style="color:var(--success);"><i class="bi bi-check-circle-fill me-1"></i>Credited</span>
              <?php elseif ($req['admin_notes']): ?>
                <?= htmlspecialchars($req['admin_notes']) ?>
              <?php else: ?>
                <span style="color:var(--muted-light);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function(){
  const form = document.querySelector('form.needs-validation');
  if (!form) return;
  form.addEventListener('submit', function(e){
    if (!form.checkValidity()){ e.preventDefault(); e.stopPropagation(); }
    form.classList.add('was-validated');
  }, false);
})();
</script>

<?php render_footer(); ?>
