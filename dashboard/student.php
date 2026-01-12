<?php
/**
 * Student Dashboard
 * Main dashboard for students showing grades, assignments, attendance, and achievements
 */

$pageTitle = "Student Dashboard - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';

// Require student role
requireRole('student');

// Get student ID
$studentRow = getRow("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
if (!$studentRow) {
    die("Student profile not found. Please contact administrator.");
}
$studentId = $studentRow['id'];

// Get current enrollment
$enrollment = getCurrentEnrollment($studentId);

// Get recent grades
$recentGrades = getAll("
    SELECT g.*, s.subject_name, e.exam_name
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    JOIN exams e ON g.exam_id = e.id
    WHERE g.student_id = ? AND g.is_published = 1
    ORDER BY g.created_at DESC
    LIMIT 5
", [$studentId]);

// Get pending assignments
$pendingAssignments = getAll("
    SELECT a.*, s.subject_name,
           COALESCE(sub.status, 'pending') as submission_status
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
    WHERE a.section_id = ? AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
", [$studentId, $enrollment['section_id'] ?? 0]);

// Get attendance stats for current month
$attendanceStats = getRow("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status= 'late' THEN 1 ELSE 0 END) as late_days
    FROM student_attendance
    WHERE student_id = ? AND MONTH(attendance_date) = MONTH(CURDATE())
", [$studentId]);

// Get badges/achievements
$badges = getAll("
    SELECT sb.*, ab.badge_name, ab.badge_description, ab.badge_icon, ab.points
    FROM student_badges sb
    JOIN achievement_badges ab ON sb.badge_id = ab.id
    WHERE sb.student_id = ?
    ORDER BY sb.awarded_date DESC
    LIMIT 6
", [$studentId]);

// Calculate attendance percentage
$attendancePercent = $attendanceStats['total_days'] > 0 
    ? round(($attendanceStats['present_days'] / $attendanceStats['total_days']) * 100, 1) 
    : 0;

// Get total points from badges
$totalPoints = getValue("
    SELECT COALESCE(SUM(ab.points), 0)
    FROM student_badges sb
    JOIN achievement_badges ab ON sb.badge_id = ab.id
    WHERE sb.student_id = ?
", [$studentId]);

// Get reading badges
$readingBadges = getAll("
    SELECT srb.*, rb.badge_name, rb.badge_description, rb.badge_icon
    FROM student_reading_badges srb
    JOIN reading_badges rb ON srb.badge_id = rb.id
    WHERE srb.student_id = ?
    ORDER BY srb.awarded_date DESC
    LIMIT 4
", [$studentId]);

// Get reading stats
$readingStats = getRow("
    SELECT 
        COUNT(*) as total_books,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_books,
        SUM(pages_read) as total_pages_read
    FROM reading_logs
    WHERE student_id = ?
", [$studentId]);
?>

<!-- Sidebar -->
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 p-6 lg:p-8">
    <!-- Student Profile Card -->
    <div class="card bg-base-200 shadow-2xl mb-8">
        <div class="card-body">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                <!-- Profile Photo -->
                <div class="avatar">
                    <div class="w-24 h-24 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                        <?php if ($currentUser['photo']): ?>
                            <img src="<?php echo APP_URL . '/' . $currentUser['photo']; ?>" alt="Profile" />
                        <?php else: ?>
                            <div class="bg-primary text-primary-content text-4xl flex items-center justify-center w-full h-full">
                                <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Student Info -->
                <div class="flex-1 text-center md:text-left">
                    <h1 class="text-3xl lg:text-4xl font-bold mb-2">
                        <?php echo htmlspecialchars($currentUser['full_name']); ?>
                    </h1>
                    <p class="text-base-content/70 mb-3">
                        <span class="inline-flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <?php echo htmlspecialchars($currentUser['email']); ?>
                        </span>
                    </p>
                    
                    <?php if ($enrollment): ?>
                        <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                            <div class="badge badge-lg badge-primary">
                                📚 <?php echo htmlspecialchars($enrollment['class_name']); ?>
                            </div>
                            <div class="badge badge-lg badge-primary">
                                🏫 Section <?php echo htmlspecialchars($enrollment['section_name']); ?>
                            </div>
                            <?php if (isset($enrollment['roll_number'])): ?>
                                <div class="badge badge-lg badge-primary">
                                    #<?php echo htmlspecialchars($enrollment['roll_number']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="badge badge-warning">Not enrolled in any class</div>
                    <?php endif; ?>
                    
                    <p class="text-sm text-base-content/60 mt-3">
                        <?php echo date('l, F j, Y'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
        <!-- Attendance -->
        <div class="stats shadow-lg hover:shadow-xl transition-all card-hover bg-gradient-to-br from-primary/10 to-primary/5">
            <div class="stat">
                <div class="stat-figure text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-title">Attendance</div>
                <div class="stat-value text-primary"><?php echo $attendancePercent; ?>%</div>
                <div class="stat-desc"><?php echo $attendanceStats['present_days']; ?> of <?php echo $attendanceStats['total_days']; ?> days</div>
            </div>
        </div>

        <!-- Pending Assignments -->
        <div class="stats shadow-lg hover:shadow-xl transition-all card-hover bg-gradient-to-br from-secondary/10 to-secondary/5">
            <div class="stat">
                <div class="stat-figure text-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="stat-title">Pending Tasks</div>
                <div class="stat-value text-secondary"><?php echo count($pendingAssignments); ?></div>
                <div class="stat-desc">assignments due soon</div>
            </div>
        </div>

        <!-- Achievement Points -->
        <div class="stats shadow-lg hover:shadow-xl transition-all card-hover bg-gradient-to-br from-accent/10 to-accent/5">
            <div class="stat">
                <div class="stat-figure text-accent">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                </div>
                <div class="stat-title">Points</div>
                <div class="stat-value text-accent"><?php echo $totalPoints; ?></div>
                <div class="stat-desc"><?php echo count($badges); ?> badges earned</div>
            </div>
        </div>

        <!-- Overall Grade -->
        <div class="stats shadow-lg hover:shadow-xl transition-all card-hover bg-gradient-to-br from-success/10 to-success/5">
            <div class="stat">
                <div class="stat-figure text-success">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path d="M12 14l9-5-9-5-9 5 9 5z" /><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 14l9-5-9-5-9 5 9 5zm-0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                    </svg>
                </div>
                <div class="stat-title">Average Grade</div>
                <div class="stat-value text-success">
                    <?php 
                    $avgGrade = getValue("SELECT AVG(grade_point) FROM grades WHERE student_id = ? AND is_published = 1", [$studentId]);
                    echo $avgGrade ? number_format($avgGrade, 2) : 'N/A';
                    ?>
                </div>
                <div class="stat-desc">GPA this semester</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Grades -->
        <div class="card bg-base-100 shadow-xl card-hover">
            <div class="card-body">
                <h2 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    Recent Grades
                </h2>
                
                <?php if (empty($recentGrades)): ?>
                    <p class="text-base-content/60 text-center py-8">No grades published yet</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Exam</th>
                                    <th class="text-right">Score</th>
                                    <th class="text-right">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentGrades as $grade): ?>
                                    <tr>
                                        <td class="font-medium"><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                        <td class="text-sm text-base-content/60"><?php echo htmlspecialchars($grade['exam_name']); ?></td>
                                        <td class="text-right"><?php echo $grade['marks_obtained']; ?></td>
                                        <td class="text-right">
                                            <span class="badge badge-primary"><?php echo $grade['grade_letter']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-actions justify-end mt-4">
                        <a href="<?php echo APP_URL; ?>/modules/exams/view-grades.php" class="btn btn-sm btn-outline btn-primary">
                            View All Grades
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Assignments -->
        <div class="card bg-base-100 shadow-xl card-hover">
            <div class="card-body">
                <h2 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    Upcoming Assignments
                </h2>
                
                <?php if (empty($pendingAssignments)): ?>
                    <p class="text-base-content/60 text-center py-8">No pending assignments</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($pendingAssignments as $assignment): 
                            $daysLeft = (strtotime($assignment['due_date']) - time()) / (60 * 60 * 24);
                            $urgentClass = $daysLeft <= 2 ? 'border-error' : ($daysLeft <= 5 ? 'border-warning' : 'border-base-300');
                        ?>
                            <div class="p-3 border-l-4 <?php echo $urgentClass; ?> bg-base-200 rounded-r-lg">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-semibold text-sm"><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                        <p class="text-xs text-base-content/60"><?php echo htmlspecialchars($assignment['subject_name']); ?></p>
                                    </div>
                                    <span class="badge badge-sm <?php echo $assignment['submission_status'] === 'submitted' ? 'badge-success' : ''; ?>">
                                        <?php echo ucfirst($assignment['submission_status']); ?>
                                    </span>
                                </div>
                                <p class="text-xs text-base-content/60 mt-2">
                                    Due: <?php echo formatDate($assignment['due_date'], 'M d, Y'); ?>
                                    (<?php echo ceil($daysLeft); ?> days left)
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-actions justify-end mt-4">
                        <a href="<?php echo APP_URL; ?>/modules/lms/student-assignments.php" class="btn btn-sm btn-outline btn-secondary">
                            View All Assignments
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Reading Badges -->
        <?php if (!empty($readingBadges)): ?>
        <div class="card bg-base-100 shadow-xl card-hover">
            <div class="card-body">
                <h2 class="card-title">
                    📚 Reading Badges
                </h2>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <?php foreach ($readingBadges as $badge): ?>
                        <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg hover:shadow-md transition-all">
                            <?php if ($badge['badge_icon']): ?>
                                <img src="<?php echo APP_URL . '/' . $badge['badge_icon']; ?>" class="w-12 h-12" alt="Badge">
                            <?php else: ?>
                                <div class="text-3xl">🏅</div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($badge['badge_name']); ?></p>
                                <p class="text-xs text-base-content/60"><?php echo formatDate($badge['awarded_date'], 'M d, Y'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-actions justify-end mt-4">
                    <a href="<?php echo APP_URL; ?>/modules/lms/my-reading-log.php" class="btn btn-sm btn-outline btn-primary">
                        View Reading Log
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Achievement Badges -->
        <?php if (!empty($badges)): ?>
        <div class="card bg-base-100 shadow-xl card-hover">
            <div class="card-body">
                <h2 class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                    Achievements
                </h2>
                <div class="grid grid-cols-3 gap-3 mt-2">
                    <?php foreach (array_slice($badges, 0, 6) as $badge): ?>
                        <div class="text-center p-3 bg-base-200 rounded-lg hover:bg-base-300 transition-all">
                            <div class="text-3xl mb-1"><?php echo $badge['badge_icon'] ?: '🏆'; ?></div>
                            <p class="text-xs font-semibold"><?php echo htmlspecialchars($badge['badge_name']); ?></p>
                            <p class="text-xs text-base-content/60"><?php echo $badge['points']; ?> pts</p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-actions justify-end mt-4">
                    <a href="<?php echo APP_URL; ?>/modules/lms/badges.php" class="btn btn-sm btn-outline btn-accent">
                        View All
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" stroke-linejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
