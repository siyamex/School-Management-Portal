<?php
/**
 * Bulk Grade Import (CSV)
 * Import grades for multiple students via CSV file
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Bulk Grade Import - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal', 'teacher']);

$errors = [];
$success = '';
$preview = [];
$examId = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : (isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0);

// Handle CSV upload and preview
if (isset($_POST['upload_csv']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $examId = (int)$_POST['exam_id'];
    
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        
        if (($handle = fopen($file, 'r')) !== false) {
            $headers = fgetcsv($handle);
            
            // Expected: student_id, subject_code, marks_obtained
            if (!$headers || count($headers) < 3) {
                $errors[] = 'Invalid CSV format. Expected: student_id, subject_code, marks_obtained';
            } else {
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) >= 3) {
                        $preview[] = [
                            'student_id' => trim($row[0]),
                            'subject_code' => trim($row[1]),
                            'marks_obtained' => trim($row[2])
                        ];
                    }
                }
            }
            fclose($handle);
            
            // Validate data
            foreach ($preview as &$item) {
                $item['errors'] = [];
                
                // Check student exists
                $student = getRow("SELECT id FROM students WHERE student_id = ?", [$item['student_id']]);
                if (!$student) {
                    $item['errors'][] = 'Student not found';
                } else {
                    $item['student_db_id'] = $student['id'];
                }
                
                // Check subject exists
                $subject = getRow("SELECT id FROM subjects WHERE subject_code = ?", [$item['subject_code']]);
                if (!$subject) {
                    $item['errors'][] = 'Subject not found';
                } else {
                    $item['subject_db_id'] = $subject['id'];
                }
                
                // Validate marks
                if (!is_numeric($item['marks_obtained']) || $item['marks_obtained'] < 0) {
                    $item['errors'][] = 'Invalid marks';
                }
            }
        }
    } else {
        $errors[] = 'Please select a CSV file';
    }
}

// Handle import confirmation
if (isset($_POST['confirm_import']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $data = json_decode($_POST['import_data'], true);
    $examId = (int)$_POST['exam_id'];
    
    $exam = getRow("SELECT total_marks FROM exams WHERE id = ?", [$examId]);
    
    if (!$exam) {
        $errors[] = 'Exam not found';
    } else {
        try {
            beginTransaction();
            
            $imported = 0;
            foreach ($data as $item) {
                if (empty($item['errors']) && isset($item['student_db_id']) && isset($item['subject_db_id'])) {
                    $marks = (float)$item['marks_obtained'];
                    $percentage = ($marks / $exam['total_marks']) * 100;
                    
                    // Calculate grade
                    if ($percentage >= 90) {
                        $gradeLetter = 'A+';
                        $gradePoint = 4.0;
                    } elseif ($percentage >= 85) {
                        $gradeLetter = 'A';
                        $gradePoint = 3.7;
                    } elseif ($percentage >= 80) {
                        $gradeLetter = 'B+';
                        $gradePoint = 3.3;
                    } elseif ($percentage >= 75) {
                        $gradeLetter = 'B';
                        $gradePoint = 3.0;
                    } elseif ($percentage >= 70) {
                        $gradeLetter = 'C+';
                        $gradePoint = 2.7;
                    } elseif ($percentage >= 65) {
                        $gradeLetter = 'C';
                        $gradePoint = 2.3;
                    } elseif ($percentage >= 60) {
                        $gradeLetter = 'D';
                        $gradePoint = 2.0;
                    } else {
                        $gradeLetter = 'F';
                        $gradePoint = 0.0;
                    }
                    
                    // Check if grade already exists
                    $existing = getRow("SELECT id FROM grades WHERE student_id = ? AND exam_id = ? AND subject_id = ?",
                                      [$item['student_db_id'], $examId, $item['subject_db_id']]);
                    
                    if ($existing) {
                        query("UPDATE grades SET marks_obtained = ?, grade_letter = ?, grade_point = ? WHERE id = ?",
                             [$marks, $gradeLetter, $gradePoint, $existing['id']]);
                    } else {
                        insert("INSERT INTO grades (student_id, exam_id, subject_id, marks_obtained, grade_letter, grade_point, is_published) 
                               VALUES (?, ?, ?, ?, ?, ?, 0)",
                              [$item['student_db_id'], $examId, $item['subject_db_id'], $marks, $gradeLetter, $gradePoint]);
                    }
                    
                    $imported++;
                }
            }
            
            commit();
            setFlash('success', "Successfully imported $imported grades");
            redirect('bulk-grade-import.php');
        } catch (Exception $e) {
            rollback();
            $errors[] = 'Import failed: ' . $e->getMessage();
        }
    }
}

// Get exams for selection
$exams = getAll("SELECT e.*, a.year_name FROM exams e JOIN academic_years a ON e.academic_year_id = a.id ORDER BY e.created_at DESC");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Bulk Grade Import</h1>
        <p class="text-base-content/60 mt-1">Import grades from CSV file</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (empty($preview)): ?>
        <!-- Upload Form -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">Upload CSV File</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">Select Exam</span></label>
                            <select name="exam_id" class="select select-bordered" required>
                                <option value="">-- Select Exam --</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>" <?php echo $examId == $exam['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['exam_name'] . ' (' . $exam['year_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label"><span class="label-text">CSV File</span></label>
                            <input type="file" name="csv_file" accept=".csv" class="file-input file-input-bordered" required />
                        </div>
                        
                        <button type="submit" name="upload_csv" class="btn btn-primary btn-block">Upload & Preview</button>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h3 class="card-title">CSV Format</h3>
                    <p class="text-sm mb-4">Your CSV file must have the following columns:</p>
                    <div class="mockup-code text-xs">
                        <pre><code>student_id,subject_code,marks_obtained
STD001,MATH101,85
STD001,ENG101,78
STD002,MATH101,92</code></pre>
                    </div>
                    <a href="data:text/csv;charset=utf-8,student_id,subject_code,marks_obtained%0ASTD001,MATH101,85%0ASTD001,ENG101,78%0ASTD002,MATH101,92" 
                       download="grade_import_template.csv" class="btn btn-ghost btn-sm mt-4">
                        📥 Download Template
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Preview Table -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Preview - Review Before Import</h3>
                <p class="text-sm text-base-content/60 mb-4">Found <?php echo count($preview); ?> records</p>
                
                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Subject Code</th>
                                <th>Marks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $validCount = 0;
                            foreach ($preview as $item): 
                                $isValid = empty($item['errors']);
                                if ($isValid) $validCount++;
                            ?>
                                <tr class="<?php echo $isValid ? '' : 'bg-error/10'; ?>">
                                    <td><?php echo htmlspecialchars($item['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['marks_obtained']); ?></td>
                                    <td>
                                        <?php if ($isValid): ?>
                                            <span class="badge badge-success badge-sm">Valid</span>
                                        <?php else: ?>
                                            <span class="badge badge-error badge-sm">
                                                <?php echo implode(', ', $item['errors']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info mt-4">
                    <span><?php echo $validCount; ?> valid records will be imported. Invalid records will be skipped.</span>
                </div>
                
                <form method="POST" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                    <input type="hidden" name="import_data" value="<?php echo htmlspecialchars(json_encode($preview)); ?>">
                    
                    <div class="flex gap-2">
                        <a href="bulk-grade-import.php" class="btn btn-ghost">Cancel</a>
                        <button type="submit" name="confirm_import" class="btn btn-success">
                            Confirm Import (<?php echo $validCount; ?> records)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
