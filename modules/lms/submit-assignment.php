<?php
/**
 * Submit Assignment - Student
 * Students submit their assignment work
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Submit Assignment - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole('student');

// Get student ID
$studentRow = getRow("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
if (!$studentRow) die("Student profile not found.");
$studentId = $studentRow['id'];

// Get assignment ID
$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get assignment details
$assignment = getRow("
    SELECT a.*, s.subject_name, sec.section_name, c.class_name, u.full_name as teacher_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN sections sec ON a.section_id = sec.id
    JOIN classes c ON sec.class_id = c.id
    JOIN teachers t ON a.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE a.id = ?
", [$assignmentId]);

if (!$assignment) {
    die("Assignment not found.");
}

// Check if already submitted
$submission = getRow("
    SELECT * FROM assignment_submissions 
    WHERE assignment_id = ? AND student_id = ?
", [$assignmentId, $studentId]);

$errors = [];
$success = '';

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $submissionText = sanitize($_POST['submission_text']);
        $attachmentPath = '';
        
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['attachment'], UPLOAD_PATH . '/submissions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip']);
            if ($uploadResult['success']) {
                $attachmentPath = str_replace(BASE_PATH . '/', '', $uploadResult['path']);
            } else {
                $errors[] = 'File upload failed: ' . $uploadResult['error'];
            }
        }
        
        if (empty($submissionText) && empty($attachmentPath)) {
            $errors[] = 'Please provide either text submission or file attachment';
        } elseif (strtotime($assignment['due_date']) < time() && !$submission) {
            $errors[] = 'This assignment is past due. Late submissions may not be accepted.';
        } else {
            try {
                if ($submission) {
                    // Update existing submission
                    query("UPDATE assignment_submissions SET submission_text = ?, attachment = ?, submitted_at = NOW(), status = 'submitted' 
                           WHERE id = ?", [$submissionText, $attachmentPath ?: $submission['attachment'], $submission['id']]);
                    $success = 'Assignment resubmitted successfully!';
                } else {
                    // New submission
                    $sql = "INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, attachment, status) 
                            VALUES (?, ?, ?, ?, 'submitted')";
                    insert($sql, [$assignmentId, $studentId, $submissionText, $attachmentPath]);
                    $success = 'Assignment submitted successfully!';
                }
                
                // Refresh submission data
                $submission = getRow("SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?", [$assignmentId, $studentId]);
            } catch (Exception $e) {
                $errors[] = 'Error submitting assignment: ' . $e->getMessage();
            }
        }
    }
}

$isOverdue = strtotime($assignment['due_date']) < time();
$daysLeft = ceil((strtotime($assignment['due_date']) - time()) / 86400);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="student-assignments.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Assignments
        </a>
        <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($assignment['title']); ?></h1>
        <p class="text-base-content/60 mt-1"><?php echo htmlspecialchars($assignment['subject_name']); ?> • <?php echo htmlspecialchars($assignment['teacher_name']); ?></p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Assignment Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Assignment Details</h2>
                    
                    <?php if ($assignment['description']): ?>
                        <div class="prose max-w-none">
                            <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($assignment['attachment']): ?>
                        <div class="divider"></div>
                        <div>
                            <p class="font-semibold mb-2">Teacher's Attachment:</p>
                            <a href="<?php echo APP_URL . '/' . $assignment['attachment']; ?>" target="_blank" class="btn btn-sm btn-outline gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Download Attachment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submission Form -->
            <?php if ($submission && $submission['status'] === 'graded'): ?>
                <div class="card bg-base-100 shadow-xl border-2 border-success">
                    <div class="card-body">
                        <h2 class="card-title text-success">✓ Graded</h2>
                        <div class="stats shadow">
                            <div class="stat">
                                <div class="stat-title">Score</div>
                                <div class="stat-value text-success"><?php echo $submission['points_earned']; ?> / <?php echo $assignment['total_points']; ?></div>
                            </div>
                        </div>
                        <?php if ($submission['feedback']): ?>
                            <div class="alert">
                                <div>
                                    <p class="font-semibold">Teacher Feedback:</p>
                                    <p class="text-sm mt-2"><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <?php echo $submission ? 'Update Submission' : 'Submit Your Work'; ?>
                        </h2>
                        
                        <?php if ($submission): ?>
                            <div class="alert alert-info mb-4">
                                <span>You submitted this on <?php echo formatDateTime($submission['submitted_at']); ?>. You can update it before the deadline.</span>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-control mb-4">
                                <label class="label"><span class="label-text">Your Answer/Write-up</span></label>
                                <textarea name="submission_text" rows="8" placeholder="Type your answer here..." class="textarea textarea-bordered"><?php echo $submission ? htmlspecialchars($submission['submission_text']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-control mb-4">
                                <label class="label"><span class="label-text">Upload File (Optional)</span></label>
                                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip" class="file-input file-input-bordered" />
                                <?php if ($submission && $submission['attachment']): ?>
                                    <label class="label">
                                        <span class="label-text-alt">
                                            Current: <a href="<?php echo APP_URL . '/' . $submission['attachment']; ?>" target="_blank" class="link">View file</a>
                                        </span>
                                    </label>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" name="submit_assignment" class="btn btn-primary btn-block">
                                <?php echo $submission ? 'Update Submission' : 'Submit Assignment'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info Sidebar -->
        <div class="space-y-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold text-lg mb-4">Information</h3>
                    
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="text-base-content/60">Class</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?></p>
                        </div>
                        
                        <div>
                            <p class="text-base-content/60">Type</p>
                            <span class="badge badge-primary"><?php echo ucfirst($assignment['assignment_type']); ?></span>
                        </div>
                        
                        <div>
                            <p class="text-base-content/60">Assigned</p>
                            <p class="font-semibold"><?php echo formatDate($assignment['assigned_date'], 'M d, Y'); ?></p>
                        </div>
                        
                        <div>
                            <p class="text-base-content/60">Due Date</p>
                            <p class="font-semibold <?php echo $isOverdue ? 'text-error' : ''; ?>">
                                <?php echo formatDate($assignment['due_date'], 'M d, Y'); ?>
                            </p>
                            <?php if (!$isOverdue): ?>
                                <p class="text-xs text-base-content/60"><?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?> left</p>
                            <?php else: ?>
                                <p class="text-xs text-error">OVERDUE</p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <p class="text-base-content/60">Points</p>
                            <p class="font-semibold"><?php echo $assignment['total_points']; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-base-content/60">Status</p>
                            <?php if ($submission): ?>
                                <span class="badge <?php echo $submission['status'] === 'graded' ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo ucfirst($submission['status']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-error">Not Submitted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
