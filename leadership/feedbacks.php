<?php
// leadership/feedbacks.php - Monitor & Respond to Student Suggestions / Feedbacks with Multi-Reply Logs
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_user_name = $_SESSION['user_name'] ?? 'Officer';
$current_role = $_SESSION['user_role'] ?? 'Officer';
$is_admin = ($current_role === 'Admin');
$current_org_id = intval($_SESSION['org_id']);
$filter_org = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;

$success_msg = '';
$error_msg = '';

// Handle Response Action (Appends a new reply log with timestamp)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond_feedback') {
    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    $status = $_POST['status'] ?? 'Under Review';
    $officer_response = trim($_POST['officer_response'] ?? '');

    if (empty($officer_response)) {
        $error_msg = "Please write a reply message before saving.";
    } else {
        if ($is_admin) {
            $stmt = $pdo->prepare("UPDATE feedbacks SET status = ?, officer_response = ?, responded_at = NOW() WHERE id = ?");
            $executed = $stmt->execute([$status, $officer_response, $feedback_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE feedbacks SET status = ?, officer_response = ?, responded_at = NOW() WHERE id = ? AND org_id = ?");
            $executed = $stmt->execute([$status, $officer_response, $feedback_id, $current_org_id]);
        }

        if ($executed) {
            $log_stmt = $pdo->prepare("INSERT INTO feedback_logs (feedback_id, user_id, officer_name, officer_role, status, reply_message) VALUES (?, ?, ?, ?, ?, ?)");
            $log_stmt->execute([$feedback_id, $current_user_id, $current_user_name, $current_role, $status, $officer_response]);

            $success_msg = "Reply logged and published! It is now visible to the student.";
        } else {
            $error_msg = "Failed to save feedback reply.";
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

// Filter Status & Calendar / Date Filter
$filter_status = trim($_GET['status'] ?? '');
$filter_date = trim($_GET['filter_date'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Fetch all dates with feedbacks (scoped by organization if not admin or if org selected) for the interactive Calendar dots
if ($filter_org > 0) {
    $stmt_dates = $pdo->prepare("SELECT DISTINCT DATE(created_at) as fb_date FROM feedbacks WHERE org_id = ?");
    $stmt_dates->execute([$filter_org]);
} else {
    $stmt_dates = $pdo->query("SELECT DISTINCT DATE(created_at) as fb_date FROM feedbacks");
}
$feedback_dates = array_values(array_filter(array_column($stmt_dates->fetchAll(), 'fb_date')));

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
if (!empty($filter_date)) {
    $where[] = "DATE(f.created_at) = ?";
    $params[] = $filter_date;
}
if (!empty($date_from)) {
    $where[] = "DATE(f.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where[] = "DATE(f.created_at) <= ?";
    $params[] = $date_to;
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

// Fetch all reply logs for displayed feedbacks
$feedback_logs_by_id = [];
if (!empty($feedbacks)) {
    $fb_ids = array_map(function($f) { return intval($f['id']); }, $feedbacks);
    $placeholders = implode(',', array_fill(0, count($fb_ids), '?'));
    $stmt_logs = $pdo->prepare("SELECT * FROM feedback_logs WHERE feedback_id IN ($placeholders) ORDER BY created_at ASC, id ASC");
    $stmt_logs->execute($fb_ids);
    foreach ($stmt_logs->fetchAll() as $log) {
        $fid = intval($log['feedback_id']);
        $log['formatted_time'] = date('M d, Y • g:i:s A', strtotime($log['created_at']));
        $feedback_logs_by_id[$fid][] = $log;
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<style>
.fb-log-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-top: 1rem;
}
.fb-log-item {
  padding: 0.85rem 1.1rem;
  background: var(--primary-light);
  border-left: 4px solid var(--primary);
  border-radius: var(--radius-sm);
}
.fb-log-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.3rem;
}
.fb-log-author {
  font-weight: 700;
  color: var(--primary-hover);
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.fb-log-time {
  font-size: 0.76rem;
  color: var(--gray-600);
  font-weight: 600;
}
.fb-log-message {
  font-size: 0.9rem;
  color: var(--gray-800);
  line-height: 1.45;
}
</style>

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

<!-- Filter Bar with Calendar & Date Filter -->
<div class="filter-bar">
  <form method="GET" action="feedbacks.php" id="leadershipFeedbackFilterForm" style="display: flex; gap: 0.85rem; align-items: center; width: 100%; flex-wrap: wrap;">
    <?php if ($is_admin): ?>
      <div class="filter-group">
        <label class="form-label" style="margin: 0;">Organization:</label>
        <select name="org_id" class="form-control" style="width: auto;" onchange="this.form.submit()">
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
      <label class="form-label" style="margin: 0;">Status:</label>
      <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
        <option value="">All Feedbacks</option>
        <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending Review</option>
        <option value="Under Review" <?= $filter_status == 'Under Review' ? 'selected' : '' ?>>Under Review</option>
        <option value="In Action" <?= $filter_status == 'In Action' ? 'selected' : '' ?>>In Action</option>
        <option value="Resolved" <?= $filter_status == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
      </select>
    </div>

    <!-- Interactive Calendar Widget + Date Range Filter -->
    <div class="filter-group">
      <div class="calendar-dropdown-wrapper" id="leadershipCalWrapper">
        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleFeedbackCalendar('leadershipCalendarPopover', event)" style="display: inline-flex; align-items: center; gap: 0.4rem;">
          <i class="fa-regular fa-calendar-days" style="color: var(--primary);"></i>
          <span><?= !empty($filter_date) ? date('M d, Y', strtotime($filter_date)) : 'Calendar' ?></span>
          <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem;"></i>
        </button>
        <div class="calendar-popover" id="leadershipCalendarPopover" onclick="event.stopPropagation()">
          <div class="cal-header">
            <button type="button" class="cal-nav-btn" onclick="changeCalMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="cal-month-title" id="leadershipCalMonthTitle"></div>
            <button type="button" class="cal-nav-btn" onclick="changeCalMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
          </div>
          <div class="cal-grid" id="leadershipCalGrid"></div>
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

    <?php if (!empty($filter_status) || ($is_admin && $filter_org > 0) || !empty($filter_date) || !empty($date_from) || !empty($date_to)): ?>
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
        'Under Review' => 'badge-todo',
        'Student Reply' => 'badge-student-reply'
      ];
      $status_badge = $fb_status_badges[$fb['status']] ?? 'badge-planning';
      $fb_logs = $feedback_logs_by_id[intval($fb['id'])] ?? [];
      $initial_limit = 1; // Show 1 log initially on cards, with Show More / Show Less when > 1
      $total_logs = count($fb_logs);
      $hidden_count = max(0, $total_logs - $initial_limit);
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

          <span style="font-size: 0.8rem; color: var(--gray-500);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y • g:i A', strtotime($fb['created_at'])) ?></span>
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

          <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openFeedbackLogsModal(<?= intval($fb['id']) ?>)">
              <i class="fa-solid fa-clock-rotate-left"></i> Reply Logs (<?= count($fb_logs) ?>)
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openFeedbackReplyModal(<?= intval($fb['id']) ?>)">
              <i class="fa-solid fa-reply"></i> <?= !empty($fb_logs) ? 'Add Another Reply' : 'Respond / Update Status' ?>
            </button>
            <form method="POST" action="feedbacks.php" onsubmit="return confirm('Are you sure you want to permanently delete this feedback?');" style="display: inline; margin: 0;">
              <input type="hidden" name="action" value="delete_feedback">
              <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Delete Feedback"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </div>

        <!-- Display All Officer & Student Reply Logs in Vertical Left-Aligned Stepper Style with Show More / Show Less -->
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
                          <i class="fa-solid fa-user-check" style="color: var(--primary);"></i>
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
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- View Feedback Reply Logs Modal -->
<div class="modal-backdrop" id="viewFeedbackLogsModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Feedback Reply History & Logs
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="background: var(--gray-100); padding: 0.85rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
        <div style="font-size: 0.78rem; color: var(--gray-500);">Feedback from <strong id="logs_fb_student"></strong></div>
        <h4 id="logs_fb_subject" style="font-size: 1rem; color: var(--dark); margin: 0.2rem 0;"></h4>
        <p id="logs_fb_message" style="font-size: 0.85rem; color: var(--gray-700); font-style: italic; margin: 0;"></p>
      </div>
      <div id="feedbackLogsModalList" style="max-height: 360px; overflow-y: auto; padding-right: 0.25rem;"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- Respond / Add Reply to Feedback Modal -->
<div class="modal-backdrop" id="respondFeedbackModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-reply" style="color: var(--primary);"></i> Add Reply / Update Feedback</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="feedbacks.php">
      <input type="hidden" name="action" value="respond_feedback">
      <input type="hidden" name="feedback_id" id="respond_feedback_id">
      
      <div class="modal-body">
        <div style="background: var(--gray-100); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
          <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.25rem;">Student Feedback from <span id="respond_student_name" style="font-weight: 700;"></span></div>
          <h4 id="respond_subject" style="font-size: 1rem; color: var(--dark); margin-bottom: 0.4rem;"></h4>
          <p id="respond_message" style="font-size: 0.85rem; color: var(--gray-700); font-style: italic;"></p>
        </div>

        <div id="respond_existing_logs_wrapper" style="display: none; margin-bottom: 1rem;">
          <label class="form-label">Previous Reply Logs</label>
          <div id="respond_existing_logs" style="max-height: 190px; overflow-y: auto; padding-right: 0.25rem;"></div>
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
          <label class="form-label">New Reply Message <span style="color: red;">*</span></label>
          <textarea name="officer_response" id="respond_officer_response" class="form-control" rows="4" placeholder="Write a new reply or update for the student (will be logged with timestamp)..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish Reply</button>
      </div>
    </form>
  </div>
</div>

<script>
const ALL_FEEDBACKS = <?= json_encode($feedbacks) ?>;
const FEEDBACK_LOGS_MAP = <?= json_encode($feedback_logs_by_id) ?>;
const LEADERSHIP_FB_DATES = <?= json_encode($feedback_dates) ?>;
const SELECTED_FB_DATE = <?= json_encode($filter_date) ?>;
let currentCalDate = SELECTED_FB_DATE ? new Date(SELECTED_FB_DATE + 'T00:00:00') : new Date();
if (isNaN(currentCalDate.getTime())) currentCalDate = new Date();

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}

function buildFeedbackLogsHtml(logs, initialLimit = 2) {
  if (!logs || logs.length === 0) {
    return '<div style="text-align: center; padding: 1.2rem; color: var(--gray-500); font-size: 0.85rem;">No replies logged for this feedback yet.</div>';
  }
  const totalLogs = logs.length;
  const hiddenCount = Math.max(0, totalLogs - initialLimit);

  const items = logs.map((log, index) => {
    const isStudentReply = (log.status === 'Student Reply' || String(log.officer_role || '').toLowerCase().includes('student'));
    let stepClass = '';
    let badgeClass = 'badge-planning';
    let iconHtml = '<i class="fa-solid fa-user-check" style="color: var(--primary);"></i>';
    let badgeLabel = log.status;

    if (isStudentReply) {
      stepClass = 'step-student';
      badgeClass = 'badge-student-reply';
      iconHtml = '<i class="fa-solid fa-user-graduate" style="color: #6d28d9;"></i>';
      badgeLabel = 'Student Reply';
    } else if (log.status === 'Resolved') {
      stepClass = 'step-success';
      badgeClass = 'badge-accomplished';
    } else if (log.status === 'In Action') {
      stepClass = 'step-info';
      badgeClass = 'badge-in-progress';
    } else if (log.status === 'Under Review') {
      stepClass = 'step-warning';
      badgeClass = 'badge-todo';
    }

    const hiddenClass = index >= initialLimit ? 'log-step-hidden' : '';
    const lastVisibleClass = (index === initialLimit - 1 && totalLogs > initialLimit) ? 'log-step-last-visible' : '';

    return `
      <div class="log-step ${stepClass} ${hiddenClass} ${lastVisibleClass}">
        <div class="log-step-node">${index + 1}</div>
        <div class="log-step-content">
          <div class="log-step-header">
            <div class="log-step-title">
              ${iconHtml}
              <span>${escapeHtml(log.officer_name)}</span>
              <span class="log-step-role">(${escapeHtml(log.officer_role)})</span>
              <span class="badge ${badgeClass}" style="font-size: 0.68rem;">${escapeHtml(badgeLabel)}</span>
            </div>
            <div class="log-step-time">
              <i class="fa-regular fa-clock"></i> ${escapeHtml(log.formatted_time || log.created_at)}
            </div>
          </div>
          <div class="log-step-body">${escapeHtml(log.reply_message)}</div>
        </div>
      </div>
    `;
  }).join('');

  const toggleBtnHtml = hiddenCount > 0 ? `
    <div class="log-stepper-toggle-wrap">
      <button type="button" class="log-stepper-toggle-btn" data-expanded="false" onclick="toggleStepperLogs(this)">
        <i class="fa-solid fa-chevron-down"></i> Show More (${hiddenCount} more ${hiddenCount === 1 ? 'log' : 'logs'})
      </button>
    </div>
  ` : '';

  return `<div class="log-stepper-container"><div class="log-stepper" data-initial-limit="${initialLimit}">${items}</div>${toggleBtnHtml}</div>`;
}

function openFeedbackLogsModal(fbId) {
  const modal = document.getElementById('viewFeedbackLogsModal');
  if (!modal) return;

  const fb = ALL_FEEDBACKS.find(f => parseInt(f.id, 10) === parseInt(fbId, 10));
  if (!fb) return;

  document.getElementById('logs_fb_student').textContent = fb.student_name;
  document.getElementById('logs_fb_subject').textContent = fb.subject;
  document.getElementById('logs_fb_message').textContent = '"' + fb.message + '"';
  const modalLogs = FEEDBACK_LOGS_MAP[fb.id] || [];
  document.getElementById('feedbackLogsModalList').innerHTML = buildFeedbackLogsHtml(modalLogs, modalLogs.length === 2 ? 1 : 2);

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function openFeedbackReplyModal(fbOrId) {
  const modal = document.getElementById('respondFeedbackModal');
  if (!modal) return;

  const fb = (typeof fbOrId === 'object' && fbOrId !== null)
    ? fbOrId
    : ALL_FEEDBACKS.find(f => parseInt(f.id, 10) === parseInt(fbOrId, 10));
  if (!fb) return;

  document.getElementById('respond_feedback_id').value = fb.id;
  document.getElementById('respond_student_name').textContent = fb.student_name;
  document.getElementById('respond_subject').textContent = fb.subject;
  document.getElementById('respond_message').textContent = '"' + fb.message + '"';
  document.getElementById('respond_status').value = fb.status === 'Pending' ? 'Under Review' : (fb.status || 'Under Review');
  document.getElementById('respond_officer_response').value = '';

  const logs = FEEDBACK_LOGS_MAP[fb.id] || [];
  const logsWrapper = document.getElementById('respond_existing_logs_wrapper');
  const logsContainer = document.getElementById('respond_existing_logs');
  if (logs.length > 0) {
    logsWrapper.style.display = 'block';
    logsContainer.innerHTML = buildFeedbackLogsHtml(logs, 1);
  } else {
    logsWrapper.style.display = 'none';
    logsContainer.innerHTML = '';
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

window.openFeedbackRespondModal = openFeedbackReplyModal;

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
  const pop = document.getElementById('leadershipCalendarPopover');
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
  const form = document.getElementById('leadershipFeedbackFilterForm');
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
  const titleEl = document.getElementById('leadershipCalMonthTitle');
  const gridEl = document.getElementById('leadershipCalGrid');
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
    const hasFb = LEADERSHIP_FB_DATES.includes(dateIso);
    const isSelected = (SELECTED_FB_DATE === dateIso);
    const classes = ['cal-day'];
    if (hasFb) classes.push('has-feedback');
    if (isSelected) classes.push('selected');

    html += `<button type="button" class="${classes.join(' ')}" onclick="selectCalendarDate('${dateIso}')" title="${hasFb ? 'Feedback submitted on ' + dateIso : dateIso}">${d}</button>`;
  }

  gridEl.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('respond')) {
    const respondId = parseInt(urlParams.get('respond'), 10);
    openFeedbackReplyModal(respondId);
  }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

