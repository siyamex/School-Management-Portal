<?php
/**
 * Monthly Salary View
 * Staff can view their salary slips
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "My Salary - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'admin', 'principal']);

$currentUserId = getCurrentUserId();
$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);
$isAdmin = in_array('admin', $userRoles) || in_array('principal', $userRoles);

// Get user's salary records
if ($isAdmin && isset($_GET['user_id'])) {
    $viewUserId = (int)$_GET['user_id'];
} else {
    $viewUserId = $currentUserId;
}

$salaryRecords = getAll("
    SELECT s.*, u.full_name
    FROM salary_slips s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
    ORDER BY s.month DESC, s.year DESC
", [$viewUserId]);

$userInfo = getRow("SELECT full_name, email FROM users WHERE id = ?", [$viewUserId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Salary Slips</h1>
        <p class="text-base-content/60 mt-1"><?php echo htmlspecialchars($userInfo['full_name']); ?></p>
    </div>

    <?php if (empty($salaryRecords)): ?>
        <div class="alert alert-info">
            <span>No salary records found. Please contact HR department.</span>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($salaryRecords as $salary): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title"><?php echo date('F Y', strtotime($salary['year'] . '-' . $salary['month'] . '-01')); ?></h3>
                        
                        <div class="divider"></div>
                        
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-base-content/60">Basic Salary</span>
                                <span class="font-semibold"><?php echo number_format($salary['basic_salary'], 2); ?></span>
                            </div>
                            
                            <?php if ($salary['allowances'] > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-base-content/60">Allowances</span>
                                    <span class="text-success">+ <?php echo number_format($salary['allowances'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($salary['ot_amount'] > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-base-content/60">OT Payment</span>
                                    <span class="text-success">+ <?php echo number_format($salary['ot_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($salary['deductions'] > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-base-content/60">Deductions</span>
                                    <span class="text-error">-<?php echo number_format($salary['deductions'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="divider my-2"></div>
                            
                            <div class="flex justify-between font-bold text-lg">
                                <span>Net Salary</span>
                                <span class="text-primary"><?php echo number_format($salary['net_salary'], 2); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($salary['remarks']): ?>
                            <div class="alert alert-info text-xs mt-4">
                                <?php echo htmlspecialchars($salary['remarks']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-actions justify-end mt-4">
                            <button onclick="printSalary(<?php echo $salary['id']; ?>)" class="btn btn-sm btn-ghost">Print</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
function printSalary(id) {
    window.print();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
