<?php
// includes/header.php - Enhanced Modern Header with User Profile Chip
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Anti-cache headers to prevent browser caching of authenticated portal pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_page = basename($_SERVER['PHP_SELF']);
$is_leadership = strpos($_SERVER['REQUEST_URI'], '/leadership/') !== false;

$is_officer_logged_in = isset($_SESSION['user_id']);
$is_student_logged_in = isset($_SESSION['student_id']);

$user_name = $_SESSION['user_name'] ?? 'Officer';
$user_role = $_SESSION['user_role'] ?? 'Leader';
$org_acronym = $_SESSION['org_acronym'] ?? 'SSLG';

$header_role_perms = function_exists('getRolePermissions')
    ? getRolePermissions($user_role, $pdo ?? null)
    : ($_SESSION['role_perms'] ?? [
        'is_admin' => ($user_role === 'Admin'),
        'is_pres_or_vp' => in_array($user_role, ['President', 'Vice President'], true),
        'can_manage_users' => in_array($user_role, ['Admin', 'President', 'Vice President'], true),
        'can_manage_tasks' => in_array($user_role, ['Admin', 'President', 'Vice President'], true),
        'can_manage_projects' => true,
        'can_manage_accomplishments' => true,
        'can_manage_feedbacks' => true,
        'can_view_reports' => true,
        'can_manage_orgs' => ($user_role === 'Admin'),
    ]);

$is_admin = !empty($header_role_perms['is_admin']);
$is_pres_or_vp = !empty($header_role_perms['is_pres_or_vp']);
$can_manage_users = !empty($header_role_perms['can_manage_users']);
$can_manage_tasks = !empty($header_role_perms['can_manage_tasks']);
$can_manage_projects = $header_role_perms['can_manage_projects'] ?? true;
$can_manage_accomplishments = $header_role_perms['can_manage_accomplishments'] ?? true;
$can_manage_feedbacks = $header_role_perms['can_manage_feedbacks'] ?? true;
$can_view_reports = $header_role_perms['can_view_reports'] ?? true;
$can_manage_orgs = !empty($header_role_perms['can_manage_orgs']);

$student_name = $_SESSION['student_name'] ?? 'Student';
$student_grade = $_SESSION['student_grade_section'] ?? '';
$student_lrn = $_SESSION['student_lrn'] ?? '';

// Fetch Header Notifications (Task & Deadline Reminders, Overdue Alerts, Who Logged In, & Recent Activity Logs)
$header_task_reminders = [];
$header_login_notifs = [];
$header_activity_notifs = [];
$latest_notif_id = 0;
$latest_act_id = 0;
$urgent_reminder_count = 0;

