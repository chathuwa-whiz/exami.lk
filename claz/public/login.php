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
<div class="mb-4">
  <h1 class="h3 fw-semibold text-dark mb-1 d-flex align-items-center gap-2"><i class="bi bi-box-arrow-in-right text-primary"></i> Sign in</h1>
  <p class="text-muted mb-0">Access your classes, exam papers and results.</p>
</div>
  <?php if ($error): ?><div class="alert alert-danger d-flex align-items-center" role="alert" aria-live="assertive"><i class="bi bi-exclamation-triangle-fill"></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
  <?php if (!empty($debug)): ?><div class="alert alert-info"><strong>Debug:</strong><br><?= implode('<br>', array_map('htmlspecialchars', $debug)) ?></div><?php endif; ?>
  <form method="post" action="login.php">
    <div class="mb-3">
      <label for="email" class="form-label text-dark">Email</label>
      <input type="email" class="form-control" id="email" name="email" value="" required>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label text-dark">Password</label>
      <input type="password" class="form-control" id="password" name="password" value="" required>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2"><i class="bi bi-door-open"></i> Login</button>
      <a href="<?= htmlspecialchars(app_href('register.php')) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"><i class="bi bi-person-plus"></i> Register</a>
    </div>
  </form>
<?php render_auth_shell_end(); ?>