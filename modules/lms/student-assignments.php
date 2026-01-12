<?php
/**
 * Student Assignments - LMS Module
 * Students view and submit assignments
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "My Assignments - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole('student');

// Get student ID and enrollment
$studentRow = getRow("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
if (!$studentRow) die("Student profile not found.");
$studentId = $studentRow['id'];

$enrollment = getCurrentEnrollment($studentId);
if (!$enrollment) die("No active enrollment found.");

// Get assignments with submission status
$assignments = getAll("
    SELECT a.*, s.subject_name, t.full_name as teacher_name,
           COALESCE(sub.status, 'pending') as submission_status,
           sub.submitted_at, sub.points_earned, sub.feedback
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN teachers teach ON a.teacher_id = teach.id
    JOIN users t ON teach.user_id = t.id
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
    WHERE a.section_id = ?
    ORDER BY a.due_date ASC
", [$studentId, $enrollment['section_id']]);

// Separate into pending and completed
$pending = array_filter($assignments, fn($a) => $a['submission_status'] === 'pending' && $a['due_date'] >= date('Y-m-d'));
$submitted = array_filter($assignments, fn($a) => in_array($a['submission_status'], ['submitted', 'graded']));
$overdue = array_filter($assignments, fn($a) => $a['submission_status'] === 'pending' && $a['due_date'] < date('Y-m-d'));
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <h1 class="text-3xl font-bold mb-8">My Assignments</h1>

    <!-- Tabs -->
    <div class="tabs tabs-boxed mb-6">
        <a class="tab tab-active" onclick="showTab('pending')">Pending (<?php echo count($pending); ?>)</a>
        <a class="tab" onclick="showTab('submitted')">Submitted (<?php echo count($submitted); ?>)</a>
        <a class="tab" onclick="showTab('overdue')">Overdue (<?php echo count($overdue); ?>)</a>
    </div>

    <!-- Pending Assignments -->
    <div id="pending-tab" class="space-y-4">
        <?php if (empty($pending)): ?>
            <div class="alert alert-success"><span>No pending assignments! 🎉</span></div>
        <?php else: ?>
            <?php foreach ($pending as $assignment): 
                $daysLeft = (strtotime($assignment['due_date']) - time()) / 86400;
                $urgentClass = $daysLeft <= 2 ? 'border-error' : ($daysLeft <= 5 ? 'border-warning' : 'border-primary');
            ?>
                <div class="card bg-base-100 shadow-lg border-l-4 <?php echo $urgentClass; ?>">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h2 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h2>
                                <div class="flex gap-2 mt-2">
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                                    <span class="badge badge-ghost"><?php echo ucfirst($assignment['assignment_type']); ?></span>
                                </div>
                                <p class="text-sm mt-3"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                                <div class="text-sm text-base-content/60 mt-3">
                                    <p>Teacher: <?php echo htmlspecialchars($assignment['teacher_name']); ?></p>
                                    <p>Assigned: <?php echo formatDate($assignment['assigned_date'], 'M d, Y'); ?></p>
                                    <p>Due: <?php echo formatDate($assignment['due_date'], 'M d, Y'); ?> 
                                        (<?php echo ceil($daysLeft); ?> days left)</p>
                                    <p>Points: <?php echo $assignment['total_points']; ?></p>
                                </div>
                            </div>
                            <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-primary">Submit</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Submitted Assignments -->
    <div id="submitted-tab" class="space-y-4 hidden">
        <?php if (empty($submitted)): ?>
            <div class="alert alert-info"><span>No submitted assignments yet.</span></div>
        <?php else: ?>
            <?php foreach ($submitted as $assignment): ?>
                <div class="card bg-base-100 shadow-lg">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h2 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h2>
                                <div class="flex gap-2 mt-2">
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                                    <span class="badge <?php echo $assignment['submission_status'] === 'graded' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo ucfirst($assignment['submission_status']); ?>
                                    </span>
                                </div>
                                <div class="text-sm text-base-content/60 mt-3">
                                    <p>Submitted: <?php echo formatDateTime($assignment['submitted_at'], 'M d, Y h:i A'); ?></p>
                                    <?php if ($assignment['submission_status'] === 'graded'): ?>
                                        <p class="font-semibold text-success mt-2">
                                            Score: <?php echo $assignment['points_earned']; ?> / <?php echo $assignment['total_points']; ?>
                                        </p>
                                        <?php if ($assignment['feedback']): ?>
                                            <div class="mt-2 p-3 bg-base-200 rounded-lg">
                                                <p class="font-semibold">Teacher Feedback:</p>
                                                <p><?php echo nl2br(htmlspecialchars($assignment['feedback'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Overdue Assignments -->
    <div id="overdue-tab" class="space-y-4 hidden">
        <?php if (empty($overdue)): ?>
            <div class="alert alert-success"><span>No overdue assignments! 👍</span></div>
        <?php else: ?>
            <?php foreach ($overdue as $assignment): ?>
                <div class="card bg-base-100 shadow-lg border-l-4 border-error">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h2 class="card-title text-error"><?php echo htmlspecialchars($assignment['title']); ?></h2>
                                <div class="flex gap-2 mt-2">
                                    <span class="badge badge-error">OVERDUE</span>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                                </div>
                                <div class="text-sm text-base-content/60 mt-3">
                                    <p class="text-error font-semibold">
                                        Due date passed: <?php echo formatDate($assignment['due_date'], 'M d, Y'); ?>
                                    </p>
                                </div>
                            </div>
                            <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-error btn-outline">Submit Late</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.getElementById('pending-tab').classList.add('hidden');
    document.getElementById('submitted-tab').classList.add('hidden');
    document.getElementById('overdue-tab').classList.add('hidden');
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.remove('hidden');
    
    // Update tab styling
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('tab-active'));
    event.target.classList.add('tab-active');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
