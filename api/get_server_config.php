<?php
/**
 * get_server_config.php
 * 
 * Returns the current server configuration including server_count and audit metadata.
 */
header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";
include __DIR__ . "/../config/auth.php";

// Standard Auth check
check_auth();

$sql = "SELECT server_count, last_updated_by, last_updated_id, updated_at FROM server_config ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
        "status" => "success",
        "data" => [
            "server_count" => (int)$row['server_count'],
            "last_updated_by" => $row['last_updated_by'],
            "last_updated_id" => $row['last_updated_id'],
            "updated_at" => $row['updated_at']
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Server configuration not found."
    ]);
}

$conn->close();
?>
