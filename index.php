<?php
/**
 * Application Entry Point
 * Redirects to login or dashboard based on authentication status
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard/');
} else {
    redirect(APP_URL . '/login.php');
}
