<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/db.php';
include("../config/auth.php");
check_auth();

// Increase time limit for large file uploads/moves (e.g. 57 files)
set_time_limit(300); 

if (!has_permission('add_task')) {
    echo json_encode(["status" => "error", "message" => "Permission denied to create tasks."]);
    exit;
}

// --- TASK LIMIT CHECK ---
$username = $_SESSION['username'] ?? 'System';

// Fetch all active limits
$limit_res = $conn->query("SELECT * FROM task_limits WHERE is_active = 1");
$limits = [];
while ($l_row = $limit_res->fetch_assoc()) {
    $limits[$l_row['limit_type']][] = $l_row;
}

if (!empty($limits)) {
    // 1. Total All Time Limit
    if (isset($limits['total'])) {
        $total_limit = $limits['total'][0]['limit_value'];
        $count_res = $conn->query("SELECT COUNT(*) as cnt FROM job_list");
        $curr_cnt = $count_res->fetch_assoc()['cnt'];
        if ($curr_cnt >= $total_limit) {
            echo json_encode(["status" => "error", "message" => "System-wide all-time task limit reached ($total_limit)."]);
            exit;
        }
    }

    // 2. Monthly Limit
    if (isset($limits['monthly'])) {
        $monthly_limit = $limits['monthly'][0]['limit_value'];
        $count_res = $conn->query("SELECT COUNT(*) as cnt FROM job_list WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $curr_cnt = $count_res->fetch_assoc()['cnt'];
        if ($curr_cnt >= $monthly_limit) {
            echo json_encode(["status" => "error", "message" => "Monthly system task limit reached ($monthly_limit)."]);
            exit;
        }
    }

    // 3. Daily Limit
    if (isset($limits['daily'])) {
        $daily_limit = $limits['daily'][0]['limit_value'];
        $count_res = $conn->query("SELECT COUNT(*) as cnt FROM job_list WHERE DATE(created_at) = CURRENT_DATE()");
        $curr_cnt = $count_res->fetch_assoc()['cnt'];
        if ($curr_cnt >= $daily_limit) {
            echo json_encode(["status" => "error", "message" => "Daily system task limit reached ($daily_limit)."]);
            exit;
        }
    }

    // 4. Per User Limit (Daily)
    // Check if there's a specific user limit first
    $user_daily_limit = null;
    if (isset($limits['specific_user'])) {
        foreach ($limits['specific_user'] as $sl) {
            if ($sl['user_id'] === $username) {
                $user_daily_limit = $sl['limit_value'];
                break;
            }
        }
    }
    
    // If no specific limit, check generic per_user limit
    if ($user_daily_limit === null && isset($limits['per_user'])) {
        $user_daily_limit = $limits['per_user'][0]['limit_value'];
    }

    if ($user_daily_limit !== null) {
        $stmt_cnt = $conn->prepare("SELECT COUNT(*) as cnt FROM job_list WHERE created_by = ? AND DATE(created_at) = CURRENT_DATE()");
        $stmt_cnt->bind_param("s", $username);
        $stmt_cnt->execute();
        $curr_cnt = $stmt_cnt->get_result()->fetch_assoc()['cnt'];
        $stmt_cnt->close();

        if ($curr_cnt >= $user_daily_limit) {
            echo json_encode(["status" => "error", "message" => "Your daily task limit reached ($user_daily_limit)."]);
            exit;
        }
    }

    // 5. Per User Limit (Monthly)
    $user_monthly_limit = null;
    if (isset($limits['specific_user_monthly'])) {
        foreach ($limits['specific_user_monthly'] as $sl) {
            if ($sl['user_id'] === $username) {
                $user_monthly_limit = $sl['limit_value'];
                break;
            }
        }
    }
    
    if ($user_monthly_limit === null && isset($limits['per_user_monthly'])) {
        $user_monthly_limit = $limits['per_user_monthly'][0]['limit_value'];
    }

    if ($user_monthly_limit !== null) {
        $stmt_cnt = $conn->prepare("SELECT COUNT(*) as cnt FROM job_list WHERE created_by = ? AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)");
        $stmt_cnt->bind_param("s", $username);
        $stmt_cnt->execute();
        $curr_cnt = $stmt_cnt->get_result()->fetch_assoc()['cnt'];
        $stmt_cnt->close();

        if ($curr_cnt >= $user_monthly_limit) {
            echo json_encode(["status" => "error", "message" => "Your monthly task limit reached ($user_monthly_limit)."]);
            exit;
        }
    }
}
// --- END TASK LIMIT CHECK ---

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST;
}

