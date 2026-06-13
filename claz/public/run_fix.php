<?php
// Fix UTF-8 encoding for all tables
// Access this via browser to execute
require_once __DIR__ . '/../src/config.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    $pdo = db();
    echo "Connected to database: " . DB_NAME . "\n\n";

    $commands = [
        "ALTER DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE questions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE questions MODIFY question_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE answer_options CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE answer_options MODIFY option_text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE papers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE papers MODIFY title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE users MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE users MODIFY email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE attempts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE responses CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE paper_access CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE teacher_student CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE payments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "ALTER TABLE messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    foreach ($commands as $cmd) {
        echo "Executing: $cmd ... ";
        try {
            $pdo->exec($cmd);
            echo "OK\n";
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "\n------------------------------------------------\n";
    echo "Database encoding fix completed.\n";
    echo "EXISTING '?????' DATA IS PERMANENTLY LOST.\n";
    echo "PLEASE EDIT/RE-SAVE THE QUESTIONS TO FIX THEM.\n";
    echo "------------------------------------------------\n";

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage();
}
?>