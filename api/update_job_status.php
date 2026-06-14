<?php
include __DIR__ . '/../config/db.php';
include __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

check_auth();
if (!has_permission('manage_tasks')) {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$job_id  = intval($data['job_id'] ?? 0);
$status  = trim($data['status'] ?? '');

if (!$job_id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid job_id or status.']);
    exit;
}

// Validate status exists in job_statuses table
$check = $conn->prepare("SELECT id FROM job_statuses WHERE status_name = ?");
$check->bind_param("s", $status);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Status does not exist.']);
    $check->close();
    exit;
}
$check->close();

$stmt = $conn->prepare("UPDATE Job_List SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $job_id);
if ($stmt->execute() && $stmt->affected_rows >= 0) {
    echo json_encode(['status' => 'success', 'message' => 'Job status updated.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status.']);
}
$stmt->close();
$conn->close();
?>
