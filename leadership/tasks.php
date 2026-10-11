<?php
// leadership/tasks.php - Task Organizer & Management for Leadership Officers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_role = $_SESSION['user_role'] ?? 'Officer';
$is_admin = ($current_role === 'Admin');
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

// 1. Handle Task Actions (Add, Edit, Delete, Quick Status Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'To Do';
        $priority = $_POST['priority'] ?? 'Medium';
        $deadline = $_POST['deadline'] ?? '';
        $assigned_to = trim($_POST['assigned_to'] ?? '') ?: 'All Officers';
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $task_org_id = $is_admin ? (!empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id) : $current_org_id;

        if (empty($title) || empty($deadline) || empty($task_org_id)) {
            $error_msg = "Task title and deadline date are required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO tasks (org_id, project_id, title, description, status, priority, deadline, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$task_org_id, $project_id, $title, $description, $status, $priority, $deadline, $assigned_to])) {
                $success_msg = "New task created successfully!";
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
        $assigned_to = trim($_POST['assigned_to'] ?? '') ?: 'All Officers';
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;

        if ($is_admin) {
            $task_org_id = !empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id;
            $stmt = $pdo->prepare("UPDATE tasks SET org_id = ?, project_id = ?, title = ?, description = ?, status = ?, priority = ?, deadline = ?, assigned_to = ? WHERE id = ?");
            if ($stmt->execute([$task_org_id, $project_id, $title, $description, $status, $priority, $deadline, $assigned_to, $task_id])) {
                $success_msg = "Task updated successfully!";
            } else {
                $error_msg = "Failed to update task.";
            }
        } else {
            $stmt = $pdo->prepare("UPDATE tasks SET project_id = ?, title = ?, description = ?, status = ?, priority = ?, deadline = ?, assigned_to = ? WHERE id = ? AND org_id = ?");
            if ($stmt->execute([$project_id, $title, $description, $status, $priority, $deadline, $assigned_to, $task_id, $current_org_id])) {
                $success_msg = "Task updated successfully!";
            } else {
                $error_msg = "Failed to update task.";
            }
        }
    }

    if ($action === 'quick_status') {
        $task_id = intval($_POST['task_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'To Do';

        if ($is_admin) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $task_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND org_id = ?");
            $stmt->execute([$new_status, $task_id, $current_org_id]);
        }
        $success_msg = "Task status changed to '$new_status'.";
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

// Fetch all orgs for Admin filter dropdown
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

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

// Fetch Projects for dropdown selection
if ($filter_org > 0) {
    $stmt_proj = $pdo->prepare("SELECT id, title, org_id FROM projects WHERE org_id = ? ORDER BY title ASC");
    $stmt_proj->execute([$filter_org]);
    $projects = $stmt_proj->fetchAll();
} else {
    $projects = $pdo->query("SELECT id, title, org_id FROM projects ORDER BY title ASC")->fetchAll();
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Individual Task Assignment and Monitoring</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Organize, assign, and monitor tasks <?= $filter_org > 0 ? 'for selected organization' : 'across all student organizations' ?>.
      <?php else: ?>
        Organize, assign, and monitor internal tasks and officer deadlines for <strong><?= htmlspecialchars($_SESSION['org_acronym']) ?></strong>.
      <?php endif; ?>
    </p>
  </div>

  <button class="btn btn-primary" data-modal-target="createTaskModal">
    <i class="fa-solid fa-plus"></i> Add New Task
  </button>
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
      <a href="tasks.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
    <?php endif; ?>
  </form>
</div>

<!-- Task Table -->
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
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($tasks)): ?>
        <tr>
          <td colspan="<?= ($is_admin && $filter_org === 0) ? '8' : '7' ?>" style="text-align: center; color: var(--gray-500); padding: 2rem;">No tasks found matching your filter criteria.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($tasks as $task): 
          $deadline_timestamp = strtotime($task['deadline']);
          $is_overdue = ($task['status'] === 'Overdue') || ($deadline_timestamp < strtotime('today') && $task['status'] !== 'Accomplished');
          $task_status_badges = ['Accomplished' => 'badge-accomplished', 'In Progress' => 'badge-in-progress', 'Overdue' => 'badge-overdue'];
          $task_badge = $task_status_badges[$task['status']] ?? 'badge-todo';
        ?>
          <tr>
            <?php if ($is_admin && $filter_org === 0): ?>
              <td><span class="badge badge-planning"><strong><?= htmlspecialchars($task['org_acronym'] ?? 'ORG') ?></strong></span></td>
            <?php endif; ?>
            <td>
              <strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($task['title']) ?></strong>
              <?php if (!empty($task['description'])): ?>
                <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.2rem;"><?= htmlspecialchars(mb_strimwidth($task['description'], 0, 80, "...")) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?= !empty($task['project_title']) ? '<i class="fa-solid fa-diagram-project" style="color: var(--secondary);"></i> ' . htmlspecialchars($task['project_title']) : '<span style="color: var(--gray-400); font-size: 0.85rem;">None</span>' ?>
            </td>
            <td><?= htmlspecialchars($task['assigned_to']) ?></td>
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
            </td>
            <td>
              <div style="display: flex; gap: 0.4rem;">
                <button class="btn btn-secondary btn-sm" onclick='openTaskEditModal(<?= json_encode($task) ?>)' title="Edit Task">
                  <i class="fa-solid fa-pen"></i>
                </button>
                <form method="POST" action="tasks.php" onsubmit="return confirm('Are you sure you want to delete this task?');" style="display: inline;">
                  <input type="hidden" name="action" value="delete_task">
                  <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" title="Delete Task"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Create Task Modal -->
<div class="modal-backdrop" id="createTaskModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-plus-circle" style="color: var(--primary);"></i> Create New Task</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="tasks.php">
      <input type="hidden" name="action" value="create_task">
      
      <div class="modal-body">
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Target Organization <span style="color: red;">*</span></label>
            <select name="org_id" class="form-control" required>
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $filter_org == $o['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
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
            <label class="form-label">Assigned To</label>
            <input type="text" name="assigned_to" class="form-control" placeholder="Officer Name or 'All Officers'">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Link to Project (Optional)</label>
          <select name="project_id" class="form-control">
            <option value="">-- No Project Linked --</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
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

    <form method="POST" action="tasks.php">
      <input type="hidden" name="action" value="update_task">
      <input type="hidden" name="task_id" id="edit_task_id">
      
      <div class="modal-body">
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <select name="org_id" id="edit_task_org_id" class="form-control" required>
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
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
            <label class="form-label">Assigned To</label>
            <input type="text" name="assigned_to" id="edit_task_assigned_to" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Link to Project (Optional)</label>
          <select name="project_id" id="edit_task_project_id" class="form-control">
            <option value="">-- No Project Linked --</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTaskEditModal(task) {
  document.getElementById('edit_task_id').value = task.id;
  document.getElementById('edit_task_title').value = task.title;
  document.getElementById('edit_task_description').value = task.description || '';
  document.getElementById('edit_task_status').value = task.status;
  document.getElementById('edit_task_priority').value = task.priority;
  document.getElementById('edit_task_deadline').value = task.deadline;
  document.getElementById('edit_task_assigned_to').value = task.assigned_to || '';
  document.getElementById('edit_task_project_id').value = task.project_id || '';
  var orgElem = document.getElementById('edit_task_org_id');
  if (orgElem && task.org_id) {
    orgElem.value = task.org_id;
  }
  
  document.getElementById('editTaskModal').classList.add('active');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
