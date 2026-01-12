<?php
/**
 * OT (Overtime) Requests
 * Staff overtime request and approval system
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "OT Requests - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

$errors = [];
$success = '';
$currentUserId = getCurrentUserId();
$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);
$isAdmin = in_array('admin', $userRoles) || in_array('principal', $userRoles);

// Handle new OT request
if (isset($_POST['submit_ot']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $date = sanitize($_POST['date']);
    $hours = (float)$_POST['hours'];
    $reason = sanitize($_POST['reason']);
    
    if (empty($date) || $hours <= 0 || empty($reason)) {
        $errors[] = 'All fields are required and hours must be positive';
    } else {
        try {
            // Get teacher ID for current user
            $teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);
            
            if (!$teacherId) {
                $errors[] = 'Only teachers can submit OT requests';
            } else {
                insert("INSERT INTO overtime_requests (teacher_id, work_date, hours, description, status, requested_at) 
                       VALUES (?, ?, ?, ?, 'pending', NOW())",
                      [$teacherId, $date, $hours, $reason]);
                setFlash('success', 'OT request submitted successfully');
                redirect('ot-requests.php');
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to submit request: ' . $e->getMessage();
        }
    }
}

// Handle approve/reject (admin only)
if (isset($_POST['update_status']) && $isAdmin && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $otId = (int)$_POST['ot_id'];
    $status = sanitize($_POST['status']);
    $remarks = sanitize($_POST['remarks']);
    
    query("UPDATE overtime_requests SET status = ? WHERE id = ?",
         [$status, $otId]);
    
    setFlash('success', 'OT request ' . $status);
    redirect('ot-requests.php');
}

// Get OT requests
if ($isAdmin) {
    // Admins see all requests
    $otRequests = getAll("
        SELECT otr.*, u.full_name, u.email
        FROM overtime_requests otr
        JOIN teachers t ON otr.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        ORDER BY otr.requested_at DESC
    ");
} else {
    // Get teacher ID for current user
    $teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);
    
    // Regular users see only their requests
    $otRequests = getAll("
        SELECT otr.*
        FROM overtime_requests otr
        WHERE otr.teacher_id = ?
        ORDER BY otr.requested_at DESC
    ", [$teacherId]);
}

// Calculate OT statistics
$teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);
$otStats = getRow("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN hours ELSE 0 END) as approved_hours,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM overtime_requests
    WHERE teacher_id = ?
    AND MONTH(work_date) = MONTH(CURDATE())
    AND YEAR(work_date) = YEAR(CURDATE())
", [$teacherId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">OT Requests</h1>
            <p class="text-base-content/60 mt-1">Overtime request management</p>
        </div>
        <?php if (!$isAdmin): ?>
            <button onclick="ot_modal.showModal()" class="btn btn-primary">
                + New OT Request
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (!$isAdmin): ?>
        <!-- Personal Stats -->
        <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
            <div class="stat">
                <div class="stat-title">This Month</div>
                <div class="stat-value"><?php echo $otStats['total_requests']; ?></div>
                <div class="stat-desc">Total requests</div>
            </div>
            <div class="stat">
                <div class="stat-title">Approved Hours</div>
                <div class="stat-value text-success"><?php echo number_format($otStats['approved_hours'] ?? 0, 1); ?></div>
                <div class="stat-desc">This month</div>
            </div>
            <div class="stat">
                <div class="stat-title">Pending</div>
                <div class="stat-value text-warning"><?php echo $otStats['pending']; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- OT Requests Table -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h3 class="card-title"><?php echo $isAdmin ? 'All OT Requests' : 'My OT Requests'; ?></h3>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?>
                                <th>Employee</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Hours</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <?php if ($isAdmin): ?>
                                <th>Actions</th>
                            <?php else: ?>
                                <th>Remarks</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($otRequests)): ?>
                            <tr>
                                <td colspan="<?php echo $isAdmin ? 7 : 6; ?>" class="text-center text-base-content/60">
                                    No OT requests found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($otRequests as $ot): 
                                $statusClass = [
                                    'pending' => 'badge-warning',
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-error'
                                ][$ot['status']] ?? 'badge-ghost';
                            ?>
                                <tr>
                                    <?php if ($isAdmin): ?>
                                        <td>
                                            <div>
                                                <div class="font-semibold"><?php echo htmlspecialchars($ot['full_name']); ?></div>
                                                <div class="text-xs text-base-content/60"><?php echo htmlspecialchars($ot['email']); ?></div>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td><?php echo formatDate($ot['work_date'], 'M d, Y'); ?></td>
                                    <td class="font-semibold"><?php echo number_format($ot['hours'], 1); ?> hrs</td>
                                    <td><small><?php echo htmlspecialchars(substr($ot['description'], 0, 40)) . (strlen($ot['description']) > 40 ? '...' : ''); ?></small></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($ot['status']); ?></span></td>
                                    <td><?php echo formatDate($ot['requested_at'], 'M d'); ?></td>
                                    <?php if ($isAdmin): ?>
                                        <td>
                                            <?php if ($ot['status'] === 'pending'): ?>
                                                <button onclick="openReviewModal(<?php echo $ot['id']; ?>, '<?php echo htmlspecialchars($ot['full_name']); ?>', <?php echo $ot['hours']; ?>)" 
                                                        class="btn btn-xs btn-ghost">Review</button>
                                            <?php else: ?>
                                                <span class="badge badge-sm <?php echo $ot['status'] === 'approved' ? 'badge-success' : 'badge-error'; ?>">
                                                    <?php echo ucfirst($ot['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php else: ?>
                                        <td>
                                            <span class="text-base-content/30">-</span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- New OT Request Modal -->
<dialog id="ot_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">New OT Request</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Date</span></label>
                <input type="date" name="date" class="input input-bordered" max="<?php echo date('Y-m-d'); ?>" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Hours</span></label>
                <input type="number" name="hours" step="0.5" min="0.5" max="12" class="input input-bordered" required />
                <label class="label">
                    <span class="label-text-alt">Enter actual overtime hours worked (0.5 to 12)</span>
                </label>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Reason/Task</span></label>
                <textarea name="reason" class="textarea textarea-bordered" rows="3" placeholder="Describe the work done during overtime" required></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="ot_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="submit_ot" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Review Modal (Admin) -->
<?php if ($isAdmin): ?>
<dialog id="review_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Review OT Request</h3>
        <p id="review_employee_info" class="mb-4"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="ot_id" id="review_ot_id">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Decision</span></label>
                <select name="status" class="select select-bordered" required>
                    <option value="approved">Approve</option>
                    <option value="rejected">Reject</option>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Remarks (Optional)</span></label>
                <textarea name="remarks" class="textarea textarea-bordered" rows="2"></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="review_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="update_status" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function openReviewModal(otId, employeeName, hours) {
    document.getElementById('review_ot_id').value = otId;
    document.getElementById('review_employee_info').textContent = 'Employee: ' + employeeName + ' | Hours: ' + hours;
    review_modal.showModal();
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
