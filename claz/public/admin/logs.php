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
$filtersActive = ($action || $actor || $from || $to);
render_header('Audit Logs');
?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-journal-text"></i></div>
    <div>
      <p class="stat-card-label">Total Entries</p>
      <p class="stat-card-value"><?= $total ?></p>
      <p class="stat-card-sub">Filtered result count</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-book"></i></div>
    <div>
      <p class="stat-card-label">Page</p>
      <p class="stat-card-value"><?= $page ?> / <?= $totalPages ?></p>
      <p class="stat-card-sub"><?= $perPage ?> entries per page</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon <?= $filtersActive ? 'yellow' : 'gray' ?>"><i class="bi bi-funnel<?= $filtersActive ? '-fill' : '' ?>"></i></div>
    <div>
      <p class="stat-card-label">Filters</p>
      <p class="stat-card-value"><?= $filtersActive ? 'Active' : 'None' ?></p>
      <p class="stat-card-sub">Action / actor / date range</p>
    </div>
  </div>
</div>

<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-funnel"></i> Filter Logs</h2>
    <?php if ($filtersActive): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(app_href('admin/logs.php')) ?>"><i class="bi bi-x"></i> Clear Filters</a>
    <?php endif; ?>
  </div>
  <div class="app-card-body">
    <form method="get" class="row g-3 align-items-end" role="search" aria-label="Filter logs">
      <div class="col-sm-6 col-lg-3">
        <label class="form-label">Action contains</label>
        <input type="text" class="form-control" name="action" placeholder="e.g. add_preapproved" value="<?= htmlspecialchars($action) ?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label">Actor User ID</label>
        <input type="number" class="form-control" name="user_id" placeholder="User ID" value="<?= $actor > 0 ? $actor : '' ?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label">From Date</label>
        <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label">To Date</label>
        <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>">
      </div>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Apply Filter</button>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(app_href('admin/index.php')) ?>"><i class="bi bi-arrow-left"></i> Students</a>
      </div>
    </form>
  </div>
</div>

<div class="app-card">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-journal-text text-primary"></i> Audit Log Entries</h2>
    <span class="badge-soft badge-soft-gray">Latest first</span>
  </div>
  <div class="app-card-body p-0">
    <div class="data-table table-responsive" tabindex="0" role="region" aria-labelledby="logs-table-caption">
      <table class="table align-middle mb-0" aria-labelledby="logs-table-caption">
        <caption id="logs-table-caption" class="visually-hidden">Audit log entries filtered by current criteria and ordered by most recent first.</caption>
        <thead>
          <tr>
            <th scope="col">#</th>
            <th scope="col">User</th>
            <th scope="col">Action</th>
            <th scope="col">Details</th>
            <th scope="col">When</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td style="color:var(--on-surface-muted);font-size:12px;"><?= $l['id'] ?></td>
            <td>
              <span style="font-weight:500;"><?= htmlspecialchars($l['name']) ?></span>
              <span style="color:var(--on-surface-muted);font-size:12px;margin-left:4px;">#<?= $l['user_id'] ?></span>
            </td>
            <td><span class="badge-soft badge-soft-primary"><?= htmlspecialchars($l['action']) ?></span></td>
            <td style="max-width:360px;white-space:pre-wrap;font-size:12px;color:var(--on-surface-muted);"><?= htmlspecialchars($l['details']) ?></td>
            <td style="font-size:12px;color:var(--on-surface-muted);white-space:nowrap;"><?= htmlspecialchars($l['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5" class="text-center py-5" style="color:var(--muted-light);">No log entries found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
      <div class="d-flex flex-wrap gap-2 align-items-center px-4 py-3" style="border-top:1px solid var(--border);">
        <?php if ($page > 1): ?>
          <a class="btn btn-outline-secondary btn-sm" href="?action=<?= urlencode($action) ?>&user_id=<?= $actor ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&page=<?= $page-1 ?>"><i class="bi bi-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="btn btn-outline-secondary btn-sm" href="?action=<?= urlencode($action) ?>&user_id=<?= $actor ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&page=<?= $page+1 ?>">Next <i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
        <span style="font-size:13px;color:var(--on-surface-muted);">Showing <?= count($logs) ?> of <?= $total ?> entries</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
