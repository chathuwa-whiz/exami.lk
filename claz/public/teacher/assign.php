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

$questionsStmt = $pdo->prepare('SELECT id, question_text, marks FROM questions WHERE paper_id=? ORDER BY position');
$questionsStmt->execute([$paperId]);
$questions = $questionsStmt->fetchAll();

$responseAnalytics = [];
foreach ($questions as $q) {
    $qid = $q['id'];
    $optionsStmt = $pdo->prepare('SELECT id, option_text, is_correct FROM answer_options WHERE question_id=? ORDER BY id');
    $optionsStmt->execute([$qid]);
    $options = $optionsStmt->fetchAll();
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css" />
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3/dist/chart.min.js"></script>
<script>
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
      } catch(err) { console.log('KaTeX render error:', err.message); }
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderMath);
  } else {
    renderMath();
  }
</script>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-journal-text"></i></div>
    <div>
      <p class="stat-card-label">Paper</p>
      <p class="stat-card-value" style="font-size:1rem;"><?= htmlspecialchars(substr($paper['title'], 0, 28)) ?><?= strlen($paper['title']) > 28 ? '…' : '' ?></p>
      <p class="stat-card-sub">ID <?= $paper['id'] ?></p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon green"><i class="bi bi-people-fill"></i></div>
    <div>
      <p class="stat-card-label">Assigned Students</p>
      <p class="stat-card-value"><?= $countAssigned ?></p>
      <p class="stat-card-sub">With access to this paper</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon yellow"><i class="bi bi-clipboard-check"></i></div>
    <div>
      <p class="stat-card-label">Submitted Attempts</p>
      <p class="stat-card-value"><?= count($attempts) ?></p>
      <p class="stat-card-sub">Total marks: <?= $totalMarks ?></p>
    </div>
  </div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger" role="alert" aria-live="assertive"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
  <div class="alert alert-success" role="status" aria-live="polite"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-person-plus text-primary"></i> Assign a Student</h2>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(app_href('teacher/manage_papers.php')) ?>"><i class="bi bi-arrow-left"></i> Back to Papers</a>
  </div>
  <div class="app-card-body">
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
    <?php if (!empty($assigned)): ?>
      <hr class="my-4">
      <h3 class="h6 mb-3" style="color:var(--on-surface-muted);text-transform:uppercase;font-size:11px;letter-spacing:.06em;">Assigned (<?= $countAssigned ?>)</h3>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($assigned as $a): ?>
          <span class="badge-soft badge-soft-primary"><?= htmlspecialchars($a['name']) ?> <span style="opacity:.65;">(<?= htmlspecialchars($a['student_id']) ?>)</span></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-person-check text-success"></i> Student Status</h2>
    <span class="badge-soft badge-soft-gray"><?= count($completionStatus) ?> students</span>
  </div>
  <div class="app-card-body p-0">
    <div class="data-table table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Student</th>
            <th>ID</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Score</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($completionStatus as $cs):
            $statusBadge = $cs['status'] === 'completed' ? 'success' : ($cs['status'] === 'in_progress' ? 'warning' : 'gray');
            $statusLabel = ucfirst(str_replace('_', ' ', $cs['status']));
          ?>
            <tr>
              <td><?= htmlspecialchars($cs['name']) ?></td>
              <td><span class="badge-soft badge-soft-gray"><?= htmlspecialchars($cs['student_id']) ?></span></td>
              <td><span class="badge-soft badge-soft-<?= $statusBadge ?>"><?= $statusLabel ?></span></td>
              <td><?= $cs['submitted_at'] ? htmlspecialchars($cs['submitted_at']) : '<span style="color:var(--muted-light);">—</span>' ?></td>
              <td><?= $cs['score'] !== null ? $cs['score'] . ' / ' . $totalMarks : '<span style="color:var(--muted-light);">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($completionStatus)): ?>
            <tr><td colspan="5" class="text-center py-4" style="color:var(--muted-light);">No students assigned yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (!empty($attempts)): ?>
