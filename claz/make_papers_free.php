<?php
// Set all existing papers to free (fee_cents = 0)
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    $result = $pdo->exec('UPDATE papers SET fee_cents = 0 WHERE fee_cents IS NULL OR fee_cents > 0');
    echo "✓ Updated {$result} papers to free (fee_cents = 0)\n";
    
    // Show current state
    $papers = $pdo->query('SELECT id, title, fee_cents, is_published FROM papers ORDER BY id')->fetchAll();
    if (empty($papers)) {
        echo "No papers found.\n";
    } else {
        echo "\nCurrent papers:\n";
        foreach ($papers as $p) {
            echo "  ID {$p['id']}: {$p['title']} - Fee: {$p['fee_cents']} cents - " . ($p['is_published'] ? 'Published' : 'Draft') . "\n";
        }
    }
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
