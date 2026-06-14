<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

if (!isset($_GET['jd_id'])) {
    die("JD ID is required.");
}

$jd_id = $_GET['jd_id'];

// Get Job Details
$stmt = $conn->prepare("SELECT task_no, job_title FROM job_list WHERE jd_id = ?");
$stmt->bind_param("s", $jd_id);
$stmt->execute();
$stmt->bind_result($task_no, $job_title);
if (!$stmt->fetch()) {
    die("Job not found.");
}
$stmt->close();

if (empty($task_no)) {
    die("Task number is not assigned for this job.");
}

$task_folder_name = preg_match('/^TASK/i', $task_no) ? $task_no : 'TASK' . $task_no;
$task_folder_name = strtoupper($task_folder_name);

$base_dir = "D:\\CV Data Backup (Don't Delete)\\CV Sorting Project\\Completed\\" . $task_folder_name;

if (!is_dir($base_dir)) {
    die("Task data folder not found on server at " . htmlspecialchars($base_dir));
}

// Get candidates mapping (n8n_id => name)
$candidates = [];
$stmt = $conn->prepare("SELECT n8n_id, name FROM candidates WHERE jd_id = ?");
$stmt->bind_param("s", $jd_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[$row['n8n_id']] = $row['name'];
}
$stmt->close();

// Create ZIP file in a temporary directory
$zip = new ZipArchive();
$temp_dir = sys_get_temp_dir();
$zip_filename = $temp_dir . "/" . $task_folder_name . "_" . time() . ".zip";

if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Failed to create ZIP file.");
}

// Function to clean filename
function clean_filename($string) {
    $string = preg_replace('/[^A-Za-z0-9.\-_ ]/', '', $string);
    return trim($string) ?: "Unknown";
}

// Add JD
$jd_dir = $base_dir . "\\JD";
$jd_file = $jd_dir . "\\" . $jd_id . ".pdf";

if (file_exists($jd_file)) {
    $safe_job_title = clean_filename($job_title);
    $zip->addFile($jd_file, "JD_" . $safe_job_title . ".pdf");
}

// Add CVs
$cv_dir = $base_dir . "\\CV";
if (is_dir($cv_dir)) {
    $files = scandir($cv_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $file_ext = pathinfo($file, PATHINFO_EXTENSION);
            $n8n_id = pathinfo($file, PATHINFO_FILENAME);
            
            $file_path = $cv_dir . "\\" . $file;
            
            if (!is_file($file_path)) continue;
            
            if (isset($candidates[$n8n_id])) {
                $candidate_name = clean_filename($candidates[$n8n_id]);
                // Put inside CV folder, format: Name_n8nId.pdf to avoid collisions
                $zip_name = "CV/" . $candidate_name . "_" . $n8n_id . "." . $file_ext;
            } else {
                // Not in DB, keep original name
                $zip_name = "CV/Unknown_" . $n8n_id . "." . $file_ext;
            }
            
            $zip->addFile($file_path, $zip_name);
        }
    }
}

$zip->close();

if (!file_exists($zip_filename)) {
    die("Failed to generate ZIP file.");
}

// Output to browser
$download_name = $task_folder_name . '_' . clean_filename($job_title) . '.zip';

header('Content-Type: application/zip');
header('Content-disposition: attachment; filename="' . $download_name . '"');
header('Content-Length: ' . filesize($zip_filename));

// Disable caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

readfile($zip_filename);

// Delete temp file after serving
unlink($zip_filename);
exit;
