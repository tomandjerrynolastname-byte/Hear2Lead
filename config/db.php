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
