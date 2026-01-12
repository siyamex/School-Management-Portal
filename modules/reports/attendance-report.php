<?php
/**
 * Attendance Report with Charts
 * Visual analytics for attendance data
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Attendance Report - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

// Get filter parameters
$sectionFilter = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$studentFilter = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Get sections for filter
$sections = getAll("
    SELECT s.id, s.section_name, c.class_name
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

// Get students if section selected
$students = [];
if ($sectionFilter) {
    $students = getAll("
        SELECT st.id, u.full_name, e.roll_number
        FROM enrollments e
        JOIN students st ON e.student_id = st.id
        JOIN users u ON st.user_id = u.id
        WHERE e.section_id = ? AND e.status = 'active'
        ORDER BY e.roll_number
    ", [$sectionFilter]);
}

// Build query for attendance data
$reportData = [];
$chartData = [];

if ($sectionFilter || $studentFilter) {
    $whereClauses = [];
    $params = [];
    
    if ($studentFilter) {
        $whereClauses[] = "sa.student_id = ?";
        $params[] = $studentFilter;
    } elseif ($sectionFilter) {
        $whereClauses[] = "sa.section_id = ?";
        $params[] = $sectionFilter;
    }
    
    $whereClauses[] = "sa.attendance_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    
    $whereSQL = implode(' AND ', $whereClauses);
    
    // Get overall stats
    $stats = getRow("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
        FROM student_attendance sa
        WHERE $whereSQL
    ", $params);
    
    // Get daily breakdown for chart
    $dailyData = getAll("
        SELECT 
            attendance_date,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM student_attendance sa
        WHERE $whereSQL
        GROUP BY attendance_date
        ORDER BY attendance_date
    ", $params);
    
    // Get student-wise data if section selected
    if ($sectionFilter && !$studentFilter) {
        $reportData = getAll("
            SELECT st.id, u.full_name, e.roll_number,
                   COUNT(*) as total_days,
                   SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
                   SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
                   SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late
            FROM enrollments e
            JOIN students st ON e.student_id = st.id
            JOIN users u ON st.user_id = u.id
            LEFT JOIN student_attendance sa ON st.id = sa.student_id 
                AND sa.attendance_date BETWEEN ? AND ?
            WHERE e.section_id = ? AND e.status = 'active'
            GROUP BY st.id
            ORDER BY e.roll_number
        ", [$startDate, $endDate, $sectionFilter]);
    }
    
    $chartData = $dailyData;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($reportData)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Roll No', 'Student Name', 'Total Days', 'Present', 'Absent', 'Late', 'Attendance %']);
    
    foreach ($reportData as $row) {
        $percentage = $row['total_days'] > 0 ? round(($row['present'] / $row['total_days']) * 100, 1) : 0;
        fputcsv($output, [
            $row['roll_number'],
            $row['full_name'],
            $row['total_days'],
            $row['present'],
            $row['absent'],
            $row['late'],
            $percentage . '%'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Attendance Report</h1>
        <p class="text-base-content/60 mt-1">Visual analytics and detailed attendance statistics</p>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Class/Section</span></label>
                    <select name="section" class="select select-bordered select-sm" onchange="this.form.submit()">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $sectionFilter == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($sectionFilter): ?>
                    <div class="form-control">
                        <label class="label"><span class="label-text">Student (Optional)</span></label>
                        <select name="student" class="select select-bordered select-sm">
                            <option value="">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $studentFilter == $student['id'] ? 'selected' : ''; ?>>
                                    #<?php echo $student['roll_number']; ?> - <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Start Date</span></label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="input input-bordered input-sm" />
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">End Date</span></label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="input input-bordered input-sm" />
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">&nbsp;</span></label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-1">Generate</button>
                        <?php if (!empty($reportData)): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success btn-sm">
                                📥 CSV
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($stats) && $stats['total_records'] > 0): ?>
        <!-- Summary Stats -->
        <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
            <div class="stat">
                <div class="stat-title">Total Records</div>
                <div class="stat-value text-primary"><?php echo $stats['total_records']; ?></div>
            </div>
            <div class="stat">
                <div class="stat-title">Present</div>
                <div class="stat-value text-success"><?php echo $stats['present']; ?></div>
                <div class="stat-desc">
                    <?php echo $stats['total_records'] > 0 ? round(($stats['present'] / $stats['total_records']) * 100, 1) : 0; ?>%
                </div>
            </div>
            <div class="stat">
                <div class="stat-title">Absent</div>
                <div class="stat-value text-error"><?php echo $stats['absent']; ?></div>
                <div class="stat-desc">
                    <?php echo $stats['total_records'] > 0 ? round(($stats['absent'] / $stats['total_records']) * 100, 1) : 0; ?>%
                </div>
            </div>
            <div class="stat">
                <div class="stat-title">Late</div>
                <div class="stat-value text-warning"><?php echo $stats['late']; ?></div>
            </div>
        </div>

        <!-- Charts -->
        <?php if (!empty($chartData)): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Daily Trend Chart -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">Daily Attendance Trend</h3>
                        <canvas id="dailyTrendChart"></canvas>
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">Status Distribution</h3>
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Student-wise Report -->
        <?php if (!empty($reportData)): ?>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Student-wise Attendance</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Roll</th>
                                    <th>Student</th>
                                    <th>Total Days</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Late</th>
                                    <th>Attendance %</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): 
                                    $percentage = $row['total_days'] > 0 ? round(($row['present'] / $row['total_days']) * 100, 1) : 0;
                                    $statusClass = $percentage >= 90 ? 'badge-success' : ($percentage >= 75 ? 'badge-warning' : 'badge-error');
                                ?>
                                    <tr>
                                        <td><?php echo str_pad($row['roll_number'], 2, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo $row['total_days']; ?></td>
                                        <td class="text-success font-semibold"><?php echo $row['present']; ?></td>
                                        <td class="text-error font-semibold"><?php echo $row['absent']; ?></td>
                                        <td class="text-warning font-semibold"><?php echo $row['late']; ?></td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold"><?php echo $percentage; ?>%</span>
                                                <progress class="progress progress-success w-20" value="<?php echo $percentage; ?>" max="100"></progress>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm <?php echo $statusClass; ?>">
                                                <?php echo $percentage >= 90 ? 'Excellent' : ($percentage >= 75 ? 'Good' : 'Poor'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($sectionFilter || $studentFilter): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No attendance data found for the selected criteria.</span>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Select a class/section to generate attendance report.</span>
        </div>
    <?php endif; ?>
</main>

<?php if (!empty($chartData)): ?>
<script>
// Daily Trend Chart
const dailyCtx = document.getElementById('dailyTrendChart').getContext('2d');
const dailyChart = new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($chartData, 'attendance_date')); ?>,
        datasets: [
            {
                label: 'Present',
                data: <?php echo json_encode(array_column($chartData, 'present')); ?>,
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.4
            },
            {
                label: 'Absent',
                data: <?php echo json_encode(array_column($chartData, 'absent')); ?>,
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4
            },
            {
                label: 'Late',
                data: <?php echo json_encode(array_column($chartData, 'late')); ?>,
                borderColor: 'rgb(251, 146, 60)',
                backgroundColor: 'rgba(251, 146, 60, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
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

// Status Pie Chart
const pieCtx = document.getElementById('statusPieChart').getContext('2d');
const pieChart = new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent', 'Late', 'Excused'],
        datasets: [{
            data: [
                <?php echo $stats['present']; ?>,
                <?php echo $stats['absent']; ?>,
                <?php echo $stats['late']; ?>,
                <?php echo $stats['excused']; ?>
            ],
            backgroundColor: [
                'rgb(34, 197, 94)',
                'rgb(239, 68, 68)',
                'rgb(251, 146, 60)',
                'rgb(59, 130, 246)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
