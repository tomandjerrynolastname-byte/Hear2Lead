<?php
// student/accomplishments.php - Student View Accomplishments
require_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/header.php';

$selected_org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

$query = "SELECT a.*, o.name as org_name, o.acronym FROM accomplishments a JOIN organizations o ON a.org_id = o.id ";
if ($selected_org_id > 0) {
    $query .= "WHERE a.org_id = $selected_org_id ";
}
$query .= "ORDER BY a.date_accomplished DESC";

$accomplishments = $pdo->query($query)->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Organization Accomplishments</h1>
    <p class="page-subtitle">Celebrating the milestone accomplishments and successful initiatives of student leadership.</p>
  </div>

  <div>
    <form method="GET" action="accomplishments.php">
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

<?php if (empty($accomplishments)): ?>
  <div class="panel" style="text-align: center; padding: 3rem;">
    <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
    <h3>No Accomplishments Found</h3>
    <p style="color: var(--gray-600);">There are no recorded accomplishments for this selection yet.</p>
  </div>
<?php else: ?>
  <div class="card-grid">
    <?php foreach ($accomplishments as $acc): ?>
      <div class="card">
        <div class="card-header">
          <div>
            <span class="badge badge-accomplished"><?= htmlspecialchars($acc['acronym']) ?></span>
            <span class="badge badge-in-progress" style="margin-left: 0.25rem;"><?= htmlspecialchars($acc['badge_tag']) ?></span>
            <h3 class="card-title" style="margin-top: 0.5rem;"><?= htmlspecialchars($acc['title']) ?></h3>
          </div>
        </div>
        <div class="card-body">
          <p style="color: var(--gray-700); line-height: 1.6;"><?= nl2br(htmlspecialchars($acc['description'])) ?></p>
        </div>
        <div class="card-footer">
          <span><i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i> Date: <strong><?= date('M d, Y', strtotime($acc['date_accomplished'])) ?></strong></span>
          <?php if (!empty($acc['impact_summary'])): ?>
            <span class="badge badge-accomplished" style="font-size: 0.75rem;"><i class="fa-solid fa-chart-line"></i> <?= htmlspecialchars($acc['impact_summary']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
