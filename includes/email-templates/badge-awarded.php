<?php include 'header.php'; ?>

<h2>🏆 Badge Awarded!</h2>

<p>Congratulations <strong><?php echo htmlspecialchars($student_name); ?></strong>!</p>

<p>You've been awarded a new badge:</p>

<div style="background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%); padding: 30px; border-radius: 8px; margin: 20px 0; text-align: center;">
    <?php if (!empty($badge_icon)): ?>
        <img src="<?php echo APP_URL . '/' . $badge_icon; ?>" alt="Badge" style="width: 100px; height: 100px; margin-bottom: 10px;">
    <?php else: ?>
        <div style="font-size: 80px; margin-bottom: 10px;">🏅</div>
    <?php endif; ?>
    <h3 style="margin: 10px 0; color: #2d3436;"><?php echo htmlspecialchars($badge_name); ?></h3>
    <p style="color: #636e72;"><?php echo htmlspecialchars($badge_description); ?></p>
    <?php if (!empty($award_reason)): ?>
        <p style="margin-top: 15px; font-style: italic; color: #2d3436;"><strong>Reason:</strong> <?php echo htmlspecialchars($award_reason); ?></p>
    <?php endif; ?>
</div>

<p>Keep up the excellent work and continue achieving great things!</p>

<a href="<?php echo APP_URL; ?>/modules/lms/my-reading-log.php" class="button">View My Badges</a>

<?php include 'footer.php'; ?>
