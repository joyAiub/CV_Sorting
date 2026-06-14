<?php
include __DIR__ . '/../config/db.php';

$empId = '097727';
$stmt = $conn->prepare("UPDATE users SET username = ? WHERE employee_id = ?");
$stmt->bind_param("ss", $empId, $empId);

if ($stmt->execute()) {
    echo "Username for Employee ID $empId has been updated to $empId.\n";
} else {
    echo "Error updating username: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
