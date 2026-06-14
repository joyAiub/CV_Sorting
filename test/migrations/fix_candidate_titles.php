<?php
include("config/db.php");
// Update candidates job_title to match Job_List job_title where jd_id matches
$sql = "UPDATE candidates c 
        JOIN Job_List j ON c.jd_id = j.jd_id 
        SET c.job_title = j.job_title 
        WHERE c.job_title != j.job_title";

if ($conn->query($sql)) {
    echo "Successfully updated " . $conn->affected_rows . " candidates.\n";
}
else {
    echo "Error: " . $conn->error . "\n";
}
$conn->close();
?>
