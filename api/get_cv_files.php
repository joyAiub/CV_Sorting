<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Configuration
$cv_dir = "C:\\Users\\joy.ballav\\.n8n-files\\CVs";
$file_info = isset($_GET['fileinfo']) ? trim($_GET['fileinfo']) : '';

// Ensure directory exists
if (!is_dir($cv_dir)) {
    header("Content-Type: application/json");
    echo json_encode(["status" => "error", "message" => "CV directory not found."]);
    exit;
}

if (empty($file_info)) {
    // List all files
    header("Content-Type: application/json");
    $files = [];
    $dir_handle = opendir($cv_dir);
    if ($dir_handle) {
        while (($file = readdir($dir_handle)) !== false) {
            if ($file != "." && $file != ".." && is_file($cv_dir . DIRECTORY_SEPARATOR . $file)) {
                $files[] = [
                    "name" => $file,
                    "size" => filesize($cv_dir . DIRECTORY_SEPARATOR . $file),
                    "modified" => date("Y-m-d H:i:s", filemtime($cv_dir . DIRECTORY_SEPARATOR . $file)),
                    "download_url" => "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?fileinfo=" . urlencode($file)
                ];
            }
        }
        closedir($dir_handle);
    }
    echo json_encode(["status" => "success", "data" => $files]);
    exit;
}
else {
    // Serve specific file
    $file_path = $cv_dir . DIRECTORY_SEPARATOR . $file_info;

    // Security check: ensure the file is actually in the CV directory
    $real_path = realpath($file_path);
    $real_cv_dir = realpath($cv_dir);

    if ($real_path && strpos($real_path, $real_cv_dir) === 0 && is_file($real_path)) {
        $mime_type = mime_content_type($real_path) ?: 'application/octet-stream';

        header("Content-Type: " . $mime_type);
        header("Content-Length: " . filesize($real_path));
        header("Content-Disposition: inline; filename=\"" . basename($real_path) . "\"");

        readfile($real_path);
        exit;
    }
    else {
        header("Content-Type: application/json");
        echo json_encode(["status" => "error", "message" => "File not found or access denied."]);
        exit;
    }
}
?>
