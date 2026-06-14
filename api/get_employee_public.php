<?php
header("Content-Type: application/json");
include("../config/db.php");

$emp_id = isset($_GET['emp_id']) ? trim($_GET['emp_id']) : '';

if (empty($emp_id)) {
    echo json_encode(["status" => "error", "message" => "Employee ID required."]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT employee_id, full_name, email, designation, department FROM employees WHERE employee_id = ?"
);
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if ($employee) {
    echo json_encode(["status" => "success", "employee" => $employee]);
} else {
    echo json_encode(["status" => "not_found", "message" => "No employee found with this ID."]);
}