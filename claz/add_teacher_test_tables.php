<?php
// Migration script to add teacher test tables
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    $errors = [];

    // Create teacher_test_attempts table
    $sql1 = "CREATE TABLE IF NOT EXISTS teacher_test_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        paper_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        submitted_at TIMESTAMP NULL,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
        INDEX idx_teacher_paper (teacher_id, paper_id)
    ) ENGINE=InnoDB";
    
    $pdo->exec($sql1);
    echo "✓ teacher_test_attempts table created/verified\n";

    // Create teacher_test_responses table
    $sql2 = "CREATE TABLE IF NOT EXISTS teacher_test_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        test_attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        selected_option_id INT NULL,
        FOREIGN KEY (test_attempt_id) REFERENCES teacher_test_attempts(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
        FOREIGN KEY (selected_option_id) REFERENCES answer_options(id) ON DELETE SET NULL,
        UNIQUE KEY uniq_test_attempt_question (test_attempt_id, question_id)
    ) ENGINE=InnoDB";
    
    $pdo->exec($sql2);
    echo "✓ teacher_test_responses table created/verified\n";
    
    echo "\n✓ All tables created successfully!\n";

} catch (PDOException $e) {
    echo "✗ Database Connection Error: " . $e->getMessage() . "\n";
    echo "Please ensure XAMPP/MySQL is running and try again.\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
