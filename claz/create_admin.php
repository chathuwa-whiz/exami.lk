<?php
require_once __DIR__ . '/src/config.php';

// Create admin user
$email = 'admin@ceylonstudyhub.lk';
$password = 'Admin@123456'; // Change this to a secure password
$name = 'Administrator';

$pdo = db();

// Check if admin already exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo "Admin account already exists with email: $email\n";
    exit(1);
}

// Hash password and insert
$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, user_type) VALUES (?, ?, ?, ?)');

try {
    $stmt->execute([$email, $passwordHash, $name, 'admin']);
    echo "✓ Admin account created successfully!\n";
    echo "Email: $email\n";
    echo "Password: $password\n\n";
    echo "You can now login at: http://localhost/claz/public/login.php\n";
} catch (Exception $e) {
    echo "✗ Error creating admin account: " . $e->getMessage() . "\n";
    exit(1);
}
?>
