<?php
// Check if teacher test tables exist and are properly set up
require_once __DIR__ . '/src/config.php';

echo "=== Teacher Test Tables Verification ===\n\n";

try {
    $pdo = db();
    echo "✓ Database connection successful\n\n";

    // Check if teacher_test_attempts table exists
    $checkTable1 = $pdo->query("SHOW TABLES LIKE 'teacher_test_attempts'");
    if ($checkTable1->rowCount() > 0) {
        echo "✓ teacher_test_attempts table EXISTS\n";
        $cols1 = $pdo->query("DESCRIBE teacher_test_attempts");
        echo "  Columns:\n";
        while ($col = $cols1->fetch(PDO::FETCH_ASSOC)) {
            echo "    - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "✗ teacher_test_attempts table MISSING - NEEDS TO BE CREATED\n";
    }

    echo "\n";

    // Check if teacher_test_responses table exists
    $checkTable2 = $pdo->query("SHOW TABLES LIKE 'teacher_test_responses'");
    if ($checkTable2->rowCount() > 0) {
        echo "✓ teacher_test_responses table EXISTS\n";
        $cols2 = $pdo->query("DESCRIBE teacher_test_responses");
        echo "  Columns:\n";
        while ($col = $cols2->fetch(PDO::FETCH_ASSOC)) {
            echo "    - {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "✗ teacher_test_responses table MISSING - NEEDS TO BE CREATED\n";
    }

    echo "\n";

    // Try to count test attempts
    $result = $pdo->query("SELECT COUNT(*) as count FROM teacher_test_attempts");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✓ Total test attempts in database: {$row['count']}\n";

    // Try to count test responses
    $result = $pdo->query("SELECT COUNT(*) as count FROM teacher_test_responses");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✓ Total test responses in database: {$row['count']}\n";

    echo "\n=== All tables verified successfully! ===\n";

} catch (PDOException $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    echo "\nThis means the tables don't exist yet.\n";
    echo "You need to create them using one of these methods:\n\n";
    echo "Method 1: Run PHP migration script\n";
    echo "  cd c:\\xampp\\htdocs\\claz\n";
    echo "  php add_teacher_test_tables.php\n\n";
    echo "Method 2: Run SQL in phpMyAdmin\n";
    echo "  1. Open phpMyAdmin\n";
    echo "  2. Select 'admin_exami' database\n";
    echo "  3. Go to SQL tab\n";
    echo "  4. Copy contents from add_teacher_test_tables.sql and execute\n\n";
    echo "Method 3: Direct MySQL command\n";
    echo "  mysql -u admin_exami -p < add_teacher_test_tables.sql\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
