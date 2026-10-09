<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE organizations ADD COLUMN logo VARCHAR(255) DEFAULT NULL AFTER acronym");
    echo "SUCCESS: Column 'logo' added to table 'organizations'.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "INFO: Column 'logo' already exists.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
?>
