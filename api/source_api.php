<?php
header("Content-Type: application/json");
include __DIR__ . "/../config/auth.php";
check_auth();

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    // Allow listing sources if user can manage sources OR add tasks
    if (!has_permission('manage_sources') && !has_permission('add_task')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
        exit;
    }
} else {
    // Other actions (add, delete) still require manage_sources
    if (!has_permission('manage_sources')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
        exit;
    }
}

include __DIR__ . "/../config/db.php";

switch ($action) {
    case 'list':
        $result = $conn->query("SELECT * FROM job_sources ORDER BY display_order ASC, source_name ASC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        break;

    case 'add':
        $data = json_decode(file_get_contents("php://input"), true);
        $name = trim($data['source_name'] ?? '');
        if (!$name) {
            echo json_encode(['status' => 'error', 'message' => 'Source name is required']);
            break;
        }
        $stmt = $conn->prepare("INSERT INTO job_sources (source_name, display_order) VALUES (?, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM job_sources s))");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Source added']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            break;
        }
        $stmt = $conn->prepare("DELETE FROM job_sources WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Source deleted']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
