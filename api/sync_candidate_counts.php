<?php
/**
 * sync_candidate_counts.php
 * 
 * This script recalibrates the 'total_candidate' column in the Job_List table
 * based on the actual number of records currently in the 'candidates' table.
 */

header("Content-Type: application/json");
include("../config/db.php");

$sync_query = "UPDATE Job_List jl
              SET total_candidate = (SELECT COUNT(*) FROM candidates c WHERE c.jd_id = jl.jd_id)";

if ($conn->query($sync_query)) {
    $affected = $conn->affected_rows;
    echo json_encode([
        "status" => "success",
        "message" => "Candidate counts synced successfully.",
        "jobs_updated" => $affected
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to sync counts: " . $conn->error
    ]);
}

$conn->close();
?>
