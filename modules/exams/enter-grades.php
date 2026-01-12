<?php
/**
 * Enter Grades - Teacher
 * Teachers can enter exam grades for students
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Enter Grades - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'leading_teacher', 'admin', 'principal']);

$errors = [];
$success = '';

// Get current academic year
$currentYear = getRow("SELECT * FROM academic_years WHERE is_current = 1");

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $examId = (int)$_POST['exam_id'];
        $subjectId = (int)$_POST['subject_id'];
        $grades = $_POST['grades'] ?? [];
        $isPublished = isset($_POST['publish']) ? 1 : 0;
        
        if (empty($grades)) {
            $errors[] = 'No grades entered';
        } else {
            try {
                beginTransaction();
                
                foreach ($grades as $studentId => $data) {
                    $marksObtained = (int)($data['marks'] ?? 0);
                    $remarks = sanitize($data['remarks'] ?? '');
                    
                    // Skip if no marks entered
                    if ($marksObtained == 0 && empty($remarks)) continue;
                    
                    // Calculate grade based on marks (out of 100)
                    $gradeData = calculateGrade($marksObtained);
                    
                    // Check if grade already exists
                    $existing = getRow("SELECT id FROM grades WHERE student_id = ? AND exam_id = ? AND subject_id = ?", 
                                      [$studentId, $examId, $subjectId]);
                    
                    if ($existing) {
                        // Update
                        query("UPDATE grades SET marks_obtained = ?, grade_letter = ?, grade_point = ?, remarks = ?, is_published = ? 
                               WHERE id = ?", 
                              [$marksObtained, $gradeData['letter'], $gradeData['point'], $remarks, $isPublished, $existing['id']]);
                    } else {
                        // Insert
                        query("INSERT INTO grades (student_id, exam_id, subject_id, marks_obtained, grade_letter, grade_point, remarks, is_published) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                              [$studentId, $examId, $subjectId, $marksObtained, $gradeData['letter'], $gradeData['point'], $remarks, $isPublished]);
                    }
                }
                
                commit();
                $success = 'Grades saved successfully' . ($isPublished ? ' and published' : ' as draft');
            } catch (Exception $e) {
                rollback();
                $errors[] = 'Error saving grades: ' . $e->getMessage();
            }
        }
    }
}

// Get sections and subjects for dropdowns
$sections = getAll("
    SELECT s.id, s.section_name, c.class_name
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

$subjects = getAll("SELECT * FROM subjects ORDER BY subject_name");

// Get exams for current year
$exams = getAll("SELECT * FROM exams WHERE academic_year_id = ? ORDER BY created_at DESC", [$currentYear['id'] ?? 0]);

// If exam and section selected, get students with grades
$selectedExam = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$selectedSubject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selectedSection = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

$students = [];
if ($selectedExam && $selectedSubject && $selectedSection) {
    $students = getAll("
        SELECT st.id, u.full_name, e.roll_number,
               g.marks_obtained, g.grade_letter, g.grade_point, g.remarks, g.is_published
        FROM enrollments e
        JOIN students st ON e.student_id = st.id
        JOIN users u ON st.user_id = u.id
        LEFT JOIN grades g ON st.id = g.student_id AND g.exam_id = ? AND g.subject_id = ?
        WHERE e.section_id = ? AND e.status = 'active'
        ORDER BY e.roll_number, u.full_name
    ", [$selectedExam, $selectedSubject, $selectedSection]);
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Enter Grades</h1>
        <p class="text-base-content/60 mt-1">Record exam results for your students</p>
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

    <!-- Selection Form -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <form method="GET">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Exam</span></label>
                        <select name="exam_id" class="select select-bordered" required>
                            <option value="">-- Select Exam --</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $selectedExam == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Subject</span></label>
                        <select name="subject_id" class="select select-bordered" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubject == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Class/Section</span></label>
                        <select name="section_id" class="select select-bordered" required>
                            <option value="">-- Select Section --</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo $selectedSection == $section['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">&nbsp;</span></label>
                        <button type="submit" class="btn btn-primary">Load Students</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Grade Entry Table -->
    <?php if (!empty($students)): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="exam_id" value="<?php echo $selectedExam; ?>">
            <input type="hidden" name="subject_id" value="<?php echo $selectedSubject; ?>">
            
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="card-title">Student Roster (<?php echo count($students); ?> students)</h2>
                        <div class="form-control">
                            <label class="label cursor-pointer gap-2">
                                <input type="checkbox" name="publish" class="checkbox checkbox-success" />
                                <span class="label-text">Publish grades to students</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Roll</th>
                                    <th>Student Name</th>
                                    <th>Marks (out of 100)</th>
                                    <th>Grade</th>
                                    <th>GPA</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): 
                                    $grade = calculateGrade($student['marks_obtained'] ?? 0);
                                ?>
                                    <tr>
                                        <td class="font-semibold"><?php echo str_pad($student['roll_number'], 2, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td>
                                            <input type="number" name="grades[<?php echo $student['id']; ?>][marks]" 
                                                   value="<?php echo $student['marks_obtained'] ?? ''; ?>"
                                                   min="0" max="100" class="input input-sm input-bordered w-24" 
                                                   onchange="updateGrade(this)" />
                                        </td>
                                        <td>
                                            <span class="grade-letter badge badge-sm"><?php echo $student['grade_letter'] ?: '-'; ?></span>
                                        </td>
                                        <td>
                                            <span class="grade-point"><?php echo $student['grade_point'] !== null ? number_format($student['grade_point'], 2) : '-'; ?></span>
                                        </td>
                                        <td>
                                            <input type="text" name="grades[<?php echo $student['id']; ?>][remarks]" 
                                                   value="<?php echo htmlspecialchars($student['remarks'] ?? ''); ?>"
                                                   placeholder="Optional" class="input input-sm input-bordered w-40" />
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card-actions justify-end mt-6">
                        <button type="submit" name="save_grades" class="btn btn-primary btn-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Save Grades
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php elseif ($selectedExam && $selectedSubject && $selectedSection): ?>
        <div class="alert alert-warning">
            <span>No students enrolled in this section.</span>
        </div>
    <?php endif; ?>
</main>

<script>
function updateGrade(input) {
    const marks = parseInt(input.value) || 0;
    const row = input.closest('tr');
    const gradeLetter = row.querySelector('.grade-letter');
    const gradePoint = row.querySelector('.grade-point');
    
    // Calculate grade
    let letter = '-', point = '-';
    if (marks >= 90) { letter = 'A+'; point = '4.0'; }
    else if (marks >= 85) { letter = 'A'; point = '3.7'; }
    else if (marks >= 80) { letter = 'B+'; point = '3.3'; }
    else if (marks >= 75) { letter = 'B'; point = '3.0'; }
    else if (marks >= 70) { letter = 'C+'; point = '2.7'; }
    else if (marks >= 65) { letter = 'C'; point = '2.3'; }
    else if (marks >= 60) { letter = 'D+'; point = '2.0'; }
    else if (marks >= 55) { letter = 'D'; point = '1.7'; }
    else if (marks >= 50) { letter = 'E'; point = '1.0'; }
    else if (marks > 0) { letter = 'F'; point = '0.0'; }
    
    gradeLetter.textContent = letter;
    gradePoint.textContent = point;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
