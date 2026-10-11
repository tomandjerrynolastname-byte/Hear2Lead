<?php
// leadership/reports.php - Leadership Performance Reports Module
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$current_role = $_SESSION['user_role'] ?? 'Officer';
$is_admin = ($current_role === 'Admin');
$current_org_id = intval($_SESSION['org_id']);
$selected_org_id = $is_admin ? (isset($_GET['org_id']) ? intval($_GET['org_id']) : 0) : $current_org_id;

// Fetch all orgs for Admin filter dropdown
$all_orgs = $pdo->query("SELECT id, name, acronym FROM organizations ORDER BY acronym ASC")->fetchAll();

// Determine active display name / acronym
if ($selected_org_id > 0) {
    if ($selected_org_id === intval($_SESSION['org_id'])) {
        $active_org_name = $_SESSION['org_name'];
        $active_org_acronym = $_SESSION['org_acronym'];
    } else {
        $stmt_org = $pdo->prepare("SELECT name, acronym FROM organizations WHERE id = ?");
        $stmt_org->execute([$selected_org_id]);
        $org_data = $stmt_org->fetch();
        $active_org_name = $org_data['name'] ?? 'Organization';
        $active_org_acronym = $org_data['acronym'] ?? 'ORG';
    }
} else {
    $active_org_name = 'All Student Organizations';
    $active_org_acronym = 'System-wide';
}

