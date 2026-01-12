<?php
/**
 * Manage Salaries - Admin/HR Module
 * Admin/HR can add and manage staff salary slips
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Manage Salaries - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Handle add/update salary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $userId = (int)$_POST['user_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $basicSalary = (float)$_POST['basic_salary'];
    $allowances = (float)($_POST['allowances'] ?? 0);
    $otAmount = (float)($_POST['ot_amount'] ?? 0);
    $deductions = (float)($_POST['deductions'] ?? 0);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $netSalary = $basicSalary + $allowances + $otAmount - $deductions;
    
    if (!$userId || !$month || !$year || $basicSalary <= 0) {
        $errors[] = 'User, month, year, and basic salary are required';
    } else {
        try {
            // Check if salary slip already exists
            $existing = getValue("SELECT id FROM salary_slips WHERE user_id = ? AND month = ? AND year = ?", 
                               [$userId, $month, $year]);
            
            if ($existing && !isset($_POST['salary_id'])) {
                $errors[] = 'Salary slip for this month already exists';
            } else if (isset($_POST['salary_id'])) {
                // Update existing
                query("UPDATE salary_slips SET basic_salary = ?, allowances = ?, ot_amount = ?, 
                       deductions = ?, net_salary = ?, remarks = ? WHERE id = ?",
                     [$basicSalary, $allowances, $otAmount, $deductions, $netSalary, $remarks, $_POST['salary_id']]);
                setFlash('success', 'Salary slip updated successfully');
            } else {
                // Insert new
                insert("INSERT INTO salary_slips (user_id, month, year, basic_salary, allowances, 
                        ot_amount, deductions, net_salary, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                      [$userId, $month, $year, $basicSalary, $allowances, $otAmount, $deductions, $netSalary, $remarks]);
                setFlash('success', 'Salary slip added successfully');
            }
            redirect('manage-salaries.php');
        } catch (Exception $e) {
            $errors[] = 'Error saving salary: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && verifyCSRFToken($_GET['csrf_token'] ?? '')) {
    $id = (int)$_GET['delete'];
    query("DELETE FROM salary_slips WHERE id = ?", [$id]);
    setFlash('success', 'Salary slip deleted');
    redirect('manage-salaries.php');
}

// Get all staff
$staff = getAll("
    SELECT u.id, u.full_name, u.email,
           GROUP_CONCAT(DISTINCT r.role_name) as roles,
           (SELECT COUNT(*) FROM salary_slips WHERE user_id = u.id) as salary_count
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE r.role_name IN ('teacher', 'admin', 'principal')
    GROUP BY u.id, u.full_name, u.email
    ORDER BY u.full_name
");

// Get recent salary slips
$recentSalaries = getAll("
    SELECT s.*, u.full_name,
           GROUP_CONCAT(DISTINCT r.role_name) as roles
    FROM salary_slips s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    GROUP BY s.id, u.full_name
    ORDER BY s.year DESC, s.month DESC
    LIMIT 50
");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">💰 Manage Staff Salaries</h1>
        <p class="text-base-content/60 mt-1">Add and manage monthly salary slips for staff</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add Salary Form -->
        <div class="lg:col-span-1">
            <div class="card bg-base-100 shadow-xl sticky top-6">
                <div class="card-body">
                    <h2 class="card-title">Add Salary Slip</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Staff Member *</span></label>
                            <select name="user_id" class="select select-bordered select-sm" required>
                                <option value="">Select staff...</option>
                                <?php foreach ($staff as $member): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['full_name']); ?> 
                                        (<?php echo ucfirst(explode(',', $member['roles'])[0]); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="form-control">
                                <label class="label"><span class="label-text">Month *</span></label>
                                <select name="month" class="select select-bordered select-sm" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text">Year *</span></label>
                                <input type="number" name="year" value="<?php echo date('Y'); ?>" 
                                       class="input input-bordered input-sm" required>
                            </div>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Basic Salary *</span></label>
                            <input type="number" name="basic_salary" step="0.01" 
                                   class="input input-bordered input-sm" required>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Allowances</span></label>
                            <input type="number" name="allowances" step="0.01" value="0" 
                                   class="input input-bordered input-sm">
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">OT Amount</span></label>
                            <input type="number" name="ot_amount" step="0.01" value="0" 
                                   class="input input-bordered input-sm">
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Deductions</span></label>
                            <input type="number" name="deductions" step="0.01" value="0" 
                                   class="input input-bordered input-sm">
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Remarks</span></label>
                            <textarea name="remarks" class="textarea textarea-bordered textarea-sm" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block btn-sm">Add Salary Slip</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Salary Records -->
        <div class="lg:col-span-2">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Recent Salary Slips</h2>
                    
                    <?php if (empty($recentSalaries)): ?>
                        <p class="text-center py-8 text-base-content/60">No salary records yet</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff</th>
                                        <th>Period</th>
                                        <th>Basic</th>
                                        <th>Allowances</th>
                                        <th>OT</th>
                                        <th>Deductions</th>
                                        <th>Net Salary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSalaries as $salary): ?>
                                        <tr>
                                            <td>
                                                <div class="font-semibold"><?php echo htmlspecialchars($salary['full_name']); ?></div>
                                                <div class="text-xs text-base-content/60"><?php echo ucfirst(explode(',', $salary['roles'])[0]); ?></div>
                                            </td>
                                            <td><?php echo date('M Y', strtotime($salary['year'] . '-' . $salary['month'] . '-01')); ?></td>
                                            <td><?php echo number_format($salary['basic_salary'], 2); ?></td>
                                            <td class="text-success"><?php echo number_format($salary['allowances'], 2); ?></td>
                                            <td class="text-success"><?php echo number_format($salary['ot_amount'], 2); ?></td>
                                            <td class="text-error"><?php echo number_format($salary['deductions'], 2); ?></td>
                                            <td class="font-bold text-primary"><?php echo number_format($salary['net_salary'], 2); ?></td>
                                            <td>
                                                <div class="flex gap-1">
                                                    <a href="?delete=<?php echo $salary['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" 
                                                       onclick="return confirm('Delete this salary slip?')" 
                                                       class="btn btn-xs btn-ghost btn-error">Delete</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
