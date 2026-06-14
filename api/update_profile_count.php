<?php
header('Content-Type: application/json');
include("../config/db.php");

// Get input from JSON body or Query Parameters
$data = json_decode(file_get_contents('php://input'), true);
$jd_id = $data['jd_id'] ?? ($_GET['jd_id'] ?? '');
$count = $data['total_bdjobs_profile'] ?? ($_GET['total_bdjobs_profile'] ?? null);

if (empty($jd_id) || $count === null) {
    echo json_encode(["status" => "error", "message" => "jd_id and total_bdjobs_profile are required."]);
    exit;
}

$count = (int)$count;

$stmt = $conn->prepare("UPDATE Job_List SET total_bdjobs_profile = ? WHERE jd_id = ?");
$stmt->bind_param("is", $count, $jd_id);

if ($stmt->execute()) {
    // Verify existence for idempotent success
    $check = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
    $check->bind_param("s", $jd_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode([
            "status" => "success", 
            "message" => "Profile count updated successfully.",
            "jd_id" => $jd_id,
            "total_bdjobs_profile" => $count
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
