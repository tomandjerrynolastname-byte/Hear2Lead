<?php
// leadership/feedbacks.php - Monitor & Respond to Student Suggestions / Feedbacks
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_role = $_SESSION['user_role'] ?? 'Officer';
$is_admin = ($current_role === 'Admin');
$current_org_id = intval($_SESSION['org_id']);
$filter_org = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;

$success_msg = '';
$error_msg = '';

// Handle Response Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond_feedback') {
    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    $status = $_POST['status'] ?? 'Under Review';
    $officer_response = trim($_POST['officer_response'] ?? '');

    if (empty($officer_response)) {
        $error_msg = "Please write a response before saving.";
    } else {
        if ($is_admin) {
            $stmt = $pdo->prepare("UPDATE feedbacks SET status = ?, officer_response = ?, responded_at = NOW() WHERE id = ?");
            $executed = $stmt->execute([$status, $officer_response, $feedback_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE feedbacks SET status = ?, officer_response = ?, responded_at = NOW() WHERE id = ? AND org_id = ?");
            $executed = $stmt->execute([$status, $officer_response, $feedback_id, $current_org_id]);
        }

        if ($executed) {
            $success_msg = "Response published! It is now visible on the Student Portal.";
        } else {
            $error_msg = "Failed to update feedback response.";
        }
    }
}

// Handle Delete Feedback Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_feedback') {
    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    if ($is_admin) {
        $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = ?");
        $executed = $stmt->execute([$feedback_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = ? AND org_id = ?");
        $executed = $stmt->execute([$feedback_id, $current_org_id]);
    }

    if ($executed) {
        $success_msg = "Feedback deleted successfully.";
    } else {
        $error_msg = "Failed to delete feedback.";
    }
}

// Fetch all orgs for Admin filter dropdown
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

// Filter Status
$filter_status = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

if ($filter_org > 0) {
    $where[] = "f.org_id = ?";
    $params[] = $filter_org;
}
if (!empty($filter_status)) {
    $where[] = "f.status = ?";
    $params[] = $filter_status;
}

$where_sql = implode(" AND ", $where);
$query = "SELECT f.*, o.acronym as org_acronym, o.name as org_name 
          FROM feedbacks f 
          JOIN organizations o ON f.org_id = o.id 
          WHERE $where_sql 
          ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-comments" style="color: var(--accent);"></i> Feedback and Concern Management</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Review, address, and publish responses to student feedback & concerns <?= $filter_org > 0 ? 'for selected organization' : 'across all student organizations' ?>.
      <?php else: ?>
        Review, address, and publish responses to student feedback & concerns sent to <strong><?= htmlspecialchars($_SESSION['org_acronym']) ?></strong>.
      <?php endif; ?>
    </p>
  </div>

  <a href="../student/feedback.php" target="_blank" class="btn btn-secondary">
    <i class="fa-solid fa-eye"></i> View Student Portal Feed
  </a>
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

<!-- Filter Bar -->
<div class="filter-bar">
  <form method="GET" action="feedbacks.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
    <?php if ($is_admin): ?>
      <div class="filter-group">
        <label class="form-label" style="margin: 0;">Organization:</label>
        <select name="org_id" class="form-control" onchange="this.form.submit()">
          <option value="0">All Organizations</option>
          <?php foreach ($all_orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $filter_org == $o['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="filter-group">
      <label class="form-label" style="margin: 0;">Filter Status:</label>
      <select name="status" class="form-control" onchange="this.form.submit()">
        <option value="">All Feedbacks</option>
        <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending Review</option>
        <option value="Under Review" <?= $filter_status == 'Under Review' ? 'selected' : '' ?>>Under Review</option>
        <option value="In Action" <?= $filter_status == 'In Action' ? 'selected' : '' ?>>In Action</option>
        <option value="Resolved" <?= $filter_status == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
      </select>
    </div>

    <?php if (!empty($filter_status) || ($is_admin && $filter_org > 0)): ?>
      <a href="feedbacks.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
    <?php endif; ?>
  </form>
</div>

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
  <?php if (empty($feedbacks)): ?>
    <div class="panel" style="text-align: center; padding: 3rem; color: var(--gray-500);">
      <i class="fa-solid fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; color: var(--gray-400);"></i>
      <h3>No Feedbacks Found</h3>
      <p>No student feedback submissions recorded for this filter criteria.</p>
    </div>
  <?php else: ?>
    <?php foreach ($feedbacks as $fb): 
      $fb_status_badges = [
        'Resolved' => 'badge-accomplished',
        'In Action' => 'badge-in-progress',
        'Under Review' => 'badge-todo'
      ];
      $status_badge = $fb_status_badges[$fb['status']] ?? 'badge-planning';
    ?>
      <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div>
            <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
              <span class="badge badge-in-progress"><?= htmlspecialchars($fb['category']) ?></span>
              <span class="badge <?= $status_badge ?>">Status: <?= htmlspecialchars($fb['status']) ?></span>
              <?php if ($is_admin && $filter_org === 0): ?>
                <span class="badge badge-planning"><strong><?= htmlspecialchars($fb['org_acronym']) ?></strong></span>
              <?php endif; ?>
            </div>
            <h3 style="font-size: 1.2rem; color: var(--dark); margin-top: 0.4rem;"><?= htmlspecialchars($fb['subject']) ?></h3>
          </div>

          <span style="font-size: 0.8rem; color: var(--gray-500);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y g:i A', strtotime($fb['created_at'])) ?></span>
        </div>

        <div style="background: var(--gray-100); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 0.75rem;">
          <p style="font-size: 0.95rem; color: var(--gray-800);">"<?= nl2br(htmlspecialchars($fb['message'])) ?>"</p>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
          <div style="font-size: 0.85rem; color: var(--gray-600);">
            Submitted by: <strong><?= htmlspecialchars($fb['student_name']) ?></strong> 
            <?= $fb['student_grade_section'] ? '• ' . htmlspecialchars($fb['student_grade_section']) : '' ?>
            <?= $fb['student_email'] ? '• <a href="mailto:' . htmlspecialchars($fb['student_email']) . '">' . htmlspecialchars($fb['student_email']) . '</a>' : '' ?>
          </div>

          <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button class="btn btn-primary btn-sm" onclick='openFeedbackRespondModal(<?= json_encode($fb) ?>)'>
              <i class="fa-solid fa-reply"></i> <?= !empty($fb['officer_response']) ? 'Edit Response' : 'Respond / Update Status' ?>
            </button>
            <form method="POST" action="feedbacks.php" onsubmit="return confirm('Are you sure you want to permanently delete this feedback?');" style="display: inline; margin: 0;">
              <input type="hidden" name="action" value="delete_feedback">
              <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Delete Feedback"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </div>

        <!-- Display Current Officer Response if exists -->
        <?php if (!empty($fb['officer_response'])): ?>
          <div style="margin-top: 1rem; padding: 0.85rem 1.15rem; background: var(--primary-light); border-left: 4px solid var(--primary); border-radius: var(--radius-sm);">
            <div style="font-weight: 700; color: var(--primary-hover); font-size: 0.85rem; margin-bottom: 0.25rem; display: flex; justify-content: space-between;">
              <span><i class="fa-solid fa-user-check"></i> Published Officer Response:</span>
              <span style="font-size: 0.75rem; color: var(--gray-600);"><?= date('M d, Y g:i A', strtotime($fb['responded_at'])) ?></span>
            </div>
            <p style="font-size: 0.9rem; color: var(--gray-800); font-style: italic;"><?= nl2br(htmlspecialchars($fb['officer_response'])) ?></p>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Respond Feedback Modal -->
<div class="modal-backdrop" id="respondFeedbackModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-reply" style="color: var(--primary);"></i> Respond to Feedback</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="feedbacks.php">
      <input type="hidden" name="action" value="respond_feedback">
      <input type="hidden" name="feedback_id" id="respond_feedback_id">
      
      <div class="modal-body">
        <div style="background: var(--gray-100); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.25rem;">
          <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.25rem;">Student Feedback from <span id="respond_student_name" style="font-weight: 700;"></span></div>
          <h4 id="respond_subject" style="font-size: 1rem; color: var(--dark); margin-bottom: 0.4rem;"></h4>
          <p id="respond_message" style="font-size: 0.85rem; color: var(--gray-700); font-style: italic;"></p>
        </div>

        <div class="form-group">
          <label class="form-label">Update Feedback Status</label>
          <select name="status" id="respond_status" class="form-control">
            <option value="Under Review">Under Review</option>
            <option value="In Action">In Action (Work in Progress)</option>
            <option value="Resolved">Resolved / Completed</option>
            <option value="Pending">Pending</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Official Officer Response <span style="color: red;">*</span></label>
          <textarea name="officer_response" id="respond_officer_response" class="form-control" rows="4" placeholder="Write an official update or response for the student..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish Response</button>
      </div>
    </form>
  </div>
</div>

<script>
function openFeedbackRespondModal(fb) {
  document.getElementById('respond_feedback_id').value = fb.id;
  document.getElementById('respond_student_name').textContent = fb.student_name;
  document.getElementById('respond_subject').textContent = fb.subject;
  document.getElementById('respond_message').textContent = '"' + fb.message + '"';
  document.getElementById('respond_status').value = fb.status || 'Under Review';
  document.getElementById('respond_officer_response').value = fb.officer_response || '';

  document.getElementById('respondFeedbackModal').classList.add('active');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
