<?php
include __DIR__ . '/../config/db.php';

// 1. Add task_no column to Job_List if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM Job_List LIKE 'task_no'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE Job_List ADD COLUMN task_no VARCHAR(20) AFTER id")) {
        echo "Column 'task_no' added to Job_List.\n";
    } else {
        die("Error adding column 'task_no': " . $conn->error);
    }
} else {
    echo "Column 'task_no' already exists.\n";
}

// 2. Backfill task_no for existing jobs based on ID order
$result = $conn->query("SELECT id FROM Job_List ORDER BY id ASC");
$counter = 1;
$stmt = $conn->prepare("UPDATE Job_List SET task_no = ? WHERE id = ?");

while ($row = $result->fetch_assoc()) {
    $task_no = "TASK" . $counter++;
    $stmt->bind_param("si", $task_no, $row['id']);
    $stmt->execute();
    echo "Job ID {$row['id']} assigned task_no: $task_no\n";
}

$stmt->close();
$conn->close();
echo "Backfill complete.\n";
?>
