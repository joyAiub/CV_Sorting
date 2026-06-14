<?php
include __DIR__ . '/config/db.php';
echo "=== job_statuses table ===\n";
$r = $conn->query("SELECT * FROM job_statuses ORDER BY display_order");
while ($row = $r->fetch_assoc()) {
    echo "id:" . $row['id'] . " | " . $row['status_name'] . " | order:" . $row['display_order'] . "\n";
}
?>
