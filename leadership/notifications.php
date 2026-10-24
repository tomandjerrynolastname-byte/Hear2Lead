<?php
// leadership/notifications.php - Full Notifications Center (Task Deadlines, Tomorrow Reminders, Auto-Overdue Alerts & Who Logged)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_user_name = $_SESSION['user_name'] ?? 'Officer';
$current_role = $_SESSION['user_role'] ?? 'Officer';
$current_org_id = intval($_SESSION['org_id'] ?? 0);

$role_perms = getRolePermissions($current_role, $pdo);
$is_admin = !empty($role_perms['is_admin']);
$can_manage_tasks = !empty($role_perms['can_manage_tasks']);

// Automatically check and transition any tasks past their deadline to 'Overdue' and log notification
syncOverdueTasks($pdo);

$success_msg = '';
$error_msg = '';

// Allow quick task status update directly from a Notification Card
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_task_status') {
    $task_id = intval($_POST['task_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? 'Accomplished');

    $old_stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $old_stmt->execute([$task_id]);
    $old_task = $old_stmt->fetch();

    if ($old_task) {
        if ($is_admin || intval($old_task['org_id']) === $current_org_id) {
            $upd = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            if ($upd->execute([$new_status, $task_id])) {
                $old_st = $old_task['status'];
                $log_details = "Task status updated from '$old_st' to '$new_status' via Notifications Center.";
                $log_stmt = $pdo->prepare("INSERT INTO task_logs (task_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, 'Status Updated', ?)");
                $log_stmt->execute([$task_id, $current_user_id, $current_user_name, $current_role, $log_details]);
                $success_msg = "Task \"{$old_task['title']}\" status updated to '$new_status'!";
            }
        } else {
            $error_msg = "Permission denied to update this task.";
        }
    }
}

// Filters
$selected_org_id = $is_admin ? intval($_GET['org_id'] ?? 0) : $current_org_id;
$search_q = trim($_GET['q'] ?? '');
$active_tab = $_GET['tab'] ?? 'all';
$allowed_tabs = $is_admin ? ['all', 'reminders', 'tasks', 'logins'] : ['all', 'reminders'];
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'all';
}

// Fetch organizations for Admin filter
$all_orgs = [];
if ($is_admin) {
    $all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();
}

// 1. Fetch Task Deadline Reminders / Overdue Alerts (ONLY for the respective assigned personnel)
$effective_is_admin_scope = ($is_admin && $selected_org_id === 0);
$scope_org_id = $selected_org_id > 0 ? $selected_org_id : $current_org_id;

$all_task_reminders = getTaskDeadlineReminders($pdo, $effective_is_admin_scope, $scope_org_id, $current_user_name, $can_manage_tasks, $current_role);

// Filter by search query if provided
if ($search_q !== '') {
    $all_task_reminders = array_values(array_filter($all_task_reminders, function($r) use ($search_q) {
        return stripos($r['title'], $search_q) !== false
            || stripos($r['message'], $search_q) !== false
            || stripos($r['assigned_to'], $search_q) !== false
            || stripos($r['badge_text'], $search_q) !== false;
    }));
}

// Separate urgent reminders (Overdue, Due Today, Due Tomorrow, Almost Due) from general active tasks
$urgent_reminders = [];
$overdue_count = 0;
$tomorrow_and_soon_count = 0;

foreach ($all_task_reminders as $rem) {
    if ($rem['category'] === 'overdue') {
        $overdue_count++;
        $urgent_reminders[] = $rem;
    } elseif (in_array($rem['category'], ['due_today', 'due_tomorrow', 'due_soon'], true)) {
        $tomorrow_and_soon_count++;
        $urgent_reminders[] = $rem;
    }
}

// 2. Fetch Activity & Task Logs + Who Logged In Notifications (Admin Account ONLY)
$task_log_notifs = [];
$login_notifs = [];

