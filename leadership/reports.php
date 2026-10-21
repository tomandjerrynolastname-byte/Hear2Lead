<?php
// leadership/reports.php - Leadership Performance Reports Module
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

// Calendar & Date Range Filter parameters
$filter_date = trim($_GET['filter_date'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$active_log_tab = $_GET['log_tab'] ?? 'task_logs';
if (!in_array($active_log_tab, ['task_logs', 'project_logs', 'acc_logs'], true)) {
    $active_log_tab = 'task_logs';
}

// Fetch all orgs for Admin filter dropdown
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

// Determine active display name / acronym
if ($selected_org_id > 0) {
    if ($selected_org_id === intval($_SESSION['org_id'])) {
        $active_org_name = $_SESSION['org_name'];
        $active_org_acronym = $_SESSION['org_acronym'];
    } else {
        $stmt_org = $pdo->prepare("SELECT name, acronym FROM organizations WHERE id = ?");
        $stmt_org->execute([$selected_org_id]);
        $org_data = $stmt_org->fetch();
        $active_org_name = $org_data['name'] ?? 'Organization';
        $active_org_acronym = $org_data['acronym'] ?? 'ORG';
    }
} else {
    $active_org_name = 'All Student Organizations';
    $active_org_acronym = 'System-wide';
}

// Collect active dates across tasks, projects, accomplishments, feedbacks, and logs for Calendar dots
$cal_dates_map = [];
$org_clause_simple = $selected_org_id > 0 ? "WHERE org_id = " . intval($selected_org_id) : "";

$date_queries = [
    "SELECT DISTINCT DATE(deadline) as d FROM tasks $org_clause_simple",
    "SELECT DISTINCT DATE(created_at) as d FROM tasks $org_clause_simple",
    "SELECT DISTINCT DATE(target_date) as d FROM projects $org_clause_simple",
    "SELECT DISTINCT DATE(start_date) as d FROM projects $org_clause_simple",
    "SELECT DISTINCT DATE(created_at) as d FROM projects $org_clause_simple",
    "SELECT DISTINCT DATE(date_accomplished) as d FROM accomplishments $org_clause_simple",
    "SELECT DISTINCT DATE(created_at) as d FROM accomplishments $org_clause_simple",
    "SELECT DISTINCT DATE(created_at) as d FROM feedbacks $org_clause_simple",
    "SELECT DISTINCT DATE(tl.created_at) as d FROM task_logs tl JOIN tasks t ON tl.task_id = t.id " . ($selected_org_id > 0 ? "WHERE t.org_id = " . intval($selected_org_id) : ""),
    "SELECT DISTINCT DATE(pl.created_at) as d FROM project_logs pl JOIN projects p ON pl.project_id = p.id " . ($selected_org_id > 0 ? "WHERE p.org_id = " . intval($selected_org_id) : ""),
    "SELECT DISTINCT DATE(al.created_at) as d FROM accomplishment_logs al JOIN accomplishments a ON al.acc_id = a.id " . ($selected_org_id > 0 ? "WHERE a.org_id = " . intval($selected_org_id) : "")
];
foreach ($date_queries as $dq) {
    foreach ($pdo->query($dq)->fetchAll() as $r) {
        if (!empty($r['d'])) {
            $cal_dates_map[$r['d']] = true;
        }
    }
}
$report_activity_dates = array_keys($cal_dates_map);

// Build reusable SQL conditions for Tasks, Projects, Feedbacks, Accomplishments, and Logs
$task_conds = ["1=1"];
$task_params = [];
if ($selected_org_id > 0) {
    $task_conds[] = "org_id = ?";
    $task_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $task_conds[] = "(DATE(deadline) = ? OR DATE(created_at) = ?)";
    $task_params[] = $filter_date;
    $task_params[] = $filter_date;
}
if (!empty($date_from)) {
    $task_conds[] = "(DATE(deadline) >= ? OR DATE(created_at) >= ?)";
    $task_params[] = $date_from;
    $task_params[] = $date_from;
}
if (!empty($date_to)) {
    $task_conds[] = "(DATE(deadline) <= ? OR DATE(created_at) <= ?)";
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
    $fb_conds[] = "org_id = ?";
    $fb_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $fb_conds[] = "DATE(created_at) = ?";
    $fb_params[] = $filter_date;
}
if (!empty($date_from)) {
    $fb_conds[] = "DATE(created_at) >= ?";
    $fb_params[] = $date_from;
}
if (!empty($date_to)) {
    $fb_conds[] = "DATE(created_at) <= ?";
    $fb_params[] = $date_to;
}
$fb_where_sql = implode(" AND ", $fb_conds);

$acc_conds = ["1=1"];
$acc_params = [];
if ($selected_org_id > 0) {
    $acc_conds[] = "org_id = ?";
    $acc_params[] = $selected_org_id;
}
if (!empty($filter_date)) {
    $acc_conds[] = "(DATE(date_accomplished) = ? OR DATE(created_at) = ?)";
    $acc_params[] = $filter_date;
    $acc_params[] = $filter_date;
}
if (!empty($date_from)) {
    $acc_conds[] = "(DATE(date_accomplished) >= ? OR DATE(created_at) >= ?)";
    $acc_params[] = $date_from;
    $acc_params[] = $date_from;
}
if (!empty($date_to)) {
    $acc_conds[] = "(DATE(date_accomplished) <= ? OR DATE(created_at) <= ?)";
    $acc_params[] = $date_to;
    $acc_params[] = $date_to;
}
$acc_where_sql = implode(" AND ", $acc_conds);

// ----------------------------------------------------
// 1. Overall Performance Statistics
// ----------------------------------------------------
$stmt = $pdo->prepare("SELECT
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'Accomplished' THEN 1 ELSE 0 END) as accomplished_tasks,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) as overdue_tasks,
    SUM(CASE WHEN status = 'To Do' THEN 1 ELSE 0 END) as todo_tasks
    FROM tasks WHERE $task_where_sql");
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
    AVG(p.progress_percent) as avg_progress
    FROM projects p WHERE $proj_where_sql");
$stmt->execute($proj_params);
$proj_totals = $stmt->fetch();

$total_projects = intval($proj_totals['total_projects'] ?? 0);
$accomplished_projects = intval($proj_totals['accomplished_projects'] ?? 0);
$avg_project_progress = round(floatval($proj_totals['avg_progress'] ?? 0));

// Feedback Stats
$stmt = $pdo->prepare("SELECT
    COUNT(*) as total_feedbacks,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_feedbacks
    FROM feedbacks WHERE $fb_where_sql");
$stmt->execute($fb_params);
$fb_totals = $stmt->fetch();

$total_feedbacks = intval($fb_totals['total_feedbacks'] ?? 0);
$resolved_feedbacks = intval($fb_totals['resolved_feedbacks'] ?? 0);
$feedback_resolution_rate = $total_feedbacks > 0 ? round(($resolved_feedbacks / $total_feedbacks) * 100) : 0;

// Accomplishments count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM accomplishments WHERE $acc_where_sql");
$stmt->execute($acc_params);
$total_accomplishments = intval($stmt->fetchColumn());

// ----------------------------------------------------
// 2. Individual Task Assignment & Monitoring Breakdown
// ----------------------------------------------------
$officer_stats_sql = "SELECT assigned_to,
    COUNT(*) as total_assigned,
    SUM(CASE WHEN status = 'Accomplished' THEN 1 ELSE 0 END) as count_accomplished,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as count_in_progress,
    SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) as count_overdue,
    SUM(CASE WHEN status = 'To Do' THEN 1 ELSE 0 END) as count_todo
    FROM tasks WHERE $task_where_sql
    GROUP BY assigned_to
    ORDER BY total_assigned DESC";
$stmt = $pdo->prepare($officer_stats_sql);
$stmt->execute($task_params);
$officer_performance = $stmt->fetchAll();

// ----------------------------------------------------
// 3. Projects Overview Table
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
// 4. Activity Logs for Tab Panel (Tasks, Projects, Accomplishments)
// ----------------------------------------------------
// 4a. Task Logs
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

// 4b. Project Logs
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

// 4c. Accomplishment Logs
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

// Format human-readable period label
$period_label = 'All Time';
if (!empty($filter_date)) {
    $period_label = date('F d, Y', strtotime($filter_date));
} elseif (!empty($date_from) || !empty($date_to)) {
    $from_str = !empty($date_from) ? date('M d, Y', strtotime($date_from)) : 'Start';
    $to_str = !empty($date_to) ? date('M d, Y', strtotime($date_to)) : 'Present';
    $period_label = "$from_str – $to_str";
}

include_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
  body { background: #fff !important; color: #000 !important; }
  .navbar, .filter-bar, .btn, .no-print { display: none !important; }
  .page-header { border-bottom: 2px solid #000; padding-bottom: 1rem; margin-bottom: 1.5rem; }
  .panel, .table-container, .stat-card { box-shadow: none !important; border: 1px solid #ccc !important; }
  .print-header { display: block !important; text-align: center; margin-bottom: 1.5rem; }
  .print-signatures { display: flex !important; justify-content: space-between; margin-top: 3rem; }
}
.print-header, .print-signatures { display: none; }
</style>

<!-- Print Official Letterhead (Print View Only) -->
<div class="print-header">
  <h2 style="margin: 0; font-size: 1.3rem; text-transform: uppercase; font-weight: 800;">Hear2Lead</h2>
  <h3 style="margin: 0.25rem 0; font-size: 1.1rem; color: #4b5563;">Official Leadership Performance & Accomplishment Report</h3>
  <p style="margin: 0; font-size: 0.9rem; color: #6b7280;">
    Organization: <strong><?= htmlspecialchars($active_org_name) ?> (<?= htmlspecialchars($active_org_acronym) ?>)</strong> •
    Period: <strong><?= htmlspecialchars($period_label) ?></strong> •
    Date Generated: <?= date('F d, Y') ?>
  </p>
  <hr style="margin: 1rem 0; border: none; border-top: 2px solid #1f2937;">
</div>

<!-- Page Header & Action Controls -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-chart-pie" style="color: var(--primary);"></i> Leadership Performance Reports</h1>
    <p class="page-subtitle">
      Comprehensive performance metrics, individual officer accountability, program delivery, and activity logs.
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <button onclick="window.print()" class="btn btn-secondary no-print">
      <i class="fa-solid fa-print"></i> Print Official Report
    </button>
  </div>
</div>

<!-- Filter Bar with Organization Scope, Interactive Calendar & Date Range Filter -->
<div class="filter-bar no-print" style="margin-bottom: 1.5rem;">
  <form method="GET" action="reports.php" id="reportsFilterForm" style="display: flex; gap: 0.85rem; align-items: center; width: 100%; flex-wrap: wrap;">
    <input type="hidden" name="log_tab" id="reportLogTabInput" value="<?= htmlspecialchars($active_log_tab) ?>">

    <?php if ($is_admin): ?>
      <div class="filter-group">
        <label class="form-label" style="margin: 0;">Scope Organization:</label>
        <select name="org_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
          <option value="0" <?= $selected_org_id === 0 ? 'selected' : '' ?>>All Organizations (Consolidated Report)</option>
          <?php foreach ($all_orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $selected_org_id == $o['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <div style="padding: 0.45rem 0.75rem; background: var(--gray-100); border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--gray-700); font-weight: 700;">
        <i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> <?= htmlspecialchars($active_org_acronym) ?>
      </div>
    <?php endif; ?>

    <!-- Interactive Calendar Widget -->
    <div class="filter-group">
      <div class="calendar-dropdown-wrapper" id="reportsCalWrapper">
        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReportsCalendar('reportsCalendarPopover', event)" style="display: inline-flex; align-items: center; gap: 0.4rem;">
          <i class="fa-regular fa-calendar-days" style="color: var(--primary);"></i>
          <span><?= !empty($filter_date) ? date('M d, Y', strtotime($filter_date)) : 'Calendar' ?></span>
          <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem;"></i>
        </button>
        <div class="calendar-popover" id="reportsCalendarPopover" onclick="event.stopPropagation()">
          <div class="cal-header">
            <button type="button" class="cal-nav-btn" onclick="changeReportCalMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="cal-month-title" id="reportsCalMonthTitle"></div>
            <button type="button" class="cal-nav-btn" onclick="changeReportCalMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
          </div>
          <div class="cal-grid" id="reportsCalGrid"></div>
          <div style="margin-top: 0.65rem; padding-top: 0.5rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; font-size: 0.72rem; color: var(--gray-500);">
            <span><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--primary);margin-right:4px;"></span>Has Activity</span>
            <button type="button" class="btn btn-secondary btn-sm" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;" onclick="selectReportCalendarDate('')">All Dates</button>
          </div>
        </div>
      </div>
      <input type="hidden" name="filter_date" id="report_filter_date_input" value="<?= htmlspecialchars($filter_date) ?>">
    </div>

    <!-- Date Range Pickers -->
    <div class="filter-group">
      <label class="form-label" style="margin: 0; font-size: 0.82rem;"><i class="fa-regular fa-calendar"></i> From:</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control" style="width: auto; padding: 0.45rem 0.65rem;" onchange="document.getElementById('report_filter_date_input').value=''; this.form.submit();">
      <label class="form-label" style="margin: 0; font-size: 0.82rem;">To:</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control" style="width: auto; padding: 0.45rem 0.65rem;" onchange="document.getElementById('report_filter_date_input').value=''; this.form.submit();">
    </div>

    <?php if (($is_admin && $selected_org_id > 0) || !empty($filter_date) || !empty($date_from) || !empty($date_to)): ?>
      <a href="reports.php?log_tab=<?= htmlspecialchars($active_log_tab) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
    <?php endif; ?>

    <div style="margin-left: auto; font-size: 0.8rem; color: var(--gray-600); font-weight: 600;">
      <i class="fa-regular fa-clock" style="color: var(--primary);"></i> Period: <strong><?= htmlspecialchars($period_label) ?></strong>
    </div>
  </form>
</div>

<!-- Performance KPI Stat Cards Grid -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
  <!-- Task Completion Rate -->
  <div class="stat-card accomplished">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div>
      <div class="stat-number"><?= $task_completion_rate ?>%</div>
      <div class="stat-label">Task Completion (<?= $accomplished_tasks ?>/<?= $total_tasks ?>)</div>
    </div>
  </div>

  <!-- Project Progress Average -->
  <div class="stat-card in-progress" style="border-left: 4px solid var(--secondary);">
    <div class="stat-icon" style="background: #e0f2fe; color: var(--secondary);"><i class="fa-solid fa-chart-line"></i></div>
    <div>
      <div class="stat-number"><?= $avg_project_progress ?>%</div>
      <div class="stat-label">Avg Project Progress</div>
    </div>
  </div>

  <!-- Feedback Resolution Rate -->
  <div class="stat-card total">
    <div class="stat-icon"><i class="fa-solid fa-comments"></i></div>
    <div>
      <div class="stat-number"><?= $feedback_resolution_rate ?>%</div>
      <div class="stat-label">Feedback Responded (<?= $resolved_feedbacks ?>/<?= $total_feedbacks ?>)</div>
    </div>
  </div>

  <!-- Total Accomplishments Milestone -->
  <div class="stat-card todo">
    <div class="stat-icon"><i class="fa-solid fa-trophy"></i></div>
    <div>
      <div class="stat-number"><?= $total_accomplishments ?></div>
      <div class="stat-label">Verified Accomplishments</div>
    </div>
  </div>
</div>

<!-- Section 1: Individual Task Assignment & Monitoring Table -->
<div class="panel" style="margin-bottom: 2rem;">
  <div class="panel-header">
    <span><i class="fa-solid fa-users-gear" style="color: var(--primary);"></i> Individual Task Assignment & Monitoring</span>
    <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($officer_performance) ?> Assignee Groups</span>
  </div>

  <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
    <table class="table">
      <thead>
        <tr>
          <th>Assigned Officer / Team</th>
          <th>Total Assigned</th>
          <th>Accomplished</th>
          <th>In Progress</th>
          <th>To Do</th>
          <th>Overdue</th>
          <th>Efficiency Score</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($officer_performance)): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--gray-500); padding: 2rem;">No individual task assignment data available for the selected period.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($officer_performance as $row): 
            $rate = $row['total_assigned'] > 0 ? round(($row['count_accomplished'] / $row['total_assigned']) * 100) : 0;
            $rate_color = $rate >= 80 ? 'var(--success)' : ($rate >= 50 ? '#b45309' : 'var(--danger)');
          ?>
            <tr>
              <td><strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($row['assigned_to'] ?: 'Unassigned') ?></strong></td>
              <td><strong><?= $row['total_assigned'] ?></strong> tasks</td>
              <td><span class="badge badge-accomplished"><?= $row['count_accomplished'] ?></span></td>
              <td><span class="badge badge-in-progress"><?= $row['count_in_progress'] ?></span></td>
              <td><span class="badge badge-todo"><?= $row['count_todo'] ?></span></td>
              <td>
                <?php if ($row['count_overdue'] > 0): ?>
                  <span class="badge badge-overdue"><?= $row['count_overdue'] ?></span>
                <?php else: ?>
                  <span style="color: var(--gray-400); font-size: 0.85rem;">0</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 120px;">
                  <div class="progress-container" style="flex: 1; margin: 0;">
                    <div class="progress-bar" style="width: <?= $rate ?>%; background: <?= $rate_color ?>;"></div>
                  </div>
                  <strong style="font-size: 0.85rem; color: <?= $rate_color ?>;"><?= $rate ?>%</strong>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Section 2: Program and Project Milestone Progress -->
<div class="panel" style="margin-bottom: 2rem;">
  <div class="panel-header">
    <span><i class="fa-solid fa-folder-tree" style="color: var(--secondary);"></i> Programs and Projects Milestone Status</span>
    <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($projects_list) ?> Total Programs</span>
  </div>

  <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
    <table class="table">
      <thead>
        <tr>
          <?php if ($selected_org_id === 0): ?>
            <th>Org</th>
          <?php endif; ?>
          <th>Project / Program Name</th>
          <th>Status</th>
          <th>Completion %</th>
          <th>Target Delivery Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($projects_list)): ?>
          <tr>
            <td colspan="<?= $selected_org_id === 0 ? '5' : '4' ?>" style="text-align: center; color: var(--gray-500); padding: 2rem;">No programs recorded for this report period.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($projects_list as $p): 
            $p_status_badges = ['Accomplished' => 'badge-accomplished', 'Ongoing' => 'badge-in-progress', 'Planning' => 'badge-todo'];
            $p_badge = $p_status_badges[$p['status']] ?? 'badge-planning';
          ?>
            <tr>
              <?php if ($selected_org_id === 0): ?>
                <td><span class="badge badge-planning"><strong><?= htmlspecialchars($p['org_acronym']) ?></strong></span></td>
              <?php endif; ?>
              <td><strong style="color: var(--dark);"><?= htmlspecialchars($p['title']) ?></strong></td>
              <td><span class="badge <?= $p_badge ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 140px;">
                  <div class="progress-container" style="flex: 1; margin: 0;">
                    <div class="progress-bar" style="width: <?= $p['progress_percent'] ?>%;"></div>
                  </div>
                  <strong style="font-size: 0.85rem; color: var(--primary);"><?= $p['progress_percent'] ?>%</strong>
                </div>
              </td>
              <td><?= $p['target_date'] ? date('M d, Y', strtotime($p['target_date'])) : 'N/A' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Section 3: Activity & Audit Logs Tab Panel (Tasks, Projects, Accomplishments) -->
<div class="panel">
  <div class="panel-header" style="border-bottom: none; padding-bottom: 0.25rem; margin-bottom: 0.75rem;">
    <span><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Activity & Audit Logs (Tasks, Projects & Accomplishments)</span>
    <span style="font-size: 0.85rem; color: var(--gray-500);">
      <?= count($report_task_logs) + count($report_project_logs) + count($report_acc_logs) ?> Total Logged Actions
    </span>
  </div>

  <!-- Tab Panel Navigation -->
  <div class="user-tabs-nav no-print" style="margin-bottom: 1.25rem;">
    <button type="button" class="user-tab-btn <?= $active_log_tab === 'task_logs' ? 'active' : '' ?>" onclick="switchReportLogTab('task_logs')">
      <i class="fa-solid fa-list-check"></i>
      <span>Task Logs</span>
      <span class="user-tab-badge"><?= count($report_task_logs) ?></span>
    </button>
    <button type="button" class="user-tab-btn <?= $active_log_tab === 'project_logs' ? 'active' : '' ?>" onclick="switchReportLogTab('project_logs')">
      <i class="fa-solid fa-folder-tree"></i>
      <span>Project Logs</span>
      <span class="user-tab-badge"><?= count($report_project_logs) ?></span>
    </button>
    <button type="button" class="user-tab-btn <?= $active_log_tab === 'acc_logs' ? 'active' : '' ?>" onclick="switchReportLogTab('acc_logs')">
      <i class="fa-solid fa-trophy"></i>
      <span>Accomplishment Logs</span>
      <span class="user-tab-badge"><?= count($report_acc_logs) ?></span>
    </button>
  </div>

  <!-- TAB 1: Task Logs -->
  <div class="user-tab-pane <?= $active_log_tab === 'task_logs' ? 'active' : '' ?>" id="report-tab-task_logs">
    <?php if (empty($report_task_logs)): ?>
      <div style="text-align: center; padding: 2.25rem; color: var(--gray-500);">
        <i class="fa-solid fa-list-check" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
        No task activity logs found for the selected filter period.
      </div>
    <?php else: ?>
      <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
        <table class="table">
          <thead>
            <tr>
              <?php if ($selected_org_id === 0): ?>
                <th>Org</th>
              <?php endif; ?>
              <th>Task Title</th>
              <th>Action</th>
              <th>Logged By</th>
              <th>Activity & Change Details</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report_task_logs as $log):
              $action_badge = $log['action_type'] === 'Created' ? 'badge-accomplished' : ($log['action_type'] === 'Status Updated' ? 'badge-todo' : 'badge-in-progress');
            ?>
              <tr>
                <?php if ($selected_org_id === 0): ?>
                  <td><span class="badge badge-planning"><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></span></td>
                <?php endif; ?>
                <td><strong style="color: var(--dark);"><?= htmlspecialchars($log['task_title'] ?? ('Task #' . $log['task_id'])) ?></strong></td>
                <td><span class="badge <?= $action_badge ?>"><?= htmlspecialchars($log['action_type']) ?></span></td>
                <td>
                  <div style="font-weight: 700; color: var(--dark); font-size: 0.88rem;"><?= htmlspecialchars($log['user_name']) ?></div>
                  <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($log['user_role']) ?></div>
                </td>
                <td style="font-size: 0.86rem; color: var(--gray-800); max-width: 420px;"><?= htmlspecialchars($log['log_details']) ?></td>
                <td style="white-space: nowrap; font-size: 0.8rem; color: var(--gray-600);">
                  <i class="fa-regular fa-clock"></i> <?= date('M d, Y • g:i:s A', strtotime($log['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- TAB 2: Project Logs -->
  <div class="user-tab-pane <?= $active_log_tab === 'project_logs' ? 'active' : '' ?>" id="report-tab-project_logs">
    <?php if (empty($report_project_logs)): ?>
      <div style="text-align: center; padding: 2.25rem; color: var(--gray-500);">
        <i class="fa-solid fa-folder-tree" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
        No project activity logs found for the selected filter period.
      </div>
    <?php else: ?>
      <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
        <table class="table">
          <thead>
            <tr>
              <?php if ($selected_org_id === 0): ?>
                <th>Org</th>
              <?php endif; ?>
              <th>Project Title</th>
              <th>Action</th>
              <th>Logged By</th>
              <th>Activity & Progress Details</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report_project_logs as $log):
              $action_badge = $log['action_type'] === 'Created' ? 'badge-accomplished' : ($log['action_type'] === 'Progress Updated' ? 'badge-todo' : 'badge-in-progress');
            ?>
              <tr>
                <?php if ($selected_org_id === 0): ?>
                  <td><span class="badge badge-planning"><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></span></td>
                <?php endif; ?>
                <td><strong style="color: var(--dark);"><?= htmlspecialchars($log['project_title'] ?? ('Project #' . $log['project_id'])) ?></strong></td>
                <td><span class="badge <?= $action_badge ?>"><?= htmlspecialchars($log['action_type']) ?></span></td>
                <td>
                  <div style="font-weight: 700; color: var(--dark); font-size: 0.88rem;"><?= htmlspecialchars($log['user_name']) ?></div>
                  <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($log['user_role']) ?></div>
                </td>
                <td style="font-size: 0.86rem; color: var(--gray-800); max-width: 420px;"><?= htmlspecialchars($log['log_details']) ?></td>
                <td style="white-space: nowrap; font-size: 0.8rem; color: var(--gray-600);">
                  <i class="fa-regular fa-clock"></i> <?= date('M d, Y • g:i:s A', strtotime($log['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- TAB 3: Accomplishment Logs -->
  <div class="user-tab-pane <?= $active_log_tab === 'acc_logs' ? 'active' : '' ?>" id="report-tab-acc_logs">
    <?php if (empty($report_acc_logs)): ?>
      <div style="text-align: center; padding: 2.25rem; color: var(--gray-500);">
        <i class="fa-solid fa-trophy" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
        No accomplishment activity logs found for the selected filter period.
      </div>
    <?php else: ?>
      <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
        <table class="table">
          <thead>
            <tr>
              <?php if ($selected_org_id === 0): ?>
                <th>Org</th>
              <?php endif; ?>
              <th>Accomplishment Title</th>
              <th>Action</th>
              <th>Logged By</th>
              <th>Activity & Document Details</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report_acc_logs as $log):
              $action_badge = $log['action_type'] === 'Created' ? 'badge-accomplished' : 'badge-in-progress';
            ?>
              <tr>
                <?php if ($selected_org_id === 0): ?>
                  <td><span class="badge badge-planning"><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></span></td>
                <?php endif; ?>
                <td><strong style="color: var(--dark);"><?= htmlspecialchars($log['acc_title'] ?? ('Accomplishment #' . $log['acc_id'])) ?></strong></td>
                <td><span class="badge <?= $action_badge ?>"><?= htmlspecialchars($log['action_type']) ?></span></td>
                <td>
                  <div style="font-weight: 700; color: var(--dark); font-size: 0.88rem;"><?= htmlspecialchars($log['user_name']) ?></div>
                  <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($log['user_role']) ?></div>
                </td>
                <td style="font-size: 0.86rem; color: var(--gray-800); max-width: 420px;"><?= htmlspecialchars($log['log_details']) ?></td>
                <td style="white-space: nowrap; font-size: 0.8rem; color: var(--gray-600);">
                  <i class="fa-regular fa-clock"></i> <?= date('M d, Y • g:i:s A', strtotime($log['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Sign-off Block for Print / Official Submission -->
<div class="print-signatures">
  <div style="text-align: center; width: 220px;">
    <div style="border-bottom: 1px solid #000; height: 50px;"></div>
    <p style="margin: 0.5rem 0 0.1rem 0; font-weight: 700;"><?= htmlspecialchars($_SESSION['user_name']) ?></p>
    <p style="margin: 0; font-size: 0.8rem; color: #4b5563;"><?= htmlspecialchars($_SESSION['user_role']) ?>, <?= htmlspecialchars($_SESSION['org_acronym']) ?></p>
    <p style="margin: 0; font-size: 0.75rem; color: #9ca3af;">Prepared By</p>
  </div>

  <div style="text-align: center; width: 220px;">
    <div style="border-bottom: 1px solid #000; height: 50px;"></div>
    <p style="margin: 0.5rem 0 0.1rem 0; font-weight: 700;">Organization Adviser</p>
    <p style="margin: 0; font-size: 0.8rem; color: #4b5563;">Faculty Adviser / Consultant</p>
    <p style="margin: 0; font-size: 0.75rem; color: #9ca3af;">Attested By</p>
  </div>

  <div style="text-align: center; width: 220px;">
    <div style="border-bottom: 1px solid #000; height: 50px;"></div>
    <p style="margin: 0.5rem 0 0.1rem 0; font-weight: 700;">School Principal / Head</p>
    <p style="margin: 0; font-size: 0.8rem; color: #4b5563;">Office of Student Affairs</p>
    <p style="margin: 0; font-size: 0.75rem; color: #9ca3af;">Approved By</p>
  </div>
</div>

<script>
const REPORT_ACTIVITY_DATES = <?= json_encode($report_activity_dates) ?>;
const SELECTED_REPORT_DATE = <?= json_encode($filter_date) ?>;
let currentReportCalDate = SELECTED_REPORT_DATE ? new Date(SELECTED_REPORT_DATE + 'T00:00:00') : new Date();
if (isNaN(currentReportCalDate.getTime())) currentReportCalDate = new Date();

function switchReportLogTab(tabName) {
  const btns = document.querySelectorAll('.user-tabs-nav .user-tab-btn');
  const panes = document.querySelectorAll('.user-tab-pane');
  btns.forEach(btn => btn.classList.remove('active'));
  panes.forEach(pane => pane.classList.remove('active'));

  const tabInput = document.getElementById('reportLogTabInput');
  if (tabInput) tabInput.value = tabName;

  if (tabName === 'project_logs') {
    if (btns[1]) btns[1].classList.add('active');
    const p = document.getElementById('report-tab-project_logs');
    if (p) p.classList.add('active');
  } else if (tabName === 'acc_logs') {
    if (btns[2]) btns[2].classList.add('active');
    const p = document.getElementById('report-tab-acc_logs');
    if (p) p.classList.add('active');
  } else {
    if (btns[0]) btns[0].classList.add('active');
    const p = document.getElementById('report-tab-task_logs');
    if (p) p.classList.add('active');
  }

  const url = new URL(window.location.href);
  url.searchParams.set('log_tab', tabName);
  history.replaceState(null, '', url.toString());
}

function toggleReportsCalendar(popoverId, event) {
  event.stopPropagation();
  const pop = document.getElementById(popoverId);
  if (!pop) return;
  pop.classList.toggle('active');
  if (pop.classList.contains('active')) {
    renderReportsMiniCalendar();
  }
}

document.addEventListener('click', (e) => {
  const pop = document.getElementById('reportsCalendarPopover');
  if (pop && !pop.contains(e.target)) {
    pop.classList.remove('active');
  }
});

function changeReportCalMonth(delta) {
  currentReportCalDate.setMonth(currentReportCalDate.getMonth() + delta);
  renderReportsMiniCalendar();
}

function selectReportCalendarDate(dateStr) {
  const input = document.getElementById('report_filter_date_input');
  const form = document.getElementById('reportsFilterForm');
  if (input && form) {
    input.value = dateStr;
    const fromIn = form.querySelector('input[name="date_from"]');
    const toIn = form.querySelector('input[name="date_to"]');
    if (fromIn) fromIn.value = '';
    if (toIn) toIn.value = '';
    form.submit();
  }
}

function renderReportsMiniCalendar() {
  const titleEl = document.getElementById('reportsCalMonthTitle');
  const gridEl = document.getElementById('reportsCalGrid');
  if (!titleEl || !gridEl) return;

  const year = currentReportCalDate.getFullYear();
  const month = currentReportCalDate.getMonth();
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  titleEl.textContent = `${monthNames[month]} ${year}`;

  const weekdays = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  let html = weekdays.map(d => `<div class="cal-weekday">${d}</div>`).join('');

  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  for (let i = 0; i < firstDay; i++) {
    html += `<div class="cal-day empty"></div>`;
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const mm = String(month + 1).padStart(2, '0');
    const dd = String(d).padStart(2, '0');
    const dateIso = `${year}-${mm}-${dd}`;
    const hasAct = REPORT_ACTIVITY_DATES.includes(dateIso);
    const isSelected = (SELECTED_REPORT_DATE === dateIso);
    const classes = ['cal-day'];
    if (hasAct) classes.push('has-feedback');
    if (isSelected) classes.push('selected');

    html += `<button type="button" class="${classes.join(' ')}" onclick="selectReportCalendarDate('${dateIso}')" title="${hasAct ? 'Activity recorded on ' + dateIso : dateIso}">${d}</button>`;
  }

  gridEl.innerHTML = html;
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

