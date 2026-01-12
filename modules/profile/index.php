<?php
/**
 * User Profile View and Edit
 * All users can view and edit their own profile
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "My Profile - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireLogin();

$userId = getCurrentUserId();
$user = getCurrentUser();
$success = '';
$errors = [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $fullName = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $photoPath = $user['photo'];
        
        if (empty($fullName)) {
            $errors[] = 'Full name is required';
        } else {
            // Handle photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['photo'], UPLOAD_PATH . '/profiles', ['jpg', 'jpeg', 'png']);
                if ($uploadResult['success']) {
                    // Delete old photo
                    if ($user['photo'] && file_exists(BASE_PATH . '/' . $user['photo'])) {
                        unlink(BASE_PATH . '/' . $user['photo']);
                    }
                    $photoPath = str_replace(BASE_PATH . '/', '', $uploadResult['path']);
                } else {
                    $errors[] = 'Photo upload failed: ' . $uploadResult['error'];
                }
            }
            
            if (empty($errors)) {
                try {
                    query("UPDATE users SET full_name = ?, phone = ?, photo = ? WHERE id = ?", 
                          [$fullName, $phone, $photoPath, $userId]);
                    
                    // Update session
                    $_SESSION['user_name'] = $fullName;
                    
                    $success = 'Profile updated successfully';
                    $user = getCurrentUser(); // Refresh user data
                } catch (Exception $e) {
                    $errors[] = 'Error updating profile: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = 'All password fields are required';
        } elseif (!verifyPassword($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        } else {
            try {
                $hashedPassword = hashPassword($newPassword);
                query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
                $success = 'Password changed successfully';
            } catch (Exception $e) {
                $errors[] = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
}

// Get user roles
$roles = getAll("
    SELECT r.role_name
    FROM user_roles ur
    JOIN roles r ON ur.role_id = r.id
    WHERE ur.user_id = ?
", [$userId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">My Profile</h1>
        <p class="text-base-content/60 mt-1">Manage your account settings</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body items-center text-center">
                <div class="avatar mb-4">
                    <div class="w-32 h-32 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2 <?php echo $user['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                        <?php if ($user['photo']): ?>
                            <img src="<?php echo APP_URL . '/' . $user['photo']; ?>" alt="" />
                        <?php else: ?>
                            <span class="text-5xl"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <h2 class="card-title text-2xl"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <p class="text-base-content/60"><?php echo htmlspecialchars($user['email']); ?></p>
                
                <div class="flex flex-wrap gap-2 justify-center mt-4">
                    <?php 
                    // Handle roles from user data (comma-separated string)
                    $userRoles = !empty($user['roles']) ? explode(',', $user['roles']) : [];
                    if (!empty($userRoles)): 
                    ?>
                        <?php foreach ($userRoles as $roleName): ?>
                            <span class="badge badge-lg badge-primary"><?php echo ucfirst(str_replace('_', ' ', trim($roleName))); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="divider"></div>
                
                <div class="stats stats-vertical shadow w-full">
                    <div class="stat">
                        <div class="stat-title">Member Since</div>
                        <div class="stat-value text-lg"><?php echo formatDate($user['created_at'], 'M Y'); ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">Account Status</div>
                        <div class="stat-value text-lg">
                            <?php echo $user['is_active'] ? '✅ Active' : '🚫 Inactive'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="lg:col-span-2 space-y-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Profile Information</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Full Name</span></label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                   class="input input-bordered" required />
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Email</span></label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   class="input input-bordered" disabled />
                            <label class="label">
                                <span class="label-text-alt">Email cannot be changed</span>
                            </label>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Phone</span></label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   class="input input-bordered" />
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Profile Photo</span></label>
                            <input type="file" name="photo" accept="image/*" class="file-input file-input-bordered" />
                            <label class="label">
                                <span class="label-text-alt">JPG, PNG - Max 2MB</span>
                            </label>
                        </div>
                        
                        <div class="card-actions justify-end">
                            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Change Password</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Current Password</span></label>
                            <input type="password" name="current_password" class="input input-bordered" />
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">New Password</span></label>
                            <input type="password" name="new_password" class="input input-bordered" />
                            <label class="label">
                                <span class="label-text-alt">Minimum 6 characters</span>
                            </label>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Confirm New Password</span></label>
                            <input type="password" name="confirm_password" class="input input-bordered" />
                        </div>
                        
                        <div class="card-actions justify-end">
                            <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
