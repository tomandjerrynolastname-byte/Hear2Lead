<?php
// leadership/dashboard.php - Leadership Task Organizer & Monitor Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_role = $_SESSION['user_role'] ?? 'Officer';
$current_user_name = $_SESSION['user_name'] ?? '';
$is_admin = ($current_role === 'Admin');
$is_pres_or_vp = in_array($current_role, ['President', 'Vice President'], true);
$can_manage_users = ($is_admin || $is_pres_or_vp);
$can_manage_tasks = ($is_admin || $is_pres_or_vp);

// Organization scoping
$selected_org_id = 0;
if ($is_admin) {
    $selected_org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
} else {
    $selected_org_id = intval($_SESSION['org_id']);
}

// Fetch all orgs for Admin filter dropdown
$all_orgs = [];
if ($is_admin) {
    $all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();
}

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
    $active_org_name = 'All Organizations';
    $active_org_acronym = 'System-wide';
}

// Automatically update task statuses to 'Overdue'
if ($selected_org_id > 0) {
    $pdo->exec("UPDATE tasks SET status = 'Overdue' WHERE org_id = $selected_org_id AND deadline < CURDATE() AND status != 'Accomplished'");
} else {
    $pdo->exec("UPDATE tasks SET status = 'Overdue' WHERE deadline < CURDATE() AND status != 'Accomplished'");
}

// 1. Fetch Task Statistics
$task_conds = ["1=1"];
$task_params = [];
if ($selected_org_id > 0) {
    $task_conds[] = "org_id = ?";
    $task_params[] = $selected_org_id;
}
if (!$can_manage_tasks) {
    $task_conds[] = "(assigned_to LIKE ? OR assigned_to = 'All Officers')";
    $task_params[] = '%' . $current_user_name . '%';
}
$base_task_where = implode(" AND ", $task_conds);

$stmt_stat = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE $base_task_where");
$stmt_stat->execute($task_params);
$total_tasks = $stmt_stat->fetchColumn();

$stmt_stat = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE $base_task_where AND status = 'Accomplished'");
$stmt_stat->execute($task_params);
$accomplished_tasks = $stmt_stat->fetchColumn();

$stmt_stat = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE $base_task_where AND status = 'To Do'");
$stmt_stat->execute($task_params);
$todo_tasks = $stmt_stat->fetchColumn();

$stmt_stat = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE $base_task_where AND status = 'In Progress'");
$stmt_stat->execute($task_params);
$in_progress_tasks = $stmt_stat->fetchColumn();

$stmt_stat = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE $base_task_where AND status = 'Overdue'");
$stmt_stat->execute($task_params);
$overdue_tasks = $stmt_stat->fetchColumn();

// 2. Fetch Upcoming Deadlines
$up_conds = ["t.status != 'Accomplished'"];
$up_params = [];
if ($selected_org_id > 0) {
    $up_conds[] = "t.org_id = ?";
    $up_params[] = $selected_org_id;
}
if (!$can_manage_tasks) {
    $up_conds[] = "(t.assigned_to LIKE ? OR t.assigned_to = 'All Officers')";
    $up_params[] = '%' . $current_user_name . '%';
}
$up_where = implode(" AND ", $up_conds);
$upcoming_stmt = $pdo->prepare("SELECT t.*, o.acronym FROM tasks t JOIN organizations o ON t.org_id = o.id WHERE $up_where ORDER BY t.deadline ASC LIMIT 6");
$upcoming_stmt->execute($up_params);
$upcoming_deadlines = $upcoming_stmt->fetchAll();

