<?php
include __DIR__ . '/config/db.php';
$res = $conn->query("SELECT id, employee_id, username, role, status FROM users");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
