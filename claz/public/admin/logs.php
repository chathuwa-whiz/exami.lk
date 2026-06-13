<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'admin') { http_response_code(403); echo 'Forbidden'; exit; }
$pdo = db();
$action = trim($_GET['action'] ?? '');
$actor = (int)($_GET['user_id'] ?? 0);
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50; $offset = ($page - 1) * $perPage;
$whereParts = []; $params = [];
if ($action !== '') { $whereParts[] = 'action LIKE ?'; $params[] = '%'.$action.'%'; }
if ($actor > 0) { $whereParts[] = 'user_id=?'; $params[] = $actor; }
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $whereParts[] = 'created_at >= ?'; $params[] = $from.' 00:00:00'; }
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $whereParts[] = 'created_at <= ?'; $params[] = $to.' 23:59:59'; }
$where = $whereParts ? ('WHERE '.implode(' AND ', $whereParts)) : '';
$countSql = "SELECT COUNT(*) FROM audit_logs $where";
$countStmt = $pdo->prepare($countSql); $countStmt->execute($params); $total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
$sql = "SELECT al.id,al.user_id,al.action,al.details,al.created_at,u.name FROM audit_logs al JOIN users u ON al.user_id=u.id $where ORDER BY al.created_at DESC LIMIT $offset,$perPage";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $logs = $stmt->fetchAll();
render_header('Audit Logs');
?>
<section class="mb-4">
  <div class="row g-3">
    <div class="col-md-4">
      <div class="app-card p-4">
        <p class="text-uppercase small text-muted mb-1">Total entries</p>
        <h3 class="mb-0"><?= $total ?></h3>
        <p class="muted small mb-0">Filtered result count</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4">
        <p class="text-uppercase small text-muted mb-1">Page</p>
        <h3 class="mb-0"><?= $page ?> / <?= $totalPages ?></h3>
        <p class="muted small mb-0">Page navigation state</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4">
        <p class="text-uppercase small text-muted mb-1">Filters active</p>
        <h3 class="mb-0"><?= ($action || $actor || $from || $to) ? 'Yes' : 'No' ?></h3>
        <p class="muted small mb-0">Action / actor / date</p>
      </div>
    </div>
  </div>
</section>

<section class="app-card p-4 mb-4">
  <form method="get" class="row g-3 align-items-end" role="search" aria-label="Filter logs">
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small text-uppercase">Action contains</label>
      <input type="text" class="form-control" name="action" placeholder="Contains" value="<?= htmlspecialchars($action) ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small text-uppercase">Actor ID</label>
      <input type="number" class="form-control" name="user_id" placeholder="ID" value="<?= $actor>0?$actor:'' ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small text-uppercase">From</label>
      <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small text-uppercase">To</label>
      <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-12 d-flex flex-wrap gap-2">
      <button class="btn btn-primary" type="submit">Filter</button>
      <?php if ($action || $actor || $from || $to): ?><a class="btn btn-outline-secondary" href="<?= htmlspecialchars(app_href('admin/logs.php')) ?>">Clear</a><?php endif; ?>
      <a class="btn btn-outline-primary" href="<?= htmlspecialchars(app_href('admin/index.php')) ?>">Back to Admin</a>
    </div>
  </form>
</section>

<section class="app-card p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h2 class="h5 mb-0">Audit Log Entries</h2>
    <span class="muted small">Latest first · <?= $perPage ?> per page</span>
  </div>
  <div class="data-table table-responsive" tabindex="0" role="region" aria-labelledby="logs-table-caption">
    <table class="table align-middle mb-0" aria-labelledby="logs-table-caption">
      <caption id="logs-table-caption" class="visually-hidden">Audit log entries filtered by current criteria and ordered by most recent first.</caption>
      <thead>
        <tr>
          <th scope="col">ID</th>
          <th scope="col">User</th>
          <th scope="col">Action</th>
          <th scope="col">Details</th>
          <th scope="col">When</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= $l['id'] ?></td>
          <td><?= htmlspecialchars($l['name']) ?> <span class="muted small">(<?= $l['user_id'] ?>)</span></td>
          <td><span class="badge-soft"><?= htmlspecialchars($l['action']) ?></span></td>
          <td style="max-width:380px;white-space:pre-wrap;"><?= htmlspecialchars($l['details']) ?></td>
          <td><?= htmlspecialchars($l['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5" class="text-center py-4 muted">No logs.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <nav class="d-flex flex-wrap gap-2 align-items-center mt-3" aria-label="Pagination">
    <?php if ($page > 1): ?>
      <a class="btn btn-outline-secondary btn-sm" href="?action=<?= urlencode($action) ?>&user_id=<?= $actor ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&page=<?= $page-1 ?>">&laquo; Prev</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="btn btn-outline-secondary btn-sm" href="?action=<?= urlencode($action) ?>&user_id=<?= $actor ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&page=<?= $page+1 ?>">Next &raquo;</a>
    <?php endif; ?>
    <span class="muted small">Showing <?= count($logs) ?> of <?= $total ?> entries</span>
  </nav>
</section>
<?php render_footer(); ?>