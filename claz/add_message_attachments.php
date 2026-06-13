<?php
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE messages ADD COLUMN attachment_type ENUM('image','pdf','audio','video') NULL AFTER body");
    echo "✓ attachment_type column added\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "✓ attachment_type column already exists\n";
    } else {
        echo "Error adding attachment_type: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(500) NULL AFTER attachment_type");
    echo "✓ attachment_path column added\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "✓ attachment_path column already exists\n";
    } else {
        echo "Error adding attachment_path: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Migration complete!\n";
