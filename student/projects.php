<?php
// student/projects.php - Student View Projects & Programs
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/student_auth_check.php';
include_once __DIR__ . '/../includes/header.php';

$selected_org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';

$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

$query = "SELECT p.*, o.name as org_name, o.acronym FROM projects p JOIN organizations o ON p.org_id = o.id WHERE 1=1 ";
if ($selected_org_id > 0) {
    $query .= "AND p.org_id = $selected_org_id ";
}
if (!empty($selected_status)) {
    $query .= "AND p.status = " . $pdo->quote($selected_status) . " ";
}
$query .= "ORDER BY p.target_date ASC";

$projects = $pdo->query($query)->fetchAll();

// Fetch tasks linked to these projects for complete milestone details in the modal
$tasks_by_project = [];
if (!empty($projects)) {
    $proj_ids = array_map(function($p) { return intval($p['id']); }, $projects);
    $placeholders = implode(',', array_fill(0, count($proj_ids), '?'));
    $stmt_t = $pdo->prepare("SELECT id, project_id, title, status, priority, deadline, assigned_to FROM tasks WHERE project_id IN ($placeholders) ORDER BY deadline ASC");
    $stmt_t->execute($proj_ids);
    foreach ($stmt_t->fetchAll() as $t) {
        $tasks_by_project[intval($t['project_id'])][] = $t;
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-regular fa-calendar-days" style="color: var(--primary);"></i> Programs and Projects</h1>
    <p class="page-subtitle">Organization programs, project descriptions, and schedules.</p>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
  <form method="GET" action="projects.php" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%; align-items: center;">
    <div class="filter-group" style="flex: 1; min-width: 200px;">
      <label class="form-label" style="margin: 0; min-width: 90px;">Organization:</label>
      <select name="org_id" class="form-control" onchange="this.form.submit()">
        <option value="0">All Organizations</option>
        <?php foreach ($orgs as $org): ?>
          <option value="<?= $org['id'] ?>" <?= $selected_org_id == $org['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group" style="flex: 1; min-width: 200px;">
      <label class="form-label" style="margin: 0; min-width: 60px;">Status:</label>
      <select name="status" class="form-control" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="Ongoing" <?= $selected_status == 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
        <option value="Planning" <?= $selected_status == 'Planning' ? 'selected' : '' ?>>Planning</option>
        <option value="Accomplished" <?= $selected_status == 'Accomplished' ? 'selected' : '' ?>>Accomplished</option>
      </select>
    </div>

    <?php if ($selected_org_id > 0 || !empty($selected_status)): ?>
      <a href="projects.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
    <?php endif; ?>
  </form>
</div>

<div class="table-container">
  <table class="table">
    <thead>
      <tr>
        <th>Organization</th>
        <th>Project / Program Title</th>
        <th>Description</th>
        <th>Progress</th>
        <th>Status</th>
        <th>Target Deadline</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($projects)): ?>
        <tr>
          <td colspan="7" style="text-align: center; color: var(--gray-500); padding: 2rem;">No projects matching the selected criteria.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($projects as $proj): 
          $proj_status_badges = [
            'Accomplished' => 'badge-accomplished',
            'Ongoing' => 'badge-in-progress',
            'Planning' => 'badge-todo'
          ];
          $status_class = $proj_status_badges[$proj['status']] ?? 'badge-planning';
        ?>
          <tr>
            <td><strong style="color: var(--primary);"><?= htmlspecialchars($proj['acronym']) ?></strong></td>
            <td><strong style="font-size: 0.95rem; color: var(--dark);"><?= htmlspecialchars($proj['title']) ?></strong></td>
            <td style="max-width: 300px; font-size: 0.85rem; color: var(--gray-600);">
              <?= htmlspecialchars(mb_strimwidth($proj['description'], 0, 100, '...')) ?>
            </td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 120px;">
                <div class="progress-container" style="margin: 0; flex: 1;">
                  <div class="progress-bar" style="width: <?= $proj['progress_percent'] ?>%;"></div>
                </div>
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--gray-700);"><?= $proj['progress_percent'] ?>%</span>
              </div>
            </td>
            <td><span class="badge <?= $status_class ?>"><?= htmlspecialchars($proj['status']) ?></span></td>
            <td><span style="font-size: 0.85rem; font-weight: 600;"><i class="fa-regular fa-calendar"></i> <?= $proj['target_date'] ? date('M d, Y', strtotime($proj['target_date'])) : 'N/A' ?></span></td>
            <td>
              <button type="button" class="btn btn-secondary btn-sm" onclick="openViewProjectModal(<?= intval($proj['id']) ?>)">
                <i class="fa-solid fa-eye"></i> View Details
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <a id="view_proj_edit_link" href="../leadership/projects.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-pen-to-square"></i> Edit in Leadership Portal
        </a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script>
const ALL_PROJECTS = <?= json_encode($projects) ?>;
const PROJECT_TASKS_MAP = <?= json_encode($tasks_by_project) ?>;

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
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

  document.getElementById('view_proj_org_acronym').textContent = proj.acronym || '';

  const statusBadge = document.getElementById('view_proj_status_badge');
  statusBadge.textContent = 'Status: ' + (proj.status || 'Planning');
  statusBadge.className = 'badge ' + (
    proj.status === 'Accomplished' ? 'badge-accomplished' :
    (proj.status === 'Ongoing' ? 'badge-in-progress' : 'badge-todo')
  );

  document.getElementById('view_proj_title').textContent = proj.title || '';
  document.getElementById('view_proj_org_name').innerHTML = '<i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> ' + escapeHtml(proj.org_name || proj.acronym || '');

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
            <div style="font-size: 0.75rem; color: var(--gray-500);">Due: ${formatReadableDate(t.deadline)}</div>
          </div>
          <span class="badge ${tb}" style="font-size: 0.7rem;">${escapeHtml(t.status)}</span>
        </div>
      `;
    }).join('');
  } else {
    tasksWrapper.style.display = 'none';
    tasksList.innerHTML = '';
  }

  const editLink = document.getElementById('view_proj_edit_link');
  if (editLink) {
    editLink.href = '../leadership/projects.php?edit=' + encodeURIComponent(proj.id);
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

