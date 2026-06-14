<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");

include("../config/db.php");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*
// 1. API Key Authentication
$provided_key = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? null);
$is_authenticated = false;

if ($provided_key) {
    $stmt = $conn->prepare("SELECT `value` FROM rpa_config WHERE `key` = 'API_KEY'");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if ($provided_key === $row['value']) {
            $is_authenticated = true;
        }
    }
}

if (!$is_authenticated) {
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please provide a valid X-API-KEY header."]);
    exit;
}
*/

// 2. Fetch the webhook URL from rpa_config
$stmt = $conn->prepare("SELECT `value` FROM rpa_config WHERE `key` = 'N8N_WEBHOOK_URL'");
$stmt->execute();
$stmt->bind_result($webhook_url);
if (!$stmt->fetch()) {
    // Fallback to config table if not in rpa_config
    $stmt->close();
    $stmt = $conn->prepare("SELECT value FROM config WHERE name = 'n8n_webhook_url'");
    $stmt->execute();
    $stmt->bind_result($webhook_url);
    if (!$stmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "Webhook URL 'N8N_WEBHOOK_URL' not found in config tables."]);
        exit;
    }
}
$stmt->close();

// Remove PHP script timeout limits
set_time_limit(0);
ini_set('max_execution_time', 0);

// Fetch any optional inputs (like job_title, jd_id, task_no) from query params OR JSON body
$data = json_decode(file_get_contents("php://input"), true) ?? [];
$job_title = $_GET['job_title'] ?? $data['job_title'] ?? '';
$jd_id = $_GET['jd_id'] ?? $data['jd_id'] ?? '';
$task_no = $_GET['TASKNO'] ?? $data['TASKNO'] ?? $data['task_no'] ?? '';

// Dynamically append jd_id, job_title, and TASKNO as query parameters to the webhook URL
$params = [];
if (!empty($jd_id)) $params[] = "JD_ID=" . urlencode($jd_id);
if (!empty($job_title)) $params[] = "job_title=" . urlencode($job_title);
if (!empty($task_no)) $params[] = "TASKNO=" . urlencode($task_no);

if (!empty($params)) {
    $separator = (strpos($webhook_url, '?') === false) ? '?' : '&';
    $webhook_url .= $separator . implode('&', $params);
}

// Just call the webhook URL as requested (always POST because n8n requires it)
$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true); // Force POST

// Send the exact payload the n8n workflow expects
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "action" => "start_screening", 
    "job_title" => $job_title,
    "jd_id" => $jd_id,
    "task_no" => $task_no
])); 

curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Disable cURL timeouts to wait infinitely for n8n to finish processing
curl_setopt($ch, CURLOPT_TIMEOUT, 0); 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Decode n8n response to extract file count
$decoded_response = json_decode($response, true);
$file_count = 0;

if (is_array($decoded_response)) {
    // If it's an array, look into the first element for file_count
    if (!empty($decoded_response) && isset($decoded_response[0]['file_count'])) {
        $file_count = $decoded_response[0]['file_count'];
    } elseif (isset($decoded_response['file_count'])) {
        // If n8n returned a single object with file_count
        $file_count = $decoded_response['file_count'];
    }
}

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode([
        "status" => "success", 
        "message" => "Webhook triggered successfully", 
        "n8n_status_code" => $http_code,
        "webhook_url" => $webhook_url,
        "jd_id" => $jd_id,
        "job_title" => $job_title,
        "task_no" => $task_no,
        "Filecount" => (int)$file_count,
        "n8n_response" => $decoded_response ?: $response
    ], JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to trigger webhook", 
        "n8n_status_code" => $http_code,
        "webhook_url" => $webhook_url,
        "jd_id" => $jd_id,
        "job_title" => $job_title,
        "task_no" => $task_no,
        "curl_error" => $error, 
        "n8n_response" => $decoded_response ?: $response
    ], JSON_UNESCAPED_SLASHES);
}

$conn->close();
?>
