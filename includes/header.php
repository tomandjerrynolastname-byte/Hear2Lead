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

$is_admin = ($user_role === 'Admin');
$is_pres_or_vp = in_array($user_role, ['President', 'Vice President'], true);
$can_manage_users = ($is_admin || $is_pres_or_vp);

$student_name = $_SESSION['student_name'] ?? 'Student';
$student_grade = $_SESSION['student_grade_section'] ?? '';
$student_lrn = $_SESSION['student_lrn'] ?? '';
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
          <li><a href="../leadership/projects.php" class="nav-link <?= $current_page == 'projects.php' ? 'active' : '' ?>"><i class="fa-solid fa-diagram-project"></i> Projects</a></li>
          <li><a href="../leadership/accomplishments.php" class="nav-link <?= $current_page == 'accomplishments.php' ? 'active' : '' ?>"><i class="fa-solid fa-trophy"></i> Accomplishments</a></li>
          <li><a href="../leadership/feedbacks.php" class="nav-link <?= $current_page == 'feedbacks.php' ? 'active' : '' ?>"><i class="fa-solid fa-comments"></i> Feedbacks</a></li>
          <li><a href="../leadership/reports.php" class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Reports</a></li>
          <?php if ($can_manage_users): ?>
            <li><a href="../leadership/users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>"><i class="fa-solid fa-users-gear"></i> Users</a></li>
          <?php endif; ?>
          <?php if ($is_admin): ?>
            <li><a href="../leadership/organizations.php" class="nav-link <?= $current_page == 'organizations.php' ? 'active' : '' ?>"><i class="fa-solid fa-sitemap"></i> Orgs</a></li>
          <?php endif; ?>

          <!-- User Profile Dropdown -->
          <li class="nav-divider"></li>

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
                <li><a href="../leadership/reports.php" class="dropdown-item"><i class="fa-solid fa-chart-pie"></i> Performance Reports</a></li>
                <li><a href="../student/index.php" class="dropdown-item"><i class="fa-solid fa-eye"></i> View Student Portal</a></li>
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

<main class="main-content">
