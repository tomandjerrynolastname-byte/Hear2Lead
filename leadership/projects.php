<?php
// leadership/projects.php - Manage Projects & Progress for Leadership Officers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_role = $_SESSION['user_role'] ?? 'Officer';
$is_admin = ($current_role === 'Admin');
$current_org_id = intval($_SESSION['org_id']);
$filter_org = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_project') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Ongoing';
        $progress_percent = intval($_POST['progress_percent'] ?? 0);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
        $proj_org_id = $is_admin ? (!empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id) : $current_org_id;

        if (empty($title)) {
            $error_msg = "Project title is required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO projects (org_id, title, description, status, progress_percent, start_date, target_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$proj_org_id, $title, $description, $status, $progress_percent, $start_date, $target_date])) {
                $success_msg = "Project created successfully!";
            } else {
                $error_msg = "Failed to create project.";
            }
        }
    }

    if ($action === 'update_project') {
        $project_id = intval($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Ongoing';
        $progress_percent = intval($_POST['progress_percent'] ?? 0);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;

        if ($is_admin) {
            $proj_org_id = !empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id;
            $stmt = $pdo->prepare("UPDATE projects SET org_id = ?, title = ?, description = ?, status = ?, progress_percent = ?, start_date = ?, target_date = ? WHERE id = ?");
            if ($stmt->execute([$proj_org_id, $title, $description, $status, $progress_percent, $start_date, $target_date, $project_id])) {
                $success_msg = "Project updated successfully!";
            } else {
                $error_msg = "Failed to update project.";
            }
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, status = ?, progress_percent = ?, start_date = ?, target_date = ? WHERE id = ? AND org_id = ?");
            if ($stmt->execute([$title, $description, $status, $progress_percent, $start_date, $target_date, $project_id, $current_org_id])) {
                $success_msg = "Project updated successfully!";
            } else {
                $error_msg = "Failed to update project.";
            }
        }
    }

    if ($action === 'delete_project') {
        $project_id = intval($_POST['project_id'] ?? 0);
        if ($is_admin) {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND org_id = ?");
            $stmt->execute([$project_id, $current_org_id]);
        }
        $success_msg = "Project deleted successfully.";
    }
}

// Fetch organizations for Admin filter
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

// Build query
$where = ["1=1"];
$params = [];
if ($filter_org > 0) {
    $where[] = "p.org_id = ?";
    $params[] = $filter_org;
}
$where_sql = implode(" AND ", $where);

$query = "SELECT p.*, o.acronym as org_acronym, o.name as org_name 
          FROM projects p 
          JOIN organizations o ON p.org_id = o.id 
          WHERE $where_sql 
          ORDER BY p.target_date ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-folder-tree" style="color: var(--secondary);"></i> Programs and Projects Management</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Create and update programs, project progress updates, and completion timelines <?= $filter_org > 0 ? 'for selected organization' : 'across all student organizations' ?>.
      <?php else: ?>
        Create and update active programs, project progress updates, and completion timelines for <strong><?= htmlspecialchars($_SESSION['org_acronym']) ?></strong>.
      <?php endif; ?>
    </p>
  </div>

  <button class="btn btn-primary" data-modal-target="createProjectModal">
    <i class="fa-solid fa-plus"></i> Add New Project
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

<?php if ($is_admin): ?>
  <div class="filter-bar" style="margin-bottom: 1.5rem;">
    <form method="GET" action="projects.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <div class="filter-group">
        <label class="form-label" style="margin: 0;">Organization Filter:</label>
        <select name="org_id" class="form-control" onchange="this.form.submit()">
          <option value="0">All Organizations</option>
          <?php foreach ($all_orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $filter_org == $o['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($filter_org > 0): ?>
        <a href="projects.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> View All</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<div class="table-container">
  <table class="table">
    <thead>
      <tr>
        <?php if ($is_admin && $filter_org === 0): ?>
          <th>Org</th>
        <?php endif; ?>
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
          <td colspan="<?= ($is_admin && $filter_org === 0) ? '7' : '6' ?>" style="text-align: center; color: var(--gray-500); padding: 2rem;">No projects recorded for selected criteria.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($projects as $proj): 
          $proj_status_badges = [
            'Accomplished' => 'badge-accomplished',
            'Ongoing' => 'badge-in-progress',
            'Planning' => 'badge-todo'
          ];
          $proj_badge_class = $proj_status_badges[$proj['status']] ?? 'badge-planning';
        ?>
          <tr>
            <?php if ($is_admin && $filter_org === 0): ?>
              <td><span class="badge badge-planning"><strong><?= htmlspecialchars($proj['org_acronym']) ?></strong></span></td>
            <?php endif; ?>
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
              <span class="badge <?= $proj_badge_class ?>">
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
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
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
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <select name="org_id" id="edit_project_org_id" class="form-control" required>
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
        <?php endif; ?>

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
  var orgElem = document.getElementById('edit_project_org_id');
  if (orgElem && proj.org_id) {
    orgElem.value = proj.org_id;
  }
  
  document.getElementById('editProjectModal').classList.add('active');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
