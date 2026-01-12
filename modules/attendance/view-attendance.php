<?php
/**
 * View Attendance - Student Portal
 * Students can view their own attendance records
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "My Attendance - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['student']);

$currentUserId = getCurrentUserId();
$studentId = getValue("SELECT id FROM students WHERE user_id = ?", [$currentUserId]);

if (!$studentId) {
    die("Student profile not found");
}

// Get student's enrollment
$enrollment = getCurrentEnrollment($studentId);

if (!$enrollment) {
    $attendanceRecords = [];
    $stats = [
        'total_days' => 0,
        'present_days' => 0,
        'absent_days' => 0,
        'late_days' => 0,
        'attendance_percentage' => 0
    ];
} else {
    // Get attendance records
    $attendanceRecords = getAll("
        SELECT a.*, s.subject_name
        FROM attendance a
        LEFT JOIN subjects s ON a.subject_id = s.id
        WHERE a.student_id = ?
        ORDER BY a.date DESC, a.created_at DESC
        LIMIT 100
    ", [$studentId]);

    // Calculate statistics
    $stats = getRow("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
        FROM attendance
        WHERE student_id = ?
    ", [$studentId]);

    $stats['attendance_percentage'] = $stats['total_days'] > 0 
        ? round(($stats['present_days'] / $stats['total_days']) * 100, 1) 
        : 0;
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">📋 My Attendance</h1>
        <p class="text-base-content/60 mt-1">View your attendance records</p>
    </div>

    <?php if (!$enrollment): ?>
        <div class="alert alert-warning shadow-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <div>
                <h3 class="font-bold">No Active Enrollment</h3>
                <div class="text-sm">You need to be enrolled in a class to view attendance records. Please contact your administrator.</div>
            </div>
        </div>
    <?php else: ?>

    <!-- Statistics -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Days</div>
            <div class="stat-value"><?php echo $stats['total_days']; ?></div>
            <div class="stat-desc">Recorded</div>
        </div>
        <div class="stat">
            <div class="stat-title">Present</div>
            <div class="stat-value text-success"><?php echo $stats['present_days']; ?></div>
            <div class="stat-desc">Days attended</div>
        </div>
        <div class="stat">
            <div class="stat-title">Absent</div>
            <div class="stat-value text-error"><?php echo $stats['absent_days']; ?></div>
            <div class="stat-desc">Days missed</div>
        </div>
        <div class="stat">
            <div class="stat-title">Attendance Rate</div>
            <div class="stat-value <?php echo $stats['attendance_percentage'] >= 80 ? 'text-success' : ($stats['attendance_percentage'] >= 60 ? 'text-warning' : 'text-error'); ?>">
                <?php echo $stats['attendance_percentage']; ?>%
            </div>
            <div class="stat-desc">
                <?php if ($stats['late_days'] > 0): ?>
                    <?php echo $stats['late_days']; ?> late days
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Attendance Records -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Attendance History</h2>
            
            <?php if (empty($attendanceRecords)): ?>
                <div class="alert alert-info">
                    <span>No attendance records found yet.</span>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceRecords as $record): 
                                $statusBadge = $record['status'] === 'present' ? 'badge-success' 
                                    : ($record['status'] === 'late' ? 'badge-warning' : 'badge-error');
                            ?>
                                <tr>
                                    <td><?php echo formatDate($record['date'], 'M d, Y'); ?></td>
                                    <td><?php echo htmlspecialchars($record['subject_name'] ?: 'General'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($record['remarks']): ?>
                                            <span class="text-sm text-base-content/60">
                                                <?php echo htmlspecialchars($record['remarks']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-base-content/40">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; // End enrollment check ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
