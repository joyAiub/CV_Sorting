<?php
header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";
include __DIR__ . "/../config/auth.php";
check_auth();

if (!has_permission('trigger_screening')) {
    echo json_encode(["status" => "error", "message" => "Permission denied to trigger AI screening."]);
    exit;
}

// Fetch the webhook URL from config
$stmt = $conn->prepare("SELECT value FROM config WHERE name = 'n8n_webhook_url'");
$stmt->execute();
$stmt->bind_result($webhook_url);
if (!$stmt->fetch()) {
    echo json_encode(["status" => "error", "message" => "Webhook URL not configured in 'config' table."]);
    exit;
}
$stmt->close();

// Decode JSON input for any extra params (like job_title)
$data = json_decode(file_get_contents("php://input"), true);
$job_title = $data['job_title'] ?? '';

// Trigger the webhook
$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["action" => "start_screening", "job_title" => $job_title]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode(["status" => "success", "message" => "Screening started successfully!", "response" => $response]);
}
else {
    echo json_encode(["status" => "error", "message" => "Failed to trigger webhook (HTTP $http_code)", "debug" => $response]);
}

$conn->close();
?>
