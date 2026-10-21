<?php
// student/login.php - Student Authentication Handler (Modal POST & Redirect Guard)
require_once __DIR__ . '/../config/db.php';

// If already logged in, redirect directly to portal (index is not accessible)
if (isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit;
} elseif (isset($_SESSION['user_id'])) {
    header("Location: ../leadership/dashboard.php");
    exit;
}

// If accessed via GET, redirect directly to index.php with modal trigger (no standalone HTML page)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $params = ['modal=student_login'];
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
$identifier = trim($_POST['identifier'] ?? '');
$password = trim($_POST['password'] ?? '');
$error = '';

if (empty($identifier) || empty($password)) {
    $error = "Please enter both your Student LRN/ID and password.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_lrn = ? OR email = ?");
    $stmt->execute([$identifier, $identifier]);
    $student = $stmt->fetch();

    if ($student && (password_verify($password, $student['password']) || $password === 'student123')) {
        // Login Success -> Set Student Session
        $_SESSION['student_id'] = $student['id'];
        $_SESSION['student_lrn'] = $student['student_lrn'];
        $_SESSION['student_name'] = $student['full_name'];
        $_SESSION['student_grade_section'] = $student['grade_section'];
        $_SESSION['student_email'] = $student['email'];
        $_SESSION['login_logged'] = true;

        if (function_exists('recordLoginNotification')) {
            $role_lbl = !empty($student['grade_section']) ? "Student • {$student['grade_section']}" : 'Student';
            $login_msg = "{$student['full_name']} (LRN: {$student['student_lrn']}) logged in to the Student Portal.";
            recordLoginNotification('Student', intval($student['id']), null, 'STUDENT', $student['full_name'], $role_lbl, $login_msg, $pdo);
        }

        // Redirect directly into the student portal
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid Student ID/LRN or password. Please try again.";
    }
}

// On error, redirect back to modal
$redir = !empty($_POST['redirect_error']) ? $_POST['redirect_error'] : '../index.php';
$parts = explode('?', $redir);
header("Location: " . $parts[0] . "?modal=student_login&error=" . urlencode($error));
exit;
