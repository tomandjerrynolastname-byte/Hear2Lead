<?php
// leadership/tasks.php - Task Organizer & Management for Leadership Officers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_role = $_SESSION['user_role'] ?? 'Officer';
$current_user_name = $_SESSION['user_name'] ?? '';
$is_admin = ($current_role === 'Admin');
$is_pres_or_vp = in_array($current_role, ['President', 'Vice President'], true);
$can_manage_tasks = ($is_admin || $is_pres_or_vp);

$current_org_id = intval($_SESSION['org_id']);
$filter_org = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;

$success_msg = '';
$error_msg = '';

// Update overdue tasks automatically
if ($filter_org > 0) {
    $pdo->exec("UPDATE tasks SET status = 'Overdue' WHERE org_id = $filter_org AND deadline < CURDATE() AND status != 'Accomplished'");
} else {
    $pdo->exec("UPDATE tasks SET status = 'Overdue' WHERE deadline < CURDATE() AND status != 'Accomplished'");
}

// 1. Handle Task Actions (Only Admin, President, and Vice President can add/edit/delete tasks)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$can_manage_tasks) {
        $error_msg = "Permission denied. Only Admin, President, and Vice President can add or modify tasks.";
    } else {
        if ($action === 'create_task') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'To Do';
            $priority = $_POST['priority'] ?? 'Medium';
            $deadline = $_POST['deadline'] ?? '';

            $assigned_input = $_POST['assigned_to'] ?? [];
            if (is_array($assigned_input)) {
                $clean_assigned = array_values(array_filter(array_map('trim', $assigned_input)));
                $assigned_to = implode(', ', $clean_assigned);
            } else {
                $assigned_to = trim((string)$assigned_input);
            }

            $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
            $task_org_id = $is_admin ? (!empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id) : $current_org_id;

            if (empty($title) || empty($deadline) || empty($task_org_id)) {
                $error_msg = "Task title and deadline date are required.";
            } elseif (empty($assigned_to)) {
                $error_msg = "Please select at least one account in 'Assigned To'.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO tasks (org_id, project_id, title, description, status, priority, deadline, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$task_org_id, $project_id, $title, $description, $status, $priority, $deadline, $assigned_to])) {
                    $new_task_id = intval($pdo->lastInsertId());
                    $log_details = "Task created with title \"$title\", status '$status', priority '$priority', deadline " . date('M d, Y', strtotime($deadline)) . ", assigned to: $assigned_to.";
                    $log_stmt = $pdo->prepare("INSERT INTO task_logs (task_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, 'Created', ?)");
                    $log_stmt->execute([$new_task_id, $current_user_id, $current_user_name, $current_role, $log_details]);

                    $success_msg = "New task created and logged successfully!";
                } else {
                    $error_msg = "Failed to create task.";
                }
            }
        }

        if ($action === 'update_task') {
            $task_id = intval($_POST['task_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'To Do';
            $priority = $_POST['priority'] ?? 'Medium';
            $deadline = $_POST['deadline'] ?? '';

            $assigned_input = $_POST['assigned_to'] ?? [];
            if (is_array($assigned_input)) {
                $clean_assigned = array_values(array_filter(array_map('trim', $assigned_input)));
                $assigned_to = implode(', ', $clean_assigned);
            } else {
                $assigned_to = trim((string)$assigned_input);
            }

            $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;

            // Fetch existing task before update for detailed change logging
            $old_stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $old_stmt->execute([$task_id]);
            $old_task = $old_stmt->fetch();

            if (!$old_task) {
                $error_msg = "Task not found.";
            } elseif (empty($title) || empty($deadline)) {
                $error_msg = "Task title and deadline date are required.";
            } elseif (empty($assigned_to)) {
                $error_msg = "Please select at least one account in 'Assigned To'.";
            } else {
                $updated = false;
                if ($is_admin) {
                    $task_org_id = !empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id;
                    $stmt = $pdo->prepare("UPDATE tasks SET org_id = ?, project_id = ?, title = ?, description = ?, status = ?, priority = ?, deadline = ?, assigned_to = ? WHERE id = ?");
                    $updated = $stmt->execute([$task_org_id, $project_id, $title, $description, $status, $priority, $deadline, $assigned_to, $task_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE tasks SET project_id = ?, title = ?, description = ?, status = ?, priority = ?, deadline = ?, assigned_to = ? WHERE id = ? AND org_id = ?");
                    $updated = $stmt->execute([$project_id, $title, $description, $status, $priority, $deadline, $assigned_to, $task_id, $current_org_id]);
                }

                if ($updated) {
                    $changes = [];
                    if ($old_task['title'] !== $title) {
                        $changes[] = "Title changed from \"{$old_task['title']}\" to \"$title\"";
                    }
                    if (($old_task['description'] ?? '') !== $description) {
                        $changes[] = "Description updated";
                    }
                    if ($old_task['status'] !== $status) {
                        $changes[] = "Status changed from '{$old_task['status']}' to '$status'";
                    }
                    if ($old_task['priority'] !== $priority) {
                        $changes[] = "Priority changed from '{$old_task['priority']}' to '$priority'";
                    }
                    if ($old_task['deadline'] !== $deadline) {
                        $changes[] = "Deadline changed from " . date('M d, Y', strtotime($old_task['deadline'])) . " to " . date('M d, Y', strtotime($deadline));
                    }
                    if (($old_task['assigned_to'] ?? '') !== $assigned_to) {
                        $changes[] = "Assigned To updated to: $assigned_to";
                    }
                    if (intval($old_task['project_id'] ?? 0) !== intval($project_id ?? 0)) {
                        $changes[] = "Linked Project updated";
                    }

                    $log_details = !empty($changes)
                        ? implode(' • ', $changes)
                        : "Task saved (Status: '$status', Priority: '$priority', Assigned to: $assigned_to).";

                    $log_stmt = $pdo->prepare("INSERT INTO task_logs (task_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, 'Updated', ?)");
                    $log_stmt->execute([$task_id, $current_user_id, $current_user_name, $current_role, $log_details]);

                    $success_msg = "Task updated and logged successfully!";
                } else {
                    $error_msg = "Failed to update task.";
                }
            }
        }

        if ($action === 'quick_status') {
            $task_id = intval($_POST['task_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? 'To Do';

            $old_stmt = $pdo->prepare("SELECT status, title FROM tasks WHERE id = ?");
            $old_stmt->execute([$task_id]);
            $old_task = $old_stmt->fetch();

            if ($is_admin) {
                $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $task_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND org_id = ?");
                $stmt->execute([$new_status, $task_id, $current_org_id]);
            }

            $old_status_label = $old_task['status'] ?? 'Previous';
            $log_details = "Task status changed from '$old_status_label' to '$new_status'.";
            $log_stmt = $pdo->prepare("INSERT INTO task_logs (task_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, 'Status Updated', ?)");
            $log_stmt->execute([$task_id, $current_user_id, $current_user_name, $current_role, $log_details]);

            $success_msg = "Task status changed to '$new_status' and logged.";
        }

        if ($action === 'delete_task') {
            $task_id = intval($_POST['task_id'] ?? 0);
            if ($is_admin) {
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->execute([$task_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND org_id = ?");
                $stmt->execute([$task_id, $current_org_id]);
            }
            $success_msg = "Task deleted successfully.";
        }
    }
}

// Fetch all orgs for Admin filter dropdown
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

// Fetch accounts (only full_name, role, and org_id) for "Assigned To" multi-select
if ($is_admin) {
    $stmt_users = $pdo->query("SELECT id, org_id, full_name, role FROM users WHERE role != 'Admin' ORDER BY CASE role WHEN 'President' THEN 1 WHEN 'Vice President' THEN 2 WHEN 'Secretary' THEN 3 WHEN 'Treasurer' THEN 4 ELSE 5 END, full_name ASC");
    $org_users = $stmt_users->fetchAll();
} else {
    $stmt_users = $pdo->prepare("SELECT id, org_id, full_name, role FROM users WHERE org_id = ? AND role != 'Admin' ORDER BY CASE role WHEN 'President' THEN 1 WHEN 'Vice President' THEN 2 WHEN 'Secretary' THEN 3 WHEN 'Treasurer' THEN 4 ELSE 5 END, full_name ASC");
    $stmt_users->execute([$current_org_id]);
    $org_users = $stmt_users->fetchAll();
}

// Filter variables
$filter_status = $_GET['status'] ?? '';
$filter_priority = $_GET['priority'] ?? '';

// Build Query
$where = ["1=1"];
$params = [];

if ($filter_org > 0) {
    $where[] = "t.org_id = ?";
    $params[] = $filter_org;
}
if (!$can_manage_tasks) {
    // Other accounts can only view the tasks assigned to them
    $where[] = "(t.assigned_to LIKE ? OR t.assigned_to = 'All Officers')";
    $params[] = '%' . $current_user_name . '%';
}
if (!empty($filter_status)) {
    $where[] = "t.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_priority)) {
    $where[] = "t.priority = ?";
    $params[] = $filter_priority;
}

$where_sql = implode(" AND ", $where);
$query = "SELECT t.*, p.title as project_title, o.acronym as org_acronym, o.name as org_name
          FROM tasks t 
          LEFT JOIN projects p ON t.project_id = p.id 
          LEFT JOIN organizations o ON t.org_id = o.id 
          WHERE $where_sql 
          ORDER BY CASE WHEN t.status = 'Overdue' THEN 1 WHEN t.status = 'To Do' THEN 2 WHEN t.status = 'In Progress' THEN 3 ELSE 4 END, t.deadline ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'logs') ? 'logs' : 'tasks';

// Fetch Task Activity Logs for displayed tasks
$task_logs_by_task = [];
$all_task_logs = [];
if (!empty($tasks)) {
    $task_ids = array_map(function($t) { return intval($t['id']); }, $tasks);
    $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
    $stmt_logs = $pdo->prepare("SELECT tl.*, t.title as task_title, t.status as task_status, o.acronym as org_acronym
                                FROM task_logs tl
                                JOIN tasks t ON tl.task_id = t.id
                                LEFT JOIN organizations o ON t.org_id = o.id
                                WHERE tl.task_id IN ($placeholders)
                                ORDER BY tl.created_at DESC, tl.id DESC");
    $stmt_logs->execute($task_ids);
    foreach ($stmt_logs->fetchAll() as $log) {
        $tid = intval($log['task_id']);
        $log['formatted_time'] = date('M d, Y • g:i:s A', strtotime($log['created_at']));
        $task_logs_by_task[$tid][] = $log;
        $all_task_logs[] = $log;
    }
}

// Fetch Projects for dropdown selection
if ($is_admin) {
    $projects = $pdo->query("SELECT id, title, org_id FROM projects ORDER BY title ASC")->fetchAll();
} else {
    $stmt_proj = $pdo->prepare("SELECT id, title, org_id FROM projects WHERE org_id = ? ORDER BY title ASC");
    $stmt_proj->execute([$current_org_id]);
    $projects = $stmt_proj->fetchAll();
}

include_once __DIR__ . '/../includes/header.php';
?>

<style>
.multi-select-container {
  position: relative;
  width: 100%;
}
.multi-select-trigger {
  width: 100%;
  min-height: 42px;
  padding: 0.5rem 2.2rem 0.5rem 0.85rem;
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-sm);
  background: var(--white);
  font-size: 0.9rem;
  color: var(--dark);
  cursor: pointer;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem;
  position: relative;
  transition: var(--transition);
  user-select: none;
}
.multi-select-trigger:hover,
.multi-select-container.open .multi-select-trigger {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-glow);
}
.multi-select-trigger .caret {
  position: absolute;
  right: 0.8rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-500);
  font-size: 0.8rem;
  transition: transform 0.2s ease;
}
.multi-select-container.open .multi-select-trigger .caret {
  transform: translateY(-50%) rotate(180deg);
  color: var(--primary);
}
.multi-select-placeholder {
  color: var(--gray-500);
  font-size: 0.88rem;
}
.multi-select-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--white);
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-md);
  max-height: 220px;
  overflow-y: auto;
  z-index: 1200;
  padding: 0.35rem;
}
.multi-select-container.open .multi-select-dropdown {
  display: block;
}
.multi-select-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.15s ease;
  font-size: 0.88rem;
}
.multi-select-item:hover {
  background: var(--gray-100);
}
.multi-select-item.select-all-row {
  border-bottom: 1px solid var(--gray-200);
  margin-bottom: 0.25rem;
  padding-bottom: 0.55rem;
  font-weight: 700;
  color: var(--primary);
}
.multi-select-left {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}
.multi-select-left input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--primary);
  cursor: pointer;
}
.multi-select-name {
  font-weight: 600;
  color: var(--dark);
}
.multi-select-role {
  font-size: 0.73rem;
  font-weight: 700;
  padding: 0.15rem 0.55rem;
  border-radius: 999px;
  background: var(--primary-light);
  color: var(--primary);
  white-space: nowrap;
}
.assignee-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: var(--primary-light);
  color: var(--primary);
  border-radius: 999px;
  padding: 0.18rem 0.6rem;
  font-size: 0.78rem;
  font-weight: 700;
  margin: 0.1rem 0.15rem 0.1rem 0;
}
.task-log-timeline {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 360px;
  overflow-y: auto;
  padding-right: 0.25rem;
}
.task-log-entry {
  background: var(--gray-100);
  border: 1px solid var(--gray-200);
  border-left: 4px solid var(--primary);
  border-radius: var(--radius-sm);
  padding: 0.8rem 1rem;
}
.task-log-entry.log-created {
  border-left-color: var(--success);
}
.task-log-entry.log-status {
  border-left-color: var(--info);
}
.task-log-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.35rem;
}
.task-log-meta {
  font-size: 0.82rem;
  color: var(--dark);
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.task-log-time {
  font-size: 0.76rem;
  color: var(--gray-600);
  font-weight: 600;
}
.task-log-body {
  font-size: 0.86rem;
  color: var(--gray-800);
  line-height: 1.45;
}
</style>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Individual Task Assignment and Monitoring</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Organize, assign, and monitor tasks <?= $filter_org > 0 ? 'for selected organization' : 'across all student organizations' ?>.
      <?php elseif ($can_manage_tasks): ?>
        Organize, assign, and monitor internal tasks and officer deadlines for <strong><?= htmlspecialchars($_SESSION['org_acronym']) ?></strong>.
      <?php else: ?>
        View tasks assigned to you under <strong><?= htmlspecialchars($_SESSION['org_acronym']) ?></strong>.
      <?php endif; ?>
    </p>
  </div>

  <?php if ($can_manage_tasks): ?>
    <button class="btn btn-primary" data-modal-target="createTaskModal">
      <i class="fa-solid fa-plus"></i> Add New Task
    </button>
  <?php endif; ?>
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

<!-- Filter Bar -->
<div class="filter-bar">
  <form method="GET" action="tasks.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
    <input type="hidden" name="tab" id="taskFilterTabInput" value="<?= htmlspecialchars($active_tab) ?>">
    <?php if ($is_admin): ?>
      <div class="filter-group">
        <label class="form-label" style="margin: 0;">Organization:</label>
        <select name="org_id" class="form-control" onchange="this.form.submit()">
          <option value="0">All Organizations</option>
          <?php foreach ($all_orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $filter_org == $o['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="filter-group">
      <label class="form-label" style="margin: 0;">Status Filter:</label>
      <select name="status" class="form-control" onchange="this.form.submit()">
        <option value="">All Task Statuses</option>
        <option value="To Do" <?= $filter_status == 'To Do' ? 'selected' : '' ?>>To Do</option>
        <option value="In Progress" <?= $filter_status == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
        <option value="Accomplished" <?= $filter_status == 'Accomplished' ? 'selected' : '' ?>>Accomplished</option>
        <option value="Overdue" <?= $filter_status == 'Overdue' ? 'selected' : '' ?>>Overdue</option>
      </select>
    </div>

    <div class="filter-group">
      <label class="form-label" style="margin: 0;">Priority:</label>
      <select name="priority" class="form-control" onchange="this.form.submit()">
        <option value="">All Priorities</option>
        <option value="High" <?= $filter_priority == 'High' ? 'selected' : '' ?>>High</option>
        <option value="Medium" <?= $filter_priority == 'Medium' ? 'selected' : '' ?>>Medium</option>
        <option value="Low" <?= $filter_priority == 'Low' ? 'selected' : '' ?>>Low</option>
      </select>
    </div>

    <?php if (!empty($filter_status) || !empty($filter_priority) || ($is_admin && $filter_org > 0)): ?>
      <a href="tasks.php?tab=<?= htmlspecialchars($active_tab) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
    <?php endif; ?>
  </form>
</div>

<!-- Tab Panel Navigation -->
<div class="user-tabs-nav">
  <button type="button" class="user-tab-btn <?= $active_tab === 'tasks' ? 'active' : '' ?>" onclick="switchTaskTab('tasks')">
    <i class="fa-solid fa-list-check"></i>
    <span>Task Assignments & Organizer</span>
    <span class="user-tab-badge"><?= count($tasks) ?></span>
  </button>
  <button type="button" class="user-tab-btn <?= $active_tab === 'logs' ? 'active' : '' ?>" onclick="switchTaskTab('logs')">
    <i class="fa-solid fa-clock-rotate-left"></i>
    <span>Task Activity Logs</span>
    <span class="user-tab-badge"><?= count($all_task_logs) ?></span>
  </button>
</div>

<!-- TAB 1: Task Table -->
<div class="user-tab-pane <?= $active_tab === 'tasks' ? 'active' : '' ?>" id="tab-tasks">
<?php
$col_count = 7;
if ($is_admin && $filter_org === 0) $col_count++;
?>
<div class="table-container">
  <table class="table">
    <thead>
      <tr>
        <?php if ($is_admin && $filter_org === 0): ?>
          <th>Org</th>
        <?php endif; ?>
        <th>Task Title</th>
        <th>Linked Project</th>
        <th>Assigned To</th>
        <th>Priority</th>
        <th>Deadline</th>
        <th>Status</th>
        <th><?= $can_manage_tasks ? 'Actions & Logs' : 'Activity Logs' ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($tasks)): ?>
        <tr>
          <td colspan="<?= $col_count ?>" style="text-align: center; color: var(--gray-500); padding: 2rem;">
            <?= $can_manage_tasks ? 'No tasks found matching your filter criteria.' : 'No tasks assigned to you at this time.' ?>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($tasks as $task): 
          $deadline_timestamp = strtotime($task['deadline']);
          $is_overdue = ($task['status'] === 'Overdue') || ($deadline_timestamp < strtotime('today') && $task['status'] !== 'Accomplished');
          $task_status_badges = ['Accomplished' => 'badge-accomplished', 'In Progress' => 'badge-in-progress', 'Overdue' => 'badge-overdue'];
          $task_badge = $task_status_badges[$task['status']] ?? 'badge-todo';
          $assignees_list = array_filter(array_map('trim', explode(',', $task['assigned_to'] ?? '')));
          $log_count = count($task_logs_by_task[intval($task['id'])] ?? []);
        ?>
          <tr>
            <?php if ($is_admin && $filter_org === 0): ?>
              <td><span class="badge badge-planning"><strong><?= htmlspecialchars($task['org_acronym'] ?? 'ORG') ?></strong></span></td>
            <?php endif; ?>
            <td>
              <strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($task['title']) ?></strong>
              <?php if (!empty($task['description'])): ?>
                <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.2rem;"><?= htmlspecialchars($task['description']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?= !empty($task['project_title']) ? '<i class="fa-solid fa-diagram-project" style="color: var(--secondary);"></i> ' . htmlspecialchars($task['project_title']) : '<span style="color: var(--gray-400); font-size: 0.85rem;">None</span>' ?>
            </td>
            <td>
              <?php if (empty($assignees_list)): ?>
                <span style="color: var(--gray-400); font-size: 0.85rem;">Unassigned</span>
              <?php else: ?>
                <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                  <?php foreach ($assignees_list as $assignee): ?>
                    <span class="assignee-chip"><i class="fa-solid fa-user-check" style="font-size: 0.7rem;"></i> <?= htmlspecialchars($assignee) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-weight: 700; font-size: 0.85rem; color: <?= $task['priority'] === 'High' ? 'var(--danger)' : ($task['priority'] === 'Medium' ? '#b45309' : 'var(--success)') ?>;">
                <?= $task['priority'] ?>
              </span>
            </td>
            <td>
              <span style="color: <?= $is_overdue ? 'var(--danger)' : 'var(--dark)' ?>; font-weight: <?= $is_overdue ? '700' : '500' ?>;">
                <i class="fa-regular fa-calendar"></i> <?= date('M d, Y', $deadline_timestamp) ?>
              </span>
            </td>
            <td>
              <?php if ($can_manage_tasks): ?>
                <!-- Quick Status Selector Form -->
                <form method="POST" action="tasks.php" style="display: inline-block;">
                  <input type="hidden" name="action" value="quick_status">
                  <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                  <select name="new_status" class="badge <?= $task_badge ?>" style="border: none; cursor: pointer; outline: none; appearance: none; padding: 0.25rem 0.5rem;" onchange="this.form.submit()">
                    <option value="To Do" <?= $task['status'] == 'To Do' ? 'selected' : '' ?>>To Do</option>
                    <option value="In Progress" <?= $task['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Accomplished" <?= $task['status'] == 'Accomplished' ? 'selected' : '' ?>>Accomplished</option>
                    <option value="Overdue" <?= $task['status'] == 'Overdue' ? 'selected' : '' ?>>Overdue</option>
                  </select>
                </form>
              <?php else: ?>
                <span class="badge <?= $task_badge ?>"><?= htmlspecialchars($task['status']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="openTaskLogsModal(<?= intval($task['id']) ?>)" title="View Task Activity Logs">
                  <i class="fa-solid fa-clock-rotate-left"></i> Logs (<?= $log_count ?>)
                </button>
                <?php if ($can_manage_tasks): ?>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="openEditTaskModal(<?= intval($task['id']) ?>)" title="Edit Task">
                    <i class="fa-solid fa-pen"></i> Edit
                  </button>
                  <form method="POST" action="tasks.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete this task?', 'Delete Task?');" style="display: inline;">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Task"><i class="fa-solid fa-trash"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<!-- TAB 2: Task Activity Logs Pane -->
<div class="user-tab-pane <?= $active_tab === 'logs' ? 'active' : '' ?>" id="tab-logs">
  <div class="panel">
    <div class="panel-header">
      <span><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Task Creation, Assignment & Status Activity Logs</span>
      <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($all_task_logs) ?> Total Entries</span>
    </div>

    <?php if (empty($all_task_logs)): ?>
      <div style="text-align: center; padding: 2.5rem; color: var(--gray-500);">
        <i class="fa-solid fa-clock-rotate-left" style="font-size: 2.2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
        No task activity logs recorded for the selected criteria.
      </div>
    <?php else: ?>
      <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
        <table class="table">
          <thead>
            <tr>
              <?php if ($is_admin && $filter_org === 0): ?>
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
            <?php foreach ($all_task_logs as $log):
              $action_badge = $log['action_type'] === 'Created' ? 'badge-accomplished' : ($log['action_type'] === 'Status Updated' ? 'badge-todo' : 'badge-in-progress');
            ?>
              <tr>
                <?php if ($is_admin && $filter_org === 0): ?>
                  <td><span class="badge badge-planning"><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></span></td>
                <?php endif; ?>
                <td>
                  <strong style="color: var(--dark);"><?= htmlspecialchars($log['task_title'] ?? ('Task #' . $log['task_id'])) ?></strong>
                </td>
                <td><span class="badge <?= $action_badge ?>"><?= htmlspecialchars($log['action_type']) ?></span></td>
                <td>
                  <div style="font-weight: 700; color: var(--dark); font-size: 0.88rem;"><?= htmlspecialchars($log['user_name']) ?></div>
                  <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($log['user_role']) ?></div>
                </td>
                <td style="font-size: 0.86rem; color: var(--gray-800); max-width: 420px;"><?= htmlspecialchars($log['log_details']) ?></td>
                <td style="white-space: nowrap; font-size: 0.8rem; color: var(--gray-600);">
                  <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($log['formatted_time']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- View Task Activity Logs Modal (Accessible to view logs with timestamps) -->
<div class="modal-backdrop" id="viewTaskLogsModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Task Activity Logs
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="background: var(--gray-100); padding: 0.85rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; border: 1px solid var(--gray-200);">
        <div style="font-size: 0.78rem; color: var(--gray-500); text-transform: uppercase; font-weight: 700;">Task</div>
        <div id="log_modal_task_title" style="font-size: 1.05rem; font-weight: 800; color: var(--dark); margin-top: 0.1rem;"></div>
        <div id="log_modal_task_assigned" style="font-size: 0.82rem; color: var(--gray-600); margin-top: 0.25rem;"></div>
      </div>

      <div id="taskLogsTimelineContainer" class="task-log-timeline"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<?php if ($can_manage_tasks): ?>
<!-- Create Task Modal -->
<div class="modal-backdrop" id="createTaskModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-plus-circle" style="color: var(--primary);"></i> Create New Task</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="tasks.php" onsubmit="return validateAssigneeSelection('createAssigneeContainer')">
      <input type="hidden" name="action" value="create_task">
      
      <div class="modal-body">
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Target Organization <span style="color: red;">*</span></label>
            <select name="org_id" id="create_task_org_id" class="form-control" required onchange="handleOrgChange('create')">
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>" <?= ($filter_org > 0 ? $filter_org == $o['id'] : $current_org_id == $o['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" id="create_task_org_id" value="<?= $current_org_id ?>">
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label">Task Title <span style="color: red;">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Finalize Event Certificates" required>
        </div>

        <div class="form-group">
          <label class="form-label">Task Description</label>
          <textarea name="description" class="form-control" placeholder="Add specific instructions or notes for this task..."></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="To Do">To Do</option>
              <option value="In Progress">In Progress</option>
              <option value="Accomplished">Accomplished</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Priority Level</label>
            <select name="priority" class="form-control">
              <option value="High">High</option>
              <option value="Medium" selected>Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Deadline Date <span style="color: red;">*</span></label>
            <input type="date" name="deadline" class="form-control" required value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Link to Project (Optional)</label>
            <select name="project_id" id="create_task_project_id" class="form-control">
              <option value="">-- No Project Linked --</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Assigned To (Multiple Select) <span style="color: red;">*</span></label>
          <div class="multi-select-container" id="createAssigneeContainer">
            <div class="multi-select-trigger" onclick="toggleMultiSelect('createAssigneeContainer', event)">
              <span class="multi-select-placeholder">Select account(s) (Name & Role)...</span>
              <i class="fa-solid fa-chevron-down caret"></i>
            </div>
            <div class="multi-select-dropdown" id="createAssigneeList"></div>
          </div>
          <small style="font-size: 0.75rem; color: var(--gray-500); display: block; margin-top: 0.3rem;">
            Only shows accounts (Name & Role) belonging to the selected organization.
          </small>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Task</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Task Modal -->
<div class="modal-backdrop" id="editTaskModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Task</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="tasks.php" onsubmit="return validateAssigneeSelection('editAssigneeContainer')">
      <input type="hidden" name="action" value="update_task">
      <input type="hidden" name="task_id" id="edit_task_id">
      <input type="hidden" id="edit_task_assigned_to">
      
      <div class="modal-body">
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <select name="org_id" id="edit_task_org_id" class="form-control" required onchange="handleOrgChange('edit')">
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" id="edit_task_org_id" value="<?= $current_org_id ?>">
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label">Task Title <span style="color: red;">*</span></label>
          <input type="text" name="title" id="edit_task_title" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Task Description</label>
          <textarea name="description" id="edit_task_description" class="form-control"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="edit_task_status" class="form-control">
              <option value="To Do">To Do</option>
              <option value="In Progress">In Progress</option>
              <option value="Accomplished">Accomplished</option>
              <option value="Overdue">Overdue</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Priority Level</label>
            <select name="priority" id="edit_task_priority" class="form-control">
              <option value="High">High</option>
              <option value="Medium">Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Deadline Date <span style="color: red;">*</span></label>
            <input type="date" name="deadline" id="edit_task_deadline" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Link to Project (Optional)</label>
            <select name="project_id" id="edit_task_project_id" class="form-control">
              <option value="">-- No Project Linked --</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Assigned To (Multiple Select) <span style="color: red;">*</span></label>
          <div class="multi-select-container" id="editAssigneeContainer">
            <div class="multi-select-trigger" onclick="toggleMultiSelect('editAssigneeContainer', event)">
              <span class="multi-select-placeholder">Select account(s) (Name & Role)...</span>
              <i class="fa-solid fa-chevron-down caret"></i>
            </div>
            <div class="multi-select-dropdown" id="editAssigneeList"></div>
          </div>
          <small style="font-size: 0.75rem; color: var(--gray-500); display: block; margin-top: 0.3rem;">
            Only shows accounts (Name & Role) belonging to the selected organization.
          </small>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const ORG_USERS = <?= json_encode($org_users) ?>;
const ORG_PROJECTS = <?= json_encode($projects) ?>;
const ALL_TASKS = <?= json_encode($tasks) ?>;
const TASK_LOGS_MAP = <?= json_encode($task_logs_by_task) ?>;

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function openTaskLogsModal(taskId) {
  const modal = document.getElementById('viewTaskLogsModal');
  if (!modal) return;

  const task = ALL_TASKS.find(t => parseInt(t.id, 10) === parseInt(taskId, 10));
  const titleEl = document.getElementById('log_modal_task_title');
  const assignedEl = document.getElementById('log_modal_task_assigned');
  const container = document.getElementById('taskLogsTimelineContainer');

  if (task) {
    titleEl.textContent = task.title;
    assignedEl.textContent = 'Assigned To: ' + (task.assigned_to || 'Unassigned') + ' • Status: ' + task.status;
  } else {
    titleEl.textContent = 'Task #' + taskId;
    assignedEl.textContent = '';
  }

  const logs = TASK_LOGS_MAP[taskId] || [];
  if (logs.length === 0) {
    container.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: var(--gray-500);">No activity logs recorded for this task yet.</div>';
  } else {
    const initialLimit = logs.length === 2 ? 1 : 2;
    const totalLogs = logs.length;
    const hiddenCount = Math.max(0, totalLogs - initialLimit);

    let html = `<div class="log-stepper-container"><div class="log-stepper" data-initial-limit="${initialLimit}">`;
    logs.forEach((log, index) => {
      let stepClass = '';
      let badgeClass = 'badge-in-progress';
      let iconClass = 'fa-pen';
      if (log.action_type === 'Created') {
        stepClass = 'step-success';
        badgeClass = 'badge-accomplished';
        iconClass = 'fa-plus';
      } else if (log.action_type === 'Status Updated') {
        stepClass = 'step-info';
        badgeClass = 'badge-todo';
        iconClass = 'fa-arrows-rotate';
      }

      const hiddenClass = index >= initialLimit ? 'log-step-hidden' : '';
      const lastVisibleClass = (index === initialLimit - 1 && totalLogs > initialLimit) ? 'log-step-last-visible' : '';

      html += `
        <div class="log-step ${stepClass} ${hiddenClass} ${lastVisibleClass}">
          <div class="log-step-node">
            <i class="fa-solid ${iconClass}"></i>
          </div>
          <div class="log-step-content">
            <div class="log-step-header">
              <div class="log-step-title">
                <span class="badge ${badgeClass}" style="font-size: 0.7rem;">${escapeHtml(log.action_type)}</span>
                <span>${escapeHtml(log.user_name)}</span>
                <span class="log-step-role">(${escapeHtml(log.user_role)})</span>
              </div>
              <div class="log-step-time">
                <i class="fa-regular fa-clock"></i> ${escapeHtml(log.formatted_time || log.created_at)}
              </div>
            </div>
            <div class="log-step-body">${escapeHtml(log.log_details)}</div>
          </div>
        </div>
      `;
    });
    html += '</div>';
    if (hiddenCount > 0) {
      html += `
        <div class="log-stepper-toggle-wrap">
          <button type="button" class="log-stepper-toggle-btn" data-expanded="false" onclick="toggleStepperLogs(this)">
            <i class="fa-solid fa-chevron-down"></i> Show More (${hiddenCount} more ${hiddenCount === 1 ? 'log' : 'logs'})
          </button>
        </div>
      `;
    }
    html += '</div>';
    container.innerHTML = html;
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function toggleMultiSelect(containerId, event) {
  event.stopPropagation();
  const container = document.getElementById(containerId);
  if (!container) return;
  document.querySelectorAll('.multi-select-container').forEach(c => {
    if (c.id !== containerId) c.classList.remove('open');
  });
  container.classList.toggle('open');
}

document.addEventListener('click', (e) => {
  document.querySelectorAll('.multi-select-container').forEach(c => {
    if (!c.contains(e.target)) {
      c.classList.remove('open');
    }
  });
});

function renderOrgProjects(mode, orgId, selectedProjectId = '') {
  const selectEl = document.getElementById(mode === 'create' ? 'create_task_project_id' : 'edit_task_project_id');
  if (!selectEl) return;

  const filtered = ORG_PROJECTS.filter(p => parseInt(p.org_id, 10) === parseInt(orgId, 10));
  let html = '<option value="">-- No Project Linked --</option>';
  filtered.forEach(p => {
    const isSel = String(p.id) === String(selectedProjectId) ? 'selected' : '';
    html += `<option value="${p.id}" ${isSel}>${escapeHtml(p.title)}</option>`;
  });
  selectEl.innerHTML = html;
}

function renderAssigneeOptions(mode, orgId, selectedValues = []) {
  const containerId = mode === 'create' ? 'createAssigneeContainer' : 'editAssigneeContainer';
  const listId = mode === 'create' ? 'createAssigneeList' : 'editAssigneeList';
  const listEl = document.getElementById(listId);
  if (!listEl) return;

  const usersForOrg = ORG_USERS.filter(u => parseInt(u.org_id, 10) === parseInt(orgId, 10));

  if (usersForOrg.length === 0) {
    listEl.innerHTML = '<div style="padding: 0.75rem; color: var(--gray-500); font-size: 0.85rem; text-align: center;">No officer accounts found in this organization.</div>';
    updateAssigneeTriggerText(containerId);
    return;
  }

  const selectAllId = `${mode}_select_all_officers`;
  let html = `
    <label class="multi-select-item select-all-row" for="${selectAllId}">
      <div class="multi-select-left">
        <input type="checkbox" id="${selectAllId}" onchange="toggleSelectAllOfficers('${containerId}', this.checked)">
        <span>Select All (${usersForOrg.length})</span>
      </div>
    </label>
  `;

  const assignAll = selectedValues.includes('All Officers');

  usersForOrg.forEach(u => {
    const labelValue = `${u.full_name} (${u.role})`;
    const isChecked = assignAll || selectedValues.includes(labelValue) || selectedValues.includes(u.full_name);
    const inputId = `${mode}_user_${u.id}`;

    html += `
      <label class="multi-select-item" for="${inputId}">
        <div class="multi-select-left">
          <input type="checkbox" id="${inputId}" class="assignee-checkbox" name="assigned_to[]" value="${escapeHtml(labelValue)}" ${isChecked ? 'checked' : ''} onchange="updateAssigneeTriggerText('${containerId}')">
          <span class="multi-select-name">${escapeHtml(u.full_name)}</span>
        </div>
        <span class="multi-select-role">${escapeHtml(u.role)}</span>
      </label>
    `;
  });

  listEl.innerHTML = html;
  updateAssigneeTriggerText(containerId);
}

function toggleSelectAllOfficers(containerId, checked) {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.querySelectorAll('.assignee-checkbox').forEach(cb => {
    cb.checked = checked;
  });
  updateAssigneeTriggerText(containerId);
}

function updateAssigneeTriggerText(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const trigger = container.querySelector('.multi-select-trigger');
  const checkboxes = Array.from(container.querySelectorAll('.assignee-checkbox'));
  const checkedBoxes = checkboxes.filter(cb => cb.checked);
  const selectAllCb = container.querySelector('input[id$="_select_all_officers"]');

  if (selectAllCb && checkboxes.length > 0) {
    selectAllCb.checked = (checkedBoxes.length === checkboxes.length);
  }

  if (checkedBoxes.length === 0) {
    trigger.innerHTML = '<span class="multi-select-placeholder">Select account(s) (Name & Role)...</span><i class="fa-solid fa-chevron-down caret"></i>';
  } else {
    let chipsHtml = '';
    checkedBoxes.forEach(cb => {
      chipsHtml += `<span class="assignee-chip">${escapeHtml(cb.value)}</span>`;
    });
    chipsHtml += '<i class="fa-solid fa-chevron-down caret"></i>';
    trigger.innerHTML = chipsHtml;
  }
}

function validateAssigneeSelection(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return true;
  const checkedCount = container.querySelectorAll('.assignee-checkbox:checked').length;
  if (checkedCount === 0) {
    if (typeof window.showToast === 'function') {
      window.showToast('Please select at least one account in Assigned To.', 'danger');
    } else {
      alert('Please select at least one account in Assigned To.');
    }
    container.classList.add('open');
    return false;
  }
  return true;
}

function handleOrgChange(mode) {
  const orgInput = document.getElementById(mode === 'create' ? 'create_task_org_id' : 'edit_task_org_id');
  if (!orgInput) return;
  const orgId = orgInput.value;
  renderAssigneeOptions(mode, orgId, []);
  renderOrgProjects(mode, orgId, '');
}

function openEditTaskModal(taskOrId) {
  const modal = document.getElementById('editTaskModal');
  if (!modal) return;

  const task = (typeof taskOrId === 'object' && taskOrId !== null)
    ? taskOrId
    : ALL_TASKS.find(t => parseInt(t.id, 10) === parseInt(taskOrId, 10));
  if (!task) return;

  document.getElementById('edit_task_id').value = task.id;
  document.getElementById('edit_task_title').value = task.title;
  document.getElementById('edit_task_description').value = task.description || '';
  document.getElementById('edit_task_status').value = task.status;
  document.getElementById('edit_task_priority').value = task.priority;
  document.getElementById('edit_task_deadline').value = task.deadline;

  const orgElem = document.getElementById('edit_task_org_id');
  const taskOrgId = task.org_id || (orgElem ? orgElem.value : <?= $current_org_id ?>);
  if (orgElem && task.org_id) {
    orgElem.value = task.org_id;
  }

  const assignedArr = (task.assigned_to || '')
    .split(',')
    .map(s => s.trim())
    .filter(Boolean);

  renderAssigneeOptions('edit', taskOrgId, assignedArr);
  renderOrgProjects('edit', taskOrgId, task.project_id || '');

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

window.openTaskEditModal = openEditTaskModal;

function switchTaskTab(tabName) {
  const btns = document.querySelectorAll('.user-tabs-nav .user-tab-btn');
  const panes = document.querySelectorAll('.user-tab-pane');
  btns.forEach(btn => btn.classList.remove('active'));
  panes.forEach(pane => pane.classList.remove('active'));

  const tabInput = document.getElementById('taskFilterTabInput');
  if (tabInput) tabInput.value = tabName;

  if (tabName === 'logs') {
    if (btns[1]) btns[1].classList.add('active');
    const logPane = document.getElementById('tab-logs');
    if (logPane) logPane.classList.add('active');
  } else {
    if (btns[0]) btns[0].classList.add('active');
    const taskPane = document.getElementById('tab-tasks');
    if (taskPane) taskPane.classList.add('active');
  }

  const url = new URL(window.location.href);
  url.searchParams.set('tab', tabName);
  history.replaceState(null, '', url.toString());
}

document.addEventListener('DOMContentLoaded', () => {
  const createOrgInput = document.getElementById('create_task_org_id');
  if (createOrgInput) {
    renderAssigneeOptions('create', createOrgInput.value, []);
    renderOrgProjects('create', createOrgInput.value, '');
  }

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('action') === 'new') {
    const createModal = document.getElementById('createTaskModal');
    if (createModal) {
      createModal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  } else if (urlParams.get('edit')) {
    const editId = parseInt(urlParams.get('edit'), 10);
    openEditTaskModal(editId);
  }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
