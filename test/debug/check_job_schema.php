<?php
include __DIR__ . '/config/db.php';
$result = $conn->query("DESCRIBE Job_List");
while($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
