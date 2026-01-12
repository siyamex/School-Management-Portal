<?php
/**
 * Create Assignment - Teacher
 * Teachers can create assignments for their classes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Create Assignment - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'leading_teacher']);

$errors = [];
$success = '';

// Get teacher ID
$teacherRow = getRow("SELECT id FROM teachers WHERE user_id = ?", [getCurrentUserId()]);
if (!$teacherRow) {
    die("Teacher profile not found.");
}
$teacherId = $teacherRow['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $subjectId = (int)$_POST['subject_id'];
        $sectionId = (int)$_POST['section_id'];
        $assignmentType = sanitize($_POST['assignment_type']);
        $assignedDate = sanitize($_POST['assigned_date']);
        $dueDate = sanitize($_POST['due_date']);
        $totalPoints = (int)$_POST['total_points'];
        $attachmentPath = '';
        
        // Validate
        if (empty($title) || empty($subjectId) || empty($sectionId) || empty($dueDate)) {
            $errors[] = 'All required fields must be filled';
        } elseif (strtotime($dueDate) < strtotime($assignedDate)) {
            $errors[] = 'Due date must be after assigned date';
        } else {
            // Handle file upload
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['attachment'], UPLOAD_PATH . '/assignments', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png']);
                if ($uploadResult['success']) {
                    $attachmentPath = str_replace(BASE_PATH . '/', '', $uploadResult['path']);
                } else {
                    $errors[] = 'File upload failed: ' . $uploadResult['error'];
                }
            }
            
            if (empty($errors)) {
                try {
                    $sql = "INSERT INTO assignments (teacher_id, subject_id, section_id, title, description, 
                            assignment_type, assigned_date, due_date, total_points, attachment) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $assignmentId = insert($sql, [
                        $teacherId, $subjectId, $sectionId, $title, $description,
                        $assignmentType, $assignedDate, $dueDate, $totalPoints, $attachmentPath
                    ]);
                    
                    $success = 'Assignment created successfully!';
                    
                    // Clear form by redirecting
                    if (isset($_POST['save_and_new'])) {
                        redirect('create-assignment.php?success=1');
                    } else {
                        redirect('manage-assignments.php?created=' . $assignmentId);
                    }
                } catch (Exception $e) {
                    $errors[] = 'Error creating assignment: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get sections and subjects for dropdowns
$sections = getAll("
    SELECT s.id, s.section_name, c.class_name
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

$subjects = getAll("SELECT * FROM subjects ORDER BY subject_name");

if (isset($_GET['success'])) {
    $success = 'Assignment created successfully!';
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Create Assignment</h1>
            <p class="text-base-content/60 mt-1">Assign homework or projects to your students</p>
        </div>
        <a href="manage-assignments.php" class="btn btn-ghost gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Assignments
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Information -->
            <div class="lg:col-span-2 space-y-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Assignment Details</h2>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Assignment Title *</span></label>
                            <input type="text" name="title" placeholder="e.g., Chapter 5 Homework" class="input input-bordered" required />
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Description</span></label>
                            <textarea name="description" rows="5" placeholder="Detailed instructions for students..." class="textarea textarea-bordered"></textarea>
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Attachment (Optional)</span></label>
                            <input type="file" name="attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" class="file-input file-input-bordered" />
                            <label class="label">
                                <span class="label-text-alt">PDF, DOC, PPT, or images - Max 5MB</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Sidebar -->
            <div class="space-y-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title text-lg">Settings</h2>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Class/Section *</span></label>
                            <select name="section_id" class="select select-bordered select-sm" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>">
                                        <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Subject *</span></label>
                            <select name="subject_id" class="select select-bordered select-sm" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Type *</span></label>
                            <select name="assignment_type" class="select select-bordered select-sm" required>
                                <option value="homework">Homework</option>
                                <option value="project">Project</option>
                                <option value="reading">Reading</option>
                                <option value="practice">Practice</option>
                            </select>
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Assigned Date *</span></label>
                            <input type="date" name="assigned_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered input-sm" required />
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Due Date *</span></label>
                            <input type="date" name="due_date" min="<?php echo date('Y-m-d'); ?>" class="input input-bordered input-sm" required />
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Total Points *</span></label>
                            <input type="number" name="total_points" value="100" min="1" class="input input-bordered input-sm" required />
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Create Assignment
                        </button>
                        <button type="submit" name="save_and_new" class="btn btn-outline btn-block">
                            Save & Create Another
                        </button>
                        <a href="manage-assignments.php" class="btn btn-ghost btn-block">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
