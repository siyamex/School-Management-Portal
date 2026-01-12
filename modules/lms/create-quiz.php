<?php
/**
 * Create Quiz
 * Teachers can create online quizzes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Create Quiz - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'admin', 'principal']);

$errors = [];
$success = '';

// Get teacher ID
$teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [getCurrentUserId()]);

if (!$teacherId) {
    die("Only teachers can create quizzes");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $title = sanitize($_POST['title']);
    $subjectId = (int)$_POST['subject_id'];
    $sectionId = (int)$_POST['section_id'];
    $totalMarks = (int)$_POST['total_marks'];
    $timeLimit = (int)$_POST['time_limit'];
    $dueDate = sanitize($_POST['due_date']);
    $instructions = sanitize($_POST['instructions']);
    
    if (empty($title) || !$subjectId || !$sectionId) {
        $errors[] = 'Please fill all required fields';
    } else {
        try {
            $quizId = insert("INSERT INTO quizzes (title, subject_id, section_id, teacher_id, total_marks, time_limit, due_date, instructions, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')",
                           [$title, $subjectId, $sectionId, $teacherId, $totalMarks, $timeLimit, $dueDate, $instructions]);
            
            setFlash('success', 'Quiz created successfully! Now add questions.');
            redirect('edit-quiz.php?id=' . $quizId);
        } catch (Exception $e) {
            $errors[] = 'Error creating quiz: ' . $e->getMessage();
        }
    }
}

// Get subjects and sections
$subjects = getAll("SELECT * FROM subjects ORDER BY subject_name");
$sections = getAll("
    SELECT s.id, s.section_name, c.class_name
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="quizzes.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Quizzes
        </a>
        <h1 class="text-3xl font-bold">Create Quiz</h1>
        <p class="text-base-content/60 mt-1">Create a new online assessment</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Quiz Details</h2>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Quiz Title *</span></label>
                            <input type="text" name="title" placeholder="e.g., Chapter 5 Assessment" class="input input-bordered" required />
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label"><span class="label-text">Subject *</span></label>
                                <select name="subject_id" class="select select-bordered" required>
                                    <option value="">-- Select Subject --</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-control">
                                <label class="label"><span class="label-text">Class/Section *</span></label>
                                <select name="section_id" class="select select-bordered" required>
                                    <option value="">-- Select Section --</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section['id']; ?>">
                                            <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Instructions</span></label>
                            <textarea name="instructions" rows="4" placeholder="Enter quiz instructions..." class="textarea textarea-bordered"></textarea>
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
                            <label class="label"><span class="label-text">Total Marks *</span></label>
                            <input type="number" name="total_marks" value="100" min="1" class="input input-bordered input-sm" required />
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Time Limit (minutes) *</span></label>
                            <input type="number" name="time_limit" value="30" min="1" class="input input-bordered input-sm" required />
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Due Date</span></label>
                            <input type="datetime-local" name="due_date" class="input input-bordered input-sm" />
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            Create Quiz
                        </button>
                        <a href="quizzes.php" class="btn btn-ghost btn-block">Cancel</a>
                    </div>
                </div>
                
                <div class="alert alert-info text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>After creating the quiz, you'll be able to add questions.</span>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
