<?php
include('config/db.php');
$res = $conn->query("SELECT username, role, permissions FROM users");
while($row = $res->fetch_assoc()) {
    echo "USER: " . $row['username'] . "\n";
    echo "ROLE: " . $row['role'] . "\n";
    echo "PERMS: " . $row['permissions'] . "\n\n";
}
