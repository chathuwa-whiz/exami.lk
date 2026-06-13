<?php
require_once __DIR__ . '/src/config.php';

echo "=== Creating Sample Teachers ===\n\n";

$teachers = [
    ['name' => 'Mr. Khan', 'email' => 'khan@school.com', 'password' => 'teacher123'],
    ['name' => 'Ms. Patel', 'email' => 'patel@school.com', 'password' => 'teacher123'],
    ['name' => 'Dr. Smith', 'email' => 'smith@school.com', 'password' => 'teacher123'],
    ['name' => 'Mrs. Johnson', 'email' => 'johnson@school.com', 'password' => 'teacher123'],
    ['name' => 'Mr. Lee', 'email' => 'lee@school.com', 'password' => 'teacher123'],
    ['name' => 'Ms. Garcia', 'email' => 'garcia@school.com', 'password' => 'teacher123'],
];

$stmt = db()->prepare("INSERT INTO users (name, email, password_hash, user_type) VALUES (?, ?, ?, 'teacher')");

foreach ($teachers as $teacher) {
    $hash = hash_password($teacher['password']);
    $stmt->execute([$teacher['name'], $teacher['email'], $hash]);
    echo "Created: {$teacher['name']} ({$teacher['email']})\n";
}

echo "\n=== Teachers Created ===\n\n";
$stmt = db()->query("SELECT id, name, email FROM users WHERE user_type = 'teacher' ORDER BY id");
$result = $stmt->fetchAll();

foreach ($result as $teacher) {
    echo "- ID: {$teacher['id']}, Name: {$teacher['name']}, Email: {$teacher['email']}\n";
}
