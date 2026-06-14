<?php
/**
 * migrate_permissions.php
 * 
 * Initializes the 'permissions' column for all existing users based on their current roles.
 */

header("Content-Type: application/json");
include("../config/db.php");

// Define role-to-permission mappings
$role_permissions = [
    'super-admin' => [
        'manage_users' => 1,
        'manage_employees' => 1,
        'manage_statuses' => 1,
        'manage_rpa' => 1,
        'add_task' => 1,
        'trigger_screening' => 1,
        'export_data' => 1,
        'manage_roles' => 1,
        'view_my_task' => 1,
        'view_all_task' => 1
    ],
    'admin' => [
        'manage_users' => 1,
        'manage_employees' => 1,
        'manage_statuses' => 1,
        'manage_rpa' => 1,
        'add_task' => 1,
        'trigger_screening' => 1,
        'export_data' => 1,
        'manage_roles' => 0,
        'view_my_task' => 1,
        'view_all_task' => 1
    ],
    'sub-admin' => [
        'manage_users' => 0,
        'manage_employees' => 0,
        'manage_statuses' => 1,
        'manage_rpa' => 1,
        'add_task' => 1,
        'trigger_screening' => 1,
        'export_data' => 1,
        'manage_roles' => 0,
        'view_my_task' => 1,
        'view_all_task' => 1
    ],
    'user' => [
        'manage_users' => 0,
        'manage_employees' => 0,
        'manage_statuses' => 0,
        'manage_rpa' => 0,
        'add_task' => 0,
        'trigger_screening' => 0,
        'export_data' => 0,
        'manage_roles' => 0,
        'view_my_task' => 1,
        'view_all_task' => 0
    ]
];

$users_processed = 0;
$result = $conn->query("SELECT id, role FROM users");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $role = $row['role'];
        $id = $row['id'];
        
        $perms = isset($role_permissions[$role]) ? $role_permissions[$role] : $role_permissions['user'];
        $json_perms = json_encode($perms);
        
        $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE id = ?");
        $stmt->bind_param("si", $json_perms, $id);
        $stmt->execute();
        $users_processed++;
        $stmt->close();
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "Permissions migrated successfully for $users_processed users."
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $conn->error
    ]);
}

$conn->close();
?>
