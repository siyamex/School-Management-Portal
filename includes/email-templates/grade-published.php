<?php include 'header.php'; ?>

<h2>📊 Grade Published</h2>

<p>Hello <strong><?php echo htmlspecialchars($student_name); ?></strong>,</p>

<p>Your grade for <strong><?php echo htmlspecialchars($exam_name); ?></strong> in <strong><?php echo htmlspecialchars($subject_name); ?></strong> has been published:</p>

<div style="background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
    <h1 style="color: #667eea; margin: 0; font-size: 48px;"><?php echo htmlspecialchars($grade_letter); ?></h1>
    <p style="font-size: 18px; margin: 10px 0;"><strong><?php echo $marks_obtained; ?></strong> out of <strong><?php echo $total_marks; ?></strong></p>
    <?php if (!empty($remarks)): ?>
        <p style="margin: 15px 0; font-style: italic;">"<?php echo htmlspecialchars($remarks); ?>"</p>
    <?php endif; ?>
</div>

<p>Keep up the good work!</p>

<a href="<?php echo APP_URL; ?>/modules/exams/view-grades.php" class="button">View All Grades</a>

<?php include 'footer.php'; ?>
