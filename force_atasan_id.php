<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain');

$q = "ALTER TABLE peminjaman ADD COLUMN atasan_id INT DEFAULT NULL";
if ($conn->query($q)) {
    echo "Success: $q\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$q2 = "ALTER TABLE event ADD COLUMN atasan_id INT DEFAULT NULL";
if ($conn->query($q2)) {
    echo "Success: $q2\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
