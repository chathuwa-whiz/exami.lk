<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'teacher') { http_response_code(403); echo 'Forbidden'; exit; }
$paperId = (int)($_GET['paper_id'] ?? 0);
if (!$paperId) { echo 'Missing paper id'; exit; }
$pdo = db();
$own = $pdo->prepare('SELECT id,title FROM papers WHERE id=? AND teacher_id=?');
$own->execute([$paperId, $user['id']]);
$paper = $own->fetch();
if (!$paper) { echo 'Paper not found or not owned'; exit; }
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'CSRF failed';
    } else {
        $studentIdentifier = trim($_POST['student_id'] ?? '');
        if ($studentIdentifier === '') {
            $errors[] = 'Student ID required';
        } else {
            $find = $pdo->prepare('SELECT id FROM users WHERE student_id=? AND user_type="student"');
            $find->execute([$studentIdentifier]);
            $stu = $find->fetch();
            if (!$stu) {
                $errors[] = 'Student not found';
            } else {
                $link = $pdo->prepare('INSERT INTO teacher_student (teacher_id,student_id) VALUES (?,?) ON DUPLICATE KEY UPDATE student_id=student_id');
                $link->execute([$user['id'], $stu['id']]);
                $grant = $pdo->prepare('INSERT INTO paper_access (user_id,paper_id) VALUES (?,?) ON DUPLICATE KEY UPDATE paper_id=paper_id');
                $grant->execute([$stu['id'], $paperId]);
                $success = 'Student assigned & access granted.';
            }
        }
    }
}
$list = $pdo->prepare('SELECT u.name,u.student_id FROM paper_access pa JOIN users u ON pa.user_id=u.id WHERE pa.paper_id=? ORDER BY u.name');
$list->execute([$paperId]);
$assigned = $list->fetchAll();
$countAssigned = count($assigned);

// Fetch submitted attempts with scores
$totalMarksStmt = $pdo->prepare('SELECT COALESCE(SUM(marks),0) AS total FROM questions WHERE paper_id=?');
$totalMarksStmt->execute([$paperId]);
$totalMarks = (int)$totalMarksStmt->fetchColumn();

$attemptsStmt = $pdo->prepare('SELECT a.id, a.score, a.submitted_at, u.name, u.student_id, u.id as user_id
  FROM attempts a
  JOIN users u ON a.student_id = u.id
  WHERE a.paper_id=? AND a.submitted_at IS NOT NULL
  ORDER BY a.submitted_at DESC');
$attemptsStmt->execute([$paperId]);
$attempts = $attemptsStmt->fetchAll();

// Fetch questions and response analytics
$questionsStmt = $pdo->prepare('SELECT id, question_text, marks FROM questions WHERE paper_id=? ORDER BY position');
$questionsStmt->execute([$paperId]);
$questions = $questionsStmt->fetchAll();

// Get response analytics for each question
$responseAnalytics = [];
foreach ($questions as $q) {
    $qid = $q['id'];
    // Get all options for this question
    $optionsStmt = $pdo->prepare('SELECT id, option_text, is_correct FROM answer_options WHERE question_id=? ORDER BY id');
    $optionsStmt->execute([$qid]);
    $options = $optionsStmt->fetchAll();
    
    // Count responses for each option
    $responseCounts = [];
    foreach ($options as $opt) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM responses WHERE question_id=? AND selected_option_id=?');
        $countStmt->execute([$qid, $opt['id']]);
        $responseCounts[$opt['id']] = [
            'option_text' => $opt['option_text'],
            'is_correct' => $opt['is_correct'],
            'count' => (int)$countStmt->fetchColumn(),
            'color' => $opt['is_correct'] ? '#00d084' : '#4facfe'
        ];
    }
    
    // Count unanswered
    $unansweredStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM attempts WHERE paper_id=? AND submitted_at IS NOT NULL AND id NOT IN (SELECT attempt_id FROM responses WHERE question_id=?)');
    $unansweredStmt->execute([$paperId, $qid]);
    $unansweredCount = (int)$unansweredStmt->fetchColumn();
    
    $responseAnalytics[$qid] = [
        'question' => $q,
        'options' => $responseCounts,
        'unanswered' => $unansweredCount,
        'total' => count($attempts)
    ];
}

