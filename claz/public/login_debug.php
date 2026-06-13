<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/layout.php';
$error = '';
$debug = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $debug .= "POST received. Email: $email\n";
    if ($email && $password) {
        $stmt = db()->prepare('SELECT id, password_hash, name, user_type FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        $debug .= "User found: " . ($row ? "YES (ID: {$row['id']}, Type: {$row['user_type']})" : "NO") . "\n";
        
        if ($row && verify_password($password, $row['password_hash'])) {
            $debug .= "Password verified: YES\n";
            $_SESSION['user_id'] = $row['id'];
            $debug .= "Session ID set to: " . $_SESSION['user_id'] . "\n";
            
            // Route based on user type
            $redirect = ($row['user_type'] === 'student') 
                ? '/claz/public/student/dashboard.php' 
                : '/claz/public/';
            $debug .= "Redirecting to: $redirect\n";
            
            header("Location: $redirect");
            exit;
        } else {
            $debug .= "Password verified: NO\n";
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'Email and password required';
    }
}
render_auth_shell_start('Welcome back', 'Sign in to continue where you left off.');
?>
<div class="mb-4">
  <h1 class="h3 fw-semibold text-dark mb-1 d-flex align-items-center gap-2"><i class="bi bi-box-arrow-in-right text-primary"></i> Sign in</h1>
  <p class="text-muted mb-0">Access your classes, exam papers and results.</p>
</div>
  <?php if ($error): ?><div class="alert alert-danger d-flex align-items-center" role="alert" aria-live="assertive"><i class="bi bi-exclamation-triangle-fill"></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
  <?php if ($debug): ?><div class="alert alert-info" style="white-space: pre-wrap; font-size: 12px;"><?= htmlspecialchars($debug) ?></div><?php endif; ?>
  <form method="post" class="needs-validation" novalidate id="login_form">
    <div class="mb-3">
      <label for="email" class="form-label text-dark">Email</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
        <div class="invalid-feedback">Email required.</div>
      </div>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label text-dark">Password</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
        <div class="invalid-feedback">Password required.</div>
      </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2"><i class="bi bi-door-open"></i> Login</button>
      <a href="<?= htmlspecialchars(app_href('register.php')) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"><i class="bi bi-person-plus"></i> Register</a>
    </div>
  </form>
<script>
(function(){
  const form = document.getElementById('login_form');
  if(!form) return;
  form.addEventListener('submit', (e) => {
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
      form.classList.add('was-validated');
    }
  });
})();
</script>
<?php render_auth_shell_end(); ?>
