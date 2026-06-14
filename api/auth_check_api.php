<?php
header("Content-Type: application/json");
include __DIR__ . "/../config/db.php";
include __DIR__ . "/../config/auth.php";

// check_auth() is called here. 
// If session is invalid, blocked, or deleted, auth.php will already:
// 1. Send 401/403 status code
// 2. Echo JSON error
// 3. Exit
check_auth();

// Refresh session data from DB for real-time permission sync
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role, permissions FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if ($res) {
    $_SESSION['role'] = $res['role'];
    $_SESSION['permissions'] = $res['permissions'];
}
$stmt->close();

// If we reach here, it means the user is still valid and authorized.
echo json_encode([
    "status" => "success",
    "username" => $_SESSION['username'],
    "root_admin_id" => get_root_admin_id(),
    "role" => $_SESSION['role'],
    "permissions" => is_string($_SESSION['permissions']) ? json_decode($_SESSION['permissions'], true) : $_SESSION['permissions']
]);
?>
