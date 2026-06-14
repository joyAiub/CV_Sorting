<?php
/**
 * get_job_task_data.php
 * 
 * Returns Job List with computed TASK numbers (TASK1, TASK2, ...) 
 * ordered by job ID ascending (lowest ID = TASK1).
 * 
 * Parameters:
 *   status (GET): 
 *     - 0 or omitted  = all statuses
 *     - 1             = status with display_order 1 (e.g. Pending)
 *     - 2             = status with display_order 2 (e.g. downloading)
 *     - 1,2,3         = multiple statuses by their display_order number
 * 
 * Example Usage:
 *   /api/get_job_task_data.php              -> all jobs
 *   /api/get_job_task_data.php?status=0     -> all jobs
 *   /api/get_job_task_data.php?status=1     -> Pending only
 *   /api/get_job_task_data.php?status=1,2   -> Pending + downloading
 */

header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";

// 1. Load statuses ordered by display_order to map number -> status_name
$statusMap = []; // e.g. 1 => 'Pending', 2 => 'downloading', etc.
$sr = $conn->query("SELECT display_order, status_name FROM job_statuses ORDER BY display_order ASC");
while ($row = $sr->fetch_assoc()) {
    $statusMap[(int)$row['display_order']] = $row['status_name'];
}

// 1.5 Load sources ordered by display_order to map number -> source_name
$sourceMap = []; // e.g. 1 => 'bdjobs', 2 => 'manual', etc.
$sr_sources = $conn->query("SELECT display_order, source_name FROM job_sources ORDER BY display_order ASC");
while ($row = $sr_sources->fetch_assoc()) {
    $sourceMap[(int)$row['display_order']] = $row['source_name'];
}

// 2. Parse parameters
$statusParam = trim($_GET['status'] ?? '0');
$jobSourceParam = trim($_GET['job_source'] ?? '0');
$taskNoParam = trim($_GET['task_no'] ?? '');
$serverAllocParam = trim($_GET['server_allocation'] ?? '');

$filterNames = []; // list of status_name values to filter by
if ($statusParam !== '0' && $statusParam !== '') {
    $requestedOrders = array_map('trim', explode(',', $statusParam));
    foreach ($requestedOrders as $ord) {
        $ord = (int)$ord;
        if (isset($statusMap[$ord])) {
            $filterNames[] = $statusMap[$ord];
        }
    }
}

$filterSources = []; // list of source_name values to filter by
if ($jobSourceParam !== '0' && $jobSourceParam !== '') {
    $requestedSOrders = array_map('trim', explode(',', $jobSourceParam));
    foreach ($requestedSOrders as $ord) {
        $ord = (int)$ord;
        if (isset($sourceMap[$ord])) {
            $filterSources[] = $sourceMap[$ord];
        }
    }
}

$filterTaskNos = []; // list of task_no values to filter by
if ($taskNoParam !== '' && $taskNoParam !== '0') {
    $filterTaskNos = array_map('trim', explode(',', strtoupper($taskNoParam)));
}

$filterServerAllocs = []; // list of server_allocation values to filter by
if ($serverAllocParam !== '' && $serverAllocParam !== '0') {
    $filterServerAllocs = array_map('intval', explode(',', $serverAllocParam));
}

include_once __DIR__ . "/../includes/path_helper.php";

// 3. Build the query
$sql = "SELECT id, task_no, job_title, jd_id, status, created_by, created_at, server_allocation, total_candidate, total_cv_download, total_bdjobs_profile, source, department AS concern_department, concern_person
        FROM Job_List
        ORDER BY id ASC";

$result = $conn->query($sql);
$allJobs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $source = strtolower($row['source'] ?? '');
        $row['Base_Path'] = "";
        $row['CV_path'] = "";
        $row['JD_path'] = "";

        if ($source === 'manual' || $source === 'both') {
            $task_no = $row['task_no'];
            // Use path_helper to get base path
            $manual_base = getRpaConfigValue($conn, 'MANUAL_UPLOAD_BASE_PATH', "C:\\Users\\admin\\.n8n-files\\Manual");
            
            $base_folder = $manual_base . DIRECTORY_SEPARATOR . $task_no;
            $row['Base_Path'] = $base_folder;
            $row['CV_path'] = $base_folder . DIRECTORY_SEPARATOR . "CVs";
            
            if ($source === 'manual') {
                $row['JD_path'] = $base_folder . DIRECTORY_SEPARATOR . "JD";
            }
        }
        $allJobs[] = $row;
    }
}

// 4. (Removed manual TASK numbering - it is now stored in DB)

// 5. Apply filters
if (!empty($filterNames) || !empty($filterSources) || !empty($filterTaskNos) || !empty($filterServerAllocs)) {
    $filterNamesLower = array_map('strtolower', $filterNames);
    $filterSourcesLower = array_map('strtolower', $filterSources);
    
    $allJobs = array_filter($allJobs, function($job) use ($filterNamesLower, $filterSourcesLower, $filterTaskNos, $filterServerAllocs) {
        $statusMatch = true;
        if (!empty($filterNamesLower)) {
            $statusMatch = in_array(strtolower(trim($job['status'])), $filterNamesLower);
        }

        $sourceMatch = true;
        if (!empty($filterSourcesLower)) {
            $sourceMatch = in_array(strtolower(trim($job['source'])), $filterSourcesLower);
        }
        
        $taskMatch = true;
        if (!empty($filterTaskNos)) {
            $taskMatch = in_array(strtoupper(trim($job['task_no'])), $filterTaskNos);
        }

        $serverMatch = true;
        if (!empty($filterServerAllocs)) {
            $serverMatch = in_array((int)$job['server_allocation'], $filterServerAllocs);
        }
        
        return $statusMatch && $sourceMatch && $taskMatch && $serverMatch;
    });
    $allJobs = array_values($allJobs); // re-index
}

// 6. Return result
$meta = [
    'total' => count($allJobs),
    'status_filter' => $statusParam === '0' || $statusParam === '' ? 'all' : $statusParam,
    'source_filter' => $jobSourceParam === '0' || $jobSourceParam === '' ? 'all' : $jobSourceParam,
    'available_statuses' => $statusMap,
    'available_sources' => $sourceMap,
];

echo json_encode([
    'status'  => 'success',
    'meta'    => $meta,
    'data'    => $allJobs,
]);

$conn->close();
?>
