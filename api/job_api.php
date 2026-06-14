<?php
ob_start(); // Buffer output to prevent accidental leaks
header('Content-Type: application/json');

// Suppress notices that might break JSON
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

include("../config/auth.php");
check_auth();

// Only admin/super-admin/sub-admin with manage_tasks permission can delete
// Check permissions: manage_tasks (admins) or add_task (limited edit window)
$can_manage = has_permission('manage_tasks');
$can_add = has_permission('add_task');

if ($action !== 'share' && !$can_manage && !$can_add) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

include("../config/db.php");

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

if ($action === 'delete') {
    $jd_id = $data['jd_id'] ?? '';
    if (empty($jd_id)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'JD ID is required.']);
        exit;
    }

    // If not admin, verify it's still in the 'created' window
    if (!$can_manage) {
        $chk = $conn->prepare("SELECT status, created_at FROM Job_List WHERE jd_id = ?");
        $chk->bind_param("s", $jd_id);
        $chk->execute();
        $res = $chk->get_result()->fetch_assoc();
        $chk->close();
        
        if (!$res || strtolower(trim($res['status'])) !== 'created') {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Task can no longer be deleted.']);
            exit;
        }
        
        $limit_res = $conn->query("SELECT `value` FROM rpa_config_app_system WHERE `key` = 'task_edit_limit_minutes'");
        $limit_mins = ($limit_res && $row = $limit_res->fetch_assoc()) ? intval($row['value']) : 5;
        if ((time() - strtotime($res['created_at'])) > ($limit_mins * 60)) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Deletion time limit expired.']);
            exit;
        }
    }

    // Fetch task info to delete physical files later
    $t_chk = $conn->prepare("SELECT task_no, source FROM Job_List WHERE jd_id = ?");
    $t_chk->bind_param("s", $jd_id);
    $t_chk->execute();
    $task_info = $t_chk->get_result()->fetch_assoc();
    $t_chk->close();

    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("DELETE FROM candidates WHERE jd_id = ?");
        $stmt1->bind_param("s", $jd_id);
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("DELETE FROM prompts WHERE jd_id = ?");
        $stmt2->bind_param("s", $jd_id);
        $stmt2->execute();
        $stmt2->close();

        $stmt3 = $conn->prepare("DELETE FROM Job_List WHERE jd_id = ?");
        $stmt3->bind_param("s", $jd_id);
        $stmt3->execute();
        $stmt3->close();

        $conn->commit();
        
        // Delete the physical manual folder
        if ($task_info && in_array(strtolower(trim($task_info['source'])), ['manual', 'both'])) {
            $task_no = $task_info['task_no'];
            if (!empty($task_no)) {
                // Fetch base path using same logic as test_path_helper_v1.php
                $stmt_path = $conn->prepare("SELECT value FROM rpa_config_app_system WHERE `key` = 'MANUAL_UPLOAD_BASE_PATH'");
                $stmt_path->execute();
                $base_val = null;
                $stmt_path->bind_result($base_val);
                $stmt_path->fetch();
                $stmt_path->close();
                
                $manual_base = $base_val ? $base_val : "C:\\Users\\admin\\.n8n-files\\Manual";
                $base_dir = $manual_base . DIRECTORY_SEPARATOR . $task_no;
                
                if (file_exists($base_dir) && is_dir($base_dir)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($files as $fileinfo) {
                        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                        @$todo($fileinfo->getRealPath());
                    }
                    @rmdir($base_dir);
                }
            }
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Task deleted successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} elseif ($action === 'update') {
    $orig_jd_id       = $data['orig_jd_id'] ?? '';
    $new_jd_id        = trim($data['jd_id'] ?? '');
    $job_title        = trim($data['job_title'] ?? '');
    $task_no          = trim($data['task_no'] ?? '');
    $server_allocation = intval($data['server_allocation'] ?? 0);
    $prompt_text      = trim($data['prompt_text'] ?? '');

    if (empty($orig_jd_id)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Original JD ID is required.']);
        exit;
    }

    // Fetch existing job data to fill blanks (for partial updates from Home Page)
    $stmt_orig = $conn->prepare("SELECT * FROM Job_List WHERE jd_id = ?");
    $stmt_orig->bind_param("s", $orig_jd_id);
    $stmt_orig->execute();
    $existing = $stmt_orig->get_result()->fetch_assoc();
    $stmt_orig->close();

    if (!$existing) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Task not found.']);
        exit;
    }

    // Fill missing fields with existing data
    if (empty($new_jd_id)) $new_jd_id = $existing['jd_id'];
    if (empty($job_title)) $job_title = $existing['job_title'];
    if (empty($task_no)) $task_no = $existing['task_no'];
    if ($server_allocation <= 0) $server_allocation = $existing['server_allocation'];

    $department     = isset($data['department']) ? trim($data['department']) : $existing['department'];
    $concern_person = isset($data['concern_person']) ? trim($data['concern_person']) : $existing['concern_person'];
    $concern_email  = isset($data['concern_email']) ? trim($data['concern_email']) : $existing['concern_email'];
    $send_mail      = isset($data['send_mail']) ? (int)$data['send_mail'] : (int)$existing['send_mail'];

    // If not admin, verify it's still in the 'created' window
    if (!$can_manage) {
        if (strtolower(trim($existing['status'])) !== 'created') {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Task can no longer be edited.']);
            exit;
        }
        
        $limit_res = $conn->query("SELECT `value` FROM rpa_config_app_system WHERE `key` = 'task_edit_limit_minutes'");
        $limit_mins = ($limit_res && $row = $limit_res->fetch_assoc()) ? intval($row['value']) : 5;
        if ((time() - strtotime($existing['created_at'])) > ($limit_mins * 60)) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Editing time limit expired.']);
            exit;
        }
    }

    // Check uniqueness if JD ID changed
    if ($new_jd_id !== $orig_jd_id) {
        $chk = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
        $chk->bind_param("s", $new_jd_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => "JD ID '$new_jd_id' already exists."]);
            exit;
        }
        $chk->close();
    }

    $stmt = $conn->prepare("UPDATE Job_List SET jd_id=?, job_title=?, task_no=?, server_allocation=?, department=?, concern_person=?, concern_email=?, send_mail=? WHERE jd_id=?");
    $stmt->bind_param("sssisssis", $new_jd_id, $job_title, $task_no, $server_allocation, $department, $concern_person, $concern_email, $send_mail, $orig_jd_id);
    
    if ($stmt->execute()) {
        if ($new_jd_id !== $orig_jd_id) {
            $conn->query("UPDATE candidates SET jd_id='$new_jd_id' WHERE jd_id='$orig_jd_id'");
            $conn->query("UPDATE prompts SET jd_id='$new_jd_id' WHERE jd_id='$orig_jd_id'");
        }
        
        // Update prompt text if provided
        if (isset($data['prompt_text'])) {
            $upd_p = $conn->prepare("UPDATE prompts SET prompt_text=? WHERE jd_id=?");
            $upd_p->bind_param("ss", $prompt_text, $new_jd_id);
            $upd_p->execute();
            $upd_p->close();
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Task updated successfully.']);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
} elseif ($action === 'share') {
    $jd_id = trim($data['jd_id'] ?? '');
    $allowed_viewers = trim($data['allowed_viewers'] ?? '');

    if (empty($jd_id)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'JD ID is required.']);
        exit;
    }

    // Fetch the task to verify existence and check creator
    $stmt_chk = $conn->prepare("SELECT created_by FROM Job_List WHERE jd_id = ?");
    $stmt_chk->bind_param("s", $jd_id);
    $stmt_chk->execute();
    $task = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();

    if (!$task) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Task not found.']);
        exit;
    }

    $is_creator = (isset($_SESSION['username']) && strtolower(trim($_SESSION['username'])) === strtolower(trim($task['created_by'])));
    
    // Root Admin bypass
    $is_root = (isset($_SESSION['username']) && $_SESSION['username'] === get_root_admin_id());

    if (!$can_manage && !$is_creator && !$is_root) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to share this task.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE Job_List SET allowed_viewers = ? WHERE jd_id = ?");
    $stmt->bind_param("ss", $allowed_viewers, $jd_id);
    
    if ($stmt->execute()) {
        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Permissions saved successfully.']);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $conn->error]);
    }
    $stmt->close();
} else {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}
