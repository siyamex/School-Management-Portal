<?php
/**
 * View and Grade Submissions - Teacher
 * Teachers can view student submissions and assign grades
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Grade Submissions - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'leading_teacher']);

// Get teacher ID
$teacherRow = getRow("SELECT id FROM teachers WHERE user_id = ?", [getCurrentUserId()]);
if (!$teacherRow) die("Teacher profile not found.");
$teacherId = $teacherRow['id'];

// Get assignment ID
$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get assignment details
$assignment = getRow("
    SELECT a.*, s.subject_name, sec.section_name, c.class_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN sections sec ON a.section_id = sec.id
    JOIN classes c ON sec.class_id = c.id
    WHERE a.id = ? AND a.teacher_id = ?
", [$assignmentId, $teacherId]);

if (!$assignment) die("Assignment not found.");

// Handle grading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $submissionId = (int)$_POST['submission_id'];
        $pointsEarned = (int)$_POST['points_earned'];
        $feedback = sanitize($_POST['feedback']);
        
        query("UPDATE assignment_submissions SET points_earned = ?, feedback = ?, status = 'graded', graded_at = NOW(), graded_by = ? 
               WHERE id = ?", [$pointsEarned, $feedback, getCurrentUserId(), $submissionId]);
        
        setFlash('success', 'Submission graded successfully');
        redirect('view-submissions.php?id=' . $assignmentId);
    }
}

// Get all submissions
$submissions = getAll("
    SELECT sub.*, st.student_id as student_code, u.full_name, u.photo, e.roll_number
    FROM assignment_submissions sub
    JOIN students st ON sub.student_id = st.id
    JOIN users u ON st.user_id = u.id
    LEFT JOIN enrollments e ON st.id = e.student_id AND e.section_id = ?
    WHERE sub.assignment_id = ?
    ORDER BY e.roll_number, u.full_name
", [$assignment['section_id'], $assignmentId]);

// Get students who haven't submitted
$notSubmitted = getAll("
    SELECT st.id, st.student_id, u.full_name, u.photo, e.roll_number
    FROM enrollments e
    JOIN students st ON e.student_id = st.id
    JOIN users u ON st.user_id = u.id
    WHERE e.section_id = ? AND e.status = 'active'
    AND st.id NOT IN (SELECT student_id FROM assignment_submissions WHERE assignment_id = ?)
    ORDER BY e.roll_number, u.full_name
", [$assignment['section_id'], $assignmentId]);

$stats = [
    'total' => count($submissions) + count($notSubmitted),
    'submitted' => count($submissions),
    'graded' => count(array_filter($submissions, fn($s) => $s['status'] === 'graded')),
    'pending' => count(array_filter($submissions, fn($s) => $s['status'] === 'submitted'))
];
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="manage-assignments.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Assignments
        </a>
        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($assignment['title']); ?></h1>
        <p class="text-base-content/60 mt-1"><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?></p>
    </div>

    <!-- Stats -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Students</div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Submitted</div>
            <div class="stat-value text-info"><?php echo $stats['submitted']; ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Graded</div>
            <div class="stat-value text-success"><?php echo $stats['graded']; ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Pending Review</div>
            <div class="stat-value text-warning"><?php echo $stats['pending']; ?></div>
        </div>
    </div>

    <!-- Submissions List -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <h2 class="card-title">Submissions</h2>
            
            <?php if (empty($submissions)): ?>
                <p class="text-base-content/60">No submissions yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($submissions as $sub): ?>
                        <div class="border border-base-300 rounded-lg p-4">
                            <div class="flex items-start gap-4">
                                <div class="avatar">
                                    <div class="w-12 h-12 rounded-full <?php echo $sub['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                                        <?php if ($sub['photo']): ?>
                                            <img src="<?php echo APP_URL . '/' . $sub['photo']; ?>" alt="" />
                                        <?php else: ?>
                                            <span><?php echo strtoupper(substr($sub['full_name'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-bold"><?php echo htmlspecialchars($sub['full_name']); ?></h3>
                                            <p class="text-sm text-base-content/60">Roll #<?php echo $sub['roll_number']; ?> • Submitted <?php echo formatDateTime($sub['submitted_at']); ?></p>
                                        </div>
                                        <span class="badge <?php echo $sub['status'] === 'graded' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo ucfirst($sub['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($sub['submission_text']): ?>
                                        <div class="mt-3 p-3 bg-base-200 rounded">
                                            <p class="text-sm whitespace-pre-wrap"><?php echo htmlspecialchars($sub['submission_text']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($sub['attachment']): ?>
                                        <div class="mt-2">
                                            <a href="<?php echo APP_URL . '/' . $sub['attachment']; ?>" target="_blank" class="btn btn-sm btn-outline gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Download Attachment
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Grading Form -->
                                    <div class="mt-4 p-4 bg-base-200 rounded-lg">
                                        <?php if ($sub['status'] === 'graded'): ?>
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="font-semibold text-success">Grade: <?php echo $sub['points_earned']; ?> / <?php echo $assignment['total_points']; ?></p>
                                                    <?php if ($sub['feedback']): ?>
                                                        <p class="text-sm text-base-content/60 mt-1">Feedback: <?php echo htmlspecialchars($sub['feedback']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <button onclick="document.getElementById('grade_form_<?php echo $sub['id']; ?>').classList.toggle('hidden')" class="btn btn-sm btn-ghost">
                                                    Edit Grade
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" id="grade_form_<?php echo $sub['id']; ?>" class="<?php echo $sub['status'] === 'graded' ? 'hidden' : ''; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div class="form-control">
                                                    <label class="label label-text">Points Earned</label>
                                                    <input type="number" name="points_earned" value="<?php echo $sub['points_earned'] ?? ''; ?>" 
                                                           min="0" max="<?php echo $assignment['total_points']; ?>" class="input input-sm input-bordered" required />
                                                    <label class="label"><span class="label-text-alt">Max: <?php echo $assignment['total_points']; ?></span></label>
                                                </div>
                                                <div class="form-control md:col-span-1">
                                                    <label class="label label-text">Feedback</label>
                                                    <textarea name="feedback" class="textarea textarea-sm textarea-bordered" rows="2"><?php echo $sub['feedback'] ?? ''; ?></textarea>
                                                </div>
                                            </div>
                                            <button type="submit" name="grade_submission" class="btn btn-sm btn-success mt-2">Save Grade</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Not Submitted -->
    <?php if (!empty($notSubmitted)): ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-error">Not Submitted (<?php echo count($notSubmitted); ?>)</h2>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($notSubmitted as $student): ?>
                        <div class="badge badge-lg badge-error gap-2">
                            <?php echo htmlspecialchars($student['full_name']); ?>
                            (#<?php echo $student['roll_number']; ?>)
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
