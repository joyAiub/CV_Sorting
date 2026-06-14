<?php
/**
 * update_server_config.php
 * 
 * Updates the server count and logs the updater's name and ID.
 */
header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";
include __DIR__ . "/../config/auth.php";

// 1. Security Check
check_auth();
if (!has_permission('manage_server_allocation')) {
    echo json_encode(["status" => "error", "message" => "Permission denied."]);
    exit;
}

// 2. Parse Input
$data = json_decode(file_get_contents("php://input"), true);
$server_count = isset($data['server_count']) ? (int)$data['server_count'] : 0;

if ($server_count < 1 || $server_count > 99) {
    echo json_encode(["status" => "error", "message" => "Server count must be between 1 and 99."]);
    exit;
}

// 3. User Info from Session
$full_name = $_SESSION['full_name'] ?? 'Unknown';
$emp_id = $_SESSION['employee_id'] ?? 'N/A';

// 4. Update the record (we use ID 1 as the single config row)
$stmt = $conn->prepare("UPDATE server_config SET server_count = ?, last_updated_by = ?, last_updated_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
$stmt->bind_param("iss", $server_count, $full_name, $emp_id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success", 
        "message" => "Server configuration updated successfully.",
        "data" => [
            "server_count" => $server_count,
            "updated_by" => $full_name,
            "updated_id" => $emp_id
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update database: " . $conn->error]);
}

$conn->close();
?>
