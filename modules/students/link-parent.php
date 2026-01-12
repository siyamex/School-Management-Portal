<?php
/**
 * Link Parent to Student
 * Connect parents/guardians to students
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Link Parent - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Get student ID from URL if provided
$preselectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Handle linking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $studentId = (int)$_POST['student_id'];
        $parentId = (int)$_POST['parent_id'];
        $relationship = sanitize($_POST['relationship']);
        $isPrimary = isset($_POST['is_primary_contact']) ? 1 : 0;
        
        if (empty($studentId) || empty($parentId) || empty($relationship)) {
            $errors[] = 'All required fields must be filled';
        } else {
            // Check if already linked
            $existing = getRow("SELECT * FROM student_parents WHERE student_id = ? AND parent_id = ?", 
                              [$studentId, $parentId]);
            
            if ($existing) {
                $errors[] = 'This parent is already linked to the student';
            } else {
                try {
                    // If setting as primary, unset all others for this student
                    if ($isPrimary) {
                        query("UPDATE student_parents SET is_primary_contact = 0 WHERE student_id = ?", [$studentId]);
                    }
                    
                    // Create link
                    $sql = "INSERT INTO student_parents (student_id, parent_id, relationship, is_primary_contact) 
                            VALUES (?, ?, ?, ?)";
                    insert($sql, [$studentId, $parentId, $relationship, $isPrimary]);
                    
                    $success = 'Parent linked successfully!';
                    redirect('view.php?id=' . $studentId);
                } catch (Exception $e) {
                    $errors[] = 'Error linking parent: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get all students
$students = getAll("
    SELECT st.id, st.student_id, u.full_name
    FROM students st
    JOIN users u ON st.user_id = u.id
    ORDER BY u.full_name
");

// Get all parents
$parents = getAll("
    SELECT p.id, u.full_name, u.email
    FROM parents p
    JOIN users u ON p.user_id = u.id
    ORDER BY u.full_name
");

// If student is preselected, get their info and existing parents
$preselectedStudent = null;
$linkedParents = [];
if ($preselectedStudentId) {
    $preselectedStudent = getRow("
        SELECT st.id, st.student_id, u.full_name
        FROM students st
        JOIN users u ON st.user_id = u.id
        WHERE st.id = ?
    ", [$preselectedStudentId]);
    
    $linkedParents = getAll("
        SELECT sp.*, p.id as parent_id, u.full_name as parent_name, u.email
        FROM student_parents sp
        JOIN parents p ON sp.parent_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE sp.student_id = ?
    ", [$preselectedStudentId]);
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="<?php echo $preselectedStudentId ? 'view.php?id='.$preselectedStudentId : 'list.php'; ?>" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
        </a>
        <h1 class="text-3xl font-bold">Link Parent to Student</h1>
        <p class="text-base-content/60 mt-1">Connect a parent or guardian to a student</p>
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
        <!-- Link Form -->
        <div class="lg:col-span-2">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Link Details</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Select Student *</span></label>
                            <select name="student_id" class="select select-bordered" required <?php echo $preselectedStudentId ? 'disabled' : ''; ?>>
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $preselectedStudentId == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['full_name']); ?> 
                                        (<?php echo htmlspecialchars($student['student_id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($preselectedStudentId): ?>
                                <input type="hidden" name="student_id" value="<?php echo $preselectedStudentId; ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Select Parent *</span></label>
                            <select name="parent_id" class="select select-bordered" required>
                                <option value="">-- Choose Parent --</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo htmlspecialchars($parent['full_name']); ?> 
                                        (<?php echo htmlspecialchars($parent['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label class="label">
                                <span class="label-text-alt">Don't see the parent? <a href="../users/create.php?type=parent" class="link">Create new parent user</a></span>
                            </label>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Relationship *</span></label>
                            <select name="relationship" class="select select-bordered" required>
                                <option value="">-- Select Relationship --</option>
                                <option value="father">Father</option>
                                <option value="mother">Mother</option>
                                <option value="guardian">Legal Guardian</option>
                                <option value="stepfather">Step-Father</option>
                                <option value="stepmother">Step-Mother</option>
                                <option value="grandfather">Grandfather</option>
                                <option value="grandmother">Grandmother</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" name="is_primary_contact" class="checkbox checkbox-primary" />
                                <span class="label-text">Set as primary contact</span>
                            </label>
                            <label class="label">
                                <span class="label-text-alt">Primary contact receives all communications from school</span>
                            </label>
                        </div>
                        
                        <div class="card-actions justify-end mt-6">
                            <a href="<?php echo $preselectedStudentId ? 'view.php?id='.$preselectedStudentId : 'list.php'; ?>" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary">Link Parent</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Instructions -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold text-lg mb-4">Instructions</h3>
                    
                    <ol class="list-decimal list-inside space-y-2 text-sm">
                        <li>Select the student</li>
                        <li>Choose the parent from the list</li>
                        <li>Specify the relationship</li>
                        <li>Optionally set as primary contact</li>
                        <li>Click "Link Parent"</li>
                    </ol>
                    
                    <div class="divider"></div>
                    
                    <div class="alert alert-info text-xs">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>A student can have multiple parents. Only one can be the primary contact.</span>
                    </div>
                </div>
            </div>

            <!-- Existing Links (if student selected) -->
            <?php if (!empty($linkedParents)): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="font-bold mb-2">Linked Parents</h3>
                        <?php foreach ($linkedParents as $link): ?>
                            <div class="border-b border-base-300 pb-2 mb-2 last:border-0">
                                <p class="font-semibold"><?php echo htmlspecialchars($link['parent_name']); ?></p>
                                <p class="text-xs text-base-content/60">
                                    <?php echo ucfirst($link['relationship']); ?>
                                    <?php if ($link['is_primary_contact']): ?>
                                        <span class="badge badge-xs badge-primary">Primary</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
