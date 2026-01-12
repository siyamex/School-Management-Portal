<?php
/**
 * Create/Manage Timetable
 * Weekly class schedule management
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Manage Timetable - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Get time slots from database
$timeSlotsData = getAll("SELECT * FROM time_slots WHERE is_active = 1 ORDER BY sort_order, start_time");
$timeSlots = [];
foreach ($timeSlotsData as $ts) {
    $key = substr($ts['start_time'], 0, 5) . '-' . substr($ts['end_time'], 0, 5);
    $timeSlots[$key] = $ts['slot_name'];
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Handle adding period
if (isset($_POST['add_period']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $sectionId = (int)$_POST['section_id'];
    $day = sanitize($_POST['day']);
    $timeSlot = sanitize($_POST['time_slot']);
    $subjectId = (int)$_POST['subject_id'];
    $teacherId = (int)$_POST['teacher_id'];
    
    // Extract start and end times from time slot (e.g., "08:00-08:45")
    $times = explode('-', $timeSlot);
    $startTime = $times[0] ?? '00:00';
    $endTime = $times[1] ?? '00:00';
    
    // Convert day to lowercase for day_of_week enum
    $dayOfWeek = strtolower($day);
    
    // Get current academic year
    $currentYear = getRow("SELECT id FROM academic_years WHERE is_current = 1");
    $academicYearId = $currentYear ? $currentYear['id'] : 1;
    
    // Check for conflicts
    $conflict = getRow("SELECT * FROM timetables WHERE section_id = ? AND day = ? AND time_slot = ?", 
                      [$sectionId, $day, $timeSlot]);
    
    if ($conflict) {
        $errors[] = 'Time slot already occupied';
    } else {
        try {
            insert("INSERT INTO timetables (section_id, day, day_of_week, time_slot, start_time, end_time, subject_id, teacher_id, academic_year_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [$sectionId, $day, $dayOfWeek, $timeSlot, $startTime, $endTime, $subjectId, $teacherId, $academicYearId]);
            setFlash('success', 'Period added to timetable');
            redirect('manage-timetable.php?section=' . $sectionId);
        } catch (Exception $e) {
            $errors[] = 'Failed to add period: ' . $e->getMessage();
        }
    }
}

// Handle edit period
if (isset($_POST['edit_period']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $periodId = (int)$_POST['period_id'];
    $subjectId = (int)$_POST['subject_id'];
    $teacherId = (int)$_POST['teacher_id'];
    $sectionId = (int)$_POST['section_id'];
    
    try {
        query("UPDATE timetables SET subject_id = ?, teacher_id = ? WHERE id = ?",
             [$subjectId, $teacherId, $periodId]);
        setFlash('success', 'Period updated successfully');
        redirect('manage-timetable.php?section=' . $sectionId);
    } catch (Exception $e) {
        $errors[] = 'Failed to update period: ' . $e->getMessage();
    }
}

// Handle copy period
if (isset($_POST['copy_period']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $periodId = (int)$_POST['period_id'];
    $targetDays = $_POST['target_days'] ?? [];
    $sectionId = (int)$_POST['section_id'];
    
    // Get original period data
    $original = getRow("SELECT * FROM timetables WHERE id = ?", [$periodId]);
    
    if ($original && !empty($targetDays)) {
        try {
            beginTransaction();
            
            foreach ($targetDays as $day) {
                $day = sanitize($day);
                $dayOfWeek = strtolower($day);
                
                // Check if slot already exists
                $conflict = getRow("SELECT id FROM timetables WHERE section_id = ? AND day = ? AND time_slot = ?",
                                  [$original['section_id'], $day, $original['time_slot']]);
                
                if (!$conflict) {
                    insert("INSERT INTO timetables (section_id, day, day_of_week, time_slot, start_time, end_time, subject_id, teacher_id, academic_year_id, room_number) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                          [$original['section_id'], $day, $dayOfWeek, $original['time_slot'], 
                           $original['start_time'], $original['end_time'], $original['subject_id'], 
                           $original['teacher_id'], $original['academic_year_id'], $original['room_number']]);
                }
            }
            
            commit();
            setFlash('success', 'Period copied to selected days');
            redirect('manage-timetable.php?section=' . $sectionId);
        } catch (Exception $e) {
            rollback();
            $errors[] = 'Failed to copy period: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_POST['delete_period']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $periodId = (int)$_POST['period_id'];
    query("DELETE FROM timetables WHERE id = ?", [$periodId]);
    setFlash('success', 'Period removed');
    redirect('manage-timetable.php?section=' . ($_POST['section_id'] ?? ''));
}

// Get selected section
$sectionId = isset($_GET['section']) ? (int)$_GET['section'] : 0;

// Get all sections
$sections = getAll("
    SELECT s.id, s.section_name, c.class_name
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

// Get timetable for selected section
$timetable = [];
if ($sectionId) {
    $timetableData = getAll("
        SELECT t.*, sub.subject_name, sub.subject_code,
               u.full_name as teacher_name
        FROM timetables t
        JOIN subjects sub ON t.subject_id = sub.id
        JOIN teachers teach ON t.teacher_id = teach.id
        JOIN users u ON teach.user_id = u.id
        WHERE t.section_id = ?
        ORDER BY FIELD(t.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.time_slot
    ", [$sectionId]);
    
    // Organize by day and time slot
    foreach ($timetableData as $period) {
        $timetable[$period['day']][$period['time_slot']] = $period;
    }
}

// Get subjects and teachers for the form
$subjects = getAll("SELECT * FROM subjects ORDER BY subject_name");
$teachers = getAll("
    SELECT t.id, u.full_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name
");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<style>
/* Custom text selection colors for better visibility */
::selection {
    background-color: rgba(59, 130, 246, 0.3); /* Light blue */
    color: inherit;
}