// 3. Fetch Recent Student Suggestions/Feedbacks
if ($selected_org_id > 0) {
    $fb_stmt = $pdo->prepare("SELECT f.*, o.acronym FROM feedbacks f JOIN organizations o ON f.org_id = o.id WHERE f.org_id = ? ORDER BY f.created_at DESC LIMIT 4");
    $fb_stmt->execute([$selected_org_id]);
    $pending_feedbacks = $fb_stmt->fetchAll();

    $fb_cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM feedbacks WHERE org_id = ? AND status IN ('Pending', 'Under Review')");
    $fb_cnt_stmt->execute([$selected_org_id]);
    $unresolved_feedback_count = $fb_cnt_stmt->fetchColumn();
} else {
    $pending_feedbacks = $pdo->query("SELECT f.*, o.acronym FROM feedbacks f JOIN organizations o ON f.org_id = o.id ORDER BY f.created_at DESC LIMIT 4")->fetchAll();
    $unresolved_feedback_count = $pdo->query("SELECT COUNT(*) FROM feedbacks WHERE status IN ('Pending', 'Under Review')")->fetchColumn();
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Greeting & Quick Actions -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <i class="fa-solid fa-gauge-high" style="color: var(--accent);"></i> 
      <?= htmlspecialchars($active_org_acronym) ?> Leadership Portal & Dashboard
    </h1>
    <p class="page-subtitle">
      <span style="font-size: 0.85rem; color: var(--gray-500); display: block; margin-bottom: 0.2rem;">For authorized officers, advisers, and designated personnel</span>
      <?php if ($is_admin): ?>
        Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> (System Administrator — <?= $selected_org_id > 0 ? 'Viewing ' . htmlspecialchars($active_org_name) : 'All Organizations' ?>)
      <?php else: ?>
        Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> (<?= htmlspecialchars($_SESSION['user_role']) ?> of <?= htmlspecialchars($active_org_name) ?>)
      <?php endif; ?>
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <a href="../student/index.php" class="btn btn-secondary" title="View Student Portal View">
      <i class="fa-solid fa-eye"></i> Monitor Student Portal
    </a>
    <?php if ($can_manage_tasks): ?>
      <a href="tasks.php?action=new<?= $selected_org_id > 0 ? '&org_id=' . $selected_org_id : '' ?>" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Add New Task
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_GET['error'])): ?>
  <div class="toast toast-danger alert-auto-dismiss" role="alert">
    <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
    <div class="toast-body">
      <div class="toast-title">Notice</div>
      <div class="toast-message"><?= htmlspecialchars($_GET['error']) ?></div>
    </div>
    <button type="button" class="toast-close" aria-label="Close">&times;</button>
    <div class="toast-progress"><div class="toast-progress-bar"></div></div>
  </div>
<?php endif; ?>

