<?php
/**
 * Digital Resources Library
 * File repository for educational resources
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Resources Library - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);
$canUpload = in_array('admin', $userRoles) || in_array('teacher', $userRoles);

// Handle upload
if (isset($_POST['upload']) && $canUpload && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filePath = uploadFile($_FILES['file'], 'uploads/resources/');
        if ($filePath) {
            $resourceType = sanitize($_POST['resource_type']);
            insert("INSERT INTO resources (title, description, resource_type, file_path, uploaded_by) 
                   VALUES (?, ?, ?, ?, ?)",
                  [$title, $description, $resourceType, $filePath, getCurrentUserId()]);
            setFlash('success', 'Resource uploaded successfully');
            redirect('resources-library.php');
        }
    }
}

// Get resources
$resourceType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$where = $resourceType ? "WHERE r.resource_type = ?" : "WHERE 1=1";
$params = $resourceType ? [$resourceType] : [];

$resources = getAll("
    SELECT r.*, u.full_name as uploader_name
    FROM resources r
    JOIN users u ON r.uploaded_by = u.id
    $where
    ORDER BY r.created_at DESC
", $params);

$resourceTypes = ['document', 'video', 'link', 'image', 'other'];
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Resources Library</h1>
            <p class="text-base-content/60 mt-1">Educational materials and documents</p>
        </div>
        <?php if ($canUpload): ?>
            <button onclick="upload_modal.showModal()" class="btn btn-primary">+ Upload Resource</button>
        <?php endif; ?>
    </div>

    <!-- Type Filter -->
    <div class="flex gap-2 mb-6 flex-wrap">
        <a href="resources-library.php" class="btn btn-sm <?php echo !$resourceType ? 'btn-primary' : 'btn-ghost'; ?>">All</a>
        <?php foreach ($resourceTypes as $type): ?>
            <a href="?type=<?php echo urlencode($type); ?>" 
               class="btn btn-sm <?php echo $resourceType === $type ? 'btn-primary' : 'btn-ghost'; ?>">
                <?php echo ucfirst($type); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Resources Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($resources)): ?>
            <div class="col-span-full alert alert-info">
                <span>No resources found.</span>
            </div>
        <?php else: ?>
            <?php foreach ($resources as $resource): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h3 class="card-title text-base"><?php echo htmlspecialchars($resource['title']); ?></h3>
                        <p class="text-sm text-base-content/60"><?php echo htmlspecialchars($resource['description']); ?></p>
                        
                        <div class="flex gap-2 mt-2">
                            <span class="badge badge-sm"><?php echo ucfirst($resource['resource_type'] ?? 'other'); ?></span>
                        </div>
                        
                        <p class="text-xs text-base-content/60 mt-2">
                            Uploaded by <?php echo htmlspecialchars($resource['uploader_name']); ?><br>
                            <?php echo formatDate($resource['created_at'], 'M d, Y'); ?>
                        </p>
                        
                        <div class="card-actions justify-end mt-4">
                            <a href="<?php echo APP_URL . '/' . $resource['file_path']; ?>" 
                               download class="btn btn-sm btn-primary">Download</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Upload Modal -->
<?php if ($canUpload): ?>
<dialog id="upload_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Upload Resource</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Title</span></label>
                <input type="text" name="title" class="input input-bordered" required />
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Description</span></label>
                <textarea name="description" class="textarea textarea-bordered" rows="2"></textarea>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Resource Type</span></label>
                <select name="resource_type" class="select select-bordered" required>
                    <?php foreach ($resourceTypes as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">File</span></label>
                <input type="file" name="file" class="file-input file-input-bordered" required />
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="upload_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="upload" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</dialog>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
