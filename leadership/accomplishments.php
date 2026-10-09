<?php
// leadership/accomplishments.php - Manage Accomplishments for Leadership Officers
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$org_id = $_SESSION['org_id'];
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_accomplishment') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $impact_summary = trim($_POST['impact_summary']);
        $badge_tag = trim($_POST['badge_tag']) ?: 'Completed Project';
        $date_accomplished = $_POST['date_accomplished'];

        if (empty($title) || empty($date_accomplished)) {
            $error_msg = "Title and date accomplished are required.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO accomplishments (org_id, title, description, impact_summary, badge_tag, date_accomplished) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$org_id, $title, $description, $impact_summary, $badge_tag, $date_accomplished])) {
                $success_msg = "Accomplishment posted successfully! Visible on Student Portal.";
            } else {
                $error_msg = "Failed to save accomplishment.";
            }
        }
    }

    if ($action === 'delete_accomplishment') {
        $acc_id = intval($_POST['acc_id']);
        $stmt = $pdo->prepare("DELETE FROM accomplishments WHERE id = ? AND org_id = ?");
        if ($stmt->execute([$acc_id, $org_id])) {
            $success_msg = "Accomplishment deleted.";
        }
    }
}

$accomplishments = $pdo->query("SELECT * FROM accomplishments WHERE org_id = $org_id ORDER BY date_accomplished DESC")->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Manage Organization Accomplishments</h1>
    <p class="page-subtitle">Publish major achievements and milestone accomplishments for <?= htmlspecialchars($_SESSION['org_acronym']) ?>.</p>
  </div>

  <button class="btn btn-primary" data-modal-target="createAccModal">
    <i class="fa-solid fa-plus"></i> Post Accomplishment
  </button>
</div>

<?php if (!empty($success_msg)): ?>
  <div class="alert alert-success alert-auto-dismiss">
    <span><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
  <div class="alert alert-danger alert-auto-dismiss">
    <span><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></span>
  </div>
<?php endif; ?>

<div class="card-grid">
  <?php if (empty($accomplishments)): ?>
    <div class="panel" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--gray-500);">
      No accomplishments posted yet. Click "Post Accomplishment" to share your achievements with students!
    </div>
  <?php else: ?>
    <?php foreach ($accomplishments as $acc): ?>
      <div class="card">
        <div class="card-header">
          <div>
            <span class="badge badge-accomplished"><?= htmlspecialchars($acc['badge_tag']) ?></span>
            <h3 class="card-title" style="margin-top: 0.5rem;"><?= htmlspecialchars($acc['title']) ?></h3>
          </div>
        </div>
        
        <div class="card-body">
          <p style="font-size: 0.9rem; color: var(--gray-700);"><?= nl2br(htmlspecialchars($acc['description'])) ?></p>
        </div>

        <?php if (!empty($acc['impact_summary'])): ?>
          <div style="background: var(--gray-100); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--dark); margin-bottom: 1rem;">
            <strong><i class="fa-solid fa-chart-line" style="color: var(--success);"></i> Impact:</strong> <?= htmlspecialchars($acc['impact_summary']) ?>
          </div>
        <?php endif; ?>

        <div class="card-footer">
          <span><i class="fa-regular fa-calendar"></i> Date: <strong><?= date('M d, Y', strtotime($acc['date_accomplished'])) ?></strong></span>
          <form method="POST" action="accomplishments.php" onsubmit="return confirm('Delete this accomplishment?');">
            <input type="hidden" name="action" value="delete_accomplishment">
            <input type="hidden" name="acc_id" value="<?= $acc['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
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

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
