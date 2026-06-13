<?php
// One-shot setup: create database (if missing) and required tables
require_once __DIR__ . '/src/config.php';

function pdo_root(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function pdo_db(): PDO {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function ensure_database(PDO $pdo): void {
    $db = DB_NAME;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function ensure_tables(PDO $pdo): void {
    $stmts = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
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

        'subjects' => "CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'teacher_subject' => "CREATE TABLE IF NOT EXISTS teacher_subject (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            UNIQUE KEY uniq_teacher_subject (teacher_id, subject_id),
            CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'teacher_student' => "CREATE TABLE IF NOT EXISTS teacher_student (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            student_id INT NOT NULL,
            UNIQUE KEY uniq_teacher_student (teacher_id, student_id),
            CONSTRAINT fk_tst_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_tst_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'messages' => "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            student_id INT NOT NULL,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            body TEXT NOT NULL,
            attachment_type ENUM('image','pdf','audio','video') NULL,
            attachment_path VARCHAR(500) NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_msg_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_msg_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_msg_pair_created (teacher_id, student_id, created_at),
            INDEX idx_msg_receiver (receiver_id, is_read, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'papers' => "CREATE TABLE IF NOT EXISTS papers (
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

        'questions' => "CREATE TABLE IF NOT EXISTS questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            paper_id INT NOT NULL,
            question_text TEXT NOT NULL,
            marks INT NOT NULL DEFAULT 1,
            position INT NOT NULL,
            CONSTRAINT fk_questions_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'answer_options' => "CREATE TABLE IF NOT EXISTS answer_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            option_text TEXT NOT NULL,
            is_correct TINYINT(1) DEFAULT 0,
            CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'payments' => "CREATE TABLE IF NOT EXISTS payments (
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

        'paper_access' => "CREATE TABLE IF NOT EXISTS paper_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            paper_id INT NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_access_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_access (user_id, paper_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'attempts' => "CREATE TABLE IF NOT EXISTS attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            paper_id INT NOT NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL,
            score INT NULL,
            CONSTRAINT fk_attempts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_attempts_paper FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'responses' => "CREATE TABLE IF NOT EXISTS responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attempt_id INT NOT NULL,
            question_id INT NOT NULL,
            selected_option_id INT NULL,
            CONSTRAINT fk_responses_attempt FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
            CONSTRAINT fk_responses_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
            CONSTRAINT fk_responses_option FOREIGN KEY (selected_option_id) REFERENCES answer_options(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_attempt_question (attempt_id, question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'preapproved_students' => "CREATE TABLE IF NOT EXISTS preapproved_students (
            student_id VARCHAR(20) PRIMARY KEY,
            name VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_deleted TINYINT(1) DEFAULT 0,
            INDEX idx_pre_students_created (created_at),
            INDEX idx_pre_students_name (name),
            INDEX idx_pre_students_deleted_created (is_deleted, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'preapproved_student_teachers' => "CREATE TABLE IF NOT EXISTS preapproved_student_teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            teacher_id INT NOT NULL,
            CONSTRAINT fk_pst_student FOREIGN KEY (student_id) REFERENCES preapproved_students(student_id) ON DELETE CASCADE,
            CONSTRAINT fk_pst_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_pre_map (student_id, teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'audit_logs' => "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_logs_user_created (user_id, created_at),
            INDEX idx_logs_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'refresh_tokens' => "CREATE TABLE IF NOT EXISTS refresh_tokens (
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

        'rate_limits' => "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rl_key VARCHAR(200) NOT NULL,
            window_start INT NOT NULL,
            count INT NOT NULL,
            INDEX idx_rl_key_window (rl_key, window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($stmts as $name => $sql) {
        $pdo->exec($sql);
    }
}

function seed_subjects(PDO $pdo): void {
    $subjects = [
        'Mathematics','English','Science','History','Chemistry','Physics','Biology','Geography'
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO subjects (name) VALUES (?)');
    foreach ($subjects as $subj) {
        $stmt->execute([$subj]);
    }
}

try {
    // Ensure DB exists
    $root = pdo_root();
    ensure_database($root);
    $root = null; // Close the root connection

    // Small delay to ensure database is created
    sleep(1);

    // Connect to target DB and ensure tables
    $pdo = pdo_db();
    ensure_tables($pdo);
    seed_subjects($pdo);

    echo "✓ Database and tables are present.\n";
    echo "✓ Subjects seeded (Mathematics, English, Science, History, Chemistry, Physics, Biology, Geography).\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
