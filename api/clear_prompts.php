<?php
header("Content-Type: application/json");
include("../config/db.php");

// This API clears all entries from the prompts table
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $jd_id = $_GET['jd_id'] ?? null;
    
    if ($jd_id === null || $jd_id == "0") {
        // Clear all prompts but keep the rows? 
        if ($conn->query("UPDATE prompts SET prompt_text = ''")) {
            echo json_encode(["status" => "success", "message" => "All prompts cleared (text reset) successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to reset all prompts: " . $conn->error]);
        }
    } else {
        // First check if JD ID exists in job_list
        $check_stmt = $conn->prepare("SELECT jd_id FROM job_list WHERE jd_id = ?");
        $check_stmt->bind_param("s", $jd_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows === 0) {
            echo json_encode(["status" => "error", "jd_id" => $jd_id, "message" => "jd id not available or invalid"]);
            exit;
        }
        $check_stmt->close();

        $stmt = $conn->prepare("UPDATE prompts SET prompt_text = '' WHERE jd_id = ?");
        $stmt->bind_param("s", $jd_id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "jd id $jd_id prompt cleared successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to clear prompt text for JD $jd_id: " . $stmt->error]);
        }
        $stmt->close();
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}

$conn->close();
?>