// Get completion status for all assigned students
$completionStmt = $pdo->prepare('SELECT u.id, u.name, u.student_id, 
  CASE WHEN a.id IS NOT NULL AND a.submitted_at IS NOT NULL THEN "completed" 
       WHEN a.id IS NOT NULL AND a.submitted_at IS NULL THEN "in_progress"
       ELSE "not_started" 
  END as status,
  a.submitted_at, a.score
  FROM users u
  JOIN paper_access pa ON u.id = pa.user_id
  LEFT JOIN attempts a ON u.id = a.student_id AND pa.paper_id = a.paper_id
  WHERE pa.paper_id = ?
  ORDER BY u.name');
$completionStmt->execute([$paperId]);
$completionStatus = $completionStmt->fetchAll();

render_header('Assign Students');
?>

<!-- Load KaTeX for math rendering in question analytics -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" />
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

<!-- Load Chart.js for pie charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3/dist/chart.min.js"></script>

<script>
  // Initialize KaTeX rendering when script loads
  function renderMath() {
    if (window.renderMathInElement) {
      try {
        window.renderMathInElement(document.body, {
          delimiters: [
            {left: '$$', right: '$$', display: true},
            {left: '$', right: '$', display: false},
            {left: '\\[', right: '\\]', display: true},
            {left: '\\(', right: '\\)', display: false}
          ],
          throwOnError: false
        });
      } catch(err) {
        console.log('KaTeX render error:', err.message);
      }
    }
  }
  
  // Render when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderMath);
  } else {
    renderMath();
  }
</script>

