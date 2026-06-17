<?php
header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$local_ip = trim($data['local_ip'] ?? '');

if (empty($local_ip)) {
    echo json_encode(["status" => "error", "message" => "No IP provided"]);
    exit;
}

// Allow comma-separated list of IPs (multiple adapters), max 100 chars
$local_ip = substr($local_ip, 0, 100);

$stmt = $conn->prepare("UPDATE users SET local_ip = ? WHERE id = ?");
$stmt->bind_param("si", $local_ip, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

echo json_encode(["status" => "success"]);
?>
