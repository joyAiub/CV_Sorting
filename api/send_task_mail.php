<?php
session_start();
// Prevent timeout for large zip operations
set_time_limit(0);

include("../config/db.php");
require_once("../config/mail_sender.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['jd_id']) || !isset($data['to'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

$jd_id = $data['jd_id'];
$to_emp_id = $data['to'];
$cc_emp_ids = isset($data['cc']) && is_array($data['cc']) ? $data['cc'] : [];

// Get Job Details
$stmt = $conn->prepare("SELECT task_no, job_title, department FROM job_list WHERE jd_id = ?");
$stmt->bind_param("s", $jd_id);
$stmt->execute();
$stmt->bind_result($task_no, $job_title, $job_department);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Job not found.']);
    exit;
}
$stmt->close();

if (empty($task_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Task number is not assigned for this job.']);
    exit;
}

$task_folder_name = preg_match('/^TASK/i', $task_no) ? $task_no : 'TASK' . $task_no;
$task_folder_name = strtoupper($task_folder_name);

$base_dir = "D:\\CV Data Backup (Don't Delete)\\CV Sorting Project\\Completed\\" . $task_folder_name;

if (!is_dir($base_dir)) {
    echo json_encode(['status' => 'error', 'message' => "Task data folder not found on server at " . htmlspecialchars($base_dir)]);
    exit;
}

// Fetch To Email
$to_email = '';
$to_name = '';
$stmt = $conn->prepare("SELECT email, full_name FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $to_emp_id);
$stmt->execute();
$stmt->bind_result($to_email, $to_name);
if (!$stmt->fetch() || empty($to_email)) {
    echo json_encode(['status' => 'error', 'message' => 'Main concern person does not have a valid email.']);
    exit;
}
$stmt->close();

// Fetch CC Emails
$cc_emails = [];
if (!empty($cc_emp_ids)) {
    $placeholders = str_repeat('?,', count($cc_emp_ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT email FROM employees WHERE employee_id IN ($placeholders) AND email != ''");
    $types = str_repeat('s', count($cc_emp_ids));
    $stmt->bind_param($types, ...$cc_emp_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cc_emails[] = $row['email'];
    }
    $stmt->close();
}

// Gather Candidates
$candidates = [];
$stmt = $conn->prepare("SELECT n8n_id, name FROM candidates WHERE jd_id = ?");
$stmt->bind_param("s", $jd_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[$row['n8n_id']] = $row['name'];
}
$stmt->close();

// Function to clean filename
function clean_filename($string) {
    $string = preg_replace('/[^A-Za-z0-9.\-_ ]/', '', $string);
    return trim($string) ?: "Unknown";
}

$safe_job_title = clean_filename($job_title);
$attachment_name = $task_folder_name . '_' . $safe_job_title . '.zip';

// ZIP Creation
$zip = new ZipArchive();
// Use the new uploads/exports directory instead of system temp to persist large files
$export_dir = "../uploads/exports";
if (!is_dir($export_dir)) {
    mkdir($export_dir, 0777, true);
}
$zip_filename = $export_dir . "/" . $attachment_name;

// If a previous zip exists, delete it so we start fresh
if (file_exists($zip_filename)) {
    unlink($zip_filename);
}

if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create ZIP attachment.']);
    exit;
}

$jd_dir = $base_dir . "\\JD";
$jd_file = $jd_dir . "\\" . $jd_id . ".pdf";
if (file_exists($jd_file)) {
    $zip->addFile($jd_file, "JD_" . $safe_job_title . ".pdf");
}

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
                $zip_name = "CV/" . $candidate_name . "_" . $n8n_id . "." . $file_ext;
            } else {
                $zip_name = "CV/Unknown_" . $n8n_id . "." . $file_ext;
            }
            $zip->addFile($file_path, $zip_name);
        }
    }
}
$zip->close();

if (!file_exists($zip_filename)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate ZIP file.']);
    exit;
}

// Check size. SMTP usually limits at 15-25MB.
$zip_size = filesize($zip_filename);
$size_limit = 15 * 1024 * 1024; // 15 MB

// Fetch Mail Config
$mail_res = $conn->query("SELECT * FROM mail_config LIMIT 1");
$mail_config = $mail_res->fetch_assoc();
if (!$mail_config) {
    echo json_encode(["status" => "error", "message" => "Mail server configuration not found."]);
    exit;
}

$mailer = new SmtpMailer($mail_config);
$subject = "Candidate CVs and JD - " . $job_title . " ($task_folder_name)";

$sender_name = $_SESSION['full_name'] ?? 'HR Team';

$dept_text = !empty($job_department) ? " (" . htmlspecialchars($job_department) . ")" : "";
$dept_li = !empty($job_department) ? "<li><strong>Department:</strong> " . htmlspecialchars($job_department) . "</li>" : "";

$html_body = "
<div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; line-height: 1.6;'>
    <h2 style='color: #4f46e5;'>Candidate Document Delivery</h2>
    <p>Dear $to_name,</p>
    <p>Please find the requested Job Description and candidate CVs for the position of <strong>$job_title</strong>$dept_text.</p>
    <div style='background-color: #f1f5f9; padding: 15px; border-radius: 8px; margin: 20px 0;'>
        <h4 style='margin-top: 0; color: #1e293b;'>Task Details:</h4>
        <ul style='margin-bottom: 0; padding-left: 20px;'>
            <li><strong>Task No:</strong> $task_folder_name</li>
            <li><strong>Job Title:</strong> $job_title</li>
            $dept_li
        </ul>
    </div>
";

$attachments = [];

if ($zip_size > $size_limit) {
    // Too large for attachment. Provide a secure download link.
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $download_url = $protocol . "://" . $host . "/download.php?file=" . rawurlencode($attachment_name);
    
    $html_body .= "
    <div style='border: 2px dashed #e2e8f0; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px;'>
        <p style='color: #64748b; font-size: 14px; margin-top: 0;'>The document package is very large (" . number_format($zip_size / 1048576, 1) . " MB) due to the high volume of CVs. Please click the button below to download the ZIP file securely from our server.</p>
        <a href='$download_url' style='display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 24px; font-weight: bold; border-radius: 6px; margin-top: 10px;'>Download Documents</a>
    </div>";
} else {
    // Attach directly
    $html_body .= "
    <p>The attached ZIP file contains:</p>
    <ul style='padding-left: 20px;'>
        <li>The Job Description (JD) document.</li>
        <li>A folder containing all relevant candidate CVs.</li>
    </ul>";
    
    $attachments[] = [
        'name' => $attachment_name,
        'path' => $zip_filename
    ];
}

$html_body .= "
    <p>Best regards,<br><strong>$sender_name</strong><br>CV Sorting System</p>
</div>
";

// Use our updated SmtpMailer method with cc and attachments
$send_success = $mailer->send($to_email, $subject, $html_body, $cc_emails, $attachments);

// If it was attached directly, we can delete the file to save space.
// If it was a link, we MUST keep the file so the user can download it.
if ($zip_size <= $size_limit && file_exists($zip_filename)) {
    unlink($zip_filename);
}

if ($send_success) {
    echo json_encode(['status' => 'success', 'message' => 'Mail sent successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send mail. Check mail server configuration.']);
}
