<?php
include("../config/auth.php");
include("../config/db.php");

// Public access logic: allow viewing if jd_id is present, even without login
$is_public = false;
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['jd_id']) && !empty($_GET['jd_id'])) {
        $stmt_v = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
        $stmt_v->bind_param("s", $_GET['jd_id']);
        $stmt_v->execute();
        $res_v = $stmt_v->get_result();
        if ($res_v->num_rows > 0) {
            $is_public = true;
        } else {
            check_auth();
        }
        $stmt_v->close();
    } else {
        check_auth(); 
    }
}

// Fetch Job Details for Concern Person Info
$jd_id = $_GET['jd_id'] ?? '';
$job_details = null;
if (!empty($jd_id)) {
    $stmt = $conn->prepare("SELECT * FROM Job_List WHERE jd_id = ?");
    $stmt->bind_param("s", $jd_id);
    $stmt->execute();
    $job_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'sub-admin', 'super-admin']);

// Fetch user profile info if not in session and NOT public
if (!$is_public && !isset($_SESSION['profile_pic'])) {
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($prof_pic);
    $stmt->fetch();
    $_SESSION['profile_pic'] = $prof_pic;
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
    <title>Candidate Dashboard | CV Sorting</title>
    <link rel="stylesheet" href="../css/style.css?v=6.4">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* High-Density Dashboard Redesign - FIXED ZOOM ADAPTATION */
        :root {
            --row-hover: #f0f7ff; /* Solid light blue for hover */
            --bg-stripe: #f8fafc; /* Solid off-white for zebra */
            --header-bg: var(--card-bg);
            --zoom: 1;
        }

        [data-theme="dark"] {
            --row-hover: #2d3748; /* Solid dark hover */
            --bg-stripe: #262f3f; /* Solid dark stripe */
        }

        .dashboard-page .container {
            /* Fix: Height must adapt to CSS zoom to prevent bottom cut-off */
            height: calc(100vh / var(--zoom) - (64px / var(--zoom))) !important;
            max-width: 100% !important;
            margin: calc(64px / var(--zoom)) 0 0 0 !important;
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            background: var(--bg) !important;
            overflow: hidden !important;
        }

        .dashboard-page .card {
            flex: 1 !important;
            display: grid !important;
            grid-template-rows: auto auto 1fr auto !important; /* Header, Search, Table, Pagination */
            height: 100% !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
            min-height: 0 !important;
        }

        /* Compact Integrated Search Bar */
        .dashboard-page .search-bar {
            padding: 10px 20px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            align-items: center;
            z-index: 110;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .dashboard-page .search-bar input,
        .dashboard-page .search-bar select {
            padding: 6px 10px !important;
            font-size: 0.8rem !important;
            height: 32px !important;
            background: var(--bg) !important;
            border: 1px solid var(--border) !important;
            border-radius: 6px !important;
        }

        /* High Density Table - Fixed and Stable Layout */
        .dashboard-page .table-container {
            flex: 1 !important;
            overflow: auto !important;
            background: var(--bg) !important;
            border: none !important;
            border-radius: 0 !important;
            position: relative;
            scrollbar-gutter: stable;
        }

        #candidateTable {
            border-collapse: separate;
            border-spacing: 0;
            width: max-content; 
            min-width: 100%;
            font-size: 0.65rem; /* Drastic density increase */
            table-layout: auto !important; /* Allow rows to grow vertically */
        }

        #candidateTable th {
            background: #f8fafc;
            padding: 15px 12px;
            text-align: left; /* Left align by default for better data connection */
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        /* Specifically center headers for columns that have centered data */
        #candidateTable th:nth-child(1),
        #candidateTable th:nth-child(3),
        #candidateTable th:nth-child(4),
        #candidateTable th:nth-child(5),
        #candidateTable th:nth-child(6),
        #candidateTable th:nth-child(10),
        #candidateTable th:nth-child(11),
        #candidateTable th:nth-child(15),
        #candidateTable th:nth-child(16),
        #candidateTable th:nth-child(18),
        #candidateTable th:nth-child(19),
        #candidateTable th:nth-child(20),
        #candidateTable th:nth-child(21) {
            text-align: center;
        }

        /* Enforce SOLID backgrounds for sticky columns to prevent overlap */
        #candidateTable td {
            padding: 2px 10px !important; 
            border-bottom: 1px solid var(--border) !important;
            border-right: 1px solid rgba(0,0,0,0.03) !important; /* Subtle vertical separation */
            vertical-align: middle; /* Center vertically for professional look */
            color: var(--text);
            white-space: normal !important;
            word-break: break-word !important; 
            overflow-wrap: break-word !important;
            line-height: 1.15;
            transition: background 0.15s ease;
            position: relative;
            height: 28px;
            background-color: var(--card-bg); /* Default solid */
        }

        /* Explicit Column Widths & Sticky Logic - FIXED EXPANSION */
        #candidateTable th:nth-child(1), #candidateTable td:nth-child(1) { 
            min-width: 65px; 
            max-width: 65px;
            position: sticky !important; 
            left: 0; 
            z-index: 100;
            border-right: 1px solid var(--border) !important;
        }
        #candidateTable th:nth-child(2), #candidateTable td:nth-child(2) { 
            min-width: 220px; 
            max-width: 220px;
            position: sticky !important; 
            left: 65px; 
            z-index: 100;
            box-shadow: 4px 0 10px rgba(0,0,0,0.08);
            border-right: 1px solid var(--border) !important;
        }

        /* Zebra Striping for ALL columns including sticky ones */
        #candidateTable tr:nth-child(even) td {
            background-color: var(--bg-stripe) !important;
        }

        /* Ensure Headers stay on top of both scroll directions */
        #candidateTable th {
            z-index: 200 !important;
            background-color: var(--card-bg) !important;
        }
        #candidateTable th:nth-child(1), #candidateTable th:nth-child(2) { 
            z-index: 210 !important; 
        }

        /* Hovered row must be above other rows but below header */
        #candidateTable tr:hover td {
            z-index: 150 !important;
        }
        #candidateTable tr:hover td:nth-child(1), 
        #candidateTable tr:hover td:nth-child(2) {
            z-index: 160 !important;
        }

        #candidateTable th:nth-child(3), #candidateTable td:nth-child(3) { width: 140px; } /* Location */
        #candidateTable th:nth-child(4), #candidateTable td:nth-child(4) { width: 100px; } /* DOB */
        #candidateTable th:nth-child(5), #candidateTable td:nth-child(5) { width: 180px; } /* Prev. Co */
        #candidateTable th:nth-child(6), #candidateTable td:nth-child(6) { width: 140px; } /* Curr. Pos */
        #candidateTable th:nth-child(7), #candidateTable td:nth-child(7) { width: 140px; } /* Org */
        #candidateTable th:nth-child(8), #candidateTable td:nth-child(8) { width: 150px; } /* Education */
        #candidateTable th:nth-child(9), #candidateTable td:nth-child(9) { width: 150px; } /* Institute */
        #candidateTable th:nth-child(10), #candidateTable td:nth-child(10) { width: 80px; }  /* Exp */
        #candidateTable th:nth-child(11), #candidateTable td:nth-child(11) { width: 100px; } /* Salary */
        #candidateTable th:nth-child(12), #candidateTable td:nth-child(12) { width: 180px; } /* Skills */
        #candidateTable th:nth-child(13), #candidateTable td:nth-child(13) { width: 150px; } /* Strength */
        #candidateTable th:nth-child(14), #candidateTable td:nth-child(14) { width: 150px; } /* Weakness */
        #candidateTable th:nth-child(15), #candidateTable td:nth-child(15) { width: 80px; }  /* Rating */
        #candidateTable th:nth-child(16), #candidateTable td:nth-child(16) { width: 80px; }  /* Match */
        #candidateTable th:nth-child(17), #candidateTable td:nth-child(17) { width: 350px; } /* Reason */
        #candidateTable th:nth-child(18), #candidateTable td:nth-child(18) { width: 90px; }  /* Shortlisted */
        #candidateTable th:nth-child(19), #candidateTable td:nth-child(19) { width: 70px; }  /* Conf */
        #candidateTable th:nth-child(20), #candidateTable td:nth-child(20) { width: 100px; } /* Added On */
        #candidateTable th:nth-child(21), #candidateTable td:nth-child(21) { width: 80px; }  /* Actions */


        #candidateTable tr:hover td:not(:nth-child(1)):not(:nth-child(2)) {
            background: var(--row-hover) !important;
        }

        /* Hover state for sticky columns must stay OPAQUE to prevent ghosting */
        #candidateTable tr:hover td:nth-child(1), 
        #candidateTable tr:hover td:nth-child(2) {
            background-color: var(--row-hover) !important; 
            z-index: 160 !important;
        }

        /* Interactive Elements in Table - Expand on Hover (Full Row) */
        .col-text, .col-skills, .col-text-wide {
            max-height: 2.3em; /* 2 lines */
            overflow: hidden;
            display: block;
            transition: max-height 0.3s ease;
            text-align: left; /* Fix: removed justify to avoid large word gaps */
        }
        
        tr:hover .col-text, 
        tr:hover .col-skills, 
        tr:hover .col-text-wide,
        .expanded-all .col-text,
        .expanded-all .col-skills,
        .expanded-all .col-text-wide {
            max-height: 2000px; 
            overflow: visible;
        }

        /* TOP N Filter Pill Style */
        .top-filter-pill {
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--card-bg);
            height: 32px;
            overflow: hidden;
            transition: all 0.2s ease;
            margin: 0 5px;
        }
        .top-filter-pill:focus-within {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
        }
        .top-label {
            font-size: 0.65rem;
            color: #64748b;
            font-weight: 800;
            text-transform: uppercase;
            padding: 0 8px;
            background: #f8fafc;
            height: 100%;
            display: flex;
            align-items: center;
            border-right: 1px solid var(--border);
            user-select: none;
        }


        /* Specific alignment for non-text columns */
        #candidateTable td:nth-child(3),
        #candidateTable td:nth-child(4),
        /* Prev. Co, Curr. Pos, Org (5,6,7) - skip global center */
        #candidateTable td:nth-child(10), /* Exp */
        #candidateTable td:nth-child(11), /* Salary */
        #candidateTable td:nth-child(15), /* Rating */
        #candidateTable td:nth-child(16), /* Match */
        #candidateTable td:nth-child(18), /* Shortlisted */
        #candidateTable td:nth-child(19), /* Conf */
        #candidateTable td:nth-child(20) { /* Added On */
            text-align: center; /* Center horizontally for data columns */
        }

        .center-content {
            text-align: center !important;
        }

        /* Sticky Pagination Bar - Always at bottom of screen */
        .dashboard-page .pagination {
            position: sticky !important;
            bottom: 0 !important;
            background: var(--card-bg) !important;
            border-top: 1px solid var(--border) !important;
            padding: 8px 20px !important;
            margin: 0 !important;
            display: flex !important;
            justify-content: center;
            align-items: center;
            z-index: 500; /* Higher than sticky table cells */
            flex-shrink: 0;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.08); /* More prominent shadow */
        }

        /* Custom Scrollbar */
        .table-container::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        .table-container::-webkit-scrollbar-track {
            background: var(--bg);
        }
        .table-container::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 5px;
            border: 2px solid var(--bg);
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: var(--text-light);
        }

        #topFilter {
            width: 50px !important;
            text-align: center;
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Top Navigation Bar (Fixed) -->
    <div class="top-nav">
        <div class="top-nav-left" style="display: flex; align-items: center; gap: 12px;"> <!-- Increased gap to prevent icons sticking -->
            <?php if (!$is_public): ?>
            <div class="sidebar-toggle" onclick="toggleSidebar()" style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.1); color: white; width: 34px; height: 34px;">
                <span class="material-icons" style="font-size: 20px;">menu</span>
            </div>
            <a href="../index.php" class="nav-link" style="font-size: 14px; text-decoration: none; color: white; display: flex; align-items: center; background: rgba(255,255,255,0.08); width: 34px; height: 34px; justify-content: center; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);" title="Back to Home">
                <span class="material-icons" style="font-size: 20px;">arrow_back</span>
            </a>
            <?php else: ?>
            <div style="width: 34px;"></div>
            <?php endif; ?>
        </div>

        <div class="top-nav-center" style="display: flex; align-items: center; gap: 25px; max-width: 80%;">
            <!-- Job Title Section -->
            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                <div style="background: rgba(16, 185, 129, 0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(16, 185, 129, 0.2);">
                    <span class="material-icons" style="color: #10b981; font-size: 18px;">work_outline</span>
                </div>
                <div style="display: flex; flex-direction: column; min-width: 0;">
                    <span id="dynamicJobTitle" onclick="window.location.reload()" class="job-title-text"
                          title="<?php echo htmlspecialchars($job_details['job_title'] ?? $_GET['job_title'] ?? ''); ?>">
                        <?php echo htmlspecialchars($job_details['job_title'] ?? $_GET['job_title'] ?? 'Job Dashboard'); ?>
                    </span>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 1px;">
                        <span style="font-size: 0.6rem; color: #10b981; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Live Sync</span>
                        <span style="color: #475569; font-size: 0.7rem;">|</span>
                        <span id="jdBadge" style="font-size: 0.6rem; color: #94a3b8; font-weight: 600;">#<?php echo htmlspecialchars($_GET['jd_id'] ?? ''); ?></span>
                    </div>
                </div>
            </div>

            <!-- Vertical Divider -->
            <div style="width: 1px; height: 25px; background: rgba(255,255,255,0.1);"></div>

            <!-- Concern Person Section -->
            <?php if ($job_details && !empty($job_details['concern_person'])): ?>
                <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                    <div style="display: flex; flex-direction: column; min-width: 0;">
                        <span style="font-size: 0.55rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Concern Person</span>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 0.8rem; color: #f1f5f9; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;">
                                <?php echo htmlspecialchars($job_details['concern_person']); ?>
                            </span>
                            <span style="font-size: 0.7rem; color: #10b981; font-weight: 500;">(<?php echo htmlspecialchars($job_details['department'] ?: 'HR'); ?>)</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- JD Button - Compact -->
            <a href="javascript:void(0)" onclick="openPDFModal('../api/view_jd.php?jd_id=<?php echo $_GET['jd_id']; ?><?php echo isset($_GET['token']) ? '&token='.$_GET['token'] : ''; ?>', '<?php echo $_GET['jd_id']; ?>', 'JD')" 
               style="display: flex; align-items: center; gap: 4px; background: #ef4444; color: white; border-radius: 4px; padding: 4px 8px; text-decoration: none; font-weight: 700; font-size: 0.7rem; transition: all 0.2s; <?php echo empty($_GET['jd_id']) ? 'display:none;' : ''; ?>" 
               onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'"
               title="View Job Description PDF">
                <span class="material-icons" style="font-size: 14px;">picture_as_pdf</span>
                <span>JD</span>
            </a>
        </div>

        <div class="top-nav-right" style="display: flex; align-items: center;">
            <?php if ($is_admin): ?>
                <a href="dashboard.php<?php echo $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" 
                style="display: flex; align-items: center; gap: 6px; background: #4f46e5; color: white; border-radius: 6px; padding: 6px 12px; text-decoration: none; font-weight: 700; font-size: 0.75rem; transition: all 0.2s; margin-right: 15px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);"
                onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'"
                title="Switch to Modern Version (v2)">
                    <span class="material-icons" style="font-size: 16px;">dashboard_customize</span>
                    <span>MODERN v2</span>
                </a>
            <?php endif; ?>
            <?php if (!$is_public): ?>
            <div class="user-profile-nav" onclick="toggleUserDropdown(event)">
                <div class="user-info-text">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                    <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
                <div class="profile-img-container">
                    <?php if (!empty($_SESSION['profile_pic'])): ?>
                        <img id="headerProfilePic" src="<?php echo '../' . $_SESSION['profile_pic']; ?>" alt="Profile">
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
                    <a href="../index.php?logout=1" class="logout-link">
                        <span class="material-icons">logout</span> Logout
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div style="color: white; font-size: 0.8rem; font-weight: 600; opacity: 0.8;">Public View Mode</div>
            <?php endif; ?>
        </div>
    </div>
 
    <?php if (!$is_public): ?>
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h3>Menu</h3>
            <span class="material-icons" onclick="toggleSidebar()" style="cursor: pointer;">close</span>
        </div>
        <div class="sidebar-menu">
            <a href="../index.php">
                <span class="material-icons">home</span> Home
            </a>
            <a href="../tasks.php" style="<?php echo has_permission('manage_tasks') ? '' : 'display:none;'; ?>">
                <span class="material-icons">task</span> Task Management
            </a>
            <a href="javascript:void(0)" onclick="toggleUserManager();" style="<?php echo has_permission('manage_users') ? '' : 'display:none;'; ?>">
                <span class="material-icons">manage_accounts</span> Manage Users
            </a>
            <a href="javascript:void(0)" onclick="toggleDbControl();" style="<?php echo has_permission('db_control') ? '' : 'display:none;'; ?>">
                <span class="material-icons">storage</span> DB Control
            </a>
            <a href="javascript:void(0)" onclick="toggleEmployeeManager();" style="<?php echo has_permission('manage_employees') ? '' : 'display:none;'; ?>">
                <span class="material-icons">badge</span> Manage Employees
            </a>
            <a href="javascript:void(0)" onclick="toggleStatusManager();" style="<?php echo has_permission('manage_statuses') ? '' : 'display:none;'; ?>">
                <span class="material-icons">settings_suggest</span> Manage Statuses
            </a>
            <a href="javascript:void(0)" onclick="toggleRpaConfig();" style="<?php echo has_permission('manage_rpa') ? '' : 'display:none;'; ?>">
                <span class="material-icons">settings_remote</span> RPA Config
            </a>
            <a href="javascript:void(0)" onclick="toggleTaskLimitsModal();" style="<?php echo has_permission('manage_task_limits') ? '' : 'display:none;'; ?>">
                <span class="material-icons">block</span> Task Limits
            </a>
            <a href="javascript:void(0)" onclick="toggleServerAllocation();" style="<?php echo has_permission('manage_server_allocation') ? '' : 'display:none;'; ?>">
                <span class="material-icons">vibration</span> Server Allocation
            </a>

            <!-- Theme Settings Section -->
            <?php if (has_permission('manage_theme_settings')): ?>
            <div style="margin-top: 10px; padding: 10px; border-top: 1px solid var(--border); background: var(--bg); border-radius: 12px;">
                <p style="margin: 0 0 10px 0; font-size: 0.75rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px;">Theme Settings</p>
                
                <!-- Dark Mode Toggle -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-icons" style="font-size: 18px; color: var(--primary);">dark_mode</span>
                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text);">Dark Mode</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="theme-toggle" onchange="toggleTheme()">
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Zoom Controls -->
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="material-icons" style="font-size: 18px; color: var(--primary);">zoom_in</span>
                        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text);">Zoom</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; background: var(--card-bg); padding: 4px 8px; border-radius: 8px; border: 1px solid var(--border);">
                        <span onclick="adjustZoom(-0.1)" class="material-icons" style="font-size: 16px; cursor: pointer; color: var(--text-light);">remove</span>
                        <span id="zoom-percent" style="font-size: 0.75rem; font-weight: 700; color: var(--primary); min-width: 35px; text-align: center;">100%</span>
                        <span onclick="adjustZoom(0.1)" class="material-icons" style="font-size: 16px; cursor: pointer; color: var(--text-light);">add</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
 
    <div class="container">
        <div class="card">
            <div class="search-bar">
                <input type="text" id="searchInput" class="real-time" placeholder="Search by name, email, contact, location, education, institute, skills, organization, previous companies or current position...">
                <select id="shortlistedFilter" class="real-time">
                    <option value="">Status: All</option>
                    <option value="1">Shortlisted</option>
                    <option value="0">Not Shortlisted</option>
                </select>
                <select id="confirmationFilter" class="real-time">
                    <option value="">Conf: All</option>
                    <option value="1">Confirmed</option>
                    <option value="0">Not Confirmed</option>
                </select>
                <div class="top-filter-pill">
                    <span class="top-label">Top</span>
                    <input type="number" id="topFilter" class="real-time" min="1" max="500" placeholder="N" onfocus="this.placeholder=''" onblur="this.placeholder='N'">
                </div>
                <button id="searchBtn" class="btn-primary" style="display: none;">
                    <span class="material-icons" style="font-size: 18px;">search</span> Search
                </button>
                <?php if ($is_public || has_permission('export_data')): ?>
                    <button id="exportCsv" class="btn-primary" style="background: var(--success); display: none; padding: 5px 12px; min-width: 44px; align-items: center; justify-content: center;" title="Export Excel / CSV">
                        <span class="material-icons">file_download</span>
                    </button>
                    <button id="expandAllBtn" onclick="toggleExpandAll()" class="btn-primary" style="background: var(--secondary); display: none; padding: 5px 12px; min-width: 44px; align-items: center; justify-content: center;" title="Expand/Collapse All Rows">
                        <span id="expandAllIcon" class="material-icons">unfold_less</span>
                    </button>
                <?php if (!$is_public && has_permission('send_mail_to_concern')): ?>
                    <button id="mailToConcernBtn" onclick="sendMailToConcern()" class="btn-primary" style="background: #3b82f6; display: none; padding: 5px 12px; min-width: 44px; align-items: center; justify-content: center;" title="Send Filtered Data to Concern Person via Email">
                        <span class="material-icons">mail</span>
                    </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="table-container" id="candidateTableContainer">
                <table id="candidateTable" class="expanded-all">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="id" style="width: 65px; padding: 10px 2px !important; font-size: 0.75rem;">SL/ID <span class="sort-icon">↕</span></th>
                            <th style="min-width: 240px;">Candidate</th>
                            <th>Location</th>
                            <th>DOB</th>
                            <th>Prev. Companies</th>
                            <th>Curr. Position</th>
                            <th>Organization</th>
                            <th>Education</th>
                            <th>Institute</th>
                            <th class="sortable" data-sort="total_experience">Exp. <span class="sort-icon">↕</span></th>
                            <th class="sortable" data-sort="expected_salary">Exp. Salary <span class="sort-icon">↕</span></th>
                            <th>Skills</th>
                            <th>Strength</th>
                            <th>Weakness</th>
                            <th class="sortable" data-sort="rating">Rating <span class="sort-icon">↕</span></th>
                            <th class="sortable" data-sort="match">Matching <span class="sort-icon">↕</span></th>
                            <th>Rating Reason</th>
                            <?php if (!$is_public): ?>
                                <th>Shortlisted</th>
                                <th>Conf.</th>
                            <?php endif; ?>
                            <th class="sortable" data-sort="created_at">Added On <span class="sort-icon">↕</span></th>
                            <?php if (!$is_public): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="candidateBody">
                        <tr><td colspan="21" style="text-align:center; padding: 20px;">Loading candidates...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="pagination" class="pagination"></div>
        </div>
    </div>

    <!-- Modals Section -->
    <?php include("../includes/modals.php"); ?>

    <!-- PDF Viewer Modal -->
    <div id="pdfModal" class="modal-overlay" style="z-index: 10000; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease;">
        <div class="modal-content" style="max-width: 98%; width: 98%; height: 98vh; padding: 0; display: flex; flex-direction: column; overflow: hidden; border-radius: 12px; background: rgba(30, 41, 59, 0.98); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6); transform: scale(0.98); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <div class="modal-header" style="padding: 8px 15px; background: rgba(15, 23, 42, 0.9); color: white; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; min-height: 40px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="background: #ef4444; width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                        <span class="material-icons" style="font-size: 16px; color: white;">picture_as_pdf</span>
                    </div>
                    <div style="display: flex; align-items: baseline; gap: 10px;">
                        <h3 id="pdfModalTitle" style="margin: 0; font-size: 0.95rem; font-weight: 700; color: white; letter-spacing: -0.01em;">Viewer</h3>
                        <span id="pdfModalSub" style="font-size: 0.7rem; color: #94a3b8; font-weight: 500;">Review Mode</span>
                    </div>
                </div>
                <button onclick="closePDFModal()" class="btn-secondary" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 4px 12px; border-radius: 6px; display: flex; align-items: center; gap: 5px; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; font-weight: 700; height: 28px;">
                    <span class="material-icons" style="font-size: 14px;">close</span> Close
                </button>
            </div>
            <div class="modal-body" style="flex: 1; padding: 0; background: #525659; overflow: hidden; position: relative;">
                <iframe id="pdfFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
                <!-- Custom Error State (Hidden by default) -->
                <div id="pdfErrorState" style="display: none; position: absolute; inset: 0; background: #1e293b; align-items: center; justify-content: center; flex-direction: column; color: white; text-align: center; padding: 40px;">
                    <div style="background: rgba(239, 68, 68, 0.1); width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 25px; border: 2px solid rgba(239, 68, 68, 0.2);">
                        <span class="material-icons" style="font-size: 50px; color: #ef4444;">error_outline</span>
                    </div>
                    <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">CV File Not Available</h2>
                    <p style="margin: 15px 0 30px 0; color: #94a3b8; max-width: 400px; line-height: 1.6; font-size: 1rem;">The requested document could not be found on the server. It may still be processing or hasn't been uploaded yet.</p>
                    <button onclick="closePDFModal()" class="btn-primary" style="padding: 12px 35px; border-radius: 12px; font-weight: 600; background: #ef4444;">Back to Dashboard</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pdfModal.show { opacity: 1 !important; pointer-events: auto !important; }
        #pdfModal.show .modal-content { transform: scale(1) !important; }
        
        #pdfModal .btn-secondary:hover { background: rgba(255,255,255,0.2) !important; transform: translateY(-1px); }
    </style>



    <script>
        window.isPublicMode = <?php echo $is_public ? 'true' : 'false'; ?>;
        window.canManageUsers = <?php echo !$is_public && has_permission('manage_users') ? 'true' : 'false'; ?>;
        window.isSuperAdmin = <?php echo !$is_public && (($_SESSION['role'] ?? '') === 'super-admin' || has_permission('manage_roles')) ? 'true' : 'false'; ?>;
        window.canAccessChat = <?php echo !$is_public && has_permission('access_chat') ? 'true' : 'false'; ?>;
        window.currentUser = {
            db_id: '<?php echo $_SESSION['user_id'] ?? ''; ?>',
            id: '<?php echo $_SESSION['username'] ?? ''; ?>',
            role: '<?php echo $_SESSION['role'] ?? ''; ?>'
        };
        window.hasConcernEmail = <?php echo ($job_details && !empty($job_details['concern_email'])) ? 'true' : 'false'; ?>;
    </script>
    <script src="../js/script.js?v=8.7"></script>
</body>
</html>
