const {
  useState,
  useEffect
} = React;
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
  const hasPerm = perm => {
    if (isRoot) return true;
    if (!perms) return false;
    const v = perms[perm];
    return v === true || v === "true" || v === 1 || v === "1" || v === "on";
  };
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
const SidebarRoot = () => {
  const [showSidebar, setShowSidebar] = useState(false);
  const [zoom, setZoom] = useState(parseFloat(localStorage.getItem('zoom')) || 1.0);
  const [sidebarPerms, setSidebarPerms] = useState(window.currentUserPermissions || null);
  const [sidebarUser, setSidebarUser] = useState({
    username: window.currentUser && window.currentUser.id || '',
    rootAdminId: window.rootAdminId || ''
  });
  useEffect(() => {
    window.toggleSidebar = () => setShowSidebar(prev => !prev);
    document.documentElement.style.setProperty('--zoom', zoom);
    const label = document.getElementById('zoom-percent');
    if (label) label.innerText = Math.round(zoom * 100) + '%';
    const handlePerms = e => {
      setSidebarPerms(e.detail.perms);
      setSidebarUser({
        username: e.detail.username,
        rootAdminId: e.detail.rootAdminId
      });
    };
    window.addEventListener('permissionsLoaded', handlePerms);
    return () => window.removeEventListener('permissionsLoaded', handlePerms);
  }, [zoom]);
  const adjustZoom = delta => {
    const newZoom = Math.max(0.5, Math.min(1.5, zoom + delta));
    setZoom(newZoom);
    localStorage.setItem('zoom', newZoom);
  };
  return /*#__PURE__*/React.createElement(Sidebar, {
    showSidebar: showSidebar,
    setShowSidebar: setShowSidebar,
    isAdmin: window.isAdmin || false,
    zoom: zoom,
    adjustZoom: adjustZoom,
    isPublic: false,
    perms: sidebarPerms,
    username: sidebarUser.username,
    rootAdminId: sidebarUser.rootAdminId
  });
};
const sidebarRootEl = document.getElementById('sidebar-root');
if (sidebarRootEl) {
  const root = ReactDOM.createRoot(sidebarRootEl);
  root.render(/*#__PURE__*/React.createElement(SidebarRoot, null));
}
