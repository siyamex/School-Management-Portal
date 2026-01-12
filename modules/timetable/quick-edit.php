<?php
/**
 * Quick Edit Timetable - Simplified Visual Editor
 * Drag-and-drop, click-to-edit timetable management
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin', 'principal']);

// Handle AJAX requests FIRST - before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Get current academic year
    $currentYear = getRow("SELECT id FROM academic_years WHERE is_current = 1");
    $academicYearId = $currentYear ? $currentYear['id'] : 1;
    
    if ($_POST['action'] === 'save_period') {
        $sectionId = (int)$_POST['section_id'];
        $day = sanitize($_POST['day']);
        $timeSlot = sanitize($_POST['time_slot']);
        $subjectId = (int)$_POST['subject_id'];
        $teacherId = (int)$_POST['teacher_id'];
        
        // Parse time slot - handle both "HH:MM-HH:MM" and just slot names
        $times = explode('-', $timeSlot);
        if (count($times) >= 2) {
            $startTime = trim($times[0]);
            $endTime = trim($times[1]);
        } else {
            // If no dash, try to get from time_slots table
            $slot = getRow("SELECT start_time, end_time FROM time_slots WHERE slot_name = ?", [$timeSlot]);
            if ($slot) {
                $startTime = substr($slot['start_time'], 0, 5);
                $endTime = substr($slot['end_time'], 0, 5);
                $timeSlot = $startTime . '-' . $endTime; // Normalize format
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid time slot']);
                exit;
            }
        }
        
        $dayOfWeek = strtolower($day);
        
        try {
            // Check if exists
            $existing = getRow("SELECT id FROM timetables WHERE section_id = ? AND day = ? AND time_slot = ?",
                             [$sectionId, $day, $timeSlot]);
            
            if ($existing) {
                query("UPDATE timetables SET subject_id = ?, teacher_id = ? WHERE id = ?",
                     [$subjectId, $teacherId, $existing['id']]);
            } else {
                insert("INSERT INTO timetables (section_id, day, day_of_week, time_slot, start_time, end_time, subject_id, teacher_id, academic_year_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                      [$sectionId, $day, $dayOfWeek, $timeSlot, $startTime, $endTime, $subjectId, $teacherId, $academicYearId]);
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_period') {
        $sectionId = (int)$_POST['section_id'];
        $day = sanitize($_POST['day']);
        $timeSlot = sanitize($_POST['time_slot']);
        
        try {
            query("DELETE FROM timetables WHERE section_id = ? AND day = ? AND time_slot = ?",
                 [$sectionId, $day, $timeSlot]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'copy_monday') {
        $sectionId = (int)$_POST['section_id'];
        
        try {
            beginTransaction();
            
            // Get Monday's schedule
            $mondaySchedule = getAll("SELECT * FROM timetables WHERE section_id = ? AND day = 'Monday' AND academic_year_id = ?",
                                    [$sectionId, $academicYearId]);
            
            if (!empty($mondaySchedule)) {
                // Clear other days
                query("DELETE FROM timetables WHERE section_id = ? AND day != 'Monday' AND academic_year_id = ?",
                     [$sectionId, $academicYearId]);
                
                // Copy to other days
                foreach (['Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
                    foreach ($mondaySchedule as $period) {
                        $dayOfWeek = strtolower($day);
                        insert("INSERT INTO timetables (section_id, day, day_of_week, time_slot, start_time, end_time, subject_id, teacher_id, academic_year_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                              [$sectionId, $day, $dayOfWeek, $period['time_slot'], $period['start_time'], $period['end_time'], 
                               $period['subject_id'], $period['teacher_id'], $academicYearId]);
                    }
                }
            }
            
            commitTransaction();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            rollbackTransaction();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Now continue with regular page rendering
$pageTitle = "Quick Edit Timetable - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

// Get selected section
$selectedSection = isset($_GET['section']) ? (int)$_GET['section'] : 0;

// Get all sections
$sections = getAll("
    SELECT s.id, s.section_name, c.class_name
    FROM sections s
    JOIN classes c ON s.class_id = c.id
    ORDER BY c.class_numeric, s.section_name
");

// Get time slots
$timeSlots = getAll("SELECT * FROM time_slots WHERE is_active = 1 ORDER BY sort_order, start_time");

// Get subjects
$subjects = getAll("SELECT * FROM subjects ORDER BY subject_name");

// Get teachers  
$teachers = getAll("
    SELECT t.id, u.full_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name
");

// Get current academic year
$currentYear = getRow("SELECT id FROM academic_years WHERE is_current = 1");
$academicYearId = $currentYear ? $currentYear['id'] : 1;

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Get existing timetable for selected section
$timetable = [];
if ($selectedSection) {
    $entries = getAll("
        SELECT t.*, sub.subject_name, u.full_name as teacher_name
        FROM timetables t
        JOIN subjects sub ON t.subject_id = sub.id
        JOIN teachers te ON t.teacher_id = te.id
        JOIN users u ON te.user_id = u.id
        WHERE t.section_id = ? AND t.academic_year_id = ?
        ORDER BY t.day, t.start_time
    ", [$selectedSection, $academicYearId]);
    
    foreach ($entries as $entry) {
        $timetable[$entry['day']][$entry['time_slot']] = $entry;
    }
}

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'save_period') {
        $sectionId = (int)$_POST['section_id'];
        $day = sanitize($_POST['day']);
        $timeSlot = sanitize($_POST['time_slot']);
        $subjectId = (int)$_POST['subject_id'];
        $teacherId = (int)$_POST['teacher_id'];
        
        $times = explode('-', $timeSlot);
        $startTime = $times[0];
        $endTime = $times[1];
        $dayOfWeek = strtolower($day);
        
        try {
            // Check if exists
            $existing = getRow("SELECT id FROM timetables WHERE section_id = ? AND day = ? AND time_slot = ?",
                             [$sectionId, $day, $timeSlot]);
            
            if ($existing) {
                query("UPDATE timetables SET subject_id = ?, teacher_id = ? WHERE id = ?",
                     [$subjectId, $teacherId, $existing['id']]);
            } else {
                insert("INSERT INTO timetables (section_id, day, day_of_week, time_slot, start_time, end_time, subject_id, teacher_id, academic_year_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                      [$sectionId, $day, $dayOfWeek, $timeSlot, $startTime, $endTime, $subjectId, $teacherId, $academicYearId]);
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_period') {
        $sectionId = (int)$_POST['section_id'];
        $day = sanitize($_POST['day']);
        $timeSlot = sanitize($_POST['time_slot']);
        
        try {
            query("DELETE FROM timetables WHERE section_id = ? AND day = ? AND time_slot = ?",
                 [$sectionId, $day, $timeSlot]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'copy_monday') {
        $sectionId = (int)$_POST['section_id'];
        
        try {
            beginTransaction();
            
            // Get Monday's schedule
            $mondaySchedule = getAll("SELECT * FROM timetables WHERE section_id = ? AND day = 'Monday' AND academic_year_id = ?",
                                    [$sectionId, $academicYearId]);
            
            if (!empty($mondaySchedule)) {
                // Clear other days
                query("DELETE FROM timetables WHERE section_id = ? AND day != 'Monday' AND academic_year_id = ?",
                     [$sectionId, $academicYearId]);
                
                // Copy to other days
                foreach (['Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
                    foreach ($mondaySchedule as $period) {
                        $dayOfWeek = strtolower($day);
                        insert("INSERT INTO timetables (section_id, day, day_of_week, time_slot, start_time, end_time, subject_id, teacher_id, academic_year_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                              [$sectionId, $day, $dayOfWeek, $period['time_slot'], $period['start_time'], $period['end_time'], 
                               $period['subject_id'], $period['teacher_id'], $academicYearId]);
                    }
                }
            }
            
            commitTransaction();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            rollbackTransaction();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <a href="manage-timetable.php" class="btn btn-ghost btn-sm gap-2 mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Classic View
        </a>
        <h1 class="text-3xl font-bold">📅 Quick Edit Timetable</h1>
        <p class="text-base-content/60 mt-1">Click any cell to edit - changes save automatically</p>
    </div>

    <!-- Section Selector -->
    <div class="card bg-base-100 shadow-xl mb-6">
        <div class="card-body">
            <div class="flex flex-wrap gap-4 items-center">
                <div class="flex-1 min-w-[200px]">
                    <label class="label"><span class="label-text font-semibold">Select Class & Section:</span></label>
                    <select id="section_selector" class="select select-bordered w-full" onchange="changSection(this.value)">
                        <option value="">-- Choose Section --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $selectedSection == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['class_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($selectedSection): ?>
                    <div>
                        <label class="label"><span class="label-text">&nbsp;</span></label>
                        <button onclick="copyMondayToWeek()" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            Copy Monday → Week
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($selectedSection): ?>
        <!-- Timetable Grid -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body p-0">
                <div class="overflow-x-auto">
                    <table class="table table-xs">
                        <thead>
                            <tr class="bg-base-200">
                                <th class="sticky left-0 bg-base-200 z-10" style="min-width: 120px;">Time</th>
                                <?php foreach ($days as $day): ?>
                                    <th class="text-center"><?php echo $day; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timeSlots as $slot): 
                                $timeKey = substr($slot['start_time'], 0, 5) . '-' . substr($slot['end_time'], 0, 5);
                            ?>
                                <tr class="hover">
                                    <td class="sticky left-0 bg-base-100 font-semibold" style="min-width: 120px;">
                                        <div class="text-xs"><?php echo $slot['slot_name']; ?></div>
                                        <div class="text-xs text-base-content/60"><?php echo $timeKey; ?></div>
                                    </td>
                                    <?php foreach ($days as $day): ?>
                                        <?php 
                                            $period = $timetable[$day][$timeKey] ?? null;
                                            $bgColor = $period ? 'bg-primary' : 'bg-base-200';
                                        ?>
                                        <td class="p-1 relative" style="min-width: 150px;">
                                            <div class="period-cell <?php echo $period ? $bgColor : 'bg-base-200'; ?> text-base-100-content p-2 rounded cursor-pointer hover:opacity-80 transition-opacity min-h-[60px]"
                                                 onclick="editPeriod('<?php echo $day; ?>', '<?php echo $timeKey; ?>', <?php echo $period ? $period['subject_id'] : 'null'; ?>, <?php echo $period ? $period['teacher_id'] : 'null'; ?>)"
                                                 data-day="<?php echo $day; ?>"
                                                 data-slot="<?php echo $timeKey; ?>">
                                                <?php if ($period): ?>
                                                    <div class="font-semibold text-sm"><?php echo htmlspecialchars($period['subject_name']); ?></div>
                                                    <div class="text-xs opacity-90"><?php echo htmlspecialchars($period['teacher_name']); ?></div>
                                                    <button onclick="event.stopPropagation(); deletePeriod('<?php echo $day; ?>', '<?php echo $timeKey; ?>')" 
                                                            class="btn btn-xs btn-circle btn-ghost absolute top-1 right-1 opacity-0 group-hover:opacity-100">×</button>
                                                <?php else: ?>
                                                    <div class="text-xs text-base-content/40 italic">Free period</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Please select a class and section to view and edit the timetable.</span>
        </div>
    <?php endif; ?>
</main>

<!-- Edit Period Modal -->
<dialog id="edit_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Edit Period</h3>
        <form id="edit_form" onsubmit="savePeriod(event)">
            <input type="hidden" id="edit_day">
            <input type="hidden" id="edit_slot">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Subject *</span></label>
                <select id="edit_subject" class="select select-bordered" required>
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Teacher *</span></label>
                <select id="edit_teacher" class="select select-bordered" required>
                    <option value="">-- Select Teacher --</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="edit_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
const sectionId = <?php echo $selectedSection ?: 'null'; ?>;

function changeSection(value) {
    if (value) {
        window.location.href = '?section=' + value;
    }
}

function editPeriod(day, slot, subjectId, teacherId) {
    document.getElementById('edit_day').value = day;
    document.getElementById('edit_slot').value = slot;
    document.getElementById('edit_subject').value = subjectId || '';
    document.getElementById('edit_teacher').value = teacherId || '';
    edit_modal.showModal();
}

async function savePeriod(e) {
    e.preventDefault();
    
    if (!sectionId) return;
    
    const data = new FormData();
    data.append('action', 'save_period');
    data.append('section_id', sectionId);
    data.append('day', document.getElementById('edit_day').value);
    data.append('time_slot', document.getElementById('edit_slot').value);
    data.append('subject_id', document.getElementById('edit_subject').value);
    data.append('teacher_id', document.getElementById('edit_teacher').value);
    
    try {
        const response = await fetch('quick-edit.php', {
            method: 'POST',
            body: data
        });
        const result = await response.json();
        
        if (result.success) {
            edit_modal.close();
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Failed to save: ' + error.message);
    }
}

async function deletePeriod(day, slot) {
    if (!confirm('Delete this period?')) return;
    
    const data = new FormData();
    data.append('action', 'delete_period');
    data.append('section_id', sectionId);
    data.append('day', day);
    data.append('time_slot', slot);
    
    try {
        const response = await fetch('quick-edit.php', {
            method: 'POST',
            body: data
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Failed to delete: ' + error.message);
    }
}

async function copyMondayToWeek() {
    if (!confirm('This will copy Monday\'s schedule to the entire week. Continue?')) return;
    
    const data = new FormData();
    data.append('action', 'copy_monday');
    data.append('section_id', sectionId);
    
    try {
        const response = await fetch('quick-edit.php', {
            method: 'POST',
            body: data
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Failed to copy: ' + error.message);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
