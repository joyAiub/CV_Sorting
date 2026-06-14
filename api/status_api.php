<?php
include __DIR__ . '/../config/db.php';
include __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $result = $conn->query("SELECT * FROM job_statuses ORDER BY display_order ASC");
    $statuses = [];
    while ($row = $result->fetch_assoc()) {
        $statuses[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $statuses]);

} elseif ($action === 'add') {
    check_auth();
    if (!has_permission('manage_statuses')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['status_name'] ?? '');
    if (!$name) {
        echo json_encode(['status' => 'error', 'message' => 'Status name required.']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO job_statuses (status_name, display_order) VALUES (?, (SELECT COALESCE(MAX(display_order),0)+1 FROM job_statuses j))");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Status added.', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Status already exists or DB error.']);
    }
    $stmt->close();

} elseif ($action === 'delete') {
    check_auth();
    if (!has_permission('manage_statuses')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM job_statuses WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Status deleted.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not delete status.']);
    }
    $stmt->close();

} elseif ($action === 'reorder') {
    check_auth();
    if (!has_permission('manage_statuses')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $ordered_ids = $data['ordered_ids'] ?? [];
    if (empty($ordered_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No order provided.']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE job_statuses SET display_order = ? WHERE id = ?");
    foreach ($ordered_ids as $order => $id) {
        $order_val = $order + 1;
        $stmt->bind_param("ii", $order_val, $id);
        $stmt->execute();
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Order updated.']);

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}

$conn->close();
?>
