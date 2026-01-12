<?php
/**
 * View Grades - Student Module
 * Students can view their grades by semester
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "My Grades - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole('student');

// Get student ID
$studentRow = getRow("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
if (!$studentRow) {
    die("Student profile not found.");
}
$studentId = $studentRow['id'];

// Get enrollment
$enrollment = getCurrentEnrollment($studentId);

// Get all grades grouped by exam
$grades = getAll("
    SELECT g.*, s.subject_name, e.exam_name, e.exam_type, sem.semester_name, ay.year_name
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    JOIN exams e ON g.exam_id = e.id
    JOIN semesters sem ON e.semester_id = sem.id
    JOIN academic_years ay ON e.academic_year_id = ay.id
    WHERE g.student_id = ? AND g.is_published = 1
    ORDER BY ay.start_date DESC, sem.start_date DESC, e.created_at DESC
", [$studentId]);

// Group grades by exam
$groupedGrades = [];
foreach ($grades as $grade) {
    $key = $grade['exam_id'];
    if (!isset($groupedGrades[$key])) {
        $groupedGrades[$key] = [
            'exam_info' => $grade,
            'subjects' => []
        ];
    }
    $groupedGrades[$key]['subjects'][] = $grade;
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">My Grades</h1>
            <p class="text-base-content/60 mt-1">View your academic performance</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Print
        </button>
    </div>

    <?php if (empty($groupedGrades)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No grades have been published yet.</span>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($groupedGrades as $examId => $examData): 
                $examInfo = $examData['exam_info'];
                $subjects = $examData['subjects'];
                
                // Calculate overall stats
                $totalMarks = array_sum(array_column($subjects, 'marks_obtained'));
                $totalPossible = count($subjects) * 100; // Assuming 100 marks per subject
                $percentage = ($totalPossible > 0) ? round(($totalMarks / $totalPossible) * 100, 2) : 0;
                $avgGPA = array_sum(array_column($subjects, 'grade_point')) / count($subjects);
            ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h2 class="card-title text-2xl"><?php echo htmlspecialchars($examInfo['exam_name']); ?></h2>
                                <div class="flex gap-2 mt-2">
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($examInfo['year_name']); ?></span>
                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($examInfo['semester_name']); ?></span>
                                    <span class="badge badge-accent"><?php echo ucfirst($examInfo['exam_type']); ?></span>
                                </div>
                            </div>
                            <div class="stats shadow">
                                <div class="stat place-items-center">
                                    <div class="stat-title">Overall</div>
                                    <div class="stat-value text-primary"><?php echo number_format($percentage, 1); ?>%</div>
                                    <div class="stat-desc">GPA: <?php echo number_format($avgGPA, 2); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th class="text-right">Marks Obtained</th>
                                        <th class="text-right">Total Marks</th>
                                        <th class="text-right">Percentage</th>
                                        <th class="text-center">Grade</th>
                                        <th class="text-right">GPA</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $grade): 
                                        $subjectPercent = ($grade['marks_obtained'] / 100) * 100;
                                    ?>
                                        <tr>
                                            <td class="font-semibold"><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                            <td class="text-right"><?php echo $grade['marks_obtained']; ?></td>
                                            <td class="text-right">100</td>
                                            <td class="text-right"><?php echo number_format($subjectPercent, 1); ?>%</td>
                                            <td class="text-center">
                                                <span class="badge badge-lg 
                                                    <?php 
                                                    if ($grade['grade_letter'] === 'A+' || $grade['grade_letter'] === 'A') echo 'badge-success';
                                                    elseif ($grade['grade_letter'] === 'B+' || $grade['grade_letter'] === 'B') echo 'badge-info';
                                                    elseif ($grade['grade_letter'] === 'C+' || $grade['grade_letter'] === 'C') echo 'badge-warning';
                                                    else echo 'badge-error';
                                                    ?>">
                                                    <?php echo $grade['grade_letter']; ?>
                                                </span>
                                            </td>
                                            <td class="text-right font-semibold"><?php echo number_format($grade['grade_point'], 2); ?></td>
                                            <td class="text-sm text-base-content/60"><?php echo htmlspecialchars($grade['remarks'] ?? '-'); ?></td>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
