const {
  useState,
  useEffect,
  useMemo,
  useCallback,
  useRef
} = React;

// --- Icons Shorthand ---
const Icon = ({
  name,
  className = ""
}) => {
  return /*#__PURE__*/React.createElement("i", {
    className: `material-icons ${className}`,
    style: {
      fontSize: 'inherit'
    }
  }, name);
};

// Shared Components
/**
 * Modern React-based Sidebar
 * This file is intended to be included within a <script type="text/babel"> block.
 */

const Sidebar = ({
  showSidebar,
  setShowSidebar,
  isAdmin,
  zoom,
  adjustZoom,
  isPublic,
  perms,
  username,
  rootAdminId
}) => {
  const basePath = window.location.pathname.includes('/view/') ? '../' : './';
  const isRoot = username && rootAdminId && username === rootAdminId;

  // Helper: check if user has a specific permission
  const hasPerm = perm => {
    if (isRoot) return true; // Root admin sees everything
    if (!perms) return false; // Permissions not loaded yet â€” hide until we know
    const v = perms[perm];
    return v === true || v === "true" || v === 1 || v === "1" || v === "on";
  };

  // System Users: either manage_users OR create_user
  const hasUserAccess = isRoot || hasPerm('manage_users') || hasPerm('create_user');
  return /*#__PURE__*/React.createElement(React.Fragment, null, showSidebar && /*#__PURE__*/React.createElement("div", {
    className: "fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] transition-opacity",
    onClick: () => setShowSidebar(false)
  }), /*#__PURE__*/React.createElement("div", {
    className: `fixed top-0 left-0 bottom-0 w-[240px] bg-white shadow-2xl z-[101] transform transition-transform duration-300 ease-in-out flex flex-col ${showSidebar ? 'translate-x-0' : '-translate-x-full'}`
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between px-4 py-2 border-b border-slate-100 shrink-0"
  }, /*#__PURE__*/React.createElement("h2", {
    className: "text-[15px] font-bold text-indigo-600 flex items-center gap-2"
  }, "Menu"), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowSidebar(false),
    className: "p-1 rounded-full hover:bg-slate-100 text-slate-500 transition-colors shrink-0"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "close",
    className: "text-[18px]"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "flex-1 overflow-y-auto p-2 space-y-0"
  }, /*#__PURE__*/React.createElement("a", {
    href: `${basePath}index.php`,
    className: "flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 font-bold text-[13px] mb-1 transition-colors hover:bg-indigo-100"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "home",
    className: "text-[16px]"
  }), " Home"), /*#__PURE__*/React.createElement("div", {
    className: "space-y-0"
  }, hasPerm('manage_tasks') && /*#__PURE__*/React.createElement("a", {
    href: `${basePath}tasks.php`,
    className: "flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "task",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " Task Management"), hasUserAccess && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleUserManager) window.toggleUserManager();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "manage_accounts",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " System Users"), hasPerm('db_control') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleDbControl) window.toggleDbControl();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "storage",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " DB Control"), hasPerm('manage_employees') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleEmployeeManager) window.toggleEmployeeManager();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "badge",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " MGI Employees"), hasPerm('manage_statuses') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleStatusManager) window.toggleStatusManager();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "settings_suggest",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " Manage Statuses"), hasPerm('manage_sources') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleSourceManager) window.toggleSourceManager();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "share",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " Manage Sources"), hasPerm('manage_rpa') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleRpaConfig) window.toggleRpaConfig();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "settings_remote",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " RPA Config"), hasPerm('manage_task_limits') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleTaskLimitsModal) window.toggleTaskLimitsModal();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "block",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " Task Limits"), hasPerm('manage_server_allocation') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleServerAllocation) window.toggleServerAllocation();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "vibration",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " Server Allocation"), hasPerm('view_user_activity') && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      if (window.toggleUserActivity) window.toggleUserActivity();
    },
    className: "w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "history",
    className: "text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors"
  }), " User Activity")), /*#__PURE__*/React.createElement("div", {
    className: "mt-2 mb-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2"
  }, "Theme Settings"), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between mb-2"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "dark_mode",
    className: "text-[16px] text-indigo-600"
  }), /*#__PURE__*/React.createElement("span", {
    className: "text-[12px] font-bold text-slate-800"
  }, "Dark Mode")), /*#__PURE__*/React.createElement("div", {
    className: "w-8 h-5 bg-slate-300 rounded-full relative cursor-pointer hover:bg-slate-400 transition-colors",
    title: "Dark Mode (Not Available in Modern View)"
  }, /*#__PURE__*/React.createElement("div", {
    className: "absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "zoom_in",
    className: "text-[16px] text-indigo-600"
  }), /*#__PURE__*/React.createElement("span", {
    className: "text-[12px] font-bold text-slate-800"
  }, "Zoom")), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2 border border-slate-200 bg-white rounded-lg px-2 py-1 shadow-sm"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => adjustZoom(-0.1),
    className: "text-[14px] text-slate-400 hover:text-indigo-600 transition-colors flex items-center justify-center p-0.5 active:scale-90"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "remove"
  })), /*#__PURE__*/React.createElement("span", {
    className: "text-[11px] font-bold text-indigo-600 w-9 text-center"
  }, Math.round(zoom * 100), "%"), /*#__PURE__*/React.createElement("button", {
    onClick: () => adjustZoom(0.1),
    className: "text-[14px] text-slate-400 hover:text-indigo-600 transition-colors flex items-center justify-center p-0.5 active:scale-90"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "add"
  }))))))));
};
const DEFAULT_COLUMNS = [{
  id: 'sl',
  label: 'SL/ID',
  width: 55,
  align: 'center',
  sticky: true
}, {
  id: 'candidate',
  label: 'Candidate',
  width: 300,
  align: 'left',
  sticky: true
}, {
  id: 'location',
  label: 'Location',
  width: 150,
  align: 'left'
}, {
  id: 'date_of_birth',
  label: 'DOB',
  width: 90,
  align: 'center'
}, {
  id: 'Previous_Companies',
  label: 'Prev. Companies',
  width: 180,
  align: 'left'
}, {
  id: 'Current_Position',
  label: 'Curr. Position',
  width: 180,
  align: 'left'
}, {
  id: 'organization',
  label: 'Organization',
  width: 150,
  align: 'left'
}, {
  id: 'education',
  label: 'Education',
  width: 160,
  align: 'left'
}, {
  id: 'educational_institute',
  label: 'Institute',
  width: 160,
  align: 'left'
}, {
  id: 'total_experience',
  label: 'Exp.',
  width: 60,
  align: 'center'
}, {
  id: 'expected_salary',
  label: 'Exp. Sal',
  width: 100,
  align: 'center'
}, {
  id: 'skills',
  label: 'Skills',
  width: 300,
  align: 'left'
}, {
  id: 'strength',
  label: 'Strength',
  width: 250,
  align: 'left'
}, {
  id: 'weakness',
  label: 'Weakness',
  width: 250,
  align: 'left'
}, {
  id: 'match',
  label: 'Match',
  width: 70,
  align: 'center'
}, {
  id: 'reason_for_rating',
  label: 'Rating Reason',
  width: 400,
  align: 'left'
}, {
  id: 'feedback',
  label: 'Feedback',
  width: 220,
  align: 'left'
}, {
  id: 'shortlisted',
  label: 'Status',
  width: 60,
  align: 'center'
}, {
  id: 'confirmation',
  label: 'Conf.',
  width: 60,
  align: 'center'
}, {
  id: 'actions',
  label: 'Actions',
  width: 80,
  align: 'center'
}];
function mergeColumnsWithDefaults(sourceCols, isPublic) {
  const effectiveDefaults = isPublic ? DEFAULT_COLUMNS.filter(c => c.id !== 'actions' && c.id !== 'shortlisted' && c.id !== 'confirmation') : DEFAULT_COLUMNS;
  if (!sourceCols) return effectiveDefaults;
  const merged = sourceCols.map(s => {
    const def = effectiveDefaults.find(d => d.id === s.id);
    if (!def) return null;
    return {
      ...def,
      ...s,
      label: def.label,
      width: Math.max(s.width || def.width, 60)
    };
  }).filter(Boolean);
  effectiveDefaults.forEach(def => {
    if (!merged.find(m => m.id === def.id)) merged.push(def);
  });
  return merged;
}
function Dashboard() {
  const isPublic = window.isPublicMode;
  const isAdmin = window.isAdmin;
  const userName = window.currentUserName;
  const userRole = window.currentUserRoleLabel;
  const userProfilePic = window.currentUserProfilePic;
  const userEmpId = window.currentUserEmpId;

  // Helper to merge source cols with default definitions to ensure all props exist
  const mergeWithDefaults = useCallback(sourceCols => {
    return mergeColumnsWithDefaults(sourceCols, isPublic);
  }, [isPublic]);
  const [canSendMail, setCanSendMail] = useState(window.canSendMailInit);
  const [canManageGlobalLayouts, setCanManageGlobalLayouts] = useState(window.canManageGlobalLayouts);
  const [hasGlobalUpdate, setHasGlobalUpdate] = useState(false);
  const [globalCols, setGlobalCols] = useState(null);
  const [draggedIndex, setDraggedIndex] = useState(null);

  // Real-time Permission Sync
  useEffect(() => {
    if (window.isPublicMode) return;
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
              mailPerm = val === true || val === "true" || val === 1 || val === "1" || val === "on";
            } else if (isSuper) {
              mailPerm = true;
            }

            // Global Layout Permission
            if (perms.hasOwnProperty('manage_global_layouts')) {
              const val = perms['manage_global_layouts'];
              globalPerm = val === true || val === "true" || val === 1 || val === "1" || val === "on";
            } else if (isSuper) {
              globalPerm = true;
            }
          }
          setCanSendMail(mailPerm && !window.isPublicMode);
          setCanManageGlobalLayouts(globalPerm);

          // Keep sidebar in sync with real-time permission changes
          window.dispatchEvent(new CustomEvent('permissionsLoaded', {
            detail: {
              perms: perms,
              username: data.username,
              rootAdminId: data.root_admin_id
            }
          }));
        }
      } catch (e) {
        console.warn("Permission sync failed", e);
      }
    };
    const interval = setInterval(checkPerms, 3000);
    return () => clearInterval(interval);
  }, []);

  // Job Metadata from PHP
  const taskNo = window.jobTaskNo;
  const creatorName = window.jobCreatorName;
  // Sidebar permissions â€” seeded from PHP session to avoid flash
  const [sidebarPerms, setSidebarPerms] = useState(window.currentUserPermissions || null);
  const [sidebarUser, setSidebarUser] = useState({
    username: window.currentUser && window.currentUser.id || '',
    rootAdminId: window.rootAdminId || ''
  });
  useEffect(() => {
    const handlePerms = e => {
      setSidebarPerms(e.detail.perms);
      setSidebarUser({
        username: e.detail.username,
        rootAdminId: e.detail.rootAdminId
      });
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
  const adjustZoom = delta => {
    setZoom(prev => Math.max(0.5, Math.min(1.5, Math.round((prev + delta) * 10) / 10)));
  };
  const [candidates, setCandidates] = useState([]);
  const [loading, setLoading] = useState(true);
  const [expandedAll, setExpandedAll] = useState(true);
  const [columns, setColumns] = useState(() => {
    // Read localStorage synchronously so first render already uses saved layout
    if (!isPublic) {
      const saved = localStorage.getItem('modern_dashboard_columns_v5');
      if (saved) {
        try {
          return mergeColumnsWithDefaults(JSON.parse(saved), false);
        } catch (e) {}
      }
    }
    return isPublic ? DEFAULT_COLUMNS.filter(c => c.id !== 'actions' && c.id !== 'shortlisted' && c.id !== 'confirmation') : DEFAULT_COLUMNS;
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
      // 1. Fetch Global from server (use prefetch if dashboard.php started it early)
      let serverGlobal = null;
      try {
        const result = window.__prefetchedColumns ? await window.__prefetchedColumns.finally(() => {
          window.__prefetchedColumns = null;
        }) : await fetch('../api/column_templates.php?action=get_global').then(r => r.json());
        if (result.status === 'success' && result.data) {
          serverGlobal = result.data;
          setGlobalCols(serverGlobal);
        }
      } catch (e) {
        console.error("Global load failed", e);
      }

      // 2. Check local
      const saved = localStorage.getItem('modern_dashboard_columns_v5');
      if (saved) {
        try {
          const savedCols = JSON.parse(saved);
          const finalCols = mergeWithDefaults(savedCols);

          // Check if global layout has been UPDATED on server since last sync/ack
          if (serverGlobal) {
            const globalSig = JSON.stringify(mergeWithDefaults(serverGlobal).map(c => ({
              id: c.id,
              h: !!c.hidden,
              w: c.width
            })));
            const ackSig = localStorage.getItem('acknowledged_global_sig');
            if (ackSig) {
              if (globalSig !== ackSig) {
                setHasGlobalUpdate(true);
              }
            } else {
              // First time: if local is different from global, show sync once, 
              // or just acknowledge if they just started.
              const localSig = JSON.stringify(finalCols.map(c => ({
                id: c.id,
                h: !!c.hidden,
                w: c.width
              })));
              if (localSig !== globalSig) {
                setHasGlobalUpdate(true);
              } else {
                localStorage.setItem('acknowledged_global_sig', globalSig);
              }
            }
          }
          setColumns(finalCols);
          return;
        } catch (e) {
          console.error(e);
        }
      }

      // 3. Fallback to Global or Default
      // Also cache to localStorage so next visit useState reads it instantly (no flash)
      const finalFallback = mergeWithDefaults(serverGlobal);
      if (serverGlobal) {
        localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(finalFallback));
        const globalSig = JSON.stringify(finalFallback.map(c => ({
          id: c.id,
          h: !!c.hidden,
          w: c.width
        })));
        localStorage.setItem('acknowledged_global_sig', globalSig);
      }
      setColumns(finalFallback);
    };
    initColumns();
  }, [mergeWithDefaults]);
  const applyGlobal = () => {
    if (!globalCols) return;
    const finalCols = mergeWithDefaults(globalCols);
    const globalSig = JSON.stringify(finalCols.map(c => ({
      id: c.id,
      h: !!c.hidden,
      w: c.width
    })));
    setColumns(finalCols);
    localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(finalCols));
    localStorage.setItem('acknowledged_global_sig', globalSig);
    setHasGlobalUpdate(false);
    Swal.fire({
      icon: 'success',
      title: 'Layout Synced',
      text: 'You are now using the latest global layout.',
      timer: 2000,
      showConfirmButton: false
    });
  };
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [confFilter, setConfFilter] = useState('');
  const [topN, setTopN] = useState('');
  const [sortBy, setSortBy] = useState('match');
  const [sortOrder, setSortOrder] = useState('DESC');
  const [pagination, setPagination] = useState({
    current_page: 1,
    total_pages: 1,
    total_records: 0
  });
  const concernEmail = window.jobConcernEmail;
  const concernName = window.jobConcernName;
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
      // Use prefetched promise on first default load to avoid double-fetch
      const isDefaultLoad = page === 1 && !search && !statusFilter && !confFilter && !topN && sortBy === 'match' && sortOrder === 'DESC';
      let jsonPromise;
      if (isDefaultLoad && window.__prefetchedCandidates) {
        jsonPromise = window.__prefetchedCandidates;
        window.__prefetchedCandidates = null;
      } else {
        jsonPromise = fetch(`../api/get_candidates.php?jd_id=${jdId}&search=${search}&page=${page}&shortlisted=${statusFilter}&confirmation=${confFilter}&sort_by=${sortBy}&sort_order=${sortOrder}&top_n=${topN}`).then(r => r.json());
      }
      const json = await jsonPromise;
      if (json.status === 'success') {
        setCandidates(json.data || []);
        setPagination(json.pagination || {
          current_page: 1,
          total_pages: 1
        });
      }
    } catch (e) {
      console.error(e);
    }
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
        } catch (e) {
          console.error(e);
        }
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
            headers: {
              'Content-Type': 'application/json'
            },
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
          text: result.value.message || 'Failed to send email. Please check SMTP settings.'
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
      } catch (e) {
        console.error(e);
      }
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
    setCandidates(prev => prev.map(c => c.id === candidateId ? {
      ...c,
      [field]: value
    } : c));
    try {
      await fetch('../api/update_status.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          id: candidateId,
          field,
          value
        })
      });
    } catch (e) {
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
    } catch (e) {
      return null;
    }
  });
  const confirmReviewerIdentity = identity => {
    sessionStorage.setItem(sessionKey, JSON.stringify(identity));
    setReviewerIdentity(identity);
  };
  const saveFeedback = useCallback(async (candidateId, comment, recommended) => {
    try {
      const response = await fetch('../api/save_feedback.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          candidate_id: candidateId,
          jd_id: jdId,
          feedback_comment: comment,
          feedback_recommended: recommended ? 1 : 0
        })
      });
      const json = await response.json();
      if (json.status === 'success') {
        setCandidates(prev => prev.map(c => c.id === candidateId ? {
          ...c,
          feedback_comment: comment,
          feedback_recommended: recommended ? 1 : 0
        } : c));
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
      html: isResubmit ? `<p style="font-size:0.9rem;color:#475569;">You have already submitted <strong>${submitState.submissionCount}</strong> time(s). This will create submission #${submitState.submissionCount + 1} and re-notify the task creator.</p>` : '<p style="font-size:0.9rem;color:#475569;">This will finalize all feedback and send a notification email to the task creator.</p>',
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
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              jd_id: jdId,
              reviewer_name: reviewerIdentity?.name || '',
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
        Swal.fire({
          icon: 'error',
          title: 'Submission Failed',
          text: val.message || 'Something went wrong.'
        });
      }
    }
  };
  const handleSort = id => {
    if (sortBy === id) {
      setSortOrder(prev => prev === 'ASC' ? 'DESC' : 'ASC');
    } else {
      setSortBy(id);
      setSortOrder('DESC');
    }
  };
  const handleResize = (id, delta) => {
    setColumns(prev => {
      const next = prev.map(col => col.id === id ? {
        ...col,
        width: Math.max(40, col.width + delta)
      } : col);
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
    const name = isGlobal ? 'GLOBAL_DEFAULT' : newTemplateName.trim() || 'Default';
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
          const updated = {
            ...templates,
            [name]: columns
          };
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
  const loadTemplate = name => {
    const template = templates[name];
    if (template) {
      setColumns(template);
      localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(template));
    }
  };
  const deleteTemplate = name => {
    const updated = {
      ...templates
    };
    delete updated[name];
    setTemplates(updated);
    localStorage.setItem('modern_dashboard_templates', JSON.stringify(updated));
  };
  const toggleColumn = id => {
    setColumns(prev => {
      const next = prev.map(col => col.id === id ? {
        ...col,
        hidden: !col.hidden
      } : col);
      localStorage.setItem('modern_dashboard_columns_v5', JSON.stringify(next));
      return next;
    });
  };
  const moveColumn = (id, direction) => {
    setColumns(prev => {
      const index = prev.findIndex(c => c.id === id);
      if (index === 0 && direction === -1 || index === prev.length - 1 && direction === 1) return prev;
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
  const getStickyOffset = id => {
    let offset = 0;
    for (const col of activeColumns) {
      if (col.id === id) break;
      if (col.sticky) offset += col.width;
    }
    return offset;
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col h-full bg-slate-50 relative text-slate-800"
  }, showMailModal && /*#__PURE__*/React.createElement("div", {
    className: "absolute inset-0 z-[110] flex items-center justify-center bg-slate-900/40 backdrop-blur-md p-6"
  }, /*#__PURE__*/React.createElement("div", {
    className: "bg-white rounded-3xl shadow-2xl w-full max-w-xl flex flex-col max-h-[85vh] animate-in zoom-in-95 duration-200 overflow-hidden border border-white/20"
  }, /*#__PURE__*/React.createElement("div", {
    className: "p-6 border-b border-slate-100 bg-gradient-to-r from-indigo-600 to-violet-600"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between"
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h2", {
    className: "text-white font-black uppercase tracking-wider text-lg"
  }, "Send Candidate List"), /*#__PURE__*/React.createElement("p", {
    className: "text-indigo-100 text-[11px] font-medium"
  }, "Select a recipient to deliver the filtered report.")), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowMailModal(false),
    className: "text-white/70 hover:text-white transition-colors p-2 hover:bg-white/10 rounded-full"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "close",
    className: "w-6 h-6"
  })))), /*#__PURE__*/React.createElement("div", {
    className: "p-6 flex flex-col gap-5 bg-slate-50 flex-1 overflow-hidden"
  }, /*#__PURE__*/React.createElement("div", {
    className: "grid grid-cols-2 gap-4"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col gap-1.5"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between ml-1"
  }, /*#__PURE__*/React.createElement("label", {
    className: "text-[10px] font-black text-slate-400 uppercase tracking-widest"
  }, "1. Filter by Department"), (selectedDept || deptSearch) && /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      setSelectedDept('');
      setDeptSearch('');
    },
    className: "text-[9px] font-bold text-rose-500 hover:text-rose-700 uppercase transition-colors"
  }, "Clear")), /*#__PURE__*/React.createElement("div", {
    className: "relative"
  }, /*#__PURE__*/React.createElement("div", {
    className: "relative"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "business",
    className: "absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4"
  }), /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Type department...",
    className: "w-full bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-[12px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600/20 focus:border-indigo-600 transition-all outline-none shadow-sm",
    value: deptSearch,
    onFocus: () => setShowDeptList(true),
    onChange: e => {
      setDeptSearch(e.target.value);
      if (!e.target.value) setSelectedDept('');
    }
  })), showDeptList && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "fixed inset-0 z-10",
    onClick: () => setShowDeptList(false)
  }), /*#__PURE__*/React.createElement("div", {
    className: "absolute top-full left-0 right-0 mt-2 bg-white border border-slate-200 rounded-2xl shadow-xl z-20 max-h-[250px] overflow-y-auto p-1 animate-in slide-in-from-top-2 duration-200"
  }, /*#__PURE__*/React.createElement("div", {
    className: "p-2.5 hover:bg-slate-50 rounded-xl cursor-pointer text-[12px] font-bold text-slate-500 border-b border-slate-50 mb-1",
    onClick: () => {
      setSelectedDept('');
      setDeptSearch('');
      setShowDeptList(false);
    }
  }, "All Departments"), allDepts.filter(d => !deptSearch || d.toLowerCase().includes(deptSearch.toLowerCase())).sort((a, b) => {
    if (!deptSearch) return 0;
    const aName = a.toLowerCase();
    const bName = b.toLowerCase();
    const s = deptSearch.toLowerCase();
    const aScore = aName.startsWith(s) ? 2 : 1;
    const bScore = bName.startsWith(s) ? 2 : 1;
    return bScore - aScore;
  }).map(d => /*#__PURE__*/React.createElement("div", {
    key: d,
    className: `p-2.5 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl cursor-pointer text-[12px] font-bold transition-all ${selectedDept === d ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700'}`,
    onClick: () => {
      setSelectedDept(d);
      setDeptSearch(d);
      setShowDeptList(false);
    }
  }, d)))))), /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col gap-1.5"
  }, /*#__PURE__*/React.createElement("label", {
    className: "text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1"
  }, "2. Search Name/ID"), /*#__PURE__*/React.createElement("div", {
    className: "relative"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "search",
    className: "absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4"
  }), /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Search employee...",
    className: "w-full bg-white border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-[12px] font-bold text-slate-700 focus:ring-2 focus:ring-indigo-600/20 focus:border-indigo-600 transition-all outline-none shadow-sm",
    value: mailSearch,
    onChange: e => setMailSearch(e.target.value)
  }), mailSearch && /*#__PURE__*/React.createElement("button", {
    onClick: () => setMailSearch(''),
    className: "absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500 transition-colors"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "cancel",
    className: "w-4 h-4"
  }))))), /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col gap-2 flex-1 overflow-hidden"
  }, /*#__PURE__*/React.createElement("label", {
    className: "text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1"
  }, "3. Select Employee"), /*#__PURE__*/React.createElement("div", {
    className: "bg-white border border-slate-200 rounded-2xl flex-1 overflow-y-auto p-2 shadow-inner min-h-[200px]"
  }, mailLoading ? /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col items-center justify-center h-full py-10"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-3"
  }), /*#__PURE__*/React.createElement("p", {
    className: "text-[11px] font-bold text-slate-500 uppercase tracking-widest"
  }, "Loading Employees...")) : allEmployees.filter(e => !selectedDept || e.department === selectedDept || e.sub_department === selectedDept).filter(e => {
    if (!mailSearch) return true;
    const s = mailSearch.toLowerCase();
    return e.full_name.toLowerCase().includes(s) || e.employee_id.includes(s) || e.department && e.department.toLowerCase().includes(s) || e.designation && e.designation.toLowerCase().includes(s);
  }).filter(e => e.email).sort((a, b) => {
    if (!mailSearch) return 0;
    const s = mailSearch.toLowerCase();
    const getScore = emp => {
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
  }).map(emp => /*#__PURE__*/React.createElement("div", {
    key: emp.employee_id,
    onClick: () => sendMailToConcern({
      email: emp.email,
      name: emp.full_name
    }),
    className: "flex items-center gap-4 p-3 hover:bg-indigo-50 rounded-xl cursor-pointer transition-all border border-transparent hover:border-indigo-100 group mb-1"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-indigo-600 font-black text-xs shrink-0 group-hover:bg-white transition-colors"
  }, emp.full_name.charAt(0)), /*#__PURE__*/React.createElement("div", {
    className: "flex-1 min-w-0"
  }, /*#__PURE__*/React.createElement("p", {
    className: "text-[13px] font-black text-slate-800 truncate"
  }, emp.full_name), /*#__PURE__*/React.createElement("p", {
    className: "text-[10px] font-bold text-slate-500 uppercase tracking-tight truncate"
  }, emp.designation, " â€¢ ", emp.department)), /*#__PURE__*/React.createElement("div", {
    className: "bg-slate-50 group-hover:bg-indigo-600 group-hover:text-white p-2 rounded-lg transition-all"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "chevron_right",
    className: "w-4 h-4"
  })))), !mailLoading && allEmployees.filter(e => e.email).length === 0 && /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col items-center justify-center h-full py-10 text-slate-400"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "person_search",
    className: "w-10 h-10 mb-2 opacity-20"
  }), /*#__PURE__*/React.createElement("p", {
    className: "text-[11px] font-medium"
  }, "No employees found with an email address."))))))), showSettings && /*#__PURE__*/React.createElement("div", {
    className: "absolute inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-6"
  }, /*#__PURE__*/React.createElement("div", {
    className: "bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-row max-h-[90vh] animate-in zoom-in duration-200 overflow-hidden border border-slate-200"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-1/3 border-r border-slate-100 bg-slate-50 p-4 flex flex-col gap-3"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "text-[10px] font-black text-slate-400 uppercase tracking-widest"
  }, "Saved Designs"), /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col gap-1.5 flex-1 overflow-y-auto pr-1"
  }, Object.keys(templates).length === 0 ? /*#__PURE__*/React.createElement("p", {
    className: "text-[10px] text-slate-400 italic"
  }, "No saved designs yet.") : Object.keys(templates).map(name => /*#__PURE__*/React.createElement("div", {
    key: name,
    className: "flex items-center justify-between group bg-white p-2 rounded-lg border border-slate-200 hover:border-indigo-600 transition-all cursor-pointer shadow-sm",
    onClick: () => loadTemplate(name)
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-[10px] font-bold text-slate-700 truncate"
  }, name), /*#__PURE__*/React.createElement("button", {
    onClick: e => {
      e.stopPropagation();
      deleteTemplate(name);
    },
    className: "text-slate-300 hover:text-rose-600 transition-colors"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "delete",
    className: "w-3.5 h-3.5"
  }))))), /*#__PURE__*/React.createElement("div", {
    className: "pt-3 border-t border-slate-200 flex flex-col gap-2"
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Design Name...",
    className: "w-full px-3 py-1.5 text-[11px] bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600/20",
    value: newTemplateName,
    onChange: e => setNewTemplateName(e.target.value)
  }), /*#__PURE__*/React.createElement("button", {
    onClick: () => saveTemplate(false),
    className: "w-full bg-indigo-600 text-white py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-md"
  }, "Save Current"), canManageGlobalLayouts && /*#__PURE__*/React.createElement("button", {
    onClick: () => saveTemplate(true),
    className: "w-full bg-emerald-600 text-white py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-md mt-1"
  }, "Save Globally"))), /*#__PURE__*/React.createElement("div", {
    className: "flex-1 flex flex-col bg-white"
  }, /*#__PURE__*/React.createElement("div", {
    className: "p-3 border-b border-slate-100 flex items-center justify-between bg-white"
  }, /*#__PURE__*/React.createElement("h2", {
    className: "font-black text-slate-800 uppercase tracking-tight text-[13px]"
  }, "Customize Columns"), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowSettings(false),
    className: "text-slate-400 hover:text-slate-600 transition-colors"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "close",
    className: "text-lg"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "p-3 overflow-y-auto flex-1 flex flex-col gap-1.5 bg-white select-none"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between mb-1"
  }, /*#__PURE__*/React.createElement("p", {
    className: "text-[10px] font-black text-slate-400 uppercase tracking-widest"
  }, "Visibility & Sequence"), /*#__PURE__*/React.createElement("span", {
    className: "text-[9px] text-slate-400 font-bold bg-slate-100 px-2 py-0.5 rounded-full"
  }, "Hold & Drag to Reorder")), columns.map((col, idx) => /*#__PURE__*/React.createElement("div", {
    key: col.id,
    draggable: "true",
    onDragStart: e => handleDragStart(e, idx),
    onDragOver: e => handleDragOver(e, idx),
    onDragEnd: handleDragEnd,
    className: `
                                                    flex items-center gap-2 p-1.5 rounded-lg border transition-all duration-150 group cursor-move
                                                    ${draggedIndex === idx ? 'bg-indigo-50 border-indigo-300 opacity-40 scale-95 shadow-inner' : 'bg-white border-slate-200 hover:border-indigo-300 hover:shadow-sm'}
                                                `
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2 shrink-0"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-[10px] font-black text-slate-300 w-4 text-center"
  }, idx + 1), /*#__PURE__*/React.createElement(Icon, {
    name: "drag_indicator",
    className: "text-slate-300 w-4 h-4"
  })), /*#__PURE__*/React.createElement("div", {
    className: "flex-1 flex items-center gap-2.5"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: !col.hidden,
    onChange: () => toggleColumn(col.id),
    className: "w-3.5 h-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 transition-colors",
    disabled: col.sticky
  }), /*#__PURE__*/React.createElement("span", {
    className: `text-[11px] font-bold truncate ${col.hidden ? 'text-slate-400 line-through opacity-50' : 'text-slate-700'}`
  }, col.label)), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: e => {
      e.stopPropagation();
      moveColumn(col.id, -1);
    },
    disabled: idx === 0,
    className: "text-slate-400 hover:text-indigo-600 disabled:opacity-0 p-0.5"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "expand_less",
    className: "text-base"
  })), /*#__PURE__*/React.createElement("button", {
    onClick: e => {
      e.stopPropagation();
      moveColumn(col.id, 1);
    },
    disabled: idx === columns.length - 1,
    className: "text-slate-400 hover:text-indigo-600 disabled:opacity-0 p-0.5"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "expand_more",
    className: "text-base"
  }))), col.sticky && /*#__PURE__*/React.createElement("div", {
    className: "shrink-0 flex items-center gap-1 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "push_pin",
    className: "text-[9px] text-indigo-500"
  }), /*#__PURE__*/React.createElement("span", {
    className: "text-[8px] font-black text-indigo-500 uppercase"
  }, "Sticky"))))), /*#__PURE__*/React.createElement("div", {
    className: "p-3 border-t border-slate-100 flex justify-end bg-slate-50/50"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowSettings(false),
    className: "bg-slate-900 text-white px-8 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest shadow-md hover:bg-slate-800 transition-all active:scale-95"
  }, "Done"))))), /*#__PURE__*/React.createElement("header", {
    className: "bg-slate-900 text-white px-6 py-1 flex items-center justify-between shrink-0 shadow-lg z-50"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-3"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowSidebar(true),
    className: "bg-slate-800/80 p-1 rounded-lg border border-slate-700/50 cursor-pointer hover:bg-slate-700 hover:border-slate-600 transition-all flex items-center justify-center w-7 h-7",
    title: "Menu"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "menu",
    className: "text-slate-300 text-[16px]"
  })), /*#__PURE__*/React.createElement("a", {
    href: "../index.php",
    className: "bg-slate-800/80 p-1 rounded-lg border border-slate-700/50 cursor-pointer hover:bg-slate-700 hover:border-slate-600 transition-all flex items-center justify-center w-8 h-8",
    title: "Back to Home"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "arrow_back",
    className: "text-slate-300 text-[18px]"
  })), /*#__PURE__*/React.createElement("div", {
    className: "w-[1px] h-4 bg-slate-700/50 mx-1"
  }), /*#__PURE__*/React.createElement("div", {
    className: "bg-emerald-500/20 p-1 rounded-lg border border-emerald-500/20 shrink-0"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "work_outline",
    className: "text-emerald-400 text-[16px]"
  })), /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col min-w-0"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2"
  }, /*#__PURE__*/React.createElement("h1", {
    className: "font-bold text-[13px] truncate max-w-[700px] uppercase tracking-tight m-0 text-white",
    title: jobTitle
  }, jobTitle), jdId && /*#__PURE__*/React.createElement("button", {
    onClick: () => openPdf(`../api/view_jd.php?jd_id=${jdId}`, `Job Description: ${jobTitle}`),
    className: "flex items-center gap-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-md px-2 py-1 text-[11px] font-black transition-all shrink-0 shadow-sm",
    title: "View/Download JD PDF"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "picture_as_pdf",
    className: "text-[14px]"
  }), " JD PDF")), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2 mt-0.5"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-[10px] font-black text-emerald-500 tracking-widest uppercase"
  }, "Live Sync"), /*#__PURE__*/React.createElement("span", {
    className: "text-slate-600 text-[11px]"
  }, "|"), /*#__PURE__*/React.createElement("span", {
    className: "text-slate-400 text-[10px] font-bold tracking-wider",
    title: "JD ID"
  }, "#", jdId), /*#__PURE__*/React.createElement("span", {
    className: "text-slate-600 text-[11px]"
  }, "|"), /*#__PURE__*/React.createElement("span", {
    className: "text-indigo-400 text-[10px] font-bold tracking-wider",
    title: "Task Number"
  }, taskNo), /*#__PURE__*/React.createElement("span", {
    className: "text-slate-600 text-[11px]"
  }, "|"), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-1"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-slate-500 text-[10px] font-medium uppercase"
  }, "By:"), /*#__PURE__*/React.createElement("span", {
    className: "text-slate-300 text-[10px] font-bold tracking-tight uppercase"
  }, creatorName))))), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-3 relative"
  }, /*#__PURE__*/React.createElement("div", {
    className: `flex items-center gap-2 px-2 py-1 -mr-2 rounded-lg transition-colors ${!isPublic ? 'cursor-pointer group hover:bg-slate-800' : ''}`,
    onClick: () => !isPublic && setShowProfileMenu(!showProfileMenu)
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col items-end mr-1"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-[11px] font-bold text-slate-200 leading-tight"
  }, isPublic ? reviewerIdentity ? reviewerIdentity.name : 'Public' : userName), /*#__PURE__*/React.createElement("span", {
    className: "text-[9px] text-slate-500 font-medium leading-tight uppercase tracking-widest"
  }, isPublic ? 'Reviewer' : userRole)), /*#__PURE__*/React.createElement("div", {
    className: "w-7 h-7 rounded-full bg-slate-700 flex items-center justify-center font-black text-[10px] shadow-inner border border-slate-600/30 overflow-hidden"
  }, isPublic ? reviewerIdentity ? /*#__PURE__*/React.createElement("span", {
    className: "text-slate-200 font-black text-[10px]"
  }, reviewerIdentity.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase()) : /*#__PURE__*/React.createElement(Icon, {
    name: "person",
    className: "text-slate-400 text-[18px]"
  }) : userProfilePic ? /*#__PURE__*/React.createElement("img", {
    src: `../${userProfilePic}`,
    alt: "Profile",
    className: "w-full h-full object-cover"
  }) : userName.substring(0, 2).toUpperCase()), !isPublic && /*#__PURE__*/React.createElement(Icon, {
    name: "keyboard_arrow_down",
    className: `text-slate-400 text-[16px] group-hover:text-white transition-all ${showProfileMenu ? 'rotate-180' : ''}`
  })), !isPublic && showProfileMenu && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "fixed inset-0 z-40",
    onClick: () => setShowProfileMenu(false)
  }), /*#__PURE__*/React.createElement("div", {
    className: "absolute top-full right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-slate-200 overflow-hidden py-1 z-50 text-slate-700 origin-top-right animate-in fade-in zoom-in-95 duration-100"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      setShowProfileMenu(false);
      setShowProfileModal(true);
      fetchProfileData();
    },
    className: "w-full flex items-center gap-2 px-4 py-2 hover:bg-slate-50 text-[11px] font-bold transition-colors text-left"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "account_circle",
    className: "text-[16px] text-indigo-500"
  }), " My Profile"), /*#__PURE__*/React.createElement("div", {
    className: "border-t border-slate-100 my-1"
  }), /*#__PURE__*/React.createElement("a", {
    href: "../index.php?logout=1",
    className: "flex items-center gap-2 px-4 py-2 hover:bg-rose-50 text-[11px] font-bold text-rose-600 transition-colors"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "logout",
    className: "text-[16px]"
  }), " Logout"))))), /*#__PURE__*/React.createElement("div", {
    className: "bg-white border-b border-slate-200 px-6 py-1 flex items-center gap-3 shrink-0 shadow-sm z-40"
  }, /*#__PURE__*/React.createElement("div", {
    className: "relative flex-1 max-w-2xl group"
  }, /*#__PURE__*/React.createElement("i", {
    className: "material-icons absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[13px] group-focus-within:text-indigo-600"
  }, "search"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    placeholder: "Search candidates...",
    className: "w-full pl-8 pr-3 py-1 text-[11px] bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition-all",
    value: search,
    onChange: e => setSearch(e.target.value)
  })), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2"
  }, /*#__PURE__*/React.createElement("select", {
    className: "text-[10px] font-bold uppercase tracking-wider bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20",
    value: statusFilter,
    onChange: e => setStatusFilter(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "All Status"), /*#__PURE__*/React.createElement("option", {
    value: "1"
  }, "Shortlisted"), /*#__PURE__*/React.createElement("option", {
    value: "0"
  }, "Not Shortlisted")), /*#__PURE__*/React.createElement("select", {
    className: "text-[10px] font-bold uppercase tracking-wider bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20",
    value: confFilter,
    onChange: e => setConfFilter(e.target.value)
  }, /*#__PURE__*/React.createElement("option", {
    value: ""
  }, "All Conf."), /*#__PURE__*/React.createElement("option", {
    value: "1"
  }, "Confirmed"), /*#__PURE__*/React.createElement("option", {
    value: "0"
  }, "Not Confirmed")), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center bg-slate-50 border border-slate-200 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500/20 h-[28px]"
  }, /*#__PURE__*/React.createElement("span", {
    className: "bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-wider px-2 py-1 h-full flex items-center border-r border-slate-200"
  }, "Top"), /*#__PURE__*/React.createElement("input", {
    type: "number",
    min: "1",
    placeholder: "N",
    value: topN,
    onChange: e => setTopN(e.target.value),
    className: "w-12 px-1 py-1 text-[11px] bg-transparent focus:outline-none font-black text-indigo-600 text-center placeholder:font-normal placeholder:text-slate-400"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2 ml-auto"
  }, canSendMail && /*#__PURE__*/React.createElement("button", {
    onClick: sendMailToConcern,
    className: `flex items-center gap-1.5 px-3 py-1 text-[10px] font-bold uppercase transition-all shadow-sm active:scale-95 group rounded-lg border cursor-pointer
                                        ${concernEmail ? 'bg-rose-50 border-rose-200 text-rose-600 hover:bg-rose-100 hover:border-rose-300' : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-slate-100 hover:border-slate-300'}`,
    title: concernEmail ? `Send to: ${concernEmail}` : 'Select recipient and send mail'
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "mail_outline",
    className: `text-[16px] group-hover:rotate-12 transition-transform ${concernEmail ? 'text-rose-500' : 'text-slate-400'}`
  }), /*#__PURE__*/React.createElement("span", null, "Mail")), hasGlobalUpdate && /*#__PURE__*/React.createElement("button", {
    onClick: applyGlobal,
    className: "flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-lg hover:bg-emerald-100 transition-all shadow-sm text-[10px] font-bold uppercase animate-pulse",
    title: "A new global layout is available. Click to sync."
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "auto_fix_high",
    className: "text-[14px]"
  }), " Sync Global"), /*#__PURE__*/React.createElement("button", {
    onClick: handleExport,
    className: "flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded-lg text-[10px] font-bold shadow-sm transition-all group"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "file_download",
    className: "text-[14px] group-hover:animate-bounce"
  }), " Export"), isPublic && /*#__PURE__*/React.createElement("button", {
    onClick: submitFeedback,
    disabled: !isDirty,
    title: isDirty ? 'Send feedback to task creator' : 'Make changes to feedback before submitting',
    className: `flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold shadow-sm transition-all ${isDirty ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed'}`
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "send",
    className: "text-[13px]"
  }), submitState.submissionCount > 0 ? 'Resubmit' : 'Submit Feedback'), isAdmin && /*#__PURE__*/React.createElement("a", {
    href: `dashboard_v1.php${window.location.search}`,
    className: "flex items-center gap-1.5 px-3 py-1 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg hover:bg-slate-900 transition-all shadow-sm text-[10px] font-bold uppercase no-underline",
    title: "Switch to Classic Version (v1)"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "history",
    className: "text-[14px]"
  }), " v1"), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowSettings(true),
    className: "flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 w-7 h-7 rounded-lg transition-all",
    title: "Table Settings"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "settings",
    className: "text-[14px]"
  })), /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      localStorage.removeItem('modern_dashboard_columns_v5');
      window.location.reload();
    },
    className: "flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 w-7 h-7 rounded-lg transition-all",
    title: "Reset Layout"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "restart_alt",
    className: "text-[14px]"
  })), /*#__PURE__*/React.createElement("button", {
    onClick: () => setExpandedAll(!expandedAll),
    className: "flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-600 w-7 h-7 rounded-lg transition-all",
    title: expandedAll ? "Collapse Rows" : "Expand Rows"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: expandedAll ? "unfold_less" : "unfold_more",
    className: "text-[14px]"
  })))), /*#__PURE__*/React.createElement("div", {
    className: "flex-1 overflow-auto relative"
  }, /*#__PURE__*/React.createElement("table", {
    id: "candidateTable",
    className: `border-separate border-spacing-0 table-fixed w-max min-w-full bg-white ${expandedAll ? 'expanded-all' : ''}`
  }, /*#__PURE__*/React.createElement("thead", {
    className: "sticky top-0 z-30"
  }, /*#__PURE__*/React.createElement("tr", null, activeColumns.map(col => /*#__PURE__*/React.createElement("th", {
    key: col.id,
    "data-col-id": col.id,
    className: `bg-slate-50 border-b border-r border-slate-200 px-2 py-2.5 text-[9px] font-black text-slate-500 uppercase tracking-wider text-center ${col.sticky ? 'sticky-col' : 'relative'} cursor-pointer hover:bg-slate-100 transition-colors group/th`,
    style: {
      width: col.width,
      minWidth: col.width,
      maxWidth: col.width,
      left: col.sticky ? getStickyOffset(col.id) : undefined,
      zIndex: col.sticky ? 45 : 10
    },
    onClick: () => handleSort(col.id)
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-center w-full"
  }, /*#__PURE__*/React.createElement("span", {
    className: "truncate"
  }, col.label)), /*#__PURE__*/React.createElement("div", {
    className: "absolute right-0.5 top-1/2 -translate-y-1/2 flex items-center gap-0.5 pointer-events-none"
  }, sortBy === col.id && /*#__PURE__*/React.createElement(Icon, {
    name: sortOrder === 'ASC' ? "arrow_upward" : "arrow_downward",
    className: "text-indigo-600 w-2.5 h-2.5 bg-slate-50/90 rounded-sm"
  }), /*#__PURE__*/React.createElement(Icon, {
    name: "filter_list",
    className: `w-2.5 h-2.5 text-slate-400 bg-slate-50/90 rounded-sm transition-opacity ${sortBy === col.id ? 'opacity-100' : 'opacity-0 group-hover/th:opacity-100'}`
  })), /*#__PURE__*/React.createElement("div", {
    className: "resizer",
    onMouseDown: e => {
      e.stopPropagation();
      const startX = e.pageX;
      const startWidth = col.width;
      const onMove = moveE => {
        const delta = moveE.pageX - startX;
        setColumns(prev => prev.map(c => c.id === col.id ? {
          ...c,
          width: Math.max(50, startWidth + delta)
        } : c));
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
    }
  }))))), /*#__PURE__*/React.createElement("tbody", {
    className: "bg-white"
  }, loading ? Array.from({
    length: 8
  }).map((_, i) => /*#__PURE__*/React.createElement("tr", {
    key: i,
    className: "animate-pulse"
  }, activeColumns.map(col => /*#__PURE__*/React.createElement("td", {
    key: col.id,
    className: "border-b border-slate-100 px-2 py-2"
  }, /*#__PURE__*/React.createElement("div", {
    className: "h-2 bg-slate-100 rounded-full w-full opacity-60"
  }))))) : candidates.length === 0 ? /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", {
    colSpan: activeColumns.length,
    className: "py-20 text-center text-slate-400 font-medium"
  }, "No candidates found matching your criteria.")) : candidates.map((c, i) => /*#__PURE__*/React.createElement("tr", {
    key: c.id,
    className: "group hover:bg-indigo-50/30 transition-colors"
  }, activeColumns.map(col => /*#__PURE__*/React.createElement("td", {
    key: col.id,
    "data-col-id": col.id,
    className: `
                                                    border-b border-r border-slate-100 px-2 py-1 text-[11px] text-slate-600 compact-cell
                                                    ${col.sticky ? 'sticky-col' : ''} 
                                                `,
    style: {
      width: col.width,
      minWidth: col.width,
      maxWidth: col.width,
      left: col.sticky ? getStickyOffset(col.id) : undefined,
      textAlign: col.align,
      backgroundColor: 'white',
      wordBreak: 'break-word',
      overflowWrap: 'break-word'
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "line-clamp-2-custom"
  }, /*#__PURE__*/React.createElement(CellContent, {
    id: col.id,
    candidate: c,
    sl: (pagination.current_page - 1) * 50 + i + 1,
    expanded: expandedAll,
    openPdf: openPdf,
    jdId: jdId,
    onToggle: handleToggle,
    editingRowId: editingRowId,
    setEditingRowId: setEditingRowId,
    isPublic: isPublic,
    onFeedbackSave: saveFeedback
  }))))))))), /*#__PURE__*/React.createElement("footer", {
    className: "bg-white border-t border-slate-200 px-6 py-1 flex items-center justify-between shrink-0 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-50"
  }, /*#__PURE__*/React.createElement("div", {
    className: "text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-3"
  }, /*#__PURE__*/React.createElement("span", {
    className: "bg-slate-100 px-2 py-1 rounded text-slate-600"
  }, "Showing ", /*#__PURE__*/React.createElement("span", {
    className: "text-indigo-600 font-black text-[13px]"
  }, pagination.total_records > 0 ? `${(pagination.current_page - 1) * 50 + 1} - ${Math.min(pagination.current_page * 50, pagination.total_records)}` : '0'), " of ", /*#__PURE__*/React.createElement("span", {
    className: "text-slate-800 font-black text-[13px]"
  }, pagination.total_records || 0))), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2"
  }, /*#__PURE__*/React.createElement("button", {
    disabled: pagination.current_page <= 1,
    onClick: () => fetchData(pagination.current_page - 1),
    className: `w-8 h-8 flex items-center justify-center rounded-lg transition-all border
                                    ${pagination.current_page <= 1 ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400 hover:text-indigo-600 shadow-sm'}`
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "chevron_left",
    className: "text-lg"
  })), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-1.5"
  }, (() => {
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
    return pages.map((p, i) => p === '...' ? /*#__PURE__*/React.createElement("span", {
      key: `dots-${i}`,
      className: "px-1 text-slate-400 font-bold"
    }, "...") : /*#__PURE__*/React.createElement("button", {
      key: p,
      onClick: () => fetchData(p),
      className: `w-8 h-8 flex items-center justify-center rounded-lg text-[11px] font-black transition-all border
                                                    ${current === p ? 'bg-indigo-600 text-white border-indigo-600 shadow-md scale-105' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400 hover:text-indigo-600 shadow-sm'}`
    }, p));
  })()), /*#__PURE__*/React.createElement("button", {
    disabled: pagination.current_page >= pagination.total_pages,
    onClick: () => fetchData(pagination.current_page + 1),
    className: `w-8 h-8 flex items-center justify-center rounded-lg transition-all border
                                    ${pagination.current_page >= pagination.total_pages ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' : 'bg-white text-slate-600 border-slate-200 hover:border-indigo-400 hover:text-indigo-600 shadow-sm'}`
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "chevron_right",
    className: "text-lg"
  })), /*#__PURE__*/React.createElement("div", {
    className: "h-6 w-[1px] bg-slate-200 mx-2"
  }), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-[10px] font-black text-slate-400 uppercase"
  }, "Go To"), /*#__PURE__*/React.createElement("input", {
    type: "number",
    min: "1",
    max: pagination.total_pages,
    className: "w-10 bg-transparent border-none text-[11px] font-black text-indigo-600 focus:ring-0 p-0 text-center",
    onKeyDown: e => {
      if (e.key === 'Enter') {
        const val = parseInt(e.target.value);
        if (val >= 1 && val <= pagination.total_pages) fetchData(val);
      }
    },
    placeholder: "Pg"
  })))), /*#__PURE__*/React.createElement(Sidebar, {
    showSidebar: showSidebar,
    setShowSidebar: setShowSidebar,
    isAdmin: isAdmin,
    zoom: zoom,
    adjustZoom: adjustZoom,
    isPublic: window.isPublicMode,
    perms: sidebarPerms,
    username: sidebarUser.username,
    rootAdminId: sidebarUser.rootAdminId
  }), showProfileModal && /*#__PURE__*/React.createElement("div", {
    className: "fixed inset-0 z-[110] flex items-center justify-center p-4"
  }, /*#__PURE__*/React.createElement("div", {
    className: "absolute inset-0 bg-slate-900/60 backdrop-blur-sm",
    onClick: () => setShowProfileModal(false)
  }), /*#__PURE__*/React.createElement("div", {
    className: "relative bg-white rounded-[24px] w-full max-w-[850px] shadow-2xl flex overflow-hidden min-h-[500px] animate-in zoom-in-95 duration-200"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-[300px] bg-gradient-to-br from-indigo-950 to-indigo-900 p-10 flex flex-col items-center text-white relative shrink-0 border-r border-indigo-800"
  }, /*#__PURE__*/React.createElement("div", {
    className: "relative mb-6"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-[150px] h-[150px] rounded-full p-1.5 bg-white/20 backdrop-blur-sm"
  }, userProfilePic ? /*#__PURE__*/React.createElement("img", {
    src: `../${userProfilePic}`,
    alt: "Profile",
    className: "w-full h-full rounded-full object-cover border-4 border-white/10 shadow-xl"
  }) : /*#__PURE__*/React.createElement("div", {
    className: "w-full h-full rounded-full bg-slate-800 flex items-center justify-center border-4 border-white/10 shadow-xl"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "person",
    className: "text-[80px] text-slate-400"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "absolute bottom-1 right-1 w-[42px] h-[42px] bg-white rounded-full flex items-center justify-center text-indigo-600 cursor-pointer shadow-lg border-2 border-indigo-100 hover:scale-105 transition-transform",
    onClick: () => alert('Profile picture upload available in classic dashboard')
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "camera_alt",
    className: "text-[22px]"
  }))), /*#__PURE__*/React.createElement("h2", {
    className: "m-0 text-2xl font-bold text-center leading-tight mb-3"
  }, userName), /*#__PURE__*/React.createElement("div", {
    className: "px-3 py-1 bg-white/20 rounded-full text-xs font-bold uppercase tracking-wider"
  }, userRole), /*#__PURE__*/React.createElement("div", {
    className: "mt-auto w-full pt-8"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2 text-sm opacity-90 mb-0"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "fingerprint",
    className: "text-[18px]"
  }), /*#__PURE__*/React.createElement("span", null, "Employee ID: ", /*#__PURE__*/React.createElement("strong", null, profileData?.employee_id || userEmpId || 'N/A'))))), /*#__PURE__*/React.createElement("div", {
    className: "flex-1 p-10 flex flex-col relative bg-white"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex justify-between items-start mb-8"
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h3", {
    className: "m-0 text-2xl text-slate-900 font-extrabold tracking-tight"
  }, "Account Details"), /*#__PURE__*/React.createElement("p", {
    className: "m-0 mt-1.5 text-slate-500 text-[13px] font-medium"
  }, "Manage your professional identity and contact information.")), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowProfileModal(false),
    className: "w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-100 text-slate-400 transition-all hover:text-rose-500"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "close",
    className: "text-[24px]"
  }))), profileLoading ? /*#__PURE__*/React.createElement("div", {
    className: "flex-1 flex flex-col items-center justify-center text-slate-400"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mb-4"
  }), /*#__PURE__*/React.createElement("p", {
    className: "text-[12px] font-black uppercase tracking-widest text-slate-500"
  }, "Syncing Profile Data...")) : !profileData ? /*#__PURE__*/React.createElement("div", {
    className: "flex-1 flex flex-col items-center justify-center text-rose-400 p-10 text-center bg-rose-50/50 rounded-[32px] border border-rose-100/50"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-16 h-16 bg-rose-100 rounded-full flex items-center justify-center mb-4"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "error_outline",
    className: "text-[32px] text-rose-600"
  })), /*#__PURE__*/React.createElement("h4", {
    className: "text-[16px] font-bold text-rose-900 mb-1"
  }, "Connection Error"), /*#__PURE__*/React.createElement("p", {
    className: "text-[13px] text-rose-600/70 mb-6"
  }, "We couldn't retrieve your profile information right now."), /*#__PURE__*/React.createElement("button", {
    onClick: fetchProfileData,
    className: "px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-indigo-200 active:scale-95"
  }, "Retry Sync")) : /*#__PURE__*/React.createElement("div", {
    className: "grid grid-cols-2 gap-4 flex-1"
  }, [{
    label: 'Email Address',
    value: profileData.email,
    icon: 'alternate_email',
    color: 'indigo'
  }, {
    label: 'Mobile Number',
    value: profileData.mobile_no,
    icon: 'phone_iphone',
    color: 'emerald'
  }, {
    label: 'Designation',
    value: profileData.designation,
    icon: 'work_history',
    color: 'amber'
  }, {
    label: 'Department',
    value: profileData.department,
    icon: 'corporate_fare',
    color: 'violet'
  }, {
    label: 'IP Phone',
    value: profileData.ip_no,
    icon: 'contact_phone',
    color: 'sky'
  }, {
    label: 'Office Floor',
    value: profileData.floor,
    icon: 'layers',
    color: 'rose'
  }].map((item, idx) => /*#__PURE__*/React.createElement("div", {
    key: idx,
    className: "group flex items-center gap-4 bg-slate-50/50 border border-slate-100 p-4 rounded-[20px] hover:bg-white hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-500/5 transition-all duration-300"
  }, /*#__PURE__*/React.createElement("div", {
    className: `w-11 h-11 rounded-2xl bg-${item.color}-100/50 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform duration-300`
  }, /*#__PURE__*/React.createElement(Icon, {
    name: item.icon,
    className: `text-${item.color}-600 text-[20px]`
  })), /*#__PURE__*/React.createElement("div", {
    className: "min-w-0"
  }, /*#__PURE__*/React.createElement("p", {
    className: "text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5"
  }, item.label), /*#__PURE__*/React.createElement("p", {
    className: "font-bold text-[14px] text-slate-800 m-0 truncate"
  }, item.value || 'Not Provided'))))), /*#__PURE__*/React.createElement("div", {
    className: "mt-8 pt-6 border-t border-slate-100 flex items-center justify-between shrink-0"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-tight"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "verified_user",
    className: "text-[14px] text-emerald-500"
  }), /*#__PURE__*/React.createElement("span", null, "Session Secure & Verified")), /*#__PURE__*/React.createElement("button", {
    onClick: () => alert('Security settings are managed in the main portal.'),
    className: "flex items-center gap-2 px-6 py-2.5 bg-slate-900 text-white font-black text-[11px] uppercase tracking-widest rounded-xl hover:bg-slate-800 transition-all shadow-lg active:scale-95"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "lock",
    className: "text-[16px]"
  }), " Account Security"))))), isPublic && !reviewerIdentity && /*#__PURE__*/React.createElement(ReviewerWelcomeModal, {
    jobTitle: jobTitle,
    jdId: jdId,
    reidToken: window.reidToken || '',
    onConfirm: confirmReviewerIdentity
  }), showPdfModal && /*#__PURE__*/React.createElement("div", {
    className: "fixed inset-0 z-[10000] bg-slate-900/80 backdrop-blur-sm flex items-center justify-center animate-in fade-in duration-300"
  }, /*#__PURE__*/React.createElement("div", {
    className: "bg-slate-900 border border-slate-700 shadow-2xl rounded-xl w-[98%] h-[98vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-300"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700 shrink-0"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-3"
  }, /*#__PURE__*/React.createElement("div", {
    className: "bg-rose-500/20 p-1.5 rounded-lg border border-rose-500/30"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "picture_as_pdf",
    className: "text-rose-400 text-[18px]"
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h3", {
    className: "text-slate-100 font-bold text-[14px] leading-tight m-0"
  }, pdfTitle), /*#__PURE__*/React.createElement("p", {
    className: "text-slate-400 text-[10px] m-0 leading-tight mt-0.5"
  }, "Secure Document Viewer"))), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-2"
  }, /*#__PURE__*/React.createElement("a", {
    href: pdfUrl,
    target: "_blank",
    rel: "noreferrer",
    className: "p-2 hover:bg-slate-700 rounded-lg text-slate-300 hover:text-white transition-colors flex items-center justify-center",
    title: "Open in New Tab"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "open_in_new",
    className: "text-[18px]"
  })), /*#__PURE__*/React.createElement("button", {
    onClick: () => setShowPdfModal(false),
    className: "p-2 hover:bg-rose-500/20 rounded-lg text-slate-300 hover:text-rose-400 transition-colors flex items-center justify-center",
    title: "Close"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "close",
    className: "text-[18px]"
  })))), /*#__PURE__*/React.createElement("div", {
    className: "flex-1 bg-slate-950 relative"
  }, /*#__PURE__*/React.createElement("iframe", {
    src: pdfUrl,
    className: "w-full h-full border-none",
    title: pdfTitle
  })))));
}
function ReviewerWelcomeModal({
  jobTitle,
  jdId,
  reidToken,
  onConfirm
}) {
  const [empId, setEmpId] = React.useState('');
  const [empInfo, setEmpInfo] = React.useState(null);
  // idle | loading | authorized | not_found | unauthorized
  const [lookupState, setLookup] = React.useState('idle');
  const [errorMsg, setErrorMsg] = React.useState('');
  const debounceRef = React.useRef(null);
  const validate = React.useCallback(async id => {
    const trimmed = id.trim().toUpperCase();
    if (!trimmed) {
      setEmpInfo(null);
      setLookup('idle');
      setErrorMsg('');
      return;
    }
    setLookup('loading');
    setEmpInfo(null);
    try {
      const res = await fetch('../api/validate_reviewer.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          jd_id: jdId,
          emp_id: trimmed,
          reid_token: reidToken
        })
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
    } catch (e) {
      setLookup('not_found');
      setErrorMsg('Connection error. Please try again.');
    }
  }, [jdId]);
  const handleChange = e => {
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
  const handleKeyDown = e => {
    if (e.key === 'Enter' && empId.trim().length >= 6) {
      clearTimeout(debounceRef.current);
      validate(empId);
    }
  };
  const handleConfirm = () => {
    if (lookupState !== 'authorized' || !empInfo) return;
    onConfirm({
      name: empInfo.full_name,
      email: empInfo.email,
      empId: empInfo.employee_id,
      designation: empInfo.designation,
      department: empInfo.department
    });
  };
  const inputClass = `w-full px-3 py-3 pr-10 border rounded-xl text-[14px] font-bold tracking-wider focus:outline-none focus:ring-2 transition-colors uppercase ${lookupState === 'authorized' ? 'border-emerald-400 bg-emerald-50 focus:ring-emerald-500/20' : lookupState === 'not_found' ? 'border-rose-400 bg-rose-50 focus:ring-rose-500/20' : lookupState === 'unauthorized' ? 'border-amber-400 bg-amber-50 focus:ring-amber-500/20' : 'border-slate-300 bg-white focus:ring-indigo-500/20 focus:border-indigo-500'}`;
  return /*#__PURE__*/React.createElement("div", {
    className: "fixed inset-0 z-[9999] flex items-center justify-center p-4",
    style: {
      background: 'rgba(15,23,42,0.9)',
      backdropFilter: 'blur(6px)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'linear-gradient(135deg,#4f46e5,#7c3aed)'
    },
    className: "px-6 py-5"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-3"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center shrink-0"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "rate_review",
    className: "text-white text-[24px]"
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("p", {
    className: "text-indigo-200 text-[10px] font-bold uppercase tracking-widest leading-tight"
  }, "Candidate Review Invitation"), /*#__PURE__*/React.createElement("h2", {
    className: "text-white font-black text-[16px] leading-snug mt-0.5"
  }, jobTitle)))), /*#__PURE__*/React.createElement("div", {
    className: "px-6 py-6"
  }, /*#__PURE__*/React.createElement("p", {
    className: "text-slate-500 text-[12px] leading-relaxed mb-5"
  }, "You've been invited to review candidates for this position. Enter your ", /*#__PURE__*/React.createElement("strong", {
    className: "text-slate-700"
  }, "Employee ID"), " to verify your identity and continue."), /*#__PURE__*/React.createElement("div", {
    className: "mb-4"
  }, /*#__PURE__*/React.createElement("label", {
    className: "block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5"
  }, "Your Employee ID"), /*#__PURE__*/React.createElement("div", {
    className: "relative"
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: empId,
    onChange: handleChange,
    onKeyDown: handleKeyDown,
    placeholder: "e.g. MGI0123",
    autoFocus: true,
    autoCapitalize: "characters",
    className: inputClass
  }), /*#__PURE__*/React.createElement("div", {
    className: "absolute right-3 top-1/2 -translate-y-1/2"
  }, lookupState === 'loading' && /*#__PURE__*/React.createElement("div", {
    className: "w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin"
  }), lookupState === 'authorized' && /*#__PURE__*/React.createElement(Icon, {
    name: "verified",
    className: "text-emerald-500 text-[20px]"
  }), lookupState === 'not_found' && /*#__PURE__*/React.createElement(Icon, {
    name: "cancel",
    className: "text-rose-500 text-[20px]"
  }), lookupState === 'unauthorized' && /*#__PURE__*/React.createElement(Icon, {
    name: "lock",
    className: "text-amber-500 text-[20px]"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "min-h-[32px] mt-1.5"
  }, lookupState === 'not_found' && /*#__PURE__*/React.createElement("p", {
    className: "text-rose-600 text-[11px] font-semibold flex items-center gap-1"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "error",
    className: "text-[13px]"
  }), " ", errorMsg), lookupState === 'unauthorized' && /*#__PURE__*/React.createElement("div", {
    className: "bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"
  }, /*#__PURE__*/React.createElement("p", {
    className: "text-amber-700 text-[11px] font-bold flex items-center gap-1 mb-0.5"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "lock",
    className: "text-[13px]"
  }), " Access Restricted"), /*#__PURE__*/React.createElement("p", {
    className: "text-amber-600 text-[10px] leading-relaxed"
  }, errorMsg)))), lookupState === 'authorized' && empInfo && /*#__PURE__*/React.createElement("div", {
    className: "mb-5 bg-emerald-50 border border-emerald-200 rounded-xl p-4 animate-in fade-in duration-200"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-3"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center font-black text-white text-[13px] shrink-0"
  }, empInfo.full_name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase()), /*#__PURE__*/React.createElement("div", {
    className: "min-w-0"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center gap-1.5"
  }, /*#__PURE__*/React.createElement("p", {
    className: "font-black text-slate-800 text-[14px] leading-tight truncate"
  }, empInfo.full_name), /*#__PURE__*/React.createElement("span", {
    className: "bg-emerald-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded-full uppercase tracking-wide shrink-0"
  }, "Verified")), /*#__PURE__*/React.createElement("p", {
    className: "text-slate-500 text-[11px] truncate"
  }, empInfo.designation, empInfo.department ? ` Â· ${empInfo.department}` : ''), empInfo.email && /*#__PURE__*/React.createElement("p", {
    className: "text-indigo-500 text-[10px] truncate mt-0.5"
  }, empInfo.email)))), /*#__PURE__*/React.createElement("button", {
    onClick: handleConfirm,
    disabled: lookupState !== 'authorized',
    className: `w-full py-3 font-black text-[13px] rounded-xl transition-all uppercase tracking-wide flex items-center justify-center gap-2 ${lookupState === 'authorized' ? 'bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] text-white shadow-lg shadow-indigo-500/25' : 'bg-slate-100 text-slate-400 cursor-not-allowed'}`
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "check_circle",
    className: "text-[18px]"
  }), lookupState === 'authorized' ? `Continue as ${empInfo.full_name.split(' ')[0]}` : 'Enter Your Employee ID to Continue'), /*#__PURE__*/React.createElement("p", {
    className: "text-[10px] text-slate-400 text-center mt-3 leading-relaxed"
  }, "Only the invited reviewer can access this form. Your identity will be recorded on submission."))));
}
function FeedbackCell({
  candidate,
  isPublic,
  onFeedbackSave
}) {
  const [localComment, setLocalComment] = useState(candidate.feedback_comment || '');
  const [localRecommended, setLocalRecommended] = useState(candidate.feedback_recommended == 1);
  const [saving, setSaving] = useState(false);
  const handleBlurSave = async () => {
    if (!onFeedbackSave) return;
    setSaving(true);
    await onFeedbackSave(candidate.id, localComment, localRecommended);
    setSaving(false);
  };
  const handleRecommendedChange = async checked => {
    setLocalRecommended(checked);
    if (!onFeedbackSave) return;
    setSaving(true);
    await onFeedbackSave(candidate.id, localComment, checked);
    setSaving(false);
  };
  if (!isPublic) {
    return /*#__PURE__*/React.createElement("div", {
      className: "flex flex-col gap-1"
    }, localRecommended && /*#__PURE__*/React.createElement("span", {
      className: "inline-flex items-center gap-1 bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 rounded-full text-[9px] font-black uppercase w-fit"
    }, /*#__PURE__*/React.createElement(Icon, {
      name: "thumb_up",
      className: "text-[10px]"
    }), " Recommended"), localComment ? /*#__PURE__*/React.createElement("p", {
      className: "text-[10px] text-slate-700 leading-relaxed m-0 whitespace-pre-wrap"
    }, localComment) : /*#__PURE__*/React.createElement("span", {
      className: "text-[10px] text-slate-400 italic"
    }, "No feedback yet"));
  }
  return /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col gap-1.5"
  }, /*#__PURE__*/React.createElement("textarea", {
    value: localComment,
    onChange: e => setLocalComment(e.target.value),
    onBlur: handleBlurSave,
    placeholder: "Enter feedback for this candidate...",
    rows: 3,
    className: "w-full text-[10px] text-slate-700 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 resize-none bg-slate-50 focus:bg-white transition-all placeholder:text-slate-400"
  }), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between"
  }, /*#__PURE__*/React.createElement("label", {
    className: "flex items-center gap-1.5 cursor-pointer select-none"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: localRecommended,
    onChange: e => handleRecommendedChange(e.target.checked),
    className: "w-3.5 h-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
  }), /*#__PURE__*/React.createElement("span", {
    className: `text-[9px] font-black uppercase tracking-wide ${localRecommended ? 'text-emerald-600' : 'text-slate-400'}`
  }, localRecommended ? 'Recommended' : 'Mark Recommended')), saving && /*#__PURE__*/React.createElement("span", {
    className: "text-[9px] text-indigo-400 font-medium animate-pulse"
  }, "Saving...")));
}
function CellContent({
  id,
  candidate,
  sl,
  expanded,
  openPdf,
  jdId,
  onToggle,
  editingRowId,
  setEditingRowId,
  isPublic,
  onFeedbackSave
}) {
  const isEditing = editingRowId === candidate.id;
  const formatDOB = dob => {
    if (!dob) return '-';
    const d = new Date(dob);
    if (isNaN(d)) return dob;
    return d.toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    }).replace(/ /g, ' ');
  };
  if (id === 'feedback') return /*#__PURE__*/React.createElement(FeedbackCell, {
    candidate: candidate,
    isPublic: isPublic,
    onFeedbackSave: onFeedbackSave
  });
  if (id === 'sl') return /*#__PURE__*/React.createElement("div", {
    className: "flex flex-col items-center gap-1.5"
  }, /*#__PURE__*/React.createElement("span", {
    className: "font-black text-slate-900 text-[14px] leading-tight"
  }, sl), /*#__PURE__*/React.createElement("span", {
    className: "bg-slate-100 border border-slate-300 px-1 py-0.5 rounded-md text-[9px] font-black uppercase text-slate-700 shadow-sm whitespace-nowrap"
  }, "ID: ", candidate.id));
  if (id === 'candidate') {
    const isValidLink = link => link && link.toLowerCase() !== 'not mentioned' && link.trim() !== '';
    const formatLink = link => !link.startsWith('http://') && !link.startsWith('https://') ? 'https://' + link : link;
    const renderBlocks = (val, type) => {
      if (!val) return null;
      const items = val.split(',').map(s => s.trim()).filter(s => s);
      return items.map((item, idx) => /*#__PURE__*/React.createElement("div", {
        key: idx,
        className: `
                            px-1.5 py-0.5 rounded text-[10px] font-medium border whitespace-nowrap
                            ${type === 'email' ? 'bg-slate-50 text-slate-800 border-slate-200' : 'bg-blue-50 text-blue-700 border-blue-200'}
                        `
      }, item));
    };
    return /*#__PURE__*/React.createElement("div", {
      className: "flex flex-col gap-1 min-h-[1.5rem]"
    }, /*#__PURE__*/React.createElement("div", {
      className: "flex items-center justify-between gap-2"
    }, /*#__PURE__*/React.createElement("span", {
      className: "font-black text-indigo-700 text-[13px] leading-tight break-words flex-1 uppercase"
    }, candidate.name), /*#__PURE__*/React.createElement("button", {
      onClick: () => openPdf(`../api/view_cv.php?n8n_id=${candidate.n8n_id}&jd_id=${jdId}`, `CV: ${candidate.name}`),
      className: "p-0.5 hover:scale-110 transition-all shrink-0 active:scale-90 group relative",
      title: "View CV (PDF)"
    }, /*#__PURE__*/React.createElement("svg", {
      width: "26",
      height: "26",
      viewBox: "0 0 45 55",
      fill: "none",
      xmlns: "http://www.w3.org/2000/svg"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M5 2C3.34 2 2 3.34 2 5V50C2 51.66 3.34 53 5 53H40C41.66 53 43 51.66 43 50V18L27 2H5Z",
      fill: "white",
      stroke: "#2D3436",
      strokeWidth: "3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M27 2V18H43",
      fill: "white",
      stroke: "#2D3436",
      strokeWidth: "3",
      strokeLinejoin: "round"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M26.5 32.5c-.2-.6-.7-1.2-1.6-1.9-2.3-1.9-5.4-3.4-6.8-4.1.8-3.4-.1-7.6-.8-7.6-.5 0-.8.6-.8 1.4 0 1.9 1.4 6.5 2.5 9.3-1.4 4.2-3.5 8.4-5.4 11.8-1.6.8-3.7 1.9-4.7 1.9-.5 0-.8-.3-.8-.9 0-1.1 1.6-3.4 4.5-5.6.2-.1.2-.4 0-.5-.6-.3-1.2-.6-1.8-.9-.2-.1-.4.1-.3.3 0 0-2.6 7.9-1.1 9.7.5.6 1.2.9 2 .9 2.1 0 5.1-2 7.7-4 3 .9 6.7 1.8 8.7 1.8 1.2 0 1.6-.3 1.6-.9 0-.6-.9-2.4-3.9-5 .2-.1.4-.2.5-.2 1.2-.5 2.6-1.1 3.2-1.6.6-.5.7-1.1.5-1.8-.2-.3-.6-.8-1.2-1.3z",
      fill: "#E53935",
      transform: "translate(1, -1)"
    }), /*#__PURE__*/React.createElement("text", {
      x: "22",
      y: "48",
      fontSize: "11",
      fontWeight: "900",
      fill: "#2D3436",
      textAnchor: "middle",
      fontFamily: "Arial, sans-serif"
    }, "PDF"), /*#__PURE__*/React.createElement("path", {
      d: "M35 1L35 15M35 15L28 8M35 15L42 8",
      stroke: "#FF0000",
      strokeWidth: "4",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    })))), /*#__PURE__*/React.createElement("div", {
      className: `
                            flex flex-col gap-1 mt-1 border-t border-dashed border-indigo-100 pt-1 animate-in slide-in-from-top-1 duration-300
                            ${expanded ? 'block' : 'hidden group-hover:block'}
                        `
    }, /*#__PURE__*/React.createElement("div", {
      className: "flex flex-wrap gap-1.5"
    }, renderBlocks(candidate.email_id, 'email'), renderBlocks(candidate.phone, 'phone')), /*#__PURE__*/React.createElement("div", {
      className: "flex items-center justify-between mt-1"
    }, /*#__PURE__*/React.createElement("div", {
      className: "flex items-center gap-2.5"
    }, isValidLink(candidate.github_link) ? /*#__PURE__*/React.createElement("a", {
      href: formatLink(candidate.github_link),
      target: "_blank",
      rel: "noreferrer",
      title: "GitHub",
      className: "text-slate-800 hover:text-black hover:scale-110 transition-transform"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "18",
      height: "18",
      stroke: "currentColor",
      strokeWidth: "2",
      fill: "none",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M15 22v-4a4.8 4.8 0 0 0-1-3.2c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M9 18c-4.51 2-5-2-7-2"
    }))) : /*#__PURE__*/React.createElement("div", {
      title: "GitHub Not Available",
      className: "text-slate-300 cursor-not-allowed"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "18",
      height: "18",
      stroke: "currentColor",
      strokeWidth: "2",
      fill: "none",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M15 22v-4a4.8 4.8 0 0 0-1-3.2c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M9 18c-4.51 2-5-2-7-2"
    }))), isValidLink(candidate.linkedin_link) ? /*#__PURE__*/React.createElement("a", {
      href: formatLink(candidate.linkedin_link),
      target: "_blank",
      rel: "noreferrer",
      title: "LinkedIn",
      className: "text-[#0a66c2] hover:text-blue-800 hover:scale-110 transition-transform"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "18",
      height: "18",
      stroke: "currentColor",
      strokeWidth: "2",
      fill: "none",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"
    }), /*#__PURE__*/React.createElement("rect", {
      width: "4",
      height: "12",
      x: "2",
      y: "9"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "4",
      cy: "4",
      r: "2"
    }))) : /*#__PURE__*/React.createElement("div", {
      title: "LinkedIn Not Available",
      className: "text-slate-300 cursor-not-allowed"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "18",
      height: "18",
      stroke: "currentColor",
      strokeWidth: "2",
      fill: "none",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"
    }), /*#__PURE__*/React.createElement("rect", {
      width: "4",
      height: "12",
      x: "2",
      y: "9"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "4",
      cy: "4",
      r: "2"
    })))), /*#__PURE__*/React.createElement("span", {
      className: "bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-md text-[10px] font-black uppercase text-indigo-600 shadow-sm"
    }, "UID: ", candidate.n8n_id))));
  }
  if (id === 'date_of_birth') return /*#__PURE__*/React.createElement("span", {
    className: "font-medium text-slate-900 text-[11px]"
  }, formatDOB(candidate.date_of_birth));
  if (id === 'total_experience') return /*#__PURE__*/React.createElement("span", {
    className: "font-bold text-slate-900"
  }, candidate.total_experience, "y");
  if (id === 'expected_salary') return /*#__PURE__*/React.createElement("span", {
    className: "font-bold text-slate-900"
  }, "à§³", parseFloat(candidate.expected_salary).toLocaleString());
  if (id === 'match') {
    const val = candidate.match || '0';
    const displayVal = val.toString().includes('%') ? val : `${val}%`;
    return /*#__PURE__*/React.createElement("span", {
      className: `px-2 py-0.5 rounded-full text-[9px] font-black border ${parseFloat(val) >= 70 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'}`
    }, displayVal);
  }
  if (id === 'reason_for_rating') return /*#__PURE__*/React.createElement("div", {
    className: "text-[10px] leading-relaxed text-slate-800 font-medium"
  }, candidate.reason_for_rating || '-');
  if (id === 'shortlisted') {
    const isChecked = candidate.shortlisted == 1;
    return /*#__PURE__*/React.createElement("div", {
      className: "flex items-center justify-center"
    }, /*#__PURE__*/React.createElement("button", {
      disabled: !isEditing,
      onClick: () => onToggle(candidate.id, 'shortlisted', isChecked ? 0 : 1),
      className: `
                                w-9 h-9 rounded-lg flex items-center justify-center transition-all duration-200 border-2 shadow-sm
                                ${isChecked ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-slate-50 border-slate-200 text-transparent'}
                                ${isEditing ? 'cursor-pointer hover:border-emerald-400 hover:scale-105 active:scale-95' : 'cursor-default opacity-60'}
                            `
    }, /*#__PURE__*/React.createElement(Icon, {
      name: "check",
      className: "text-[32px] font-black"
    })));
  }
  if (id === 'confirmation') {
    const isChecked = candidate.confirmation == 1;
    return /*#__PURE__*/React.createElement("div", {
      className: "flex items-center justify-center"
    }, /*#__PURE__*/React.createElement("button", {
      disabled: !isEditing,
      onClick: () => onToggle(candidate.id, 'confirmation', isChecked ? 0 : 1),
      className: `
                                w-9 h-9 rounded-lg flex items-center justify-center transition-all duration-200 border-2 shadow-sm
                                ${isChecked ? 'bg-blue-500 border-blue-500 text-white' : 'bg-slate-50 border-slate-200 text-transparent'}
                                ${isEditing ? 'cursor-pointer hover:border-blue-400 hover:scale-105 active:scale-95' : 'cursor-default opacity-60'}
                            `
    }, /*#__PURE__*/React.createElement(Icon, {
      name: "check",
      className: "text-[32px] font-black"
    })));
  }
  if (id === 'actions') return /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-center"
  }, isEditing ? /*#__PURE__*/React.createElement("button", {
    onClick: () => setEditingRowId(null),
    className: "w-11 h-11 bg-indigo-600 hover:bg-indigo-700 rounded-xl flex items-center justify-center transition-all text-white shadow-lg shadow-indigo-100 active:scale-95",
    title: "Save Changes"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "check",
    className: "text-[32px] font-bold"
  })) : /*#__PURE__*/React.createElement("button", {
    onClick: () => setEditingRowId(candidate.id),
    className: "w-11 h-11 bg-white hover:bg-slate-50 rounded-xl flex items-center justify-center transition-all text-slate-500 hover:text-indigo-600 border border-slate-200 hover:border-indigo-200 shadow-sm active:scale-95 group",
    title: "Edit Status"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "edit",
    className: "text-[32px] group-hover:rotate-12 transition-transform"
  })));
  return /*#__PURE__*/React.createElement("span", {
    className: "block text-slate-800 font-medium"
  }, candidate[id] || '-');
}
const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(/*#__PURE__*/React.createElement(Dashboard, null));
