<?php
// leadership/users.php - User Management Module (Leaders & Officers vs Student Accounts)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = intval($_SESSION['user_id']);
$current_org_id = intval($_SESSION['org_id']);
$current_role = $_SESSION['user_role'] ?? 'Officer';

$is_admin = ($current_role === 'Admin');
$is_pres_or_vp = in_array($current_role, ['President', 'Vice President'], true);

// Strict RBAC: Only Admin, President, and Vice President can access this page
if (!$is_admin && !$is_pres_or_vp) {
    header("Location: dashboard.php?error=" . urlencode("Access denied. You do not have permission to access User Management."));
    exit;
}

$success_msg = '';
$error_msg = '';

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'students') ? 'students' : 'leaders';

// ----------------------------------------------------
// Handle CRUD Actions
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ==========================================
    // 1. LEADER / OFFICER ACTIONS
    // ==========================================
    if ($action === 'create_user') {
        $active_tab = 'leaders';
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'Officer');
        
        // Scope org_id
        $org_id = $is_admin ? intval($_POST['org_id'] ?? 0) : $current_org_id;

        // Prevent non-admin from creating Admin accounts
        if (!$is_admin && $role === 'Admin') {
            $role = 'Officer';
        }

        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($org_id)) {
            $error_msg = "All required fields must be filled out.";
        } else {
            // Check if username or email already exists in users table
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetchColumn() > 0) {
                $error_msg = "Username or Email address is already taken by another officer account.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (org_id, full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$org_id, $full_name, $username, $email, $hashed_password, $role])) {
                    $success_msg = "Officer account '$username' created successfully!";
                } else {
                    $error_msg = "Failed to create officer account.";
                }
            }
        }
    }

    if ($action === 'update_user') {
        $active_tab = 'leaders';
        $user_id = intval($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'Officer');
        $new_password = trim($_POST['new_password'] ?? '');

        // Fetch target user for security verification
        $target_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $target_stmt->execute([$user_id]);
        $target_user = $target_stmt->fetch();

        if (!$target_user) {
            $error_msg = "The specified user account was not found.";
        } elseif (!$is_admin && $target_user['org_id'] != $current_org_id) {
            $error_msg = "Permission denied: You can only manage officers in your own organization.";
        } elseif (!$is_admin && $target_user['role'] === 'Admin') {
            $error_msg = "Permission denied: You cannot modify an Administrator account.";
        } else {
            // Scope org_id
            $org_id = $is_admin ? intval($_POST['org_id'] ?? $target_user['org_id']) : $current_org_id;

            // Non-admin cannot escalate anyone to Admin
            if (!$is_admin && $role === 'Admin') {
                $role = $target_user['role'];
            }

            if (empty($full_name) || empty($username) || empty($email) || empty($org_id)) {
                $error_msg = "Full Name, Username, Email, and Organization are required.";
            } else {
                // Check duplicate username for other users
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check->execute([$username, $email, $user_id]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "Username or Email is already in use by another account.";
                } else {
                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE users SET org_id = ?, full_name = ?, username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                        $stmt->execute([$org_id, $full_name, $username, $email, $hashed_password, $role, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET org_id = ?, full_name = ?, username = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$org_id, $full_name, $username, $email, $role, $user_id]);
                    }

                    // Update active session name if editing self
                    if ($user_id === $current_user_id) {
                        $_SESSION['user_name'] = $full_name;
                        $_SESSION['user_role'] = $role;
                    }

                    $success_msg = "Officer account updated successfully!";
                }
            }
        }
    }

    if ($action === 'delete_user') {
        $active_tab = 'leaders';
        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id === $current_user_id) {
            $error_msg = "You cannot delete your own currently logged-in account!";
        } else {
            $target_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $target_stmt->execute([$user_id]);
            $target_user = $target_stmt->fetch();

            if (!$target_user) {
                $error_msg = "Target account not found.";
            } elseif (!$is_admin && $target_user['org_id'] != $current_org_id) {
                $error_msg = "Permission denied: You can only delete accounts in your own organization.";
            } elseif (!$is_admin && $target_user['role'] === 'Admin') {
                $error_msg = "Permission denied: You cannot delete an Administrator account.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $success_msg = "Officer account deleted successfully.";
                } else {
                    $error_msg = "Failed to delete user account.";
                }
            }
        }
    }

    // ==========================================
    // 2. STUDENT ACCOUNT ACTIONS (Admin Only)
    // ==========================================
    if (in_array($action, ['create_student', 'update_student', 'delete_student'])) {
        $active_tab = 'students';
        if (!$is_admin) {
            $error_msg = "Permission denied: Only Administrators can create, edit, or delete student accounts.";
        } elseif ($action === 'create_student') {
            $student_lrn = trim($_POST['student_lrn'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $grade_section = trim($_POST['grade_section'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($student_lrn) || empty($full_name) || empty($grade_section) || empty($password)) {
                $error_msg = "LRN, Full Name, Grade & Section, and Password are required.";
            } else {
                // Check duplicate LRN or email
                $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_lrn = ? OR (email = ? AND email != '')");
                $check->execute([$student_lrn, $email]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "A student with this LRN or Email already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO students (student_lrn, full_name, grade_section, email, password) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $hashed_password])) {
                        $success_msg = "Student account for '$full_name' (LRN: $student_lrn) created successfully!";
                    } else {
                        $error_msg = "Failed to create student account.";
                    }
                }
            }
        } elseif ($action === 'update_student') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $student_lrn = trim($_POST['student_lrn'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $grade_section = trim($_POST['grade_section'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $new_password = trim($_POST['new_password'] ?? '');

            if (empty($student_lrn) || empty($full_name) || empty($grade_section)) {
                $error_msg = "LRN, Full Name, and Grade & Section are required.";
            } else {
                // Check duplicate LRN or email for other students
                $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE (student_lrn = ? OR (email = ? AND email != '')) AND id != ?");
                $check->execute([$student_lrn, $email, $student_id]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "Another student is already using this LRN or Email address.";
                } else {
                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE students SET student_lrn = ?, full_name = ?, grade_section = ?, email = ?, password = ? WHERE id = ?");
                        $stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $hashed_password, $student_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE students SET student_lrn = ?, full_name = ?, grade_section = ?, email = ? WHERE id = ?");
                        $stmt->execute([$student_lrn, $full_name, $grade_section, $email ?: null, $student_id]);
                    }
                    $success_msg = "Student account updated successfully!";
                }
            }
        } elseif ($action === 'delete_student') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
            if ($stmt->execute([$student_id])) {
                $success_msg = "Student account removed successfully.";
            } else {
                $error_msg = "Failed to delete student account.";
            }
        }
    }
}

// ----------------------------------------------------
// Data Fetching & Scoping
// ----------------------------------------------------
// 1. Organizations List (for filters & modals)
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

// 2. Leaders Query Scoping
$search_leaders = trim($_GET['q_leaders'] ?? ($_GET['q'] ?? ''));
$filter_org = $is_admin ? intval($_GET['org_id'] ?? 0) : $current_org_id;

$leader_where = ["1=1"];
$leader_params = [];

if (!$is_admin) {
    // President / Vice President strictly scoped to their own organization
    $leader_where[] = "u.org_id = ?";
    $leader_params[] = $current_org_id;
} elseif ($filter_org > 0) {
    // Admin filtering by specific organization
    $leader_where[] = "u.org_id = ?";
    $leader_params[] = $filter_org;
}

if (!empty($search_leaders)) {
    $leader_where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $leader_params[] = "%$search_leaders%";
    $leader_params[] = "%$search_leaders%";
    $leader_params[] = "%$search_leaders%";
}

$leader_where_sql = implode(" AND ", $leader_where);
$sql_leaders = "SELECT u.*, o.name as org_name, o.acronym 
                FROM users u 
                JOIN organizations o ON u.org_id = o.id 
                WHERE $leader_where_sql 
                ORDER BY o.acronym ASC, u.full_name ASC";
$stmt_leaders = $pdo->prepare($sql_leaders);
$stmt_leaders->execute($leader_params);
$users = $stmt_leaders->fetchAll();

// Count badges
if ($is_admin) {
    $leader_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} else {
    $stmt_lc = $pdo->prepare("SELECT COUNT(*) FROM users WHERE org_id = ?");
    $stmt_lc->execute([$current_org_id]);
    $leader_count = $stmt_lc->fetchColumn();
}

// 3. Students Query Scoping
$search_students = trim($_GET['q_students'] ?? '');
$student_where = ["1=1"];
$student_params = [];

if (!empty($search_students)) {
    $student_where[] = "(s.full_name LIKE ? OR s.student_lrn LIKE ? OR s.grade_section LIKE ? OR s.email LIKE ?)";
    $student_params[] = "%$search_students%";
    $student_params[] = "%$search_students%";
    $student_params[] = "%$search_students%";
    $student_params[] = "%$search_students%";
}

$student_where_sql = implode(" AND ", $student_where);
$sql_students = "SELECT * FROM students s WHERE $student_where_sql ORDER BY s.full_name ASC";
$stmt_students = $pdo->prepare($sql_students);
$stmt_students->execute($student_params);
$students = $stmt_students->fetchAll();

$student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-users-gear" style="color: var(--primary);"></i> User Management Module</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Manage leader and officer accounts across all student organizations, and configure verified student accounts.
      <?php else: ?>
        Manage leader and officer accounts for <strong><?= htmlspecialchars($_SESSION['org_name']) ?> (<?= htmlspecialchars($_SESSION['org_acronym']) ?>)</strong>, and view student portal accounts.
      <?php endif; ?>
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <button class="btn btn-primary" data-modal-target="createUserModal">
      <i class="fa-solid fa-user-plus"></i> Add New Officer
    </button>
    <?php if ($is_admin): ?>
      <button class="btn btn-secondary" data-modal-target="createStudentModal">
        <i class="fa-solid fa-user-graduate"></i> Add Student Account
      </button>
    <?php endif; ?>
  </div>
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

<!-- Tab Panel Navigation -->
<div class="user-tabs-nav">
  <button type="button" class="user-tab-btn <?= $active_tab === 'leaders' ? 'active' : '' ?>" onclick="switchUserTab('leaders')">
    <i class="fa-solid fa-user-shield"></i>
    <span>Leaders & Officers Accounts</span>
    <span class="user-tab-badge"><?= $leader_count ?></span>
  </button>
  <button type="button" class="user-tab-btn <?= $active_tab === 'students' ? 'active' : '' ?>" onclick="switchUserTab('students')">
    <i class="fa-solid fa-graduation-cap"></i>
    <span>Student Accounts</span>
    <span class="user-tab-badge"><?= $student_count ?></span>
  </button>
</div>

<!-- ======================================================== -->
<!-- TAB 1: LEADERS & OFFICERS ACCOUNTS PANE                  -->
<!-- ======================================================== -->
<div class="user-tab-pane <?= $active_tab === 'leaders' ? 'active' : '' ?>" id="tab-leaders">

  <!-- Leaders Filter & Search Bar -->
  <div class="filter-bar">
    <form method="GET" action="users.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <input type="hidden" name="tab" value="leaders">

      <div style="flex: 2; min-width: 240px; position: relative;">
        <input type="text" name="q_leaders" class="form-control" placeholder="Search officers by name, username, or email..." value="<?= htmlspecialchars($search_leaders) ?>" style="padding-left: 2.25rem;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
      </div>

      <?php if ($is_admin): ?>
        <div style="flex: 1; min-width: 220px;">
          <select name="org_id" class="form-control" onchange="this.form.submit()">
            <option value="0">All Organizations</option>
            <?php foreach ($orgs as $org): ?>
              <option value="<?= $org['id'] ?>" <?= $filter_org == $org['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <div style="padding: 0.5rem 0.75rem; background: var(--gray-100); border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--gray-700); font-weight: 600;">
          <i class="fa-solid fa-shield-halved" style="color: var(--accent);"></i> Org: <?= htmlspecialchars($_SESSION['org_acronym']) ?>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Search</button>
      
      <?php if (!empty($search_leaders) || ($is_admin && $filter_org > 0)): ?>
        <a href="users.php?tab=leaders" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Leaders Table -->
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Full Name</th>
          <th>Username</th>
          <th>Email Address</th>
          <th>Organization</th>
          <th>Role</th>
          <th>Date Created</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--gray-500); padding: 2.5rem;">
              <i class="fa-solid fa-user-xmark" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
              No officer or leader accounts found matching your criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $user): 
            $is_self = ($user['id'] === $current_user_id);
            $role_badge_class = 'badge-role-officer';
            switch ($user['role']) {
                case 'Admin': $role_badge_class = 'badge-role-admin'; break;
                case 'President': $role_badge_class = 'badge-role-president'; break;
                case 'Vice President': $role_badge_class = 'badge-role-vp'; break;
                case 'Secretary': $role_badge_class = 'badge-role-secretary'; break;
                case 'Treasurer': $role_badge_class = 'badge-role-treasurer'; break;
                default: $role_badge_class = 'badge-role-officer'; break;
            }
          ?>
            <tr style="<?= $is_self ? 'background-color: #f0f9ff;' : '' ?>">
              <td>
                <strong style="font-size: 0.95rem; color: var(--dark);"><?= htmlspecialchars($user['full_name']) ?></strong>
                <?php if ($is_self): ?>
                  <span class="badge badge-in-progress" style="font-size: 0.65rem; margin-left: 0.25rem;">YOU</span>
                <?php endif; ?>
              </td>
              <td><code><?= htmlspecialchars($user['username']) ?></code></td>
              <td><a href="mailto:<?= htmlspecialchars($user['email']) ?>" style="font-size: 0.85rem; color: var(--primary);"><?= htmlspecialchars($user['email']) ?></a></td>
              <td><span class="badge badge-planning"><strong><?= htmlspecialchars($user['acronym']) ?></strong></span></td>
              <td><span class="badge <?= $role_badge_class ?>"><?= htmlspecialchars($user['role']) ?></span></td>
              <td><span style="font-size: 0.8rem; color: var(--gray-600);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y', strtotime($user['created_at'])) ?></span></td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                  <button class="btn btn-secondary btn-sm" onclick='openUserEditModal(<?= json_encode($user) ?>)' title="Edit Officer">
                    <i class="fa-solid fa-user-pen"></i> Edit
                  </button>
                  <?php if (!$is_self && ($is_admin || $user['role'] !== 'Admin')): ?>
                    <form method="POST" action="users.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete officer account \'<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>\'?', 'Delete Officer Account?');" style="display: inline;">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete User"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ======================================================== -->
<!-- TAB 2: STUDENT ACCOUNTS PANE                             -->
<!-- ======================================================== -->
<div class="user-tab-pane <?= $active_tab === 'students' ? 'active' : '' ?>" id="tab-students">

  <!-- Students Filter & Search Bar -->
  <div class="filter-bar">
    <form method="GET" action="users.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <input type="hidden" name="tab" value="students">

      <div style="flex: 2; min-width: 260px; position: relative;">
        <input type="text" name="q_students" class="form-control" placeholder="Search students by name, LRN, grade/section, or email..." value="<?= htmlspecialchars($search_students) ?>" style="padding-left: 2.25rem;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
      </div>

      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Search</button>
      
      <?php if (!empty($search_students)): ?>
        <a href="users.php?tab=students" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Students Table -->
  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Student Name</th>
          <th>Student LRN</th>
          <th>Grade & Section</th>
          <th>Email Address</th>
          <th>Date Registered</th>
          <?php if ($is_admin): ?>
            <th style="text-align: right;">Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
          <tr>
            <td colspan="<?= $is_admin ? '6' : '5' ?>" style="text-align: center; color: var(--gray-500); padding: 2.5rem;">
              <i class="fa-solid fa-graduation-cap" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
              No student accounts found matching your search.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($students as $student): ?>
            <tr>
              <td>
                <strong style="font-size: 0.95rem; color: var(--dark);"><?= htmlspecialchars($student['full_name']) ?></strong>
              </td>
              <td><code><?= htmlspecialchars($student['student_lrn']) ?></code></td>
              <td><span class="badge badge-planning"><i class="fa-solid fa-school"></i> <?= htmlspecialchars($student['grade_section']) ?></span></td>
              <td>
                <?php if (!empty($student['email'])): ?>
                  <a href="mailto:<?= htmlspecialchars($student['email']) ?>" style="font-size: 0.85rem; color: var(--primary);"><?= htmlspecialchars($student['email']) ?></a>
                <?php else: ?>
                  <span style="color: var(--gray-400); font-size: 0.85rem;">None</span>
                <?php endif; ?>
              </td>
              <td><span style="font-size: 0.8rem; color: var(--gray-600);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y', strtotime($student['created_at'])) ?></span></td>
              <?php if ($is_admin): ?>
                <td style="text-align: right;">
                  <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                    <button class="btn btn-secondary btn-sm" onclick='openStudentEditModal(<?= json_encode($student) ?>)' title="Edit Student">
                      <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <form method="POST" action="users.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete student account \'<?= htmlspecialchars($student['full_name'], ENT_QUOTES) ?>\' (LRN: <?= htmlspecialchars($student['student_lrn'], ENT_QUOTES) ?>)?', 'Delete Student Account?');" style="display: inline;">
                      <input type="hidden" name="action" value="delete_student">
                      <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Delete Student"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD NEW OFFICER / USER                           -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="createUserModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> Add New Officer Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="create_user">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span style="color: red;">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Juan Dela Cruz" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Username <span style="color: red;">*</span></label>
            <input type="text" name="username" class="form-control" placeholder="e.g. juan_sslg" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address <span style="color: red;">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="juan@school.edu.ph" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <?php if ($is_admin): ?>
              <select name="org_id" class="form-control" required>
                <option value="">-- Select Org --</option>
                <?php foreach ($orgs as $org): ?>
                  <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
              <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['org_acronym'] . ' - ' . $_SESSION['org_name']) ?>" disabled style="background: var(--gray-100);">
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Role <span style="color: red;">*</span></label>
            <select name="role" class="form-control" required>
              <option value="Officer">Officer</option>
              <option value="President">President</option>
              <option value="Vice President">Vice President</option>
              <option value="Secretary">Secretary</option>
              <option value="Treasurer">Treasurer</option>
              <?php if ($is_admin): ?>
                <option value="Admin">Admin</option>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password <span style="color: red;">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Account</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT OFFICER / USER                               -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="editUserModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Edit Officer Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="user_id" id="edit_user_id">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span style="color: red;">*</span></label>
          <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Username <span style="color: red;">*</span></label>
            <input type="text" name="username" id="edit_username" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address <span style="color: red;">*</span></label>
            <input type="email" name="email" id="edit_email" class="form-control" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <?php if ($is_admin): ?>
              <select name="org_id" id="edit_org_id" class="form-control" required>
                <?php foreach ($orgs as $org): ?>
                  <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
              <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['org_acronym'] . ' - ' . $_SESSION['org_name']) ?>" disabled style="background: var(--gray-100);">
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label">Role <span style="color: red;">*</span></label>
            <select name="role" id="edit_role" class="form-control" required>
              <option value="Officer">Officer</option>
              <option value="President">President</option>
              <option value="Vice President">Vice President</option>
              <option value="Secretary">Secretary</option>
              <option value="Treasurer">Treasurer</option>
              <?php if ($is_admin): ?>
                <option value="Admin">Admin</option>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">New Password (Leave blank to keep existing password)</label>
          <input type="password" name="new_password" class="form-control" placeholder="••••••••">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php if ($is_admin): ?>
