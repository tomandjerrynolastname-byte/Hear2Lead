<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE tasks");
    $pdo->exec("TRUNCATE TABLE feedbacks");
    $pdo->exec("TRUNCATE TABLE projects");
    $pdo->exec("TRUNCATE TABLE accomplishments");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "SUCCESS: All tasks, suggestions/feedbacks, projects, and accomplishments have been completely removed.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
