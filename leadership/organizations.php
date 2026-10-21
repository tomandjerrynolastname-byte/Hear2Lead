<?php
// leadership/organizations.php - Dynamic Organization Management with Logo Upload
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Strict RBAC: Only Admin or Custom Role with Orgs Management permission can access organizations management
$org_role_perms = getRolePermissions($_SESSION['user_role'] ?? 'Officer', $pdo);
if (empty($org_role_perms['can_manage_orgs'])) {
    header("Location: dashboard.php?error=" . urlencode("Access denied. Only Administrators can access the Organizations Management page."));
    exit;
}

$success_msg = '';
$error_msg = '';

$upload_dir = __DIR__ . '/../assets/uploads/logos/';

// Ensure upload directory exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Function to handle image upload
function handleLogoUpload($file, $existing_logo = null) {
    global $upload_dir;
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return $existing_logo; // Keep existing if no file uploaded
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        throw new Exception("Invalid image file format. Allowed: JPG, PNG, GIF, WEBP, SVG.");
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception("Logo file size must be less than 5MB.");
    }

    // Generate unique filename
    $new_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Delete old logo if replacing
        if ($existing_logo && file_exists($upload_dir . $existing_logo)) {
            @unlink($upload_dir . $existing_logo);
        }
        return $new_filename;
    } else {
        throw new Exception("Failed to save uploaded logo image.");
    }
}

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create Organization
    if ($action === 'create_org') {
        $name = trim($_POST['name']);
        $acronym = strtoupper(trim($_POST['acronym']));
        $category = trim($_POST['category']) ?: 'Student Organization';
        $description = trim($_POST['description']);

        if (empty($name) || empty($acronym)) {
            $error_msg = "Organization Name and Acronym are required.";
        } else {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE acronym = ? OR name = ?");
                $check->execute([$acronym, $name]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "An organization with this Name or Acronym already exists.";
                } else {
                    $logo_filename = handleLogoUpload($_FILES['logo'] ?? null);

                    $stmt = $pdo->prepare("INSERT INTO organizations (name, acronym, logo, category, description) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $acronym, $logo_filename, $category, $description])) {
                        $success_msg = "New Organization '$acronym - $name' added successfully!";
                    } else {
                        $error_msg = "Failed to create organization.";
                    }
                }
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }
    }

    // Update Organization
    if ($action === 'update_org') {
        $org_id = intval($_POST['org_id']);
        $name = trim($_POST['name']);
        $acronym = strtoupper(trim($_POST['acronym']));
        $category = trim($_POST['category']) ?: 'Student Organization';
        $description = trim($_POST['description']);

        if (empty($name) || empty($acronym)) {
            $error_msg = "Organization Name and Acronym are required.";
        } else {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE (acronym = ? OR name = ?) AND id != ?");
                $check->execute([$acronym, $name, $org_id]);
                if ($check->fetchColumn() > 0) {
                    $error_msg = "Another organization already uses this Name or Acronym.";
                } else {
                    // Fetch existing logo
                    $curr_org = $pdo->query("SELECT logo FROM organizations WHERE id = $org_id")->fetch();
                    $existing_logo = $curr_org['logo'] ?? null;

                    $logo_filename = handleLogoUpload($_FILES['logo'] ?? null, $existing_logo);

                    $stmt = $pdo->prepare("UPDATE organizations SET name = ?, acronym = ?, logo = ?, category = ?, description = ? WHERE id = ?");
                    if ($stmt->execute([$name, $acronym, $logo_filename, $category, $description, $org_id])) {
                        // Update active session if editing current user's org
                        if ($org_id == $_SESSION['org_id']) {
                            $_SESSION['org_name'] = $name;
                            $_SESSION['org_acronym'] = $acronym;
                        }
                        $success_msg = "Organization updated successfully!";
                    } else {
                        $error_msg = "Failed to update organization.";
                    }
                }
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }
    }

    // Delete Organization
    if ($action === 'delete_org') {
        $org_id = intval($_POST['org_id']);
        
        if ($org_id == $_SESSION['org_id']) {
            $error_msg = "You cannot delete your own active organization while logged in!";
        } else {
            // Delete associated logo file
            $curr_org = $pdo->query("SELECT logo FROM organizations WHERE id = $org_id")->fetch();
            if ($curr_org && !empty($curr_org['logo']) && file_exists($upload_dir . $curr_org['logo'])) {
                @unlink($upload_dir . $curr_org['logo']);
            }

            $stmt = $pdo->prepare("DELETE FROM organizations WHERE id = ?");
            if ($stmt->execute([$org_id])) {
                $success_msg = "Organization deleted successfully.";
            } else {
                $error_msg = "Failed to delete organization.";
            }
        }
    }
}

