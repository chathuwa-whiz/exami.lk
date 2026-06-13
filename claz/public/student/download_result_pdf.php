<?php
require_once __DIR__ . '/../../src/config.php';
require_login();
$user = current_user();
$attemptId = (int)($_GET['attempt_id'] ?? 0);
if (!$attemptId) { echo 'Missing attempt'; exit; }

$pdo = db();
$stmt = $pdo->prepare('SELECT a.id,a.score,a.submitted_at,p.id AS paper_id, p.title FROM attempts a JOIN papers p ON a.paper_id=p.id WHERE a.id=? AND a.student_id=?');
$stmt->execute([$attemptId, $user['id']]);
$attempt = $stmt->fetch();
if (!$attempt) { echo 'Attempt not found'; exit; }
if (!$attempt['submitted_at']) { echo 'Test not submitted yet'; exit; }

// Get total marks
$totalStmt = $pdo->prepare('SELECT COALESCE(SUM(marks),0) AS total FROM questions WHERE paper_id=?');
$totalStmt->execute([$attempt['paper_id']]);
$totalRow = $totalStmt->fetch();
$totalMarks = (int)$totalRow['total'];
$score = (int)$attempt['score'];
$percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100) : 0;

// Get questions and responses
$qStmt = $pdo->prepare('SELECT id, question_text, marks, image_path FROM questions WHERE paper_id=? ORDER BY position');
$qStmt->execute([$attempt['paper_id']]);
$questions = $qStmt->fetchAll();
$rStmt = $pdo->prepare('SELECT selected_option_id FROM responses WHERE attempt_id=? AND question_id=?');
$optStmt = $pdo->prepare('SELECT id, option_text, is_correct, attachment_path FROM answer_options WHERE question_id=?');

