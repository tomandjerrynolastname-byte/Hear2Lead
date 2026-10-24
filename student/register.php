<?php
// student/register.php - Student Registration Handler (Modal POST & Redirect Guard with CAPTCHA, Strong Passwords, Unique LRN & Admin Approval)
require_once __DIR__ . '/../config/db.php';

// If already logged in, redirect directly to portal
if (isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit;
} elseif (isset($_SESSION['user_id'])) {
    header("Location: ../leadership/dashboard.php");
    exit;
}

// If accessed via GET, redirect directly to index.php with modal trigger
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $params = ['modal=register'];
    if (!empty($_GET['error'])) {
        $params[] = 'error=' . urlencode($_GET['error']);
    }
    header("Location: ../index.php?" . implode('&', $params));
    exit;
}

// Retrieve form inputs
$student_lrn = trim($_POST['student_lrn'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$grade_section = trim($_POST['grade_section'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$captcha_input = trim($_POST['captcha'] ?? '');
$expected_captcha = $_SESSION['student_reg_captcha'] ?? '';

$error = '';

// 1. CAPTCHA Validation
if (empty($expected_captcha) || empty($captcha_input) || strtoupper($captcha_input) !== strtoupper($expected_captcha)) {
    $error = "Invalid or expired CAPTCHA code. Please re-enter the characters shown in the security image.";
    // Reset captcha on failure to prevent brute forcing
    unset($_SESSION['student_reg_captcha']);
} elseif (empty($student_lrn) || empty($full_name) || empty($grade_section) || empty($password)) {
    $error = "Please fill in all required fields (Student LRN, Full Name, Grade & Section, and Password).";
} elseif (strlen($student_lrn) < 3) {
    $error = "Please enter a valid Student LRN / ID.";
} elseif ($password !== $confirm_password) {
    $error = "Passwords do not match. Please verify both password fields.";
} else {
    // 2. Strong Password Policies Validation
    $has_min_len = strlen($password) >= 8;
    $has_upper = preg_match('/[A-Z]/', $password);
    $has_lower = preg_match('/[a-z]/', $password);
    $has_number = preg_match('/[0-9]/', $password);
    $has_special = preg_match('/[^A-Za-z0-9]/', $password);

    if (!$has_min_len) {
        $error = "Password must be at least 8 characters in length.";
    } elseif (!$has_upper) {
        $error = "Password must contain at least one uppercase letter (A-Z).";
    } elseif (!$has_lower) {
        $error = "Password must contain at least one lowercase letter (a-z).";
    } elseif (!$has_number) {
        $error = "Password must contain at least one number (0-9).";
    } elseif (!$has_special) {
        $error = "Password must contain at least one special character (e.g. !@#$%^&*()_+-=[]{}).";
    } else {
        // 3. Unique LRN Check
        $checkLrnStmt = $pdo->prepare("SELECT id, status FROM students WHERE LOWER(TRIM(student_lrn)) = LOWER(?) LIMIT 1");
        $checkLrnStmt->execute([$student_lrn]);
        $existingStudent = $checkLrnStmt->fetch();

        if ($existingStudent) {
            $error = "The Student LRN '" . htmlspecialchars($student_lrn) . "' is already registered in the system. If you already have an account, please sign in or contact the administrator.";
        } else {
            // Check Unique Email if provided
            if (!empty($email)) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please enter a valid email address.";
                } else {
                    $checkEmailStmt = $pdo->prepare("SELECT id FROM students WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1");
                    $checkEmailStmt->execute([$email]);
                    if ($checkEmailStmt->fetch()) {
                        $error = "The email address '" . htmlspecialchars($email) . "' is already in use by another student account.";
                    }
                }
            }

            if (empty($error)) {
                // 4. Secure Password Hashing & Insertion with 'Pending' Status
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO students (student_lrn, full_name, grade_section, email, password, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");

                if ($stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $hashed])) {
                    $new_student_id = intval($pdo->lastInsertId());
                    if (function_exists('recordStudentAccountLog')) {
                        $log_msg = "Student self-registered with LRN '{$student_lrn}' ({$grade_section}). Initial status set to 'Pending' awaiting administrator approval.";
                        recordStudentAccountLog($new_student_id, $student_lrn, $full_name, null, $full_name, "Student • {$grade_section}", 'Registered (Pending)', null, 'Pending', $log_msg, $pdo);
                    }
                    // Invalidate CAPTCHA after successful use
                    unset($_SESSION['student_reg_captcha']);

                    // DO NOT log the student in automatically.
                    // Account requires Administrator approval before activation.
                    $redir = !empty($_POST['redirect_error']) ? $_POST['redirect_error'] : '../index.php';
                    $parts = explode('?', $redir);
                    
                    header("Location: " . $parts[0] . "?modal=student_login&registered_pending=1&lrn=" . urlencode($student_lrn));
                    exit;
                } else {
                    $error = "Failed to create student account. Please try again or contact the administrator.";
                }
            }
        }
    }
}

// On error, redirect back to modal with error and sticky inputs
// Invalidate CAPTCHA so fresh one is needed
unset($_SESSION['student_reg_captcha']);

$redir = !empty($_POST['redirect_error']) ? $_POST['redirect_error'] : '../index.php';
$parts = explode('?', $redir);
$err_params = [
    'modal=register',
    'error=' . urlencode($error),
    'lrn=' . urlencode($student_lrn),
    'name=' . urlencode($full_name),
    'grade=' . urlencode($grade_section),
    'email=' . urlencode($email)
];
header("Location: " . $parts[0] . "?" . implode('&', $err_params));
exit;
