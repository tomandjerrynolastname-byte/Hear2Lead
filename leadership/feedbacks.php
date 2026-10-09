<?php
// leadership/feedbacks.php - Monitor & Respond to Student Suggestions / Feedbacks
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$org_id = $_SESSION['org_id'];
$success_msg = '';
$error_msg = '';

// Handle Response Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond_feedback') {
    $feedback_id = intval($_POST['feedback_id']);
    $status = $_POST['status'];
    $officer_response = trim($_POST['officer_response']);

    if (empty($officer_response)) {
        $error_msg = "Please write a response before saving.";
    } else {
        $stmt = $pdo->prepare("UPDATE feedbacks SET status = ?, officer_response = ?, responded_at = NOW() WHERE id = ? AND org_id = ?");
        if ($stmt->execute([$status, $officer_response, $feedback_id, $org_id])) {
            $success_msg = "Response published! It is now visible on the Student Portal.";
        } else {
            $error_msg = "Failed to update feedback response.";
        }
    }
}

// Filter Status
$filter_status = $_GET['status'] ?? '';

$query = "SELECT * FROM feedbacks WHERE org_id = $org_id ";
if (!empty($filter_status)) {
    $query .= "AND status = " . $pdo->quote($filter_status) . " ";
}
$query .= "ORDER BY created_at DESC";

$feedbacks = $pdo->query($query)->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-comments" style="color: var(--accent);"></i> Student Suggestions & Feedbacks Monitor</h1>
    <p class="page-subtitle">Review, address, and publish responses to feedback sent by students to <?= htmlspecialchars($_SESSION['org_acronym']) ?>.</p>
  </div>

  <a href="../student/feedback.php" target="_blank" class="btn btn-secondary">
    <i class="fa-solid fa-eye"></i> View Student Portal Feed
  </a>
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

<!-- Filter Bar -->
<div class="filter-bar">
  <form method="GET" action="feedbacks.php" style="display: flex; gap: 1rem; align-items: center;">
    <label class="form-label" style="margin: 0;">Filter Status:</label>
    <select name="status" class="form-control" onchange="this.form.submit()" style="max-width: 220px;">
      <option value="">All Feedbacks</option>
      <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending Review</option>
      <option value="Under Review" <?= $filter_status == 'Under Review' ? 'selected' : '' ?>>Under Review</option>
      <option value="In Action" <?= $filter_status == 'In Action' ? 'selected' : '' ?>>In Action</option>
      <option value="Resolved" <?= $filter_status == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
    </select>
  </form>
</div>

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
  <?php if (empty($feedbacks)): ?>
    <div class="panel" style="text-align: center; padding: 3rem; color: var(--gray-500);">
      <i class="fa-solid fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; color: var(--gray-400);"></i>
      <h3>No Feedbacks Found</h3>
      <p>No student feedback submissions recorded for this status filter.</p>
    </div>
  <?php else: ?>
    <?php foreach ($feedbacks as $fb): 
      $status_badge = match($fb['status']) {
        'Resolved' => 'badge-accomplished',
        'In Action' => 'badge-in-progress',
        'Under Review' => 'badge-todo',
        default => 'badge-planning'
      };
    ?>
      <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
          <div>
            <span class="badge badge-in-progress"><?= htmlspecialchars($fb['category']) ?></span>
            <span class="badge <?= $status_badge ?>" style="margin-left: 0.25rem;">Status: <?= htmlspecialchars($fb['status']) ?></span>
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

          <button class="btn btn-primary btn-sm" onclick='openFeedbackRespondModal(<?= json_encode($fb) ?>)'>
            <i class="fa-solid fa-reply"></i> <?= !empty($fb['officer_response']) ? 'Edit Response' : 'Respond / Update Status' ?>
          </button>
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

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
