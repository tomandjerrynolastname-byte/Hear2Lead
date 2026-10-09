<?php
// leadership/projects.php - Manage Projects & Progress for Leadership Officers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$org_id = $_SESSION['org_id'];
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_project') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $progress_percent = intval($_POST['progress_percent']);
        $start_date = $_POST['start_date'] ?: null;
        $target_date = $_POST['target_date'] ?: null;

        if (empty($title)) {
            $error_msg = "Project title is required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO projects (org_id, title, description, status, progress_percent, start_date, target_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$org_id, $title, $description, $status, $progress_percent, $start_date, $target_date])) {
                $success_msg = "Project created successfully!";
            } else {
                $error_msg = "Failed to create project.";
            }
        }
    }

    if ($action === 'update_project') {
        $project_id = intval($_POST['project_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $progress_percent = intval($_POST['progress_percent']);
        $start_date = $_POST['start_date'] ?: null;
        $target_date = $_POST['target_date'] ?: null;

        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, status = ?, progress_percent = ?, start_date = ?, target_date = ? WHERE id = ? AND org_id = ?");
        if ($stmt->execute([$title, $description, $status, $progress_percent, $start_date, $target_date, $project_id, $org_id])) {
            $success_msg = "Project updated successfully!";
        }
    }

    if ($action === 'delete_project') {
        $project_id = intval($_POST['project_id']);
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND org_id = ?");
        if ($stmt->execute([$project_id, $org_id])) {
            $success_msg = "Project deleted successfully.";
        }
    }
}

$projects = $pdo->query("SELECT * FROM projects WHERE org_id = $org_id ORDER BY target_date ASC")->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-diagram-project" style="color: var(--secondary);"></i> Manage Organization Projects</h1>
    <p class="page-subtitle">Create and update active programs & completion progress bars shown on the Student Portal.</p>
  </div>

  <button class="btn btn-primary" data-modal-target="createProjectModal">
    <i class="fa-solid fa-plus"></i> Add New Project
  </button>
</div>

<?php if (!empty($success_msg)): ?>
  <div class="alert alert-success alert-auto-dismiss">
    <span><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
  <div class="alert alert-danger alert-auto-dismiss">
    <span><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></span>
  </div>
<?php endif; ?>

<div class="table-container">
  <table class="table">
    <thead>
      <tr>
        <th>Project Title</th>
        <th>Description</th>
        <th>Progress</th>
        <th>Status</th>
        <th>Target Completion</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($projects)): ?>
        <tr>
          <td colspan="6" style="text-align: center; color: var(--gray-500); padding: 2rem;">No projects recorded for <?= htmlspecialchars($_SESSION['org_acronym']) ?>.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($projects as $proj): ?>
          <tr>
            <td><strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($proj['title']) ?></strong></td>
            <td style="max-width: 280px; font-size: 0.85rem; color: var(--gray-600);"><?= htmlspecialchars($proj['description']) ?></td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 130px;">
                <div class="progress-container" style="margin: 0; flex: 1;">
                  <div class="progress-bar" style="width: <?= $proj['progress_percent'] ?>%;"></div>
                </div>
                <strong style="font-size: 0.8rem; color: var(--primary);"><?= $proj['progress_percent'] ?>%</strong>
              </div>
            </td>
            <td>
              <span class="badge <?= match($proj['status']) { 'Accomplished' => 'badge-accomplished', 'Ongoing' => 'badge-in-progress', 'Planning' => 'badge-todo', default => 'badge-planning' } ?>">
                <?= $proj['status'] ?>
              </span>
            </td>
            <td><span style="font-size: 0.85rem;"><i class="fa-regular fa-calendar"></i> <?= $proj['target_date'] ? date('M d, Y', strtotime($proj['target_date'])) : 'N/A' ?></span></td>
            <td>
              <div style="display: flex; gap: 0.4rem;">
                <button class="btn btn-secondary btn-sm" onclick='openProjectEditModal(<?= json_encode($proj) ?>)'><i class="fa-solid fa-pen"></i></button>
                <form method="POST" action="projects.php" onsubmit="return confirm('Delete this project?');" style="display: inline;">
                  <input type="hidden" name="action" value="delete_project">
                  <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Create Project Modal -->
<div class="modal-backdrop" id="createProjectModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-folder-plus" style="color: var(--primary);"></i> Add New Project</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="projects.php">
      <input type="hidden" name="action" value="create_project">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Project Title <span style="color: red;">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Tree Planting & Greener School Campaign" required>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" placeholder="Brief summary of project objectives..."></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="Planning">Planning</option>
              <option value="Ongoing" selected>Ongoing</option>
              <option value="Accomplished">Accomplished</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Progress Percentage (0 - 100%)</label>
            <input type="number" name="progress_percent" class="form-control" min="0" max="100" value="25" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Target End Date</label>
            <input type="date" name="target_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Create Project</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Project Modal -->
<div class="modal-backdrop" id="editProjectModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Project & Progress</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="projects.php">
      <input type="hidden" name="action" value="update_project">
      <input type="hidden" name="project_id" id="edit_project_id">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Project Title <span style="color: red;">*</span></label>
          <input type="text" name="title" id="edit_project_title" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_project_description" class="form-control"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="edit_project_status" class="form-control">
              <option value="Planning">Planning</option>
              <option value="Ongoing">Ongoing</option>
              <option value="Accomplished">Accomplished</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Progress Percentage (0 - 100%)</label>
            <input type="number" name="progress_percent" id="edit_project_progress_percent" class="form-control" min="0" max="100" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" id="edit_project_start_date" class="form-control">
          </div>

          <div class="form-group">
            <label class="form-label">Target End Date</label>
            <input type="date" name="target_date" id="edit_project_target_date" class="form-control">
          </div>
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
function openProjectEditModal(proj) {
  document.getElementById('edit_project_id').value = proj.id;
  document.getElementById('edit_project_title').value = proj.title;
  document.getElementById('edit_project_description').value = proj.description || '';
  document.getElementById('edit_project_status').value = proj.status;
  document.getElementById('edit_project_progress_percent').value = proj.progress_percent;
  document.getElementById('edit_project_start_date').value = proj.start_date || '';
  document.getElementById('edit_project_target_date').value = proj.target_date || '';
  
  document.getElementById('editProjectModal').classList.add('active');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
