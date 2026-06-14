<?php
include("config/db.php");
$res = $conn->query("SELECT id, name, total_experience, rating FROM candidates ORDER BY created_at DESC LIMIT 10");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Exp: " . $row['total_experience'] . " | Rating: " . $row['rating'] . "\n";
}
?>
