<?php
header("Content-Type: application/json");
include("../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['shortlisted'])) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

$id = (int)$data['id'];
$shortlisted = $data['shortlisted'] ? 1 : 0;

$stmt = $conn->prepare("UPDATE candidates SET shortlisted = ? WHERE id = ?");
$stmt->bind_param("ii", $shortlisted, $id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "shortlisted" => $shortlisted]);
}
else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
