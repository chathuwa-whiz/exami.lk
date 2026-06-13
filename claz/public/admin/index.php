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
require_once __DIR__ . '/../../src/layout.php';
render_header('Preapproved Students', [], $user);
?>
<?php render_welcome_banner($user); ?>
<?php render_welcome_banner($user); ?>

<!-- Stats Section -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #6b7280; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
      <i class="bi bi-people me-1" style="color: #6b7280;"></i>Total Approved
    </p>
    <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #1f2937;">
      <?= $total ?>
    </p>
    <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #9ca3af;">
      Students in your import
    </p>
  </div>
  <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
      <i class="bi bi-list me-1" style="color: #f59e0b;"></i>Page Size
    </p>
    <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #1f2937;">
      <?= $perPage ?>
    </p>
    <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #9ca3af;">
      Rows per view
    </p>
  </div>
  <div style="padding: 1.5rem; background: white; border-radius: 8px; border-bottom: 3px solid #6b7280; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.9rem; font-weight: 600;">
      <i class="bi bi-archive me-1" style="color: #9ca3af;"></i>Deleted Filter
    </p>
    <p style="margin: 0; font-size: 2rem; font-weight: 700; color: #1f2937;">
      <?= $showDeleted ? 'ON' : 'OFF' ?>
    </p>
    <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #9ca3af;">
      Show archived students
    </p>
  </div>
</div>

