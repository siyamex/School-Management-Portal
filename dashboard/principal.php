<?php
/**
 * Principal Dashboard
 * Dashboard for school principal with overview and approvals
 */

$pageTitle = "Principal Dashboard - " . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
requireRole('principal');
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl lg:text-4xl font-bold text-base-content mb-2">
            Principal Dashboard 🏫
        </h1>
        <p class="text-base-content/60"><?php echo date('l, F j, Y'); ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stats shadow-lg bg-gradient-to-br from-primary to-primary/80 text-primary-content">
            <div class="stat">
                <div class="stat-title text-primary-content/80">Pending Approvals</div>
                <div class="stat-value">8</div>
                <div class="stat-desc text-primary-content/60">Requires attention</div>
            </div>
        </div>
        
        <div class="stats shadow-lg">
            <div class="stat">
                <div class="stat-title">School Attendance</div>
                <div class="stat-value text-success">94.5%</div>
                <div class="stat-desc">↗︎ 2% from last month</div>
            </div>
        </div>
        
        <div class="stats shadow-lg">
            <div class="stat">
                <div class="stat-title">Academic Performance</div>
                <div class="stat-value text-accent">3.42</div>
                <div class="stat-desc">Average GPA</div>
            </div>
        </div>
        
        <div class="stats shadow-lg">
            <div class="stat">
                <div class="stat-title">Teacher Satisfaction</div>
                <div class="stat-value text-secondary">87%</div>
                <div class="stat-desc">Based on survey</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">Pending Approvals</h2>
                <div class="space-y-2">
                    <div class="alert alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <div class="text-sm">
                            <div class="font-semibold">5 leave requests</div>
                            <div class="text-xs">Awaiting your approval</div>
                        </div>
                        <button class="btn btn-sm">Review</button>
                    </div>
                    <div class="alert alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div class="text-sm">
                            <div class="font-semibold">3 overtime requests</div>
                            <div class="text-xs">From teaching staff</div>
                        </div>
                        <button class="btn btn-sm">Review</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">School Overview</h2>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Student Enrollment</span>
                            <span class="font-semibold">1,234 / 1,500</span>
                        </div>
                        <progress class="progress progress-primary" value="82" max="100"></progress>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Teacher Capacity</span>
                            <span class="font-semibold">85 / 100</span>
                        </div>
                        <progress class="progress progress-secondary" value="85" max="100"></progress>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Classrooms Utilized</span>
                            <span class="font-semibold">42 / 50</span>
                        </div>
                        <progress class="progress progress-accent" value="84" max="100"></progress>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
