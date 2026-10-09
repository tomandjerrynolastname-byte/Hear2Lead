<?php
// includes/header.php - Enhanced Modern Header with User Profile Chip
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$is_leadership = strpos($_SERVER['REQUEST_URI'], '/leadership/') !== false;
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? 'Officer';
$user_role = $_SESSION['user_role'] ?? 'Leader';
$org_acronym = $_SESSION['org_acronym'] ?? 'SSLG';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Leadership Management System</title>
  
  <!-- Font Awesome Icons & Google Fonts -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="navbar">
  <div class="navbar-container">
    <!-- Brand Logo & Portal Badge -->
    <a href="../index.php" class="brand">
      <img src="../assets/uploads/logos.jpg" alt="School Logo" class="brand-school-logo">
      <div class="brand-text-wrapper">
        <span class="brand-title">Student Leadership</span>
        <span class="portal-badge <?= $is_leadership ? 'leadership' : 'student' ?>">
          <i class="<?= $is_leadership ? 'fa-solid fa-shield-halved' : 'fa-solid fa-users' ?>"></i>
          <?= $is_leadership ? 'Leadership Portal' : 'Student Portal' ?>
        </span>
      </div>
    </a>

    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-toggle-btn" aria-label="Toggle navigation menu">
      <i class="fa-solid fa-bars"></i>
    </button>

    <!-- Navigation Menu -->
    <nav>
      <ul class="nav-links">
        <?php if ($is_leadership): ?>
          <!-- Leadership Navigation Links -->
          <li><a href="../leadership/dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
          <li><a href="../leadership/tasks.php" class="nav-link <?= $current_page == 'tasks.php' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Tasks</a></li>
          <li><a href="../leadership/feedbacks.php" class="nav-link <?= $current_page == 'feedbacks.php' ? 'active' : '' ?>"><i class="fa-solid fa-comments"></i> Feedbacks</a></li>
          <li><a href="../leadership/projects.php" class="nav-link <?= $current_page == 'projects.php' ? 'active' : '' ?>"><i class="fa-solid fa-diagram-project"></i> Projects</a></li>
          <li><a href="../leadership/accomplishments.php" class="nav-link <?= $current_page == 'accomplishments.php' ? 'active' : '' ?>"><i class="fa-solid fa-trophy"></i> Accomplishments</a></li>
          <li><a href="../leadership/users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>"><i class="fa-solid fa-users-gear"></i> Users</a></li>
          <li><a href="../leadership/organizations.php" class="nav-link <?= $current_page == 'organizations.php' ? 'active' : '' ?>"><i class="fa-solid fa-sitemap"></i> Orgs</a></li>

          <!-- User Profile & Action Controls -->
          <li class="nav-divider"></li>

          <li class="user-chip-container">
            <div class="user-profile-chip">
              <div class="user-avatar-initial"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
              <div class="user-info-text">
                <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="user-role-org"><?= htmlspecialchars($user_role) ?> • <?= htmlspecialchars($org_acronym) ?></span>
              </div>
            </div>
          </li>

          <li><a href="../student/index.php" class="btn btn-secondary btn-sm" title="View Portal as Student"><i class="fa-solid fa-eye"></i> View Student Portal</a></li>
          <li><a href="../leadership/logout.php" class="btn btn-danger btn-sm" title="Logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>

        <?php else: ?>
          <!-- Student Navigation Links -->
          <li><a href="../student/index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Overview</a></li>
          <li><a href="../student/accomplishments.php" class="nav-link <?= $current_page == 'accomplishments.php' ? 'active' : '' ?>"><i class="fa-solid fa-trophy"></i> Accomplishments</a></li>
          <li><a href="../student/progress.php" class="nav-link <?= $current_page == 'progress.php' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Progress Tracker</a></li>
          <li><a href="../student/projects.php" class="nav-link <?= $current_page == 'projects.php' ? 'active' : '' ?>"><i class="fa-solid fa-folder-open"></i> Projects & Programs</a></li>
          <li><a href="../student/feedback.php" class="nav-link <?= $current_page == 'feedback.php' ? 'active' : '' ?>"><i class="fa-solid fa-comment-dots"></i> Suggestions / Feedback</a></li>
          
          <li class="nav-divider"></li>

          <?php if ($is_logged_in): ?>
            <li class="user-chip-container">
              <div class="user-profile-chip">
                <div class="user-avatar-initial"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <div class="user-info-text">
                  <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                  <span class="user-role-org"><?= htmlspecialchars($org_acronym) ?></span>
                </div>
              </div>
            </li>
            <li><a href="../leadership/dashboard.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-gear"></i> Leadership Dashboard</a></li>
          <?php else: ?>
            <li><a href="../leadership/login.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-lock"></i> Officer Login</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>

<main class="main-content">
