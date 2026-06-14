<?php
include("config/db.php");
$res = $conn->query("DESCRIBE candidates");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
