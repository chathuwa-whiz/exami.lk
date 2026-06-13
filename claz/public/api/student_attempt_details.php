<?php
require_once __DIR__ . '/../../src/config.php';
header('Content-Type: application/json');

require_login();
$user = current_user();

// Only teachers can view student attempt details
if ($user['user_type'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$attemptId = (int)($_GET['attempt_id'] ?? 0);
if (!$attemptId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing attempt_id']);
    exit;
}

$pdo = db();

// Verify that this attempt belongs to a paper owned by this teacher
$verifyStmt = $pdo->prepare('
    SELECT a.id, a.paper_id, a.student_id, a.score, a.submitted_at,
           p.title, p.teacher_id,
           u.name as student_name, u.student_id as student_identifier
    FROM attempts a
    JOIN papers p ON a.paper_id = p.id
    JOIN users u ON a.student_id = u.id
    WHERE a.id = ? AND p.teacher_id = ?
');
$verifyStmt->execute([$attemptId, $user['id']]);
$attempt = $verifyStmt->fetch();

if (!$attempt) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Attempt not found or access denied']);
    exit;
}

// Get total marks for the paper
$totalMarksStmt = $pdo->prepare('SELECT COALESCE(SUM(marks), 0) as total FROM questions WHERE paper_id = ?');
$totalMarksStmt->execute([$attempt['paper_id']]);
$totalMarks = (int)$totalMarksStmt->fetchColumn();

// Get all questions for this paper with student's responses
$questionsStmt = $pdo->prepare('
    SELECT q.id, q.question_text, q.marks, q.position
    FROM questions q
    WHERE q.paper_id = ?
    ORDER BY q.position
');
$questionsStmt->execute([$attempt['paper_id']]);
$questions = $questionsStmt->fetchAll();

$result = [];
foreach ($questions as $question) {
    $questionId = $question['id'];
    
    // Get all options for this question
    $optionsStmt = $pdo->prepare('
        SELECT id, option_text, is_correct, attachment_path
        FROM answer_options
        WHERE question_id = ?
        ORDER BY id
    ');
    $optionsStmt->execute([$questionId]);
    $options = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get student's response for this question
    $responseStmt = $pdo->prepare('
        SELECT selected_option_id
        FROM responses
        WHERE attempt_id = ? AND question_id = ?
    ');
    $responseStmt->execute([$attemptId, $questionId]);
    $response = $responseStmt->fetch();
    
    $selectedOptionId = $response ? (int)$response['selected_option_id'] : null;
    
    // Check if the selected option is correct
    $isCorrect = false;
    if ($selectedOptionId) {
        foreach ($options as $opt) {
            if ($opt['id'] == $selectedOptionId && $opt['is_correct']) {
                $isCorrect = true;
                break;
            }
        }
    }
    
    $result[] = [
        'question_id' => (int)$questionId,
        'question_text' => $question['question_text'],
        'marks' => (int)$question['marks'],
        'selected_option' => $selectedOptionId,
        'is_correct' => $isCorrect,
        'options' => array_map(function($opt) {
            return [
                'id' => (int)$opt['id'],
                'option_text' => $opt['option_text'],
                'attachment_path' => $opt['attachment_path'],
                'is_correct' => (bool)$opt['is_correct']
            ];
        }, $options)
    ];
}

echo json_encode([
    'success' => true,
    'attempt_id' => (int)$attemptId,
    'student_name' => $attempt['student_name'],
    'student_id' => $attempt['student_identifier'],
    'paper_title' => $attempt['title'],
    'score' => (int)$attempt['score'],
    'total_marks' => $totalMarks,
    'percentage' => $totalMarks > 0 ? round(((int)$attempt['score'] / $totalMarks) * 100) : 0,
    'submitted_at' => $attempt['submitted_at'],
    'questions' => $result
]);