<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-bar-chart-line text-primary"></i> Results</h2>
    <span class="badge-soft badge-soft-gray"><?= count($attempts) ?> submitted</span>
  </div>
  <div class="app-card-body p-0">
    <div class="data-table table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Student</th>
            <th>ID</th>
            <th>Submitted</th>
            <th>Score</th>
            <th>%</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attempts as $att):
            $score = (int)$att['score'];
            $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100) : 0;
            $pctBadge = $pct >= 75 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
          ?>
            <tr>
              <td><?= htmlspecialchars($att['name']) ?></td>
              <td><span class="badge-soft badge-soft-gray"><?= htmlspecialchars($att['student_id']) ?></span></td>
              <td style="font-size:13px;color:var(--on-surface-muted);"><?= htmlspecialchars($att['submitted_at']) ?></td>
              <td><strong><?= $score ?></strong> / <?= $totalMarks ?></td>
              <td><span class="badge-soft badge-soft-<?= $pctBadge ?>"><?= $pct ?>%</span></td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewStudentAnswers(<?= $att['id'] ?>, '<?= htmlspecialchars($att['name'], ENT_QUOTES) ?>')">
                  <i class="bi bi-eye"></i> View
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($responseAnalytics)): ?>
<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-pie-chart text-primary"></i> Question Analytics</h2>
    <span class="badge-soft badge-soft-gray"><?= count($questions) ?> questions</span>
  </div>
  <div class="app-card-body">
    <div class="accordion" id="analyticsAccordion">
      <?php $qIndex = 0; foreach ($responseAnalytics as $qid => $analytics): $qIndex++; ?>
        <div class="accordion-item" style="border:1px solid var(--border);border-radius:var(--radius);margin-bottom:6px;overflow:hidden;">
          <h2 class="accordion-header" id="heading_<?= $qid ?>">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $qid ?>" aria-expanded="false" style="padding:.75rem 1rem;font-size:.875rem;background:var(--surface-subtle);">
              <span style="flex:1;display:flex;align-items:center;gap:.5rem;">
                <span style="font-weight:700;color:var(--on-surface);">Q<?= $qIndex ?>.</span>
                <span style="color:var(--on-surface-muted);"><?= htmlspecialchars(substr($analytics['question']['question_text'], 0, 80)) ?><?= strlen($analytics['question']['question_text']) > 80 ? '…' : '' ?></span>
              </span>
              <span class="badge-soft badge-soft-primary ms-3"><?= $analytics['question']['marks'] ?> marks</span>
              <span class="badge-soft badge-soft-gray ms-2"><?= $analytics['total'] ?> responses</span>
            </button>
          </h2>
          <div id="collapse_<?= $qid ?>" class="accordion-collapse collapse" data-bs-parent="#analyticsAccordion">
            <div class="accordion-body" style="padding:1.5rem;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
                <div>
                  <div style="position:relative;height:220px;margin-bottom:1rem;">
                    <canvas id="chart_<?= $qid ?>" style="max-height:220px;"></canvas>
                  </div>
                </div>
                <div>
                  <div style="background:var(--surface-subtle);padding:1rem;border-radius:var(--radius);font-size:.85rem;">
                    <p style="margin:0 0 .75rem 0;font-weight:600;color:var(--on-surface);">Response Summary (Total: <?= $analytics['total'] ?>)</p>
                    <?php foreach ($analytics['options'] as $optId => $opt): ?>
                      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;padding:.5rem;background:var(--surface);border-radius:var(--radius-sm);">
                        <span style="flex:1;">
                          <strong style="<?= $opt['is_correct'] ? 'color:var(--success);' : 'color:var(--on-surface);' ?>"><?= htmlspecialchars(substr($opt['option_text'], 0, 50)) ?></strong>
                          <?= $opt['is_correct'] ? '<i class="bi bi-check-circle-fill ms-1" style="color:var(--success);"></i>' : '' ?>
                        </span>
                        <span style="font-weight:600;color:var(--primary);white-space:nowrap;margin-left:1rem;">
                          <?= $opt['count'] ?> (<?= $analytics['total'] > 0 ? round(($opt['count'] / $analytics['total']) * 100) : 0 ?>%)
                        </span>
                      </div>
                    <?php endforeach; ?>
                    <?php if ($analytics['unanswered'] > 0): ?>
                      <div style="display:flex;justify-content:space-between;margin-top:.5rem;padding:.5rem;background:#fee2e2;border-radius:var(--radius-sm);color:var(--danger);">
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
  </div>
</div>
<?php endif; ?>

