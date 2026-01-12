<?php
/**
 * Edit User (Admin Only)
 * Admin can edit any user's information
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Edit User - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Get user ID
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get user details
$user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    die("User not found.");
}

// Get user roles
$userRoles = getAll("
    SELECT r.id, r.role_name
    FROM user_roles ur
    JOIN roles r ON ur.role_id = r.id
    WHERE ur.user_id = ?
", [$userId]);

$userRoleIds = array_column($userRoles, 'id');

// Get all available roles
$allRoles = getAll("SELECT * FROM roles ORDER BY role_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $status = sanitize($_POST['status'] ?? '1');
        $roles = isset($_POST['roles']) ? $_POST['roles'] : [];
        
        if (empty($fullName) || empty($email)) {
            $errors[] = 'Name and email are required';
        } else {
            // Check email uniqueness
            $emailExists = getRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($emailExists) {
                $errors[] = 'Email is already in use by another user';
            } else {
                try {
                    beginTransaction();
                    
                    // Update user
                    query("UPDATE users SET full_name = ?, email = ?, phone = ?, is_active = ? WHERE id = ?",
                         [$fullName, $email, $phone, $status, $userId]);
                    
                    // Update roles
                    query("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
                    foreach ($roles as $roleId) {
                        insert("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, (int)$roleId]);
                    }
                    
                    // Handle photo upload
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        $photo = uploadFile($_FILES['photo'], 'uploads/photos/');
                        if ($photo) {
                            query("UPDATE users SET photo = ? WHERE id = ?", [$photo, $userId]);
                        }
                    }
                    
                    commit();
                    $success = 'User updated successfully';
                    
                    // Refresh user data
                    $user = getRow("SELECT * FROM users WHERE id = ?", [$userId]);
                    $userRoles = getAll("
                        SELECT r.id, r.role_name
                        FROM user_roles ur
                        JOIN roles r ON ur.role_id = r.id
                        WHERE ur.user_id = ?
                    ", [$userId]);
                    $userRoleIds = array_column($userRoles, 'id');
                } catch (Exception $e) {
                    rollback();
                    $errors[] = 'Update failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Handle password reset
if (isset($_POST['reset_password']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $newPassword = bin2hex(random_bytes(4)); // 8 character password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
    
    $success = 'Password reset successfully. New password: ' . $newPassword;
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="index.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Users
        </a>
        <h1 class="text-3xl font-bold">Edit User</h1>
        <p class="text-base-content/60 mt-1">Modify user information and settings</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">User Information</h2>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Full Name *</span></label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="input input-bordered" required />
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Email *</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="input input-bordered" required />
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Phone</span></label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="input input-bordered" />
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Profile Photo</span></label>
                            <?php if ($user['photo']): ?>
                                <div class="avatar mb-2">
                                    <div class="w-24 rounded">
                                        <img src="<?php echo APP_URL . '/' . $user['photo']; ?>" alt="Current photo" />
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="photo" accept="image/*" class="file-input file-input-bordered" />
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Status</span></label>
                            <select name="status" class="select select-bordered">
                                <option value="1" <?php echo $user['is_active'] == 1 ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo $user['is_active'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <label class="label">
                                <span class="label-text-alt">Inactive users cannot log in</span>
                            </label>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Roles</span></label>
                            <div class="space-y-2">
                                <?php foreach ($allRoles as $role): ?>
                                    <label class="label cursor-pointer justify-start gap-3">
                                        <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>" 
                                               class="checkbox checkbox-primary"
                                               <?php echo in_array($role['id'], $userRoleIds) ? 'checked' : ''; ?> />
                                        <span class="label-text"><?php echo ucfirst($role['role_name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="card-actions justify-end mt-6">
                            <a href="index.php" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="space-y-6">
            <!-- User Info -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold">User Details</h3>
                    <div class="space-y-2 text-sm">
                        <p><strong>ID:</strong> <?php echo $user['id']; ?></p>
                        <p><strong>Created:</strong> <?php echo formatDateTime($user['created_at']); ?></p>
                        <p><strong>Current Roles:</strong></p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($userRoles as $role): ?>
                                <span class="badge badge-sm"><?php echo ucfirst($role['role_name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Reset -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold text-warning">Reset Password</h3>
                    <p class="text-sm text-base-content/60 mb-4">Generate a new random password for this user</p>
                    <form method="POST" onsubmit="return confirm('Reset password? The new password will be displayed once.');">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" name="reset_password" class="btn btn-warning btn-block btn-sm">
                            Reset Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold">Quick Links</h3>
                    <div class="space-y-2">
                        <?php if (in_array('student', array_column($userRoles, 'role_name'))): ?>
                            <?php $studentId = getValue("SELECT id FROM students WHERE user_id = ?", [$userId]); ?>
                            <?php if ($studentId): ?>
                                <a href="../students/view.php?id=<?php echo $studentId; ?>" class="btn btn-sm btn-outline btn-block">View Student Profile</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (in_array('teacher', array_column($userRoles, 'role_name'))): ?>
                            <?php $teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$userId]); ?>
                            <?php if ($teacherId): ?>
                                <a href="../teachers/view.php?id=<?php echo $teacherId; ?>" class="btn btn-sm btn-outline btn-block">View Teacher Profile</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
