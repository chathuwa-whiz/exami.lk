<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'admin') { http_response_code(403); echo 'Forbidden'; exit; }
$pdo = db();
$errors=[];$success='';
$showDeleted = (int)($_GET['showDeleted'] ?? 0);
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!csrf_verify()) { $errors[]='Bad CSRF'; }
    else {
        if(isset($_POST['bulk_delete'])) {
            $ids = $_POST['bulk_ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                $errors[] = 'No students selected for bulk delete.';
            } else {
                $deletedCount = 0;
                $delStmt = $pdo->prepare('UPDATE preapproved_students SET is_deleted=1 WHERE student_id=?');
                $logStmt = $pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
                foreach ($ids as $bid) {
                    $bidTrim = trim($bid);
                    if ($bidTrim === '') continue;
                    $delStmt->execute([$bidTrim]);
                    $logStmt->execute([$user['id'],'bulk_delete_preapproved',json_encode(['student_id'=>$bidTrim])]);
                    $deletedCount++;
                }
                $success = 'Bulk deleted ' . $deletedCount . ' students.';
            }
        }
        if(isset($_POST['bulk_restore'])) {
            $ids = $_POST['bulk_ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                $errors[] = 'No students selected for bulk restore.';
            } else {
                $restoredCount = 0;
                $resStmt = $pdo->prepare('UPDATE preapproved_students SET is_deleted=0 WHERE student_id=?');
                $logStmt = $pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
                foreach ($ids as $bid) {
                    $bidTrim = trim($bid);
                    if ($bidTrim === '') continue;
                    $resStmt->execute([$bidTrim]);
                    $logStmt->execute([$user['id'],'bulk_restore_preapproved',json_encode(['student_id'=>$bidTrim])]);
                    $restoredCount++;
                }
                $success = 'Bulk restored ' . $restoredCount . ' students.';
            }
        }
        if(isset($_POST['add_student'])) {
            $sid=trim($_POST['student_id']??'');
            $sname=trim($_POST['student_name']??'');
            if($sid==='') $errors[]='Student ID required';
            else {
                try {
                    $stmt=$pdo->prepare('INSERT INTO preapproved_students (student_id,name) VALUES (?,?)');
                    $stmt->execute([$sid,$sname]);
                    $log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
                    $log->execute([$user['id'],'add_preapproved',json_encode(['student_id'=>$sid])]);
                    $success='Preapproved student added.';
                } catch(Exception $e){ $errors[]='Add failed: '.$e->getMessage(); }
            }
        }
        if(isset($_POST['delete_student'])) {
            $sid=trim($_POST['student_id']??'');
            if($sid==='') { $sid = trim($_POST['delete_student']); }
            if($sid!=='') {
                $del=$pdo->prepare('UPDATE preapproved_students SET is_deleted=1 WHERE student_id=?');
                $del->execute([$sid]);
                $log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
                $log->execute([$user['id'],'delete_preapproved',json_encode(['student_id'=>$sid])]);
                $success='Preapproved student deleted.';
            }
        }
        if(isset($_POST['restore_student'])) {
            $sid=trim($_POST['restore_student']);
            if($sid!=='') {
                $res=$pdo->prepare('UPDATE preapproved_students SET is_deleted=0 WHERE student_id=?');
                $res->execute([$sid]);
                $log=$pdo->prepare('INSERT INTO audit_logs (user_id,action,details) VALUES (?,?,?)');
                $log->execute([$user['id'],'restore_preapproved',json_encode(['student_id'=>$sid])]);
                $success='Preapproved student restored.';
            }
        }
    }
}
// Pagination & search
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$allowedPerPage = [25,50,100];
$perPage = (int)($_GET['perPage'] ?? ($_SESSION['admin_perPage'] ?? 25));
if (!in_array($perPage, $allowedPerPage, true)) { $perPage = 25; }
// Sorting
$allowedSort = ['student_id','name','created_at'];
$sort = $_GET['sort'] ?? ($_SESSION['admin_sort'] ?? 'created_at');
if (!in_array($sort, $allowedSort, true)) { $sort = 'created_at'; }
$dir = strtolower($_GET['dir'] ?? ($_SESSION['admin_dir'] ?? 'desc'));
if (!in_array($dir, ['asc','desc'], true)) { $dir = 'desc'; }
$sortLabel = ucfirst(str_replace('_', ' ', $sort));
$dirReadable = $dir === 'asc' ? 'ascending' : 'descending';

