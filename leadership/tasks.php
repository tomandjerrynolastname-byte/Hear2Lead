<?php
// leadership/tasks.php - Task Organizer & Management for Leadership Officers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$org_id = $_SESSION['org_id'];
$success_msg = '';
$error_msg = '';

// Update overdue tasks automatically
$pdo->exec("UPDATE tasks SET status = 'Overdue' WHERE org_id = $org_id AND deadline < CURDATE() AND status != 'Accomplished'");

// 1. Handle Task Actions (Add, Edit, Delete, Quick Status Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $deadline = $_POST['deadline'];
        $assigned_to = trim($_POST['assigned_to']) ?: 'All Officers';
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;

        if (empty($title) || empty($deadline)) {
            $error_msg = "Task title and deadline date are required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO tasks (org_id, project_id, title, description, status, priority, deadline, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$org_id, $project_id, $title, $description, $status, $priority, $deadline, $assigned_to])) {
                $success_msg = "New task created successfully!";
            } else {
                $error_msg = "Failed to create task.";
            }
        }
    }

    if ($action === 'update_task') {
        $task_id = intval($_POST['task_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $deadline = $_POST['deadline'];
        $assigned_to = trim($_POST['assigned_to']) ?: 'All Officers';
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;

        $stmt = $pdo->prepare("UPDATE tasks SET project_id = ?, title = ?, description = ?, status = ?, priority = ?, deadline = ?, assigned_to = ? WHERE id = ? AND org_id = ?");
        if ($stmt->execute([$project_id, $title, $description, $status, $priority, $deadline, $assigned_to, $task_id, $org_id])) {
            $success_msg = "Task updated successfully!";
        } else {
            $error_msg = "Failed to update task.";
        }
    }

    if ($action === 'quick_status') {
        $task_id = intval($_POST['task_id']);
        $new_status = $_POST['new_status'];

        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND org_id = ?");
        if ($stmt->execute([$new_status, $task_id, $org_id])) {
            $success_msg = "Task status changed to '$new_status'.";
        }
    }

    if ($action === 'delete_task') {
        $task_id = intval($_POST['task_id']);
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND org_id = ?");
        if ($stmt->execute([$task_id, $org_id])) {
            $success_msg = "Task deleted successfully.";
        }
    }
}

// Filter variables
$filter_status = $_GET['status'] ?? '';
$filter_priority = $_GET['priority'] ?? '';

// Build Query
$query = "SELECT t.*, p.title as project_title FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.org_id = $org_id ";
if (!empty($filter_status)) {
    $query .= "AND t.status = " . $pdo->quote($filter_status) . " ";
}
if (!empty($filter_priority)) {
    $query .= "AND t.priority = " . $pdo->quote($filter_priority) . " ";
}
$query .= "ORDER BY CASE WHEN t.status = 'Overdue' THEN 1 WHEN t.status = 'To Do' THEN 2 WHEN t.status = 'In Progress' THEN 3 ELSE 4 END, t.deadline ASC";

$tasks = $pdo->query($query)->fetchAll();

// Fetch Projects for dropdown selection
$projects = $pdo->query("SELECT id, title FROM projects WHERE org_id = $org_id ORDER BY title ASC")->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Organization Task Tracker</h1>
    <p class="page-subtitle">Organize and monitor all internal tasks, officer assignments, and deadlines for <?= htmlspecialchars($_SESSION['org_acronym']) ?>.</p>
  </div>

  <button class="btn btn-primary" data-modal-target="createTaskModal">
    <i class="fa-solid fa-plus"></i> Add New Task
  </button>
</div>

<?php if (!empty($success_msg)): ?>
  <div class="alert alert-success alert-auto-dismiss">
    <span><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
  <div class="alert alert-danger alert-auto-dismiss">
    <span><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></span>
  </div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-bar">
  <form method="GET" action="tasks.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
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
        <option value="High" <?= $filter_priority == 'High' ? 'selected' : '' ?>>High Priority</option>
        <option value="Medium" <?= $filter_priority == 'Medium' ? 'selected' : '' ?>>Medium Priority</option>
        <option value="Low" <?= $filter_priority == 'Low' ? 'selected' : '' ?>>Low Priority</option>
      </select>
    </div>

    <?php if (!empty($filter_status) || !empty($filter_priority)): ?>
      <a href="tasks.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Clear Filter</a>
    <?php endif; ?>
  </form>
</div>

<!-- Task Table List -->
<div class="table-container">
  <table class="table">
    <thead>
      <tr>
        <th>Task Title</th>
        <th>Assigned Officer</th>
        <th>Priority</th>
        <th>Deadline</th>
        <th>Status</th>
        <th>Quick Action</th>
        <th>Manage</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($tasks)): ?>
        <tr>
          <td colspan="7" style="text-align: center; color: var(--gray-500); padding: 2rem;">No tasks found matching your filter selection.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($tasks as $task): 
          $deadline_time = strtotime($task['deadline']);
          $is_overdue = ($task['status'] === 'Overdue') || ($deadline_time < strtotime('today') && $task['status'] !== 'Accomplished');
          
          $status_class = match($task['status']) {
            'Accomplished' => 'badge-accomplished',
            'In Progress' => 'badge-in-progress',
            'Overdue' => 'badge-overdue',
            default => 'badge-todo'
          };
          
          $priority_color = match($task['priority']) {
            'High' => 'color: #dc2626; font-weight: 700;',
            'Medium' => 'color: #d97706; font-weight: 600;',
            default => 'color: #16a34a;'
          };
        ?>
          <tr style="<?= $is_overdue ? 'background-color: #fef2f2;' : '' ?>">
            <td>
              <strong style="font-size: 0.95rem; color: var(--dark);"><?= htmlspecialchars($task['title']) ?></strong>
              <?php if (!empty($task['project_title'])): ?>
                <div style="font-size: 0.75rem; color: var(--gray-500);"><i class="fa-solid fa-diagram-project"></i> <?= htmlspecialchars($task['project_title']) ?></div>
              <?php endif; ?>
              <?php if (!empty($task['description'])): ?>
                <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.2rem; max-width: 300px;"><?= htmlspecialchars($task['description']) ?></div>
              <?php endif; ?>
            </td>
            <td><span style="font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($task['assigned_to']) ?></span></td>
            <td><span style="<?= $priority_color ?>; font-size: 0.85rem;"><i class="fa-solid fa-flag"></i> <?= $task['priority'] ?></span></td>
            <td>
              <span style="font-size: 0.85rem; <?= $is_overdue ? 'color: var(--danger); font-weight: 700;' : '' ?>">
                <i class="fa-regular fa-calendar-times"></i> <?= date('M d, Y', $deadline_time) ?>
              </span>
            </td>
            <td><span class="badge <?= $status_class ?>"><?= htmlspecialchars($task['status']) ?></span></td>
            <td>
              <form method="POST" action="tasks.php" style="display: inline;">
                <input type="hidden" name="action" value="quick_status">
                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                
                <?php if ($task['status'] !== 'Accomplished'): ?>
                  <button type="submit" name="new_status" value="Accomplished" class="btn btn-success btn-sm" title="Mark as Accomplished">
                    <i class="fa-solid fa-check"></i> Accomplish
                  </button>
                <?php else: ?>
                  <button type="submit" name="new_status" value="To Do" class="btn btn-secondary btn-sm" title="Reopen Task">
                    <i class="fa-solid fa-rotate-left"></i> Reopen
                  </button>
                <?php endif; ?>
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

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
