<?php
require_once __DIR__ . '/src/config.php';

$pdo = db();

// Create integrity logs table
$sql = "CREATE TABLE IF NOT EXISTS exam_integrity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    details JSON NULL,
    tab_switches INT DEFAULT 0,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt (attempt_id),
    INDEX idx_activity (activity_type)
) ENGINE=InnoDB;";

// Add integrity_flagged column to attempts if it doesn't exist
$checkColumn = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='attempts' AND COLUMN_NAME='integrity_flagged'";
$result = $pdo->query($checkColumn)->fetch();

if (!$result) {
    $pdo->exec("ALTER TABLE attempts ADD COLUMN integrity_flagged TINYINT DEFAULT 0");
    echo "✓ Added integrity_flagged column\n";
}

try {
    $pdo->exec($sql);
    echo "✓ Created exam_integrity_logs table\n";
} catch (Exception $e) {
    echo "✓ Table already exists\n";
}

echo "Integrity logging system initialized!\n";
?>
