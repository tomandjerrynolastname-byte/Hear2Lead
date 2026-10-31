<?php
// index.php - Main Portal Selector
require_once __DIR__ . '/config/db.php';

// Prevent browser from serving cached index page via Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// If someone is logged in or has an active session, index is NOT accessible until they log out
if (isset($_SESSION['user_id'])) {
    header("Location: leadership/dashboard.php");
    exit;
} elseif (isset($_SESSION['student_id'])) {
    header("Location: student/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hear2Lead</title>
  
  <script>
    // Force reload if page is restored from bfcache on back navigation
    window.addEventListener('pageshow', function (e) {
      if (e.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
        window.location.reload();
      }
    });
  </script>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .portal-landing {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.75)), url('assets/uploads/bg.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
      padding: 6rem 1rem 3rem;
      position: relative;
    }
    .landing-header {
      text-align: center;
      max-width: 650px;
      margin-bottom: 2.5rem;
    }
    .landing-header h1 {
      font-size: 2.5rem;
      font-weight: 800;
      color: #ffffff;
      margin-bottom: 0.5rem;
      text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    .landing-header p {
      font-size: 1.1rem;
      color: rgba(255, 255, 255, 0.9);
      text-shadow: 0 1px 5px rgba(0,0,0,0.2);
    }
    .portal-cards-wrapper {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      max-width: 900px;
      width: 100%;
    }
    @media (max-width: 768px) {
      .landing-header h1 { font-size: 1.85rem; }
      .landing-header p { font-size: 0.95rem; }
      .portal-selection-card { padding: 1.75rem 1.25rem; }
      .portal-landing { padding-top: 7.5rem; }
    }
    .portal-selection-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 2.5rem 2rem;
      box-shadow: var(--shadow-md);
      border: 2px solid transparent;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    .portal-selection-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary);
    }
    .portal-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.25rem;
      margin-bottom: 1.5rem;
    }
    .student-card .portal-icon {
      background: #e0f2fe;
      color: #0284c7;
    }
    .leadership-card .portal-icon {
      background: #f3e8ff;
      color: #9333ea;
    }
    .portal-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.75rem;
    }
    .portal-desc {
      font-size: 0.95rem;
      color: var(--gray-600);
      margin-bottom: 1.75rem;
      flex: 1;
    }
    .portal-features {
      text-align: left;
      width: 100%;
      margin-bottom: 2rem;
      font-size: 0.9rem;
      color: var(--gray-700);
      list-style: none;
    }
    .portal-features li {
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .portal-features li i {
      color: var(--success);
    }
  </style>
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

<!-- Landing Page Top Navigation Bar -->
<header class="landing-topbar">
  <a href="index.php" class="landing-brand">
    <img src="assets/uploads/logoswhite.jpg" alt="Logo" class="landing-brand-logo">
    <span class="landing-brand-name">Hear2Lead</span>
  </a>
</header>

<div class="portal-landing">
  <!-- Status Toast Notifications (Bottom Right) -->
  <div class="toast-container" id="toastContainer">
    <?php if (!empty($_GET['success'])): ?>
      <div class="toast toast-success alert-auto-dismiss" role="alert">
        <div class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="toast-body">
          <div class="toast-title">Success</div>
          <div class="toast-message"><?= htmlspecialchars($_GET['success']) ?></div>
        </div>
        <button type="button" class="toast-close" aria-label="Close">&times;</button>
        <div class="toast-progress"><div class="toast-progress-bar"></div></div>
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['error']) && empty($_GET['modal'])): ?>
      <div class="toast toast-danger alert-auto-dismiss" role="alert">
        <div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div class="toast-body">
          <div class="toast-title">Notice</div>
          <div class="toast-message"><?= htmlspecialchars($_GET['error']) ?></div>
        </div>
        <button type="button" class="toast-close" aria-label="Close">&times;</button>
        <div class="toast-progress"><div class="toast-progress-bar"></div></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="landing-header">
    <img src="assets/uploads/logoswhite.jpg" alt="School Logo" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 3px solid var(--cream); margin: 0 auto 1.25rem auto; box-shadow: 0 8px 25px rgba(0,0,0,0.4); display: block;">
    <h1>Hear2Lead</h1>
    <p>Select your portal to explore organization projects, track accomplishments, submit feedback, or organize student leadership tasks.</p>
  </div>

  <div class="portal-cards-wrapper">
    <!-- Student Portal Card -->
    <div class="portal-selection-card student-card">
      <div class="portal-icon">
        <i class="fa-solid fa-users"></i>
      </div>
      <h2 class="portal-title">Student Portal</h2>
      <p class="portal-desc">Open for all students to monitor school organization activities and voice suggestions.</p>
      
      <ul class="portal-features">
        <li><i class="fa-solid fa-circle-check"></i> View Accomplishments & Achievements</li>
        <li><i class="fa-solid fa-circle-check"></i> Track Progress of Ongoing Programs</li>
        <li><i class="fa-solid fa-circle-check"></i> Explore Projects & Events Calendar</li>
        <li><i class="fa-solid fa-circle-check"></i> Submit Suggestions & Feedbacks</li>
      </ul>

      <button type="button" onclick="openAuthModal('student')" class="btn btn-primary" style="width: 100%; font-size: 1rem; padding: 0.8rem 1.5rem; cursor: pointer; border: none;">
        Student Login <i class="fa-solid fa-right-to-bracket"></i>
      </button>
      <button type="button" onclick="openAuthModal('register')" class="btn btn-secondary" style="width: 100%; font-size: 0.88rem; padding: 0.6rem 1.25rem; cursor: pointer; border: 1.5px solid var(--primary); color: var(--primary); background: rgba(100, 25, 20, 0.04); font-weight: 700; margin-top: 0.6rem;">
        <i class="fa-solid fa-user-plus"></i> Register Student Account
      </button>
    </div>

    <!-- Leadership Portal Card -->
    <div class="portal-selection-card leadership-card">
      <div class="portal-icon">
        <i class="fa-solid fa-user-shield"></i>
      </div>
      <h2 class="portal-title">Leadership Portal</h2>
      <p class="portal-desc">Accessible to Organization Officers (e.g., SSLG, YES-O) for task tracking & management.</p>
      
      <ul class="portal-features">
        <li><i class="fa-solid fa-circle-check"></i> Task Tracker & Organizer Dashboard</li>
        <li><i class="fa-solid fa-circle-check"></i> Accomplished, To Do & Overdue Stats</li>
        <li><i class="fa-solid fa-circle-check"></i> Upcoming Deadlines Organizer</li>
        <li><i class="fa-solid fa-circle-check"></i> Monitor Student Suggestions & Feedbacks</li>
      </ul>

      <button type="button" onclick="openAuthModal('officer')" class="btn btn-primary" style="width: 100%; font-size: 1rem; padding: 0.8rem 1.5rem; background-color: var(--accent); cursor: pointer; border: none;">
        Officer Login <i class="fa-solid fa-right-to-bracket"></i>
      </button>
    </div>
  </div>
</div>

<?php include_once __DIR__ . '/includes/login_modal.php'; ?>
<script src="assets/js/main.js"></script>
</body>
</html>
