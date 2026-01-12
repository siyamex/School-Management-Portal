<?php
/**
 * Logout Handler
 * Handles user logout
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Auth.php';

// Logout user
Auth::logout();

// Set success message
setFlash('success', 'You have been logged out successfully.');

// Redirect to login
redirect('login.php');
