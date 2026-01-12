<?php
/**
 * Leave Requests
 * Staff and teacher leave management system
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Leave Requests - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

$errors = [];
$success = '';
$currentUserId = getCurrentUserId();
$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);
$isAdmin = in_array('admin', $userRoles) || in_array('principal', $userRoles);

// Handle new leave request
if (isset($_POST['submit_leave']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $leaveType = sanitize($_POST['leave_type']);
    $startDate = sanitize($_POST['start_date']);
    $endDate = sanitize($_POST['end_date']);
    $reason = sanitize($_POST['reason']);
    
    if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
        $errors[] = 'All fields are required';
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $errors[] = 'End date must be after start date';
    } else {
        try {
            // Get teacher ID for current user
            $teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);
            
            if (!$teacherId) {
                $errors[] = 'Only teachers can submit leave requests';
            } else {
                insert("INSERT INTO leave_requests (teacher_id, leave_type, start_date, end_date, reason, status, requested_at) 
                       VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
                      [$teacherId, $leaveType, $startDate, $endDate, $reason]);
                setFlash('success', 'Leave request submitted successfully');
                redirect('leave-requests.php');
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to submit request: ' . $e->getMessage();
        }
    }
}

// Handle approve/reject (admin only)
if (isset($_POST['update_status']) && $isAdmin && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $leaveId = (int)$_POST['leave_id'];
    $status = sanitize($_POST['status']);
    
    query("UPDATE leave_requests SET status = ? WHERE id = ?",
         [$status, $leaveId]);
    
    setFlash('success', 'Leave request ' . $status);
    redirect('leave-requests.php');
}

// Get leave requests
if ($isAdmin) {
    // Admins see all requests
    $leaveRequests = getAll("
        SELECT lr.*, u.full_name, u.email
        FROM leave_requests lr
        JOIN teachers t ON lr.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        ORDER BY lr.requested_at DESC
    ");
} else {
    // Get teacher ID for current user
    $teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);
    
    // Regular users see only their requests
    $leaveRequests = getAll("
        SELECT lr.*
        FROM leave_requests lr
        WHERE lr.teacher_id = ?
        ORDER BY lr.requested_at DESC
    ", [$teacherId]);
}

// Calculate leave statistics
$teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);
$leaveStats = getRow("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM leave_requests
    WHERE teacher_id = ?
    AND YEAR(requested_at) = YEAR(CURDATE())
", [$teacherId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Leave Requests</h1>
            <p class="text-base-content/60 mt-1">Manage leave applications</p>
        </div>
        <?php if (!$isAdmin): ?>
            <button onclick="leave_modal.showModal()" class="btn btn-primary">
                + New Leave Request
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
                <div class="stat-title">Total Requests</div>
                <div class="stat-value"><?php echo $leaveStats['total_requests']; ?></div>
                <div class="stat-desc">This year</div>
            </div>
            <div class="stat">
                <div class="stat-title">Pending</div>
                <div class="stat-value text-warning"><?php echo $leaveStats['pending']; ?></div>
            </div>
            <div class="stat">
                <div class="stat-title">Approved</div>
                <div class="stat-value text-success"><?php echo $leaveStats['approved']; ?></div>
            </div>
            <div class="stat">
                <div class="stat-title">Rejected</div>
                <div class="stat-value text-error"><?php echo $leaveStats['rejected']; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Leave Requests Table -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h3 class="card-title"><?php echo $isAdmin ? 'All Leave Requests' : 'My Leave Requests'; ?></h3>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?>
                                <th>Employee</th>
                            <?php endif; ?>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <?php if ($isAdmin): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaveRequests)): ?>
                            <tr>
                                <td colspan="<?php echo $isAdmin ? 9 : 7; ?>" class="text-center text-base-content/60">
                                    No leave requests found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leaveRequests as $leave): 
                                $days = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;
                                $statusClass = [
                                    'pending' => 'badge-warning',
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-error'
                                ][$leave['status']] ?? 'badge-ghost';
                            ?>
                                <tr>
                                    <?php if ($isAdmin): ?>
                                        <td>
                                            <div>
                                                <div class="font-semibold"><?php echo htmlspecialchars($leave['full_name']); ?></div>
                                                <div class="text-xs text-base-content/60"><?php echo htmlspecialchars($leave['email']); ?></div>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td><span class="badge badge-sm"><?php echo ucfirst(str_replace('_', ' ', $leave['leave_type'])); ?></span></td>
                                    <td><?php echo formatDate($leave['start_date'], 'M d, Y'); ?></td>
                                    <td><?php echo formatDate($leave['end_date'], 'M d, Y'); ?></td>
                                    <td><?php echo $days; ?></td>
                                    <td><small><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></small></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($leave['status']); ?></span></td>
                                    <td><?php echo formatDate($leave['requested_at'], 'M d'); ?></td>
                                    <?php if ($isAdmin): ?>
                                        <td>
                                            <?php if ($leave['status'] === 'pending'): ?>
                                                <button onclick="openReviewModal(<?php echo $leave['id']; ?>, '<?php echo htmlspecialchars($leave['full_name']); ?>')" 
                                                        class="btn btn-xs btn-ghost">Review</button>
                                            <?php else: ?>
                                                <span class="badge badge-sm <?php echo $leave['status'] === 'approved' ? 'badge-success' : 'badge-error'; ?>">
                                                    <?php echo ucfirst($leave['status']); ?>
                                                </span>
                                            <?php endif; ?>
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

<!-- New Leave Request Modal -->
<dialog id="leave_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">New Leave Request</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Leave Type</span></label>
                <select name="leave_type" class="select select-bordered" required>
                    <option value="">-- Select Type --</option>
                    <option value="sick_leave">Sick Leave</option>
                    <option value="casual_leave">Casual Leave</option>
                    <option value="annual_leave">Annual Leave</option>
                    <option value="emergency_leave">Emergency Leave</option>
                    <option value="unpaid_leave">Unpaid Leave</option>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Start Date</span></label>
                <input type="date" name="start_date" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">End Date</span></label>
                <input type="date" name="end_date" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Reason</span></label>
                <textarea name="reason" class="textarea textarea-bordered" rows="3" required></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="leave_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="submit_leave" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Review Modal (Admin) -->
<?php if ($isAdmin): ?>
<dialog id="review_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Review Leave Request</h3>
        <p id="review_employee_name" class="mb-4"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="leave_id" id="review_leave_id">
            
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
function openReviewModal(leaveId, employeeName) {
    document.getElementById('review_leave_id').value = leaveId;
    document.getElementById('review_employee_name').textContent = 'Employee: ' + employeeName;
    review_modal.showModal();
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
