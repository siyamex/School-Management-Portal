<?php
/**
 * Teacher Dashboard
 * Dashboard for teachers with class management, assignments, and HR functions
 */

$pageTitle = "Teacher Dashboard - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
requireRole(['teacher', 'leading_teacher']);

$user = getCurrentUser();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl lg:text-4xl font-bold text-base-content mb-2">
            Teacher Dashboard 👨‍🏫
        </h1>
        <p class="text-base-content/60"><?php echo date('l, F j, Y'); ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stats shadow-lg bg-primary text-primary-content">
            <div class="stat">
                <div class="stat-title text-primary-content/80">My Classes</div>
                <div class="stat-value">5</div>
                <div class="stat-desc text-primary-content/60">Active this semester</div>
            </div>
        </div>
        
        <div class="stats shadow-lg bg-secondary text-secondary-content">
            <div class="stat">
                <div class="stat-title text-secondary-content/80">Assignments</div>
                <div class="stat-value">12</div>
                <div class="stat-desc text-secondary-content/60">Pending grading</div>
            </div>
        </div>
        
        <div class="stats shadow-lg bg-accent text-accent-content">
            <div class="stat">
                <div class="stat-title text-accent-content/80">Students</div>
                <div class="stat-value">150</div>
                <div class="stat-desc text-accent-content/60">Total enrolled</div>
            </div>
        </div>
        
        <div class="stats shadow-lg bg-success text-success-content">
            <div class="stat">
                <div class="stat-title text-success-content/80">Leave Balance</div>
                <div class="stat-value">8</div>
                <div class="stat-desc text-success-content/60">Days remaining</div>
            </div>
        </div>
    </div>

    <div class="alert alert-info shadow-lg mb-8">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <div>
            <h3 class="font-bold">Welcome to the Teacher Portal!</h3>
            <div class="text-xs">Use the sidebar to access class management, assignments, attendance marking, and HR functions.</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Quick Actions</h2>
                <div class="space-y-2">
                    <a href="<?php echo APP_URL; ?>/modules/attendance/student-attendance.php" class="btn btn-outline w-full justify-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                        Mark Attendance
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/lms/assignments.php" class="btn btn-outline w-full justify-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        Create Assignment
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/exams/grade-entry.php" class="btn btn-outline w-full justify-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        Enter Grades
                    </a>
                    <a href="<?php echo APP_URL; ?>/modules/hr/leave-request.php" class="btn btn-outline w-full justify-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Request Leave
                    </a>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Today's Schedule</h2>
                <div class="space-y-2">
                    <div class="p-3 bg-base-200 rounded-lg">
                        <div class="flex justify-between">
                            <span class="font-semibold">Mathematics</span>
                            <span class="badge badge-primary">Grade 10-A</span>
                        </div>
                        <p class="text-sm text-base-content/60">8:00 AM - 9:00 AM • Room 201</p>
                    </div>
                    <div class="p-3 bg-base-200 rounded-lg">
                        <div class="flex justify-between">
                            <span class="font-semibold">Physics</span>
                            <span class="badge badge-secondary">Grade 11-B</span>
                        </div>
                        <p class="text-sm text-base-content/60">9:30 AM - 10:30 AM • Lab 1</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
