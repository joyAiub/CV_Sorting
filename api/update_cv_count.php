<?php
header('Content-Type: application/json');
include("../config/db.php");

// Get input from JSON body or Query Parameters
$data = json_decode(file_get_contents('php://input'), true);
$jd_id = $data['jd_id'] ?? ($_GET['jd_id'] ?? '');
$count = $data['total_cv_download'] ?? ($_GET['total_cv_download'] ?? null);

if (empty($jd_id) || $count === null) {
    echo json_encode(["status" => "error", "message" => "jd_id and total_cv_download are required."]);
    exit;
}

$count = (int)$count;

$stmt = $conn->prepare("UPDATE Job_List SET total_cv_download = ? WHERE jd_id = ?");
$stmt->bind_param("is", $count, $jd_id);

if ($stmt->execute()) {
    // If affected_rows is 0, it might mean the ID exists but the value was already the same.
    // So we check if the JD ID actually exists in the table.
    $check = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
    $check->bind_param("s", $jd_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode([
            "status" => "success", 
            "message" => "Download count updated successfully.",
            "jd_id" => $jd_id,
            "total_cv_download" => $count
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "No task found with JD ID: $jd_id"]);
    }
    $check->close();
} else {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>
