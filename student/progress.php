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
          <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 1rem;"><?= htmlspecialchars($proj['description']) ?></p>
          
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

        <div class="card-footer">
          <span><i class="fa-regular fa-calendar"></i> Start: <strong><?= $proj['start_date'] ? date('M d, Y', strtotime($proj['start_date'])) : 'TBA' ?></strong></span>
          <span><i class="fa-solid fa-flag-checkered"></i> Target: <strong><?= $proj['target_date'] ? date('M d, Y', strtotime($proj['target_date'])) : 'TBA' ?></strong></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
