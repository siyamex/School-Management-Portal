<?php
/**
 * Email Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file and rename it to 'email.php'
 * 2. Fill in your SMTP server details below
 * 3. For Gmail: Use an "App Password" (https://myaccount.google.com/apppasswords)
 * 4. Test the configuration from the admin panel
 */

// Enable/Disable email notifications
define('EMAIL_ENABLED', false); // Set to true after configuration

// SMTP Server Settings
define('SMTP_HOST', 'smtp.gmail.com'); // Gmail: smtp.gmail.com, Outlook: smtp.office365.com
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
define('SMTP_AUTH', true); // Keep as true

// SMTP Authentication
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'your-app-password-here'); // Your app password (NOT your regular password)

// Sender Information
define('EMAIL_FROM_ADDRESS', 'noreply@yourschool.com'); // From email address
define('EMAIL_FROM_NAME', APP_NAME); // From name (uses app name)
define('EMAIL_REPLY_TO', 'support@yourschool.com'); // Reply-to address

// Email Settings
define('EMAIL_DEBUG', 0); // 0=off, 1=client, 2=server, 3=connection, 4=low-level
define('EMAIL_CHARSET', 'UTF-8');

// Notification Settings
define('NOTIFY_ASSIGNMENT_POSTED', true); // Notify students when assignment is posted
define('NOTIFY_GRADE_PUBLISHED', true); // Notify students when grade is published
define('NOTIFY_BADGE_AWARDED', true); // Notify students when badge is awarded
define('NOTIFY_ASSIGNMENT_SUBMITTED', true); // Notify teacher when assignment is submitted
?>
