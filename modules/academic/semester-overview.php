<?php
/**
 * Semester Overview
 * Dashboard and statistics for semesters
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Semester Overview - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

// Get semester ID or use current
$semesterId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($semesterId) {
    $semester = getRow("SELECT s.*, a.year_name FROM semesters s JOIN academic_years a ON s.academic_year_id = a.id WHERE s.id = ?", [$semesterId]);
} else {
    $semester = getRow("SELECT s.*, a.year_name FROM semesters s JOIN academic_years a ON s.academic_year_id = a.id WHERE s.is_current = 1");
}

if (!$semester) {
    die("Semester not found. Please select a semester.");
}

$semesterId = $semester['id'];

// Get statistics
$stats = [];

// Total students
$stats['total_students'] = getValue("
    SELECT COUNT(DISTINCT e.student_id)
    FROM enrollments e
    WHERE e.academic_year_id = ? AND e.status = 'active'
", [$semester['academic_year_id']]);

// Total exams in semester
$stats['total_exams'] = getValue("SELECT COUNT(*) FROM exams WHERE semester_id = ?", [$semesterId]);

// Total assignments
$stats['total_assignments'] = getValue("
    SELECT COUNT(*)
    FROM assignments
    WHERE created_at BETWEEN ? AND ?
", [$semester['start_date'], $semester['end_date']]);

// Average attendance
$stats['avg_attendance'] = getValue("
    SELECT AVG(
        CASE WHEN status = 'present' THEN 100
             WHEN status = 'late' THEN 75
             ELSE 0 END
    )
    FROM student_attendance
    WHERE attendance_date BETWEEN ? AND ?
", [$semester['start_date'], $semester['end_date']]) ?: 0;

// Get exams in this semester
$exams = getAll("
    SELECT e.*,
           (SELECT COUNT(*) FROM grades WHERE exam_id = e.id) as grade_count
    FROM exams e
    WHERE e.semester_id = ?
    ORDER BY e.exam_date DESC
", [$semesterId]);

// Get top performing students (if exams exist)
$topStudents = [];
if ($stats['total_exams'] > 0) {
    $topStudents = getAll("
        SELECT st.id, u.full_name, st.student_id,
               AVG(g.grade_point) as avg_gpa,
               COUNT(DISTINCT g.exam_id) as exams_taken
        FROM grades g
        JOIN students st ON g.student_id = st.id
        JOIN users u ON st.user_id = u.id
        WHERE g.exam_id IN (SELECT id FROM exams WHERE semester_id = ?)
          AND g.is_published = 1
        GROUP BY st.id
        HAVING exams_taken > 0
        ORDER BY avg_gpa DESC
        LIMIT 10
    ", [$semesterId]);
}

// Get all semesters for dropdown
$allSemesters = getAll("
    SELECT s.*, a.year_name
    FROM semesters s
    JOIN academic_years a ON s.academic_year_id = a.id
    ORDER BY a.is_current DESC, s.start_date DESC
");

// Calculate semester progress
$today = date('Y-m-d');
$start = new DateTime($semester['start_date']);
$end = new DateTime($semester['end_date']);
$now = new DateTime($today);

$totalDays = $start->diff($end)->days;
$elapsedDays = $start->diff($now)->days;
$progress = min(100, max(0, round(($elapsedDays / $totalDays) * 100)));
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Semester Overview</h1>
            <p class="text-base-content/60 mt-1"><?php echo htmlspecialchars($semester['semester_name']); ?> - <?php echo htmlspecialchars($semester['year_name']); ?></p>
        </div>
        <div class="flex gap-2">
            <select class="select select-bordered select-sm" onchange="if(this.value) window.location.href='semester-overview.php?id='+this.value">
                <?php foreach ($allSemesters as $sem): ?>
                    <option value="<?php echo $sem['id']; ?>" <?php echo $sem['id'] == $semesterId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sem['semester_name'] . ' - ' . $sem['year_name']); ?>
                        <?php echo $sem['is_current'] ? ' (Current)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="semesters.php" class="btn btn-ghost btn-sm">Manage Semesters</a>
        </div>
    </div>

    <!-- Semester Info Card -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-base-content/60">Start Date</p>
                    <p class="text-lg font-semibold"><?php echo formatDate($semester['start_date'], 'M d, Y'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-base-content/60">End Date</p>
                    <p class="text-lg font-semibold"><?php echo formatDate($semester['end_date'], 'M d, Y'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-base-content/60">Duration</p>
                    <p class="text-lg font-semibold"><?php echo $totalDays; ?> days</p>
                </div>
                <div>
                    <p class="text-sm text-base-content/60">Progress</p>
                    <div class="flex items-center gap-2">
                        <progress class="progress progress-primary w-20" value="<?php echo $progress; ?>" max="100"></progress>
                        <span class="text-lg font-semibold"><?php echo $progress; ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-figure text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div class="stat-title">Total Students</div>
            <div class="stat-value text-primary"><?php echo $stats['total_students']; ?></div>
        </div>

        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-figure text-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div class="stat-title">Exams</div>
            <div class="stat-value text-secondary"><?php echo $stats['total_exams']; ?></div>
        </div>

        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-figure text-accent">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            </div>
            <div class="stat-title">Assignments</div>
            <div class="stat-value text-accent"><?php echo $stats['total_assignments']; ?></div>
        </div>

        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-figure text-success">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-8 h-8 stroke-current"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="stat-title">Avg Attendance</div>
            <div class="stat-value text-success"><?php echo round($stats['avg_attendance']); ?>%</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Exams List -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Exams This Semester</h3>
                <?php if (empty($exams)): ?>
                    <p class="text-base-content/60">No exams scheduled for this semester yet.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($exams as $exam): ?>
                            <div class="border border-base-300 rounded-lg p-3 flex justify-between items-center">
                                <div>
                                    <p class="font-semibold"><?php echo htmlspecialchars($exam['exam_name']); ?></p>
                                    <p class="text-xs text-base-content/60">
                                        <?php echo ucfirst(str_replace('_', ' ', $exam['exam_type'])); ?>
                                        <?php if ($exam['exam_date']): ?>
                                            • <?php echo formatDate($exam['exam_date'], 'M d'); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm"><?php echo $exam['grade_count']; ?> grades</p>
                                    <a href="../exams/enter-grades.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-xs btn-ghost">View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Students -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Top Performing Students</h3>
                <?php if (empty($topStudents)): ?>
                    <p class="text-base-content/60">No data available yet.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($topStudents as $index => $student): ?>
                            <div class="flex items-center gap-3 p-2 hover:bg-base-200 rounded">
                                <div class="badge badge-lg <?php echo $index < 3 ? 'badge-primary' : 'badge-ghost'; ?>">
                                    #<?php echo $index + 1; ?>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold"><?php echo htmlspecialchars($student['full_name']); ?></p>
                                    <p class="text-xs text-base-content/60"><?php echo htmlspecialchars($student['student_id']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-lg"><?php echo number_format($student['avg_gpa'], 2); ?></p>
                                    <p class="text-xs text-base-content/60">GPA</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
