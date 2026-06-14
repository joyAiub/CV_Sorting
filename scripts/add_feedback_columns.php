<?php
include("../config/db.php");

$results = [];

// Add feedback fields to candidates table
$candidate_cols = [
    "ALTER TABLE candidates ADD COLUMN feedback_comment TEXT NULL",
    "ALTER TABLE candidates ADD COLUMN feedback_recommended TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE candidates ADD COLUMN feedback_updated_at DATETIME NULL",
];

foreach ($candidate_cols as $sql) {
    try {
        $conn->query($sql);
        $results[] = ['ok', $sql];
    } catch (Exception $e) {
        $results[] = ['skip', $sql . ' — ' . $e->getMessage()];
    }
}

// Add submission tracking fields to Job_List
$job_cols = [
    "ALTER TABLE Job_List ADD COLUMN feedback_submitted_at DATETIME NULL",
    "ALTER TABLE Job_List ADD COLUMN feedback_submitted_by VARCHAR(255) NULL",
    "ALTER TABLE Job_List ADD COLUMN feedback_submission_count INT NOT NULL DEFAULT 0",
];

foreach ($job_cols as $sql) {
    try {
        $conn->query($sql);
        $results[] = ['ok', $sql];
    } catch (Exception $e) {
        $results[] = ['skip', $sql . ' — ' . $e->getMessage()];
    }
}

// Create submission history audit table
$create_history = "CREATE TABLE IF NOT EXISTS task_feedback_submission_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    submission_no INT NOT NULL DEFAULT 1,
    submitted_by VARCHAR(255) NULL,
    submitted_at DATETIME NULL,
    feedback_snapshot_json LONGTEXT NULL,
    INDEX idx_task_id (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($create_history)) {
    $results[] = ['ok', 'Created task_feedback_submission_history table'];
} else {
    $results[] = ['skip', 'task_feedback_submission_history — ' . $conn->error];
}

$conn->close();

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;'>";
echo "<strong>Feedback Module — Database Migration</strong>\n";
echo str_repeat('-', 70) . "\n";
foreach ($results as [$status, $msg]) {
    $color = $status === 'ok' ? 'green' : 'gray';
    echo "<span style='color:{$color};'>[" . strtoupper($status) . "]</span> {$msg}\n";
}
echo str_repeat('-', 70) . "\n";
echo "Done. Run this script only once.\n";
echo "</pre>";