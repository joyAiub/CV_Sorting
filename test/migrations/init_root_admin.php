<?php
include("config/db.php");
$conn->query("CREATE TABLE IF NOT EXISTS root_admins (id VARCHAR(50) PRIMARY KEY, name VARCHAR(100))");
$conn->query("INSERT IGNORE INTO root_admins (id, name) VALUES ('097727', 'joy.ballav')");
echo "Table created and data inserted.";
$conn->close();
?>