::-moz-selection {
    background-color: rgba(59, 130, 246, 0.3); /* Light blue for Firefox */
    color: inherit;
}

/* Specific for timetable cells */
.timetable-table ::selection {
    background-color: rgba(251, 191, 36, 0.4); /* Light amber */
    color: inherit;
}

.timetable-table ::-moz-selection {
    background-color: rgba(251, 191, 36, 0.4); /* Light amber for Firefox */
    color: inherit;
}
</style>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Manage Timetable</h1>
            <p class="text-base-content/60 mt-1">Create and manage class schedules</p>
        </div>
        <div class="flex gap-2">
            <a href="<?php echo APP_URL; ?>/modules/academic/time-slots.php" class="btn btn-ghost btn-sm" title="Configure Time Slots">
                ⚙️ Time Slots
            </a>
            <a href="quick-edit.php<?php echo $sectionId ? '?section=' . $sectionId : ''; ?>" class="btn btn-primary btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Quick Edit
            </a>
            <?php if ($sectionId): ?>
                <button onclick="add_period_modal.showModal()" class="btn btn-primary">
                    + Add Period
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Section Selector -->
    <div class="card bg-base-100 shadow-lg mb-6">
        <div class="card-body">
            <form method="GET" class="flex gap-4 items-end">
                <div class="form-control flex-1">
                    <label class="label"><span class="label-text">Select Class/Section</span></label>
                    <select name="section" class="select select-bordered" onchange="this.form.submit()">
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $sectionId == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($sectionId): ?>
                    <a href="view-timetable.php?section=<?php echo $sectionId; ?>" class="btn btn-ghost" target="_blank">
                        View Timetable
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($sectionId): ?>
        <!-- Timetable Grid -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title mb-4">Weekly Schedule</h3>
                <div class="overflow-x-auto">
                    <table class="table table-sm table-bordered timetable-table">
                        <thead>
                            <tr>
                                <th class="bg-base-200">Time</th>
                                <?php foreach ($days as $day): ?>
                                    <th class="bg-base-200 text-center"><?php echo $day; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timeSlots as $slot => $label): ?>
                                <?php if (strpos($label, 'Break') !== false || strpos($label, 'Lunch') !== false): ?>
                                    <tr class="bg-warning/10">
                                        <td class="font-semibold"><?php echo $slot; ?><br><small><?php echo $label; ?></small></td>
                                        <td colspan="5" class="text-center font-semibold"><?php echo $label; ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td class="font-semibold"><?php echo $slot; ?><br><small><?php echo $label; ?></small></td>
                                        <?php foreach ($days as $day): ?>
                                            <td class="p-1">
                                                <?php if (isset($timetable[$day][$slot])): ?>
                                                    <?php
                                                    $period = $timetable[$day][$slot];
                                                    // Better color scheme with solid backgrounds
                                                    $colorSchemes = [
                                                        ['bg' => 'bg-blue-500', 'text' => 'text-white', 'border' => 'border-blue-700'],
                                                        ['bg' => 'bg-green-500', 'text' => 'text-white', 'border' => 'border-green-700'],
                                                        ['bg' => 'bg-purple-500', 'text' => 'text-white', 'border' => 'border-purple-700'],
                                                        ['bg' => 'bg-orange-500', 'text' => 'text-white', 'border' => 'border-orange-700'],
                                                        ['bg' => 'bg-pink-500', 'text' => 'text-white', 'border' => 'border-pink-700'],
                                                        ['bg' => 'bg-teal-500', 'text' => 'text-white', 'border' => 'border-teal-700'],
                                                        ['bg' => 'bg-indigo-500', 'text' => 'text-white', 'border' => 'border-indigo-700'],
                                                        ['bg' => 'bg-cyan-500', 'text' => 'text-white', 'border' => 'border-cyan-700'],
                                                    ];
                                                    $colorIndex = $period['subject_id'] % count($colorSchemes);
                                                    $scheme = $colorSchemes[$colorIndex];
                                                    ?>
                                                    <div class="<?php echo $scheme['bg']; ?> <?php echo $scheme['text']; ?> border-l-4 <?php echo $scheme['border']; ?> p-2 rounded hover:shadow-lg hover:scale-105 transition-all cursor-pointer group relative" 
                                                         onclick="showEditModal(<?php echo $period['id']; ?>, <?php echo $period['subject_id']; ?>, <?php echo $period['teacher_id']; ?>, <?php echo $sectionId; ?>)">
                                                        <p class="font-bold text-sm"><?php echo htmlspecialchars($period['subject_code']); ?></p>
                                                        <p class="text-xs opacity-90"><?php echo htmlspecialchars($period['teacher_name']); ?></p>
                                                        
                                                        <!-- Quick Actions (show on hover) -->
                                                        <div class="hidden group-hover:flex gap-1 mt-1">
                                                            <button type="button" 
                                                                    onclick="event.stopPropagation(); showCopyModal(<?php echo $period['id']; ?>, <?php echo $sectionId; ?>, '<?php echo htmlspecialchars($day); ?>')" 
                                                                    class="btn btn-xs bg-white/20 hover:bg-white/30 border-none text-white" title="Copy to other days">
                                                                📋
                                                            </button>
                                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this period?');" onclick="event.stopPropagation()">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                                                <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
                                                                <button type="submit" name="delete_period" class="btn btn-xs bg-red-600 hover:bg-red-700 border-none text-white" title="Remove">🗑️</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-base-content/30">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Select a section to manage its timetable.</span>
        </div>
    <?php endif; ?>
