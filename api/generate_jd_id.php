<?php
header("Content-Type: application/json");
include("../config/db.php");

function generateRandomID()
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $result = 'JD';
    for ($i = 0; $i < 6; $i++) {
        $result .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $result;
}

$unique_id = "";
$exists = true;
$attempts = 0;
$max_attempts = 10;

while ($exists && $attempts < $max_attempts) {
    $unique_id = generateRandomID();
    $stmt = $conn->prepare("SELECT id FROM job_list WHERE jd_id = ?");
    $stmt->bind_param("s", $unique_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $exists = false;
    }
    $attempts++;
    $stmt->close();
}

if (!$exists) {
    echo json_encode(["status" => "success", "jd_id" => $unique_id]);
}
else {
    // Highly unlikely fallback for high collisions
    $unique_id = "JD" . time();
    echo json_encode(["status" => "success", "jd_id" => $unique_id]);
}

$conn->close();
?>
