<?php
header("Content-Type: application/json");
session_start();
include("../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['field']) || !isset($data['value'])) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

$id = (int)$data['id'];
$field = $data['field'];
$value = (int)$data['value'];

// Whitelist allowed fields for security
$allowed_fields = ['shortlisted', 'confirmation'];
if (!in_array($field, $allowed_fields)) {
    echo json_encode(["status" => "error", "message" => "Invalid field"]);
    exit;
}

$sql = "UPDATE candidates SET $field = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $value, $id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
}
else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$stmt->close();
$conn->close();
?>
