<?php
ob_start();
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';

set_time_limit(300);

// session already started by csrf.php
ob_clean();
header('Content-Type: application/json; charset=UTF-8');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthenticated']);
    exit;
}
if (($user['user_type'] ?? '') !== 'teacher' && ($user['user_type'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

// AJAX endpoint: verify token manually without rotating it.
// Rotating breaks subsequent AJAX calls from the same page load.
$_csrfSent = $_POST['_csrf'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_csrfSent)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'invalid_csrf']);
    exit;
}

if (empty($_FILES['pdf_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'PDF file is missing']);
    exit;
}

$file = $_FILES['pdf_file'];
if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid upload']);
    exit;
}

$mimeType = mime_content_type($file['tmp_name']) ?: '';
$extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
if ($mimeType !== 'application/pdf' && $extension !== 'pdf') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please upload a valid PDF file.']);
    exit;
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Composer autoload not found. Run composer install.']);
    exit;
}
require_once $autoloadPath;

try {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($file['tmp_name']);
    $text = $pdf->getText();
    $text = mb_substr($text, 0, 15000);

    if (trim($text) === '') {
        echo json_encode([
            'success' => false,
            'error' => 'PDF text could not be extracted. It may be a scanned image PDF.'
        ]);
        exit;
    }

    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    if ($apiKey === '') {
        $apiKey = getenv('GEMINI_API_KEY') ?: '';
    }
    if ($apiKey === '') {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Gemini API key is not configured. Set GEMINI_API_KEY in config or environment.'
        ]);
        exit;
    }

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);

    $prompt = "Extract all multiple-choice questions and their answers from the following text.\n\n"
        . "CRITICAL LANGUAGE INSTRUCTIONS FOR SINHALA:\n"
        . "1. If the extracted text is already in proper Sinhala Unicode, keep it exactly as it is.\n"
        . "2. LEGACY FONT CONVERSION: If the text contains English ASCII gibberish that represents Sinhala words typed in legacy fonts like 'FM-Abhaya' or 'DL-Manel' (For example: 'my; ±lafjk' which means 'පහත දැක්වෙන', or ';e;iSñhd'), you MUST decode/transliterate all this legacy ASCII text into proper Standard Sinhala Unicode.\n"
        . "3. The final output MUST be in perfectly readable Sinhala Unicode. DO NOT return the English ASCII gibberish. DO NOT translate the questions into English.\n\n"
        . "Format the output strictly as a JSON array of objects.\n"
        . "Each object MUST have:\n"
        . "- 'question_text': (string) The question itself in readable Sinhala Unicode.\n"
        . "- 'options': (array of strings) Minimum 2, maximum 5 options in readable Sinhala Unicode. Do not include A, B, C prefixes in the string.\n"
        . "- 'correct_index': (integer) The array index (0-based) of the correct answer. If not found, use 0.\n"
        . "- 'marks': (integer) Default to 1.\n\n"
        . "Do not return any markdown tags like ```json. Return ONLY the JSON array.\n\n"
        . "Text to process:\n\n" . $text;

    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlErr) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'AI API connection failed: ' . $curlErr]);
        exit;
    }

    if ($httpCode !== 200) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'AI API Error: ' . $response]);
        exit;
    }

    $resultData = json_decode($response, true);
    $aiExtractedText = $resultData['candidates'][0]['content']['parts'][0]['text'] ?? '[]';

    // AI sometimes wraps response in markdown code fences (```json ... ```) — strip them.
    $aiExtractedText = trim($aiExtractedText);
    if (str_starts_with($aiExtractedText, '```')) {
        $aiExtractedText = preg_replace('/^```[a-z]*\s*/i', '', $aiExtractedText);
        $aiExtractedText = preg_replace('/\s*```$/', '', $aiExtractedText);
        $aiExtractedText = trim($aiExtractedText);
    }

    $questions = json_decode($aiExtractedText, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Failed to parse AI response into JSON.']);
        exit;
    }

    // Basic normalization before returning to frontend.
    $normalized = [];
    foreach ($questions as $q) {
        if (!is_array($q)) {
            continue;
        }

        $questionText = trim((string)($q['question_text'] ?? ''));
        $options = $q['options'] ?? [];
        if (!is_array($options)) {
            $options = [];
        }

        $cleanOptions = [];
        foreach ($options as $opt) {
            $optText = trim((string)$opt);
            if ($optText !== '') {
                $cleanOptions[] = $optText;
            }
            if (count($cleanOptions) >= 5) {
                break;
            }
        }

        if ($questionText === '' || count($cleanOptions) < 2) {
            continue;
        }

        $correctIndex = (int)($q['correct_index'] ?? 0);
        if ($correctIndex < 0 || $correctIndex >= count($cleanOptions)) {
            $correctIndex = 0;
        }

        $marks = (int)($q['marks'] ?? 1);
        if ($marks < 1) {
            $marks = 1;
        }

        $normalized[] = [
            'question_text' => $questionText,
            'options' => $cleanOptions,
            'correct_index' => $correctIndex,
            'marks' => $marks
        ];
    }

    if (count($normalized) === 0) {
        echo json_encode(['success' => false, 'error' => 'No valid multiple-choice questions found in AI response.']);
        exit;
    }

    echo json_encode(['success' => true, 'questions' => $normalized]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'System Error: ' . $e->getMessage()]);
}