<?php if ($is_admin): ?>
  <!-- Admin Organization Switcher Bar -->
  <div class="filter-bar" style="margin-bottom: 1.5rem; background: #faf5ff; border: 1px solid #e9d5ff;">
    <form method="GET" action="dashboard.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <div style="font-weight: 700; color: #6b21a8; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fa-solid fa-sliders"></i> Admin Org View:
      </div>
      <div style="flex: 1; max-width: 320px;">
        <select name="org_id" class="form-control" onchange="this.form.submit()">
          <option value="0" <?= $selected_org_id === 0 ? 'selected' : '' ?>>All Organizations (System-wide Overview)</option>
          <?php foreach ($all_orgs as $org): ?>
            <option value="<?= $org['id'] ?>" <?= $selected_org_id == $org['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($selected_org_id > 0): ?>
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> View All Organizations</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<!-- Task Counter Grid -->
<div class="stats-grid">
  <!-- Total Tasks Card -->
  <div class="stat-card total" onclick="navigateWithLoader('tasks.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>')" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
    <div>
      <div class="stat-number"><?= $total_tasks ?></div>
      <div class="stat-label">Total Tasks</div>
    </div>
    <i class="fa-solid fa-chevron-right stat-arrow"></i>
  </div>

  <!-- Accomplished Tasks Card -->
  <div class="stat-card accomplished" onclick="navigateWithLoader('tasks.php?status=Accomplished<?= $selected_org_id > 0 ? '&org_id=' . $selected_org_id : '' ?>')" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div>
      <div class="stat-number"><?= $accomplished_tasks ?></div>
      <div class="stat-label">Accomplished</div>
    </div>
    <i class="fa-solid fa-chevron-right stat-arrow"></i>
  </div>

  <!-- To Do Tasks Card -->
  <div class="stat-card todo" onclick="navigateWithLoader('tasks.php?status=To Do<?= $selected_org_id > 0 ? '&org_id=' . $selected_org_id : '' ?>')" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
    <div>
      <div class="stat-number"><?= $todo_tasks ?></div>
      <div class="stat-label">To Do</div>
    </div>
    <i class="fa-solid fa-chevron-right stat-arrow"></i>
  </div>

  <!-- Overdue Tasks Card -->
  <div class="stat-card overdue" onclick="navigateWithLoader('tasks.php?status=Overdue<?= $selected_org_id > 0 ? '&org_id=' . $selected_org_id : '' ?>')" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div>
      <div class="stat-number"><?= $overdue_tasks ?></div>
      <div class="stat-label">Overdue</div>
    </div>
    <i class="fa-solid fa-chevron-right stat-arrow"></i>
  </div>
</div>

<!-- Two-Column Organizer Layout -->
<div class="layout-split">
  <div>
    <!-- Deadlines & Upcoming Tasks Section -->
    <div class="panel">
      <div class="panel-header">
        <span><i class="fa-solid fa-calendar-day" style="color: var(--danger);"></i> Task Deadlines Organizer</span>
        <a href="tasks.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" style="font-size: 0.85rem;"><?= $can_manage_tasks ? 'Manage All Tasks' : 'View Assigned Tasks' ?> <i class="fa-solid fa-arrow-right"></i></a>
      </div>

      <?php if (empty($upcoming_deadlines)): ?>
        <p style="color: var(--gray-500); text-align: center; padding: 1.5rem;">No upcoming task deadlines recorded.</p>
      <?php else: ?>
        <ul class="deadline-list">
          <?php foreach ($upcoming_deadlines as $task): 
            $deadline_timestamp = strtotime($task['deadline']);
            $is_overdue = ($task['status'] === 'Overdue') || ($deadline_timestamp < strtotime('today'));
            $task_status_badges = ['Accomplished' => 'badge-accomplished', 'In Progress' => 'badge-in-progress', 'Overdue' => 'badge-overdue'];
            $task_badge = $task_status_badges[$task['status']] ?? 'badge-todo';
          ?>
            <li class="deadline-item">
              <div class="deadline-date-badge" style="background: <?= $is_overdue ? 'var(--danger-light)' : 'var(--primary-light)' ?>; color: <?= $is_overdue ? 'var(--danger)' : 'var(--primary)' ?>;">
                <div class="deadline-day"><?= date('d', $deadline_timestamp) ?></div>
                <div class="deadline-month"><?= date('M', $deadline_timestamp) ?></div>
              </div>

              <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                  <strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($task['title']) ?></strong>
                  <span class="badge <?= $task_badge ?>">
                    <?= $task['status'] ?>
                  </span>
                  <?php if ($selected_org_id === 0 && !empty($task['acronym'])): ?>
                    <span class="badge badge-planning" style="font-size: 0.7rem; font-weight: 700;">
                      <?= htmlspecialchars($task['acronym']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.2rem;">
                  Assigned to: <strong><?= htmlspecialchars($task['assigned_to']) ?></strong> • Priority: <strong><?= $task['priority'] ?></strong>
                </div>
              </div>

              <?php if ($can_manage_tasks): ?>
                <a href="tasks.php?edit=<?= $task['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Task"><i class="fa-solid fa-pen"></i></a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Monitoring Student Suggestions/Feedbacks Panel -->
    <div class="panel">
      <div class="panel-header">
        <span>
          <i class="fa-solid fa-comment-dots" style="color: var(--primary);"></i> Student Suggestions & Feedbacks Monitor
          <?php if ($unresolved_feedback_count > 0): ?>
            <span class="badge badge-overdue" style="margin-left: 0.5rem;"><?= $unresolved_feedback_count ?> Needs Response</span>
          <?php endif; ?>
        </span>
        <a href="feedbacks.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" style="font-size: 0.85rem;">View All Feedbacks <i class="fa-solid fa-chevron-right"></i></a>
      </div>

      <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 1rem;">
        Monitor submissions from students and publish responses directly visible on the Student Portal.
      </p>

      <?php if (empty($pending_feedbacks)): ?>
        <p style="color: var(--gray-500); text-align: center; padding: 1.5rem;">No student feedbacks submitted for <?= htmlspecialchars($active_org_acronym) ?> yet.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          <?php foreach ($pending_feedbacks as $fb): 
            $fb_status_badges = ['Resolved' => 'badge-accomplished', 'In Action' => 'badge-in-progress', 'Under Review' => 'badge-todo'];
            $fb_badge = $fb_status_badges[$fb['status']] ?? 'badge-planning';
          ?>
            <div style="padding: 0.85rem; background: var(--gray-100); border-radius: var(--radius-sm); border: 1px solid var(--gray-200);">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
                <div>
                  <strong style="font-size: 0.9rem; color: var(--dark);"><?= htmlspecialchars($fb['subject']) ?></strong>
                  <?php if ($selected_org_id === 0 && !empty($fb['acronym'])): ?>
                    <span class="badge badge-planning" style="font-size: 0.65rem; margin-left: 0.25rem;"><?= htmlspecialchars($fb['acronym']) ?></span>
                  <?php endif; ?>
                </div>
                <span class="badge <?= $fb_badge ?>">
                  <?= $fb['status'] ?>
                </span>
              </div>
              <p style="font-size: 0.85rem; color: var(--gray-700); margin-bottom: 0.5rem;">"<?= htmlspecialchars(mb_strimwidth($fb['message'], 0, 100, "...")) ?>"</p>
              <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--gray-500);">
                <span>By: <strong><?= htmlspecialchars($fb['student_name']) ?></strong></span>
                <a href="feedbacks.php?respond=<?= $fb['id'] ?>" class="btn btn-primary btn-sm" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;"><i class="fa-solid fa-reply"></i> Respond</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar: Quick Control & Navigation -->
  <div>
    <!-- Access Student Portal Widget -->
    <div class="panel" style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #fcd34d;">
      <h3 style="font-size: 1.1rem; color: #92400e; margin-bottom: 0.5rem;"><i class="fa-solid fa-up-right-from-square"></i> Student Portal Monitor</h3>
      <p style="font-size: 0.85rem; color: #78350f; margin-bottom: 1rem;">Click below to browse how students see your organization's accomplishments, project progress, and suggestions.</p>
      <a href="../student/index.php" target="_blank" class="btn btn-primary" style="width: 100%; background: #b45309; border: none;">
        Open Student Portal <i class="fa-solid fa-external-link"></i>
      </a>
    </div>

    <!-- Leadership Quick Tools -->
    <div class="panel">
      <div class="panel-header">Quick Leadership Actions</div>
      <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <?php if ($is_admin): ?>
          <a href="organizations.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> Organization Management</a>
        <?php endif; ?>
        <a href="projects.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-folder-tree" style="color: var(--secondary);"></i> Programs and Projects Management</a>
        <a href="tasks.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Individual Task Assignment and Monitoring</a>
        <a href="projects.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-chart-line" style="color: #0284c7;"></i> Project Progress Updates</a>
        <a href="accomplishments.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishment and Document Records</a>
        <a href="feedbacks.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-comments" style="color: var(--accent);"></i> Feedback and Concern Management</a>
        <a href="reports.php<?= $selected_org_id > 0 ? '?org_id=' . $selected_org_id : '' ?>" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-chart-pie" style="color: #7c3aed;"></i> Leadership Performance Reports</a>
        <?php if ($can_manage_users): ?>
          <a href="users.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-users-gear" style="color: var(--info);"></i> User Management (Leaders & Students)</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
