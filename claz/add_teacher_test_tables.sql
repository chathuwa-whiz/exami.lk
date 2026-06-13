-- Migration: Add teacher test attempt tables
-- This allows teachers to test their own papers

CREATE TABLE IF NOT EXISTS teacher_test_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    paper_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
    INDEX idx_teacher_paper (teacher_id, paper_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teacher_test_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT NULL,
    FOREIGN KEY (test_attempt_id) REFERENCES teacher_test_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES answer_options(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_test_attempt_question (test_attempt_id, question_id)
) ENGINE=InnoDB;
