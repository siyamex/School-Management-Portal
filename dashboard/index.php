<?php
/**
 * Dashboard Router
 * Routes users to their role-specific dashboard
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireLogin();

// Get current user
$user = getCurrentUser();

// Route to appropriate dashboard based on role
if (hasRole('principal')) {
    require_once __DIR__ . '/principal.php';
} elseif (hasRole('admin')) {
    require_once __DIR__ . '/admin.php';
} elseif (hasRole('leading_teacher') || hasRole('teacher')) {
    require_once __DIR__ . '/teacher.php';
} elseif (hasRole('parent')) {
    require_once __DIR__ . '/parent.php';
} elseif (hasRole('student')) {
    require_once __DIR__ . '/student.php';
} else {
    // No role assigned - show error
    http_response_code(403);
    die('No role assigned to your account. Please contact the administrator.');
}
