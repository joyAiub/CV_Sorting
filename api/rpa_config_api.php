<?php
header("Content-Type: application/json");
include("../config/db.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_name = $_SESSION['full_name'] ?? 'System';

$action = $_GET['action'] ?? 'list';
$data = json_decode(file_get_contents("php://input"), true);

switch ($action) {
    case 'all':
        $query = "SELECT * FROM rpa_config ORDER BY category, `key` ASC";
        $result = $conn->query($query);
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $configs]);
        break;

    case 'list':
        $query = "SELECT `key`, `value` FROM rpa_config";
        $result = $conn->query($query);
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[$row['key']] = $row['value'];
        }
        echo json_encode($configs);
        break;

    case 'get':
        $key = $_GET['key'] ?? ($data['key'] ?? '');
        if (!$key) {
            echo json_encode(["status" => "error", "message" => "Key is required"]);
            exit;
        }
        $stmt = $conn->prepare("SELECT `value` FROM rpa_config WHERE `key` = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            echo json_encode(["status" => "success", "key" => $key, "value" => $res['value']]);
        } else {
            echo json_encode(["status" => "error", "message" => "Key not found"]);
        }
        break;

    case 'create':
    case 'update':
        $key = $data['key'] ?? '';
        $value = $data['value'] ?? '';
        $category = $data['category'] ?? '';
        $project = $data['project'] ?? '';
        $description = $data['description'] ?? '';
        $id = $data['id'] ?? null;

        if (!$key || !$project) {
            echo json_encode(["status" => "error", "message" => "Key and Project are required"]);
            exit;
        }

        if ($id) {
            $stmt = $conn->prepare("UPDATE rpa_config SET `key`=?, `value`=?, category=?, project=?, description=?, updated_by=? WHERE id=?");
            $stmt->bind_param("ssssssi", $key, $value, $category, $project, $description, $user_name, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO rpa_config (`key`, `value`, category, project, description, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), category=VALUES(category), description=VALUES(description), updated_by=VALUES(updated_by)");
            $stmt->bind_param("sssssss", $key, $value, $category, $project, $description, $user_name, $user_name);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Configuration saved"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    case 'update_by_key':
        $key = $_GET['key'] ?? ($data['key'] ?? '');
        $value = $_GET['value'] ?? ($data['value'] ?? '');

        if (!$key) {
            echo json_encode(["status" => "error", "message" => "Key is required"]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE rpa_config SET `value`=?, updated_by=? WHERE `key`=?");
        $stmt->bind_param("sss", $value, $user_name, $key);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Configuration updated"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    case 'update_multiple':
        // Merge JSON body and Query Parameters
        $payload = array_merge($_GET, $data ?? []);
        unset($payload['action']); 

        if (empty($payload)) {
            echo json_encode(["status" => "error", "message" => "No data provided to update"]);
            exit;
        }

        $success_count = 0;
        $updated_data = [];
        $errors = [];

        // Use UPSERT (Insert or Update)
        $stmt = $conn->prepare("INSERT INTO rpa_config (`key`, `value`, category, project, created_by, updated_by) VALUES (?, ?, 'General', 'General', ?, ?) 
                               ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by)");

        foreach ($payload as $k => $v) {
            // Skip api_key or internal params
            if ($k === 'api_key') continue;

            $val_to_save = (is_array($v) || is_object($v)) ? json_encode($v) : (string)$v;

            $stmt->bind_param("ssss", $k, $val_to_save, $user_name, $user_name);
            if ($stmt->execute()) {
                $success_count++;
                $updated_data[$k] = $v;
            } else {
                $errors[$k] = $stmt->error;
            }
        }
        $stmt->close();

        echo json_encode([
            "status" => empty($errors) ? "success" : "partial_success",
            "message" => "Processed $success_count configuration(s)",
            "updated_data" => $updated_data,
            "errors" => empty($errors) ? null : $errors
        ]);
        break;

    case 'delete':
        $id = $data['id'] ?? null;
        if (!$id) {
            echo json_encode(["status" => "error", "message" => "ID required"]);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM rpa_config WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Configuration deleted"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
}

$conn->close();
?>
