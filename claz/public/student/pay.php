<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'student') { http_response_code(403); echo 'Forbidden'; exit; }

$paperId = (int)($_GET['paper_id'] ?? $_POST['paper_id'] ?? 0);
if (!$paperId) { echo 'Missing paper'; exit; }

$pdo = db();
$stmt = $pdo->prepare('SELECT id, title, fee_cents FROM papers WHERE id=?');
$stmt->execute([$paperId]);
$paper = $stmt->fetch();
if (!$paper) { echo 'Paper not found'; exit; }
if ((int)$paper['fee_cents'] === 0) { 
  header('Location: ' . app_href('student/attempt.php?paper_id=' . $paperId));
  exit;
}

$errors = [];
$success = '';

// Check if already paid
$paymentStmt = $pdo->prepare('SELECT 1 FROM payments WHERE user_id=? AND paper_id=? AND status="completed"');
$paymentStmt->execute([$user['id'], $paperId]);
if ($paymentStmt->fetch()) {
  header('Location: ' . app_href('student/attempt.php?paper_id=' . $paperId));
  exit;
}

// Create pending payment record
$orderId = 'PAP' . $paperId . 'U' . $user['id'] . 'T' . time();
$checkExisting = $pdo->prepare('SELECT id FROM payments WHERE user_id=? AND paper_id=? AND status="pending"');
$checkExisting->execute([$user['id'], $paperId]);
if (!$checkExisting->fetch()) {
  $insertPayment = $pdo->prepare('INSERT INTO payments (user_id, paper_id, order_id, amount_cents, status) VALUES (?, ?, ?, ?, "pending")');
  $insertPayment->execute([$user['id'], $paperId, $orderId, $paper['fee_cents']]);
}

$amountLKR = $paper['fee_cents'] / 100;
$merchantId = PAYHERE_MERCHANT_ID;
$merchantSecret = PAYHERE_MERCHANT_SECRET;

// Generate PayHere hash
$hashedSecret = strtoupper(md5($merchantSecret));
$amountFormatted = number_format($amountLKR, 2, '.', '');
$hash = strtoupper(md5($merchantId . $orderId . $amountFormatted . 'LKR' . $hashedSecret));

$notifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . app_href('api/payhere_notify.php');
$returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . app_href('student/payment_success.php?paper_id=' . $paperId);
$cancelUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . app_href('student/pay.php?paper_id=' . $paperId . '&cancel=1');

render_header('Payment Required');
?>
<section class="mb-4">
  <div class="row g-3">
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Paper</p>
        <h3 class="mb-0"><?= htmlspecialchars($paper['title']) ?></h3>
      </div>
    </div>
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Amount Due</p>
        <h3 class="mb-0"><?= CURRENCY_SYMBOL ?> <?= number_format($amountLKR, 2) ?></h3>
        <p class="muted small mb-0"><?= CURRENCY ?></p>
      </div>
    </div>
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Payment Gateway</p>
        <h3 class="mb-0">PayHere</h3>
        <p class="muted small mb-0">Secure Sri Lankan payment</p>
      </div>
    </div>
  </div>
</section>

<?php if (isset($_GET['cancel'])): ?>
  <div class="alert alert-warning mb-4">Payment was cancelled. Try again when ready.</div>
<?php endif; ?>

<section class="app-card p-4" style="max-width: 600px; margin: 0 auto;">
  <h2 class="h5 mb-3">Secure Payment via PayHere</h2>
  
  <div class="alert alert-info mb-3">
    <i class="bi bi-info-circle-fill"></i> You will be redirected to PayHere's secure payment page. You can pay using:
    <ul class="mb-0 mt-2">
      <li>Credit/Debit Cards (Visa, MasterCard, Amex)</li>
      <li>eZ Cash, mCash</li>
      <li>Internet Banking</li>
    </ul>
  </div>

  <form method="post" action="https://<?= PAYHERE_MODE === 'live' ? 'www' : 'sandbox' ?>.payhere.lk/pay/checkout" id="payhereForm">
    <input type="hidden" name="merchant_id" value="<?= htmlspecialchars($merchantId) ?>">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">
    <input type="hidden" name="cancel_url" value="<?= htmlspecialchars($cancelUrl) ?>">
    <input type="hidden" name="notify_url" value="<?= htmlspecialchars($notifyUrl) ?>">
    
    <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">
    <input type="hidden" name="items" value="<?= htmlspecialchars($paper['title']) ?>">
    <input type="hidden" name="currency" value="LKR">
    <input type="hidden" name="amount" value="<?= htmlspecialchars($amountFormatted) ?>">
    
    <input type="hidden" name="first_name" value="<?= htmlspecialchars($user['name']) ?>">
    <input type="hidden" name="last_name" value="">
    <input type="hidden" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
    <input type="hidden" name="phone" value="">
    <input type="hidden" name="address" value="">
    <input type="hidden" name="city" value="">
    <input type="hidden" name="country" value="Sri Lanka">
    
    <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">
    
    <input type="hidden" name="custom_1" value="<?= $user['id'] ?>">
    <input type="hidden" name="custom_2" value="<?= $paperId ?>">
    
    <div class="alert alert-info small mb-3">
      <i class="bi bi-shield-check"></i> Your payment is secure. Access granted only after bank confirmation.
    </div>
    
    <div class="d-flex gap-2 flex-wrap">
      <button type="submit" class="btn btn-primary btn-lg d-inline-flex align-items-center gap-2">
        <i class="bi bi-credit-card"></i> Proceed to Payment (<?= CURRENCY_SYMBOL ?> <?= number_format($amountLKR, 2) ?>)
      </button>
      <a href="<?= htmlspecialchars(app_href('student/papers.php')) ?>" class="btn btn-outline-secondary">Back to Papers</a>
    </div>
  </form>
</section>

<section class="app-card p-4 mt-3" style="max-width: 600px; margin: 0 auto;">
  <h3 class="h6 mb-2"><i class="bi bi-info-circle text-primary"></i> Payment Process</h3>
  <p class="small text-muted mb-0">Click the button above to proceed to PayHere's secure payment page. You'll be able to pay with your preferred method, and access to the paper will be granted automatically after successful payment.</p>
</section>
