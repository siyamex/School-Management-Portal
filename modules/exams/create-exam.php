<?php
/**
 * Create Exam
 * Create exams for grade entry
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Create Exam - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $examName = sanitize($_POST['exam_name']);
        $examType = sanitize($_POST['exam_type']);
        $academicYearId = (int)$_POST['academic_year_id'];
        $semesterId = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;
        $totalMarks = (int)$_POST['total_marks'];
        $passingMarks = (int)$_POST['passing_marks'];
        $description = sanitize($_POST['description']);
        
        if (empty($examName) || empty($examType) || empty($academicYearId)) {
            $errors[] = 'All required fields must be filled';
        } elseif ($passingMarks > $totalMarks) {
            $errors[] = 'Passing marks cannot be greater than total marks';
        } else {
            try {
                $sql = "INSERT INTO exams (exam_name, exam_type, academic_year_id, semester_id, 
                                          total_marks, passing_marks, description) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $examId = insert($sql, [$examName, $examType, $academicYearId, $semesterId, 
                                       $totalMarks, $passingMarks, $description]);
                
                $success = 'Exam created successfully!';
                redirect('manage-exams.php?created=' . $examId);
            } catch (Exception $e) {
                $errors[] = 'Error creating exam: ' . $e->getMessage();
            }
        }
    }
}

// Get academic years and semesters
$academicYears = getAll("SELECT * FROM academic_years ORDER BY is_current DESC, start_date DESC");
$semesters = getAll("SELECT s.*, a.year_name FROM semesters s JOIN academic_years a ON s.academic_year_id = a.id ORDER BY a.is_current DESC, s.semester_number");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="manage-exams.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Exams
        </a>
        <h1 class="text-3xl font-bold">Create Exam</h1>
        <p class="text-base-content/60 mt-1">Create a new exam for grade entry</p>
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

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Exam Information</h2>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Exam Name *</span></label>
                            <input type="text" name="exam_name" placeholder="e.g., Mid-Term Exam, Final Exam" class="input input-bordered" required />
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Exam Type *</span></label>
                            <select name="exam_type" class="select select-bordered" required>
                                <option value="">-- Select Type --</option>
                                <option value="mid_term">Mid-Term</option>
                                <option value="final">Final Exam</option>
                                <option value="quiz">Quiz</option>
                                <option value="monthly">Monthly Test</option>
                                <option value="unit_test">Unit Test</option>
                                <option value="practical">Practical Exam</option>
                            </select>
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Description (Optional)</span></label>
                            <textarea name="description" rows="3" placeholder="Additional exam details..." class="textarea textarea-bordered"></textarea>
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
                            <label class="label"><span class="label-text">Academic Year *</span></label>
                            <select name="academic_year_id" class="select select-bordered select-sm" required>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['year_name']); ?>
                                        <?php echo $year['is_current'] ? ' (Current)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Semester (Optional)</span></label>
                            <select name="semester_id" class="select select-bordered select-sm">
                                <option value="">-- None --</option>
                                <?php foreach ($semesters as $semester): ?>
                                    <option value="<?php echo $semester['id']; ?>">
                                        <?php echo htmlspecialchars($semester['year_name'] . ' - ' . $semester['semester_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Total Marks *</span></label>
                            <input type="number" name="total_marks" value="100" min="1" class="input input-bordered input-sm" required />
                        </div>
                        
                        <div class="form-control">
                            <label class="label"><span class="label-text">Passing Marks *</span></label>
                            <input type="number" name="passing_marks" value="40" min="1" class="input input-bordered input-sm" required />
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Create Exam
                        </button>
                        <a href="manage-exams.php" class="btn btn-ghost btn-block">Cancel</a>
                    </div>
                </div>
                
                <div class="alert alert-info text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>After creating the exam, you can enter grades from the "Enter Grades" page</span>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