<!-- ======================================================== -->
<!-- MODAL: ADD NEW STUDENT ACCOUNT                           -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="createStudentModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-graduate" style="color: var(--primary);"></i> Add Student Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="create_student">
      
      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Student LRN <span style="color: red;">*</span></label>
            <input type="text" name="student_lrn" class="form-control" placeholder="e.g. 2026-0004" required>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name <span style="color: red;">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Clara" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Grade & Section <span style="color: red;">*</span></label>
            <input type="text" name="grade_section" class="form-control" placeholder="e.g. Grade 11 - STEM" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address (Optional)</label>
            <input type="email" name="email" class="form-control" placeholder="maria@student.edu.ph">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password <span style="color: red;">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Student Account</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT STUDENT ACCOUNT                             -->
<!-- ======================================================== -->
<div class="modal-backdrop" id="editStudentModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Student Account</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="action" value="update_student">
      <input type="hidden" name="student_id" id="edit_student_id">
      
      <div class="modal-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Student LRN <span style="color: red;">*</span></label>
            <input type="text" name="student_lrn" id="edit_student_lrn" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name <span style="color: red;">*</span></label>
            <input type="text" name="full_name" id="edit_student_fullname" class="form-control" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Grade & Section <span style="color: red;">*</span></label>
            <input type="text" name="grade_section" id="edit_student_grade" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address (Optional)</label>
            <input type="email" name="email" id="edit_student_email" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">New Password (Leave blank to keep existing password)</label>
          <input type="password" name="new_password" class="form-control" placeholder="••••••••">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
