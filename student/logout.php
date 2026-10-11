<?php
// student/logout.php - Logout student session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['student_id']);
unset($_SESSION['student_lrn']);
unset($_SESSION['student_name']);
unset($_SESSION['student_grade_section']);
unset($_SESSION['student_email']);

header("Location: ../index.php?success=" . urlencode("You have been logged out successfully."));
exit;
