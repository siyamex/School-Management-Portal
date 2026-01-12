<?php
/**
 * Lesson Planner
 * Teachers plan and organize their lessons
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Lesson Planner - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'admin', 'principal']);

$currentUserId = getCurrentUserId();

// Handle create/update
if (isset($_POST['save_lesson']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $title = sanitize($_POST['title']);
    $subjectId = (int)$_POST['subject_id'];
    $sectionId = (int)$_POST['section_id'];
    $lessonDate = sanitize($_POST['lesson_date']);
    $objectives = sanitize($_POST['objectives']);
    $activities = sanitize($_POST['activities']);
    $resources = sanitize($_POST['resources']);
    $homework = sanitize($_POST['homework']);
    
    try {
        insert("INSERT INTO lesson_plans (teacher_id, subject_id, section_id, lesson_date, title, objectives, activities, resources, homework, created_at) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
              [$currentUserId, $subjectId, $sectionId, $lessonDate, $title, $objectives, $activities, $resources, $homework]);
        setFlash('success', 'Lesson plan saved');
        redirect('lesson-planner.php');
    } catch (Exception $e) {
        $errors[] = 'Failed to save: ' . $e->getMessage();
    }
}

// Get lesson plans
$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
$weekEnd = date('Y-m-d', strtotime('friday this week', strtotime($date)));

$lessons = getAll("
    SELECT lp.*, sub.subject_name, sub.subject_code, 
           c.class_name, s.section_name
    FROM lesson_plans lp
    JOIN subjects sub ON lp.subject_id = sub.id
    JOIN sections s ON lp.section_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE lp.teacher_id = ?
      AND lp.lesson_date BETWEEN ? AND ?
    ORDER BY lp.lesson_date, s.id
", [$currentUserId, $weekStart, $weekEnd]);

// Get teacher's subjects and sections
$teacherSubjects = getAll("
    SELECT DISTINCT ts.subject_id, ts.section_id, sub.subject_name, c.class_name, s.section_name
    FROM teacher_subjects ts
    JOIN subjects sub ON ts.subject_id = sub.id
    JOIN sections s ON ts.section_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE ts.teacher_id = (SELECT id FROM teachers WHERE user_id = ?)
", [$currentUserId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">📚 Lesson Planner</h1>
            <p class="text-base-content/60 mt-1">Plan and organize your lessons</p>
        </div>
        <button onclick="plan_modal.showModal()" class="btn btn-primary">+ New Lesson Plan</button>
    </div>

    <!-- Week Navigator -->
    <div class="flex justify-between items-center mb-6">
        <a href="?date=<?php echo date('Y-m-d', strtotime('-1 week', strtotime($date))); ?>" class="btn btn-sm btn-ghost">← Previous Week</a>
        <div class="text-center">
            <p class="font-semibold">Week of <?php echo formatDate($weekStart, 'M d'); ?> - <?php echo formatDate($weekEnd, 'M d, Y'); ?></p>
        </div>
        <a href="?date=<?php echo date('Y-m-d', strtotime('+1 week', strtotime($date))); ?>" class="btn btn-sm btn-ghost">Next Week →</a>
    </div>

    <!-- Lesson Plans -->
    <?php if (empty($lessons)): ?>
        <div class="alert alert-info">
            <span>No lesson plans for this week. Click "New Lesson Plan" to get started!</span>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php 
            $currentDate = '';
            foreach ($lessons as $lesson): 
                if ($currentDate !== $lesson['lesson_date']) {
                    $currentDate = $lesson['lesson_date'];
                    echo '<h3 class="text-lg font-bold mt-6">' . formatDate($currentDate, 'l, M d, Y') . '</h3>';
                }
            ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-bold text-lg"><?php echo htmlspecialchars($lesson['title']); ?></h4>
                                <p class="text-sm text-base-content/60">
                                    <?php echo htmlspecialchars($lesson['subject_name'] . ' - ' . $lesson['class_name'] . ' ' . $lesson['section_name']); ?>
                                </p>
                            </div>
                            <span class="badge badge-primary"><?php echo $lesson['subject_code']; ?></span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <?php if ($lesson['objectives']): ?>
                                <div>
                                    <p class="font-semibold text-sm">Objectives:</p>
                                    <p class="text-sm text-base-content/60"><?php echo nl2br(htmlspecialchars($lesson['objectives'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($lesson['activities']): ?>
                                <div>
                                    <p class="font-semibold text-sm">Activities:</p>
                                    <p class="text-sm text-base-content/60"><?php echo nl2br(htmlspecialchars($lesson['activities'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($lesson['resources']): ?>
                                <div>
                                    <p class="font-semibold text-sm">Resources:</p>
                                    <p class="text-sm text-base-content/60"><?php echo nl2br(htmlspecialchars($lesson['resources'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($lesson['homework']): ?>
                                <div>
                                    <p class="font-semibold text-sm">Homework:</p>
                                    <p class="text-sm text-base-content/60"><?php echo nl2br(htmlspecialchars($lesson['homework'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- New Lesson Plan Modal -->
<dialog id="plan_modal" class="modal">
    <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg mb-4">New Lesson Plan</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Subject</span></label>
                    <select name="subject_id" id="subject_select" class="select select-bordered select-sm" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($teacherSubjects as $ts): ?>
                            <option value="<?php echo $ts['subject_id']; ?>" data-section="<?php echo $ts['section_id']; ?>">
                                <?php echo htmlspecialchars($ts['subject_name'] . ' - ' . $ts['class_name'] . ' ' . $ts['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Date</span></label>
                    <input type="date" name="lesson_date" class="input input-bordered input-sm" required />
                </div>
            </div>
            
            <input type="hidden" name="section_id" id="section_input">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Lesson Title</span></label>
                <input type="text" name="title" class="input input-bordered input-sm" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Learning Objectives</span></label>
                <textarea name="objectives" class="textarea textarea-bordered textarea-sm" rows="2"></textarea>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Activities</span></label>
                <textarea name="activities" class="textarea textarea-bordered textarea-sm" rows="2"></textarea>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Resources Needed</span></label>
                <textarea name="resources" class="textarea textarea-bordered textarea-sm" rows="2"></textarea>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Homework</span></label>
                <textarea name="homework" class="textarea textarea-bordered textarea-sm" rows="2"></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="plan_modal.close()" class="btn btn-ghost btn-sm">Cancel</button>
                <button type="submit" name="save_lesson" class="btn btn-primary btn-sm">Save Lesson Plan</button>
            </div>
        </form>
    </div>
</dialog>

<script>
document.getElementById('subject_select').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const sectionId = selectedOption.getAttribute('data-section');
    document.getElementById('section_input').value = sectionId;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
