<?php
include("config/db.php");
$res = $conn->query("SELECT * FROM Job_List");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
$conn->close();
?>
