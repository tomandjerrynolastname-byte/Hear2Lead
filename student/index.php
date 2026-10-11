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
              <p><?= htmlspecialchars($acc['description']) ?></p>
            </div>
            <?php if (!empty($acc['impact_summary'])): ?>
              <div class="card-footer" style="background: var(--gray-100); margin: 0 -1.5rem -1.5rem -1.5rem; padding: 0.75rem 1.5rem; border-bottom-left-radius: var(--radius); border-bottom-right-radius: var(--radius);">
                <span><i class="fa-solid fa-chart-line" style="color: var(--success);"></i> <strong>Impact:</strong> <?= htmlspecialchars($acc['impact_summary']) ?></span>
              </div>
            <?php endif; ?>
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
            <div class="progress-info" style="margin-top: 0.5rem;">
              <span>Target Finish: <?= date('M d, Y', strtotime($proj['target_date'])) ?></span>
              <span>Status: <?= $proj['status'] ?></span>
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

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
