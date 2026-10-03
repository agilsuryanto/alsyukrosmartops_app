<?php
require_once __DIR__ . '/config/database.php';
$res = $conn->query("SELECT atasan_id FROM peminjaman LIMIT 1");
if (!$res) echo "Error: " . $conn->error;
else echo "Success!";
