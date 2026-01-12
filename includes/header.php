<?php
/**
 * Common Header
 * Includes navigation, user menu, and notifications
 */

if (!defined('APP_NAME')) {
    die('Direct access not allowed');
}

$currentUser = getCurrentUser();
$notificationCount = getUnreadNotificationCount(getCurrentUserId());
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Complete school management system with attendance, grades, timetable, and more">
    <meta name="theme-color" content="#3b82f6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="School Portal">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    
    <!-- App Icons -->
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo APP_URL; ?>/assets/images/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="<?php echo APP_URL; ?>/assets/images/icon-512x512.png">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/images/icon-192x192.png">
    
    <title><?php echo $pageTitle ?? APP_NAME; ?></title>
    
    <!-- Tailwind CSS + DaisyUI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.6.0/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-base-200">

    <!-- Navigation Bar -->
    <div class="navbar bg-base-100 shadow-lg sticky top-0 z-50">
        <div class="flex-1">
            <!-- Mobile Menu Button -->
            <button id="mobile-menu-toggle" class="btn btn-ghost lg:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            
            <!-- Logo and Title -->
            <a href="<?php echo APP_URL; ?>/dashboard/" class="btn btn-ghost text-xl gap-2">
                <div class="w-10 h-10 rounded-lg bg-primary flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary-content" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <span class="hidden sm:inline font-bold">School Portal</span>
            </a>
        </div>
        
        <div class="flex-none gap-2">
            <!-- Theme Toggle -->
            <button id="theme-toggle" class="btn btn-ghost btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>
            
            <!-- Notifications -->
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-ghost btn-circle">
                    <div class="indicator">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <?php if ($notificationCount > 0): ?>
                            <span id="notification-count" class="badge badge-sm badge-error indicator-item"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </div>
                </button>
                <div tabindex="0" class="mt-3 card card-compact dropdown-content w-80 bg-base-100 shadow-xl z-50">
                    <div class="card-body">
                        <h3 class="card-title text-sm">Notifications</h3>
                        <div class="divider my-0"></div>
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            <!-- Notifications will be loaded here via AJAX -->
                            <p class="text-sm text-base-content/60 text-center py-4">No new notifications</p>
                        </div>
                        <a href="<?php echo APP_URL; ?>/modules/notifications/all.php" class="btn btn-sm btn-ghost mt-2">View All</a>
                    </div>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-ghost btn-circle avatar">
                    <div class="w-10 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                        <?php if ($currentUser['photo']): ?>
                            <img src="<?php echo APP_URL . '/' . $currentUser['photo']; ?>" alt="<?php echo htmlspecialchars($currentUser['full_name']); ?>" />
                        <?php else: ?>
                            <div class="bg-primary text-primary-content w-full h-full flex items-center justify-center text-lg font-bold">
                                <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </button>
                <div tabindex="0" class="mt-3 card card-compact dropdown-content w-64 bg-base-100 shadow-xl z-50">
                    <div class="card-body">
                        <div class="flex items-center gap-3 pb-2">
                            <div class="avatar">
                                <div class="w-12 rounded-full">
                                    <?php if ($currentUser['photo']): ?>
                                        <img src="<?php echo APP_URL . '/' . $currentUser['photo']; ?>" />
                                    <?php else: ?>
                                        <div class="bg-primary text-primary-content w-full h-full flex items-center justify-center text-xl font-bold">
                                            <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($currentUser['full_name']); ?></p>
                                <p class="text-xs text-base-content/60"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                                <div class="badge badge-sm badge-primary mt-1"><?php echo ucfirst(explode(',', $currentUser['roles'])[0]); ?></div>
                            </div>
                        </div>
                        <div class="divider my-0"></div>
                        <ul class="menu menu-sm p-0">
                            <li><a href="<?php echo APP_URL; ?>/modules/profile/index.php"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg> My Profile</a></li>
                        </ul>
                        <div class="divider my-0"></div>
                        <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-sm btn-error btn-outline gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php
    $flashMessages = getFlash();
    if (!empty($flashMessages)):
    ?>
    <div class="container mx-auto px-4 pt-4">
        <?php foreach ($flashMessages as $flash): 
            $alertClass = [
                'success' => 'alert-success',
                'error' => 'alert-error',
                'warning' => 'alert-warning',
                'info' => 'alert-info'
            ][$flash['type']] ?? 'alert-info';
        ?>
            <div class="alert <?php echo $alertClass; ?> mb-2">
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <div class="flex">
