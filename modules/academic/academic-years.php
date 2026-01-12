<?php
/**
 * Academic Years Management
 * Admin can create and manage academic years
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Academic Years - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request');
        redirect('academic-years.php');
    }
    
    if (isset($_POST['create_year'])) {
        $yearName = sanitize($_POST['year_name']);
        $startDate = sanitize($_POST['start_date']);
        $endDate = sanitize($_POST['end_date']);
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        
        if (empty($yearName) || empty($startDate) || empty($endDate)) {
            setFlash('error', 'All fields are required');
        } else {
            try {
                // If setting as current, unset all others first
                if ($isCurrent) {
                    query("UPDATE academic_years SET is_current = 0");
                }
                
                $sql = "INSERT INTO academic_years (year_name, start_date, end_date, is_current) VALUES (?, ?, ?, ?)";
                insert($sql, [$yearName, $startDate, $endDate, $isCurrent]);
                
                setFlash('success', 'Academic year created successfully');
                redirect('academic-years.php');
            } catch (Exception $e) {
                setFlash('error', 'Error creating academic year: ' . $e->getMessage());
            }
        }
    }
    
    if (isset($_POST['set_current'])) {
        $yearId = (int)$_POST['year_id'];
        query("UPDATE academic_years SET is_current = 0");
        query("UPDATE academic_years SET is_current = 1 WHERE id = ?", [$yearId]);
        setFlash('success', 'Current academic year updated');
        redirect('academic-years.php');
    }
    
    if (isset($_POST['delete_year'])) {
        $yearId = (int)$_POST['year_id'];
        try {
            query("DELETE FROM academic_years WHERE id = ?", [$yearId]);
            setFlash('success', 'Academic year deleted');
            redirect('academic-years.php');
        } catch (Exception $e) {
            setFlash('error', 'Cannot delete: ' . $e->getMessage());
        }
    }
}

// Get all academic years
$academicYears = getAll("SELECT * FROM academic_years ORDER BY start_date DESC");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Academic Years</h1>
            <p class="text-base-content/60 mt-1">Manage school academic years</p>
        </div>
        <button onclick="create_modal.showModal()" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Add Academic Year
        </button>
    </div>

    <?php if (empty($academicYears)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No academic years found. Create your first academic year to get started.</span>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($academicYears as $year): ?>
                <div class="card bg-base-100 shadow-xl <?php echo $year['is_current'] ? 'ring-2 ring-primary' : ''; ?>">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <h2 class="card-title"><?php echo htmlspecialchars($year['year_name']); ?></h2>
                            <?php if ($year['is_current']): ?>
                                <span class="badge badge-primary">Current</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="space-y-2 text-sm">
                            <p><strong>Start:</strong> <?php echo formatDate($year['start_date'], 'F d, Y'); ?></p>
                            <p><strong>End:</strong> <?php echo formatDate($year['end_date'], 'F d, Y'); ?></p>
                            <p><strong>Duration:</strong> 
                                <?php 
                                $start = new DateTime($year['start_date']);
                                $end = new DateTime($year['end_date']);
                                $diff = $start->diff($end);
                                echo $diff->days . ' days';
                                ?>
                            </p>
                        </div>
                        
                        <div class="card-actions justify-end mt-4">
                            <a href="semesters.php?year_id=<?php echo $year['id']; ?>" class="btn btn-sm btn-outline">
                                Semesters
                            </a>
                            
                            <?php if (!$year['is_current']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="year_id" value="<?php echo $year['id']; ?>">
                                    <button type="submit" name="set_current" class="btn btn-sm btn-success">
                                        Set as Current
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" onsubmit="return confirm('Delete this academic year? This will also delete all associated semesters.');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="year_id" value="<?php echo $year['id']; ?>">
                                <button type="submit" name="delete_year" class="btn btn-sm btn-error btn-outline">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Create Modal -->
<dialog id="create_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Create Academic Year</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Year Name</span>
                </label>
                <input type="text" name="year_name" placeholder="e.g., 2024-2025" class="input input-bordered" required />
                <label class="label">
                    <span class="label-text-alt">Format: YYYY-YYYY</span>
                </label>
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Start Date</span>
                </label>
                <input type="date" name="start_date" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">End Date</span>
                </label>
                <input type="date" name="end_date" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" name="is_current" class="checkbox checkbox-primary" />
                    <span class="label-text">Set as current academic year</span>
                </label>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="create_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="create_year" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
