<?php
header("Content-Type: application/json");
include("../config/db.php");
include("../config/auth.php");
check_auth();

if (!has_permission('manage_task_limits')) {
    echo json_encode(["status" => "error", "message" => "Permission denied"]);
    exit;
}

$action = $_GET['action'] ?? 'list';
$data = json_decode(file_get_contents("php://input"), true);

switch ($action) {
    case 'get_stats':
        $daily = $conn->query("SELECT COUNT(*) as cnt FROM job_list WHERE DATE(created_at) = CURRENT_DATE()")->fetch_assoc()['cnt'];
        $monthly = $conn->query("SELECT COUNT(*) as cnt FROM job_list WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)")->fetch_assoc()['cnt'];
        $total = $conn->query("SELECT COUNT(*) as cnt FROM job_list")->fetch_assoc()['cnt'];
        echo json_encode([
            "status" => "success", 
            "data" => [
                "daily" => $daily,
                "monthly" => $monthly,
                "total" => $total
            ]
        ]);
        exit;

    case 'list':
        $query = "SELECT * FROM task_limits ORDER BY limit_type ASC";
        $result = $conn->query($query);
        $limits = [];
        while ($row = $result->fetch_assoc()) {
            $limits[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $limits]);
        break;

    case 'save':
        $id = $data['id'] ?? null;
        $limit_type = $data['limit_type'] ?? '';
        $user_id = $data['user_id'] ?? null;
        $limit_value = $data['limit_value'] ?? 0;
        $is_active = $data['is_active'] ?? 1;

        if (!$limit_type) {
            echo json_encode(["status" => "error", "message" => "Limit type is required"]);
            exit;
        }

        // --- LOGICAL VALIDATION ---
        if ($is_active == 1) {
            // Fetch current active limits to compare
            $all_limits_res = $conn->query("SELECT limit_type, limit_value FROM task_limits WHERE is_active = 1 AND id != " . ($id ?: 0));
            $existing = [];
            while ($row = $all_limits_res->fetch_assoc()) {
                $existing[$row['limit_type']] = $row['limit_value'];
            }

            // Define hierarchy for validation
            if ($limit_type === 'daily') {
                if (isset($existing['monthly']) && $limit_value > $existing['monthly']) {
                    echo json_encode(["status" => "error", "message" => "Daily limit ($limit_value) cannot be greater than Monthly limit (" . $existing['monthly'] . ")"]);
                    exit;
                }
                if (isset($existing['total']) && $limit_value > $existing['total']) {
                    echo json_encode(["status" => "error", "message" => "Daily limit ($limit_value) cannot be greater than Total limit (" . $existing['total'] . ")"]);
                    exit;
                }
            }
            elseif ($limit_type === 'monthly') {
                if (isset($existing['total']) && $limit_value > $existing['total']) {
                    echo json_encode(["status" => "error", "message" => "Monthly limit ($limit_value) cannot be greater than Total limit (" . $existing['total'] . ")"]);
                    exit;
                }
                if (isset($existing['daily']) && $limit_value < $existing['daily']) {
                    echo json_encode(["status" => "error", "message" => "Monthly limit ($limit_value) cannot be less than Daily limit (" . $existing['daily'] . ")"]);
                    exit;
                }
            }
            elseif ($limit_type === 'total') {
                if (isset($existing['monthly']) && $limit_value < $existing['monthly']) {
                    echo json_encode(["status" => "error", "message" => "Total limit ($limit_value) cannot be less than Monthly limit (" . $existing['monthly'] . ")"]);
                    exit;
                }
                if (isset($existing['daily']) && $limit_value < $existing['daily']) {
                    echo json_encode(["status" => "error", "message" => "Total limit ($limit_value) cannot be less than Daily limit (" . $existing['daily'] . ")"]);
                    exit;
                }
            }
            elseif ($limit_type === 'per_user' || $limit_type === 'specific_user') {
                if (isset($existing['daily']) && $limit_value > $existing['daily']) {
                    echo json_encode(["status" => "error", "message" => "User daily limit ($limit_value) cannot be greater than System Daily limit (" . $existing['daily'] . ")"]);
                    exit;
                }
                if (isset($existing['monthly']) && $limit_value > $existing['monthly']) {
                    echo json_encode(["status" => "error", "message" => "User daily limit ($limit_value) cannot be greater than System Monthly limit (" . $existing['monthly'] . ")"]);
                    exit;
                }
                if (isset($existing['total']) && $limit_value > $existing['total']) {
                    echo json_encode(["status" => "error", "message" => "User daily limit ($limit_value) cannot be greater than System Total limit (" . $existing['total'] . ")"]);
                    exit;
                }
            }
            elseif ($limit_type === 'per_user_monthly' || $limit_type === 'specific_user_monthly') {
                if (isset($existing['monthly']) && $limit_value > $existing['monthly']) {
                    echo json_encode(["status" => "error", "message" => "User monthly limit ($limit_value) cannot be greater than System Monthly limit (" . $existing['monthly'] . ")"]);
                    exit;
                }
                if (isset($existing['total']) && $limit_value > $existing['total']) {
                    echo json_encode(["status" => "error", "message" => "User monthly limit ($limit_value) cannot be greater than System Total limit (" . $existing['total'] . ")"]);
                    exit;
                }
            }
        }
        // --- END LOGICAL VALIDATION ---

        if ($id) {
            $stmt = $conn->prepare("UPDATE task_limits SET limit_type=?, user_id=?, limit_value=?, is_active=? WHERE id=?");
            $stmt->bind_param("ssiii", $limit_type, $user_id, $limit_value, $is_active, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO task_limits (limit_type, user_id, limit_value, is_active) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE limit_value=VALUES(limit_value), is_active=VALUES(is_active)");
            $stmt->bind_param("ssii", $limit_type, $user_id, $limit_value, $is_active);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Limit saved successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    case 'delete':
        $id = $data['id'] ?? null;
        if (!$id) {
            echo json_encode(["status" => "error", "message" => "ID required"]);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM task_limits WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Limit deleted"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
}

$conn->close();
?>
