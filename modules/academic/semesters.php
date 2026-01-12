<?php
/**
 * Semester Management
 * Admin can create and manage semesters within academic years
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Semesters - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

// Get year ID from URL or use current
$yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;

// Get academic year
if ($yearId) {
    $academicYear = getRow("SELECT * FROM academic_years WHERE id = ?", [$yearId]);
} else {
    $academicYear = getRow("SELECT * FROM academic_years WHERE is_current = 1");
    if ($academicYear) $yearId = $academicYear['id'];
}

if (!$academicYear) {
    die("Academic year not found. Please create an academic year first.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request');
        redirect('semesters.php?year_id=' . $yearId);
    }
    
    if (isset($_POST['create_semester'])) {
        $semesterName = sanitize($_POST['semester_name']);
        $semesterNumber = (int)$_POST['semester_number'];
        $startDate = sanitize($_POST['start_date']);
        $endDate = sanitize($_POST['end_date']);
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        
        if (empty($semesterName) || empty($startDate) || empty($endDate)) {
            setFlash('error', 'All fields are required');
        } else {
            try {
                // If setting as current, unset all others for this year
                if ($isCurrent) {
                    query("UPDATE semesters SET is_current = 0 WHERE academic_year_id = ?", [$yearId]);
                }
                
                $sql = "INSERT INTO semesters (academic_year_id, semester_name, semester_number, start_date, end_date, is_current) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                insert($sql, [$yearId, $semesterName, $semesterNumber, $startDate, $endDate, $isCurrent]);
                
                setFlash('success', 'Semester created successfully');
                redirect('semesters.php?year_id=' . $yearId);
            } catch (Exception $e) {
                setFlash('error', 'Error creating semester: ' . $e->getMessage());
            }
        }
    }
    
    if (isset($_POST['set_current'])) {
        $semesterId = (int)$_POST['semester_id'];
        query("UPDATE semesters SET is_current = 0 WHERE academic_year_id = ?", [$yearId]);
        query("UPDATE semesters SET is_current = 1 WHERE id = ?", [$semesterId]);
        setFlash('success', 'Current semester updated');
        redirect('semesters.php?year_id=' . $yearId);
    }
    
    if (isset($_POST['delete_semester'])) {
        $semesterId = (int)$_POST['semester_id'];
        try {
            query("DELETE FROM semesters WHERE id = ?", [$semesterId]);
            setFlash('success', 'Semester deleted');
            redirect('semesters.php?year_id=' . $yearId);
        } catch (Exception $e) {
            setFlash('error', 'Cannot delete: ' . $e->getMessage());
        }
    }
}

// Get all semesters for this year
$semesters = getAll("SELECT * FROM semesters WHERE academic_year_id = ? ORDER BY semester_number", [$yearId]);

// Get all academic years for dropdown
$allYears = getAll("SELECT * FROM academic_years ORDER BY start_date DESC");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Semesters</h1>
            <p class="text-base-content/60 mt-1">Manage semesters for <?php echo htmlspecialchars($academicYear['year_name']); ?></p>
        </div>
        <div class="flex gap-3">
            <select class="select select-bordered" onchange="if(this.value) window.location.href='semesters.php?year_id='+this.value">
                <?php foreach ($allYears as $year): ?>
                    <option value="<?php echo $year['id']; ?>" <?php echo $year['id'] == $yearId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($year['year_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="create_modal.showModal()" class="btn btn-primary gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                </svg>
                Add Semester
            </button>
        </div>
    </div>

    <?php if (empty($semesters)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No semesters found. Create your first semester to get started.</span>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($semesters as $semester): ?>
                <div class="card bg-base-100 shadow-xl <?php echo $semester['is_current'] ? 'ring-2 ring-primary' : ''; ?>">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <h2 class="card-title"><?php echo htmlspecialchars($semester['semester_name']); ?></h2>
                            <?php if ($semester['is_current']): ?>
                                <span class="badge badge-primary">Current</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="space-y-2 text-sm">
                            <p><strong>Number:</strong> Semester <?php echo $semester['semester_number']; ?></p>
                            <p><strong>Start:</strong> <?php echo formatDate($semester['start_date'], 'M d, Y'); ?></p>
                            <p><strong>End:</strong> <?php echo formatDate($semester['end_date'], 'M d, Y'); ?></p>
                            <p><strong>Duration:</strong> 
                                <?php 
                                $start = new DateTime($semester['start_date']);
                                $end = new DateTime($semester['end_date']);
                                $diff = $start->diff($end);
                                echo $diff->days . ' days';
                                ?>
                            </p>
                        </div>
                        
                        <div class="card-actions justify-end mt-4">
                            <?php if (!$semester['is_current']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="semester_id" value="<?php echo $semester['id']; ?>">
                                    <button type="submit" name="set_current" class="btn btn-sm btn-success">
                                        Set as Current
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" onsubmit="return confirm('Delete this semester? This will also affect associated exams.');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="semester_id" value="<?php echo $semester['id']; ?>">
                                <button type="submit" name="delete_semester" class="btn btn-sm btn-error btn-outline">
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
        <h3 class="font-bold text-lg mb-4">Create Semester</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Semester Name</span>
                </label>
                <input type="text" name="semester_name" placeholder="e.g., Fall 2024, Spring 2025" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text">Semester Number</span>
                </label>
                <input type="number" name="semester_number" placeholder="1, 2, 3..." class="input input-bordered" required />
                <label class="label">
                    <span class="label-text-alt">For ordering (1st semester, 2nd semester, etc.)</span>
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
                    <span class="label-text">Set as current semester</span>
                </label>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="create_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="create_semester" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
