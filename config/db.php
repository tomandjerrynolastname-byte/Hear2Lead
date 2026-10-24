<?php
// config/db.php - Database connection with auto-initialization

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'student_leadership_db');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            // First connect without DB name to check/create DB
            $dsnNoDB = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $tempPdo = new PDO($dsnNoDB, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Create database if not exists
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Connect to specific database
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            // Auto-seed if tables don't exist
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'organizations'")->rowCount();
            if ($tableCheck === 0) {
                $sqlPath = __DIR__ . '/../database.sql';
                if (file_exists($sqlPath)) {
                    $sql = file_get_contents($sqlPath);
                    $pdo->exec($sql);
                }
            }

            // Ensure students table exists
            $studentTableCheck = $pdo->query("SHOW TABLES LIKE 'students'")->rowCount();
            if ($studentTableCheck === 0) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `student_lrn` VARCHAR(50) NOT NULL UNIQUE,
                    `full_name` VARCHAR(100) NOT NULL,
                    `grade_section` VARCHAR(50) NOT NULL,
                    `email` VARCHAR(100) DEFAULT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Seed sample student accounts (password: student123)
                $demoPassword = password_hash('student123', PASSWORD_DEFAULT);
                $seedStmt = $pdo->prepare("INSERT INTO `students` (`student_lrn`, `full_name`, `grade_section`, `email`, `password`) VALUES (?, ?, ?, ?, ?)");
                $seedStmt->execute(['2026-0001', 'Alex Morgan', 'Grade 11 - STEM', 'alex.morgan@student.edu.ph', $demoPassword]);
                $seedStmt->execute(['2026-0002', 'Samantha Cruz', 'Grade 10 - Rizal', 'samantha.cruz@student.edu.ph', $demoPassword]);
            }

            // Purge any legacy anonymous feedback submissions
            $studentStatusColCheck = $pdo->query("SHOW COLUMNS FROM `students` LIKE 'status'")->rowCount();
            if ($studentStatusColCheck === 0) {
                $pdo->exec("ALTER TABLE `students`
                    ADD COLUMN `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER `password`,
                    ADD COLUMN `approved_by` INT DEFAULT NULL AFTER `status`,
                    ADD COLUMN `approved_at` DATETIME DEFAULT NULL AFTER `approved_by`");
                // Existing student accounts are approved by default
                $pdo->exec("UPDATE `students` SET `status` = 'Approved', `approved_at` = NOW() WHERE `status` = 'Pending'");
            }
            // Ensure student_logs table exists for student account status/approval audit logs
            $pdo->exec("CREATE TABLE IF NOT EXISTS `student_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_id` INT DEFAULT NULL,
                `student_lrn` VARCHAR(50) NOT NULL,
                `student_name` VARCHAR(100) NOT NULL,
                `user_id` INT DEFAULT NULL,
                `user_name` VARCHAR(100) NOT NULL,
                `user_role` VARCHAR(50) NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `previous_status` VARCHAR(30) DEFAULT NULL,
                `new_status` VARCHAR(30) NOT NULL,
                `log_details` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed initial log for any existing student accounts without logs
            $pdo->exec("INSERT INTO `student_logs` (`student_id`, `student_lrn`, `student_name`, `user_id`, `user_name`, `user_role`, `action_type`, `previous_status`, `new_status`, `log_details`, `created_at`)
                SELECT s.id, s.student_lrn, s.full_name, s.approved_by, 'System Administrator', 'Admin',
                       CASE WHEN s.status = 'Approved' THEN 'Approved' WHEN s.status = 'Rejected' THEN 'Rejected' ELSE 'Registered (Pending)' END,
                       CASE WHEN s.status = 'Approved' THEN 'Pending' ELSE NULL END,
                       s.status,
                       CONCAT('Student account (LRN: ', s.student_lrn, ', ', s.grade_section, ') status recorded as ''', s.status, '''.'),
                       IFNULL(s.approved_at, s.created_at)
                FROM `students` s
                LEFT JOIN `student_logs` sl ON sl.student_id = s.id
                WHERE sl.id IS NULL");

            $pdo->exec("DELETE FROM feedbacks WHERE student_name = 'Anonymous Student' OR student_name IS NULL OR TRIM(student_name) = ''");

            // Ensure tasks.assigned_to is TEXT for multiple assignees
            $pdo->exec("ALTER TABLE `tasks` MODIFY `assigned_to` TEXT DEFAULT NULL");

            // Ensure task_logs table exists for task creation/update history
            $pdo->exec("CREATE TABLE IF NOT EXISTS `task_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_id` INT NOT NULL,
                `user_id` INT DEFAULT NULL,
                `user_name` VARCHAR(100) NOT NULL,
                `user_role` VARCHAR(50) NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `log_details` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed initial creation log for any existing tasks without logs
            $pdo->exec("INSERT INTO `task_logs` (`task_id`, `user_id`, `user_name`, `user_role`, `action_type`, `log_details`, `created_at`)
                SELECT t.id, NULL, 'System / Leadership', 'Leader', 'Created',
                       CONCAT('Task created with status ''', t.status, ''', priority ''', t.priority, ''', deadline ', DATE_FORMAT(t.deadline, '%b %d, %Y'), ', assigned to: ', IFNULL(t.assigned_to, 'Unassigned'), '.'),
                       t.created_at
                FROM `tasks` t
                LEFT JOIN `task_logs` tl ON tl.task_id = t.id
                WHERE tl.id IS NULL");

            // Ensure project_logs table exists for project creation/update history
            $pdo->exec("CREATE TABLE IF NOT EXISTS `project_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `project_id` INT NOT NULL,
                `user_id` INT DEFAULT NULL,
                `user_name` VARCHAR(100) NOT NULL,
                `user_role` VARCHAR(50) NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `log_details` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed initial creation log for any existing projects without logs
            $pdo->exec("INSERT INTO `project_logs` (`project_id`, `user_id`, `user_name`, `user_role`, `action_type`, `log_details`, `created_at`)
                SELECT p.id, NULL, 'System / Leadership', 'Leader', 'Created',
                       CONCAT('Project created with title \"', p.title, '\", status ''', p.status, ''', progress ', p.progress_percent, '%.'),
                       p.created_at
                FROM `projects` p
                LEFT JOIN `project_logs` pl ON pl.project_id = p.id
                WHERE pl.id IS NULL");

            // Ensure accomplishment_logs table exists for accomplishment creation/update history
            $pdo->exec("CREATE TABLE IF NOT EXISTS `accomplishment_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `acc_id` INT NOT NULL,
                `user_id` INT DEFAULT NULL,
                `user_name` VARCHAR(100) NOT NULL,
                `user_role` VARCHAR(50) NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `log_details` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`acc_id`) REFERENCES `accomplishments`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed initial creation log for any existing accomplishments without logs
            $pdo->exec("INSERT INTO `accomplishment_logs` (`acc_id`, `user_id`, `user_name`, `user_role`, `action_type`, `log_details`, `created_at`)
                SELECT a.id, NULL, 'System / Leadership', 'Leader', 'Created',
                       CONCAT('Accomplishment published with title \"', a.title, '\", tag ''', IFNULL(a.badge_tag, 'Completed Event'), ''', date accomplished ', DATE_FORMAT(a.date_accomplished, '%b %d, %Y'), '.'),
                       a.created_at
                FROM `accomplishments` a
                LEFT JOIN `accomplishment_logs` al ON al.acc_id = a.id
                WHERE al.id IS NULL");

            // Ensure student_id column exists in feedbacks table
            $colCheck = $pdo->query("SHOW COLUMNS FROM `feedbacks` LIKE 'student_id'")->rowCount();
            if ($colCheck === 0) {
                $pdo->exec("ALTER TABLE `feedbacks` ADD COLUMN `student_id` INT DEFAULT NULL AFTER `org_id`");
            }

            // Ensure feedback_logs table exists for multiple officer replies with timestamps
            $pdo->exec("CREATE TABLE IF NOT EXISTS `feedback_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `feedback_id` INT NOT NULL,
                `user_id` INT DEFAULT NULL,
                `officer_name` VARCHAR(100) NOT NULL,
                `officer_role` VARCHAR(50) NOT NULL,
                `status` VARCHAR(50) NOT NULL,
                `reply_message` TEXT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`feedback_id`) REFERENCES `feedbacks`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Migrate any existing single officer_response into feedback_logs if not yet logged
            $pdo->exec("INSERT INTO `feedback_logs` (`feedback_id`, `user_id`, `officer_name`, `officer_role`, `status`, `reply_message`, `created_at`)
                SELECT f.id, NULL, 'Organization Officer', 'Officer', f.status, f.officer_response, IFNULL(f.responded_at, f.created_at)
                FROM `feedbacks` f
                LEFT JOIN `feedback_logs` fl ON fl.feedback_id = f.id
                WHERE f.officer_response IS NOT NULL AND TRIM(f.officer_response) != '' AND fl.id IS NULL");

            // Seed initial student reply on responded feedbacks if no student reply exists yet
            $studentReplyCount = $pdo->query("SELECT COUNT(*) FROM `feedback_logs` WHERE `status` = 'Student Reply'")->fetchColumn();
            if (intval($studentReplyCount) === 0) {
                $pdo->exec("INSERT INTO `feedback_logs` (`feedback_id`, `user_id`, `officer_name`, `officer_role`, `status`, `reply_message`, `created_at`)
                    SELECT f.id, f.student_id, f.student_name,
                           CASE WHEN f.student_grade_section IS NOT NULL AND f.student_grade_section != '' THEN CONCAT('Student • ', f.student_grade_section) ELSE 'Student' END,
                           'Student Reply',
                           'Thank you for the update! Looking forward to the next steps from the organization.',
                           DATE_ADD(IFNULL(f.responded_at, NOW()), INTERVAL 15 MINUTE)
                    FROM `feedbacks` f
                    WHERE EXISTS (SELECT 1 FROM `feedback_logs` fl WHERE fl.feedback_id = f.id AND fl.status != 'Student Reply')");
            }

            // Ensure attachment columns exist in accomplishments table for image or PDF uploads
            $accFileCol = $pdo->query("SHOW COLUMNS FROM `accomplishments` LIKE 'attachment_file'")->rowCount();
            if ($accFileCol === 0) {
                $pdo->exec("ALTER TABLE `accomplishments`
                    ADD COLUMN `attachment_file` VARCHAR(255) DEFAULT NULL AFTER `badge_tag`,
                    ADD COLUMN `attachment_type` VARCHAR(20) DEFAULT NULL AFTER `attachment_file`,
                    ADD COLUMN `attachment_name` VARCHAR(255) DEFAULT NULL AFTER `attachment_type`");
            }

            $accJsonCol = $pdo->query("SHOW COLUMNS FROM `accomplishments` LIKE 'attachments_json'")->rowCount();
            if ($accJsonCol === 0) {
                $pdo->exec("ALTER TABLE `accomplishments` ADD COLUMN `attachments_json` LONGTEXT DEFAULT NULL AFTER `attachment_name`");
                // Migrate any existing single attachment into attachments_json array
                $legacyAccs = $pdo->query("SELECT id, attachment_file, attachment_type, attachment_name FROM `accomplishments` WHERE attachment_file IS NOT NULL AND attachment_file != '' AND (attachments_json IS NULL OR attachments_json = '')")->fetchAll();
                $migStmt = $pdo->prepare("UPDATE `accomplishments` SET `attachments_json` = ? WHERE `id` = ?");
                foreach ($legacyAccs as $row) {
                    $ext = strtolower(pathinfo($row['attachment_file'], PATHINFO_EXTENSION));
                    $t = !empty($row['attachment_type']) ? $row['attachment_type'] : (($ext === 'pdf') ? 'pdf' : 'image');
                    $n = !empty($row['attachment_name']) ? $row['attachment_name'] : $row['attachment_file'];
                    $json = json_encode([['file' => $row['attachment_file'], 'type' => $t, 'name' => $n]]);
                    $migStmt->execute([$json, $row['id']]);
                }
            }
            // Ensure users.role is VARCHAR(100) to support dynamic/custom roles beyond fixed ENUM
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(100) NOT NULL DEFAULT 'Officer'");

            // Ensure user_roles table exists for Dynamic / Custom Role Library & System Accessibility
            $pdo->exec("CREATE TABLE IF NOT EXISTS `user_roles` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `role_name` VARCHAR(100) NOT NULL UNIQUE,
                `base_role` VARCHAR(50) NOT NULL DEFAULT 'Officer',
                `description` VARCHAR(255) DEFAULT NULL,
                `badge_style` VARCHAR(50) NOT NULL DEFAULT 'badge-role-officer',
                `can_manage_users` TINYINT(1) NOT NULL DEFAULT 0,
                `can_manage_tasks` TINYINT(1) NOT NULL DEFAULT 0,
                `can_manage_projects` TINYINT(1) NOT NULL DEFAULT 1,
                `can_manage_accomplishments` TINYINT(1) NOT NULL DEFAULT 1,
                `can_manage_feedbacks` TINYINT(1) NOT NULL DEFAULT 1,
                `can_view_reports` TINYINT(1) NOT NULL DEFAULT 1,
                `can_manage_orgs` TINYINT(1) NOT NULL DEFAULT 0,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed default system roles if user_roles is empty
            $roleCount = intval($pdo->query("SELECT COUNT(*) FROM `user_roles`")->fetchColumn());
            if ($roleCount === 0) {
                $defaultRoles = [
                    ['Admin', 'Admin', 'Full system-wide administrator across all student organizations, student accounts, and role library.', 'badge-role-admin', 1, 1, 1, 1, 1, 1, 1, 1],
                    ['President', 'President', 'Chief executive leader of an organization. Can manage organization officers and assign/manage tasks.', 'badge-role-president', 1, 1, 1, 1, 1, 1, 0, 1],
                    ['Vice President', 'Vice President', 'Co-executive leader of an organization. Can manage organization officers and assign/manage tasks.', 'badge-role-vp', 1, 1, 1, 1, 1, 1, 0, 1],
                    ['Secretary', 'Secretary', 'Organization secretary handling documentation, projects, accomplishments, feedbacks, and reports.', 'badge-role-secretary', 0, 0, 1, 1, 1, 1, 0, 1],
                    ['Treasurer', 'Treasurer', 'Organization treasurer handling financial records, projects, accomplishments, feedbacks, and reports.', 'badge-role-treasurer', 0, 0, 1, 1, 1, 1, 0, 1],
                    ['Officer', 'Officer', 'General organization officer with access to assigned tasks, projects, accomplishments, feedbacks, and reports.', 'badge-role-officer', 0, 0, 1, 1, 1, 1, 0, 1],
                ];
                $insRole = $pdo->prepare("INSERT IGNORE INTO `user_roles` (`role_name`, `base_role`, `description`, `badge_style`, `can_manage_users`, `can_manage_tasks`, `can_manage_projects`, `can_manage_accomplishments`, `can_manage_feedbacks`, `can_view_reports`, `can_manage_orgs`, `is_system`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($defaultRoles as $dr) {
                    $insRole->execute($dr);
                }
            }

            // Ensure login_logs table exists for Header Notifications (Who Logged In)
            $pdo->exec("CREATE TABLE IF NOT EXISTS `login_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `account_type` VARCHAR(20) NOT NULL DEFAULT 'Officer',
                `account_id` INT DEFAULT NULL,
                `org_id` INT DEFAULT NULL,
                `org_acronym` VARCHAR(30) DEFAULT NULL,
                `user_name` VARCHAR(100) NOT NULL,
                `user_role` VARCHAR(100) NOT NULL,
                `log_details` VARCHAR(255) NOT NULL,
                `ip_address` VARCHAR(60) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed initial login log notifications if table is empty
            $loginLogCount = intval($pdo->query("SELECT COUNT(*) FROM `login_logs`")->fetchColumn());
            if ($loginLogCount === 0) {
                $pdo->exec("INSERT INTO `login_logs` (`account_type`, `account_id`, `org_id`, `org_acronym`, `user_name`, `user_role`, `log_details`, `ip_address`, `created_at`)
                    SELECT 'Officer', u.id, u.org_id, o.acronym, u.full_name, u.role,
                           CONCAT(u.full_name, ' (', u.role, ') logged in to the ', o.acronym, ' Leadership Portal.'),
                           '127.0.0.1',
                           DATE_SUB(NOW(), INTERVAL (u.id * 12) MINUTE)
                    FROM `users` u
                    JOIN `organizations` o ON u.org_id = o.id
                    ORDER BY u.id ASC
                    LIMIT 5");
            }
        } catch (PDOException $e) {
            die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; border: 1px solid #f87171; color: #991b1b; border-radius: 8px; margin: 20px;'>
                <h3>Database Connection Error</h3>
                <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <p>Please make sure your XAMPP MySQL service is running!</p>
            </div>");
        }
    }

    return $pdo;
}

/**
 * Record a login event in login_logs for header notifications
 */
function recordLoginNotification(string $accountType, int $accountId, ?int $orgId, string $orgAcronym, string $userName, string $userRole, string $details, ?PDO $pdo = null): void {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO `login_logs` (`account_type`, `account_id`, `org_id`, `org_acronym`, `user_name`, `user_role`, `log_details`, `ip_address`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$accountType, $accountId, $orgId, $orgAcronym, $userName, $userRole, $details, $ip]);
    } catch (PDOException $e) {
        // Fail silently if table is not ready
    }
}

/**
 * Fetch all roles from the Dynamic Role Library ordered by hierarchy
 */
/**
 * Record a student account registration, approval, rejection, or status change in student_logs
 */
function recordStudentAccountLog(?int $studentId, string $studentLrn, string $studentName, ?int $userId, string $userName, string $userRole, string $actionType, ?string $previousStatus, string $newStatus, string $logDetails, ?PDO $pdo = null): void {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO `student_logs` (`student_id`, `student_lrn`, `student_name`, `user_id`, `user_name`, `user_role`, `action_type`, `previous_status`, `new_status`, `log_details`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $studentLrn, $studentName, $userId, $userName, $userRole, $actionType, $previousStatus, $newStatus, $logDetails]);
    } catch (PDOException $e) {
        // Fail silently if table is not ready
    }
}

function getAllUserRoles(?PDO $pdo = null): array {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->query("SELECT * FROM `user_roles` ORDER BY 
            CASE `base_role`
                WHEN 'Admin' THEN 1
                WHEN 'President' THEN 2
                WHEN 'Vice President' THEN 3
                WHEN 'Secretary' THEN 4
                WHEN 'Treasurer' THEN 5
                ELSE 6
            END ASC,
            `is_system` DESC,
            `role_name` ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Resolve effective system permissions and base role equivalent for any role name (built-in or custom)
 */
function getRolePermissions(string $roleName, ?PDO $pdo = null): array {
    static $cache = [];
    $roleKey = strtolower(trim($roleName ?: 'Officer'));
    if (isset($cache[$roleKey])) {
        return $cache[$roleKey];
    }

    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    $row = null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM `user_roles` WHERE LOWER(`role_name`) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($roleName)]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        $row = null;
    }

    if ($row) {
        $base = $row['base_role'] ?: 'Officer';
        $isAdmin = (strcasecmp($row['role_name'], 'Admin') === 0 || strcasecmp($base, 'Admin') === 0 || intval($row['can_manage_orgs']) === 1);
        $isPresOrVp = in_array($base, ['President', 'Vice President'], true) || in_array($row['role_name'], ['President', 'Vice President'], true);

        $perms = [
            'role_name' => $row['role_name'],
            'base_role' => $base,
            'description' => $row['description'] ?? '',
            'badge_style' => $row['badge_style'] ?: 'badge-role-officer',
            'is_system' => intval($row['is_system']) === 1,
            'is_admin' => $isAdmin,
            'is_pres_or_vp' => $isPresOrVp,
            'can_manage_users' => $isAdmin || intval($row['can_manage_users']) === 1,
            'can_manage_tasks' => $isAdmin || intval($row['can_manage_tasks']) === 1,
            'can_manage_projects' => $isAdmin || intval($row['can_manage_projects']) === 1,
            'can_manage_accomplishments' => $isAdmin || intval($row['can_manage_accomplishments']) === 1,
            'can_manage_feedbacks' => $isAdmin || intval($row['can_manage_feedbacks']) === 1,
            'can_view_reports' => $isAdmin || intval($row['can_view_reports']) === 1,
            'can_manage_orgs' => $isAdmin || intval($row['can_manage_orgs']) === 1,
        ];
    } else {
        // Fallback if role is not yet in user_roles table
        $isAdmin = (strcasecmp($roleName, 'Admin') === 0);
        $isPresOrVp = in_array($roleName, ['President', 'Vice President'], true);
        $badge = 'badge-role-officer';
        if ($isAdmin) $badge = 'badge-role-admin';
        elseif (strcasecmp($roleName, 'President') === 0) $badge = 'badge-role-president';
        elseif (strcasecmp($roleName, 'Vice President') === 0) $badge = 'badge-role-vp';
        elseif (strcasecmp($roleName, 'Secretary') === 0) $badge = 'badge-role-secretary';
        elseif (strcasecmp($roleName, 'Treasurer') === 0) $badge = 'badge-role-treasurer';

        $perms = [
            'role_name' => $roleName ?: 'Officer',
            'base_role' => $isAdmin ? 'Admin' : ($isPresOrVp ? $roleName : 'Officer'),
            'description' => '',
            'badge_style' => $badge,
            'is_system' => in_array($roleName, ['Admin', 'President', 'Vice President', 'Secretary', 'Treasurer', 'Officer'], true),
            'is_admin' => $isAdmin,
            'is_pres_or_vp' => $isPresOrVp,
            'can_manage_users' => $isAdmin || $isPresOrVp,
            'can_manage_tasks' => $isAdmin || $isPresOrVp,
            'can_manage_projects' => true,
            'can_manage_accomplishments' => true,
            'can_manage_feedbacks' => true,
            'can_view_reports' => true,
            'can_manage_orgs' => $isAdmin,
        ];
    }

    $cache[$roleKey] = $perms;
    return $perms;
}

/**
 * Automatically mark tasks that exceeded their deadline as 'Overdue' and record an Auto-Overdue notification log
 */
function syncOverdueTasks(?PDO $pdo = null): void {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    try {
        // 1. Find tasks that just exceeded their deadline and are not yet 'Overdue' or 'Accomplished'
        $stmt = $pdo->query("SELECT id, title, status, deadline, assigned_to FROM `tasks` WHERE `deadline` < CURDATE() AND `status` NOT IN ('Accomplished', 'Overdue')");
        $newlyOverdue = $stmt->fetchAll();

        if (!empty($newlyOverdue)) {
            $updStmt = $pdo->prepare("UPDATE `tasks` SET `status` = 'Overdue' WHERE `id` = ?");
            $logStmt = $pdo->prepare("INSERT INTO `task_logs` (`task_id`, `user_id`, `user_name`, `user_role`, `action_type`, `log_details`) VALUES (?, NULL, 'System Auto-Monitor', 'System', 'Auto-Overdue', ?)");

            foreach ($newlyOverdue as $t) {
                $tid = intval($t['id']);
                $oldStatus = $t['status'] ?: 'To Do';
                $dlFormatted = date('M d, Y', strtotime($t['deadline']));
                $updStmt->execute([$tid]);

                $details = "Target completion deadline ($dlFormatted) exceeded for task \"{$t['title']}\". Status automatically changed from '$oldStatus' to 'Overdue'.";
                $logStmt->execute([$tid, $details]);
            }
        }

        // 2. Ensure any existing Overdue task also has an Auto-Overdue log entry for notifications
        $stmtExisting = $pdo->query("SELECT t.id, t.title, t.deadline FROM `tasks` t
            LEFT JOIN `task_logs` tl ON tl.task_id = t.id AND tl.action_type = 'Auto-Overdue'
            WHERE t.status = 'Overdue' AND tl.id IS NULL");
        $unloggedOverdue = $stmtExisting->fetchAll();
        if (!empty($unloggedOverdue)) {
            $logStmt = $pdo->prepare("INSERT INTO `task_logs` (`task_id`, `user_id`, `user_name`, `user_role`, `action_type`, `log_details`) VALUES (?, NULL, 'System Auto-Monitor', 'System', 'Auto-Overdue', ?)");
            foreach ($unloggedOverdue as $ut) {
                $dlFormatted = date('M d, Y', strtotime($ut['deadline']));
                $details = "Target completion deadline ($dlFormatted) exceeded for task \"{$ut['title']}\". Status automatically marked as 'Overdue'.";
                $logStmt->execute([intval($ut['id']), $details]);
            }
        }
    } catch (PDOException $e) {
        // Fail silently
    }
}

/**
 * Check if a specific user (by full name / role) is among the assigned personnel for a task
 */
function isUserAssignedToTask(?string $assignedTo, string $userName, string $userRole = ''): bool {
    $userName = trim($userName);
    $userRole = trim($userRole);
    if ($userName === '' || $assignedTo === null || trim($assignedTo) === '') {
        return false;
    }

    $parts = explode(',', $assignedTo);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        // Match "All Officers" for non-Admin organization officers
        if (strcasecmp($part, 'All Officers') === 0 && strcasecmp($userRole, 'Admin') !== 0) {
            return true;
        }
        // Exact match on "Full Name" or "Full Name (Role)"
        if (strcasecmp($part, $userName) === 0) {
            return true;
        }
        if ($userRole !== '' && strcasecmp($part, $userName . ' (' . $userRole . ')') === 0) {
            return true;
        }
        // Extract name before " (Role)" and compare exact name
        $nameOnly = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $part));
        if ($nameOnly !== '' && strcasecmp($nameOnly, $userName) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Fetch active Task Deadline Reminders ONLY for the respective assigned personnel
 * (Tomorrow deadline, Due Today, Almost on Target Completion, Overdue, and Assigned Tasks)
 */
function getTaskDeadlineReminders(?PDO $pdo, bool $isAdmin, int $orgId, string $userName, bool $canManageTasks = false, string $userRole = ''): array {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    syncOverdueTasks($pdo);

    $reminders = [];
    if (trim($userName) === '') {
        return $reminders;
    }

    try {
        $conds = ["t.status != 'Accomplished'"];
        $params = [];
        if (!$isAdmin && $orgId > 0) {
            $conds[] = "t.org_id = ?";
            $params[] = $orgId;
        }
        $whereSql = implode(" AND ", $conds);

        $sql = "SELECT t.*, DATEDIFF(t.deadline, CURDATE()) as days_left, o.acronym as org_acronym, p.title as project_title
                FROM `tasks` t
                LEFT JOIN `organizations` o ON t.org_id = o.id
                LEFT JOIN `projects` p ON t.project_id = p.id
                WHERE $whereSql
                ORDER BY t.deadline ASC, FIELD(t.priority, 'High', 'Medium', 'Low')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        foreach ($tasks as $t) {
            // Send reminder ONLY to the respective assigned personnel of this task
            if (!isUserAssignedToTask($t['assigned_to'] ?? '', $userName, $userRole)) {
                continue;
            }

            $daysLeft = intval($t['days_left']);
            $dlLabel = date('M d, Y', strtotime($t['deadline']));
            $assigned = $t['assigned_to'] ?: 'Unassigned';

            if ($daysLeft < 0 || $t['status'] === 'Overdue') {
                $overdueDays = abs($daysLeft);
                $overdueText = $overdueDays > 0 ? "$overdueDays day" . ($overdueDays === 1 ? '' : 's') . " past deadline" : "Past target completion";
                $reminders[] = [
                    'category' => 'overdue',
                    'module' => 'Task',
                    'item_id' => intval($t['id']),
                    'title' => $t['title'],
                    'status' => 'Overdue',
                    'priority' => $t['priority'],
                    'deadline' => $t['deadline'],
                    'deadline_formatted' => $dlLabel,
                    'days_left' => $daysLeft,
                    'assigned_to' => $assigned,
                    'org_acronym' => $t['org_acronym'] ?? '',
                    'badge_text' => 'OVERDUE (AUTO-UPDATED)',
                    'badge_class' => 'badge-overdue',
                    'icon' => 'fa-triangle-exclamation',
                    'color' => '#dc2626',
                    'bg_color' => '#fef2f2',
                    'border_color' => '#fecaca',
                    'headline' => "Overdue Task: \"{$t['title']}\"",
                    'message' => "Exceeded target completion deadline ($dlLabel — $overdueText). Status was automatically set to Overdue.",
                    'link' => '../leadership/tasks.php?status=Overdue'
                ];
            } elseif ($daysLeft === 0) {
                $reminders[] = [
                    'category' => 'due_today',
                    'module' => 'Task',
                    'item_id' => intval($t['id']),
                    'title' => $t['title'],
                    'status' => $t['status'],
                    'priority' => $t['priority'],
                    'deadline' => $t['deadline'],
                    'deadline_formatted' => $dlLabel,
                    'days_left' => 0,
                    'assigned_to' => $assigned,
                    'org_acronym' => $t['org_acronym'] ?? '',
                    'badge_text' => 'DEADLINE TODAY',
                    'badge_class' => 'badge-overdue',
                    'icon' => 'fa-bell',
                    'color' => '#d97706',
                    'bg_color' => '#fffbeb',
                    'border_color' => '#fde68a',
                    'headline' => "Due Today Reminder: \"{$t['title']}\"",
                    'message' => "Urgent Reminder: Today ($dlLabel) is the target completion deadline for your assigned task! Current status: '{$t['status']}'.",
                    'link' => '../leadership/tasks.php'
                ];
            } elseif ($daysLeft === 1) {
                $reminders[] = [
                    'category' => 'due_tomorrow',
                    'module' => 'Task',
                    'item_id' => intval($t['id']),
                    'title' => $t['title'],
                    'status' => $t['status'],
                    'priority' => $t['priority'],
                    'deadline' => $t['deadline'],
                    'deadline_formatted' => $dlLabel,
                    'days_left' => 1,
                    'assigned_to' => $assigned,
                    'org_acronym' => $t['org_acronym'] ?? '',
                    'badge_text' => 'TOMORROW DEADLINE',
                    'badge_class' => 'badge-in-progress',
                    'icon' => 'fa-clock',
                    'color' => '#b45309',
                    'bg_color' => '#fffbeb',
                    'border_color' => '#fcd34d',
                    'headline' => "Reminder — Tomorrow is the Deadline: \"{$t['title']}\"",
                    'message' => "Almost on target completion! Tomorrow ($dlLabel) is the deadline for your assigned task. Current status: '{$t['status']}'.",
                    'link' => '../leadership/tasks.php'
                ];
            } elseif ($daysLeft >= 2 && $daysLeft <= 3) {
                $reminders[] = [
                    'category' => 'due_soon',
                    'module' => 'Task',
                    'item_id' => intval($t['id']),
                    'title' => $t['title'],
                    'status' => $t['status'],
                    'priority' => $t['priority'],
                    'deadline' => $t['deadline'],
                    'deadline_formatted' => $dlLabel,
                    'days_left' => $daysLeft,
                    'assigned_to' => $assigned,
                    'org_acronym' => $t['org_acronym'] ?? '',
                    'badge_text' => "ALMOST DUE ($daysLeft DAYS LEFT)",
                    'badge_class' => 'badge-planning',
                    'icon' => 'fa-hourglass-half',
                    'color' => '#0284c7',
                    'bg_color' => '#f0f9ff',
                    'border_color' => '#bae6fd',
                    'headline' => "Approaching Target Completion: \"{$t['title']}\"",
                    'message' => "Your assigned task is almost on target completion ($dlLabel — $daysLeft days remaining). Current status: '{$t['status']}'.",
                    'link' => '../leadership/tasks.php'
                ];
            } else {
                // Active upcoming task notification for the assigned personnel
                $reminders[] = [
                    'category' => 'active_task',
                    'module' => 'Task',
                    'item_id' => intval($t['id']),
                    'title' => $t['title'],
                    'status' => $t['status'],
                    'priority' => $t['priority'],
                    'deadline' => $t['deadline'],
                    'deadline_formatted' => $dlLabel,
                    'days_left' => $daysLeft,
                    'assigned_to' => $assigned,
                    'org_acronym' => $t['org_acronym'] ?? '',
                    'badge_text' => strtoupper($t['status']),
                    'badge_class' => $t['status'] === 'In Progress' ? 'badge-in-progress' : 'badge-todo',
                    'icon' => 'fa-list-check',
                    'color' => '#4f46e5',
                    'bg_color' => '#ffffff',
                    'border_color' => '#e2e8f0',
                    'headline' => "Task Assigned to You: \"{$t['title']}\"",
                    'message' => "Target completion date: $dlLabel ($daysLeft days left) • Current status: '{$t['status']}'.",
                    'link' => '../leadership/tasks.php'
                ];
            }
        }
    } catch (PDOException $e) {
        // Fail silently
    }

    return $reminders;
}

// Global PDO instance helper & automatic overdue task synchronization
$pdo = getDBConnection();
syncOverdueTasks($pdo);
?>