if ($is_admin) {
    $tl_conds = ["1=1"];
    $tl_params = [];
    if (!$effective_is_admin_scope && $scope_org_id > 0) {
        $tl_conds[] = "org_id = ?";
        $tl_params[] = $scope_org_id;
    }
    if ($search_q !== '') {
        $tl_conds[] = "(item_title LIKE ? OR user_name LIKE ? OR log_details LIKE ? OR action_type LIKE ? OR module_name LIKE ?)";
        $tl_params[] = "%$search_q%";
        $tl_params[] = "%$search_q%";
        $tl_params[] = "%$search_q%";
        $tl_params[] = "%$search_q%";
        $tl_params[] = "%$search_q%";
    }
    $tl_where = implode(" AND ", $tl_conds);
    $stmt_tl = $pdo->prepare("SELECT * FROM (
        SELECT tl.id, 'Task' as module_name, t.title as item_title, t.status as item_status, t.deadline as item_date, tl.user_name, tl.user_role, tl.action_type, tl.log_details, tl.created_at, t.org_id, o.acronym as org_acronym
        FROM task_logs tl JOIN tasks t ON tl.task_id = t.id LEFT JOIN organizations o ON t.org_id = o.id
        UNION ALL
        SELECT pl.id, 'Project' as module_name, p.title as item_title, p.status as item_status, p.target_date as item_date, pl.user_name, pl.user_role, pl.action_type, pl.log_details, pl.created_at, p.org_id, o.acronym as org_acronym
        FROM project_logs pl JOIN projects p ON pl.project_id = p.id LEFT JOIN organizations o ON p.org_id = o.id
        UNION ALL
        SELECT al.id, 'Accomplishment' as module_name, a.title as item_title, a.badge_tag as item_status, a.date_accomplished as item_date, al.user_name, al.user_role, al.action_type, al.log_details, al.created_at, a.org_id, o.acronym as org_acronym
        FROM accomplishment_logs al JOIN accomplishments a ON al.acc_id = a.id LEFT JOIN organizations o ON a.org_id = o.id
        UNION ALL
        SELECT sl.id, 'Student Account' as module_name, CONCAT(sl.student_name, ' (', sl.student_lrn, ')') as item_title, sl.new_status as item_status, DATE(sl.created_at) as item_date, sl.user_name, sl.user_role, sl.action_type, sl.log_details, sl.created_at, 0 as org_id, 'STUDENT' as org_acronym
        FROM student_logs sl
    ) combined_logs
    WHERE $tl_where
    ORDER BY created_at DESC, id DESC
    LIMIT 100");
    $stmt_tl->execute($tl_params);
    $task_log_notifs = $stmt_tl->fetchAll();

    // 3. Fetch Who Logged In Notifications (Admin Account ONLY)
    $ln_conds = ["1=1"];
    $ln_params = [];
    if (!$effective_is_admin_scope && $scope_org_id > 0) {
        $ln_conds[] = "org_id = ?";
        $ln_params[] = $scope_org_id;
    }
    if ($search_q !== '') {
        $ln_conds[] = "(user_name LIKE ? OR user_role LIKE ? OR log_details LIKE ? OR org_acronym LIKE ?)";
        $ln_params[] = "%$search_q%";
        $ln_params[] = "%$search_q%";
        $ln_params[] = "%$search_q%";
        $ln_params[] = "%$search_q%";
    }
    $ln_where = implode(" AND ", $ln_conds);
    $stmt_ln = $pdo->prepare("SELECT * FROM login_logs WHERE $ln_where ORDER BY created_at DESC, id DESC LIMIT 100");
    $stmt_ln->execute($ln_params);
    $login_notifs = $stmt_ln->fetchAll();
}

