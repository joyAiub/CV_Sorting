<?php
header("Content-Type: application/json");
include("../config/db.php");
include("../config/auth.php");

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Shortcut: ?action=get_global allows prefetching without a POST body
    if (($_GET['action'] ?? '') === 'get_global') {
        $stmt = $conn->prepare("SELECT column_config FROM user_column_templates WHERE user_id = 0 AND template_name = 'GLOBAL_DEFAULT' LIMIT 1");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode(["status" => "success", "data" => json_decode($row['column_config'], true)]);
        } else {
            echo json_encode(["status" => "error", "message" => "No global template found"]);
        }
        exit;
    }
    $stmt = $conn->prepare("SELECT id, template_name, column_config, is_default FROM user_column_templates WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $row['column_config'] = json_decode($row['column_config'], true);
        $templates[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $templates]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input['action'] ?? 'save';

    if ($action === 'save') {
        $name = $input['template_name'] ?? 'Default';
        $config = json_encode($input['column_config']);
        $is_default = isset($input['is_default']) ? ($input['is_default'] ? 1 : 0) : 0;
        $is_global = $input['is_global'] ?? false;

        // Check if saving global
        $role = $_SESSION['role'] ?? 'user';
        $username = $_SESSION['username'] ?? '';
        $is_admin = ($role === 'super-admin' || $username === '097727'); // Simplified admin check matching auth.php

        if ($is_global && !$is_admin) {
            echo json_encode(["status" => "error", "message" => "Permission denied for global save"]);
            exit;
        }

        $target_user_id = $is_global ? "0" : $user_id; // Use "0" for global templates

        if ($is_default && !$is_global) {
            $reset = $conn->prepare("UPDATE user_column_templates SET is_default = 0 WHERE user_id = ?");
            $reset->bind_param("s", $user_id);
            $reset->execute();
        }

        $stmt = $conn->prepare("INSERT INTO user_column_templates (user_id, template_name, column_config, is_default) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE column_config = VALUES(column_config), is_default = VALUES(is_default)");
        $stmt->bind_param("ssss", $target_user_id, $name, $config, $is_default);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Template saved"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
    } elseif ($action === 'get_global') {
        $stmt = $conn->prepare("SELECT column_config FROM user_column_templates WHERE user_id = 0 AND template_name = 'GLOBAL_DEFAULT' LIMIT 1");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode(["status" => "success", "data" => json_decode($row['column_config'], true)]);
        } else {
            echo json_encode(["status" => "error", "message" => "No global template found"]);
        }
    } elseif ($action === 'delete') {
        $id = $input['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM user_column_templates WHERE id = ? AND user_id = ?");
        $stmt->bind_param("is", $id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Template deleted"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to delete"]);
        }
    }
    exit;
}