if ($is_officer_logged_in && isset($pdo)) {
    try {
        $sess_org_id = intval($_SESSION['org_id'] ?? 0);

        // 1. Task Target Completion Reminders & Overdue Notifications (ONLY for respective assigned personnel)
        if (function_exists('getTaskDeadlineReminders')) {
            $header_task_reminders = getTaskDeadlineReminders($pdo, $is_admin, $sess_org_id, $user_name, $can_manage_tasks, $user_role);
            foreach ($header_task_reminders as $rem) {
                if (in_array($rem['category'], ['overdue', 'due_today', 'due_tomorrow', 'due_soon'], true)) {
                    $urgent_reminder_count++;
                }
            }
        }

        // 2. Who Logged In & Module Activity Logs Notifications (Admin Account ONLY)
        if ($is_admin) {
            $stmt_ln = $pdo->query("SELECT * FROM `login_logs` ORDER BY `created_at` DESC, `id` DESC LIMIT 15");
            $header_login_notifs = $stmt_ln->fetchAll();

            if (!empty($header_login_notifs)) {
                $latest_notif_id = intval($header_login_notifs[0]['id']);
            }

            // 3. Recent Module Activity Logs (including Auto-Overdue system logs) - Admin Account ONLY
            $sql_act = "SELECT * FROM (
                SELECT tl.id, 'Task' as module_name, tl.user_name, tl.user_role, tl.action_type, tl.log_details, tl.created_at, t.org_id, o.acronym as org_acronym
                FROM task_logs tl JOIN tasks t ON tl.task_id = t.id LEFT JOIN organizations o ON t.org_id = o.id
                UNION ALL
                SELECT pl.id, 'Project' as module_name, pl.user_name, pl.user_role, pl.action_type, pl.log_details, pl.created_at, p.org_id, o.acronym as org_acronym
                FROM project_logs pl JOIN projects p ON pl.project_id = p.id LEFT JOIN organizations o ON p.org_id = o.id
                UNION ALL
                SELECT al.id, 'Accomplishment' as module_name, al.user_name, al.user_role, al.action_type, al.log_details, al.created_at, a.org_id, o.acronym as org_acronym
                FROM accomplishment_logs al JOIN accomplishments a ON al.acc_id = a.id LEFT JOIN organizations o ON a.org_id = o.id
                UNION ALL
                SELECT sl.id, 'Student Account' as module_name, sl.user_name, sl.user_role, sl.action_type, CONCAT(sl.student_name, ' (LRN: ', sl.student_lrn, ') — ', sl.log_details) as log_details, sl.created_at, NULL as org_id, 'STUDENT' as org_acronym
                FROM student_logs sl
            ) combined_logs ORDER BY created_at DESC LIMIT 12";
            $header_activity_notifs = $pdo->query($sql_act)->fetchAll();
            if (!empty($header_activity_notifs)) {
                $latest_act_id = intval($header_activity_notifs[0]['id']);
            }
        }
    } catch (PDOException $e) {
        $header_task_reminders = [];
        $header_login_notifs = [];
        $header_activity_notifs = [];
    }
}
$notif_total_count = $is_admin
    ? (count($header_task_reminders) + count($header_login_notifs) + count($header_activity_notifs))
    : count($header_task_reminders);
