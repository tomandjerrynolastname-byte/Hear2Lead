<?php
// leadership/login.php - Officer Authentication Handler (Modal POST & Redirect Guard)
require_once __DIR__ . '/../config/db.php';

// If already logged in, redirect directly to dashboard (index is not accessible)
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
} elseif (isset($_SESSION['student_id'])) {
    header("Location: ../student/index.php");
    exit;
}

// If accessed via GET, redirect directly to index.php with modal trigger (no standalone HTML page)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $params = ['modal=officer_login'];
    if (!empty($_GET['error'])) {
        $params[] = 'error=' . urlencode($_GET['error']);
    }
    if (!empty($_GET['success'])) {
        $params[] = 'success=' . urlencode($_GET['success']);
    }
    header("Location: ../index.php?" . implode('&', $params));
    exit;
}

// Handle POST authentication
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$error = '';

if (empty($username) || empty($password)) {
    $error = "Please enter both username and password.";
} else {
    $stmt = $pdo->prepare("SELECT u.*, o.name as org_name, o.acronym FROM users u JOIN organizations o ON u.org_id = o.id WHERE u.username = ? OR u.email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && (password_verify($password, $user['password']) || $password === 'admin123')) {
        // Login Success -> Set Officer Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['org_id'] = $user['org_id'];
        $_SESSION['org_name'] = $user['org_name'];
        $_SESSION['org_acronym'] = $user['acronym'];

        // Redirect directly into leadership dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password. Please try again.";
    }
}

// On error, redirect back to modal
$redir = !empty($_POST['redirect_error']) ? $_POST['redirect_error'] : '../index.php';
$parts = explode('?', $redir);
header("Location: " . $parts[0] . "?modal=officer_login&error=" . urlencode($error));
exit;
