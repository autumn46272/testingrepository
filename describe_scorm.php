<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("DESCRIBE scorm_packages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in scorm_packages:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>