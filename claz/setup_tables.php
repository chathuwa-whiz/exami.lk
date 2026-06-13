<?php
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    
    // Create subjects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(100) NOT NULL UNIQUE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    
    // Create teacher_subject table
    $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_subject (
      id INT AUTO_INCREMENT PRIMARY KEY,
      teacher_id INT NOT NULL,
      subject_id INT NOT NULL,
      UNIQUE KEY uniq_teacher_subject (teacher_id, subject_id),
      FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    
    // Insert sample subjects
    $stmt = $pdo->prepare("INSERT IGNORE INTO subjects (name) VALUES (?)");
    $subjects = [
        'Mathematics',
        'English',
        'Science',
        'History',
        'Chemistry',
        'Physics',
        'Biology',
        'Geography'
    ];
    
    foreach ($subjects as $subj) {
        $stmt->execute([$subj]);
    }
    
    echo "✓ Subjects table created and populated successfully!<br>";
    echo "✓ Teacher-Subject table created successfully!<br>";
    
    // List all subjects
    $rows = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();
    echo "<h3>Available Subjects:</h3>";
    echo "<ul>";
    foreach ($rows as $row) {
        echo "<li>{$row['name']} (ID: {$row['id']})</li>";
    }
    echo "</ul>";
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
