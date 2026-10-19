<?php
// student/accomplishments.php - Student View Accomplishments
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/student_auth_check.php';
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
    <h1 class="page-title"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishments</h1>
    <p class="page-subtitle">Completed activities, achievements, and documentation.</p>
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
          <p style="color: var(--gray-700); line-height: 1.6;">
            <?= nl2br(htmlspecialchars(mb_strimwidth($acc['description'], 0, 160, '...'))) ?>
          </p>
        </div>
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <span><i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i> Date: <strong><?= date('M d, Y', strtotime($acc['date_accomplished'])) ?></strong></span>
            <?php if (!empty($acc['impact_summary'])): ?>
              <span class="badge badge-accomplished" style="font-size: 0.72rem; width: fit-content;"><i class="fa-solid fa-chart-line"></i> <?= htmlspecialchars($acc['impact_summary']) ?></span>
            <?php endif; ?>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="openViewAccModal(<?= intval($acc['id']) ?>)">
            <i class="fa-solid fa-eye"></i> View Details
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- View Accomplishment Complete Details Modal -->
<div class="modal-backdrop" id="viewAccModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishment Complete Details
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.65rem;">
        <span class="badge badge-accomplished" id="view_acc_org_acronym"></span>
        <span class="badge badge-in-progress" id="view_acc_badge_tag"></span>
      </div>

      <h3 id="view_acc_title" style="font-size: 1.3rem; font-weight: 800; color: var(--dark); margin-bottom: 0.35rem;"></h3>
      <div id="view_acc_org_name" style="font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 1rem;"></div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.15rem;">
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Date Accomplished</div>
          <div id="view_acc_date" style="font-size: 0.92rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Published On</div>
          <div id="view_acc_created" style="font-size: 0.92rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
      </div>

      <div style="margin-bottom: 1.15rem;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.4rem;">
          <i class="fa-solid fa-align-left" style="color: var(--primary);"></i> Full Description & Highlights
        </div>
        <div id="view_acc_description" style="background: var(--cream-light); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.95rem; color: var(--gray-800); line-height: 1.65; white-space: pre-wrap;"></div>
      </div>

      <div id="view_acc_impact_box" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-sm); padding: 0.85rem 1rem;">
        <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #047857; margin-bottom: 0.2rem;">
          <i class="fa-solid fa-chart-line"></i> Impact & Outcome Summary
        </div>
        <div id="view_acc_impact" style="font-size: 0.92rem; font-weight: 700; color: #065f46;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a id="view_acc_edit_link" href="../leadership/accomplishments.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-pen-to-square"></i> Edit in Leadership Portal
        </a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script>
const ALL_ACCOMPLISHMENTS = <?= json_encode($accomplishments) ?>;

function formatReadableDate(dateStr) {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
}

function openViewAccModal(accId) {
  const modal = document.getElementById('viewAccModal');
  if (!modal) return;

  const acc = ALL_ACCOMPLISHMENTS.find(a => parseInt(a.id, 10) === parseInt(accId, 10));
  if (!acc) return;

  document.getElementById('view_acc_org_acronym').textContent = acc.acronym || '';
  document.getElementById('view_acc_badge_tag').textContent = acc.badge_tag || 'Completed Project';
  document.getElementById('view_acc_title').textContent = acc.title || '';
  document.getElementById('view_acc_org_name').innerHTML = '<i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> ' + (acc.org_name || acc.acronym || '');
  document.getElementById('view_acc_date').innerHTML = '<i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i> ' + formatReadableDate(acc.date_accomplished);
  document.getElementById('view_acc_created').innerHTML = '<i class="fa-regular fa-clock" style="color: var(--gray-500);"></i> ' + formatReadableDate(acc.created_at);
  document.getElementById('view_acc_description').textContent = acc.description || 'No description provided.';

  const impactBox = document.getElementById('view_acc_impact_box');
  const impactEl = document.getElementById('view_acc_impact');
  if (acc.impact_summary && acc.impact_summary.trim() !== '') {
    impactBox.style.display = 'block';
    impactEl.textContent = acc.impact_summary;
  } else {
    impactBox.style.display = 'none';
    impactEl.textContent = '';
  }

  const editLink = document.getElementById('view_acc_edit_link');
  if (editLink) {
    editLink.href = '../leadership/accomplishments.php?edit=' + encodeURIComponent(acc.id);
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