$total_all_count = $is_admin
    ? (count($all_task_reminders) + count($task_log_notifs) + count($login_notifs))
    : count($all_task_reminders);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <i class="fa-solid fa-bell" style="color: var(--primary);"></i> Notifications & Deadline Monitor
    </h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Monitor assigned task target completion reminders, automatic overdue status alerts, module activity logs, and user login activity.
      <?php else: ?>
        Monitor your assigned task target completion reminders (including tomorrow's deadlines) and automatic overdue status alerts.
      <?php endif; ?>
    </p>
  </div>
  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <a href="tasks.php" class="btn btn-primary">
      <i class="fa-solid fa-list-check"></i> Go to Task Organizer
    </a>
    <?php if ($is_admin || $can_view_reports): ?>
      <a href="reports.php#section-activity-logs" class="btn btn-secondary">
        <i class="fa-solid fa-chart-pie"></i> View Audit Reports
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($success_msg)): ?>
  <div class="toast toast-success alert-auto-dismiss" role="alert">
    <div class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="toast-body">
      <div class="toast-title">Success</div>
      <div class="toast-message"><?= htmlspecialchars($success_msg) ?></div>
    </div>
    <button type="button" class="toast-close" aria-label="Close">&times;</button>
    <div class="toast-progress"><div class="toast-progress-bar"></div></div>
  </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
  <div class="toast toast-danger alert-auto-dismiss" role="alert">
    <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
    <div class="toast-body">
      <div class="toast-title">Notice</div>
      <div class="toast-message"><?= htmlspecialchars($error_msg) ?></div>
    </div>
    <button type="button" class="toast-close" aria-label="Close">&times;</button>
    <div class="toast-progress"><div class="toast-progress-bar"></div></div>
  </div>
<?php endif; ?>

<!-- Summary KPI Cards -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
  <div class="stat-card overdue" onclick="switchNotifPageTab('reminders')" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div>
      <div class="stat-number"><?= $overdue_count ?></div>
      <div class="stat-label">Overdue (Auto-Marked)</div>
    </div>
  </div>

  <div class="stat-card todo" onclick="switchNotifPageTab('reminders')" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
    <div>
      <div class="stat-number"><?= $tomorrow_and_soon_count ?></div>
      <div class="stat-label">Tomorrow / Near Deadline</div>
    </div>
  </div>

  <div class="stat-card total" onclick="switchNotifPageTab('all')" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
    <div>
      <div class="stat-number"><?= count($all_task_reminders) ?></div>
      <div class="stat-label">My Assigned Tasks</div>
    </div>
  </div>

  <?php if ($is_admin): ?>
    <div class="stat-card accomplished" onclick="switchNotifPageTab('logins')" style="cursor: pointer;">
      <div class="stat-icon"><i class="fa-solid fa-right-to-bracket"></i></div>
      <div>
        <div class="stat-number"><?= count($login_notifs) ?></div>
        <div class="stat-label">Who Logged In (Admin)</div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Filter & Search Bar -->
