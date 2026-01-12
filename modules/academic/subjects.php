<?php
/**
 * Subjects Management
 * Admin can create and manage subjects
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Subjects - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request');
        redirect('subjects.php');
    }
    
    if (isset($_POST['create_subject'])) {
        $subjectName = sanitize($_POST['subject_name']);
        $subjectCode = sanitize($_POST['subject_code']);
        $description = sanitize($_POST['description']);
        
        if (empty($subjectName)) {
            setFlash('error', 'Subject name is required');
        } else {
            $sql = "INSERT INTO subjects (subject_name, subject_code, description) VALUES (?, ?, ?)";
            insert($sql, [$subjectName, $subjectCode, $description]);
            setFlash('success', 'Subject created successfully');
            redirect('subjects.php');
        }
    }
    
    if (isset($_POST['assign_to_class'])) {
        $subjectId = (int)$_POST['subject_id'];
        $classId = (int)$_POST['class_id'];
        $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        
        try {
            $sql = "INSERT INTO class_subjects (class_id, subject_id, is_mandatory) VALUES (?, ?, ?)";
            insert($sql, [$classId, $subjectId, $isMandatory]);
            setFlash('success', 'Subject assigned to class');
            redirect('subjects.php');
        } catch (Exception $e) {
            setFlash('error', 'Assignment failed: ' . $e->getMessage());
        }
    }
    
    if (isset($_POST['delete_subject'])) {
        $subjectId = (int)$_POST['subject_id'];
        try {
            query("DELETE FROM subjects WHERE id = ?", [$subjectId]);
            setFlash('success', 'Subject deleted');
            redirect('subjects.php');
        } catch (Exception $e) {
            setFlash('error', 'Cannot delete: ' . $e->getMessage());
        }
    }
}

// Get all subjects with class assignments
$subjects = getAll("SELECT * FROM subjects ORDER BY subject_name");
$classes = getAll("SELECT * FROM classes ORDER BY class_numeric");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Subjects</h1>
            <p class="text-base-content/60 mt-1">Manage school subjects and assign to classes</p>
        </div>
        <button onclick="create_modal.showModal()" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Add Subject
        </button>
    </div>

    <?php if (empty($subjects)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No subjects found. Create your first subject to get started.</span>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach ($subjects as $subject):
                $assignedClasses = getAll("
                    SELECT c.class_name, cs.is_mandatory, cs.id as assignment_id
                    FROM class_subjects cs
                    JOIN classes c ON cs.class_id = c.id
                    WHERE cs.subject_id = ?
                    ORDER BY c.class_numeric
                ", [$subject['id']]);
            ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <div>
                                <h2 class="card-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h2>
                                <?php if ($subject['subject_code']): ?>
                                    <span class="badge badge-outline mt-2"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this subject?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                <button type="submit" name="delete_subject" class="btn btn-sm btn-error btn-outline">Delete</button>
                            </form>
                        </div>
                        
                        <?php if ($subject['description']): ?>
                            <p class="text-sm text-base-content/60"><?php echo htmlspecialchars($subject['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="divider">Assigned Classes</div>
                        
                        <?php if (empty($assignedClasses)): ?>
                            <p class="text-sm text-base-content/60">Not assigned to any class yet</p>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($assignedClasses as $ac): ?>
                                    <span class="badge badge-lg <?php echo $ac['is_mandatory'] ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo htmlspecialchars($ac['class_name']); ?>
                                        <?php echo $ac['is_mandatory'] ? '(Required)' : '(Optional)'; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <button onclick="showAssignModal(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_name']); ?>')" class="btn btn-sm btn-primary mt-4">
                            Assign to Class
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Create Subject Modal -->
<dialog id="create_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Create Subject</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Subject Name</span></label>
                <input type="text" name="subject_name" placeholder="e.g., Mathematics, English" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Subject Code (Optional)</span></label>
                <input type="text" name="subject_code" placeholder="e.g., MATH101" class="input input-bordered" />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Description (Optional)</span></label>
                <textarea name="description" class="textarea textarea-bordered" placeholder="Brief description"></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="create_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="create_subject" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- Assign to Class Modal -->
<dialog id="assign_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Assign <span id="assign_subject_name"></span> to Class</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="subject_id" id="assign_subject_id" value="">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Select Class</span></label>
                <select name="class_id" class="select select-bordered" required>
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" name="is_mandatory" class="checkbox checkbox-primary" checked />
                    <span class="label-text">Mandatory subject</span>
                </label>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="assign_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="assign_to_class" class="btn btn-primary">Assign</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
function showAssignModal(subjectId, subjectName) {
    document.getElementById('assign_subject_id').value = subjectId;
    document.getElementById('assign_subject_name').textContent = subjectName;
    assign_modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
