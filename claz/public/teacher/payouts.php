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

// Check if teacher has pending request
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
        // Get teacher's available balance
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
        
        // Check previous payouts
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
                $success = 'Payout request submitted successfully. Your payment will be credited to your bank account within 3 working days after approval.';
                
                // Refresh pending request
                $checkStmt->execute([$user['id']]);
                $pendingRequest = $checkStmt->fetch();
            } catch (Exception $e) {
                $error = 'Failed to submit payout request: ' . $e->getMessage();
            }
        }
    }
}

// Fetch teacher's payout history
$historyStmt = $pdo->prepare('SELECT id, amount_cents, status, requested_at, processed_at, admin_notes FROM payout_requests WHERE teacher_id = ? ORDER BY requested_at DESC LIMIT 10');
$historyStmt->execute([$user['id']]);
$payoutHistory = $historyStmt->fetchAll();

// Get available balance
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

<div class="mb-4">
    <a href="<?= htmlspecialchars(app_href('teacher/profile.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Profile
    </a>
</div>

<!-- Balance Card -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="app-card p-4 shadow-sm" style="border-top: 4px solid #43e97b;">
            <p class="text-muted small mb-2">Available Balance</p>
            <h2 class="mb-0" style="color: #43e97b; font-weight: 700;"><?= number_format($availableBalance, 2) ?> LKR</h2>
            <p class="text-muted small mt-2 mb-0">Minimum withdrawal: 1000 LKR</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="app-card p-4 shadow-sm" style="border-top: 4px solid #667eea;">
            <p class="text-muted small mb-2">Total Earned</p>
            <h2 class="mb-0" style="color: #667eea; font-weight: 700;"><?= number_format($teacherShare, 2) ?> LKR</h2>
            <p class="text-muted small mt-2 mb-0">80% revenue share</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="app-card p-4 shadow-sm" style="border-top: 4px solid #f093fb;">
            <p class="text-muted small mb-2">Total Withdrawn</p>
            <h2 class="mb-0" style="color: #f093fb; font-weight: 700;"><?= number_format($totalPaidOut, 2) ?> LKR</h2>
            <p class="text-muted small mt-2 mb-0"><?= count($payoutHistory) ?> request<?= count($payoutHistory) !== 1 ? 's' : '' ?></p>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-check-circle-fill"></i>
    <span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>

<?php if ($pendingRequest): ?>
<div class="alert alert-info d-flex align-items-start gap-3 mb-4" style="border-left: 4px solid #38f9d7;">
    <i class="bi bi-hourglass-split" style="font-size: 1.5rem;"></i>
    <div class="flex-grow-1">
        <strong>Pending Payout Request</strong>
        <p class="mb-1 mt-2">Amount: <strong><?= number_format($pendingRequest['amount_cents'] / 100, 2) ?> LKR</strong></p>
        <p class="mb-0 text-muted small">Requested on <?= date('M d, Y', strtotime($pendingRequest['requested_at'])) ?></p>
        <p class="mb-0 mt-2 small"><i class="bi bi-info-circle"></i> Your payment will be credited to your bank account within 3 working days after approval.</p>
    </div>
</div>
<?php endif; ?>

<!-- Request Payout Form -->
<?php if (!$pendingRequest && $availableBalance >= 1000): ?>
<div class="app-card p-4 shadow-sm mb-4">
    <h3 class="h5 mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-cash-stack text-success"></i> Request Payout
    </h3>
    <form method="post" class="needs-validation" novalidate>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="amount" class="form-label">Amount (LKR)</label>
                <input type="number" class="form-control" id="amount" name="amount" 
                       min="1000" max="<?= $availableBalance ?>" step="0.01" required>
                <div class="form-text">Minimum: 1000 LKR, Available: <?= number_format($availableBalance, 2) ?> LKR</div>
            </div>
            <div class="col-12">
                <label for="bank_details" class="form-label">Bank Account Details</label>
                <textarea class="form-control" id="bank_details" name="bank_details" rows="4" required 
                          placeholder="Bank Name:&#10;Account Number:&#10;Account Holder Name:&#10;Branch:"></textarea>
                <div class="form-text">Provide complete bank details for transfer</div>
            </div>
        </div>
        <button type="submit" class="btn btn-success mt-3">
            <i class="bi bi-send-fill"></i> Submit Payout Request
        </button>
    </form>
</div>
<?php elseif (!$pendingRequest): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-exclamation-circle-fill"></i>
    <span>You need at least 1000 LKR to request a payout. Current balance: <?= number_format($availableBalance, 2) ?> LKR</span>
</div>
<?php endif; ?>

<!-- Payout History -->
<?php if (!empty($payoutHistory)): ?>
<div class="app-card p-4 shadow-sm">
    <h3 class="h5 mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-primary"></i> Payout History
    </h3>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Processed Date</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payoutHistory as $req): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($req['requested_at'])) ?></td>
                    <td><strong><?= number_format($req['amount_cents'] / 100, 2) ?> LKR</strong></td>
                    <td>
                        <?php
                        $badges = [
                            'pending' => 'warning',
                            'approved' => 'info',
                            'completed' => 'success',
                            'rejected' => 'danger'
                        ];
                        $badgeClass = $badges[$req['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $badgeClass ?>">
                            <?= ucfirst($req['status']) ?>
                        </span>
                    </td>
                    <td><?= $req['processed_at'] ? date('M d, Y', strtotime($req['processed_at'])) : '-' ?></td>
                    <td>
                        <?php if ($req['status'] === 'completed'): ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill"></i> Payment credited to your account</span>
                        <?php elseif ($req['admin_notes']): ?>
                            <?= htmlspecialchars($req['admin_notes']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    const form = document.querySelector('form.needs-validation');
    if(!form) return;
    form.addEventListener('submit', function(e){
        if(!form.checkValidity()){
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
})();
</script>

<?php render_footer(); ?>