// Detect if PHP dropped the POST payload because the upload exceeded post_max_size
if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0 && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    echo json_encode(["status" => "error", "message" => "Upload failed: The total size of the selected files exceeds the server's maximum allowed limit. Please increase 'post_max_size' in php.ini or upload fewer files at once."]);
    exit;
}

if (!isset($data['job_title']) || empty(trim($data['job_title']))) {
    echo json_encode(["status" => "error", "message" => "Job title is required."]);
    exit;
}

if (!isset($data['jd_id']) || empty(trim($data['jd_id']))) {
    echo json_encode(["status" => "error", "message" => "JD ID is required."]);
    exit;
}

$job_title = trim($data['job_title']);
$jd_id = trim($data['jd_id']);
$department = isset($data['department']) ? trim($data['department']) : '';
$concern_person = isset($data['concern_person']) ? trim($data['concern_person']) : '';
$concern_email = isset($data['concern_email']) ? trim($data['concern_email']) : NULL;
$send_mail = isset($data['send_mail']) ? (int)$data['send_mail'] : 0;
$source = isset($data['source']) ? trim($data['source']) : 'bdjobs';

// Check if jd_id already exists
$check_stmt = $conn->prepare("SELECT id FROM job_list WHERE jd_id = ?");
$check_stmt->bind_param("s", $jd_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "JD ID already exists."]);
    exit;
}

// --- OPTION B: NEVER REUSE TASK NUMBERS (PERSISTENT SEQUENCE) ---
$conn->begin_transaction();

// 1. Get and increment the last task number from rpa_config_app_system (Lock for update)
$seq_res = $conn->query("SELECT `value` FROM rpa_config_app_system WHERE `key` = 'last_task_number' FOR UPDATE");
$last_num = 0;
if ($seq_res && $seq_row = $seq_res->fetch_assoc()) {
    $last_num = (int)$seq_row['value'];
}
$new_task_num = $last_num + 1;

// 2. Update the sequence in rpa_config_app_system
$upd_seq = $conn->prepare("UPDATE rpa_config_app_system SET `value` = ? WHERE `key` = 'last_task_number'");
$upd_seq->bind_param("s", $new_task_num);
$upd_seq->execute();
$upd_seq->close();

$next_task_no = "TASK" . $new_task_num;

