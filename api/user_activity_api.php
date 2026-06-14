<?php
header("Content-Type: application/json");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../config/db.php");
include("../config/auth.php");

// Only admins/super-admins with explicit permission can view activity
check_auth(['admin', 'super-admin']);

if (!has_permission('view_user_activity')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Permission denied: view_user_activity"]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        // A user is considered "Online" if they had activity in the last 2 minutes
        // We count distinct sessions to see how many devices are connected
        $query = "SELECT u.id, u.username, u.employee_id, u.role, u.last_activity, u.last_ip, e.full_name, e.department,
                  CASE WHEN u.last_activity >= NOW() - INTERVAL 2 MINUTE THEN 'online' ELSE 'offline' END as online_status,
                  TIMESTAMPDIFF(SECOND, u.last_activity, NOW()) as seconds_ago,
                  (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.last_activity >= NOW() - INTERVAL 2 MINUTE) as session_count
                  FROM users u
                  LEFT JOIN employees e ON u.employee_id = e.employee_id
                  ORDER BY FIELD(u.role, 'super-admin', 'admin', 'sub-admin', 'sub_admin', 'user') ASC, u.username ASC";
        
        $result = $conn->query($query);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $data]);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}
?>
