<?php include 'header.php'; ?>

<h2>✅ Assignment Submitted</h2>

<p>Hello <strong><?php echo htmlspecialchars($student_name); ?></strong>,</p>

<p>Your assignment has been successfully submitted!</p>

<div style="background: #d5f4e6; padding: 20px; border-left: 4px solid #00b894; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #00b894;">Submission Details</h3>
    <p><strong>Assignment:</strong> <?php echo htmlspecialchars($assignment_title); ?></p>
    <p><strong>Subject:</strong> <?php echo htmlspecialchars($subject_name); ?></p>
    <p><strong>Submitted at:</strong> <?php echo formatDateTime($submitted_at, 'F j, Y h:i A'); ?></p>
    <p><strong>Status:</strong> <span style="color: #00b894;">Submitted</span></p>
</div>

<p>Your teacher will review your work and provide feedback soon.</p>

<a href="<?php echo APP_URL; ?>/modules/lms/student-assignments.php" class="button">View Assignments</a>

<?php include 'footer.php'; ?>
