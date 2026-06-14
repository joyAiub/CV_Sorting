<?php
require 'c:\wamp64\www\CV_Sorting\config\db.php';
$res = $conn->query("SELECT task_no, created_by, allowed_viewers FROM Job_List WHERE created_by = '097727'");
while($row = $res->fetch_assoc()) {
    echo "Task: " . $row['task_no'] . " | Created by: " . $row['created_by'] . " | Allowed Viewers: '" . $row['allowed_viewers'] . "'\n";
}
?>
