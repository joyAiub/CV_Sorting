<?php
include("../includes/path_helper.php");

$n8n_id = $_GET['n8n_id'] ?? '';
$jd_id = $_GET['jd_id'] ?? '';
$token = $_GET['token'] ?? '';
$sub_folder = "CV"; // Subfolder inside the Task folder

if (empty($n8n_id) || empty($jd_id)) {
    die("Missing parameters.");
}

include("../config/auth.php");
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    if (!empty($jd_id)) {
        $stmt_v = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
        $stmt_v->bind_param("s", $jd_id);
        $stmt_v->execute();
        if ($stmt_v->get_result()->num_rows === 0) {
            die("Unauthorized access: Invalid JD ID.");
        }
        $stmt_v->close();
    } else {
        die("Unauthorized access: Login required.");
    }
}

// Get task_no directly from candidates table
$stmt = $conn->prepare("SELECT task_no FROM candidates WHERE n8n_id = ? AND jd_id = ?");
$stmt->bind_param("ss", $n8n_id, $jd_id);
$stmt->execute();
$res = $stmt->get_result();
$candidate = $res->fetch_assoc();
$task_no = $candidate ? $candidate['task_no'] : '';

if (empty($task_no)) {
    $stmt = $conn->prepare("SELECT task_no FROM Job_List WHERE jd_id = ?");
    $stmt->bind_param("s", $jd_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $job = $res->fetch_assoc();
    $task_no = $job ? $job['task_no'] : '';
}

if (empty($task_no)) {
    die("Task information not found.");
}

// Use centralized helper to find the file
$file_path = get_processed_path($conn, $task_no, $sub_folder, $n8n_id . ".pdf");

if ($file_path && file_exists($file_path)) {
    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"" . $n8n_id . ".pdf\"");
    readfile($file_path);
} else {
    http_response_code(404);
    echo "File not found for task: " . $task_no;
}
?>
