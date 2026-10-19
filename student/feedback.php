<?php
// student/feedback.php - Submit & View Suggestions / Feedbacks
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/student_auth_check.php';

$is_student = isset($_SESSION['student_id']);
$is_officer = isset($_SESSION['user_id']);

$logged_student_id = $is_student ? intval($_SESSION['student_id']) : null;

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
        $stmt = $pdo->prepare("INSERT INTO feedbacks (org_id, student_id, student_name, student_email, student_grade_section, category, subject, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        if ($stmt->execute([$org_id, $logged_student_id, $student_name, $student_email, $student_grade, $category, $subject, $message])) {
            $success_msg = "Thank you! Your feedback has been submitted to the organization leaders.";
        } else {
            $error_msg = "Failed to submit feedback. Please try again.";
        }
    }
}

// Handle Student Reply on Organization Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'student_reply') {
    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    $reply_message = trim($_POST['reply_message'] ?? '');

    if ($feedback_id <= 0 || empty($reply_message)) {
        $error_msg = "Please write a reply message before sending.";
    } else {
        // Verify this feedback belongs to the logged-in student
        if ($is_student) {
            $check_stmt = $pdo->prepare("SELECT id, status FROM feedbacks WHERE id = ? AND (student_id = ? OR (student_id IS NULL AND student_name = ?))");
            $check_stmt->execute([$feedback_id, $logged_student_id, $submitter_name]);
        } else {
            $check_stmt = $pdo->prepare("SELECT id, status FROM feedbacks WHERE id = ? AND student_name = ?");
            $check_stmt->execute([$feedback_id, $submitter_name]);
        }
        $owned_fb = $check_stmt->fetch();

        if (!$owned_fb) {
            $error_msg = "Unauthorized: You can only reply to your own feedback submissions.";
        } else {
            $student_role_tag = !empty($submitter_grade) ? ('Student • ' . $submitter_grade) : 'Student';
            $ins_reply = $pdo->prepare("INSERT INTO feedback_logs (feedback_id, user_id, officer_name, officer_role, status, reply_message) VALUES (?, ?, ?, ?, 'Student Reply', ?)");
            if ($ins_reply->execute([$feedback_id, $logged_student_id, $submitter_name, $student_role_tag, $reply_message])) {
                $success_msg = "Your reply has been sent to the organization and added to the conversation log!";
            } else {
                $error_msg = "Failed to send reply. Please try again.";
            }
        }
    }
}

include_once __DIR__ . '/../includes/header.php';

// Fetch organizations for form & filter
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