// ----------------------------------------------------
// 1. Overall Performance Statistics
// ----------------------------------------------------
$task_scope = $selected_org_id > 0 ? "WHERE org_id = $selected_org_id" : "";
$total_tasks = $pdo->query("SELECT COUNT(*) FROM tasks $task_scope")->fetchColumn();
$accomplished_tasks = $pdo->query("SELECT COUNT(*) FROM tasks " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'Accomplished'" : "WHERE status = 'Accomplished'"))->fetchColumn();
$in_progress_tasks = $pdo->query("SELECT COUNT(*) FROM tasks " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'In Progress'" : "WHERE status = 'In Progress'"))->fetchColumn();
$overdue_tasks = $pdo->query("SELECT COUNT(*) FROM tasks " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'Overdue'" : "WHERE status = 'Overdue'"))->fetchColumn();
$todo_tasks = $pdo->query("SELECT COUNT(*) FROM tasks " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'To Do'" : "WHERE status = 'To Do'"))->fetchColumn();

$task_completion_rate = $total_tasks > 0 ? round(($accomplished_tasks / $total_tasks) * 100) : 0;

// Projects Stats
$proj_scope = $selected_org_id > 0 ? "WHERE org_id = $selected_org_id" : "";
$total_projects = $pdo->query("SELECT COUNT(*) FROM projects $proj_scope")->fetchColumn();
$accomplished_projects = $pdo->query("SELECT COUNT(*) FROM projects " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'Accomplished'" : "WHERE status = 'Accomplished'"))->fetchColumn();
$avg_progress = $pdo->query("SELECT AVG(progress_percent) FROM projects $proj_scope")->fetchColumn() ?: 0;
$avg_project_progress = round($avg_progress);

// Feedback Stats
$fb_scope = $selected_org_id > 0 ? "WHERE org_id = $selected_org_id" : "";
$total_feedbacks = $pdo->query("SELECT COUNT(*) FROM feedbacks $fb_scope")->fetchColumn();
$resolved_feedbacks = $pdo->query("SELECT COUNT(*) FROM feedbacks " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id AND status = 'Resolved'" : "WHERE status = 'Resolved'"))->fetchColumn();
$feedback_resolution_rate = $total_feedbacks > 0 ? round(($resolved_feedbacks / $total_feedbacks) * 100) : 0;

// Accomplishments count
$acc_scope = $selected_org_id > 0 ? "WHERE org_id = $selected_org_id" : "";
$total_accomplishments = $pdo->query("SELECT COUNT(*) FROM accomplishments $acc_scope")->fetchColumn();

// ----------------------------------------------------
// 2. Individual Task Assignment & Monitoring Breakdown
// ----------------------------------------------------
$officer_stats_sql = "SELECT assigned_to,
    COUNT(*) as total_assigned,
    SUM(CASE WHEN status = 'Accomplished' THEN 1 ELSE 0 END) as count_accomplished,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as count_in_progress,
    SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) as count_overdue,
    SUM(CASE WHEN status = 'To Do' THEN 1 ELSE 0 END) as count_todo
    FROM tasks " . ($selected_org_id > 0 ? "WHERE org_id = $selected_org_id" : "") . "
    GROUP BY assigned_to
    ORDER BY total_assigned DESC";
$officer_performance = $pdo->query($officer_stats_sql)->fetchAll();

// ----------------------------------------------------
// 3. Projects Overview Table
// ----------------------------------------------------
$projects_sql = "SELECT p.*, o.acronym as org_acronym 
                 FROM projects p 
                 JOIN organizations o ON p.org_id = o.id " . 
                 ($selected_org_id > 0 ? "WHERE p.org_id = $selected_org_id" : "") . " 
                 ORDER BY p.progress_percent DESC, p.target_date ASC";
$projects_list = $pdo->query($projects_sql)->fetchAll();

include_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
  body { background: #fff !important; color: #000 !important; }
  .navbar, .filter-bar, .btn, .no-print { display: none !important; }
  .page-header { border-bottom: 2px solid #000; padding-bottom: 1rem; margin-bottom: 1.5rem; }
  .panel, .table-container, .stat-card { box-shadow: none !important; border: 1px solid #ccc !important; }
  .print-header { display: block !important; text-align: center; margin-bottom: 1.5rem; }
  .print-signatures { display: flex !important; justify-content: space-between; margin-top: 3rem; }
}
.print-header, .print-signatures { display: none; }
</style>

<!-- Print Official Letterhead (Print View Only) -->
<div class="print-header">
  <h2 style="margin: 0; font-size: 1.3rem; text-transform: uppercase; font-weight: 800;">Hear2Lead</h2>
  <h3 style="margin: 0.25rem 0; font-size: 1.1rem; color: #4b5563;">Official Leadership Performance & Accomplishment Report</h3>
  <p style="margin: 0; font-size: 0.9rem; color: #6b7280;">Organization: <strong><?= htmlspecialchars($active_org_name) ?> (<?= htmlspecialchars($active_org_acronym) ?>)</strong> • Date Generated: <?= date('F d, Y') ?></p>
  <hr style="margin: 1rem 0; border: none; border-top: 2px solid #1f2937;">
</div>

<!-- Page Header & Action Controls -->
<div class="page-header">
  <div>
    <h1 class="page-title"><i class="fa-solid fa-chart-pie" style="color: var(--primary);"></i> Leadership Performance Reports</h1>
    <p class="page-subtitle">
      Comprehensive performance metrics, individual officer accountability, task completion rate, and program delivery.
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
    <button onclick="window.print()" class="btn btn-secondary no-print">
      <i class="fa-solid fa-print"></i> Print Official Report
    </button>
  </div>
</div>

<?php if ($is_admin): ?>
  <!-- Admin Filter Bar -->
  <div class="filter-bar no-print" style="margin-bottom: 1.5rem;">
    <form method="GET" action="reports.php" style="display: flex; gap: 1rem; align-items: center; width: 100%; flex-wrap: wrap;">
      <div class="filter-group">
        <label class="form-label" style="margin: 0;">Scope Organization:</label>
        <select name="org_id" class="form-control" onchange="this.form.submit()">
          <option value="0" <?= $selected_org_id === 0 ? 'selected' : '' ?>>All Organizations (Consolidated Report)</option>
          <?php foreach ($all_orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $selected_org_id == $o['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($o['acronym'] . ' - ' . $o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($selected_org_id > 0): ?>
        <a href="reports.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i> View Consolidated</a>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<!-- Performance KPI Stat Cards Grid -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
  <!-- Task Completion Rate -->
  <div class="stat-card accomplished">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div>
      <div class="stat-number"><?= $task_completion_rate ?>%</div>
      <div class="stat-label">Task Completion (<?= $accomplished_tasks ?>/<?= $total_tasks ?>)</div>
    </div>
  </div>

  <!-- Project Progress Average -->
  <div class="stat-card in-progress" style="border-left: 4px solid var(--secondary);">
    <div class="stat-icon" style="background: #e0f2fe; color: var(--secondary);"><i class="fa-solid fa-chart-line"></i></div>
    <div>
      <div class="stat-number"><?= $avg_project_progress ?>%</div>
      <div class="stat-label">Avg Project Progress</div>
    </div>
  </div>

  <!-- Feedback Resolution Rate -->
  <div class="stat-card total">
    <div class="stat-icon"><i class="fa-solid fa-comments"></i></div>
    <div>
      <div class="stat-number"><?= $feedback_resolution_rate ?>%</div>
      <div class="stat-label">Feedback Responded (<?= $resolved_feedbacks ?>/<?= $total_feedbacks ?>)</div>
    </div>
  </div>

  <!-- Total Accomplishments Milestone -->
  <div class="stat-card todo">
    <div class="stat-icon"><i class="fa-solid fa-trophy"></i></div>
    <div>
      <div class="stat-number"><?= $total_accomplishments ?></div>
      <div class="stat-label">Verified Accomplishments</div>
    </div>
  </div>
</div>

<!-- Section 1: Individual Task Assignment & Monitoring Table -->
<div class="panel" style="margin-bottom: 2rem;">
  <div class="panel-header">
    <span><i class="fa-solid fa-users-gear" style="color: var(--primary);"></i> Individual Task Assignment & Monitoring</span>
    <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($officer_performance) ?> Assignee Groups</span>
  </div>

  <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
    <table class="table">
      <thead>
        <tr>
          <th>Assigned Officer / Team</th>
          <th>Total Assigned</th>
          <th>Accomplished</th>
          <th>In Progress</th>
          <th>To Do</th>
          <th>Overdue</th>
          <th>Efficiency Score</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($officer_performance)): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--gray-500); padding: 2rem;">No individual task assignment data available.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($officer_performance as $row): 
            $rate = $row['total_assigned'] > 0 ? round(($row['count_accomplished'] / $row['total_assigned']) * 100) : 0;
            $rate_color = $rate >= 80 ? 'var(--success)' : ($rate >= 50 ? '#b45309' : 'var(--danger)');
          ?>
            <tr>
              <td><strong style="color: var(--dark); font-size: 0.95rem;"><?= htmlspecialchars($row['assigned_to'] ?: 'Unassigned') ?></strong></td>
              <td><strong><?= $row['total_assigned'] ?></strong> tasks</td>
              <td><span class="badge badge-accomplished"><?= $row['count_accomplished'] ?></span></td>
              <td><span class="badge badge-in-progress"><?= $row['count_in_progress'] ?></span></td>
              <td><span class="badge badge-todo"><?= $row['count_todo'] ?></span></td>
              <td>
                <?php if ($row['count_overdue'] > 0): ?>
                  <span class="badge badge-overdue"><?= $row['count_overdue'] ?></span>
                <?php else: ?>
                  <span style="color: var(--gray-400); font-size: 0.85rem;">0</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 120px;">
                  <div class="progress-container" style="flex: 1; margin: 0;">
                    <div class="progress-bar" style="width: <?= $rate ?>%; background: <?= $rate_color ?>;"></div>
                  </div>
                  <strong style="font-size: 0.85rem; color: <?= $rate_color ?>;"><?= $rate ?>%</strong>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Section 2: Program and Project Milestone Progress -->
<div class="panel">
  <div class="panel-header">
    <span><i class="fa-solid fa-folder-tree" style="color: var(--secondary);"></i> Programs and Projects Milestone Status</span>
    <span style="font-size: 0.85rem; color: var(--gray-500);"><?= count($projects_list) ?> Total Programs</span>
  </div>

  <div class="table-container" style="box-shadow: none; border: none; margin: 0;">
    <table class="table">
      <thead>
        <tr>
          <?php if ($selected_org_id === 0): ?>
            <th>Org</th>
          <?php endif; ?>
          <th>Project / Program Name</th>
          <th>Status</th>
          <th>Completion %</th>
          <th>Target Delivery Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($projects_list)): ?>
          <tr>
            <td colspan="<?= $selected_org_id === 0 ? '5' : '4' ?>" style="text-align: center; color: var(--gray-500); padding: 2rem;">No programs recorded for this report.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($projects_list as $p): 
            $p_status_badges = ['Accomplished' => 'badge-accomplished', 'Ongoing' => 'badge-in-progress', 'Planning' => 'badge-todo'];
            $p_badge = $p_status_badges[$p['status']] ?? 'badge-planning';
          ?>
            <tr>
              <?php if ($selected_org_id === 0): ?>
                <td><span class="badge badge-planning"><strong><?= htmlspecialchars($p['org_acronym']) ?></strong></span></td>
              <?php endif; ?>
              <td><strong style="color: var(--dark);"><?= htmlspecialchars($p['title']) ?></strong></td>
              <td><span class="badge <?= $p_badge ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 140px;">
                  <div class="progress-container" style="flex: 1; margin: 0;">
                    <div class="progress-bar" style="width: <?= $p['progress_percent'] ?>%;"></div>
                  </div>
                  <strong style="font-size: 0.85rem; color: var(--primary);"><?= $p['progress_percent'] ?>%</strong>
                </div>
              </td>
              <td><?= $p['target_date'] ? date('M d, Y', strtotime($p['target_date'])) : 'N/A' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Sign-off Block for Print / Official Submission -->
<div class="print-signatures">
  <div style="text-align: center; width: 220px;">
    <div style="border-bottom: 1px solid #000; height: 50px;"></div>
    <p style="margin: 0.5rem 0 0.1rem 0; font-weight: 700;"><?= htmlspecialchars($_SESSION['user_name']) ?></p>
    <p style="margin: 0; font-size: 0.8rem; color: #4b5563;"><?= htmlspecialchars($_SESSION['user_role']) ?>, <?= htmlspecialchars($_SESSION['org_acronym']) ?></p>
    <p style="margin: 0; font-size: 0.75rem; color: #9ca3af;">Prepared By</p>
  </div>

  <div style="text-align: center; width: 220px;">
    <div style="border-bottom: 1px solid #000; height: 50px;"></div>
    <p style="margin: 0.5rem 0 0.1rem 0; font-weight: 700;">Organization Adviser</p>
    <p style="margin: 0; font-size: 0.8rem; color: #4b5563;">Faculty Adviser / Consultant</p>
    <p style="margin: 0; font-size: 0.75rem; color: #9ca3af;">Attested By</p>
  </div>

  <div style="text-align: center; width: 220px;">
    <div style="border-bottom: 1px solid #000; height: 50px;"></div>
    <p style="margin: 0.5rem 0 0.1rem 0; font-weight: 700;">School Principal / Head</p>
    <p style="margin: 0; font-size: 0.8rem; color: #4b5563;">Office of Student Affairs</p>
    <p style="margin: 0; font-size: 0.75rem; color: #9ca3af;">Approved By</p>
  </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
