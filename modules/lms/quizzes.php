<?php
/**
 * Online Quizzes
 * Create and take online assessments
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Quizzes - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);
$isTeacher = in_array('teacher', $userRoles) || in_array('admin', $userRoles);
$isStudent = in_array('student', $userRoles);

if ($isTeacher) {
    // Teacher view: Get teacher ID and show their quizzes
    $teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [getCurrentUserId()]);
    
    $quizzes = getAll("
        SELECT q.*, sub.subject_name, 
               (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count
        FROM quizzes q
        JOIN subjects sub ON q.subject_id = sub.id
        WHERE q.teacher_id = ?
        ORDER BY q.created_at DESC
    ", [$teacherId]);
} else {
    // Student view: Show available quizzes
    $studentId = getValue("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
    $sectionId = getValue("SELECT section_id FROM enrollments WHERE student_id = ? AND status = 'active'", [$studentId]);
    
    $quizzes = getAll("
        SELECT q.*, sub.subject_name,
               qa.score, qa.completed_at
        FROM quizzes q
        JOIN subjects sub ON q.subject_id = sub.id
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = ?
        WHERE q.section_id = ? AND q.status = 'active'
        ORDER BY q.due_date ASC
    ", [$studentId, $sectionId]);
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">📝 Quizzes</h1>
            <p class="text-base-content/60 mt-1"><?php echo $isTeacher ? 'Manage quizzes' : 'Take assessments'; ?></p>
        </div>
        <?php if ($isTeacher): ?>
            <a href="create-quiz.php" class="btn btn-primary">+ Create Quiz</a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($quizzes)): ?>
            <div class="col-span-full alert alert-info">
                <span><?php echo $isTeacher ? 'Create your first quiz!' : 'No quizzes available.'; ?></span>
            </div>
        <?php else: ?>
            <?php foreach ($quizzes as $quiz): 
                $isCompleted = isset($quiz['completed_at']) && $quiz['completed_at'];
                $statusClass = $isCompleted ? 'badge-success' : 'badge-warning';
            ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title text-base"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <p class="text-sm text-base-content/60"><?php echo htmlspecialchars($quiz['subject_name']); ?></p>
                        
                        <div class="flex gap-2 mt-2">
                            <span class="badge badge-sm"><?php echo $quiz['total_marks']; ?> marks</span>
                            <span class="badge badge-sm badge-ghost"><?php echo $quiz['time_limit']; ?> min</span>
                        </div>
                        
                        <?php if ($quiz['due_date']): ?>
                            <p class="text-xs text-base-content/60 mt-2">
                                Due: <?php echo formatDate($quiz['due_date'], 'M d, Y'); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($isStudent): ?>
                            <?php if ($isCompleted): ?>
                                <div class="alert alert-success text-xs mt-4">
                                    <span>✓ Completed - Score: <?php echo $quiz['score']; ?>%</span>
                                </div>
                            <?php else: ?>
                                <div class="card-actions justify-end mt-4">
                                    <a href="take-quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">Start Quiz</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-xs text-base-content/60 mt-2">
                                <?php echo $quiz['attempt_count']; ?> attempts
                            </p>
                            <div class="card-actions justify-end mt-4">
                                <a href="quiz-results.php?id=<?php echo $quiz['id']; ?>" class="btn btn-xs btn-ghost">View Results</a>
                                <a href="edit-quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-xs btn-ghost">Edit</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
