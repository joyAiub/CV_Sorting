<?php
header("Content-Type: application/json");
include("../config/db.php");
include("../config/mail_sender.php");

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'request_reset':
        $data = json_decode(file_get_contents("php://input"), true);
        $employee_id = trim($data['employee_id'] ?? '');

        if (empty($employee_id)) {
            echo json_encode(["status" => "error", "message" => "Please enter your User ID."]);
            exit;
        }

        // Fetch user, their role, and permissions
        $stmt = $conn->prepare("SELECT u.id, u.username, u.role, u.status, u.permissions, e.email, e.full_name 
                                FROM users u 
                                JOIN employees e ON u.employee_id = e.employee_id 
                                WHERE u.employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "User ID not found."]);
            exit;
        }

        $user = $result->fetch_assoc();
        
        // Permission Check
        $perms = json_decode($user['permissions'] ?? '{}', true);
        $can_reset = false;
        
        // 1. Root Admin always allowed
        include_once("../config/auth.php");
        if ($user['username'] === get_root_admin_id()) {
            $can_reset = true;
        } 
        // 2. Explicit permission check
        else {
            $val = $perms['reset_password'] ?? null;
            if ($val === true || $val === "true" || $val === 1 || $val === "1" || $val === "on") {
                $can_reset = true;
            }
        }

        if (!$can_reset) {
            echo json_encode(["status" => "error", "message" => "Please contact with admin for password reset."]);
            exit;
        }

        if (empty($user['email'])) {
            echo json_encode(["status" => "error", "message" => "No email found for this account. Please contact Super Admin."]);
            exit;
        }

        // Generate Token
        $token = bin2hex(random_bytes(32));

        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $update_stmt->bind_param("si", $token, $user['id']);
        
        if ($update_stmt->execute()) {
            // Fetch SMTP config
            $mail_res = $conn->query("SELECT * FROM mail_config LIMIT 1");
            $mail_config = $mail_res->fetch_assoc();

            if (!$mail_config) {
                echo json_encode(["status" => "error", "message" => "Mailing system not configured."]);
                exit;
            }

            // Send Email using SMTP
            // Sanitize base path for Windows environments (ensure forward slashes)
            $base_path = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'], 2));
            if ($base_path === '/') $base_path = '';
            $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $base_path . "/view/reset_password.php?token=" . $token;
            
            $to = $user['email'];
            $subject = "Password Reset Request - CV Sorting System";
            
            // Send as HTML Email for better clickability
            $message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                    <h2 style='color: #4f46e5;'>Password Reset Request</h2>
                    <p>Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                    <p>We received a request to reset your password for the CV Sorting System.</p>
                    <p style='margin: 30px 0;'>
                        <a href='" . $reset_link . "' style='background: #4f46e5; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset My Password</a>
                    </p>
                    <p>Or click this link: <br><a href='" . $reset_link . "'>" . $reset_link . "</a></p>
                    <p style='color: #64748b; font-size: 0.9rem;'>This link will expire in 1 hour.</p>
                    <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                    <p style='font-size: 0.8rem; color: #94a3b8;'>If you did not request this, please ignore this email.</p>
                </div>
            ";
            
            $mailer = new SmtpMailer($mail_config);
            if ($mailer->send($to, $subject, $message)) {
                echo json_encode(["status" => "success", "message" => "A password reset link has been sent to your registered email: " . substr($user['email'], 0, 3) . "..." . substr($user['email'], strpos($user['email'], "@"))]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to send email. Please check SMTP configuration."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to generate reset token."]);
        }
        break;

    case 'verify_token':
        $token = $_GET['token'] ?? '';
        if (empty($token)) {
            echo json_encode(["status" => "error", "message" => "Invalid token."]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 1) {
            echo json_encode(["status" => "success", "message" => "Token is valid."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Token is invalid or has expired."]);
        }
        break;

    case 'reset_password':
        $data = json_decode(file_get_contents("php://input"), true);
        $token = $data['token'] ?? '';
        $new_password = $data['password'] ?? '';

        if (empty($token) || empty($new_password)) {
            echo json_encode(["status" => "error", "message" => "Missing required data."]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL, session_version = session_version + 1 WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($update_stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Password has been reset successfully! You can now log in."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to update password."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid or expired token."]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}

$conn->close();
?>
