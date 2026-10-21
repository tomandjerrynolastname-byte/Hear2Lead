<?php
// includes/auth_check.php - Session guard & dynamic role permission resolver for Leadership Portal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['org_id'])) {
    header("Location: ../index.php?modal=officer_login&error=" . urlencode("Please log in to access the Leadership Portal."));
    exit;
}

if (isset($pdo) && function_exists('getRolePermissions')) {
    $stmt_sync = $pdo->prepare("SELECT role, full_name, org_id FROM users WHERE id = ? LIMIT 1");
    $stmt_sync->execute([intval($_SESSION['user_id'])]);
    if ($synced_user = $stmt_sync->fetch()) {
        $_SESSION['user_role'] = $synced_user['role'];
        $_SESSION['user_name'] = $synced_user['full_name'];
    }
    $_SESSION['role_perms'] = getRolePermissions($_SESSION['user_role'] ?? 'Officer', $pdo);

    if (empty($_SESSION['login_logged']) && function_exists('recordLoginNotification')) {
        $_SESSION['login_logged'] = true;
        $uName = $_SESSION['user_name'] ?? 'Officer';
        $uRole = $_SESSION['user_role'] ?? 'Officer';
        $oAcro = $_SESSION['org_acronym'] ?? 'ORG';
        $login_msg = "$uName ($uRole) logged in to the $oAcro Leadership Portal.";
        recordLoginNotification('Officer', intval($_SESSION['user_id']), intval($_SESSION['org_id']), $oAcro, $uName, $uRole, $login_msg, $pdo);
    }
}
?>
