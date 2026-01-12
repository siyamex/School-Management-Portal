<?php
/**
 * Teacher Subject Assignment
 * Assign teachers to subjects and classes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Teacher Subjects - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Handle assignment
if (isset($_POST['assign']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $teacherId = (int)$_POST['teacher_id'];
    $subjectId = (int)$_POST['subject_id'];
    $sectionId = (int)$_POST['section_id'];
    
    // Check if already assigned
    $existing = getRow("SELECT * FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ? AND section_id = ?",
                      [$teacherId, $subjectId, $sectionId]);
    
    if ($existing) {
        $errors[] = 'This assignment already exists';
    } else {
        try {
            insert("INSERT INTO teacher_subjects (teacher_id, subject_id, section_id) VALUES (?, ?, ?)",
                  [$teacherId, $subjectId, $sectionId]);
            setFlash('success', 'Teacher assigned to subject');
            redirect('assign-subjects.php');
        } catch (Exception $e) {
            $errors[] = 'Assignment failed: ' . $e->getMessage();
        }
    }
}

// Handle removal
if (isset($_POST['remove']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $assignmentId = (int)$_POST['assignment_id'];
    query("DELETE FROM teacher_subjects WHERE id = ?", [$assignmentId]);
    setFlash('success', 'Assignment removed');
    redirect('assign-subjects.php');
}

// Get all teachers
$teachers = getAll("
    SELECT t.id, u.full_name, d.department_name as department
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    ORDER BY u.full_name
");

// Get all subjects
$subjects = getAll("SELECT * FROM subjects ORDER BY subject_name");

// Get all sections with classes
$sections = getAll("
    SELECT s.id, s.section_name, c.class_name
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

// Get all assignments
$assignments = getAll("
    SELECT ts.id, t.id as teacher_id, u.full_name as teacher_name, 
           sub.subject_name, sub.subject_code,
           c.class_name, s.section_name
    FROM teacher_subjects ts
    JOIN teachers t ON ts.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    JOIN subjects sub ON ts.subject_id = sub.id
    JOIN sections s ON ts.section_id = s.id
    JOIN classes c ON s.class_id = c.id
    ORDER BY u.full_name, c.class_numeric, s.section_name
");

// Group by teacher
$assignmentsByTeacher = [];
foreach ($assignments as $assignment) {
    $teacherName = $assignment['teacher_name'];
    if (!isset($assignmentsByTeacher[$teacherName])) {
        $assignmentsByTeacher[$teacherName] = [
            'teacher_id' => $assignment['teacher_id'],
            'assignments' => []
        ];
    }
    $assignmentsByTeacher[$teacherName]['assignments'][] = $assignment;
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Teacher Subject Assignment</h1>
            <p class="text-base-content/60 mt-1">Assign teachers to subjects and classes</p>
        </div>
        <button onclick="assign_modal.showModal()" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            New Assignment
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Teachers</div>
            <div class="stat-value text-primary"><?php echo count($teachers); ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Total Assignments</div>
            <div class="stat-value text-secondary"><?php echo count($assignments); ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">Teachers with Assignments</div>
            <div class="stat-value text-success"><?php echo count($assignmentsByTeacher); ?></div>
        </div>
    </div>

    <!-- Assignments by Teacher -->
    <?php if (empty($assignmentsByTeacher)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No teacher assignments found. Click "New Assignment" to get started.</span>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6">
            <?php foreach ($assignmentsByTeacher as $teacherName => $data): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-center">
                            <h2 class="card-title">
                                <?php echo htmlspecialchars($teacherName); ?>
                                <span class="badge badge-lg"><?php echo count($data['assignments']); ?> assignments</span>
                            </h2>
                            <a href="view.php?id=<?php echo $data['teacher_id']; ?>" class="btn btn-sm btn-ghost">View Profile</a>
                        </div>
                        
                        <div class="overflow-x-auto mt-4">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Code</th>
                                        <th>Class/Section</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['assignments'] as $assignment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['subject_name']); ?></td>
                                            <td><span class="badge badge-sm"><?php echo htmlspecialchars($assignment['subject_code']); ?></span></td>
                                            <td><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['section_name']); ?></td>
                                            <td>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this assignment?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                    <button type="submit" name="remove" class="btn btn-xs btn-error btn-outline">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Assignment Modal -->
<dialog id="assign_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">New Assignment</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Teacher</span></label>
                <select name="teacher_id" class="select select-bordered" required>
                    <option value="">-- Select Teacher --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                            <?php if ($teacher['department']): ?>
                                (<?php echo htmlspecialchars($teacher['department']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Subject</span></label>
                <select name="subject_id" class="select select-bordered" required>
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Class/Section</span></label>
                <select name="section_id" class="select select-bordered" required>
                    <option value="">-- Select Section --</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>">
                            <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="assign_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="assign" class="btn btn-primary">Assign</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
