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
<section class="mb-4">
  <div class="row g-3">
    <div class="col-md-4">
      <div class="app-card p-4">
        <p class="text-uppercase small text-muted mb-1">Total teachers</p>
        <h3 class="mb-0"><?= $total ?></h3>
        <p class="muted small mb-0">Directory entries</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4">
        <p class="text-uppercase small text-muted mb-1">Sort column</p>
        <h3 class="mb-0 text-capitalize"><?= htmlspecialchars($sort) ?></h3>
        <p class="muted small mb-0">Current ordering</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="app-card p-4">
        <p class="text-uppercase small text-muted mb-1">Direction</p>
        <h3 class="mb-0 text-uppercase"><?= $dir ?></h3>
        <p class="muted small mb-0">Ascending vs Descending</p>
      </div>
    </div>
  </div>
</section>

<section class="app-card p-4 mb-4">
  <form method="get" class="row g-3 align-items-end" role="search" aria-label="Search teachers">
    <div class="col-sm-6 col-lg-4">
      <label class="form-label small text-uppercase">Query</label>
      <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or code">
    </div>
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small text-uppercase">Sort By</label>
      <select class="form-select" name="sort">
        <?php foreach ($allowedSort as $sKey): ?>
          <option value="<?= $sKey ?>" <?= $sKey === $sort ? 'selected' : '' ?>><?= $sKey ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-6 col-lg-3">
      <label class="form-label small text-uppercase">Direction</label>
      <select class="form-select" name="dir">
        <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>Ascending</option>
        <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>Descending</option>
      </select>
    </div>
    <div class="col-12 d-flex flex-wrap gap-2">
      <button class="btn btn-primary" type="submit">Search</button>
      <?php if ($search): ?><a class="btn btn-outline-secondary" href="<?= htmlspecialchars(app_href('admin/teachers.php')) ?>">Clear</a><?php endif; ?>
      <a class="btn btn-outline-primary" href="<?= htmlspecialchars(app_href('admin/index.php')) ?>">Back to Preapproved</a>
    </div>
  </form>
</section>

<section class="app-card p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h2 class="h5 mb-0">Teacher Directory</h2>
    <span class="muted small">Page <?= $page ?> of <?= $totalPages ?></span>
  </div>
  <div class="data-table table-responsive" tabindex="0" role="region" aria-labelledby="teacher-table-caption">
    <table class="table align-middle mb-0" aria-describedby="teacher-count" aria-labelledby="teacher-table-caption">
      <caption id="teacher-table-caption" class="visually-hidden">Teacher directory sorted by <?= htmlspecialchars($sortLabel) ?> in <?= $dirReadable ?> order.</caption>
      <thead>
        <tr>
          <th scope="col">Photo</th>
          <th scope="col">ID</th>
          <th scope="col" aria-sort="<?= $sort === 'name' ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
            Name
            <a class="code" aria-label="Change sort for Name" href="?q=<?= urlencode($search) ?>&sort=name&dir=<?= $sort==='name' && $dir==='asc'?'desc':'asc' ?>">↕</a>
          </th>
          <th scope="col" aria-sort="<?= $sort === 'teacher_code' ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
            Code
            <a class="code" aria-label="Change sort for Code" href="?q=<?= urlencode($search) ?>&sort=teacher_code&dir=<?= $sort==='teacher_code' && $dir==='asc'?'desc':'asc' ?>">↕</a>
          </th>
          <th scope="col">Email</th>
          <th scope="col" aria-sort="<?= $sort === 'created_at' ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
            Joined
            <a class="code" aria-label="Change sort for Joined" href="?q=<?= urlencode($search) ?>&sort=created_at&dir=<?= $sort==='created_at' && $dir==='asc'?'desc':'asc' ?>">↕</a>
          </th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($teachers as $t): ?>
        <tr>
          <td>
            <?php if (!empty($t['profile_image'])): ?>
              <img src="<?= app_href($t['profile_image']) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
            <?php else: ?>
              <?php $initial = strtoupper(substr($t['name'] ?? 'T', 0, 1)); ?>
              <div style="width:40px;height:40px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;font-weight:600;">
                <?= htmlspecialchars($initial) ?>
              </div>
            <?php endif; ?>
          </td>
          <td><?= $t['id'] ?></td>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><span class="badge-soft"><?= htmlspecialchars($t['teacher_code']) ?></span></td>
          <td><?= htmlspecialchars($t['email']) ?></td>
          <td><?= htmlspecialchars($t['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($teachers)): ?>
        <tr><td colspan="5" class="text-center py-4 muted">No teachers found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <nav class="d-flex flex-wrap gap-2 align-items-center mt-3" aria-label="Pagination">
    <?php if ($page > 1): ?>
      <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&page=<?= $page-1 ?>">&laquo; Prev</a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&page=<?= $page+1 ?>">Next &raquo;</a>
    <?php endif; ?>
    <span id="teacher-count" class="muted small">Showing <?= count($teachers) ?> of <?= $total ?> records</span>
  </nav>
</section>
<?php render_footer(); ?>