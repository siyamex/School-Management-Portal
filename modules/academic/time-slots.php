<?php
/**
 * Time Slots Management
 * Configure school periods and break times
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Time Slots - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin', 'principal']);

$errors = [];
$success = '';

// Handle create
if (isset($_POST['create_slot']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $slotName = sanitize($_POST['slot_name'] ?? '');
    $startTime = sanitize($_POST['start_time'] ?? '');
    $endTime = sanitize($_POST['end_time'] ?? '');
    $slotType = sanitize($_POST['slot_type'] ?? 'class');
    $sortOrder = (int)$_POST['sort_order'];
    
    if (empty($slotName) || empty($startTime) || empty($endTime)) {
        $errors[] = 'All fields are required';
    } else {
        try {
            insert("INSERT INTO time_slots (slot_name, start_time, end_time, slot_type, sort_order) VALUES (?, ?, ?, ?, ?)",
                  [$slotName, $startTime, $endTime, $slotType, $sortOrder]);
            setFlash('success', 'Time slot created');
            redirect('time-slots.php');
        } catch (Exception $e) {
            $errors[] = 'Failed to create: ' . $e->getMessage();
        }
    }
}

// Handle update
if (isset($_POST['update_slot']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $slotId = (int)$_POST['slot_id'];
    $slotName = sanitize($_POST['slot_name'] ?? '');
    $startTime = sanitize($_POST['start_time'] ?? '');
    $endTime = sanitize($_POST['end_time'] ?? '');
    $slotType = sanitize($_POST['slot_type'] ?? 'class');
    $sortOrder = (int)$_POST['sort_order'];
    
    try {
        query("UPDATE time_slots SET slot_name = ?, start_time = ?, end_time = ?, slot_type = ?, sort_order = ? WHERE id = ?",
             [$slotName, $startTime, $endTime, $slotType, $sortOrder, $slotId]);
        setFlash('success', 'Time slot updated');
        redirect('time-slots.php');
    } catch (Exception $e) {
        $errors[] = 'Failed to update: ' . $e->getMessage();
    }
}

// Handle delete
if (isset($_POST['delete_slot']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $slotId = (int)$_POST['slot_id'];
    try {
        query("DELETE FROM time_slots WHERE id = ?", [$slotId]);
        setFlash('success', 'Time slot deleted');
        redirect('time-slots.php');
    } catch (Exception $e) {
        $errors[] = 'Cannot delete: ' . $e->getMessage();
    }
}

// Handle toggle active
if (isset($_POST['toggle_active']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $slotId = (int)$_POST['slot_id'];
    $isActive = (int)$_POST['is_active'];
    query("UPDATE time_slots SET is_active = ? WHERE id = ?", [$isActive, $slotId]);
    setFlash('success', 'Status updated');
    redirect('time-slots.php');
}

// Get all time slots
$timeSlots = getAll("SELECT * FROM time_slots ORDER BY sort_order, start_time");
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold">Time Slots</h1>
            <p class="text-base-content/60 mt-1">Configure school periods and break times</p>
        </div>
        <button onclick="create_modal.showModal()" class="btn btn-primary gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Add Time Slot
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Slot Name</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Duration</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeSlots as $slot): ?>
                            <?php
                            $start = new DateTime($slot['start_time']);
                            $end = new DateTime($slot['end_time']);
                            $duration = $start->diff($end);
                            ?>
                            <tr class="<?php echo $slot['is_active'] ? '' : 'opacity-50'; ?>">
                                <td><?php echo $slot['sort_order']; ?></td>
                                <td>
                                    <span class="font-semibold"><?php echo htmlspecialchars($slot['slot_name']); ?></span>
                                </td>
                                <td><?php echo $start->format('h:i A'); ?></td>
                                <td><?php echo $end->format('h:i A'); ?></td>
                                <td class="text-sm text-base-content/60"><?php echo $duration->i; ?> mins</td>
                                <td>
                                    <?php if ($slot['slot_type'] === 'break'): ?>
                                        <span class="badge badge-warning">Break</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">Class</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $slot['is_active'] ? 0 : 1; ?>">
                                        <button type="submit" name="toggle_active" class="btn btn-xs <?php echo $slot['is_active'] ? 'btn-success' : 'btn-ghost'; ?>">
                                            <?php echo $slot['is_active'] ? '✓ Active' : 'Inactive'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($slot)); ?>)" class="btn btn-sm btn-ghost">
                                        ✏️ Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this time slot?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                        <button type="submit" name="delete_slot" class="btn btn-sm btn-error btn-ghost">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Create Modal -->
<dialog id="create_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Add Time Slot</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Slot Name</span></label>
                <input type="text" name="slot_name" placeholder="e.g., Period 1, Break" class="input input-bordered" required>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Start Time</span></label>
                    <input type="time" name="start_time" class="input input-bordered" required>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text">End Time</span></label>
                    <input type="time" name="end_time" class="input input-bordered" required>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Type</span></label>
                    <select name="slot_type" class="select select-bordered">
                        <option value="class">Class Period</option>
                        <option value="break">Break/Lunch</option>
                    </select>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text">Sort Order</span></label>
                    <input type="number" name="sort_order" value="<?php echo count($timeSlots) + 1; ?>" class="input input-bordered" required>
                </div>
            </div>
            
            <div class="modal-action">
                <button type="button" onclick="create_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="create_slot" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Edit Modal -->
<dialog id="edit_modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Edit Time Slot</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="slot_id" id="edit_slot_id">
            
            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Slot Name</span></label>
                <input type="text" name="slot_name" id="edit_slot_name" class="input input-bordered" required>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Start Time</span></label>
                    <input type="time" name="start_time" id="edit_start_time" class="input input-bordered" required>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text">End Time</span></label>
                    <input type="time" name="end_time" id="edit_end_time" class="input input-bordered" required>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="form-control">
                    <label class="label"><span class="label-text">Type</span></label>
                    <select name="slot_type" id="edit_slot_type" class="select select-bordered">
                        <option value="class">Class Period</option>
                        <option value="break">Break/Lunch</option>
                    </select>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text">Sort Order</span></label>
                    <input type="number" name="sort_order" id="edit_sort_order" class="input input-bordered" required>
                </div</div>
            
            <div class="modal-action">
                <button type="button" onclick="edit_modal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" name="update_slot" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
function showEditModal(slot) {
    document.getElementById('edit_slot_id').value = slot.id;
    document.getElementById('edit_slot_name').value = slot.slot_name;
    document.getElementById('edit_start_time').value = slot.start_time;
    document.getElementById('edit_end_time').value = slot.end_time;
    document.getElementById('edit_slot_type').value = slot.slot_type;
    document.getElementById('edit_sort_order').value = slot.sort_order;
    edit_modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
