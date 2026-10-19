<?php
// student/progress.php - Student View Progress Tracker
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/student_auth_check.php';
include_once __DIR__ . '/../includes/header.php';

$selected_org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

$query = "SELECT p.*, o.name as org_name, o.acronym FROM projects p JOIN organizations o ON p.org_id = o.id ";
if ($selected_org_id > 0) {
    $query .= "WHERE p.org_id = $selected_org_id ";
}
$query .= "ORDER BY p.progress_percent DESC, p.target_date ASC";

$projects = $pdo->query($query)->fetchAll();

// Fetch tasks linked to these projects for complete milestone & progress details in the modal
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
    <h1 class="page-title"><i class="fa-solid fa-chart-line" style="color: var(--secondary);"></i> Progress</h1>
    <p class="page-subtitle">Overall project progress, milestones, and current status.</p>
  </div>

  <div>
    <form method="GET" action="progress.php">
      <select name="org_id" class="form-control" onchange="this.form.submit()" style="font-weight: 600;">
        <option value="0">All Student Organizations</option>
        <?php foreach ($orgs as $org): ?>
          <option value="<?= $org['id'] ?>" <?= $selected_org_id == $org['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));">
  <?php if (empty($projects)): ?>
    <div class="panel" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
      <i class="fa-solid fa-spinner" style="font-size: 3rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
      <h3>No Projects Found</h3>
      <p style="color: var(--gray-600);">No project progress recorded for this organization selection.</p>
    </div>
  <?php else: ?>
    <?php foreach ($projects as $proj): 
      $proj_status_badges = [
        'Accomplished' => 'badge-accomplished',
        'Ongoing' => 'badge-in-progress',
        'Planning' => 'badge-todo'
      ];
      $status_class = $proj_status_badges[$proj['status']] ?? 'badge-planning';
    ?>
      <div class="card">
        <div class="card-header">
          <div>
            <span class="badge badge-planning"><?= htmlspecialchars($proj['acronym']) ?></span>
            <span class="badge <?= $status_class ?>" style="margin-left: 0.25rem;"><?= htmlspecialchars($proj['status']) ?></span>
            <h3 class="card-title" style="margin-top: 0.5rem;"><?= htmlspecialchars($proj['title']) ?></h3>
          </div>
          <span style="font-size: 1.25rem; font-weight: 800; color: var(--primary);"><?= $proj['progress_percent'] ?>%</span>
        </div>

        <div class="card-body">
          <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 1rem;">
            <?= htmlspecialchars(mb_strimwidth($proj['description'], 0, 140, '...')) ?>
          </p>
          
          <div style="margin-bottom: 0.5rem;">
            <div style="display: flex; justify-content: space-between; font-weight: 600; font-size: 0.85rem; color: var(--gray-700);">
              <span>Overall Completion</span>
              <span><?= $proj['progress_percent'] ?>%</span>
            </div>
            <div class="progress-container">
              <div class="progress-bar" style="width: <?= $proj['progress_percent'] ?>%; background: <?= $proj['progress_percent'] == 100 ? 'var(--success)' : 'linear-gradient(90deg, var(--primary), var(--secondary))' ?>;"></div>
            </div>
          </div>
        </div>

        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <div style="display: flex; flex-direction: column; gap: 0.15rem; font-size: 0.8rem;">
            <span><i class="fa-regular fa-calendar"></i> Start: <strong><?= $proj['start_date'] ? date('M d, Y', strtotime($proj['start_date'])) : 'TBA' ?></strong></span>
            <span><i class="fa-solid fa-flag-checkered"></i> Target: <strong><?= $proj['target_date'] ? date('M d, Y', strtotime($proj['target_date'])) : 'TBA' ?></strong></span>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="openViewProgressModal(<?= intval($proj['id']) ?>)">
            <i class="fa-solid fa-eye"></i> View Details
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- View Progress & Milestone Complete Details Modal -->
<div class="modal-backdrop" id="viewProgressModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-chart-line" style="color: var(--secondary);"></i> Progress & Milestone Complete Details
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.65rem;">
        <span class="badge badge-planning" id="view_prog_org_acronym"></span>
        <span class="badge" id="view_prog_status_badge"></span>
      </div>

      <h3 id="view_prog_title" style="font-size: 1.3rem; font-weight: 800; color: var(--dark); margin-bottom: 0.35rem;"></h3>
      <div id="view_prog_org_name" style="font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 1rem;"></div>

      <!-- Large Progress Tracker Box -->
      <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
          <span style="font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: var(--gray-700);">Overall Completion Progress</span>
          <strong id="view_prog_percent_text" style="font-size: 1.15rem; font-weight: 800; color: var(--primary);"></strong>
        </div>
        <div class="progress-container" style="height: 12px; margin: 0;">
          <div class="progress-bar" id="view_prog_bar" style="width: 0%;"></div>
        </div>
      </div>

      <!-- Schedule & Timeline Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; margin-bottom: 1.15rem;">
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Start Date</div>
          <div id="view_prog_start_date" style="font-size: 0.9rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Target Completion</div>
          <div id="view_prog_target_date" style="font-size: 0.9rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
      </div>

      <!-- Full Description -->
      <div style="margin-bottom: 1.15rem;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.4rem;">
          <i class="fa-solid fa-align-left" style="color: var(--primary);"></i> Full Project Description & Scope
        </div>
        <div id="view_prog_description" style="background: var(--cream-light); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.95rem; color: var(--gray-800); line-height: 1.65; white-space: pre-wrap;"></div>
      </div>

      <!-- Linked Milestones & Tasks Breakdown -->
      <div id="view_prog_tasks_wrapper" style="display: none;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.45rem;">
          <i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Associated Milestones & Tasks (<span id="view_prog_tasks_count">0</span>)
        </div>
        <div id="view_prog_tasks_list" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a id="view_prog_edit_link" href="../leadership/projects.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-pen-to-square"></i> Edit Progress in Leadership Portal
        </a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script>
const ALL_PROGRESS_PROJECTS = <?= json_encode($projects) ?>;
const PROGRESS_TASKS_MAP = <?= json_encode($tasks_by_project) ?>;

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

function openViewProgressModal(projId) {
  const modal = document.getElementById('viewProgressModal');
  if (!modal) return;

  const proj = ALL_PROGRESS_PROJECTS.find(p => parseInt(p.id, 10) === parseInt(projId, 10));
  if (!proj) return;

  document.getElementById('view_prog_org_acronym').textContent = proj.acronym || '';

  const statusBadge = document.getElementById('view_prog_status_badge');
  statusBadge.textContent = 'Status: ' + (proj.status || 'Planning');
  statusBadge.className = 'badge ' + (
    proj.status === 'Accomplished' ? 'badge-accomplished' :
    (proj.status === 'Ongoing' ? 'badge-in-progress' : 'badge-todo')
  );

  document.getElementById('view_prog_title').textContent = proj.title || '';
  document.getElementById('view_prog_org_name').innerHTML = '<i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> ' + escapeHtml(proj.org_name || proj.acronym || '');

  const pct = parseInt(proj.progress_percent || 0, 10);
  document.getElementById('view_prog_percent_text').textContent = pct + '%';
  const bar = document.getElementById('view_prog_bar');
  bar.style.width = pct + '%';
  bar.style.background = pct === 100 ? 'var(--success)' : 'linear-gradient(90deg, var(--primary), var(--secondary))';

  document.getElementById('view_prog_start_date').innerHTML = '<i class="fa-regular fa-calendar" style="color: var(--primary);"></i> ' + formatReadableDate(proj.start_date);
  document.getElementById('view_prog_target_date').innerHTML = '<i class="fa-solid fa-flag-checkered" style="color: var(--primary);"></i> ' + formatReadableDate(proj.target_date);
  document.getElementById('view_prog_description').textContent = proj.description || 'No description provided.';

  const tasks = PROGRESS_TASKS_MAP[proj.id] || [];
  const tasksWrapper = document.getElementById('view_prog_tasks_wrapper');
  const tasksList = document.getElementById('view_prog_tasks_list');
  const tasksCount = document.getElementById('view_prog_tasks_count');
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

  const editLink = document.getElementById('view_prog_edit_link');
  if (editLink) {
    editLink.href = '../leadership/projects.php?edit=' + encodeURIComponent(proj.id);
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