// Filter parameters (Organization, Status, Calendar Date / Date Range)
$filter_org = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
$filter_status = trim($_GET['status'] ?? '');
$filter_date = trim($_GET['filter_date'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Fetch all dates of the logged-in student's feedbacks for the interactive Calendar dots
if ($is_student) {
    $stmt_dates = $pdo->prepare("SELECT DISTINCT DATE(created_at) as fb_date FROM feedbacks WHERE student_id = ? OR (student_id IS NULL AND student_name = ?)");
    $stmt_dates->execute([$logged_student_id, $submitter_name]);
} else {
    $stmt_dates = $pdo->prepare("SELECT DISTINCT DATE(created_at) as fb_date FROM feedbacks WHERE student_name = ?");
    $stmt_dates->execute([$submitter_name]);
}
$feedback_dates = array_values(array_filter(array_column($stmt_dates->fetchAll(), 'fb_date')));

// Build query for ONLY the logged-in student's own feedbacks
$where_clauses = [];
$params = [];

if ($is_student) {
    $where_clauses[] = "(f.student_id = ? OR (f.student_id IS NULL AND f.student_name = ?))";
    $params[] = $logged_student_id;
    $params[] = $submitter_name;
} else {
    $where_clauses[] = "f.student_name = ?";
    $params[] = $submitter_name;
}

if ($filter_org > 0) {
    $where_clauses[] = "f.org_id = ?";
    $params[] = $filter_org;
}
if (!empty($filter_status)) {
    $where_clauses[] = "f.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_date)) {
    $where_clauses[] = "DATE(f.created_at) = ?";
    $params[] = $filter_date;
}
if (!empty($date_from)) {
    $where_clauses[] = "DATE(f.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where_clauses[] = "DATE(f.created_at) <= ?";
    $params[] = $date_to;
}

$where_sql = implode(" AND ", $where_clauses);
$stmt_fb = $pdo->prepare("SELECT f.*, o.name as org_name, o.acronym 
                          FROM feedbacks f 
                          JOIN organizations o ON f.org_id = o.id 
                          WHERE $where_sql
                          ORDER BY f.created_at DESC");
$stmt_fb->execute($params);
$my_feedbacks = $stmt_fb->fetchAll();

// Fetch all reply logs for the student's feedbacks
$feedback_logs_by_id = [];
if (!empty($my_feedbacks)) {
    $fb_ids = array_map(function($f) { return intval($f['id']); }, $my_feedbacks);
    $placeholders = implode(',', array_fill(0, count($fb_ids), '?'));
    $stmt_logs = $pdo->prepare("SELECT * FROM feedback_logs WHERE feedback_id IN ($placeholders) ORDER BY created_at ASC, id ASC");
    $stmt_logs->execute($fb_ids);
    foreach ($stmt_logs->fetchAll() as $log) {
        $fid = intval($log['feedback_id']);
        $log['formatted_time'] = date('M d, Y • g:i:s A', strtotime($log['created_at']));
        $feedback_logs_by_id[$fid][] = $log;
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-regular fa-comment-dots" style="color: var(--accent);"></i> My Feedback, Suggestions, and Concerns</h1>
    <p class="page-subtitle">Submit feedback to student organizations, filter by calendar date, and reply directly to organization responses.</p>
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

<!-- Filter Bar with Calendar & Date Filter -->
<div class="filter-bar">
  <form method="GET" action="feedback.php" id="studentFeedbackFilterForm" style="display: flex; gap: 0.85rem; align-items: center; width: 100%; flex-wrap: wrap;">
    <div class="filter-group">
      <label class="form-label" style="margin: 0;">Organization:</label>
      <select name="org_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
        <option value="0">All Organizations</option>
        <?php foreach ($orgs as $o): ?>
          <option value="<?= $o['id'] ?>" <?= $filter_org == $o['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($o['acronym']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-group">
      <label class="form-label" style="margin: 0;">Status:</label>
      <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="Pending" <?= $filter_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
        <option value="Under Review" <?= $filter_status === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
        <option value="In Action" <?= $filter_status === 'In Action' ? 'selected' : '' ?>>In Action</option>
        <option value="Resolved" <?= $filter_status === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
      </select>
    </div>

    <!-- Interactive Calendar Widget + Date Inputs -->
    <div class="filter-group">
      <div class="calendar-dropdown-wrapper" id="studentCalWrapper">
        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleFeedbackCalendar('studentCalendarPopover', event)" style="display: inline-flex; align-items: center; gap: 0.4rem;">
          <i class="fa-regular fa-calendar-days" style="color: var(--primary);"></i>
          <span><?= !empty($filter_date) ? date('M d, Y', strtotime($filter_date)) : 'Calendar' ?></span>
          <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem;"></i>
        </button>
        <div class="calendar-popover" id="studentCalendarPopover" onclick="event.stopPropagation()">
          <div class="cal-header">
            <button type="button" class="cal-nav-btn" onclick="changeCalMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="cal-month-title" id="studentCalMonthTitle"></div>
            <button type="button" class="cal-nav-btn" onclick="changeCalMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
          </div>
          <div class="cal-grid" id="studentCalGrid"></div>
          <div style="margin-top: 0.65rem; padding-top: 0.5rem; border-top: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; font-size: 0.72rem; color: var(--gray-500);">
            <span><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--primary);margin-right:4px;"></span>Has Feedback</span>
            <button type="button" class="btn btn-secondary btn-sm" style="padding: 0.15rem 0.5rem; font-size: 0.72rem;" onclick="selectCalendarDate('')">All Dates</button>
          </div>
        </div>
      </div>
      <input type="hidden" name="filter_date" id="filter_date_input" value="<?= htmlspecialchars($filter_date) ?>">
    </div>

    <div class="filter-group">
      <label class="form-label" style="margin: 0; font-size: 0.82rem;"><i class="fa-regular fa-calendar"></i> From:</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control" style="width: auto; padding: 0.45rem 0.65rem;" onchange="document.getElementById('filter_date_input').value=''; this.form.submit();">
      <label class="form-label" style="margin: 0; font-size: 0.82rem;">To:</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control" style="width: auto; padding: 0.45rem 0.65rem;" onchange="document.getElementById('filter_date_input').value=''; this.form.submit();">
    </div>

    <?php if ($filter_org > 0 || !empty($filter_status) || !empty($filter_date) || !empty($date_from) || !empty($date_to)): ?>
      <a href="feedback.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
    <?php endif; ?>
  </form>
</div>

<!-- Submitted Feedbacks & Responses Feed (Strictly Logged-In Student's Own Feedbacks) -->
<div class="panel-header">
  <span><i class="fa-solid fa-comments"></i> My Submitted Feedbacks & Conversation Logs</span>
  <span style="font-size: 0.8rem; color: var(--gray-500);"><i class="fa-solid fa-lock"></i> Private to your account (<?= htmlspecialchars($submitter_name) ?>)</span>
</div>

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
  <?php if (empty($my_feedbacks)): ?>
    <div class="panel" style="text-align: center; padding: 2.5rem; color: var(--gray-500);">
      No feedback submissions found matching your current filter criteria.
    </div>
  <?php else: ?>
    <?php foreach ($my_feedbacks as $fb): 
      $fb_status_badges = [
        'Resolved' => 'badge-accomplished',
        'In Action' => 'badge-in-progress',
        'Under Review' => 'badge-todo',
        'Student Reply' => 'badge-student-reply'
      ];
      $status_badge = $fb_status_badges[$fb['status']] ?? 'badge-planning';
      $fb_logs = $feedback_logs_by_id[intval($fb['id'])] ?? [];
      $initial_limit = 1; // Show 1 log initially, with Show More / Show Less when > 1
      $total_logs = count($fb_logs);
      $hidden_count = max(0, $total_logs - $initial_limit);
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
            <i class="fa-regular fa-clock"></i> <?= date('M d, Y • g:i A', strtotime($fb['created_at'])) ?>
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

          <?php if (!empty($fb_logs)): ?>
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleStudentReplyBox(<?= intval($fb['id']) ?>)">
              <i class="fa-solid fa-reply" style="color: var(--primary);"></i> Reply to <?= htmlspecialchars($fb['acronym']) ?>
            </button>
          <?php endif; ?>
        </div>

        <!-- Officer & Student Reply Logs Timeline (Vertical Left-Aligned Stepper Style with Show More / Show Less) -->
        <?php if (!empty($fb_logs)): ?>
          <div class="log-stepper-container" style="margin-top: 1.1rem; padding-top: 0.9rem; border-top: 1px dashed var(--gray-300);">
            <div style="font-size: 0.78rem; font-weight: 700; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.65rem;">
              <i class="fa-solid fa-diagram-next" style="color: var(--primary);"></i> Conversation & Reply Logs (<?= $total_logs ?>)
            </div>
            <div class="log-stepper" data-initial-limit="<?= $initial_limit ?>">
              <?php foreach ($fb_logs as $idx => $log): 
                $is_student_reply = ($log['status'] === 'Student Reply' || stripos($log['officer_role'], 'Student') !== false);
                $log_status_badge = $is_student_reply ? 'badge-student-reply' : ($fb_status_badges[$log['status']] ?? 'badge-planning');
                $step_color_class = $is_student_reply
                  ? 'step-student'
                  : (($log['status'] === 'Resolved') ? 'step-success' : (($log['status'] === 'In Action') ? 'step-info' : (($log['status'] === 'Under Review') ? 'step-warning' : '')));
                $hidden_class = ($idx >= $initial_limit) ? 'log-step-hidden' : '';
                $last_visible_class = ($idx === $initial_limit - 1 && $total_logs > $initial_limit) ? 'log-step-last-visible' : '';
              ?>
                <div class="log-step <?= $step_color_class ?> <?= $hidden_class ?> <?= $last_visible_class ?>">
                  <div class="log-step-node" title="Step <?= $idx + 1 ?>">
                    <?= $idx + 1 ?>
                  </div>
                  <div class="log-step-content">
                    <div class="log-step-header">
                      <div class="log-step-title">
                        <?php if ($is_student_reply): ?>
                          <i class="fa-solid fa-user-graduate" style="color: #6d28d9;"></i>
                        <?php else: ?>
                          <i class="fa-solid fa-user-shield" style="color: var(--primary);"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($log['officer_name']) ?></span>
                        <span class="log-step-role">(<?= htmlspecialchars($log['officer_role']) ?>)</span>
                        <span class="badge <?= $log_status_badge ?>" style="font-size: 0.68rem;">
                          <?= htmlspecialchars($is_student_reply ? 'Student Reply' : $log['status']) ?>
                        </span>
                      </div>
                      <div class="log-step-time">
                        <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($log['formatted_time']) ?>
                      </div>
                    </div>
                    <div class="log-step-body"><?= nl2br(htmlspecialchars($log['reply_message'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if ($hidden_count > 0): ?>
              <div class="log-stepper-toggle-wrap">
                <button type="button" class="log-stepper-toggle-btn" data-expanded="false" onclick="toggleStepperLogs(this)">
                  <i class="fa-solid fa-chevron-down"></i> Show More (<?= $hidden_count ?> more <?= $hidden_count === 1 ? 'log' : 'logs' ?>)
                </button>
              </div>
            <?php endif; ?>

            <!-- Collapsible Student Reply Form to reply to the organization's response -->
            <div id="studentReplyBox_<?= intval($fb['id']) ?>" style="display: none; margin-top: 0.9rem; background: #f8fafc; border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.9rem;">
              <form method="POST" action="feedback.php">
                <input type="hidden" name="action" value="student_reply">
                <input type="hidden" name="feedback_id" value="<?= intval($fb['id']) ?>">
                <label class="form-label" style="font-size: 0.82rem; margin-bottom: 0.35rem;">
                  <i class="fa-solid fa-reply" style="color: #6d28d9;"></i> Write Your Reply to <?= htmlspecialchars($fb['acronym']) ?> Leaders
                </label>
                <textarea name="reply_message" class="form-control" rows="2" placeholder="Write a follow-up message or reply to the organization's response..." required style="min-height: 68px; margin-bottom: 0.6rem;"></textarea>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                  <button type="button" class="btn btn-secondary btn-sm" onclick="toggleStudentReplyBox(<?= intval($fb['id']) ?>)">Cancel</button>
                  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Send Reply</button>
                </div>
              </form>
            </div>
          </div>
        <?php else: ?>
          <div style="margin-top: 0.75rem; font-size: 0.82rem; color: var(--gray-500); font-style: italic;">
            <i class="fa-regular fa-hourglass-half"></i> Awaiting response from <?= htmlspecialchars($fb['acronym']) ?> leaders.
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

<script>
const STUDENT_FB_DATES = <?= json_encode($feedback_dates) ?>;
const SELECTED_FB_DATE = <?= json_encode($filter_date) ?>;
let currentCalDate = SELECTED_FB_DATE ? new Date(SELECTED_FB_DATE + 'T00:00:00') : new Date();
if (isNaN(currentCalDate.getTime())) currentCalDate = new Date();

function toggleStudentReplyBox(fbId) {
  const box = document.getElementById('studentReplyBox_' + fbId);
  if (!box) return;
  const isHidden = box.style.display === 'none' || box.style.display === '';
  box.style.display = isHidden ? 'block' : 'none';
  if (isHidden) {
    const textarea = box.querySelector('textarea');
    if (textarea) textarea.focus();
  }
}

function toggleFeedbackCalendar(popoverId, event) {
  event.stopPropagation();
  const pop = document.getElementById(popoverId);
  if (!pop) return;
  pop.classList.toggle('active');
  if (pop.classList.contains('active')) {
    renderMiniCalendar();
  }
}

document.addEventListener('click', (e) => {
  const pop = document.getElementById('studentCalendarPopover');
  if (pop && !pop.contains(e.target)) {
    pop.classList.remove('active');
  }
});

function changeCalMonth(delta) {
  currentCalDate.setMonth(currentCalDate.getMonth() + delta);
  renderMiniCalendar();
}

function selectCalendarDate(dateStr) {
  const input = document.getElementById('filter_date_input');
  const form = document.getElementById('studentFeedbackFilterForm');
  if (input && form) {
    input.value = dateStr;
    const fromIn = form.querySelector('input[name="date_from"]');
    const toIn = form.querySelector('input[name="date_to"]');
    if (fromIn) fromIn.value = '';
    if (toIn) toIn.value = '';
    form.submit();
  }
}

function renderMiniCalendar() {
  const titleEl = document.getElementById('studentCalMonthTitle');
  const gridEl = document.getElementById('studentCalGrid');
  if (!titleEl || !gridEl) return;

  const year = currentCalDate.getFullYear();
  const month = currentCalDate.getMonth();
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  titleEl.textContent = `${monthNames[month]} ${year}`;

  const weekdays = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  let html = weekdays.map(d => `<div class="cal-weekday">${d}</div>`).join('');

  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  for (let i = 0; i < firstDay; i++) {
    html += `<div class="cal-day empty"></div>`;
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const mm = String(month + 1).padStart(2, '0');
    const dd = String(d).padStart(2, '0');
    const dateIso = `${year}-${mm}-${dd}`;
    const hasFb = STUDENT_FB_DATES.includes(dateIso);
    const isSelected = (SELECTED_FB_DATE === dateIso);
    const classes = ['cal-day'];
    if (hasFb) classes.push('has-feedback');
    if (isSelected) classes.push('selected');

    html += `<button type="button" class="${classes.join(' ')}" onclick="selectCalendarDate('${dateIso}')" title="${hasFb ? 'Feedback submitted on ' + dateIso : dateIso}">${d}</button>`;
  }

  gridEl.innerHTML = html;
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

