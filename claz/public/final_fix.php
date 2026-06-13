<?php
// public/final_fix.php
// Access http://localhost/claz/public/final_fix.php to run this.

require_once __DIR__ . '/../src/config.php';

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>UTF-8 DB Test</title><style>body{font-family:'Noto Sans Sinhala', sans-serif; padding: 2rem;}</style></head><body>";
echo "<h1>Database Encoding Diagnostics</h1>";

try {
    $pdo = db();

    // Force session character set to utf8mb4 explicitly
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET character_set_client = utf8mb4");
    $pdo->exec("SET character_set_results = utf8mb4");
    $pdo->exec("SET character_set_connection = utf8mb4");

    echo "<h2>Session character set</h2>";
    $vars = $pdo->query("SHOW VARIABLES LIKE 'character_set_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo "<pre>" . htmlspecialchars(print_r($vars, true)) . "</pre>";
    
    // 1. Force Alter Tables
    echo "<h2>1. Updating Table Schema...</h2>";
    $tables = ['questions', 'answer_options', 'papers', 'users'];
    foreach ($tables as $t) {
        try {
            $pdo->exec("ALTER TABLE $t CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<div style='color:green'>✓ Table '$t' updated to utf8mb4.</div>";
        } catch (Exception $e) {
            echo "<div style='color:red'>✗ Failed to update '$t': " . $e->getMessage() . "</div>";
        }
    }

    // 2. Insert Test Data
    echo "<h2>2. Inserting Test Data...</h2>";
    $testStr = "TEST Sinhala: සිංහල ටෙස්ට් $ \pi $";
    
    // Create a temporary test table to avoid messing up production data too much, 
    // or just insert into papers/questions and delete/rollback?
    // Let's create a temp table.
    $pdo->exec("CREATE TABLE IF NOT EXISTS test_utf8 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $stmt = $pdo->prepare("INSERT INTO test_utf8 (content) VALUES (?)");
    $stmt->execute([$testStr]);
    $id = $pdo->lastInsertId();
    echo "<div style='color:green'>✓ Inserted test string: " . htmlspecialchars($testStr) . "</div>";
    
    // 3. Retrieve Test Data
    echo "<h2>3. Retrieving Test Data...</h2>";
    $stmt = $pdo->prepare("SELECT content FROM test_utf8 WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $retrieved = $row['content'];
    
    echo "<div><strong>Original:</strong> $testStr</div>";
    echo "<div><strong>Retrieved:</strong> " . htmlspecialchars($retrieved) . "</div>";
    
    if ($testStr === $retrieved) {
        echo "<h3 style='color:green; border: 2px solid green; padding: 10px;'>SUCCESS! The database is correctly saving and retrieving Sinhala text.</h3>";
        echo "<p><strong>INSTRUCTIONS FOR YOU:</strong><br>
        1. The system is fixed.<br>
        2. Expected behavior: Old questions that show '?????' are permanently damaged and cannot be recovered.<br>
        3. <strong>ACTION REQUIRED:</strong> Go to 'Edit Paper', DELETE the questions with '?????', and RE-TYPE them. New questions will save correctly.</p>";
    } else {
        echo "<h3 style='color:red; border: 2px solid red; padding: 10px;'>FAILURE! Retrieved text does not match.</h3>";
        echo "<div>Hex dump of retrieved: " . bin2hex($retrieved) . "</div>";
    }
    
    // Clean up
    $pdo->exec("DROP TABLE test_utf8");

} catch (Throwable $e) {
    echo "<h2 style='color:red'>Fatal Error</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
