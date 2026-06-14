<?php
if (session_status() === PHP_SESSION_NONE) {
    if (isset($_COOKIE['PHPSESSID']) && !preg_match('/^[a-zA-Z0-9,-]{1,128}$/', $_COOKIE['PHPSESSID'])) {
        // If session ID is malformed (e.g. duplicated or contains illegal chars), clear it
        unset($_COOKIE['PHPSESSID']);
        setcookie('PHPSESSID', '', time() - 3600, '/');
    }
    @session_start();

    // --- Session Timeout Logic (1 Hour Inactivity) ---
    $timeout_duration = 3600; // 1 hour in seconds
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];
        if ($elapsed_time > $timeout_duration) {
            // Session expired
            session_unset();
            session_destroy();
            // Start a new session for error messaging if needed
            @session_start();
            $_SESSION['logout_reason'] = 'timeout';
        }
    }

    // Update last activity timestamp ONLY if not a background heartbeat
    if (!isset($_GET['nobump'])) {
        $_SESSION['last_activity'] = time();
    }

    // Update user_sessions table for multi-device tracking
    if (isset($_SESSION['user_id'])) {
        global $conn;
        if (!isset($conn)) include_once(__DIR__ . "/db.php");
        if (isset($conn)) {
            $u_id = $_SESSION['user_id'];
            $s_id = session_id();
            $u_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $u_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $u_ip = $_SERVER['HTTP_CLIENT_IP'];
            }
            $u_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            // Insert or Update the specific session record
            $stmt_sess = $conn->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_activity) 
                                        VALUES (?, ?, ?, ?, NOW()) 
                                        ON DUPLICATE KEY UPDATE last_activity = NOW(), ip_address = ?, user_agent = ?");
            $stmt_sess->bind_param("isssss", $u_id, $s_id, $u_ip, $u_agent, $u_ip, $u_agent);
            $stmt_sess->execute();
            $stmt_sess->close();

            // Also update the main users table for backward compatibility/legacy views
            $stmt_act = $conn->prepare("UPDATE users SET last_activity = NOW(), last_ip = ? WHERE id = ?");
            $stmt_act->bind_param("si", $u_ip, $u_id);
            $stmt_act->execute();
            $stmt_act->close();
        }
    }
}

// Prevent browser caching for all protected pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

function check_auth($allowed_roles = [])
{
    $script_path = $_SERVER['PHP_SELF'];
    $is_api = (strpos($script_path, '/api/') !== false);
    $is_in_subdirectory = $is_api || (strpos($script_path, '/view/') !== false);
    $root_path = $is_in_subdirectory ? '../' : '';

    if (!isset($_SESSION['user_id'])) {
        if ($is_api) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Session expired. Please login again.", "auth_failed" => true]);
            exit;
        }
        header("Location: " . $root_path . "login.php");
        exit;
    }

    // Refresh user data from DB to ensure real-time permissions/status updates
    global $conn;
    if (!isset($conn)) {
        include_once(__DIR__ . "/db.php");
    }

    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT role, permissions, status, session_version FROM users WHERE id = ?");
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // --- Global Logout / Session Version Check ---
            // If the DB version is higher than the session version, it means the password was changed 
            // on another device, and we should force a logout here.
            if (isset($_SESSION['session_version']) && $user['session_version'] > $_SESSION['session_version']) {
                @session_destroy();
                if ($is_api) {
                    http_response_code(401);
                    echo json_encode(["status" => "error", "message" => "Your session has been invalidated (password changed elsewhere). Please login again.", "auth_failed" => true]);
                    exit;
                }
                header("Location: " . $root_path . "login.php?error=session_invalidated");
                exit;
            }

            // Real-time status check
            if ($user['status'] === 'blocked') {
                @session_destroy();
                if ($is_api) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "message" => "Your account has been blocked.", "auth_failed" => true]);
                    exit;
                }
                header("Location: " . $root_path . "login.php?error=blocked");
                exit;
            }
            
            // Real-time role and permission updates
            $_SESSION['role'] = $user['role'];
            $_SESSION['permissions'] = $user['permissions'];
            $_SESSION['admin_logged_in'] = in_array($user['role'], ['admin', 'sub-admin', 'super-admin']);
            $_SESSION['session_version'] = $user['session_version'];
        } else {
            // User was deleted from DB
            @session_destroy();
            if ($is_api) {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Account no longer exists.", "auth_failed" => true]);
                exit;
            }
            header("Location: " . $root_path . "login.php?error=account_deleted");
            exit;
        }
        $stmt->close();
    }

    // Role-based check (legacy support)
    // Root Admin bypasses all role-based checks
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        if ($_SESSION['username'] !== get_root_admin_id()) {
            if ($is_api) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Permission denied for this action."]);
                exit;
            }
            header("Location: " . $root_path . "index.php");
            exit;
        }
    }
}

function get_root_admin_id() {
    if (isset($_SESSION['root_admin_id'])) {
        return $_SESSION['root_admin_id'];
    }
    global $conn;
    if (!isset($conn)) {
        include_once(__DIR__ . "/db.php");
    }
    $res = $conn->query("SELECT id FROM root_admins LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $_SESSION['root_admin_id'] = $row['id'];
        return $row['id'];
    }
    return '097727'; // Fallback
}

/**
 * Checks if the current user has a specific permission flag enabled.
 */
function has_permission($permission_name)
{
    $raw = $_SESSION['permissions'] ?? null;
    $permissions = [];
    if (is_string($raw)) {
        $permissions = json_decode($raw, true) ?? [];
    } elseif (is_array($raw)) {
        $permissions = $raw;
    }

    // 1. Root Admin ALWAYS has all powers (Safety Bypass)
    if (isset($_SESSION['username']) && $_SESSION['username'] === get_root_admin_id()) {
        return true;
    }

    // 2. If permission is EXPLICITLY set in the database, that is the FINAL authority.
    //    This handles both true AND false values — false means explicitly denied.
    if (array_key_exists($permission_name, $permissions)) {
        $val = $permissions[$permission_name];
        return ($val === true || $val === "true" || $val === 1 || $val === "1" || $val === "on");
    }

    // 3. Fallback: Super-Admin gets all permissions ONLY IF NO permissions have been
    //    explicitly configured for their account yet (empty DB permissions column).
    //    Once ANY permission is saved to DB, missing keys default to FALSE.
    //    This prevents newly added permission keys from being silently granted.
    $has_configured_perms = !empty($permissions);
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'super-admin' && !$has_configured_perms) {
        return true;
    }

    return false;
}
?>