<section class="mb-4">
  <div class="row g-3">
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Paper</p>
        <h3 class="mb-0"><?= htmlspecialchars($paper['title']) ?></h3>
        <p class="muted small mb-0">ID <?= $paper['id'] ?></p>
      </div>
    </div>
    <div class="col-md-6 col-lg-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Assigned students</p>
        <h3 class="mb-0"><?= $countAssigned ?></h3>
        <p class="muted small mb-0">With access to this paper</p>
      </div>
    </div>
    <div class="col-md-12 col-lg-4">
      <div class="app-card p-4 h-100">
        <p class="text-uppercase small text-muted mb-1">Actions</p>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>">Back to Papers</a>
          <button class="btn btn-outline-secondary btn-sm" type="button" onclick="document.getElementById('student_id').focus()">Assign another</button>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="app-card p-4">
  <h2 class="h5 mb-3">Assign Students to <?= htmlspecialchars($paper['title']) ?></h2>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger" role="alert" aria-live="assertive"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <?php if ($success): ?><div class="alert alert-success" role="status" aria-live="polite"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <form method="post" class="row g-3 align-items-end">
    <?= csrf_field(); ?>
    <div class="col-sm-6 col-lg-4">
      <label class="form-label">Student ID</label>
      <input id="student_id" name="student_id" class="form-control" required placeholder="e.g. 25C18379">
    </div>
    <div class="col-sm-6 col-lg-3">
      <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2"><i class="bi bi-person-plus"></i> Assign</button>
    </div>
  </form>
  <hr class="my-4">
  <h3 class="h6 mb-3">Currently Assigned (<?= $countAssigned ?>)</h3>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($assigned as $a): ?>
      <span class="badge-soft"><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['student_id']) ?>)</span>
    <?php endforeach; ?>
    <?php if (empty($assigned)): ?><span class="muted">None assigned yet.</span><?php endif; ?>
  </div>

  <hr class="my-4">
  <h3 class="h6 mb-3">Results for this paper</h3>
  <?php if (empty($attempts)): ?>
    <div class="alert alert-info" role="status">No submitted attempts yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Student</th>
            <th>ID</th>
            <th>Submitted</th>
            <th>Score</th>
            <th>Percentage</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attempts as $att):
            $score = (int)$att['score'];
            $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100) : 0;
          ?>
            <tr>
              <td><?= htmlspecialchars($att['name']) ?></td>
              <td><?= htmlspecialchars($att['student_id']) ?></td>
              <td><?= htmlspecialchars($att['submitted_at']) ?></td>
              <td><?= $score ?> / <?= $totalMarks ?></td>
              <td><?= $pct ?>%</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewStudentAnswers(<?= $att['id'] ?>, '<?= htmlspecialchars($att['name'], ENT_QUOTES) ?>')">
                  <i class="bi bi-eye"></i> View Answers
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <hr class="my-4">
  <h3 class="h6 mb-3">Student Completion Status</h3>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Student</th>
          <th>ID</th>
          <th>Status</th>
          <th>Submitted At</th>
          <th>Score</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($completionStatus as $cs):
          $statusColor = $cs['status'] === 'completed' ? 'success' : ($cs['status'] === 'in_progress' ? 'warning' : 'secondary');
          $statusLabel = ucfirst(str_replace('_', ' ', $cs['status']));
        ?>
          <tr>
            <td><?= htmlspecialchars($cs['name']) ?></td>
            <td><?= htmlspecialchars($cs['student_id']) ?></td>
            <td><span class="badge bg-<?= $statusColor ?>"><?= $statusLabel ?></span></td>
            <td><?= $cs['submitted_at'] ? htmlspecialchars($cs['submitted_at']) : '-' ?></td>
            <td><?= $cs['score'] !== null ? $cs['score'] . ' / ' . $totalMarks : '-' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <hr class="my-4">
  <h3 class="h6 mb-3">Question Analytics - Response Distribution</h3>
  <div class="accordion" id="analyticsAccordion">
    <?php $qIndex = 0; foreach ($responseAnalytics as $qid => $analytics): $qIndex++; ?>
      <div class="accordion-item" style="border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 0.5rem; overflow: hidden;">
        <h2 class="accordion-header" id="heading_<?= $qid ?>">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $qid ?>" aria-expanded="false" aria-controls="collapse_<?= $qid ?>" style="padding: 0.75rem 1rem; font-size: 0.9rem; background: #f8f9fa;">
            <span style="flex: 1;">
              <strong style="color: #333;">Q<?= $qIndex ?>.</strong>
              <span style="color: #666; margin-left: 0.5rem;">
                <?= htmlspecialchars(substr($analytics['question']['question_text'], 0, 80)) ?><?= strlen($analytics['question']['question_text']) > 80 ? '...' : '' ?>
              </span>
            </span>
            <span style="margin-left: 1rem; padding: 0.25rem 0.75rem; background: #4facfe; color: white; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
              <?= $analytics['question']['marks'] ?> marks
            </span>
            <span style="margin-left: 0.5rem; color: #666; font-size: 0.85rem;">
              <?= $analytics['total'] ?> responses
            </span>
          </button>
        </h2>
        <div id="collapse_<?= $qid ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?= $qid ?>" data-bs-parent="#analyticsAccordion">
          <div class="accordion-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
              <!-- Chart Section -->
              <div>
                <div style="position: relative; height: 250px; margin-bottom: 1rem;">
                  <canvas id="chart_<?= $qid ?>" style="max-height: 250px;"></canvas>
                </div>
              </div>
              
              <!-- Response Summary Section -->
              <div>
                <div style="background: #f9f9f9; padding: 1rem; border-radius: 8px; font-size: 0.85rem;">
                  <p style="margin: 0 0 0.75rem 0; font-weight: 600; color: #333;">Response Summary (Total: <?= $analytics['total'] ?>)</p>
                  <?php foreach ($analytics['options'] as $optId => $opt): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px;">
                      <span style="flex: 1;">
                        <strong style="<?= $opt['is_correct'] ? 'color: #00d084;' : '' ?>"><?= htmlspecialchars(substr($opt['option_text'], 0, 50)) ?></strong>
                        <?= $opt['is_correct'] ? '<i class="bi bi-check-circle-fill ms-2" style="color: #00d084;"></i>' : '' ?>
                      </span>
                      <span style="font-weight: 600; color: #4facfe; white-space: nowrap; margin-left: 1rem;">
                        <?= $opt['count'] ?> (<?= $analytics['total'] > 0 ? round(($opt['count'] / $analytics['total']) * 100) : 0 ?>%)
                      </span>
                    </div>
                  <?php endforeach; ?>
                  <?php if ($analytics['unanswered'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; padding: 0.5rem; background: #fee; border-radius: 4px; color: #dc3545;">
                      <span><strong>Unanswered</strong></span>
                      <span><?= $analytics['unanswered'] ?> (<?= $analytics['total'] > 0 ? round(($analytics['unanswered'] / $analytics['total']) * 100) : 0 ?>%)</span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    // Initialize pie charts for each question
    <?php foreach ($responseAnalytics as $qid => $analytics): ?>
      const ctx_<?= $qid ?> = document.getElementById('chart_<?= $qid ?>');
      if (ctx_<?= $qid ?>) {
        const labels = [
          <?php foreach ($analytics['options'] as $optId => $opt): ?>
            '<?= addslashes(substr($opt['option_text'], 0, 30)) ?>',
          <?php endforeach; ?>
          <?= $analytics['unanswered'] > 0 ? "'Unanswered'," : "" ?>
        ];
        
        const data = [
          <?php foreach ($analytics['options'] as $optId => $opt): ?>
            <?= $opt['count'] ?>,
          <?php endforeach; ?>
          <?= $analytics['unanswered'] > 0 ? $analytics['unanswered'] . ',' : "" ?>
        ];
        
        const colors = [
          <?php 
          $colorPalette = ['#4facfe', '#ff6b9d', '#ffc107', '#9c88ff', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#fdcb6e', '#a29bfe'];
          $colorIndex = 0;
          foreach ($analytics['options'] as $optId => $opt): 
            if ($opt['is_correct']) {
              echo "'#00d084',";
            } else {
              echo "'" . $colorPalette[$colorIndex % count($colorPalette)] . "',";
              $colorIndex++;
            }
          endforeach; 
          ?>
          <?= $analytics['unanswered'] > 0 ? "'#dc3545'," : "" ?>
        ];
        
        new Chart(ctx_<?= $qid ?>, {
          type: 'doughnut',
          data: {
            labels: labels,
            datasets: [{
              data: data,
              backgroundColor: colors,
              borderColor: '#fff',
              borderWidth: 2,
              hoverOffset: 4
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  font: { size: 12 },
                  padding: 15,
                  usePointStyle: true
                }
              },
              tooltip: {
                backgroundColor: 'rgba(0,0,0,0.7)',
                padding: 10,
                titleFont: { size: 13 },
                bodyFont: { size: 12 },
                callbacks: {
                  label: function(context) {
                    const total = context.dataset.data.reduce((a,b) => a+b, 0);
                    const percentage = ((context.parsed || 0) / total * 100).toFixed(1);
                    return context.label + ': ' + (context.parsed || 0) + ' (' + percentage + '%)';
                  }
                }
              }
            }
          }
        });
      }
    <?php endforeach; ?>

    // Function to view student's answers
    async function viewStudentAnswers(attemptId, studentName) {
      try {
        const response = await fetch(`../api/student_attempt_details.php?attempt_id=${attemptId}`);
        const data = await response.json();
        
        if (!data.success) {
          alert('Failed to load student answers: ' + (data.error || 'Unknown error'));
          return;
        }
        
        let html = `
          <div class="modal fade" id="answersModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Answers by ${escapeHtml(studentName)}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-3">
                    <strong>Score:</strong> ${data.score} / ${data.total_marks} (${data.percentage}%)
                  </div>
        `;
        
        data.questions.forEach((q, index) => {
          const isCorrect = q.is_correct;
          const statusBadge = q.selected_option ? 
            (isCorrect ? '<span class="badge bg-success">Correct</span>' : '<span class="badge bg-danger">Incorrect</span>') :
            '<span class="badge bg-secondary">Not Answered</span>';
          
          html += `
            <div class="card mb-3" style="border-left: 4px solid ${isCorrect ? '#00d084' : (q.selected_option ? '#dc3545' : '#6c757d')}">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h6 class="mb-0">Q${index + 1}. ${escapeHtml(q.question_text)} (${q.marks} marks)</h6>
                  ${statusBadge}
                </div>
                <div class="ms-3">
          `;
          
          q.options.forEach(opt => {
            const isSelected = opt.id === q.selected_option;
            const isCorrectOption = opt.is_correct;
            let optionClass = '';
            let icon = '';
            
            if (isSelected && isCorrectOption) {
              optionClass = 'bg-success bg-opacity-10 border-success';
              icon = '<i class="bi bi-check-circle-fill text-success"></i>';
            } else if (isSelected && !isCorrectOption) {
              optionClass = 'bg-danger bg-opacity-10 border-danger';
              icon = '<i class="bi bi-x-circle-fill text-danger"></i>';
            } else if (isCorrectOption) {
              optionClass = 'border-success';
              icon = '<i class="bi bi-check-circle text-success"></i>';
            }
            
            html += `
              <div class="border rounded p-2 mb-2 ${optionClass}">
                <div class="d-flex align-items-center gap-2">
                  ${icon}
                  <span style="font-weight: ${isSelected ? '600' : 'normal'}">
                    ${escapeHtml(opt.option_text)}
                  </span>
                </div>
              </div>
            `;
          });
          
          html += `
                </div>
              </div>
            </div>
          `;
        });
        
        html += `
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('answersModal');
        if (existingModal) {
          existingModal.remove();
        }
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', html);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('answersModal'));
        modal.show();
        
        // Clean up when modal is hidden
        document.getElementById('answersModal').addEventListener('hidden.bs.modal', function () {
          this.remove();
        });
        
      } catch (error) {
        console.error('Error:', error);
        alert('Failed to load student answers. Please try again.');
      }
    }
    
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  </script>
</section>
<?php render_footer(); ?>