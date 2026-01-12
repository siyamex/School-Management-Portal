<?php
/**
 * Application Configuration File
 * School Management Portal
 */

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Colombo'); // Adjust as needed

// Base Paths
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH);
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'School Management Portal');
define('APP_URL', 'http://localhost/sp');
define('APP_ENV', 'development'); // development or production

// Session Settings
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('SESSION_NAME', 'school_portal_session');

// Security
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);

// Upload Settings
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Google OAuth Configuration (import from separate file for security)
if (file_exists(__DIR__ . '/google-oauth.php')) {
    require_once __DIR__ . '/google-oauth.php';
} else {
    // Defaults if not configured
    define('GOOGLE_CLIENT_ID', '');
    define('GOOGLE_CLIENT_SECRET', '');
    define('GOOGLE_REDIRECT_URI', APP_URL . '/oauth-callback.php');
    define('GOOGLE_SCOPES', [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ]);
}

// Email Configuration (for future use)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '');
define('SMTP_FROM_NAME', APP_NAME);

// Load database connection
require_once __DIR__ . '/database.php';
