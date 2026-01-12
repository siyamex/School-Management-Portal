<?php
/**
 * Mark Attendance
 * Teachers can mark daily attendance for their classes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Mark Attendance - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['teacher', 'leading_teacher', 'admin', 'principal']);

$success = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request';
    } else {
        $sectionId = (int)$_POST['section_id'];
        $attendanceDate = sanitize($_POST['attendance_date']);
        $attendanceData = $_POST['attendance'] ?? [];
        
        if (empty($attendanceData)) {
            $errors[] = 'No attendance data provided';
        } else {
            try {
                beginTransaction();
                
                // Delete existing attendance for this date/section (if correcting)
                query("DELETE FROM student_attendance WHERE section_id = ? AND attendance_date = ?", [$sectionId, $attendanceDate]);
                
                // Insert new attendance records
                foreach ($attendanceData as $studentId => $status) {
                    $remarks = sanitize($_POST['remarks'][$studentId] ?? '');
                    $sql = "INSERT INTO student_attendance (student_id, section_id, attendance_date, status, remarks, marked_by) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    insert($sql, [(int)$studentId, $sectionId, $attendanceDate, $status, $remarks, getCurrentUserId()]);
                }
                
                commit();
                $success = 'Attendance marked successfully for ' . count($attendanceData) . ' students';
            } catch (Exception $e) {
                rollback();
                $errors[] = 'Error saving attendance: ' . $e->getMessage();
            }
        }
    }
}

// Get sections for selection
$selectedSection = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$selectedDate = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

$sections = getAll("
    SELECT s.*, c.class_name, 
           (SELECT COUNT(*) FROM enrollments WHERE section_id = s.id AND status = 'active') as student_count
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

// Get students if section selected
$students = [];
$attendanceRecords = [];
if ($selectedSection) {
    $students = getAll("
        SELECT st.id, u.full_name, u.photo, e.roll_number
        FROM enrollments e
        JOIN students st ON e.student_id = st.id
        JOIN users u ON st.user_id = u.id
        WHERE e.section_id = ? AND e.status = 'active'
        ORDER BY e.roll_number, u.full_name
    ", [$selectedSection]);
    
    // Check if attendance already marked for this date
    $existing = getAll("
        SELECT student_id, status, remarks
        FROM student_attendance
        WHERE section_id = ? AND attendance_date = ?
    ", [$selectedSection, $selectedDate]);
    
    foreach ($existing as $record) {
        $attendanceRecords[$record['student_id']] = $record;
    }
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Mark Attendance</h1>
        <p class="text-base-content/60 mt-1">Record student attendance for your class</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <!-- Selection Form -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <form method="GET" id="selectionForm">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text">Select Class/Section</span></label>
                        <select name="section_id" class="select select-bordered" required onchange="this.form.submit()">
                            <option value="">-- Choose Section --</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo $selectedSection == $section['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                                    (<?php echo $section['student_count']; ?> students)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">Attendance Date</span></label>
                        <input type="date" name="date" value="<?php echo $selectedDate; ?>" class="input input-bordered" 
                               max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()" />
                    </div>
                    
                    <div class="form-control">
                        <label class="label"><span class="label-text">&nbsp;</span></label>
                        <button type="submit" class="btn btn-primary">Load Students</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedSection && !empty($students)): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="section_id" value="<?php echo $selectedSection; ?>">
            <input type="hidden" name="attendance_date" value="<?php echo $selectedDate; ?>">
            
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="card-title">Student Roster (<?php echo count($students); ?> students)</h2>
                        <div class="flex gap-2">
                            <button type="button" onclick="markAll('present')" class="btn btn-sm btn-success btn-outline">Mark All Present</button>
                            <button type="button" onclick="markAll('absent')" class="btn btn-sm btn-error btn-outline">Mark All Absent</button>
                        </div>
                    </div>
                    
                    <?php if (!empty($attendanceRecords)): ?>
                        <div class="alert alert-warning mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <span>Attendance already marked for this date. You can update it by resubmitting.</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Roll No.</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): 
                                    $currentStatus = $attendanceRecords[$student['id']]['status'] ?? 'present';
                                    $currentRemarks = $attendanceRecords[$student['id']]['remarks'] ?? '';
                                ?>
                                    <tr>
                                        <td class="font-semibold"><?php echo str_pad($student['roll_number'], 2, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar">
                                                    <div class="w-10 h-10 rounded-full <?php echo $student['photo'] ? '' : 'bg-primary text-primary-content flex items-center justify-center'; ?>">
                                                        <?php if ($student['photo']): ?>
                                                            <img src="<?php echo APP_URL . '/' . $student['photo']; ?>" alt="" />
                                                        <?php else: ?>
                                                            <span><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <span class="font-medium"><?php echo htmlspecialchars($student['full_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex gap-2">
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="present" 
                                                           class="radio radio-success radio-sm" <?php echo $currentStatus === 'present' ? 'checked' : ''; ?> />
                                                    <span class="ml-1 text-sm">Present</span>
                                                </label>
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="absent" 
                                                           class="radio radio-error radio-sm" <?php echo $currentStatus === 'absent' ? 'checked' : ''; ?> />
                                                    <span class="ml-1 text-sm">Absent</span>
                                                </label>
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="late" 
                                                           class="radio radio-warning radio-sm" <?php echo $currentStatus === 'late' ? 'checked' : ''; ?> />
                                                    <span class="ml-1 text-sm">Late</span>
                                                </label>
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="excused" 
                                                           class="radio radio-info radio-sm" <?php echo $currentStatus === 'excused' ? 'checked' : ''; ?> />
                                                    <span class="ml-1 text-sm">Excused</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="remarks[<?php echo $student['id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($currentRemarks); ?>"
                                                   placeholder="Optional remarks" class="input input-sm input-bordered w-full" />
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card-actions justify-end mt-6">
                        <button type="submit" name="submit_attendance" class="btn btn-primary btn-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                            Submit Attendance
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php elseif ($selectedSection): ?>
        <div class="alert alert-warning">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <span>No students enrolled in this section.</span>
        </div>
    <?php endif; ?>
</main>

<script>
function markAll(status) {
    const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
    radios.forEach(radio => {
        radio.checked = true;
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
