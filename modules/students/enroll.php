<?php
/**
 * Enroll Student
 * Assign students to classes and sections
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Enroll Student - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Get student ID from URL if provided
$preselectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $studentId = (int)$_POST['student_id'];
        $section_id = (int)$_POST['section_id'];
        $academicYearId = (int)$_POST['academic_year_id'];
        $rollNumber = (int)$_POST['roll_number'];
        $enrollmentDate = sanitize($_POST['enrollment_date']);
        
        if (empty($studentId) || empty($section_id) || empty($academicYearId)) {
            $errors[] = 'All required fields must be filled';
        } else {
            // Check if already enrolled in this section
            $existing = getRow("SELECT * FROM enrollments WHERE student_id = ? AND section_id = ? AND academic_year_id = ?", 
                              [$studentId, $section_id, $academicYearId]);
            
            if ($existing) {
                $errors[] = 'Student is already enrolled in this section for the selected academic year';
            } else {
                // Check if roll number is taken
                $rollTaken = getRow("SELECT * FROM enrollments WHERE section_id = ? AND roll_number = ? AND status = 'active'", 
                                   [$section_id, $rollNumber]);
                
                if ($rollTaken) {
                    $errors[] = 'Roll number is already assigned in this section';
                } else {
                    try {
                        // Deactivate any previous active enrollments for this student
                        query("UPDATE enrollments SET status = 'inactive' WHERE student_id = ? AND status = 'active'", [$studentId]);
                        
                        // Create new enrollment
                        $sql = "INSERT INTO enrollments (student_id, section_id, academic_year_id, roll_number, enrollment_date, status) 
                                VALUES (?, ?, ?, ?, ?, 'active')";
                        insert($sql, [$studentId, $section_id, $academicYearId, $rollNumber, $enrollmentDate]);
                        
                        $success = 'Student enrolled successfully!';
                        
                        // Redirect to student list
                        redirect('list.php?success=enrolled');
                    } catch (Exception $e) {
                        $errors[] = 'Error enrolling student: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Get all students
$students = getAll("
    SELECT st.id, st.student_id, u.full_name,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = st.id AND status = 'active') as has_enrollment
    FROM students st
    JOIN users u ON st.user_id = u.id
    ORDER BY u.full_name
");

// Get academic years
$academicYears = getAll("SELECT * FROM academic_years ORDER BY is_current DESC, start_date DESC");

// Get all classes and sections
$sections = getAll("
    SELECT s.id, s.section_name, s.capacity, c.class_name,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = s.id AND status = 'active') as enrolled_count
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

// If student is preselected, get their info
$preselectedStudent = null;
if ($preselectedStudentId) {
    $preselectedStudent = getRow("
        SELECT st.id, st.student_id, u.full_name
        FROM students st
        JOIN users u ON st.user_id = u.id
        WHERE st.id = ?
    ", [$preselectedStudentId]);
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="list.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Students
        </a>
        <h1 class="text-3xl font-bold">Enroll Student</h1>
        <p class="text-base-content/60 mt-1">Assign a student to a class and section</p>
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
        <!-- Enrollment Form -->
        <div class="lg:col-span-2">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Enrollment Details</h2>
                    
                    <form method="POST" id="enrollmentForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Select Student *</span></label>
                            <select name="student_id" id="student_id" class="select select-bordered" required>
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $preselectedStudentId == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['full_name']); ?> 
                                        (<?php echo htmlspecialchars($student['student_id']); ?>)
                                        <?php if ($student['has_enrollment']): ?>
                                            - Already Enrolled
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Academic Year *</span></label>
                            <select name="academic_year_id" class="select select-bordered" required>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo $year['is_current'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['year_name']); ?>
                                        <?php echo $year['is_current'] ? ' (Current)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Class & Section *</span></label>
                            <select name="section_id" id="section_id" class="select select-bordered" required onchange="suggestRollNumber()">
                                <option value="">-- Choose Class & Section --</option>
                                <?php foreach ($sections as $section): 
                                    $isFull = $section['enrolled_count'] >= $section['capacity'];
                                ?>
                                    <option value="<?php echo $section['id']; ?>" 
                                            data-next-roll="<?php echo $section['enrolled_count'] + 1; ?>"
                                            <?php echo $isFull ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                                        (<?php echo $section['enrolled_count']; ?>/<?php echo $section['capacity']; ?>)
                                        <?php echo $isFull ? ' - FULL' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Roll Number *</span></label>
                            <input type="number" name="roll_number" id="roll_number" min="1" class="input input-bordered" required />
                            <label class="label">
                                <span class="label-text-alt" id="roll_suggestion"></span>
                            </label>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Enrollment Date</span></label>
                            <input type="date" name="enrollment_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered" required />
                        </div>
                        
                        <div class="card-actions justify-end mt-6">
                            <a href="list.php" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary">Enroll Student</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Help/Info Sidebar -->
        <div>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold text-lg mb-4">Instructions</h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="alert alert-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Students can only be enrolled in one class at a time</span>
                        </div>
                        
                        <ol class="list-decimal list-inside space-y-2">
                            <li>Select the student to enroll</li>
                            <li>Choose the academic year</li>
                            <li>Select class and section</li>
                            <li>Assign a roll number (suggested automatically)</li>
                            <li>Click "Enroll Student"</li>
                        </ol>
                        
                        <div class="divider"></div>
                        
                        <p class="font-semibold">Notes:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>Previous active enrollments will be automatically deactivated</li>
                            <li>Roll numbers must be unique within a section</li>
                            <li>Sections at capacity cannot accept new students</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card bg-base-100 shadow-xl mt-6">
                <div class="card-body">
                    <h3 class="font-bold">Quick Actions</h3>
                    <a href="../users/create.php?type=student" class="btn btn-sm btn-outline btn-block">Create New Student</a>
                    <a href="manage-enrollment.php" class="btn btn-sm btn-outline btn-block">Manage Enrollments</a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function suggestRollNumber() {
    const sectionSelect = document.getElementById('section_id');
    const rollInput = document.getElementById('roll_number');
    const suggestion = document.getElementById('roll_suggestion');
    
    if (sectionSelect.value) {
        const selectedOption = sectionSelect.options[sectionSelect.selectedIndex];
        const nextRoll = selectedOption.getAttribute('data-next-roll');
        
        rollInput.value = nextRoll;
        suggestion.textContent = 'Suggested next roll number: ' + nextRoll;
    } else {
        rollInput.value = '';
        suggestion.textContent = '';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
