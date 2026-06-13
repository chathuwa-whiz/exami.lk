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
    
    echo "✓ Subjects table created<br>";
    
    // Drop teacher_subject if it exists (to avoid FK constraint errors)
    $pdo->exec("DROP TABLE IF EXISTS teacher_subject");
    
        // Create teacher_subject table WITHOUT foreign keys to avoid FK issues
        $pdo->exec("CREATE TABLE teacher_subject (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            UNIQUE KEY uniq_teacher_subject (teacher_id, subject_id)
        ) ENGINE=InnoDB");
    
    echo "✓ Teacher-Subject table created<br>";
    
    // Insert sample subjects
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
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO subjects (name) VALUES (?)");
    foreach ($subjects as $subj) {
        $stmt->execute([$subj]);
    }
    
    echo "✓ Subjects populated successfully!<br>";
    
    // List all subjects
    $rows = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();
    echo "<h3>Available Subjects:</h3>";
    echo "<ul>";
    foreach ($rows as $row) {
        echo "<li>{$row['name']} (ID: {$row['id']})</li>";
    }
    echo "</ul>";
    
    echo "<p><strong>Setup complete!</strong> You can now register teachers and students.</p>";
    
} catch (Throwable $e) {
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
}
?>
