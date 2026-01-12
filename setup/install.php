<?php
/**
 * Installation Wizard
 * Initial setup for database and admin account creation
 */

// Check if already installed
if (file_exists(__DIR__ . '/../config/installed.lock')) {
    die('Application already installed. Delete config/installed.lock to reinstall.');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = '';

// Step 2: Database Setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? 'school_portal';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    
    try {
        // Test database connection
        $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");
        
        // Execute schema
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $pdo->exec($schema);
        
        // Update config file
        $configPath = __DIR__ . '/../config/config.php';
        $configContent = file_get_contents($configPath);
        $configContent = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', '$dbHost');", $configContent);
        $configContent = str_replace("define('DB_NAME', 'school_portal');", "define('DB_NAME', '$dbName');", $configContent);
        $configContent = str_replace("define('DB_USER', 'root');", "define('DB_USER', '$dbUser');", $configContent);
        $configContent = str_replace("define('DB_PASS', '');", "define('DB_PASS', '" . addslashes($dbPass) . "');", $configContent);
        file_put_contents($configPath, $configContent);
        
        $success = 'Database created successfully!';
        $step = 3;
        
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Step 3: Admin Account Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    $adminName = $_POST['admin_name'] ?? '';
    $adminEmail = $_POST['admin_email'] ?? '';
    $adminPassword = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($adminName) || empty($adminEmail) || empty($adminPassword)) {
        $errors[] = 'All fields are required';
    } elseif (!isValidEmail($adminEmail)) {
        $errors[] = 'Invalid email address';
    } elseif ($adminPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    } elseif (strlen($adminPassword) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    } else {
        try {
            beginTransaction();
            
            // Create admin user
            $hashedPassword = hashPassword($adminPassword);
            $userId = insert(
                "INSERT INTO users (email, password, full_name, is_active, email_verified) VALUES (?, ?, ?, 1, 1)",
                [$adminEmail, $hashedPassword, $adminName]
            );
            
            // Assign principal role (highest access)
            $roleId = getRoleId('principal');
            query("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
            
            // Also assign admin role
            $adminRoleId = getRoleId('admin');
            query("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $adminRoleId]);
            
            commit();
            
            // Create installed.lock file
            file_put_contents(__DIR__ . '/../config/installed.lock', date('Y-m-d H:i:s'));
            
            $success = 'Installation complete! Redirecting to login...';
            header('refresh:2;url=../login.php');
            
        } catch (Exception $e) {
            rollback();
            $errors[] = 'Error creating admin account: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Wizard - School Management Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.6.0/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-primary/10 via-secondary/10 to-accent/10 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-2xl">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">🎓 School Portal</h1>
            <p class="text-base-content/60">Installation Wizard</p>
        </div>

        <div class="card bg-base-100 shadow-2xl">
            <div class="card-body">
                <!-- Progress Steps -->
                <ul class="steps w-full mb-8">
                    <li class="step step-primary">Welcome</li>
                    <li class="step <?php echo $step >= 2 ? 'step-primary' : ''; ?>">Database</li>
                    <li class="step <?php echo $step >= 3 ? 'step-primary' : ''; ?>">Admin Account</li>
                    <li class="step <?php echo $step >= 4 ? 'step-primary' : ''; ?>">Complete</li>
                </ul>

                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-error mb-4">
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success mb-4">
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Welcome -->
                <?php if ($step === 1): ?>
                    <h2 class="text-2xl font-bold mb-4">Welcome to School Portal</h2>
                    <div class="space-y-4 mb-6">
                        <p>This wizard will guide you through the installation process.</p>
                        
                        <div class="alert alert-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <div>
                                <h3 class="font-bold">Requirements:</h3>
                                <ul class="list-disc list-inside text-sm mt-2">
                                    <li>PHP 7.4 or higher</li>
                                    <li>MySQL 5.7 or higher</li>
                                    <li>Apache/Nginx web server</li>
                                    <li>Write permissions for config directory</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <a href="?step=2" class="btn btn-primary w-full">Continue →</a>
                <?php endif; ?>

                <!-- Step 2: Database Configuration -->
                <?php if ($step === 2): ?>
                    <h2 class="text-2xl font-bold mb-4">Database Configuration</h2>
                    <form method="POST" class="space-y-4">
                        <div class="form-control">
                            <label class="label"><span class="label-text">Database Host</span></label>
                            <input type="text" name="db_host" value="localhost" class="input input-bordered" required />
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Database Name</span></label>
                            <input type="text" name="db_name" value="school_portal" class="input input-bordered" required />
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Database Username</span></label>
                            <input type="text" name="db_user" value="root" class="input input-bordered" required />
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Database Password</span></label>
                            <input type="password" name="db_pass" class="input input-bordered" />
                        </div>
                        <button type="submit" class="btn btn-primary w-full">Create Database →</button>
                    </form>
                <?php endif; ?>

                <!-- Step 3: Admin Account -->
                <?php if ($step === 3 && !$success): ?>
                    <h2 class="text-2xl font-bold mb-4">Create Admin Account</h2>
                    <form method="POST" class="space-y-4">
                        <div class="form-control">
                            <label class="label"><span class="label-text">Full Name</span></label>
                            <input type="text" name="admin_name" class="input input-bordered" required />
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Email Address</span></label>
                            <input type="email" name="admin_email" class="input input-bordered" required />
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Password</span></label>
                            <input type="password" name="admin_password" class="input input-bordered" required />
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Confirm Password</span></label>
                            <input type="password" name="confirm_password" class="input input-bordered" required />
                        </div>
                        <button type="submit" class="btn btn-primary w-full">Complete Installation</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
