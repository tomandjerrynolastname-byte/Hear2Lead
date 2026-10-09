<?php
// leadership/users.php - User Management Module for Leadership Officers & Admins
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = $_SESSION['user_id'];
$current_org_id = $_SESSION['org_id'];
$current_role = $_SESSION['user_role'] ?? 'Officer';

$success_msg = '';
$error_msg = '';

// Handle CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create User
    if ($action === 'create_user') {
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $org_id = intval($_POST['org_id']);

        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($org_id)) {
            $error_msg = "All required fields must be filled out.";
        } else {
            // Check if username already exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetchColumn() > 0) {
                $error_msg = "Username or Email address is already taken by another account.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (org_id, full_name, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$org_id, $full_name, $username, $email, $hashed_password, $role])) {
                    $success_msg = "User account '$username' created successfully!";
                } else {
                    $error_msg = "Failed to create user account.";
                }
            }
        }
    }

    // Update User
    if ($action === 'update_user') {
        $user_id = intval($_POST['user_id']);
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $org_id = intval($_POST['org_id']);
        $new_password = trim($_POST['new_password'] ?? '');

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

                $success_msg = "User account updated successfully!";
            }
        }
    }

    // Delete User
    if ($action === 'delete_user') {
        $user_id = intval($_POST['user_id']);
        
        if ($user_id === $current_user_id) {
            $error_msg = "You cannot delete your own currently logged-in account!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $success_msg = "User account deleted successfully.";
            } else {
                $error_msg = "Failed to delete user.";
            }
        }
    }
}

// Search & Filter
$search_query = trim($_GET['q'] ?? '');
$filter_org = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;

$where_clauses = ["1=1"];
$params = [];

if (!empty($search_query)) {
    $where_clauses[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($filter_org > 0) {
    $where_clauses[] = "u.org_id = ?";
    $params[] = $filter_org;
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT u.*, o.name as org_name, o.acronym FROM users u JOIN organizations o ON u.org_id = o.id WHERE $where_sql ORDER BY o.acronym ASC, u.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch Organizations for dropdowns
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-users-gear" style="color: var(--primary);"></i> User Management Module</h1>
    <p class="page-subtitle">Manage organization officers, leader user accounts, roles, and access credentials.</p>
  </div>

  <button class="btn btn-primary" data-modal-target="createUserModal">
    <i class="fa-solid fa-user-plus"></i> Add New Officer / User
  </button>
</div>

<?php if (!empty($success_msg)): ?>
  <div class="alert alert-success alert-auto-dismiss">
    <span><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
  <div class="alert alert-danger alert-auto-dismiss">
    <span><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></span>
  </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="filter-bar">
  <form method="GET" action="users.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 240px; position: relative;">
      <input type="text" name="q" class="form-control" placeholder="Search by name, username, or email..." value="<?= htmlspecialchars($search_query) ?>" style="padding-left: 2.25rem;">
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
    </div>

    <div style="flex: 1; min-width: 200px;">
      <select name="org_id" class="form-control" onchange="this.form.submit()">
        <option value="0">All Organizations</option>
        <?php foreach ($orgs as $org): ?>
          <option value="<?= $org['id'] ?>" <?= $filter_org == $org['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Search</button>
    
    <?php if (!empty($search_query) || $filter_org > 0): ?>
      <a href="users.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset Filter</a>
    <?php endif; ?>
  </form>
</div>

<!-- Users Data Table -->
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
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
        <tr>
          <td colspan="7" style="text-align: center; color: var(--gray-500); padding: 2rem;">No user accounts found matching your search.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($users as $user): 
          $is_self = ($user['id'] === $current_user_id);
          $role_badge = match($user['role']) {
            'Admin' => 'badge-overdue',
            'President' => 'badge-accomplished',
            'Vice President' => 'badge-in-progress',
            'Secretary' => 'badge-todo',
            default => 'badge-planning'
          };
        ?>
          <tr style="<?= $is_self ? 'background-color: #f0f9ff;' : '' ?>">
            <td>
              <strong style="font-size: 0.95rem; color: var(--dark);"><?= htmlspecialchars($user['full_name']) ?></strong>
              <?php if ($is_self): ?>
                <span class="badge badge-in-progress" style="font-size: 0.65rem; margin-left: 0.25rem;">YOU</span>
              <?php endif; ?>
            </td>
            <td><code><?= htmlspecialchars($user['username']) ?></code></td>
            <td><a href="mailto:<?= htmlspecialchars($user['email']) ?>" style="font-size: 0.85rem;"><?= htmlspecialchars($user['email']) ?></a></td>
            <td><span class="badge badge-planning"><strong><?= htmlspecialchars($user['acronym']) ?></strong></span></td>
            <td><span class="badge <?= $role_badge ?>"><?= htmlspecialchars($user['role']) ?></span></td>
            <td><span style="font-size: 0.8rem; color: var(--gray-600);"><i class="fa-regular fa-clock"></i> <?= date('M d, Y', strtotime($user['created_at'])) ?></span></td>
            <td>
              <div style="display: flex; gap: 0.4rem;">
                <button class="btn btn-secondary btn-sm" onclick='openUserEditModal(<?= json_encode($user) ?>)' title="Edit User">
                  <i class="fa-solid fa-user-pen"></i> Edit
                </button>
                <?php if (!$is_self): ?>
                  <form method="POST" action="users.php" onsubmit="return confirm('Are you sure you want to delete user account \'<?= htmlspecialchars($user['username']) ?>\'?');" style="display: inline;">
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

<!-- Create User Modal -->
<div class="modal-backdrop" id="createUserModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> Add New User / Officer</h3>
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
            <select name="org_id" class="form-control" required>
              <option value="">-- Select Org --</option>
              <?php foreach ($orgs as $org): ?>
                <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Role <span style="color: red;">*</span></label>
            <select name="role" class="form-control" required>
              <option value="Officer">Officer</option>
              <option value="President">President</option>
              <option value="Vice President">Vice President</option>
              <option value="Secretary">Secretary</option>
              <option value="Treasurer">Treasurer</option>
              <option value="Admin">Admin</option>
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

<!-- Edit User Modal -->
<div class="modal-backdrop" id="editUserModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Edit User Account</h3>
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
            <select name="org_id" id="edit_org_id" class="form-control" required>
              <?php foreach ($orgs as $org): ?>
                <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Role <span style="color: red;">*</span></label>
            <select name="role" id="edit_role" class="form-control" required>
              <option value="Officer">Officer</option>
              <option value="President">President</option>
              <option value="Vice President">Vice President</option>
              <option value="Secretary">Secretary</option>
              <option value="Treasurer">Treasurer</option>
              <option value="Admin">Admin</option>
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

<script>
function openUserEditModal(user) {
  document.getElementById('edit_user_id').value = user.id;
  document.getElementById('edit_full_name').value = user.full_name;
  document.getElementById('edit_username').value = user.username;
  document.getElementById('edit_email').value = user.email;
  document.getElementById('edit_org_id').value = user.org_id;
  document.getElementById('edit_role').value = user.role;
  
  document.getElementById('editUserModal').classList.add('active');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
