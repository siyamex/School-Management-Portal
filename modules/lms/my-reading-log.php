<?php
/**
 * My Reading Log - Student Portal
 * Students can track books they're reading and update progress
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "My Reading Log - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['student']);

$errors = [];
$success = '';
$currentUserId = getCurrentUserId();
$studentId = getValue("SELECT id FROM students WHERE user_id = ?", [$currentUserId]);

if (!$studentId) {
    die("Student profile not found");
}

// Handle add/update book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['add_book'])) {
        $bookTitle = sanitize($_POST['book_title']);
        $author = sanitize($_POST['author']);
        $totalPages = (int)$_POST['total_pages'];
        $startDate = sanitize($_POST['start_date']);
        
        if (empty($bookTitle) || $totalPages <= 0) {
            $errors[] = 'Book title and total pages are required';
        } else {
            try {
                insert("INSERT INTO reading_logs (student_id, book_title, author, total_pages, pages_read, start_date, status) 
                       VALUES (?, ?, ?, ?, 0, ?, 'reading')",
                      [$studentId, $bookTitle, $author, $totalPages, $startDate]);
                setFlash('success', 'Book added to your reading log!');
                redirect('my-reading-log.php');
            } catch (Exception $e) {
                $errors[] = 'Error adding book: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['update_progress'])) {
        $logId = (int)$_POST['log_id'];
        $pagesRead = (int)$_POST['pages_read'];
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Get total pages
        $log = getRow("SELECT total_pages FROM reading_logs WHERE id = ? AND student_id = ?", [$logId, $studentId]);
        
        if ($log) {
            $status = ($pagesRead >= $log['total_pages']) ? 'completed' : 'reading';
            $completionDate = ($status === 'completed') ? date('Y-m-d') : null;
            
            query("UPDATE reading_logs SET pages_read = ?, status = ?, completion_date = ?, notes = ? WHERE id = ? AND student_id = ?",
                 [$pagesRead, $status, $completionDate, $notes, $logId, $studentId]);
            
            setFlash('success', 'Reading progress updated!');
            redirect('my-reading-log.php');
        }
    }
}

// Get student's reading logs
$readingLogs = getAll("
    SELECT * FROM reading_logs 
    WHERE student_id = ? 
    ORDER BY 
        CASE WHEN status = 'reading' THEN 0 ELSE 1 END,
        created_at DESC
", [$studentId]);

// Get student's earned badges
$earnedBadges = getAll("
    SELECT srb.*, rb.badge_name, rb.badge_description, rb.badge_icon,
           t.user_id, u.full_name as awarded_by_name
    FROM student_reading_badges srb
    JOIN reading_badges rb ON srb.badge_id = rb.id
    LEFT JOIN teachers t ON srb.awarded_by = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE srb.student_id = ?
    ORDER BY srb.awarded_date DESC
", [$studentId]);

// Calculate statistics
$stats = getRow("
    SELECT 
        COUNT(*) as total_books,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_books,
        SUM(CASE WHEN status = 'reading' THEN 1 ELSE 0 END) as reading_now,
        SUM(pages_read) as total_pages_read
    FROM reading_logs
    WHERE student_id = ?
", [$studentId]);
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">📚 My Reading Log</h1>
        <p class="text-base-content/60 mt-1">Track your reading journey</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Total Books</div>
            <div class="stat-value text-primary"><?php echo $stats['total_books']; ?></div>
            <div class="stat-desc">In your library</div>
        </div>
        <div class="stat">
            <div class="stat-title">Completed</div>
            <div class="stat-value text-success"><?php echo $stats['completed_books']; ?></div>
            <div class="stat-desc">Books finished</div>
        </div>
        <div class="stat">
            <div class="stat-title">Currently Reading</div>
            <div class="stat-value text-warning"><?php echo $stats['reading_now']; ?></div>
            <div class="stat-desc">In progress</div>
        </div>
        <div class="stat">
            <div class="stat-title">Pages Read</div>
            <div class="stat-value"><?php echo number_format($stats['total_pages_read'] ?? 0); ?></div>
            <div class="stat-desc">Total pages</div>
        </div>
    </div>

    <!-- Earned Badges -->
    <?php if (!empty($earnedBadges)): ?>
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title">🏆 My Reading Badges</h2>
                <div class="flex gap-4 flex-wrap">
                    <?php foreach ($earnedBadges as $badge): ?>
                        <div class="tooltip" data-tip="<?php echo htmlspecialchars($badge['badge_description']); ?>&#10;Awarded: <?php echo formatDate($badge['awarded_date'], 'M d, Y'); ?>">
                            <div class="card bg-base-200 w-24 hover:shadow-lg transition-shadow">
                                <div class="card-body p-3 items-center text-center">
                                    <?php if ($badge['badge_icon']): ?>
                                        <img src="<?php echo APP_URL . '/' . $badge['badge_icon']; ?>" alt="<?php echo htmlspecialchars($badge['badge_name']); ?>" class="w-12 h-12">
                                    <?php else: ?>
                                        <div class="text-4xl">🏅</div>
                                    <?php endif; ?>
                                    <p class="text-xs font-semibold mt-1"><?php echo htmlspecialchars($badge['badge_name']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold">Reading List</h2>
        <button onclick="add_modal.showModal()" class="btn btn-primary">+ Add Book</button>
    </div>

    <!-- Reading Log -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($readingLogs)): ?>
            <div class="col-span-full alert alert-info">
                <span>Start tracking your reading! Add your first book to begin.</span>
            </div>
        <?php else: ?>
            <?php foreach ($readingLogs as $log): 
                $progress = $log['total_pages'] > 0 ? round(($log['pages_read'] / $log['total_pages']) * 100) : 0;
                $statusClass = $log['status'] === 'completed' ? 'badge-success' : ($log['status'] === 'verified' ? 'badge-info' : 'badge-warning');
            ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title text-base"><?php echo htmlspecialchars($log['book_title']); ?></h3>
                        <p class="text-sm text-base-content/60">by <?php echo htmlspecialchars($log['author'] ?: 'Unknown'); ?></p>
                        
                        <div class="my-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span>Progress</span>
                                <span><?php echo $progress; ?>%</span>
                            </div>
                            <progress class="progress progress-primary" value="<?php echo $progress; ?>" max="100"></progress>
                            <p class="text-xs text-base-content/60 mt-1">
                                <?php echo $log['pages_read']; ?> / <?php echo $log['total_pages']; ?> pages
                            </p>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($log['status']); ?></span>
                            <?php if ($log['status'] !== 'completed' && $log['status'] !== 'verified'): ?>
                                <button onclick="updateProgress(<?php echo $log['id']; ?>, '<?php echo htmlspecialchars(addslashes($log['book_title'])); ?>', <?php echo $log['total_pages']; ?>, <?php echo $log['pages_read']; ?>)" 
                                        class="btn btn-xs btn-ghost">Update</button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($log['notes']): ?>
                            <div class="alert alert-info text-xs mt-2">
                                <p><?php echo nl2br(htmlspecialchars($log['notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-xs text-base-content/60 mt-2">
                            Started: <?php echo formatDate($log['start_date'], 'M d, Y'); ?>
                            <?php if ($log['completion_date']): ?>
                                <br>Completed: <?php echo formatDate($log['completion_date'], 'M d, Y'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Add Book Modal -->
<dialog id="add_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Add Book to Reading Log</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Book Title *</span></label>
                <input type="text" name="book_title" class="input input-bordered" required>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Author</span></label>
                <input type="text" name="author" class="input input-bordered">
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Total Pages *</span></label>
                    <input type="number" name="total_pages" min="1" class="input input-bordered" required>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Start Date</span></label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" class="input input-bordered">
                </div>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="add_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Update Progress Modal -->
<dialog id="update_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Update Reading Progress</h3>
        <p id="update_book_title" class="text-sm text-base-content/60 mb-4"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="log_id" id="update_log_id">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Pages Read</span></label>
                <input type="number" name="pages_read" id="update_pages_read" min="0" class="input input-bordered" required>
                <label class="label">
                    <span class="label-text-alt">Out of <span id="update_total_pages"></span> pages</span>
                </label>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Notes (Optional)</span></label>
                <textarea name="notes" class="textarea textarea-bordered" rows="2" placeholder="Your thoughts about the book..."></textarea>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="update_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="update_progress" class="btn btn-primary">Update Progress</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function updateProgress(logId, bookTitle, totalPages, currentPages) {
    document.getElementById('update_log_id').value = logId;
    document.getElementById('update_book_title').textContent = bookTitle;
    document.getElementById('update_pages_read').value = currentPages;
    document.getElementById('update_pages_read').max = totalPages;
    document.getElementById('update_total_pages').textContent = totalPages;
    update_modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
