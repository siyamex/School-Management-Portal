<?php
/**
 * Manage Assignments - Teacher
 * Teachers view and manage their assignments
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "My Assignments - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'leading_teacher']);

// Get teacher ID
$teacherRow = getRow("SELECT id FROM teachers WHERE user_id = ?", [getCurrentUserId()]);
if (!$teacherRow) {
    die("Teacher profile not found.");
}
$teacherId = $teacherRow['id'];

// Handle delete
if (isset($_POST['delete_assignment']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $assignmentId = (int)$_POST['assignment_id'];
    query("DELETE FROM assignments WHERE id = ? AND teacher_id = ?", [$assignmentId, $teacherId]);
    setFlash('success', 'Assignment deleted');
    redirect('manage-assignments.php');
}

// Get all assignments by this teacher
$assignments = getAll("
    SELECT a.*, s.subject_name, sec.section_name, c.class_name,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = a.section_id AND status = 'active') as student_count
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN sections sec ON a.section_id = sec.id
    JOIN classes c ON sec.class_id = c.id
    WHERE a.teacher_id = ?
    ORDER BY a.due_date DESC, a.created_at DESC
", [$teacherId]);

// Group by status
$upcoming = array_filter($assignments, fn($a) => $a['due_date'] >= date('Y-m-d'));
$past = array_filter($assignments, fn($a) => $a['due_date'] < date('Y-m-d'));
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">My Assignments</h1>
            <p class="text-base-content/60 mt-1">Manage your assignments and view submissions</p>
        </div>
        <a href="create-assignment.php" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            New Assignment
        </a>
    </div>

    <!-- Tabs -->
    <div class="tabs tabs-boxed mb-6">
        <a class="tab tab-active" onclick="showTab('upcoming')">Upcoming (<?php echo count($upcoming); ?>)</a>
        <a class="tab" onclick="showTab('past')">Past (<?php echo count($past); ?>)</a>
    </div>

    <!-- Upcoming Assignments -->
    <div id="upcoming-tab">
        <?php if (empty($upcoming)): ?>
            <div class="alert alert-info">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>No upcoming assignments. Click "New Assignment" to create one.</span>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($upcoming as $assignment): 
                    $daysLeft = (strtotime($assignment['due_date']) - time()) / 86400;
                    $urgentClass = $daysLeft <= 2 ? 'ring-error' : ($daysLeft <= 5 ? 'ring-warning' : 'ring-primary');
                    $submissionRate = $assignment['student_count'] > 0 
                        ? round(($assignment['submission_count'] / $assignment['student_count']) * 100) 
                        : 0;
                ?>
                    <div class="card bg-base-100 shadow-xl ring-2 <?php echo $urgentClass; ?>">
                        <div class="card-body">
                            <div class="flex justify-between items-start">
                                <h2 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h2>
                                <div class="dropdown dropdown-end">
                                    <label tabindex="0" class="btn btn-ghost btn-sm btn-circle">⋮</label>
                                    <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                                        <li><a href="view-submissions.php?id=<?php echo $assignment['id']; ?>">View Submissions</a></li>
                                        <li>
                                            <form method="POST" onsubmit="return confirm('Delete this assignment?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                <button type="submit" name="delete_assignment" class="text-error">Delete</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="flex gap-2 flex-wrap">
                                <span class="badge badge-primary"><?php echo htmlspecialchars($assignment['subject_name']); ?></span>
                                <span class="badge badge-secondary"><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?></span>
                                <span class="badge badge-accent"><?php echo ucfirst($assignment['assignment_type']); ?></span>
                            </div>
                            
                            <?php if ($assignment['description']): ?>
                                <p class="text-sm text-base-content/70 line-clamp-2"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="divider my-2"></div>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-base-content/60">Assigned</p>
                                    <p class="font-semibold"><?php echo formatDate($assignment['assigned_date'], 'M d, Y'); ?></p>
                                </div>
                                <div>
                                    <p class="text-base-content/60">Due Date</p>
                                    <p class="font-semibold"><?php echo formatDate($assignment['due_date'], 'M d, Y'); ?></p>
                                    <p class="text-xs text-base-content/60">(<?php echo ceil($daysLeft); ?> days left)</p>
                                </div>
                                <div>
                                    <p class="text-base-content/60">Total Points</p>
                                    <p class="font-semibold"><?php echo $assignment['total_points']; ?></p>
                                </div>
                                <div>
                                    <p class="text-base-content/60">Submissions</p>
                                    <p class="font-semibold"><?php echo $assignment['submission_count']; ?> / <?php echo $assignment['student_count']; ?></p>
                                    <div class="w-full bg-base-300 rounded-full h-2 mt-1">
                                        <div class="bg-success h-2 rounded-full" style="width: <?php echo $submissionRate; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-actions justify-end mt-4">
                                <a href="view-submissions.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary">
                                    View Submissions (<?php echo $assignment['submission_count']; ?>)
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Past Assignments -->
    <div id="past-tab" class="hidden">
        <?php if (empty($past)): ?>
            <div class="alert alert-info">
                <span>No past assignments.</span>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Class/Section</th>
                            <th>Due Date</th>
                            <th>Submissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past as $assignment): 
                            $submissionRate = $assignment['student_count'] > 0 
                                ? round(($assignment['submission_count'] / $assignment['student_count']) * 100) 
                                : 0;
                        ?>
                            <tr>
                                <td>
                                    <div class="font-semibold"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                    <div class="text-sm text-base-content/60"><?php echo htmlspecialchars($assignment['subject_name']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?></td>
                                <td><?php echo formatDate($assignment['due_date'], 'M d, Y'); ?></td>
                                <td>
                                    <div><?php echo $assignment['submission_count']; ?> / <?php echo $assignment['student_count']; ?> (<?php echo $submissionRate; ?>%)</div>
                                    <div class="w-24 bg-base-300 rounded-full h-2 mt-1">
                                        <div class="bg-success h-2 rounded-full" style="width: <?php echo $submissionRate; ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <a href="view-submissions.php?id=<?php echo $assignment['id']; ?>" class="btn btn-xs btn-ghost">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
function showTab(tabName) {
    document.getElementById('upcoming-tab').classList.toggle('hidden', tabName !== 'upcoming');
    document.getElementById('past-tab').classList.toggle('hidden', tabName !== 'past');
    
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('tab-active'));
    event.target.classList.add('tab-active');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
