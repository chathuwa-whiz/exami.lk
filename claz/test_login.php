<?php
require 'src/config.php';
try {
    $pdo = db();
    // Test with the existing student user
    $email = 'test@gmail.com';
    $stmt = $pdo->prepare('SELECT id, password_hash, name, user_type FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    
    if ($row) {
        echo "User found:\n";
        print_r($row);
        echo "\n\nAttempting password verification...\n";
        // Try a test password
        $test_password = 'test123';
        if (function_exists('verify_password')) {
            $result = verify_password($test_password, $row['password_hash']);
            echo "Password 'test123' valid: " . ($result ? 'YES' : 'NO') . "\n";
        } else {
            echo "verify_password function not found\n";
        }
    } else {
        echo "User not found with email: $email\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
