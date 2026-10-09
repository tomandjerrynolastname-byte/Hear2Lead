<?php
// student/feedback.php - Submit & View Suggestions / Feedbacks
require_once __DIR__ . '/../config/db.php';

$success_msg = '';
$error_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $org_id = intval($_POST['org_id']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    $student_name = $is_anonymous ? 'Anonymous Student' : trim($_POST['student_name']);
    $student_email = $is_anonymous ? null : trim($_POST['student_email']);
    $student_grade = $is_anonymous ? null : trim($_POST['student_grade_section']);
    
    $category = trim($_POST['category']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($org_id) || empty($subject) || empty($message)) {
        $error_msg = "Please fill in all required fields (Organization, Subject, Message).";
    } else {
        $stmt = $pdo->prepare("INSERT INTO feedbacks (org_id, student_name, student_email, student_grade_section, category, subject, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
        if ($stmt->execute([$org_id, $student_name, $student_email, $student_grade, $category, $subject, $message])) {
            $success_msg = "Thank you! Your suggestion/feedback has been submitted successfully to the organization leaders.";
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
    <h1 class="page-title"><i class="fa-solid fa-comment-dots" style="color: var(--accent);"></i> Student Suggestions & Feedbacks</h1>
    <p class="page-subtitle">Share your ideas, concerns, or appreciation with your student government & organization officers.</p>
  </div>

  <button class="btn btn-primary" data-modal-target="submitFeedbackModal">
    <i class="fa-solid fa-plus"></i> Submit New Feedback
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

        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem;">
          Submitted by: <strong><?= htmlspecialchars($fb['student_name']) ?></strong> 
          <?= $fb['student_grade_section'] ? '(' . htmlspecialchars($fb['student_grade_section']) . ')' : '' ?>
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

        <div style="background: var(--gray-100); padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem;">
          <div class="form-group" style="margin-bottom: 0.5rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; color: var(--dark);">
              <input type="checkbox" name="is_anonymous" id="is_anonymous" onchange="toggleStudentInfo(this.checked)">
              Submit Anonymously (Hide my name & details)
            </label>
          </div>

          <div id="student_info_fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.75rem;">
            <div>
              <label class="form-label" style="font-size: 0.8rem;">Your Name</label>
              <input type="text" name="student_name" class="form-control" placeholder="e.g. Maria Santos">
            </div>
            <div>
              <label class="form-label" style="font-size: 0.8rem;">Grade & Section</label>
              <input type="text" name="student_grade_section" class="form-control" placeholder="e.g. Grade 11 - STEM">
            </div>
            <div style="grid-column: 1 / -1;">
              <label class="form-label" style="font-size: 0.8rem;">Email Address (Optional)</label>
              <input type="email" name="student_email" class="form-control" placeholder="student@school.edu.ph">
            </div>
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

<script>
function toggleStudentInfo(isAnon) {
  const fields = document.getElementById('student_info_fields');
  if (isAnon) {
    fields.style.opacity = '0.4';
    fields.style.pointerEvents = 'none';
  } else {
    fields.style.opacity = '1';
    fields.style.pointerEvents = 'auto';
  }
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
