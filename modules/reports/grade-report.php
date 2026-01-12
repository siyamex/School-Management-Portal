<?php
/**
 * Grade Report / Report Card
 * Student grade reports and class performance analytics
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Grade Report - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

// Get filter parameters
$examFilter = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
$sectionFilter = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$studentFilter = isset($_GET['student']) ? (int)$_GET['student'] : 0;

// Get exams for filter
$exams = getAll("SELECT e.*, a.year_name FROM exams e JOIN academic_years a ON e.academic_year_id = a.id ORDER BY e.created_at DESC");

// Get sections for filter
$sections = getAll("SELECT s.id, s.section_name, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY c.class_numeric, s.section_name");

// Generate report
$reportData = [];
$chartData = [];
$sectionName = '';

if ($examFilter && $sectionFilter) {
    $sectionInfo = getRow("SELECT s.section_name, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$sectionFilter]);
    $sectionName = $sectionInfo['class_name'] . ' - ' . $sectionInfo['section_name'];
    
    // Get all students with their grades
    $reportData = getAll("
        SELECT st.id, u.full_name, e.roll_number,
               GROUP_CONCAT(CONCAT(sub.subject_code, ':', g.grade_letter, ':', g.grade_point, ':', g.marks_obtained) SEPARATOR '|') as grades_data,
               AVG(g.grade_point) as avg_gpa
        FROM enrollments e
        JOIN students st ON e.student_id = st.id
        JOIN users u ON st.user_id = u.id
        LEFT JOIN grades g ON st.id = g.student_id AND g.exam_id = ? AND g.is_published = 1
        LEFT JOIN subjects sub ON g.subject_id = sub.id
        WHERE e.section_id = ? AND e.status = 'active'
        GROUP BY st.id
        ORDER BY e.roll_number
    ", [$examFilter, $sectionFilter]);
    
    // Get grade distribution for chart
    $gradeDistribution = getRow("
        SELECT 
            SUM(CASE WHEN grade_letter IN ('A+', 'A') THEN 1 ELSE 0 END) as grade_a,
            SUM(CASE WHEN grade_letter IN ('B+', 'B') THEN 1 ELSE 0 END) as grade_b,
            SUM(CASE WHEN grade_letter IN ('C+', 'C') THEN 1 ELSE 0 END) as grade_c,
            SUM(CASE WHEN grade_letter IN ('D+', 'D') THEN 1 ELSE 0 END) as grade_d,
            SUM(CASE WHEN grade_letter = 'F' THEN 1 ELSE 0 END) as grade_f
        FROM (
            SELECT student_id, AVG(grade_point) as avg_gpa
            FROM grades
            WHERE exam_id = ? AND student_id IN (
                SELECT student_id FROM enrollments WHERE section_id = ? AND status = 'active'
            ) AND is_published = 1
            GROUP BY student_id
        ) as student_averages
        JOIN grades ON student_averages.student_id = grades.student_id
        WHERE grades.exam_id = ?
    ", [$examFilter, $sectionFilter, $examFilter]);
    
    $chartData = $gradeDistribution;
}

// Handle print report card
$printMode = isset($_GET['print']) && $_GET['print'] === '1';
?>

<?php if (!$printMode): ?>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<?php endif; ?>

<main class="flex-1 p-6 lg:p-8 <?php echo $printMode ? 'print:p-0' : ''; ?>">
    <?php if (!$printMode): ?>
        <div class="mb-8">
            <h1 class="text-3xl font-bold">Grade Report</h1>
            <p class="text-base-content/60 mt-1">Student report cards and class performance analytics</p>
        </div>

        <!-- Filters -->
        <div class="card bg-base-100 shadow-lg mb-6">
            <div class="card-body">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Exam</span></label>
                        <select name="exam" class="select select-bordered select-sm" required>
                            <option value="">-- Select Exam --</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $examFilter == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name'] . ' (' . $exam['year_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Class/Section</span></label>
                        <select name="section" class="select select-bordered select-sm" required>
                            <option value="">-- Select Section --</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo $sectionFilter == $section['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control md:col-span-2">
                        <label class="label"><span class="label-text">&nbsp;</span></label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-1">Generate Report</button>
                            <?php if (!empty($reportData)): ?>
                                <button type="button" onclick="window.print()" class="btn btn-ghost btn-sm">🖨️ Print</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($reportData)): ?>
        <!-- Class Statistics -->
        <?php if (!$printMode): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Stats Cards -->
                <div class="stats stats-vertical shadow">
                    <div class="stat">
                        <div class="stat-title">Average GPA</div>
                        <div class="stat-value text-primary">
                            <?php 
                            $avgGPAs = array_filter(array_column($reportData, 'avg_gpa'));
                            $classAvg = !empty($avgGPAs) ? array_sum($avgGPAs) / count($avgGPAs) : 0;
                            echo number_format($classAvg, 2); 
                            ?>
                        </div>
                        <div class="stat-desc">Class Performance</div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">Students</div>
                        <div class="stat-value"><?php echo count($reportData); ?></div>
                        <div class="stat-desc"><?php echo htmlspecialchars($sectionName); ?></div>
                    </div>
                </div>

                <!-- Grade Distribution Chart -->
                <?php if ($chartData): ?>
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <h3 class="card-title">Grade Distribution</h3>
                            <canvas id="gradeDistChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Student Grades Table -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title"><?php echo htmlspecialchars($sectionName); ?> - Grade Sheet</h3>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Roll</th>
                                <th>Student Name</th>
                                <th>Subjects</th>
                                <th>GPA</th>
                                <th>Grade</th>
                                <th class="print:hidden">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $student): 
                                $gpa = $student['avg_gpa'] ? number_format($student['avg_gpa'], 2) : 'N/A';
                                $gradeLetter = '-';
                                if ($student['avg_gpa']) {
                                    if ($student['avg_gpa'] >= 3.7) $gradeLetter = 'A+';
                                    elseif ($student['avg_gpa'] >= 3.3) $gradeLetter = 'A';
                                    elseif ($student['avg_gpa'] >= 3.0) $gradeLetter = 'B+';
                                    elseif ($student['avg_gpa'] >= 2.7) $gradeLetter = 'B';
                                    elseif ($student['avg_gpa'] >= 2.3) $gradeLetter = 'C+';
                                    elseif ($student['avg_gpa'] >= 2.0) $gradeLetter = 'C';
                                    else $gradeLetter = 'D';
                                }
                            ?>
                                <tr>
                                    <td><?php echo str_pad($student['roll_number'], 2, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td>
                                        <?php if ($student['grades_data']): ?>
                                            <div class="flex gap-1 flex-wrap">
                                                <?php 
                                                $grades = explode('|', $student['grades_data']);
                                                foreach ($grades as $grade) {
                                                    list($code, $letter, $point, $marks) = explode(':', $grade);
                                                    echo '<span class="badge badge-sm" title="' . $marks . ' marks">' . $code . ': ' . $letter . '</span>';
                                                }
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-base-content/60">No grades</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-semibold"><?php echo $gpa; ?></td>
                                    <td><span class="badge badge-lg"><?php echo $gradeLetter; ?></span></td>
                                    <td class="print:hidden">
                                        <a href="report-card.php?student=<?php echo $student['id']; ?>&exam=<?php echo $examFilter; ?>" 
                                           class="btn btn-xs btn-ghost" target="_blank">
                                            View Card
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($examFilter && $sectionFilter): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No grade data found for the selected exam and section.</span>
        </div>
    <?php endif; ?>
</main>

<?php if (!empty($chartData) && !$printMode): ?>
<script>
const gradeCtx = document.getElementById('gradeDistChart').getContext('2d');
const gradeChart = new Chart(gradeCtx, {
    type: 'bar',
    data: {
        labels: ['A (Excellent)', 'B (Good)', 'C (Average)', 'D (Pass)', 'F (Fail)'],
        datasets: [{
            label: 'Number of Students',
            data: [
                <?php echo $chartData['grade_a'] ?? 0; ?>,
                <?php echo $chartData['grade_b'] ?? 0; ?>,
                <?php echo $chartData['grade_c'] ?? 0; ?>,
                <?php echo $chartData['grade_d'] ?? 0; ?>,
                <?php echo $chartData['grade_f'] ?? 0; ?>
            ],
            backgroundColor: [
                'rgb(34, 197, 94)',
                'rgb(59, 130, 246)',
                'rgb(251, 146, 60)',
                'rgb(234, 179, 8)',
                'rgb(239, 68, 68)'
            ]
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
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php if (!$printMode): ?>
    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>
