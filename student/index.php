<?php
// student/index.php - Student Portal Overview
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/student_auth_check.php';
include_once __DIR__ . '/../includes/header.php';

// Fetch organizations
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

// Filter by Org ID if set
$selected_org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;

$where_clause = $selected_org_id > 0 ? "WHERE org_id = $selected_org_id" : "";

// Fetch stats for overview
$total_accomplishments = $pdo->query("SELECT COUNT(*) FROM accomplishments $where_clause")->fetchColumn();
$ongoing_projects = $pdo->query("SELECT COUNT(*) FROM projects " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'Ongoing'" : "WHERE status = 'Ongoing'"))->fetchColumn();
$resolved_feedbacks = $pdo->query("SELECT COUNT(*) FROM feedbacks " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'Resolved'" : "WHERE status = 'Resolved'"))->fetchColumn();

// Fetch latest accomplishments
$accomplishments = $pdo->query("SELECT a.*, o.name as org_name, o.acronym FROM accomplishments a JOIN organizations o ON a.org_id = o.id " . ($selected_org_id > 0 ? "WHERE a.org_id = $selected_org_id" : "") . " ORDER BY a.date_accomplished DESC LIMIT 3")->fetchAll();

// Fetch ongoing projects
$projects = $pdo->query("SELECT p.*, o.name as org_name, o.acronym FROM projects p JOIN organizations o ON p.org_id = o.id " . ($selected_org_id > 0 ? "WHERE p.org_id = $selected_org_id AND p.status = 'Ongoing'" : "WHERE p.status = 'Ongoing'") . " ORDER BY p.target_date ASC LIMIT 4")->fetchAll();
?>

<!-- Hero Banner -->
<div class="hero-card">
  <div style="font-size: 0.9rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.08em; color: rgba(255,255,255,0.85); margin-bottom: 0.25rem;">Student Portal</div>
  <h1 class="hero-title" style="margin-top: 0; font-size: 2.1rem;">For students and organization members</h1>
  <p class="hero-desc">Explore completed activities, organization programs & schedules, overall project progress, and submit feedback or concerns directly to student leaders.</p>

  <!-- Organization Filter -->
  <form method="GET" action="index.php" style="display: flex; gap: 0.75rem; align-items: center; max-width: 450px;">
    <select name="org_id" class="form-control" onchange="this.form.submit()" style="background: white; font-weight: 600;">
      <option value="0">All Student Organizations</option>
      <?php foreach ($orgs as $org): ?>
        <option value="<?= $org['id'] ?>" <?= $selected_org_id == $org['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- Stats Quick View -->
<div class="stats-grid">
  <div class="stat-card accomplished">
    <div class="stat-icon"><i class="fa-solid fa-trophy"></i></div>
    <div>
      <div class="stat-number"><?= $total_accomplishments ?></div>
      <div class="stat-label">Accomplishments</div>
    </div>
  </div>
  <div class="stat-card todo">
    <div class="stat-icon"><i class="fa-solid fa-spinner"></i></div>
    <div>
      <div class="stat-number"><?= $ongoing_projects ?></div>
      <div class="stat-label">Ongoing Projects</div>
    </div>
  </div>
  <div class="stat-card total">
    <div class="stat-icon"><i class="fa-solid fa-comments"></i></div>
    <div>
      <div class="stat-number"><?= $resolved_feedbacks ?></div>
      <div class="stat-label">Feedbacks Acted Upon</div>
    </div>
  </div>
</div>

<!-- Main Layout Split -->
<div class="layout-split">
  <div>
    <!-- Section: Latest Accomplishments -->
    <div class="panel-header">
      <span><i class="fa-solid fa-award" style="color: var(--primary);"></i> Recent Accomplishments</span>
      <a href="accomplishments.php" style="font-size: 0.85rem;">View All <i class="fa-solid fa-chevron-right"></i></a>
    </div>

    <div class="card-grid" style="grid-template-columns: 1fr;">
      <?php if (empty($accomplishments)): ?>
        <p style="color: var(--gray-500); padding: 1rem;">No accomplishments recorded yet.</p>
      <?php else: ?>
        <?php foreach ($accomplishments as $acc): ?>
          <div class="card">
            <div class="card-header">
              <div>
                <span class="badge badge-accomplished"><?= htmlspecialchars($acc['acronym']) ?></span>
                <span class="badge badge-planning" style="margin-left: 0.25rem;"><?= htmlspecialchars($acc['badge_tag']) ?></span>
                <h3 class="card-title" style="margin-top: 0.4rem;"><?= htmlspecialchars($acc['title']) ?></h3>
              </div>
              <span style="font-size: 0.8rem; color: var(--gray-500); font-weight: 600;">
                <i class="fa-regular fa-calendar"></i> <?= date('M d, Y', strtotime($acc['date_accomplished'])) ?>
              </span>
            </div>
            <div class="card-body">
              <p><?= htmlspecialchars(mb_strimwidth($acc['description'], 0, 150, '...')) ?></p>
            </div>
            <div class="card-footer" style="background: var(--gray-100); margin: 0 -1.5rem -1.5rem -1.5rem; padding: 0.75rem 1.5rem; border-bottom-left-radius: var(--radius); border-bottom-right-radius: var(--radius); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
              <span>
                <?php if (!empty($acc['impact_summary'])): ?>
                  <i class="fa-solid fa-chart-line" style="color: var(--success);"></i> <strong>Impact:</strong> <?= htmlspecialchars($acc['impact_summary']) ?>
                <?php else: ?>
                  <i class="fa-solid fa-building-columns" style="color: var(--primary);"></i> <?= htmlspecialchars($acc['org_name']) ?>
                <?php endif; ?>
              </span>
              <button type="button" class="btn btn-secondary btn-sm" onclick="openOverviewAccModal(<?= intval($acc['id']) ?>)">
                <i class="fa-solid fa-eye"></i> View Details
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Section: Ongoing Project Progress -->
    <div class="panel-header" style="margin-top: 2rem;">
      <span><i class="fa-solid fa-bars-progress" style="color: var(--secondary);"></i> Active Project Progress</span>
      <a href="progress.php" style="font-size: 0.85rem;">View Progress Tracker <i class="fa-solid fa-chevron-right"></i></a>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <?php if (empty($projects)): ?>
        <p style="color: var(--gray-500); padding: 1rem;">No active projects currently listed.</p>
      <?php else: ?>
        <?php foreach ($projects as $proj): ?>
          <div class="card">
            <div class="card-header">
              <div>
                <span class="badge badge-in-progress"><?= htmlspecialchars($proj['acronym']) ?></span>
                <h4 style="margin-top: 0.3rem; color: var(--dark);"><?= htmlspecialchars($proj['title']) ?></h4>
              </div>
              <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary);"><?= $proj['progress_percent'] ?>% Completed</span>
            </div>
            <div class="progress-container">
              <div class="progress-bar" style="width: <?= $proj['progress_percent'] ?>%;"></div>
            </div>
            <div class="progress-info" style="margin-top: 0.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
              <div>
                <span>Target Finish: <?= $proj['target_date'] ? date('M d, Y', strtotime($proj['target_date'])) : 'TBA' ?></span>
                <span style="margin-left: 0.75rem;">Status: <?= htmlspecialchars($proj['status']) ?></span>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="openOverviewProjModal(<?= intval($proj['id']) ?>)">
                <i class="fa-solid fa-eye"></i> View Details
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar / Action panel -->
  <div>
    <!-- Submit Feedback Banner Card -->
    <div class="panel" style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-color: #bae6fd;">
      <h3 style="font-size: 1.15rem; color: #0369a1; margin-bottom: 0.5rem;"><i class="fa-solid fa-paper-plane"></i> Have a Suggestion or Concern?</h3>
      <p style="font-size: 0.9rem; color: #075985; margin-bottom: 1rem;">Voice your thoughts, ideas, or feedback to your student leaders (e.g. SSLG). Submissions are recorded under your verified student account.</p>
      <a href="feedback.php" class="btn btn-primary" style="width: 100%;"><i class="fa-solid fa-pen-to-square"></i> Submit Feedback / Suggestion</a>
    </div>

    <!-- Student Leadership Organizations List -->
    <div class="panel">
      <div class="panel-header">Active Organizations</div>
      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
        <?php foreach ($orgs as $o): 
          $logo_path = !empty($o['logo']) && file_exists(__DIR__ . '/../assets/uploads/logos/' . $o['logo']) ? '../assets/uploads/logos/' . $o['logo'] : null;
        ?>
          <div style="display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--gray-200);">
            <?php if ($logo_path): ?>
              <img src="<?= $logo_path ?>" alt="<?= htmlspecialchars($o['acronym']) ?>" style="width: 42px; height: 42px; object-fit: cover; border-radius: 50%; border: 1px solid var(--gray-300);">
            <?php else: ?>
              <div style="width: 42px; height: 42px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                <?= htmlspecialchars($o['acronym']) ?>
              </div>
            <?php endif; ?>
            <div>
              <div style="font-weight: 700; font-size: 0.9rem; color: var(--dark);"><?= htmlspecialchars($o['name']) ?></div>
              <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($o['category']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Overview Accomplishment Complete Details Modal -->
<div class="modal-backdrop" id="overviewAccModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishment Complete Details
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.65rem;">
        <span class="badge badge-accomplished" id="ov_acc_acronym"></span>
        <span class="badge badge-planning" id="ov_acc_badge"></span>
      </div>
      <h3 id="ov_acc_title" style="font-size: 1.3rem; font-weight: 800; color: var(--dark); margin-bottom: 0.35rem;"></h3>
      <div id="ov_acc_org_name" style="font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 1rem;"></div>
      <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem; margin-bottom: 1rem;">
        <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Date Accomplished</div>
        <div id="ov_acc_date" style="font-size: 0.92rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
      </div>
      <div style="margin-bottom: 1rem;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.4rem;">Full Description</div>
        <div id="ov_acc_desc" style="background: var(--cream-light); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.95rem; color: var(--gray-800); line-height: 1.65; white-space: pre-wrap;"></div>
      </div>
      <div id="ov_acc_impact_box" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-sm); padding: 0.85rem 1rem;">
        <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #047857;">Impact Summary</div>
        <div id="ov_acc_impact" style="font-size: 0.92rem; font-weight: 700; color: #065f46; margin-top: 0.2rem;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a id="ov_acc_edit_link" href="../leadership/accomplishments.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit in Leadership Portal</a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- Overview Project Progress Complete Details Modal -->
<div class="modal-backdrop" id="overviewProjModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-chart-line" style="color: var(--secondary);"></i> Project Progress Complete Details
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.65rem;">
        <span class="badge badge-planning" id="ov_proj_acronym"></span>
        <span class="badge badge-in-progress" id="ov_proj_status"></span>
      </div>
      <h3 id="ov_proj_title" style="font-size: 1.3rem; font-weight: 800; color: var(--dark); margin-bottom: 0.35rem;"></h3>
      <div id="ov_proj_org_name" style="font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 1rem;"></div>
      <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.9rem 1rem; margin-bottom: 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
          <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--gray-600);">Overall Completion</span>
          <strong id="ov_proj_pct" style="font-size: 1.05rem; color: var(--primary);"></strong>
        </div>
        <div class="progress-container" style="height: 10px; margin: 0;">
          <div class="progress-bar" id="ov_proj_bar" style="width: 0%;"></div>
        </div>
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Start Date</div>
          <div id="ov_proj_start" style="font-size: 0.9rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Target Completion</div>
          <div id="ov_proj_target" style="font-size: 0.9rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
      </div>
      <div>
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.4rem;">Full Project Description</div>
        <div id="ov_proj_desc" style="background: var(--cream-light); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.95rem; color: var(--gray-800); line-height: 1.65; white-space: pre-wrap;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a id="ov_proj_edit_link" href="../leadership/projects.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit in Leadership Portal</a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script>
const OV_ACCOMPLISHMENTS = <?= json_encode($accomplishments) ?>;
const OV_PROJECTS = <?= json_encode($projects) ?>;

function formatOvDate(dateStr) {
  if (!dateStr) return 'TBA';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
}

function openOverviewAccModal(accId) {
  const modal = document.getElementById('overviewAccModal');
  if (!modal) return;
  const acc = OV_ACCOMPLISHMENTS.find(a => parseInt(a.id, 10) === parseInt(accId, 10));
  if (!acc) return;

  document.getElementById('ov_acc_acronym').textContent = acc.acronym || '';
  document.getElementById('ov_acc_badge').textContent = acc.badge_tag || 'Completed';
  document.getElementById('ov_acc_title').textContent = acc.title || '';
  document.getElementById('ov_acc_org_name').textContent = acc.org_name || '';
  document.getElementById('ov_acc_date').textContent = formatOvDate(acc.date_accomplished);
  document.getElementById('ov_acc_desc').textContent = acc.description || 'No description provided.';

  const impBox = document.getElementById('ov_acc_impact_box');
  if (acc.impact_summary && acc.impact_summary.trim() !== '') {
    impBox.style.display = 'block';
    document.getElementById('ov_acc_impact').textContent = acc.impact_summary;
  } else {
    impBox.style.display = 'none';
  }

  const editLink = document.getElementById('ov_acc_edit_link');
  if (editLink) editLink.href = '../leadership/accomplishments.php?edit=' + encodeURIComponent(acc.id);

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function openOverviewProjModal(projId) {
  const modal = document.getElementById('overviewProjModal');
  if (!modal) return;
  const proj = OV_PROJECTS.find(p => parseInt(p.id, 10) === parseInt(projId, 10));
  if (!proj) return;

  document.getElementById('ov_proj_acronym').textContent = proj.acronym || '';
  document.getElementById('ov_proj_status').textContent = 'Status: ' + (proj.status || 'Ongoing');
  document.getElementById('ov_proj_title').textContent = proj.title || '';
  document.getElementById('ov_proj_org_name').textContent = proj.org_name || '';

  const pct = parseInt(proj.progress_percent || 0, 10);
  document.getElementById('ov_proj_pct').textContent = pct + '%';
  document.getElementById('ov_proj_bar').style.width = pct + '%';
  document.getElementById('ov_proj_start').textContent = formatOvDate(proj.start_date);
  document.getElementById('ov_proj_target').textContent = formatOvDate(proj.target_date);
  document.getElementById('ov_proj_desc').textContent = proj.description || 'No description provided.';

  const editLink = document.getElementById('ov_proj_edit_link');
  if (editLink) editLink.href = '../leadership/projects.php?edit=' + encodeURIComponent(proj.id);

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