<script>
  <?php foreach ($responseAnalytics as $qid => $analytics): ?>
    (function() {
      const ctx = document.getElementById('chart_<?= $qid ?>');
      if (!ctx) return;
      const labels = [<?php foreach ($analytics['options'] as $optId => $opt): ?>'<?= addslashes(substr($opt['option_text'], 0, 30)) ?>',<?php endforeach; ?><?= $analytics['unanswered'] > 0 ? "'Unanswered'," : "" ?>];
      const data = [<?php foreach ($analytics['options'] as $optId => $opt): ?><?= $opt['count'] ?>,<?php endforeach; ?><?= $analytics['unanswered'] > 0 ? $analytics['unanswered'] . ',' : "" ?>];
      const colors = [<?php
        $colorPalette = ['#4facfe', '#ff6b9d', '#ffc107', '#9c88ff', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#fdcb6e', '#a29bfe'];
        $colorIndex = 0;
        foreach ($analytics['options'] as $optId => $opt):
          if ($opt['is_correct']) { echo "'#16A34A',"; }
          else { echo "'" . $colorPalette[$colorIndex % count($colorPalette)] . "',"; $colorIndex++; }
        endforeach;
      ?><?= $analytics['unanswered'] > 0 ? "'#DC2626'," : "" ?>];
      new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors, borderColor: '#fff', borderWidth: 2, hoverOffset: 4 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 15, usePointStyle: true } },
            tooltip: {
              backgroundColor: 'rgba(0,0,0,0.75)', padding: 10,
              callbacks: {
                label: function(ctx) {
                  const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                  return ctx.label + ': ' + (ctx.parsed||0) + ' (' + ((ctx.parsed||0)/total*100).toFixed(1) + '%)';
                }
              }
            }
          }
        }
      });
    })();
  <?php endforeach; ?>

  async function viewStudentAnswers(attemptId, studentName) {
    try {
      const response = await fetch(`../api/student_attempt_details.php?attempt_id=${attemptId}`);
      const data = await response.json();
      if (!data.success) { alert('Failed to load answers: ' + (data.error || 'Unknown error')); return; }
      let html = `
        <div class="modal fade" id="answersModal" tabindex="-1">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Answers — ${escapeHtml(studentName)}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <span class="badge-soft badge-soft-primary">Score: ${data.score} / ${data.total_marks} (${data.percentage}%)</span>
                </div>
      `;
      data.questions.forEach((q, index) => {
        const isCorrect = q.is_correct;
        const statusBadge = q.selected_option
          ? (isCorrect ? '<span class="badge-soft badge-soft-success">Correct</span>' : '<span class="badge-soft badge-soft-danger">Incorrect</span>')
          : '<span class="badge-soft badge-soft-gray">Not Answered</span>';
        html += `
          <div class="mb-3" style="border-left:4px solid ${isCorrect ? '#16A34A' : (q.selected_option ? '#DC2626' : '#9CA3AF')};padding-left:12px;">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="mb-0" style="font-size:13px;">Q${index + 1}. ${escapeHtml(q.question_text)} <span style="color:var(--on-surface-muted);font-weight:400;">(${q.marks} marks)</span></h6>
              ${statusBadge}
            </div>
            <div class="d-flex flex-column gap-1">
        `;
        q.options.forEach(opt => {
          const isSelected = opt.id === q.selected_option;
          const isCorrectOption = opt.is_correct;
          let border = 'var(--border)', bg = 'transparent', icon = '';
          if (isSelected && isCorrectOption)  { border = '#16A34A'; bg = '#f0fdf4'; icon = '<i class="bi bi-check-circle-fill" style="color:#16A34A;"></i>'; }
          else if (isSelected && !isCorrectOption) { border = '#DC2626'; bg = '#fef2f2'; icon = '<i class="bi bi-x-circle-fill" style="color:#DC2626;"></i>'; }
          else if (isCorrectOption)            { border = '#16A34A'; icon = '<i class="bi bi-check-circle" style="color:#16A34A;"></i>'; }
          html += `<div style="border:1px solid ${border};background:${bg};border-radius:6px;padding:6px 10px;font-size:13px;display:flex;align-items:center;gap:8px;">${icon}<span style="font-weight:${isSelected?'600':'400'}">${escapeHtml(opt.option_text)}</span></div>`;
        });
        html += `</div></div>`;
      });
      html += `</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button></div></div></div></div>`;
      const existing = document.getElementById('answersModal');
      if (existing) existing.remove();
      document.body.insertAdjacentHTML('beforeend', html);
      const modal = new bootstrap.Modal(document.getElementById('answersModal'));
      modal.show();
      document.getElementById('answersModal').addEventListener('hidden.bs.modal', function() { this.remove(); });
    } catch (err) {
      console.error(err);
      alert('Failed to load student answers. Please try again.');
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
</script>

<?php render_footer(); ?>
