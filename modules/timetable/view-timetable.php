<?php
/**
 * View Timetable
 * Display timetable for students, teachers, and classes
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "View Timetable - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);

// Define time slots
$timeSlots = [
    '08:00-08:45' => 'Period 1',
    '08:45-09:30' => 'Period 2',
    '09:30-10:15' => 'Period 3',
    '10:15-10:30' => 'Break',
    '10:30-11:15' => 'Period 4',
    '11:15-12:00' => 'Period 5',
    '12:00-12:45' => 'Period 6',
    '12:45-13:30' => 'Lunch',
   '13:30-14:15' => 'Period 7',
    '14:15-15:00' => 'Period 8',
];

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Determine what to display
$sectionId = 0;
$teacherId = 0;
$viewMode = 'section'; // section or teacher
$title = '';

if (isset($_GET['section'])) {
    $sectionId = (int)$_GET['section'];
    $viewMode = 'section';
    $sectionInfo = getRow("SELECT s.section_name, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$sectionId]);
    $title = $sectionInfo ? $sectionInfo['class_name'] . ' - ' . $sectionInfo['section_name'] : 'Unknown Section';
} elseif (isset($_GET['teacher'])) {
    $teacherId = (int)$_GET['teacher'];
    $viewMode = 'teacher';
    $teacherName = getValue("SELECT u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?", [$teacherId]);
    $title = $teacherName ? $teacherName . "'s Schedule" : 'Unknown Teacher';
} elseif (in_array('student', $userRoles)) {
    // Auto-load student's section
    $studentId = getValue("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
    $sectionId = getValue("SELECT section_id FROM enrollments WHERE student_id = ? AND status = 'active'", [$studentId]);
    $viewMode = 'section';
    if ($sectionId) {
        $sectionInfo = getRow("SELECT s.section_name, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$sectionId]);
        $title = $sectionInfo ? 'My Timetable - ' . $sectionInfo['class_name'] . ' ' . $sectionInfo['section_name'] : 'My Timetable';
    }
} elseif (in_array('teacher', $userRoles)) {
    // Auto-load teacher's schedule
    $teacherId = getValue("SELECT id FROM teachers WHERE user_id = ?", [getCurrentUserId()]);
    $viewMode = 'teacher';
    $title = 'My Teaching Schedule';
}

// Get timetable data
$timetable = [];

if ($viewMode === 'section' && $sectionId) {
    $timetableData = getAll("
        SELECT t.*, sub.subject_name, sub.subject_code,
               u.full_name as teacher_name
        FROM timetables t
        JOIN subjects sub ON t.subject_id = sub.id
        JOIN teachers teach ON t.teacher_id = teach.id
        JOIN users u ON teach.user_id = u.id
        WHERE t.section_id = ?
        ORDER BY FIELD(t.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'), t.start_time
    ", [$sectionId]);
} elseif ($viewMode === 'teacher' && $teacherId) {
    $timetableData = getAll("
        SELECT t.*, sub.subject_name, sub.subject_code,
               s.section_name, c.class_name
        FROM timetables t
        JOIN subjects sub ON t.subject_id = sub.id
        JOIN sections s ON t.section_id = s.id
        JOIN classes c ON s.class_id = c.id
        WHERE t.teacher_id = ?
        ORDER BY FIELD(t.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'), t.start_time
    ", [$teacherId]);
}

// Organize by day and time slot
if (isset($timetableData)) {
    foreach ($timetableData as $period) {
        $timetable[$period['day']][$period['time_slot']] = $period;
    }
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<style>
/* Prevent black hover on table rows */
.timetable-table tbody tr:hover {
    background: rgba(59, 130, 246, 0.08) !important; /* Very light blue */
}

/* Light selection colors */
.timetable-table ::selection {
    background-color: rgba(96, 165, 250, 0.3) !important;
    color: inherit !important;
}

@media print {
    .no-print { display: none !important; }
    .timetable-table { font-size: 12px; }
    .timetable-table tbody tr:hover {
        background: transparent !important;
    }
}
</style>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8 no-print">
        <div>
            <h1 class="text-3xl font-bold">Timetable</h1>
            <p class="text-base-content/60 mt-1"><?php echo htmlspecialchars($title); ?></p>
        </div>
        <button onclick="window.print()" class="btn btn-ghost btn-sm">
            🖨️ Print
        </button>
    </div>

    <?php if (empty($timetable)): ?>
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>No timetable available yet.</span>
        </div>
    <?php else: ?>
        <!-- Timetable Grid -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title mb-4"><?php echo htmlspecialchars($title); ?></h3>
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
                                            <td class="p-2">
                                                <?php if (isset($timetable[$day][$slot])): ?>
                                                    <?php $period = $timetable[$day][$slot]; ?>
                                                    <div class="bg-primary/10 p-2 rounded">
                                                        <p class="font-semibold text-sm"><?php echo htmlspecialchars($period['subject_name']); ?></p>
                                                        <p class="text-xs text-base-content/60">
                                                            <?php if ($viewMode === 'section'): ?>
                                                                <?php echo htmlspecialchars($period['teacher_name']); ?>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($period['class_name'] . ' - ' . $period['section_name']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-base-content/30">Free</span>
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
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
