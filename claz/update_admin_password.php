<?php
require_once __DIR__ . '/src/config.php';

$email = 'admin@exami.lk';
$newPassword = 'NewStrongPass1!';
$passwordHash = '$2y$10$n8Hu19Tk..G4LfC1tQq2CeDrD7d6uyhVHGh6CEqOa0fY0p2xDvOem';

$pdo = db();

// Update admin password
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ? AND user_type = "admin"');
$stmt->execute([$passwordHash, $email]);

if ($stmt->rowCount() > 0) {
    echo "✓ Admin password updated successfully!\n";
    echo "Email: $email\n";
    echo "New Password: $newPassword\n\n";
    echo "You can now login at: http://localhost/claz/public/login.php\n";
} else {
    echo "✗ Admin account not found. Make sure the admin account exists.\n";
    exit(1);
}
?>
