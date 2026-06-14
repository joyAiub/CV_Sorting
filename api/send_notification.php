<?php
// Standalone script for sending background notifications
// Usage: php send_notification.php <type> <jd_id> <user_id> [job_id]

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die("CLI only.");
}

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../config/mail_sender.php';

function mail_log($msg) {
    file_put_contents(__DIR__ . '/mail_log.txt', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

$type = $argv[1] ?? $_GET['type'] ?? '';
$jd_id = $argv[2] ?? $_GET['jd_id'] ?? '';
$user_id = $argv[3] ?? $_GET['user_id'] ?? '';
$job_id = $argv[4] ?? $_GET['job_id'] ?? '';

mail_log("Starting notification: Type=$type, JD=$jd_id, JobID=$job_id");

if (!$type || !$jd_id) {
    mail_log("Error: Missing type or JD ID");
    exit;
}

// Fetch Mail Config
$mail_res = $conn->query("SELECT * FROM mail_config LIMIT 1");
$mail_config = $mail_res->fetch_assoc();
if (!$mail_config) exit;

$mailer = new SmtpMailer($mail_config);

// Fetch Job Details
if ($job_id) {
    $job_res = $conn->query("SELECT * FROM job_list WHERE id = $job_id");
} else {
    $job_res = $conn->query("SELECT * FROM job_list WHERE jd_id = '$jd_id'");
}
$job_data = $job_res->fetch_assoc();

// Handle Delay for Creation Emails
if ($type === 'creation') {
    $limit_res = $conn->query("SELECT `value` FROM rpa_config_app_system WHERE `key` = 'task_edit_limit_minutes'");
    $limit_mins = ($limit_res && $l_row = $limit_res->fetch_assoc()) ? (int)$l_row['value'] : 5;
    
    mail_log("Creation delay: Sleeping for $limit_mins minutes...");
    sleep($limit_mins * 60);
    
    if ($job_id) {
        $job_res = $conn->query("SELECT * FROM job_list WHERE id = $job_id");
    } else {
        $job_res = $conn->query("SELECT * FROM job_list WHERE jd_id = '$jd_id'");
    }
    $job_data = $job_res->fetch_assoc();
}

if (!$job_data) {
    mail_log("Error: Job data not found for ID $job_id or JD $jd_id");
    exit;
}

// Fetch User/Creator Email from the Job Record
$creator_username = $job_data['created_by'];
$user_res = $conn->query("SELECT e.email, e.full_name FROM users u JOIN employees e ON u.employee_id = e.employee_id WHERE u.username = '$creator_username'");
$user_data = $user_res->fetch_assoc();

if (!$user_data) {
    mail_log("Error: Creator data not found for username $creator_username");
} else {
    mail_log("Found Creator: " . $user_data['full_name'] . " (" . $user_data['email'] . ")");
}

if ($type === 'creation') {
    // 1. Notify Creator
    if ($user_data && !empty($user_data['email'])) {
        $prompt_res = $conn->query("SELECT prompt_text FROM prompts WHERE jd_id = '$jd_id' LIMIT 1");
        $prompt_text = ($prompt_res && $p_row = $prompt_res->fetch_assoc()) ? $p_row['prompt_text'] : '';

        $subject = "Task Creation Confirmation: " . $job_data['job_title'] . " (" . $job_data['jd_id'] . ")";
        $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #4f46e5;'>Task Created Successfully</h2>
                <p>Hello <strong>" . htmlspecialchars($user_data['full_name']) . "</strong>,</p>
                <p>Your new screening task has been created in the system.</p>
                <div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 5px 0; color: #64748b; width: 120px;'>Job Title:</td><td style='padding: 5px 0; font-weight: 700;'>" . htmlspecialchars($job_data['job_title']) . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b;'>JD ID:</td><td style='padding: 5px 0; font-weight: 700;'>" . htmlspecialchars($job_data['jd_id']) . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b;'>Task No:</td><td style='padding: 5px 0; font-weight: 700; color: #4f46e5;'>" . htmlspecialchars($job_data['task_no']) . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b;'>Department:</td><td style='padding: 5px 0;'>" . htmlspecialchars($job_data['department'] ?: '—') . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b;'>Concern:</td><td style='padding: 5px 0;'>" . htmlspecialchars($job_data['concern_person'] ?: '—') . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b; vertical-align: top;'>Mand. Req:</td><td style='padding: 5px 0; font-size: 0.85rem;'>" . nl2br(htmlspecialchars($prompt_text ?: 'No mandatory requirements set.')) . "</td></tr>
                    </table>
                </div>
                <p style='font-size: 0.8rem; color: #94a3b8;'>This is an automated notification from the CV Sorting System.</p>
            </div>
        ";
        $res = $mailer->send($user_data['email'], $subject, $message);
        mail_log("Creation Mail sent to Creator (" . $user_data['email'] . "). Result: " . ($res ? 'Success' : 'Failed'));
    }

    // 2. Notify Concern Person
    if ($job_data['send_mail'] == 1 && !empty($job_data['concern_email'])) {
        $prompt_res = $conn->query("SELECT prompt_text FROM prompts WHERE jd_id = '$jd_id' LIMIT 1");
        $prompt_text = ($prompt_res && $p_row = $prompt_res->fetch_assoc()) ? $p_row['prompt_text'] : '';

        $subject = "New Screening Task Assigned: " . $job_data['job_title'] . " (" . $job_data['jd_id'] . ")";
        $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #4f46e5;'>New Screening Task Assigned</h2>
                <p>Hello <strong>" . htmlspecialchars($job_data['concern_person']) . "</strong>,</p>
                <p>A new CV screening task has been initiated and you have been marked as the concern person.</p>
                <div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 5px 0; color: #64748b; width: 120px;'>Job Title:</td><td style='padding: 5px 0; font-weight: 700;'>" . htmlspecialchars($job_data['job_title']) . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b;'>JD ID:</td><td style='padding: 5px 0; font-weight: 700;'>" . htmlspecialchars($job_data['jd_id']) . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b;'>Task No:</td><td style='padding: 5px 0; font-weight: 700; color: #4f46e5;'>" . htmlspecialchars($job_data['task_no']) . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b;'>Created By:</td><td style='padding: 5px 0;'>" . htmlspecialchars($user_data['full_name'] ?: 'System') . "</td></tr>
                        <tr><td style='padding: 5px 0; color: #64748b; vertical-align: top;'>Mand. Req:</td><td style='padding: 5px 0; font-size: 0.85rem;'>" . nl2br(htmlspecialchars($prompt_text ?: 'No mandatory requirements set.')) . "</td></tr>
                    </table>
                </div>
                <p>You will receive another notification once the screening process is completed.</p>
                <p style='font-size: 0.8rem; color: #94a3b8;'>This is an automated notification from the CV Sorting System.</p>
            </div>
        ";
        $res = $mailer->send($job_data['concern_email'], $subject, $message);
        mail_log("Creation Mail sent to Concern (" . $job_data['concern_email'] . "). Result: " . ($res ? 'Success' : 'Failed'));
    }
} 
elseif ($type === 'completion') {
    $base_url = "http://10.201.26.53:8080"; 
    $view_url = $base_url . "/view/dashboard.php?jd_id=" . urlencode($job_data['jd_id']) . "&job_title=" . urlencode($job_data['job_title']) . "&token=" . $job_data['view_token'];

    // 1. Notify Concern Person
    if ($job_data['send_mail'] == 1 && !empty($job_data['concern_email'])) {
        $subject = "Task Completed: " . $job_data['job_title'] . " (" . $job_data['jd_id'] . ")";
        $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #4f46e5;'>Task Screening Completed</h2>
                <p>Hello <strong>" . htmlspecialchars($job_data['concern_person']) . "</strong>,</p>
                <p>The CV screening task for <strong>" . htmlspecialchars($job_data['job_title']) . "</strong> (JD ID: " . htmlspecialchars($job_data['jd_id']) . ") has been completed.</p>
                <p>You can view the screening results and dashboard using the link below:</p>
                <p style='margin: 30px 0;'>
                    <a href='" . $view_url . "' style='background: #4f46e5; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>View Dashboard Results</a>
                </p>
                <p>Or copy this link: <br><a href='" . $view_url . "'>" . $view_url . "</a></p>
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 0.8rem; color: #94a3b8;'>This is an automated notification from the CV Sorting System.</p>
            </div>
        ";
        $res = $mailer->send($job_data['concern_email'], $subject, $message);
        mail_log("Completion Mail sent to Concern (" . $job_data['concern_email'] . "). Result: " . ($res ? 'Success' : 'Failed'));
    }

    // 2. Notify Creator
    if ($user_data && !empty($user_data['email'])) {
        $subject = "Task Completed: " . $job_data['job_title'] . " (" . $job_data['jd_id'] . ")";
        $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #10b981;'>Screening Task Completed</h2>
                <p>Hello <strong>" . htmlspecialchars($user_data['full_name']) . "</strong>,</p>
                <p>The task you created, <strong>" . htmlspecialchars($job_data['job_title']) . "</strong> (JD ID: " . htmlspecialchars($job_data['jd_id']) . "), has been successfully processed.</p>
                <p style='margin: 30px 0;'>
                    <a href='" . $view_url . "' style='background: #10b981; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>View Completed Dashboard</a>
                </p>
                <p>Or copy this link: <br><a href='" . $view_url . "'>" . $view_url . "</a></p>
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 0.8rem; color: #94a3b8;'>This is an automated notification from the CV Sorting System.</p>
            </div>
        ";
        $res = $mailer->send($user_data['email'], $subject, $message);
        mail_log("Completion Mail sent to Creator (" . $user_data['email'] . "). Result: " . ($res ? 'Success' : 'Failed'));
    }
}

$conn->close();
