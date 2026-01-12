<?php
/**
 * Classes & Sections Management
 * Admin can create and manage classes and sections
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Classes & Sections - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request');
        redirect('classes.php');
    }
    
    // Create Class
    if (isset($_POST['create_class'])) {
        $className = sanitize($_POST['class_name']);
        $classNumeric = (int)$_POST['class_numeric'];
        $description = sanitize($_POST['description']);
        
        if (empty($className)) {
            setFlash('error', 'Class name is required');
        } else {
            $sql = "INSERT INTO classes (class_name, class_numeric, description) VALUES (?, ?, ?)";
            insert($sql, [$className, $classNumeric, $description]);
            setFlash('success', 'Class created successfully');
            redirect('classes.php');
        }
    }
    
    // Create Section
    if (isset($_POST['create_section'])) {
        $classId = (int)$_POST['class_id'];
        $sectionName = sanitize($_POST['section_name']);
        $roomNumber = sanitize($_POST['room_number']);
        $capacity = (int)$_POST['capacity'];
        $teacherId = !empty($_POST['class_teacher_id']) ? (int)$_POST['class_teacher_id'] : null;
        
        if (empty($sectionName)) {
            setFlash('error', 'Section name is required');
        } else {
            $sql = "INSERT INTO sections (class_id, section_name, room_number, capacity, class_teacher_id) 
                    VALUES (?, ?, ?, ?, ?)";
            insert($sql, [$classId, $sectionName, $roomNumber, $capacity, $teacherId]);
            setFlash('success', 'Section created successfully');
            redirect('classes.php');
        }
    }
    
    // Delete Class
    if (isset($_POST['delete_class'])) {
        $classId = (int)$_POST['class_id'];
        try {
            query("DELETE FROM classes WHERE id = ?", [$classId]);
            setFlash('success', 'Class deleted');
            redirect('classes.php');
        } catch (Exception $e) {
            setFlash('error', 'Cannot delete: ' . $e->getMessage());
        }
    }
    
    // Delete Section
    if (isset($_POST['delete_section'])) {
        $sectionId = (int)$_POST['section_id'];
        try {
            query("DELETE FROM sections WHERE id = ?", [$sectionId]);
            setFlash('success', 'Section deleted');
            redirect('classes.php');
        } catch (Exception $e) {
            setFlash('error', 'Cannot delete: ' . $e->getMessage());
        }
    }
}

// Get all classes with sections
$classes = getAll("SELECT * FROM classes ORDER BY class_numeric ASC, class_name ASC");

// Get all teachers for dropdown
$teachers = getAll("
    SELECT t.id, u.full_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE u.is_active = 1
    ORDER BY u.full_name
");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Classes & Sections</h1>
            <p class="text-base-content/60 mt-1">Manage school classes and their sections</p>
        </div>
        <button onclick="create_class_modal.showModal()" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Add Class
        </button>
    </div>

    <?php if (empty($classes)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No classes found. Create your first class to get started.</span>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($classes as $class): 
                $sections = getAll("
                    SELECT s.*, u.full_name as teacher_name,
                           (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = s.id AND e.status = 'active') as student_count
                    FROM sections s
                    LEFT JOIN teachers t ON s.class_teacher_id = t.id
                    LEFT JOIN users u ON t.user_id = u.id
                    WHERE s.class_id = ?
                    ORDER BY s.section_name
                ", [$class['id']]);
            ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <div>
                                <h2 class="card-title text-2xl"><?php echo htmlspecialchars($class['class_name']); ?></h2>
                                <?php if ($class['description']): ?>
                                    <p class="text-sm text-base-content/60 mt-1"><?php echo htmlspecialchars($class['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="showAddSection(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>')" class="btn btn-sm btn-primary">
                                    Add Section
                                </button>
                                <form method="POST" onsubmit="return confirm('Delete this class and all its sections?');" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                    <button type="submit" name="delete_class" class="btn btn-sm btn-error btn-outline">Delete Class</button>
                                </form>
                            </div>
                        </div>
                        
                        <?php if (empty($sections)): ?>
                            <div class="alert alert-warning mt-4">
                                <span>No sections created yet. Add sections to this class.</span>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto mt-4">
                                <table class="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Section</th>
                                            <th>Room</th>
                                            <th>Capacity</th>
                                            <th>Enrolled</th>
                                            <th>Class Teacher</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sections as $section): ?>
                                            <tr>
                                                <td class="font-semibold"><?php echo htmlspecialchars($section['section_name']); ?></td>
                                                <td><?php echo htmlspecialchars($section['room_number'] ?: '-'); ?></td>
                                                <td><?php echo $section['capacity']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $section['student_count'] >= $section['capacity'] ? 'badge-error' : 'badge-success'; ?>">
                                                        <?php echo $section['student_count']; ?> / <?php echo $section['capacity']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($section['teacher_name'] ?: 'Not assigned'); ?></td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Delete this section?');" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                                        <button type="submit" name="delete_section" class="btn btn-xs btn-error btn-outline">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Create Class Modal -->
<dialog id="create_class_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Create Class</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Class Name</span></label>
                <input type="text" name="class_name" placeholder="e.g., Grade 1, Class 10" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Numeric Order</span></label>
                <input type="number" name="class_numeric" placeholder="1, 2, 3..." class="input input-bordered" required />
                <label class="label"><span class="label-text-alt">For sorting purposes</span></label>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Description (Optional)</span></label>
                <textarea name="description" class="textarea textarea-bordered" placeholder="Brief description"></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="create_class_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="create_class" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- Create Section Modal -->
<dialog id="create_section_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Add Section to <span id="section_class_name"></span></h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="class_id" id="section_class_id" value="">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Section Name</span></label>
                <input type="text" name="section_name" placeholder="e.g., A, B, C" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Room Number</span></label>
                <input type="text" name="room_number" placeholder="e.g., 101, 202" class="input input-bordered" />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Capacity</span></label>
                <input type="number" name="capacity" value="30" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Class Teacher (Optional)</span></label>
                <select name="class_teacher_id" class="select select-bordered">
                    <option value="">-- Select Teacher --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="create_section_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="create_section" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
function showAddSection(classId, className) {
    document.getElementById('section_class_id').value = classId;
    document.getElementById('section_class_name').textContent = className;
    create_section_modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
