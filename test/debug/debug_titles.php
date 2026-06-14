<?php
include("config/db.php");

echo "--- Current Job Titles in Job_List ---\n";
$res = $conn->query("SELECT DISTINCT job_title FROM Job_List");
while ($row = $res->fetch_assoc()) {
    echo "[" . $row['job_title'] . "]\n";
}

echo "\n--- Current Job Titles in Candidates ---\n";
$res = $conn->query("SELECT DISTINCT job_title, COUNT(*) as count FROM candidates GROUP BY job_title");
while ($row = $res->fetch_assoc()) {
    echo "[" . $row['job_title'] . "] - Count: " . $row['count'] . "\n";
}

$conn->close();
?>
