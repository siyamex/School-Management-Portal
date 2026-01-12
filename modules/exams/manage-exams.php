<?php
/**
 * Manage Exams
 * View, edit, and delete exams
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Manage Exams - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

// Handle delete
if (isset($_POST['delete_exam']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $examId = (int)$_POST['exam_id'];
    try {
        query("DELETE FROM exams WHERE id = ?", [$examId]);
        setFlash('success', 'Exam deleted');
        redirect('manage-exams.php');
    } catch (Exception $e) {
        setFlash('error', 'Cannot delete exam: ' . $e->getMessage());
    }
}

// Get filter parameters
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$typeFilter = isset($_GET['type']) ? sanitize($_GET['type']) : '';

// Build query
$whereClauses = [];
$params = [];

if ($yearFilter) {
    $whereClauses[] = "e.academic_year_id = ?";
    $params[] = $yearFilter;
}

if ($typeFilter) {
    $whereClauses[] = "e.exam_type = ?";
    $params[] = $typeFilter;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get all exams
$exams = getAll("
    SELECT e.*, a.year_name, s.semester_name,
           (SELECT COUNT(*) FROM grades WHERE exam_id = e.id) as grade_count
    FROM exams e
    JOIN academic_years a ON e.academic_year_id = a.id
    LEFT JOIN semesters s ON e.semester_id = s.id
    $whereSQL
    ORDER BY e.created_at DESC
", $params);

// Get academic years for filter
$academicYears = getAll("SELECT * FROM academic_years ORDER BY start_date DESC");

// Group exams by academic year
$examsByYear = [];
foreach ($exams as $exam) {
    $examsByYear[$exam['year_name']][] = $exam;
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Manage Exams</h1>
            <p class="text-base-content/60 mt-1">View and manage all exams</p>
        </div>
        <div class="flex gap-2">
            <a href="enter-grades.php" class="btn btn-outline gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Enter Grades
            </a>
            <a href="create-exam.php" class="btn btn-primary gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                Create Exam
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Academic Year</span></label>
                    <select name="year" class="select select-bordered select-sm">
                        <option value="">All Years</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?php echo $year['id']; ?>" <?php echo $yearFilter == $year['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['year_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Exam Type</span></label>
                    <select name="type" class="select select-bordered select-sm">
                        <option value="">All Types</option>
                        <option value="mid_term" <?php echo $typeFilter === 'mid_term' ? 'selected' : ''; ?>>Mid-Term</option>
                        <option value="final" <?php echo $typeFilter === 'final' ? 'selected' : ''; ?>>Final</option>
                        <option value="quiz" <?php echo $typeFilter === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                        <option value="monthly" <?php echo $typeFilter === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="unit_test" <?php echo $typeFilter === 'unit_test' ? 'selected' : ''; ?>>Unit Test</option>
                    </select>
                </div>
                
                <div class="form-control md:col-span-2">
                    <label class="label"><span class="label-text">&nbsp;</span></label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-1">Filter</button>
                        <a href="manage-exams.php" class="btn btn-ghost btn-sm">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Exams</div>
            <div class="stat-value text-primary"><?php echo count($exams); ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">With Grades</div>
            <div class="stat-value text-success">
                <?php echo count(array_filter($exams, fn($e) => $e['grade_count'] > 0)); ?>
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">Pending</div>
            <div class="stat-value text-warning">
                <?php echo count(array_filter($exams, fn($e) => $e['grade_count'] == 0)); ?>
            </div>
        </div>
    </div>

    <!-- Exams List -->
    <?php if (empty($exams)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No exams found. Click "Create Exam" to add one.</span>
        </div>
    <?php else: ?>
        <?php foreach ($examsByYear as $yearName => $yearExams): ?>
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4"><?php echo htmlspecialchars($yearName); ?></h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($yearExams as $exam): ?>
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <div class="flex justify-between items-start">
                                    <h3 class="card-title"><?php echo htmlspecialchars($exam['exam_name']); ?></h3>
                                    <div class="dropdown dropdown-end">
                                        <label tabindex="0" class="btn btn-ghost btn-sm btn-circle">⋮</label>
                                        <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-52">
                                            <li><a href="enter-grades.php?exam_id=<?php echo $exam['id']; ?>">Enter Grades</a></li>
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Delete this exam and all associated grades?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                    <button type="submit" name="delete_exam" class="text-error w-full text-left">Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="flex gap-2 flex-wrap">
                                    <span class="badge badge-primary"><?php echo ucfirst(str_replace('_', ' ', $exam['exam_type'])); ?></span>
                                    <?php if ($exam['semester_name']): ?>
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($exam['semester_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($exam['description']): ?>
                                    <p class="text-sm text-base-content/70 line-clamp-2"><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
                                <?php endif; ?>
                                
                                <div class="divider my-2"></div>
                                
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <?php if ($exam['exam_date']): ?>
                                        <div>
                                            <p class="text-base-content/60">Date</p>
                                            <p class="font-semibold"><?php echo formatDate($exam['exam_date'], 'M d, Y'); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="text-base-content/60">Total Marks</p>
                                        <p class="font-semibold"><?php echo $exam['total_marks']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-base-content/60">Passing</p>
                                        <p class="font-semibold"><?php echo $exam['passing_marks']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-base-content/60">Grades Entered</p>
                                        <p class="font-semibold"><?php echo $exam['grade_count']; ?></p>
                                    </div>
                                </div>
                                
                                <div class="card-actions justify-end mt-4">
                                    <a href="enter-grades.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary">
                                        Enter Grades
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
