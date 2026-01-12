<?php
/**
 * Student Progress Report
 * Multi-semester performance tracking with trends
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Student Progress Report - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher', 'student', 'parent']);

$currentUser = getCurrentUser();
$studentId = 0;

// Determine which student to view
if (in_array('student', getUserRoles())) {
    $studentId = getValue("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
} elseif (in_array('parent', getUserRoles())) {
    // Parent can view their children
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    if ($studentId) {
        // Verify parent has access
        $parentId = getValue("SELECT id FROM parents WHERE user_id = ?", [getCurrentUserId()]);
        $hasAccess = getValue("SELECT COUNT(*) FROM student_parents WHERE parent_id = ? AND student_id = ?", [$parentId, $studentId]);
        if (!$hasAccess) {
            die("Access denied");
        }
    }
} else {
    // Admin/Principal/Teacher can view any student
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
}

if (!$studentId) {
    die("Please select a student");
}

// Get student info
$student = getRow("
    SELECT st.*, u.full_name, u.photo
    FROM students st
    JOIN users u ON st.user_id = u.id
    WHERE st.id = ?
", [$studentId]);

if (!$student) {
    die("Student not found");
}

// Get all exams the student has taken
$examResults = getAll("
    SELECT e.id, e.exam_name, e.exam_type, e.exam_date,
           s.semester_name, s.semester_number,
           a.year_name,
           AVG(g.grade_point) as avg_gpa,
           SUM(g.marks_obtained) as total_marks,
           COUNT(g.id) as subjects_taken
    FROM grades g
    JOIN exams e ON g.exam_id = e.id
    LEFT JOIN semesters s ON e.semester_id = s.id
    JOIN academic_years a ON e.academic_year_id = a.id
    WHERE g.student_id = ? AND g.is_published = 1
    GROUP BY e.id
    ORDER BY e.exam_date DESC, e.created_at DESC
", [$studentId]);

// Get attendance history by semester
$attendanceHistory = getAll("
    SELECT 
        s.semester_name,
        a.year_name,
        COUNT(*) as total_days,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_days,
        ROUND((SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as attendance_percentage
    FROM student_attendance sa
    JOIN semesters s ON sa.attendance_date BETWEEN s.start_date AND s.end_date
    JOIN academic_years a ON s.academic_year_id = a.id
    WHERE sa.student_id = ?
    GROUP BY s.id
    ORDER BY s.start_date DESC
", [$studentId]);

// Calculate overall statistics
$overallStats = getRow("
    SELECT 
        AVG(grade_point) as overall_gpa,
        COUNT(DISTINCT exam_id) as total_exams,
        COUNT(DISTINCT subject_id) as total_subjects
    FROM grades
    WHERE student_id = ? AND is_published = 1
", [$studentId]);

// Get assignment completion rate
$assignmentStats = getRow("
    SELECT 
        COUNT(DISTINCT a.id) as total_assignments,
        COUNT(DISTINCT asub.id) as submitted_assignments,
        AVG(asub.points_earned) as avg_points
    FROM assignments a
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
    WHERE a.section_id IN (
        SELECT section_id FROM enrollments WHERE student_id = ?
    )
", [$studentId, $studentId]);

$completionRate = $assignmentStats['total_assignments'] > 0 
    ? round(($assignmentStats['submitted_assignments'] / $assignmentStats['total_assignments']) * 100) 
    : 0;

// Prepare chart data
$chartLabels = array_reverse(array_column($examResults, 'exam_name'));
$chartGPAs = array_reverse(array_column($examResults, 'avg_gpa'));
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Student Progress Report</h1>
        <p class="text-base-content/60 mt-1">Longitudinal performance tracking</p>
    </div>

    <!-- Student Header -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <div class="flex items-center gap-4">
                <div class="avatar">
                    <div class="w-20 h-20 rounded-full <?php echo $student['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                        <?php if ($student['photo']): ?>
                            <img src="<?php echo APP_URL . '/' . $student['photo']; ?>" alt="" />
                        <?php else: ?>
                            <span class="text-3xl"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-1">
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                    <p class="text-base-content/60"><?php echo htmlspecialchars($student['student_id']); ?></p>
                </div>
                <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨️ Print</button>
            </div>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-title">Overall GPA</div>
            <div class="stat-value text-primary"><?php echo number_format($overallStats['overall_gpa'] ?: 0, 2); ?></div>
            <div class="stat-desc">Across all exams</div>
        </div>

        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-title">Exams Taken</div>
            <div class="stat-value text-secondary"><?php echo $overallStats['total_exams']; ?></div>
            <div class="stat-desc"><?php echo $overallStats['total_subjects']; ?> subjects</div>
        </div>

        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-title">Assignment Rate</div>
            <div class="stat-value text-accent"><?php echo $completionRate; ?>%</div>
            <div class="stat-desc"><?php echo $assignmentStats['submitted_assignments']; ?>/<?php echo $assignmentStats['total_assignments']; ?> submitted</div>
        </div>

        <div class="stat bg-base-100 shadow-xl rounded-box">
            <div class="stat-title">Avg Attendance</div>
            <div class="stat-value text-success">
                <?php 
                $totalPresent = array_sum(array_column($attendanceHistory, 'present_days'));
                $totalDays = array_sum(array_column($attendanceHistory, 'total_days'));
                $avgAttendance = $totalDays > 0 ? round(($totalPresent / $totalDays) * 100) : 0;
                echo $avgAttendance;
                ?>%
            </div>
            <div class="stat-desc"><?php echo $totalPresent; ?>/<?php echo $totalDays; ?> days</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- GPA Trend Chart -->
        <?php if (!empty($examResults)): ?>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">GPA Trend</h3>
                    <canvas id="gpaTrendChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attendance History -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Attendance by Semester</h3>
                <?php if (empty($attendanceHistory)): ?>
                    <p class="text-base-content/60">No attendance data available</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($attendanceHistory as $att): ?>
                            <div class="flex justify-between items-center p-2 bg-base-200 rounded">
                                <div>
                                    <p class="font-semibold"><?php echo htmlspecialchars($att['semester_name']); ?></p>
                                    <p class="text-xs text-base-content/60"><?php echo htmlspecialchars($att['year_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold <?php echo $att['attendance_percentage'] >= 90 ? 'text-success' : ($att['attendance_percentage'] >= 75 ? 'text-warning' : 'text-error'); ?>">
                                        <?php echo $att['attendance_percentage']; ?>%
                                    </p>
                                    <p class="text-xs text-base-content/60"><?php echo $att['present_days']; ?>/<?php echo $att['total_days']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Exam Results Timeline -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h3 class="card-title">Exam Results History</h3>
            <?php if (empty($examResults)): ?>
                <p class="text-base-content/60">No exam results available yet</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Type</th>
                                <th>Semester/Year</th>
                                <th>Date</th>
                                <th>Subjects</th>
                                <th>Total Marks</th>
                                <th>GPA</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($examResults as $result): 
                                $gradeLetter = '-';
                                if ($result['avg_gpa']) {
                                    if ($result['avg_gpa'] >= 3.7) $gradeLetter = 'A+';
                                    elseif ($result['avg_gpa'] >= 3.3) $gradeLetter = 'A';
                                    elseif ($result['avg_gpa'] >= 3.0) $gradeLetter = 'B+';
                                    elseif ($result['avg_gpa'] >= 2.7) $gradeLetter = 'B';
                                    elseif ($result['avg_gpa'] >= 2.3) $gradeLetter = 'C+';
                                    elseif ($result['avg_gpa'] >= 2.0) $gradeLetter = 'C';
                                    else $gradeLetter = 'D';
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                                    <td><span class="badge badge-sm"><?php echo ucfirst(str_replace('_', ' ', $result['exam_type'])); ?></span></td>
                                    <td>
                                        <?php if ($result['semester_name']): ?>
                                            <?php echo htmlspecialchars($result['semester_name']); ?><br>
                                        <?php endif; ?>
                                        <span class="text-xs text-base-content/60"><?php echo htmlspecialchars($result['year_name']); ?></span>
                                    </td>
                                    <td><?php echo $result['exam_date'] ? formatDate($result['exam_date'], 'M d, Y') : '-'; ?></td>
                                    <td><?php echo $result['subjects_taken']; ?></td>
                                    <td><?php echo $result['total_marks']; ?></td>
                                    <td class="font-semibold"><?php echo number_format($result['avg_gpa'], 2); ?></td>
                                    <td><span class="badge badge-lg"><?php echo $gradeLetter; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if (!empty($examResults)): ?>
<script>
// GPA Trend Chart
const gpaTrendCtx = document.getElementById('gpaTrendChart').getContext('2d');
const gpaTrendChart = new Chart(gpaTrendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'GPA',
            data: <?php echo json_encode($chartGPAs); ?>,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 4,
                ticks: {
                    stepSize: 0.5
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
