<?php
/**
 * update_task_status.php
 * 
 * Updates a job's status using its jd_id.
 * Accepts POST data (JSON or Form-data).
 * 
 * Parameters:
 *   jd_id (string): The unique JD ID of the job.
 *   status (string|int): The new status name (e.g., 'downloading') 
 *                        OR the display_order number (e.g., 2).
 */

header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";

// 1. Inputs (Checks JSON, POST, and GET)
$is_bot = true; // For this specific API, we treat all calls as "System/Bot" to trigger notifications
$input = json_decode(file_get_contents('php://input'), true);
$jd_id = trim($input['jd_id'] ?? $_POST['jd_id'] ?? $_GET['jd_id'] ?? '');
$status_input = trim($input['status'] ?? $_POST['status'] ?? $_GET['status'] ?? '');

if (!$jd_id || $status_input === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing jd_id or status.']);
    exit;
}

// 2. Resolve Status Name if input is numeric
$final_status = $status_input;
if (is_numeric($status_input)) {
    $stmt = $conn->prepare("SELECT status_name FROM job_statuses WHERE display_order = ?");
    $display_order = (int)$status_input;
    $stmt->bind_param("i", $display_order);
    $stmt->execute();
    $stmt->bind_result($status_name);
    if ($stmt->fetch()) {
        $final_status = $status_name;
    } else {
        echo json_encode(['status' => 'error', 'message' => "Status with order '$status_input' not found."]);
        $stmt->close();
        exit;
    }
    $stmt->close();
} else {
    // Validate that the status name exists in our allowed list
    $stmt = $conn->prepare("SELECT id FROM job_statuses WHERE LOWER(status_name) = LOWER(?)");
    $stmt->bind_param("s", $status_input);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => "Status name '$status_input' is not valid."]);
        $stmt->close();
        exit;
    }
    $stmt->close();
}

// 3. Update the Job
$update = $conn->prepare("UPDATE Job_List SET status = ? WHERE jd_id = ?");
$update->bind_param("ss", $final_status, $jd_id);

if ($update->execute()) {
    if ($update->affected_rows > 0) {
        // Trigger Email and File Move if status is Completed in BACKGROUND (ONLY FOR BOTS)
        if (strtolower($final_status) === 'completed' && $is_bot) {
            try {
                // Fetch numeric ID and task_no
                $job_res = $conn->query("SELECT id, task_no FROM Job_List WHERE jd_id = '$jd_id'");
                if ($job_row = $job_res->fetch_assoc()) {
                    $job_id = $job_row['id'];
                    $task_no = $job_row['task_no'];
                    
                    // 1. Trigger Notification
                    $php_path = 'C:\wamp64\bin\php\php8.3.28\php.exe'; 
                    $script_path = __DIR__ . '/send_notification.php';
                    $cmd = "start /B $php_path \"$script_path\" completion \"$jd_id\" \"System\" $job_id";
                    pclose(popen($cmd, "r"));

                    // 2. Move Folder from C to D (for storage optimization)
                    if ($task_no) {
                        $source_base = "C:\\Users\\admin\\.n8n-files\\Processed";
                        $target_base = "D:\\CV Data Backup (Don't Delete)\\CV Sorting Project\\Completed";
                        
                        $source_path = $source_base . DIRECTORY_SEPARATOR . $task_no;
                        $target_path = $target_base . DIRECTORY_SEPARATOR . $task_no;

                        if (is_dir($source_path)) {
                            // Ensure target directory exists
                            if (!is_dir($target_base)) {
                                mkdir($target_base, 0777, true);
                            }
                            
                            // Use robocopy for reliable move across drives
                            // /MOVE moves files AND directories, /E includes subfolders
                            $move_cmd = "robocopy \"$source_path\" \"$target_path\" /E /MOVE /NP /NDL /NJH /NJS";
                            exec($move_cmd);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Background Processing Error: " . $e->getMessage());
            }
        }

        echo json_encode([
            'status' => 'success', 
            'message' => "Job $jd_id updated to '$final_status'.",
            'updated_jd_id' => $jd_id,
            'new_status' => $final_status
        ]);
    } else {
        // Check if JD_ID actually exists
        $check = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
        $check->bind_param("s", $jd_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => "Job with JD ID '$jd_id' not found."]);
        } else {
            echo json_encode(['status' => 'success', 'message' => "Job $jd_id is already set to '$final_status'. No changes made."]);
        }
        $check->close();
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $conn->error]);
}

$update->close();
$conn->close();
?>
