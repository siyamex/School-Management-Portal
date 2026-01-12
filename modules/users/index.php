<?php
/**
 * User Management - List All Users
 * Admin can view, search, and manage all users
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "User Management - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

// Get filter parameters
$roleFilter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build SQL query
$whereClauses = [];
$params = [];

if ($roleFilter) {
    $whereClauses[] = "r.role_name = ?";
    $params[] = $roleFilter;
}

if ($searchQuery) {
    $whereClauses[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get users with their roles
$sql = "SELECT u.*, GROUP_CONCAT(r.role_name) as roles
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        $whereSQL
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT $perPage OFFSET $offset";

$users = getAll($sql, $params);

// Get total count for pagination
$countSQL = "SELECT COUNT(DISTINCT u.id) as total
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             $whereSQL";
$totalUsers = getValue($countSQL, $params);
$totalPages = ceil($totalUsers / $perPage);

// Get role list for filter
$allRoles = getAll("SELECT * FROM roles ORDER BY role_name");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">User Management</h1>
            <p class="text-base-content/60 mt-1">Manage all system users</p>
        </div>
        <a href="create.php" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Add New User
        </a>
    </div>

    <!-- Search and Filter -->
    <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Search</span></label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Name or email..." class="input input-bordered" />
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Role</span></label>
                    <select name="role" class="select select-bordered">
                        <option value="">All Roles</option>
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?php echo $role['role_name']; ?>" <?php echo $roleFilter === $role['role_name'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">&nbsp;</span></label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary flex-1">Filter</button>
                        <a href="index.php" class="btn btn-ghost">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Users</div>
            <div class="stat-value text-primary"><?php echo $totalUsers; ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Active Users</div>
            <div class="stat-value text-success">
                <?php echo getValue("SELECT COUNT(*) FROM users WHERE is_active = 1"); ?>
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">Inactive</div>
            <div class="stat-value text-error">
                <?php echo getValue("SELECT COUNT(*) FROM users WHERE is_active = 0"); ?>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <?php if (empty($users)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No users found matching your criteria.</span>
        </div>
    <?php else: ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body p-0">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="w-12 h-12 rounded-full <?php echo $user['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                                                    <?php if ($user['photo']): ?>
                                                        <img src="<?php echo APP_URL . '/' . $user['photo']; ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>" />
                                                    <?php else: ?>
                                                        <span class="text-xl"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <?php if ($user['google_id']): ?>
                                                    <span class="badge badge-sm badge-info">Google SSO</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <?php if ($user['roles']): ?>
                                                <?php foreach (explode(',', $user['roles']) as $role): ?>
                                                    <span class="badge badge-sm badge-outline"><?php echo ucfirst(str_replace('_', ' ', $role)); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-base-content/60">No role</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-error">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm text-base-content/60"><?php echo formatDate($user['created_at'], 'M d, Y'); ?></td>
                                    <td>
                                        <div class="flex gap-1">
                                            <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-xs btn-ghost">Edit</a>
                                            <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-xs btn-ghost">View</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-6">
                <?php 
                $baseUrl = 'index.php?role=' . urlencode($roleFilter) . '&search=' . urlencode($searchQuery);
                echo getPagination($page, $totalPages, $baseUrl);
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
