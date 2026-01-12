<?php
/**
 * Login Page
 * Handles user authentication with email/password and Google SSO
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/GoogleOAuth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard/');
}

// Initialize Google OAuth
$googleOAuth = new GoogleOAuth();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request. Please try again.');
        redirect('login.php');
    }
    
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $rememberMe = isset($_POST['remember_me']);
    
    // Attempt login
    $result = Auth::login($email, $password, $rememberMe);
    
    if ($result['success']) {
        // Check for redirect URL
        $redirectUrl = $_SESSION['redirect_after_login'] ?? APP_URL . '/dashboard/';
        unset($_SESSION['redirect_after_login']);
        
        redirect($redirectUrl);
    } else {
        setFlash('error', $result['message']);
        redirect('login.php');
    }
}
?>

<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Tailwind CSS + DaisyUI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.6.0/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-primary/10 via-secondary/10 to-accent/10 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        <!-- Logo and Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary text-primary-content mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-base-content"><?php echo APP_NAME; ?></h1>
            <p class="text-base-content/60 mt-2">Sign in to your account</p>
        </div>
        
        <!-- Login Card -->
        <div class="card bg-base-100 shadow-2xl">
            <div class="card-body">
                
                <?php
                // Display flash messages
                $flashMessages = getFlash();
                foreach ($flashMessages as $flash) {
                    $alertClass = [
                        'success' => 'alert-success',
                        'error' => 'alert-error',
                        'warning' => 'alert-warning',
                        'info' => 'alert-info'
                    ][$flash['type']] ?? 'alert-info';
                    
                    echo '<div class="alert ' . $alertClass . ' mb-4">
                            <span>' . htmlspecialchars($flash['message']) . '</span>
                          </div>';
                }
                ?>
                
                <!-- Login Form -->
                <form method="POST" action="login.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-medium">Email Address</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            placeholder="your.email@school.com" 
                            class="input input-bordered w-full" 
                            required 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        />
                    </div>
                    
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-medium">Password</span>
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            placeholder="Enter your password" 
                            class="input input-bordered w-full" 
                            required 
                        />
                        <label class="label">
                            <a href="#" class="label-text-alt link link-hover text-primary">Forgot password?</a>
                        </label>
                    </div>
                    
                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="remember_me" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">Remember me</span>
                        </label>
                    </div>
                    
                    <div class="form-control mt-6">
                        <button type="submit" name="login" class="btn btn-primary w-full">
                            Sign In
                        </button>
                    </div>
                </form>
                
                <?php if ($googleOAuth->isConfigured()): ?>
                <!-- Divider -->
                <div class="divider">OR</div>
                
                <!-- Google Sign In -->
                <a href="<?php echo $googleOAuth->getAuthUrl(); ?>" class="btn btn-outline w-full gap-2">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </a>
                <?php endif; ?>
                
                <!-- Register Link -->
                <div class="text-center mt-6">
                    <p class="text-sm text-base-content/60">
                        Don't have an account? 
                        <span class="text-base-content/40">Contact your school administrator.</span>
                    </p>
                </div>
                
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-6 text-sm text-base-content/60">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </div>
    
</body>
</html>
