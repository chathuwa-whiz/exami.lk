<?php
// Check answer_options for paper questions and show which are marked correct
require_once __DIR__ . '/src/config.php';

try {
    $pdo = db();
    $paperId = 1; // The paper in question
    
    echo "Checking Paper ID: $paperId\n\n";
    
    $questions = $pdo->query("SELECT q.id, q.question_text, q.position 
                              FROM questions q 
                              WHERE q.paper_id = $paperId 
                              ORDER BY q.position")->fetchAll();
    
    if (empty($questions)) {
        echo "No questions found.\n";
        exit;
    }
    
    foreach ($questions as $q) {
        echo "Q{$q['position']}: {$q['question_text']}\n";
        
        $options = $pdo->query("SELECT id, option_text, is_correct 
                                FROM answer_options 
                                WHERE question_id = {$q['id']}")->fetchAll();
        
        $hasCorrect = false;
        foreach ($options as $o) {
            $marker = $o['is_correct'] ? '✓ CORRECT' : '';
            echo "  - {$o['option_text']} $marker\n";
            if ($o['is_correct']) $hasCorrect = true;
        }
        
        if (!$hasCorrect) {
            echo "  ⚠️  WARNING: No correct answer marked!\n";
        }
        echo "\n";
    }
    
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
