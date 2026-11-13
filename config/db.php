<?php
// config/db.php - Database connection with auto-initialization & Supabase PostgreSQL support

// Suppress deprecation and notice output in production serverless runtimes
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
if (getenv('VERCEL')) {
    ini_set('display_errors', '0');
}

// Default local environment constants (overridden by environment variables if present)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'student_leadership_db');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: (getenv('DB_CONNECTION') === 'pgsql' ? '5432' : '3306'));

/**
 * PDO Subclass that adds cross-database compatibility (PostgreSQL / MySQL)
 */
class SupabaseCompatPDO extends PDO {
    private bool $isPgsql = false;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {
        $this->isPgsql = (strpos($dsn, 'pgsql:') === 0);
        parent::__construct($dsn, $username, $password, $options);
    }

    public function isPostgres(): bool {
        return $this->isPgsql;
    }

    private function transformSql(string $sql): string {
        if (!$this->isPgsql) {
            return $sql;
        }
        // Strip MySQL backticks
        $sql = str_replace('`', '', $sql);
        // Replace CURDATE() with CURRENT_DATE
        $sql = preg_replace('/\bCURDATE\(\)/i', 'CURRENT_DATE', $sql);
        // Replace DATEDIFF(a, b) with (CAST(a AS DATE) - CAST(b AS DATE))
        $sql = preg_replace('/\bDATEDIFF\s*\(\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i', '(CAST($1 AS DATE) - CAST($2 AS DATE))', $sql);
        // Replace FIELD(t.priority, 'High', 'Medium', 'Low') with CASE expression
        $sql = preg_replace(
            "/\bFIELD\s*\(\s*([a-zA-Z0-9_.]+)\s*,\s*'High'\s*,\s*'Medium'\s*,\s*'Low'\s*\)/i",
            "CASE $1 WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END",
            $sql
        );
        // Replace CRC32(...) with ABS(hashtext(...)) for PostgreSQL compatibility in live_sync.php
        $sql = preg_replace('/\bCRC32\s*\(/i', 'ABS(hashtext(', $sql);
        $sql = str_replace('ABS(hashtext(CONCAT_WS(', 'ABS(hashtext(CONCAT_WS(', $sql);
        // If CRC32 was replaced with ABS(hashtext(, close the extra parenthesis before ))
        if (strpos($sql, 'ABS(hashtext(') !== false) {
            $sql = preg_replace('/ABS\(hashtext\((CONCAT_WS\([^)]+\))\)\)/i', 'ABS(hashtext($1)::bigint)', $sql);
        }
        // Replace INSERT IGNORE INTO
        if (preg_match('/^\s*INSERT\s+IGNORE\s+INTO\s+(.+)$/is', $sql, $m)) {
            $sql = "INSERT INTO " . $m[1] . " ON CONFLICT DO NOTHING";
        }
        return $sql;
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = []) {
        return parent::prepare($this->transformSql($query), $options ?: []);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetchModeArgs) {
        $sql = $this->transformSql($query);
        if ($fetchMode !== null) {
            return parent::query($sql, $fetchMode, ...$fetchModeArgs);
        }
        return parent::query($sql);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement) {
        return parent::exec($this->transformSql($statement));
    }
}

/**
 * Persistent Serverless Database-Backed Session Handler (for Vercel & Multi-Instance Clusters)
 */
class SupabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private bool $readFailed = false;
    private bool $hadAuthSession = false;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    #[\ReturnTypeWillChange]
    public function open($path, $name) {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM user_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $res = $stmt->fetchColumn();
            $str = $res !== false ? (string)$res : '';
            if (strpos($str, 'user_id') !== false || strpos($str, 'student_id') !== false) {
                $this->hadAuthSession = true;
            }
            return $str;
        } catch (Throwable $e) {
            $this->readFailed = true;
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data) {
        $hasAuthNow = (strpos($data, 'user_id') !== false || strpos($data, 'student_id') !== false);
        // Never overwrite an existing session in DB if read() failed due to transient DB hiccup and current session has no auth data
        if ($this->readFailed && !$hasAuthNow) {
            return true;
        }
        try {
            // PostgreSQL upsert: if current write has no auth data, preserve existing DB data if existing DB row has user_id/student_id unless explicitly destroyed
            if (!$hasAuthNow && !$this->hadAuthSession) {
                $stmt = $this->pdo->prepare("INSERT INTO user_sessions (id, data, last_activity) VALUES (?, ?, ?) ON CONFLICT (id) DO UPDATE SET data = CASE WHEN user_sessions.data LIKE '%user_id%' OR user_sessions.data LIKE '%student_id%' THEN user_sessions.data ELSE EXCLUDED.data END, last_activity = EXCLUDED.last_activity");
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO user_sessions (id, data, last_activity) VALUES (?, ?, ?) ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_activity = EXCLUDED.last_activity");
            }
            return $stmt->execute([$id, $data, time()]);
        } catch (Throwable $e) {
            try {
                // MySQL replace fallback
                $stmt = $this->pdo->prepare("REPLACE INTO user_sessions (id, data, last_activity) VALUES (?, ?, ?)");
                return $stmt->execute([$id, $data, time()]);
            } catch (Throwable $e2) {
                return false;
            }
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function gc($max_lifetime) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE last_activity < ?");
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return false;
        }
    }
}

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $databaseUrl = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('SUPABASE_DB_URL') ?: '';
        $dbDriver = strtolower(getenv('DB_CONNECTION') ?: '');

        $isPgsql = false;
        if (!empty($databaseUrl) || $dbDriver === 'pgsql' || strpos($databaseUrl, 'postgres') !== false) {
            $isPgsql = true;
        }

        if ($isPgsql) {
            // Supabase / PostgreSQL Connection
            try {
                if (!empty($databaseUrl)) {
                    $parts = parse_url($databaseUrl);
                    $host = $parts['host'] ?? '127.0.0.1';
                    $port = $parts['port'] ?? 5432;
                    $user = isset($parts['user']) ? urldecode($parts['user']) : 'postgres';
                    $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
                    $dbname = ltrim($parts['path'] ?? 'postgres', '/');
                } else {
                    $host = DB_HOST;
                    $port = DB_PORT;
                    $user = DB_USER;
                    $pass = DB_PASS;
                    $dbname = DB_NAME;
                }

                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
                $pdo = new SupabaseCompatPDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);

                // Auto-create user_sessions & password_reset_tickets tables & fix demo student password hash in Supabase PostgreSQL
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
                        id VARCHAR(128) PRIMARY KEY,
                        data TEXT NOT NULL,
                        last_activity INT NOT NULL
                    )");
                    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tickets (
                        id SERIAL PRIMARY KEY,
                        account_type VARCHAR(20) NOT NULL DEFAULT 'Student',
                        account_id INT NOT NULL,
                        identifier_submitted VARCHAR(150) NOT NULL,
                        user_name VARCHAR(120) NOT NULL,
                        user_email VARCHAR(120) DEFAULT NULL,
                        account_details VARCHAR(255) DEFAULT NULL,
                        request_reason TEXT DEFAULT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                        resolved_by INT DEFAULT NULL,
                        resolved_by_name VARCHAR(120) DEFAULT NULL,
                        resolution_notes TEXT DEFAULT NULL,
                        resolved_at TIMESTAMP DEFAULT NULL,
                        ip_address VARCHAR(60) DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    $updDemo = $pdo->prepare("UPDATE students SET password = ? WHERE student_lrn IN ('2026-0001', '2026-0002', '2026-0003') AND password = ?");
                    $updDemo->execute(['$2y$10$UGMFyI2pF9yi3cFxn5yO5.JwwxC9NYERdxxJzGBSYNecYPja9EiiO', '$2y$10$dUHysNgmk6Q2.NG7U9A11exvwZx7XI6/caGn8eA/tRGQTfs0iPw9m']);
                    // Ensure organizations.logo supports base64 Data URIs on serverless deployments
                    $logoColType = $pdo->query("SELECT data_type FROM information_schema.columns WHERE table_name = 'organizations' AND column_name = 'logo'")->fetchColumn();
                    if ($logoColType && strtolower($logoColType) !== 'text') {
                        $pdo->exec("ALTER TABLE organizations ALTER COLUMN logo TYPE TEXT");
                    }
                } catch (PDOException $e) {
                    // Ignore if table already exists or connection is read-only
                }
            } catch (PDOException $e) {
                die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; border: 1px solid #f87171; color: #991b1b; border-radius: 8px; margin: 20px;'>
                    <h3>Supabase PostgreSQL Database Connection Error</h3>
                    <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <p>Please check your Supabase Database credentials (DATABASE_URL or DB_HOST, DB_USER, DB_PASS, DB_NAME)!</p>
                </div>");
            }
        } else {
            // Local MySQL (XAMPP) Connection
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
                $pdo = new SupabaseCompatPDO($dsn, DB_USER, DB_PASS, [
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

                    $demoPassword = password_hash('student123', PASSWORD_DEFAULT);
                    $seedStmt = $pdo->prepare("INSERT INTO `students` (`student_lrn`, `full_name`, `grade_section`, `email`, `password`) VALUES (?, ?, ?, ?, ?)");
                    $seedStmt->execute(['2026-0001', 'Alex Morgan', 'Grade 11 - STEM', 'alex.morgan@student.edu.ph', $demoPassword]);
                    $seedStmt->execute(['2026-0002', 'Samantha Cruz', 'Grade 10 - Rizal', 'samantha.cruz@student.edu.ph', $demoPassword]);
                }

                $studentStatusColCheck = $pdo->query("SHOW COLUMNS FROM `students` LIKE 'status'")->rowCount();
                if ($studentStatusColCheck === 0) {
                    $pdo->exec("ALTER TABLE `students`
                        ADD COLUMN `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending' AFTER `password`,
                        ADD COLUMN `approved_by` INT DEFAULT NULL AFTER `status`,
                        ADD COLUMN `approved_at` DATETIME DEFAULT NULL AFTER `approved_by`");
                    $pdo->exec("UPDATE `students` SET `status` = 'Approved', `approved_at` = NOW() WHERE `status` = 'Pending'");
                }

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

                $pdo->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
                    `id` VARCHAR(128) PRIMARY KEY,
                    `data` TEXT NOT NULL,
                    `last_activity` INT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("DELETE FROM feedbacks WHERE student_name = 'Anonymous Student' OR student_name IS NULL OR TRIM(student_name) = ''");
                $pdo->exec("ALTER TABLE `tasks` MODIFY `assigned_to` TEXT DEFAULT NULL");

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

                $colCheck = $pdo->query("SHOW COLUMNS FROM `feedbacks` LIKE 'student_id'")->rowCount();
                if ($colCheck === 0) {
                    $pdo->exec("ALTER TABLE `feedbacks` ADD COLUMN `student_id` INT DEFAULT NULL AFTER `org_id`");
                }

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

                $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(100) NOT NULL DEFAULT 'Officer'");

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

                $pdo->exec("CREATE TABLE IF NOT EXISTS `password_reset_tickets` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `account_type` VARCHAR(20) NOT NULL DEFAULT 'Student',
                    `account_id` INT DEFAULT NULL,
                    `identifier_submitted` VARCHAR(150) NOT NULL,
                    `user_name` VARCHAR(150) NOT NULL,
                    `user_email` VARCHAR(150) DEFAULT NULL,
                    `account_details` VARCHAR(255) DEFAULT NULL,
                    `request_reason` TEXT DEFAULT NULL,
                    `status` ENUM('Pending', 'Resolved', 'Rejected') NOT NULL DEFAULT 'Pending',
                    `admin_notes` TEXT DEFAULT NULL,
                    `resolved_by` INT DEFAULT NULL,
                    `resolved_by_name` VARCHAR(100) DEFAULT NULL,
                    `resolved_at` DATETIME DEFAULT NULL,
                    `new_temp_password` VARCHAR(100) DEFAULT NULL,
                    `ip_address` VARCHAR(60) DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Ensure organizations.logo supports base64 Data URIs
                $logoColInfo = $pdo->query("SHOW COLUMNS FROM `organizations` LIKE 'logo'")->fetch();
                if ($logoColInfo && stripos($logoColInfo['Type'] ?? '', 'varchar') !== false) {
                    $pdo->exec("ALTER TABLE `organizations` MODIFY COLUMN `logo` LONGTEXT DEFAULT NULL");
                }

            } catch (PDOException $e) {
                die("<div style='padding: 20px; font-family: sans-serif; background: #fee2e2; border: 1px solid #f87171; color: #991b1b; border-radius: 8px; margin: 20px;'>
                    <h3>Database Connection Error</h3>
                    <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <p>Please make sure your database service is running!</p>
                </div>");
            }
        }
    }

    return $pdo;
}

/**
 * Resolve an organization's logo value (Data URI, external URL, or local filename) into a renderable src URL.
 */
function getOrgLogoUrl(?string $logo, string $relativePrefix = '../'): ?string {
    $logo = trim((string)$logo);
    if ($logo === '') {
        return null;
    }
    if (strpos($logo, 'data:image/') === 0 || strpos($logo, 'http://') === 0 || strpos($logo, 'https://') === 0) {
        return $logo;
    }
    $filename = basename($logo);
    $localPath = __DIR__ . '/../assets/uploads/logos/' . $filename;
    if (file_exists($localPath)) {
        return $relativePrefix . 'assets/uploads/logos/' . rawurlencode($filename);
    }
    return null;
}

// Session initialization with serverless support
if (session_status() === PHP_SESSION_NONE && !defined('H2L_SKIP_DB_SESSION')) {
    $isSecureReq = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'secure' => $isSecureReq,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    if (getenv('VERCEL') || getenv('USE_DB_SESSIONS') || getenv('DATABASE_URL') || getenv('POSTGRES_URL') || getenv('SUPABASE_DB_URL') || strtolower(getenv('DB_CONNECTION') ?: '') === 'pgsql') {
        try {
            $sessionPdo = getDBConnection();
            $handler = new SupabaseSessionHandler($sessionPdo);
            session_set_save_handler($handler, true);
        } catch (Throwable $e) {
            // Fallback to standard session if DB handler fails
        }
    }
    session_start();
}

/**
 * Secret key for signing stateless CAPTCHA & Auth backup tokens on serverless deployments
 */
function getCaptchaSecretKey(): string {
    return hash('sha256', 'Hear2Lead_Captcha_Secret_2026|' . (getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: DB_HOST . '|' . DB_NAME));
}

/**
 * Set HMAC-signed Officer/Admin authentication backup cookie for serverless session persistence
 */
function setOfficerAuthCookie(array $userData): void {
    $payload = [
        'user_id' => intval($userData['user_id'] ?? ($userData['id'] ?? 0)),
        'user_name' => (string)($userData['user_name'] ?? ($userData['full_name'] ?? '')),
        'user_role' => (string)($userData['user_role'] ?? ($userData['role'] ?? 'Officer')),
        'org_id' => intval($userData['org_id'] ?? 0),
        'org_name' => (string)($userData['org_name'] ?? ''),
        'org_acronym' => (string)($userData['org_acronym'] ?? ($userData['acronym'] ?? '')),
        'exp' => time() + (86400 * 7)
    ];
    if ($payload['user_id'] <= 0) {
        return;
    }
    $b64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, getCaptchaSecretKey());
    $token = $b64 . '.' . $sig;
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @setcookie('h2l_officer_auth', $token, [
        'expires' => $payload['exp'],
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Clear Officer/Admin authentication backup cookie on logout
 */
function clearOfficerAuthCookie(): void {
    unset($_COOKIE['h2l_officer_auth']);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @setcookie('h2l_officer_auth', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Set HMAC-signed Student authentication backup cookie for serverless session persistence
 */
function setStudentAuthCookie(array $studentData): void {
    $payload = [
        'student_id' => intval($studentData['student_id'] ?? ($studentData['id'] ?? 0)),
        'student_lrn' => (string)($studentData['student_lrn'] ?? ''),
        'student_name' => (string)($studentData['student_name'] ?? ($studentData['full_name'] ?? '')),
        'student_grade_section' => (string)($studentData['student_grade_section'] ?? ($studentData['grade_section'] ?? '')),
        'student_email' => (string)($studentData['student_email'] ?? ($studentData['email'] ?? '')),
        'student_status' => (string)($studentData['student_status'] ?? ($studentData['status'] ?? 'Approved')),
        'exp' => time() + (86400 * 7)
    ];
    if ($payload['student_id'] <= 0) {
        return;
    }
    $b64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, getCaptchaSecretKey());
    $token = $b64 . '.' . $sig;
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @setcookie('h2l_student_auth', $token, [
        'expires' => $payload['exp'],
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Clear Student authentication backup cookie on logout
 */
function clearStudentAuthCookie(): void {
    unset($_COOKIE['h2l_student_auth']);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @setcookie('h2l_student_auth', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Automatically restore Officer/Admin or Student session from HMAC-signed backup cookie if serverless session was dropped
 */
function restoreAuthSessionFromCookie(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $secret = getCaptchaSecretKey();
    if (!isset($_SESSION['user_id']) && !empty($_COOKIE['h2l_officer_auth'])) {
        $parts = explode('.', (string)$_COOKIE['h2l_officer_auth'], 2);
        if (count($parts) === 2) {
            [$b64, $sig] = $parts;
            $expected = hash_hmac('sha256', $b64, $secret);
            if (hash_equals($expected, $sig)) {
                $json = base64_decode(strtr($b64, '-_', '+/'), true);
                $data = $json ? json_decode($json, true) : null;
                if (is_array($data) && !empty($data['user_id']) && intval($data['exp'] ?? 0) > time()) {
                    $_SESSION['user_id'] = intval($data['user_id']);
                    $_SESSION['user_name'] = (string)($data['user_name'] ?? 'Officer');
                    $_SESSION['user_role'] = (string)($data['user_role'] ?? 'Officer');
                    $_SESSION['org_id'] = intval($data['org_id'] ?? 1);
                    $_SESSION['org_name'] = (string)($data['org_name'] ?? 'Organization');
                    $_SESSION['org_acronym'] = (string)($data['org_acronym'] ?? 'ORG');
                    $_SESSION['login_logged'] = true;
                }
            }
        }
    } elseif (isset($_SESSION['user_id']) && empty($_COOKIE['h2l_officer_auth']) && !headers_sent()) {
        setOfficerAuthCookie($_SESSION);
    }

    if (!isset($_SESSION['student_id']) && !isset($_SESSION['user_id']) && !empty($_COOKIE['h2l_student_auth'])) {
        $parts = explode('.', (string)$_COOKIE['h2l_student_auth'], 2);
        if (count($parts) === 2) {
            [$b64, $sig] = $parts;
            $expected = hash_hmac('sha256', $b64, $secret);
            if (hash_equals($expected, $sig)) {
                $json = base64_decode(strtr($b64, '-_', '+/'), true);
                $data = $json ? json_decode($json, true) : null;
                if (is_array($data) && !empty($data['student_id']) && intval($data['exp'] ?? 0) > time()) {
                    $_SESSION['student_id'] = intval($data['student_id']);
                    $_SESSION['student_lrn'] = (string)($data['student_lrn'] ?? '');
                    $_SESSION['student_name'] = (string)($data['student_name'] ?? 'Student');
                    $_SESSION['student_grade_section'] = (string)($data['student_grade_section'] ?? '');
                    $_SESSION['student_email'] = (string)($data['student_email'] ?? '');
                    $_SESSION['student_status'] = (string)($data['student_status'] ?? 'Approved');
                    $_SESSION['login_logged'] = true;
                }
            }
        }
    } elseif (isset($_SESSION['student_id']) && empty($_COOKIE['h2l_student_auth']) && !headers_sent()) {
        setStudentAuthCookie($_SESSION);
    }
}

restoreAuthSessionFromCookie();

/**
 * Store generated CAPTCHA code in both Session and HMAC-signed Cookie for serverless reliability
 */
function storeCaptchaCode(string $captchaCode, string $type = 'default'): void {
    $normCode = strtoupper(trim($captchaCode));
    $_SESSION['student_reg_captcha'] = $normCode;
    $_SESSION['captcha_code'] = $normCode;
    if ($type === 'forgot') {
        $_SESSION['forgot_captcha'] = $normCode;
    }

    if (!isset($_SESSION['captcha_valid_codes']) || !is_array($_SESSION['captcha_valid_codes'])) {
        $_SESSION['captcha_valid_codes'] = [];
    }
    $_SESSION['captcha_valid_codes'][] = $normCode;
    if (count($_SESSION['captcha_valid_codes']) > 8) {
        $_SESSION['captcha_valid_codes'] = array_slice($_SESSION['captcha_valid_codes'], -8);
    }

    // Build signed HMAC cookie containing recent valid signatures (valid for 20 minutes)
    $secret = getCaptchaSecretKey();
    $ts = time();
    $sig = hash_hmac('sha256', $ts . '|' . $normCode, $secret);
    $entry = $ts . '.' . $sig;

    $existing = [];
    if (!empty($_COOKIE['h2l_captcha_pool'])) {
        foreach (explode(',', (string)$_COOKIE['h2l_captcha_pool']) as $part) {
            $part = trim($part);
            if ($part !== '' && strpos($part, '.') !== false) {
                [$pts] = explode('.', $part, 2);
                if (is_numeric($pts) && ($ts - intval($pts)) < 1200) {
                    $existing[] = $part;
                }
            }
        }
    }
    $existing[] = $entry;
    if (count($existing) > 8) {
        $existing = array_slice($existing, -8);
    }
    $cookieVal = implode(',', $existing);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    @setcookie('h2l_captcha_pool', $cookieVal, [
        'expires' => $ts + 1200,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Verify user-submitted CAPTCHA code against Session OR HMAC-signed Cookie pool
 */
function verifyCaptchaCode(string $submittedCode): bool {
    $normInput = strtoupper(trim($submittedCode));
    if ($normInput === '') {
        return false;
    }

    // 1. Check direct session keys
    $candidates = [
        $_SESSION['student_reg_captcha'] ?? '',
        $_SESSION['captcha_code'] ?? '',
        $_SESSION['forgot_captcha'] ?? ''
    ];
    if (!empty($_SESSION['captcha_valid_codes']) && is_array($_SESSION['captcha_valid_codes'])) {
        $candidates = array_merge($candidates, $_SESSION['captcha_valid_codes']);
    }
    foreach ($candidates as $cand) {
        $candNorm = strtoupper(trim((string)$cand));
        if ($candNorm !== '' && hash_equals($candNorm, $normInput)) {
            return true;
        }
    }

    // 2. Check HMAC-signed cookie pool (serverless fallback)
    if (!empty($_COOKIE['h2l_captcha_pool'])) {
        $secret = getCaptchaSecretKey();
        $now = time();
        foreach (explode(',', (string)$_COOKIE['h2l_captcha_pool']) as $part) {
            $part = trim($part);
            if (strpos($part, '.') === false) {
                continue;
            }
            [$tsStr, $sig] = explode('.', $part, 2);
            if (!is_numeric($tsStr)) {
                continue;
            }
            $ts = intval($tsStr);
            if (($now - $ts) > 1200 || ($ts - $now) > 60) {
                continue;
            }
            $expectedSig = hash_hmac('sha256', $ts . '|' . $normInput, $secret);
            if (hash_equals($expectedSig, $sig)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Clear stored CAPTCHA codes after validation attempt
 */
function clearCaptchaCode(): void {
    unset($_SESSION['student_reg_captcha'], $_SESSION['captcha_code'], $_SESSION['forgot_captcha'], $_SESSION['captcha_valid_codes']);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    @setcookie('h2l_captcha_pool', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
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
        $stmt = $pdo->prepare("INSERT INTO login_logs (account_type, account_id, org_id, org_acronym, user_name, user_role, log_details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$accountType, $accountId, $orgId, $orgAcronym, $userName, $userRole, $details, $ip]);
    } catch (PDOException $e) {
        // Fail silently if table is not ready
    }
}

/**
 * Record a student account registration, approval, rejection, or status change in student_logs
 */
function recordStudentAccountLog(?int $studentId, string $studentLrn, string $studentName, ?int $userId, string $userName, string $userRole, string $actionType, ?string $previousStatus, string $newStatus, string $logDetails, ?PDO $pdo = null): void {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO student_logs (student_id, student_lrn, student_name, user_id, user_name, user_role, action_type, previous_status, new_status, log_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $studentLrn, $studentName, $userId, $userName, $userRole, $actionType, $previousStatus, $newStatus, $logDetails]);
    } catch (PDOException $e) {
        // Fail silently if table is not ready
    }
}

/**
 * Get count of pending password reset tickets (Admin only)
 */
function getPendingResetTicketsCount(?PDO $pdo = null): int {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM password_reset_tickets WHERE status = 'Pending'");
        return intval($stmt->fetchColumn());
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Fetch all roles from the Dynamic Role Library ordered by hierarchy
 */
function getAllUserRoles(?PDO $pdo = null): array {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->query("SELECT * FROM user_roles ORDER BY 
            CASE base_role
                WHEN 'Admin' THEN 1
                WHEN 'President' THEN 2
                WHEN 'Vice President' THEN 3
                WHEN 'Secretary' THEN 4
                WHEN 'Treasurer' THEN 5
                ELSE 6
            END ASC,
            is_system DESC,
            role_name ASC");
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
        $stmt = $pdo->prepare("SELECT * FROM user_roles WHERE LOWER(role_name) = LOWER(?) LIMIT 1");
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
        // 1. Find tasks that just exceeded their deadline and are not yet 'Accomplished' or 'Overdue'
        $stmt = $pdo->query("SELECT id, title, status, deadline, assigned_to FROM tasks WHERE deadline < CURRENT_DATE AND status NOT IN ('Accomplished', 'Overdue')");
        $newlyOverdue = $stmt->fetchAll();

        if (!empty($newlyOverdue)) {
            $updStmt = $pdo->prepare("UPDATE tasks SET status = 'Overdue' WHERE id = ?");
            $logStmt = $pdo->prepare("INSERT INTO task_logs (task_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, NULL, 'System Auto-Monitor', 'System', 'Auto-Overdue', ?)");

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
        $stmtExisting = $pdo->query("SELECT t.id, t.title, t.deadline FROM tasks t
            LEFT JOIN task_logs tl ON tl.task_id = t.id AND tl.action_type = 'Auto-Overdue'
            WHERE t.status = 'Overdue' AND tl.id IS NULL");
        $unloggedOverdue = $stmtExisting->fetchAll();
        if (!empty($unloggedOverdue)) {
            $logStmt = $pdo->prepare("INSERT INTO task_logs (task_id, user_id, user_name, user_role, action_type, log_details) VALUES (?, NULL, 'System Auto-Monitor', 'System', 'Auto-Overdue', ?)");
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
        if (strcasecmp($part, 'All Officers') === 0 && strcasecmp($userRole, 'Admin') !== 0) {
            return true;
        }
        if (strcasecmp($part, $userName) === 0) {
            return true;
        }
        if ($userRole !== '' && strcasecmp($part, $userName . ' (' . $userRole . ')') === 0) {
            return true;
        }
        $nameOnly = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $part));
        if ($nameOnly !== '' && strcasecmp($nameOnly, $userName) === 0) {
            return true;
        }
        if ($userRole !== '' && strcasecmp($part, $userRole) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Fetch active Task Deadline Reminders ONLY for the respective assigned personnel
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

        $sql = "SELECT t.*, DATEDIFF(t.deadline, CURRENT_DATE) as days_left, o.acronym as org_acronym, p.title as project_title
                FROM tasks t
                LEFT JOIN organizations o ON t.org_id = o.id
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE $whereSql
                ORDER BY t.deadline ASC, FIELD(t.priority, 'High', 'Medium', 'Low')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        foreach ($tasks as $t) {
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

/**
 * Compute per-personnel task assignment and status breakdown
 */
function getIndividualPersonnelTaskStats(?PDO $pdo, array $taskRows): array {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    $officersByOrg = [];
    $canonicalByOrgAndKey = [];
    try {
        $stmt = $pdo->query("SELECT u.id, u.org_id, u.full_name, u.role, o.acronym as org_acronym
                             FROM users u
                             LEFT JOIN organizations o ON u.org_id = o.id
                             WHERE u.role != 'Admin'
                             ORDER BY u.full_name ASC");
        foreach ($stmt->fetchAll() as $u) {
            $oid = intval($u['org_id']);
            $label = trim($u['full_name']) . ' (' . trim($u['role']) . ')';
            $info = [
                'label' => $label,
                'full_name' => trim($u['full_name']),
                'role' => trim($u['role']),
                'org_acronym' => $u['org_acronym'] ?? ''
            ];
            $officersByOrg[$oid][] = $info;
            $canonicalByOrgAndKey[$oid][strtolower($label)] = $info;
            $canonicalByOrgAndKey[$oid][strtolower(trim($u['full_name']))] = $info;
        }
    } catch (PDOException $e) {
        // Continue with raw assignee strings if users query fails
    }

    $statsMap = [];

    foreach ($taskRows as $t) {
        $oid = intval($t['org_id'] ?? 0);
        $taskOrgAcronym = $t['org_acronym'] ?? '';
        $status = $t['status'] ?? 'To Do';
        $rawAssigned = trim($t['assigned_to'] ?? '');

        $resolvedPersonnel = [];
        if ($rawAssigned === '') {
            $resolvedPersonnel['unassigned|' . $oid] = [
                'assigned_to' => 'Unassigned',
                'role' => '',
                'org_acronym' => $taskOrgAcronym
            ];
        } else {
            $parts = array_values(array_filter(array_map('trim', explode(',', $rawAssigned))));
            foreach ($parts as $part) {
                if (strcasecmp($part, 'All Officers') === 0 && !empty($officersByOrg[$oid])) {
                    foreach ($officersByOrg[$oid] as $off) {
                        $key = strtolower($off['label']) . '|' . $oid;
                        $resolvedPersonnel[$key] = [
                            'assigned_to' => $off['label'],
                            'role' => $off['role'],
                            'org_acronym' => $off['org_acronym'] ?: $taskOrgAcronym
                        ];
                    }
                } else {
                    $lookupKey = strtolower($part);
                    $nameOnlyKey = strtolower(trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $part)));
                    if (isset($canonicalByOrgAndKey[$oid][$lookupKey])) {
                        $off = $canonicalByOrgAndKey[$oid][$lookupKey];
                        $key = strtolower($off['label']) . '|' . $oid;
                        $resolvedPersonnel[$key] = [
                            'assigned_to' => $off['label'],
                            'role' => $off['role'],
                            'org_acronym' => $off['org_acronym'] ?: $taskOrgAcronym
                        ];
                    } elseif ($nameOnlyKey !== '' && isset($canonicalByOrgAndKey[$oid][$nameOnlyKey])) {
                        $off = $canonicalByOrgAndKey[$oid][$nameOnlyKey];
                        $key = strtolower($off['label']) . '|' . $oid;
                        $resolvedPersonnel[$key] = [
                            'assigned_to' => $off['label'],
                            'role' => $off['role'],
                            'org_acronym' => $off['org_acronym'] ?: $taskOrgAcronym
                        ];
                    } else {
                        $roleMatch = '';
                        if (preg_match('/\(([^)]+)\)\s*$/', $part, $m)) {
                            $roleMatch = trim($m[1]);
                        }
                        $key = strtolower($part) . '|' . $oid;
                        $resolvedPersonnel[$key] = [
                            'assigned_to' => $part,
                            'role' => $roleMatch,
                            'org_acronym' => $taskOrgAcronym
                        ];
                    }
                }
            }
        }

        foreach ($resolvedPersonnel as $personKey => $personInfo) {
            if (!isset($statsMap[$personKey])) {
                $statsMap[$personKey] = [
                    'assigned_to' => $personInfo['assigned_to'],
                    'role' => $personInfo['role'],
                    'org_acronym' => $personInfo['org_acronym'],
                    'total_assigned' => 0,
                    'count_accomplished' => 0,
                    'count_in_progress' => 0,
                    'count_overdue' => 0,
                    'count_todo' => 0
                ];
            }
            $statsMap[$personKey]['total_assigned']++;
            if ($status === 'Accomplished') {
                $statsMap[$personKey]['count_accomplished']++;
            } elseif ($status === 'In Progress') {
                $statsMap[$personKey]['count_in_progress']++;
            } elseif ($status === 'Overdue') {
                $statsMap[$personKey]['count_overdue']++;
            } else {
                $statsMap[$personKey]['count_todo']++;
            }
        }
    }

    $result = array_values($statsMap);
    usort($result, function ($a, $b) {
        if ($b['total_assigned'] !== $a['total_assigned']) {
            return $b['total_assigned'] <=> $a['total_assigned'];
        }
        if ($b['count_accomplished'] !== $a['count_accomplished']) {
            return $b['count_accomplished'] <=> $a['count_accomplished'];
        }
        return strcasecmp($a['assigned_to'], $b['assigned_to']);
    });

    return $result;
}

// Global PDO instance helper & automatic overdue task synchronization
if (!defined('H2L_SKIP_DB_SESSION')) {
    $pdo = getDBConnection();
    syncOverdueTasks($pdo);
}
?>