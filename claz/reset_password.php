<?php
require 'src/config.php';

// TEMPORARY: Set a test password for user ID 4 (Ravindu)
$test_password = 'password123';
$hashed = hash_password($test_password);

$pdo = db();
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([$hashed, 4]);

echo "Password updated for user ID 4 (test@gmail.com)\n";
echo "New password: $test_password\n";
echo "New hash: $hashed\n";
?>
