<?php
/**
 * System Settings
 * Manage application-wide configuration
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "System Settings - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

requireRole(['admin']);

$errors = [];
$success = '';
$currentUserId = getCurrentUserId();

// Handle settings update
if (isset($_POST['save_settings']) && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    try {
        beginTransaction();
        
        foreach ($_POST['settings'] as $key => $value) {
            $key = sanitize($key);
            $value = sanitize($value);
            
            // Update or insert setting
            $exists = getValue("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
            
            if ($exists) {
                query("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?",
                     [$value, $currentUserId, $key]);
            } else {
                insert("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)",
                      [$key, $value, $currentUserId]);
            }
        }
        
        commit();
        setFlash('success', 'Settings updated successfully');
        redirect('settings.php');
    } catch (Exception $e) {
        rollback();
        $errors[] = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Get all settings grouped
$allSettings = getAll("SELECT * FROM system_settings ORDER BY setting_group, setting_key");
$settingsByGroup = [];
foreach ($allSettings as $setting) {
    $settingsByGroup[$setting['setting_group']][] = $setting;
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">System Settings</h1>
        <p class="text-base-content/60 mt-1">Configure application-wide settings</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error mb-6">
            <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="space-y-6">
            <?php foreach ($settingsByGroup as $group => $settings): ?>
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title capitalize"><?php echo str_replace('_', ' ', $group); ?> Settings</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($settings as $setting): ?>
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text font-semibold">
                                            <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                        </span>
                                    </label>
                                    
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <select name="settings[<?php echo $setting['setting_key']; ?>]" class="select select-bordered select-sm">
                                            <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                            <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                                        </select>
                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                        <input type="number" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                               class="input input-bordered input-sm">
                                    <?php elseif ($setting['setting_type'] === 'email'): ?>
                                        <input type="email" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                               class="input input-bordered input-sm">
                                    <?php elseif ($setting['setting_type'] === 'url'): ?>
                                        <input type="url" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                               class="input input-bordered input-sm">
                                    <?php else: ?>
                                        <input type="text" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>" 
                                               class="input input-bordered input-sm">
                                    <?php endif; ?>
                                    
                                    <?php if ($setting['description']): ?>
                                        <label class="label">
                                            <span class="label-text-alt text-base-content/60">
                                                <?php echo htmlspecialchars($setting['description']); ?>
                                            </span>
                                        </label>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-base-content/60">
                            💡 Some settings may require page refresh or cache clear to take effect
                        </div>
                        <div class="flex gap-2">
                            <a href="<?php echo APP_URL; ?>/dashboard/admin.php" class="btn btn-ghost">Cancel</a>
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                💾 Save All Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
