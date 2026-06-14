<?php
header('Content-Type: application/json');
include('../config/db.php');

$task_no = 'TASK161';

$stmt = $conn->prepare("SELECT id, task_no, job_title, status FROM Job_List WHERE task_no = ?");
$stmt->bind_param("s", $task_no);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $task = $result->fetch_assoc();
    echo json_encode([
        "status" => "found",
        "task" => $task
    ]);
} else {
    // Try to find any task with 161 in it
    $searchStmt = $conn->prepare("SELECT id, task_no, job_title, status FROM Job_List WHERE task_no LIKE ? LIMIT 5");
    $search = '%161%';
    $searchStmt->bind_param("s", $search);
    $searchStmt->execute();
    $searchResult = $searchStmt->get_result();

    if ($searchResult->num_rows > 0) {
        $tasks = [];
        while ($row = $searchResult->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode([
            "status" => "not_found_exact",
            "message" => "TASK161 not found, but found similar:",
            "similar_tasks" => $tasks
        ]);
    } else {
        echo json_encode([
            "status" => "not_found",
            "message" => "TASK161 does not exist in database"
        ]);
    }
}

$conn->close();
?>
