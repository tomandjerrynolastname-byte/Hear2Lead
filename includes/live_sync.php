<?php
// includes/live_sync.php - Real-Time Data Tunneling & Change Fingerprint Endpoint
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$is_authenticated = isset($_SESSION['user_id']) || isset($_SESSION['student_id']);
$user_id = intval($_SESSION['user_id'] ?? 0);
$student_id = intval($_SESSION['student_id'] ?? 0);

// Release session lock immediately so background polling never blocks page navigation
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $sql = "SELECT
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(CRC32(CONCAT_WS('|', title, COALESCE(description,''), status, priority, deadline, COALESCE(assigned_to,''), COALESCE(project_id,0)))),0)) FROM tasks) AS tasks_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0)) FROM task_logs) AS task_logs_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(progress_percent),0), COALESCE(SUM(CRC32(CONCAT_WS('|', title, COALESCE(description,''), status, COALESCE(start_date,''), COALESCE(target_date,'')))) ,0)) FROM projects) AS projects_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0)) FROM project_logs) AS project_logs_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(CRC32(CONCAT_WS('|', title, COALESCE(description,''), COALESCE(impact_summary,''), date_accomplished, COALESCE(badge_tag,''), COALESCE(attachments_json,'')))),0)) FROM accomplishments) AS acc_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0)) FROM accomplishment_logs) AS acc_logs_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(CRC32(CONCAT_WS('|', status, COALESCE(officer_response,''), COALESCE(responded_at,'')))),0)) FROM feedbacks) AS fb_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0)) FROM feedback_logs) AS fb_logs_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(CRC32(CONCAT_WS('|', full_name, username, email, role, org_id))),0)) FROM users) AS users_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(CRC32(CONCAT_WS('|', role_name, base_role, badge_style, can_manage_users, can_manage_tasks, can_manage_projects, can_manage_accomplishments, can_manage_feedbacks, can_view_reports, can_manage_orgs))),0)) FROM user_roles) AS roles_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(CRC32(CONCAT_WS('|', name, acronym, COALESCE(logo,''), COALESCE(description,''), COALESCE(category,'')))),0)) FROM organizations) AS orgs_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0), COALESCE(SUM(CRC32(CONCAT_WS('|', student_lrn, full_name, grade_section, COALESCE(email,''), status, COALESCE(approved_by,0)))),0)) FROM students) AS students_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0)) FROM student_logs) AS student_logs_fp,
        (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id),0)) FROM login_logs) AS login_logs_fp
    ";

    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    $modules = [
        'tasks'           => md5(($row['tasks_fp'] ?? '') . '|' . ($row['task_logs_fp'] ?? '')),
        'projects'        => md5(($row['projects_fp'] ?? '') . '|' . ($row['project_logs_fp'] ?? '')),
        'accomplishments' => md5(($row['acc_fp'] ?? '') . '|' . ($row['acc_logs_fp'] ?? '')),
        'feedbacks'       => md5(($row['fb_fp'] ?? '') . '|' . ($row['fb_logs_fp'] ?? '')),
        'users'           => md5(($row['users_fp'] ?? '') . '|' . ($row['roles_fp'] ?? '') . '|' . ($row['students_fp'] ?? '') . '|' . ($row['student_logs_fp'] ?? '')),
        'organizations'   => md5($row['orgs_fp'] ?? ''),
        'notifications'   => md5(($row['login_logs_fp'] ?? '') . '|' . ($row['task_logs_fp'] ?? '') . '|' . ($row['project_logs_fp'] ?? '') . '|' . ($row['acc_logs_fp'] ?? '') . '|' . ($row['student_logs_fp'] ?? ''))
    ];

    $state_hash = md5(implode('|', $modules));

    echo json_encode([
        'status'        => 'ok',
        'authenticated' => $is_authenticated,
        'state_hash'    => $state_hash,
        'modules'       => $modules,
        'timestamp'     => time()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Sync check failed'
    ]);
}
