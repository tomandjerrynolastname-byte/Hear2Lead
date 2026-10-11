<?php
// student/register.php - Student Registration Handler (Modal POST & Redirect Guard)
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
    $params = ['modal=register'];
    if (!empty($_GET['error'])) {
        $params[] = 'error=' . urlencode($_GET['error']);
    }
    header("Location: ../index.php?" . implode('&', $params));
    exit;
}

// Handle POST registration
$student_lrn = trim($_POST['student_lrn'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$grade_section = trim($_POST['grade_section'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$error = '';

if (empty($student_lrn) || empty($full_name) || empty($grade_section) || empty($password)) {
    $error = "Please fill in all required fields (LRN, Full Name, Grade & Section, Password).";
} elseif (strlen($password) < 6) {
    $error = "Password must be at least 6 characters long.";
} elseif ($password !== $confirm_password) {
    $error = "Passwords do not match.";
} else {
    // Check if LRN already exists
    $checkStmt = $pdo->prepare("SELECT id FROM students WHERE student_lrn = ?");
    $checkStmt->execute([$student_lrn]);
    if ($checkStmt->fetch()) {
        $error = "A student with this LRN / ID is already registered.";
    } else {
        // Hash password and insert
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO students (student_lrn, full_name, grade_section, email, password) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $hashed])) {
            // Auto log-in student
            $_SESSION['student_id'] = $pdo->lastInsertId();
            $_SESSION['student_lrn'] = $student_lrn;
            $_SESSION['student_name'] = $full_name;
            $_SESSION['student_grade_section'] = $grade_section;
            $_SESSION['student_email'] = $email ?: null;

            // Redirect directly to student portal with registered flag
            header("Location: index.php?registered=1");
            exit;
        } else {
            $error = "Failed to register account. Please try again.";
        }
    }
}

// On error, redirect back to modal
$redir = !empty($_POST['redirect_error']) ? $_POST['redirect_error'] : '../index.php';
$parts = explode('?', $redir);
header("Location: " . $parts[0] . "?modal=register&error=" . urlencode($error));
exit;
