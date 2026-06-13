<?php
require_once __DIR__ . '/src/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "Setting foreign key checks to 0...\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

// Drop tables that reference the ones we need to fix
$tablesToDrop = ['responses', 'attempts', 'paper_access', 'payments'];
foreach ($tablesToDrop as $table) {
    echo "Dropping table: $table\n";
    $pdo->exec("DROP TABLE IF EXISTS `$table`");
}

echo "Setting foreign key checks to 1...\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

// Now recreate them with correct schema
$stmts = [
    'payments' => "CREATE TABLE payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        paper_id INT NOT NULL,
        order_id VARCHAR(100) NOT NULL,
        amount_cents INT NOT NULL,
        status ENUM('pending','completed','failed') DEFAULT 'pending',
        transaction_id VARCHAR(100) DEFAULT NULL,
        paid_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_payments_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_order (order_id),
        INDEX idx_user_paper (user_id, paper_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'paper_access' => "CREATE TABLE paper_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        paper_id INT NOT NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_access_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_access (user_id, paper_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'attempts' => "CREATE TABLE attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        paper_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        submitted_at TIMESTAMP NULL,
        score INT NULL,
        CONSTRAINT fk_attempts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_attempts_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'responses' => "CREATE TABLE responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        selected_option_id INT NULL,
        CONSTRAINT fk_responses_attempt FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
        CONSTRAINT fk_responses_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
        CONSTRAINT fk_responses_option FOREIGN KEY (selected_option_id) REFERENCES answer_options(id) ON DELETE SET NULL,
        UNIQUE KEY uniq_attempt_question (attempt_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($stmts as $name => $sql) {
    echo "Creating table: $name\n";
    $pdo->exec($sql);
}

echo "Schema fixed successfully!\n";
