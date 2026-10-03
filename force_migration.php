<?php
require_once __DIR__ . '/config/database.php';
$tables = ['peminjaman', 'event', 'pengajuan_maintenance'];
foreach ($tables as $t) {
    try {
        $conn->query("ALTER TABLE $t ADD COLUMN catatan_atasan VARCHAR(255) DEFAULT NULL");
    } catch (\Throwable $e) {}
    try {
        $conn->query("ALTER TABLE $t MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan'");
    } catch (\Throwable $e) {}
}
echo "Done forcing migrations.";
