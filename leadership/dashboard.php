<?php
// leadership/dashboard.php - Leadership Task Organizer & Monitor Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$org_id = $_SESSION['org_id'];
$org_name = $_SESSION['org_name'];
$org_acronym = $_SESSION['org_acronym'];

// Automatically update task statuses to 'Overdue' if deadline has passed and not accomplished
$pdo->exec("UPDATE tasks SET status = 'Overdue' WHERE org_id = $org_id AND deadline < CURDATE() AND status != 'Accomplished'");

// 1. Fetch Task Statistics (Exact structure requested by user)
$total_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE org_id = $org_id")->fetchColumn();
$accomplished_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE org_id = $org_id AND status = 'Accomplished'")->fetchColumn();
$todo_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE org_id = $org_id AND status = 'To Do'")->fetchColumn();
$in_progress_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE org_id = $org_id AND status = 'In Progress'")->fetchColumn();
$overdue_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE org_id = $org_id AND status = 'Overdue'")->fetchColumn();

// 2. Fetch Upcoming Deadlines
$upcoming_deadlines = $pdo->query("SELECT * FROM tasks WHERE org_id = $org_id AND status != 'Accomplished' ORDER BY deadline ASC LIMIT 5")->fetchAll();

// 3. Fetch Recent Student Suggestions/Feedbacks for Monitoring
$pending_feedbacks = $pdo->query("SELECT * FROM feedbacks WHERE org_id = $org_id ORDER BY created_at DESC LIMIT 4")->fetchAll();
$unresolved_feedback_count = $pdo->query("SELECT COUNT(*) FROM feedbacks WHERE org_id = $org_id AND status IN ('Pending', 'Under Review')")->fetchColumn();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Greeting & Quick Actions -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-gauge-high" style="color: var(--accent);"></i> <?= htmlspecialchars($org_acronym) ?> Task Organizer & Dashboard</h1>
    <p class="page-subtitle">Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> (<?= htmlspecialchars($_SESSION['user_role']) ?> of <?= htmlspecialchars($org_name) ?>)</p>
  </div>

  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <a href="../student/index.php" class="btn btn-secondary" title="View Student Portal View">
      <i class="fa-solid fa-eye"></i> Monitor Student Portal
    </a>
    <a href="tasks.php?action=new" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Add New Task
    </a>
  </div>
</div>

<!-- Task Counter Grid (Exact requested UI format) -->
<div class="stats-grid">
  <!-- Total Tasks Card -->
  <div class="stat-card total" onclick="window.location.href='tasks.php'" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
    <div>
      <div class="stat-number"><?= $total_tasks ?></div>
      <div class="stat-label">Total Tasks</div>
    </div>
    <i class="fa-solid fa-chevron-right stat-arrow"></i>
  </div>

  <!-- Accomplished Tasks Card -->
  <div class="stat-card accomplished" onclick="window.location.href='tasks.php?status=Accomplished'" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div>
      <div class="stat-number"><?= $accomplished_tasks ?></div>
      <div class="stat-label">Accomplished</div>
    </div>
    <i class="fa-solid fa-chevron-right stat-arrow"></i>
  </div>

  <!-- To Do Tasks Card -->
  <div class="stat-card todo" onclick="window.location.href='tasks.php?status=To Do'" style="cursor: pointer;">
    <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
    <div>
      <div class="stat-number"><?= $todo_tasks ?></div>
      <div class="stat-label">To Do</div>
    </div>
    <i class="fa-solid fa-chevron-right stat-arrow"></i>
  </div>

  <!-- Overdue Tasks Card -->
  <div class="stat-card overdue" onclick="window.location.href='tasks.php?status=Overdue'" style="cursor: pointer;">
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
        <a href="tasks.php" style="font-size: 0.85rem;">Manage All Tasks <i class="fa-solid fa-arrow-right"></i></a>
      </div>

      <?php if (empty($upcoming_deadlines)): ?>
        <p style="color: var(--gray-500); text-align: center; padding: 1.5rem;">No upcoming task deadlines recorded.</p>
      <?php else: ?>
        <ul class="deadline-list">
          <?php foreach ($upcoming_deadlines as $task): 
            $deadline_timestamp = strtotime($task['deadline']);
            $is_overdue = ($task['status'] === 'Overdue') || ($deadline_timestamp < strtotime('today'));
          ?>
            <li class="deadline-item">
              <div class="deadline-date-badge" style="background: <?= $is_overdue ? 'var(--danger-light)' : 'var(--primary-light)' ?>; color: <?= $is_overdue ? 'var(--danger)' : 'var(--primary)' ?>;">
                <div class="deadline-day"><?= date('d', $deadline_timestamp) ?></div>
                <div class="deadline-month"><?= date('M', $deadline_timestamp) ?></div>
              </div>

              <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($task['title']) ?></strong>
                  <span class="badge <?= match($task['status']) { 'Accomplished' => 'badge-accomplished', 'In Progress' => 'badge-in-progress', 'Overdue' => 'badge-overdue', default => 'badge-todo' } ?>">
                    <?= $task['status'] ?>
                  </span>
                </div>
                <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.2rem;">
                  Assigned to: <strong><?= htmlspecialchars($task['assigned_to']) ?></strong> • Priority: <strong><?= $task['priority'] ?></strong>
                </div>
              </div>

              <a href="tasks.php?edit=<?= $task['id'] ?>" class="btn btn-secondary btn-sm" title="Edit Task"><i class="fa-solid fa-pen"></i></a>
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
        <a href="feedbacks.php" style="font-size: 0.85rem;">View All Feedbacks <i class="fa-solid fa-chevron-right"></i></a>
      </div>

      <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 1rem;">
        Monitor submissions from students and publish responses directly visible on the Student Portal.
      </p>

      <?php if (empty($pending_feedbacks)): ?>
        <p style="color: var(--gray-500); text-align: center; padding: 1.5rem;">No student feedbacks submitted for <?= htmlspecialchars($org_acronym) ?> yet.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          <?php foreach ($pending_feedbacks as $fb): ?>
            <div style="padding: 0.85rem; background: var(--gray-100); border-radius: var(--radius-sm); border: 1px solid var(--gray-200);">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
                <strong style="font-size: 0.9rem; color: var(--dark);"><?= htmlspecialchars($fb['subject']) ?></strong>
                <span class="badge <?= match($fb['status']) { 'Resolved' => 'badge-accomplished', 'In Action' => 'badge-in-progress', 'Under Review' => 'badge-todo', default => 'badge-planning' } ?>">
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
        <a href="tasks.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Manage Organization Tasks</a>
        <a href="projects.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-diagram-project" style="color: var(--secondary);"></i> Update Project Progress</a>
        <a href="accomplishments.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Post New Accomplishment</a>
        <a href="feedbacks.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-comments" style="color: var(--accent);"></i> Review Student Suggestions</a>
        <a href="users.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-users-gear" style="color: var(--info);"></i> Manage Officers & User Accounts</a>
        <a href="organizations.php" class="btn btn-secondary" style="justify-content: flex-start;"><i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> Manage Student Organizations</a>
      </div>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
