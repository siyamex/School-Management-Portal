<?php
/**
 * Teacher Profile View
 * Detailed teacher information and assigned subjects
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Teacher Profile - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

// Get teacher ID
$teacherId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get teacher details
$teacher = getRow("
    SELECT t.*, u.full_name, u.email, u.phone, u.photo, u.created_at
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
", [$teacherId]);

if (!$teacher) {
    die("Teacher not found.");
}

// Get assigned subjects
$assignedSubjects = getAll("
    SELECT ts.id, sub.subject_name, sub.subject_code,
           c.class_name, s.section_name,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = s.id AND status = 'active') as student_count
    FROM teacher_subjects ts
    JOIN subjects sub ON ts.subject_id = sub.id
    JOIN sections s ON ts.section_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE ts.teacher_id = ?
    ORDER BY c.class_numeric, s.section_name
", [$teacherId]);

// Get class teacher sections
$classTeacherSections = getAll("
    SELECT s.id, s.section_name, c.class_name,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = s.id AND status = 'active') as student_count
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    WHERE s.class_teacher_id = ?
", [$teacherId]);

// Get assignment statistics
$assignmentStats = getRow("
    SELECT 
        COUNT(DISTINCT a.id) as total_assignments,
        COUNT(DISTINCT CASE WHEN a.due_date >= CURDATE() THEN a.id END) as active_assignments,
        COUNT(DISTINCT asub.id) as total_submissions
    FROM assignments a
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id
    WHERE a.teacher_id = (SELECT user_id FROM teachers WHERE id = ?)
", [$teacherId]);

// Get attendance marking statistics
$attendanceStats = getRow("
    SELECT 
        COUNT(DISTINCT attendance_date) as days_marked,
        COUNT(*) as total_records
    FROM student_attendance
    WHERE teacher_id = (SELECT user_id FROM teachers WHERE id = ?)
", [$teacherId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="../users/index.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Users
        </a>
        <h1 class="text-3xl font-bold">Teacher Profile</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="space-y-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body items-center text-center">
                    <div class="avatar mb-4">
                        <div class="w-32 h-32 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2 <?php echo $teacher['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                            <?php if ($teacher['photo']): ?>
                                <img src="<?php echo APP_URL . '/' . $teacher['photo']; ?>" alt="" />
                            <?php else: ?>
                                <span class="text-5xl"><?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h2 class="card-title text-2xl"><?php echo htmlspecialchars($teacher['full_name']); ?></h2>
                    <div class="badge badge-lg badge-primary">Teacher</div>
                    
                    <div class="divider"></div>
                    
                    <div class="w-full space-y-2 text-sm text-left">
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
                        <?php if ($teacher['phone']): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($teacher['phone']); ?></p>
                        <?php endif; ?>
                        <?php if ($teacher['department']): ?>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($teacher['department']); ?></p>
                        <?php endif; ?>
                        <?php if ($teacher['employee_id']): ?>
                            <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($teacher['employee_id']); ?></p>
                        <?php endif; ?>
                        <?php if ($teacher['date_of_joining']): ?>
                            <p><strong>Joined:</strong> <?php echo formatDate($teacher['date_of_joining'], 'M d, Y'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold mb-2">Quick Actions</h3>
                    <div class="space-y-2">
                        <a href="assign-subjects.php" class="btn btn-sm btn-outline btn-block">Manage Subjects</a>
                        <a href="../users/edit.php?id=<?php echo $teacher['user_id']; ?>" class="btn btn-sm btn-outline btn-block">Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Stats -->
            <div class="stats stats-vertical sm:stats-horizontal shadow w-full">
                <div class="stat">
                    <div class="stat-title">Subjects Teaching</div>
                    <div class="stat-value text-lg"><?php echo count($assignedSubjects); ?></div>
                    <div class="stat-desc">Different classes</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Assignments</div>
                    <div class="stat-value text-lg"><?php echo $assignmentStats['total_assignments']; ?></div>
                    <div class="stat-desc"><?php echo $assignmentStats['active_assignments']; ?> active</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Attendance</div>
                    <div class="stat-value text-lg"><?php echo $attendanceStats['days_marked']; ?></div>
                    <div class="stat-desc">Days marked</div>
                </div>
            </div>

            <!-- Class Teacher Sections -->
            <?php if (!empty($classTeacherSections)): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title">Class Teacher Of</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($classTeacherSections as $section): ?>
                                <div class="border border-base-300 rounded-lg p-4">
                                    <h4 class="font-bold"><?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?></h4>
                                    <p class="text-sm text-base-content/60"><?php echo $section['student_count']; ?> students</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Assigned Subjects -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Assigned Subjects</h3>
                    <?php if (empty($assignedSubjects)): ?>
                        <p class="text-base-content/60">No subjects assigned yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Code</th>
                                        <th>Class/Section</th>
                                        <th>Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedSubjects as $subject): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                            <td><span class="badge badge-sm"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                                            <td><?php echo htmlspecialchars($subject['class_name'] . ' - ' . $subject['section_name']); ?></td>
                                            <td><?php echo $subject['student_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Teaching Activity -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Teaching Activity</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-base-content/60">Total Assignments Created</p>
                            <p class="text-2xl font-bold"><?php echo $assignmentStats['total_assignments']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-base-content/60">Submissions Received</p>
                            <p class="text-2xl font-bold"><?php echo $assignmentStats['total_submissions']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-base-content/60">Attendance Days Marked</p>
                            <p class="text-2xl font-bold"><?php echo $attendanceStats['days_marked']; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-base-content/60">Total Records</p>
                            <p class="text-2xl font-bold"><?php echo $attendanceStats['total_records']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
