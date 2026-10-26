<?php
// leadership/print_report.php - Official Printable Leadership Performance & Accomplishment Report Template
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_role = $_SESSION['user_role'] ?? 'Officer';
$role_perms = getRolePermissions($current_role, $pdo);
$is_admin = !empty($role_perms['is_admin']);
if (empty($role_perms['can_view_reports'])) {
    header("Location: dashboard.php?error=" . urlencode("Access denied. Your role ($current_role) does not have access to Performance Reports."));
    exit;
}

$current_org_id = intval($_SESSION['org_id']);
$selected_org_id = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;

// Filter parameters passed from reports.php or toolbar
$filter_date = trim($_GET['filter_date'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$active_log_tab = trim($_GET['log_tab'] ?? 'all_logs');
if (!in_array($active_log_tab, ['all_logs', 'task_logs', 'project_logs', 'acc_logs', 'student_logs'], true)) {
    $active_log_tab = 'all_logs';
}
$auto_print = isset($_GET['auto_print']) && $_GET['auto_print'] === '1';

// Fetch all orgs for Admin filter dropdown
$all_orgs = $pdo->query("SELECT id, name, acronym, logo, category FROM organizations ORDER BY acronym ASC")->fetchAll();

// Determine active Organization display details
$active_org_logo = null;
$active_org_category = 'Consolidated Multi-Organization Scope';
if ($selected_org_id > 0) {
    $stmt_org = $pdo->prepare("SELECT name, acronym, logo, category FROM organizations WHERE id = ?");
    $stmt_org->execute([$selected_org_id]);
    $org_data = $stmt_org->fetch();
    $active_org_name = $org_data['name'] ?? ($_SESSION['org_name'] ?? 'Organization');
    $active_org_acronym = $org_data['acronym'] ?? ($_SESSION['org_acronym'] ?? 'ORG');
    $active_org_logo = $org_data['logo'] ?? null;
    $active_org_category = $org_data['category'] ?? 'Student Organization';
} else {
    $active_org_name = 'All Student Organizations (Consolidated Report)';
    $active_org_acronym = 'System-wide';
}

// Build reusable SQL conditions based on active filters
$task_conds = ["1=1"];
$task_params = [];
if ($selected_org_id > 0) {
    $task_conds[] = "t.org_id = ?";
    $task_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $task_conds[] = "(DATE(t.deadline) = ? OR DATE(t.created_at) = ?)";
    $task_params[] = $filter_date;
    $task_params[] = $filter_date;
}
if (!empty($date_from)) {
    $task_conds[] = "(DATE(t.deadline) >= ? OR DATE(t.created_at) >= ?)";
    $task_params[] = $date_from;
    $task_params[] = $date_from;
}
if (!empty($date_to)) {
    $task_conds[] = "(DATE(t.deadline) <= ? OR DATE(t.created_at) <= ?)";
    $task_params[] = $date_to;
    $task_params[] = $date_to;
}
$task_where_sql = implode(" AND ", $task_conds);

$proj_conds = ["1=1"];
$proj_params = [];
if ($selected_org_id > 0) {
    $proj_conds[] = "p.org_id = ?";
    $proj_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $proj_conds[] = "(DATE(p.target_date) = ? OR DATE(p.start_date) = ? OR DATE(p.created_at) = ?)";
    $proj_params[] = $filter_date;
    $proj_params[] = $filter_date;
    $proj_params[] = $filter_date;
}
if (!empty($date_from)) {
    $proj_conds[] = "(DATE(p.target_date) >= ? OR DATE(p.start_date) >= ? OR DATE(p.created_at) >= ?)";
    $proj_params[] = $date_from;
    $proj_params[] = $date_from;
    $proj_params[] = $date_from;
}
if (!empty($date_to)) {
    $proj_conds[] = "(DATE(p.target_date) <= ? OR DATE(p.start_date) <= ? OR DATE(p.created_at) <= ?)";
    $proj_params[] = $date_to;
    $proj_params[] = $date_to;
    $proj_params[] = $date_to;
}
$proj_where_sql = implode(" AND ", $proj_conds);

$fb_conds = ["1=1"];
$fb_params = [];
if ($selected_org_id > 0) {
    $fb_conds[] = "f.org_id = ?";
    $fb_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $fb_conds[] = "DATE(f.created_at) = ?";
    $fb_params[] = $filter_date;
}
if (!empty($date_from)) {
    $fb_conds[] = "DATE(f.created_at) >= ?";
    $fb_params[] = $date_from;
}
if (!empty($date_to)) {
    $fb_conds[] = "DATE(f.created_at) <= ?";
    $fb_params[] = $date_to;
}
$fb_where_sql = implode(" AND ", $fb_conds);

$acc_conds = ["1=1"];
$acc_params = [];
if ($selected_org_id > 0) {
    $acc_conds[] = "a.org_id = ?";
    $acc_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $acc_conds[] = "(DATE(a.date_accomplished) = ? OR DATE(a.created_at) = ?)";
    $acc_params[] = $filter_date;
    $acc_params[] = $filter_date;
}
if (!empty($date_from)) {
    $acc_conds[] = "(DATE(a.date_accomplished) >= ? OR DATE(a.created_at) >= ?)";
    $acc_params[] = $date_from;
    $acc_params[] = $date_from;
}
if (!empty($date_to)) {
    $acc_conds[] = "(DATE(a.date_accomplished) <= ? OR DATE(a.created_at) <= ?)";
    $acc_params[] = $date_to;
    $acc_params[] = $date_to;
}
$acc_where_sql = implode(" AND ", $acc_conds);

// ----------------------------------------------------
// 1. Overall Performance Statistics (Filtered)
// ----------------------------------------------------
$stmt = $pdo->prepare("SELECT
    COUNT(*) as total_tasks,
    SUM(CASE WHEN t.status = 'Accomplished' THEN 1 ELSE 0 END) as accomplished_tasks,
    SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN t.status = 'Overdue' THEN 1 ELSE 0 END) as overdue_tasks,
    SUM(CASE WHEN t.status = 'To Do' THEN 1 ELSE 0 END) as todo_tasks
    FROM tasks t WHERE $task_where_sql");
$stmt->execute($task_params);
$task_totals = $stmt->fetch();

$total_tasks = intval($task_totals['total_tasks'] ?? 0);
$accomplished_tasks = intval($task_totals['accomplished_tasks'] ?? 0);
$in_progress_tasks = intval($task_totals['in_progress_tasks'] ?? 0);
$overdue_tasks = intval($task_totals['overdue_tasks'] ?? 0);
$todo_tasks = intval($task_totals['todo_tasks'] ?? 0);
$task_completion_rate = $total_tasks > 0 ? round(($accomplished_tasks / $total_tasks) * 100) : 0;

// Projects Stats
$stmt = $pdo->prepare("SELECT
    COUNT(*) as total_projects,
    SUM(CASE WHEN p.status = 'Accomplished' THEN 1 ELSE 0 END) as accomplished_projects,
    SUM(CASE WHEN p.status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing_projects,
    SUM(CASE WHEN p.status = 'Planning' THEN 1 ELSE 0 END) as planning_projects,
    AVG(p.progress_percent) as avg_progress
    FROM projects p WHERE $proj_where_sql");
$stmt->execute($proj_params);
$proj_totals = $stmt->fetch();

$total_projects = intval($proj_totals['total_projects'] ?? 0);
$accomplished_projects = intval($proj_totals['accomplished_projects'] ?? 0);
$ongoing_projects = intval($proj_totals['ongoing_projects'] ?? 0);
$planning_projects = intval($proj_totals['planning_projects'] ?? 0);
$avg_project_progress = round(floatval($proj_totals['avg_progress'] ?? 0));

// Feedback Stats
$stmt = $pdo->prepare("SELECT
    COUNT(*) as total_feedbacks,
    SUM(CASE WHEN f.status = 'Resolved' THEN 1 ELSE 0 END) as resolved_feedbacks,
    SUM(CASE WHEN f.status != 'Resolved' THEN 1 ELSE 0 END) as pending_feedbacks
    FROM feedbacks f WHERE $fb_where_sql");
$stmt->execute($fb_params);
$fb_totals = $stmt->fetch();

$total_feedbacks = intval($fb_totals['total_feedbacks'] ?? 0);
$resolved_feedbacks = intval($fb_totals['resolved_feedbacks'] ?? 0);
$pending_feedbacks = intval($fb_totals['pending_feedbacks'] ?? 0);
$feedback_resolution_rate = $total_feedbacks > 0 ? round(($resolved_feedbacks / $total_feedbacks) * 100) : 0;

// Accomplishments List & Count
$stmt = $pdo->prepare("SELECT a.*, o.acronym as org_acronym
                       FROM accomplishments a
                       JOIN organizations o ON a.org_id = o.id
                       WHERE $acc_where_sql
                       ORDER BY a.date_accomplished DESC, a.id DESC");
$stmt->execute($acc_params);
$accomplishments_list = $stmt->fetchAll();
$total_accomplishments = count($accomplishments_list);

// ----------------------------------------------------
// 2. Detailed Tasks List & Individual Task Assignment Breakdown (Filtered)
// ----------------------------------------------------
$tasks_list_sql = "SELECT t.*, o.acronym as org_acronym, p.title as project_title
                   FROM tasks t
                   JOIN organizations o ON t.org_id = o.id
                   LEFT JOIN projects p ON t.project_id = p.id
                   WHERE $task_where_sql
                   ORDER BY t.deadline ASC, t.id DESC";
$stmt = $pdo->prepare($tasks_list_sql);
$stmt->execute($task_params);
$tasks_list = $stmt->fetchAll();
$officer_performance = getIndividualPersonnelTaskStats($pdo, $tasks_list);

// ----------------------------------------------------
// 4. Projects Overview Table (Filtered)
// ----------------------------------------------------
$projects_sql = "SELECT p.*, o.acronym as org_acronym
                 FROM projects p
                 JOIN organizations o ON p.org_id = o.id
                 WHERE $proj_where_sql
                 ORDER BY p.progress_percent DESC, p.target_date ASC";
$stmt = $pdo->prepare($projects_sql);
$stmt->execute($proj_params);
$projects_list = $stmt->fetchAll();

// ----------------------------------------------------
// 5. Activity & Audit Logs (Filtered)
// ----------------------------------------------------
// 5a. Task Logs
$tl_conds = ["1=1"];
$tl_params = [];
if ($selected_org_id > 0) {
    $tl_conds[] = "t.org_id = ?";
    $tl_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $tl_conds[] = "DATE(tl.created_at) = ?";
    $tl_params[] = $filter_date;
}
if (!empty($date_from)) {
    $tl_conds[] = "DATE(tl.created_at) >= ?";
    $tl_params[] = $date_from;
}
if (!empty($date_to)) {
    $tl_conds[] = "DATE(tl.created_at) <= ?";
    $tl_params[] = $date_to;
}
$tl_where = implode(" AND ", $tl_conds);
$stmt_tl = $pdo->prepare("SELECT tl.*, t.title as task_title, t.assigned_to, t.status as task_status, o.acronym as org_acronym
                          FROM task_logs tl
                          JOIN tasks t ON tl.task_id = t.id
                          LEFT JOIN organizations o ON t.org_id = o.id
                          WHERE $tl_where
                          ORDER BY tl.created_at DESC, tl.id DESC");
$stmt_tl->execute($tl_params);
$report_task_logs = $stmt_tl->fetchAll();

// 5b. Project Logs
$pl_conds = ["1=1"];
$pl_params = [];
if ($selected_org_id > 0) {
    $pl_conds[] = "p.org_id = ?";
    $pl_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $pl_conds[] = "DATE(pl.created_at) = ?";
    $pl_params[] = $filter_date;
}
if (!empty($date_from)) {
    $pl_conds[] = "DATE(pl.created_at) >= ?";
    $pl_params[] = $date_from;
}
if (!empty($date_to)) {
    $pl_conds[] = "DATE(pl.created_at) <= ?";
    $pl_params[] = $date_to;
}
$pl_where = implode(" AND ", $pl_conds);
$stmt_pl = $pdo->prepare("SELECT pl.*, p.title as project_title, p.progress_percent, p.status as project_status, o.acronym as org_acronym
                          FROM project_logs pl
                          JOIN projects p ON pl.project_id = p.id
                          LEFT JOIN organizations o ON p.org_id = o.id
                          WHERE $pl_where
                          ORDER BY pl.created_at DESC, pl.id DESC");
$stmt_pl->execute($pl_params);
$report_project_logs = $stmt_pl->fetchAll();

// 5c. Accomplishment Logs
$al_conds = ["1=1"];
$al_params = [];
if ($selected_org_id > 0) {
    $al_conds[] = "a.org_id = ?";
    $al_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $al_conds[] = "DATE(al.created_at) = ?";
    $al_params[] = $filter_date;
}
if (!empty($date_from)) {
    $al_conds[] = "DATE(al.created_at) >= ?";
    $al_params[] = $date_from;
}
if (!empty($date_to)) {
    $al_conds[] = "DATE(al.created_at) <= ?";
    $al_params[] = $date_to;
}
$al_where = implode(" AND ", $al_conds);
$stmt_al = $pdo->prepare("SELECT al.*, a.title as acc_title, a.badge_tag, a.date_accomplished, o.acronym as org_acronym
                          FROM accomplishment_logs al
                          JOIN accomplishments a ON al.acc_id = a.id
                          LEFT JOIN organizations o ON a.org_id = o.id
                          WHERE $al_where
                          ORDER BY al.created_at DESC, al.id DESC");
$stmt_al->execute($al_params);
$report_acc_logs = $stmt_al->fetchAll();

// 5d. Student Account Status Logs
$sl_conds = ["1=1"];
$sl_params = [];
if (!empty($filter_date)) {
    $sl_conds[] = "DATE(sl.created_at) = ?";
    $sl_params[] = $filter_date;
}
if (!empty($date_from)) {
    $sl_conds[] = "DATE(sl.created_at) >= ?";
    $sl_params[] = $date_from;
}
if (!empty($date_to)) {
    $sl_conds[] = "DATE(sl.created_at) <= ?";
    $sl_params[] = $date_to;
}
$sl_where = implode(" AND ", $sl_conds);
$stmt_sl = $pdo->prepare("SELECT sl.* FROM student_logs sl WHERE $sl_where ORDER BY sl.created_at DESC, sl.id DESC");
$stmt_sl->execute($sl_params);
$report_student_logs = $stmt_sl->fetchAll();

// Format human-readable period label & filter description
$period_label = 'All Time (Cumulative Records)';
$filter_criteria_badges = [];
if ($selected_org_id > 0) {
    $filter_criteria_badges[] = "Organization: $active_org_acronym ($active_org_name)";
} else {
    $filter_criteria_badges[] = "Organization: All Student Organizations (Consolidated)";
}

if (!empty($filter_date)) {
    $period_label = date('F d, Y', strtotime($filter_date));
    $filter_criteria_badges[] = "Specific Date: " . $period_label;
} elseif (!empty($date_from) || !empty($date_to)) {
    $from_str = !empty($date_from) ? date('M d, Y', strtotime($date_from)) : 'Beginning';
    $to_str = !empty($date_to) ? date('M d, Y', strtotime($date_to)) : 'Present';
    $period_label = "$from_str – $to_str";
    $filter_criteria_badges[] = "Date Range: $period_label";
} else {
    $filter_criteria_badges[] = "Date Scope: All Time";
}

$log_tab_labels = [
    'all_logs' => 'All Activity & Audit Logs',
    'task_logs' => 'Task Activity Logs Only',
    'project_logs' => 'Project Activity Logs Only',
    'acc_logs' => 'Accomplishment Activity Logs Only',
    'student_logs' => 'Student Account Status Logs Only'
];
$filter_criteria_badges[] = "Log Scope: " . ($log_tab_labels[$active_log_tab] ?? 'All Activity & Audit Logs');

$report_ref_no = 'H2L-RPT-' . date('Ymd') . '-' . strtoupper(substr(md5($selected_org_id . $filter_date . $date_from . $date_to . $active_log_tab), 0, 5));

$back_query = http_build_query([
    'org_id' => $selected_org_id,
    'filter_date' => $filter_date,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'log_tab' => ($active_log_tab === 'all_logs' ? 'task_logs' : $active_log_tab)
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Official Report - <?= htmlspecialchars($active_org_acronym) ?> (<?= htmlspecialchars($period_label) ?>)</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #1e3a8a;
      --primary-light: #eff6ff;
      --secondary: #0284c7;
      --dark: #0f172a;
      --gray-800: #1e293b;
      --gray-700: #334155;
      --gray-600: #475569;
      --gray-500: #64748b;
      --gray-400: #94a3b8;
      --gray-300: #cbd5e1;
      --gray-200: #e2e8f0;
      --gray-100: #f1f5f9;
      --success: #15803d;
      --warning: #b45309;
      --danger: #b91c1c;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      background: #cbd5e1;
      color: #0f172a;
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 12.5px;
      line-height: 1.45;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    /* Sticky Top Print Control Bar (Screen Only) */
    .print-toolbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: #0f172a;
      color: #fff;
      padding: 0.75rem 1.5rem;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
      border-bottom: 1px solid #334155;
    }

    .print-toolbar-inner {
      max-width: 1120px;
      margin: 0 auto;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.85rem;
    }

    .toolbar-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.5rem 0.95rem;
      border-radius: 6px;
      font-size: 0.82rem;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      border: 1px solid transparent;
      transition: all 0.15s ease;
      font-family: inherit;
    }

    .toolbar-btn-back {
      background: #1e293b;
      color: #e2e8f0;
      border-color: #475569;
    }
    .toolbar-btn-back:hover {
      background: #334155;
    }

    .toolbar-btn-print {
      background: #2563eb;
      color: #fff;
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
    }
    .toolbar-btn-print:hover {
      background: #1d4ed8;
    }

    .toolbar-filters-bar {
      max-width: 1120px;
      margin: 0.65rem auto 0 auto;
      padding-top: 0.65rem;
      border-top: 1px solid #1e293b;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      font-size: 0.78rem;
    }

    .toolbar-form {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem;
    }

    .toolbar-input, .toolbar-select {
      background: #1e293b;
      color: #f8fafc;
      border: 1px solid #475569;
      border-radius: 5px;
      padding: 0.32rem 0.55rem;
      font-size: 0.78rem;
      font-family: inherit;
    }

    .section-toggles {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.65rem;
      color: #cbd5e1;
    }

    .section-toggles label {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      cursor: pointer;
      user-select: none;
    }

    /* Official Paper Sheet Container */
    .report-sheet {
      max-width: 980px;
      margin: 1.75rem auto 3rem auto;
      background: #ffffff;
      padding: 2.25rem 2.5rem;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.22);
      border-radius: 4px;
    }

    /* Official Institutional Letterhead */
    .official-letterhead {
      display: grid;
      grid-template-columns: 80px 1fr 80px;
      align-items: center;
      gap: 1rem;
      padding-bottom: 1rem;
      border-bottom: 3px double #1e3a8a;
      margin-bottom: 1.15rem;
    }

    .letterhead-logo {
      width: 74px;
      height: 74px;
      object-fit: contain;
      border-radius: 50%;
      border: 1px solid var(--gray-200);
      padding: 2px;
      background: #fff;
    }

    .letterhead-seal-placeholder {
      width: 74px;
      height: 74px;
      border-radius: 50%;
      background: var(--primary-light);
      border: 2px solid var(--primary);
      color: var(--primary);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 0.7rem;
      text-align: center;
      line-height: 1.1;
    }

    .letterhead-center {
      text-align: center;
    }

    .letterhead-republic {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--gray-600);
      font-weight: 700;
      margin: 0;
    }

    .letterhead-system {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--primary);
      letter-spacing: 0.02em;
      margin: 0.1rem 0;
      text-transform: uppercase;
    }

    .letterhead-org {
      font-size: 0.96rem;
      font-weight: 800;
      color: var(--dark);
      margin: 0.1rem 0;
    }

    .letterhead-ReportTitle {
      display: inline-block;
      margin-top: 0.35rem;
      padding: 0.22rem 0.9rem;
      background: var(--primary);
      color: #ffffff;
      font-size: 0.8rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-radius: 3px;
    }

    /* Document Metadata & Applied Filter Table */
    .meta-box {
      border: 1px solid var(--gray-300);
      background: #f8fafc;
      border-radius: 4px;
      padding: 0.75rem 1rem;
      margin-bottom: 1.35rem;
    }

    .meta-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.45rem 1.5rem;
      font-size: 0.8rem;
    }

    .meta-item {
      display: flex;
      gap: 0.4rem;
    }

    .meta-label {
      color: var(--gray-600);
      font-weight: 700;
      min-width: 135px;
    }

    .meta-value {
      color: var(--dark);
      font-weight: 600;
    }

    .applied-filters-strip {
      margin-top: 0.6rem;
      padding-top: 0.55rem;
      border-top: 1px dashed var(--gray-300);
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.74rem;
    }

    .filter-pill {
      background: #e0f2fe;
      color: #0369a1;
      border: 1px solid #bae6fd;
      padding: 0.12rem 0.55rem;
      border-radius: 99px;
      font-weight: 700;
    }

    /* Section Headings */
    .report-section {
      margin-bottom: 1.5rem;
    }

    .section-title-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #f1f5f9;
      border-left: 4px solid var(--primary);
      padding: 0.42rem 0.75rem;
      margin-bottom: 0.65rem;
    }

    .section-title-bar h3 {
      margin: 0;
      font-size: 0.86rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      color: var(--dark);
    }

    .section-title-bar .section-count {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--gray-600);
    }

    /* KPI Summary Grid */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.65rem;
      margin-bottom: 0.35rem;
    }

    .kpi-box {
      border: 1px solid var(--gray-300);
      border-radius: 4px;
      padding: 0.65rem 0.75rem;
      background: #fff;
    }

    .kpi-box-title {
      font-size: 0.7rem;
      font-weight: 800;
      text-transform: uppercase;
      color: var(--gray-600);
      margin-bottom: 0.25rem;
    }

    .kpi-box-value {
      font-size: 1.3rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }

    .kpi-box-sub {
      margin-top: 0.25rem;
      font-size: 0.72rem;
      color: var(--gray-600);
    }

    /* Formal Report Tables */
    .official-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.78rem;
    }

    .official-table th,
    .official-table td {
      border: 1px solid var(--gray-300);
      padding: 0.42rem 0.55rem;
      text-align: left;
      vertical-align: top;
    }

    .official-table thead th {
      background: #f8fafc;
      color: var(--dark);
      font-weight: 800;
      font-size: 0.73rem;
      text-transform: uppercase;
      letter-spacing: 0.02em;
    }

    .official-table tbody tr:nth-child(even) {
      background: #fcfcfd;
    }

    .status-pill {
      display: inline-block;
      padding: 0.1rem 0.42rem;
      border-radius: 3px;
      font-size: 0.68rem;
      font-weight: 800;
      text-transform: uppercase;
      border: 1px solid transparent;
    }
    .status-accomplished { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .status-inprogress { background: #e0f2fe; color: #075985; border-color: #bae6fd; }
    .status-todo { background: #fef3c7; color: #92400e; border-color: #fde68a; }
    .status-overdue { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .status-neutral { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }

    /* Certification & Signatories */
    .certification-box {
      margin-top: 1.75rem;
      padding: 0.75rem 1rem;
      border: 1px solid var(--gray-300);
      background: #f8fafc;
      font-size: 0.78rem;
      color: var(--gray-700);
      font-style: italic;
      page-break-inside: avoid;
    }

    .signatories-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2rem;
      margin-top: 2.5rem;
      page-break-inside: avoid;
    }

    .signatory-col {
      text-align: center;
    }

    .signatory-line {
      border-bottom: 1.5px solid #0f172a;
      height: 38px;
      margin-bottom: 0.4rem;
    }

    .signatory-name {
      font-weight: 800;
      font-size: 0.85rem;
      color: var(--dark);
      text-transform: uppercase;
      outline: none;
    }

    .signatory-role {
      font-size: 0.76rem;
      color: var(--gray-600);
      font-weight: 600;
      outline: none;
    }

    .signatory-caption {
      font-size: 0.7rem;
      color: var(--gray-500);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-top: 0.2rem;
      font-weight: 700;
    }

    [contenteditable="true"]:hover {
      background: #fef9c3;
      border-radius: 3px;
      cursor: text;
    }
    [contenteditable="true"]:focus {
      background: #fef08a;
      outline: 1px dashed var(--primary);
      border-radius: 3px;
    }

    .report-footer {
      margin-top: 2rem;
      padding-top: 0.65rem;
      border-top: 1px solid var(--gray-300);
      display: flex;
      justify-content: space-between;
      font-size: 0.7rem;
      color: var(--gray-500);
    }

    @media print {
      @page {
        size: A4 portrait;
        margin: 12mm 12mm 14mm 12mm;
      }
      body {
        background: #ffffff !important;
      }
      .no-print, .print-toolbar {
        display: none !important;
      }
      .report-sheet {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
      }
      .report-section {
        page-break-inside: auto;
      }
      .section-title-bar {
        page-break-after: avoid;
      }
      tr {
        page-break-inside: avoid;
      }
      [contenteditable="true"]:hover,
      [contenteditable="true"]:focus {
        background: transparent !important;
        outline: none !important;
      }
    }
  </style>
</head>
<body>

  <!-- Screen-Only Interactive Toolbar & Filter Controls -->
  <div class="print-toolbar no-print">
    <div class="print-toolbar-inner">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <a href="reports.php?<?= htmlspecialchars($back_query) ?>" class="toolbar-btn toolbar-btn-back">
          <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
        <div>
          <div style="font-weight: 800; font-size: 0.92rem; display: flex; align-items: center; gap: 0.45rem;">
            <i class="fa-solid fa-file-contract" style="color: #60a5fa;"></i>
            Official Report Print Template
          </div>
          <div style="font-size: 0.73rem; color: #94a3b8;">
            Data dynamically applied from active filters • Tip: Click any signatory name or title to edit before printing.
          </div>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 0.6rem;">
        <button type="button" onclick="window.print()" class="toolbar-btn toolbar-btn-print">
          <i class="fa-solid fa-print"></i> Print / Save Official PDF
        </button>
      </div>
    </div>

    <!-- Filter & Section Customizer Bar -->
    <div class="toolbar-filters-bar">
      <form method="GET" action="print_report.php" class="toolbar-form">
        <?php if ($is_admin): ?>
          <label style="color: #cbd5e1; font-weight: 600;">Org:</label>
          <select name="org_id" class="toolbar-select" onchange="this.form.submit()">
            <option value="0" <?= $selected_org_id === 0 ? 'selected' : '' ?>>All Organizations</option>
            <?php foreach ($all_orgs as $o): ?>
              <option value="<?= $o['id'] ?>" <?= $selected_org_id == $o['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <label style="color: #cbd5e1; font-weight: 600;">Specific Date:</label>
        <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>" class="toolbar-input" onchange="this.form.date_from.value=''; this.form.date_to.value=''; this.form.submit();">

        <label style="color: #cbd5e1; font-weight: 600;">Range From:</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="toolbar-input" onchange="this.form.filter_date.value=''; this.form.submit();">

        <label style="color: #cbd5e1; font-weight: 600;">To:</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="toolbar-input" onchange="this.form.filter_date.value=''; this.form.submit();">

        <label style="color: #cbd5e1; font-weight: 600;">Logs Scope:</label>
        <select name="log_tab" class="toolbar-select" onchange="this.form.submit()">
          <option value="all_logs" <?= $active_log_tab === 'all_logs' ? 'selected' : '' ?>>All Activity Logs</option>
          <option value="task_logs" <?= $active_log_tab === 'task_logs' ? 'selected' : '' ?>>Task Logs Only</option>
          <option value="project_logs" <?= $active_log_tab === 'project_logs' ? 'selected' : '' ?>>Project Logs Only</option>
          <option value="acc_logs" <?= $active_log_tab === 'acc_logs' ? 'selected' : '' ?>>Accomplishment Logs Only</option>
          <option value="student_logs" <?= $active_log_tab === 'student_logs' ? 'selected' : '' ?>>Student Status Logs Only</option>
        </select>

        <?php if (($is_admin && $selected_org_id > 0) || !empty($filter_date) || !empty($date_from) || !empty($date_to) || $active_log_tab !== 'all_logs'): ?>
          <a href="print_report.php" class="toolbar-btn toolbar-btn-back" style="padding: 0.3rem 0.6rem; font-size: 0.74rem;">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </a>
        <?php endif; ?>
      </form>

      <div class="section-toggles">
        <strong style="color: #94a3b8;">Sections:</strong>
        <label><input type="checkbox" checked onchange="toggleReportSection('sec-kpis', this.checked)"> KPIs</label>
        <label><input type="checkbox" checked onchange="toggleReportSection('sec-officers', this.checked)"> Officer Breakdown</label>
        <label><input type="checkbox" checked onchange="toggleReportSection('sec-tasks', this.checked)"> Tasks Roster</label>
        <label><input type="checkbox" checked onchange="toggleReportSection('sec-projects', this.checked)"> Programs/Projects</label>
        <label><input type="checkbox" checked onchange="toggleReportSection('sec-accomplishments', this.checked)"> Accomplishments</label>
        <label><input type="checkbox" checked onchange="toggleReportSection('sec-logs', this.checked)"> Audit Logs</label>
      </div>
    </div>
  </div>

  <!-- Official Printable Report Sheet -->
  <div class="report-sheet">

    <!-- 1. Official Institutional Letterhead -->
    <div class="official-letterhead">
      <div style="display: flex; justify-content: flex-start;">
        <img src="../assets/uploads/logos.jpg" alt="School Logo" class="letterhead-logo" onerror="this.style.display='none'">
      </div>

      <div class="letterhead-center">
        <p class="letterhead-republic">Office of Student Affairs &amp; Student Leadership Development</p>
        <h1 class="letterhead-system">Hear2Lead: Student Leadership Management System</h1>
        <h2 class="letterhead-org"><?= htmlspecialchars($active_org_name) ?> <?= $selected_org_id > 0 ? '(' . htmlspecialchars($active_org_acronym) . ')' : '' ?></h2>
        <div class="letterhead-ReportTitle" contenteditable="true" title="Click to customize report title">
          Official Leadership Performance &amp; Accomplishment Report
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end;">
        <?php if (!empty($active_org_logo) && file_exists(__DIR__ . '/../assets/uploads/logos/' . $active_org_logo)): ?>
          <img src="../assets/uploads/logos/<?= htmlspecialchars($active_org_logo) ?>" alt="Org Logo" class="letterhead-logo">
        <?php else: ?>
          <div class="letterhead-seal-placeholder">
            <i class="fa-solid fa-shield-halved" style="font-size: 1.25rem; margin-bottom: 2px;"></i>
            <span><?= htmlspecialchars($selected_org_id > 0 ? $active_org_acronym : 'H2L') ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 2. Document Control & Applied Filter Summary Box -->
    <div class="meta-box">
      <div class="meta-grid">
        <div class="meta-item">
          <span class="meta-label">Report Reference No.:</span>
          <span class="meta-value"><code><?= htmlspecialchars($report_ref_no) ?></code></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Reporting Period:</span>
          <span class="meta-value"><?= htmlspecialchars($period_label) ?></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Organization Scope:</span>
          <span class="meta-value"><?= htmlspecialchars($active_org_name) ?></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Date &amp; Time Generated:</span>
          <span class="meta-value"><?= date('F d, Y • g:i A') ?></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Category / Classification:</span>
          <span class="meta-value"><?= htmlspecialchars($active_org_category) ?></span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Prepared &amp; Generated By:</span>
          <span class="meta-value"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Officer') ?> (<?= htmlspecialchars($_SESSION['user_role'] ?? 'Officer') ?>)</span>
        </div>
      </div>

      <div class="applied-filters-strip">
        <strong style="color: var(--gray-700);"><i class="fa-solid fa-filter"></i> Applied Report Filters:</strong>
        <?php foreach ($filter_criteria_badges as $fbadge): ?>
          <span class="filter-pill"><?= htmlspecialchars($fbadge) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SECTION I: Executive Performance Summary (Filtered KPIs) -->
    <div class="report-section" id="sec-kpis">
      <div class="section-title-bar">
        <h3>I. Executive Performance Summary (Filtered Scope)</h3>
        <span class="section-count">Period: <?= htmlspecialchars($period_label) ?></span>
      </div>

      <div class="kpi-grid">
        <div class="kpi-box">
          <div class="kpi-box-title">Task Completion Rate</div>
          <div class="kpi-box-value"><?= $task_completion_rate ?>%</div>
          <div class="kpi-box-sub">
            <strong><?= $accomplished_tasks ?></strong> of <strong><?= $total_tasks ?></strong> tasks accomplished<br>
            (<?= $in_progress_tasks ?> In Progress • <?= $todo_tasks ?> To Do • <?= $overdue_tasks ?> Overdue)
          </div>
        </div>

        <div class="kpi-box">
          <div class="kpi-box-title">Program Delivery Avg</div>
          <div class="kpi-box-value"><?= $avg_project_progress ?>%</div>
          <div class="kpi-box-sub">
            <strong><?= $total_projects ?></strong> Total Programs/Projects<br>
            (<?= $accomplished_projects ?> Accomplished • <?= $ongoing_projects ?> Ongoing • <?= $planning_projects ?> Planning)
          </div>
        </div>

        <div class="kpi-box">
          <div class="kpi-box-title">Student Feedback Resolution</div>
          <div class="kpi-box-value"><?= $feedback_resolution_rate ?>%</div>
          <div class="kpi-box-sub">
            <strong><?= $resolved_feedbacks ?></strong> of <strong><?= $total_feedbacks ?></strong> resolved<br>
            (<?= $pending_feedbacks ?> Pending / In Action)
          </div>
        </div>

        <div class="kpi-box">
          <div class="kpi-box-title">Verified Accomplishments</div>
          <div class="kpi-box-value"><?= $total_accomplishments ?></div>
          <div class="kpi-box-sub">
            Published institutional milestones &amp; documented achievements within period
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION II: Individual Task Assignment & Monitoring Breakdown -->
    <div class="report-section" id="sec-officers">
      <div class="section-title-bar">
        <h3>II. Individual Officer Task Assignment &amp; Accountability Breakdown</h3>
        <span class="section-count"><?= count($officer_performance) ?> Assigned Personnel</span>
      </div>

      <table class="official-table">
        <thead>
          <tr>
            <th style="width: 34px; text-align: center;">#</th>
            <?php if ($selected_org_id === 0): ?>
              <th>Org</th>
            <?php endif; ?>
            <th>Assigned Personnel</th>
            <th style="text-align: center;">Total Assigned</th>
            <th style="text-align: center;">Accomplished</th>
            <th style="text-align: center;">In Progress</th>
            <th style="text-align: center;">To Do</th>
            <th style="text-align: center;">Overdue</th>
            <th style="text-align: right;">Efficiency Score</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($officer_performance)): ?>
            <tr>
              <td colspan="<?= $selected_org_id === 0 ? '9' : '8' ?>" style="text-align: center; color: var(--gray-500); padding: 1rem;">
                No task assignments found matching the selected filter criteria (<?= htmlspecialchars($period_label) ?>).
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($officer_performance as $idx => $row):
              $rate = $row['total_assigned'] > 0 ? round(($row['count_accomplished'] / $row['total_assigned']) * 100) : 0;
              $pill_class = $rate >= 80 ? 'status-accomplished' : ($rate >= 50 ? 'status-todo' : 'status-overdue');
            ?>
              <tr>
                <td style="text-align: center; font-weight: 700; color: var(--gray-500);"><?= $idx + 1 ?></td>
                <?php if ($selected_org_id === 0): ?>
                  <td><strong><?= htmlspecialchars($row['org_acronym'] ?: 'ORG') ?></strong></td>
                <?php endif; ?>
                <td><strong><?= htmlspecialchars($row['assigned_to'] ?: 'Unassigned') ?></strong></td>
                <td style="text-align: center;"><strong><?= intval($row['total_assigned']) ?></strong></td>
                <td style="text-align: center; color: var(--success); font-weight: 700;"><?= intval($row['count_accomplished']) ?></td>
                <td style="text-align: center; color: var(--secondary); font-weight: 700;"><?= intval($row['count_in_progress']) ?></td>
                <td style="text-align: center; color: var(--warning); font-weight: 700;"><?= intval($row['count_todo']) ?></td>
                <td style="text-align: center; color: <?= intval($row['count_overdue']) > 0 ? 'var(--danger)' : 'var(--gray-400)' ?>; font-weight: 700;">
                  <?= intval($row['count_overdue']) ?>
                </td>
                <td style="text-align: right;">
                  <span class="status-pill <?= $pill_class ?>"><?= $rate ?>%</span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- SECTION III: Detailed Filtered Tasks Roster -->
    <div class="report-section" id="sec-tasks">
      <div class="section-title-bar">
        <h3>III. Detailed Tasks Roster (Filtered Period)</h3>
        <span class="section-count"><?= count($tasks_list) ?> Task Record(s)</span>
      </div>

      <table class="official-table">
        <thead>
          <tr>
            <th style="width: 34px; text-align: center;">#</th>
            <?php if ($selected_org_id === 0): ?>
              <th>Org</th>
            <?php endif; ?>
            <th>Task Title &amp; Linked Program</th>
            <th>Assigned Personnel</th>
            <th style="text-align: center;">Priority</th>
            <th style="text-align: center;">Target Deadline</th>
            <th style="text-align: center;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tasks_list)): ?>
            <tr>
              <td colspan="<?= $selected_org_id === 0 ? '7' : '6' ?>" style="text-align: center; color: var(--gray-500); padding: 1rem;">
                No tasks recorded for the filtered scope and period.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($tasks_list as $idx => $t):
              $st_map = [
                'Accomplished' => 'status-accomplished',
                'In Progress' => 'status-inprogress',
                'To Do' => 'status-todo',
                'Overdue' => 'status-overdue'
              ];
              $st_cls = $st_map[$t['status']] ?? 'status-neutral';
            ?>
              <tr>
                <td style="text-align: center; color: var(--gray-500); font-weight: 700;"><?= $idx + 1 ?></td>
                <?php if ($selected_org_id === 0): ?>
                  <td><strong><?= htmlspecialchars($t['org_acronym']) ?></strong></td>
                <?php endif; ?>
                <td>
                  <strong><?= htmlspecialchars($t['title']) ?></strong>
                  <?php if (!empty($t['project_title'])): ?>
                    <div style="font-size: 0.7rem; color: var(--gray-600);">Program: <?= htmlspecialchars($t['project_title']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($t['assigned_to'] ?: 'Unassigned') ?></td>
                <td style="text-align: center; font-weight: 700;"><?= htmlspecialchars($t['priority']) ?></td>
                <td style="text-align: center; white-space: nowrap;"><?= !empty($t['deadline']) ? date('M d, Y', strtotime($t['deadline'])) : 'N/A' ?></td>
                <td style="text-align: center;"><span class="status-pill <?= $st_cls ?>"><?= htmlspecialchars($t['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- SECTION IV: Programs and Projects Milestone Status -->
    <div class="report-section" id="sec-projects">
      <div class="section-title-bar">
        <h3>IV. Programs &amp; Projects Milestone Status</h3>
        <span class="section-count"><?= count($projects_list) ?> Program(s)</span>
      </div>

      <table class="official-table">
        <thead>
          <tr>
            <th style="width: 34px; text-align: center;">#</th>
            <?php if ($selected_org_id === 0): ?>
              <th>Org</th>
            <?php endif; ?>
            <th>Program / Project Title &amp; Description</th>
            <th style="text-align: center;">Start Date</th>
            <th style="text-align: center;">Target Delivery</th>
            <th style="text-align: center;">Progress</th>
            <th style="text-align: center;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($projects_list)): ?>
            <tr>
              <td colspan="<?= $selected_org_id === 0 ? '7' : '6' ?>" style="text-align: center; color: var(--gray-500); padding: 1rem;">
                No programs or projects found for the selected filter period.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($projects_list as $idx => $p):
              $p_cls = $p['status'] === 'Accomplished' ? 'status-accomplished' : ($p['status'] === 'Ongoing' ? 'status-inprogress' : 'status-todo');
            ?>
              <tr>
                <td style="text-align: center; color: var(--gray-500); font-weight: 700;"><?= $idx + 1 ?></td>
                <?php if ($selected_org_id === 0): ?>
                  <td><strong><?= htmlspecialchars($p['org_acronym']) ?></strong></td>
                <?php endif; ?>
                <td>
                  <strong><?= htmlspecialchars($p['title']) ?></strong>
                  <?php if (!empty($p['description'])): ?>
                    <div style="font-size: 0.72rem; color: var(--gray-600); margin-top: 2px;"><?= htmlspecialchars($p['description']) ?></div>
                  <?php endif; ?>
                </td>
                <td style="text-align: center; white-space: nowrap;"><?= !empty($p['start_date']) ? date('M d, Y', strtotime($p['start_date'])) : '—' ?></td>
                <td style="text-align: center; white-space: nowrap;"><?= !empty($p['target_date']) ? date('M d, Y', strtotime($p['target_date'])) : '—' ?></td>
                <td style="text-align: center; font-weight: 800; color: var(--primary);"><?= intval($p['progress_percent']) ?>%</td>
                <td style="text-align: center;"><span class="status-pill <?= $p_cls ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- SECTION V: Verified Accomplishments & Institutional Impact -->
    <div class="report-section" id="sec-accomplishments">
      <div class="section-title-bar">
        <h3>V. Verified Accomplishments &amp; Institutional Impact</h3>
        <span class="section-count"><?= count($accomplishments_list) ?> Accomplishment(s)</span>
      </div>

      <table class="official-table">
        <thead>
          <tr>
            <th style="width: 34px; text-align: center;">#</th>
            <?php if ($selected_org_id === 0): ?>
              <th>Org</th>
            <?php endif; ?>
            <th>Accomplishment Title</th>
            <th>Category / Tag</th>
            <th>Impact &amp; Description</th>
            <th style="text-align: center;">Date Accomplished</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($accomplishments_list)): ?>
            <tr>
              <td colspan="<?= $selected_org_id === 0 ? '6' : '5' ?>" style="text-align: center; color: var(--gray-500); padding: 1rem;">
                No verified accomplishments recorded for the selected filter period.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($accomplishments_list as $idx => $acc): ?>
              <tr>
                <td style="text-align: center; color: var(--gray-500); font-weight: 700;"><?= $idx + 1 ?></td>
                <?php if ($selected_org_id === 0): ?>
                  <td><strong><?= htmlspecialchars($acc['org_acronym']) ?></strong></td>
                <?php endif; ?>
                <td><strong><?= htmlspecialchars($acc['title']) ?></strong></td>
                <td><span class="status-pill status-accomplished"><?= htmlspecialchars($acc['badge_tag'] ?: 'Milestone') ?></span></td>
                <td>
                  <?php if (!empty($acc['impact_summary'])): ?>
                    <div style="font-weight: 700; color: var(--dark); margin-bottom: 2px;">Impact: <?= htmlspecialchars($acc['impact_summary']) ?></div>
                  <?php endif; ?>
                  <div style="color: var(--gray-700); font-size: 0.74rem;"><?= htmlspecialchars($acc['description']) ?></div>
                </td>
                <td style="text-align: center; white-space: nowrap; font-weight: 700;">
                  <?= !empty($acc['date_accomplished']) ? date('M d, Y', strtotime($acc['date_accomplished'])) : '—' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- SECTION VI: Filtered Activity & Audit Logs -->
    <div class="report-section" id="sec-logs">
      <div class="section-title-bar">
        <h3>VI. Official Activity &amp; Audit Logs (<?= htmlspecialchars($log_tab_labels[$active_log_tab] ?? 'All Activity Logs') ?>)</h3>
        <span class="section-count">Filtered Audit Trail</span>
      </div>

      <?php if ($active_log_tab === 'all_logs' || $active_log_tab === 'task_logs'): ?>
        <div style="margin-bottom: 1rem;">
          <div style="font-weight: 800; font-size: 0.78rem; color: var(--primary); margin-bottom: 0.35rem; text-transform: uppercase;">
            A. Task Activity Logs (<?= count($report_task_logs) ?>)
          </div>
          <table class="official-table">
            <thead>
              <tr>
                <?php if ($selected_org_id === 0): ?><th>Org</th><?php endif; ?>
                <th>Task Title</th>
                <th>Action</th>
                <th>Logged By</th>
                <th>Activity &amp; Change Details</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($report_task_logs)): ?>
                <tr><td colspan="<?= $selected_org_id === 0 ? '6' : '5' ?>" style="text-align: center; color: var(--gray-500);">No task activity logs for the selected period.</td></tr>
              <?php else: ?>
                <?php foreach ($report_task_logs as $log): ?>
                  <tr>
                    <?php if ($selected_org_id === 0): ?><td><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></td><?php endif; ?>
                    <td><strong><?= htmlspecialchars($log['task_title'] ?? ('Task #' . $log['task_id'])) ?></strong></td>
                    <td><span class="status-pill status-inprogress"><?= htmlspecialchars($log['action_type']) ?></span></td>
                    <td><strong><?= htmlspecialchars($log['user_name']) ?></strong> <span style="color:var(--gray-500);">(<?= htmlspecialchars($log['user_role']) ?>)</span></td>
                    <td><?= htmlspecialchars($log['log_details']) ?></td>
                    <td style="white-space: nowrap;"><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($active_log_tab === 'all_logs' || $active_log_tab === 'project_logs'): ?>
        <div style="margin-bottom: 1rem;">
          <div style="font-weight: 800; font-size: 0.78rem; color: var(--primary); margin-bottom: 0.35rem; text-transform: uppercase;">
            B. Program &amp; Project Activity Logs (<?= count($report_project_logs) ?>)
          </div>
          <table class="official-table">
            <thead>
              <tr>
                <?php if ($selected_org_id === 0): ?><th>Org</th><?php endif; ?>
                <th>Project Title</th>
                <th>Action</th>
                <th>Logged By</th>
                <th>Activity &amp; Progress Details</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($report_project_logs)): ?>
                <tr><td colspan="<?= $selected_org_id === 0 ? '6' : '5' ?>" style="text-align: center; color: var(--gray-500);">No project activity logs for the selected period.</td></tr>
              <?php else: ?>
                <?php foreach ($report_project_logs as $log): ?>
                  <tr>
                    <?php if ($selected_org_id === 0): ?><td><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></td><?php endif; ?>
                    <td><strong><?= htmlspecialchars($log['project_title'] ?? ('Project #' . $log['project_id'])) ?></strong></td>
                    <td><span class="status-pill status-inprogress"><?= htmlspecialchars($log['action_type']) ?></span></td>
                    <td><strong><?= htmlspecialchars($log['user_name']) ?></strong> <span style="color:var(--gray-500);">(<?= htmlspecialchars($log['user_role']) ?>)</span></td>
                    <td><?= htmlspecialchars($log['log_details']) ?></td>
                    <td style="white-space: nowrap;"><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($active_log_tab === 'all_logs' || $active_log_tab === 'acc_logs'): ?>
        <div style="margin-bottom: 1rem;">
          <div style="font-weight: 800; font-size: 0.78rem; color: var(--primary); margin-bottom: 0.35rem; text-transform: uppercase;">
            C. Accomplishment Activity Logs (<?= count($report_acc_logs) ?>)
          </div>
          <table class="official-table">
            <thead>
              <tr>
                <?php if ($selected_org_id === 0): ?><th>Org</th><?php endif; ?>
                <th>Accomplishment Title</th>
                <th>Action</th>
                <th>Logged By</th>
                <th>Activity &amp; Document Details</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($report_acc_logs)): ?>
                <tr><td colspan="<?= $selected_org_id === 0 ? '6' : '5' ?>" style="text-align: center; color: var(--gray-500);">No accomplishment activity logs for the selected period.</td></tr>
              <?php else: ?>
                <?php foreach ($report_acc_logs as $log): ?>
                  <tr>
                    <?php if ($selected_org_id === 0): ?><td><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></td><?php endif; ?>
                    <td><strong><?= htmlspecialchars($log['acc_title'] ?? ('Accomplishment #' . $log['acc_id'])) ?></strong></td>
                    <td><span class="status-pill status-accomplished"><?= htmlspecialchars($log['action_type']) ?></span></td>
                    <td><strong><?= htmlspecialchars($log['user_name']) ?></strong> <span style="color:var(--gray-500);">(<?= htmlspecialchars($log['user_role']) ?>)</span></td>
                    <td><?= htmlspecialchars($log['log_details']) ?></td>
                    <td style="white-space: nowrap;"><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($active_log_tab === 'all_logs' || $active_log_tab === 'student_logs'): ?>
        <div>
          <div style="font-weight: 800; font-size: 0.78rem; color: var(--primary); margin-bottom: 0.35rem; text-transform: uppercase;">
            D. Student Account Approval &amp; Status Logs (<?= count($report_student_logs) ?>)
          </div>
          <table class="official-table">
            <thead>
              <tr>
                <th>Student Name &amp; LRN</th>
                <th>Action</th>
                <th>Status Transition</th>
                <th>Processed By</th>
                <th>Audit Details</th>
                <th>Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($report_student_logs)): ?>
                <tr><td colspan="6" style="text-align: center; color: var(--gray-500);">No student account status logs for the selected period.</td></tr>
              <?php else: ?>
                <?php foreach ($report_student_logs as $slog):
                  $st_cls = $slog['new_status'] === 'Approved' ? 'status-accomplished' : ($slog['new_status'] === 'Pending' ? 'status-todo' : 'status-overdue');
                ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($slog['student_name']) ?></strong>
                      <div style="font-size: 0.7rem; color: var(--gray-600);">LRN: <?= htmlspecialchars($slog['student_lrn']) ?></div>
                    </td>
                    <td><strong><?= htmlspecialchars($slog['action_type']) ?></strong></td>
                    <td style="white-space: nowrap;">
                      <?php if (!empty($slog['previous_status'])): ?>
                        <span class="status-pill status-neutral"><?= htmlspecialchars($slog['previous_status']) ?></span> &rarr;
                      <?php endif; ?>
                      <span class="status-pill <?= $st_cls ?>"><?= htmlspecialchars($slog['new_status']) ?></span>
                    </td>
                    <td><strong><?= htmlspecialchars($slog['user_name']) ?></strong> <span style="color:var(--gray-500);">(<?= htmlspecialchars($slog['user_role']) ?>)</span></td>
                    <td><?= htmlspecialchars($slog['log_details']) ?></td>
                    <td style="white-space: nowrap;"><?= date('M d, Y g:i A', strtotime($slog['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Official Certification Statement -->
    <div class="certification-box" contenteditable="true" title="Click to customize certification text">
      <strong>CERTIFICATION:</strong> I hereby certify that the leadership performance indicators, task monitoring records, program delivery milestones, verified accomplishments, and audit trail logs presented in this official report are true, accurate, and generated directly from the official records of <strong>Hear2Lead</strong> for <strong><?= htmlspecialchars($active_org_name) ?></strong> covering the period <strong><?= htmlspecialchars($period_label) ?></strong>.
    </div>

    <!-- Signatories Block (Editable inline before printing) -->
    <div class="signatories-grid">
      <div class="signatory-col">
        <div class="signatory-line"></div>
        <div class="signatory-name" contenteditable="true" title="Click to edit signatory name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Authorized Officer') ?></div>
        <div class="signatory-role" contenteditable="true" title="Click to edit signatory title"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Officer') ?>, <?= htmlspecialchars($_SESSION['org_acronym'] ?? 'SSLG') ?></div>
        <div class="signatory-caption">Prepared &amp; Submitted By</div>
      </div>

      <div class="signatory-col">
        <div class="signatory-line"></div>
        <div class="signatory-name" contenteditable="true" title="Click to edit Adviser name">Organization Adviser</div>
        <div class="signatory-role" contenteditable="true" title="Click to edit Adviser title">Faculty Adviser / Moderator</div>
        <div class="signatory-caption">Reviewed &amp; Attested By</div>
      </div>

      <div class="signatory-col">
        <div class="signatory-line"></div>
        <div class="signatory-name" contenteditable="true" title="Click to edit Principal/Head name">School Principal / Head</div>
        <div class="signatory-role" contenteditable="true" title="Click to edit Principal/Head title">Office of Student Affairs / Administration</div>
        <div class="signatory-caption">Noted &amp; Approved By</div>
      </div>
    </div>

    <!-- Report Footer -->
    <div class="report-footer">
      <span>Reference: <?= htmlspecialchars($report_ref_no) ?> • Scope: <?= htmlspecialchars($active_org_acronym) ?> (<?= htmlspecialchars($period_label) ?>)</span>
      <span>Hear2Lead Official System-Generated Document • <?= date('Y-m-d H:i:s') ?></span>
    </div>

  </div>

  <script>
    function toggleReportSection(sectionId, isVisible) {
      const el = document.getElementById(sectionId);
      if (el) {
        el.style.display = isVisible ? '' : 'none';
      }
    }

    <?php if ($auto_print): ?>
    window.addEventListener('load', function() {
      setTimeout(function() {
        window.print();
      }, 350);
    });
    <?php endif; ?>
  </script>
  <script src="../assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: time() ?>"></script>
</body>
</html>
