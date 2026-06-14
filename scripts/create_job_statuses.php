<?php
include __DIR__ . '/../config/db.php';

// 1. Create job_statuses table
$sql = "CREATE TABLE IF NOT EXISTS job_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL UNIQUE,
    display_order INT NOT NULL DEFAULT 0
)";

if ($conn->query($sql)) {
    echo "job_statuses table created or already exists.\n";
} else {
    die("Error creating job_statuses table: " . $conn->error);
}

// 2. Seed initial statuses
$statuses = [
    ['Pending', 1],
    ['downloading', 2],
    ['screening', 3],
    ['completed', 4]
];

$stmt = $conn->prepare("INSERT IGNORE INTO job_statuses (status_name, display_order) VALUES (?, ?)");

foreach ($statuses as $status) {
    $stmt->bind_param("si", $status[0], $status[1]);
    $stmt->execute();
}
$stmt->close();
echo "job_statuses seeded successfully.\n";

$conn->close();
?>
