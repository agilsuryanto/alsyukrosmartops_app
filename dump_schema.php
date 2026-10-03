<?php
require 'config/database.php';
$out = "";
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    $table = $row[0];
    $res2 = $conn->query("SHOW CREATE TABLE `$table`");
    if ($res2) {
        $row2 = $res2->fetch_row();
        $out .= $row2[1] . ";\n\n";
    }
}
file_put_contents('schema_dump_clean.txt', $out);
