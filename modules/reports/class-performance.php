<?php
/**
 * Class Performance Dashboard
 * Compare performance across sections and classes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Class Performance - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

// Get filter parameters
$examFilter = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;

// Get exams for filter
$exams = getAll("SELECT e.*, a.year_name FROM exams e JOIN academic_years a ON e.academic_year_id = a.id ORDER BY e.created_at DESC");

// Get classes
$classes = getAll("SELECT * FROM classes ORDER BY class_numeric");

//Get performance data
$performanceData = [];

if ($examFilter) {
    if ($classFilter) {
        // Compare sections within a class
        $performanceData = getAll("
            SELECT s.id, s.section_name, c.class_name,
                   COUNT(DISTINCT e.student_id) as student_count,
                   AVG(g.grade_point) as avg_gpa,
                   AVG(g.marks_obtained) as avg_marks,
                   SUM(CASE WHEN g.grade_letter IN ('A+', 'A') THEN 1 ELSE 0 END) as a_grades,
                   SUM(CASE WHEN g.grade_letter IN ('B+', 'B') THEN 1 ELSE 0 END) as b_grades,
                   SUM(CASE WHEN g.grade_letter IN ('C+', 'C') THEN 1 ELSE 0 END) as c_grades,
                   SUM(CASE WHEN g.grade_letter = 'F' THEN 1 ELSE 0 END) as f_grades
            FROM sections s
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN enrollments e ON s.id = e.section_id AND e.status = 'active'
            LEFT JOIN grades g ON e.student_id = g.student_id AND g.exam_id = ? AND g.is_published = 1
            WHERE c.id = ?
            GROUP BY s.id
            ORDER BY s.section_name
        ", [$examFilter, $classFilter]);
    } else {
        // Compare all classes
        $performanceData = getAll("
            SELECT c.id, c.class_name,
                   COUNT(DISTINCT e.student_id) as student_count,
                   AVG(g.grade_point) as avg_gpa,
                   AVG(g.marks_obtained) as avg_marks,
                   SUM(CASE WHEN g.grade_letter IN ('A+', 'A') THEN 1 ELSE 0 END) as a_grades,
                   SUM(CASE WHEN g.grade_letter IN ('B+', 'B') THEN 1 ELSE 0 END) as b_grades,
                   SUM(CASE WHEN g.grade_letter IN ('C+', 'C') THEN 1 ELSE 0 END) as c_grades,
                   SUM(CASE WHEN g.grade_letter = 'F' THEN 1 ELSE 0 END) as f_grades
            FROM classes c
            LEFT JOIN sections s ON c.id = s.class_id
            LEFT JOIN enrollments e ON s.id = e.section_id AND e.status = 'active'
            LEFT JOIN grades g ON e.student_id = g.student_id AND g.exam_id = ? AND g.is_published = 1
            GROUP BY c.id
            ORDER BY c.class_numeric
        ", [$examFilter]);
    }
}

// Prepare chart data
$chartLabels = [];
$chartGPAs = [];
if (!empty($performanceData)) {
    foreach ($performanceData as $data) {
        $label = isset($data['section_name']) 
            ? $data['class_name'] . ' - ' . $data['section_name']
            : $data['class_name'];
        $chartLabels[] = $label;
        $chartGPAs[] = $data['avg_gpa'] ?: 0;
    }
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Class Performance Dashboard</h1>
        <p class="text-base-content/60 mt-1">Compare academic performance across classes and sections</p>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Exam *</span></label>
                    <select name="exam" class="select select-bordered" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $examFilter == $exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['exam_name'] . ' (' . $exam['year_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Class (Optional)</span></label>
                    <select name="class" class="select select-bordered">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $classFilter == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">&nbsp;</span></label>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($performanceData)): ?>
        <!-- Performance Chart -->
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h3 class="card-title">Average GPA Comparison</h3>
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Performance Table -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Detailed Comparison</h3>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th><?php echo $classFilter ? 'Section' : 'Class'; ?></th>
                                <th>Students</th>
                                <th>Avg GPA</th>
                                <th>Avg Marks</th>
                                <th>A Grades</th>
                                <th>B Grades</th>
                                <th>C Grades</th>
                                <th>F Grades</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performanceData as $data): 
                                $name = isset($data['section_name']) 
                                    ? $data['class_name'] . ' - ' . $data['section_name']
                                    : $data['class_name'];
                                $gpa = $data['avg_gpa'] ?: 0;
                                $performanceClass = $gpa >= 3.5 ? 'badge-success' : ($gpa >= 2.5 ? 'badge-warning' : 'badge-error');
                                $performanceText = $gpa >= 3.5 ? 'Excellent' : ($gpa >= 2.5 ? 'Good' : 'Needs Improvement');
                            ?>
                                <tr>
                                    <td class="font-semibold"><?php echo htmlspecialchars($name); ?></td>
                                    <td><?php echo $data['student_count']; ?></td>
                                    <td class="font-bold text-lg"><?php echo number_format($gpa, 2); ?></td>
                                    <td><?php echo round($data['avg_marks'] ?: 0); ?></td>
                                    <td class="text-success font-semibold"><?php echo $data['a_grades']; ?></td>
                                    <td class="text-info font-semibold"><?php echo $data['b_grades']; ?></td>
                                    <td class="text-warning font-semibold"><?php echo $data['c_grades']; ?></td>
                                    <td class="text-error font-semibold"><?php echo $data['f_grades']; ?></td>
                                    <td><span class="badge <?php echo $performanceClass; ?>"><?php echo $performanceText; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($examFilter): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No performance data available for the selected criteria.</span>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Select an exam to view class performance comparison.</span>
        </div>
    <?php endif; ?>
</main>

<?php if (!empty($performanceData)): ?>
<script>
// Performance Comparison Chart
const perfCtx = document.getElementById('performanceChart').getContext('2d');
const perfChart = new Chart(perfCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Average GPA',
            data: <?php echo json_encode($chartGPAs); ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.5)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
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