<!-- Search and Filter Section -->
<div style="background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
  <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
    <div>
      <label style="display: block; margin-bottom: 0.5rem; color: #6b7280; font-size: 0.9rem; font-weight: 600;">Search</label>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ID or Name" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem;">
    </div>
    <div>
      <label style="display: block; margin-bottom: 0.5rem; color: #6b7280; font-size: 0.9rem; font-weight: 600;">Per Page</label>
      <select name="perPage" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem;">
        <?php foreach($allowedPerPage as $pp): ?>
          <option value="<?= $pp ?>" <?= $pp===$perPage?'selected':'' ?>><?= $pp ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display: block; margin-bottom: 0.5rem; color: #6b7280; font-size: 0.9rem; font-weight: 600;">Sort By</label>
      <select name="sort" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem;">
        <?php foreach($allowedSort as $sKey): ?>
          <option value="<?= $sKey ?>" <?= $sKey===$sort?'selected':'' ?>><?= ucfirst(str_replace('_', ' ', $sKey)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display: block; margin-bottom: 0.5rem; color: #6b7280; font-size: 0.9rem; font-weight: 600;">Direction</label>
      <select name="dir" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem;">
        <option value="asc" <?= $dir==='asc'?'selected':'' ?>>Ascending</option>
        <option value="desc" <?= $dir==='desc'?'selected':'' ?>>Descending</option>
      </select>
    </div>
    <div style="display: flex; align-items: center; gap: 0.5rem;">
      <input type="checkbox" name="showDeleted" value="1" id="toggleDeleted" <?= $showDeleted? 'checked':'' ?> onchange="this.form.submit()">
      <label for="toggleDeleted" style="margin: 0; color: #6b7280; font-size: 0.9rem;">Show deleted</label>
    </div>
    <div style="display: flex; gap: 0.5rem;">
      <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.9rem;">Search</button>
      <?php if($search): ?>
        <a class="btn btn-outline-secondary" href="/admin/index.php" style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.9rem;">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Action Buttons -->
<div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 2rem;">
  <a class="btn btn-outline-primary" href="/admin/import.php" style="font-weight: 600; font-size: 0.9rem;">
    <i class="bi bi-upload me-1"></i>Bulk Import
  </a>
  <a class="btn btn-outline-primary" href="/admin/export_preapproved.php?q=<?= urlencode($search) ?>&perPage=<?= $perPage ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>" target="_blank" rel="noreferrer" style="font-weight: 600; font-size: 0.9rem;">
    <i class="bi bi-download me-1"></i>Export CSV
  </a>
  <a class="btn btn-outline-primary" href="/admin/teachers.php" style="font-weight: 600; font-size: 0.9rem;">
    <i class="bi bi-mortarboard me-1"></i>Teachers
  </a>
  <a class="btn btn-outline-primary" href="/admin/logs.php" style="font-weight: 600; font-size: 0.9rem;">
    <i class="bi bi-journal-text me-1"></i>Audit Logs
  </a>
</div>
<!-- Students Table Section -->
<div style="background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
  <?php foreach($errors as $e): ?>
    <div style="padding: 1rem; background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 6px; margin-bottom: 1rem; color: #7f1d1d; font-size: 0.9rem;">
      <?= htmlspecialchars($e) ?>
    </div>
  <?php endforeach; ?>
  <?php if($success): ?>
    <div style="padding: 1rem; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 6px; margin-bottom: 1rem; color: #166534; font-size: 0.9rem;">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  
  <form method="post" id="bulkForm">
    <?= csrf_field(); ?>
    <div class="table-responsive">
      <table style="width: 100%; font-size: 0.9rem;">
        <thead>
          <tr style="border-bottom: 2px solid #e5e7eb;">
            <th style="padding: 1rem; text-align: left; font-weight: 700; color: #1f2937; width: 50px;">
              <input aria-label="Select all" type="checkbox" onclick="toggleAll(this)">
            </th>
            <th style="padding: 1rem; text-align: left; font-weight: 700; color: #1f2937;">
              ID
              <a href="?q=<?= urlencode($search) ?>&perPage=<?= $perPage ?>&sort=student_id&dir=<?= $sort==='student_id' && $dir==='asc'?'desc':'asc' ?>&showDeleted=<?= $showDeleted ?>" style="margin-left: 0.5rem; color: #6b7280; text-decoration: none; font-weight: 600;">↕</a>
            </th>
            <th style="padding: 1rem; text-align: left; font-weight: 700; color: #1f2937;">
              Name
              <a href="?q=<?= urlencode($search) ?>&perPage=<?= $perPage ?>&sort=name&dir=<?= $sort==='name' && $dir==='asc'?'desc':'asc' ?>&showDeleted=<?= $showDeleted ?>" style="margin-left: 0.5rem; color: #6b7280; text-decoration: none; font-weight: 600;">↕</a>
            </th>
            <th style="padding: 1rem; text-align: left; font-weight: 700; color: #1f2937;">
              Added
              <a href="?q=<?= urlencode($search) ?>&perPage=<?= $perPage ?>&sort=created_at&dir=<?= $sort==='created_at' && $dir==='asc'?'desc':'asc' ?>&showDeleted=<?= $showDeleted ?>" style="margin-left: 0.5rem; color: #6b7280; text-decoration: none; font-weight: 600;">↕</a>
            </th>
            <th style="padding: 1rem; text-align: left; font-weight: 700; color: #1f2937;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($students as $s): ?>
            <tr style="border-bottom: 1px solid #e5e7eb; background-color: <?= $s['is_deleted'] ? '#fee2e2' : 'transparent' ?>;">
              <td style="padding: 1rem; text-align: center;">
                <input type="checkbox" name="bulk_ids[]" value="<?= htmlspecialchars($s['student_id']) ?>">
              </td>
              <td style="padding: 1rem; color: #1f2937; font-weight: 600;">
                <?= htmlspecialchars($s['student_id']) ?>
              </td>
              <td style="padding: 1rem; color: #1f2937;">
                <?= htmlspecialchars($s['name']) ?>
              </td>
              <td style="padding: 1rem; color: #6b7280; font-size: 0.85rem;">
                <?= htmlspecialchars($s['created_at']) ?>
              </td>
              <td style="padding: 1rem;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                  <a class="btn btn-sm btn-outline-primary" href="/admin/edit_student.php?student_id=<?= urlencode($s['student_id']) ?>" style="font-size: 0.8rem;">
                    <i class="bi bi-pencil me-1"></i>Manage
                  </a>
                  <?php if(!$s['is_deleted']): ?>
                    <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_student" value="<?= htmlspecialchars($s['student_id']) ?>" onclick="return confirm('Delete preapproved student?');" style="font-size: 0.8rem;">
                      <i class="bi bi-trash me-1"></i>Delete
                    </button>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline-success" type="submit" name="restore_student" value="<?= htmlspecialchars($s['student_id']) ?>" onclick="return confirm('Restore preapproved student?');" style="font-size: 0.8rem;">
                      <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(empty($students)): ?>
            <tr>
              <td colspan="5" style="padding: 3rem 1rem; text-align: center; color: #6b7280;">
                <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; color: #9ca3af;"></i>
                No records found
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
      <button name="bulk_delete" class="btn btn-danger" style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.9rem;" onclick="return confirm('Delete selected students?');">
        <i class="bi bi-trash me-1"></i>Bulk Delete
      </button>
      <button name="bulk_restore" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-weight: 600; font-size: 0.9rem;" onclick="return confirm('Restore selected students?');">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Bulk Restore
      </button>
    </div>
    
    <p style="margin-top: 1rem; color: #6b7280; font-size: 0.85rem;">
      Page <?= $page ?> of <?= $totalPages ?> (Total <?= $total ?> <?= $showDeleted? '(including deleted)':'' ?>, Showing <?= count($students) ?>)
    </p>
    
    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
      <?php if($page>1): ?>
        <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&page=<?= $page-1 ?>&perPage=<?= $perPage ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&showDeleted=<?= $showDeleted ?>" style="font-weight: 600;">
          <i class="bi bi-chevron-left me-1"></i>Previous
        </a>
      <?php endif; ?>
      <?php if($page<$totalPages): ?>
        <a class="btn btn-outline-secondary btn-sm" href="?q=<?= urlencode($search) ?>&page=<?= $page+1 ?>&perPage=<?= $perPage ?>&sort=<?= urlencode($sort) ?>&dir=<?= urlencode($dir) ?>&showDeleted=<?= $showDeleted ?>" style="font-weight: 600;">
          Next<i class="bi bi-chevron-right ms-1"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Add Student Section -->
<div style="background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
  <h3 style="margin: 0 0 1.5rem 0; font-size: 1.1rem; font-weight: 700; color: #1f2937;">
    <i class="bi bi-plus-circle me-2"></i>Add Preapproved Student
  </h3>
  <form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
    <?= csrf_field(); ?>
    <div>
      <label style="display: block; margin-bottom: 0.5rem; color: #6b7280; font-size: 0.9rem; font-weight: 600;">Student ID</label>
      <input class="form-control" name="student_id" required style="padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; width: 100%;">
    </div>
    <div>
      <label style="display: block; margin-bottom: 0.5rem; color: #6b7280; font-size: 0.9rem; font-weight: 600;">Name</label>
      <input class="form-control" name="student_name" style="padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.9rem; width: 100%;">
    </div>
    <button name="add_student" class="btn btn-primary" style="padding: 0.5rem 1.5rem; font-weight: 600; font-size: 0.9rem; background-color: #3b82f6; border-color: #3b82f6; width: 100%;">
      <i class="bi bi-plus me-1"></i>Add Student
    </button>
  </form>
</div>
<script>
function toggleAll(box){
    document.querySelectorAll('input[name="bulk_ids[]"]').forEach(cb=>cb.checked=box.checked);
}
</script>
<?php render_footer(); ?>