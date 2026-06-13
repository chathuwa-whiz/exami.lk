<?php
require_once __DIR__ . '/src/config.php';

echo "=== Teachers in Database ===\n\n";

$stmt = db()->query("SELECT id, name, email, user_type FROM users WHERE user_type = 'teacher' ORDER BY id");
$teachers = $stmt->fetchAll();

if ($teachers) {
    foreach ($teachers as $teacher) {
        echo "- ID: {$teacher['id']}, Name: {$teacher['name']}, Email: {$teacher['email']}\n";
    }
} else {
    echo "No teachers found!\n";
}

echo "\n=== All Users ===\n\n";
$stmt = db()->query("SELECT id, name, email, user_type FROM users ORDER BY id");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo "- ID: {$user['id']}, Name: {$user['name']}, Type: {$user['user_type']}, Email: {$user['email']}\n";
}
