<?php
include 'c:\wamp64\www\CV_Sorting\config\db.php';
$res = $conn->query('DESCRIBE users');
$schema = [];
while($row = $res->fetch_assoc()) {
    $schema[] = $row;
}
echo json_encode($schema, JSON_PRETTY_PRINT);
?>
