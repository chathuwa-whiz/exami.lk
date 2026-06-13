<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = '';
$debug = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug[] = 'POST received';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $debug[] = "Email: $email";
    $debug[] = "Password length: " . strlen($password);
    
    if ($email && $password) {
        $debug[] = 'Email and password provided';
        $stmt = db()->prepare('SELECT id, password_hash, name, user_type FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        $debug[] = 'User found: ' . ($row ? 'YES' : 'NO');
        
        if ($row) {
            $debug[] = "User ID: {$row['id']}, Type: {$row['user_type']}";
            $verify_result = verify_password($password, $row['password_hash']);
            $debug[] = 'Password verified: ' . ($verify_result ? 'YES' : 'NO');
            
            if ($verify_result) {
                $_SESSION['user_id'] = $row['id'];
                
                // Redirect to appropriate page
                if ($row['user_type'] === 'student') {
                    header('Location: ./student/dashboard.php', true, 302);
                } else {
                    header('Location: ./', true, 302);
                }
                die();
            } else {
                $error = 'Invalid credentials';
            }
        } else {
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'Email and password required';
    }
}
render_auth_shell_start('Welcome back', 'Sign in to continue where you left off.');
?>
<style>
.auth-left { position: relative; }
.auth-left::before {
  content: '';
  position: absolute;
  inset: 0;
  background: url('<?= htmlspecialchars(app_href('assets/examihome.png')) ?>') center / cover no-repeat;
  opacity: .08;
  pointer-events: none;
}
.auth-left > * { position: relative; }
</style>
<?php if ($error): ?>
  <div class="alert alert-danger" role="alert" aria-live="assertive">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
<?php endif; ?>
<?php if (!empty($debug)): ?>
  <div class="alert alert-info">
    <strong>Debug:</strong><br><?= implode('<br>', array_map('htmlspecialchars', $debug)) ?>
  </div>
<?php endif; ?>

<form method="post" action="login.php" autocomplete="on">
  <div class="mb-4">
    <label for="email" class="form-label">Email address</label>
    <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" autocomplete="email" required>
  </div>
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <label for="password" class="form-label mb-0">Password</label>
    </div>
    <div class="input-with-toggle">
      <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
      <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show/hide password">
        <i class="bi bi-eye" id="pwToggleIcon"></i>
      </button>
    </div>
  </div>
  <div class="d-grid mb-3">
    <button type="submit" class="btn btn-primary" style="padding:11px;">
      <i class="bi bi-box-arrow-in-right"></i> Sign in
    </button>
  </div>
  <p class="text-center mb-0" style="font-size:13px;color:var(--muted);">
    Don't have an account?
    <a href="<?= htmlspecialchars(app_href('register.php')) ?>" class="fw-semibold">Create one</a>
  </p>
</form>

<script>
(function(){
  var btn = document.getElementById('pwToggle');
  var inp = document.getElementById('password');
  var ico = document.getElementById('pwToggleIcon');
  if (btn && inp && ico) {
    btn.addEventListener('click', function() {
      var shown = inp.type === 'text';
      inp.type = shown ? 'password' : 'text';
      ico.className = shown ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  }
})();
</script>
<?php render_auth_shell_end(); ?>