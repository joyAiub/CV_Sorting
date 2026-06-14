<?php
header("Content-Type: application/json");
include("../config/db.php"); // also defines REVIEWER_HMAC_SECRET

$data       = json_decode(file_get_contents("php://input"), true);
$jd_id      = trim($data['jd_id']      ?? '');
$emp_id     = strtoupper(trim($data['emp_id']     ?? ''));
$reid_token = trim($data['reid_token'] ?? '');

if (empty($jd_id) || empty($emp_id)) {
    echo json_encode(["valid" => false, "code" => "missing", "message" => "Missing parameters."]);
    exit;
}

// ── STRICT PATH (invitation link contains a signed token) ────────────────────
if (!empty($reid_token)) {
    // Compute the expected HMAC for the employee ID the user entered
    $expected_token = hash_hmac('sha256', $emp_id . '|' . $jd_id, REVIEWER_HMAC_SECRET);

    // Timing-safe comparison — prevents timing attacks
    if (!hash_equals($expected_token, $reid_token)) {
        echo json_encode([
            "valid"   => false,
            "code"    => "unauthorized",
            "message" => "This Employee ID does not match the person this review link was sent to. Only the invited reviewer can access this form."
        ]);
        exit;
    }

    // Token matches — employee is the right person, fetch their info
    $stmt = $conn->prepare(
        "SELECT employee_id, full_name, email, designation, department FROM employees WHERE employee_id = ?"
    );
    $stmt->bind_param("s", $emp_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if ($emp) {
        echo json_encode(["valid" => true, "employee" => $emp]);
    } else {
        echo json_encode(["valid" => false, "code" => "not_found", "message" => "Employee record not found."]);
    }
    exit;
}

// ── FALLBACK PATH (no token — link shared without mail, e.g. manually) ───────
// No token means we cannot verify the invitation — block access entirely.
$conn->close();
echo json_encode([
    "valid"   => false,
    "code"    => "no_token",
    "message" => "This link does not contain a valid reviewer token. Please use the original link sent to you by email."
]);