<div class="filter-bar" style="margin-bottom: 1.5rem;">
  <form method="GET" action="notifications.php" style="display: flex; gap: 0.85rem; align-items: center; width: 100%; flex-wrap: wrap;">
    <input type="hidden" name="tab" id="notifFilterTabInput" value="<?= htmlspecialchars($active_tab) ?>">

    <div style="flex: 2; min-width: 240px; position: relative;">
      <input type="text" name="q" class="form-control" placeholder="<?= $is_admin ? 'Search notifications by task title, officer name, role, or status...' : 'Search your assigned task reminders by title or status...' ?>" value="<?= htmlspecialchars($search_q) ?>" style="padding-left: 2.25rem;">
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
    </div>

    <?php if ($is_admin): ?>
      <div style="flex: 1; min-width: 220px;">
        <select name="org_id" class="form-control" onchange="this.form.submit()">
          <option value="0" <?= $selected_org_id === 0 ? 'selected' : '' ?>>All Organizations (System-wide)</option>
          <?php foreach ($all_orgs as $org): ?>
            <option value="<?= $org['id'] ?>" <?= $selected_org_id == $org['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
    <?php if ($search_q !== '' || ($is_admin && $selected_org_id > 0)): ?>
      <a href="notifications.php?tab=<?= urlencode($active_tab) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Tab Panel Navigation -->
<div class="user-tabs-nav">
  <button type="button" class="user-tab-btn <?= $active_tab === 'all' ? 'active' : '' ?>" id="npage-btn-all" onclick="switchNotifPageTab('all')">
    <i class="fa-solid fa-bell"></i>
    <span><?= $is_admin ? 'All Notifications' : 'My Assigned Task Notifications' ?></span>
    <span class="user-tab-badge"><?= $total_all_count ?></span>
  </button>
  <button type="button" class="user-tab-btn <?= $active_tab === 'reminders' ? 'active' : '' ?>" id="npage-btn-reminders" onclick="switchNotifPageTab('reminders')">
    <i class="fa-solid fa-hourglass-half"></i>
    <span>Deadline & Overdue Reminders</span>
    <span class="user-tab-badge" style="<?= ($overdue_count + $tomorrow_and_soon_count) > 0 ? 'background: #fee2e2; color: #991b1b;' : '' ?>">
      <?= count($urgent_reminders) ?>
    </span>
  </button>
  <?php if ($is_admin): ?>
    <button type="button" class="user-tab-btn <?= $active_tab === 'tasks' ? 'active' : '' ?>" id="npage-btn-tasks" onclick="switchNotifPageTab('tasks')">
      <i class="fa-solid fa-clock-rotate-left"></i>
      <span>Activity & Task Logs</span>
      <span class="user-tab-badge"><?= count($task_log_notifs) ?></span>
    </button>
    <button type="button" class="user-tab-btn <?= $active_tab === 'logins' ? 'active' : '' ?>" id="npage-btn-logins" onclick="switchNotifPageTab('logins')">
      <i class="fa-solid fa-right-to-bracket"></i>
      <span>Who Logged In</span>
      <span class="user-tab-badge"><?= count($login_notifs) ?></span>
    </button>
  <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- TAB 1: ALL NOTIFICATIONS                                 -->
<!-- ======================================================== -->
<div class="user-tab-pane <?= $active_tab === 'all' ? 'active' : '' ?>" id="npage-pane-all">

  <!-- Section A: Assigned Task Reminders & Overdue Alerts -->
  <div class="panel" style="margin-bottom: 1.5rem;">
    <div class="panel-header">
      <span><i class="fa-solid fa-bell" style="color: var(--danger);"></i> Assigned Task Target Completion & Overdue Notifications</span>
      <span class="badge badge-planning"><?= count($all_task_reminders) ?> Assigned Task(s)</span>
    </div>

    <?php if (empty($all_task_reminders)): ?>
      <p style="text-align: center; color: var(--gray-500); padding: 1.75rem;">
        <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1.6rem; display: block; margin-bottom: 0.4rem;"></i>
        No pending or overdue tasks are currently assigned to you.
      </p>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($all_task_reminders as $rem): ?>
          <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.15rem; border-radius: var(--radius-sm); background: <?= htmlspecialchars($rem['bg_color']) ?>; border: 1px solid <?= htmlspecialchars($rem['border_color']) ?>; border-left: 4px solid <?= htmlspecialchars($rem['color']) ?>;">
            <div style="display: flex; align-items: flex-start; gap: 0.85rem; flex: 1; min-width: 260px;">
              <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= htmlspecialchars($rem['color']) ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                <i class="fa-solid <?= htmlspecialchars($rem['icon']) ?>"></i>
              </div>
              <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                  <span class="badge <?= htmlspecialchars($rem['badge_class']) ?>"><?= htmlspecialchars($rem['badge_text']) ?></span>
                  <span class="badge badge-todo" style="font-size: 0.7rem;"><?= htmlspecialchars($rem['module']) ?></span>
                  <?php if (!empty($rem['org_acronym'])): ?>
                    <span class="badge badge-planning" style="font-size: 0.7rem;"><strong><?= htmlspecialchars($rem['org_acronym']) ?></strong></span>
                  <?php endif; ?>
                  <span style="font-size: 0.76rem; color: var(--gray-600); font-weight: 700;">
                    <i class="fa-regular fa-calendar"></i> Target Completion: <?= htmlspecialchars($rem['deadline_formatted']) ?>
                  </span>
                </div>
                <div style="font-size: 0.96rem; font-weight: 800; color: var(--dark); margin-bottom: 0.2rem;">
                  <?= htmlspecialchars($rem['headline']) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--gray-700); margin-bottom: 0.3rem;">
                  <?= htmlspecialchars($rem['message']) ?>
                </div>
                <div style="font-size: 0.78rem; color: var(--gray-600);">
                  <i class="fa-solid fa-user-tag"></i> Assigned To: <strong><?= htmlspecialchars($rem['assigned_to']) ?></strong> • Priority: <strong><?= htmlspecialchars($rem['priority']) ?></strong>
                </div>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
              <?php if ($rem['module'] === 'Task'): ?>
                <form method="POST" action="notifications.php?tab=all" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                  <input type="hidden" name="action" value="quick_task_status">
                  <input type="hidden" name="task_id" value="<?= $rem['item_id'] ?>">
                  <input type="hidden" name="new_status" value="Accomplished">
                  <button type="submit" class="btn btn-primary btn-sm" style="background: #059669; border-color: #059669;" title="Mark Task Accomplished">
                    <i class="fa-solid fa-check"></i> Mark Accomplished
                  </button>
                </form>
              <?php endif; ?>
              <a href="<?= htmlspecialchars($rem['link']) ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View <?= htmlspecialchars($rem['module']) ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($is_admin): ?>
    <!-- Section B: Recent Who Logged In (Admin Account ONLY) -->
    <div class="panel" style="margin-bottom: 1.5rem;">
      <div class="panel-header">
        <span><i class="fa-solid fa-right-to-bracket" style="color: var(--primary);"></i> Recent User Login Notifications (Who Logged In)</span>
        <button type="button" class="btn btn-secondary btn-sm" onclick="switchNotifPageTab('logins')">View All Logins</button>
      </div>
      <div class="table-container" style="border: none; box-shadow: none;">
        <table class="table">
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Who Logged In</th>
              <th>Role</th>
              <th>Organization / Portal</th>
              <th>Notification Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($login_notifs)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--gray-500); padding: 2rem;">No login notifications found.</td>
              </tr>
            <?php else: ?>
              <?php foreach (array_slice($login_notifs, 0, 10) as $ln):
                $is_student_ln = (strcasecmp($ln['account_type'] ?? '', 'Student') === 0);
                $ln_perms = !$is_student_ln && function_exists('getRolePermissions') ? getRolePermissions($ln['user_role'], $pdo) : null;
                $ln_badge = $is_student_ln ? 'badge-planning' : ($ln_perms['badge_style'] ?? 'badge-role-officer');
              ?>
                <tr>
                  <td style="white-space: nowrap; font-size: 0.82rem; font-weight: 600; color: var(--gray-600);">
                    <i class="fa-regular fa-clock" style="color: var(--primary);"></i>
                    <?= date('M d, Y • g:i:s A', strtotime($ln['created_at'])) ?>
                  </td>
                  <td><strong><?= htmlspecialchars($ln['user_name']) ?></strong></td>
                  <td><span class="badge <?= htmlspecialchars($ln_badge) ?>"><?= htmlspecialchars($ln['user_role']) ?></span></td>
                  <td>
                    <span class="badge badge-planning">
                      <?= htmlspecialchars(!empty($ln['org_acronym']) ? $ln['org_acronym'] : $ln['account_type']) ?>
                    </span>
                  </td>
                  <td style="font-size: 0.85rem; color: var(--gray-700);">
                    <i class="fa-solid fa-circle-check" style="color: #10b981;"></i> <?= htmlspecialchars($ln['log_details']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Section C: Recent Module Activity Logs (Admin Account ONLY) -->
    <div class="panel">
      <div class="panel-header">
        <span><i class="fa-solid fa-clock-rotate-left" style="color: var(--accent);"></i> Recent Activity & Task Logs</span>
        <button type="button" class="btn btn-secondary btn-sm" onclick="switchNotifPageTab('tasks')">View All Logs</button>
      </div>
      <div class="table-container" style="border: none; box-shadow: none;">
        <table class="table">
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Module & Item</th>
              <th>Org</th>
              <th>Logged By</th>
              <th>Action</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($task_log_notifs)): ?>
              <tr>
                <td colspan="6" style="text-align: center; color: var(--gray-500); padding: 2rem;">No activity logs found.</td>
              </tr>
            <?php else: ?>
              <?php foreach (array_slice($task_log_notifs, 0, 10) as $tl):
                $is_auto_overdue = ($tl['action_type'] === 'Auto-Overdue');
                $t_badge = 'badge-in-progress';
                if ($is_auto_overdue) $t_badge = 'badge-overdue';
                elseif ($tl['action_type'] === 'Created') $t_badge = 'badge-accomplished';
              ?>
                <tr style="<?= $is_auto_overdue ? 'background: #fef2f2;' : '' ?>">
                  <td style="white-space: nowrap; font-size: 0.82rem; font-weight: 600; color: var(--gray-600);">
                    <i class="fa-regular fa-clock" style="color: <?= $is_auto_overdue ? 'var(--danger)' : 'var(--primary)' ?>;"></i>
                    <?= date('M d, Y • g:i:s A', strtotime($tl['created_at'])) ?>
                  </td>
                  <td>
                    <span class="badge badge-todo" style="font-size: 0.65rem; margin-right: 0.25rem;"><?= htmlspecialchars($tl['module_name']) ?></span>
                    <strong style="color: var(--dark);"><?= htmlspecialchars($tl['item_title']) ?></strong>
                  </td>
                  <td><span class="badge badge-planning"><strong><?= htmlspecialchars($tl['org_acronym'] ?? 'ORG') ?></strong></span></td>
                  <td>
                    <strong><?= htmlspecialchars($tl['user_name']) ?></strong>
                    <span class="badge badge-role-officer" style="font-size: 0.65rem; margin-left: 0.25rem;"><?= htmlspecialchars($tl['user_role']) ?></span>
                  </td>
                  <td><span class="badge <?= $t_badge ?>"><?= htmlspecialchars($tl['action_type']) ?></span></td>
                  <td style="font-size: 0.84rem; color: var(--gray-700); max-width: 380px;">
                    <?= htmlspecialchars($tl['log_details']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- TAB 2: DEADLINE & OVERDUE REMINDERS                      -->
<!-- ======================================================== -->
<div class="user-tab-pane <?= $active_tab === 'reminders' ? 'active' : '' ?>" id="npage-pane-reminders">
  <div class="panel">
    <div class="panel-header">
      <span><i class="fa-solid fa-hourglass-half" style="color: var(--warning);"></i> Urgent Target Completion Reminders (Tomorrow / Today) & Automatic Overdue Alerts</span>
      <span class="badge badge-overdue"><?= count($urgent_reminders) ?> Urgent Item(s)</span>
    </div>

    <?php if (empty($urgent_reminders)): ?>
      <div style="text-align: center; color: var(--gray-500); padding: 2.5rem;">
        <i class="fa-solid fa-calendar-check" style="font-size: 2.2rem; color: #10b981; margin-bottom: 0.6rem; display: block;"></i>
        <strong>No overdue or due-tomorrow tasks assigned to you!</strong>
        <p style="font-size: 0.85rem; margin-top: 0.25rem;">Tasks assigned to you that are approaching their deadline (tomorrow or within 3 days) or have exceeded their deadline will automatically appear here.</p>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($urgent_reminders as $rem): ?>
          <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding: 1rem 1.15rem; border-radius: var(--radius-sm); background: <?= htmlspecialchars($rem['bg_color']) ?>; border: 1px solid <?= htmlspecialchars($rem['border_color']) ?>; border-left: 4px solid <?= htmlspecialchars($rem['color']) ?>;">
            <div style="display: flex; align-items: flex-start; gap: 0.85rem; flex: 1; min-width: 260px;">
              <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= htmlspecialchars($rem['color']) ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0;">
                <i class="fa-solid <?= htmlspecialchars($rem['icon']) ?>"></i>
              </div>
              <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                  <span class="badge <?= htmlspecialchars($rem['badge_class']) ?>"><?= htmlspecialchars($rem['badge_text']) ?></span>
                  <span class="badge badge-todo" style="font-size: 0.7rem;"><?= htmlspecialchars($rem['module']) ?></span>
                  <?php if (!empty($rem['org_acronym'])): ?>
                    <span class="badge badge-planning" style="font-size: 0.7rem;"><strong><?= htmlspecialchars($rem['org_acronym']) ?></strong></span>
                  <?php endif; ?>
                  <span style="font-size: 0.76rem; color: var(--gray-600); font-weight: 700;">
                    <i class="fa-regular fa-calendar"></i> Target Completion: <?= htmlspecialchars($rem['deadline_formatted']) ?>
                  </span>
                </div>
                <div style="font-size: 0.96rem; font-weight: 800; color: var(--dark); margin-bottom: 0.2rem;">
                  <?= htmlspecialchars($rem['headline']) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--gray-700); margin-bottom: 0.3rem;">
                  <?= htmlspecialchars($rem['message']) ?>
                </div>
                <div style="font-size: 0.78rem; color: var(--gray-600);">
                  <i class="fa-solid fa-user-tag"></i> Assigned To: <strong><?= htmlspecialchars($rem['assigned_to']) ?></strong> • Priority: <strong><?= htmlspecialchars($rem['priority']) ?></strong>
                </div>
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
              <?php if ($rem['module'] === 'Task'): ?>
                <form method="POST" action="notifications.php?tab=reminders" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                  <input type="hidden" name="action" value="quick_task_status">
                  <input type="hidden" name="task_id" value="<?= $rem['item_id'] ?>">
                  <input type="hidden" name="new_status" value="Accomplished">
                  <button type="submit" class="btn btn-primary btn-sm" style="background: #059669; border-color: #059669;">
                    <i class="fa-solid fa-check"></i> Mark Accomplished
                  </button>
                </form>
              <?php endif; ?>
              <a href="<?= htmlspecialchars($rem['link']) ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open <?= htmlspecialchars($rem['module']) ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($is_admin): ?>
  <!-- ======================================================== -->
  <!-- TAB 3: ACTIVITY & TASK LOGS (ADMIN ONLY)                 -->
  <!-- ======================================================== -->
  <div class="user-tab-pane <?= $active_tab === 'tasks' ? 'active' : '' ?>" id="npage-pane-tasks">
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Module & Title</th>
            <th>Org</th>
            <th>Triggered By</th>
            <th>Notification Type</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($task_log_notifs)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--gray-500); padding: 2.5rem;">
                No activity or task logs found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($task_log_notifs as $tl):
              $is_auto_overdue = ($tl['action_type'] === 'Auto-Overdue');
              $t_badge = 'badge-in-progress';
              if ($is_auto_overdue) $t_badge = 'badge-overdue';
              elseif ($tl['action_type'] === 'Created') $t_badge = 'badge-accomplished';
            ?>
              <tr style="<?= $is_auto_overdue ? 'background: #fef2f2;' : '' ?>">
                <td style="white-space: nowrap; font-size: 0.82rem; font-weight: 600; color: var(--gray-600);">
                  <i class="fa-regular fa-clock" style="color: <?= $is_auto_overdue ? 'var(--danger)' : 'var(--primary)' ?>;"></i>
                  <?= date('M d, Y • g:i:s A', strtotime($tl['created_at'])) ?>
                </td>
                <td>
                  <span class="badge badge-todo" style="font-size: 0.65rem; margin-bottom: 0.15rem;"><?= htmlspecialchars($tl['module_name']) ?></span>
                  <div><strong style="color: var(--dark);"><?= htmlspecialchars($tl['item_title']) ?></strong></div>
                  <?php if (!empty($tl['item_date'])): ?>
                    <div style="font-size: 0.74rem; color: var(--gray-500); margin-top: 0.1rem;">
                      <i class="fa-regular fa-calendar"></i> Date: <strong><?= date('M d, Y', strtotime($tl['item_date'])) ?></strong> • Status: <strong><?= htmlspecialchars($tl['item_status']) ?></strong>
                    </div>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-planning"><strong><?= htmlspecialchars($tl['org_acronym'] ?? 'ORG') ?></strong></span></td>
                <td>
                  <strong><?= htmlspecialchars($tl['user_name']) ?></strong>
                  <span class="badge badge-role-officer" style="font-size: 0.65rem; margin-left: 0.25rem;"><?= htmlspecialchars($tl['user_role']) ?></span>
                </td>
                <td><span class="badge <?= $t_badge ?>"><?= htmlspecialchars($tl['action_type']) ?></span></td>
                <td style="font-size: 0.84rem; color: var(--gray-700); max-width: 380px;">
                  <?= htmlspecialchars($tl['log_details']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ======================================================== -->
  <!-- TAB 4: WHO LOGGED IN (ADMIN ONLY)                        -->
  <!-- ======================================================== -->
  <div class="user-tab-pane <?= $active_tab === 'logins' ? 'active' : '' ?>" id="npage-pane-logins">
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Login Date & Time</th>
            <th>Account Name</th>
            <th>Role</th>
            <th>Organization / Portal</th>
            <th>Login Notification Message</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($login_notifs)): ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--gray-500); padding: 2.5rem;">
                No user login notifications recorded yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($login_notifs as $ln):
              $is_student_ln = (strcasecmp($ln['account_type'] ?? '', 'Student') === 0);
              $ln_perms = !$is_student_ln && function_exists('getRolePermissions') ? getRolePermissions($ln['user_role'], $pdo) : null;
              $ln_badge = $is_student_ln ? 'badge-planning' : ($ln_perms['badge_style'] ?? 'badge-role-officer');
            ?>
              <tr>
                <td style="white-space: nowrap; font-size: 0.82rem; font-weight: 600; color: var(--gray-600);">
                  <i class="fa-regular fa-clock" style="color: var(--primary);"></i>
                  <?= date('M d, Y • g:i:s A', strtotime($ln['created_at'])) ?>
                </td>
                <td><strong style="color: var(--dark);"><?= htmlspecialchars($ln['user_name']) ?></strong></td>
                <td><span class="badge <?= htmlspecialchars($ln_badge) ?>"><?= htmlspecialchars($ln['user_role']) ?></span></td>
                <td>
                  <span class="badge badge-planning">
                    <strong><?= htmlspecialchars(!empty($ln['org_acronym']) ? $ln['org_acronym'] : $ln['account_type']) ?></strong>
                  </span>
                </td>
                <td style="font-size: 0.85rem; color: var(--gray-700);">
                  <i class="fa-solid fa-circle-check" style="color: #10b981; margin-right: 0.3rem;"></i>
                  <?= htmlspecialchars($ln['log_details']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<script>
function switchNotifPageTab(tabName) {
  var tabs = ['all', 'reminders', 'tasks', 'logins'];
  tabs.forEach(function(t) {
    var btn = document.getElementById('npage-btn-' + t);
    var pane = document.getElementById('npage-pane-' + t);
    if (btn) btn.classList.toggle('active', t === tabName);
    if (pane) pane.classList.toggle('active', t === tabName);
  });
  var hiddenTab = document.getElementById('notifFilterTabInput');
  if (hiddenTab) hiddenTab.value = tabName;

  var url = new URL(window.location.href);
  url.searchParams.set('tab', tabName);
  history.replaceState(null, '', url.toString());
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

