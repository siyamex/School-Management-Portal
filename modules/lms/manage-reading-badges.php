<?php
/**
 * Manage Reading Badges - Teacher Portal
 * Teachers can create, edit, and upload badge icons
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Manage Reading Badges - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'admin', 'principal']);

$errors = [];
$success = '';
$currentUserId = getCurrentUserId();
$teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [$currentUserId]);

// Handle badge creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['create_badge'])) {
        $badgeName = sanitize($_POST['badge_name']);
        $description = sanitize($_POST['description']);
        $pagesThreshold = (int)($_POST['pages_threshold'] ?? 0);
        $booksThreshold = (int)($_POST['books_threshold'] ?? 0);
        
        if (empty($badgeName)) {
            $errors[] = 'Badge name is required';
        } else {
            $badgeIcon = null;
            
            // Handle icon upload
            if (isset($_FILES['badge_icon']) && $_FILES['badge_icon']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/png', 'image/svg+xml', 'image/jpeg'];
                $fileType = $_FILES['badge_icon']['type'];
                
                if (in_array($fileType, $allowed)) {
                    $ext = pathinfo($_FILES['badge_icon']['name'], PATHINFO_EXTENSION);
                    $filename = 'badge_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $uploadPath = __DIR__ . '/../../uploads/badges/';
                    
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    if (move_uploaded_file($_FILES['badge_icon']['tmp_name'], $uploadPath . $filename)) {
                        $badgeIcon = 'uploads/badges/' . $filename;
                    }
                } else {
                    $errors[] = 'Only PNG, SVG, and JPEG files are allowed';
                }
            }
            
            if (empty($errors)) {
                try {
                    insert("INSERT INTO reading_badges (badge_name, badge_description, badge_icon, pages_threshold, books_threshold, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?)",
                          [$badgeName, $description, $badgeIcon, $pagesThreshold, $booksThreshold, $teacherId]);
                    setFlash('success', 'Badge created successfully!');
                    redirect('manage-reading-badges.php');
                } catch (Exception $e) {
                    $errors[] = 'Error creating badge: ' . $e->getMessage();
                }
            }
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $badgeId = (int)$_POST['badge_id'];
        $isActive = (int)$_POST['is_active'];
        
        query("UPDATE reading_badges SET is_active = ? WHERE id = ?", [!$isActive, $badgeId]);
        setFlash('success', 'Badge status updated');
        redirect('manage-reading-badges.php');
    }
}

// Get all badges
$badges = getAll("
    SELECT rb.*, t.user_id, u.full_name as created_by_name,
           (SELECT COUNT(*) FROM student_reading_badges WHERE badge_id = rb.id) as award_count
    FROM reading_badges rb
    LEFT JOIN teachers t ON rb.created_by = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY rb.created_at DESC
");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">🏅 Manage Reading Badges</h1>
            <p class="text-base-content/60 mt-1">Create and manage reading achievement badges</p>
        </div>
        <button onclick="create_modal.showModal()" class="btn btn-primary">+ Create Badge</button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Badges Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($badges)): ?>
            <div class="col-span-full alert alert-info">
                <span>No badges created yet. Create your first reading badge!</span>
            </div>
        <?php else: ?>
            <?php foreach ($badges as $badge): ?>
                <div class="card bg-base-100 shadow-xl <?php echo !$badge['is_active'] ? 'opacity-60' : ''; ?>">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <h3 class="card-title text-base"><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                            <?php if (!$badge['is_active']): ?>
                                <span class="badge badge-ghost badge-sm">Inactive</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex justify-center my-4">
                            <?php if ($badge['badge_icon']): ?>
                                <img src="<?php echo APP_URL . '/' . $badge['badge_icon']; ?>" 
                                     alt="<?php echo htmlspecialchars($badge['badge_name']); ?>" 
                                     class="w-20 h-20 object-contain">
                            <?php else: ?>
                                <div class="text-6xl">🏅</div>
                            <?php endif; ?>
                        </div>
                        
                        <p class="text-sm text-base-content/60"><?php echo nl2br(htmlspecialchars($badge['badge_description'])); ?></p>
                        
                        <div class="divider my-2"></div>
                        
                        <div class="text-xs space-y-1">
                            <?php if ($badge['pages_threshold'] > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-base-content/60">Pages Requirement:</span>
                                    <span class="font-semibold"><?php echo number_format($badge['pages_threshold']); ?> pages</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($badge['books_threshold'] > 0): ?>
                                <div class="flex justify-between">
                                    <span class="text-base-content/60">Books Requirement:</span>
                                    <span class="font-semibold"><?php echo $badge['books_threshold']; ?> books</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between">
                                <span class="text-base-content/60">Times Awarded:</span>
                                <span class="font-semibold"><?php echo $badge['award_count']; ?></span>
                            </div>
                        </div>
                        
                        <div class="card-actions justify-between mt-4">
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                <input type="hidden" name="is_active" value="<?php echo $badge['is_active']; ?>">
                                <button type="submit" name="toggle_status" class="btn btn-xs btn-ghost">
                                    <?php echo $badge['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <a href="award-reading-badges.php?badge_id=<?php echo $badge['id']; ?>" class="btn btn-xs btn-primary">Award</a>
                        </div>
                        
                        <?php if ($badge['created_by_name']): ?>
                            <p class="text-xs text-base-content/60 mt-2">
                                Created by <?php echo htmlspecialchars($badge['created_by_name']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Create Badge Modal -->
<dialog id="create_modal" class="modal">
    <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg mb-4">Create Reading Badge</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Badge Name *</span></label>
                    <input type="text" name="badge_name" class="input input-bordered" required>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Badge Icon (PNG/SVG)</span></label>
                    <input type="file" name="badge_icon" accept=".png,.svg,.jpg,.jpeg" class="file-input file-input-bordered file-input-sm">
                    <label class="label">
                        <span class="label-text-alt">Recommended size: 512x512 px</span>
                    </label>
                </div>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Description</span></label>
                <textarea name="description" class="textarea textarea-bordered" rows="2" placeholder="What this badge represents..."></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Minimum Pages</span></label>
                    <input type="number" name="pages_threshold" value="0" min="0" class="input input-bordered input-sm">
                    <label class="label">
                        <span class="label-text-alt">0 = No requirement</span>
                    </label>
                </div>
                
                <div class="form-control">
                    <label class="label"><span class="label-text">Minimum Books</span></label>
                    <input type="number" name="books_threshold" value="0" min="0" class="input input-bordered input-sm">
                    <label class="label">
                        <span class="label-text-alt">0 = No requirement</span>
                    </label>
                </div>
            </div>
            
            <div class="alert alert-info text-sm mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Upload a custom icon in PNG or SVG format. If no icon is uploaded, a default emoji will be used.</span>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="create_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="create_badge" class="btn btn-primary">Create Badge</button>
            </div>
        </form>
    </div>
</dialog>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
