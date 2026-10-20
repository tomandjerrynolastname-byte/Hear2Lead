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

// Global PDO instance helper
$pdo = getDBConnection();
?>
