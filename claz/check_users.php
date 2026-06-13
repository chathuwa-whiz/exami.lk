<?php
require 'src/config.php';
$pdo = db();

// Check all users
$users = $pdo->query('SELECT id, name, email, user_type FROM users')->fetchAll();
echo "=== All Users ===\n";
foreach ($users as $u) {
    echo "ID: {$u['id']}, Name: {$u['name']}, Email: {$u['email']}, Type: {$u['user_type']}\n";
}

// Test login with a known email
echo "\n=== Test Login ===\n";
$test_email = 'test@gmail.com';
$test_password = 'test123'; // Change this to what you used

$stmt = $pdo->prepare('SELECT id, password_hash, name, user_type FROM users WHERE email = ?');
$stmt->execute([$test_email]);
$row = $stmt->fetch();

if ($row) {
    echo "User found: {$row['name']} ({$row['email']})\n";
    echo "Password hash: {$row['password_hash']}\n";
    
    // Try different passwords
    $passwords_to_try = ['test123', 'password', 'test', ''];
    foreach ($passwords_to_try as $pwd) {
        $result = verify_password($pwd, $row['password_hash']);
        echo "Password '$pwd': " . ($result ? 'MATCH' : 'no match') . "\n";
    }
} else {
    echo "User not found with email: $test_email\n";
}
?>
