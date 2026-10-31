<?php
// includes/forgot_password_handler.php - Password Reset Ticket Submission Handler
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) 
    || isset($_POST['ajax']);

function sendResponse(bool $success, string $message, array $extra = []) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit;
    }

    $redir = !empty($_POST['redirect_url']) ? $_POST['redirect_url'] : '../index.php';
    $parts = explode('?', $redir);
    $param = $success ? 'success=' . urlencode($message) : 'error=' . urlencode($message);
    header("Location: " . $parts[0] . "?modal=forgot&" . $param);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, "Invalid request method.");
}

// 1. CAPTCHA Verification
$submitted_captcha = strtoupper(trim($_POST['captcha'] ?? ''));
$stored_captcha = strtoupper(trim($_SESSION['captcha_code'] ?? ($_SESSION['student_reg_captcha'] ?? '')));

if (empty($submitted_captcha) || empty($stored_captcha) || $submitted_captcha !== $stored_captcha) {
    sendResponse(false, "Security CAPTCHA verification failed. Please enter the characters shown in the security image.");
}
// Clear captcha after successful validation
unset($_SESSION['captcha_code'], $_SESSION['student_reg_captcha']);

// 2. Validate Inputs
$account_type = strtolower(trim($_POST['account_type'] ?? 'student'));
$identifier = trim($_POST['identifier'] ?? '');
$reason = trim($_POST['reason'] ?? 'Forgot password / Request for account password reset');

if (empty($identifier)) {
    sendResponse(false, "Please provide your identifier (Student LRN/Email or Officer Username/Email).");
}

try {
    $account_id = null;
    $account_type_db = 'Student';
    $user_name = '';
    $user_email = null;
    $account_details = '';

    if ($account_type === 'leadership' || $account_type === 'officer') {
        $account_type_db = 'Leadership';
        // Leadership: Match username OR email
        $stmt = $pdo->prepare("SELECT u.id, u.org_id, u.full_name, u.username, u.email, u.role, o.name as org_name, o.acronym 
                               FROM users u 
                               JOIN organizations o ON u.org_id = o.id 
                               WHERE LOWER(TRIM(u.username)) = LOWER(?) OR (u.email IS NOT NULL AND TRIM(u.email) != '' AND LOWER(TRIM(u.email)) = LOWER(?)) 
                               LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(false, "No leadership account found matching username or email: '" . htmlspecialchars($identifier) . "'. Please double check and try again.");
        }

        $account_id = intval($user['id']);
        $user_name = $user['full_name'];
        $user_email = $user['email'];
        $account_details = "{$user['acronym']} • {$user['role']} (Username: {$user['username']})";
    } else {
        $account_type_db = 'Student';
        // Student: Match student_lrn OR email
        $stmt = $pdo->prepare("SELECT id, student_lrn, full_name, grade_section, email, status 
                               FROM students 
                               WHERE LOWER(TRIM(student_lrn)) = LOWER(?) OR (email IS NOT NULL AND TRIM(email) != '' AND LOWER(TRIM(email)) = LOWER(?)) 
                               LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $student = $stmt->fetch();

        if (!$student) {
            sendResponse(false, "No student account found matching LRN or Email: '" . htmlspecialchars($identifier) . "'. Please verify your student credentials.");
        }

        if (($student['status'] ?? '') === 'Rejected') {
            sendResponse(false, "This student account registration was previously rejected. Please contact an administrator directly.");
        }

        $account_id = intval($student['id']);
        $user_name = $student['full_name'];
        $user_email = $student['email'];
        $account_details = "LRN: {$student['student_lrn']} • " . ($student['grade_section'] ?: 'Student');
    }

    // 3. Check for existing pending ticket to prevent spam
    $chk = $pdo->prepare("SELECT id, created_at FROM password_reset_tickets WHERE account_type = ? AND account_id = ? AND status = 'Pending' LIMIT 1");
    $chk->execute([$account_type_db, $account_id]);
    $existing = $chk->fetch();

    if ($existing) {
        $formatted_date = date('M d, Y • g:i A', strtotime($existing['created_at']));
        sendResponse(false, "You already have an active password reset ticket (#{$existing['id']}) pending administrator review (Submitted on {$formatted_date}). Please wait for the administrator to reset your password or contact them directly.", [
            'existing_ticket_id' => $existing['id'],
            'is_duplicate' => true
        ]);
    }

    // 4. Create new Ticket
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ins = $pdo->prepare("INSERT INTO password_reset_tickets 
        (account_type, account_id, identifier_submitted, user_name, user_email, account_details, request_reason, status, ip_address, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())");
    
    $ins->execute([
        $account_type_db,
        $account_id,
        $identifier,
        $user_name,
        $user_email,
        $account_details,
        $reason,
        $ip
    ]);

    $ticket_id = intval($pdo->lastInsertId());

    sendResponse(true, "Your password reset ticket has been submitted to the system administrator.", [
        'ticket_id' => $ticket_id,
        'user_name' => $user_name,
        'account_type' => $account_type_db,
        'account_details' => $account_details
    ]);

} catch (PDOException $e) {
    sendResponse(false, "A database error occurred while submitting your ticket: " . $e->getMessage());
}
