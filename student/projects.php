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
      </tr>
    </thead>
    <tbody>
      <?php if (empty($projects)): ?>
        <tr>
          <td colspan="6" style="text-align: center; color: var(--gray-500); padding: 2rem;">No projects matching the selected criteria.</td>
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
            <td style="max-width: 320px; font-size: 0.85rem; color: var(--gray-600);"><?= htmlspecialchars($proj['description']) ?></td>
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
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
