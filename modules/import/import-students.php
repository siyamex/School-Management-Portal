<?php
/**
 * Bulk Student Import
 * Upload CSV file to import multiple students at once
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'principal']);

// Handle template download BEFORE any output
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "full_name,email,phone,date_of_birth,gender,address\n";
    echo "\"John Doe\",john.doe@example.com,1234567890,2010-01-15,male,\"123 Main St\"\n";
    echo "\"Jane Smith\",jane.smith@example.com,0987654321,2010-03-20,female,\"456 Oak Ave\"\n";
    exit; // Stop execution after download
}

$pageTitle = "Import Students - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

$errors = [];
$success = '';
$importPreview = [];

// Handle file upload and import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        if ($handle === false) {
            $errors[] = 'Could not read file';
        } else {
            // Read header
            $header = fgetcsv($handle);
            $expectedColumns = ['full_name', 'email', 'phone', 'date_of_birth', 'gender', 'address'];
            
            if ($header !== $expectedColumns) {
                $errors[] = 'Invalid CSV format. Please use the template.';
            } else {
                $row = 1;
                while (($data = fgetcsv($handle)) !== false) {
                    $row++;
                    
                    // Validate data
                    $rowErrors = [];
                    if (empty($data[0])) $rowErrors[] = 'Name required';
                    if (empty($data[1]) || !filter_var($data[1], FILTER_VALIDATE_EMAIL)) $rowErrors[] = 'Valid email required';
                    if (!in_array($data[4], ['male', 'female', 'other'])) $rowErrors[] = 'Gender must be: male, female, or other';
                    
                    // Check if email exists
                    if (!empty($data[1]) && getRow("SELECT id FROM users WHERE email = ?", [$data[1]])) {
                        $rowErrors[] = 'Email already exists';
                    }
                    
                    $importPreview[] = [
                        'row' => $row,
                        'data' => $data,
                        'errors' => $rowErrors
                    ];
                }
                
                fclose($handle);
                
                // If preview mode, show data
                if (!isset($_POST['confirm_import'])) {
                    // Just preview
                } else {
                    // Actually import
                    $imported = 0;
                    $failed = 0;
                    
                    beginTransaction();
                    try {
                        foreach ($importPreview as $preview) {
                            if (!empty($preview['errors'])) {
                                $failed++;
                                continue;
                            }
                            
                            $data = $preview['data'];
                            
                            // Generate temp password
                            $tempPassword = bin2hex(random_bytes(4)); // 8 characters
                            
                            // Create user
                            $userId = insert("INSERT INTO users (full_name, email, phone, password, created_at) VALUES (?, ?, ?, ?, NOW())",
                                           [$data[0], $data[1], $data[2], password_hash($tempPassword, PASSWORD_BCRYPT)]);
                            
                            // Generate student ID
                            $studentId = 'STU' . date('Y') . str_pad($userId, 4, '0', STR_PAD_LEFT);
                            
                            // Create student record
                            insert("INSERT INTO students (user_id, student_id, date_of_birth, gender, address) VALUES (?, ?, ?, ?, ?)",
                                 [$userId, $studentId, $data[3], $data[4], $data[5]]);
                            
                            // Assign student role
                            $studentRoleId = getValue("SELECT id FROM roles WHERE role_name = 'student'");
                            insert("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $studentRoleId]);
                            
                            $imported++;
                        }
                        
                        commit();
                        $success = "Successfully imported $imported students. Failed: $failed";
                        $importPreview = []; // Clear preview after import
                    } catch (Exception $e) {
                        rollback();
                        $errors[] = 'Import failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Import Students</h1>
        <p class="text-base-content/60 mt-1">Bulk upload students from CSV file</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Upload Form -->
        <div class="lg:col-span-2">
            <?php if (empty($importPreview)): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Upload CSV File</h2>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-control mb-4">
                                <label class="label"><span class="label-text">Select CSV File</span></label>
                                <input type="file" name="csv_file" accept=".csv" class="file-input file-input-bordered" required />
                                <label class="label">
                                    <span class="label-text-alt">Make sure your CSV follows the template format</span>
                                </label>
                            </div>
                            
                            <div class="card-actions justify-end">
                                <button type="submit" class="btn btn-primary">Upload & Preview</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Preview Table -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Import Preview (<?php echo count($importPreview); ?> rows)</h2>
                        
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>DOB</th>
                                        <th>Gender</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($importPreview as $preview): ?>
                                        <tr class="<?php echo !empty($preview['errors']) ? 'bg-error/10' : ''; ?>">
                                            <td><?php echo $preview['row']; ?></td>
                                            <td><?php echo htmlspecialchars($preview['data'][0]); ?></td>
                                            <td><?php echo htmlspecialchars($preview['data'][1]); ?></td>
                                            <td><?php echo htmlspecialchars($preview['data'][2]); ?></td>
                                            <td><?php echo htmlspecialchars($preview['data'][3]); ?></td>
                                            <td><?php echo htmlspecialchars($preview['data'][4]); ?></td>
                                            <td>
                                                <?php if (empty($preview['errors'])): ?>
                                                    <span class="badge badge-success badge-sm">Ready</span>
                                                <?php else: ?>
                                                    <div class="tooltip" data-tip="<?php echo implode(', ', $preview['errors']); ?>">
                                                        <span class="badge badge-error badge-sm">Error</span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="confirm_import" value="1">
                            
                            <!-- Re-upload the file data -->
                            <?php foreach ($importPreview as $i => $preview): ?>
                                <?php foreach ($preview['data'] as $j => $value): ?>
                                    <input type="hidden" name="preview_data[<?php echo $i; ?>][<?php echo $j; ?>]" value="<?php echo htmlspecialchars($value); ?>">
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            
                            <div class="card-actions justify-between mt-4">
                                <a href="import-students.php" class="btn btn-ghost">Cancel</a>
                                <button type="submit" class="btn btn-success">
                                    Confirm & Import (<?php echo count(array_filter($importPreview, fn($p) => empty($p['errors']))); ?> valid)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Instructions -->
        <div class="space-y-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold text-lg mb-4">Instructions</h3>
                    
                    <ol class="list-decimal list-inside space-y-2 text-sm">
                        <li>Download the CSV template</li>
                        <li>Fill in student data</li>
                        <li>Save as CSV format</li>
                        <li>Upload the file</li>
                        <li>Review the preview</li>
                        <li>Confirm import</li>
                    </ol>
                    
                    <div class="divider"></div>
                    
                    <a href="?download_template=1" class="btn btn-outline btn-block btn-sm">
                        📥 Download Template
                    </a>
                </div>
            </div>

            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="font-bold">CSV Format</h3>
                    <div class="text-xs space-y-2">
                        <p><strong>Columns (in order):</strong></p>
                        <ul class="list-disc list-inside">
                            <li>full_name (required)</li>
                            <li>email (required, unique)</li>
                            <li>phone</li>
                            <li>date_of_birth (YYYY-MM-DD)</li>
                            <li>gender (male/female/other)</li>
                            <li>address</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <span>Temporary passwords will be auto-generated. Remember to share them with students.</span>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
