<?php
header("Content-Type: application/json");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../config/db.php");

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'search':
        include("../config/auth.php");
        check_auth();
        $q = isset($_GET['q']) ? $_GET['q'] : '';
        $q = "%$q%";
        $stmt = $conn->prepare("SELECT u.username, u.employee_id, e.full_name, e.department 
                                FROM users u 
                                LEFT JOIN employees e ON u.employee_id = e.employee_id 
                                WHERE u.username LIKE ? OR u.employee_id LIKE ? OR e.full_name LIKE ? 
                                LIMIT 10");
        $stmt->bind_param("sss", $q, $q, $q);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $users]);
        exit;

    case 'register':
        $data = json_decode(file_get_contents("php://input"), true);
        $emp_id = trim($data['employee_id']);
        $password = $data['password'];

        if (empty($emp_id) || empty($password)) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        // Check if employee exists
        $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $emp_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Invalid Employee ID. Please contact Admin."]);
            exit;
        }
        $stmt->close();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ? OR username = ?");
        $stmt->bind_param("ss", $emp_id, $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "Registration already exists for this Employee ID."]);
            exit;
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, role, status) VALUES (?, ?, ?, 'user', 'pending')");
        $stmt->bind_param("sss", $emp_id, $emp_id, $hashed_password);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Registration successful! Awaiting Admin approval."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Registration error: " . $conn->error]);
        }
        $stmt->close();
        break;

    case 'create':
        include("../config/auth.php");
        check_auth(); 
        if (!has_permission('manage_users') && !has_permission('create_user')) {
            echo json_encode(["status" => "error", "message" => "Permission denied: You do not have 'Create User' or 'Manage User' permission."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $emp_id = trim($data['username']); // The frontend uses 'username' field for EMP ID
        $password = $data['password'];
        $role = isset($data['role']) ? $data['role'] : 'user';

        // SECURITY: Only a Super Admin can create another Super Admin
        if ($role === 'super-admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super-admin')) {
            echo json_encode(["status" => "error", "message" => "Security Violation: Only a Super Admin can create another Super Admin account."]);
            exit;
        }

        if (empty($emp_id) || empty($password)) {
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        // Check if employee exists
        $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Invalid Employee ID. Not found in master list."]);
            exit;
        }
        $stmt->close();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ? OR username = ?");
        $stmt->bind_param("ss", $emp_id, $emp_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "User already exists with this ID."]);
            exit;
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Admin creates users as 'active' by default
        $stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, role, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssss", $emp_id, $emp_id, $hashed_password, $role);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User created successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        $stmt->close();
        break;

    case 'list':
        include("../config/auth.php");
        check_auth(); 
        if (!has_permission('manage_users')) {
            echo json_encode(["status" => "error", "message" => "Permission denied: You cannot view user list."]);
            exit;
        }

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $ROOT_ADMIN_ID = get_root_admin_id();
        $is_root = ($_SESSION['username'] === $ROOT_ADMIN_ID);
        $caller_role = $_SESSION['role'] ?? 'user';

        // Role hierarchy: determine which roles the current user is allowed to view
        // Root admin: all roles
        // Super-admin: all roles except root (root is filtered at row level below)
        // Admin: user + sub-admin only
        // Anyone else (user role with permission): user only
        if ($is_root || $caller_role === 'super-admin') {
            $allowed_roles_sql = ""; // No filter — see all
        } elseif ($caller_role === 'admin') {
            $allowed_roles_sql = " AND u.role IN ('user', 'sub-admin', 'sub_admin') ";
        } else {
            $allowed_roles_sql = " AND u.role = 'user' ";
        }

        $query = "SELECT u.id, u.username, u.employee_id, u.role, u.status, u.permissions, u.created_at, 
                         e.full_name, e.designation, e.department, e.mobile_no, e.ip_no 
                  FROM users u 
                  LEFT JOIN employees e ON u.employee_id = e.employee_id
                  WHERE 1=1 $allowed_roles_sql";

        if ($search !== '') {
            $query .= " AND (u.username LIKE ? OR u.employee_id LIKE ? OR e.full_name LIKE ?) ";
        }

        $query .= " ORDER BY FIELD(u.status, 'pending', 'active', 'blocked'), u.created_at DESC";

        if ($search !== '') {
            $stmt = $conn->prepare($query);
            $searchParam = "%$search%";
            $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        $users = [];
        while ($row = $result->fetch_assoc()) {
            // Extra guard: never expose root admin account to non-root users
            if (!$is_root && $row['username'] === $ROOT_ADMIN_ID) continue;

            if (!empty($row['permissions'])) {
                $row['permissions'] = json_decode($row['permissions'], true);
            } else {
                $row['permissions'] = null;
            }
            $users[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $users]);
        break;

    case 'update_permissions':
        include("../config/auth.php");
        if ($_SESSION['username'] !== get_root_admin_id() && $_SESSION['role'] !== 'super-admin' && !has_permission('manage_roles')) {
            echo json_encode(["status" => "error", "message" => "Unauthorized action."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['user_id'] ?? $data['id'];
        $requested_perms = $data['permissions'] ?? [];
        $ROOT_ADMIN_ID = get_root_admin_id();
        $is_root = ($_SESSION['username'] === $ROOT_ADMIN_ID);
        $caller_role = $_SESSION['role'] ?? 'user';

        // Fetch the target user's role and username for hierarchy check
        $target_stmt = $conn->prepare("SELECT role, username FROM users WHERE id = ?");
        $target_stmt->bind_param("i", $id);
        $target_stmt->execute();
        $target_user = $target_stmt->get_result()->fetch_assoc();
        $target_stmt->close();

        if (!$target_user) {
            echo json_encode(["status" => "error", "message" => "Target user not found."]);
            exit;
        }

        // Role-hierarchy guard: you cannot edit permissions of accounts at or above your level
        // Root admin: can edit anyone
        // Super-admin: can edit everyone except root admin
        // Admin: can only edit user/sub-admin accounts
        // User (with manage_roles perm): can only edit other user accounts
        if (!$is_root) {
            $target_role = $target_user['role'];
            $target_is_root = ($target_user['username'] === $ROOT_ADMIN_ID);
            if ($target_is_root) {
                echo json_encode(["status" => "error", "message" => "Permission denied: Cannot modify root admin."]);
                exit;
            }
            if ($caller_role === 'super-admin') {
                // Super-admin can edit anyone except root — already filtered above
            } elseif ($caller_role === 'admin') {
                if (in_array($target_role, ['super-admin', 'admin'])) {
                    echo json_encode(["status" => "error", "message" => "Permission denied: Admins cannot modify super-admin or other admin accounts."]);
                    exit;
                }
            } else {
                // user role with manage_roles permission — can only edit user accounts
                if ($target_role !== 'user') {
                    echo json_encode(["status" => "error", "message" => "Permission denied: You can only manage standard user accounts."]);
                    exit;
                }
            }
        }

        // Backend Enforcement: Users can only delegate permissions they ALREADY HAVE.
        // Root Super Admin is exempt from this check.
        if (!$is_root) {
            foreach ($requested_perms as $key => $value) {
                // Check if they are trying to enable a permission they don't have
                $is_enabled = ($value === true || $value === "true" || $value === 1 || $value === "1" || $value === "on");
                if ($is_enabled && !has_permission($key)) {
                    echo json_encode(["status" => "error", "message" => "Security Violation: You cannot grant the '$key' permission because you do not have it yourself."]);
                    exit;
                }
            }
        }

        $permissions_json = json_encode($requested_perms);
        
        $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE id = ?");
        $stmt->bind_param("si", $permissions_json, $id);

        if ($stmt->execute()) {
            // If the user is updating their own permissions, refresh their session
            if ($id == $_SESSION['user_id']) {
                $_SESSION['permissions'] = $requested_perms;
            }
            echo json_encode(["status" => "success", "message" => "Permissions updated successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Update failed."]);
        }
        $stmt->close();
        break;

    case 'update_role':
        include("../config/auth.php");
        check_auth();
        if (!has_permission('manage_actions')) {
            echo json_encode(["status" => "error", "message" => "Unauthorized: You don't have permission to manage user roles."]);
            exit;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['user_id'] ?? $data['id'];
        $new_role = $data['role'];

        // SECURITY: Only a Super Admin can promote someone to Super Admin
        if ($new_role === 'super-admin' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super-admin')) {
            echo json_encode(["status" => "error", "message" => "Security Violation: Only a Super Admin can promote a user to the Super Admin role."]);
            exit;
        }
        $ROOT_ADMIN_ID = get_root_admin_id();
        $is_root = ($_SESSION['username'] === $ROOT_ADMIN_ID);

        if ($id == $_SESSION['user_id']) {
            echo json_encode(["status" => "error", "message" => "You cannot change your own role."]);
            exit;
        }

        // Hierarchy Check
        $stmt = $conn->prepare("SELECT role, employee_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$target) {
            echo json_encode(["status" => "error", "message" => "Target user not found."]);
            exit;
        }

        // Hierarchy Logic:
        // 1. Root Admin (097727) can manage EVERYONE.
        // 2. Others cannot manage Super Admins.
        // 3. Admins can only manage Standard Users and Sub-Admins.
        if (!$is_root) {
            if ($target['role'] === 'super-admin') {
                echo json_encode(["status" => "error", "message" => "Hierarchy Violation: Only the Root Super Admin can manage other Super Admins."]);
                exit;
            }
            if ($_SESSION['role'] === 'admin' && !($target['role'] === 'user' || $target['role'] === 'sub-admin')) {
                echo json_encode(["status" => "error", "message" => "Hierarchy Violation: Admins can only manage Standard Users and Sub-Admins."]);
                exit;
            }
        }

        // Rule: Only Super Admin can promote someone TO Super Admin
        if ($new_role === 'super-admin' && $_SESSION['username'] !== get_root_admin_id()) {
            echo json_encode(["status" => "error", "message" => "Permission Denied: Only Super Admins can promote others to Super Admin status."]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User role updated to $new_role."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update role."]);
        }
        $stmt->close();
        break;

    case 'approve':
        include("../config/auth.php");
        check_auth();
        if (!has_permission('manage_users')) {
            echo json_encode(["status" => "error", "message" => "Permission denied: You cannot approve users."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User approved successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Approval error."]);
        }
        $stmt->close();
        break;

    case 'update_status':
        include("../config/auth.php");
        check_auth();
        if (!has_permission('manage_actions')) {
            echo json_encode(["status" => "error", "message" => "Unauthorized: You don't have permission to manage user status."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        $status = $data['status'];
        $ROOT_ADMIN_ID = get_root_admin_id();
        $is_root = ($_SESSION['username'] === $ROOT_ADMIN_ID);

        if ($id == $_SESSION['user_id']) {
            echo json_encode(["status" => "error", "message" => "Action not allowed on self."]);
            exit;
        }

        // Hierarchy Check
        $stmt = $conn->prepare("SELECT role, employee_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$is_root) {
            if ($target['role'] === 'super-admin') {
                echo json_encode(["status" => "error", "message" => "Hierarchy Violation: Only the Root Super Admin can manage other Super Admins."]);
                exit;
            }
            if ($_SESSION['role'] === 'admin' && !($target['role'] === 'user' || $target['role'] === 'sub-admin')) {
                echo json_encode(["status" => "error", "message" => "Hierarchy Violation: Admins can only block Standard Users and Sub-Admins."]);
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Status updated to $status."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Status update failed."]);
        }
        $stmt->close();
        break;

    case 'delete':
        include("../config/auth.php");
        check_auth();
        if (!has_permission('manage_actions')) {
            echo json_encode(["status" => "error", "message" => "Unauthorized: You don't have permission to delete users."]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        $ROOT_ADMIN_ID = get_root_admin_id();
        $is_root = ($_SESSION['username'] === $ROOT_ADMIN_ID);

        if ($id == $_SESSION['user_id']) {
            echo json_encode(["status" => "error", "message" => "Action not allowed on self."]);
            exit;
        }

        // Hierarchy Check
        $stmt = $conn->prepare("SELECT role, employee_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$is_root) {
            if ($target['role'] === 'super-admin') {
                echo json_encode(["status" => "error", "message" => "Hierarchy Violation: Only the Root Super Admin can manage other Super Admins."]);
                exit;
            }
            if ($_SESSION['role'] === 'admin' && !($target['role'] === 'user' || $target['role'] === 'sub-admin')) {
                echo json_encode(["status" => "error", "message" => "Hierarchy Violation: Admins can only delete Standard Users and Sub-Admins."]);
                exit;
            }
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User deleted successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Deletion failed."]);
        }
        $stmt->close();
        break;

    case 'get_profile':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["status" => "error", "message" => "Not logged in."]);
            exit;
        }
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT u.employee_id, u.username, u.role, u.profile_pic, e.* 
                                FROM users u 
                                LEFT JOIN employees e ON u.employee_id = e.employee_id 
                                WHERE u.id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        
        if (!$profile) {
            echo json_encode(["status" => "error", "message" => "User not found. Please log out and back in."]);
        } else {
            echo json_encode(["status" => "success", "data" => $profile]);
        }
        break;

    case 'update_profile_pic':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["status" => "error", "message" => "Not logged in."]);
            exit;
        }
        if (!isset($_FILES['profile_pic'])) {
            echo json_encode(["status" => "error", "message" => "No file uploaded."]);
            exit;
        }

        $target_dir = "../uploads/profile_pics/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
        $target_file = $target_dir . $_SESSION['user_id'] . "_" . time() . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $db_path = str_replace("../", "", $target_file);
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param("si", $db_path, $_SESSION['user_id']);
            $stmt->execute();
            echo json_encode(["status" => "success", "message" => "Profile picture updated!", "path" => $db_path]);
        } else {
            echo json_encode(["status" => "error", "message" => "Upload failed."]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}

$conn->close();
?>
