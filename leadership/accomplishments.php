<?php
// leadership/accomplishments.php - Manage Accomplishments with Multiple Images & PDFs
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_user_name = $_SESSION['user_name'] ?? 'Officer';
$current_role = $_SESSION['user_role'] ?? 'Officer';
$role_perms = getRolePermissions($current_role, $pdo);
$is_admin = !empty($role_perms['is_admin']);
if (empty($role_perms['can_manage_accomplishments'])) {
    header("Location: dashboard.php?error=" . urlencode("Access denied. Your role ($current_role) does not have access to Accomplishments."));
    exit;
}
$current_org_id = intval($_SESSION['org_id']);
$filter_org = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;
$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'logs') ? 'logs' : 'posts';

$success_msg = '';
$error_msg = '';

$upload_dir = __DIR__ . '/../assets/uploads/accomplishments/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/**
 * Parse and normalize attachments array from an accomplishment record
 */
function getAccomplishmentAttachments(array $acc, string $upload_dir): array {
    $items = [];
    if (!empty($acc['attachments_json'])) {
        $decoded = json_decode($acc['attachments_json'], true);
        if (is_array($decoded)) {
            $items = $decoded;
        }
    }
    if (empty($items) && !empty($acc['attachment_file'])) {
        $items[] = [
            'file' => $acc['attachment_file'],
            'type' => $acc['attachment_type'] ?? '',
            'name' => $acc['attachment_name'] ?? $acc['attachment_file']
        ];
    }

    $valid = [];
    foreach ($items as $item) {
        $file = trim($item['file'] ?? '');
        if ($file === '' || !file_exists($upload_dir . $file)) {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = !empty($item['type']) ? $item['type'] : (($ext === 'pdf') ? 'pdf' : 'image');
        $name = !empty($item['name']) ? $item['name'] : $file;
        $valid[] = [
            'file' => $file,
            'type' => $type,
            'name' => $name
        ];
    }
    return $valid;
}

/**
 * Handle multiple image & PDF uploads, plus removal of selected existing attachments
 */
function handleMultipleAccomplishmentAttachments(?array $files, array $existing_attachments = [], array $removed_files = []): array {
    global $upload_dir;

    $removed_lookup = array_flip(array_map('strval', $removed_files));
    $final_attachments = [];

    // 1. Keep existing attachments that were not marked for removal; delete removed ones from disk
    foreach ($existing_attachments as $att) {
        $f = $att['file'] ?? '';
        if ($f === '') continue;
        if (isset($removed_lookup[$f])) {
            if (file_exists($upload_dir . $f)) {
                @unlink($upload_dir . $f);
            }
        } else {
            $final_attachments[] = $att;
        }
    }

    // 2. Process newly uploaded files (supports multiple)
    if ($files && isset($files['name'])) {
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmp_names = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $pdf_extensions = ['pdf'];
        $allowed_extensions = array_merge($image_extensions, $pdf_extensions);

        $count = count($names);
        for ($i = 0; $i < $count; $i++) {
            $err = $errors[$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error on file \"" . htmlspecialchars($names[$i] ?? '') . "\" (Code: " . intval($err) . ").");
            }

            $original_name = basename($names[$i]);
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_extensions, true)) {
                throw new Exception("Invalid format for \"$original_name\". Allowed: JPG, PNG, GIF, WEBP, and PDF.");
            }

            if (($sizes[$i] ?? 0) > 15 * 1024 * 1024) {
                throw new Exception("File \"$original_name\" exceeds the 15MB size limit.");
            }

            $att_type = in_array($file_ext, $pdf_extensions, true) ? 'pdf' : 'image';
            $new_filename = 'acc_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_ext;
            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_names[$i], $target_path)) {
                $final_attachments[] = [
                    'file' => $new_filename,
                    'type' => $att_type,
                    'name' => $original_name
                ];
            } else {
                throw new Exception("Failed to save uploaded file \"$original_name\".");
            }
        }
    }

    return $final_attachments;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_accomplishment') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $impact_summary = trim($_POST['impact_summary'] ?? '');
        $badge_tag = trim($_POST['badge_tag'] ?? '') ?: 'Completed Project';
        $date_accomplished = $_POST['date_accomplished'] ?? '';
        $acc_org_id = $is_admin ? (!empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id) : $current_org_id;

        if (empty($title) || empty($date_accomplished)) {
            $error_msg = "Title and date accomplished are required.";
        } else {
            try {
                $attachments = handleMultipleAccomplishmentAttachments($_FILES['attachments'] ?? null);
                $first = $attachments[0] ?? ['file' => null, 'type' => null, 'name' => null];
                $attachments_json = !empty($attachments) ? json_encode($attachments) : null;

                $stmt = $pdo->prepare("INSERT INTO accomplishments (org_id, title, description, impact_summary, badge_tag, attachment_file, attachment_type, attachment_name, attachments_json, date_accomplished) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$acc_org_id, $title, $description, $impact_summary, $badge_tag, $first['file'], $first['type'], $first['name'], $attachments_json, $date_accomplished])) {
                    $new_acc_id = intval($pdo->lastInsertId());
                    $att_count = count($attachments);
                    $log_details = "Accomplishment published with title \"$title\", tag '$badge_tag', date accomplished " . date('M d, Y', strtotime($date_accomplished)) . " ($att_count attachment(s)).";
                    $log_stmt = $pdo->prepare("INSERT INTO accomplishment_logs (acc_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, 'Created', ?)");
                    $log_stmt->execute([$new_acc_id, $current_user_id, $current_user_name, $current_role, $log_details]);

                    $success_msg = "Accomplishment posted and logged successfully! Visible on Student Portal.";
                } else {
                    $error_msg = "Failed to save accomplishment.";
                }
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }
    }

    if ($action === 'update_accomplishment') {
        $acc_id = intval($_POST['acc_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $impact_summary = trim($_POST['impact_summary'] ?? '');
        $badge_tag = trim($_POST['badge_tag'] ?? '') ?: 'Completed Project';
        $date_accomplished = $_POST['date_accomplished'] ?? '';
        $removed_files_raw = $_POST['removed_files'] ?? '[]';
        $removed_files = json_decode($removed_files_raw, true);
        if (!is_array($removed_files)) {
            $removed_files = [];
        }

        if ($acc_id <= 0 || empty($title) || empty($date_accomplished)) {
            $error_msg = "Title and date accomplished are required.";
        } else {
            try {
                if ($is_admin) {
                    $curr_stmt = $pdo->prepare("SELECT * FROM accomplishments WHERE id = ?");
                    $curr_stmt->execute([$acc_id]);
                } else {
                    $curr_stmt = $pdo->prepare("SELECT * FROM accomplishments WHERE id = ? AND org_id = ?");
                    $curr_stmt->execute([$acc_id, $current_org_id]);
                }
                $curr_acc = $curr_stmt->fetch();

                if (!$curr_acc) {
                    $error_msg = "Accomplishment post not found or access denied.";
                } else {
                    $existing_attachments = getAccomplishmentAttachments($curr_acc, $upload_dir);
                    $attachments = handleMultipleAccomplishmentAttachments(
                        $_FILES['attachments'] ?? null,
                        $existing_attachments,
                        $removed_files
                    );
                    $first = $attachments[0] ?? ['file' => null, 'type' => null, 'name' => null];
                    $attachments_json = !empty($attachments) ? json_encode($attachments) : null;

                    if ($is_admin) {
                        $acc_org_id = !empty($_POST['org_id']) ? intval($_POST['org_id']) : $current_org_id;
                        $stmt = $pdo->prepare("UPDATE accomplishments SET org_id = ?, title = ?, description = ?, impact_summary = ?, badge_tag = ?, attachment_file = ?, attachment_type = ?, attachment_name = ?, attachments_json = ?, date_accomplished = ? WHERE id = ?");
                        $executed = $stmt->execute([$acc_org_id, $title, $description, $impact_summary, $badge_tag, $first['file'], $first['type'], $first['name'], $attachments_json, $date_accomplished, $acc_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE accomplishments SET title = ?, description = ?, impact_summary = ?, badge_tag = ?, attachment_file = ?, attachment_type = ?, attachment_name = ?, attachments_json = ?, date_accomplished = ? WHERE id = ? AND org_id = ?");
                        $executed = $stmt->execute([$title, $description, $impact_summary, $badge_tag, $first['file'], $first['type'], $first['name'], $attachments_json, $date_accomplished, $acc_id, $current_org_id]);
                    }

                    if ($executed) {
                        $changes = [];
                        if ($curr_acc['title'] !== $title) {
                            $changes[] = "Title changed from \"{$curr_acc['title']}\" to \"$title\"";
                        }
                        if (($curr_acc['description'] ?? '') !== $description) {
                            $changes[] = "Description updated";
                        }
                        if (($curr_acc['impact_summary'] ?? '') !== $impact_summary) {
                            $changes[] = "Impact summary updated";
                        }
                        if (($curr_acc['badge_tag'] ?? '') !== $badge_tag) {
                            $changes[] = "Tag changed from '{$curr_acc['badge_tag']}' to '$badge_tag'";
                        }
                        if (($curr_acc['date_accomplished'] ?? '') !== $date_accomplished) {
                            $changes[] = "Date accomplished updated to " . date('M d, Y', strtotime($date_accomplished));
                        }
                        if (count($existing_attachments) !== count($attachments) || !empty($removed_files)) {
                            $changes[] = "Attachments updated (" . count($attachments) . " file(s) attached)";
                        }
                        $log_details = !empty($changes)
                            ? implode(' • ', $changes)
                            : "Accomplishment post saved (Tag: '$badge_tag', Date: " . date('M d, Y', strtotime($date_accomplished)) . ").";
                        $log_stmt = $pdo->prepare("INSERT INTO accomplishment_logs (acc_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, ?, ?, ?, 'Updated', ?)");
                        $log_stmt->execute([$acc_id, $current_user_id, $current_user_name, $current_role, $log_details]);

                        $success_msg = "Accomplishment post updated and logged successfully!";
                    } else {
                        $error_msg = "Failed to update accomplishment.";
                    }
                }
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }
    }

    if ($action === 'delete_accomplishment') {
        $acc_id = intval($_POST['acc_id'] ?? 0);
        if ($is_admin) {
            $curr_stmt = $pdo->prepare("SELECT * FROM accomplishments WHERE id = ?");
            $curr_stmt->execute([$acc_id]);
        } else {
            $curr_stmt = $pdo->prepare("SELECT * FROM accomplishments WHERE id = ? AND org_id = ?");
            $curr_stmt->execute([$acc_id, $current_org_id]);
        }
        $curr_acc = $curr_stmt->fetch();

        if ($curr_acc) {
            $existing_attachments = getAccomplishmentAttachments($curr_acc, $upload_dir);
            foreach ($existing_attachments as $att) {
                if (!empty($att['file']) && file_exists($upload_dir . $att['file'])) {
                    @unlink($upload_dir . $att['file']);
                }
            }
            if ($is_admin) {
                $stmt = $pdo->prepare("DELETE FROM accomplishments WHERE id = ?");
                $stmt->execute([$acc_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM accomplishments WHERE id = ? AND org_id = ?");
                $stmt->execute([$acc_id, $current_org_id]);
            }
            $success_msg = "Accomplishment deleted.";
        }
    }
}

// Fetch organizations for Admin filter
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

// Build query
$where = ["1=1"];
$params = [];
if ($filter_org > 0) {
    $where[] = "a.org_id = ?";
    $params[] = $filter_org;
}
$where_sql = implode(" AND ", $where);

$query = "SELECT a.*, o.acronym as org_acronym, o.name as org_name 
          FROM accomplishments a 
          JOIN organizations o ON a.org_id = o.id 
          WHERE $where_sql 
          ORDER BY a.date_accomplished DESC, a.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$accomplishments = $stmt->fetchAll();

// Normalize attachments array on each accomplishment for both PHP and JS
foreach ($accomplishments as &$acc_row) {
    $acc_row['attachments'] = getAccomplishmentAttachments($acc_row, $upload_dir);
}
unset($acc_row);

// Fetch Accomplishment Activity Logs
$acc_logs_by_acc = [];
$all_acc_logs = [];
if (!empty($accomplishments)) {
    $acc_ids = array_map(function($a) { return intval($a['id']); }, $accomplishments);
    $placeholders = implode(',', array_fill(0, count($acc_ids), '?'));
    $stmt_al = $pdo->prepare("SELECT al.*, a.title as acc_title, a.badge_tag, a.date_accomplished, o.acronym as org_acronym
                              FROM accomplishment_logs al
                              JOIN accomplishments a ON al.acc_id = a.id
                              LEFT JOIN organizations o ON a.org_id = o.id
                              WHERE al.acc_id IN ($placeholders)
                              ORDER BY al.created_at DESC, al.id DESC");
    $stmt_al->execute($acc_ids);
    foreach ($stmt_al->fetchAll() as $log) {
        $aid = intval($log['acc_id']);
        $log['formatted_time'] = date('M d, Y • g:i:s A', strtotime($log['created_at']));
        $acc_logs_by_acc[$aid][] = $log;
        $all_acc_logs[] = $log;
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishment and Document Records</h1>
    <p class="page-subtitle">
      <?php if ($is_admin): ?>
        Publish major achievements, multiple activity photos, PDF documentation, and milestone accomplishments <?= $filter_org > 0 ? 'for selected organization' : 'across all student organizations' ?>.
      <?php else: ?>
        Publish major achievements, multiple activity photos, PDF documentation, and milestone accomplishments for <strong><?= htmlspecialchars($_SESSION['org_acronym']) ?></strong>.
      <?php endif; ?>
    </p>
  </div>

  <button class="btn btn-primary" data-modal-target="createAccModal">
    <i class="fa-solid fa-plus"></i> Post Accomplishment
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

<?php if ($is_admin): ?>
  <div class="filter-bar" style="margin-bottom: 1.5rem;">
    <form method="GET" action="accomplishments.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <input type="hidden" name="tab" id="accFilterTabInput" value="<?= htmlspecialchars($active_tab) ?>">
      <div class="filter-group">
        <label class="form-label" style="margin: 0;">Organization Filter:</label>
        <select name="org_id" class="form-control" onchange="this.form.submit()">
          <option value="0">All Organizations</option>
          <?php foreach ($all_orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $filter_org == $o['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($filter_org > 0): ?>
        <a href="accomplishments.php?tab=<?= htmlspecialchars($active_tab) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> View All</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<!-- Tab Panel Navigation -->
<div class="user-tabs-nav">
  <button type="button" class="user-tab-btn <?= $active_tab === 'posts' ? 'active' : '' ?>" onclick="switchAccTab('posts')">
    <i class="fa-solid fa-trophy"></i>
    <span>Accomplishment Posts</span>
    <span class="user-tab-badge"><?= count($accomplishments) ?></span>
  </button>
  <button type="button" class="user-tab-btn <?= $active_tab === 'logs' ? 'active' : '' ?>" onclick="switchAccTab('logs')">
    <i class="fa-solid fa-clock-rotate-left"></i>
    <span>Accomplishment Activity Logs</span>
    <span class="user-tab-badge"><?= count($all_acc_logs) ?></span>
  </button>
</div>

<!-- TAB 1: Accomplishment Cards Grid -->
<div class="user-tab-pane <?= $active_tab === 'posts' ? 'active' : '' ?>" id="tab-posts">
<div class="card-grid">
  <?php if (empty($accomplishments)): ?>
    <div class="panel" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--gray-500);">
      <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; color: var(--gray-400); margin-bottom: 0.75rem; display: block;"></i>
      No accomplishments posted yet. Click <strong>"Post Accomplishment"</strong> to share achievements, multiple photos, or PDF documents with students!
    </div>
  <?php else: ?>
    <?php foreach ($accomplishments as $acc):
      $atts = $acc['attachments'] ?? [];
      $img_atts = array_values(array_filter($atts, fn($a) => $a['type'] === 'image'));
      $pdf_atts = array_values(array_filter($atts, fn($a) => $a['type'] === 'pdf'));
      $img_count = count($img_atts);
      $pdf_count = count($pdf_atts);
      $acc_log_count = count($acc_logs_by_acc[intval($acc['id'])] ?? []);
    ?>
      <div class="card">
        <div class="card-header">
          <div style="width: 100%;">
            <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
              <span class="badge badge-accomplished"><?= htmlspecialchars($acc['badge_tag']) ?></span>
              <?php if ($is_admin && $filter_org === 0): ?>
                <span class="badge badge-planning"><strong><?= htmlspecialchars($acc['org_acronym']) ?></strong></span>
              <?php endif; ?>
              <div style="margin-left: auto; display: flex; gap: 0.35rem; flex-wrap: wrap;">
                <?php if ($img_count > 0): ?>
                  <span class="badge badge-in-progress"><i class="fa-regular fa-images"></i> <?= $img_count ?> <?= $img_count === 1 ? 'Image' : 'Images' ?></span>
                <?php endif; ?>
                <?php if ($pdf_count > 0): ?>
                  <span class="badge badge-overdue" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;"><i class="fa-solid fa-file-pdf"></i> <?= $pdf_count ?> <?= $pdf_count === 1 ? 'PDF' : 'PDFs' ?></span>
                <?php endif; ?>
              </div>
            </div>
            <h3 class="card-title" style="margin-top: 0.5rem;"><?= htmlspecialchars($acc['title']) ?></h3>
          </div>
        </div>

        <?php if ($img_count > 0):
          $cover_url = '../assets/uploads/accomplishments/' . rawurlencode($img_atts[0]['file']);
        ?>
          <div onclick="openViewAccModal(<?= intval($acc['id']) ?>)" style="margin-bottom: 0.85rem; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--gray-200); cursor: pointer; position: relative; background: var(--gray-100); height: 185px;">
            <img src="<?= htmlspecialchars($cover_url) ?>" alt="<?= htmlspecialchars($acc['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
            <?php if ($img_count > 1): ?>
              <span style="position: absolute; bottom: 8px; right: 8px; background: rgba(15, 23, 42, 0.78); color: #fff; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 999px; backdrop-filter: blur(4px);">
                <i class="fa-regular fa-images"></i> +<?= $img_count - 1 ?> more <?= ($img_count - 1) === 1 ? 'photo' : 'photos' ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($pdf_count > 0): ?>
          <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 0.85rem;">
            <?php foreach (array_slice($pdf_atts, 0, 2) as $pdf_item):
              $pdf_url = '../assets/uploads/accomplishments/' . rawurlencode($pdf_item['file']);
            ?>
              <div style="padding: 0.55rem 0.75rem; background: #fff5f5; border: 1px solid #fecaca; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: space-between; gap: 0.6rem;">
                <div style="display: flex; align-items: center; gap: 0.55rem; min-width: 0;">
                  <i class="fa-solid fa-file-pdf" style="font-size: 1.25rem; color: #dc2626; flex-shrink: 0;"></i>
                  <span style="font-size: 0.8rem; font-weight: 700; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($pdf_item['name']) ?>">
                    <?= htmlspecialchars($pdf_item['name']) ?>
                  </span>
                </div>
                <a href="<?= htmlspecialchars($pdf_url) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="flex-shrink: 0; font-size: 0.72rem; padding: 0.22rem 0.55rem;" title="Open PDF in New Tab">
                  <i class="fa-solid fa-arrow-up-right-from-square"></i> Open
                </a>
              </div>
            <?php endforeach; ?>
            <?php if ($pdf_count > 2): ?>
              <div onclick="openViewAccModal(<?= intval($acc['id']) ?>)" style="font-size: 0.75rem; font-weight: 700; color: var(--primary); cursor: pointer; text-align: right;">
                +<?= $pdf_count - 2 ?> more PDF <?= ($pdf_count - 2) === 1 ? 'document' : 'documents' ?> (Click View)
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
        <div class="card-body">
          <p style="font-size: 0.9rem; color: var(--gray-700);">
            <?= nl2br(htmlspecialchars(mb_strimwidth($acc['description'], 0, 160, '...'))) ?>
          </p>
        </div>

        <?php if (!empty($acc['impact_summary'])): ?>
          <div style="background: var(--gray-100); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--dark); margin-bottom: 1rem;">
            <strong><i class="fa-solid fa-chart-line" style="color: var(--success);"></i> Impact:</strong> <?= htmlspecialchars($acc['impact_summary']) ?>
          </div>
        <?php endif; ?>

        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <span><i class="fa-regular fa-calendar"></i> Date: <strong><?= date('M d, Y', strtotime($acc['date_accomplished'])) ?></strong></span>
          <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openViewAccModal(<?= intval($acc['id']) ?>)" title="View Post Details">
              <i class="fa-solid fa-eye"></i> View
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="openAccLogsModal(<?= intval($acc['id']) ?>)" title="View Accomplishment Activity Logs">
              <i class="fa-solid fa-clock-rotate-left"></i> Logs (<?= $acc_log_count ?>)
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="openEditAccModal(<?= intval($acc['id']) ?>)" title="Edit Post">
              <i class="fa-solid fa-pen"></i> Edit
            </button>
            <form method="POST" action="accomplishments.php" onsubmit="return confirmDeleteSwal(event, 'Are you sure you want to delete this accomplishment post and all its attachments?', 'Delete Accomplishment?');" style="display: inline; margin: 0;">
              <input type="hidden" name="action" value="delete_accomplishment">
              <input type="hidden" name="acc_id" value="<?= $acc['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Delete Accomplishment"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</div>

<!-- TAB 2: Accomplishment Activity Logs Pane -->
<div class="user-tab-pane <?= $active_tab === 'logs' ? 'active' : '' ?>" id="tab-logs">
  <div class="panel">
    <div class="panel-header">
      <span><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Accomplishment & Document Publication Logs</span>
      <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($all_acc_logs) ?> Total Entries</span>
    </div>

    <?php if (empty($all_acc_logs)): ?>
      <div style="text-align: center; padding: 2.5rem; color: var(--gray-500);">
        <i class="fa-solid fa-clock-rotate-left" style="font-size: 2.2rem; color: var(--gray-400); margin-bottom: 0.5rem; display: block;"></i>
        No accomplishment activity logs recorded for the selected criteria.
      </div>
    <?php else: ?>
      <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
        <table class="table">
          <thead>
            <tr>
              <?php if ($is_admin && $filter_org === 0): ?>
                <th>Org</th>
              <?php endif; ?>
              <th>Accomplishment Title</th>
              <th>Action</th>
              <th>Logged By</th>
              <th>Activity & Document Details</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($all_acc_logs as $log):
              $action_badge = $log['action_type'] === 'Created' ? 'badge-accomplished' : 'badge-in-progress';
            ?>
              <tr>
                <?php if ($is_admin && $filter_org === 0): ?>
                  <td><span class="badge badge-planning"><strong><?= htmlspecialchars($log['org_acronym'] ?? 'ORG') ?></strong></span></td>
                <?php endif; ?>
                <td><strong style="color: var(--dark);"><?= htmlspecialchars($log['acc_title'] ?? ('Accomplishment #' . $log['acc_id'])) ?></strong></td>
                <td><span class="badge <?= $action_badge ?>"><?= htmlspecialchars($log['action_type']) ?></span></td>
                <td>
                  <div style="font-weight: 700; color: var(--dark); font-size: 0.88rem;"><?= htmlspecialchars($log['user_name']) ?></div>
                  <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($log['user_role']) ?></div>
                </td>
                <td style="font-size: 0.86rem; color: var(--gray-800); max-width: 420px;"><?= htmlspecialchars($log['log_details']) ?></td>
                <td style="white-space: nowrap; font-size: 0.8rem; color: var(--gray-600);">
                  <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($log['formatted_time']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- View Accomplishment Activity Logs Modal -->
<div class="modal-backdrop" id="viewAccLogsModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Accomplishment Activity Logs
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="background: var(--gray-100); padding: 0.85rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; border: 1px solid var(--gray-200);">
        <div style="font-size: 0.78rem; color: var(--gray-500); text-transform: uppercase; font-weight: 700;">Accomplishment Post</div>
        <div id="log_modal_acc_title" style="font-size: 1.05rem; font-weight: 800; color: var(--dark); margin-top: 0.1rem;"></div>
        <div id="log_modal_acc_meta" style="font-size: 0.82rem; color: var(--gray-600); margin-top: 0.25rem;"></div>
      </div>
      <div id="accLogsTimelineContainer" style="max-height: 360px; overflow-y: auto; padding-right: 0.25rem;"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- View Accomplishment Complete Details Modal -->
<div class="modal-backdrop" id="viewAccModal">
  <div class="modal-card" style="max-width: 780px;">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishment Post Details
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.65rem;">
        <span class="badge badge-planning" id="view_acc_org_acronym"></span>
        <span class="badge badge-accomplished" id="view_acc_badge_tag"></span>
        <span class="badge badge-in-progress" id="view_acc_img_count_badge" style="display: none;"></span>
        <span class="badge badge-overdue" id="view_acc_pdf_count_badge" style="display: none; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;"></span>
      </div>

      <h3 id="view_acc_title" style="font-size: 1.35rem; font-weight: 800; color: var(--dark); margin-bottom: 0.35rem;"></h3>
      <div id="view_acc_org_name" style="font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 1rem;"></div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.15rem;">
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Date Accomplished</div>
          <div id="view_acc_date" style="font-size: 0.92rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
        <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem;">
          <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500);">Published On</div>
          <div id="view_acc_created" style="font-size: 0.92rem; font-weight: 700; color: var(--dark); margin-top: 0.2rem;"></div>
        </div>
      </div>

      <!-- Multiple Images Gallery Section -->
      <div id="view_acc_images_section" style="display: none; margin-bottom: 1.25rem;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.45rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <span id="view_acc_images_heading"><i class="fa-regular fa-images" style="color: var(--primary);"></i> Attached Photos</span>
          <div style="display: flex; gap: 0.4rem;">
            <a id="view_acc_active_img_open" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.25rem 0.65rem;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Full Image
            </a>
            <a id="view_acc_active_img_download" href="#" download class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.25rem 0.65rem;">
              <i class="fa-solid fa-download"></i> Download
            </a>
          </div>
        </div>

        <div style="position: relative; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--gray-200); background: #0f172a; text-align: center;">
          <img id="view_acc_active_img" src="" alt="Accomplishment Photo" style="max-width: 100%; max-height: 440px; width: auto; height: auto; object-fit: contain; display: block; margin: 0 auto;">
          <button type="button" id="view_acc_img_prev" onclick="stepViewGallery(-1)" style="display: none; position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border-radius: 50%; border: none; background: rgba(255,255,255,0.9); color: var(--dark); cursor: pointer; box-shadow: var(--shadow); align-items: center; justify-content: center;">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <button type="button" id="view_acc_img_next" onclick="stepViewGallery(1)" style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border-radius: 50%; border: none; background: rgba(255,255,255,0.9); color: var(--dark); cursor: pointer; box-shadow: var(--shadow); align-items: center; justify-content: center;">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
          <div id="view_acc_img_caption" style="background: rgba(15, 23, 42, 0.75); color: #fff; font-size: 0.75rem; padding: 0.35rem 0.75rem; text-align: center;"></div>
        </div>

        <!-- Thumbnails Strip -->
        <div id="view_acc_thumbs_strip" style="display: none; gap: 0.5rem; overflow-x: auto; padding-top: 0.6rem; align-items: center;"></div>
      </div>

      <!-- Multiple PDF Documents Section -->
      <div id="view_acc_pdfs_section" style="display: none; margin-bottom: 1.25rem;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.45rem;">
          <i class="fa-solid fa-file-pdf" style="color: #dc2626;"></i> Attached PDF Documents
        </div>
        <div id="view_acc_pdfs_list" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem;"></div>
        <div id="view_acc_pdf_viewer_wrap" style="border-radius: var(--radius-sm); overflow: hidden; border: 1px solid #fecaca; background: #fff5f5;">
          <div style="padding: 0.55rem 0.9rem; background: #fef2f2; border-bottom: 1px solid #fecaca; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span id="view_acc_active_pdf_title" style="font-size: 0.82rem; font-weight: 700; color: var(--dark);"></span>
            <a id="view_acc_active_pdf_open" href="#" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 0.73rem; padding: 0.2rem 0.55rem;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in New Tab
            </a>
          </div>
          <iframe id="view_acc_pdf_frame" src="" style="width: 100%; height: 420px; border: none; background: #ffffff;"></iframe>
        </div>
      </div>

      <div style="margin-bottom: 1.15rem;">
        <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.4rem;">
          <i class="fa-solid fa-align-left" style="color: var(--primary);"></i> Full Description & Highlights
        </div>
        <div id="view_acc_description" style="background: var(--cream-light); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.95rem; color: var(--gray-800); line-height: 1.65; white-space: pre-wrap;"></div>
      </div>

      <div id="view_acc_impact_box" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-sm); padding: 0.85rem 1rem;">
        <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #047857; margin-bottom: 0.2rem;">
          <i class="fa-solid fa-chart-line"></i> Impact & Outcome Summary
        </div>
        <div id="view_acc_impact" style="font-size: 0.92rem; font-weight: 700; color: #065f46;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" id="view_acc_switch_edit_btn">
        <i class="fa-solid fa-pen-to-square"></i> Edit Post
      </button>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- Create Accomplishment Modal -->
<div class="modal-backdrop" id="createAccModal">
  <div class="modal-card" style="max-width: 660px;">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);"><i class="fa-solid fa-award" style="color: var(--warning);"></i> Post New Accomplishment</h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="accomplishments.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create_accomplishment">
      
      <div class="modal-body">
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <select name="org_id" class="form-control" required>
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $filter_org == $o['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" value="<?= $current_org_id ?>">
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label">Accomplishment Title <span style="color: red;">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Conducted Campus-wide Mental Health Summit" required>
        </div>

        <div class="form-group">
          <label class="form-label">Full Description <span style="color: red;">*</span></label>
          <textarea name="description" class="form-control" rows="4" placeholder="Detail what was accomplished, key highlights, and participants..." required></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Badge Tag</label>
            <input type="text" name="badge_tag" class="form-control" value="Completed Event" placeholder="e.g. Facility Upgrade, Campaign">
          </div>

          <div class="form-group">
            <label class="form-label">Date Accomplished <span style="color: red;">*</span></label>
            <input type="date" name="date_accomplished" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Impact Summary (Optional)</label>
          <input type="text" name="impact_summary" class="form-control" placeholder="e.g. 400+ Students Benefited, 120kg Recycled">
        </div>

        <!-- Multiple Images & PDFs Attachment Input -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label"><i class="fa-solid fa-paperclip" style="color: var(--primary);"></i> Attach Images & PDF Documents (Multiple Allowed)</label>
          <div style="border: 2px dashed var(--gray-300); border-radius: var(--radius-sm); padding: 1rem; background: var(--cream-light); text-align: center;">
            <input type="file" name="attachments[]" id="create_acc_attachments" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/*,application/pdf" onchange="handleMultiFileSelect(this, 'create')" style="margin-bottom: 0.4rem;">
            <div style="font-size: 0.76rem; color: var(--gray-600);">
              Select one or multiple <strong>Images (JPG, PNG, GIF, WEBP)</strong> and <strong>PDF Documents</strong> • Max 15MB per file
            </div>

            <!-- Live Multi-File Preview Container -->
            <div id="create_acc_preview_wrap" style="display: none; margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--gray-200); text-align: left;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span id="create_acc_preview_count" style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; color: var(--primary);">Selected Files</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllPendingFiles('create')" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                  <i class="fa-solid fa-trash-can"></i> Clear All
                </button>
              </div>
              <div id="create_acc_preview_list" style="display: flex; flex-direction: column; gap: 0.45rem; max-height: 240px; overflow-y: auto;"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish Accomplishment</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Accomplishment Modal -->
<div class="modal-backdrop" id="editAccModal">
  <div class="modal-card" style="max-width: 660px;">
    <div class="modal-header">
      <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--dark);">
        <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Accomplishment Post
      </h3>
      <button class="close-btn" data-modal-close>&times;</button>
    </div>

    <form method="POST" action="accomplishments.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_accomplishment">
      <input type="hidden" name="acc_id" id="edit_acc_id">
      <input type="hidden" name="removed_files" id="edit_acc_removed_files" value="[]">

      <div class="modal-body">
        <?php if ($is_admin): ?>
          <div class="form-group">
            <label class="form-label">Organization <span style="color: red;">*</span></label>
            <select name="org_id" id="edit_acc_org_id" class="form-control" required>
              <?php foreach ($all_orgs as $o): ?>
                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="org_id" id="edit_acc_org_id" value="<?= $current_org_id ?>">
        <?php endif; ?>

        <div class="form-group">
          <label class="form-label">Accomplishment Title <span style="color: red;">*</span></label>
          <input type="text" name="title" id="edit_acc_title" class="form-control" required>
        </div>

        <div class="form-group">
          <label class="form-label">Full Description <span style="color: red;">*</span></label>
          <textarea name="description" id="edit_acc_description" class="form-control" rows="4" required></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div class="form-group">
            <label class="form-label">Badge Tag</label>
            <input type="text" name="badge_tag" id="edit_acc_badge_tag" class="form-control">
          </div>

          <div class="form-group">
            <label class="form-label">Date Accomplished <span style="color: red;">*</span></label>
            <input type="date" name="date_accomplished" id="edit_acc_date" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Impact Summary (Optional)</label>
          <input type="text" name="impact_summary" id="edit_acc_impact_summary" class="form-control">
        </div>

        <!-- Existing & New Multi-File Attachments Section -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label"><i class="fa-solid fa-paperclip" style="color: var(--primary);"></i> Attached Images & PDF Documents</label>

          <!-- Existing Attachments List -->
          <div id="edit_acc_existing_wrap" style="display: none; margin-bottom: 0.85rem; padding: 0.75rem; border: 1px solid var(--gray-200); border-radius: var(--radius-sm); background: var(--gray-50);">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--gray-600); margin-bottom: 0.5rem;">
              Current Attached Files
            </div>
            <div id="edit_acc_existing_list" style="display: flex; flex-direction: column; gap: 0.45rem; max-height: 220px; overflow-y: auto;"></div>
          </div>

          <!-- Add More Files Box -->
          <div style="border: 2px dashed var(--gray-300); border-radius: var(--radius-sm); padding: 1rem; background: var(--cream-light); text-align: center;">
            <input type="file" name="attachments[]" id="edit_acc_attachments" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/*,application/pdf" onchange="handleMultiFileSelect(this, 'edit')" style="margin-bottom: 0.4rem;">
            <div style="font-size: 0.76rem; color: var(--gray-600);">
              Add more <strong>Images (JPG, PNG, GIF, WEBP)</strong> or <strong>PDF Documents</strong> (Multiple allowed • Max 15MB each)
            </div>

            <!-- Live Multi-File Preview Container -->
            <div id="edit_acc_preview_wrap" style="display: none; margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--gray-200); text-align: left;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span id="edit_acc_preview_count" style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; color: var(--primary);">New Files to Upload</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="clearAllPendingFiles('edit')" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;">
                  <i class="fa-solid fa-trash-can"></i> Clear New Files
                </button>
              </div>
              <div id="edit_acc_preview_list" style="display: flex; flex-direction: column; gap: 0.45rem; max-height: 240px; overflow-y: auto;"></div>
            </div>
          </div>
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
const ALL_ACCOMPLISHMENTS = <?= json_encode($accomplishments) ?>;
const ACC_LOGS_MAP = <?= json_encode($acc_logs_by_acc) ?>;

function switchAccTab(tabName) {
  const btns = document.querySelectorAll('.user-tabs-nav .user-tab-btn');
  const panes = document.querySelectorAll('.user-tab-pane');
  btns.forEach(btn => btn.classList.remove('active'));
  panes.forEach(pane => pane.classList.remove('active'));

  const tabInput = document.getElementById('accFilterTabInput');
  if (tabInput) tabInput.value = tabName;

  if (tabName === 'logs') {
    if (btns[1]) btns[1].classList.add('active');
    const logPane = document.getElementById('tab-logs');
    if (logPane) logPane.classList.add('active');
  } else {
    if (btns[0]) btns[0].classList.add('active');
    const postPane = document.getElementById('tab-posts');
    if (postPane) postPane.classList.add('active');
  }

  const url = new URL(window.location.href);
  url.searchParams.set('tab', tabName);
  history.replaceState(null, '', url.toString());
}

function openAccLogsModal(accId) {
  const modal = document.getElementById('viewAccLogsModal');
  if (!modal) return;

  const acc = ALL_ACCOMPLISHMENTS.find(a => parseInt(a.id, 10) === parseInt(accId, 10));
  const titleEl = document.getElementById('log_modal_acc_title');
  const metaEl = document.getElementById('log_modal_acc_meta');
  const container = document.getElementById('accLogsTimelineContainer');

  if (acc) {
    titleEl.textContent = acc.title;
    metaEl.textContent = 'Tag: ' + (acc.badge_tag || 'Completed Event') + ' • Date Accomplished: ' + formatReadableDate(acc.date_accomplished);
  } else {
    titleEl.textContent = 'Accomplishment #' + accId;
    metaEl.textContent = '';
  }

  const logs = ACC_LOGS_MAP[accId] || [];
  if (logs.length === 0) {
    container.innerHTML = '<div style="text-align: center; padding: 1.5rem; color: var(--gray-500);">No activity logs recorded for this accomplishment yet.</div>';
  } else {
    const initialLimit = logs.length === 2 ? 1 : 2;
    const totalLogs = logs.length;
    const hiddenCount = Math.max(0, totalLogs - initialLimit);

    let html = `<div class="log-stepper-container"><div class="log-stepper" data-initial-limit="${initialLimit}">`;
    logs.forEach((log, index) => {
      let stepClass = '';
      let badgeClass = 'badge-in-progress';
      let iconClass = 'fa-pen';
      if (log.action_type === 'Created') {
        stepClass = 'step-success';
        badgeClass = 'badge-accomplished';
        iconClass = 'fa-plus';
      }

      const hiddenClass = index >= initialLimit ? 'log-step-hidden' : '';
      const lastVisibleClass = (index === initialLimit - 1 && totalLogs > initialLimit) ? 'log-step-last-visible' : '';

      html += `
        <div class="log-step ${stepClass} ${hiddenClass} ${lastVisibleClass}">
          <div class="log-step-node">
            <i class="fa-solid ${iconClass}"></i>
          </div>
          <div class="log-step-content">
            <div class="log-step-header">
              <div class="log-step-title">
                <span class="badge ${badgeClass}" style="font-size: 0.7rem;">${escapeHtml(log.action_type)}</span>
                <span>${escapeHtml(log.user_name)}</span>
                <span class="log-step-role">(${escapeHtml(log.user_role)})</span>
              </div>
              <div class="log-step-time">
                <i class="fa-regular fa-clock"></i> ${escapeHtml(log.formatted_time || log.created_at)}
              </div>
            </div>
            <div class="log-step-body">${escapeHtml(log.log_details)}</div>
          </div>
        </div>
      `;
    });
    html += '</div>';
    if (hiddenCount > 0) {
      html += `
        <div class="log-stepper-toggle-wrap">
          <button type="button" class="log-stepper-toggle-btn" data-expanded="false" onclick="toggleStepperLogs(this)">
            <i class="fa-solid fa-chevron-down"></i> Show More (${hiddenCount} more ${hiddenCount === 1 ? 'log' : 'logs'})
          </button>
        </div>
      `;
    }
    html += '</div>';
    container.innerHTML = html;
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

// Accumulator for multi-file inputs so users can select multiple times & remove individual files
const pendingFilesStore = {
  create: [],
  edit: []
};

// State for View Modal Image Gallery & PDF Viewer
let activeGalleryImages = [];
let activeGalleryIndex = 0;
let editExistingAttachments = [];
let editRemovedFiles = new Set();

function formatReadableDate(dateStr) {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

function formatFileSize(bytes) {
  if (bytes > 1024 * 1024) {
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
  }
  return (bytes / 1024).toFixed(1) + ' KB';
}

function syncInputFiles(mode) {
  const input = document.getElementById(mode + '_acc_attachments');
  if (!input || typeof DataTransfer === 'undefined') return;
  const dt = new DataTransfer();
  pendingFilesStore[mode].forEach(f => dt.items.add(f));
  input.files = dt.files;
}

function handleMultiFileSelect(input, mode) {
  if (!input.files || input.files.length === 0) return;
  const incoming = Array.from(input.files);

  incoming.forEach(file => {
    const exists = pendingFilesStore[mode].some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
    if (!exists) {
      pendingFilesStore[mode].push(file);
    }
  });

  syncInputFiles(mode);
  renderPendingFilesPreview(mode);
}

function removePendingFile(mode, idx) {
  pendingFilesStore[mode].splice(idx, 1);
  syncInputFiles(mode);
  renderPendingFilesPreview(mode);
}

function clearAllPendingFiles(mode) {
  pendingFilesStore[mode] = [];
  const input = document.getElementById(mode + '_acc_attachments');
  if (input) input.value = '';
  renderPendingFilesPreview(mode);
}

function renderPendingFilesPreview(mode) {
  const wrap = document.getElementById(mode + '_acc_preview_wrap');
  const list = document.getElementById(mode + '_acc_preview_list');
  const countEl = document.getElementById(mode + '_acc_preview_count');
  if (!wrap || !list) return;

  const files = pendingFilesStore[mode];
  if (!files || files.length === 0) {
    wrap.style.display = 'none';
    list.innerHTML = '';
    return;
  }

  wrap.style.display = 'block';
  if (countEl) {
    countEl.textContent = `${files.length} ${files.length === 1 ? 'File' : 'Files'} Selected`;
  }
  list.innerHTML = '';

  files.forEach((file, idx) => {
    const ext = file.name.split('.').pop().toLowerCase();
    const isPdf = (ext === 'pdf' || file.type === 'application/pdf');
    const sizeLabel = formatFileSize(file.size);
    const blobUrl = URL.createObjectURL(file);

    const row = document.createElement('div');
    row.style.cssText = 'display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; padding: 0.5rem 0.7rem; background: var(--white); border: 1px solid var(--gray-200); border-radius: 6px;';

    if (isPdf) {
      row.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.6rem; min-width: 0;">
          <i class="fa-solid fa-file-pdf" style="font-size: 1.45rem; color: #dc2626; flex-shrink: 0;"></i>
          <div style="min-width: 0;">
            <div style="font-size: 0.82rem; font-weight: 700; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(file.name)}</div>
            <div style="font-size: 0.7rem; color: var(--gray-600);">PDF Document • ${sizeLabel}</div>
          </div>
        </div>
        <div style="display: flex; gap: 0.35rem; flex-shrink: 0;">
          <a href="${blobUrl}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;">Preview</a>
          <button type="button" class="btn btn-danger btn-sm" onclick="removePendingFile('${mode}', ${idx})" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;" title="Remove"><i class="fa-solid fa-xmark"></i></button>
        </div>
      `;
    } else {
      row.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.6rem; min-width: 0;">
          <img src="${blobUrl}" alt="Preview" style="width: 46px; height: 38px; object-fit: cover; border-radius: 4px; border: 1px solid var(--gray-200); flex-shrink: 0;">
          <div style="min-width: 0;">
            <div style="font-size: 0.82rem; font-weight: 700; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(file.name)}</div>
            <div style="font-size: 0.7rem; color: var(--gray-600);">Image • ${sizeLabel}</div>
          </div>
        </div>
        <div style="display: flex; gap: 0.35rem; flex-shrink: 0;">
          <a href="${blobUrl}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;">View</a>
          <button type="button" class="btn btn-danger btn-sm" onclick="removePendingFile('${mode}', ${idx})" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;" title="Remove"><i class="fa-solid fa-xmark"></i></button>
        </div>
      `;
    }
    list.appendChild(row);
  });
}

// Render & toggle existing attachments in Edit Modal
function renderEditExistingAttachments() {
  const wrap = document.getElementById('edit_acc_existing_wrap');
  const list = document.getElementById('edit_acc_existing_list');
  const hiddenInput = document.getElementById('edit_acc_removed_files');
  if (!wrap || !list || !hiddenInput) return;

  hiddenInput.value = JSON.stringify(Array.from(editRemovedFiles));

  if (!editExistingAttachments || editExistingAttachments.length === 0) {
    wrap.style.display = 'none';
    list.innerHTML = '';
    return;
  }

  wrap.style.display = 'block';
  list.innerHTML = '';

  editExistingAttachments.forEach(att => {
    const isRemoved = editRemovedFiles.has(att.file);
    const url = '../assets/uploads/accomplishments/' + encodeURIComponent(att.file);
    const isPdf = (att.type === 'pdf');

    const row = document.createElement('div');
    row.style.cssText = `display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; padding: 0.5rem 0.7rem; background: ${isRemoved ? '#fef2f2' : 'var(--white)'}; border: 1px solid ${isRemoved ? '#fecaca' : 'var(--gray-200)'}; border-radius: 6px; opacity: ${isRemoved ? '0.65' : '1'};`;

    const thumbHtml = isPdf
      ? `<i class="fa-solid fa-file-pdf" style="font-size: 1.4rem; color: #dc2626; flex-shrink: 0;"></i>`
      : `<img src="${url}" alt="Thumb" style="width: 46px; height: 38px; object-fit: cover; border-radius: 4px; border: 1px solid var(--gray-200); flex-shrink: 0;">`;

    const actionBtnHtml = isRemoved
      ? `<button type="button" class="btn btn-secondary btn-sm" onclick="toggleRemoveExistingFile('${escapeHtml(att.file)}')" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;"><i class="fa-solid fa-rotate-left"></i> Undo</button>`
      : `<button type="button" class="btn btn-danger btn-sm" onclick="toggleRemoveExistingFile('${escapeHtml(att.file)}')" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;"><i class="fa-solid fa-trash-can"></i> Remove</button>`;

    row.innerHTML = `
      <div style="display: flex; align-items: center; gap: 0.6rem; min-width: 0;">
        ${thumbHtml}
        <div style="min-width: 0;">
          <div style="font-size: 0.82rem; font-weight: 700; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; ${isRemoved ? 'text-decoration: line-through;' : ''}">${escapeHtml(att.name)}</div>
          <div style="font-size: 0.7rem; color: ${isRemoved ? '#dc2626' : 'var(--gray-600)'}; font-weight: 600;">${isRemoved ? 'Marked for removal on save' : (isPdf ? 'PDF Document' : 'Image')}</div>
        </div>
      </div>
      <div style="display: flex; gap: 0.35rem; flex-shrink: 0;">
        <a href="${url}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;">Open</a>
        ${actionBtnHtml}
      </div>
    `;
    list.appendChild(row);
  });
}

function toggleRemoveExistingFile(filename) {
  if (editRemovedFiles.has(filename)) {
    editRemovedFiles.delete(filename);
  } else {
    editRemovedFiles.add(filename);
  }
  renderEditExistingAttachments();
}

// Gallery controls inside View Modal
function renderViewGalleryImage() {
  if (!activeGalleryImages.length) return;
  const current = activeGalleryImages[activeGalleryIndex];
  const url = '../assets/uploads/accomplishments/' + encodeURIComponent(current.file);

  const imgEl = document.getElementById('view_acc_active_img');
  const openBtn = document.getElementById('view_acc_active_img_open');
  const dlBtn = document.getElementById('view_acc_active_img_download');
  const captionEl = document.getElementById('view_acc_img_caption');
  const prevBtn = document.getElementById('view_acc_img_prev');
  const nextBtn = document.getElementById('view_acc_img_next');
  const thumbsStrip = document.getElementById('view_acc_thumbs_strip');

  imgEl.src = url;
  openBtn.href = url;
  dlBtn.href = url;
  dlBtn.setAttribute('download', current.name || current.file);
  captionEl.textContent = `${current.name || current.file} (${activeGalleryIndex + 1} of ${activeGalleryImages.length})`;

  if (activeGalleryImages.length > 1) {
    prevBtn.style.display = 'inline-flex';
    nextBtn.style.display = 'inline-flex';
    thumbsStrip.style.display = 'flex';
    thumbsStrip.innerHTML = activeGalleryImages.map((img, i) => {
      const tUrl = '../assets/uploads/accomplishments/' + encodeURIComponent(img.file);
      const isActive = (i === activeGalleryIndex);
      return `<img src="${tUrl}" alt="Thumb ${i + 1}" onclick="selectViewGalleryImage(${i})" style="width: 64px; height: 48px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid ${isActive ? 'var(--primary)' : 'var(--gray-300)'}; opacity: ${isActive ? '1' : '0.65'}; flex-shrink: 0; transition: all 0.15s ease;">`;
    }).join('');
  } else {
    prevBtn.style.display = 'none';
    nextBtn.style.display = 'none';
    thumbsStrip.style.display = 'none';
    thumbsStrip.innerHTML = '';
  }
}

function stepViewGallery(dir) {
  if (!activeGalleryImages.length) return;
  activeGalleryIndex = (activeGalleryIndex + dir + activeGalleryImages.length) % activeGalleryImages.length;
  renderViewGalleryImage();
}

function selectViewGalleryImage(idx) {
  activeGalleryIndex = idx;
  renderViewGalleryImage();
}

function previewPdfInModal(file, name) {
  const url = '../assets/uploads/accomplishments/' + encodeURIComponent(file);
  document.getElementById('view_acc_pdf_frame').src = url;
  document.getElementById('view_acc_active_pdf_title').innerHTML = '<i class="fa-solid fa-file-pdf" style="color: #dc2626;"></i> ' + escapeHtml(name || file);
  document.getElementById('view_acc_active_pdf_open').href = url;
}

function openViewAccModal(accId) {
  const modal = document.getElementById('viewAccModal');
  if (!modal) return;

  const acc = ALL_ACCOMPLISHMENTS.find(a => parseInt(a.id, 10) === parseInt(accId, 10));
  if (!acc) return;

  document.getElementById('view_acc_org_acronym').textContent = acc.org_acronym || '';
  document.getElementById('view_acc_badge_tag').textContent = acc.badge_tag || 'Completed Project';
  document.getElementById('view_acc_title').textContent = acc.title || '';
  document.getElementById('view_acc_org_name').innerHTML = '<i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> ' + escapeHtml(acc.org_name || acc.org_acronym || '');
  document.getElementById('view_acc_date').innerHTML = '<i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i> ' + formatReadableDate(acc.date_accomplished);
  document.getElementById('view_acc_created').innerHTML = '<i class="fa-regular fa-clock" style="color: var(--gray-500);"></i> ' + formatReadableDate(acc.created_at);
  document.getElementById('view_acc_description').textContent = acc.description || 'No description provided.';

  const atts = Array.isArray(acc.attachments) ? acc.attachments : [];
  const images = atts.filter(a => a.type === 'image');
  const pdfs = atts.filter(a => a.type === 'pdf');

  const imgBadge = document.getElementById('view_acc_img_count_badge');
  const pdfBadge = document.getElementById('view_acc_pdf_count_badge');
  const imagesSection = document.getElementById('view_acc_images_section');
  const pdfsSection = document.getElementById('view_acc_pdfs_section');

  // Images Gallery
  if (images.length > 0) {
    imgBadge.style.display = 'inline-flex';
    imgBadge.innerHTML = `<i class="fa-regular fa-images"></i> ${images.length} ${images.length === 1 ? 'Image' : 'Images'}`;
    imagesSection.style.display = 'block';
    document.getElementById('view_acc_images_heading').innerHTML = `<i class="fa-regular fa-images" style="color: var(--primary);"></i> Attached Photos (${images.length})`;
    activeGalleryImages = images;
    activeGalleryIndex = 0;
    renderViewGalleryImage();
  } else {
    imgBadge.style.display = 'none';
    imagesSection.style.display = 'none';
    activeGalleryImages = [];
    document.getElementById('view_acc_active_img').src = '';
  }

  // PDFs List & Embedded Viewer
  if (pdfs.length > 0) {
    pdfBadge.style.display = 'inline-flex';
    pdfBadge.innerHTML = `<i class="fa-solid fa-file-pdf"></i> ${pdfs.length} ${pdfs.length === 1 ? 'PDF' : 'PDFs'}`;
    pdfsSection.style.display = 'block';
    const pdfList = document.getElementById('view_acc_pdfs_list');
    pdfList.innerHTML = pdfs.map((p, idx) => {
      const pUrl = '../assets/uploads/accomplishments/' + encodeURIComponent(p.file);
      return `
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.6rem 0.85rem; background: #fff5f5; border: 1px solid #fecaca; border-radius: 8px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 0.6rem; min-width: 0;">
            <i class="fa-solid fa-file-pdf" style="color: #dc2626; font-size: 1.35rem; flex-shrink: 0;"></i>
            <span style="font-size: 0.85rem; font-weight: 700; color: var(--dark); word-break: break-all;">${escapeHtml(p.name || p.file)}</span>
          </div>
          <div style="display: flex; gap: 0.4rem; flex-shrink: 0;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="previewPdfInModal('${escapeHtml(p.file)}', '${escapeHtml(p.name || p.file)}')" style="font-size: 0.74rem; padding: 0.25rem 0.6rem;">
              <i class="fa-solid fa-eye"></i> Preview Here
            </button>
            <a href="${pUrl}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 0.74rem; padding: 0.25rem 0.6rem;">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> New Tab
            </a>
            <a href="${pUrl}" download="${escapeHtml(p.name || p.file)}" class="btn btn-secondary btn-sm" style="font-size: 0.74rem; padding: 0.25rem 0.6rem;">
              <i class="fa-solid fa-download"></i>
            </a>
          </div>
        </div>
      `;
    }).join('');
    previewPdfInModal(pdfs[0].file, pdfs[0].name || pdfs[0].file);
  } else {
    pdfBadge.style.display = 'none';
    pdfsSection.style.display = 'none';
    document.getElementById('view_acc_pdf_frame').src = '';
  }

  const impactBox = document.getElementById('view_acc_impact_box');
  const impactEl = document.getElementById('view_acc_impact');
  if (acc.impact_summary && acc.impact_summary.trim() !== '') {
    impactBox.style.display = 'block';
    impactEl.textContent = acc.impact_summary;
  } else {
    impactBox.style.display = 'none';
    impactEl.textContent = '';
  }

  const switchEditBtn = document.getElementById('view_acc_switch_edit_btn');
  if (switchEditBtn) {
    switchEditBtn.onclick = function() {
      modal.classList.remove('active');
      openEditAccModal(acc.id);
    };
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function openEditAccModal(accOrId) {
  const modal = document.getElementById('editAccModal');
  if (!modal) return;

  const acc = (typeof accOrId === 'object' && accOrId !== null)
    ? accOrId
    : ALL_ACCOMPLISHMENTS.find(a => parseInt(a.id, 10) === parseInt(accOrId, 10));
  if (!acc) return;

  document.getElementById('edit_acc_id').value = acc.id;
  document.getElementById('edit_acc_title').value = acc.title || '';
  document.getElementById('edit_acc_description').value = acc.description || '';
  document.getElementById('edit_acc_badge_tag').value = acc.badge_tag || 'Completed Event';
  document.getElementById('edit_acc_date').value = acc.date_accomplished || '';
  document.getElementById('edit_acc_impact_summary').value = acc.impact_summary || '';

  clearAllPendingFiles('edit');
  editExistingAttachments = Array.isArray(acc.attachments) ? acc.attachments.slice() : [];
  editRemovedFiles = new Set();
  renderEditExistingAttachments();

  const orgEl = document.getElementById('edit_acc_org_id');
  if (orgEl && acc.org_id) {
    orgEl.value = acc.org_id;
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('edit')) {
    openEditAccModal(parseInt(urlParams.get('edit'), 10));
  } else if (urlParams.get('view')) {
    openViewAccModal(parseInt(urlParams.get('view'), 10));
  }
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
