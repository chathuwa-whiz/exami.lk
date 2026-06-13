<?php
// Debug script to check responses and answer_options for a given attempt
require_once __DIR__ . '/src/config.php';

$attemptId = isset($argv[1]) ? (int)$argv[1] : 0;
if (!$attemptId) {
    echo "Usage: php check_attempt_debug.php <attempt_id>\n";
    exit(1);
}

$pdo = db();

// Get attempt info
$attempt = $pdo->query("SELECT * FROM attempts WHERE id = $attemptId")->fetch();
if (!$attempt) {
    echo "Attempt not found.\n";
    exit(1);
}

$paperId = $attempt['paper_id'];
echo "Checking Attempt ID: $attemptId (Paper ID: $paperId)\n";

// Get all questions for the paper
$questions = $pdo->query("SELECT id, question_text, marks FROM questions WHERE paper_id = $paperId ORDER BY position")->fetchAll();

foreach ($questions as $q) {
    echo "\nQ{$q['id']}: {$q['question_text']} ({$q['marks']} marks)\n";
    // Get all options
    $options = $pdo->query("SELECT id, option_text, is_correct FROM answer_options WHERE question_id = {$q['id']}")->fetchAll();
    foreach ($options as $o) {
        echo "  - [{$o['id']}] {$o['option_text']} ";
        if ($o['is_correct']) echo "[CORRECT] ";
        echo "\n";
    }
    // Get student's response
    $resp = $pdo->query("SELECT selected_option_id FROM responses WHERE attempt_id = $attemptId AND question_id = {$q['id']}")->fetch();
    if ($resp) {
        echo "  Student selected option ID: {$resp['selected_option_id']}\n";
        $sel = null;
        foreach ($options as $o) {
            if ($o['id'] == $resp['selected_option_id']) $sel = $o;
        }
        if ($sel) {
            echo "    -> Selected text: {$sel['option_text']} ";
            if ($sel['is_correct']) echo "[CORRECT]";
            echo "\n";
        } else {
            echo "    -> Selected option not found in options!\n";
        }
    } else {
        echo "  No response recorded for this question.\n";
    }
}
