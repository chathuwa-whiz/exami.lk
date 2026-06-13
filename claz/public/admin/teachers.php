<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'admin') { http_response_code(403); echo 'Forbidden'; exit; }
$pdo = db();
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$allowedSort = ['name','teacher_code','created_at'];
$sort = $_GET['sort'] ?? 'name';
if (!in_array($sort, $allowedSort, true)) { $sort = 'name'; }
$dir = strtolower($_GET['dir'] ?? 'asc');
if (!in_array($dir, ['asc','desc'], true)) { $dir = 'asc'; }
$perPage = 25; $offset = ($page - 1) * $perPage;
$sortLabel = ucfirst(str_replace('_', ' ', $sort));
$dirReadable = $dir === 'asc' ? 'ascending' : 'descending';
$where = 'user_type="teacher"'; $params = [];
if ($search !== '') { $where .= ' AND (name LIKE ? OR teacher_code LIKE ?)'; $like = '%'.$search.'%'; $params[] = $like; $params[] = $like; }
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where"); $countStmt->execute($params); $total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
$sql = "SELECT id,name,teacher_code,email,created_at,profile_image FROM users WHERE $where ORDER BY $sort $dir, id ASC LIMIT $offset,$perPage";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $teachers = $stmt->fetchAll();
render_header('Teachers');
?>

<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-person-badge"></i></div>
    <div>
      <p class="stat-card-label">Total Teachers</p>
      <p class="stat-card-value"><?= $total ?></p>
      <p class="stat-card-sub">Directory entries</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-file-earmark-text"></i></div>
    <div>
      <p class="stat-card-label">Page</p>
      <p class="stat-card-value"><?= $page ?> / <?= $totalPages ?></p>
      <p class="stat-card-sub">Showing <?= count($teachers) ?> of <?= $total ?></p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon gray"><i class="bi bi-arrow-down-up"></i></div>
    <div>
      <p class="stat-card-label">Sorted By</p>
      <p class="stat-card-value" style="font-size:.95rem;"><?= htmlspecialchars($sortLabel) ?></p>
      <p class="stat-card-sub"><?= $dir === 'asc' ? 'Ascending' : 'Descending' ?></p>
    </div>
  </div>
</div>

<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-search"></i> Search & Filter</h2>
  </div>
  <div class="app-card-body">
    <form method="get" class="row g-3 align-items-end" role="search" aria-label="Search teachers">
      <div class="col-sm-6 col-lg-4">
        <label class="form-label">Search</label>
        <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name or teacher code">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label">Sort By</label>
        <select class="form-select" name="sort">
          <?php foreach ($allowedSort as $sKey): ?>
            <option value="<?= $sKey ?>" <?= $sKey === $sort ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $sKey)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label">Direction</label>
        <select class="form-select" name="dir">
          <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Ascending</option>
          <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Descending</option>
        </select>
      </div>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
        <?php if ($search): ?>
          <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(app_href('admin/teachers.php')) ?>"><i class="bi bi-x"></i> Clear</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(app_href('admin/index.php')) ?>"><i class="bi bi-arrow-left"></i> Students</a>
      </div>
    </form>
  </div>
</div>

<div class="app-card">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-people text-primary"></i> Teacher Directory</h2>
    <span class="badge-soft badge-soft-gray">Page <?= $page ?> of <?= $totalPages ?></span>
  </div>
  <div class="app-card-body p-0">
    <div class="data-table table-responsive" tabindex="0" role="region" aria-labelledby="teacher-table-caption">
      <table class="table align-middle mb-0" aria-describedby="teacher-count" aria-labelledby="teacher-table-caption">
        <caption id="teacher-table-caption" class="visually-hidden">Teacher directory sorted by <?= htmlspecialchars($sortLabel) ?> in <?= $dirReadable ?> order.</caption>
        <thead>
          <tr>
            <th scope="col" style="width:52px;"></th>
            <th scope="col">ID</th>
            <th scope="col" aria-sort="<?= $sort === 'name' ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
              Name
              <a class="ms-1" style="opacity:.5;text-decoration:none;" aria-label="Sort by Name" href="?q=<?= urlencode($search) ?>&sort=name&dir=<?= $sort==='name' && $dir==='asc'?'desc':'asc' ?>"><i class="bi bi-arrow-down-up" style="font-size:11px;"></i></a>
            </th>
            <th scope="col" aria-sort="<?= $sort === 'teacher_code' ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
              Code
              <a class="ms-1" style="opacity:.5;text-decoration:none;" aria-label="Sort by Code" href="?q=<?= urlencode($search) ?>&sort=teacher_code&dir=<?= $sort==='teacher_code' && $dir==='asc'?'desc':'asc' ?>"><i class="bi bi-arrow-down-up" style="font-size:11px;"></i></a>
            </th>
            <th scope="col">Email</th>
            <th scope="col" aria-sort="<?= $sort === 'created_at' ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
              Joined
              <a class="ms-1" style="opacity:.5;text-decoration:none;" aria-label="Sort by Joined" href="?q=<?= urlencode($search) ?>&sort=created_at&dir=<?= $sort==='created_at' && $dir==='asc'?'desc':'asc' ?>"><i class="bi bi-arrow-down-up" style="font-size:11px;"></i></a>
            </th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($teachers as $t): ?>
          <tr>
            <td>
              <?php if (!empty($t['profile_image'])): ?>
                <img src="<?= app_href($t['profile_image']) ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
              <?php else: ?>
                <div class="sidebar-avatar" style="width:36px;height:36px;font-size:13px;border-radius:50%;">
                  <?= htmlspecialchars(strtoupper(substr($t['name'] ?? 'T', 0, 1))) ?>
                </div>
              <?php endif; ?>
            </td>
            <td style="color:var(--on-surface-muted);font-size:13px;"><?= $t['id'] ?></td>
            <td style="font-weight:500;"><?= htmlspecialchars($t['name']) ?></td>
            <td><span class="badge-soft badge-soft-primary"><?= htmlspecialchars($t['teacher_code']) ?></span></td>
            <td style="font-size:13px;color:var(--on-surface-muted);"><?= htmlspecialchars($t['email']) ?></td>
            <td style="font-size:13px;color:var(--on-surface-muted);"><?= htmlspecialchars(date('M d, Y', strtotime($t['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($teachers)): ?>
          <tr><td colspan="6" class="text-center py-5" style="color:var(--muted-light);">No teachers found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
      <div class="d-flex flex-wrap gap-2 align-items-center px-4 py-3" style="border-top:1px solid var(--border);">
        <?php if ($page > 1): ?>
          <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&page=<?= $page-1 ?>"><i class="bi bi-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&page=<?= $page+1 ?>">Next <i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
        <span id="teacher-count" style="font-size:13px;color:var(--on-surface-muted);">Showing <?= count($teachers) ?> of <?= $total ?> records</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
