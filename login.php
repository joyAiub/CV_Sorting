<?php
if (isset($_COOKIE['PHPSESSID']) && !preg_match('/^[a-zA-Z0-9,-]{1,128}$/', $_COOKIE['PHPSESSID'])) {
    unset($_COOKIE['PHPSESSID']);
    setcookie('PHPSESSID', '', time() - 3600, '/');
}
@session_start();
include("config/db.php");

$error = "";

// Handle redirection errors (Real-time updates)
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'blocked') $error = "Your account has been blocked by an administrator.";
    if ($_GET['error'] === 'account_deleted') $error = "Your account was not found or has been deleted.";
}

// Check for session timeout
if (isset($_SESSION['logout_reason']) && $_SESSION['logout_reason'] === 'timeout') {
    $error = "Your session has expired due to 1 hour of inactivity. Please login again.";
    unset($_SESSION['logout_reason']);
}

// Auto-login via "Remember Me" Cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $conn->prepare("SELECT id, username, role, permissions, session_version FROM users WHERE remember_token = ? AND status != 'blocked'");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['admin_logged_in'] = in_array($user['role'], ['admin', 'sub-admin', 'super-admin']);
        $_SESSION['session_version'] = $user['session_version'];

        header("Location: index.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT u.id, u.username, u.password, u.role, u.status, u.profile_pic, u.permissions, u.session_version, e.full_name 
                                FROM users u 
                                LEFT JOIN employees e ON u.employee_id = e.employee_id 
                                WHERE u.username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] === 'blocked') {
                $error = "Your account is blocked. Contact administrator.";
            }
            else if ($user['status'] === 'pending') {
                $error = "Your registration is pending Admin approval.";
            }
            else if (password_verify($password, $user['password'])) {
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['employee_id'] = $user['employee_id']; // Added this line
                $_SESSION['role'] = $user['role'];
                $_SESSION['permissions'] = $user['permissions'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $_SESSION['admin_logged_in'] = in_array($user['role'], ['admin', 'sub-admin', 'super-admin']);
                $_SESSION['session_version'] = $user['session_version'];

                // Remember Me Logic
                if (isset($_POST['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt->bind_param("si", $token, $user['id']);
                    $stmt->execute();
                    setcookie("remember_me", $token, time() + (30 * 24 * 60 * 60), "/"); // 30 Days
                    setcookie("remembered_username", $user['username'], time() + (30 * 24 * 60 * 60), "/"); // 30 Days
                }
                else {
                    // Clear the remembered username if they login without checking remember_me
                    setcookie("remembered_username", "", time() - 3600, "/");
                }

                // Redirect based on role
                header("Location: index.php");
                exit;
            }
            else {
                $error = "Invalid username or password.";
            }
        }
        else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
    else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CV Sorting System</title>
    <link rel="stylesheet" href="css/style.css?v=3.6">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        .login-header p {
            color: #64748b;
            font-size: 0.9rem;
        }
        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            text-align: center;
            border: 1px solid #fecaca;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .btn-login {
            width: 100%;
            background: var(--primary);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }
        .btn-login:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>
<body>
    <img src="images/company logo/MGI_Logo.png" alt="Company Logo" class="company-logo">
    <div class="login-card">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Please enter your credentials to login</p>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php
endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" value="<?php echo isset($_COOKIE['remembered_username']) ? htmlspecialchars($_COOKIE['remembered_username']) : ''; ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-top: -10px;">
                <input type="checkbox" name="remember_me" id="remember_me" style="width: auto;" <?php echo isset($_COOKIE['remembered_username']) ? 'checked' : ''; ?>>
                <label for="remember_me" style="margin: 0; cursor: pointer; font-weight: 500;">Remember Me</label>
            </div>
            <button type="submit" class="btn-login">Login to System</button>
            <div style="display: flex; justify-content: space-between; margin-top: 15px; font-size: 0.85rem;">
                <a href="javascript:void(0)" onclick="toggleForgotModal()" style="color: #64748b; text-decoration: none;">Forgot Password?</a>
                <div>
                    Don't have an account? <a href="javascript:void(0)" onclick="toggleRegisterModal()" style="color: var(--primary); font-weight: 600; text-decoration: none;">Register Now</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 450px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 style="margin: 0; color: var(--primary);">Forgot Password</h2>
                <span class="material-icons" onclick="toggleForgotModal()" style="cursor: pointer; color: #94a3b8;">close</span>
            </div>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px;">Enter your User ID. Admins can reset via mail.</p>
            <form id="forgotForm">
                <div class="form-group">
                    <label>User ID (Employee ID)</label>
                    <input type="text" id="forgotUserId" placeholder="Enter your User ID" required>
                </div>
                <button type="submit" class="btn-login" style="margin-top: 10px;">Verify & Request Reset</button>
            </form>
            <div id="forgotMsg" style="margin-top: 20px; text-align: center; font-size: 0.9rem; display: none; padding: 15px; border-radius: 12px; line-height: 1.5; word-break: break-word; overflow-wrap: break-word;"></div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div id="registerModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 450px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 style="margin: 0; color: var(--primary);">User Registration</h2>
                <span class="material-icons" onclick="toggleRegisterModal()" style="cursor: pointer; color: #94a3b8;">close</span>
            </div>
            <form id="registerForm">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" id="regEmpId" placeholder="Enter your MGI Employee ID" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="regPassword" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" id="regConfirmPassword" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-login" style="margin-top: 10px;">Submit Registration</button>
            </form>
            <div id="regMsg" style="margin-top: 15px; text-align: center; font-size: 0.85rem; display: none; padding: 10px; border-radius: 8px;"></div>
        </div>
    </div>

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script>
        function toggleForgotModal() {
            const modal = document.getElementById('forgotModal');
            modal.style.display = (modal.style.display === 'none' || modal.style.display === '') ? 'flex' : 'none';
            if (modal.style.display === 'none') {
                document.getElementById('forgotForm').reset();
                if (document.getElementById('forgotMsg')) {
                    document.getElementById('forgotMsg').style.display = 'none';
                }
            }
        }

        function toggleRegisterModal() {
            const modal = document.getElementById('registerModal');
            modal.style.display = (modal.style.display === 'none' || modal.style.display === '') ? 'flex' : 'none';
            if (modal.style.display === 'none') {
                document.getElementById('registerForm').reset();
                document.getElementById('regMsg').style.display = 'none';
            }
        }

        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const empId = document.getElementById('regEmpId').value;
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('regConfirmPassword').value;
            const msgDiv = document.getElementById('regMsg');

            if (password !== confirmPassword) {
                msgDiv.innerText = "Passwords do not match!";
                msgDiv.style.background = "#fee2e2";
                msgDiv.style.color = "#dc2626";
                msgDiv.style.display = "block";
                return;
            }

            try {
                const response = await fetch('api/user_api.php?action=register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ employee_id: empId, password: password })
                });
                const result = await response.json();
                
                msgDiv.innerText = result.message;
                msgDiv.style.background = result.status === 'success' ? "#dcfce7" : "#fee2e2";
                msgDiv.style.color = result.status === 'success' ? "#166534" : "#dc2626";
                msgDiv.style.display = "block";

                if (result.status === 'success') {
                    setTimeout(() => toggleRegisterModal(), 3000);
                }
            } catch (error) {
                msgDiv.innerText = "Connection error. Please try again.";
                msgDiv.style.background = "#fee2e2";
                msgDiv.style.color = "#dc2626";
                msgDiv.style.display = "block";
            }
        });

        document.getElementById('forgotForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const userId = document.getElementById('forgotUserId').value;
            const msgDiv = document.getElementById('forgotMsg');

            msgDiv.innerText = "Processing...";
            msgDiv.style.background = "#f1f5f9";
            msgDiv.style.color = "#64748b";
            msgDiv.style.display = "block";

            try {
                const response = await fetch('api/forgot_password_api.php?action=request_reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ employee_id: userId })
                });
                const result = await response.json();
                
                msgDiv.innerText = result.message;
                msgDiv.style.background = result.status === 'success' ? "#dcfce7" : "#fee2e2";
                msgDiv.style.color = result.status === 'success' ? "#166534" : "#dc2626";
                msgDiv.style.display = "block";
            } catch (error) {
                msgDiv.innerText = "Connection error. Please try again.";
                msgDiv.style.background = "#fee2e2";
                msgDiv.style.color = "#dc2626";
                msgDiv.style.display = "block";
            }
        });
    </script>
</body>
</html>
