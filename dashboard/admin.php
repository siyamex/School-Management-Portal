<?php
/**
 * Admin Dashboard
 * Dashboard for school administrators
 */

$pageTitle = "Admin Dashboard - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl lg:text-4xl font-bold text-base-content mb-2">
            Admin Dashboard 🎓
        </h1>
        <p class="text-base-content/60"><?php echo date('l, F j, Y'); ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stats shadow-lg">
            <div class="stat">
                <div class="stat-title">Total Students</div>
                <div class="stat-value text-primary">1,234</div>
                <div class="stat-desc">↗︎ 8% from last year</div>
            </div>
        </div>
        
        <div class="stats shadow-lg">
            <div class="stat">
                <div class="stat-title">Teachers</div>
                <div class="stat-value text-secondary">85</div>
                <div class="stat-desc">Active this semester</div>
            </div>
        </div>
        
        <div class="stats shadow-lg">
            <div class="stat">
                <div class="stat-title">Classes</div>
                <div class="stat-value text-accent">42</div>
                <div class="stat-desc">With sections</div>
            </div>
        </div>
        
        <div class="stats shadow-lg">
            <div class="stat">
                <div class="stat-title">Avg. Attendance</div>
                <div class="stat-value text-success">93%</div>
                <div class="stat-desc">This month</div>
            </div>
        </div>
    </div>

    <div class="alert alert-info shadow-lg mb-8">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <div>
            <h3 class="font-bold">Admin Portal Ready!</h3>
            <div class="text-xs">Manage students, teachers, academic settings, and view comprehensive reports.</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Quick Actions</h2>
                <div class="grid grid-cols-2 gap-2">
                    <a href="<?php echo APP_URL; ?>/modules/students/create.php" class="btn btn-outline">Add Student</a>
                    <a href="<?php echo APP_URL; ?>/modules/users/" class="btn btn-outline">Manage Users</a>
                    <a href="<?php echo APP_URL; ?>/modules/academic/academic-years.php" class="btn btn-outline">Academic Years</a>
                    <a href="<?php echo APP_URL; ?>/modules/academic/classes.php" class="btn btn-outline">Classes</a>
                    <a href="<?php echo APP_URL; ?>/modules/academic/subjects.php" class="btn btn-outline">Subjects</a>
                    <a href="<?php echo APP_URL; ?>/modules/attendance/mark-attendance.php" class="btn btn-outline">Attendance</a>
                    <a href="<?php echo APP_URL; ?>/modules/reports/attendance-report.php" class="btn btn-outline">Reports</a>
                    <a href="<?php echo APP_URL; ?>/modules/exams/manage-exams.php" class="btn btn-outline">Exams</a>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Recent Activity</h2>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <div class="badge badge-success badge-sm"></div>
                        <span>New student enrolled</span>
                        <span class="text-base-content/60 ml-auto">2 hours ago</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <div class="badge badge-info badge-sm"></div>
                        <span>Exam schedule updated</span>
                        <span class="text-base-content/60 ml-auto">5 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
