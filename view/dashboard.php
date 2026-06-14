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
    // Try: match jd_id (token optional – data APIs enforce token security)
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
    
    <!-- React & Babel CDN -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Icons & Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
    <link rel="stylesheet" href="../css/style.css?v=4.3">
    


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
    </script>
    <script type="text/babel">
        const { useState, useEffect, useMemo, useCallback, useRef } = React;

        // --- Icons Shorthand ---
        const Icon = ({ name, className = "" }) => {
            return <i className={`material-icons ${className}`} style={{ fontSize: 'inherit' }}>{name}</i>;
        };

        // Shared Components
        <?php include '../includes/sidebar_modern.php'; ?>

        const DEFAULT_COLUMNS = [
            { id: 'sl', label: 'SL/ID', width: 55, align: 'center', sticky: true },
            { id: 'candidate', label: 'Candidate', width: 300, align: 'left', sticky: true },
            { id: 'location', label: 'Location', width: 150, align: 'left' },
            { id: 'date_of_birth', label: 'DOB', width: 90, align: 'center' },
            { id: 'Previous_Companies', label: 'Prev. Companies', width: 180, align: 'left' },
            { id: 'Current_Position', label: 'Curr. Position', width: 180, align: 'left' },
            { id: 'organization', label: 'Organization', width: 150, align: 'left' },
            { id: 'education', label: 'Education', width: 160, align: 'left' },
            { id: 'educational_institute', label: 'Institute', width: 160, align: 'left' },
            { id: 'total_experience', label: 'Exp.', width: 60, align: 'center' },
            { id: 'expected_salary', label: 'Exp. Sal', width: 100, align: 'center' },
            { id: 'skills', label: 'Skills', width: 300, align: 'left' },
            { id: 'strength', label: 'Strength', width: 250, align: 'left' },
            { id: 'weakness', label: 'Weakness', width: 250, align: 'left' },
            { id: 'match', label: 'Match', width: 70, align: 'center' },
            { id: 'reason_for_rating', label: 'Rating Reason', width: 400, align: 'left' },
            { id: 'feedback', label: 'Feedback', width: 220, align: 'left' },
            { id: 'shortlisted', label: 'Status', width: 60, align: 'center' },
            { id: 'confirmation', label: 'Conf.', width: 60, align: 'center' },
            { id: 'actions', label: 'Actions', width: 80, align: 'center' }
        ];

        function Dashboard() {
            const isPublic = <?php echo $is_public ? 'true' : 'false'; ?>;
            const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
            const userName = <?php echo json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?>;
            const userRole = <?php echo json_encode(ucfirst($_SESSION['role'] ?? 'user')); ?>;
            const userProfilePic = <?php echo json_encode($_SESSION['profile_pic'] ?? ''); ?>;
            const userEmpId = <?php echo json_encode($_SESSION['employee_id'] ?? 'N/A'); ?>;

            // Helper to merge source cols with default definitions to ensure all props exist
            const mergeWithDefaults = useCallback((sourceCols) => {
                const effectiveDefaults = isPublic 
                    ? DEFAULT_COLUMNS.filter(c => c.id !== 'actions' && c.id !== 'shortlisted' && c.id !== 'confirmation') 
                    : DEFAULT_COLUMNS;
                if (!sourceCols) return effectiveDefaults;
                const merged = sourceCols.map(s => {
                    const def = effectiveDefaults.find(d => d.id === s.id);
                    if (!def) return null;
                    return { ...def, ...s, label: def.label, width: Math.max(s.width || def.width, 60) };
                }).filter(Boolean);
                effectiveDefaults.forEach(def => {
                    if (!merged.find(m => m.id === def.id)) merged.push(def);
                });
                return merged;
            }, [isPublic]);
            
            const [canSendMail, setCanSendMail] = useState(<?php echo ($can_send_mail && !$is_public) ? 'true' : 'false'; ?>);
            const [canManageGlobalLayouts, setCanManageGlobalLayouts] = useState(<?php echo has_permission('manage_global_layouts') ? 'true' : 'false'; ?>);
            const [hasGlobalUpdate, setHasGlobalUpdate] = useState(false);
            const [globalCols, setGlobalCols] = useState(null);
            const [draggedIndex, setDraggedIndex] = useState(null);

            // Real-time Permission Sync
            useEffect(() => {
                if (<?php echo $is_public ? 'true' : 'false'; ?>) return;

                const checkPerms = async () => {
                    try {
                        const response = await fetch('../api/auth_check_api.php?nobump=1');
                        if (response.status === 401 || response.status === 403) {
                            window.location.href = '../login.php';
                            return;
                        }
                        const data = await response.json();
                        if (data.status === 'success') {
                            const perms = data.permissions || {};
                            const isRoot = data.username === data.root_admin_id;
                            const isSuper = isRoot || data.role === 'super-admin';
                            
                            let mailPerm = false;
                            let globalPerm = false;
                            
                            if (isRoot) {
                                mailPerm = true;
                                globalPerm = true;
                            } else {
                                // Mail Permission
                                if (perms.hasOwnProperty('send_mail_to_concern')) {
                                    const val = perms['send_mail_to_concern'];
                                    mailPerm = (val === true || val === "true" || val === 1 || val === "1" || val === "on");
                                } else if (isSuper) {
                                    mailPerm = true;
                                }

                                // Global Layout Permission
                                if (perms.hasOwnProperty('manage_global_layouts')) {
                                    const val = perms['manage_global_layouts'];
                                    globalPerm = (val === true || val === "true" || val === 1 || val === "1" || val === "on");
                                } else if (isSuper) {
                                    globalPerm = true;
                                }
                            }

                            setCanSendMail(mailPerm && !<?php echo $is_public ? 'true' : 'false'; ?>);
                            setCanManageGlobalLayouts(globalPerm);

                            // Keep sidebar in sync with real-time permission changes
                            window.dispatchEvent(new CustomEvent('permissionsLoaded', {
                                detail: { perms: perms, username: data.username, rootAdminId: data.root_admin_id }
                            }));
                        }
                    } catch (e) { console.warn("Permission sync failed", e); }
                };

                const interval = setInterval(checkPerms, 3000);
                return () => clearInterval(interval);
            }, []);
            
            // Job Metadata from PHP
            const taskNo = <?php echo json_encode($job_details['task_no'] ?? 'N/A'); ?>;
            const creatorName = <?php echo json_encode($job_details['creator_name'] ?? 'System'); ?>;
            // Sidebar permissions — seeded from PHP session to avoid flash
            const [sidebarPerms, setSidebarPerms] = useState(<?php
                $p = $_SESSION['permissions'] ?? null;
                if (is_string($p) && !empty($p)) { echo $p; }
                elseif (is_array($p)) { echo json_encode($p); }
                else { echo 'null'; }
            ?>);
            const [sidebarUser, setSidebarUser] = useState({
                username: '<?php echo addslashes($_SESSION['username'] ?? ''); ?>',
                rootAdminId: '<?php echo addslashes(get_root_admin_id()); ?>'
            });

            useEffect(() => {
                const handlePerms = (e) => {
                    setSidebarPerms(e.detail.perms);
                    setSidebarUser({ username: e.detail.username, rootAdminId: e.detail.rootAdminId });
                };
                window.addEventListener('permissionsLoaded', handlePerms);
                return () => window.removeEventListener('permissionsLoaded', handlePerms);
            }, []);

            const [showProfileMenu, setShowProfileMenu] = useState(false);
            const [showSidebar, setShowSidebar] = useState(false);
            const [showProfileModal, setShowProfileModal] = useState(false);
            const [showPdfModal, setShowPdfModal] = useState(false);
            const [pdfUrl, setPdfUrl] = useState('');
            const [pdfTitle, setPdfTitle] = useState('');

            const openPdf = (url, title) => {
                setPdfUrl(url);
                setPdfTitle(title);
                setShowPdfModal(true);
            };

            const [profileData, setProfileData] = useState(null);
            const [profileLoading, setProfileLoading] = useState(false);
            // --- Zoom Logic ---
            const [zoom, setZoom] = useState(() => {
                const saved = localStorage.getItem('zoom');
                return saved ? parseFloat(saved) : 1.0;
            });

            useEffect(() => {
                document.documentElement.style.setProperty('--zoom', zoom);
                localStorage.setItem('zoom', zoom);
            }, [zoom]);

            const adjustZoom = (delta) => {
                setZoom(prev => Math.max(0.5, Math.min(1.5, Math.round((prev + delta) * 10) / 10)));
            };

            const [candidates, setCandidates] = useState([]);
            const [loading, setLoading] = useState(true);
            const [expandedAll, setExpandedAll] = useState(true);
            const [columns, setColumns] = useState(() => {
                return isPublic 
                    ? DEFAULT_COLUMNS.filter(c => c.id !== 'actions' && c.id !== 'shortlisted' && c.id !== 'confirmation') 
                    : DEFAULT_COLUMNS;
            });
            const [editingRowId, setEditingRowId] = useState(null);

            const fetchProfileData = async () => {
                setProfileLoading(true);
                try {
                    const res = await fetch('../api/user_api.php?action=get_profile');
                    const json = await res.json();
                    if (json.status === 'success') {
                        setProfileData(json.data);
                    } else {
                        console.error("Profile API Error:", json.message);
                    }
                } catch (e) {
                    console.error("Failed to fetch profile:", e);
                } finally {
                    setProfileLoading(false);
                }
            };

            useEffect(() => {
                const initColumns = async () => {
                    // 1. Fetch Global from server
                    let serverGlobal = null;
                    try {
                        const response = await fetch('../api/column_templates.php', {
                            method: 'POST',
                            body: JSON.stringify({ action: 'get_global' })
                        });
                        const result = await response.json();
                        if (result.status === 'success' && result.data) {
                            serverGlobal = result.data;
                            setGlobalCols(serverGlobal);
                        }
                    } catch(e) { console.error("Global load failed", e); }

                    // 2. Check local
                    const saved = localStorage.getItem('modern_dashboard_columns_v5');
                    if (saved) {
                        try {
                            const savedCols = JSON.parse(saved);
                            const finalCols = mergeWithDefaults(savedCols);
                            
                            // Check if global layout has been UPDATED on server since last sync/ack
                            if (serverGlobal) {
                                const globalSig = JSON.stringify(mergeWithDefaults(serverGlobal).map(c => ({ id: c.id, h: !!c.hidden, w: c.width })));
                                const ackSig = localStorage.getItem('acknowledged_global_sig');
                                
                                if (ackSig) {
                                    if (globalSig !== ackSig) {
                                        setHasGlobalUpdate(true);
                                    }
                                } else {
                                    // First time: if local is different from global, show sync once, 
                                    // or just acknowledge if they just started.
                                    const localSig = JSON.stringify(finalCols.map(c => ({ id: c.id, h: !!c.hidden, w: c.width })));
                                    if (localSig !== globalSig) {
                                        setHasGlobalUpdate(true);
                                    } else {
                                        localStorage.setItem('acknowledged_global_sig', globalSig);
                                    }
                                }
                            }
                            
                            setColumns(finalCols);
                            return;
                        } catch(e) { console.error(e); }
                    }

                    // 3. Fallback to Global or Default
                    const finalFallback = mergeWithDefaults(serverGlobal);
                    if (serverGlobal) {
                        const globalSig = JSON.stringify(finalFallback.map(c => ({ id: c.id, h: !!c.hidden, w: c.width })));
                        localStorage.setItem('acknowledged_global_sig', globalSig);
                    }
                    setColumns(finalFallback);
                };
                initColumns();
            }, [mergeWithDefaults]);

            const applyGlobal = () => {
                if (!globalCols) return;
                const finalCols = mergeWithDefaults(globalCols);
                const globalSig = JSON.stringify(finalCols.map(c => ({ id: c.id, h: !!c.hidden, w: c.width })));
                
                setColumns(finalCols);
                localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(finalCols));
                localStorage.setItem('acknowledged_global_sig', globalSig);
                setHasGlobalUpdate(false);
                Swal.fire({ icon: 'success', title: 'Layout Synced', text: 'You are now using the latest global layout.', timer: 2000, showConfirmButton: false });
            };

            const [search, setSearch] = useState('');
            const [statusFilter, setStatusFilter] = useState('');
            const [confFilter, setConfFilter] = useState('');
            const [topN, setTopN] = useState('');
            const [sortBy, setSortBy] = useState('match');
            const [sortOrder, setSortOrder] = useState('DESC');
            const [pagination, setPagination] = useState({ current_page: 1, total_pages: 1, total_records: 0 });
            const concernEmail = '<?php echo $job_details['concern_email'] ?? ''; ?>';
            const concernName = '<?php echo $job_details['concern_name'] ?? ''; ?>';

            const [showMailModal, setShowMailModal] = useState(false);
            const [allDepts, setAllDepts] = useState([]);
            const [selectedDept, setSelectedDept] = useState('');
            const [deptSearch, setDeptSearch] = useState('');
            const [showDeptList, setShowDeptList] = useState(false);
            const [allEmployees, setAllEmployees] = useState([]);
            const [mailSearch, setMailSearch] = useState('');
            const [isSendingMail, setIsSendingMail] = useState(false);

            const params = new URLSearchParams(window.location.search);
            const jdId = params.get('jd_id') || '';
            const jobTitle = params.get('job_title') || 'Candidate Dashboard';

            const fetchData = useCallback(async (page = 1, isSilent = false) => {
                if (!isSilent) setLoading(true);
                try {
                    const response = await fetch(`../api/get_candidates.php?jd_id=${jdId}&search=${search}&page=${page}&shortlisted=${statusFilter}&confirmation=${confFilter}&sort_by=${sortBy}&sort_order=${sortOrder}&top_n=${topN}`);
                    const json = await response.json();
                    if (json.status === 'success') {
                        setCandidates(json.data || []);
                        setPagination(json.pagination || { current_page: 1, total_pages: 1 });
                    }
                } catch (e) { console.error(e); }
                if (!isSilent) setLoading(false);
            }, [jdId, search, statusFilter, confFilter, sortBy, sortOrder, topN]);

            const sendMailToConcern = async (manualTarget = null) => {
                let targetEmail = manualTarget?.email || concernEmail;
                let targetName = manualTarget?.name || concernName;

                if (!targetEmail) {
                    // Load departments if not loaded
                    if (allDepts.length === 0) {
                        try {
                            const res = await fetch('../api/employee_api.php?action=get_filters');
                            const json = await res.json();
                            if (json.status === 'success') setAllDepts(json.departments || []);
                        } catch(e) { console.error(e); }
                    }
                    setShowMailModal(true);
                    return;
                }

                const filterInfo = [];
                if (statusFilter === '1') filterInfo.push('Shortlisted');
                if (statusFilter === '0') filterInfo.push('Not Shortlisted');
                if (confFilter === '1') filterInfo.push('Confirmed');
                if (confFilter === '0') filterInfo.push('Not Confirmed');
                if (search) filterInfo.push(`Search: "${search}"`);
                if (topN) filterInfo.push(`Top ${topN}`);
                const filterText = filterInfo.length > 0 ? filterInfo.join(', ') : 'All Candidates';

                const result = await Swal.fire({
                    title: 'Send to Concern Person?',
                    text: `Are you sure you want to send the current data (${filterText}) to ${targetEmail}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#4f46e5',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, Send Mail',
                    showLoaderOnConfirm: true,
                    preConfirm: async () => {
                        try {
                            const response = await fetch('../api/send_filtered_mail.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    jd_id: jdId,
                                    search,
                                    shortlisted: statusFilter,
                                    confirmation: confFilter,
                                    top_n: topN,
                                    override_email: targetEmail !== concernEmail ? targetEmail : null
                                })
                            });
                            return await response.json();
                        } catch (error) {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        }
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                });

                if (result.isConfirmed) {
                    if (result.value.status === 'success') {
                        setShowMailModal(false);
                        Swal.fire({
                            icon: 'success',
                            title: 'Sent!',
                            text: 'The candidate list has been successfully emailed.',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: result.value.message || 'Failed to send email. Please check SMTP settings.',
                        });
                    }
                }
            };

            const [mailLoading, setMailLoading] = useState(false);

            // Fetch employees when department changes
            useEffect(() => {
                if (!showMailModal) return;
                const fetchDeptEmployees = async () => {
                    setMailLoading(true);
                    setAllEmployees([]); // Clear old results
                    try {
                        const res = await fetch(`../api/employee_api.php?action=list&dept=${encodeURIComponent(selectedDept)}`);
                        const json = await res.json();
                        if (json.status === 'success') setAllEmployees(json.data || []);
                    } catch(e) { console.error(e); }
                    setMailLoading(false);
                };
                fetchDeptEmployees();
            }, [selectedDept, showMailModal]);
            const handleExport = () => {
                const exportUrl = `../api/export_candidates.php?jd_id=${jdId}&search=${search}&shortlisted=${statusFilter}&confirmation=${confFilter}&sort_by=${sortBy}&sort_order=${sortOrder}&top_n=${topN}`;
                window.location.href = exportUrl;
            };

            // Handle initial load and filter changes
            useEffect(() => {
                fetchData();
            }, [fetchData]);

            // Handle polling (Live Sync)
            useEffect(() => {
                const pollInterval = setInterval(() => {
                    const isSelectionActive = window.getSelection().toString().length > 0;
                    const isAnyModalOpen = Array.from(document.querySelectorAll('.modal-overlay, .modal')).some(m => m.style.display === 'flex' || m.style.display === 'block' || m.classList.contains('show'));
                    
                    const isAnyTextareaFocused = document.activeElement && document.activeElement.tagName === 'TEXTAREA';
                    if (!editingRowId && !isSelectionActive && !isAnyModalOpen && !isAnyTextareaFocused) {
                        fetchData(pagination.current_page, true);
                    }
                }, 3000);

                if (typeof window.loadStatuses === 'function') window.loadStatuses();
                if (typeof window.loadEmployeeRefs === 'function') window.loadEmployeeRefs();

                return () => clearInterval(pollInterval);
            }, [fetchData, editingRowId, pagination.current_page]);

            const handleToggle = async (candidateId, field, value) => {
                // Optimistically update UI
                setCandidates(prev => prev.map(c => c.id === candidateId ? { ...c, [field]: value } : c));
                try {
                    await fetch('../api/update_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: candidateId, field, value })
                    });
                } catch(e) {
                    console.error('Toggle failed', e);
                    fetchData(pagination.current_page); // revert on error
                }
            };

            const [submitState, setSubmitState] = useState({
                submittedAt: window.feedbackSubmittedAt || null,
                submissionCount: window.feedbackSubmissionCount || 0
            });
            const [isDirty, setIsDirty] = useState(false);

            const sessionKey = `reviewer_identity_${jdId}`;
            const [reviewerIdentity, setReviewerIdentity] = useState(() => {
                if (!isPublic) return null;
                try {
                    const stored = sessionStorage.getItem(sessionKey);
                    return stored ? JSON.parse(stored) : null;
                } catch(e) { return null; }
            });
            const confirmReviewerIdentity = (identity) => {
                sessionStorage.setItem(sessionKey, JSON.stringify(identity));
                setReviewerIdentity(identity);
            };

            const saveFeedback = useCallback(async (candidateId, comment, recommended) => {
                try {
                    const response = await fetch('../api/save_feedback.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            candidate_id: candidateId,
                            jd_id: jdId,
                            feedback_comment: comment,
                            feedback_recommended: recommended ? 1 : 0
                        })
                    });
                    const json = await response.json();
                    if (json.status === 'success') {
                        setCandidates(prev => prev.map(c => c.id === candidateId
                            ? { ...c, feedback_comment: comment, feedback_recommended: recommended ? 1 : 0 }
                            : c
                        ));
                        setIsDirty(true);
                    }
                } catch (e) {
                    console.error('Failed to save feedback', e);
                }
            }, [jdId]);

            const submitFeedback = async () => {
                const isResubmit = submitState.submissionCount > 0;
                const result = await Swal.fire({
                    title: isResubmit ? 'Resubmit Feedback?' : 'Submit Feedback?',
                    html: isResubmit
                        ? `<p style="font-size:0.9rem;color:#475569;">You have already submitted <strong>${submitState.submissionCount}</strong> time(s). This will create submission #${submitState.submissionCount + 1} and re-notify the task creator.</p>`
                        : '<p style="font-size:0.9rem;color:#475569;">This will finalize all feedback and send a notification email to the task creator.</p>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#4f46e5',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: isResubmit ? 'Resubmit' : 'Submit Feedback',
                    showLoaderOnConfirm: true,
                    preConfirm: async () => {
                        try {
                            const response = await fetch('../api/submit_feedback_mail.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    jd_id: jdId,
                                    reviewer_name:  reviewerIdentity?.name  || '',
                                    reviewer_email: reviewerIdentity?.email || ''
                                })
                            });
                            return await response.json();
                        } catch (error) {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        }
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                });
                if (result.isConfirmed && result.value) {
                    const val = result.value;
                    if (val.submission_no) {
                        setSubmitState(prev => ({
                            ...prev,
                            submittedAt: val.submitted_at || new Date().toISOString(),
                            submissionCount: val.submission_no
                        }));
                        setIsDirty(false);
                        Swal.fire({
                            icon: val.status === 'success' ? 'success' : 'warning',
                            title: val.status === 'success' ? 'Feedback Submitted!' : 'Partially Done',
                            html: `<p style="font-size:0.9rem;color:#475569;">${val.message}</p>`,
                            timer: 4000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Submission Failed', text: val.message || 'Something went wrong.' });
                    }
                }
            };

            const handleSort = (id) => {
                if (sortBy === id) {
                    setSortOrder(prev => prev === 'ASC' ? 'DESC' : 'ASC');
                } else {
                    setSortBy(id);
                    setSortOrder('DESC');
                }
            };

            const handleResize = (id, delta) => {
                setColumns(prev => {
                    const next = prev.map(col => col.id === id ? { ...col, width: Math.max(40, col.width + delta) } : col);
                    localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(next));
                    return next;
                });
            };

            const [showSettings, setShowSettings] = useState(false);
            const [templates, setTemplates] = useState(() => {
                const saved = localStorage.getItem('modern_dashboard_templates');
                return saved ? JSON.parse(saved) : {};
            });
            const [newTemplateName, setNewTemplateName] = useState('');

            const saveTemplate = (isGlobal = false) => {
                const name = isGlobal ? 'GLOBAL_DEFAULT' : (newTemplateName.trim() || 'Default');
                fetch('../api/column_templates.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'save',
                        template_name: name,
                        column_config: columns,
                        is_global: isGlobal
                    })
                }).then(res => res.json()).then(result => {
                    if (result.status === 'success') {
                        if (!isGlobal) {
                            const updated = { ...templates, [name]: columns };
                            setTemplates(updated);
                            localStorage.setItem('modern_dashboard_templates', JSON.stringify(updated));
                        }
                        alert(isGlobal ? "Global Default Saved for all users!" : "Template saved successfully!");
                        setNewTemplateName('');
                    } else {
                        alert("Error: " + result.message);
                    }
                });
            };

            const loadTemplate = (name) => {
                const template = templates[name];
                if (template) {
                    setColumns(template);
                    localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(template));
                }
            };

            const deleteTemplate = (name) => {
                const updated = { ...templates };
                delete updated[name];
                setTemplates(updated);
                localStorage.setItem('modern_dashboard_templates', JSON.stringify(updated));
            };

            const toggleColumn = (id) => {
                setColumns(prev => {
                    const next = prev.map(col => col.id === id ? { ...col, hidden: !col.hidden } : col);
                    localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(next));
                    return next;
                });
            };

            const moveColumn = (id, direction) => {
                setColumns(prev => {
                    const index = prev.findIndex(c => c.id === id);
                    if ((index === 0 && direction === -1) || (index === prev.length - 1 && direction === 1)) return prev;
                    const next = [...prev];
                    const temp = next[index];
                    next[index] = next[index + direction];
                    next[index + direction] = temp;
                    localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(next));
                    return next;
                });
            };

            const handleDragStart = (e, index) => {
                setDraggedIndex(index);
                e.dataTransfer.effectAllowed = "move";
                // Required for some browsers
                e.dataTransfer.setData("text/html", e.currentTarget);
            };

            const handleDragOver = (e, index) => {
                e.preventDefault();
                if (draggedIndex === null || draggedIndex === index) return;
                
                const next = [...columns];
                const draggedItem = next[draggedIndex];
                next.splice(draggedIndex, 1);
                next.splice(index, 0, draggedItem);
                setDraggedIndex(index);
                setColumns(next);
            };

            const handleDragEnd = () => {
                setDraggedIndex(null);
                localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(columns));
            };

            const activeColumns = columns.filter(c => !c.hidden);

            const getStickyOffset = (id) => {
                let offset = 0;
                for (const col of activeColumns) {
                    if (col.id === id) break;
                    if (col.sticky) offset += col.width;
                }
                return offset;
            };

            return (
                <div className="flex flex-col h-full bg-slate-50 relative text-slate-800">
                    {/* Mail Selection Modal */}
                    {showMailModal && (
                        <div className="absolute inset-0 z-[110] flex items-center justify-center bg-slate-900/40 backdrop-blur-md p-6">
                            <div className="bg-white rounded-3xl shadow-2xl w-full max-w-xl flex flex-col max-h-[85vh] animate-in zoom-in-95 duration-200 overflow-hidden border border-white/20">
                                <div className="p-6 border-b border-slate-100 bg-gradient-to-r from-indigo-600 to-violet-600">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h2 className="text-white font-black uppercase tracking-wider text-lg">Send Candidate List</h2>
                                            <p className="text-indigo-100 text-[11px] font-medium">Select a recipient to deliver the filtered report.</p>
                                        </div>
                                        <button onClick={() => setShowMailModal(false)} className="text-white/70 hover:text-white transition-colors p-2 hover:bg-white/10 rounded-full">
                                            <Icon name="close" className="w-6 h-6" />
                                        </button>
                                    </div>
                                </div>

                                <div className="p-6 flex flex-col gap-5 bg-slate-50 flex-1 overflow-hidden">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="flex flex-col gap-1.5">
                                            <div className="flex items-center justify-between ml-1">
                                                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest">1. Filter by Department</label>
                                                {(selectedDept || deptSearch) && (
                                                    <button onClick={() => { setSelectedDept(''); setDeptSearch(''); }} className="text-[9px] font-bold text-rose-500 hover:text-rose-700 uppercase transition-colors">Clear</button>
                                                )}
                                            </div>
                                            <div className="relative">
                                                <div className="relative">
                                                    <Icon name="business" className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4" />
                                                    <input 
                                                        type="text" 
                                                        placeholder="Type department..." 
                                                        className="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-[12px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600/20 focus:border-indigo-600 transition-all outline-none shadow-sm"
                                                        value={deptSearch}
                                                        onFocus={() => setShowDeptList(true)}
                                                        onChange={(e) => {
                                                            setDeptSearch(e.target.value);
                                                            if (!e.target.value) setSelectedDept('');
                                                        }}
                                                    />
                                                </div>

                                                {showDeptList && (
                                                    <>
                                                        <div className="fixed inset-0 z-10" onClick={() => setShowDeptList(false)}></div>
                                                        <div className="absolute top-full left-0 right-0 mt-2 bg-white border border-slate-200 rounded-2xl shadow-xl z-20 max-h-[250px] overflow-y-auto p-1 animate-in slide-in-from-top-2 duration-200">
                                                            <div 
                                                                className="p-2.5 hover:bg-slate-50 rounded-xl cursor-pointer text-[12px] font-bold text-slate-500 border-b border-slate-50 mb-1"
                                                                onClick={() => { setSelectedDept(''); setDeptSearch(''); setShowDeptList(false); }}
                                                            >
                                                                All Departments
                                                            </div>
                                                            {allDepts
                                                                .filter(d => !deptSearch || d.toLowerCase().includes(deptSearch.toLowerCase()))
                                                                .sort((a, b) => {
                                                                    if (!deptSearch) return 0;
                                                                    const aName = a.toLowerCase();
                                                                    const bName = b.toLowerCase();
                                                                    const s = deptSearch.toLowerCase();
                                                                    const aScore = aName.startsWith(s) ? 2 : 1;
                                                                    const bScore = bName.startsWith(s) ? 2 : 1;
                                                                    return bScore - aScore;
                                                                })
                                                                .map(d => (
                                                                    <div 
                                                                        key={d} 
                                                                        className={`p-2.5 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl cursor-pointer text-[12px] font-bold transition-all ${selectedDept === d ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700'}`}
                                                                        onClick={() => { 
                                                                            setSelectedDept(d); 
                                                                            setDeptSearch(d); 
                                                                            setShowDeptList(false); 
                                                                        }}
                                                                    >
                                                                        {d}
                                                                    </div>
                                                                ))
                                                            }
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">2. Search Name/ID</label>
                                            <div className="relative">
                                                <Icon name="search" className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4" />
                                                <input 
                                                    type="text" 
                                                    placeholder="Search employee..." 
                                                    className="w-full bg-white border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-[12px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600/20 focus:border-indigo-600 transition-all outline-none shadow-sm"
                                                    value={mailSearch}
                                                    onChange={(e) => setMailSearch(e.target.value)}
                                                />
                                                {mailSearch && (
                                                    <button 
                                                        onClick={() => setMailSearch('')}
                                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500 transition-colors"
                                                    >
                                                        <Icon name="cancel" className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-2 flex-1 overflow-hidden">
                                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">3. Select Employee</label>
                                        <div className="bg-white border border-slate-200 rounded-2xl flex-1 overflow-y-auto p-2 shadow-inner min-h-[200px]">
                                            {mailLoading ? (
                                                <div className="flex flex-col items-center justify-center h-full py-10">
                                                    <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-3"></div>
                                                    <p className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Loading Employees...</p>
                                                </div>
                                            ) : (
                                                allEmployees
                                                    .filter(e => !selectedDept || e.department === selectedDept || e.sub_department === selectedDept)
                                                    .filter(e => {
                                                        if (!mailSearch) return true;
                                                        const s = mailSearch.toLowerCase();
                                                        return e.full_name.toLowerCase().includes(s) || 
                                                               e.employee_id.includes(s) || 
                                                               (e.department && e.department.toLowerCase().includes(s)) ||
                                                               (e.designation && e.designation.toLowerCase().includes(s));
                                                    })
                                                    .filter(e => e.email)
                                                    .sort((a, b) => {
                                                        if (!mailSearch) return 0;
                                                        const s = mailSearch.toLowerCase();
                                                        const getScore = (emp) => {
                                                            let score = 0;
                                                            const name = emp.full_name.toLowerCase();
                                                            const dept = (emp.department || '').toLowerCase();
                                                            const desig = (emp.designation || '').toLowerCase();
                                                            const id = emp.employee_id.toLowerCase();
                                                            
                                                            if (name === s || dept === s) score += 100;
                                                            if (name.startsWith(s)) score += 50;
                                                            if (dept.startsWith(s)) score += 40;
                                                            if (id.startsWith(s)) score += 30;
                                                            if (name.includes(s)) score += 10;
                                                            if (dept.includes(s)) score += 5;
                                                            return score;
                                                        };
                                                        return getScore(b) - getScore(a);
                                                    })
                                                    .map(emp => (
                                                        <div 
                                                            key={emp.employee_id} 
                                                            onClick={() => sendMailToConcern({ email: emp.email, name: emp.full_name })}
                                                            className="flex items-center gap-4 p-3 hover:bg-indigo-50 rounded-xl cursor-pointer transition-all border border-transparent hover:border-indigo-100 group mb-1"
                                                        >
                                                            <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-indigo-600 font-black text-xs shrink-0 group-hover:bg-white transition-colors">
                                                                {emp.full_name.charAt(0)}
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <p className="text-[13px] font-black text-slate-800 truncate">{emp.full_name}</p>
                                                                <p className="text-[10px] font-bold text-slate-500 uppercase tracking-tight truncate">{emp.designation} • {emp.department}</p>
                                                            </div>
                                                            <div className="bg-slate-50 group-hover:bg-indigo-600 group-hover:text-white p-2 rounded-lg transition-all">
                                                                <Icon name="chevron_right" className="w-4 h-4" />
                                                            </div>
                                                        </div>
                                                    ))
                                            )}
                                            {!mailLoading && allEmployees.filter(e => e.email).length === 0 && (
                                                <div className="flex flex-col items-center justify-center h-full py-10 text-slate-400">
                                                    <Icon name="person_search" className="w-10 h-10 mb-2 opacity-20" />
                                                    <p className="text-[11px] font-medium">No employees found with an email address.</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    {/* Settings Modal */}
                    {showSettings && (
                        <div className="absolute inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-6">
                            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-row max-h-[90vh] animate-in zoom-in duration-200 overflow-hidden border border-slate-200">
                                {/* Left Side: Templates */}
                                <div className="w-1/3 border-r border-slate-100 bg-slate-50 p-4 flex flex-col gap-3">
                                    <h3 className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Saved Designs</h3>
                                    <div className="flex flex-col gap-1.5 flex-1 overflow-y-auto pr-1">
                                        {Object.keys(templates).length === 0 ? (
                                            <p className="text-[10px] text-slate-400 italic">No saved designs yet.</p>
                                        ) : Object.keys(templates).map(name => (
                                            <div key={name} className="flex items-center justify-between group bg-white p-2 rounded-lg border border-slate-200 hover:border-indigo-600 transition-all cursor-pointer shadow-sm" onClick={() => loadTemplate(name)}>
                                                <span className="text-[10px] font-bold text-slate-700 truncate">{name}</span>
                                                <button onClick={(e) => { e.stopPropagation(); deleteTemplate(name); }} className="text-slate-300 hover:text-rose-600 transition-colors">
                                                    <Icon name="delete" className="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="pt-3 border-t border-slate-200 flex flex-col gap-2">
                                        <input 
                                            type="text" 
                                            placeholder="Design Name..." 
                                            className="w-full px-3 py-1.5 text-[11px] bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600/20"
                                            value={newTemplateName}
                                            onChange={(e) => setNewTemplateName(e.target.value)}
                                        />
                                        <button onClick={() => saveTemplate(false)} className="w-full bg-indigo-600 text-white py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-md">
                                            Save Current
                                        </button>
                                        {canManageGlobalLayouts && (
                                            <button onClick={() => saveTemplate(true)} className="w-full bg-emerald-600 text-white py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-md mt-1">
                                                Save Globally
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Right Side: Column Config */}
                                <div className="flex-1 flex flex-col bg-white">
                                    <div className="p-3 border-b border-slate-100 flex items-center justify-between bg-white">
                                        <h2 className="font-black text-slate-800 uppercase tracking-tight text-[13px]">Customize Columns</h2>
                                        <button onClick={() => setShowSettings(false)} className="text-slate-400 hover:text-slate-600 transition-colors">
                                            <Icon name="close" className="text-lg" />
                                        </button>
                                    </div>
                                    <div className="p-3 overflow-y-auto flex-1 flex flex-col gap-1.5 bg-white select-none">
                                        <div className="flex items-center justify-between mb-1">
                                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Visibility & Sequence</p>
                                            <span className="text-[9px] text-slate-400 font-bold bg-slate-100 px-2 py-0.5 rounded-full">Hold & Drag to Reorder</span>
                                        </div>
                                        {columns.map((col, idx) => (
                                            <div 
                                                key={col.id} 
                                                draggable="true"
                                                onDragStart={(e) => handleDragStart(e, idx)}
                                                onDragOver={(e) => handleDragOver(e, idx)}
                                                onDragEnd={handleDragEnd}
                                                className={`
                                                    flex items-center gap-2 p-1.5 rounded-lg border transition-all duration-150 group cursor-move
                                                    ${draggedIndex === idx 
                                                        ? 'bg-indigo-50 border-indigo-300 opacity-40 scale-95 shadow-inner' 
                                                        : 'bg-white border-slate-200 hover:border-indigo-300 hover:shadow-sm'
                                                    }
                                                `}
                                            >
                                                <div className="flex items-center gap-2 shrink-0">
                                                    <span className="text-[10px] font-black text-slate-300 w-4 text-center">{idx + 1}</span>
                                                    <Icon name="drag_indicator" className="text-slate-300 w-4 h-4" />
                                                </div>
                                                
                                                <div className="flex-1 flex items-center gap-2.5">
                                                    <input 
                                                        type="checkbox" 
                                                        checked={!col.hidden} 
                                                        onChange={() => toggleColumn(col.id)}
                                                        className="w-3.5 h-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 transition-colors"
                                                        disabled={col.sticky}
                                                    />
                                                    <span className={`text-[11px] font-bold truncate ${col.hidden ? 'text-slate-400 line-through opacity-50' : 'text-slate-700'}`}>{col.label}</span>
                                                </div>

                                                <div className="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button onClick={(e) => { e.stopPropagation(); moveColumn(col.id, -1); }} disabled={idx === 0} className="text-slate-400 hover:text-indigo-600 disabled:opacity-0 p-0.5"><Icon name="expand_less" className="text-base" /></button>
                                                    <button onClick={(e) => { e.stopPropagation(); moveColumn(col.id, 1); }} disabled={idx === columns.length - 1} className="text-slate-400 hover:text-indigo-600 disabled:opacity-0 p-0.5"><Icon name="expand_more" className="text-base" /></button>
                                                </div>
                                                
                                                {col.sticky && (
                                                    <div className="shrink-0 flex items-center gap-1 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">
                                                        <Icon name="push_pin" className="text-[9px] text-indigo-500" />
                                                        <span className="text-[8px] font-black text-indigo-500 uppercase">Sticky</span>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="p-3 border-t border-slate-100 flex justify-end bg-slate-50/50">
                                        <button onClick={() => setShowSettings(false)} className="bg-slate-900 text-white px-8 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest shadow-md hover:bg-slate-800 transition-all active:scale-95">Done</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Header */}
                    <header className="bg-slate-900 text-white px-6 py-1 flex items-center justify-between shrink-0 shadow-lg z-50">
                        <div className="flex items-center gap-3">
                            <button onClick={() => setShowSidebar(true)} className="bg-slate-800/80 p-1 rounded-lg border border-slate-700/50 cursor-pointer hover:bg-slate-700 hover:border-slate-600 transition-all flex items-center justify-center w-7 h-7" title="Menu">
                                <Icon name="menu" className="text-slate-300 text-[16px]" />
                            </button>
                            <a href="../index.php" className="bg-slate-800/80 p-1 rounded-lg border border-slate-700/50 cursor-pointer hover:bg-slate-700 hover:border-slate-600 transition-all flex items-center justify-center w-8 h-8" title="Back to Home">
                                <Icon name="arrow_back" className="text-slate-300 text-[18px]" />
                            </a>
                            
                            <div className="w-[1px] h-4 bg-slate-700/50 mx-1"></div>

                            <div className="bg-emerald-500/20 p-1 rounded-lg border border-emerald-500/20 shrink-0">
                                <Icon name="work_outline" className="text-emerald-400 text-[16px]" />
                            </div>
                            <div className="flex flex-col min-w-0">
                                <div className="flex items-center gap-2">
                                    <h1 className="font-bold text-[13px] truncate max-w-[700px] uppercase tracking-tight m-0 text-white" title={jobTitle}>{jobTitle}</h1>
                                    {jdId && (
                                        <button onClick={() => openPdf(`../api/view_jd.php?jd_id=${jdId}`, `Job Description: ${jobTitle}`)} className="flex items-center gap-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-md px-2 py-1 text-[11px] font-black transition-all shrink-0 shadow-sm" title="View/Download JD PDF">
                                            <Icon name="picture_as_pdf" className="text-[14px]" /> JD PDF
                                        </button>
                                    )}
                                </div>
                                <div className="flex items-center gap-2 mt-0.5">
                                    <span className="text-[10px] font-black text-emerald-500 tracking-widest uppercase">Live Sync</span>
                                    <span className="text-slate-600 text-[11px]">|</span>
                                    <span className="text-slate-400 text-[10px] font-bold tracking-wider" title="JD ID">#{jdId}</span>
                                    <span className="text-slate-600 text-[11px]">|</span>
                                    <span className="text-indigo-400 text-[10px] font-bold tracking-wider" title="Task Number">{taskNo}</span>
                                    <span className="text-slate-600 text-[11px]">|</span>
                                    <div className="flex items-center gap-1">
                                        <span className="text-slate-500 text-[10px] font-medium uppercase">By:</span>
                                        <span className="text-slate-300 text-[10px] font-bold tracking-tight uppercase">{creatorName}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 relative">
                            {/* Profile Section */}
                            <div 
                                className={`flex items-center gap-2 px-2 py-1 -mr-2 rounded-lg transition-colors ${!isPublic ? 'cursor-pointer group hover:bg-slate-800' : ''}`} 
                                onClick={() => !isPublic && setShowProfileMenu(!showProfileMenu)}
                            >
                                <div className="flex flex-col items-end mr-1">
                                    <span className="text-[11px] font-bold text-slate-200 leading-tight">{isPublic ? (reviewerIdentity ? reviewerIdentity.name : 'Public') : userName}</span>
                                    <span className="text-[9px] text-slate-500 font-medium leading-tight uppercase tracking-widest">{isPublic ? 'Reviewer' : userRole}</span>
                                </div>
                                <div className="w-7 h-7 rounded-full bg-slate-700 flex items-center justify-center font-black text-[10px] shadow-inner border border-slate-600/30 overflow-hidden">
                                    {isPublic ? (
                                        reviewerIdentity
                                            ? <span className="text-slate-200 font-black text-[10px]">{reviewerIdentity.name.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase()}</span>
                                            : <Icon name="person" className="text-slate-400 text-[18px]" />
                                    ) : userProfilePic ? (
                                        <img src={`../${userProfilePic}`} alt="Profile" className="w-full h-full object-cover" />
                                    ) : (
                                        userName.substring(0, 2).toUpperCase()
                                    )}
                                </div>
                                {!isPublic && (
                                    <Icon name="keyboard_arrow_down" className={`text-slate-400 text-[16px] group-hover:text-white transition-all ${showProfileMenu ? 'rotate-180' : ''}`} />
                                )}
                            </div>

                            {/* Dropdown Menu */}
                            {!isPublic && showProfileMenu && (
                                <>
                                    <div 
                                        className="fixed inset-0 z-40" 
                                        onClick={() => setShowProfileMenu(false)}
                                    ></div>
                                    <div className="absolute top-full right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-slate-200 overflow-hidden py-1 z-50 text-slate-700 origin-top-right animate-in fade-in zoom-in-95 duration-100">
                                        <button 
                                            onClick={() => { setShowProfileMenu(false); setShowProfileModal(true); fetchProfileData(); }}
                                            className="w-full flex items-center gap-2 px-4 py-2 hover:bg-slate-50 text-[11px] font-bold transition-colors text-left"
                                        >
                                            <Icon name="account_circle" className="text-[16px] text-indigo-500" /> My Profile
                                        </button>
                                        <div className="border-t border-slate-100 my-1"></div>
                                        <a href="../index.php?logout=1" className="flex items-center gap-2 px-4 py-2 hover:bg-rose-50 text-[11px] font-bold text-rose-600 transition-colors">
                                            <Icon name="logout" className="text-[16px]" /> Logout
                                        </a>
                                    </div>
                                </>
                            )}
                        </div>
                    </header>

                    {/* Toolbar */}
                    <div className="bg-white border-b border-slate-200 px-6 py-1 flex items-center gap-3 shrink-0 shadow-sm z-40">
                        <div className="relative flex-1 max-w-2xl group">
                            <i className="material-icons absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[13px] group-focus-within:text-indigo-600">search</i>
                            <input 
                                type="text" 
                                placeholder="Search candidates..." 
                                className="w-full pl-8 pr-3 py-1 text-[11px] bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        
                        <div className="flex items-center gap-2">
                            <select 
                                className="text-[10px] font-bold uppercase tracking-wider bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                            >
                                <option value="">All Status</option>
                                <option value="1">Shortlisted</option>
                                <option value="0">Not Shortlisted</option>
                            </select>
                            <select 
                                className="text-[10px] font-bold uppercase tracking-wider bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                value={confFilter}
                                onChange={(e) => setConfFilter(e.target.value)}
                            >
                                <option value="">All Conf.</option>
                                <option value="1">Confirmed</option>
                                <option value="0">Not Confirmed</option>
                            </select>
                            <div className="flex items-center bg-slate-50 border border-slate-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500/20 h-[28px]">
                                <span className="bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-wider px-2 py-1 h-full flex items-center border-r border-slate-200">Top</span>
                                <input 
                                    type="number" 
                                    min="1" 
                                    placeholder="N"
                                    value={topN}
                                    onChange={(e) => setTopN(e.target.value)}
                                    className="w-12 px-1 py-1 text-[11px] bg-transparent focus:outline-none font-black text-indigo-600 text-center placeholder:font-normal placeholder:text-slate-400"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-2 ml-auto">
                            {canSendMail && (
                                <button 
                                    onClick={sendMailToConcern} 
                                    className={`flex items-center gap-1.5 px-3 py-1 text-[10px] font-bold uppercase transition-all shadow-sm active:scale-95 group rounded-lg border cursor-pointer
                                        ${concernEmail 
                                            ? 'bg-rose-50 border-rose-200 text-rose-600 hover:bg-rose-100 hover:border-rose-300' 
                                            : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100 hover:border-slate-300'
                                        }`}
                                    title={concernEmail ? `Send to: ${concernEmail}` : 'Select recipient and send mail'}
                                >
                                    <Icon name="mail_outline" className={`text-[16px] group-hover:rotate-12 transition-transform ${concernEmail ? 'text-rose-500' : 'text-slate-400'}`} />
                                    <span>Mail</span>
                                </button>
                            )}
                            {hasGlobalUpdate && (
                                <button 
                                    onClick={applyGlobal}
                                    className="flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-lg hover:bg-emerald-100 transition-all shadow-sm text-[10px] font-bold uppercase animate-pulse"
                                    title="A new global layout is available. Click to sync."
                                >
                                    <Icon name="auto_fix_high" className="text-[14px]" /> Sync Global
                                </button>
                            )}
                            <button onClick={handleExport} className="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg text-[10px] font-bold shadow-sm transition-all group">
                                <Icon name="file_download" className="text-[14px] group-hover:animate-bounce" /> Export
                            </button>
                            {isPublic && (
                                <button
                                    onClick={submitFeedback}
                                    disabled={!isDirty}
                                    title={isDirty ? 'Send feedback to task creator' : 'Make changes to feedback before submitting'}
                                    className={`flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold shadow-sm transition-all ${isDirty ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed'}`}
                                >
                                    <Icon name="send" className="text-[13px]" />
                                    {submitState.submissionCount > 0 ? 'Resubmit' : 'Submit Feedback'}
                                </button>
                            )}
                            {isAdmin && (
                                <a 
                                    href={`dashboard_v1.php${window.location.search}`}
                                    className="flex items-center gap-1.5 px-3 py-1 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg hover:bg-slate-900 transition-all shadow-sm text-[10px] font-bold uppercase no-underline"
                                    title="Switch to Classic Version (v1)"
                                >
                                    <Icon name="history" className="text-[14px]" /> v1
                                </a>
                            )}
                            <button 
                                onClick={() => setShowSettings(true)}
                                className="flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 w-7 h-7 rounded-lg transition-all"
                                title="Table Settings"
                            >
                                <Icon name="settings" className="text-[14px]" />
                            </button>
                            <button 
                                onClick={() => {
                                    localStorage.removeItem('modern_dashboard_columns_v5');
                                    window.location.reload();
                                }}
                                className="flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 w-7 h-7 rounded-lg transition-all"
                                title="Reset Layout"
                            >
                                <Icon name="restart_alt" className="text-[14px]" />
                            </button>
                            <button 
                                onClick={() => setExpandedAll(!expandedAll)}
                                className="flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 w-7 h-7 rounded-lg transition-all"
                                title={expandedAll ? "Collapse Rows" : "Expand Rows"}
                            >
                                <Icon name={expandedAll ? "unfold_less" : "unfold_more"} className="text-[14px]" />
                            </button>
                        </div>
                    </div>

                    {/* Table Container */}
                    <div className="flex-1 overflow-auto relative">
                        <table id="candidateTable" className={`border-separate border-spacing-0 table-fixed w-max min-w-full bg-white ${expandedAll ? 'expanded-all' : ''}`}>
                            <thead className="sticky top-0 z-30">
                                <tr>
                                    {activeColumns.map(col => (
                                        <th 
                                            key={col.id}
                                            data-col-id={col.id}
                                            className={`bg-slate-50 border-b border-r border-slate-200 px-2 py-2.5 text-[9px] font-black text-slate-500 uppercase tracking-wider text-center ${col.sticky ? 'sticky-col' : 'relative'} cursor-pointer hover:bg-slate-100 transition-colors group/th`}
                                            style={{
                                                width: col.width,
                                                minWidth: col.width,
                                                maxWidth: col.width,
                                                left: col.sticky ? getStickyOffset(col.id) : undefined,
                                                zIndex: col.sticky ? 45 : 10
                                            }}
                                            onClick={() => handleSort(col.id)}
                                        >
                                            <div className="flex items-center justify-center w-full">
                                                <span className="truncate">{col.label}</span>
                                            </div>
                                            
                                            {/* Overlay Icons to save space */}
                                            <div className="absolute right-0.5 top-1/2 -translate-y-1/2 flex items-center gap-0.5 pointer-events-none">
                                                {sortBy === col.id && (
                                                    <Icon 
                                                        name={sortOrder === 'ASC' ? "arrow_upward" : "arrow_downward"} 
                                                        className="text-indigo-600 w-2.5 h-2.5 bg-slate-50/90 rounded-sm" 
                                                    />
                                                )}
                                                <Icon 
                                                    name="filter_list" 
                                                    className={`w-2.5 h-2.5 text-slate-400 bg-slate-50/90 rounded-sm transition-opacity ${sortBy === col.id ? 'opacity-100' : 'opacity-0 group-hover/th:opacity-100'}`} 
                                                />
                                            </div>
                                            <div 
                                                className="resizer" 
                                                onMouseDown={(e) => {
                                                    e.stopPropagation();
                                                    const startX = e.pageX;
                                                    const startWidth = col.width;
                                                    const onMove = (moveE) => {
                                                        const delta = moveE.pageX - startX;
                                                        setColumns(prev => prev.map(c => 
                                                            c.id === col.id ? { ...c, width: Math.max(50, startWidth + delta) } : c
                                                        ));
                                                    };
                                                    const onUp = () => {
                                                        document.removeEventListener('mousemove', onMove);
                                                        document.removeEventListener('mouseup', onUp);
                                                        // Persist after resize
                                                        setColumns(curr => {
                                                            localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(curr));
                                                            return curr;
                                                        });
                                                    };
                                                    document.addEventListener('mousemove', onMove);
                                                    document.addEventListener('mouseup', onUp);
                                                }}
                                            />
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="bg-white">
                                {loading ? (
                                    Array.from({ length: 8 }).map((_, i) => (
                                        <tr key={i} className="animate-pulse">
                                            {activeColumns.map(col => (
                                                <td key={col.id} className="border-b border-slate-100 px-2 py-2">
                                                    <div className="h-2 bg-slate-100 rounded-full w-full opacity-60"></div>
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                ) : candidates.length === 0 ? (
                                    <tr><td colSpan={activeColumns.length} className="py-20 text-center text-slate-400 font-medium">No candidates found matching your criteria.</td></tr>
                                ) : candidates.map((c, i) => (
                                    <tr key={c.id} className="group hover:bg-indigo-50/30 transition-colors">
                                        {activeColumns.map(col => (
                                            <td 
                                                key={col.id}
                                                data-col-id={col.id}
                                                className={`
                                                    border-b border-r border-slate-100 px-2 py-1 text-[11px] text-slate-600 compact-cell
                                                    ${col.sticky ? 'sticky-col' : ''} 
                                                `}
                                                style={{
                                                    width: col.width,
                                                    minWidth: col.width,
                                                    maxWidth: col.width,
                                                    left: col.sticky ? getStickyOffset(col.id) : undefined,
                                                    textAlign: col.align,
                                                    backgroundColor: 'white',
                                                    wordBreak: 'break-word',
                                                    overflowWrap: 'break-word'
                                                }}
                                            >
                                                <div className="line-clamp-2-custom">
                                                    <CellContent id={col.id} candidate={c} sl={(pagination.current_page - 1) * 50 + i + 1} expanded={expandedAll} openPdf={openPdf} jdId={jdId} onToggle={handleToggle} editingRowId={editingRowId} setEditingRowId={setEditingRowId} isPublic={isPublic} onFeedbackSave={saveFeedback} />
                                                </div>
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer */}
                    <footer className="bg-white border-t border-slate-200 px-6 py-1 flex items-center justify-between shrink-0 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-50">
                        <div className="text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-3">
                            <span className="bg-slate-100 px-2 py-1 rounded text-slate-600">
                                Showing <span className="text-indigo-600 font-black text-[13px]">
                                    {pagination.total_records > 0 ? `${(pagination.current_page - 1) * 50 + 1} - ${Math.min(pagination.current_page * 50, pagination.total_records)}` : '0'}
                                </span> of <span className="text-slate-800 font-black text-[13px]">{pagination.total_records || 0}</span>
                            </span>
                        </div>
                        
                        <div className="flex items-center gap-2">
                            <button 
                                disabled={pagination.current_page <= 1}
                                onClick={() => fetchData(pagination.current_page - 1)}
                                className={`w-8 h-8 flex items-center justify-center rounded-lg transition-all border
                                    ${pagination.current_page <= 1 
                                        ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' 
                                        : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400 hover:text-indigo-600 shadow-sm'
                                    }`}
                            >
                                <Icon name="chevron_left" className="text-lg" />
                            </button>

                            <div className="flex items-center gap-1.5">
                                {(() => {
                                    const total = pagination.total_pages;
                                    const current = pagination.current_page;
                                    const pages = [];
                                    if (total <= 7) {
                                        for (let i = 1; i <= total; i++) pages.push(i);
                                    } else {
                                        if (current <= 4) {
                                            pages.push(1, 2, 3, 4, 5, '...', total);
                                        } else if (current >= total - 3) {
                                            pages.push(1, '...', total - 4, total - 3, total - 2, total - 1, total);
                                        } else {
                                            pages.push(1, '...', current - 1, current, current + 1, '...', total);
                                        }
                                    }
                                    return pages.map((p, i) => (
                                        p === '...' ? (
                                            <span key={`dots-${i}`} className="px-1 text-slate-400 font-bold">...</span>
                                        ) : (
                                            <button 
                                                key={p} 
                                                onClick={() => fetchData(p)}
                                                className={`w-8 h-8 flex items-center justify-center rounded-lg text-[11px] font-black transition-all border
                                                    ${current === p 
                                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-md scale-105' 
                                                        : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400 hover:text-indigo-600 shadow-sm'
                                                    }`}
                                            >
                                                {p}
                                            </button>
                                        )
                                    ));
                                })()}
                            </div>

                            <button 
                                disabled={pagination.current_page >= pagination.total_pages}
                                onClick={() => fetchData(pagination.current_page + 1)}
                                className={`w-8 h-8 flex items-center justify-center rounded-lg transition-all border
                                    ${pagination.current_page >= pagination.total_pages
                                        ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' 
                                        : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400 hover:text-indigo-600 shadow-sm'
                                    }`}
                            >
                                <Icon name="chevron_right" className="text-lg" />
                            </button>

                            <div className="h-6 w-[1px] bg-slate-200 mx-2"></div>

                            <div className="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1">
                                <span className="text-[10px] font-black text-slate-400 uppercase">Go To</span>
                                <input 
                                    type="number" 
                                    min="1" 
                                    max={pagination.total_pages}
                                    className="w-10 bg-transparent border-none text-[11px] font-black text-indigo-600 focus:ring-0 p-0 text-center"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            const val = parseInt(e.target.value);
                                            if (val >= 1 && val <= pagination.total_pages) fetchData(val);
                                        }
                                    }}
                                    placeholder="Pg"
                                />
                            </div>
                        </div>
                    </footer>

                    {/* Sidebar */}
                    <Sidebar 
                        showSidebar={showSidebar}
                        setShowSidebar={setShowSidebar}
                        isAdmin={isAdmin}
                        zoom={zoom}
                        adjustZoom={adjustZoom}
                        isPublic={window.isPublicMode}
                        perms={sidebarPerms}
                        username={sidebarUser.username}
                        rootAdminId={sidebarUser.rootAdminId}
                    />

                    {/* Profile Modal */}
                    {showProfileModal && (
                        <div className="fixed inset-0 z-[110] flex items-center justify-center p-4">
                            <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={() => setShowProfileModal(false)}></div>
                            <div className="relative bg-white rounded-[24px] w-full max-w-[850px] shadow-2xl flex overflow-hidden min-h-[500px] animate-in zoom-in-95 duration-200">
                                {/* Left Side: Deep Blue Hero */}
                                <div className="w-[300px] bg-gradient-to-br from-indigo-950 to-indigo-900 p-10 flex flex-col items-center text-white relative shrink-0 border-r border-indigo-800">
                                    <div className="relative mb-6">
                                        <div className="w-[150px] h-[150px] rounded-full p-1.5 bg-white/20 backdrop-blur-sm">
                                            {userProfilePic ? (
                                                <img src={`../${userProfilePic}`} alt="Profile" className="w-full h-full rounded-full object-cover border-4 border-white/10 shadow-xl" />
                                            ) : (
                                                <div className="w-full h-full rounded-full bg-slate-800 flex items-center justify-center border-4 border-white/10 shadow-xl">
                                                    <Icon name="person" className="text-[80px] text-slate-400" />
                                                </div>
                                            )}
                                        </div>
                                        <div className="absolute bottom-1 right-1 w-[42px] h-[42px] bg-white rounded-full flex items-center justify-center text-indigo-600 cursor-pointer shadow-lg border-2 border-indigo-100 hover:scale-105 transition-transform" onClick={() => alert('Profile picture upload available in classic dashboard')}>
                                            <Icon name="camera_alt" className="text-[22px]" />
                                        </div>
                                    </div>
                                    
                                    <h2 className="m-0 text-2xl font-bold text-center leading-tight mb-3">{userName}</h2>
                                    <div className="px-3 py-1 bg-white/20 rounded-full text-xs font-bold uppercase tracking-wider">{userRole}</div>
                                    
                                    <div className="mt-auto w-full pt-8">
                                        <div className="flex items-center gap-2 text-sm opacity-90 mb-0">
                                            <Icon name="fingerprint" className="text-[18px]" />
                                            <span>Employee ID: <strong>{profileData?.employee_id || userEmpId || 'N/A'}</strong></span>
                                        </div>
                                    </div>
                                </div>
                                
                                {/* Right Side: Details */}
                                <div className="flex-1 p-10 flex flex-col relative bg-white">
                                    <div className="flex justify-between items-start mb-8">
                                        <div>
                                            <h3 className="m-0 text-2xl text-slate-900 font-extrabold tracking-tight">Account Details</h3>
                                            <p className="m-0 mt-1.5 text-slate-500 text-[13px] font-medium">Manage your professional identity and contact information.</p>
                                        </div>
                                        <button onClick={() => setShowProfileModal(false)} className="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400 transition-all hover:text-rose-500">
                                            <Icon name="close" className="text-[24px]" />
                                        </button>
                                    </div>
                                    
                                    {profileLoading ? (
                                        <div className="flex-1 flex flex-col items-center justify-center text-slate-400">
                                            <div className="w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
                                            <p className="text-[12px] font-black uppercase tracking-widest text-slate-500">Syncing Profile Data...</p>
                                        </div>
                                    ) : !profileData ? (
                                        <div className="flex-1 flex flex-col items-center justify-center text-rose-400 p-10 text-center bg-rose-50/50 rounded-[32px] border border-rose-100/50">
                                            <div className="w-16 h-16 bg-rose-100 rounded-full flex items-center justify-center mb-4">
                                                <Icon name="error_outline" className="text-[32px] text-rose-600" />
                                            </div>
                                            <h4 className="text-[16px] font-bold text-rose-900 mb-1">Connection Error</h4>
                                            <p className="text-[13px] text-rose-600/70 mb-6">We couldn't retrieve your profile information right now.</p>
                                            <button onClick={fetchProfileData} className="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-indigo-200 active:scale-95">Retry Sync</button>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-2 gap-4 flex-1">
                                            {[
                                                { label: 'Email Address', value: profileData.email, icon: 'alternate_email', color: 'indigo' },
                                                { label: 'Mobile Number', value: profileData.mobile_no, icon: 'phone_iphone', color: 'emerald' },
                                                { label: 'Designation', value: profileData.designation, icon: 'work_history', color: 'amber' },
                                                { label: 'Department', value: profileData.department, icon: 'corporate_fare', color: 'violet' },
                                                { label: 'IP Phone', value: profileData.ip_no, icon: 'contact_phone', color: 'sky' },
                                                { label: 'Office Floor', value: profileData.floor, icon: 'layers', color: 'rose' }
                                            ].map((item, idx) => (
                                                <div key={idx} className="group flex items-center gap-4 bg-slate-50/50 border border-slate-100 p-4 rounded-[20px] hover:bg-white hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-500/5 transition-all duration-300">
                                                    <div className={`w-11 h-11 rounded-2xl bg-${item.color}-100/50 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform duration-300`}>
                                                        <Icon name={item.icon} className={`text-${item.color}-600 text-[20px]`} />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">{item.label}</p>
                                                        <p className="font-bold text-[14px] text-slate-800 m-0 truncate">{item.value || 'Not Provided'}</p>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    
                                    <div className="mt-8 pt-6 border-t border-slate-100 flex items-center justify-between shrink-0">
                                        <div className="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-tight">
                                            <Icon name="verified_user" className="text-[14px] text-emerald-500" />
                                            <span>Session Secure & Verified</span>
                                        </div>
                                        <button onClick={() => alert('Security settings are managed in the main portal.')} className="flex items-center gap-2 px-6 py-2.5 bg-slate-900 text-white font-black text-[11px] uppercase tracking-widest rounded-xl hover:bg-slate-800 transition-all shadow-lg active:scale-95">
                                            <Icon name="lock" className="text-[16px]" /> Account Security
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Reviewer Identity Modal — shown once per session for public view */}
                    {isPublic && !reviewerIdentity && (
                        <ReviewerWelcomeModal
                            jobTitle={jobTitle}
                            jdId={jdId}
                            reidToken={window.reidToken || ''}
                            onConfirm={confirmReviewerIdentity}
                        />
                    )}

                    {/* PDF Viewer Modal */}
                    {showPdfModal && (
                        <div className="fixed inset-0 z-[10000] bg-slate-900/80 backdrop-blur-sm flex items-center justify-center animate-in fade-in duration-300">
                            <div className="bg-slate-900 border border-slate-700 shadow-2xl rounded-xl w-[98%] h-[98vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-300">
                                {/* Header */}
                                <div className="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700 shrink-0">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-rose-500/20 p-1.5 rounded-lg border border-rose-500/30">
                                            <Icon name="picture_as_pdf" className="text-rose-400 text-[18px]" />
                                        </div>
                                        <div>
                                            <h3 className="text-slate-100 font-bold text-[14px] leading-tight m-0">{pdfTitle}</h3>
                                            <p className="text-slate-400 text-[10px] m-0 leading-tight mt-0.5">Secure Document Viewer</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <a href={pdfUrl} target="_blank" rel="noreferrer" className="p-2 hover:bg-slate-700 rounded-lg text-slate-300 hover:text-white transition-colors flex items-center justify-center" title="Open in New Tab">
                                            <Icon name="open_in_new" className="text-[18px]" />
                                        </a>
                                        <button onClick={() => setShowPdfModal(false)} className="p-2 hover:bg-rose-500/20 rounded-lg text-slate-300 hover:text-rose-400 transition-colors flex items-center justify-center" title="Close">
                                            <Icon name="close" className="text-[18px]" />
                                        </button>
                                    </div>
                                </div>
                                {/* Iframe */}
                                <div className="flex-1 bg-slate-950 relative">
                                    <iframe src={pdfUrl} className="w-full h-full border-none" title={pdfTitle}></iframe>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            );
        }

        function ReviewerWelcomeModal({ jobTitle, jdId, reidToken, onConfirm }) {
            const [empId, setEmpId]        = React.useState('');
            const [empInfo, setEmpInfo]    = React.useState(null);
            // idle | loading | authorized | not_found | unauthorized
            const [lookupState, setLookup] = React.useState('idle');
            const [errorMsg, setErrorMsg]  = React.useState('');
            const debounceRef              = React.useRef(null);

            const validate = React.useCallback(async (id) => {
                const trimmed = id.trim().toUpperCase();
                if (!trimmed) { setEmpInfo(null); setLookup('idle'); setErrorMsg(''); return; }
                setLookup('loading');
                setEmpInfo(null);
                try {
                    const res  = await fetch('../api/validate_reviewer.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ jd_id: jdId, emp_id: trimmed, reid_token: reidToken })
                    });
                    const json = await res.json();
                    if (json.valid) {
                        setEmpInfo(json.employee);
                        setLookup('authorized');
                        setErrorMsg('');
                    } else {
                        setLookup(json.code === 'not_found' ? 'not_found' : 'unauthorized');
                        setErrorMsg(json.message || 'Not authorized.');
                    }
                } catch(e) {
                    setLookup('not_found');
                    setErrorMsg('Connection error. Please try again.');
                }
            }, [jdId]);

            const handleChange = (e) => {
                const val = e.target.value;
                setEmpId(val);
                setEmpInfo(null);
                setErrorMsg('');
                clearTimeout(debounceRef.current);
                if (val.trim().length < 6) {
                    setLookup('idle');
                    return;
                }
                setLookup('idle');
                debounceRef.current = setTimeout(() => validate(val), 500);
            };

            const handleKeyDown = (e) => {
                if (e.key === 'Enter' && empId.trim().length >= 6) {
                    clearTimeout(debounceRef.current);
                    validate(empId);
                }
            };

            const handleConfirm = () => {
                if (lookupState !== 'authorized' || !empInfo) return;
                onConfirm({
                    name:        empInfo.full_name,
                    email:       empInfo.email,
                    empId:       empInfo.employee_id,
                    designation: empInfo.designation,
                    department:  empInfo.department
                });
            };

            const inputClass = `w-full px-3 py-3 pr-10 border rounded-xl text-[14px] font-bold tracking-wider focus:outline-none focus:ring-2 transition-colors uppercase ${
                lookupState === 'authorized'   ? 'border-emerald-400 bg-emerald-50 focus:ring-emerald-500/20' :
                lookupState === 'not_found'    ? 'border-rose-400 bg-rose-50 focus:ring-rose-500/20' :
                lookupState === 'unauthorized' ? 'border-amber-400 bg-amber-50 focus:ring-amber-500/20' :
                'border-slate-300 bg-white focus:ring-indigo-500/20 focus:border-indigo-500'
            }`;

            return (
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4" style={{background:'rgba(15,23,42,0.9)',backdropFilter:'blur(6px)'}}>
                    <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">

                        {/* Gradient header */}
                        <div style={{background:'linear-gradient(135deg,#4f46e5,#7c3aed)'}} className="px-6 py-5">
                            <div className="flex items-center gap-3">
                                <div className="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                                    <Icon name="rate_review" className="text-white text-[24px]" />
                                </div>
                                <div>
                                    <p className="text-indigo-200 text-[10px] font-bold uppercase tracking-widest leading-tight">Candidate Review Invitation</p>
                                    <h2 className="text-white font-black text-[16px] leading-snug mt-0.5">{jobTitle}</h2>
                                </div>
                            </div>
                        </div>

                        {/* Body */}
                        <div className="px-6 py-6">
                            <p className="text-slate-500 text-[12px] leading-relaxed mb-5">
                                You've been invited to review candidates for this position. Enter your <strong className="text-slate-700">Employee ID</strong> to verify your identity and continue.
                            </p>

                            {/* Employee ID input */}
                            <div className="mb-4">
                                <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Your Employee ID</label>
                                <div className="relative">
                                    <input
                                        type="text"
                                        value={empId}
                                        onChange={handleChange}
                                        onKeyDown={handleKeyDown}
                                        placeholder="e.g. MGI0123"
                                        autoFocus
                                        autoCapitalize="characters"
                                        className={inputClass}
                                    />
                                    <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                        {lookupState === 'loading'      && <div className="w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>}
                                        {lookupState === 'authorized'   && <Icon name="verified" className="text-emerald-500 text-[20px]" />}
                                        {lookupState === 'not_found'    && <Icon name="cancel" className="text-rose-500 text-[20px]" />}
                                        {lookupState === 'unauthorized' && <Icon name="lock" className="text-amber-500 text-[20px]" />}
                                    </div>
                                </div>

                                {/* Fixed-height feedback area — prevents layout shift */}
                                <div className="min-h-[32px] mt-1.5">
                                    {lookupState === 'not_found' && (
                                        <p className="text-rose-600 text-[11px] font-semibold flex items-center gap-1">
                                            <Icon name="error" className="text-[13px]" /> {errorMsg}
                                        </p>
                                    )}
                                    {lookupState === 'unauthorized' && (
                                        <div className="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                            <p className="text-amber-700 text-[11px] font-bold flex items-center gap-1 mb-0.5">
                                                <Icon name="lock" className="text-[13px]" /> Access Restricted
                                            </p>
                                            <p className="text-amber-600 text-[10px] leading-relaxed">{errorMsg}</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Employee info card — shown only when authorized */}
                            {lookupState === 'authorized' && empInfo && (
                                <div className="mb-5 bg-emerald-50 border border-emerald-200 rounded-xl p-4 animate-in fade-in duration-200">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center font-black text-white text-[13px] shrink-0">
                                            {empInfo.full_name.split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase()}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-1.5">
                                                <p className="font-black text-slate-800 text-[14px] leading-tight truncate">{empInfo.full_name}</p>
                                                <span className="bg-emerald-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded-full uppercase tracking-wide shrink-0">Verified</span>
                                            </div>
                                            <p className="text-slate-500 text-[11px] truncate">{empInfo.designation}{empInfo.department ? ` · ${empInfo.department}` : ''}</p>
                                            {empInfo.email && <p className="text-indigo-500 text-[10px] truncate mt-0.5">{empInfo.email}</p>}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Confirm button */}
                            <button
                                onClick={handleConfirm}
                                disabled={lookupState !== 'authorized'}
                                className={`w-full py-3 font-black text-[13px] rounded-xl transition-all uppercase tracking-wide flex items-center justify-center gap-2 ${
                                    lookupState === 'authorized'
                                        ? 'bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] text-white shadow-lg shadow-indigo-500/25'
                                        : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                }`}
                            >
                                <Icon name="check_circle" className="text-[18px]" />
                                {lookupState === 'authorized'
                                    ? `Continue as ${empInfo.full_name.split(' ')[0]}`
                                    : 'Enter Your Employee ID to Continue'}
                            </button>

                            <p className="text-[10px] text-slate-400 text-center mt-3 leading-relaxed">
                                Only the invited reviewer can access this form. Your identity will be recorded on submission.
                            </p>
                        </div>
                    </div>
                </div>
            );
        }

        function FeedbackCell({ candidate, isPublic, onFeedbackSave }) {
            const [localComment, setLocalComment] = useState(candidate.feedback_comment || '');
            const [localRecommended, setLocalRecommended] = useState(candidate.feedback_recommended == 1);
            const [saving, setSaving] = useState(false);

            const handleBlurSave = async () => {
                if (!onFeedbackSave) return;
                setSaving(true);
                await onFeedbackSave(candidate.id, localComment, localRecommended);
                setSaving(false);
            };

            const handleRecommendedChange = async (checked) => {
                setLocalRecommended(checked);
                if (!onFeedbackSave) return;
                setSaving(true);
                await onFeedbackSave(candidate.id, localComment, checked);
                setSaving(false);
            };

            if (!isPublic) {
                return (
                    <div className="flex flex-col gap-1">
                        {localRecommended && (
                            <span className="inline-flex items-center gap-1 bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase w-fit">
                                <Icon name="thumb_up" className="text-[10px]" /> Recommended
                            </span>
                        )}
                        {localComment ? (
                            <p className="text-[10px] text-slate-700 leading-relaxed m-0 whitespace-pre-wrap">{localComment}</p>
                        ) : (
                            <span className="text-[10px] text-slate-400 italic">No feedback yet</span>
                        )}
                    </div>
                );
            }

            return (
                <div className="flex flex-col gap-1.5">
                    <textarea
                        value={localComment}
                        onChange={(e) => setLocalComment(e.target.value)}
                        onBlur={handleBlurSave}
                        placeholder="Enter feedback for this candidate..."
                        rows={3}
                        className="w-full text-[10px] text-slate-700 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 resize-none bg-slate-50 focus:bg-white transition-all placeholder:text-slate-400"
                    />
                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-1.5 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                checked={localRecommended}
                                onChange={(e) => handleRecommendedChange(e.target.checked)}
                                className="w-3.5 h-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                            />
                            <span className={`text-[9px] font-black uppercase tracking-wide ${localRecommended ? 'text-emerald-600' : 'text-slate-400'}`}>
                                {localRecommended ? 'Recommended' : 'Mark Recommended'}
                            </span>
                        </label>
                        {saving && <span className="text-[9px] text-indigo-400 font-medium animate-pulse">Saving...</span>}
                    </div>
                </div>
            );
        }

        function CellContent({ id, candidate, sl, expanded, openPdf, jdId, onToggle, editingRowId, setEditingRowId, isPublic, onFeedbackSave }) {
            const isEditing = editingRowId === candidate.id;
            const formatDOB = (dob) => {
                if (!dob) return '-';
                const d = new Date(dob);
                if (isNaN(d)) return dob;
                return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).replace(/ /g, ' ');
            };
            if (id === 'feedback') return (
                <FeedbackCell candidate={candidate} isPublic={isPublic} onFeedbackSave={onFeedbackSave} />
            );
            if (id === 'sl') return (
                <div className="flex flex-col items-center gap-1.5">
                    <span className="font-black text-slate-900 text-[14px] leading-tight">{sl}</span>
                    <span className="bg-slate-100 border border-slate-300 px-1 py-0.5 rounded-md text-[9px] font-black uppercase text-slate-700 shadow-sm whitespace-nowrap">ID: {candidate.id}</span>
                </div>
            );
            if (id === 'candidate') {
                const isValidLink = (link) => link && link.toLowerCase() !== 'not mentioned' && link.trim() !== '';
                const formatLink = (link) => (!link.startsWith('http://') && !link.startsWith('https://') ? 'https://' + link : link);

                const renderBlocks = (val, type) => {
                    if (!val) return null;
                    const items = val.split(',').map(s => s.trim()).filter(s => s);
                    return items.map((item, idx) => (
                        <div key={idx} className={`
                            px-1.5 py-0.5 rounded text-[10px] font-medium border whitespace-nowrap
                            ${type === 'email' ? 'bg-slate-50 text-slate-800 border-slate-200' : 'bg-blue-50 text-blue-700 border-blue-200'}
                        `}>
                            {item}
                        </div>
                    ));
                };

                return (
                    <div className="flex flex-col gap-1 min-h-[1.5rem]">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-black text-indigo-700 text-[13px] leading-tight break-words flex-1 uppercase">{candidate.name}</span>
                            <button 
                                onClick={() => openPdf(`../api/view_cv.php?n8n_id=${candidate.n8n_id}&jd_id=${jdId}`, `CV: ${candidate.name}`)} 
                                className="p-0.5 hover:scale-110 transition-all shrink-0 active:scale-90 group relative"
                                title="View CV (PDF)"
                            >
                                <svg width="26" height="26" viewBox="0 0 45 55" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    {/* Document Outline */}
                                    <path d="M5 2C3.34 2 2 3.34 2 5V50C2 51.66 3.34 53 5 53H40C41.66 53 43 51.66 43 50V18L27 2H5Z" fill="white" stroke="#2D3436" strokeWidth="3" />
                                    <path d="M27 2V18H43" fill="white" stroke="#2D3436" strokeWidth="3" strokeLinejoin="round"/>
                                    
                                    {/* Adobe Logo */}
                                    <path d="M26.5 32.5c-.2-.6-.7-1.2-1.6-1.9-2.3-1.9-5.4-3.4-6.8-4.1.8-3.4-.1-7.6-.8-7.6-.5 0-.8.6-.8 1.4 0 1.9 1.4 6.5 2.5 9.3-1.4 4.2-3.5 8.4-5.4 11.8-1.6.8-3.7 1.9-4.7 1.9-.5 0-.8-.3-.8-.9 0-1.1 1.6-3.4 4.5-5.6.2-.1.2-.4 0-.5-.6-.3-1.2-.6-1.8-.9-.2-.1-.4.1-.3.3 0 0-2.6 7.9-1.1 9.7.5.6 1.2.9 2 .9 2.1 0 5.1-2 7.7-4 3 .9 6.7 1.8 8.7 1.8 1.2 0 1.6-.3 1.6-.9 0-.6-.9-2.4-3.9-5 .2-.1.4-.2.5-.2 1.2-.5 2.6-1.1 3.2-1.6.6-.5.7-1.1.5-1.8-.2-.3-.6-.8-1.2-1.3z" fill="#E53935" transform="translate(1, -1)" />
                                    
                                    {/* PDF Text */}
                                    <text x="22" y="48" fontSize="11" fontWeight="900" fill="#2D3436" textAnchor="middle" fontFamily="Arial, sans-serif">PDF</text>
                                    
                                    {/* Download Arrow Overlay */}
                                    <path d="M35 1L35 15M35 15L28 8M35 15L42 8" stroke="#FF0000" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </button>
                        </div>
                        <div className={`
                            flex flex-col gap-1 mt-1 border-t border-dashed border-indigo-100 pt-1 animate-in slide-in-from-top-1 duration-300
                            ${expanded ? 'block' : 'hidden group-hover:block'}
                        `}>
                            <div className="flex flex-wrap gap-1.5">
                                {renderBlocks(candidate.email_id, 'email')}
                                {renderBlocks(candidate.phone, 'phone')}
                            </div>
                            <div className="flex items-center justify-between mt-1">
                                <div className="flex items-center gap-2.5">
                                    {isValidLink(candidate.github_link) ? (
                                        <a href={formatLink(candidate.github_link)} target="_blank" rel="noreferrer" title="GitHub" className="text-slate-800 hover:text-black hover:scale-110 transition-transform">
                                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.2c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
                                        </a>
                                    ) : (
                                        <div title="GitHub Not Available" className="text-slate-300 cursor-not-allowed">
                                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.2c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
                                        </div>
                                    )}
                                    {isValidLink(candidate.linkedin_link) ? (
                                        <a href={formatLink(candidate.linkedin_link)} target="_blank" rel="noreferrer" title="LinkedIn" className="text-[#0a66c2] hover:text-blue-800 hover:scale-110 transition-transform">
                                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg>
                                        </a>
                                    ) : (
                                        <div title="LinkedIn Not Available" className="text-slate-300 cursor-not-allowed">
                                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg>
                                        </div>
                                    )}
                                </div>
                                <span className="bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md text-[10px] font-black uppercase text-indigo-600 shadow-sm">UID: {candidate.n8n_id}</span>
                            </div>
                        </div>
                    </div>
                );
            }
            if (id === 'date_of_birth') return <span className="font-medium text-slate-900 text-[11px]">{formatDOB(candidate.date_of_birth)}</span>;
            if (id === 'total_experience') return <span className="font-bold text-slate-900">{candidate.total_experience}y</span>;
            if (id === 'expected_salary') return <span className="font-bold text-slate-900">৳{parseFloat(candidate.expected_salary).toLocaleString()}</span>;
            if (id === 'match') {
                const val = candidate.match || '0';
                const displayVal = val.toString().includes('%') ? val : `${val}%`;
                return (
                    <span className={`px-2 py-0.5 rounded-full text-[9px] font-black border ${parseFloat(val) >= 70 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'}`}>
                        {displayVal}
                    </span>
                );
            }
            if (id === 'reason_for_rating') return (
                <div className="text-[10px] leading-relaxed text-slate-800 font-medium">
                    {candidate.reason_for_rating || '-'}
                </div>
            );
            if (id === 'shortlisted') {
                const isChecked = candidate.shortlisted == 1;
                return (
                    <div className="flex items-center justify-center">
                        <button 
                            disabled={!isEditing}
                            onClick={() => onToggle(candidate.id, 'shortlisted', isChecked ? 0 : 1)}
                            className={`
                                w-9 h-9 rounded-lg flex items-center justify-center transition-all duration-200 border-2 shadow-sm
                                ${isChecked ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-slate-50 border-slate-200 text-transparent'}
                                ${isEditing ? 'cursor-pointer hover:border-emerald-400 hover:scale-105 active:scale-95' : 'cursor-default opacity-60'}
                            `}
                        >
                            <Icon name="check" className="text-[32px] font-black" />
                        </button>
                    </div>
                );
            }
            if (id === 'confirmation') {
                const isChecked = candidate.confirmation == 1;
                return (
                    <div className="flex items-center justify-center">
                        <button 
                            disabled={!isEditing}
                            onClick={() => onToggle(candidate.id, 'confirmation', isChecked ? 0 : 1)}
                            className={`
                                w-9 h-9 rounded-lg flex items-center justify-center transition-all duration-200 border-2 shadow-sm
                                ${isChecked ? 'bg-blue-500 border-blue-500 text-white' : 'bg-slate-50 border-slate-200 text-transparent'}
                                ${isEditing ? 'cursor-pointer hover:border-blue-400 hover:scale-105 active:scale-95' : 'cursor-default opacity-60'}
                            `}
                        >
                            <Icon name="check" className="text-[32px] font-black" />
                        </button>
                    </div>
                );
            }
            if (id === 'actions') return (
                <div className="flex items-center justify-center">
                    {isEditing ? (
                        <button 
                            onClick={() => setEditingRowId(null)}
                            className="w-11 h-11 bg-indigo-600 hover:bg-indigo-700 rounded-xl flex items-center justify-center transition-all text-white shadow-lg shadow-indigo-100 active:scale-95" 
                            title="Save Changes"
                        >
                            <Icon name="check" className="text-[32px] font-bold" />
                        </button>
                    ) : (
                        <button 
                            onClick={() => setEditingRowId(candidate.id)}
                            className="w-11 h-11 bg-white hover:bg-slate-50 rounded-xl flex items-center justify-center transition-all text-slate-500 hover:text-indigo-600 border border-slate-200 hover:border-indigo-200 shadow-sm active:scale-95 group" 
                            title="Edit Status"
                        >
                            <Icon name="edit" className="text-[32px] group-hover:rotate-12 transition-transform" />
                        </button>
                    )}
                </div>
            );
            return <span className="block text-slate-800 font-medium">{candidate[id] || '-'}</span>;
        }

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<Dashboard />);
    </script>

    <?php include("../includes/modals.php"); ?>
    <script src="../js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
