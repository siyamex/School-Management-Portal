<?php
/**
 * Badge & Achievement System
 * Gamification for student motivation
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Achievements - " . APP_NAME;
require_once __DIR__ . '/../../includes/header.php';

$currentUser = getCurrentUser();
$userRoles = explode(',', $currentUser['roles']);
$isStudent = in_array('student', $userRoles);

if ($isStudent) {
    $studentId = getValue("SELECT id FROM students WHERE user_id = ?", [getCurrentUserId()]);
    
    // Get student's badges
    $earnedBadges = getAll("
        SELECT sb.*, b.name, b.description, b.icon, b.category
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        WHERE sb.student_id = ?
        ORDER BY sb.created_at DESC
    ", [$studentId]);
    
    // Get all available badges
    $allBadges = getAll("SELECT * FROM badges ORDER BY category, name");
} else {
    // Teachers/admins see recent badges
    $earnedBadges = getAll("
        SELECT sb.*, b.name, b.description, b.icon, st.student_id, u.full_name
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        JOIN students st ON sb.student_id = st.id
        JOIN users u ON st.user_id = u.id
        ORDER BY sb.created_at DESC
        LIMIT 50
    ");
    $allBadges = [];
}
?>

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="flex-1 p-6 lg:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">🏆 Achievements & Badges</h1>
        <p class="text-base-content/60 mt-1"><?php echo $isStudent ? 'Your earned badges' : 'Recent student achievements'; ?></p>
    </div>

    <?php if ($isStudent): ?>
        <!-- Student View -->
        <div class="stats stats-vertical lg:stats-horizontal shadow mb-6 w-full">
            <div class="stat">
                <div class="stat-title">Badges Earned</div>
                <div class="stat-value text-primary"><?php echo count($earnedBadges); ?></div>
                <div class="stat-desc">out of <?php echo count($allBadges); ?> total</div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($allBadges as $badge): 
                $earned = array_filter($earnedBadges, fn($b) => $b['badge_id'] == $badge['id']);
                $isEarned = !empty($earned);
                $earnedDate = $isEarned ? reset($earned)['created_at'] : null;
            ?>
                <div class="card bg-base-100 shadow-xl <?php echo !$isEarned ? 'opacity-40' : ''; ?>">
                    <div class="card-body items-center text-center p-4">
                        <div class="text-5xl mb-2"><?php echo $badge['icon'] ?: '🏅'; ?></div>
                        <h3 class="text-sm font-bold"><?php echo htmlspecialchars($badge['name']); ?></h3>
                        <p class="text-xs text-base-content/60"><?php echo htmlspecialchars($badge['description']); ?></p>
                        <?php if ($isEarned): ?>
                            <span class="badge badge-success badge-xs mt-2">
                                <?php echo formatDate($earnedDate, 'M d'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Teacher/Admin View -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h3 class="card-title">Recent Achievements</h3>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Badge</th>
                                <th>Description</th>
                                <th>Earned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($earnedBadges)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-base-content/60">No badges awarded yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($earnedBadges as $badge): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <div class="font-semibold"><?php echo htmlspecialchars($badge['full_name']); ?></div>
                                                <div class="text-xs text-base-content/60"><?php echo htmlspecialchars($badge['student_id']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-2xl"><?php echo $badge['icon'] ?: '🏅'; ?></span>
                                            <?php echo htmlspecialchars($badge['name']); ?>
                                        </td>
                                        <td class="text-sm"><?php echo htmlspecialchars($badge['description']); ?></td>
                                        <td><?php echo formatDate($badge['created_at'], 'M d, Y'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
