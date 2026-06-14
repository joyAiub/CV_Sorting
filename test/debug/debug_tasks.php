<?php
header('Content-Type: application/json');
include('../config/db.php');

$result = $conn->query("SELECT id, task_no, job_title, status FROM Job_List LIMIT 5");
$tasks = [];

while ($row = $result->fetch_assoc()) {
    $tasks[] = [
        'task_no' => $row['task_no'],
        'task_no_type' => gettype($row['task_no']),
        'task_no_length' => strlen($row['task_no']),
        'job_title' => $row['job_title'],
        'status' => $row['status']
    ];
}

echo json_encode([
    "status" => "success",
    "sample_tasks" => $tasks,
    "query_example" => "SELECT * FROM Job_List WHERE task_no = 'TASK161'"
]);

$conn->close();
?>
