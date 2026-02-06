<?php
require 'config.php';

echo "Table: users\n";
$stmt = $pdo->query('SHOW COLUMNS FROM users');
foreach ($stmt->fetchAll() as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}

echo "\nTable: students\n";
$stmt = $pdo->query('SHOW COLUMNS FROM students');
foreach ($stmt->fetchAll() as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
?>
