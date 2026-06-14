<?php
/**
 * Modern React-based Sidebar
 * This file is intended to be included within a <script type="text/babel"> block.
 */
?>

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
    const hasPerm = (perm) => {
        if (isRoot) return true; // Root admin sees everything
        if (!perms) return false; // Permissions not loaded yet — hide until we know
        const v = perms[perm];
        return v === true || v === "true" || v === 1 || v === "1" || v === "on";
    };

    // System Users: either manage_users OR create_user
    const hasUserAccess = isRoot || hasPerm('manage_users') || hasPerm('create_user');

    return (
        <>
            {/* Sidebar Overlay */}
            {showSidebar && (
                <div 
                    className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] transition-opacity" 
                    onClick={() => setShowSidebar(false)}
                ></div>
            )}

            {/* Sidebar Panel */}
            <div className={`fixed top-0 left-0 bottom-0 w-[240px] bg-white shadow-2xl z-[101] transform transition-transform duration-300 ease-in-out flex flex-col ${showSidebar ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex items-center justify-between px-4 py-2 border-b border-slate-100 shrink-0">
                    <h2 className="text-[15px] font-bold text-indigo-600 flex items-center gap-2">Menu</h2>
                    <button 
                        onClick={() => setShowSidebar(false)} 
                        className="p-1 rounded-full hover:bg-slate-100 text-slate-500 transition-colors shrink-0"
                    >
                        <Icon name="close" className="text-[18px]" />
                    </button>
                </div>
                
                <div className="flex-1 overflow-y-auto p-2 space-y-0">
                    <a href={`${basePath}index.php`} className="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 font-bold text-[13px] mb-1 transition-colors hover:bg-indigo-100">
                        <Icon name="home" className="text-[16px]" /> Home
                    </a>
                    
                    <div className="space-y-0">
                            {hasPerm('manage_tasks') && (
                                <a href={`${basePath}tasks.php`} className="flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group">
                                    <Icon name="task" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> Task Management
                                </a>
                            )}
                            {hasUserAccess && (
                                <button onClick={() => { if(window.toggleUserManager) window.toggleUserManager(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="manage_accounts" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> System Users
                                </button>
                            )}
                            {hasPerm('db_control') && (
                                <button onClick={() => { if(window.toggleDbControl) window.toggleDbControl(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="storage" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> DB Control
                                </button>
                            )}
                            {hasPerm('manage_employees') && (
                                <button onClick={() => { if(window.toggleEmployeeManager) window.toggleEmployeeManager(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="badge" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> MGI Employees
                                </button>
                            )}
                            {hasPerm('manage_statuses') && (
                                <button onClick={() => { if(window.toggleStatusManager) window.toggleStatusManager(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="settings_suggest" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> Manage Statuses
                                </button>
                            )}
                            {hasPerm('manage_sources') && (
                                <button onClick={() => { if(window.toggleSourceManager) window.toggleSourceManager(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="share" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> Manage Sources
                                </button>
                            )}
                            {hasPerm('manage_rpa') && (
                                <button onClick={() => { if(window.toggleRpaConfig) window.toggleRpaConfig(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="settings_remote" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> RPA Config
                                </button>
                            )}
                            {hasPerm('manage_task_limits') && (
                                <button onClick={() => { if(window.toggleTaskLimitsModal) window.toggleTaskLimitsModal(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="block" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> Task Limits
                                </button>
                            )}
                            {hasPerm('manage_server_allocation') && (
                                <button onClick={() => { if(window.toggleServerAllocation) window.toggleServerAllocation(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="vibration" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> Server Allocation
                                </button>
                            )}
                            {hasPerm('view_user_activity') && (
                                <button onClick={() => { if(window.toggleUserActivity) window.toggleUserActivity(); }} className="w-full flex items-center gap-2 px-3 py-1 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-semibold text-[13px] transition-colors group text-left">
                                    <Icon name="history" className="text-[18px] text-slate-400 group-hover:text-indigo-500 transition-colors" /> User Activity
                                </button>
                            )}
                        </div>
                    
                    <div className="mt-2 mb-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                        <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Theme Settings</span>
                        
                        <div className="flex items-center justify-between mb-2">
                            <div className="flex items-center gap-2">
                                <Icon name="dark_mode" className="text-[16px] text-indigo-600" />
                                <span className="text-[12px] font-bold text-slate-800">Dark Mode</span>
                            </div>
                            <div className="w-8 h-5 bg-slate-300 rounded-full relative cursor-pointer hover:bg-slate-400 transition-colors" title="Dark Mode (Not Available in Modern View)">
                                <div className="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm"></div>
                            </div>
                        </div>
                        
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Icon name="zoom_in" className="text-[16px] text-indigo-600" />
                                <span className="text-[12px] font-bold text-slate-800">Zoom</span>
                            </div>
                            <div className="flex items-center gap-2 border border-slate-200 bg-white rounded-lg px-2 py-1 shadow-sm">
                                <button 
                                    onClick={() => adjustZoom(-0.1)}
                                    className="text-[14px] text-slate-400 hover:text-indigo-600 transition-colors flex items-center justify-center p-0.5 active:scale-90"
                                >
                                    <Icon name="remove" />
                                </button>
                                <span className="text-[11px] font-bold text-indigo-600 w-9 text-center">{Math.round(zoom * 100)}%</span>
                                <button 
                                    onClick={() => adjustZoom(0.1)}
                                    className="text-[14px] text-slate-400 hover:text-indigo-600 transition-colors flex items-center justify-center p-0.5 active:scale-90"
                                >
                                    <Icon name="add" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

