<?php
require_once '../../src/config.php';
require_once '../../src/db.php';
require_once '../../src/layout.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_href('login.php'));
    exit;
}

$pdo = get_db();
$user = $pdo->query("SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id'])->fetch();

if (!$user || $user['user_type'] !== 'student') {
    header('Location: ' . app_href('index.php'));
    exit;
}

$paperId = $_GET['paper_id'] ?? null;
if (!$paperId) {
    header('Location: ' . app_href('student/papers.php'));
    exit;
}

// Fetch paper details
$stmt = $pdo->prepare("SELECT * FROM papers WHERE id = ? AND is_published = 1");
$stmt->execute([$paperId]);
$paper = $stmt->fetch();

if (!$paper) {
    header('Location: ' . app_href('student/papers.php'));
    exit;
}

// Check if payment is completed
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE user_id = ? AND paper_id = ? AND status = 'completed'
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$user['id'], $paperId]);
$payment = $stmt->fetch();

$pageTitle = 'Payment ' . ($payment ? 'Successful' : 'Processing');
render_header($pageTitle, $user);
?>

<div class="container my-4">
<?php if ($payment): ?>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <i class="bi bi-check-circle-fill fs-3 me-3"></i>
    <div>
      <h4 class="alert-heading mb-1">Payment Successful!</h4>
      <p class="mb-0">Your payment has been confirmed and you now have access to the paper.</p>
    </div>
  </div>
  
  <section class="app-card p-4" style="max-width: 600px; margin: 0 auto;">
    <h2 class="h5 mb-3"><i class="bi bi-receipt text-success"></i> Payment Details</h2>
    
    <table class="table table-sm">
      <tbody>
        <tr>
          <th width="40%">Order ID:</th>
          <td><code><?= htmlspecialchars($payment['order_id']) ?></code></td>
        </tr>
        <tr>
          <th>Transaction ID:</th>
          <td><code><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></code></td>
        </tr>
        <tr>
          <th>Paper:</th>
          <td><?= htmlspecialchars($paper['title']) ?></td>
        </tr>
        <tr>
          <th>Amount:</th>
          <td><strong><?= CURRENCY_SYMBOL ?> <?= number_format($payment['amount_cents'] / 100, 2) ?></strong></td>
        </tr>
        <tr>
          <th>Payment Time:</th>
          <td><?= date('F j, Y g:i A', strtotime($payment['paid_at'])) ?></td>
        </tr>
        <tr>
          <th>Status:</th>
          <td><span class="badge bg-success">Completed</span></td>
        </tr>
      </tbody>
    </table>
    
    <div class="d-flex gap-2 flex-wrap mt-4">
      <a href="<?= htmlspecialchars(app_href('student/attempt.php?id=' . $paperId)) ?>" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-text"></i> Start Attempting Paper
      </a>
      <a href="<?= htmlspecialchars(app_href('student/papers.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Papers
      </a>
    </div>
  </section>
  
<?php else: ?>
  <div class="alert alert-warning d-flex align-items-center" role="alert">
    <i class="bi bi-hourglass-split fs-3 me-3"></i>
    <div>
      <h4 class="alert-heading mb-1">Payment Processing</h4>
      <p class="mb-0">Your payment is being processed. This usually takes a few moments.</p>
    </div>
  </div>
  
  <section class="app-card p-4 text-center" style="max-width: 600px; margin: 0 auto;">
    <div class="spinner-border text-primary mb-3" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <h2 class="h5 mb-2">Confirming Payment...</h2>
    <p class="text-muted mb-3">Please wait while we verify your payment with the bank.</p>
    <p class="small text-muted">This page will automatically refresh every 5 seconds.</p>
    
    <div class="mt-4">
      <a href="<?= htmlspecialchars(app_href('student/papers.php')) ?>" class="btn btn-outline-secondary">
        Return to Papers
      </a>
    </div>
  </section>
  
  <script>
    // Auto-refresh every 5 seconds to check payment status
    setTimeout(function() {
      window.location.reload();
    }, 5000);
  </script>
<?php endif; ?>
</div>

<?php render_footer(); ?>
