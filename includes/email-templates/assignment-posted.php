<?php include 'header.php'; ?>

<h2>📝 New Assignment Posted</h2>

<p>Hello <strong><?php echo htmlspecialchars($student_name); ?></strong>,</p>

<p>A new assignment has been posted in <strong><?php echo htmlspecialchars($subject_name); ?></strong>:</p>

<div style="background: #f9f9f9; padding: 20px; border-left: 4px solid #667eea; margin: 20px 0;">
    <h3 style="margin-top: 0;"><?php echo htmlspecialchars($assignment_title); ?></h3>
    <p><?php echo nl2br(htmlspecialchars($assignment_description)); ?></p>
    <p><strong>Due Date:</strong> <?php echo formatDate($due_date, 'F j, Y'); ?></p>
    <p><strong>Total Points:</strong> <?php echo $total_points; ?></p>
</div>

<p>Don't forget to submit your work before the deadline!</p>

<a href="<?php echo APP_URL; ?>/modules/lms/student-assignments.php" class="button">View Assignment</a>

<?php include 'footer.php'; ?>