</main>

<!-- Add Period Modal -->
<dialog id="add_period_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Add Period</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Day</span></label>
                <select name="day" class="select select-bordered" required>
                    <option value="">-- Select Day --</option>
                    <?php foreach ($days as $day): ?>
                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Time Slot</span></label>
                <select name="time_slot" class="select select-bordered" required>
                    <option value="">-- Select Time --</option>
                    <?php foreach ($timeSlots as $slot => $label): ?>
                        <?php if (strpos($label, 'Break') === false && strpos($label, 'Lunch') === false): ?>
                            <option value="<?php echo $slot; ?>"><?php echo $slot; ?> - <?php echo $label; ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Subject</span></label>
                <select name="subject_id" class="select select-bordered" required>
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Teacher</span></label>
                <select name="teacher_id" class="select select-bordered" required>
                    <option value="">-- Select Teacher --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="add_period_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="add_period" class="btn btn-primary">Add Period</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Edit Period Modal -->
<dialog id="edit_period_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Edit Period</h3>
        <form method="POST" id="editPeriodForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="period_id" id="edit_period_id">
            <input type="hidden" name="section_id" id="edit_section_id">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Subject</span></label>
                <select name="subject_id" id="edit_subject_id" class="select select-bordered" required>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Teacher</span></label>
                <select name="teacher_id" id="edit_teacher_id" class="select select-bordered" required>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="edit_period_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="edit_period" class="btn btn-primary">Update Period</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Copy Period Modal -->
<dialog id="copy_period_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Copy Period to Other Days</h3>
        <form method="POST" id="copyPeriodForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="period_id" id="copy_period_id">
            <input type="hidden" name="section_id" id="copy_section_id">
            
            <p class="text-sm text-base-content/70 mb-3">Select the days to copy this period to (same time slot):</p>
            
            <div class="form-control mb-2">
                <?php foreach ($days as $day): ?>
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" name="target_days[]" value="<?php echo $day; ?>" class="checkbox checkbox-primary copy-day-checkbox">
                        <span class="label-text"><?php echo $day; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="copy_period_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="copy_period" class="btn btn-primary">Copy Period</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
// Show edit modal with pre-filled data
function showEditModal(periodId, subjectId, teacherId, sectionId) {
    document.getElementById('edit_period_id').value = periodId;
    document.getElementById('edit_subject_id').value = subjectId;
    document.getElementById('edit_teacher_id').value = teacherId;
    document.getElementById('edit_section_id').value = sectionId;
    edit_period_modal.showModal();
}

// Show copy modal and pre-fill section, exclude current day
function showCopyModal(periodId, sectionId, currentDay) {
    document.getElementById('copy_period_id').value = periodId;
    document.getElementById('copy_section_id').value = sectionId;
    
    // Uncheck all checkboxes
    document.querySelectorAll('.copy-day-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        // Disable current day
        if (checkbox.value === currentDay) {
            checkbox.disabled = true;
            checkbox.parentElement.style.opacity = '0.5';
        } else {
            checkbox.disabled = false;
            checkbox.parentElement.style.opacity = '1';
        }
    });
    
    copy_period_modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
