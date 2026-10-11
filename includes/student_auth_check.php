<?php
// includes/student_auth_check.php - Session guard for Student Portal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow access if logged in as a student OR logged in as an officer/leader
$is_student = isset($_SESSION['student_id']);
$is_officer = isset($_SESSION['user_id']);

if (!$is_student && !$is_officer) {
    header("Location: ../index.php?modal=student_login&error=" . urlencode("Please log in to access the Student Portal."));
    exit;
}
?>
