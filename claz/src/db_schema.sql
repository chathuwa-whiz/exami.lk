-- ceylonstudyhub initial schema
-- Adjust engine/charset as needed

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('teacher','student','admin') NOT NULL,
  student_id VARCHAR(20) NULL UNIQUE,
  teacher_code VARCHAR(20) NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Index to speed up name searches (especially for teachers)
CREATE INDEX idx_users_name ON users(name);

CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE teacher_subject (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  subject_id INT NOT NULL,
  UNIQUE KEY uniq_teacher_subject (teacher_id, subject_id),
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE teacher_student (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  student_id INT NOT NULL,
  UNIQUE KEY uniq_teacher_student (teacher_id, student_id),
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Direct messaging between a teacher and a student
CREATE TABLE messages (
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
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_msg_pair_created (teacher_id, student_id, created_at),
  INDEX idx_msg_receiver (receiver_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE papers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  fee_cents INT DEFAULT 0, -- fee in smallest currency unit
  time_limit_seconds INT NOT NULL, -- duration to answer
  is_published TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paper_id INT NOT NULL,
  question_text TEXT NOT NULL,
  marks INT NOT NULL DEFAULT 1,
  position INT NOT NULL,
  FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE answer_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  option_text TEXT NOT NULL,
  is_correct TINYINT(1) DEFAULT 0,
  attachment_path VARCHAR(500) NULL,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  paper_id INT NOT NULL,
  order_id VARCHAR(100) NOT NULL,
  amount_cents INT NOT NULL,
  status ENUM('pending','completed','failed') DEFAULT 'pending',
  transaction_id VARCHAR(100) DEFAULT NULL,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_order (order_id),
  INDEX idx_user_paper (user_id, paper_id)
) ENGINE=InnoDB;

CREATE TABLE paper_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  paper_id INT NOT NULL,
  granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_access (user_id, paper_id)
) ENGINE=InnoDB;

CREATE TABLE attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  paper_id INT NOT NULL,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP NULL,
  score INT NULL,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE responses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  selected_option_id INT NULL,
  FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  FOREIGN KEY (selected_option_id) REFERENCES answer_options(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB;

-- Index suggestions
CREATE INDEX idx_questions_paper ON questions(paper_id);
CREATE INDEX idx_answer_options_question ON answer_options(question_id);
CREATE INDEX idx_attempts_student_paper ON attempts(student_id, paper_id);

-- Preapproved student system (admin loads these before registration)
CREATE TABLE preapproved_students (
  student_id VARCHAR(20) PRIMARY KEY,
  name VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_deleted TINYINT(1) DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE preapproved_student_teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(20) NOT NULL,
  teacher_id INT NOT NULL,
  FOREIGN KEY (student_id) REFERENCES preapproved_students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_pre_map (student_id, teacher_id)
) ENGINE=InnoDB;

-- Indexes to accelerate admin search/pagination
CREATE INDEX idx_pre_students_created ON preapproved_students(created_at);
CREATE INDEX idx_pre_students_name ON preapproved_students(name);
CREATE INDEX idx_pre_students_deleted_created ON preapproved_students(is_deleted, created_at);

-- Audit log for admin actions
CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_logs_user_created (user_id, created_at),
  INDEX idx_logs_action (action)
) ENGINE=InnoDB;

-- Refresh tokens for long-lived sessions (rotate on use)
CREATE TABLE refresh_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(200) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  revoked TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_refresh_token (token),
  INDEX idx_refresh_user_exp (user_id, expires_at)
) ENGINE=InnoDB;

-- Simple rate limiting table (sliding fixed window per key)
CREATE TABLE rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rl_key VARCHAR(200) NOT NULL,
  window_start INT NOT NULL, -- epoch second for window start
  count INT NOT NULL,
  INDEX idx_rl_key_window (rl_key, window_start)
) ENGINE=InnoDB;
