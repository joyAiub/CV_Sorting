<?php
include("config/auth.php");
check_auth(); // All roles allowed
include("config/db.php");
$is_admin = in_array($_SESSION['role'], ['admin', 'sub-admin', 'super-admin']);

// Handle Logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, last_activity = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        
        // Also delete this specific session for multi-device tracking
        $sess_stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ?");
        $curr_sess = session_id();
        $sess_stmt->bind_param("is", $_SESSION['user_id'], $curr_sess);
        $sess_stmt->execute();
    }
    setcookie("remember_me", "", time() - 3600, "/");
    session_destroy();
    header("Location: login.php");
    exit;
}

// Fetch user profile info if not in session
if (!isset($_SESSION['profile_pic'])) {
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($prof_pic);
    $stmt->fetch();
    $_SESSION['profile_pic'] = $prof_pic;
    $stmt->close();
}

// Handle Password Change
$msg = "";
$is_error = false;
if (isset($_POST['new_password']) && isset($_POST['current_password'])) {
    $current_pass_input = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed_pass);
    $stmt->fetch();
    $stmt->close();

    if ($hashed_pass && password_verify($current_pass_input, $hashed_pass)) {
        $new_hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_hashed_pass, $user_id);

        if ($update_stmt->execute()) {
            // Success: Also increment session_version to log out other devices
            $conn->query("UPDATE users SET session_version = session_version + 1 WHERE id = $user_id");
            
            // Get the new version for the CURRENT session so it stays logged in
            $v_res = $conn->query("SELECT session_version FROM users WHERE id = $user_id");
            if ($v_row = $v_res->fetch_assoc()) {
                $_SESSION['session_version'] = $v_row['session_version'];
            }

            $msg = "Password changed successfully!";
            $is_error = false;
        } else {
            $msg = "Error updating password.";
            $is_error = true;
        }
        $update_stmt->close();
    } else {
        $msg = "Current password incorrect. Change failed.";
        $is_error = true;
    }
}

