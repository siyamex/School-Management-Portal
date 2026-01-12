<?php
/**
 * Google OAuth Callback Handler
 * Processes the OAuth callback from Google
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/GoogleOAuth.php';

$googleOAuth = new GoogleOAuth();

// Check if we have authorization code
if (!isset($_GET['code'])) {
    setFlash('error', 'Authorization failed. Please try again.');
    redirect('login.php');
}

try {
    // Exchange code for access token
    $tokenData = $googleOAuth->getAccessToken($_GET['code']);
    
    if (!$tokenData || !isset($tokenData['access_token'])) {
        throw new Exception('Failed to get access token');
    }
    
    // Get user info from Google
    $googleUser = $googleOAuth->getUserInfo($tokenData['access_token']);
    
    if (!$googleUser) {
        throw new Exception('Failed to get user information');
    }
    
    // Add tokens to user data
    $googleUser['access_token'] = $tokenData['access_token'];
    $googleUser['refresh_token'] = $tokenData['refresh_token'] ?? null;
    
    // Login with Google
    $result = Auth::loginWithGoogle($googleUser);
    
    if ($result['success']) {
        // Check for redirect URL
        $redirectUrl = $_SESSION['redirect_after_login'] ?? APP_URL . '/dashboard/';
        unset($_SESSION['redirect_after_login']);
        
        redirect($redirectUrl);
    } else {
        setFlash('error', $result['message']);
        redirect('login.php');
    }
    
} catch (Exception $e) {
    error_log('OAuth callback error: ' . $e->getMessage());
    setFlash('error', 'Google login failed. Please try again.');
    redirect('login.php');
}
