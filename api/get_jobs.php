<?php
header("Content-Type: application/json");
include("../config/db.php");

// Proactively recalibrate counts ONLY if explicitly requested (optional, usually handled by insert_candidate)
if (isset($_GET['recalibrate'])) {
    $conn->query("UPDATE Job_List jl SET total_candidate = (SELECT COUNT(*) FROM candidates c WHERE c.jd_id = jl.jd_id)");
}

// Fetch Task Edit Limit (Default 5 mins)
$limit_res = $conn->query("SELECT `value` FROM rpa_config_app_system WHERE `key` = 'task_edit_limit_minutes'");
$edit_limit_mins = ($limit_res && $l_row = $limit_res->fetch_assoc()) ? intval($l_row['value']) : 5;

// Auto-transition 'created' tasks after limit expires
// Using SQL-side time calculation to avoid PHP/DB timezone mismatches
// For BDJobs/Both, it goes to 'pending' (to be downloaded by bot)
$conn->query("UPDATE Job_List SET status = 'pending' WHERE LOWER(TRIM(status)) = 'created' AND LOWER(TRIM(source)) != 'manual' AND created_at <= DATE_SUB(NOW(), INTERVAL $edit_limit_mins MINUTE)");

// For Manual tasks, it skips 'pending' and goes straight to 'downloaded'
$conn->query("UPDATE Job_List SET status = 'downloaded' WHERE LOWER(TRIM(status)) = 'created' AND LOWER(TRIM(source)) = 'manual' AND created_at <= DATE_SUB(NOW(), INTERVAL $edit_limit_mins MINUTE)");

// Optimized Main Query — derived table avoids correlated subquery per row
$query = "SELECT j.*, e.full_name as creator_name, p.id as prompt_id, p.prompt_text
          FROM Job_List j
          LEFT JOIN users u ON j.created_by = u.username
          LEFT JOIN employees e ON u.employee_id = e.employee_id
          LEFT JOIN (SELECT jd_id, MAX(id) AS max_id FROM prompts GROUP BY jd_id) lp ON lp.jd_id = j.jd_id
          LEFT JOIN prompts p ON p.id = lp.max_id
          LEFT JOIN job_statuses js ON j.status = js.status_name
          ORDER BY COALESCE(js.display_order, 9999) ASC, j.created_at DESC";

$result = $conn->query($query);

$jobs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $created_time = strtotime($row['created_at']);
        $now = time();
        $diff_mins = ($now - $created_time) / 60;
        
        // Status must be 'created' AND time must be within limit
        $row['is_editable'] = (strtolower(trim($row['status'])) === 'created' && $diff_mins <= $edit_limit_mins);
        $row['edit_limit_mins'] = $edit_limit_mins;
        $row['time_remaining_mins'] = max(0, round($edit_limit_mins - $diff_mins, 1));
        
        $jobs[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "data" => $jobs
]);

$conn->close();
?>
