<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
include("../includes/path_helper.php");

$source_dir = getRpaConfigValue($conn, 'N8NCVPATH', "C:\\Users\\admin\\.n8n-files\\CVs");
$dest_dir = ""; // Will be resolved dynamically

$n8n_id = $_GET['n8n_id'] ?? '';
$jd_id = $_GET['jd_id'] ?? '';

// If n8n_id and jd_id are provided, resolve dynamic path using helper
if (!empty($n8n_id) && !empty($jd_id)) {
    $stmt = $conn->prepare("SELECT task_no FROM candidates WHERE n8n_id = ? AND jd_id = ?");
    $stmt->bind_param("ss", $n8n_id, $jd_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $candidate = $res->fetch_assoc();
    $task_no = $candidate ? $candidate['task_no'] : '';

    if (empty($task_no)) {
        $stmt = $conn->prepare("SELECT task_no FROM job_list WHERE jd_id = ?");
        $stmt->bind_param("s", $jd_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $job = $res->fetch_assoc();
        $task_no = $job ? $job['task_no'] : '';
    }

    if (!empty($task_no)) {
        // Use centralized helper for directory path
        $dest_dir = get_processed_path($conn, $task_no, "CV");
    }
}

// Fallback to base processed path if no task was resolved
if (empty($dest_dir)) {
    $base_processed = getRpaConfigValue($conn, 'CV_PROCESSED_BASE_PATH', "C:\\Users\\admin\\.n8n-files\\Processed");
    $dest_dir = $base_processed . DIRECTORY_SEPARATOR . "CV";
}

// Ensure directories exist
if (!is_dir($source_dir)) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Source directory not found: $source_dir"]);
    exit;
}
if (!is_dir($dest_dir)) {
    if (!mkdir($dest_dir, 0777, true)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Destination directory could not be created: $dest_dir"]);
        exit;
    }
}

$filename = isset($_GET['filename']) ? trim($_GET['filename']) : '';
$newfilename = isset($_GET['newfilename']) ? trim($_GET['newfilename']) : '';

if (!empty($filename)) {
    // Move specific file
    $source_path = $source_dir . DIRECTORY_SEPARATOR . $filename;
    $target_name = !empty($newfilename) ? $newfilename : $filename;
    $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $target_name;

    if (file_exists($source_path)) {
        if (rename($source_path, $dest_path)) {
            ob_clean();
            echo json_encode(["status" => "success", "message" => "File '$filename' moved successfully."]);
        }
        else {
            ob_clean();
            echo json_encode(["status" => "error", "message" => "Failed to move file '$filename'."]);
        }
    }
    else {
        // Check if it already exists in destination (already moved by another process)
        if (file_exists($dest_path)) {
            ob_clean();
            echo json_encode(["status" => "success", "message" => "File '$target_name' is already in destination."]);
        } else {
            ob_clean();
            echo json_encode(["status" => "error", "message" => "File '$filename' does not exist in source or destination."]);
        }
    }
}
else {
    // Move all files if no filename provided
    $files_moved = 0;
    $errors = [];
    $dir_handle = opendir($source_dir);

    if ($dir_handle) {
        while (($file = readdir($dir_handle)) !== false) {
            if ($file != "." && $file != ".." && is_file($source_dir . DIRECTORY_SEPARATOR . $file)) {
                if (rename($source_dir . DIRECTORY_SEPARATOR . $file, $dest_dir . DIRECTORY_SEPARATOR . $file)) {
                    $files_moved++;
                }
                else {
                    $errors[] = "Failed to move '$file'.";
                }
            }
        }
        closedir($dir_handle);
    }

    ob_clean();
    echo json_encode([
        "status" => count($errors) === 0 ? "success" : "partial_success",
        "message" => "$files_moved files moved successfully.",
        "errors" => $errors
    ]);
}
?>
