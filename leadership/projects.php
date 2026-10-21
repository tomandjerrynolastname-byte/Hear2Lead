<?php
// leadership/projects.php - Manage Projects & Progress for Leadership Officers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_user_name = $_SESSION['user_name'] ?? 'Officer';
$current_role = $_SESSION['user_role'] ?? 'Officer';
$role_perms = getRolePermissions($current_role, $pdo);
$is_admin = !empty($role_perms['is_admin']);
if (empty($role_perms['can_manage_projects'])) {
    header("Location: dashboard.php?error=" . urlencode("Access denied. Your role ($current_role) does not have access to Programs & Projects."));
    exit;
}
$current_org_id = intval($_SESSION['org_id']);
$filter_org = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;
$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'logs') ? 'logs' : 'projects';

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
                $new_proj_id = intval($pdo->lastInsertId());
                $target_label = $target_date ? date('M d, Y', strtotime($target_date)) : 'TBA';
                $log_details = "Project created with title \"$title\", status '$status', progress {$progress_percent}%, target date: $target_label.";
                $log_stmt = $pdo->prepare("INSERT INTO project_logs (project_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, 'Created', ?)");
                $log_stmt->execute([$new_proj_id, $current_user_id, $current_user_name, $current_role, $log_details]);

                $success_msg = "Project created and logged successfully!";
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

        $old_stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $old_stmt->execute([$project_id]);
        $old_proj = $old_stmt->fetch();

        $updated = false;
        if ($is_admin) {
            $proj_org_id = !empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id;
            $stmt = $pdo->prepare("UPDATE projects SET org_id = ?, title = ?, description = ?, status = ?, progress_percent = ?, start_date = ?, target_date = ? WHERE id = ?");
            $updated = $stmt->execute([$proj_org_id, $title, $description, $status, $progress_percent, $start_date, $target_date, $project_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, status = ?, progress_percent = ?, start_date = ?, target_date = ? WHERE id = ? AND org_id = ?");
            $updated = $stmt->execute([$title, $description, $status, $progress_percent, $start_date, $target_date, $project_id, $current_org_id]);
        }

        if ($updated) {
            $changes = [];
            if ($old_proj) {
                if ($old_proj['title'] !== $title) {
                    $changes[] = "Title changed from \"{$old_proj['title']}\" to \"$title\"";
                }
                if (($old_proj['description'] ?? '') !== $description) {
                    $changes[] = "Description updated";
                }
                if ($old_proj['status'] !== $status) {
                    $changes[] = "Status changed from '{$old_proj['status']}' to '$status'";
                }
                if (intval($old_proj['progress_percent']) !== $progress_percent) {
                    $changes[] = "Progress updated from {$old_proj['progress_percent']}% to {$progress_percent}%";
                }
                if (($old_proj['start_date'] ?? '') !== ($start_date ?? '')) {
                    $changes[] = "Start date updated";
                }
                if (($old_proj['target_date'] ?? '') !== ($target_date ?? '')) {
                    $changes[] = "Target completion date updated";
                }
            }
            $action_type = ($old_proj && (intval($old_proj['progress_percent']) !== $progress_percent || $old_proj['status'] !== $status)) ? 'Progress Updated' : 'Updated';
            $log_details = !empty($changes)
                ? implode(' • ', $changes)
                : "Project saved (Status: '$status', Progress: {$progress_percent}%).";
            $log_stmt = $pdo->prepare("INSERT INTO project_logs (project_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, ?, ?)");
            $log_stmt->execute([$project_id, $current_user_id, $current_user_name, $current_role, $action_type, $log_details]);

            $success_msg = "Project updated and logged successfully!";
        } else {
            $error_msg = "Failed to update project.";
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

// Fetch tasks linked to these projects for complete milestone details in the modal
$tasks_by_project = [];
$project_logs_by_project = [];
$all_project_logs = [];
if (!empty($projects)) {
    $proj_ids = array_map(function($p) { return intval($p['id']); }, $projects);
    $placeholders = implode(',', array_fill(0, count($proj_ids), '?'));
    $stmt_t = $pdo->prepare("SELECT id, project_id, title, status, priority, deadline, assigned_to FROM tasks WHERE project_id IN ($placeholders) ORDER BY deadline ASC");
    $stmt_t->execute($proj_ids);
    foreach ($stmt_t->fetchAll() as $t) {
        $tasks_by_project[intval($t['project_id'])][] = $t;
    }

    $stmt_pl = $pdo->prepare("SELECT pl.*, p.title as project_title, p.status as project_status, p.progress_percent, o.acronym as org_acronym
                              FROM project_logs pl
                              JOIN projects p ON pl.project_id = p.id
                              LEFT JOIN organizations o ON p.org_id = o.id
                              WHERE pl.project_id IN ($placeholders)
                              ORDER BY pl.created_at DESC, pl.id DESC");
    $stmt_pl->execute($proj_ids);
    foreach ($stmt_pl->fetchAll() as $log) {
        $pid = intval($log['project_id']);
        $log['formatted_time'] = date('M d, Y • g:i:s A', strtotime($log['created_at']));
        $project_logs_by_project[$pid][] = $log;
        $all_project_logs[] = $log;
    }
}

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
      <input type="hidden" name="tab" id="projFilterTabInput" value="<?= htmlspecialchars($active_tab) ?>">
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
        <a href="projects.php?tab=<?= htmlspecialchars($active_tab) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> View All</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<!-- Tab Panel Navigation -->
<div class="user-tabs-nav">
  <button type="button" class="user-tab-btn <?= $active_tab === 'projects' ? 'active' : '' ?>" onclick="switchProjectTab('projects')">
    <i class="fa-solid fa-folder-tree"></i>
    <span>Programs & Projects</span>
    <span class="user-tab-badge"><?= count($projects) ?></span>
  </button>
  <button type="button" class="user-tab-btn <?= $active_tab === 'logs' ? 'active' : '' ?>" onclick="switchProjectTab('logs')">
    <i class="fa-solid fa-clock-rotate-left"></i>
    <span>Project Activity Logs</span>
    <span class="user-tab-badge"><?= count($all_project_logs) ?></span>
  </button>
</div>

<!-- TAB 1: Programs & Projects Table -->
<div class="user-tab-pane <?= $active_tab === 'projects' ? 'active' : '' ?>" id="tab-projects">
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
        <th>Actions & Logs</th>
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
          $proj_log_count = count($project_logs_by_project[intval($proj['id'])] ?? []);
        ?>
          <tr>
            <?php if ($is_admin && $filter_org === 0): ?>
              <td><span class="badge badge-planning"><strong><?= htmlspecialchars($proj['org_acronym']) ?></strong></span></td>
            <?php endif; ?>
            <td><strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($proj['title']) ?></strong></td>
            <td style="max-width: 280px; font-size: 0.85rem; color: var(--gray-600);">
              <?= htmlspecialchars(mb_strimwidth($proj['description'], 0, 100, '...')) ?>
            </td>
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
                <?= htmlspecialchars($proj['status']) ?>
              </span>
            </td>
            <td><span style="font-size: 0.85rem;"><i class="fa-regular fa-calendar"></i> <?= $proj['target_date'] ? date('M d, Y', strtotime($proj['target_date'])) : 'N/A' ?></span></td>
            <td>
              <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="openViewProjectModal(<?= intval($proj['id']) ?>)" title="View Complete Details">
                  <i class="fa-solid fa-eye"></i> View
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openProjectLogsModal(<?= intval($proj['id']) ?>)" title="View Project Activity Logs">
                  <i class="fa-solid fa-clock-rotate-left"></i> Logs (<?= $proj_log_count ?>)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openProjectEditModal(<?= intval($proj['id']) ?>)" title="Edit Project & Progress">
                  <i class="fa-solid fa-pen"></i> Edit
                </button>
                <form method="POST" action="projects.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete this project?', 'Delete Project?');" style="display: inline; margin: 0;">
                  <input type="hidden" name="action" value="delete_project">
                  <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" title="Delete Project"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>

<!-- TAB 2: Project Activity Logs Pane -->
<div class="user-tab-pane <?= $active_tab === 'logs' ? 'active' : '' ?>" id="tab-logs">
  <div class="panel">
    <div class="panel-header">
      <span><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Program & Project Progress Activity Logs</span>
      <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($all_project_logs) ?> Total Entries</span>
    </div>

    <?php if (empty($all_project_logs)): ?>
      <div style="text-align: center; padding: 2.5rem; color: var(--gray-500);">
        <i class="fa-solid fa-clock-rotate-left" style="font-size: 2.2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
        No project activity logs recorded for the selected criteria.
      </div>
    <?php else: ?>
      <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
        <table class="table">
          <thead>
            <tr>
              <?php if ($is_admin && $filter_org === 0): ?>
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
            <?php foreach ($all_project_logs as $log):
              $action_badge = $log['action_type'] === 'Created' ? 'badge-accomplished' : ($log['action_type'] === 'Progress Updated' ? 'badge-todo' : 'badge-in-progress');
            ?>
              <tr>
                <?php if ($is_admin && $filter_org === 0): ?>
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

<!-- View Project Activity Logs Modal -->
<div class="modal-backdrop" id="viewProjectLogsModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Project Activity Logs
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="background: var(--gray-100); padding: 0.85rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; border: 1px solid var(--gray-200);">
        <div style="font-size: 0.78rem; color: var(--gray-500); text-transform: uppercase; font-weight: 700;">Program / Project</div>
        <div id="log_modal_proj_title" style="font-size: 1.05rem; font-weight: 800; color: var(--dark); margin-top: 0.1rem;"></div>
        <div id="log_modal_proj_meta" style="font-size: 0.82rem; color: var(--gray-600); margin-top: 0.25rem;"></div>
      </div>
      <div id="projectLogsTimelineContainer" style="max-height: 360px; overflow-y: auto; padding-right: 0.25rem;"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- View Program / Project Complete Details Modal -->
<div class="modal-backdrop" id="viewProjectModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-folder-tree" style="color: var(--primary);"></i> Program & Project Complete Details
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.65rem;">
        <span class="badge badge-planning" id="view_proj_org_acronym"></span>
        <span class="badge" id="view_proj_status_badge"></span>
      </div>

      <h3 id="view_proj_title" style="font-size: 1.3rem; font-weight: 800; color: var(--dark); margin-bottom: 0.35rem;"></h3>
      <div id="view_proj_org_name" style="font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 1rem;"></div>

      <!-- Progress Bar Section -->
      <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.9rem 1rem; margin-bottom: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
          <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--gray-600);">Overall Completion Progress</span>
          <strong id="view_proj_progress_text" style="font-size: 1rem; color: var(--primary);"></strong>
        </div>
        <div class="progress-container" style="height: 10px; margin: 0;">
          <div class="progress-bar" id="view_proj_progress_bar" style="width: 0%;"></div>
        </div>
      </div>

      <!-- Dates Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; margin-bottom: 1.15rem;">
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Start Date</div>
          <div id="view_proj_start_date" style="font-size: 0.9rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Target Completion</div>
          <div id="view_proj_target_date" style="font-size: 0.9rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
      </div>

      <!-- Full Description -->
      <div style="margin-bottom: 1.15rem;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.4rem;">
          <i class="fa-solid fa-align-left" style="color: var(--primary);"></i> Full Program Description & Objectives
        </div>
        <div id="view_proj_description" style="background: var(--cream-light); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.95rem; color: var(--gray-800); line-height: 1.65; white-space: pre-wrap;"></div>
      </div>

      <!-- Linked Tasks / Milestones -->
      <div id="view_proj_tasks_wrapper" style="display: none;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.45rem;">
          <i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Linked Milestones & Tasks (<span id="view_proj_tasks_count">0</span>)
        </div>
        <div id="view_proj_tasks_list" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" id="view_proj_switch_edit_btn">
        <i class="fa-solid fa-pen-to-square"></i> Edit Project & Progress
      </button>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
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
const ALL_PROJECTS = <?= json_encode($projects) ?>;
const PROJECT_TASKS_MAP = <?= json_encode($tasks_by_project) ?>;
const PROJECT_LOGS_MAP = <?= json_encode($project_logs_by_project) ?>;

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function switchProjectTab(tabName) {
  const btns = document.querySelectorAll('.user-tabs-nav .user-tab-btn');
  const panes = document.querySelectorAll('.user-tab-pane');
  btns.forEach(btn => btn.classList.remove('active'));
  panes.forEach(pane => pane.classList.remove('active'));

  const tabInput = document.getElementById('projFilterTabInput');
  if (tabInput) tabInput.value = tabName;

  if (tabName === 'logs') {
    if (btns[1]) btns[1].classList.add('active');
    const logPane = document.getElementById('tab-logs');
    if (logPane) logPane.classList.add('active');
  } else {
    if (btns[0]) btns[0].classList.add('active');
    const projPane = document.getElementById('tab-projects');
    if (projPane) projPane.classList.add('active');
  }

  const url = new URL(window.location.href);
  url.searchParams.set('tab', tabName);
  history.replaceState(null, '', url.toString());
}

function openProjectLogsModal(projId) {
  const modal = document.getElementById('viewProjectLogsModal');
  if (!modal) return;

  const proj = ALL_PROJECTS.find(p => parseInt(p.id, 10) === parseInt(projId, 10));
  const titleEl = document.getElementById('log_modal_proj_title');
  const metaEl = document.getElementById('log_modal_proj_meta');
  const container = document.getElementById('projectLogsTimelineContainer');

  if (proj) {
    titleEl.textContent = proj.title;
    metaEl.textContent = 'Status: ' + proj.status + ' • Progress: ' + (proj.progress_percent ?? 0) + '%';
  } else {
    titleEl.textContent = 'Project #' + projId;
    metaEl.textContent = '';
  }

  const logs = PROJECT_LOGS_MAP[projId] || [];
  if (logs.length === 0) {
    container.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: var(--gray-500);">No activity logs recorded for this project yet.</div>';
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
      } else if (log.action_type === 'Progress Updated') {
        stepClass = 'step-info';
        badgeClass = 'badge-todo';
        iconClass = 'fa-chart-line';
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

function formatReadableDate(dateStr) {
  if (!dateStr) return 'TBA';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
}

function openViewProjectModal(projId) {
  const modal = document.getElementById('viewProjectModal');
  if (!modal) return;

  const proj = ALL_PROJECTS.find(p => parseInt(p.id, 10) === parseInt(projId, 10));
  if (!proj) return;

  document.getElementById('view_proj_org_acronym').textContent = proj.org_acronym || '';

  const statusBadge = document.getElementById('view_proj_status_badge');
  statusBadge.textContent = 'Status: ' + (proj.status || 'Planning');
  statusBadge.className = 'badge ' + (
    proj.status === 'Accomplished' ? 'badge-accomplished' :
    (proj.status === 'Ongoing' ? 'badge-in-progress' : 'badge-todo')
  );

  document.getElementById('view_proj_title').textContent = proj.title || '';
  document.getElementById('view_proj_org_name').innerHTML = '<i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> ' + escapeHtml(proj.org_name || proj.org_acronym || '');

  const pct = parseInt(proj.progress_percent || 0, 10);
  document.getElementById('view_proj_progress_text').textContent = pct + '%';
  const bar = document.getElementById('view_proj_progress_bar');
  bar.style.width = pct + '%';
  bar.style.background = pct === 100 ? 'var(--success)' : 'linear-gradient(90deg, var(--primary), var(--secondary))';

  document.getElementById('view_proj_start_date').innerHTML = '<i class="fa-regular fa-calendar" style="color: var(--primary);"></i> ' + formatReadableDate(proj.start_date);
  document.getElementById('view_proj_target_date').innerHTML = '<i class="fa-solid fa-flag-checkered" style="color: var(--primary);"></i> ' + formatReadableDate(proj.target_date);
  document.getElementById('view_proj_description').textContent = proj.description || 'No description provided.';

  const tasks = PROJECT_TASKS_MAP[proj.id] || [];
  const tasksWrapper = document.getElementById('view_proj_tasks_wrapper');
  const tasksList = document.getElementById('view_proj_tasks_list');
  const tasksCount = document.getElementById('view_proj_tasks_count');
  if (tasks.length > 0) {
    tasksWrapper.style.display = 'block';
    tasksCount.textContent = tasks.length;
    tasksList.innerHTML = tasks.map(t => {
      const tb = t.status === 'Accomplished' ? 'badge-accomplished' : (t.status === 'In Progress' ? 'badge-in-progress' : 'badge-todo');
      return `
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.6rem 0.85rem; flex-wrap: wrap;">
          <div>
            <div style="font-size: 0.88rem; font-weight: 700; color: var(--dark);">${escapeHtml(t.title)}</div>
            <div style="font-size: 0.75rem; color: var(--gray-500);">Assigned: ${escapeHtml(t.assigned_to || 'Unassigned')} • Due: ${formatReadableDate(t.deadline)}</div>
          </div>
          <span class="badge ${tb}" style="font-size: 0.7rem;">${escapeHtml(t.status)}</span>
        </div>
      `;
    }).join('');
  } else {
    tasksWrapper.style.display = 'none';
    tasksList.innerHTML = '';
  }

  const switchEditBtn = document.getElementById('view_proj_switch_edit_btn');
  if (switchEditBtn) {
    switchEditBtn.onclick = function() {
      modal.classList.remove('active');
      openProjectEditModal(proj.id);
    };
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function openProjectEditModal(projOrId) {
  const modal = document.getElementById('editProjectModal');
  if (!modal) return;

  const proj = (typeof projOrId === 'object' && projOrId !== null)
    ? projOrId
    : ALL_PROJECTS.find(p => parseInt(p.id, 10) === parseInt(projOrId, 10));
  if (!proj) return;

  document.getElementById('edit_project_id').value = proj.id;
  document.getElementById('edit_project_title').value = proj.title || '';
  document.getElementById('edit_project_description').value = proj.description || '';
  document.getElementById('edit_project_status').value = proj.status || 'Ongoing';
  document.getElementById('edit_project_progress_percent').value = proj.progress_percent ?? 0;
  document.getElementById('edit_project_start_date').value = proj.start_date || '';
  document.getElementById('edit_project_target_date').value = proj.target_date || '';
  var orgElem = document.getElementById('edit_project_org_id');
  if (orgElem && proj.org_id) {
    orgElem.value = proj.org_id;
  }
  
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('edit')) {
    openProjectEditModal(parseInt(urlParams.get('edit'), 10));
  } else if (urlParams.get('view')) {
    openViewProjectModal(parseInt(urlParams.get('view'), 10));
  }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

