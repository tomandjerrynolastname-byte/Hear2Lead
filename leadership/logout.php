<?php
// leadership/logout.php - Session destruction & logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_role']);
unset($_SESSION['org_id']);
unset($_SESSION['org_name']);
unset($_SESSION['org_acronym']);

if (!isset($_SESSION['student_id'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

header("Location: ../index.php?success=" . urlencode("Logged out successfully."));
exit;
?>
