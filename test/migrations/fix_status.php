<?php
include __DIR__ . '/config/db.php';
$result = $conn->query("UPDATE Job_List SET status = 'downloading' WHERE LOWER(TRIM(status)) = 'in progress' OR LOWER(TRIM(status)) = 'in-progress'");
echo "Rows affected: " . $conn->affected_rows . "\n";
// Show current statuses
$r = $conn->query("SELECT id, job_title, status FROM Job_List");
while ($row = $r->fetch_assoc()) {
    echo $row['id'] . " | " . $row['status'] . " | " . $row['job_title'] . "\n";
}
?>
