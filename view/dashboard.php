<?php
include("../config/db.php"); // DB first so we can check public access
include("../config/auth.php");

/** @var mysqli $conn */

// Auth check
if (session_status() === PHP_SESSION_NONE) session_start();
$is_public = false;
$jd_id_get = isset($_GET['jd_id']) ? trim($_GET['jd_id']) : '';
$token_get  = isset($_GET['token'])  ? trim($_GET['token'])  : '';

if (!empty($jd_id_get) && !isset($_SESSION['user_id'])) {
    // Try: match jd_id (token optional â€“ data APIs enforce token security)
    $stmt_pub = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
    $stmt_pub->bind_param("s", $jd_id_get);
    $stmt_pub->execute();
    if ($stmt_pub->get_result()->num_rows > 0) {
        $is_public = true;
    }
    $stmt_pub->close();
}

// Only enforce login if NOT a valid public link AND not already logged in
if (!$is_public && !isset($_SESSION['user_id'])) {
    check_auth();
}

$user_role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? '';
$is_admin = ($user_role === 'super-admin' || $username === get_root_admin_id());

// Fetch Job Details for Header Metadata
$jd_id = $_GET['jd_id'] ?? '';
$job_details = null;
if (!empty($jd_id)) {
    $stmt = $conn->prepare("SELECT j.*, e.full_name as creator_name FROM Job_List j LEFT JOIN users u ON j.created_by = u.username LEFT JOIN employees e ON u.employee_id = e.employee_id WHERE j.jd_id = ?");
    $stmt->bind_param("s", $jd_id);
    $stmt->execute();
    $job_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$can_send_mail = has_permission('send_mail_to_concern');
$feedback_submitted_at     = $job_details['feedback_submitted_at']     ?? null;
$feedback_submission_count = (int)($job_details['feedback_submission_count'] ?? 0);
$reviewer_name = isset($_GET['rname']) ? trim($_GET['rname']) : ($job_details['concern_person'] ?? '');
$reid_token    = isset($_GET['reid'])  ? trim($_GET['reid'])  : '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Dashboard | CV Sorting</title>
    
    <!-- React (local vendor â€” no Babel needed, JSX is pre-compiled) -->
    <script src="../js/vendor/react.production.min.js"></script>
    <script src="../js/vendor/react-dom.production.min.js"></script>

    <!-- Tailwind CSS (local vendor) -->
    <script src="../js/vendor/tailwindcss.js"></script>

    <!-- Icons & Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="../js/vendor/lucide.min.js"></script>
    <script src="../js/vendor/sweetalert2.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        success: '#10b981',
                        border: '#e2e8f0'
                    }
                }
            }
        }
    </script>

    <style type="text/tailwindcss">
        @layer base {
            body { font-family: 'Inter', sans-serif; }
        }
        @layer components {
            .resizer {
                position: absolute;
                right: -4px;
                top: 0;
                bottom: 0;
                width: 8px;
                cursor: col-resize;
                z-index: 50;
                transition: all 0.2s;
            }
            .resizer:hover, .resizer:active {
                background: #4f46e5;
                box-shadow: 0 0 8px rgba(79, 70, 229, 0.4);
                width: 3px;
                right: 0;
            }
            .sticky-col {
                position: sticky;
                z-index: 20;
                background-color: white;
            }
            thead .sticky-col {
                z-index: 40;
                background-color: #f8fafc; /* slate-50 */
            }
            
            /* High-Density Line Clamp Logic */
            .line-clamp-2-custom {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                white-space: normal; /* Allow internal wrapping */
                transition: all 0.3s ease;
            }
            
            tr:hover .line-clamp-2-custom,
            .expanded-all .line-clamp-2-custom {
                -webkit-line-clamp: unset;
                display: block;
                overflow: visible;
            }

            .compact-cell {
                vertical-align: middle;
                transition: all 0.3s ease;
            }
            
            tr:hover .compact-cell,
            .expanded-all .compact-cell {
                vertical-align: top !important;
                padding-top: 0.5rem !important;
            }
        }
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="h-full overflow-hidden">
    <div id="root" class="h-full"></div>

    <!-- Modern Dashboard Styles -->
    <style>
        /* Scoped styles for legacy modals to prevent conflicts with modern dashboard */
        .modal-overlay { z-index: 9999 !important; }
        .modal-content { font-family: 'Inter', sans-serif !important; }
        /* Ensure Material Icons are available for legacy modals */
        .material-icons { font-family: 'Material Icons' !important; }
    </style>

    <!-- Legacy Resources for Modals -->
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__.'/../css/style.css'); ?>">
    


    <script>
        window.isPublicMode = <?php echo $is_public ? 'true' : 'false'; ?>;
        window.canManageUsers = <?php echo has_permission('manage_users') ? 'true' : 'false'; ?>;
        window.canManageRoles = <?php echo has_permission('manage_roles') ? 'true' : 'false'; ?>;
        window.canManageActions = <?php echo has_permission('manage_actions') ? 'true' : 'false'; ?>;
        window.rootAdminId = <?php echo json_encode(get_root_admin_id()); ?>;
        window.isSuperAdmin = <?php echo (isset($_SESSION['username']) && ($_SESSION['username'] === get_root_admin_id() || $_SESSION['role'] === 'super-admin')) ? 'true' : 'false'; ?>;
        window.currentUserPermissions = <?php echo json_encode($_SESSION['permissions'] ?? []); ?>;
        window.currentUser = {
            db_id: '<?php echo $_SESSION['user_id'] ?? ''; ?>',
            id: '<?php echo $_SESSION['username'] ?? ''; ?>',
            role: '<?php echo $_SESSION['role'] ?? ''; ?>'
        };
        window.feedbackSubmittedAt     = <?php echo json_encode($feedback_submitted_at); ?>;
        window.feedbackSubmissionCount = <?php echo $feedback_submission_count; ?>;
        window.reviewerName = <?php echo json_encode($reviewer_name); ?>;
        window.reidToken    = <?php echo json_encode($reid_token); ?>;
        window.isAdmin               = <?php echo $is_admin ? 'true' : 'false'; ?>;
        window.currentUserName       = <?php echo json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?>;
        window.currentUserRoleLabel  = <?php echo json_encode(ucfirst($_SESSION['role'] ?? 'user')); ?>;
        window.currentUserProfilePic = <?php echo json_encode($_SESSION['profile_pic'] ?? ''); ?>;
        window.currentUserEmpId      = <?php echo json_encode($_SESSION['employee_id'] ?? 'N/A'); ?>;
        window.canSendMailInit          = <?php echo ($can_send_mail && !$is_public) ? 'true' : 'false'; ?>;
        window.canManageGlobalLayouts   = <?php echo has_permission('manage_global_layouts') ? 'true' : 'false'; ?>;
        window.jobTaskNo             = <?php echo json_encode($job_details['task_no'] ?? 'N/A'); ?>;
        window.jobCreatorName        = <?php echo json_encode($job_details['creator_name'] ?? 'System'); ?>;
        window.jobConcernEmail       = <?php echo json_encode($job_details['concern_email'] ?? ''); ?>;
        window.jobConcernName        = <?php echo json_encode($job_details['concern_name'] ?? ''); ?>;

        // Prefetch candidates and column layout immediately — both start before React mounts
        (function() {
            var jdId = new URLSearchParams(window.location.search).get('jd_id') || '';
            if (jdId) {
                window.__prefetchedCandidates = fetch(
                    '../api/get_candidates.php?jd_id=' + encodeURIComponent(jdId) +
                    '&search=&page=1&shortlisted=&confirmation=&sort_by=match&sort_order=DESC&top_n='
                ).then(function(r){ return r.json(); });
            }
            window.__prefetchedColumns = fetch('../api/column_templates.php?action=get_global')
                .then(function(r){ return r.json(); });
        })();
    </script>
    <script src="../js/dashboard-app.js?v=3"></script>

    <?php include("../includes/modals.php"); ?>
    <script src="../js/script.js?v=<?php echo filemtime(__DIR__.'/../js/script.js'); ?>"></script>
</body>
</html>
