<?php
include("config/db.php");

echo "--- Schema for candidates ---\n";
$result = $conn->query("DESCRIBE candidates");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n--- First 5 rows --- \n";
$result = $conn->query("SELECT * FROM candidates LIMIT 5");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

// Also check specific job title
$jt = "AI Engineer - Applied AI Developer";
echo "\n--- Count for '$jt' --- \n";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates WHERE job_title = ?");
$stmt->bind_param("s", $jt);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
echo "Count: " . $row['count'] . "\n";

$conn->close();
?>
