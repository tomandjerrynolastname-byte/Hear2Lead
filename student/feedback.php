<?php
// student/feedback.php - Submit & View Suggestions / Feedbacks
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/student_auth_check.php';

$is_student = isset($_SESSION['student_id']);
$is_officer = isset($_SESSION['user_id']);

if ($is_student) {
    $submitter_name = $_SESSION['student_name'];
    $submitter_grade = $_SESSION['student_grade_section'];
    $submitter_email = $_SESSION['student_email'] ?? '';
    $submitter_role_label = 'Student (' . $submitter_grade . ')';
} elseif ($is_officer) {
    $submitter_name = $_SESSION['user_name'];
    $submitter_grade = $_SESSION['user_role'] . ' • ' . $_SESSION['org_acronym'];
    $submitter_email = $_SESSION['user_email'] ?? '';
    $submitter_role_label = 'Officer (' . $_SESSION['user_role'] . ' • ' . $_SESSION['org_acronym'] . ')';
} else {
    $submitter_name = 'Student';
    $submitter_grade = '';
    $submitter_email = '';
    $submitter_role_label = 'Student';
}

$success_msg = '';
$error_msg = '';

// Handle Officer Delete Feedback from Student Feed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_feedback' && $is_officer) {
    $feedback_id = intval($_POST['feedback_id']);
    $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = ?");
    if ($stmt->execute([$feedback_id])) {
        $success_msg = "Feedback removed successfully.";
    } else {
        $error_msg = "Failed to remove feedback.";
    }
}

// Handle Form Submission (Verified Non-Anonymous)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $org_id = intval($_POST['org_id']);
    
    // Attributed strictly to the verified logged-in account (no anonymity)
    $student_name = $submitter_name;
    $student_grade = $submitter_grade;
    $student_email = !empty($_POST['student_email']) ? trim($_POST['student_email']) : ($submitter_email ?: null);
    
    $category = trim($_POST['category']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($org_id) || empty($subject) || empty($message)) {
        $error_msg = "Please fill in all required fields (Organization, Subject, Message).";
    } else {
        $stmt = $pdo->prepare("INSERT INTO feedbacks (org_id, student_name, student_email, student_grade_section, category, subject, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
        if ($stmt->execute([$org_id, $student_name, $student_email, $student_grade, $category, $subject, $message])) {
            $success_msg = "Thank you! Your feedback has been submitted with your verified name to the organization leaders.";
        } else {
            $error_msg = "Failed to submit feedback. Please try again.";
        }
    }
}

include_once __DIR__ . '/../includes/header.php';

// Fetch organizations for form
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

// Fetch public feedbacks with responses
$public_feedbacks = $pdo->query("SELECT f.*, o.name as org_name, o.acronym FROM feedbacks f JOIN organizations o ON f.org_id = o.id ORDER BY f.created_at DESC")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-regular fa-comment-dots" style="color: var(--accent);"></i> Feedback, Suggestions, and Concerns</h1>
    <p class="page-subtitle">Submit feedback and monitor its status or response.</p>
  </div>

  <button class="btn btn-primary" data-modal-target="submitFeedbackModal">
    <i class="fa-solid fa-plus"></i> Submit New Feedback
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

<!-- Submitted Feedbacks & Responses Feed -->
<div class="panel-header">
  <span><i class="fa-solid fa-comments"></i> Recent Feedback & Leadership Actions</span>
