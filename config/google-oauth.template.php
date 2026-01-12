<?php
/**
 * Google OAuth Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Go to Google Cloud Console: https://console.cloud.google.com/
 * 2. Create a new project or select existing project
 * 3. Enable Google+ API
 * 4. Go to "Credentials" and create OAuth 2.0 Client ID
 * 5. Add authorized redirect URI: http://localhost/sp/oauth-callback.php
 * 6. Copy your Client ID and Client Secret below
 * 7. Rename this file to google-oauth.php
 */

// Your Google Client ID
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');

// Your Google Client Secret
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');

// Redirect URI (must match exactly with Google Cloud Console)
define('GOOGLE_REDIRECT_URI', 'http://localhost/sp/oauth-callback.php');

// Scopes needed
define('GOOGLE_SCOPES', [
    'https://www.googleapis.com/auth/userinfo.email',
    'https://www.googleapis.com/auth/userinfo.profile'
]);
