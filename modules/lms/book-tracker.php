<?php
/**
 * Book Reading Tracker
 * Track student reading progress and library books
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Book Tracker - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'teacher', 'student']);

$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);
$isStudent = in_array('student', $userRoles);
$currentUserId = getCurrentUserId();

if ($isStudent) {
    $studentId = getValue("SELECT id FROM students WHERE user_id = ?", [$currentUserId]);
    $books = getAll("
        SELECT br.*, b.title, b.author, b.isbn
        FROM book_reading br
        JOIN books b ON br.book_id = b.id
        WHERE br.student_id = ?
        ORDER BY br.started_at DESC
    ", [$studentId]);
} else {
    $books = getAll("
        SELECT br.*, b.title, b.author, st.student_id, u.full_name
        FROM book_reading br
        JOIN books b ON br.book_id = b.id
        JOIN students st ON br.student_id = st.id
        JOIN users u ON st.user_id = u.id
        ORDER BY br.started_at DESC
        LIMIT 100
    ");
}

// Get available books
$availableBooks = getAll("SELECT * FROM books ORDER BY title");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Book Reading Tracker</h1>
            <p class="text-base-content/60 mt-1">Track reading progress</p>
        </div>
        <?php if ($isStudent): ?>
            <button onclick="add_book_modal.showModal()" class="btn btn-primary">+ Start Reading</button>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($books)): ?>
            <div class="col-span-full">
                <div class="alert alert-info">
                    <span><?php echo $isStudent ? 'Start tracking your reading progress!' : 'No reading records found.'; ?></span>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($books as $book): 
                $statusBadge = $book['status'] === 'completed' ? 'badge-success' : 'badge-warning';
                $progress = $book['pages_read'] > 0 && $book['total_pages'] > 0 
                    ? round(($book['pages_read'] / $book['total_pages']) * 100) 
                    : 0;
            ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title text-base"><?php echo htmlspecialchars($book['title']); ?></h3>
                        <p class="text-sm text-base-content/60">by <?php echo htmlspecialchars($book['author']); ?></p>
                        
                        <?php if (!$isStudent): ?>
                            <p class="text-xs"><strong>Student:</strong> <?php echo htmlspecialchars($book['full_name']); ?></p>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span>Progress</span>
                                <span><?php echo $progress; ?>%</span>
                            </div>
                            <progress class="progress progress-primary" value="<?php echo $progress; ?>" max="100"></progress>
                            <p class="text-xs text-base-content/60 mt-1">
                                <?php echo $book['pages_read']; ?> / <?php echo $book['total_pages']; ?> pages
                            </p>
                        </div>
                        
                        <div class="card-actions justify-between items-center mt-4">
                            <span class="badge <?php echo $statusBadge; ?>"><?php echo ucfirst($book['status']); ?></span>
                            <span class="text-xs text-base-content/60">
                                <?php echo formatDate($book['started_at'], 'M d'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Add Book Modal -->
<dialog id="add_book_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Start Reading a Book</h3>
        <form method="POST" action="track-book.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Select Book</span></label>
                <select name="book_id" class="select select-bordered" required>
                    <option value="">-- Choose a book --</option>
                    <?php foreach ($availableBooks as $book): ?>
                        <option value="<?php echo $book['id']; ?>">
                            <?php echo htmlspecialchars($book['title'] . ' - ' . $book['author']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="add_book_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Reading</button>
            </div>
        </form>
    </div>
</dialog>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