// Generate HTML content
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exam Result - <?= htmlspecialchars($attempt['title']) ?></title>
    <!-- Sinhala-capable font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Sinhala','Segoe UI',Arial,sans-serif;
            margin: 20px;
            color: #333;
            font-size: 11pt;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #4f46e5;
            margin: 10px 0;
            font-size: 24pt;
            letter-spacing: .5px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .info-box {
            background: #f0f4ff;
            border: 1px solid #667eea;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #667eea;
        }
        .score-box {
            text-align: center;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 6px 20px rgba(79,70,229,.25);
        }
        .score-box .percentage {
            font-size: 48pt;
            font-weight: bold;
            margin: 10px 0;
        }
        .score-box .score-text {
            font-size: 16pt;
            margin: 10px 0;
        }
        .question {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .question-header {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .question-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 10px;
        }
        .question-text {
            font-weight: 700;
            font-size: 12pt;
        }
        .marks-badge {
            float: right;
            background: #ffc107;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 9pt;
        }
        .options {
            margin: 15px 0;
        }
        .option {
            padding: 10px;
            margin: 8px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .option.correct {
            background: #d4edda;
            border-color: #28a745;
        }
        .option.incorrect {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .option.neutral {
            background: #fff;
        }
        .option-label {
            font-weight: bold;
            margin-right: 8px;
        }
        .answer-summary {
            display: table;
            width: 100%;
            margin-top: 15px;
        }
        .answer-box {
            display: table-cell;
            width: 50%;
            padding: 12px;
            border-radius: 5px;
        }
        .your-answer {
            background: #e7f3ff;
            border-left: 4px solid #4facfe;
        }
        .correct-answer {
            background: #f0fff4;
            border-left: 4px solid #00d084;
        }
        .answer-box strong {
            display: block;
            margin-bottom: 5px;
            font-size: 10pt;
        }
        .status-badge {
            float: right;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 10pt;
        }
        .status-correct { background: #16a34a; color: #fff; }
        .status-incorrect { background: #dc2626; color: #fff; }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Exam Result Report</h1>
        <p><?= htmlspecialchars($attempt['title']) ?></p>
        <p>Student: <?= htmlspecialchars($user['name']) ?> | Date: <?= date('F d, Y', strtotime($attempt['submitted_at'])) ?></p>
    </div>

    <!-- Score Box -->
    <div class="score-box">
        <div class="percentage"><?= $percentage ?>%</div>
        <div class="score-text"><?= $score ?> out of <?= $totalMarks ?> marks</div>
        <div>
            <?php if ($percentage >= 90) { echo '🎯 Excellent Performance'; }
            elseif ($percentage >= 80) { echo '⭐ Very Good'; }
            elseif ($percentage >= 70) { echo '👍 Good'; }
            elseif ($percentage >= 60) { echo '✓ Satisfactory'; }
            else { echo '📚 Keep Practicing'; } ?>
        </div>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Total Questions:</span>
            <span><?= count($questions) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Submitted On:</span>
            <span><?= date('F d, Y H:i', strtotime($attempt['submitted_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Percentage:</span>
            <span><?= $percentage ?>%</span>
        </div>
    </div>

    <!-- Questions and Answers -->
    <h2 style="color: #667eea; margin-bottom: 20px;">📝 Detailed Answer Review</h2>

    <?php foreach ($questions as $idx => $q): ?>
        <?php 
        $rStmt->execute([$attemptId, $q['id']]); 
        $resp = $rStmt->fetch(); 
        $selectedId = $resp['selected_option_id'] ?? null;
        
        $optStmt->execute([$q['id']]);
        $opts = $optStmt->fetchAll();
        $correct = null; 
        $correctIndex = null;
        foreach ($opts as $i => $o) {
            if ($o['is_correct']) { 
                $correct = $o; 
                $correctIndex = $i; 
                break; 
            }
        }
        $correctLabel = is_null($correctIndex) ? null : chr(ord('A') + $correctIndex);

        $selectedOption = null; 
        $selectedIndex = null;
        if ($selectedId) {
            foreach ($opts as $i => $o) {
                if ($o['id'] == $selectedId) { 
                    $selectedOption = $o; 
                    $selectedIndex = $i; 
                    break; 
                }
            }
        }
        $selectedLabel = is_null($selectedIndex) ? null : chr(ord('A') + $selectedIndex);
        $isCorrect = ($selectedId && $correct && $selectedId == $correct['id']);
        ?>
        
        <div class="question">
            <div class="question-header">
                <span class="marks-badge">★ <?= $q['marks'] ?> mark<?= $q['marks']==1?'':'s' ?></span>
                <span class="status-badge <?= $isCorrect ? 'status-correct' : 'status-incorrect' ?>">
                    <?= $isCorrect ? '✓ Correct' : '✗ Incorrect' ?>
                </span>
                <span class="question-number">Q<?= $idx+1 ?></span>
                <span class="question-text"><?= htmlspecialchars($q['question_text']) ?></span>
            </div>

            <div class="options">
                <?php foreach ($opts as $i => $o): ?>
                    <?php 
                    $isSel = ($selectedId && $selectedId == $o['id']); 
                    $isCor = (bool)$o['is_correct']; 
                    $label = chr(ord('A') + $i);
                    $optClass = $isCor ? 'correct' : ($isSel ? 'incorrect' : 'neutral');
                    ?>
                    <div class="option <?= $optClass ?>">
                        <span style="font-size: 14pt; margin-right: 10px;">
                            <?php if ($isCor && $isSel) { echo '✅'; }
                            elseif ($isCor) { echo '✓'; }
                            elseif ($isSel) { echo '❌'; }
                            else { echo '○'; } ?>
                        </span>
                        <span class="option-label"><?= $label ?>.</span>
                        <?= htmlspecialchars($o['option_text']) ?>
                                                <?php if (!empty($o['attachment_path'])): ?>
                                                    <?php
                                                        $optFilePath = $o['attachment_path'];
                                                        $optExt = strtolower(pathinfo($optFilePath, PATHINFO_EXTENSION));
                                                        $optNorm = ltrim($optFilePath, '/');
                                                        if (stripos($optNorm, 'public/') === 0) { $optNorm = substr($optNorm, 7); }
                                                        if (stripos($optNorm, 'uploads/options/') === 0) { $optNorm = 'teacher/' . $optNorm; }
                                                        if (stripos($optNorm, 'public/teacher/uploads/options/') === 0) {
                                                            $optNorm = substr($optNorm, strlen('public/'));
                                                        }
                                                        $optSrc = app_href($optNorm);
                                                    ?>
                                                    <div style="margin-top: 6px;">
                                                        <?php if ($optExt === 'pdf'): ?>
                                                            <a href="<?= htmlspecialchars($optSrc) ?>" target="_blank" rel="noopener">View PDF</a>
                                                        <?php else: ?>
                                                            <img src="<?= htmlspecialchars($optSrc) ?>" alt="Option attachment" style="max-width: 260px; max-height: 180px; border-radius: 6px; border: 1px solid #e0e0e0;">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                        <?php if ($isSel) { echo '<strong style="color: #dc3545;"> (your choice)</strong>'; } ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="answer-summary">
                <div class="answer-box your-answer">
                    <strong>👤 Your Answer:</strong>
                    <?= $selectedOption ? htmlspecialchars(($selectedLabel ? $selectedLabel . '. ' : '') . $selectedOption['option_text']) : 'No answer provided' ?>
                </div>
                <div class="answer-box correct-answer" style="margin-left: 10px;">
                    <strong>🎯 Correct Answer:</strong>
                    <?= $correct ? htmlspecialchars(($correctLabel ? $correctLabel . '. ' : '') . $correct['option_text']) : 'N/A' ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Footer -->
    <div class="footer">
        <p>Generated on <?= date('F d, Y H:i:s') ?></p>
        <p>This is an official exam result report. Keep it for your records.</p>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
// Return print-friendly HTML that browser can convert to PDF (ensures Sinhala renders correctly)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="result_' . $attemptId . '.html"');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exam Result - Print to PDF</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .print-instructions {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .print-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16pt;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .print-button:hover {
            background: #5568d3;
        }
    </style>
    <script>
        function printPDF() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="print-instructions no-print">
        <h2 style="color: #667eea; margin-bottom: 15px;">📥 Download Your Result as PDF</h2>
        <p style="margin-bottom: 20px; font-size: 12pt;">
            Click the button below to open your browser's print dialog.<br>
            <strong>Select "Save as PDF" or "Microsoft Print to PDF"</strong> as your printer.
        </p>
        <button class="print-button" onclick="printPDF()">
            🖨️ Print / Save as PDF
        </button>
        <p style="margin-top: 15px; color: #666; font-size: 10pt;">
            Or press <kbd>Ctrl+P</kbd> (Windows) / <kbd>Cmd+P</kbd> (Mac)
        </p>
    </div>
    
    <?= $html ?>
</body>
</html>