// Switch between Leaders tab and Students tab
function switchUserTab(tabName) {
  var btns = document.querySelectorAll('.user-tab-btn');
  var panes = document.querySelectorAll('.user-tab-pane');
  
  btns.forEach(function(btn) { btn.classList.remove('active'); });
  panes.forEach(function(pane) { pane.classList.remove('active'); });

  if (tabName === 'students') {
    if (btns[1]) btns[1].classList.add('active');
    var stPane = document.getElementById('tab-students');
    if (stPane) stPane.classList.add('active');
    history.replaceState(null, '', 'users.php?tab=students');
  } else {
    if (btns[0]) btns[0].classList.add('active');
    var ldPane = document.getElementById('tab-leaders');
    if (ldPane) ldPane.classList.add('active');
    history.replaceState(null, '', 'users.php?tab=leaders');
  }
}

// Open Officer Edit Modal
function openUserEditModal(user) {
  document.getElementById('edit_user_id').value = user.id;
  document.getElementById('edit_full_name').value = user.full_name;
  document.getElementById('edit_username').value = user.username;
  document.getElementById('edit_email').value = user.email;
  var orgField = document.getElementById('edit_org_id');
  if (orgField) {
    orgField.value = user.org_id;
  }
  document.getElementById('edit_role').value = user.role;
  
  document.getElementById('editUserModal').classList.add('active');
}

<?php if ($is_admin): ?>
// Open Student Edit Modal
function openStudentEditModal(student) {
  document.getElementById('edit_student_id').value = student.id;
  document.getElementById('edit_student_lrn').value = student.student_lrn;
  document.getElementById('edit_student_fullname').value = student.full_name;
  document.getElementById('edit_student_grade').value = student.grade_section;
  document.getElementById('edit_student_email').value = student.email || '';
  
  document.getElementById('editStudentModal').classList.add('active');
}
<?php endif; ?>

// Sync active tab on initial load if hash is set
document.addEventListener('DOMContentLoaded', function() {
  var hash = window.location.hash;
  if (hash === '#tab-students') {
    switchUserTab('students');
  } else if (hash === '#tab-leaders') {
    switchUserTab('leaders');
  }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
