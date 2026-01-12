<?php
/**
 * Individual Student Report Card
 * Printable report card for a specific student and exam
 */

$pageTitle = "Report Card - " . APP_NAME;
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

// Get parameters
$studentId = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$examId = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
$print = isset($_GET['print']) && $_GET['print'] === '1';

if (!$studentId || !$examId) {
    die("Invalid parameters");
}

// Get student info
$student = getRow("
    SELECT st.*, u.full_name, u.photo, e.roll_number,
           c.class_name, s.section_name, a.year_name
    FROM students st
    JOIN users u ON st.user_id = u.id
    LEFT JOIN enrollments e ON st.id = e.student_id AND e.status = 'active'
    LEFT JOIN sections sec ON e.section_id = sec.id
    LEFT JOIN classes c ON sec.class_id = c.id
    LEFT JOIN academic_years a ON e.academic_year_id = a.id
    WHERE st.id = ?
", [$studentId]);

if (!$student) {
    die("Student not found");
}

// Get exam info
$exam = getRow("SELECT * FROM exams WHERE id = ?", [$examId]);

if (!$exam) {
    die("Exam not found");
}

// Get all grades for this student and exam
$grades = getAll("
    SELECT g.*, sub.subject_name, sub.subject_code
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    WHERE g.student_id = ? AND g.exam_id = ? AND g.is_published = 1
    ORDER BY sub.subject_name
", [$studentId, $examId]);

// Calculate statistics
$totalMarks = 0;
$obtainedMarks = 0;
$totalGradePoints = 0;
$subjectCount = count($grades);

foreach ($grades as $grade) {
    $totalMarks += $exam['total_marks'];
    $obtainedMarks += $grade['marks_obtained'];
    $totalGradePoints += $grade['grade_point'];
}

$percentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
$gpa = $subjectCount > 0 ? round($totalGradePoints / $subjectCount, 2) : 0;

// Determine overall grade
$overallGrade = '-';
if ($gpa >= 3.7) $overallGrade = 'A+';
elseif ($gpa >= 3.3) $overallGrade = 'A';
elseif ($gpa >= 3.0) $overallGrade = 'B+';
elseif ($gpa >= 2.7) $overallGrade = 'B';
elseif ($gpa >= 2.3) $overallGrade = 'C+';
elseif ($gpa >= 2.0) $overallGrade = 'C';
elseif ($gpa >= 1.0) $overallGrade = 'D';
else $overallGrade = 'F';

// Get class rank (optional)
$classRank = getValue("
    SELECT COUNT(*) + 1
    FROM (
        SELECT student_id, AVG(grade_point) as avg_gpa
        FROM grades
        WHERE exam_id = ? AND student_id IN (
            SELECT student_id FROM enrollments WHERE section_id = (
                SELECT section_id FROM enrollments WHERE student_id = ? AND status = 'active'
            ) AND status = 'active'
        ) AND is_published = 1
        GROUP BY student_id
        HAVING AVG(grade_point) > ?
    ) as rankings
", [$examId, $studentId, $gpa]);

if (!$print) {
    require_once __DIR__ . '/../../includes/header.php';
}
?>

<?php if (!$print): ?>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white; }
    .report-card { box-shadow: none !important; padding: 0 !important; }
}

.report-card {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
}

.report-header {
    text-align: center;
    border-bottom: 3px solid #000;
    padding-bottom: 1rem;
    margin-bottom: 2rem;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th,
.report-table td {
    border: 1px solid #000;
    padding: 0.5rem;
    text-align: left;
}

.report-table th {
    background: #f3f4f6;
    font-weight: bold;
}

.grade-summary {
    margin-top: 2rem;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.grade-box {
    border: 2px solid #000;
    padding: 1rem;
    text-align: center;
}
</style>

<main class="<?php echo !$print ? 'flex-1 p-6 lg:p-8' : ''; ?>">
    <?php if (!$print): ?>
        <div class="no-print mb-4">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report Card</button>
            <a href="grade-report.php" class="btn btn-ghost">Back to Reports</a>
        </div>
    <?php endif; ?>

    <div class="report-card">
        <!-- Header -->
        <div class="report-header">
            <h1 style="font-size: 2rem; margin: 0;"><?php echo APP_NAME; ?></h1>
            <h2 style="font-size: 1.5rem; margin: 0.5rem 0;">STUDENT REPORT CARD</h2>
            <p style="margin: 0;">Academic Year: <?php echo htmlspecialchars($student['year_name']); ?></p>
        </div>

        <!-- Student Info -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
            <div>
                <p><strong>Student Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                <p><strong>Class/Section:</strong> <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section_name']); ?></p>
                <p><strong>Roll Number:</strong> <?php echo $student['roll_number']; ?></p>
            </div>
            <div>
                <p><strong>Exam:</strong> <?php echo htmlspecialchars($exam['exam_name']); ?></p>
                <p><strong>Exam Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $exam['exam_type'])); ?></p>
                <?php if ($exam['exam_date']): ?>
                    <p><strong>Exam Date:</strong> <?php echo formatDate($exam['exam_date'], 'M d, Y'); ?></p>
                <?php endif; ?>
                <p><strong>Report Date:</strong> <?php echo date('M d, Y'); ?></p>
            </div>
        </div>

        <!-- Grades Table -->
        <table class="report-table">
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Total Marks</th>
                    <th>Marks Obtained</th>
                    <th>Grade Letter</th>
                    <th>Grade Point</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $grade): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($grade['subject_code']); ?></td>
                        <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                        <td><?php echo $exam['total_marks']; ?></td>
                        <td><?php echo $grade['marks_obtained']; ?></td>
                        <td style="text-align: center; font-weight: bold;"><?php echo $grade['grade_letter']; ?></td>
                        <td style="text-align: center;"><?php echo number_format($grade['grade_point'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background: #e5e7eb; font-weight: bold;">
                    <td colspan="2">TOTAL</td>
                    <td><?php echo $totalMarks; ?></td>
                    <td><?php echo $obtainedMarks; ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="grade-summary">
            <div class="grade-box">
                <h3 style="margin: 0 0 0.5rem 0;">Percentage</h3>
                <p style="font-size: 2rem; margin: 0; font-weight: bold;"><?php echo $percentage; ?>%</p>
            </div>
            <div class="grade-box">
                <h3 style="margin: 0 0 0.5rem 0;">GPA</h3>
                <p style="font-size: 2rem; margin: 0; font-weight: bold;"><?php echo $gpa; ?></p>
            </div>
            <div class="grade-box">
                <h3 style="margin: 0 0 0.5rem 0;">Overall Grade</h3>
                <p style="font-size: 2rem; margin: 0; font-weight: bold;"><?php echo $overallGrade; ?></p>
            </div>
            <div class="grade-box">
                <h3 style="margin: 0 0 0.5rem 0;">Class Rank</h3>
                <p style="font-size: 2rem; margin: 0; font-weight: bold;"><?php echo $classRank; ?></p>
            </div>
        </div>

        <!-- Grade Scale -->
        <div style="margin-top: 2rem; padding: 1rem; background: #f9fafb; border: 1px solid #d1d5db;">
            <h3 style="margin: 0 0 0.5rem 0;">Grading Scale</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; font-size: 0.875rem;">
                <div><strong>A+ (3.7-4.0):</strong> Excellent</div>
                <div><strong>A (3.3-3.6):</strong> Very Good</div>
                <div><strong>B+ (3.0-3.2):</strong> Good</div>
                <div><strong>B (2.7-2.9):</strong> Above Average</div>
                <div><strong>C+ (2.3-2.6):</strong> Average</div>
                <div><strong>C (2.0-2.2):</strong> Satisfactory</div>
                <div><strong>D (1.0-1.9):</strong> Pass</div>
                <div><strong>F (&lt;1.0):</strong> Fail</div>
            </div>
        </div>

        <!-- Remarks -->
        <div style="margin-top: 2rem; padding: 1rem; border: 1px solid #000;">
            <h3 style="margin: 0 0 0.5rem 0;">Teacher's Remarks</h3>
            <p style="min-height: 3rem;"></p>
        </div>

        <!-- Signatures -->
        <div style="margin-top: 3rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem;">
            <div style="text-align: center;">
                <div style="border-top: 2px solid #000; margin-top: 3rem; padding-top: 0.5rem;">
                    <strong>Class Teacher</strong>
                </div>
            </div>
            <div style="text-align: center;">
                <div style="border-top: 2px solid #000; margin-top: 3rem; padding-top: 0.5rem;">
                    <strong>Principal</strong>
                </div>
            </div>
            <div style="text-align: center;">
                <div style="border-top: 2px solid #000; margin-top: 3rem; padding-top: 0.5rem;">
                    <strong>Parent's Signature</strong>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="margin-top: 2rem; text-align: center; font-size: 0.875rem; color: #6b7280;">
            <p>Generated on <?php echo date('F d, Y \a\t h:i A'); ?></p>
        </div>
    </div>
</main>

<?php if (!$print): ?>
    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>
