<?php
/**
 * Student Profile/View
 * Detailed student information and academic history
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Student Profile - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

// Get student ID
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get student details
$student = getRow("
    SELECT st.*, u.full_name, u.email, u.phone, u.photo, u.created_at
    FROM students st
    JOIN users u ON st.user_id = u.id
    WHERE st.id = ?
", [$studentId]);

if (!$student) {
    die("Student not found.");
}

// Get current enrollment
$enrollment = getRow("
    SELECT e.*, s.section_name, c.class_name, c.id as class_id, a.year_name
    FROM enrollments e
    JOIN sections s ON e.section_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN academic_years a ON e.academic_year_id = a.id
    WHERE e.student_id = ? AND e.status = 'active'
    ORDER BY e.enrollment_date DESC
    LIMIT 1
", [$studentId]);

// Get attendance statistics
$attendanceStats = getRow("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
    FROM student_attendance
    WHERE student_id = ?
", [$studentId]);

$attendancePercentage = $attendanceStats['total_days'] > 0 
    ? round(($attendanceStats['present'] / $attendanceStats['total_days']) * 100, 1) 
    : 0;

// Get recent grades
$recentGrades = getAll("
    SELECT g.*, e.exam_name, s.subject_name
    FROM grades g
    JOIN exams e ON g.exam_id = e.id
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ? AND g.is_published = 1
    ORDER BY e.created_at DESC
    LIMIT 5
", [$studentId]);

// Calculate average GPA
$avgGPA = getValue("
    SELECT AVG(grade_point) 
    FROM grades 
    WHERE student_id = ? AND is_published = 1
", [$studentId]) ?: 0;

// Get pending assignments
$pendingAssignments = getAll("
    SELECT a.*, s.subject_name,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as is_submitted
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.section_id = ? AND a.due_date >= CURDATE() AND a.id NOT IN 
          (SELECT assignment_id FROM assignment_submissions WHERE student_id = ?)
    ORDER BY a.due_date ASC
    LIMIT 5
", [$studentId, $enrollment['section_id'] ?? 0, $studentId]);

// Get linked parents
$parents = getAll("
    SELECT p.*, u.full_name, u.email, u.phone, sp.is_primary
    FROM student_parents sp
    JOIN parents p ON sp.parent_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE sp.student_id = ?
    ORDER BY sp.is_primary DESC
", [$studentId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="list.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Students
        </a>
        <h1 class="text-3xl font-bold">Student Profile</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="space-y-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body items-center text-center">
                    <div class="avatar mb-4">
                        <div class="w-32 h-32 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2 <?php echo $student['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                            <?php if ($student['photo']): ?>
                                <img src="<?php echo APP_URL . '/' . $student['photo']; ?>" alt="" />
                            <?php else: ?>
                                <span class="text-5xl"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h2 class="card-title text-2xl"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                    <p class="font-mono text-sm text-base-content/60"><?php echo htmlspecialchars($student['student_id']); ?></p>
                    
                    <?php if ($enrollment): ?>
                        <div class="badge badge-lg badge-primary"><?php echo htmlspecialchars($enrollment['class_name'] . ' - ' . $enrollment['section_name']); ?></div>
                    <?php else: ?>
                        <div class="badge badge-lg badge-warning">Not Enrolled</div>
                    <?php endif; ?>
                    
                    <div class="divider"></div>
                    
                    <div class="w-full space-y-2 text-sm text-left">
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                        <?php if ($student['phone']): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone']); ?></p>
                        <?php endif; ?>
                        <?php if ($student['date_of_birth']): ?>
                            <p><strong>DOB:</strong> <?php echo formatDate($student['date_of_birth'], 'M d, Y'); ?></p>
                        <?php endif; ?>
                        <?php if ($student['gender']): ?>
                            <p><strong>Gender:</strong> <?php echo ucfirst($student['gender']); ?></p>
                        <?php endif; ?>
                        <?php if ($enrollment): ?>
                            <p><strong>Roll No:</strong> <?php echo $enrollment['roll_number']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold mb-2">Quick Actions</h3>
                    <div class="space-y-2">
                        <?php if (!$enrollment): ?>
                            <a href="enroll.php?student_id=<?php echo $studentId; ?>" class="btn btn-sm btn-primary btn-block">Enroll Student</a>
                        <?php endif; ?>
                        <a href="link-parent.php?student_id=<?php echo $studentId; ?>" class="btn btn-sm btn-outline btn-block">Link Parent</a>
                        <a href="../users/edit.php?id=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-outline btn-block">Edit Profile</a>
                    </div>
                </div>
            </div>

            <!-- Linked Parents -->
            <?php if (!empty($parents)): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="font-bold mb-2">Parents/Guardians</h3>
                        <?php foreach ($parents as $parent): ?>
                            <div class="border-b border-base-300 pb-2 mb-2 last:border-0">
                                <p class="font-semibold"><?php echo htmlspecialchars($parent['full_name']); ?></p>
                                <p class="text-xs text-base-content/60">
                                    <?php if ($parent['is_primary']): ?>
                                        <span class="badge badge-xs badge-primary">Primary Contact</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs"><?php echo htmlspecialchars($parent['email']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Stats -->
            <div class="stats stats-vertical sm:stats-horizontal shadow w-full">
                <div class="stat">
                    <div class="stat-title">Attendance</div>
                    <div class="stat-value text-lg"><?php echo $attendancePercentage; ?>%</div>
                    <div class="stat-desc"><?php echo $attendanceStats['present']; ?> / <?php echo $attendanceStats['total_days']; ?> days</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Average GPA</div>
                    <div class="stat-value text-lg"><?php echo number_format($avgGPA, 2); ?></div>
                    <div class="stat-desc">Out of 4.0</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Pending</div>
                    <div class="stat-value text-lg"><?php echo count($pendingAssignments); ?></div>
                    <div class="stat-desc">Assignments</div>
                </div>
            </div>

            <!-- Recent Grades -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Recent Grades</h3>
                    <?php if (empty($recentGrades)): ?>
                        <p class="text-base-content/60">No grades available yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Marks</th>
                                        <th>Grade</th>
                                        <th>GPA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentGrades as $grade): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($grade['exam_name']); ?></td>
                                            <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                            <td><?php echo $grade['marks_obtained']; ?></td>
                                            <td><span class="badge badge-sm"><?php echo $grade['grade_letter']; ?></span></td>
                                            <td><?php echo number_format($grade['grade_point'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="../exams/view-grades.php" class="btn btn-sm btn-ghost">View All Grades →</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Assignments -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Pending Assignments</h3>
                    <?php if (empty($pendingAssignments)): ?>
                        <p class="text-base-content/60">No pending assignments.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($pendingAssignments as $assignment): ?>
                                <div class="border border-base-300 rounded-lg p-3">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold"><?php echo htmlspecialchars($assignment['title']); ?></p>
                                            <p class="text-xs text-base-content/60"><?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                        </div>
                                        <span class="badge badge-warning">Due: <?php echo formatDate($assignment['due_date'], 'M d'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Academic Information -->
            <?php if ($enrollment): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">Enrollment Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-base-content/60">Academic Year</p>
                                <p class="font-semibold"><?php echo htmlspecialchars($enrollment['year_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/60">Class</p>
                                <p class="font-semibold"><?php echo htmlspecialchars($enrollment['class_name'] . ' - ' . $enrollment['section_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/60">Roll Number</p>
                                <p class="font-semibold"><?php echo $enrollment['roll_number']; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-base-content/60">Enrollment Date</p>
                                <p class="font-semibold"><?php echo formatDate($enrollment['enrollment_date'], 'M d, Y'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
