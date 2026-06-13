<?php
// Reset and recreate the schema for ceylonstudyhub (drops tables, then recreates and seeds subjects)
// WARNING: This will remove all existing data in these tables.
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $dropOrder = [
        'rate_limits','refresh_tokens','audit_logs','preapproved_student_teachers','preapproved_students',
        'responses','attempts','paper_access','payments','answer_options','questions','papers',
        'teacher_student','teacher_subject','subjects','users'
    ];
    foreach ($dropOrder as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $stmts = [
        "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_type ENUM('teacher','student','admin') NOT NULL,
            student_id VARCHAR(20) NULL UNIQUE,
            teacher_code VARCHAR(20) NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_users_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE teacher_subject (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            UNIQUE KEY uniq_teacher_subject (teacher_id, subject_id),
            CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE teacher_student (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            student_id INT NOT NULL,
            UNIQUE KEY uniq_teacher_student (teacher_id, student_id),
            CONSTRAINT fk_tst_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_tst_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE papers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NULL,
            fee_cents INT DEFAULT 0,
            time_limit_seconds INT NOT NULL,
            is_published TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_papers_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            paper_id INT NOT NULL,
            question_text TEXT NOT NULL,
            marks INT NOT NULL DEFAULT 1,
            position INT NOT NULL,
            CONSTRAINT fk_questions_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE answer_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            option_text TEXT NOT NULL,
            is_correct TINYINT(1) DEFAULT 0,
            CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            paper_id INT NOT NULL,
            amount_cents INT NOT NULL,
            status ENUM('pending','completed','failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_payments_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_payment (student_id, paper_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE paper_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            paper_id INT NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_access_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_access_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_access (student_id, paper_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            paper_id INT NOT NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL,
            score INT NULL,
            CONSTRAINT fk_attempts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_attempts_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id INT NOT NULL,
            question_id INT NOT NULL,
            selected_option_id INT NULL,
            CONSTRAINT fk_responses_attempt FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
            CONSTRAINT fk_responses_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
            CONSTRAINT fk_responses_option FOREIGN KEY (selected_option_id) REFERENCES answer_options(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_attempt_question (attempt_id, question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE preapproved_students (
            student_id VARCHAR(20) PRIMARY KEY,
            name VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_deleted TINYINT(1) DEFAULT 0,
            INDEX idx_pre_students_created (created_at),
            INDEX idx_pre_students_name (name),
            INDEX idx_pre_students_deleted_created (is_deleted, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE preapproved_student_teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            teacher_id INT NOT NULL,
            CONSTRAINT fk_pst_student FOREIGN KEY (student_id) REFERENCES preapproved_students(student_id) ON DELETE CASCADE,
            CONSTRAINT fk_pst_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_pre_map (student_id, teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_logs_user_created (user_id, created_at),
            INDEX idx_logs_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE refresh_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(200) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            revoked TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_refresh_token (token),
            INDEX idx_refresh_user_exp (user_id, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rl_key VARCHAR(200) NOT NULL,
            window_start INT NOT NULL,
            count INT NOT NULL,
            INDEX idx_rl_key_window (rl_key, window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($stmts as $sql) { $pdo->exec($sql); }

    // Seed subjects
    $subjects = ['Mathematics','English','Science','History','Chemistry','Physics','Biology','Geography'];
    $stmt = $pdo->prepare('INSERT IGNORE INTO subjects (name) VALUES (?)');
    foreach ($subjects as $subj) { $stmt->execute([$subj]); }

    echo "✓ Schema reset and recreated successfully.\n";
    echo "✓ Subjects seeded.\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
