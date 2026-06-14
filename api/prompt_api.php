<?php
header("Content-Type: application/json");
include("../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $prompt_text = $data['prompt_text'] ?? null;
    $jd_id = $data['jd_id'] ?? null;
    $id = $data['id'] ?? null;
    $user_id = $_SESSION['username'] ?? 'System';
    $user_name = $_SESSION['full_name'] ?? 'System';

    if (!$jd_id) {
        echo json_encode(["status" => "error", "message" => "JD ID is required"]);
        exit;
    }

    // Check if JD ID exists in job_list
    $check_stmt = $conn->prepare("SELECT jd_id FROM job_list WHERE jd_id = ?");
    $check_stmt->bind_param("s", $jd_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(["status" => "error", "jd_id" => $jd_id, "message" => "invalid jd id provided"]);
        exit;
    }
    $check_stmt->close();

    if ($id) {
        // Update existing by specific primary ID
        $stmt = $conn->prepare("UPDATE prompts SET prompt_text = ?, jd_id = ?, user_id = ?, user_name = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $prompt_text, $jd_id, $user_id, $user_name, $id);
    } else {
        // Check if JD ID already has a prompt
        $check_p = $conn->prepare("SELECT id FROM prompts WHERE jd_id = ? LIMIT 1");
        $check_p->bind_param("s", $jd_id);
        $check_p->execute();
        $p_res = $check_p->get_result();

        if ($p_res->num_rows > 0) {
            $existing_id = $p_res->fetch_assoc()['id'];
            // Update existing by JD ID
            $stmt = $conn->prepare("UPDATE prompts SET prompt_text = ?, user_id = ?, user_name = ? WHERE id = ?");
            $stmt->bind_param("sssi", $prompt_text, $user_id, $user_name, $existing_id);
            $id = $existing_id;
        } else {
            // Create new
            $stmt = $conn->prepare("INSERT INTO prompts (prompt_text, jd_id, user_id, user_name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $prompt_text, $jd_id, $user_id, $user_name);
        }
        $check_p->close();
    }

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "jd id $jd_id updated successfully", "id" => $id ?? $stmt->insert_id]);
    }
    else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
    $stmt->close();
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jd_id = $_GET['jd_id'] ?? null;

    if ($jd_id === null) {
        echo json_encode(["status" => "error", "message" => "jd id param mandatory (0 for all or specific ID)"]);
        exit;
    }

    if ($jd_id == "0") {
        $query = "SELECT * FROM prompts ORDER BY created_at DESC";
        $result = $conn->query($query);
        $prompts = [];
        while ($result && $row = $result->fetch_assoc()) {
            $row['prompt_text'] = $row['prompt_text'] ?? "";
            $prompts[] = $row;
        }
        $response = ["status" => "success", "data" => $prompts];
        if (empty($prompts)) {
            $response["message"] = "no prompt jdid found";
        }
        echo json_encode($response);
    } else {
        // First check if JD ID exists in job_list
        $check_stmt = $conn->prepare("SELECT jd_id FROM job_list WHERE jd_id = ?");
        $check_stmt->bind_param("s", $jd_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res->num_rows === 0) {
            echo json_encode(["status" => "error", "jd_id" => $jd_id, "message" => "invalid jd id provided"]);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM prompts WHERE jd_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $jd_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $prompts = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $row['prompt_text'] = $row['prompt_text'] ?? "";
                $prompts[] = $row;
            }
        } else {
            // Return jd_id with empty prompt as requested
            $prompts[] = [
                "id" => null,
                "prompt_text" => "",
                "created_at" => null,
                "jd_id" => $jd_id,
                "user_id" => null,
                "user_name" => null
            ];
        }
        echo json_encode(["status" => "success", "data" => $prompts]);
    }
}

$conn->close();
?>