// Search
$search = trim($_GET['q'] ?? '');
$query = "SELECT o.*, 
            (SELECT COUNT(*) FROM users WHERE org_id = o.id) as user_count,
            (SELECT COUNT(*) FROM projects WHERE org_id = o.id) as project_count,
            (SELECT COUNT(*) FROM accomplishments WHERE org_id = o.id) as accomplishment_count,
            (SELECT COUNT(*) FROM feedbacks WHERE org_id = o.id) as feedback_count
          FROM organizations o ";

if (!empty($search)) {
    $query .= "WHERE o.name LIKE " . $pdo->quote("%$search%") . " OR o.acronym LIKE " . $pdo->quote("%$search%") . " OR o.category LIKE " . $pdo->quote("%$search%") . " ";
}
$query .= "ORDER BY o.name ASC";

$organizations = $pdo->query($query)->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-sitemap" style="color: var(--secondary);"></i> Organization Management</h1>
    <p class="page-subtitle">Dynamically manage school organizations, custom logos, councils, academic societies, and clubs.</p>
  </div>

  <button class="btn btn-primary" data-modal-target="createOrgModal">
    <i class="fa-solid fa-plus-circle"></i> Add New Organization
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

<!-- Search Bar -->
<div class="filter-bar">
  <form method="GET" action="organizations.php" style="display: flex; gap: 1rem; align-items: center; width: 100%;">
    <div style="flex: 1; position: relative;">
      <input type="text" name="q" class="form-control" placeholder="Search organization by name, acronym, or category..." value="<?= htmlspecialchars($search) ?>" style="padding-left: 2.25rem;">
      <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
    </div>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i> Search</button>
    <?php if (!empty($search)): ?>
      <a href="organizations.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Organizations Grid -->
<div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));">
  <?php if (empty($organizations)): ?>
    <div class="panel" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--gray-500);">
      No student organizations found matching your search.
    </div>
  <?php else: ?>
    <?php foreach ($organizations as $org): 
      $is_my_org = ($org['id'] == $_SESSION['org_id']);
      $logo_url = !empty($org['logo']) && file_exists($upload_dir . $org['logo']) ? '../assets/uploads/logos/' . $org['logo'] : null;
    ?>
      <div class="card" style="<?= $is_my_org ? 'border: 2px solid var(--primary);' : '' ?>">
        <div class="card-header">
          <div style="display: flex; align-items: center; gap: 0.85rem;">
            <?php if ($logo_url): ?>
              <img src="<?= $logo_url ?>" alt="<?= htmlspecialchars($org['acronym']) ?> Logo" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius); border: 1px solid var(--gray-300); box-shadow: var(--shadow-sm);">
            <?php else: ?>
              <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);">
                <?= htmlspecialchars($org['acronym']) ?>
              </div>
            <?php endif; ?>

            <div>
              <span class="badge badge-in-progress" style="font-size: 0.7rem;"><?= htmlspecialchars($org['category']) ?></span>
              <?php if ($is_my_org): ?>
                <span class="badge badge-accomplished" style="font-size: 0.7rem; margin-left: 0.25rem;">YOUR ORG</span>
              <?php endif; ?>
              <h3 class="card-title" style="font-size: 1.1rem; margin-top: 0.25rem;"><?= htmlspecialchars($org['name']) ?></h3>
            </div>
          </div>
        </div>

        <div class="card-body">
          <p style="font-size: 0.88rem; color: var(--gray-600); margin-bottom: 1rem; min-height: 42px;">
            <?= htmlspecialchars($org['description'] ?: 'No description provided.') ?>
          </p>

          <!-- Metrics summary for this organization -->
          <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; background: var(--gray-100); padding: 0.75rem; border-radius: var(--radius-sm); text-align: center; font-size: 0.75rem;">
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--primary);"><?= $org['user_count'] ?></div>
              <div style="color: var(--gray-600);">Officers</div>
            </div>
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--secondary);"><?= $org['project_count'] ?></div>
              <div style="color: var(--gray-600);">Projects</div>
            </div>
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--warning);"><?= $org['accomplishment_count'] ?></div>
              <div style="color: var(--gray-600);">Achieved</div>
            </div>
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--accent);"><?= $org['feedback_count'] ?></div>
              <div style="color: var(--gray-600);">Feedbacks</div>
            </div>
          </div>
        </div>

        <div class="card-footer">
          <span style="font-size: 0.75rem; color: var(--gray-500);"><i class="fa-regular fa-calendar"></i> Added: <?= date('M Y', strtotime($org['created_at'])) ?></span>
          <div style="display: flex; gap: 0.4rem;">
            <button class="btn btn-secondary btn-sm" onclick='openOrgEditModal(<?= json_encode($org) ?>)' title="Edit Organization">
              <i class="fa-solid fa-pen"></i> Edit
            </button>
            <?php if (!$is_my_org): ?>
              <form method="POST" action="organizations.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete organization \'<?= htmlspecialchars($org['name'], ENT_QUOTES) ?>\'? All associated officers and records will also be removed!', 'Delete Organization?');" style="display: inline;">
                <input type="hidden" name="action" value="delete_org">
                <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Delete Organization"><i class="fa-solid fa-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Create Organization Modal -->
