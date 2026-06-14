<?php
header("Content-Type: application/json");
include("../config/db.php");
include("../config/auth.php");

if (session_status() === PHP_SESSION_NONE) session_start();

$data = json_decode(file_get_contents("php://input"), true);
$candidate_id = isset($data['candidate_id']) ? (int)$data['candidate_id'] : 0;
$jd_id        = isset($data['jd_id']) ? trim($data['jd_id']) : '';
$comment      = isset($data['feedback_comment']) ? trim($data['feedback_comment']) : '';
$recommended  = isset($data['feedback_recommended']) ? (int)(bool)$data['feedback_recommended'] : 0;

if (!$candidate_id || empty($jd_id)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

// Feedback editing is only available through the public review link (not while logged in)
if (isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Feedback can only be edited through the public review link."]);
    exit;
}

// Verify jd_id exists
$stmt_v = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
$stmt_v->bind_param("s", $jd_id);
$stmt_v->execute();
if ($stmt_v->get_result()->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid task reference."]);
    exit;
}
$stmt_v->close();

// Verify candidate belongs to this task
$stmt_c = $conn->prepare("SELECT id FROM candidates WHERE id = ? AND jd_id = ?");
$stmt_c->bind_param("is", $candidate_id, $jd_id);
$stmt_c->execute();
if ($stmt_c->get_result()->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Candidate not found in this task."]);
    exit;
}
$stmt_c->close();

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare(
    "UPDATE candidates SET feedback_comment = ?, feedback_recommended = ?, feedback_updated_at = ? WHERE id = ? AND jd_id = ?"
);
$stmt->bind_param("sisis", $comment, $recommended, $now, $candidate_id, $jd_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Feedback saved."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to save feedback: " . $conn->error]);
}
$stmt->close();
$conn->close();