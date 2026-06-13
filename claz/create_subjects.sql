CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO subjects (name) VALUES 
('Mathematics'),
('English'),
('Science'),
('History'),
('Chemistry'),
('Physics'),
('Biology'),
('Geography');
