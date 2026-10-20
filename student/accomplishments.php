<?php
// student/accomplishments.php - Student View Accomplishments (with Multiple Images & PDFs Viewer)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/student_auth_check.php';
include_once __DIR__ . '/../includes/header.php';

$selected_org_id = isset($_GET['org_id']) ? intval($_GET['org_id']) : 0;
$orgs = $pdo->query("SELECT * FROM organizations ORDER BY name ASC")->fetchAll();

$query = "SELECT a.*, o.name as org_name, o.acronym FROM accomplishments a JOIN organizations o ON a.org_id = o.id ";
if ($selected_org_id > 0) {
    $query .= "WHERE a.org_id = $selected_org_id ";
}
$query .= "ORDER BY a.date_accomplished DESC, a.id DESC";

$accomplishments = $pdo->query($query)->fetchAll();
$upload_dir = __DIR__ . '/../assets/uploads/accomplishments/';

function getStudentAccAttachments(array $acc, string $upload_dir): array {
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

foreach ($accomplishments as &$acc_row) {
    $acc_row['attachments'] = getStudentAccAttachments($acc_row, $upload_dir);
}
unset($acc_row);
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-trophy" style="color: var(--warning);"></i> Accomplishments</h1>
    <p class="page-subtitle">Completed activities, achievements, photos, and PDF documentation.</p>
  </div>

  <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
    <form method="GET" action="accomplishments.php">
      <select name="org_id" class="form-control" onchange="this.form.submit()" style="font-weight: 600;">
        <option value="0">All Student Organizations</option>
        <?php foreach ($orgs as $org): ?>
          <option value="<?= $org['id'] ?>" <?= $selected_org_id == $org['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($org['acronym'] . ' - ' . $org['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="../leadership/accomplishments.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus"></i> Manage / Post Accomplishment
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($accomplishments)): ?>
  <div class="panel" style="text-align: center; padding: 3rem;">
    <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
    <h3>No Accomplishments Found</h3>
    <p style="color: var(--gray-600);">There are no recorded accomplishments for this selection yet.</p>
  </div>
<?php else: ?>
  <div class="card-grid">
    <?php foreach ($accomplishments as $acc):
      $atts = $acc['attachments'] ?? [];
      $img_atts = array_values(array_filter($atts, fn($a) => $a['type'] === 'image'));
      $pdf_atts = array_values(array_filter($atts, fn($a) => $a['type'] === 'pdf'));
      $img_count = count($img_atts);
      $pdf_count = count($pdf_atts);
    ?>
      <div class="card">
        <div class="card-header">
          <div style="width: 100%;">
            <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
              <span class="badge badge-accomplished"><?= htmlspecialchars($acc['acronym']) ?></span>
              <span class="badge badge-in-progress"><?= htmlspecialchars($acc['badge_tag']) ?></span>
              <div style="margin-left: auto; display: flex; gap: 0.35rem; flex-wrap: wrap;">
                <?php if ($img_count > 0): ?>
                  <span class="badge badge-planning"><i class="fa-regular fa-images"></i> <?= $img_count ?> <?= $img_count === 1 ? 'Image' : 'Images' ?></span>
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
          <p style="color: var(--gray-700); line-height: 1.6;">
            <?= nl2br(htmlspecialchars(mb_strimwidth($acc['description'], 0, 160, '...'))) ?>
          </p>
        </div>

        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
          <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <span><i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i> Date: <strong><?= date('M d, Y', strtotime($acc['date_accomplished'])) ?></strong></span>
            <?php if (!empty($acc['impact_summary'])): ?>
              <span class="badge badge-accomplished" style="font-size: 0.72rem; width: fit-content;"><i class="fa-solid fa-chart-line"></i> <?= htmlspecialchars($acc['impact_summary']) ?></span>
            <?php endif; ?>
          </div>
          <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openViewAccModal(<?= intval($acc['id']) ?>)">
              <i class="fa-solid fa-eye"></i> View
            </button>
            <?php if (isset($_SESSION['user_id'])): ?>
              <a href="../leadership/accomplishments.php?edit=<?= intval($acc['id']) ?>" class="btn btn-secondary btn-sm" title="Edit Post">
                <i class="fa-solid fa-pen"></i> Edit
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

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
        <span class="badge badge-accomplished" id="view_acc_org_acronym"></span>
        <span class="badge badge-in-progress" id="view_acc_badge_tag"></span>
        <span class="badge badge-planning" id="view_acc_img_count_badge" style="display: none;"></span>
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
      <?php if (isset($_SESSION['user_id'])): ?>
        <a id="view_acc_edit_link" href="../leadership/accomplishments.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-pen-to-square"></i> Edit Post
        </a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script>
const ALL_ACCOMPLISHMENTS = <?= json_encode($accomplishments) ?>;

let activeGalleryImages = [];
let activeGalleryIndex = 0;

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
      return `<img src="${tUrl}" alt="Thumb ${i + 1}" onclick="selectViewGalleryImage(${i})" style="width: 64px; height: 48px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid ${isActive ? 'var(--primary)' : 'var(--gray-300)'}; opacity: ${isActive ? '1' : '0.65'}; flex-shrink: 0;">`;
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

  document.getElementById('view_acc_org_acronym').textContent = acc.acronym || '';
  document.getElementById('view_acc_badge_tag').textContent = acc.badge_tag || 'Completed Project';
  document.getElementById('view_acc_title').textContent = acc.title || '';
  document.getElementById('view_acc_org_name').innerHTML = '<i class="fa-solid fa-sitemap" style="color: var(--primary);"></i> ' + escapeHtml(acc.org_name || acc.acronym || '');
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

  if (pdfs.length > 0) {
    pdfBadge.style.display = 'inline-flex';
    pdfBadge.innerHTML = `<i class="fa-solid fa-file-pdf"></i> ${pdfs.length} ${pdfs.length === 1 ? 'PDF' : 'PDFs'}`;
    pdfsSection.style.display = 'block';
    const pdfList = document.getElementById('view_acc_pdfs_list');
    pdfList.innerHTML = pdfs.map(p => {
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

  const editLink = document.getElementById('view_acc_edit_link');
  if (editLink) {
    editLink.href = '../leadership/accomplishments.php?edit=' + encodeURIComponent(acc.id);
  }

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