$notif_signature_id = $latest_notif_id * 10000 + $latest_act_id * 100 + count($header_task_reminders) * 10 + $urgent_reminder_count;
$header_pending_students = 0;
$header_pending_tickets = 0;
if ($is_admin && isset($pdo)) {
    try {
        $header_pending_tickets = intval($pdo->query("SELECT COUNT(*) FROM password_reset_tickets WHERE status = 'Pending'")->fetchColumn());
    } catch (PDOException $e) {
        $header_pending_tickets = 0;
    }
}
if ($is_officer_logged_in && $can_manage_users && isset($pdo)) {
    try {
        $header_pending_students = intval($pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Pending'")->fetchColumn());
    } catch (PDOException $e) {
        $header_pending_students = 0;
    }
}
$notif_total_count += $header_pending_tickets;
$default_header_notif_tab = ($is_admin && empty($header_task_reminders)) ? 'logins' : 'tasks';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hear2Lead</title>
  
  <!-- Font Awesome Icons & Google Fonts -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<!-- Global Page Transition Loader & Progress Bar -->
<div id="globalProgressBar" class="page-progress-bar"></div>
<div id="globalPageLoader" class="page-loader-overlay">
  <div class="page-loader-content">
    <div class="loader-spinner-wrapper">
      <div class="loader-ring"></div>
      <div class="loader-ring"></div>
      <div class="loader-ring"></div>
      <div class="loader-logo-center">
        <i class="fa-solid fa-graduation-cap"></i>
      </div>
    </div>
    <div class="loader-text">Loading...</div>
    <div class="loader-subtext">Please wait a moment</div>
  </div>
</div>

<header class="navbar">
  <div class="navbar-container">
    <!-- Brand Logo & Portal Badge -->
    <?php $portal_home = $is_leadership ? '../leadership/dashboard.php' : ($is_officer_logged_in ? '../leadership/dashboard.php' : '../student/index.php'); ?>
    <a href="<?= $portal_home ?>" class="brand">
      <img src="../assets/uploads/logos.jpg" alt="School Logo" class="brand-school-logo">
      <div class="brand-text-wrapper">
        <span class="brand-title">Hear2Lead</span>
        <span class="portal-badge <?= $is_leadership ? 'leadership' : 'student' ?>">
          <i class="<?= $is_leadership ? 'fa-solid fa-shield-halved' : 'fa-solid fa-users' ?>"></i>
          <?= $is_leadership ? 'Leadership Portal' : 'Student Portal' ?>
        </span>
      </div>
    </a>

    <!-- Navigation Menu -->
    <nav class="navbar-nav">
      <ul class="nav-links">
        <?php if ($is_leadership): ?>
          <!-- Leadership Navigation Links -->
          <li><a href="../leadership/dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
          <li><a href="../leadership/tasks.php" class="nav-link <?= $current_page == 'tasks.php' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Tasks</a></li>
          <?php if ($can_manage_projects): ?>
            <li><a href="../leadership/projects.php" class="nav-link <?= $current_page == 'projects.php' ? 'active' : '' ?>"><i class="fa-solid fa-diagram-project"></i> Projects</a></li>
          <?php endif; ?>
          <?php if ($can_manage_accomplishments): ?>
            <li><a href="../leadership/accomplishments.php" class="nav-link <?= $current_page == 'accomplishments.php' ? 'active' : '' ?>"><i class="fa-solid fa-trophy"></i> Accomplishments</a></li>
          <?php endif; ?>
          <?php if ($can_manage_feedbacks): ?>
            <li><a href="../leadership/feedbacks.php" class="nav-link <?= $current_page == 'feedbacks.php' ? 'active' : '' ?>"><i class="fa-solid fa-comments"></i> Feedbacks</a></li>
          <?php endif; ?>
          <?php if ($can_view_reports): ?>
            <li><a href="../leadership/reports.php" class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
          <?php endif; ?>
          <?php if ($can_manage_users): ?>
            <li>
              <a href="../leadership/users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i> Users
                <?php if ($header_pending_students > 0): ?>
                  <span class="badge" style="background: #dc2626; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 10px; margin-left: 2px; font-weight: 800; vertical-align: middle;" title="<?= $header_pending_students ?> student registration(s) pending approval"><?= $header_pending_students ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endif; ?>
          <?php if ($can_manage_orgs): ?>
            <li><a href="../leadership/organizations.php" class="nav-link <?= $current_page == 'organizations.php' ? 'active' : '' ?>"><i class="fa-solid fa-sitemap"></i> Orgs</a></li>
          <?php endif; ?>

          <!-- Notification Bell & User Profile Dropdown -->
          <li class="nav-divider"></li>

          <li class="profile-dropdown notif-dropdown" id="headerNotifDropdown" data-latest-id="<?= $notif_signature_id ?>">
            <button type="button" class="profile-dropdown-toggle notif-bell-btn <?= $current_page === 'notifications.php' ? 'active' : '' ?>" aria-expanded="false" title="<?= $is_admin ? 'Notifications — Tasks, Logins & Activity Logs' : 'Notifications — Your Assigned Task Reminders' ?>">
              <i class="fa-solid fa-bell"></i>
              <span class="notif-mobile-label">Notifications</span>
              <?php if ($notif_total_count > 0): ?>
                <span class="notif-count-badge" id="headerNotifBadge"><?= $notif_total_count ?></span>
              <?php endif; ?>
            </button>
            <div class="profile-dropdown-menu notif-dropdown-menu" onclick="event.stopPropagation();">
              <div class="notif-dropdown-header">
                <div>
                  <div class="notif-header-title"><i class="fa-solid fa-bell" style="color: var(--primary);"></i> Notifications Center</div>
                  <div class="notif-header-sub">
                    <?= $is_admin ? 'Task Deadlines, Overdue Alerts, Logins & Logs' : 'Assigned Task Deadlines & Overdue Reminders' ?>
                  </div>
                </div>
                <button type="button" class="notif-mark-read-btn" onclick="markHeaderNotifsRead(<?= $notif_signature_id ?>)">
                  <i class="fa-solid fa-check-double"></i> Mark read
                </button>
              </div>

              <?php if ($is_admin): ?>
                <div class="notif-tabs-bar" style="grid-template-columns: 1fr 1fr 1fr;">
                  <button type="button" class="notif-tab-btn <?= $default_header_notif_tab === 'tasks' ? 'active' : '' ?>" id="notifTabBtnTasks" onclick="switchHeaderNotifTab('tasks')">
                    <i class="fa-solid fa-list-check"></i> Tasks (<?= count($header_task_reminders) ?>)
                  </button>
                  <button type="button" class="notif-tab-btn <?= $default_header_notif_tab === 'logins' ? 'active' : '' ?>" id="notifTabBtnLogins" onclick="switchHeaderNotifTab('logins')">
                    <i class="fa-solid fa-right-to-bracket"></i> Logins (<?= count($header_login_notifs) ?>)
                  </button>
                  <button type="button" class="notif-tab-btn" id="notifTabBtnActivity" onclick="switchHeaderNotifTab('activity')">
                    <i class="fa-solid fa-clock-rotate-left"></i> Logs (<?= count($header_activity_notifs) ?>)
                  </button>
                </div>
              <?php else: ?>
                <div class="notif-tabs-bar" style="grid-template-columns: 1fr;">
                  <button type="button" class="notif-tab-btn active" id="notifTabBtnTasks">
                    <i class="fa-solid fa-list-check"></i> My Assigned Task Reminders (<?= count($header_task_reminders) ?>)
                  </button>
                </div>
              <?php endif; ?>

              <?php if (!empty($header_pending_students)): ?>
                <a href="../leadership/users.php?tab=students&student_status=pending" style="display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.85rem; background: #fffbeb; border-bottom: 1.5px solid #fde68a; text-decoration: none; color: #92400e; font-size: 0.8rem; font-weight: 700;">
                  <span><i class="fa-solid fa-clock" style="color: #d97706; margin-right: 4px;"></i> <?= $header_pending_students ?> Student Registration(s) Pending Approval</span>
                  <span style="background: #d97706; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem;">Review</span>
                </a>
              <?php endif; ?>

              <?php if (!empty($header_pending_tickets)): ?>
                <a href="../leadership/users.php?tab=tickets&ticket_status=pending" style="display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0.85rem; background: #fff7ed; border-bottom: 1.5px solid #fed7aa; text-decoration: none; color: #9a3412; font-size: 0.8rem; font-weight: 700;">
                  <span><i class="fa-solid fa-key" style="color: #ea580c; margin-right: 4px;"></i> <?= $header_pending_tickets ?> Password Reset Request(s) Pending</span>
                  <span style="background: #ea580c; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem;">Manage</span>
                </a>
              <?php endif; ?>

              <!-- Pane 1: Assigned Tasks, Tomorrow Deadline Reminders & Overdue Alerts -->
              <div class="notif-list-container" id="notifPaneTasks" style="<?= ($is_admin && $default_header_notif_tab !== 'tasks') ? 'display: none;' : '' ?>">
                <?php if (empty($header_task_reminders)): ?>
                  <div class="notif-empty-state">
                    <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
                    <p>No pending or overdue tasks assigned to you right now.</p>
                  </div>
                <?php else: ?>
                  <?php foreach (array_slice($header_task_reminders, 0, 10) as $rem): ?>
                    <a href="<?= htmlspecialchars($rem['link']) ?>" class="notif-item" style="text-decoration: none; background: <?= htmlspecialchars($rem['bg_color']) ?>; border-left: 3px solid <?= htmlspecialchars($rem['color']) ?>;">
                      <div class="notif-avatar" style="background: <?= htmlspecialchars($rem['color']) ?>;">
                        <i class="fa-solid <?= htmlspecialchars($rem['icon']) ?>" style="font-size: 0.8rem;"></i>
                      </div>
                      <div class="notif-item-content">
                        <div class="notif-item-top">
                          <span class="badge <?= htmlspecialchars($rem['badge_class']) ?>" style="font-size: 0.63rem; padding: 0.12rem 0.42rem;">
                            <?= htmlspecialchars($rem['badge_text']) ?>
                          </span>
                          <?php if (!empty($rem['org_acronym'])): ?>
                            <span class="badge badge-planning" style="font-size: 0.62rem; padding: 0.1rem 0.35rem;">
                              <?= htmlspecialchars($rem['org_acronym']) ?>
                            </span>
                          <?php endif; ?>
                        </div>
                        <div style="font-weight: 800; font-size: 0.82rem; color: var(--dark); margin-bottom: 0.15rem;">
                          <?= htmlspecialchars($rem['headline']) ?>
                        </div>
                        <div class="notif-item-desc">
                          <?= htmlspecialchars($rem['message']) ?>
                        </div>
                        <div class="notif-item-time">
                          <i class="fa-regular fa-calendar"></i> Target: <?= htmlspecialchars($rem['deadline_formatted']) ?> • Assigned: <?= htmlspecialchars($rem['assigned_to']) ?>
                        </div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <?php if ($is_admin): ?>
                <!-- Pane 2: Who Logged In (Admin Only) -->
                <div class="notif-list-container" id="notifPaneLogins" style="<?= $default_header_notif_tab === 'logins' ? '' : 'display: none;' ?>">
                  <?php if (empty($header_login_notifs)): ?>
                    <div class="notif-empty-state">
                      <i class="fa-regular fa-bell-slash"></i>
                      <p>No login notifications recorded yet.</p>
                    </div>
                  <?php else: ?>
                    <?php foreach ($header_login_notifs as $ln):
                      $ln_initial = strtoupper(substr(trim($ln['user_name'] ?: 'U'), 0, 1));
                      $is_student_ln = (strcasecmp($ln['account_type'] ?? '', 'Student') === 0);
                      $ln_perms = !$is_student_ln && function_exists('getRolePermissions') ? getRolePermissions($ln['user_role'], $pdo) : null;
                      $ln_badge = $is_student_ln ? 'badge-planning' : ($ln_perms['badge_style'] ?? 'badge-role-officer');
                    ?>
                      <div class="notif-item">
                        <div class="notif-avatar" style="background: <?= $is_student_ln ? 'var(--primary)' : 'var(--accent)' ?>;">
                          <?= htmlspecialchars($ln_initial) ?>
                        </div>
                        <div class="notif-item-content">
                          <div class="notif-item-top">
                            <strong class="notif-user-name"><?= htmlspecialchars($ln['user_name']) ?></strong>
                            <span class="badge <?= htmlspecialchars($ln_badge) ?>" style="font-size: 0.65rem; padding: 0.12rem 0.42rem;">
                              <?= htmlspecialchars($ln['user_role']) ?>
                            </span>
                            <?php if (!empty($ln['org_acronym']) && $ln['org_acronym'] !== 'STUDENT'): ?>
                              <span class="badge badge-planning" style="font-size: 0.63rem; padding: 0.1rem 0.38rem;">
                                <?= htmlspecialchars($ln['org_acronym']) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                          <div class="notif-item-desc">
                            <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 0.7rem;"></i>
                            <?= htmlspecialchars($ln['log_details']) ?>
                          </div>
                          <div class="notif-item-time">
                            <i class="fa-regular fa-clock"></i> <?= date('M d, Y • g:i:s A', strtotime($ln['created_at'])) ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>

                <!-- Pane 3: Who Logged Module Updates (Admin Only) -->
                <div class="notif-list-container" id="notifPaneActivity" style="display: none;">
                  <?php if (empty($header_activity_notifs)): ?>
                    <div class="notif-empty-state">
                      <i class="fa-solid fa-clock-rotate-left"></i>
                      <p>No recent module activity logs recorded.</p>
                    </div>
                  <?php else: ?>
                    <?php foreach ($header_activity_notifs as $act):
                      $mod_icon = 'fa-list-check';
                      if ($act['module_name'] === 'Project') $mod_icon = 'fa-diagram-project';
                      elseif ($act['module_name'] === 'Accomplishment') $mod_icon = 'fa-trophy';
                      $is_overdue_act = ($act['action_type'] === 'Auto-Overdue');
                    ?>
                      <div class="notif-item" style="<?= $is_overdue_act ? 'background: #fef2f2; border-left: 3px solid #dc2626;' : '' ?>">
                        <div class="notif-avatar" style="background: <?= $is_overdue_act ? '#dc2626' : 'var(--primary)' ?>;">
                          <i class="fa-solid <?= $is_overdue_act ? 'fa-triangle-exclamation' : $mod_icon ?>" style="font-size: 0.78rem;"></i>
                        </div>
                        <div class="notif-item-content">
                          <div class="notif-item-top">
                            <strong class="notif-user-name"><?= htmlspecialchars($act['user_name']) ?></strong>
                            <span class="badge <?= $is_overdue_act ? 'badge-overdue' : 'badge-role-officer' ?>" style="font-size: 0.65rem; padding: 0.12rem 0.42rem;">
                              <?= htmlspecialchars($act['user_role']) ?>
                            </span>
                            <?php if (!empty($act['org_acronym'])): ?>
                              <span class="badge badge-planning" style="font-size: 0.63rem; padding: 0.1rem 0.38rem;">
                                <?= htmlspecialchars($act['org_acronym']) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                          <div class="notif-item-desc">
                            <strong>[<?= htmlspecialchars($act['module_name']) ?> • <?= htmlspecialchars($act['action_type']) ?>]</strong>
                            <?= htmlspecialchars(mb_strimwidth($act['log_details'], 0, 95, '...')) ?>
                          </div>
                          <div class="notif-item-time">
                            <i class="fa-regular fa-clock"></i> <?= date('M d, Y • g:i:s A', strtotime($act['created_at'])) ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <div class="notif-dropdown-footer" style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                <a href="../leadership/notifications.php" class="btn btn-primary btn-sm" style="width: 100%; justify-content: center; color: #fff; text-decoration: none; padding: 0.45rem 0.75rem;">
                  <i class="fa-solid fa-bell"></i> See All Notifications
                </a>
              </div>
            </div>
          </li>

          <li class="profile-dropdown">
            <button type="button" class="profile-dropdown-toggle" aria-expanded="false">
              <div class="user-avatar-initial" style="background: var(--accent);"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
              <div class="user-info-text">
                <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="user-role-org" style="color: var(--accent);"><?= htmlspecialchars($user_role) ?> • <?= htmlspecialchars($org_acronym) ?></span>
              </div>
              <i class="fa-solid fa-chevron-down caret-icon"></i>
            </button>
            <div class="profile-dropdown-menu">
              <div class="dropdown-user-header">
                <div class="user-avatar-initial" style="background: var(--accent);"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <div class="header-info">
                  <span class="header-name"><?= htmlspecialchars($user_name) ?></span>
                  <span class="header-role" style="color: var(--accent);"><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($user_role) ?></span>
                  <span class="header-sub"><?= htmlspecialchars($org_acronym) ?> Organization</span>
                </div>
              </div>
              <ul class="dropdown-menu-list">
                <li><a href="../leadership/dashboard.php" class="dropdown-item"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
                <li><a href="../leadership/notifications.php" class="dropdown-item"><i class="fa-solid fa-bell"></i> All Notifications</a></li>
                <li><a href="../leadership/reports.php" class="dropdown-item"><i class="fa-solid fa-chart-pie"></i> Performance Reports</a></li>
                <li><a href="../student/index.php" class="dropdown-item"><i class="fa-solid fa-eye"></i> View Student Portal</a></li>
                <li><button type="button" class="dropdown-item" onclick="openChangePasswordModal()"><i class="fa-solid fa-key" style="color: var(--primary);"></i> Change Password</button></li>
                <li class="dropdown-divider"></li>
                <li><a href="../leadership/logout.php" class="dropdown-item dropdown-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
              </ul>
            </div>
          </li>

        <?php else: ?>
          <!-- Student Navigation Links -->
          <li><a href="../student/index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Overview</a></li>
          <li><a href="../student/accomplishments.php" class="nav-link <?= $current_page == 'accomplishments.php' ? 'active' : '' ?>"><i class="fa-solid fa-trophy"></i> Accomplishments</a></li>
          <li><a href="../student/projects.php" class="nav-link <?= $current_page == 'projects.php' ? 'active' : '' ?>"><i class="fa-regular fa-calendar-days"></i> Programs and Projects</a></li>
          <li><a href="../student/progress.php" class="nav-link <?= $current_page == 'progress.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Progress</a></li>
          <li><a href="../student/feedback.php" class="nav-link <?= $current_page == 'feedback.php' ? 'active' : '' ?>"><i class="fa-regular fa-comment-dots"></i> Feedback & Concerns</a></li>
          
          <li class="nav-divider"></li>

          <?php if ($is_officer_logged_in): ?>
            <!-- Officer viewing Student Portal -->
            <li class="profile-dropdown">
              <button type="button" class="profile-dropdown-toggle" aria-expanded="false" style="background: #fdf2f8; border-color: #fbcfe8;">
                <div class="user-avatar-initial" style="background: var(--accent);"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <div class="user-info-text">
                  <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                  <span class="user-role-org" style="color: var(--accent);"><?= htmlspecialchars($user_role) ?> • <?= htmlspecialchars($org_acronym) ?></span>
                </div>
                <i class="fa-solid fa-chevron-down caret-icon"></i>
              </button>
              <div class="profile-dropdown-menu">
                <div class="dropdown-user-header">
                  <div class="user-avatar-initial" style="background: var(--accent);"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                  <div class="header-info">
                    <span class="header-name"><?= htmlspecialchars($user_name) ?></span>
                    <span class="header-role" style="color: var(--accent);"><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($user_role) ?></span>
                    <span class="header-sub"><?= htmlspecialchars($org_acronym) ?> Organization</span>
                  </div>
                </div>
                <ul class="dropdown-menu-list">
                  <li><a href="../leadership/dashboard.php" class="dropdown-item"><i class="fa-solid fa-gauge-high"></i> Officer Dashboard</a></li>
                  <li><a href="../student/index.php" class="dropdown-item"><i class="fa-solid fa-graduation-cap"></i> Student Portal</a></li>
                  <li><button type="button" class="dropdown-item" onclick="openChangePasswordModal()"><i class="fa-solid fa-key" style="color: var(--primary);"></i> Change Password</button></li>
                  <li class="dropdown-divider"></li>
                  <li><a href="../leadership/logout.php" class="dropdown-item dropdown-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                </ul>
              </div>
            </li>

          <?php elseif ($is_student_logged_in): ?>
            <!-- Verified Student logged in -->
            <li class="profile-dropdown">
              <button type="button" class="profile-dropdown-toggle" aria-expanded="false" style="background: #f0f9ff; border-color: #bae6fd;">
                <div class="user-avatar-initial" style="background: var(--primary);"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
                <div class="user-info-text">
                  <span class="user-name"><?= htmlspecialchars($student_name) ?></span>
                  <span class="user-role-org" style="color: var(--primary);"><?= htmlspecialchars($student_grade ?: 'Student') ?></span>
                </div>
                <i class="fa-solid fa-chevron-down caret-icon"></i>
              </button>
              <div class="profile-dropdown-menu">
                <div class="dropdown-user-header">
                  <div class="user-avatar-initial" style="background: var(--primary);"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
                  <div class="header-info">
                    <span class="header-name"><?= htmlspecialchars($student_name) ?></span>
                    <span class="header-role"><i class="fa-solid fa-id-card"></i> LRN: <?= htmlspecialchars($student_lrn) ?></span>
                    <span class="header-sub"><?= htmlspecialchars($student_grade) ?></span>
                  </div>
                </div>
                <ul class="dropdown-menu-list">
                  <li><a href="../student/index.php" class="dropdown-item"><i class="fa-solid fa-graduation-cap"></i> Portal Overview</a></li>
                  <li><a href="../student/feedback.php" class="dropdown-item"><i class="fa-solid fa-comment-dots"></i> My Feedback</a></li>
                  <li><button type="button" class="dropdown-item" onclick="openChangePasswordModal()"><i class="fa-solid fa-key" style="color: var(--primary);"></i> Change Password</button></li>
                  <li class="dropdown-divider"></li>
                  <li><a href="../student/logout.php" class="dropdown-item dropdown-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                </ul>
              </div>
            </li>

          <?php else: ?>
            <!-- Guest / Unauthenticated -->
            <li><button type="button" onclick="openAuthModal('student')" class="btn btn-secondary btn-sm" style="cursor: pointer;"><i class="fa-solid fa-user"></i> Student Login</button></li>
            <li><button type="button" onclick="openAuthModal('officer')" class="btn btn-primary btn-sm" style="cursor: pointer;"><i class="fa-solid fa-lock"></i> Officer Login</button></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
    </nav>

    <!-- Mobile Menu Toggle Button -->
    <button type="button" class="mobile-toggle-btn" aria-label="Toggle navigation menu" aria-expanded="false">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>
</header>

<?php include_once __DIR__ . '/change_password_modal.php'; ?>

<main class="main-content">