</div>

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
  <?php if (empty($public_feedbacks)): ?>
    <div class="panel" style="text-align: center; padding: 2.5rem; color: var(--gray-500);">
      No feedback submissions recorded yet. Be the first to submit a suggestion!
    </div>
  <?php else: ?>
    <?php foreach ($public_feedbacks as $fb): 
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
            <span class="badge badge-planning"><?= htmlspecialchars($fb['acronym']) ?></span>
            <span class="badge badge-in-progress" style="margin-left: 0.25rem;"><?= htmlspecialchars($fb['category']) ?></span>
            <span class="badge <?= $status_badge ?>" style="margin-left: 0.25rem;">Status: <?= htmlspecialchars($fb['status']) ?></span>
            <h3 style="font-size: 1.15rem; color: var(--dark); margin-top: 0.4rem;"><?= htmlspecialchars($fb['subject']) ?></h3>
          </div>
          <span style="font-size: 0.8rem; color: var(--gray-500); font-weight: 500;">
            <i class="fa-regular fa-clock"></i> <?= date('M d, Y', strtotime($fb['created_at'])) ?>
          </span>
        </div>

        <p style="font-size: 0.95rem; color: var(--gray-700); margin-bottom: 0.75rem; background: var(--gray-100); padding: 0.85rem; border-radius: var(--radius-sm);">
          "<?= nl2br(htmlspecialchars($fb['message'])) ?>"
        </p>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
          <div style="font-size: 0.8rem; color: var(--gray-600);">
            <i class="fa-solid fa-user-check" style="color: var(--primary);"></i> Submitted by: <strong><?= htmlspecialchars($fb['student_name']) ?></strong> 
            <?= $fb['student_grade_section'] ? '(' . htmlspecialchars($fb['student_grade_section']) . ')' : '' ?>
          </div>

          <?php if ($is_officer): ?>
            <!-- Officer moderation control directly on student feed -->
            <form method="POST" action="feedback.php" onsubmit="return confirm('Delete this feedback submission?');" style="margin: 0;">
              <input type="hidden" name="action" value="delete_feedback">
              <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.2rem 0.6rem; font-size: 0.75rem;" title="Delete this feedback as Officer">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Officer Response Box -->
        <?php if (!empty($fb['officer_response'])): ?>
          <div style="margin-top: 1rem; padding: 0.85rem 1.15rem; background: var(--primary-light); border-left: 4px solid var(--primary); border-radius: var(--radius-sm);">
            <div style="font-weight: 700; color: var(--primary-hover); font-size: 0.85rem; margin-bottom: 0.25rem; display: flex; justify-content: space-between;">
              <span><i class="fa-solid fa-reply"></i> Response from <?= htmlspecialchars($fb['acronym']) ?> Leaders:</span>
              <span style="font-weight: 500; font-size: 0.75rem; color: var(--gray-600);"><?= date('M d, Y g:i A', strtotime($fb['responded_at'])) ?></span>
            </div>
            <p style="font-size: 0.9rem; color: var(--gray-800); font-style: italic;"><?= nl2br(htmlspecialchars($fb['officer_response'])) ?></p>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Submit Feedback Modal -->
<div class="modal-backdrop" id="submitFeedbackModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-paper-plane" style="color: var(--primary);"></i> Submit Suggestion / Feedback</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    
    <form method="POST" action="feedback.php">
      <input type="hidden" name="action" value="submit_feedback">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Target Organization <span style="color: red;">*</span></label>
          <select name="org_id" class="form-control" required>
            <option value="">-- Select Organization --</option>
            <?php foreach ($orgs as $org): ?>
              <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Feedback Category</label>
          <select name="category" class="form-control">
            <option value="Suggestion">Suggestion / Idea</option>
            <option value="Complaint">Complaint / Issue</option>
            <option value="Inquiry">General Inquiry</option>
            <option value="Appreciation">Appreciation / Commendation</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Subject Title <span style="color: red;">*</span></label>
          <input type="text" name="subject" class="form-control" placeholder="Brief title of your feedback" required>
        </div>

        <div class="form-group">
          <label class="form-label">Detailed Message <span style="color: red;">*</span></label>
          <textarea name="message" class="form-control" rows="4" placeholder="Explain your feedback or suggestion clearly..." required></textarea>
        </div>

        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 0.85rem 1rem; margin-top: 1rem;">
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <i class="fa-solid fa-circle-check" style="color: #16a34a; font-size: 1rem;"></i>
            <strong style="color: #166534; font-size: 0.9rem;">Verified Submission Identity</strong>
          </div>
          <p style="font-size: 0.8rem; color: #15803d; margin: 0 0 0.5rem 0;">
            Anonymous feedback is disabled. Your verified identity will be recorded with this feedback:
          </p>
          <div style="background: #ffffff; border: 1px solid #dcfce7; border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.85rem; color: var(--dark); display: flex; flex-direction: column; gap: 0.2rem;">
            <div><strong>Posting Name:</strong> <?= htmlspecialchars($submitter_name) ?></div>
            <div><strong>Identity:</strong> <?= htmlspecialchars($submitter_role_label) ?></div>
            <?php if (!empty($submitter_email)): ?>
              <div><strong>Email:</strong> <?= htmlspecialchars($submitter_email) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Feedback</button>
      </div>
    </form>
  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
