<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | CV Sorting System</title>
    <link rel="stylesheet" href="../css/style.css?v=6.4">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            background: #f1f5f9;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .reset-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .reset-card h2 {
            margin-bottom: 10px;
            color: #1e293b;
        }
        .reset-card p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
        }
        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s;
        }
        .input-group input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .btn-reset {
            background: #6366f1;
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-reset:hover {
            background: #4f46e5;
        }
        #message {
            margin-top: 20px;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            display: none;
        }
        .success { background: #ecfdf5; color: #065f46; }
        .error { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>

<div class="reset-card" id="formContainer">
    <h2>Set New Password</h2>
    <p>Please enter your new password below.</p>
    
    <div class="input-group">
        <label>New Password</label>
        <input type="password" id="newPassword" placeholder="Minimum 6 characters">
    </div>
    
    <div class="input-group">
        <label>Confirm Password</label>
        <input type="password" id="confirmPassword" placeholder="Confirm your password">
    </div>
    
    <button class="btn-reset" onclick="handleReset()">Update Password</button>
    <div id="message"></div>
</div>

<script>
const urlParams = new URL(window.location.href).searchParams;
const token = urlParams.get('token');

async function verifyToken() {
    if (!token) {
        showError("Missing reset token.");
        document.querySelector('.btn-reset').disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`../api/forgot_password_api.php?action=verify_token&token=${token}`);
        const result = await response.json();
        if (result.status !== 'success') {
            showError(result.message);
            document.querySelector('.btn-reset').disabled = true;
        }
    } catch (e) {
        showError("Failed to verify token.");
    }
}

async function handleReset() {
    const password = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    const msgDiv = document.getElementById('message');
    
    if (password.length < 4) {
        showError("Password is too short.");
        return;
    }
    
    if (password !== confirm) {
        showError("Passwords do not match.");
        return;
    }
    
    try {
        const response = await fetch('../api/forgot_password_api.php?action=reset_password', {
            method: 'POST',
            body: JSON.stringify({ token, password })
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            msgDiv.className = 'success';
            msgDiv.textContent = result.message;
            msgDiv.style.display = 'block';
            setTimeout(() => {
                window.location.href = '../login.php';
            }, 3000);
        } else {
            showError(result.message);
        }
    } catch (e) {
        showError("An error occurred. Please try again.");
    }
}

function showError(msg) {
    const msgDiv = document.getElementById('message');
    msgDiv.className = 'error';
    msgDiv.textContent = msg;
    msgDiv.style.display = 'block';
}

verifyToken();
</script>

</body>
</html>
