<?php
require_once __DIR__ . '/src/config.php';

echo "=== Testing Login Direct ===\n\n";

// Check user exists
$stmt = db()->prepare('SELECT id, email, name, user_type, password_hash FROM users WHERE email = ?');
$stmt->execute(['test@gmail.com']);
$row = $stmt->fetch();

if ($row) {
    echo "User found:\n";
    echo "- ID: {$row['id']}\n";
    echo "- Email: {$row['email']}\n";
    echo "- Name: {$row['name']}\n";
    echo "- Type: {$row['user_type']}\n";
    echo "- Hash: {$row['password_hash']}\n\n";
    
    // Test password
    $password = 'password123';
    $verified = verify_password($password, $row['password_hash']);
    echo "Password verification for 'password123': " . ($verified ? 'SUCCESS' : 'FAILED') . "\n";
} else {
    echo "User NOT found!\n";
}
