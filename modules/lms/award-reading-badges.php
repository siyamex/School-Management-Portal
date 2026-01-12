<?php
/**
 * Award Reading Badges - Teacher Portal
 * Teachers can award badges to students based on their reading progress
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Award Reading Badges - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'admin', 'principal']);

$errors = [];
$success = '';
$currentUserId = getCurrentUserId();
$teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);

// Handle badge award
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $studentId = (int)$_POST['student_id'];
    $badgeId = (int)$_POST['badge_id'];
    $reason = sanitize($_POST['reason'] ?? '');
    $awardedDate = sanitize($_POST['awarded_date'] ?? date('Y-m-d'));
    
    if (!$studentId || !$badgeId) {
        $errors[] = 'Student and badge selection required';
    } else {
        try {
            // Check if student already has this badge
            $existing = getValue("SELECT id FROM student_reading_badges WHERE student_id = ? AND badge_id = ?", 
                               [$studentId, $badgeId]);
            
            if ($existing) {
                $errors[] = 'This student already has this badge';
            } else {
                insert("INSERT INTO student_reading_badges (student_id, badge_id, awarded_by, awarded_date, reason) 
                       VALUES (?, ?, ?, ?, ?)",
                      [$studentId, $badgeId, $teacherId, $awardedDate, $reason]);
                setFlash('success', 'Badge awarded successfully!');
                redirect('award-reading-badges.php' . (isset($_GET['badge_id']) ? '?badge_id=' . $_GET['badge_id'] : ''));
            }
        } catch (Exception $e) {
            $errors[] = 'Error awarding badge: ' . $e->getMessage();
        }
    }
}

// Get selected badge if specified
$selectedBadgeId = isset($_GET['badge_id']) ? (int)$_GET['badge_id'] : 0;

// Get all active badges
$badges = getAll("SELECT * FROM reading_badges WHERE is_active = 1 ORDER BY badge_name");

// Get students with their reading statistics
$students = getAll("
    SELECT s.id, u.full_name, u.email,
           COUNT(rl.id) as total_books_logged,
           SUM(CASE WHEN rl.status = 'completed' THEN 1 ELSE 0 END) as books_completed,
           SUM(rl.pages_read) as total_pages_read,
           (SELECT GROUP_CONCAT(rb.badge_name SEPARATOR ', ') 
            FROM student_reading_badges srb 
            JOIN reading_badges rb ON srb.badge_id = rb.id 
            WHERE srb.student_id = s.id) as earned_badges
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN reading_logs rl ON s.id = rl.student_id
    GROUP BY s.id, u.full_name, u.email
    ORDER BY u.full_name
");

// Get recent awards
$recentAwards = getAll("
    SELECT srb.*, u.full_name as student_name, rb.badge_name, rb.badge_icon,
           t.user_id as teacher_user_id, ut.full_name as teacher_name
    FROM student_reading_badges srb
    JOIN students s ON srb.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN reading_badges rb ON srb.badge_id = rb.id
    LEFT JOIN teachers t ON srb.awarded_by = t.id
    LEFT JOIN users ut ON t.user_id = ut.id
    ORDER BY srb.created_at DESC
    LIMIT 20
");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="manage-reading-badges.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Badges
        </a>
        <h1 class="text-3xl font-bold">🏆 Award Reading Badges</h1>
        <p class="text-base-content/60 mt-1">Recognize students' reading achievements</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Students List -->
        <div class="lg:col-span-2">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Students Reading Statistics</h2>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Books Logged</th>
                                    <th>Completed</th>
                                    <th>Pages Read</th>
                                    <th>Badges</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <div class="font-semibold"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                                <div class="text-xs text-base-content/60"><?php echo htmlspecialchars($student['email']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo $student['total_books_logged']; ?></td>
                                        <td class="font-semibold text-success"><?php echo $student['books_completed']; ?></td>
                                        <td><?php echo number_format($student['total_pages_read'] ?? 0); ?></td>
                                        <td>
                                            <?php if ($student['earned_badges']): ?>
                                                <div class="tooltip" data-tip="<?php echo htmlspecialchars($student['earned_badges']); ?>">
                                                    <span class="badge badge-sm badge-success"><?php echo count(explode(',', $student['earned_badges'])); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-base-content/40">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="awardBadge(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['full_name'])); ?>')" 
                                                    class="btn btn-xs btn-primary">Award</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Awards -->
        <div>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-base">Recent Awards</h2>
                    <div class="space-y-2">
                        <?php if (empty($recentAwards)): ?>
                            <p class="text-sm text-base-content/60">No badges awarded yet</p>
                        <?php else: ?>
                            <?php foreach ($recentAwards as $award): ?>
                                <div class="flex gap-3 items-start p-2 rounded hover:bg-base-200">
                                    <?php if ($award['badge_icon']): ?>
                                        <img src="<?php echo APP_URL . '/' . $award['badge_icon']; ?>" class="w-8 h-8" alt="Badge">
                                    <?php else: ?>
                                        <div class="text-2xl">🏅</div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($award['student_name']); ?></p>
                                        <p class="text-xs text-base-content/60"><?php echo htmlspecialchars($award['badge_name']); ?></p>
                                        <p class="text-xs text-base-content/40"><?php echo formatDate($award['awarded_date'], 'M d'); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Award Badge Modal -->
<dialog id="award_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Award Reading Badge</h3>
        <p id="student_name" class="text-sm text-base-content/60 mb-4"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="student_id" id="award_student_id">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Select Badge *</span></label>
                <select name="badge_id" class="select select-bordered" required>
                    <option value="">-- Choose a badge --</option>
                    <?php foreach ($badges as $badge): ?>
                        <option value="<?php echo $badge['id']; ?>" <?php echo $selectedBadgeId == $badge['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($badge['badge_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Award Date</span></label>
                <input type="date" name="awarded_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered input-sm">
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Reason (Optional)</span></label>
                <textarea name="reason" class="textarea textarea-bordered" rows="2" placeholder="Why this student earned this badge..."></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="award_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Award Badge</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function awardBadge(studentId, studentName) {
    document.getElementById('award_student_id').value = studentId;
    document.getElementById('student_name').textContent = 'Awarding badge to: ' + studentName;
    award_modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
