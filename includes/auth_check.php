<?php
// includes/auth_check.php - Session guard for Leadership Portal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['org_id'])) {
    header("Location: ../index.php?modal=officer_login&error=" . urlencode("Please log in to access the Leadership Portal."));
    exit;
}
?>