// Fetch latest prompt
$latest_prompt = "";
$stmt = $conn->prepare("SELECT prompt_text FROM prompts ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$stmt->bind_result($latest_prompt);
$stmt->fetch();
$stmt->close();

// Session full name and employee id
if ((!isset($_SESSION['full_name']) || !isset($_SESSION['employee_id'])) && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT e.full_name, e.employee_id FROM users u JOIN employees e ON u.employee_id = e.employee_id WHERE u.id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($fullName, $empId);
    if ($stmt->fetch()) {
        $_SESSION['full_name'] = $fullName;
        $_SESSION['employee_id'] = $empId;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Job | CV Sorting System</title>
    <link rel="stylesheet" href="css/style.css?v=4.3">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <style>
        :root { 
            --zoom: 1;
            --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        }
        html { zoom: var(--zoom); }
        
        .container {
            width: 100% !important;
            max-width: none !important;
            margin: 64px 0 0 0 !important;
            padding: 12px !important;
            background: #f1f5f9;
            min-height: calc(100vh - 64px);
        }

        .card {
            background: white;
            border-radius: 12px !important;
            padding: 15px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
            border: 1px solid #e2e8f0 !important;
        }

        #jobTable {
            font-size: 0.8rem !important;
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 5px;
        }

        #jobTable thead tr {
            background: #f8fafc !important;
            border-bottom: 2px solid #e2e8f0;
        }

        #jobTable th {
            font-size: 0.7rem !important;
            text-transform: uppercase;
            font-weight: 800;
            color: #475569;
            letter-spacing: 0.05em;
            padding: 10px 8px !important;
            text-align: center !important;
        }

        #jobTable th:nth-child(2) { text-align: left !important; padding-left: 15px !important; }

        #jobTable tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s ease;
        }

        #jobTable tbody tr:hover {
            background-color: #f8fbff !important;
        }

        #jobTable td {
            padding: 6px 8px !important;
            vertical-align: middle !important;
            text-align: center !important;
            color: #334155;
        }

        .job-title-link {
            text-decoration: none;
            color: #0f172a;
            font-weight: 700;
            font-size: 0.85rem;
            line-height: 1.2;
            display: block;
            transition: color 0.2s;
        }
        .job-title-link:hover { color: #4f46e5; }

        /* Column Widths Optimized for Home Page */
        #jobTable th:nth-child(1), #jobTable td:nth-child(1) { width: 35px; }
        #jobTable th:nth-child(2), #jobTable td:nth-child(2) { text-align: left !important; min-width: 200px; width: 25%; }
        #jobTable th:nth-child(3), #jobTable td:nth-child(3) { width: 75px; }
        #jobTable th:nth-child(4), #jobTable td:nth-child(4) { width: 180px; }
        #jobTable th:nth-child(5), #jobTable td:nth-child(5) { width: 450px; text-align: left !important; }
        #jobTable th:nth-child(6), #jobTable td:nth-child(6) { width: 90px; }
        #jobTable th:nth-child(7), #jobTable td:nth-child(7) { width: 65px; }
        #jobTable th:nth-child(8), #jobTable td:nth-child(8) { width: 90px; font-size: 0.65rem; }
        #jobTable th:nth-child(9), #jobTable td:nth-child(9) { width: 65px; font-weight: 700; color: #0f172a; font-size: 0.65rem; }
        #jobTable th:nth-child(10), #jobTable td:nth-child(10) { width: 85px; }

        .action-btns {
            display: flex !important;
            gap: 4px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap !important;
        }

        .badge-jd {
            background: #f1f5f9;
            color: #64748b;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Roboto Mono', monospace;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid #e2e8f0;
        }

        .status-pill {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            margin-left: 6px;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0% { transform: scale(0.95); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); }
        }
        
        /* Task View Toggle styling */
        .task-view-btn {
            padding: 5px 14px !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            border-radius: 6px !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }
        .task-view-btn.active {
            background: white !important;
            color: #1e293b !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06) !important;
        }
        .task-view-btn:not(.active) {
            background: transparent !important;
            color: #64748b !important;
        }
        .task-view-btn:not(.active):hover {
            color: #1e293b !important;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <div class="top-nav">
        <div class="top-nav-left">
            <div class="sidebar-toggle" onclick="toggleSidebar()" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); color: white;">
                <span class="material-icons">menu</span>
            </div>
            <a href="index.php" class="nav-logo" style="display: flex; align-items: center; gap: 10px; color: white; text-decoration: none; font-weight: 700; font-size: 1.1rem; margin-left: 10px;">
                <span class="material-icons" style="color: #60a5fa;">auto_awesome_motion</span>
                CV SORTING
            </a>
        </div>
        <div class="top-nav-right">
            <div class="user-profile-nav" onclick="toggleUserDropdown(event)">
                <div class="user-info-text">
                    <span id="header-user-name" class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                    <span id="header-user-role" class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
                <div class="profile-img-container">
                    <?php if (!empty($_SESSION['profile_pic'])): ?>
                        <img id="headerProfilePic" src="<?php echo $_SESSION['profile_pic']; ?>" alt="Profile">
                    <?php else: ?>
                        <div id="headerProfileFallback" class="profile-icon-fallback">
                            <span class="material-icons">person</span>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="material-icons expand-icon">expand_more</span>
                
                <div id="userDropdown" class="dropdown-menu">
                    <a href="javascript:void(0)" onclick="toggleProfileModal()">
                        <span class="material-icons">account_circle</span> My Profile
                    </a>
                    <a href="javascript:void(0)" onclick="toggleChangePass()">
                        <span class="material-icons">lock</span> Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="?logout=1" class="logout-link">
                        <span class="material-icons">logout</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Root -->
    <div id="sidebar-root"></div>

    <div class="container">
        <?php if ($msg): ?>
            <?php $bgColor = $is_error ? '#fee2e2' : '#dcfce7';
            $textColor = $is_error ? '#991b1b' : '#166534';
            $borderColor = $is_error ? '#fecaca' : '#bbf7d0'; ?>
            <div id="statusMsg" style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 12px; border-radius: 10px; margin-bottom: 25px; text-align: center; border: 1px solid <?php echo $borderColor; ?>; font-weight: 500; font-size: 0.95rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <?php echo $msg; ?>
                <?php if ($is_error): ?>
                    <a href="javascript:void(0)" onclick="toggleChangePass(); document.getElementById('statusMsg').style.display='none';" style="color: <?php echo $textColor; ?>; font-weight: 700; margin-left: 10px; text-decoration: underline;">Retry</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: var(--primary); cursor: pointer;" onclick="window.location.href = window.location.pathname">JOB TASK LIST <span class="live-indicator" title="Live Sync Active"></span></h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <!-- Task View Selector (My Task / All Task) -->
                    <div id="task-view-selector" style="display: flex; background: #e2e8f0; padding: 3px; border-radius: 8px; gap: 2px; align-items: center; margin-right: 5px;">
                        <button id="btn-view-my-tasks" onclick="setTaskViewMode('my')" class="task-view-btn active" style="display: <?php echo has_permission('view_my_task') ? 'block' : 'none'; ?>;">
                            My Task
                        </button>
                        <button id="btn-view-all-tasks" onclick="setTaskViewMode('all')" class="task-view-btn" style="display: <?php echo has_permission('view_all_task') ? 'block' : 'none'; ?>;">
                            All Task
                        </button>
                    </div>

                    <button id="btn-add-task" onclick="toggleAddTask()" class="btn-primary" style="background: var(--primary); padding: 5px 15px; font-size: 0.9rem; display: <?php echo has_permission('add_task') ? 'flex' : 'none'; ?>; align-items: center; gap: 5px; border: 1px solid white;">
                        <span class="material-icons" style="font-size: 18px;">add</span> TASK
                    </button>
                        <button id="btn-trigger-screening" class="btn-primary" style="background: #10b981; padding: 5px 20px; font-size: 0.9rem; display: <?php echo has_permission('trigger_screening') ? 'flex' : 'none'; ?>; align-items: center; gap: 5px; border: 1px solid white;">
                            <span class="material-icons" style="font-size: 16px;">play_arrow</span> Start CV Screening
                        </button>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <button onclick="toggleFilterBar(event)" class="btn-secondary" style="display: flex; align-items: center; gap: 5px; padding: 5px 14px; font-size: 0.85rem; background: #fff; border: 1px solid var(--border); color: #64748b; border-radius: 8px; flex-shrink: 0;">
                    <span class="material-icons" style="font-size: 18px;">filter_list</span>
                    Filter
                </button>
                
                <div id="filterBar" class="filter-container-horizontal" style="display: none; margin-bottom: 0; flex-grow: 1;">
                    <div class="filter-group-inline">
                        <label>Search</label>
                        <input type="text" id="filterJdId" placeholder="Title, ID, Task#..." style="width: 150px;">
                    </div>
                    <div class="filter-group-inline">
                        <label>Status</label>
                        <select id="filterStatus" style="width: 130px;">
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="filter-group-inline">
                        <label>User</label>
                        <input type="text" id="filterCreatedBy" placeholder="Created by..." style="width: 120px;">
                    </div>
                    <div style="margin-left: auto; display: flex; gap: 10px;">
                        <button onclick="clearJobFilters()" style="padding: 6px 12px; background: #e2e8f0; border: none; border-radius: 6px; color: #475569; font-size: 0.8rem; font-weight: 600; cursor: pointer;">Clear</button>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table id="jobTable">
                    <thead>
                        <tr style="background: #f1f5f9;">
                            <th style="width: 35px; text-align: center;">SL</th>
                            <th class="sortable" data-sort="job_title" onclick="handleJobSort('job_title')" style="text-align: left; padding-left: 15px; cursor: pointer;">
                                Job Title <span class="sort-icon">↕</span>
                            </th>
                            <th class="sortable" data-sort="jd_id" onclick="handleJobSort('jd_id')" style="width: 75px; text-align: center; cursor: pointer;">
                                JD ID <span class="sort-icon">↕</span>
                            </th>
                            <th style="width: 180px; text-align: center;">Concern</th>
                            <th style="width: 450px; text-align: center;">Mand. Req</th>
                            <th class="sortable" data-sort="status" onclick="handleJobSort('status')" style="width: 90px; text-align: center; cursor: pointer;">
                                Status <span class="sort-icon">↕</span>
                            </th>
                            <th class="sortable" data-sort="total_candidate" onclick="handleJobSort('total_candidate')" style="width: 65px; text-align: center; cursor: pointer;">
                                Screened CV <span class="sort-icon">↕</span>
                            </th>
                            <th class="sortable" data-sort="created_at" onclick="handleJobSort('created_at')" style="width: 90px; text-align: center; cursor: pointer;">
                                Created At <span class="sort-icon">↕</span>
                            </th>
                            <th class="sortable" data-sort="task_no" onclick="handleJobSort('task_no')" style="width: 65px; text-align: center; cursor: pointer;">
                                Task No <span class="sort-icon">↕</span>
                            </th>
                            <th style="width: 85px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="jobList">
                        <tr><td colspan="10" style="text-align:center; padding: 30px; color: #888;">Loading job list...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals Section -->
    <!-- Profile Modal -->

    <!-- Modals (Reusable) -->
    <?php include("includes/modals.php"); ?>

    <script>
        window.canManageUsers = <?php echo has_permission('manage_users') ? 'true' : 'false'; ?>;
        window.canManageRoles = <?php echo has_permission('manage_roles') ? 'true' : 'false'; ?>;
        window.canManageActions = <?php echo has_permission('manage_actions') ? 'true' : 'false'; ?>;
        window.rootAdminId = '<?php include_once("config/auth.php"); echo get_root_admin_id(); ?>';
        window.isSuperAdmin = <?php echo (isset($_SESSION['username']) && ($_SESSION['username'] === get_root_admin_id() || $_SESSION['role'] === 'super-admin')) ? 'true' : 'false'; ?>;
        window.canAccessChat = <?php echo has_permission('access_chat') ? 'true' : 'false'; ?>;
        window.currentUserPermissions = <?php echo json_encode($_SESSION['permissions'] ?? []); ?>;
        window.currentUser = {
            db_id: '<?php echo $_SESSION['user_id'] ?? ''; ?>',
            id: '<?php echo $_SESSION['username'] ?? ''; ?>',
            role: '<?php echo $_SESSION['role'] ?? ''; ?>'
        };

        // Real-time Auth & Permission Guard
        let isRedirecting = false;
        async function syncAuthPermissions() {
            if (isRedirecting) return;
            try {
                // Add cache buster to prevent browser caching of auth/perms response
                const response = await fetch(`api/auth_check_api.php?nobump=1&t=${Date.now()}`);
                if (response.status === 401 || response.status === 403) {
                    isRedirecting = true;
                    window.location.href = 'login.php';
                    return;
                }

                const data = await response.json();
                if (data.status === 'success') {
                    const perms = data.permissions || {};
                    const role = data.role;
                    
                    // Update Role in Header
                    const roleEl = document.getElementById('header-user-role');
                    if (roleEl) roleEl.innerText = role.charAt(0).toUpperCase() + role.slice(1);

                    // Sync UI Visibility helper
                    const syncElement = (id, permission, displayType = '') => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        
                        // Root Admin bypass or explicit permission check
                        const hasPerm = (data.username === data.root_admin_id) || 
                                      (perms[permission] === true || perms[permission] === "true" || 
                                       perms[permission] === 1 || perms[permission] === "1" || perms[permission] === "on");
                        
                        el.style.display = hasPerm ? displayType : 'none';
                    };

                    // Sync Sidebar items
                    syncElement('nav-tasks', 'manage_tasks');
                    const hasUserAccess = (data.username === data.root_admin_id) || (perms['manage_users'] === true || perms['manage_users'] === 'true' || perms['manage_users'] === 1 || perms['manage_users'] === 'on') || (perms['create_user'] === true || perms['create_user'] === 'true' || perms['create_user'] === 1 || perms['create_user'] === 'on'); if (document.getElementById('nav-manage-users')) { document.getElementById('nav-manage-users').style.display = hasUserAccess ? '' : 'none'; }
                    syncElement('nav-db-control', 'db_control');
                    syncElement('nav-manage-employees', 'manage_employees');
                    syncElement('nav-manage-statuses', 'manage_statuses');
                    syncElement('nav-manage-sources', 'manage_sources');
                    syncElement('nav-manage-rpa', 'manage_rpa');
                    syncElement('nav-manage-task-limits', 'manage_task_limits');
                    syncElement('nav-server-allocation', 'manage_server_allocation');
                    syncElement('nav-user-activity', 'view_user_activity');
                    
                    // Sync Action buttons
                    syncElement('btn-add-task', 'add_task', 'flex');
                    syncElement('btn-trigger-screening', 'trigger_screening', 'flex');
                    
                    // Sync Task View Selector buttons
                    syncElement('btn-view-all-tasks', 'view_all_task', 'block');
                    syncElement('btn-view-my-tasks', 'view_my_task', 'block');
                    
                    const containerView = document.getElementById('task-view-selector');
                    if (containerView) {
                        const showAll = (data.username === data.root_admin_id) || 
                                       (perms['view_all_task'] === true || perms['view_all_task'] === "true" || 
                                        perms['view_all_task'] === 1 || perms['view_all_task'] === "1" || perms['view_all_task'] === "on");
                        const showMy = (data.username === data.root_admin_id) || 
                                      (perms['view_my_task'] === true || perms['view_my_task'] === "true" || 
                                       perms['view_my_task'] === 1 || perms['view_my_task'] === "1" || perms['view_my_task'] === "on");
                        containerView.style.display = (showAll || showMy) ? 'flex' : 'none';
                    }
                    
                    // Sync AI Chat Widget
                    syncElement('aiChatWidget', 'access_chat', 'block');
                    
                    // Global flags for other scripts
                    window.currentUserPermissions = perms;
                    window.canManageUsers = hasUserAccess;
                    window.canManageRoles = (data.username === data.root_admin_id) || (perms['manage_roles'] === true || perms['manage_roles'] === "on" || perms['manage_roles'] === "1" || perms['manage_roles'] === 1);
                    window.canManageActions = (data.username === data.root_admin_id) || (perms['manage_actions'] === true || perms['manage_actions'] === "on" || perms['manage_actions'] === "1" || perms['manage_actions'] === 1);
                    window.isSuperAdmin = (data.username === data.root_admin_id || data.role === 'super-admin');

                    // Sync User Management Modal Button (REAL-TIME PERMISSION ENFORCEMENT)
                    const canCreate = (data.username === data.root_admin_id) || (data.role === 'super-admin') || 
                                     (perms['create_user'] === true || perms['create_user'] === 'true' || 
                                      perms['create_user'] === 1 || perms['create_user'] === 'on');
                    const modalCreateBtn = document.getElementById('toggleUserFormBtn');
                    if (modalCreateBtn) { modalCreateBtn.style.display = canCreate ? 'flex' : 'none'; }
                }
            } catch (err) {
                console.warn("Real-time auth check failed:", err);
            }
        }

        // Run once immediately, then every 3 seconds
        syncAuthPermissions();
        setInterval(syncAuthPermissions, 3000);

        // --- Deep Linking for Modals ---
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const modal = urlParams.get('modal');
            if (modal) {
                // Short delay to ensure script.js is fully initialized
                setTimeout(() => {
                    switch(modal) {
                        case 'manage_users': if (typeof toggleUserManager === 'function') toggleUserManager(); break;
                        case 'db_control': if (typeof toggleDbControl === 'function') toggleDbControl(); break;
                        case 'manage_employees': if (typeof toggleEmployeeManager === 'function') toggleEmployeeManager(); break;
                        case 'manage_statuses': if (typeof toggleStatusManager === 'function') toggleStatusManager(); break;
                        case 'manage_sources': if (typeof toggleSourceManager === 'function') toggleSourceManager(); break;
                        case 'manage_rpa': if (typeof toggleRpaConfig === 'function') toggleRpaConfig(); break;
                        case 'manage_task_limits': if (typeof toggleTaskLimitsModal === 'function') toggleTaskLimitsModal(); break;
                        case 'server_allocation': if (typeof toggleServerAllocation === 'function') toggleServerAllocation(); break;
                        case 'user_activity': if (typeof toggleUserActivity === 'function') toggleUserActivity(); break;
                    }
                }, 500);
            }
        });
    </script>
    <script src="js/script_test.js?v=<?php echo time(); ?>"></script>
    <script type="text/babel">
        const { useState, useEffect } = React;

        // --- Icons Shorthand ---
        const Icon = ({ name, className = "" }) => {
            return <i className={`material-icons ${className}`} style={{ fontSize: 'inherit' }}>{name}</i>;
        };

        // Shared Sidebar Component
        <?php include 'includes/sidebar_modern.php'; ?>

        const SidebarRoot = () => {
            const [showSidebar, setShowSidebar] = useState(false);
            const [zoom, setZoom] = useState(parseFloat(localStorage.getItem('zoom')) || 1.0);

            useEffect(() => {
                // Override legacy toggleSidebar
                window.toggleSidebar = () => setShowSidebar(prev => !prev);
                
                // Sync zoom with existing CSS variable
                document.documentElement.style.setProperty('--zoom', zoom);
                const label = document.getElementById('zoom-percent');
                if (label) label.innerText = Math.round(zoom * 100) + '%';
            }, [zoom]);

            const adjustZoom = (delta) => {
                const newZoom = Math.max(0.5, Math.min(1.5, zoom + delta));
                setZoom(newZoom);
                localStorage.setItem('zoom', newZoom);
            };

            return (
                <Sidebar 
                    showSidebar={showSidebar}
                    setShowSidebar={setShowSidebar}
                    isAdmin={<?php echo $is_admin ? 'true' : 'false'; ?>}
                    zoom={zoom}
                    adjustZoom={adjustZoom}
                    isPublic={false}
                />
            );
        };

        const root = ReactDOM.createRoot(document.getElementById('sidebar-root'));
        root.render(<SidebarRoot />);
    </script>
</body>
</html>
