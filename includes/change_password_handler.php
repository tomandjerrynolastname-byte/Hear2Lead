<?php
// includes/change_password_handler.php - Secure Change Password Handler for Logged-In Users
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) 
    || isset($_POST['ajax']);

function sendChangePassResponse(bool $success, string $message) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }

    $redir = !empty($_POST['redirect_url']) ? $_POST['redirect_url'] : ($_SERVER['HTTP_REFERER'] ?? '../index.php');
    $parts = explode('?', $redir);
    $param = $success ? 'pass_success=' . urlencode($message) : 'pass_error=' . urlencode($message);
    header("Location: " . $parts[0] . "?" . $param);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendChangePassResponse(false, "Invalid request method.");
}

$is_officer = isset($_SESSION['user_id']);
$is_student = isset($_SESSION['student_id']);

if (!$is_officer && !$is_student) {
    sendChangePassResponse(false, "Authentication required. Please log in first.");
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    sendChangePassResponse(false, "All password fields are required.");
}

if ($new_password !== $confirm_password) {
    sendChangePassResponse(false, "The new password and confirmation password do not match.");
}

if (strlen($new_password) < 8) {
    sendChangePassResponse(false, "New password must be at least 8 characters long.");
}

if (!preg_match('/[A-Z]/', $new_password)) {
    sendChangePassResponse(false, "New password must contain at least one uppercase letter (A-Z).");
}

if (!preg_match('/[a-z]/', $new_password)) {
    sendChangePassResponse(false, "New password must contain at least one lowercase letter (a-z).");
}

if (!preg_match('/[0-9]/', $new_password)) {
    sendChangePassResponse(false, "New password must contain at least one number (0-9).");
}

if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
    sendChangePassResponse(false, "New password must contain at least one special symbol (!@#$%^&*...).");
}

try {
    if ($is_officer) {
        $user_id = intval($_SESSION['user_id']);
        $stmt = $pdo->prepare("SELECT id, password, full_name, username, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            sendChangePassResponse(false, "Officer account not found.");
        }

        if (!password_verify($current_password, $user['password'])) {
            sendChangePassResponse(false, "The current password you entered is incorrect.");
        }

        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$hashed, $user_id]);

        sendChangePassResponse(true, "Your password has been changed successfully!");

    } elseif ($is_student) {
        $student_id = intval($_SESSION['student_id']);
        $stmt = $pdo->prepare("SELECT id, password, full_name, student_lrn FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();

        if (!$student) {
            sendChangePassResponse(false, "Student account not found.");
        }

        if (!password_verify($current_password, $student['password'])) {
            sendChangePassResponse(false, "The current password you entered is incorrect.");
        }

        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
        $upd->execute([$hashed, $student_id]);

        sendChangePassResponse(true, "Your student account password has been changed successfully!");
    }
} catch (PDOException $e) {
    sendChangePassResponse(false, "Failed to update password: " . $e->getMessage());
}
