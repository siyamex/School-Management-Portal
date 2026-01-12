<?php
/**
 * Parent Dashboard
 * Dashboard for parents to view their children's information
 */

$pageTitle = "Parent Dashboard - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
requireRole('parent');

// Get parent ID
$parentRow = getRow("SELECT id FROM parents WHERE user_id = ?", [getCurrentUserId()]);
if (!$parentRow) {
    die("Parent profile not found.");
}
$parentId = $parentRow['id'];

// Get all linked children
$children = getAll("
    SELECT st.id, st.student_id, u.full_name, u.photo,
           sp.relationship, sp.is_primary_contact,
           c.class_name, s.section_name, e.roll_number,
           (SELECT COUNT(*) FROM student_attendance WHERE student_id = st.id AND status = 'present') as present_days,
           (SELECT COUNT(*) FROM student_attendance WHERE student_id = st.id) as total_days,
           (SELECT AVG(grade_point) FROM grades WHERE student_id = st.id AND is_published = 1) as avg_gpa
    FROM student_parents sp
    JOIN students st ON sp.student_id = st.id
    JOIN users u ON st.user_id = u.id
    LEFT JOIN enrollments e ON st.id = e.student_id AND e.status = 'active'
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE sp.parent_id = ?
    ORDER BY sp.is_primary_contact DESC, u.full_name
", [$parentId]);
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl lg:text-4xl font-bold text-base-content mb-2">
            Parent Portal 👪
        </h1>
        <p class="text-base-content/60"><?php echo date('l, F j, Y'); ?></p>
    </div>

    <?php if (empty($children)): ?>
        <div class="alert alert-warning shadow-lg mb-8">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <div>
                <h3 class="font-bold">No Children Linked</h3>
                <div class="text-xs">Contact the school administrator to link your child to your account.</div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info shadow-lg mb-8">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <h3 class="font-bold">Welcome to the Parent Portal!</h3>
                <div class="text-xs">View your children's grades, attendance, assignments, and communicate with teachers.</div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats stats-vertical lg:stats-horizontal shadow mb-8 w-full">
            <div class="stat">
                <div class="stat-title">My Children</div>
                <div class="stat-value text-primary"><?php echo count($children); ?></div>
            </div>
            <div class="stat">
                <div class="stat-title">Overall Attendance</div>
                <div class="stat-value text-lg">
                    <?php 
                    $totalPresent = array_sum(array_column($children, 'present_days'));
                    $totalDays = array_sum(array_column($children, 'total_days'));
                    $overallAttendance = $totalDays > 0 ? round(($totalPresent / $totalDays) * 100, 1) : 0;
                    echo $overallAttendance; 
                    ?>%
                </div>
            </div>
            <div class="stat">
                <div class="stat-title">Average GPA</div>
                <div class="stat-value text-lg">
                    <?php 
                    $avgGPAs = array_filter(array_column($children, 'avg_gpa'));
                    $overallGPA = !empty($avgGPAs) ? array_sum($avgGPAs) / count($avgGPAs) : 0;
                    echo number_format($overallGPA, 2); 
                    ?>
                </div>
            </div>
        </div>

        <!-- Children List -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach ($children as $child): 
                $attendancePercentage = $child['total_days'] > 0 
                    ? round(($child['present_days'] / $child['total_days']) * 100, 1) 
                    : 0;
                $gpa = $child['avg_gpa'] ? number_format($child['avg_gpa'], 2) : 'N/A';
                
                // Get pending assignments for this child
                $pendingCount = getValue("
                    SELECT COUNT(*) FROM assignments a
                    WHERE a.section_id = (SELECT section_id FROM enrollments WHERE student_id = ? AND status = 'active')
                    AND a.due_date >= CURDATE()
                    AND a.id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id = ?)
                ", [$child['id'], $child['id']]);
            ?>
                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition">
                    <div class="card-body">
                        <div class="flex items-start gap-4 mb-4">
                            <div class="avatar">
                                <div class="w-16 h-16 rounded-full <?php echo $child['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                                    <?php if ($child['photo']): ?>
                                        <img src="<?php echo APP_URL . '/' . $child['photo']; ?>" alt="" />
                                    <?php else: ?>
                                        <span class="text-2xl"><?php echo strtoupper(substr($child['full_name'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h2 class="card-title"><?php echo htmlspecialchars($child['full_name']); ?></h2>
                                <p class="text-sm text-base-content/60">
                                    <?php if ($child['class_name']): ?>
                                        <?php echo htmlspecialchars($child['class_name'] . ' - ' . $child['section_name']); ?> • Roll #<?php echo $child['roll_number']; ?>
                                    <?php else: ?>
                                        Not enrolled
                                    <?php endif; ?>
                                </p>
                                <div class="flex gap-1 mt-1">
                                    <span class="badge badge-sm badge-outline"><?php echo ucfirst($child['relationship']); ?></span>
                                    <?php if ($child['is_primary_contact']): ?>
                                        <span class="badge badge-sm badge-primary">Primary</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="divider my-2"></div>

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p class="text-2xl font-bold text-success"><?php echo $attendancePercentage; ?>%</p>
                                <p class="text-xs text-base-content/60">Attendance</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-primary"><?php echo $gpa; ?></p>
                                <p class="text-xs text-base-content/60">GPA</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-warning"><?php echo $pendingCount; ?></p>
                                <p class="text-xs text-base-content/60">Pending</p>
                            </div>
                        </div>

                        <div class="divider my-2"></div>

                        <!-- Quick Links -->
                        <div class="card-actions justify-between">
                            <a href="../modules/exams/view-grades.php?student_id=<?php echo $child['id']; ?>" class="btn btn-sm btn-ghost">
                                📊 Grades
                            </a>
                            <a href="../modules/attendance/view-attendance.php?student_id=<?php echo $child['id']; ?>" class="btn btn-sm btn-ghost">
                                📅 Attendance
                            </a>
                            <a href="../modules/lms/student-assignments.php?student_id=<?php echo $child['id']; ?>" class="btn btn-sm btn-ghost">
                                📚 Assignments
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Activity -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold mb-4">Recent Activity</h2>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <?php
                    // Get recent grades for all children
                    $childIds = array_column($children, 'id');
                    if (!empty($childIds)) {
                        $recentActivity = getAll("
                            SELECT 'grade' as type, g.created_at as activity_date,
                                   st.id as student_id, u.full_name as student_name,
                                   e.exam_name as title, s.subject_name as subtitle,
                                   g.grade_letter as detail
                            FROM grades g
                            JOIN students st ON g.student_id = st.id
                            JOIN users u ON st.user_id = u.id
                            JOIN exams e ON g.exam_id = e.id
                            JOIN subjects s ON g.subject_id = s.id
                            WHERE g.student_id IN (" . implode(',', $childIds) . ") AND g.is_published = 1
                            ORDER BY g.created_at DESC
                            LIMIT 10
                        ");

                        if (empty($recentActivity)) {
                            echo '<p class="text-base-content/60">No recent activity found.</p>';
                        } else {
                            echo '<div class="space-y-2">';
                            foreach ($recentActivity as $activity) {
                                echo '<div class="flex items-center justify-between p-3 bg-base-200 rounded-lg">';
                                echo '<div class="flex-1">';
                                echo '<p class="font-semibold">' . htmlspecialchars($activity['student_name']) . '</p>';
                                echo '<p class="text-sm text-base-content/60">' . htmlspecialchars($activity['title']) . ' - ' . htmlspecialchars($activity['subtitle']) . '</p>';
                                echo '</div>';
                                echo '<div class="text-right">';
                                echo '<span class="badge badge-lg">' . htmlspecialchars($activity['detail']) . '</span>';
                                echo '<p class="text-xs text-base-content/60 mt-1">' . formatDateTime($activity['activity_date']) . '</p>';
                                echo '</div>';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
