php -r "$p=password_hash('NewStrongPass1!', PASSWORD_DEFAULT); echo $p;"php -r "$p=password_hash('NewStrongPass1!', PASSWORD_DEFAULT); echo $p;"php -r "$p=password_hash('NewStrongPass1!', PASSWORD_DEFAULT); echo $p;"<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();

$user = current_user();
if ($user['user_type'] !== 'admin') { 
    http_response_code(403); 
    echo 'Forbidden'; 
    exit; 
}

$pdo = db();
$success = '';
$error = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $requestId = (int)$_POST['request_id'];
    $newStatus = $_POST['status'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    if (in_array($newStatus, ['approved', 'completed', 'rejected'], true)) {
        try {
            $updateStmt = $pdo->prepare('UPDATE payout_requests SET status = ?, admin_notes = ?, processed_at = NOW(), processed_by = ? WHERE id = ?');
            $updateStmt->execute([$newStatus, $adminNotes, $user['id'], $requestId]);
            $success = 'Payout request updated successfully.';
        } catch (Exception $e) {
            $error = 'Failed to update request: ' . $e->getMessage();
        }
    }
}

// Fetch all payout requests
$statusFilter = $_GET['status'] ?? 'all';
$whereClause = $statusFilter !== 'all' ? 'WHERE pr.status = ?' : '';
$stmt = $pdo->prepare("
    SELECT 
        pr.id,
        pr.amount_cents,
        pr.status,
        pr.bank_details,
        pr.admin_notes,
        pr.requested_at,
        pr.processed_at,
        u.name AS teacher_name,
        u.email AS teacher_email,
        u.teacher_code,
        pa.name AS processed_by_name
    FROM payout_requests pr
    INNER JOIN users u ON u.id = pr.teacher_id
    LEFT JOIN users pa ON pa.id = pr.processed_by
    $whereClause
    ORDER BY 
        CASE pr.status 
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'rejected' THEN 4
        END,
        pr.requested_at DESC
");

if ($statusFilter !== 'all') {
    $stmt->execute([$statusFilter]);
} else {
    $stmt->execute();
}

$requests = $stmt->fetchAll();

// Get statistics
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'completed' THEN amount_cents ELSE 0 END) AS total_paid_cents
    FROM payout_requests
");
$stats = $statsStmt->fetch();

render_header('Payout Requests');
?>

<div class="mb-4">
    <h2 class="h4 mb-3">Payout Request Management</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="app-card p-3 shadow-sm" style="border-left: 4px solid #ffc107;">
                <p class="text-muted small mb-1">Pending Requests</p>
                <h3 class="mb-0" style="color: #ffc107;"><?= (int)$stats['pending'] ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card p-3 shadow-sm" style="border-left: 4px solid #17a2b8;">
                <p class="text-muted small mb-1">Approved</p>
                <h3 class="mb-0" style="color: #17a2b8;"><?= (int)$stats['approved'] ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card p-3 shadow-sm" style="border-left: 4px solid #28a745;">
                <p class="text-muted small mb-1">Completed</p>
                <h3 class="mb-0" style="color: #28a745;"><?= (int)$stats['completed'] ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="app-card p-3 shadow-sm" style="border-left: 4px solid #667eea;">
                <p class="text-muted small mb-1">Total Paid Out</p>
                <h3 class="mb-0" style="color: #667eea;"><?= number_format(($stats['total_paid_cents'] ?? 0) / 100, 2) ?> LKR</h3>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-check-circle-fill"></i>
    <span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="mb-3">
    <div class="btn-group" role="group">
        <a href="?status=all" class="btn btn-sm btn-outline-primary <?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
        <a href="?status=pending" class="btn btn-sm btn-outline-warning <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?status=approved" class="btn btn-sm btn-outline-info <?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
        <a href="?status=completed" class="btn btn-sm btn-outline-success <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="?status=rejected" class="btn btn-sm btn-outline-danger <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
    </div>
</div>

<!-- Requests Table -->
<div class="app-card p-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Teacher</th>
                    <th>Amount</th>
                    <th>Bank Details</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Processed By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No payout requests found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><strong>#<?= $req['id'] ?></strong></td>
                        <td>
                            <div><?= htmlspecialchars($req['teacher_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($req['teacher_email']) ?></small><br>
                            <small class="text-muted">Code: <?= htmlspecialchars($req['teacher_code']) ?></small>
                        </td>
                        <td><strong><?= number_format($req['amount_cents'] / 100, 2) ?> LKR</strong></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" onclick="showBankDetails(<?= $req['id'] ?>)">
                                <i class="bi bi-bank"></i> View Details
                            </button>
                            <div id="bank-<?= $req['id'] ?>" style="display:none;" class="mt-2 small">
                                <pre class="mb-0 text-wrap"><?= htmlspecialchars($req['bank_details']) ?></pre>
                            </div>
                        </td>
                        <td>
                            <?php
                            $badges = [
                                'pending' => 'warning',
                                'approved' => 'info',
                                'completed' => 'success',
                                'rejected' => 'danger'
                            ];
                            $badgeClass = $badges[$req['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($req['status']) ?></span>
                        </td>
                        <td>
                            <small><?= date('M d, Y', strtotime($req['requested_at'])) ?></small><br>
                            <small class="text-muted"><?= date('h:i A', strtotime($req['requested_at'])) ?></small>
                        </td>
                        <td>
                            <?php if ($req['processed_by_name']): ?>
                                <small><?= htmlspecialchars($req['processed_by_name']) ?></small><br>
                                <small class="text-muted"><?= date('M d, Y', strtotime($req['processed_at'])) ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($req['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-success" onclick="updateStatus(<?= $req['id'] ?>, 'approved')">
                                <i class="bi bi-check-circle"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="updateStatus(<?= $req['id'] ?>, 'rejected')">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                            <?php elseif ($req['status'] === 'approved'): ?>
                            <button class="btn btn-sm btn-success" onclick="updateStatus(<?= $req['id'] ?>, 'completed')">
                                <i class="bi bi-check-lg"></i> Mark Completed
                            </button>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Update Payout Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="modal_request_id">
                    <input type="hidden" name="status" id="modal_status">
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes (optional)</label>
                        <textarea class="form-control" name="admin_notes" id="admin_notes" rows="3" placeholder="Add any notes or comments"></textarea>
                    </div>
                    <div class="alert alert-info" id="modal_message"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showBankDetails(id) {
    const el = document.getElementById('bank-' + id);
    if(el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function updateStatus(requestId, status) {
    document.getElementById('modal_request_id').value = requestId;
    document.getElementById('modal_status').value = status;
    
    const messages = {
        'approved': 'Are you sure you want to approve this payout request? The teacher will be notified.',
        'rejected': 'Are you sure you want to reject this payout request? Please provide a reason in the notes.',
        'completed': 'Confirm that you have transferred the amount to the teacher\'s bank account. The teacher will be notified that payment has been completed and will be credited within 3 working days.'
    };
    
    document.getElementById('modal_message').textContent = messages[status] || 'Update status?';
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}
</script>

<?php render_footer(); ?>
