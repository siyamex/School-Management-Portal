<?php
/**
 * Manage Enrollments
 * View, transfer, and promote student enrollments
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Manage Enrollments - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Handle transfer
if (isset($_POST['transfer']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $enrollmentId = (int)$_POST['enrollment_id'];
    $newSectionId = (int)$_POST['new_section_id'];
    $newRollNumber = (int)$_POST['new_roll_number'];
    
    // Check if roll number is available
    $rollTaken = getRow("SELECT * FROM enrollments WHERE section_id = ? AND roll_number = ? AND status = 'active' AND id != ?", 
                       [$newSectionId, $newRollNumber, $enrollmentId]);
    
    if ($rollTaken) {
        $errors[] = 'Roll number is already assigned in the target section';
    } else {
        try {
            query("UPDATE enrollments SET section_id = ?, roll_number = ? WHERE id = ?", 
                  [$newSectionId, $newRollNumber, $enrollmentId]);
            setFlash('success', 'Student transferred successfully');
            redirect('manage-enrollment.php');
        } catch (Exception $e) {
            $errors[] = 'Transfer failed: ' . $e->getMessage();
        }
    }
}

// Handle bulk promotion
if (isset($_POST['promote']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $fromSectionId = (int)$_POST['from_section'];
    $toSectionId = (int)$_POST['to_section'];
    $academicYearId = (int)$_POST['academic_year_id'];
    
    try {
        beginTransaction();
        
        // Get all students from source section
        $students = getAll("
            SELECT student_id FROM enrollments 
            WHERE section_id = ? AND status = 'active'
            ORDER BY roll_number
        ", [$fromSectionId]);
        
        $promoted = 0;
        $rollNumber = 1;
        
        foreach ($students as $student) {
            // Deactivate old enrollment
            query("UPDATE enrollments SET status = 'inactive' WHERE student_id = ? AND status = 'active'", 
                  [$student['student_id']]);
            
            // Create new enrollment
            insert("INSERT INTO enrollments (student_id, section_id, academic_year_id, roll_number, enrollment_date, status) 
                   VALUES (?, ?, ?, ?, NOW(), 'active')",
                  [$student['student_id'], $toSectionId, $academicYearId, $rollNumber]);
            
            $rollNumber++;
            $promoted++;
        }
        
        commit();
        setFlash('success', "Successfully promoted $promoted students");
        redirect('manage-enrollment.php');
    } catch (Exception $e) {
        rollback();
        $errors[] = 'Promotion failed: ' . $e->getMessage();
    }
}

// Handle withdraw
if (isset($_POST['withdraw']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $enrollmentId = (int)$_POST['enrollment_id'];
    query("UPDATE enrollments SET status = 'withdrawn' WHERE id = ?", [$enrollmentId]);
    setFlash('success', 'Student withdrawn from class');
    redirect('manage-enrollment.php');
}

// Get filter parameters
$classFilter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$sectionFilter = isset($_GET['section']) ? (int)$_GET['section'] : 0;
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : 'active';

// Build query
$whereClauses = ["1=1"];
$params = [];

if ($classFilter) {
    $whereClauses[] = "c.id = ?";
    $params[] = $classFilter;
}

if ($sectionFilter) {
    $whereClauses[] = "s.id = ?";
    $params[] = $sectionFilter;
}

if ($statusFilter) {
    $whereClauses[] = "e.status = ?";
    $params[] = $statusFilter;
}

$whereSQL = implode(' AND ', $whereClauses);

// Get enrollments
$enrollments = getAll("
    SELECT e.id, e.roll_number, e.enrollment_date, e.status,
           st.id as student_id, st.student_id as student_code, 
           u.full_name, c.class_name, s.section_name, s.id as section_id,
           a.year_name
    FROM enrollments e
    JOIN students st ON e.student_id = st.id
    JOIN users u ON st.user_id = u.id
    JOIN sections s ON e.section_id = s.id
    JOIN classes c ON s.class_id = c.id
    JOIN academic_years a ON e.academic_year_id = a.id
    WHERE $whereSQL
    ORDER BY c.class_numeric, s.section_name, e.roll_number
", $params);

// Get classes and sections for filters
$classes = getAll("SELECT * FROM classes ORDER BY class_numeric");
$sections = getAll("SELECT s.*, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY c.class_numeric, s.section_name");
$academicYears = getAll("SELECT * FROM academic_years ORDER BY is_current DESC, start_date DESC");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Manage Enrollments</h1>
            <p class="text-base-content/60 mt-1">Transfer students and bulk promotions</p>
        </div>
        <button onclick="promote_modal.showModal()" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 11l5-5m0 0l5 5m-5-5v12" />
            </svg>
            Bulk Promote
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Class</span></label>
                    <select name="class" class="select select-bordered select-sm">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $classFilter == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Section</span></label>
                    <select name="section" class="select select-bordered select-sm">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $sectionFilter == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Status</span></label>
                    <select name="status" class="select select-bordered select-sm">
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="withdrawn" <?php echo $statusFilter === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">&nbsp;</span></label>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Enrollments</div>
            <div class="stat-value text-primary"><?php echo count($enrollments); ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Active</div>
            <div class="stat-value text-success">
                <?php echo count(array_filter($enrollments, fn($e) => $e['status'] === 'active')); ?>
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">Withdrawn</div>
            <div class="stat-value text-error">
                <?php echo count(array_filter($enrollments, fn($e) => $e['status'] === 'withdrawn')); ?>
            </div>
        </div>
    </div>

    <!-- Enrollments Table -->
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>Roll</th>
                            <th>Student</th>
                            <th>Class/Section</th>
                            <th>Academic Year</th>
                            <th>Enrolled Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $enrollment): ?>
                            <tr>
                                <td><?php echo str_pad($enrollment['roll_number'], 2, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div>
                                        <div class="font-semibold"><?php echo htmlspecialchars($enrollment['full_name']); ?></div>
                                        <div class="text-xs text-base-content/60"><?php echo htmlspecialchars($enrollment['student_code']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($enrollment['class_name'] . ' - ' . $enrollment['section_name']); ?></td>
                                <td><?php echo htmlspecialchars($enrollment['year_name']); ?></td>
                                <td><?php echo formatDate($enrollment['enrollment_date'], 'M d, Y'); ?></td>
                                <td>
                                    <?php if ($enrollment['status'] === 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php elseif ($enrollment['status'] === 'inactive'): ?>
                                        <span class="badge badge-warning">Inactive</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Withdrawn</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($enrollment['status'] === 'active'): ?>
                                        <div class="flex gap-1">
                                            <button onclick="openTransferModal(<?php echo $enrollment['id']; ?>, '<?php echo htmlspecialchars($enrollment['full_name']); ?>', <?php echo $enrollment['section_id']; ?>)" 
                                                    class="btn btn-xs btn-ghost">Transfer</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Withdraw this student?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                                                <button type="submit" name="withdraw" class="btn btn-xs btn-error btn-outline">Withdraw</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Transfer Modal -->
<dialog id="transfer_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Transfer Student</h3>
        <p id="transfer_student_name" class="mb-4"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="enrollment_id" id="transfer_enrollment_id">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">New Section</span></label>
                <select name="new_section_id" id="new_section_select" class="select select-bordered" required onchange="suggestNewRoll()">
                    <option value="">-- Select Section --</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>" 
                                data-next-roll="<?php echo getValue("SELECT COALESCE(MAX(roll_number), 0) + 1 FROM enrollments WHERE section_id = ? AND status = 'active'", [$section['id']]); ?>">
                            <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">New Roll Number</span></label>
                <input type="number" name="new_roll_number" id="new_roll_number" class="input input-bordered" required />
                <label class="label">
                    <span class="label-text-alt" id="roll_suggestion"></span>
                </label>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="transfer_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="transfer" class="btn btn-primary">Transfer</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Promote Modal -->
<dialog id="promote_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Bulk Promote Students</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">From Section</span></label>
                <select name="from_section" class="select select-bordered" required>
                    <option value="">-- Select Current Section --</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>">
                            <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                            (<?php echo getValue("SELECT COUNT(*) FROM enrollments WHERE section_id = ? AND status = 'active'", [$section['id']]); ?> students)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">To Section</span></label>
                <select name="to_section" class="select select-bordered" required>
                    <option value="">-- Select New Section --</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>">
                            <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Academic Year</span></label>
                <select name="academic_year_id" class="select select-bordered" required>
                    <?php foreach ($academicYears as $year): ?>
                        <option value="<?php echo $year['id']; ?>" <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['year_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="alert alert-warning text-xs mb-4">
                <span>This will move ALL active students from the source section to the target section. Old enrollments will be marked inactive.</span>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="promote_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="promote" class="btn btn-success" onclick="return confirm('Promote all students? This cannot be undone.');">Promote All</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function openTransferModal(enrollmentId, studentName, currentSectionId) {
    document.getElementById('transfer_enrollment_id').value = enrollmentId;
    document.getElementById('transfer_student_name').textContent = 'Transferring: ' + studentName;
    
    // Remove current section from options
    const select = document.getElementById('new_section_select');
    for (let option of select.options) {
        if (option.value == currentSectionId) {
            option.disabled = true;
            option.text += ' (Current)';
        }
    }
    
    transfer_modal.showModal();
}

function suggestNewRoll() {
    const select = document.getElementById('new_section_select');
    const selectedOption = select.options[select.selectedIndex];
    const nextRoll = selectedOption.getAttribute('data-next-roll');
    
    document.getElementById('new_roll_number').value = nextRoll;
    document.getElementById('roll_suggestion').textContent = 'Suggested: ' + nextRoll;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
