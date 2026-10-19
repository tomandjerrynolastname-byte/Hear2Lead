<?php
// leadership/accomplishments.php - Manage Accomplishments for Leadership Officers
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

    if ($action === 'create_accomplishment') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $impact_summary = trim($_POST['impact_summary'] ?? '');
        $badge_tag = trim($_POST['badge_tag'] ?? '') ?: 'Completed Project';
        $date_accomplished = $_POST['date_accomplished'] ?? '';
        $acc_org_id = $is_admin ? (!empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id) : $current_org_id;

        if (empty($title) || empty($date_accomplished)) {
            $error_msg = "Title and date accomplished are required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO accomplishments (org_id, title, description, impact_summary, badge_tag, date_accomplished) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$acc_org_id, $title, $description, $impact_summary, $badge_tag, $date_accomplished])) {
                $success_msg = "Accomplishment posted successfully! Visible on Student Portal.";
            } else {
                $error_msg = "Failed to save accomplishment.";
            }
        }
    }

    if ($action === 'update_accomplishment') {
        $acc_id = intval($_POST['acc_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $impact_summary = trim($_POST['impact_summary'] ?? '');
        $badge_tag = trim($_POST['badge_tag'] ?? '') ?: 'Completed Project';
        $date_accomplished = $_POST['date_accomplished'] ?? '';

        if ($acc_id <= 0 || empty($title) || empty($date_accomplished)) {
            $error_msg = "Title and date accomplished are required.";
        } else {
            if ($is_admin) {
                $acc_org_id = !empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id;
                $stmt = $pdo->prepare("UPDATE accomplishments SET org_id = ?, title = ?, description = ?, impact_summary = ?, badge_tag = ?, date_accomplished = ? WHERE id = ?");
                $executed = $stmt->execute([$acc_org_id, $title, $description, $impact_summary, $badge_tag, $date_accomplished, $acc_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE accomplishments SET title = ?, description = ?, impact_summary = ?, badge_tag = ?, date_accomplished = ? WHERE id = ? AND org_id = ?");
                $executed = $stmt->execute([$title, $description, $impact_summary, $badge_tag, $date_accomplished, $acc_id, $current_org_id]);
            }

            if ($executed) {
                $success_msg = "Accomplishment updated successfully!";
            } else {
                $error_msg = "Failed to update accomplishment.";
            }
        }
    }

    if ($action === 'delete_accomplishment') {
        $acc_id = intval($_POST['acc_id'] ?? 0);
        if ($is_admin) {
            $stmt = $pdo->prepare("DELETE FROM accomplishments WHERE id = ?");
            $stmt->execute([$acc_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM accomplishments WHERE id = ? AND org_id = ?");
            $stmt->execute([$acc_id, $current_org_id]);
        }
        $success_msg = "Accomplishment deleted.";
    }
}

// Fetch organizations for Admin filter
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

// Build query
$where = ["1=1"];
$params = [];
if ($filter_org > 0) {
    $where[] = "a.org_id = ?";
    $params[] = $filter_org;
}
$where_sql = implode(" AND ", $where);

$query = "SELECT a.*, o.acronym as org_acronym, o.name as org_name 
          FROM accomplishments a 
          JOIN organizations o ON a.org_id = o.id 
          WHERE $where_sql 
          ORDER BY a.date_accomplished DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$accomplishments = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishment and Document Records</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Publish major achievements, activity documentation, and milestone accomplishments <?= $filter_org > 0 ? 'for selected organization' : 'across all student organizations' ?>.
      <?php else: ?>
        Publish major achievements, activity documentation, and milestone accomplishments for <strong><?= htmlspecialchars($_SESSION['org_acronym']) ?></strong>.
      <?php endif; ?>
    </p>
  </div>

  <button class="btn btn-primary" data-modal-target="createAccModal">
    <i class="fa-solid fa-plus"></i> Post Accomplishment
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
    <form method="GET" action="accomplishments.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
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
        <a href="accomplishments.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> View All</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<div class="card-grid">
  <?php if (empty($accomplishments)): ?>
    <div class="panel" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--gray-500);">
      No accomplishments posted yet. Click "Post Accomplishment" to share achievements with students!
    </div>
  <?php else: ?>
    <?php foreach ($accomplishments as $acc): ?>
      <div class="card">
        <div class="card-header">
          <div>
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
              <span class="badge badge-accomplished"><?= htmlspecialchars($acc['badge_tag']) ?></span>
              <?php if ($is_admin && $filter_org === 0): ?>
                <span class="badge badge-planning"><strong><?= htmlspecialchars($acc['org_acronym']) ?></strong></span>
              <?php endif; ?>
            </div>
            <h3 class="card-title" style="margin-top: 0.5rem;"><?= htmlspecialchars($acc['title']) ?></h3>
          </div>
        </div>
        
        <div class="card-body">
          <p style="font-size: 0.9rem; color: var(--gray-700);">
            <?= nl2br(htmlspecialchars(mb_strimwidth($acc['description'], 0, 160, '...'))) ?>
          </p>
        </div>

        <?php if (!empty($acc['impact_summary'])): ?>
          <div style="background: var(--gray-100); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--dark); margin-bottom: 1rem;">
            <strong><i class="fa-solid fa-chart-line" style="color: var(--success);"></i> Impact:</strong> <?= htmlspecialchars($acc['impact_summary']) ?>
          </div>
        <?php endif; ?>

        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <span><i class="fa-regular fa-calendar"></i> Date: <strong><?= date('M d, Y', strtotime($acc['date_accomplished'])) ?></strong></span>
          <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openViewAccModal(<?= intval($acc['id']) ?>)" title="View Complete Details">
              <i class="fa-solid fa-eye"></i> View
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="openEditAccModal(<?= intval($acc['id']) ?>)" title="Edit Accomplishment">
              <i class="fa-solid fa-pen"></i> Edit
            </button>
            <form method="POST" action="accomplishments.php" onsubmit="return confirm('Delete this accomplishment?');" style="display: inline; margin: 0;">
              <input type="hidden" name="action" value="delete_accomplishment">
              <input type="hidden" name="acc_id" value="<?= $acc['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Delete Accomplishment"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

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
        <span class="badge badge-planning" id="view_acc_org_acronym"></span>
        <span class="badge badge-accomplished" id="view_acc_badge_tag"></span>
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
      <button type="button" class="btn btn-primary" id="view_acc_switch_edit_btn">
        <i class="fa-solid fa-pen-to-square"></i> Edit Accomplishment
      </button>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- Create Accomplishment Modal -->
<div class="modal-backdrop" id="createAccModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-award" style="color: var(--warning);"></i> Post New Accomplishment</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="accomplishments.php">
      <input type="hidden" name="action" value="create_accomplishment">
      
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
          <label class="form-label">Accomplishment Title <span style="color: red;">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Conducted Campus-wide Mental Health Summit" required>
        </div>

        <div class="form-group">
          <label class="form-label">Full Description <span style="color: red;">*</span></label>
          <textarea name="description" class="form-control" rows="4" placeholder="Detail what was accomplished, key highlights, and participants..." required></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Badge Tag</label>
            <input type="text" name="badge_tag" class="form-control" value="Completed Event" placeholder="e.g. Facility Upgrade, Campaign">
          </div>

          <div class="form-group">
            <label class="form-label">Date Accomplished <span style="color: red;">*</span></label>
            <input type="date" name="date_accomplished" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Impact Summary (Optional)</label>
          <input type="text" name="impact_summary" class="form-control" placeholder="e.g. 400+ Students Benefited, 120kg Recycled">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish Accomplishment</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Accomplishment Modal -->
<div class="modal-backdrop" id="editAccModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Accomplishment
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="accomplishments.php">
      <input type="hidden" name="action" value="update_accomplishment">
      <input type="hidden" name="acc_id" id="edit_acc_id">

      <div class="modal-body">
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <select name="org_id" id="edit_acc_org_id" class="form-control" required>
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" id="edit_acc_org_id" value="<?= $current_org_id ?>">
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label">Accomplishment Title <span style="color: red;">*</span></label>
          <input type="text" name="title" id="edit_acc_title" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Full Description <span style="color: red;">*</span></label>
          <textarea name="description" id="edit_acc_description" class="form-control" rows="4" required></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Badge Tag</label>
            <input type="text" name="badge_tag" id="edit_acc_badge_tag" class="form-control">
          </div>

          <div class="form-group">
            <label class="form-label">Date Accomplished <span style="color: red;">*</span></label>
            <input type="date" name="date_accomplished" id="edit_acc_date" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Impact Summary (Optional)</label>
          <input type="text" name="impact_summary" id="edit_acc_impact_summary" class="form-control">
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

  document.getElementById('view_acc_org_acronym').textContent = acc.org_acronym || '';
  document.getElementById('view_acc_badge_tag').textContent = acc.badge_tag || 'Completed Project';
  document.getElementById('view_acc_title').textContent = acc.title || '';
  document.getElementById('view_acc_org_name').innerHTML = '<i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> ' + (acc.org_name || acc.org_acronym || '');
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

  const switchEditBtn = document.getElementById('view_acc_switch_edit_btn');
  if (switchEditBtn) {
    switchEditBtn.onclick = function() {
      modal.classList.remove('active');
      openEditAccModal(acc.id);
    };
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function openEditAccModal(accOrId) {
  const modal = document.getElementById('editAccModal');
  if (!modal) return;

  const acc = (typeof accOrId === 'object' && accOrId !== null)
    ? accOrId
    : ALL_ACCOMPLISHMENTS.find(a => parseInt(a.id, 10) === parseInt(accOrId, 10));
  if (!acc) return;

  document.getElementById('edit_acc_id').value = acc.id;
  document.getElementById('edit_acc_title').value = acc.title || '';
  document.getElementById('edit_acc_description').value = acc.description || '';
  document.getElementById('edit_acc_badge_tag').value = acc.badge_tag || 'Completed Event';
  document.getElementById('edit_acc_date').value = acc.date_accomplished || '';
  document.getElementById('edit_acc_impact_summary').value = acc.impact_summary || '';

  const orgEl = document.getElementById('edit_acc_org_id');
  if (orgEl && acc.org_id) {
    orgEl.value = acc.org_id;
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('edit')) {
    openEditAccModal(parseInt(urlParams.get('edit'), 10));
  } else if (urlParams.get('view')) {
    openViewAccModal(parseInt(urlParams.get('view'), 10));
  }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