// Persist user preferences in session
$_SESSION['admin_perPage'] = $perPage;
$_SESSION['admin_sort'] = $sort;
$_SESSION['admin_dir'] = $dir;

// Dynamic conditions
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(student_id LIKE ? OR name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
}
if (!$showDeleted) {
    $conditions[] = 'is_deleted=0';
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// Count total (respecting filter of deleted if not showing them)
$countSql = "SELECT COUNT(*) FROM preapproved_students $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

// Fetch page
$orderClause = "$sort $dir, student_id ASC"; // secondary deterministic sort
$sql = "SELECT student_id,name,created_at,is_deleted FROM preapproved_students $where ORDER BY $orderClause LIMIT $offset,$perPage";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
render_header('Preapproved Students', [], $user);
?>
<?php render_welcome_banner($user); ?>

<?php foreach($errors as $e): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php if($success): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="stat-cards">
  <div class="stat-card">
    <div class="stat-card-icon blue"><i class="bi bi-people-fill"></i></div>
    <div>
      <p class="stat-card-label">Total Approved</p>
      <p class="stat-card-value"><?= $total ?></p>
      <p class="stat-card-sub">Students in list</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon yellow"><i class="bi bi-list-ul"></i></div>
    <div>
      <p class="stat-card-label">Page Size</p>
      <p class="stat-card-value"><?= $perPage ?></p>
      <p class="stat-card-sub">Rows per view</p>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-icon <?= $showDeleted ? 'yellow' : 'gray' ?>"><i class="bi bi-archive"></i></div>
    <div>
      <p class="stat-card-label">Deleted Filter</p>
      <p class="stat-card-value"><?= $showDeleted ? 'ON' : 'OFF' ?></p>
      <p class="stat-card-sub">Show archived</p>
    </div>
  </div>
</div>

<!-- Search & Filter -->
<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-search text-primary"></i> Search &amp; Filter</h2>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary btn-sm" href="<?= app_href('admin/import.php') ?>"><i class="bi bi-upload"></i> Import</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= app_href('admin/export_preapproved.php?q=' . urlencode($search) . '&perPage=' . $perPage . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)) ?>" target="_blank"><i class="bi bi-download"></i> CSV</a>
    </div>
  </div>
  <div class="app-card-body">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="ID or Name">
      </div>
      <div class="col-md-2">
        <label class="form-label">Per Page</label>
        <select name="perPage" class="form-select">
          <?php foreach($allowedPerPage as $pp): ?>
            <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Sort By</label>
        <select name="sort" class="form-select">
          <?php foreach($allowedSort as $sKey): ?>
            <option value="<?= $sKey ?>" <?= $sKey===$sort?'selected':'' ?>><?= ucfirst(str_replace('_', ' ', $sKey)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Direction</label>
        <select name="dir" class="form-select">
          <option value="asc"  <?= $dir==='asc' ?'selected':'' ?>>Ascending</option>
          <option value="desc" <?= $dir==='desc'?'selected':'' ?>>Descending</option>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end gap-2">
        <div class="form-check mb-1">
          <input type="checkbox" class="form-check-input" name="showDeleted" value="1" id="toggleDeleted" <?= $showDeleted? 'checked':'' ?> onchange="this.form.submit()">
          <label class="form-check-label" for="toggleDeleted" style="font-size:13px;">Show deleted</label>
        </div>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
        <?php if($search): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= app_href('admin/index.php') ?>">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Students Table -->
<div class="app-card mb-4">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-table text-primary"></i> Student Records</h2>
    <span class="badge-soft badge-soft-gray">Page <?= $page ?> of <?= $totalPages ?> &bull; <?= $total ?> total</span>
  </div>
  <div class="app-card-body no-pad">
    <form method="post" id="bulkForm">
      <?= csrf_field(); ?>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead style="background:var(--surface-subtle);">
            <tr>
              <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);width:40px;">
                <input aria-label="Select all" type="checkbox" onclick="toggleAll(this)">
              </th>
              <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);">ID <a href="?q=<?= urlencode($search) ?>&perPage=<?= $perPage ?>&sort=student_id&dir=<?= $sort==='student_id' && $dir==='asc'?'desc':'asc' ?>&showDeleted=<?= $showDeleted ?>" style="color:var(--muted);">↕</a></th>
              <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);">Name <a href="?q=<?= urlencode($search) ?>&perPage=<?= $perPage ?>&sort=name&dir=<?= $sort==='name' && $dir==='asc'?'desc':'asc' ?>&showDeleted=<?= $showDeleted ?>" style="color:var(--muted);">↕</a></th>
              <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);">Added <a href="?q=<?= urlencode($search) ?>&perPage=<?= $perPage ?>&sort=created_at&dir=<?= $sort==='created_at' && $dir==='asc'?'desc':'asc' ?>&showDeleted=<?= $showDeleted ?>" style="color:var(--muted);">↕</a></th>
              <th style="padding:10px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($students as $s): ?>
              <tr style="<?= $s['is_deleted'] ? 'background:var(--danger-bg);' : '' ?>">
                <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);">
                  <input type="checkbox" name="bulk_ids[]" value="<?= htmlspecialchars($s['student_id']) ?>">
                </td>
                <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);font-weight:600;color:var(--on-surface);font-size:13px;"><?= htmlspecialchars($s['student_id']) ?></td>
                <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);font-size:13px;color:var(--on-surface);"><?= htmlspecialchars($s['name']) ?></td>
                <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);font-size:12px;color:var(--muted);"><?= htmlspecialchars($s['created_at']) ?></td>
                <td style="padding:11px 16px;vertical-align:middle;border-bottom:1px solid var(--border);">
                  <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= app_href('admin/edit_student.php?student_id=' . urlencode($s['student_id'])) ?>"><i class="bi bi-pencil"></i> Manage</a>
                    <?php if(!$s['is_deleted']): ?>
                      <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_student" value="<?= htmlspecialchars($s['student_id']) ?>" onclick="return confirm('Delete preapproved student?');"><i class="bi bi-trash"></i></button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-success" type="submit" name="restore_student" value="<?= htmlspecialchars($s['student_id']) ?>" onclick="return confirm('Restore preapproved student?');"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($students)): ?>
              <tr><td colspan="5" class="text-center py-5" style="color:var(--muted-light);"><i class="bi bi-inbox" style="display:block;font-size:2rem;margin-bottom:8px;"></i>No records found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 p-3" style="border-top:1px solid var(--border);">
        <button name="bulk_delete"  class="btn btn-danger btn-sm"     onclick="return confirm('Delete selected students?');"><i class="bi bi-trash"></i> Bulk Delete</button>
        <button name="bulk_restore" class="btn btn-secondary btn-sm"  onclick="return confirm('Restore selected students?');"><i class="bi bi-arrow-counterclockwise"></i> Bulk Restore</button>
      </div>
      <div class="d-flex gap-2 px-4 pb-3 pt-1">
        <?php if($page>1): ?>
          <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&page=<?= $page-1 ?>&perPage=<?= $perPage ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&showDeleted=<?= $showDeleted ?>"><i class="bi bi-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <?php if($page<$totalPages): ?>
          <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&page=<?= $page+1 ?>&perPage=<?= $perPage ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&showDeleted=<?= $showDeleted ?>">Next <i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
        <span style="font-size:12px;color:var(--muted);align-self:center;">Showing <?= count($students) ?> of <?= $total ?></span>
      </div>
    </form>
  </div>
</div>

<!-- Add Student -->
<div class="app-card">
  <div class="app-card-header">
    <h2 class="app-card-title"><i class="bi bi-plus-circle text-primary"></i> Add Preapproved Student</h2>
  </div>
  <div class="app-card-body">
    <form method="post" class="row g-3 align-items-end">
      <?= csrf_field(); ?>
      <div class="col-md-4">
        <label class="form-label">Student ID</label>
        <input class="form-control" name="student_id" required placeholder="e.g., 20240001">
      </div>
      <div class="col-md-4">
        <label class="form-label">Name</label>
        <input class="form-control" name="student_name" placeholder="Full name">
      </div>
      <div class="col-md-4">
        <button name="add_student" class="btn btn-primary w-100"><i class="bi bi-plus"></i> Add Student</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleAll(box){
    document.querySelectorAll('input[name="bulk_ids[]"]').forEach(cb=>cb.checked=box.checked);
}
</script>
<?php render_footer(); ?>