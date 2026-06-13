-- Payout requests table
CREATE TABLE payout_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  amount_cents INT NOT NULL,
  status ENUM('pending','approved','completed','rejected') DEFAULT 'pending',
  bank_details TEXT NULL,
  admin_notes TEXT NULL,
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  processed_by INT NULL,
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_teacher_status (teacher_id, status),
  INDEX idx_status_requested (status, requested_at)
) ENGINE=InnoDB;
