<?php
include("config/db.php");

echo "Starting synchronization of candidate counts and statuses...\n";

// 1. Get all jobs
$jobs = $conn->query("SELECT jd_id, status FROM Job_List");

if ($jobs) {
    while ($job = $jobs->fetch_assoc()) {
        $jd_id = $job['jd_id'];
        $current_status = strtolower(trim($job['status']));

        // 2. Count candidates for this job
        $count_stmt = $conn->prepare("SELECT COUNT(*) FROM candidates WHERE jd_id = ?");
        $count_stmt->bind_param("s", $jd_id);
        $count_stmt->execute();
        $count_stmt->bind_result($count);
        $count_stmt->fetch();
        $count_stmt->close();

        // 3. Determine new status
        $new_status = $job['status'];
        if ($count == 0) {
            $new_status = 'Pending';
        }
        elseif ($count > 0 && ($current_status === 'pending' || empty($current_status))) {
            $new_status = 'In Progress';
        }

        // 4. Update Job_List
        $update_stmt = $conn->prepare("UPDATE Job_List SET total_candidate = ?, status = ? WHERE jd_id = ?");
        $update_stmt->bind_param("iss", $count, $new_status, $jd_id);
        $update_stmt->execute();
        $update_stmt->close();

        echo "JD ID: $jd_id | Count: $count | New Status: $new_status\n";
    }
}

echo "Synchronization complete!\n";
$conn->close();
?>
