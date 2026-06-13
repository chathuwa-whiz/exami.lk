<?php
// Run this file once to add profile fields to the database
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    
    // Check if columns already exist
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'first_name'");
    if ($result->rowCount() > 0) {
        echo "Profile fields already exist!\n";
        exit;
    }
    
    // Add profile fields
    $sql = "ALTER TABLE users
        ADD COLUMN first_name VARCHAR(50) NULL AFTER name,
        ADD COLUMN second_name VARCHAR(50) NULL AFTER first_name,
        ADD COLUMN birth_date DATE NULL,
        ADD COLUMN sexuality ENUM('Male', 'Female', 'Other') NULL,
        ADD COLUMN nic_no VARCHAR(20) NULL,
        ADD COLUMN postal_id_card_no VARCHAR(20) NULL,
        ADD COLUMN school_name VARCHAR(150) NULL,
        ADD COLUMN grade VARCHAR(20) NULL,
        ADD COLUMN school_category ENUM('Government', 'Private') NULL,
        ADD COLUMN main_subject VARCHAR(100) NULL,
        ADD COLUMN first_appointment_date DATE NULL";
    
    $pdo->exec($sql);
    
    echo "✅ Profile fields added successfully!\n";
    echo "You can now access the profile pages:\n";
    echo "- Student Profile: /student/profile.php\n";
    echo "- Teacher Profile: /teacher/profile.php (updated)\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