// Handle File Uploads for Manual/Both sources dynamically using path helper
include_once '../includes/path_helper.php';
if ($source === 'manual' || $source === 'both') {
    $upload_dir_jd = get_manual_upload_path($conn, $next_task_no, "JD");
    $upload_dir_cv = get_manual_upload_path($conn, $next_task_no, "CVs");
    
    // Process JD File if source is manual
    if ($source === 'manual' && isset($_FILES['jd_file'])) {
        if (!is_dir($upload_dir_jd)) {
            if (!mkdir($upload_dir_jd, 0777, true)) {
                echo json_encode(["status" => "error", "message" => "Failed to create JD directory: " . $upload_dir_jd]);
                exit;
            }
        }
        $jd_name = basename($_FILES['jd_file']['name']);
        if (!move_uploaded_file($_FILES['jd_file']['tmp_name'], $upload_dir_jd . DIRECTORY_SEPARATOR . $jd_name)) {
            echo json_encode(["status" => "error", "message" => "Failed to move JD file: " . $jd_name]);
            exit;
        }
    }

    // Process CV Files
    if (isset($_FILES['cv_files'])) {
        if (!is_dir($upload_dir_cv)) {
            if (!mkdir($upload_dir_cv, 0777, true)) {
                echo json_encode(["status" => "error", "message" => "Failed to create CV directory: " . $upload_dir_cv]);
                exit;
            }
        }
        $total_files = count($_FILES['cv_files']['name']);
        $move_errors = [];
        for ($i = 0; $i < $total_files; $i++) {
            $tmp_name = $_FILES['cv_files']['tmp_name'][$i];
            $cv_name = basename($_FILES['cv_files']['name'][$i]);
            if ($tmp_name != "") {
                $ext = strtolower(pathinfo($cv_name, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    if (!move_uploaded_file($tmp_name, $upload_dir_cv . DIRECTORY_SEPARATOR . $cv_name)) {
                        $move_errors[] = $cv_name;
                    }
                }
            }
        }
        if (!empty($move_errors)) {
            echo json_encode(["status" => "error", "message" => "Failed to move " . count($move_errors) . " CV files. Check folder permissions."]);
            exit;
        }
    }
}
// 3. Round-Robin Server Allocation
$server_count = 5; 
$last_server = 0;
$sc_res = $conn->query("SELECT server_count, last_server_allocated FROM server_config ORDER BY id DESC LIMIT 1");
if ($sc_res && $sc_row = $sc_res->fetch_assoc()) {
    $server_count = (int)$sc_row['server_count'];
    $last_server = (int)$sc_row['last_server_allocated'];
}
$next_server = ($last_server % $server_count) + 1;

// Update server pointer
$conn->query("UPDATE server_config SET last_server_allocated = $next_server ORDER BY id DESC LIMIT 1");

$created_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'System';

// 4. Insert Job with unique Task No and new metadata
// Also store the file count for manual/both sources
$uploaded_count = 0;
if (($source === 'manual' || $source === 'both') && isset($_FILES['cv_files'])) {
    $total_files = count($_FILES['cv_files']['name']);
    for ($i = 0; $i < $total_files; $i++) {
        $tmp_name = $_FILES['cv_files']['tmp_name'][$i];
        $cv_name = basename($_FILES['cv_files']['name'][$i]);
        if ($tmp_name != "") {
            $ext = strtolower(pathinfo($cv_name, PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $uploaded_count++;
            }
        }
    }
}

$view_token = bin2hex(random_bytes(16)); // Secure random token
$type = isset($data['type']) && trim($data['type']) === 'test' ? 'test' : NULL;

$stmt = $conn->prepare("INSERT INTO Job_List (job_title, jd_id, created_by, task_no, server_allocation, status, department, concern_person, concern_email, send_mail, source, total_cv_download, total_bdjobs_profile, view_token, type) VALUES (?, ?, ?, ?, ?, 'created', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssisssisiiss", $job_title, $jd_id, $created_by, $next_task_no, $next_server, $department, $concern_person, $concern_email, $send_mail, $source, $uploaded_count, $uploaded_count, $view_token, $type);

// Capture prompt text from request if provided
$prompt_text = isset($data['prompt_text']) ? trim($data['prompt_text']) : '';

if ($stmt->execute()) {
    $job_id = $stmt->insert_id;
    $conn->commit(); // Commit all changes
    
    // Also insert an initial prompt record to link the JD ID
    $p_stmt = $conn->prepare("INSERT INTO prompts (prompt_text, jd_id, user_id, user_name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE prompt_text = VALUES(prompt_text)");
    $full_name = $_SESSION['full_name'] ?? 'System';
    $p_stmt->bind_param("ssss", $prompt_text, $jd_id, $created_by, $full_name);
    $p_stmt->execute();
    $p_stmt->close();

    // Send Confirmation Email to the Creator in BACKGROUND
    try {
        $php_path = 'C:\wamp64\bin\php\php8.3.28\php.exe'; 
        $script_path = __DIR__ . '/send_notification.php';
        $cmd = "start /B $php_path \"$script_path\" creation \"$jd_id\" \"$created_by\" $job_id";
        pclose(popen($cmd, "r"));
    } catch (Exception $e) {
        error_log("Background Mail Launch Error: " . $e->getMessage());
    }

    echo json_encode(["status" => "success", "message" => "Job title added successfully.", "id" => $job_id, "task_no" => $next_task_no, "server_allocation" => $next_server]);
}
else {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => "Failed to add job title: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>