<div class="modal-backdrop" id="createOrgModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-plus-circle" style="color: var(--primary);"></i> Add New Organization</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="organizations.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create_org">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Organization Name <span style="color: red;">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="e.g. Arts & Cultural Society" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Acronym <span style="color: red;">*</span></label>
            <input type="text" name="acronym" class="form-control" placeholder="e.g. ACS" required style="text-transform: uppercase;">
          </div>

          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category" class="form-control">
              <option value="Student Council">Student Council / Government</option>
              <option value="Academic">Academic Club</option>
              <option value="Special Interest">Special Interest</option>
              <option value="Sports & Recreation">Sports & Recreation</option>
              <option value="Arts & Culture">Arts & Culture</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Organization Logo Image (Optional)</label>
          <input type="file" name="logo" class="form-control" accept="image/*">
          <small style="color: var(--gray-500); font-size: 0.75rem;">Supported: JPG, PNG, WEBP, SVG (Max 5MB)</small>
        </div>

        <div class="form-group">
          <label class="form-label">Description / Mission Statement</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Brief statement about the organization's purpose..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Organization</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Organization Modal -->
<div class="modal-backdrop" id="editOrgModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Organization Details</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="organizations.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_org">
      <input type="hidden" name="org_id" id="edit_org_id">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Organization Name <span style="color: red;">*</span></label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Acronym <span style="color: red;">*</span></label>
            <input type="text" name="acronym" id="edit_acronym" class="form-control" required style="text-transform: uppercase;">
          </div>

          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category" id="edit_category" class="form-control">
              <option value="Student Council">Student Council / Government</option>
              <option value="Academic">Academic Club</option>
              <option value="Special Interest">Special Interest</option>
              <option value="Sports & Recreation">Sports & Recreation</option>
              <option value="Arts & Culture">Arts & Culture</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Change / Upload New Logo</label>
          <input type="file" name="logo" class="form-control" accept="image/*">
          <small style="color: var(--gray-500); font-size: 0.75rem;">Leave blank to keep existing logo.</small>
        </div>

        <div class="form-group">
          <label class="form-label">Description / Mission Statement</label>
          <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
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
function openOrgEditModal(org) {
  document.getElementById('edit_org_id').value = org.id;
  document.getElementById('edit_name').value = org.name;
  document.getElementById('edit_acronym').value = org.acronym;
  document.getElementById('edit_category').value = org.category;
  document.getElementById('edit_description').value = org.description || '';
  
  document.getElementById('editOrgModal').classList.add('active');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
