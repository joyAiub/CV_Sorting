<?php
include __DIR__ . '/config/db.php';

// Fix the display_order to match intended sequence:
// Pending=1, downloading=2, screening=3, completed=4
$updates = [
    ['Pending', 1],
    ['downloading', 2],
    ['screening', 3],
    ['completed', 4],
];

$stmt = $conn->prepare("UPDATE job_statuses SET display_order = ? WHERE LOWER(status_name) = LOWER(?)");
foreach ($updates as $u) {
    $stmt->bind_param("is", $u[1], $u[0]);
    $stmt->execute();
    echo "Updated '{$u[0]}' => order {$u[1]}\n";
}
$stmt->close();

// Verify
echo "\n=== Final Order ===\n";
$r = $conn->query("SELECT * FROM job_statuses ORDER BY display_order");
while ($row = $r->fetch_assoc()) {
    echo "order:{$row['display_order']} | {$row['status_name']}\n";
}
$conn->close();
?>
