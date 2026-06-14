// --- Global Fetch Interceptor for Auth & Permissions ---
(function() {
    let isRedirecting = false;
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        try {
            const response = await originalFetch(...args);
            // Check for authentication or permission failures (401 Unauthorized / 403 Forbidden)
            if (response.status === 401 || response.status === 403) {
                if (isRedirecting) return new Promise(() => {}); // Already handled
                
                // Determine if this is an internal API call
                const url = typeof args[0] === 'string' ? args[0] : (args[0] instanceof Request ? args[0].url : '');
                if (url.includes('/api/') || url.includes('api/')) {
                    const data = await response.clone().json().catch(() => ({}));
                    if (data.auth_failed || response.status === 403) {
                        if (window.isPublicMode) return response; // DON'T redirect if we are in public view mode
                        isRedirecting = true;
                        const isViewPage = window.location.pathname.includes('/view/');
                        window.location.href = isViewPage ? '../login.php' : 'login.php';
                        return new Promise(() => {}); // Stop further processing
                    }
                }
            }
            return response;
        } catch (error) {
            throw error;
        }
    };
})();

// --- UI Utilities ---
window.showNotification = function(message, type = 'success') {
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.style.cssText = `padding: 12px 24px; border-radius: 8px; color: white; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 10px; animation: toastSlideIn 0.3s ease-out; background: ${type === 'success' ? '#10b981' : (type === 'info' ? '#3b82f6' : '#ef4444')};`;
    toast.innerHTML = `<span class="material-icons" style="font-size: 18px;">${type === 'success' ? 'check_circle' : (type === 'info' ? 'info' : 'error')}</span><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.transition = '0.3s'; toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 3000);
};
window.showToast = window.showNotification;
if (!document.getElementById('toast-style')) {
    const s = document.createElement('style'); s.id='toast-style'; s.innerHTML = `@keyframes toastSlideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }`;
    document.head.appendChild(s);
}

// --- Global Utility Functions (Defined First) ---
function getApiUrl(endpoint) {
    const isViewPage = window.location.pathname.includes('/view/');
    return isViewPage ? `../api/${endpoint}` : `api/${endpoint}`;
}

// Global Sorting State for Job List
window.currentJobSortBy = 'created_at';
window.currentJobSortOrder = 'DESC';

window.handleJobSort = function(field) {
    if (window.currentJobSortBy === field) {
        window.currentJobSortOrder = window.currentJobSortOrder === 'ASC' ? 'DESC' : 'ASC';
    } else {
        window.currentJobSortBy = field;
        window.currentJobSortOrder = field === 'created_at' || field === 'task_no' ? 'DESC' : 'ASC';
    }
    updateJobSortUI();
    if (typeof applyJobFilters === 'function') applyJobFilters();
};

window.updateJobSortUI = function() {
    document.querySelectorAll('#jobTable thead th.sortable').forEach(th => {
        const sortField = th.getAttribute('data-sort');
        const icon = th.querySelector('.sort-icon');
        if (!icon) return;

        th.classList.remove('active-sort');
        if (sortField === window.currentJobSortBy) {
            th.classList.add('active-sort');
            icon.innerText = window.currentJobSortOrder === 'ASC' ? ' ↑' : ' ↓';
        } else {
            icon.innerText = ' ↕';
        }
    });
};

window.allJobsData = [];
window.taskViewMode = 'my';

window.setTaskViewMode = function(mode) {
    window.taskViewMode = mode;
    
    const btnAll = document.getElementById('btn-view-all-tasks');
    const btnMy = document.getElementById('btn-view-my-tasks');
    
    if (btnAll && btnMy) {
        if (mode === 'all') {
            btnAll.classList.add('active');
            btnMy.classList.remove('active');
        } else {
            btnAll.classList.remove('active');
            btnMy.classList.add('active');
        }
    }
    
    if (typeof window.applyJobFilters === 'function') {
        window.applyJobFilters();
    }
};

window.applyJobFilters = function() {
    const jdFilter = (document.getElementById('filterJdId')?.value || '').toLowerCase().trim();
    const statusFilter = document.getElementById('filterStatus')?.value || 'all';
    const userFilter = (document.getElementById('filterCreatedBy')?.value || '').toLowerCase().trim();
    
    let viewMode = window.taskViewMode || 'all';
    
    // Enforce view_my_task if view_all_task is disabled (except for root admin)
    let perms = window.currentUserPermissions;
    if (typeof perms === 'string') {
        try { perms = JSON.parse(perms); } catch(e) { perms = {}; }
    }
    if (!perms) perms = {};
    const isRoot = (window.currentUser?.id && window.currentUser.id === window.rootAdminId);
    const hasAll = isRoot || 
                   perms['view_all_task'] === true || perms['view_all_task'] === "true" || 
                   perms['view_all_task'] === 1 || perms['view_all_task'] === "1" || perms['view_all_task'] === "on";
                   
    if (!hasAll) {
        viewMode = 'my';
    }

    console.log(`Filtering: JD="${jdFilter}", Status="${statusFilter}", User="${userFilter}", ViewMode="${viewMode}"`);

    const filtered = window.allJobsData.filter(job => {
        const jd = String(job.jd_id || "").toLowerCase();
        const title = String(job.job_title || "").toLowerCase();
        const taskNo = String(job.task_no || "").toLowerCase();
        const user = String(job.created_by || 'System').toLowerCase();
        const creatorName = String(job.creator_name || '').toLowerCase();

        // Search matches JD, Title, or Task No
        const matchesJd = !jdFilter || (jd.includes(jdFilter) || title.includes(jdFilter) || taskNo.includes(jdFilter));
        // Match both username and full name
        const matchesUser = !userFilter || (user.includes(userFilter) || creatorName.includes(userFilter));

        let matchesStatus = true;
        if (statusFilter !== 'all') {
            const rawStatus = (job.status || '').toLowerCase().trim();
            // Match the actual status record from the DB
            matchesStatus = (rawStatus === statusFilter.toLowerCase());
        }
        
        let matchesViewMode = true;
        if (viewMode === 'my') {
            const loggedInUser = (window.currentUser?.id || '').toLowerCase().trim();
            const jobCreator = (job.created_by || '').toLowerCase().trim();
            matchesViewMode = (loggedInUser === jobCreator);
        }

        return matchesJd && matchesUser && matchesStatus && matchesViewMode;
    });

    // Apply Sorting
    filtered.sort((a, b) => {
        let valA = a[window.currentJobSortBy] || '';
        let valB = b[window.currentJobSortBy] || '';

        // Numerical sort for Task No (TASK1, TASK2, etc)
        if (window.currentJobSortBy === 'task_no') {
            const numA = parseInt(String(valA).replace(/\D/g, '')) || 0;
            const numB = parseInt(String(valB).replace(/\D/g, '')) || 0;
            return window.currentJobSortOrder === 'ASC' ? numA - numB : numB - numA;
        }

        // Case-insensitive string sort for others
        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();

        if (valA < valB) return window.currentJobSortOrder === 'ASC' ? -1 : 1;
        if (valA > valB) return window.currentJobSortOrder === 'ASC' ? 1 : -1;
        return 0;
    });

    if (typeof renderJobList === 'function') renderJobList(filtered);
};

// Dynamic status globals
let allStatuses = [];
let allEmployees = [];

async function loadStatuses() {
    try {
        const res = await fetch(getApiUrl('status_api.php?action=list'));
        const result = await res.json();
        if (result.status === 'success') {
            allStatuses = result.data;
            populateStatusFilter();
        }
    } catch(e) { console.error('Failed to load statuses', e); }
}

function populateStatusFilter() {
    const filter = document.getElementById('filterStatus');
    if (!filter) return;
    
    // Keep "All" and clear others
    filter.innerHTML = '<option value="all">All</option>';
    
    allStatuses.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.status_name;
        opt.textContent = s.status_name;
        filter.appendChild(opt);
    });
}

window.toggleUserManager = function() {
    const modal = document.getElementById('userManagerModal');
    if (!modal) return;
    const isOpening = modal.style.display !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    
    if (isOpening) {
        // Reset form visibility when opening
        const addUserForm = document.getElementById('addUserForm');
        if (addUserForm) addUserForm.style.display = 'none';
        if (typeof updateUserBtnText === 'function') updateUserBtnText(false);

        // Instant Permission Check for Create Button
        const createBtn = document.getElementById('toggleUserFormBtn');
        if (createBtn) {
            const perms = window.currentUserPermissions || {};
            const isSuper = window.isSuperAdmin || (window.currentUser && window.currentUser.role === 'super-admin');
            
            const canCreate = isSuper || 
                             perms['create_user'] === true || 
                             perms['create_user'] === "true" || 
                             perms['create_user'] === 1 || 
                             perms['create_user'] === "1" || 
                             perms['create_user'] === "on";
                             
            createBtn.style.display = canCreate ? 'flex' : 'none';
        }
        loadUsers();
        loadEmployeeRefs();
    }
};

function toggleStatusManager() {
    const modal = document.getElementById('statusManagerModal');
    if (!modal) return;
    const isOpening = modal.style.display !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    if (isOpening) renderStatusList();
}

function renderStatusList() {
    const container = document.getElementById('statusList');
    if (!container) return;
    container.innerHTML = '';
    allStatuses.forEach((s, idx) => {
        const item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 15px;background:var(--bg);border:1px solid var(--border);border-radius:10px;color:var(--text);';
        item.innerHTML = `
            <span class="material-icons" style="color:var(--text-light);font-size:18px;">drag_indicator</span>
            <span style="flex:1;font-weight:600;font-size:0.9rem;">${s.status_name}</span>
            <button onclick="reorderStatus(${s.id},-1)" style="background:none;border:none;cursor:pointer;padding:2px;" title="Move Up"><span class="material-icons" style="font-size:18px;color:var(--text-light);">arrow_upward</span></button>
            <button onclick="reorderStatus(${s.id},1)" style="background:none;border:none;cursor:pointer;padding:2px;" title="Move Down"><span class="material-icons" style="font-size:18px;color:var(--text-light);">arrow_downward</span></button>
            <button onclick="deleteStatus(${s.id},'${s.status_name}')" style="background:none;border:none;cursor:pointer;padding:2px;" title="Delete"><span class="material-icons" style="font-size:18px;color:#ef4444;">delete</span></button>
        `;
        container.appendChild(item);
    });
}

async function addStatus() {
    const input = document.getElementById('newStatusInput');
    const name = input.value.trim();
    if (!name) return alert('Please enter a status name.');
    try {
        const res = await fetch(getApiUrl('status_api.php?action=add'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status_name: name })
        });
        const result = await res.json();
        if (result.status === 'success') {
            input.value = '';
            await loadStatuses();
            renderStatusList();
        } else {
            alert(result.message || 'Failed to add status.');
        }
    } catch(e) { console.error(e); }
}

async function deleteStatus(id, name) {
    if (!confirm(`Delete status "${name}"?`)) return;
    try {
        const res = await fetch(getApiUrl('status_api.php?action=delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.status === 'success') {
            await loadStatuses();
            renderStatusList();
        } else {
            alert(result.message || 'Failed to delete status.');
        }
    } catch(e) { console.error(e); }
}

async function reorderStatus(id, direction) {
    const idx = allStatuses.findIndex(s => s.id == id);
    if (idx < 0) return;
    const swapIdx = idx + direction;
    if (swapIdx < 0 || swapIdx >= allStatuses.length) return;

    // Swap in local array
    [allStatuses[idx], allStatuses[swapIdx]] = [allStatuses[swapIdx], allStatuses[idx]];
    const ordered_ids = allStatuses.map(s => s.id);

    try {
        await fetch(getApiUrl('status_api.php?action=reorder'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ordered_ids })
        });
        renderStatusList();
    } catch(e) { console.error(e); }
}

async function updateJobStatus(jobId, newStatus) {
    try {
        const res = await fetch(getApiUrl('update_job_status.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: jobId, status: newStatus })
        });
        const result = await res.json();
        if (result.status !== 'success') {
            alert('Failed to update job status: ' + (result.message || ''));
            loadJobList(); // Revert
        }
    } catch(e) { console.error(e); }
}

function toggleFilterBar(e) {
    if (e) e.stopPropagation();
    const bar = document.getElementById('filterBar');
    if (bar) {
        const isHidden = bar.style.display === 'none' || bar.style.display === '';
        bar.style.display = isHidden ? 'flex' : 'none';
    }
}

// Sidebar Logic
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

// Global click handler to close menu/sidebar if clicking outside
window.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        // Prevent closing if we clicked inside a modal or its overlay
        if (!e.target.closest('.modal-content') && !e.target.closest('.modal-overlay')) {
            sidebar.classList.remove('open');
        }
    }

    // Close AI Chat when clicking outside
    const chatWindow = document.getElementById('aiChatWindow');
    const chatButton = document.getElementById('aiChatButton');
    if (chatWindow && chatWindow.style.display === 'flex' && !chatWindow.contains(e.target) && !chatButton.contains(e.target)) {
        // Only close if not clicking on the toggle button which has its own listener
        toggleAiChat();
    }

    // Close employee suggestions when clicking outside
    const suggestions = document.getElementById('empSuggestions');
    const searchInput = document.getElementById('userEmpSearch');
    if (suggestions && !suggestions.contains(e.target) && !searchInput.contains(e.target)) {
        suggestions.style.display = 'none';
    }
});

function toggleUserDropdown(e) {
    if (e) e.stopPropagation();
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

function toggleEmployeeManager() {
    const modal = document.getElementById('employeeManagerModal');
    if (modal) {
        const isOpening = modal.style.display !== 'flex';
        modal.style.display = isOpening ? 'flex' : 'none';
        if (isOpening) {
            // Check for Add permission
            const addBtn = document.getElementById('toggleEmployeeFormBtn');
            if (addBtn) {
                const hasActionPerm = window.isSuperAdmin || (window.currentUserPermissions?.manage_employee_actions || window.currentUserPermissions?.manage_employee_actions === "on");
                addBtn.style.display = hasActionPerm ? 'flex' : 'none';
            }
            loadEmployeeFilters();
            loadEmployees();
        }
    }
}

async function loadEmployeeFilters() {
    try {
        const response = await fetch(getApiUrl('employee_api.php?action=get_filters'));
        const result = await response.json();
        if (result.status === 'success') {
            const deptFilter = document.getElementById('employeeDeptFilter');
            const locFilter = document.getElementById('employeeLocFilter');
            const floorFilter = document.getElementById('employeeFloorFilter');
            
            if (deptFilter) {
                const currentVal = deptFilter.value;
                deptFilter.innerHTML = '<option value="">All Departments</option>';
                result.departments.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    deptFilter.appendChild(opt);
                });
                deptFilter.value = currentVal;
            }
            
            if (locFilter) {
                const currentVal = locFilter.value;
                locFilter.innerHTML = '<option value="">All Locations</option>';
                result.locations.forEach(l => {
                    const opt = document.createElement('option');
                    opt.value = l;
                    opt.textContent = l;
                    locFilter.appendChild(opt);
                });
                locFilter.value = currentVal;
            }

            if (floorFilter) {
                const currentVal = floorFilter.value;
                floorFilter.innerHTML = '<option value="">All Floors</option>';
                result.floors.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f;
                    opt.textContent = f;
                    floorFilter.appendChild(opt);
                });
                floorFilter.value = currentVal;
            }
        }
    } catch (e) { console.error(e); }
}

function clearEmployeeFilters() {
    const search = document.getElementById('employeeSearchInput');
    const dept = document.getElementById('employeeDeptFilter');
    const loc = document.getElementById('employeeLocFilter');
    const floor = document.getElementById('employeeFloorFilter');
    if (search) search.value = '';
    if (dept) dept.value = '';
    if (loc) loc.value = '';
    if (floor) floor.value = '';
    loadEmployees();
}

function toggleProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        const isOpening = modal.style.display !== 'flex';
        modal.style.display = isOpening ? 'flex' : 'none';
        if (isOpening) loadProfileData();
    }
}

async function loadProfileData() {
    const grid = document.getElementById('profileDetailsGrid');
    if (!grid) return;
    
    try {
        const response = await fetch(getApiUrl('user_api.php?action=get_profile'));
        const result = await response.json();
        
        if (result.status === 'success') {
            const d = result.data;
            
            // Update Hero Section
            document.getElementById('profileModalName').innerText = d.full_name || 'User';
            document.getElementById('profileModalRoleBadge').innerText = d.role ? d.role.charAt(0).toUpperCase() + d.role.slice(1) : 'User';
            document.getElementById('profileModalEmpId').innerText = d.employee_id || 'N/A';
            
            const picEl = document.getElementById('profileModalPic');
            const fallbackEl = document.getElementById('profileModalFallback');
            if (d.profile_pic) {
                if (picEl) {
                    picEl.src = getMediaUrl(d.profile_pic);
                    picEl.style.display = 'block';
                }
                if (fallbackEl) fallbackEl.style.display = 'none';
            } else {
                if (picEl) picEl.style.display = 'none';
                if (fallbackEl) fallbackEl.style.display = 'flex';
            }

            // Update Details Grid
            const items = [
                { icon: 'email', label: 'Email Address', value: d.email },
                { icon: 'phone', label: 'Mobile Number', value: d.mobile_no },
                { icon: 'work', label: 'Designation', value: d.designation },
                { icon: 'business', label: 'Department', value: d.department },
                { icon: 'lan', label: 'IP Phone', value: d.ip_no },
                { icon: 'layers', label: 'Office Floor', value: d.floor }
            ];

            grid.innerHTML = items.map(item => `
                <div style="background: var(--bg); padding: 16px; border-radius: 16px; border: 1px solid var(--border); display: flex; align-items: center; gap: 16px; transition: all 0.2s ease;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: var(--card-bg); display: flex; align-items: center; justify-content: center; color: var(--primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border);">
                        <span class="material-icons" style="font-size: 20px;">${item.icon}</span>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-light); font-weight: 600; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 2px;">${item.label}</div>
                        <div style="font-size: 0.95rem; color: var(--text); font-weight: 600;">${item.value || 'Not specified'}</div>
                    </div>
                </div>
            `).join('');
            
        } else {
            grid.innerHTML = `<div style="grid-column: span 2; text-align: center; color: #dc2626; padding: 40px;">
                <span class="material-icons" style="font-size: 48px; display: block; margin-bottom: 15px;">error_outline</span>
                <p style="font-weight: 600;">${result.message || 'Error loading profile'}</p>
            </div>`;
        }
    } catch (e) { 
        console.error(e); 
        grid.innerHTML = `<div style="grid-column: span 2; text-align: center; color: #dc2626; padding: 40px;">
            <span class="material-icons" style="font-size: 48px; display: block; margin-bottom: 15px;">cloud_off</span>
            <p style="font-weight: 600;">Failed to connect to server.</p>
        </div>`;
    }
}

async function uploadProfilePic() {
    const fileInput = document.getElementById('profileUpload');
    if (!fileInput.files[0]) return;

    const formData = new FormData();
    formData.append('profile_pic', fileInput.files[0]);

    try {
        const response = await fetch(getApiUrl('user_api.php?action=update_profile_pic'), {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.status === 'success') {
            const newUrl = getMediaUrl(result.path);
            
            // Update Modal UI
            const picEl = document.getElementById('profileModalPic');
            if (picEl) {
                picEl.src = newUrl;
                picEl.style.display = 'block';
                const fallbackEl = document.getElementById('profileModalFallback');
                if (fallbackEl) fallbackEl.style.display = 'none';
            }
            
            // Update Header UI
            const headerPicEl = document.getElementById('headerProfilePic');
            if (headerPicEl) {
                headerPicEl.src = newUrl;
                headerPicEl.style.display = 'block';
                const headerFallbackEl = document.getElementById('headerProfileFallback');
                if (headerFallbackEl) headerFallbackEl.style.display = 'none';
            }
            
            showNotification('Profile picture updated successfully!', 'success');
        } else {
            showNotification(result.message || 'Failed to update picture.', 'error');
        }
    } catch (e) { 
        console.error(e);
        showNotification('An error occurred while uploading.', 'error');
    }
}

function toggleAddTask() {
    const modal = document.getElementById('addTaskModal');
    if (modal) {
        modal.classList.toggle('show');
        const isShowing = modal.classList.contains('show');
        modal.style.display = isShowing ? 'flex' : 'none';
        
        if (isShowing) {
            // Auto-generate JD ID if it's empty
            const jdInput = document.getElementById('publicJdId');
            if (jdInput && !jdInput.value) {
                generateJdId();
            }
            // Load sources dynamically
            window.loadSourcesForAddTask();
        }
    }
}

function toggleMandatoryReq() {
    const section = document.getElementById('mandatoryReqSection');
    const icon = document.getElementById('reqToggleIcon');
    if (section && icon) {
        const isHidden = section.style.display === 'none';
        section.style.display = isHidden ? 'block' : 'none';
        icon.textContent = isHidden ? 'remove' : 'add';
    }
}

function generateJdId() {
    const jdInput = document.getElementById('publicJdId');
    if (!jdInput) return;
    
    // Simple random JD ID generator 
    const randomNum = Math.floor(10000 + Math.random() * 90000);
    jdInput.value = 'JD' + randomNum;
}

window.toggleUploadOptions = function() {
    const sourceRadio = document.querySelector('input[name="taskSource"]:checked');
    if (!sourceRadio) return;
    
    const source = sourceRadio.value.toLowerCase();
    const uploadSection = document.getElementById('uploadSection');
    const jdUploadGroup = document.getElementById('jdUploadGroup');
    const cvUploadGroup = document.getElementById('cvUploadGroup');

    if (source === 'manual') {
        uploadSection.style.display = 'block';
        jdUploadGroup.style.display = 'block';
        cvUploadGroup.style.display = 'block';
    } else if (source === 'both') {
        uploadSection.style.display = 'block';
        jdUploadGroup.style.display = 'none';
        cvUploadGroup.style.display = 'block';
    } else {
        // bdjobs, linkedin, upcoming, etc.
        uploadSection.style.display = 'none';
        jdUploadGroup.style.display = 'none';
        cvUploadGroup.style.display = 'none';
    }
};

window.toggleCvInputMode = function() {
    const mode = document.querySelector('input[name="cvUploadMode"]:checked').value;
    const fileInput = document.getElementById('cvUploadFiles');
    const folderInput = document.getElementById('cvUploadFolder');
    
    if (mode === 'files') {
        fileInput.style.display = 'block';
        folderInput.style.display = 'none';
    } else {
        fileInput.style.display = 'none';
        folderInput.style.display = 'block';
    }
}

// --- User Management Functions ---
// Removed duplicate toggleUserManager function

function toggleAddUserForm() {
    const form = document.getElementById('addUserForm');
    if (form) {
        const isShowing = form.style.display === 'block';
        form.style.display = isShowing ? 'none' : 'block';
        updateUserBtnText(!isShowing);
    }
}

function updateUserBtnText(isFormVisible) {
    const btnText = document.getElementById('userBtnText');
    const btnIcon = document.querySelector('#toggleUserFormBtn .material-icons');
    const subTitle = document.getElementById('userManagerSubTitle');
    
    if (isFormVisible) {
        if (btnText) btnText.innerText = 'Back to List';
        if (btnIcon) btnIcon.innerText = 'arrow_back';
        if (subTitle) subTitle.innerText = 'Create New User';
    } else {
        if (btnText) btnText.innerText = 'Create New User';
        if (btnIcon) btnIcon.innerText = 'add';
        if (subTitle) subTitle.innerText = 'System Users List';
    }
}

async function loadEmployeeRefs() {
    try {
        const response = await fetch(getApiUrl(`employee_api.php?action=list&t=${Date.now()}`));
        const result = await response.json();
        if (result.status === 'success') {
            allEmployees = result.data;
        }
    } catch (e) { console.error(e); }
}

let employeeSearchTimeout = null;
async function searchEmployees(query) {
    const suggestions = document.getElementById('empSuggestions');
    const hiddenInput = document.getElementById('userEmpRef');
    if (!suggestions) return;
    
    // Clear selection if we start typing again
    if (hiddenInput && hiddenInput.value) hiddenInput.value = '';

    if (!query || query.length < 1) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
        return;
    }

    // Debounce to prevent too many API calls
    clearTimeout(employeeSearchTimeout);
    employeeSearchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(getApiUrl(`employee_api.php?action=list&search=${encodeURIComponent(query)}&t=${Date.now()}`));
            const result = await response.json();
            
            if (result.status === 'success' && result.data.length > 0) {
                suggestions.innerHTML = result.data.slice(0, 10).map(emp => `
                    <div class="suggestion-item" onclick="selectEmployee('${emp.employee_id}', '${emp.full_name.replace(/'/g, "\\'")}')">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <span class="suggestion-name">${emp.full_name}</span>
                            <span class="suggestion-id">ID: ${emp.employee_id}</span>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 2px; margin-top: 4px;">
                            <span style="font-size: 0.75rem; color: #6366f1; font-weight: 600;">${emp.designation}</span>
                            <div style="display: flex; gap: 10px; color: #64748b; font-size: 0.7rem;">
                                <span style="display: flex; align-items: center; gap: 3px;"><span class="material-icons" style="font-size: 12px;">business</span> ${emp.department}</span>
                                <span style="display: flex; align-items: center; gap: 3px;"><span class="material-icons" style="font-size: 12px;">email</span> ${emp.email || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                `).join('');
                suggestions.style.display = 'block';
            } else {
                suggestions.innerHTML = '<div style="padding: 12px 15px; color: #64748b; font-size: 0.85rem; background: white;">No matching employee found</div>';
                suggestions.style.display = 'block';
            }
        } catch (e) {
            console.error(e);
        }
    }, 300);
}

function selectEmployee(id, name) {
    const searchInput = document.getElementById('userEmpSearch');
    const hiddenInput = document.getElementById('userEmpRef');
    const suggestions = document.getElementById('empSuggestions');
    
    if (searchInput) searchInput.value = `${name} (${id})`;
    if (hiddenInput) hiddenInput.value = id;
    if (suggestions) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
    }
}

async function loadUsers() {
    const tbody = document.getElementById('userListBody');
    if (!tbody) return;

    const search = document.getElementById('userSearchInput')?.value || '';
    
    try {
        const response = await fetch(getApiUrl(`user_api.php?action=list&search=${encodeURIComponent(search)}&t=${Date.now()}`));
        const result = await response.json();
        if (result.status === 'success') {
            tbody.innerHTML = '';
            
            const ROOT_ID = window.rootAdminId || "097727";
            result.data.sort((a, b) => {
                // 1. ROOT ADMIN ALWAYS FIRST
                if (a.employee_id === ROOT_ID) return -1;
                if (b.employee_id === ROOT_ID) return 1;

                // 2. Role Priority
                const roleOrder = { 'super-admin': 0, 'admin': 1, 'sub-admin': 2, 'user': 3 };
                const rA = roleOrder[a.role] ?? 99;
                const rB = roleOrder[b.role] ?? 99;
                if (rA !== rB) return rA - rB;

                // 3. Status Priority (active first, then pending, then blocked)
                const statusOrder = { 'active': 0, 'pending': 1, 'blocked': 2 };
                const sA = statusOrder[a.status] ?? 99;
                const sB = statusOrder[b.status] ?? 99;
                return sA - sB;
            });

            result.data.forEach(user => {
                const tr = document.createElement('tr');
                const isPending = user.status === 'pending';
                const isBlocked = user.status === 'blocked';
                const isSuperAdminUser = user.role === 'super-admin';
                const loggedInRole = window.currentUser?.role || '';
                const loggedInEmpId = window.currentUser?.id || '';
                const isSelf = String(user.id) === String(window.currentUser?.db_id);
                const ROOT_ADMIN_ID = window.rootAdminId || "097727";
                const isRootAdmin = loggedInEmpId === ROOT_ADMIN_ID;
                const isTargetRootAdmin = user.employee_id === ROOT_ADMIN_ID;
                const isEditing = window.editingUserRoleId !== null && String(window.editingUserRoleId) === String(user.id);
                
                // 1. HIDE Logic:
                if (isTargetRootAdmin && !isRootAdmin) {
                    return; // Skip rendering the Root Admin row
                }
                if (isSuperAdminUser && loggedInRole !== 'super-admin') {
                    return; // Skip rendering any Super Admin for lower roles
                }

                const statusBadge = `<span class="badge ${user.status === 'active' ? 'badge-success' : (isPending ? 'badge-warning' : 'badge-danger')}">${user.status}</span>`;
                
                // Role Column: Show dropdown if editing
                let roleHtml = `<span class="badge badge-info">${user.role}</span>`;
                if (isEditing) {
                    roleHtml = `
                        <select id="inlineRole_${user.id}" style="padding: 2px 5px; border-radius: 4px; border: 1px solid #6366f1; font-size: 0.75rem;">
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                            <option value="sub-admin" ${user.role === 'sub-admin' ? 'selected' : ''}>Sub-Admin</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                            <option value="super-admin" ${user.role === 'super-admin' ? 'selected' : ''} ${!isRootAdmin ? 'disabled' : ''}>Super-Admin</option>
                        </select>
                    `;
                }
                
                // - Root Super Admin (097727) can manage EVERYONE (except self).
                // - Other Super Admins can manage everyone EXCEPT other Super Admins.
                // - Admin (with manage_actions) can manage 'user' and 'sub-admin'.
                
                const canManageActionsThisUser = !isSelf && (
                    isRootAdmin || 
                    (!isSuperAdminUser && (window.isSuperAdmin || (window.canManageActions && (user.role === 'user' || user.role === 'sub-admin'))))
                );

                let actionHtml = '';
                if (isSelf) {
                    actionHtml = '<small style="color:#94a3b8; font-style:italic;">Your Account (N/A)</small>';
                } else if (isEditing) {
                    actionHtml = `
                        <button onclick="saveInlineRole(${user.id})" class="btn-primary" style="padding: 4px 8px; background: #10b981; border:none; font-size: 0.7rem;">Save</button>
                        <button onclick="cancelInlineEdit()" class="btn-secondary" style="padding: 4px 8px; font-size: 0.7rem;">Cancel</button>
                    `;
                } else {
                    const buttons = [];
                    
                    // 1. Permissions Button
                    const canManagePerms = window.canManageRoles && (isRootAdmin || !isSuperAdminUser);
                    if (canManagePerms) {
                        buttons.push(`<button onclick="openPermissionsModalFromBase64('${btoa(unescape(encodeURIComponent(JSON.stringify(user))))}')" class="btn-primary" style="padding: 5px 8px; background: #6366f1; border:none; font-size: 0.75rem;" title="Manage Permissions">Perms</button>`);
                    }

                    // 2. Action Buttons (Edit/Block/Delete)
                    if (canManageActionsThisUser) {
                        buttons.push(`<button onclick="startInlineEdit(${user.id})" class="btn-primary" style="padding: 5px 8px; background: #f59e0b; border:none; font-size: 0.75rem;" title="Edit Role">Edit</button>`);
                        buttons.push(`<button onclick="updateUserStatus(${user.id}, '${user.status === 'active' ? 'blocked' : 'active'}')" class="btn-secondary" style="padding: 5px 8px; font-size: 0.75rem;">${user.status === 'active' ? 'Block' : 'Unblock'}</button>`);
                        buttons.push(`<button onclick="deleteUser(${user.id})" class="btn-danger" style="padding: 5px 8px; background: #ef4444; border:none; color:white; font-size: 0.75rem;">Delete</button>`);
                    }

                    if (isPending && window.isSuperAdmin) {
                        buttons.unshift(`<button onclick="approveUser(${user.id})" class="btn-primary" style="padding: 5px 8px; background: #10b981; border:none; font-size: 0.75rem;" title="Approve Registration">Approve</button>`);
                    }

                    actionHtml = buttons.length > 0 ? buttons.join(' ') : (isSuperAdminUser ? '<small style="color:#94a3b8; font-style:italic;">Protected</small>' : '-');
                }

                tr.innerHTML = `
                    <td style="text-align: left; padding: 6px 15px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 700; color: #0f172a; font-size: 0.85rem; line-height: 1.2;">${user.full_name || 'In-Progress Reg'}</div>
                        <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">ID: ${user.employee_id}</div>
                    </td>
                    <td style="text-align: left; padding: 6px 15px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 600; color: #475569; font-size: 0.8rem;">${user.designation || '-'}</div>
                    </td>
                    <td style="text-align: left; padding: 6px 15px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 600; color: #475569; font-size: 0.8rem;">${user.department || '-'}</div>
                    </td>
                    <td style="text-align: center; padding: 6px 15px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 600; color: #475569; font-size: 0.8rem; line-height: 1.2;">${user.ip_no || '-'}</div>
                        <div style="font-size: 0.7rem; color: #64748b;">${user.mobile_no || '-'}</div>
                    </td>
                    <td style="text-align:center; border-bottom: 1px solid #f1f5f9;">${roleHtml}</td>
                    <td style="text-align:center; border-bottom: 1px solid #f1f5f9;">${statusBadge}</td>
                    <td style="text-align:center; border-bottom: 1px solid #f1f5f9;">${new Date(user.created_at).toLocaleDateString()}</td>
                    <td style="text-align: center; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                            ${actionHtml}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { console.error(e); }
}

async function createUser() {
    const employee_id = document.getElementById('userEmpRef')?.value;
    const password = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('newConfirmPassword').value;
    const role = document.getElementById('newRole').value;

    if (!employee_id) return alert('Please select an Employee Reference');
    if (!password) return alert('Create Password is required');
    if (password.length < 6) return alert('Password must be at least 6 characters');
    if (password !== confirmPassword) return alert('Passwords do not match');

    try {
        const res = await fetch(getApiUrl('user_api.php?action=create'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: employee_id, password, role, employee_id })
        });
        const result = await res.json();
        if (result.status === 'success') {
            showNotification('User created successfully!', 'success');
            
            // Instant form reset
            document.getElementById('userEmpRef').value = '';
            document.getElementById('userEmpSearch').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('newConfirmPassword').value = '';
            
            toggleAddUserForm();
            loadUsers();
        } else {
            alert(result.message);
        }
    } catch (e) { console.error(e); }
}

async function updateUserStatus(id, status) {
    try {
        const res = await fetch(getApiUrl('user_api.php?action=update_status'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status })
        });
        if ((await res.json()).status === 'success') loadUsers();
    } catch (e) { console.error(e); }
}

async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    try {
        const res = await fetch(getApiUrl('user_api.php?action=delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        if ((await res.json()).status === 'success') loadUsers();
    } catch (e) { console.error(e); }
}

async function approveUser(id) {
    if (!confirm('Approve this user registration?')) return;
    try {
        const response = await fetch(getApiUrl('user_api.php?action=approve'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        if ((await response.json()).status === 'success') loadUsers();
    } catch (e) { console.error(e); }
}

// --- Employee Management Functions ---
// toggleEmployeeManager consolidated at top of file

function toggleAddEmployeeForm() {
    const form = document.getElementById('addEmployeeForm');
    if (form) {
        const isShowing = form.style.display === 'block';
        form.style.display = isShowing ? 'none' : 'block';
        if (!isShowing) {
            employeeAction = 'create';
            const empIdInput = document.getElementById('empId');
            if (empIdInput) {
                empIdInput.readOnly = false;
                empIdInput.style.background = '#fff';
            }
            document.querySelectorAll('#addEmployeeForm input').forEach(i => i.value = '');
        }
        updateEmployeeBtnText(!isShowing);
    }
}

function updateEmployeeBtnText(isFormVisible) {
    const btnText = document.getElementById('employeeBtnText');
    const btnIcon = document.querySelector('#toggleEmployeeFormBtn .material-icons');
    const subTitle = document.getElementById('employeeManagerSubTitle');
    
    if (isFormVisible) {
        if (btnText) btnText.innerText = 'Back to List';
        if (btnIcon) btnIcon.innerText = 'arrow_back';
        if (subTitle) subTitle.innerText = 'Employee Record';
    } else {
        if (btnText) btnText.innerText = 'Add New Employee';
        if (btnIcon) btnIcon.innerText = 'add';
        if (subTitle) subTitle.innerText = 'Employee Master List';
    }
}

let currentEmployeeData = [];
let employeeAction = 'create';

async function loadEmployees() {
    const tbody = document.getElementById('employeeListBody');
    const emptyState = document.getElementById('employeeEmptyState');
    if (!tbody) return;

    const search = document.getElementById('employeeSearchInput')?.value || '';
    const dept = document.getElementById('employeeDeptFilter')?.value || '';
    const loc = document.getElementById('employeeLocFilter')?.value || '';
    const floor = document.getElementById('employeeFloorFilter')?.value || '';

    try {
        const url = `employee_api.php?action=list&search=${encodeURIComponent(search)}&dept=${encodeURIComponent(dept)}&loc=${encodeURIComponent(loc)}&floor=${encodeURIComponent(floor)}&t=${Date.now()}`;
        const response = await fetch(getApiUrl(url));
        const result = await response.json();
        
        if (result.status === 'success') {
            currentEmployeeData = result.data;
            tbody.innerHTML = '';
            
            if (result.data.length === 0) {
                if (emptyState) emptyState.style.display = 'block';
                return;
            }
            if (emptyState) emptyState.style.display = 'none';

            result.data.forEach(emp => {
                const tr = document.createElement('tr');
                tr.style.transition = "background 0.2s";
                tr.onmouseover = () => tr.style.background = "#f1f5f9";
                tr.onmouseout = () => tr.style.background = "transparent";

                tr.innerHTML = `
                    <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 700; color: var(--primary); font-size: 0.95rem;">${emp.full_name}</div>
                        <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">ID: ${emp.employee_id}</div>
                    </td>
                    <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 600; color: #475569; font-size: 0.85rem;">${emp.designation}</div>
                        <div style="font-size: 0.75rem; color: #64748b;">${emp.department}${emp.sub_department ? ` (${emp.sub_department})` : ''}</div>
                    </td>
                    <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 600; color: #475569; font-size: 0.85rem;">${emp.office_location}</div>
                        <div style="font-size: 0.75rem; color: #64748b;">Floor: ${emp.floor} | IP: <span style="font-family: monospace;">${emp.ip_no}</span></div>
                    </td>
                    <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9;">
                        <div style="font-weight: 600; color: #475569; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                            <span class="material-icons" style="font-size: 14px;">email</span> ${emp.email}
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; display: flex; align-items: center; gap: 4px;">
                            <span class="material-icons" style="font-size: 14px;">phone</span> ${emp.mobile_no}
                        </div>
                    </td>
                    <td style="padding: 15px 20px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            ${(window.currentUserPermissions?.manage_employee_actions || window.isSuperAdmin) ? `
                            <button onclick="editEmployee('${emp.employee_id}')" class="btn-secondary" style="padding: 6px 12px; font-size: 0.75rem; border-radius: 6px; display: flex; align-items: center; gap: 4px;">
                                <span class="material-icons" style="font-size: 14px;">edit</span> Edit
                            </button>
                            <button onclick="deleteEmployee('${emp.employee_id}')" class="btn-danger" style="padding: 6px 12px; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; font-size: 0.75rem; border-radius: 6px; display: flex; align-items: center; gap: 4px;">
                                <span class="material-icons" style="font-size: 14px;">delete_outline</span> Delete
                            </button>
                            ` : `<span style="color:#94a3b8; font-size:0.75rem; font-style:italic;">No Actions</span>`}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (e) { console.error(e); }
}

async function saveEmployee() {
    const data = {
        employee_id: document.getElementById('empId').value,
        full_name: document.getElementById('empFullName').value,
        email: document.getElementById('empEmail').value,
        mobile_no: document.getElementById('empMobile').value,
        designation: document.getElementById('empDesignation').value,
        department: document.getElementById('empDepartment').value,
        sub_department: document.getElementById('empSubDepartment').value,
        ip_no: document.getElementById('empIpNo').value,
        office_location: document.getElementById('empOfficeLocation').value,
        floor: document.getElementById('empFloor').value
    };

    if (!data.employee_id || !data.full_name) return alert('ID and Name are required');

    try {
        const response = await fetch(getApiUrl(`employee_api.php?action=${employeeAction}`), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.status === 'success') {
            alert(employeeAction === 'create' ? 'Employee created!' : 'Employee updated!');
            employeeAction = 'create';
            toggleAddEmployeeForm();
            loadEmployees();
        } else { alert(result.message); }
    } catch (e) { console.error(e); }
}

async function deleteEmployee(id) {
    if (!confirm('Are you sure you want to delete this employee record?')) return;
    try {
        const response = await fetch(getApiUrl('employee_api.php?action=delete'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ employee_id: id })
        });
        if ((await response.json()).status === 'success') loadEmployees();
    } catch (e) { console.error(e); }
}

function editEmployee(id) {
    const emp = currentEmployeeData.find(e => e.employee_id === id);
    if (!emp) return;
    employeeAction = 'update';
    const form = document.getElementById('addEmployeeForm');
    if (form) form.style.display = 'block';
    updateEmployeeBtnText(true);
    document.getElementById('empId').value = emp.employee_id;
    document.getElementById('empId').readOnly = true;
    document.getElementById('empId').style.background = '#f1f5f9';
    document.getElementById('empFullName').value = emp.full_name;
    document.getElementById('empEmail').value = emp.email;
    document.getElementById('empMobile').value = emp.mobile_no;
    document.getElementById('empDesignation').value = emp.designation;
    document.getElementById('empDepartment').value = emp.department;
    document.getElementById('empSubDepartment').value = emp.sub_department || '';
    document.getElementById('empIpNo').value = emp.ip_no;
    document.getElementById('empOfficeLocation').value = emp.office_location || '';
    document.getElementById('empFloor').value = emp.floor;
}

window.changePassFromProfile = false;

function openChangePassFromProfile() {
    // Close profile, remember to return to it when change pass is closed
    window.changePassFromProfile = true;
    const profileModal = document.getElementById('profileModal');
    if (profileModal) profileModal.style.display = 'none';
    const passModal = document.getElementById('changePassModal');
    if (passModal) passModal.style.display = 'flex';
}

function toggleChangePass() {
    const modal = document.getElementById('changePassModal');
    if (!modal) return;

    const isClosing = modal.style.display === 'flex';
    modal.style.display = isClosing ? 'none' : 'flex';

    // If closing and it was opened from profile — go back to profile
    if (isClosing && window.changePassFromProfile) {
        window.changePassFromProfile = false;
        const profileModal = document.getElementById('profileModal');
        if (profileModal) {
            profileModal.style.display = 'flex';
            loadProfileData();
        }
    }
}

// Basic API Path Helper
// getApiUrl moved to top

const getMediaUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http') || path.startsWith('data:')) return path;
    const isViewPage = window.location.pathname.includes('/view/');
    return isViewPage ? `../${path}` : path;
};

// --- Dashboard Logic (List Loading) ---
let currentPage = 1;
let currentSortBy = 'created_at';
let currentSortOrder = 'ASC';
const urlParams = new URLSearchParams(window.location.search);
let selectedJobTitle = urlParams.get('job_title') || '';
let selectedJdId = urlParams.get('jd_id') || '';
let selectedToken = urlParams.get('token') || '';

async function loadCandidates(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput')?.value.trim() || '';
    const shortlisted = document.getElementById('shortlistedFilter')?.value || '';
    const confirmation = document.getElementById('confirmationFilter')?.value || '';
    const topN = document.getElementById('topFilter')?.value || '';

    let url = `${getApiUrl('get_candidates.php')}?page=${page}&search=${encodeURIComponent(search)}&shortlisted=${shortlisted}&confirmation=${confirmation}&sort_by=${currentSortBy}&sort_order=${currentSortOrder}&top_n=${topN}`;

    if (selectedJdId) {
        url += `&jd_id=${encodeURIComponent(selectedJdId)}`;
    } else if (selectedJobTitle) {
        url += `&job_title=${encodeURIComponent(selectedJobTitle)}`;
    }

    if (selectedToken) {
        url += `&token=${encodeURIComponent(selectedToken)}`;
    }

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const result = await response.json();

        if (result.status === 'success') {
            renderTable(result.data, result.pagination);
            
            // Handle Pagination Visibility for Top N
            const paginationContainer = document.getElementById('pagination');
            if (topN > 0) {
                if (paginationContainer) paginationContainer.innerHTML = '';
            } else {
                renderPagination(result.pagination);
            }
            
            updateSortUI();
            updateDashboardTitle(result.data);

            // Toggle Export button visibility
            const exportBtn = document.getElementById('exportCsv');
            const expandBtn = document.getElementById('expandAllBtn');
            if (exportBtn) exportBtn.style.display = (result.data && result.data.length > 0) ? 'flex' : 'none';
            if (expandBtn) expandBtn.style.display = (result.data && result.data.length > 0) ? 'flex' : 'none';
            const mailBtn = document.getElementById('mailToConcernBtn');
            if (mailBtn) mailBtn.style.display = (result.data && result.data.length > 0 && window.hasConcernEmail) ? 'flex' : 'none';
        }
    } catch (error) {
        console.error('Error loading candidates:', error);
    }
}

window.sendMailToConcern = async function() {
    if (!selectedJdId) {
        showToast("No JD ID selected.", "error");
        return;
    }

    const search = document.getElementById('searchInput')?.value.trim() || '';
    const shortlisted = document.getElementById('shortlistedFilter')?.value || '';
    const confirmation = document.getElementById('confirmationFilter')?.value || '';
    const topN = document.getElementById('topFilter')?.value || '';

    let filterDesc = [];
    if (shortlisted !== '') filterDesc.push(shortlisted == '1' ? 'Shortlisted' : 'Not Shortlisted');
    if (confirmation !== '') filterDesc.push(confirmation == '1' ? 'Confirmed' : 'Not Confirmed');
    if (search) filterDesc.push(`Search: "${search}"`);
    if (topN) filterDesc.push(`Top ${topN}`);

    const filterText = filterDesc.length > 0 ? filterDesc.join(', ') : 'All Data';

    if (!confirm(`Are you sure you want to send the current filtered data (${filterText}) to the Concern Person via email?`)) {
        return;
    }

    showToast("Sending mail...", "info");

    try {
        const response = await fetch(`${getApiUrl('send_filtered_mail.php')}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                jd_id: selectedJdId,
                search: search,
                shortlisted: shortlisted,
                confirmation: confirmation,
                top_n: topN,
                token: selectedToken
            })
        });

        const result = await response.json();
        if (result.status === 'success') {
            showToast("Mail sent successfully to Concern Person!", "success");
        } else {
            showToast("Error: " + (result.message || "Failed to send mail"), "error");
        }
    } catch (error) {
        console.error('Error sending mail:', error);
        showToast("Network error. Could not send mail.", "error");
    }
}

function toggleExpandAll() {
    const table = document.getElementById('candidateTable');
    const icon = document.getElementById('expandAllIcon');
    if (!table) return;

    table.classList.toggle('expanded-all');
    
    if (table.classList.contains('expanded-all')) {
        if (icon) icon.innerText = 'unfold_less';
        showToast('Expanded all rows');
    } else {
        if (icon) icon.innerText = 'unfold_more';
        showToast('Collapsed rows');
    }
}

function updateDashboardTitle(data) {
    const titleSpan = document.getElementById('dynamicJobTitle');
    const jdSpan = document.getElementById('jdBadge');
    if (!titleSpan) return;

    if (selectedJobTitle) {
        titleSpan.innerText = selectedJobTitle;
        if (selectedJdId && jdSpan) jdSpan.innerText = selectedJdId;
    } else if (data.length > 0) {
        titleSpan.innerText = data[0].job_title || 'General';
        if (jdSpan) jdSpan.innerText = data[0].jd_id || '';
    } else if (selectedJdId) {
        titleSpan.innerText = 'Job View';
        if (jdSpan) jdSpan.innerText = selectedJdId;
    } else {
        titleSpan.innerText = 'All Jobs';
    }
}

function renderTable(data, pagination = null) {
    const tbody = document.getElementById('candidateBody');
    if (!tbody) return;

    tbody.innerHTML = '';
    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${window.isPublicMode ? 18 : 21}" style="text-align:center; padding: 20px;">No candidates found.</td></tr>`;
        return;
    }

    data.forEach((candidate, index) => {
        const sl = pagination ? (pagination.current_page - 1) * (pagination.limit || 20) + index + 1 : index + 1;
        const formattedEmails = (candidate.email_id || '').split(',').map(e => e.trim()).filter(e => e).join('<br>');
        const formattedPhones = (candidate.phone || '').split(',').map(p => p.trim()).filter(p => p).join('<br>');
        const isPublic = window.isPublicMode || false;
        const tokenParam = isPublic ? `&token=${encodeURIComponent(selectedToken)}` : '';

        const tr = document.createElement('tr');
        tr.id = `row-${candidate.id}`;
        tr.innerHTML = `
            <td style="text-align: center; vertical-align: middle; padding: 2px 2px !important;">
                <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem; line-height: 1.1;">${sl}</div>
                <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600; margin-top: 1px; border-top: 1px solid #f1f5f9; padding-top: 1px; line-height: 1;">${candidate.id}</div>
            </td>
            <td>
                <div style="position: relative; padding-right: 25px;">
                    <div style="font-weight: 700; color: var(--primary);">${candidate.name}</div>
                    <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 2px;">${formattedEmails}</div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 2px;">
                        <div style="font-size: 0.75rem; color: var(--primary); font-weight: 500;">${formattedPhones || '-'}</div>
                        <div style="margin-left: 10px; flex-shrink: 0; margin-bottom: 2px;">
                            <span style="font-size: 0.65rem; color: #475569; font-weight: 700; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; white-space: nowrap;">
                                ID: ${candidate.n8n_id || 'N/A'}
                            </span>
                        </div>
                    </div>
                    <a href="javascript:void(0)" onclick="openPDFModal('../api/view_cv.php?n8n_id=${candidate.n8n_id}&jd_id=${candidate.jd_id}${tokenParam}', '${candidate.name.replace(/'/g, "\\'")}')" 
                       style="position: absolute; top: -2px; right: -5px; width:20px; height:20px; display: flex; align-items: center; justify-content: center; background: #ef4444; color: white; border-radius: 4px; text-decoration: none;" 
                       title="View CV PDF">
                        <span class="material-icons" style="font-size: 14px;">picture_as_pdf</span>
                    </a>
                </div>
            </td>
            <td><small>${candidate.location || '-'}</small></td>
            <td style="white-space: nowrap;"><small>${candidate.date_of_birth ? new Date(candidate.date_of_birth).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'}</small></td>
            <td class="${(!candidate.Previous_Companies || !candidate.Previous_Companies.trim()) ? 'center-content' : ''}"><div class="col-text" title="${(candidate.Previous_Companies || '').trim()}"><small>${(candidate.Previous_Companies && candidate.Previous_Companies.trim()) ? candidate.Previous_Companies : '-'}</small></div></td>
            <td class="${(!candidate.Current_Position || !candidate.Current_Position.trim()) ? 'center-content' : ''}"><div class="col-text" title="${(candidate.Current_Position || '').trim()}"><small>${(candidate.Current_Position && candidate.Current_Position.trim()) ? candidate.Current_Position : '-'}</small></div></td>
            <td class="${(!candidate.organization || !candidate.organization.trim()) ? 'center-content' : ''}">${(candidate.organization && candidate.organization.trim()) ? candidate.organization : '-'}</td>
            <td class="${(!candidate.education || !candidate.education.trim()) ? 'center-content' : ''}"><div class="col-text" title="${candidate.education || ''}">${(candidate.education && candidate.education.trim()) ? candidate.education : '-'}</div></td>
            <td class="${(!candidate.educational_institute || !candidate.educational_institute.trim()) ? 'center-content' : ''}"><div class="col-text" title="${candidate.educational_institute || ''}">${(candidate.educational_institute && candidate.educational_institute.trim()) ? candidate.educational_institute : '-'}</div></td>
            <td>${candidate.total_experience}</td>
            <td>৳${parseFloat(candidate.expected_salary).toLocaleString()}</td>
            <td><div class="col-skills"><small>${candidate.skills || '-'}</small></div></td>
            <td><div class="col-skills"><small>${candidate.strength || '-'}</small></div></td>
            <td><div class="col-skills"><small>${candidate.weakness || '-'}</small></div></td>
            <td style="text-align: center;">
                <span class="badge ${candidate.rating >= 4 ? 'badge-success' : 'badge-secondary'}" title="Reason: ${candidate.reason_for_rating || 'None'}">
                    ${candidate.rating}
                </span>
            </td>
            <td style="text-align: center;">
                ${candidate.match ? `
                    <span style="font-size: 0.8rem; font-weight: 700; color: #4f46e5; background: #eef2ff; padding: 2px 7px; border-radius: 6px; border: 1px solid #e0e7ff;" title="AI Match Score">
                        ${candidate.match}
                    </span>` : '-'}
            </td>
            <td><div class="col-text-wide"><small>${candidate.reason_for_rating || '-'}</small></div></td>
            ${isPublic ? '' : `
            <td style="text-align:center;">
                <input type="checkbox" class="status-checkbox" data-field="shortlisted" ${candidate.shortlisted == 1 ? 'checked' : ''} disabled
                    onchange="updateCandidateStatus(${candidate.id}, 'shortlisted', this.checked)">
            </td>
            <td style="text-align:center;">
                <input type="checkbox" class="status-checkbox" data-field="confirmation" ${candidate.confirmation == 1 ? 'checked' : ''} disabled
                    onchange="updateCandidateStatus(${candidate.id}, 'confirmation', this.checked)">
            </td>
            `}
            <td><small>${new Date(candidate.created_at).toLocaleDateString()}</small></td>
            ${isPublic ? '' : `
            <td style="text-align:center;">
                <button class="btn-primary edit-toggle-btn" onclick="toggleRowEdit(${candidate.id})" style="width:32px; height:32px; padding: 0; display: flex; align-items: center; justify-content: center; background: var(--secondary);" title="Edit Status">
                    <span class="material-icons" style="font-size: 16px;">edit</span>
                </button>
            </td>
            `}
        `;
        tbody.appendChild(tr);
    });
}

function updateSortUI() {
    document.querySelectorAll('.sortable').forEach(th => {
        const sortField = th.getAttribute('data-sort');
        const icon = th.querySelector('.sort-icon');
        if (!icon) return;

        th.classList.remove('active-sort');
        if (sortField === currentSortBy) {
            th.classList.add('active-sort');
            icon.innerText = currentSortOrder === 'ASC' ? ' ↑' : ' ↓';
        } else {
            icon.innerText = ' ↕';
        }
    });
}

function handleSort(field) {
    if (currentSortBy === field) {
        currentSortOrder = currentSortOrder === 'ASC' ? 'DESC' : 'ASC';
    } else {
        currentSortBy = field;
        currentSortOrder = 'DESC';
    }
    loadCandidates(1);
}

function toggleRowEdit(id) {
    const row = document.getElementById(`row-${id}`);
    const btn = row.querySelector('.edit-toggle-btn');
    const icon = btn.querySelector('.material-icons');
    const checkboxes = row.querySelectorAll('.status-checkbox');
    const isEditing = icon.textContent === 'check';

    if (isEditing) {
        icon.textContent = 'edit';
        btn.classList.remove('active');
        checkboxes.forEach(cb => {
            cb.disabled = true;
            cb.parentElement.classList.remove('editable-cell');
        });
    } else {
        icon.textContent = 'check';
        btn.classList.add('active');
        checkboxes.forEach(cb => {
            cb.disabled = false;
            cb.parentElement.classList.add('editable-cell');
        });
    }
}

// --- PDF Viewer Functions ---
window.openPDFModal = async function(url, name, type = 'CV') {
    const modal = document.getElementById('pdfModal');
    const frame = document.getElementById('pdfFrame');
    const title = document.getElementById('pdfModalTitle');
    const subTitle = document.getElementById('pdfModalSub');
    const errorState = document.getElementById('pdfErrorState');
    const errorTitle = errorState ? errorState.querySelector('h2') : null;
    const errorText = errorState ? errorState.querySelector('p') : null;
    
    if (modal && frame) {
        // RESET AND INITIALIZE
        const label = type === 'JD' ? 'JD Viewer' : 'CV Viewer';
        const subLabel = type === 'JD' ? 'Project Requirement Review' : 'Secure Profile Document Review';
        const errorLabel = type === 'JD' ? 'JD File Not Available' : 'CV File Not Available';
        
        if (title) title.innerText = `${label}: ${name}`;
        if (subTitle) subTitle.innerText = subLabel;
        if (errorTitle) errorTitle.innerText = errorLabel;

        // Reset display states
        frame.style.display = 'none';
        errorState.style.display = 'none';
        frame.src = ''; 

        // Show modal and loading state
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
        document.body.style.overflow = 'hidden';
        
        // Hide frame and error state initially
        frame.style.display = 'none';
        errorState.style.display = 'none';

        try {
            const response = await fetch(url, { method: 'HEAD' });
            if (response.ok) {
                frame.src = url;
                frame.style.display = 'block';
            } else {
                errorState.style.display = 'flex';
            }
        } catch (e) {
            errorState.style.display = 'flex';
        }
    }
};

window.closePDFModal = function() {
    const modal = document.getElementById('pdfModal');
    const frame = document.getElementById('pdfFrame');
    
    if (modal && frame) {
        frame.src = ''; // Clear source to stop any background processing
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.style.overflow = ''; // Restore scrolling
    }
};

function renderPagination(meta) {
    const container = document.getElementById('pagination');
    if (!container) return;
    container.innerHTML = '';

    if (meta.total_pages <= 1) return;

    if (meta.current_page > 1) {
        container.appendChild(createPageBtn('←', () => loadCandidates(meta.current_page - 1)));
    }

    for (let i = 1; i <= meta.total_pages; i++) {
        if (i === 1 || i === meta.total_pages || (i >= meta.current_page - 2 && i <= meta.current_page + 2)) {
            const btn = createPageBtn(i, () => loadCandidates(i));
            if (i === meta.current_page) btn.classList.add('active');
            container.appendChild(btn);
        } else if (i === meta.current_page - 3 || i === meta.current_page + 3) {
            const span = document.createElement('span');
            span.innerText = '...';
            span.style.padding = '5px';
            container.appendChild(span);
        }
    }

    if (meta.current_page < meta.total_pages) {
        container.appendChild(createPageBtn('→', () => loadCandidates(meta.current_page + 1)));
    }
}

function createPageBtn(text, onclick) {
    const btn = document.createElement('button');
    btn.className = 'page-btn';
    btn.innerText = text;
    btn.onclick = onclick;
    return btn;
}

async function updateCandidateStatus(id, field, value) {
    try {
        const response = await fetch(getApiUrl('update_status.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, field, value: value ? 1 : 0 })
        });
        const result = await response.json();
        if (result.status !== 'success') {
            alert('Failed to update status: ' + (result.message || 'Unknown error'));
            loadCandidates(currentPage);
        }
    } catch (error) {
        console.error('Error updating status:', error);
    }
}

// --- Helper for formatting datetime ---
function formatDateTime(datetimeStr) {
    if (!datetimeStr) return 'N/A';
    const parts = datetimeStr.split(' ');
    if (parts.length !== 2) return datetimeStr;
    const datePart = parts[0];
    const timePart = parts[1];

    const timeParts = timePart.split(':');
    let hours = parseInt(timeParts[0], 10);
    const minutes = timeParts[1];
    let seconds = timeParts[2] || '00';
    if (seconds.includes('.')) seconds = seconds.split('.')[0];

    const ampm = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12;
    hours = hours ? hours : 12;

    const formattedTime = `${hours}:${minutes}:${seconds} ${ampm}`;
    return `<div style="line-height: 1.2;"><div>${datePart}</div><div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">${formattedTime}</div></div>`;
}

// --- Welcome Screen Logic (Job List) ---

async function loadJobList() {
    const container = document.getElementById('jobList');
    if (!container) return;

    try {
        const response = await fetch(getApiUrl('get_jobs.php'));
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            window.allJobsData = result.data;
            window.applyJobFilters();
        } else {
            console.error('API returned success but data is not an array:', result);
        }
    } catch (error) {
        console.error('Error loading job list:', error);
        if (container.innerHTML.includes('Loading')) {
            container.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 40px; color: red;">Failed to load jobs.</td></tr>';
        }
    }
}

function clearJobFilters() {
    if (document.getElementById('filterJdId')) document.getElementById('filterJdId').value = '';
    if (document.getElementById('filterStatus')) document.getElementById('filterStatus').value = 'all';
    if (document.getElementById('filterCreatedBy')) document.getElementById('filterCreatedBy').value = '';
    applyJobFilters();
}

function renderJobList(data) {
    const container = document.getElementById('jobList');
    if (!container) return;

    // Fix: Prevent background refreshes from overwriting active inline edits
    if (window.isEditingTask) {
        console.log("Render ignored because an edit is in progress.");
        return;
    }

    container.innerHTML = '';

    if (data.length === 0) {
        const colCount = window.isTaskManagementPage ? 12 : 10;
        container.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center; padding: 40px; color: #888; white-space: nowrap;">No jobs found matching filters.</td></tr>`;
        return;
    }

    data.forEach((job, index) => {
        const tr = document.createElement('tr');
        const dashboardUrl = `view/dashboard.php?jd_id=${encodeURIComponent(job.jd_id)}&job_title=${encodeURIComponent(job.job_title)}`;
        
        // Compact Title Block
        // Source-based badge colors
        let sourceStyle = 'background: #f5f3ff; color: #7c3aed; border-color: #ddd6fe;'; // Default Lavender (Manual)
        const sourceLC = (job.source || '').toLowerCase();
        if (sourceLC === 'bdjobs') {
            sourceStyle = 'background: #fff1f1; color: #800000; border-color: #fecaca;'; // Maroon
        } else if (sourceLC === 'both') {
            sourceStyle = 'background: #fffbeb; color: #d97706; border-color: #fef3c7;'; // Orange
        }
        const sourceBadge = job.source ? `<span class="badge-jd" style="margin-left: 8px; font-size: 0.65rem; padding: 1px 5px; ${sourceStyle}">${job.source.toUpperCase()}</span>` : '';
        const jobTitleHtml = `
            <a href="${dashboardUrl}" class="job-title-link">${job.job_title}</a>
            <div style="font-size: 0.65rem; color: #64748b; margin-top: 2px; display: flex; align-items: center;">
                by <span style="color: #4f46e5; font-weight: 600; margin-left: 3px;">${job.creator_name || job.created_by || 'System'}</span>
                ${sourceBadge}
            </div>
        `;

        // Modern Status Badge
        let displayStatus = (job.status || 'Pending').trim();
        const lc = displayStatus.toLowerCase();
        let statusStyle = 'background: #f1f5f9; color: #64748b;';

        if (lc === 'completed') {
            statusStyle = 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;';
        } else if (lc === 'screening' || lc === 'downloading') {
            statusStyle = 'background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe;';
            if (lc === 'downloading' && job.total_cv_download > 0) {
                displayStatus = `DOWNLOADING <span style="color: #ef4444; margin: 0 4px; font-weight: 900;">|</span> ${job.total_cv_download}`;
            }
        }

        const statusCellHtml = `<span class="status-pill" style="${statusStyle}">${displayStatus}</span>`;

        const encodedJob = btoa(unescape(encodeURIComponent(JSON.stringify(job))));
        tr.setAttribute('data-jdid', job.jd_id);
        tr.setAttribute('data-job', encodedJob);

        let actionHtml = '';
        if (window.isTaskManagementPage) {
            actionHtml = `
                <td style="text-align: center;">
                    <div class="action-btns" style="gap: 2px;">
                        <button onclick="startTaskEdit('${job.jd_id}')" style="width: 26px; height: 26px; background: #f8fafc; color: #6366f1; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Edit Task">
                            <span class="material-icons" style="font-size: 16px;">edit</span>
                        </button>
                        <button onclick="downloadTaskPdf('${job.jd_id}', '${job.job_title.replace(/'/g, "\\'")}')" style="width: 26px; height: 26px; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Download PDF">
                            <span class="material-icons" style="font-size: 16px;">picture_as_pdf</span>
                        </button>
                        <button onclick="deleteJobTask('${job.jd_id}', '${job.job_title.replace(/'/g, "\\'")}')" style="width: 26px; height: 26px; background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Delete Task">
                            <span class="material-icons" style="font-size: 16px;">delete_outline</span>
                        </button>
                    </div>
                </td>
            `;


            tr.innerHTML = `
                <td style="font-weight: 600; color: #94a3b8;">${index + 1}</td>
                <td style="text-align: left !important; padding-left: 15px;">${jobTitleHtml}</td>
                <td><span class="badge-jd">${job.jd_id || 'N/A'}</span></td>
                <td style="text-align: center; font-size: 0.75rem; line-height: 1.3;">
                    <div style="font-weight: 700; color: #1e293b;">${job.department || '—'}</div>
                    <div style="color: #64748b; font-size: 0.7rem;">${job.concern_person || '—'}</div>
                </td>
                <td>
                    <div style="font-size: 0.75rem; color: #475569; line-height: 1.4; max-height: 4.2em; overflow-y: auto; white-space: normal; word-break: break-word; padding: 2px 0; scrollbar-width: thin;">
                        ${job.prompt_text || '—'}
                    </div>
                </td>
                <td>${statusCellHtml}</td>
                <td><span class="metric-pill">${job.total_cv_download || 0} <span style="color: #ef4444; font-weight: 900; margin: 0 4px;">|</span> ${job.total_bdjobs_profile || 0}</span></td>
                <td><span style="font-weight: 700; color: #1d4ed8;">${job.total_candidate || 0}</span></td>
                <td style="font-size: 0.65rem; color: #64748b;">${formatDateTime(job.created_at)}</td>
                <td style="font-size: 0.8rem; font-weight: 800;">${job.task_no || 'N/A'}</td>
                <td><span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">${job.server_allocation ?? '-'}</span></td>
                ${actionHtml}
            `;
        } else {
            // Home Page Original Logic: Show Edit/Delete only if status is 'created' and within time limit
            if ((job.status || '').toLowerCase().trim() === 'created') {
                if (job.is_editable) {
                    actionHtml = `
                        <td style="text-align: center;">
                            <div class="action-btns" style="gap: 4px;">
                                <button onclick="startHomeTaskEdit('${job.jd_id}')" style="width: 26px; height: 26px; background: #f8fafc; color: #6366f1; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                    <span class="material-icons" style="font-size: 16px;">edit</span>
                                </button>
                                <button onclick="deleteJobTask('${job.jd_id}', '${job.job_title.replace(/'/g, "\\'")}')" style="width: 26px; height: 26px; background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                    <span class="material-icons" style="font-size: 16px;">delete_outline</span>
                                </button>
                            </div>
                        </td>
                    `;
                } else {
                    actionHtml = `<td style="text-align: center; color: #ef4444; font-size: 0.7rem; font-weight: 700; vertical-align: middle;">TIME OVER</td>`;
                }
            } else {
                actionHtml = `<td style="text-align: center; color: #94a3b8; font-size: 0.7rem; vertical-align: middle;">—</td>`;
            }

            tr.innerHTML = `
                <td style="text-align: center; color: #94a3b8;">${index + 1}</td>
                <td style="text-align: left !important; padding-left: 15px;">${jobTitleHtml}</td>
                <td style="text-align: center;"><span class="badge-jd">${job.jd_id || 'N/A'}</span></td>
                <td style="text-align: center; font-size: 0.75rem; line-height: 1.3;">
                    <div style="font-weight: 700; color: #1e293b;">${job.department || '—'}</div>
                    <div style="color: #64748b; font-size: 0.7rem;">${job.concern_person || '—'}</div>
                </td>
                <td>
                    <div style="font-size: 0.75rem; color: #475569; line-height: 1.4; max-height: 4.2em; overflow-y: auto; white-space: normal; word-break: break-word; padding: 2px 0; scrollbar-width: thin;">
                        ${job.prompt_text || '—'}
                    </div>
                </td>
                <td style="text-align: center;">${statusCellHtml}</td>
                <td style="text-align: center;"><span style="font-weight: 600; color: #4f46e5;">${job.total_candidate || 0}</span></td>
                <td style="text-align: center; color: #64748b; font-size: 0.75rem;">${formatDateTime(job.created_at)}</td>
                <td style="text-align: center; font-weight: 700; color: #0f172a;">${job.task_no || 'N/A'}</td>
                ${actionHtml}
            `;
        }
        container.appendChild(tr);
    });
}

// ---- Home Page Task Edit (Title & Prompt Only) ----
window.startHomeTaskEdit = function(jdId) {
    window.isEditingTask = true;
    const tr = document.querySelector(`tr[data-jdid="${jdId}"]`);
    if (!tr) return;
    const job = JSON.parse(decodeURIComponent(escape(atob(tr.getAttribute('data-job')))));

    const inStyle = `padding: 6px 10px; border: 1px solid var(--primary); border-radius: 8px; font-size: 0.85rem; width: 100%; background: var(--card-bg); color: var(--text); outline: none; font-family: inherit; box-sizing: border-box; transition: all 0.2s;`;
    const suggBoxStyle = `position: absolute; width: 100%; max-height: 250px; overflow-y: auto; background: white; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 1000; display: none; margin-top: 5px;`;

    tr.innerHTML = `
        <td style="text-align:center; color: #94a3b8; font-size:0.75rem;">${tr.rowIndex}</td>
        <td style="padding: 4px 8px;">
            <textarea id="edit_title_${jdId}" style="${inStyle} min-height:40px; font-weight: 600;">${(job.job_title || '').replace(/"/g,'&quot;')}</textarea>
        </td>
        <td style="text-align:center;"><span class="badge-jd" style="font-size: 0.65rem; padding: 1px 4px;">${jdId}</span></td>
        <td style="padding: 4px 8px; position: relative;">
            <div style="margin-bottom: 4px; position: relative;">
                <input type="text" id="edit_dept_input_${jdId}" 
                       placeholder="Dept..."
                       style="${inStyle} font-size: 0.75rem; padding: 4px 8px; height: 26px;" 
                       value="${job.department || ''}"
                       oninput="searchDepartmentsForEdit('${jdId}', this.value)"
                       autocomplete="off">
                <input type="hidden" id="edit_dept_val_${jdId}" value="${job.department || ''}">
                <div id="edit_dept_suggestions_${jdId}" style="${suggBoxStyle}"></div>
            </div>
            <div style="position: relative;">
                <input type="text" id="edit_concern_${jdId}" 
                       placeholder="Concern..."
                       style="${inStyle} font-size: 0.75rem; padding: 4px 8px; height: 26px;" 
                       value="${job.concern_person || ''}"
                       oninput="searchEmployeesForEdit('${jdId}', this.value)"
                       autocomplete="off">
                <input type="hidden" id="edit_email_val_${jdId}" value="${job.concern_email || ''}">
                <div id="edit_suggestions_${jdId}" style="${suggBoxStyle}"></div>
            </div>
            <div style="margin-top: 4px; display: flex; align-items: center; gap: 5px;">
                <input type="checkbox" id="edit_send_mail_${jdId}" ${job.send_mail == 1 ? 'checked' : ''} style="width: 13px; height: 13px; cursor: pointer;">
                <label for="edit_send_mail_${jdId}" style="font-size: 0.65rem; color: #475569; font-weight: 700; cursor: pointer;">Mail Concern</label>
            </div>
        </td>
        <td style="padding: 4px 8px;">
            <textarea id="edit_prompt_${jdId}" style="${inStyle} min-height:60px; font-size: 0.75rem; line-height: 1.3;">${(job.prompt_text || '')}</textarea>
        </td>
        <td style="text-align:center;"><span class="status-pill" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; font-size: 0.6rem; padding: 2px 6px;">${job.status}</span></td>
        <td style="text-align:center;">—</td>
        <td style="text-align:center; font-size:0.7rem; color: #64748b;">${formatDateTime(job.created_at)}</td>
        <td style="text-align:center; font-weight:700; font-size: 0.75rem;">${job.task_no}</td>
        <td style="text-align:center; white-space:nowrap; padding:8px;">
            <div style="display: flex; gap: 6px; justify-content: center;">
                <button onclick="saveHomeTaskEdit('${jdId}')" title="Save" style="width: 32px; height: 32px; background:#10b981; border:none; color:white; border-radius:8px; cursor:pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
                    <span class="material-icons" style="font-size:18px;">check</span>
                </button>
                <button onclick="cancelTaskEdit()" title="Cancel" style="width: 32px; height: 32px; background:#ef4444; border:none; color:white; border-radius:8px; cursor:pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);">
                    <span class="material-icons" style="font-size:18px;">close</span>
                </button>
            </div>
        </td>
    `;

    tr.style.background = 'rgba(79, 70, 229, 0.03)';
    tr.style.boxShadow = 'inset 0 0 0 2px var(--primary)';
};

window.saveHomeTaskEdit = function(jdId) {
    const title        = document.getElementById(`edit_title_${jdId}`).value.trim();
    const prompt       = document.getElementById(`edit_prompt_${jdId}`).value.trim();
    const department   = document.getElementById(`edit_dept_input_${jdId}`)?.value.trim();
    const concern      = document.getElementById(`edit_concern_${jdId}`)?.value.trim();
    const concernEmail = document.getElementById(`edit_email_val_${jdId}`)?.value.trim();
    const sendMail     = document.getElementById(`edit_send_mail_${jdId}`)?.checked ? 1 : 0;

    if (!title) {
        showNotification("Job title cannot be empty.", "error");
        return;
    }

    fetch(getApiUrl('job_api.php?action=update'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            orig_jd_id: jdId,
            jd_id: jdId, 
            job_title: title,
            prompt_text: prompt,
            department: department,
            concern_person: concern,
            concern_email: concernEmail,
            send_mail: sendMail,
            task_no: '', 
            server_allocation: 0
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            window.isEditingTask = false;
            if (typeof loadJobList === 'function') {
                loadJobList(); 
            } else if (typeof applyJobFilters === 'function') {
                applyJobFilters();
            }
            alert(`Task ${jdId} updated successfully`);
        } else {
            alert("Server Error: " + (res.message || "Unknown error"));
        }
    })
    .catch(err => {
        console.error("Save Error:", err);
        alert("Success in backend, but UI refresh failed. Please refresh page manually.");
    });
};
window.isEditingTask = false;

window.startTaskEdit = function(jdId) {
    window.isEditingTask = true;
    const tr = document.querySelector(`tr[data-jdid="${jdId}"]`);
    if (!tr) return;
    const job = JSON.parse(decodeURIComponent(escape(atob(tr.getAttribute('data-job')))));

    const inStyle = `padding: 3px 6px; border: 1px solid var(--primary); border-radius: 4px; font-size: 0.75rem; width: 100%; background: var(--bg); color: var(--text); outline: none;`;

    // Calculate initial height based on text (more compact)
    const initialHeight = Math.max(30, (job.job_title || '').length / 50 * 18);

    tr.innerHTML = `
        <td style="text-align:center; color: var(--text-light); font-size:0.75rem;">${tr.rowIndex}</td>
        <td style="padding: 4px; min-width: 200px;">
            <textarea id="edit_title_${jdId}" style="${inStyle} min-height:${initialHeight}px; resize: vertical; display: block; font-family: inherit;">${(job.job_title || '').replace(/"/g,'&quot;')}</textarea>
        </td>
        <td style="text-align:center; padding:4px; min-width: 80px;">
            <span class="badge-jd" style="font-size: 0.65rem; padding: 1px 4px;">${job.jd_id || ''}</span>
            <input type="hidden" id="edit_jdid_${jdId}" value="${job.jd_id || ''}">
        </td>
        <td style="text-align:center; font-size: 0.7rem; padding: 4px; min-width: 160px; position: relative;">
            <div style="margin-bottom: 2px; position: relative;">
                <input id="edit_dept_input_${jdId}" value="${job.department || ''}" 
                       style="${inStyle} font-size: 0.7rem; font-weight: 700; text-align: center; margin-bottom: 2px; height: 24px;" 
                       oninput="searchDepartmentsForEdit('${jdId}', this.value)"
                       placeholder="Dept..."
                       autocomplete="off">
                <div id="edit_dept_suggestions_${jdId}" class="suggestions-box" style="display:none; position: absolute; top: 100%; left: 0; right: 0; text-align: left; background: white; border: 1px solid var(--primary); border-radius: 6px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
            </div>
            <div style="position: relative;">
                <input id="edit_concern_${jdId}" value="${(job.concern_person || '').replace(/"/g,'&quot;')}" 
                       style="${inStyle} font-size: 0.65rem; height: 24px;" 
                       oninput="searchEmployeesForEdit('${jdId}', this.value)"
                       placeholder="Concern Person..."
                       autocomplete="off">
                <input type="hidden" id="edit_dept_val_${jdId}" value="${job.department || ''}">
                <input type="hidden" id="edit_email_val_${jdId}" value="${job.concern_email || ''}">
                <div id="edit_suggestions_${jdId}" class="suggestions-box" style="display:none; position: absolute; top: 100%; left: 0; right: 0; text-align: left; background: white; border: 1px solid var(--primary); border-radius: 6px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
            </div>
            <div style="margin-top: 2px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                <input type="checkbox" id="edit_send_mail_${jdId}" ${job.send_mail == 1 ? 'checked' : ''} style="width: 12px; height: 12px; cursor: pointer;">
                <label for="edit_send_mail_${jdId}" style="font-size: 0.6rem; color: #475569; font-weight: 700; cursor: pointer;">Mail Concern</label>
            </div>
        </td>
        <td style="padding: 4px; min-width: 150px;">
            <textarea id="edit_prompt_${jdId}" style="${inStyle} min-height: 30px; resize: vertical; display: block; font-family: inherit;">${(job.prompt_text || '')}</textarea>
        </td>
        <td style="text-align:center; padding:4px;">
            <span class="badge-status status-pending" style="font-size: 0.6rem; padding: 2px 4px;">${job.status || 'Pending'}</span>
        </td>
        <td style="text-align:center; padding:4px;">
            <span class="metric-pill" style="font-size: 0.7rem; padding: 1px 4px;">${job.total_cv_download || 0} | ${job.total_bdjobs_profile || 0}</span>
        </td>
        <td style="text-align:center; padding:4px;">
            <span style="font-weight: 600; color: var(--primary); font-size: 0.75rem;">${job.total_candidate || 0}</span>
        </td>
        <td style="text-align:center; padding:4px; font-size: 0.65rem; color: var(--text-light); line-height: 1.1;">
            ${formatDateTime(job.created_at)}
        </td>
        <td style="text-align:center; padding:4px; min-width: 70px;">
            <span style="font-weight: 700; color: var(--text); font-size: 0.75rem;">${job.task_no || 'N/A'}</span>
            <input type="hidden" id="edit_taskno_${jdId}" value="${job.task_no || ''}">
        </td>
        <td style="text-align:center; padding:4px; min-width: 50px;">
            <input id="edit_serv_${jdId}" type="number" value="${job.server_allocation ?? 1}" style="${inStyle} width:40px; text-align:center; font-weight:700; padding: 2px;">
        </td>
        <td style="text-align:center; white-space:nowrap; padding:4px;">
            <div style="display: flex; gap: 3px; justify-content: center;">
                <button onclick="saveTaskEdit('${jdId}')" title="Save" style="padding:3px 6px; background:#10b981; border:none; color:white; border-radius:4px; cursor:pointer;">
                    <span class="material-icons" style="font-size:14px; vertical-align:middle;">check</span>
                </button>
                <button onclick="cancelTaskEdit()" title="Cancel" style="padding:3px 6px; background:#64748b; border:none; color:white; border-radius:4px; cursor:pointer;">
                    <span class="material-icons" style="font-size:14px; vertical-align:middle;">close</span>
                </button>
            </div>
        </td>
    `;
    // Highlight editing row
    tr.style.background = 'rgba(79, 70, 229, 0.05)';
    tr.style.outline = '2px solid var(--primary)';

    // Auto-focus and resize textarea
    const ta = document.getElementById(`edit_title_${jdId}`);
    if (ta) {
        ta.focus();
        ta.setSelectionRange(ta.value.length, ta.value.length);
        ta.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Keyboard Shortcuts
        ta.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                saveTaskEdit(jdId);
            }
            if (e.key === 'Escape') {
                cancelTaskEdit();
            }
        });
    }

    // Also handle Esc on the row or inputs
    const inputs = tr.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') saveTaskEdit(jdId);
            if (e.key === 'Escape') cancelTaskEdit();
        });
    });

    // Hide suggestions on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.suggestions-box') && !e.target.closest('.suggestions-container') && !e.target.closest('input')) {
            document.querySelectorAll('.suggestions-box, .suggestions-container').forEach(box => box.style.display = 'none');
        }
    });
};

window.searchDepartmentsForTask = function(query) {
    const suggestions = document.getElementById('deptSuggestionsTask');
    const concernInput = document.getElementById('publicConcern');
    const emailVal = document.getElementById('publicConcernEmail');
    
    if (concernInput) concernInput.value = '';
    if (emailVal) emailVal.value = '';

    if (!suggestions) return;

    if (!query || query.trim().length < 1) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
        return;
    }

    suggestions.innerHTML = '<div style="padding: 8px 12px; color: #64748b; font-size: 0.75rem; display: flex; align-items: center; gap: 8px;"><span class="material-icons" style="font-size: 14px; animation: spin 1s linear infinite;">autorenew</span> Searching...</div>';
    suggestions.style.display = 'block';

    clearTimeout(window.taskDeptSearchTimeout);
    window.taskDeptSearchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(getApiUrl(`employee_api.php?action=get_filters&t=${Date.now()}`));
            const result = await response.json();
            
            if (result.status === 'success' && result.departments) {
                const qLower = query.toLowerCase().trim();
                const filtered = result.departments.filter(d => d && String(d).toLowerCase().includes(qLower));
                
                // Sort: Exact match first, then startsWith, then alphabetical
                filtered.sort((a, b) => {
                    const aLow = a.toLowerCase();
                    const bLow = b.toLowerCase();
                    if (aLow === qLower) return -1;
                    if (bLow === qLower) return 1;
                    const aStarts = aLow.startsWith(qLower);
                    const bStarts = bLow.startsWith(qLower);
                    if (aStarts && !bStarts) return -1;
                    if (!aStarts && bStarts) return 1;
                    return a.localeCompare(b);
                });

                if (filtered.length > 0) {
                    suggestions.innerHTML = filtered.slice(0, 15).map(dept => `
                        <div class="suggestion-item" 
                             onclick="selectDepartmentForTask('${dept.replace(/'/g, "\\'")}')"
                             style="padding: 4px 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                            <div style="font-weight: 600; font-size: 0.7rem; color: var(--text);">${dept}</div>
                        </div>
                    `).join('');
                    suggestions.style.display = 'block';
                } else {
                    suggestions.innerHTML = '<div style="padding: 6px 10px; color: #64748b; font-size: 0.7rem;">No matches</div>';
                    suggestions.style.display = 'block';
                }
            }
        } catch (e) { console.error(e); }
    }, 200);
};

window.selectDepartmentForTask = function(dept) {
    const input = document.getElementById('publicDept');
    const suggestions = document.getElementById('deptSuggestionsTask');
    if (input) input.value = dept;
    if (suggestions) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
    }
};

window.searchEmployeesForTask = function(query) {
    const suggestions = document.getElementById('concernSuggestionsTask');
    const dept = document.getElementById('publicDept')?.value || '';
    if (!suggestions) return;

    if (!query || query.trim().length < 1) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
        return;
    }

    suggestions.innerHTML = '<div style="padding: 8px 12px; color: #64748b; font-size: 0.75rem; display: flex; align-items: center; gap: 8px;"><span class="material-icons" style="font-size: 14px; animation: spin 1s linear infinite;">autorenew</span> Searching...</div>';
    suggestions.style.display = 'block';

    clearTimeout(window.taskEmpSearchTimeout);
    window.taskEmpSearchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(getApiUrl(`employee_api.php?action=list&search=${encodeURIComponent(query)}&dept=${encodeURIComponent(dept)}&t=${Date.now()}`));
            const result = await response.json();
            
            if (result.status === 'success' && result.data.length > 0) {
                const qLower = query.toLowerCase().trim();
                const filteredData = result.data.filter(emp => {
                    const searchStr = `${emp.full_name} ${emp.employee_id} ${emp.designation} ${emp.department}`.toLowerCase();
                    return searchStr.includes(qLower);
                });
                
                // Sort: Exact name match first
                filteredData.sort((a, b) => {
                    const aName = a.full_name.toLowerCase();
                    const bName = b.full_name.toLowerCase();
                    if (aName === qLower) return -1;
                    if (bName === qLower) return 1;
                    return aName.localeCompare(bName);
                });

                if (filteredData.length > 0) {
                    suggestions.innerHTML = filteredData.slice(0, 15).map(emp => `
                        <div class="suggestion-item" 
                             onclick="selectEmployeeForTask('${emp.full_name.replace(/'/g, "\\'")}', '${emp.email || ''}', '${emp.department}', '${emp.designation}', '${emp.employee_id}')"
                             style="padding: 4px 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                            <div style="font-weight: 600; font-size: 0.7rem; color: var(--text);">${emp.full_name} <span style="font-size: 0.6rem; color: var(--primary); opacity: 0.8;">(${emp.employee_id})</span></div>
                            <div style="font-size: 0.6rem; color: #64748b; line-height: 1.1;">${emp.designation} | ${emp.department}</div>
                        </div>
                    `).join('');
                } else {
                    suggestions.innerHTML = '<div style="padding: 6px 10px; color: #64748b; font-size: 0.7rem;">No matches</div>';
                }
            } else {
                suggestions.innerHTML = '<div style="padding: 6px 10px; color: #64748b; font-size: 0.7rem;">No matches</div>';
                suggestions.style.display = 'block';
            }
        } catch (e) { console.error(e); }
    }, 300);
};

window.selectEmployeeForTask = function(name, email, dept, designation, id) {
    const input = document.getElementById('publicConcern');
    const deptInput = document.getElementById('publicDept');
    const emailVal = document.getElementById('publicConcernEmail');
    const suggestions = document.getElementById('concernSuggestionsTask');

    if (input) input.value = `${name} | ${designation} (${id})`;
    if (deptInput && (!deptInput.value || deptInput.value.trim() === '')) {
        deptInput.value = dept;
    }
    if (emailVal) emailVal.value = email;
    
    if (suggestions) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
    }
};

window.searchDepartmentsForEdit = function(jdId, query) {
    const suggestions = document.getElementById(`edit_dept_suggestions_${jdId}`);
    const concernInput = document.getElementById(`edit_concern_${jdId}`);
    const emailVal = document.getElementById(`edit_email_val_${jdId}`);
    
    if (concernInput) concernInput.value = '';
    if (emailVal) emailVal.value = '';

    if (!suggestions) return;

    if (!query || query.trim().length < 1) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
        return;
    }

    clearTimeout(window.editDeptSearchTimeout);
    window.editDeptSearchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(getApiUrl(`employee_api.php?action=get_filters&t=${Date.now()}`));
            const result = await response.json();
            
            if (result.status === 'success' && result.departments) {
                const qLower = query.toLowerCase().trim();
                const filtered = result.departments.filter(d => d.toLowerCase().includes(qLower));
                
                filtered.sort((a, b) => {
                    const aLow = a.toLowerCase();
                    const bLow = b.toLowerCase();
                    if (aLow === qLower) return -1;
                    if (bLow === qLower) return 1;
                    return aLow.startsWith(qLower) ? -1 : (bLow.startsWith(qLower) ? 1 : a.localeCompare(b));
                });

                if (filtered.length > 0) {
                    suggestions.innerHTML = filtered.slice(0, 10).map(dept => `
                        <div class="suggestion-item" 
                             style="padding: 4px 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9;"
                             onclick="selectDepartmentForEdit('${jdId}', '${dept.replace(/'/g, "\\'")}')">
                            <div style="font-weight: 600; font-size: 0.7rem; color: var(--text);">${dept}</div>
                        </div>
                    `).join('');
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            }
        } catch (e) { console.error(e); }
    }, 200);
};

window.selectDepartmentForEdit = function(jdId, dept) {
    const input = document.getElementById(`edit_dept_input_${jdId}`);
    const hiddenDept = document.getElementById(`edit_dept_val_${jdId}`);
    const suggestions = document.getElementById(`edit_dept_suggestions_${jdId}`);

    if (input) input.value = dept;
    if (hiddenDept) hiddenDept.value = dept;
    if (suggestions) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
    }
};

window.searchEmployeesForEdit = function(jdId, query) {
    const suggestions = document.getElementById(`edit_suggestions_${jdId}`);
    const dept = document.getElementById(`edit_dept_input_${jdId}`)?.value || '';
    if (!suggestions) return;

    if (!query || query.length < 1) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
        return;
    }

    // Debounce
    clearTimeout(window.editSearchTimeout);
    window.editSearchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(getApiUrl(`employee_api.php?action=list&search=${encodeURIComponent(query)}&dept=${encodeURIComponent(dept)}&t=${Date.now()}`));
            const result = await response.json();
            
            if (result.status === 'success' && result.data.length > 0) {
                suggestions.innerHTML = result.data.slice(0, 10).map(emp => `
                    <div class="suggestion-item" 
                         onclick="selectEmployeeForEdit('${jdId}', '${emp.full_name.replace(/'/g, "\\'")}', '${emp.email || ''}', '${emp.department}', '${emp.designation}', '${emp.employee_id}')">
                        <div style="font-weight: 600; font-size: 0.8rem; color: var(--text);">${emp.full_name}</div>
                        <div style="font-size: 0.65rem; color: #64748b;">${emp.designation} | ${emp.department}</div>
                    </div>
                `).join('');
                suggestions.style.display = 'block';
            } else {
                suggestions.innerHTML = '<div style="padding: 8px 12px; color: #64748b; font-size: 0.75rem;">No results</div>';
                suggestions.style.display = 'block';
            }
        } catch (e) { console.error(e); }
    }, 300);
};

window.selectEmployeeForEdit = function(jdId, name, email, dept, designation, id) {
    const input = document.getElementById(`edit_concern_${jdId}`);
    const deptInput = document.getElementById(`edit_dept_input_${jdId}`);
    const deptVal = document.getElementById(`edit_dept_val_${jdId}`);
    const emailVal = document.getElementById(`edit_email_val_${jdId}`);
    const suggestions = document.getElementById(`edit_suggestions_${jdId}`);

    if (input) input.value = `${name} | ${designation} (${id})`;
    if (deptInput) deptInput.value = dept;
    if (deptVal) deptVal.value = dept;
    if (emailVal) emailVal.value = email;
    
    if (suggestions) {
        suggestions.innerHTML = '';
        suggestions.style.display = 'none';
    }
};

window.cancelTaskEdit = function() {
    window.isEditingTask = false;
    if (typeof loadJobList === 'function') loadJobList();
};

window.saveTaskEdit = async function(origJdId) {
    const newJdId      = document.getElementById(`edit_jdid_${origJdId}`)?.value.trim();
    const jobTitle     = document.getElementById(`edit_title_${origJdId}`)?.value.trim();
    const taskNo       = document.getElementById(`edit_taskno_${origJdId}`)?.value.trim();
    const promptText   = document.getElementById(`edit_prompt_${origJdId}`)?.value.trim();
    const serverAlloc  = document.getElementById(`edit_serv_${origJdId}`)?.value.trim();
    const department   = document.getElementById(`edit_dept_input_${origJdId}`)?.value.trim();
    const concern      = document.getElementById(`edit_concern_${origJdId}`)?.value.trim();
    const concernEmail = document.getElementById(`edit_email_val_${origJdId}`)?.value.trim();
    const sendMail     = document.getElementById(`edit_send_mail_${origJdId}`)?.checked ? 1 : 0;

    if (!newJdId || !jobTitle) {
        showNotification('Job Title and JD ID are required.', 'error');
        return;
    }

    try {
        const response = await fetch(getApiUrl('job_api.php?action=update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                orig_jd_id: origJdId,
                jd_id: newJdId,
                job_title: jobTitle,
                prompt_text: promptText,
                task_no: taskNo,
                server_allocation: serverAlloc,
                department: department,
                concern_person: concern,
                concern_email: concernEmail,
                send_mail: sendMail
            })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showNotification(result.message, 'success');
            window.isEditingTask = false;
            loadJobList();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (e) {
        showNotification('Failed to save changes.', 'error');
    }
};

// --- PDF Download Options Modal ---
window.downloadTaskPdf = function(jdId, jobTitle) {
    let existingModal = document.getElementById('pdfOptionsModal');
    if (existingModal) existingModal.remove();

    const escapedTitle = jobTitle ? jobTitle.replace(/"/g, '&quot;') : '';

    const modalHtml = `
        <div id="pdfOptionsModal" class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" style="transition: opacity 0.3s ease;">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-sm transform scale-95 transition-transform duration-300" id="pdfOptionsModalContent">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div class="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center text-rose-600 shadow-sm">
                            <span class="material-icons text-2xl">picture_as_pdf</span>
                        </div>
                        <button onclick="closePdfOptionsModal()" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-full transition-colors flex items-center justify-center">
                            <span class="material-icons text-xl">close</span>
                        </button>
                    </div>
                    
                    <h3 class="text-xl font-bold text-slate-800 mb-1">Task Document Options</h3>
                    <p class="text-sm text-slate-500 mb-6 font-medium truncate" title="${escapedTitle}">For: ${escapedTitle}</p>
                    
                    <div class="flex flex-col gap-3">
                        <button onclick="triggerPdfDownload('${jdId}')" class="group relative flex items-center justify-between p-4 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50 transition-all duration-200 w-full text-left shadow-sm hover:shadow-md">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform">
                                    <span class="material-icons">file_download</span>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-700 group-hover:text-indigo-700">Download PDF</div>
                                    <div class="text-xs text-slate-500 font-medium mt-0.5">Save to your device</div>
                                </div>
                            </div>
                            <span class="material-icons text-slate-300 group-hover:text-indigo-400 transition-colors">chevron_right</span>
                        </button>
                        
                        <button onclick="triggerPdfSendMail('${jdId}')" class="group relative flex items-center justify-between p-4 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50 transition-all duration-200 w-full text-left shadow-sm hover:shadow-md">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
                                    <span class="material-icons">forward_to_inbox</span>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-700 group-hover:text-emerald-700">Send Mail</div>
                                    <div class="text-xs text-slate-500 font-medium mt-0.5">Email to concern person</div>
                                </div>
                            </div>
                            <span class="material-icons text-slate-300 group-hover:text-emerald-400 transition-colors">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Force reflow
    void document.getElementById('pdfOptionsModal').offsetWidth;
    
    // Animate in
    requestAnimationFrame(() => {
        const modal = document.getElementById('pdfOptionsModal');
        const content = document.getElementById('pdfOptionsModalContent');
        if (modal) modal.classList.remove('opacity-0');
        if (content) content.classList.replace('scale-95', 'scale-100');
    });
};

window.closePdfOptionsModal = function() {
    const modal = document.getElementById('pdfOptionsModal');
    const content = document.getElementById('pdfOptionsModalContent');
    if (modal) {
        modal.classList.add('opacity-0');
        if (content) content.classList.replace('scale-100', 'scale-95');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
};

window.triggerPdfDownload = async function(jdId) {
    const isViewPage = window.location.pathname.includes('/view/');
    const url = (isViewPage ? '../api/' : 'api/') + 'download_task_zip.php?jd_id=' + encodeURIComponent(jdId);
    
    // Change UI to loading state
    const contentDiv = document.getElementById('pdfOptionsModalContent');
    if (contentDiv) {
        contentDiv.innerHTML = `
            <div class="p-10 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 border-4 border-slate-100 border-t-indigo-600 rounded-full animate-spin mb-6"></div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Processing ZIP...</h3>
                <p class="text-sm text-slate-500">Gathering JD and CVs. Please wait...</p>
            </div>
        `;
    }

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to download ZIP');

        let filename = `${jdId}_documents.zip`;
        const disposition = response.headers.get('Content-Disposition');
        if (disposition && disposition.indexOf('filename=') !== -1) {
            const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
            const matches = filenameRegex.exec(disposition);
            if (matches != null && matches[1]) { 
                filename = matches[1].replace(/['"]/g, '');
            }
        }

        const blob = await response.blob();
        
        // Trigger Download
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(downloadUrl);
        a.remove();

        // Change UI to success state
        if (contentDiv) {
            contentDiv.innerHTML = `
                <div class="p-10 flex flex-col items-center justify-center text-center">
                    <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mb-6">
                        <div class="w-14 h-14 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-500">
                            <span class="material-icons text-4xl" style="font-weight: bold;">check</span>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-2">Downloaded!</h3>
                    <p class="text-sm text-slate-500">The task documents have been successfully downloaded.</p>
                </div>
            `;
        }

        // Auto close after 2.5 seconds
        setTimeout(() => {
            closePdfOptionsModal();
        }, 2500);

    } catch (error) {
        console.error("Download Error:", error);
        if (contentDiv) {
            contentDiv.innerHTML = `
                <div class="p-10 flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 mb-6">
                        <span class="material-icons text-3xl">error_outline</span>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">Download Failed</h3>
                    <p class="text-sm text-slate-500 mb-6">An error occurred while generating the ZIP file.</p>
                    <button onclick="closePdfOptionsModal()" class="px-6 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg font-semibold transition-colors">Close</button>
                </div>
            `;
        }
    }
};

window.triggerPdfSendMail = async function(jdId) {
    const contentDiv = document.getElementById('pdfOptionsModalContent');
    if (!contentDiv) return;

    contentDiv.classList.remove('max-w-sm', 'max-w-2xl', 'max-w-4xl');
    contentDiv.classList.add('max-w-3xl', 'max-h-[90vh]', 'flex', 'flex-col', 'overflow-hidden');
    
    contentDiv.innerHTML = `
        <!-- Vibrant Header -->
        <div class="px-6 py-5 bg-gradient-to-r from-indigo-600 via-violet-600 to-indigo-700 flex justify-between items-center flex-shrink-0">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2 tracking-wide uppercase shadow-sm">
                    <span class="material-icons text-white/90">send</span> Send Candidate List
                </h2>
                <p class="text-indigo-100 text-xs mt-1 font-medium">Select recipients to deliver the task documents (JD & CVs in ZIP).</p>
            </div>
            <button onclick="closePdfOptionsModal()" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 transition-colors p-2 rounded-xl backdrop-blur-sm shadow-sm">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <!-- Scrollable Body with strict min-h-0 (Direct child of flex modal) -->
        <div class="p-6 bg-slate-50 flex-1 overflow-y-auto min-h-0 relative">
            <!-- Decorative background gradient -->
            <div class="absolute inset-x-0 top-0 bg-gradient-to-b from-indigo-100/40 to-transparent pointer-events-none h-40"></div>
            
            <div class="relative z-10">
                <!-- Selected Recipients -->
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div class="bg-white border-t-4 border-t-indigo-500 border-x border-b border-slate-200 p-4 rounded-xl flex flex-col h-[90px] shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] hover:shadow-md transition-shadow">
                        <label class="block text-[10px] font-bold text-indigo-800 mb-1.5 uppercase flex-shrink-0 tracking-wider">Main Concern (To)</label>
                        <div id="mailMainSelected" class="flex-1 overflow-y-auto flex items-start">
                            <span class="text-sm text-slate-400 italic">No recipient selected</span>
                        </div>
                        <input type="hidden" id="mailMainConcernId" value="">
                    </div>
                    <div class="bg-white border-t-4 border-t-purple-500 border-x border-b border-slate-200 p-4 rounded-xl flex flex-col h-[90px] shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] hover:shadow-md transition-shadow">
                        <label class="block text-[10px] font-bold text-purple-800 mb-1.5 uppercase flex-shrink-0 tracking-wider">CC Recipients</label>
                        <div id="mailCcSelected" class="flex-1 overflow-y-auto flex flex-wrap gap-1.5 content-start">
                            <span class="text-sm text-slate-400 italic">None</span>
                        </div>
                    </div>
                </div>

                <!-- Search Area -->
                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-[0_4px_15px_-3px_rgba(0,0,0,0.05)]">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="flex justify-between items-center text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wide">
                                <span>1. Filter by Department</span>
                                <button onclick="document.getElementById('mailDeptFilter').value=''; window.searchMailEmployees();" class="text-[9px] text-rose-500 hover:text-rose-600 font-bold tracking-wider bg-rose-50 hover:bg-rose-100 px-1.5 py-0.5 rounded transition-colors">CLEAR</button>
                            </label>
                            <div class="relative">
                                <span class="material-icons absolute left-3 top-2.5 text-indigo-400 text-sm z-10">domain</span>
                                <input type="text" id="mailDeptFilter" placeholder="Type or select department..." class="w-full pl-9 pr-8 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all bg-slate-50" onclick="document.getElementById('deptDropdownList').classList.remove('hidden')" oninput="window.filterDeptDropdown()">
                                <span class="material-icons absolute right-3 top-2.5 text-slate-400 text-sm pointer-events-none">expand_more</span>
                                
                                <div id="deptDropdownList" class="absolute top-full left-0 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-xl z-50 hidden max-h-48 overflow-y-auto">
                                    <div class="px-4 py-2.5 text-sm hover:bg-indigo-50 cursor-pointer font-bold text-indigo-700" onclick="window.selectDept('')">All Departments</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="flex justify-between items-center text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wide">
                                <span>2. Search Name/ID</span>
                                <button onclick="document.getElementById('mailNameSearch').value=''; window.searchMailEmployees();" class="text-[9px] text-rose-500 hover:text-rose-600 font-bold tracking-wider bg-rose-50 hover:bg-rose-100 px-1.5 py-0.5 rounded transition-colors">CLEAR</button>
                            </label>
                            <div class="relative">
                                <span class="material-icons absolute left-3 top-2.5 text-indigo-400 text-sm">search</span>
                                <input type="text" id="mailNameSearch" placeholder="Search by name or ID..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all bg-slate-50" oninput="window.searchMailEmployees()">
                            </div>
                        </div>
                    </div>

                    <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wide">3. Select Employee</label>
                    <div id="mailEmployeeSuggestions" class="w-full bg-slate-50 border border-slate-200 rounded-xl overflow-y-auto shadow-inner" style="height: 200px;">
                        <div class="p-8 text-center text-slate-400 text-sm">Start typing to search employees...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Fixed Footer -->
        <div class="px-6 py-4 bg-white border-t border-slate-200 flex justify-end gap-3 flex-shrink-0 z-20 relative">
            <button onclick="closePdfOptionsModal()" class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl transition-all shadow-sm hover:shadow">Cancel</button>
            <button onclick="executeMailSend('${jdId}')" class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 rounded-xl transition-all flex items-center gap-2 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                <span class="material-icons text-[18px]">send</span> Send Mail
            </button>
        </div>
    `;

    window.selectedCcList = [];
    
    // Load allEmployees using the new public mail_list endpoint
    if (!window.allEmployees || window.allEmployees.length === 0) {
        try {
            const isViewPage = window.location.pathname.includes('/view/');
            const res = await fetch((isViewPage ? '../api/' : 'api/') + 'employee_api.php?action=mail_list');
            const result = await res.json();
            if (result.status === 'success') {
                window.allEmployees = result.data;
            }
        } catch(e) { console.error(e); }
    }
    
    // Populate Custom Department Dropdown dynamically
    if (window.allEmployees) {
        const depts = [...new Set(window.allEmployees.map(e => e.department).filter(d => d))].sort();
        const deptList = document.getElementById('deptDropdownList');
        if (deptList && deptList.children.length === 1) {
            depts.forEach(d => {
                const div = document.createElement('div');
                div.className = 'px-4 py-2 text-sm hover:bg-slate-50 cursor-pointer border-t border-slate-100 text-slate-700 dept-dropdown-item';
                div.setAttribute('data-dept', d);
                div.textContent = d;
                div.onclick = () => window.selectDept(d);
                deptList.appendChild(div);
            });
        }
    }
    
    // Close dropdown when clicking outside — persistent, self-cleaning listener
    if (window._deptDropdownOutsideHandler) {
        document.removeEventListener('click', window._deptDropdownOutsideHandler);
    }
    window._deptDropdownOutsideHandler = function(e) {
        const filterInput = document.getElementById('mailDeptFilter');
        const dropList = document.getElementById('deptDropdownList');
        if (!filterInput) {
            // Modal is gone, clean up listener
            document.removeEventListener('click', window._deptDropdownOutsideHandler);
            window._deptDropdownOutsideHandler = null;
            return;
        }
        if (!filterInput.contains(e.target) && dropList && !dropList.contains(e.target)) {
            dropList.classList.add('hidden');
        }
    };
    document.addEventListener('click', window._deptDropdownOutsideHandler);
    
    // Pre-fill main concern if possible
    const jdTr = document.querySelector(`tr[data-jdid="${jdId}"]`);
    if (jdTr) {
        const jobStr = jdTr.getAttribute('data-job');
        if (jobStr) {
            const job = JSON.parse(decodeURIComponent(escape(atob(jobStr))));
            if (job.concern_person) {
                const emp = (window.allEmployees || []).find(e => e.full_name === job.concern_person);
                if (emp) {
                    selectMailEmployee(emp.employee_id, emp.full_name, 'main');
                } else {
                    document.getElementById('mailNameSearch').value = job.concern_person;
                }
            }
        }
    }
    
    window.searchMailEmployees(); // Trigger initial search
};

window.filterDeptDropdown = function() {
    const input = document.getElementById('mailDeptFilter');
    const filter = input.value.toLowerCase().trim();
    const list = document.getElementById('deptDropdownList');
    list.classList.remove('hidden');
    
    const items = list.querySelectorAll('.dept-dropdown-item');
    items.forEach(item => {
        const text = item.getAttribute('data-dept').toLowerCase();
        if (text.includes(filter)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
    
    window.searchMailEmployees();
};

window.selectDept = function(val) {
    document.getElementById('mailDeptFilter').value = val;
    document.getElementById('deptDropdownList').classList.add('hidden');
    window.searchMailEmployees();
};

window.searchMailEmployees = function() {
    const suggBox = document.getElementById('mailEmployeeSuggestions');
    const deptQuery = document.getElementById('mailDeptFilter').value.toLowerCase().trim();
    const nameQuery = document.getElementById('mailNameSearch').value.toLowerCase().trim();
    
    if (!deptQuery && !nameQuery) {
        suggBox.innerHTML = '<div class="p-8 text-center text-slate-400 text-sm">Type a department or name to see results...</div>';
        return;
    }
    
    const filtered = (window.allEmployees || []).filter(emp => {
        let matchDept = true;
        let matchName = true;
        
        if (deptQuery) {
            matchDept = emp.department && emp.department.toLowerCase().includes(deptQuery);
        }
        
        if (nameQuery) {
            matchName = (emp.full_name && emp.full_name.toLowerCase().includes(nameQuery)) || 
                        (emp.employee_id && emp.employee_id.toLowerCase().includes(nameQuery));
        }
        
        return matchDept && matchName;
    }).slice(0, 50); // Show more results since we have a dedicated scrolling box

    if (filtered.length === 0) {
        suggBox.innerHTML = '<div class="p-8 text-center text-slate-500 text-sm">No employees found matching the filters</div>';
    } else {
        suggBox.innerHTML = filtered.map(emp => {
            const initial = emp.full_name ? emp.full_name.charAt(0).toUpperCase() : 'U';
            const safeName = emp.full_name ? emp.full_name.replace(/'/g, "\\'") : '';
            return `
                <div class="flex items-center gap-3 p-2 hover:bg-white bg-slate-50 border-b border-slate-200 last:border-0 transition-colors">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold flex-shrink-0 text-xs">${initial}</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-slate-800 text-[13px] leading-tight truncate">${emp.full_name} <span class="text-[10px] font-normal text-slate-400 bg-slate-200 px-1 py-0.5 rounded ml-1">ID: ${emp.employee_id}</span></div>
                        <div class="text-[10px] text-slate-500 font-medium truncate uppercase mt-0.5">${emp.designation || 'N/A'} &bull; ${emp.department || 'N/A'}</div>
                        <div class="text-[10px] text-slate-400 truncate mt-0.5">${emp.email || 'No Email'}</div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="selectMailEmployee('${emp.employee_id}', '${safeName}', 'main')" class="px-2 py-1 text-[10px] font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded transition-colors uppercase">To</button>
                        <button onclick="selectMailEmployee('${emp.employee_id}', '${safeName}', 'cc')" class="px-2 py-1 text-[10px] font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-300 rounded transition-colors uppercase">+ CC</button>
                    </div>
                </div>
            `;
        }).join('');
    }
};

window.selectMailEmployee = function(id, name, target) {
    if (target === 'main') {
        document.getElementById('mailMainConcernId').value = id;
        document.getElementById('mailMainSelected').innerHTML = `
            <div class="inline-flex items-center gap-2 bg-indigo-50 border border-indigo-200 text-indigo-800 px-3 py-1.5 rounded-lg text-sm font-semibold shadow-sm">
                ${name} 
                <button onclick="clearMailMain()" class="hover:text-rose-500 transition-colors ml-1 flex"><span class="material-icons text-[14px]">close</span></button>
            </div>
        `;
    } else {
        if (!window.selectedCcList.find(e => e.id === id)) {
            window.selectedCcList.push({id, name});
            renderMailCcList();
        }
    }
};

window.clearMailMain = function() {
    document.getElementById('mailMainConcernId').value = '';
    document.getElementById('mailMainSelected').innerHTML = '<span class="text-sm text-slate-400 italic">No recipient selected</span>';
};

window.removeMailCc = function(id) {
    window.selectedCcList = window.selectedCcList.filter(e => e.id !== id);
    renderMailCcList();
};

window.renderMailCcList = function() {
    const container = document.getElementById('mailCcSelected');
    if (window.selectedCcList.length === 0) {
        container.innerHTML = '<span class="text-sm text-slate-400 italic">None</span>';
        return;
    }
    
    container.innerHTML = window.selectedCcList.map(emp => `
        <div class="inline-flex items-center gap-1.5 bg-white border border-slate-300 shadow-sm text-slate-700 px-2.5 py-1 rounded-md text-xs font-semibold">
            ${emp.name} 
            <button onclick="removeMailCc('${emp.id}')" class="hover:text-rose-500 transition-colors flex"><span class="material-icons text-[14px]">close</span></button>
        </div>
    `).join('');
};

window.executeMailSend = async function(jdId) {
    const mainId = document.getElementById('mailMainConcernId').value;
    if (!mainId) {
        alert("Please select a Main Concern recipient.");
        return;
    }
    
    const ccIds = window.selectedCcList.map(e => e.id);
    
    const contentDiv = document.getElementById('pdfOptionsModalContent');
    contentDiv.innerHTML = `
        <div class="p-10 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 border-4 border-slate-100 border-t-emerald-600 rounded-full animate-spin mb-6"></div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Sending Email...</h3>
            <p class="text-sm text-slate-500">Generating ZIP and dispatching to recipients.</p>
        </div>
    `;
    
    try {
        const isViewPage = window.location.pathname.includes('/view/');
        const url = (isViewPage ? '../api/' : 'api/') + 'send_task_mail.php';
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                jd_id: jdId,
                to: mainId,
                cc: ccIds
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            contentDiv.innerHTML = `
                <div class="p-10 flex flex-col items-center justify-center text-center">
                    <div class="w-24 h-24 rounded-full border-4 border-emerald-100 flex items-center justify-center mb-6">
                        <span class="material-icons text-5xl text-emerald-500" style="font-weight: bold;">check</span>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-2">Sent!</h3>
                    <p class="text-sm text-slate-500">The candidate list has been successfully emailed.</p>
                </div>
            `;
            setTimeout(closePdfOptionsModal, 3000);
        } else {
            throw new Error(result.message || 'Unknown error');
        }
    } catch(err) {
        contentDiv.innerHTML = `
            <div class="p-10 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 mb-6">
                    <span class="material-icons text-3xl">error_outline</span>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Sending Failed</h3>
                <p class="text-sm text-slate-500 mb-6">${err.message}</p>
                <button onclick="closePdfOptionsModal()" class="px-6 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg font-semibold transition-colors">Close</button>
            </div>
        `;
    }
};

window.deleteJobTask = async function(jdId, jobTitle) {

    if (!confirm(`Are you sure you want to delete the task "${jobTitle}" (${jdId})?\nThis will remove all associated candidates and data permanently.`)) return;
    
    // Optimistic UI: Hide/Fade row immediately
    const tr = document.querySelector(`tr[data-jdid="${jdId}"]`);
    const delBtn = tr?.querySelector('button[title="Delete"]');
    
    if (tr) {
        tr.style.opacity = '0.6'; // Keep it slightly visible so user sees the icon
        tr.style.pointerEvents = 'none';
    }
    
    if (delBtn) {
        delBtn.innerHTML = '<span class="material-icons rotating" style="font-size: 16px; vertical-align: middle;">sync</span>';
        delBtn.style.background = '#94a3b8'; // Muted color while deleting
    }

    try {
        const response = await fetch(getApiUrl('job_api.php?action=delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ jd_id: jdId })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showNotification(result.message, 'success');
            if (tr) tr.remove(); // Final removal
            loadJobList();
        } else {
            showNotification(result.message, 'error');
            if (tr) {
                tr.style.opacity = '1';
                tr.style.pointerEvents = 'auto';
            }
        }
    } catch (e) {
        showNotification('Failed to delete task.', 'error');
        if (tr) {
            tr.style.opacity = '1';
            tr.style.pointerEvents = 'auto';
        }
    }
};

function editJob(id) {
    alert('Edit feature coming soon for ID: ' + id);
}
function copyJob(id) {
    alert('Copy feature coming soon for ID: ' + id);
}
function deleteJob(id) {
    if (confirm('Delete Job ID: ' + id + '?')) {
        alert('Delete feature coming soon.');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // 1. Home Page / Job List Initialization
    const addJobBtn = document.getElementById('addJobBtnPublic') || document.getElementById('addJobBtn');
    if (addJobBtn) {
        addJobBtn.addEventListener('click', async () => {
            const jobTitleInput = document.getElementById('publicJobTitle');
            const jdIdInput = document.getElementById('publicJdId');
            const deptInput = document.getElementById('publicDept');
            const concernInput = document.getElementById('publicConcern');
            const sourceInput = document.querySelector('input[name="taskSource"]:checked');
            
            const concernEmailInput = document.getElementById('publicConcernEmail');
            const sendMailCheckbox = document.getElementById('sendEmailCheckbox');
            
            const jobTitle = jobTitleInput ? jobTitleInput.value.trim() : "";
            const jdId = jdIdInput ? jdIdInput.value.trim() : "";
            const dept = deptInput ? deptInput.value.trim() : "";
            const concern = concernInput ? concernInput.value.trim() : "";
            const concernEmail = concernEmailInput ? concernEmailInput.value : "";
            const sendMail = sendMailCheckbox && sendMailCheckbox.checked ? 1 : 0;
            const source = sourceInput ? sourceInput.value : "bdjobs";

            if (!jobTitle || !jdId) {
                alert('Please fill in both Job Title and JD ID');
                return;
            }

            const formData = new FormData();
            formData.append('job_title', jobTitle);
            formData.append('jd_id', jdId);
            formData.append('department', dept);
            formData.append('concern_person', concern);
            formData.append('concern_email', concernEmail);
            formData.append('send_mail', sendMail);
            formData.append('source', source);

            let totalFiles = 0;

            // Handle file uploads based on source
            if (source === 'manual' || source === 'both') {
                if (source === 'manual') {
                    const jdFile = document.getElementById('jdUploadFile').files[0];
                    if (jdFile) {
                        formData.append('jd_file', jdFile);
                    } else {
                        alert('Please upload a JD file for Manual CV mode.');
                        return;
                    }
                }

                const cvMode = document.querySelector('input[name="cvUploadMode"]:checked').value;
                const cvInputId = cvMode === 'files' ? 'cvUploadFiles' : 'cvUploadFolder';
                const cvFiles = document.getElementById(cvInputId).files;
                
                if (cvFiles.length > 1000) {
                    alert('File limit exceeded: You cannot upload more than 1000 files at once. You selected ' + cvFiles.length + ' files.');
                    return;
                }

                if (cvFiles.length > 0) {
                    for (let i = 0; i < cvFiles.length; i++) {
                        // Skip non-PDF files (case-insensitive check)
                        if (cvFiles[i].name.toLowerCase().endsWith('.pdf')) {
                            formData.append('cv_files[]', cvFiles[i]);
                            totalFiles++;
                        }
                    }
                    if (totalFiles === 0) {
                        alert('No valid PDF files found. Please ensure you select a folder containing PDF CVs.');
                        return;
                    }
                } else {
                    alert('Please upload at least one CV or select a folder containing CVs.');
                    return;
                }
            }

            addJobBtn.disabled = true;

            try {
                const result = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', getApiUrl('insert_job.php'));
                    
                    const progressContainer = document.getElementById('uploadProgressContainer');
                    const progressBar = document.getElementById('uploadProgressBar');
                    const progressTextUI = document.getElementById('uploadProgressText');
                    const progressStats = document.getElementById('uploadProgressStats');
                    
                    if (totalFiles > 0 && progressContainer) {
                        progressContainer.style.display = 'block';
                        progressBar.style.width = '0%';
                        progressBar.style.background = '#10b981';
                        progressTextUI.innerText = 'Uploading...';
                        progressStats.innerText = `0% [0/${totalFiles}]`;
                        addJobBtn.innerText = 'Uploading...';
                    } else {
                        addJobBtn.innerText = 'Creating...';
                    }
                    
                    xhr.upload.onprogress = function(event) {
                        if (event.lengthComputable) {
                            const percentComplete = Math.round((event.loaded / event.total) * 100);
                            if (totalFiles > 0 && progressContainer) {
                                progressBar.style.width = percentComplete + '%';
                                const uploadedEst = Math.floor((percentComplete / 100) * totalFiles);
                                progressStats.innerText = `${percentComplete}% [${uploadedEst}/${totalFiles}]`;
                            }
                        }
                    };

                    xhr.upload.onload = function() {
                        if (totalFiles > 0 && progressContainer) {
                            progressTextUI.innerText = 'Server Processing (Moving files)... Please wait.';
                            
                            // Create an animated striped background for the processing phase
                            progressBar.style.background = 'repeating-linear-gradient(45deg, #eab308, #eab308 10px, #ca8a04 10px, #ca8a04 20px)';
                            progressBar.style.backgroundSize = '28px 28px';
                            progressBar.style.animation = 'moveStripes 1s linear infinite';
                            
                            // Add the keyframes if they don't exist
                            if (!document.getElementById('progressAnimation')) {
                                const style = document.createElement('style');
                                style.id = 'progressAnimation';
                                style.innerHTML = `@keyframes moveStripes { 0% { background-position: 0 0; } 100% { background-position: 28px 0; } }`;
                                document.head.appendChild(style);
                            }

                            progressStats.innerText = `Saving ${totalFiles} files...`;
                            addJobBtn.innerText = 'Processing...';
                        }
                    };

                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                resolve(JSON.parse(xhr.responseText));
                            } catch(e) {
                                reject(new Error('Invalid JSON response'));
                            }
                        } else {
                            reject(new Error('Server error: ' + xhr.status));
                        }
                    };
                    
                    xhr.onerror = function() {
                        reject(new Error('Network error during upload'));
                    };

                    // Initial state is handled above, so just start send
                    xhr.send(formData);
                });
                if (result.status === 'success') {
                    // Check if there's a mandatory requirement
                    const aiPromptArea = document.getElementById('aiPrompt');
                    if (aiPromptArea && aiPromptArea.value.trim()) {
                        addJobBtn.innerText = 'Applying Req...';
                        try {
                            const promptResponse = await fetch(getApiUrl('prompt_api.php'), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ 
                                    prompt_text: aiPromptArea.value.trim(),
                                    jd_id: jdId 
                                })
                            });
                            const promptResult = await promptResponse.json();
                            if (promptResult.status === 'success') {
                                aiPromptArea.value = ''; // clear it
                                const reqSection = document.getElementById('mandatoryReqSection');
                                const reqIcon = document.getElementById('reqToggleIcon');
                                if (reqSection) reqSection.style.display = 'none';
                                if (reqIcon) reqIcon.textContent = 'add';
                            } else {
                                alert('Job added, but failed to store requirement: ' + promptResult.message);
                            }
                        } catch (pErr) {
                            console.error('Error saving prompt:', pErr);
                            alert('Job added, but connection error while storing requirement.');
                        }
                    }

                    alert('Task added successfully!');
                    if (jobTitleInput) jobTitleInput.value = '';
                    if (jdIdInput) jdIdInput.value = '';
                    const deptInput = document.getElementById('publicDept');
                    if (deptInput) deptInput.value = '';
                    const concernInput = document.getElementById('publicConcern');
                    if (concernInput) concernInput.value = '';
                    const jdFileInput = document.getElementById('jdUploadFile');
                    if (jdFileInput) jdFileInput.value = '';
                    const cvFilesInput = document.getElementById('cvUploadFiles');
                    if (cvFilesInput) cvFilesInput.value = '';
                    const cvFolderInput = document.getElementById('cvUploadFolder');
                    if (cvFolderInput) cvFolderInput.value = '';
                    const cvValidCount = document.getElementById('cvValidCount');
                    if (cvValidCount) {
                        cvValidCount.innerText = '';
                        cvValidCount.style.display = 'none';
                    }
                    
                    // Reset Task Source to default 'bdjobs' and hide upload sections
                    const bdjobsRadio = document.querySelector('input[name="taskSource"][value="bdjobs"]');
                    if (bdjobsRadio) {
                        bdjobsRadio.checked = true;
                        if (typeof toggleUploadOptions === 'function') toggleUploadOptions();
                    }
                    toggleAddTask(); // Close modal
                    loadJobList(); // Refresh the list
                } else {
                    alert('Error: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error adding job:', error);
                alert('Failed to add job: ' + error.message);
            } finally {
                addJobBtn.innerText = 'Create Task';
                addJobBtn.disabled = false;
                
                // Hide progress container on finish
                const pContainer = document.getElementById('uploadProgressContainer');
                if (pContainer) pContainer.style.display = 'none';
            }
        });
    }

    // Attach Filter Event Listeners for Job List
    ['filterJdId', 'filterCreatedBy'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', applyJobFilters);
    });

    const statusSelect = document.getElementById('filterStatus');
    if (statusSelect) {
        statusSelect.addEventListener('change', applyJobFilters);
        statusSelect.addEventListener('input', applyJobFilters); // Extra robustness
    }

    // Global Trigger Screening (Home Page)
    const globalTriggerBtn = document.getElementById('triggerGlobalScreening');
    if (globalTriggerBtn) {
        globalTriggerBtn.addEventListener('click', async () => {
            if (!confirm(`Are you sure you want to start CV screening for ALL jobs?`)) return;

            globalTriggerBtn.innerText = 'Starting...';
            globalTriggerBtn.disabled = true;

            try {
                const response = await fetch(getApiUrl('trigger_screening.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ job_title: 'All' })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    alert('Global screening triggered successfully!');
                } else {
                    alert('Error: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error triggering screening:', error);
                alert('Connection error while triggering screening.');
            } finally {
                globalTriggerBtn.innerText = 'Start CV Screening';
                globalTriggerBtn.disabled = false;
            }
        });
    }

    if (document.getElementById('jobList') || document.getElementById('candidateBody')) {
        // Fast title load for dashboard
        if (document.getElementById('candidateBody')) {
            const urlParams = new URLSearchParams(window.location.search);
            const jTitle = urlParams.get('job_title');
            const jId = urlParams.get('jd_id');
            const titleSpan = document.getElementById('dynamicJobTitle');
            const jdSpan = document.getElementById('jdBadge');
            if (titleSpan && jTitle) titleSpan.innerText = jTitle;
            if (jdSpan && jId) jdSpan.innerText = jId;
        }

        if (window.isPublicMode) {
            loadCandidates(1);
        } else {
            loadStatuses().then(() => {
                if (document.getElementById('jobList')) loadJobList();
                if (document.getElementById('candidateBody')) loadCandidates(1);
            });
        }
    }

    // Search input real-time for Dashboard
    document.querySelectorAll('.real-time').forEach(el => {
        const eventType = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(eventType, () => {
            clearTimeout(window.filterTimeout);
            window.filterTimeout = setTimeout(() => loadCandidates(1), 400);
        });

        if (el.tagName === 'INPUT') {
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    clearTimeout(window.filterTimeout);
                    loadCandidates(1);
                }
                // Custom logic for TOP N field: If value is 1 and ArrowDown is pressed, clear to "N"
                if (el.id === 'topFilter' && e.key === 'ArrowDown') {
                    if (el.value == '1') {
                        el.value = '';
                        clearTimeout(window.filterTimeout);
                        loadCandidates(1);
                        e.preventDefault();
                    } else if (el.value == '') {
                        // Already blank, prevent browser from resetting to 1 on ArrowDown
                        e.preventDefault();
                    }
                }
            });
        }
    });

    // Event delegation for Dashboard sorting
    document.querySelector('#candidateTable thead')?.addEventListener('click', (e) => {
        const th = e.target.closest('.sortable');
        if (th) {
            handleSort(th.getAttribute('data-sort'));
        }
    });

    // Main search button for Dashboard
    document.getElementById('searchBtn')?.addEventListener('click', () => loadCandidates(1));

    // Clear search button for Dashboard
    document.getElementById('clearSearchBtn')?.addEventListener('click', () => {
        document.querySelectorAll('.real-time').forEach(el => {
            if (el.tagName === 'SELECT') el.value = 'All';
            else el.value = '';
        });
        loadCandidates(1);
    });

    // Export Excel listener
    document.getElementById('exportCsv')?.addEventListener('click', () => {
        const search = document.getElementById('searchInput')?.value.trim() || '';
        const shortlisted = document.getElementById('shortlistedFilter')?.value || '';
        const confirmation = document.getElementById('confirmationFilter')?.value || '';
        const topN = document.getElementById('topFilter')?.value || '';
        
        let url = `${getApiUrl('export_candidates.php')}?search=${encodeURIComponent(search)}&shortlisted=${shortlisted}&confirmation=${confirmation}&sort_by=${currentSortBy}&sort_order=${currentSortOrder}&top_n=${topN}`;
        
        if (selectedJdId) {
            url += `&jd_id=${encodeURIComponent(selectedJdId)}`;
        } else if (selectedJobTitle) {
            url += `&job_title=${encodeURIComponent(selectedJobTitle)}`;
        }
        
        window.location.href = url;
    });

    // Auto-refresh logic (Every 5 seconds for real-time feel)
    let refreshInProgress = false;
    
    // Global interaction tracking to prevent refresh interruptions
    window.isUserInteracting = false;
    document.addEventListener('mousedown', () => window.isUserInteracting = true);
    document.addEventListener('mouseup', () => {
        // Delay resetting to allow the selection to be processed
        setTimeout(() => {
            window.isUserInteracting = window.getSelection().toString().length > 0;
        }, 100);
    });

    setInterval(async () => {
        if (refreshInProgress) return;

        // Shared Smart Pause States
        const hasSelection = window.getSelection().toString().length > 0;
        const isInteracting = window.isUserInteracting || hasSelection;
        const isModalOpen = Array.from(document.querySelectorAll('.modal')).some(m => m.style.display === 'flex' || m.style.display === 'block');

        // 1. Refresh Job List (Home/Tasks page)
        const jobList = document.getElementById('jobList');
        if (jobList && !window.isEditingTask) {
            const isHoveringJobRow = jobList.querySelector('tr:hover') !== null;
            if (!isInteracting && !isModalOpen && !isHoveringJobRow) {
                refreshInProgress = true;
                try {
                    await loadJobList();
                } finally {
                    refreshInProgress = false;
                }
            }
        }

        // 2. Refresh Dashboard
        const candidateBody = document.getElementById('candidateBody');
        if (candidateBody) {
            const isHoveringCandidateRow = document.querySelector('#candidateTable tbody tr:hover') !== null;
            if (!isInteracting && !isModalOpen && !isHoveringCandidateRow) {
                refreshInProgress = true;
                try {
                    const ind = document.querySelector('.live-indicator');
                    if (ind) {
                        ind.style.animation = 'none';
                        ind.offsetHeight; // trigger reflow
                        ind.style.animation = 'pulse-live 0.5s 1';
                        setTimeout(() => ind.style.animation = 'pulse-live 2s infinite', 500);
                    }
                    await loadCandidates(currentPage || 1);
                } finally {
                    refreshInProgress = false;
                }
            }
        }

        // 3. Refresh RPA Config if modal is open
        const rpaModal = document.getElementById('rpaConfigModal');
        if (rpaModal && rpaModal.style.display === 'flex' && !isInteracting) {
            refreshInProgress = true;
            try { await loadRpaConfigs(); } finally { refreshInProgress = false; }
        }

        // 4. Refresh User Manager if open
        const userModal = document.getElementById('userManagerModal');
        if (userModal && userModal.style.display === 'flex' && !window.editingUserRoleId && !isInteracting) {
            refreshInProgress = true;
            try { await loadUsers(); } finally { refreshInProgress = false; }
        }

        // 5. Refresh Permissions Modal if open
        const permModal = document.getElementById('permissionsModal');
        if (permModal && permModal.style.display === 'flex' && !window.permissionsModalDirty && !isInteracting) {
            refreshInProgress = true;
            try { await refreshPermissionsModal(); } finally { refreshInProgress = false; }
        }
    }, 3000);

    // Restore UI state (Manage Users modal) if pending after reload
    if (localStorage.getItem('restore_user_manager') === 'true') {
        localStorage.removeItem('restore_user_manager');
        toggleUserManager();
    }
});

// Form submission
const candidateForm = document.getElementById('candidateForm');
if (candidateForm) {
    candidateForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = Object.fromEntries(new FormData(candidateForm));
        try {
            const response = await fetch(getApiUrl('insert_candidate.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            const result = await response.json();
            if (result.status === 'success') {
                alert('Candidate submitted successfully!');
                candidateForm.reset();
            } else {
                alert('Error: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error submitting form:', error);
        }
    });
}

function toggleLogout(event) {
    toggleUserDropdown(event);
}


// Close dropdowns/modals when clicking outside
window.addEventListener('click', function (event) {
    // User Dropdown
    const userDropdown = document.getElementById('userDropdown');
    const profile = document.querySelector('.user-profile');
    if (userDropdown && userDropdown.classList.contains('show')) {
        if (profile && !profile.contains(event.target)) {
            userDropdown.classList.remove('show');
        }
    }

    // Filter Dropdown
    const filterBar = document.getElementById('filterBar');
    const filterBtn = document.querySelector('.filter-dropdown-container button');
    if (filterBar && filterBar.style.display === 'block') {
        if (!filterBar.contains(event.target) && (!filterBtn || !filterBtn.contains(event.target))) {
            filterBar.style.display = 'none';
        }
    }

    // Modals (Background click closing disabled as per user request)
    // if (event.target.classList.contains('modal-overlay')) {
    //     event.target.style.display = 'none';
    // }
});

// --- RPA Configuration Management ---
function toggleRpaConfig() {
    console.log('toggleRpaConfig called');
    const modal = document.getElementById('rpaConfigModal');
    if (modal) {
        const isNowShowing = modal.style.display !== 'flex';
        modal.style.display = isNowShowing ? 'flex' : 'none';
        console.log('Modal display set to:', modal.style.display);
        if (isNowShowing) loadRpaConfigs();
    } else {
        console.error('rpaConfigModal not found in DOM');
    }
}

function showAddRpaForm() {
    const form = document.getElementById('rpaForm');
    if (form) form.style.display = 'block';
    const idField = document.getElementById('rpaId');
    if (idField) idField.value = '';
    const keyField = document.getElementById('rpaKey');
    if (keyField) keyField.value = '';
    const valField = document.getElementById('rpaValue');
    if (valField) valField.value = '';
    const catField = document.getElementById('rpaCategory');
    if (catField) catField.value = '';
    const projField = document.getElementById('rpaProject');
    if (projField) projField.value = '';
    const descField = document.getElementById('rpaDescription');
    if (descField) descField.value = '';
}

let currentRpaSortField = 'updated_at';
let currentRpaSortOrder = 'desc';
let rpaConfigsData = [];

function sortRpaConfigs(field) {
    if (currentRpaSortField === field) {
        currentRpaSortOrder = currentRpaSortOrder === 'asc' ? 'desc' : 'asc';
    } else {
        currentRpaSortField = field;
        currentRpaSortOrder = 'asc';
    }
    renderRpaConfigs();
}

function filterRpaConfigs() {
    renderRpaConfigs();
}

async function loadRpaConfigs() {
    try {
        const response = await fetch(getApiUrl('rpa_config_api.php?action=all'));
        const result = await response.json();
        if (result.status === 'success') {
            rpaConfigsData = result.data;
            renderRpaConfigs();
        }
    } catch (e) { console.error(e); }
}

function renderRpaConfigs() {
    const tbody = document.getElementById('rpaConfigListBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    const filterText = (document.getElementById('rpaSearchInput')?.value || '').toLowerCase().trim();
    
    // Apply filtering
    let filteredData = rpaConfigsData.filter(conf => {
        const searchIn = [conf.key, conf.project, conf.category, conf.value].map(v => (v || '').toLowerCase()).join(' ');
        return searchIn.includes(filterText);
    });

    // Apply sorting
    filteredData.sort((a, b) => {
        // Multi-level sorting: If values are equal, fallback to Project > Category > Created At DESC
        const compare = (field, order) => {
            let valA = (a[field] || '').toString().toLowerCase();
            let valB = (b[field] || '').toString().toLowerCase();
            if (valA < valB) return order === 'asc' ? -1 : 1;
            if (valA > valB) return order === 'asc' ? 1 : -1;
            return 0;
        };

        // Primary Sort
        let result = compare(currentRpaSortField, currentRpaSortOrder);
        if (result !== 0) return result;

        // Level 2: Project (if primary wasn't project)
        if (currentRpaSortField !== 'project') {
            result = compare('project', 'asc');
            if (result !== 0) return result;
        }

        // Level 3: Category
        if (currentRpaSortField !== 'category') {
            result = compare('category', 'asc');
            if (result !== 0) return result;
        }

        // Level 4: Created At (DESC)
        return compare('created_at', 'desc');
    });

    if (filteredData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #94a3b8;">No matching configurations found.</td></tr>`;
        return;
    }

    filteredData.forEach(conf => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border)';
        const confEncoded = btoa(unescape(encodeURIComponent(JSON.stringify(conf))));
        
        const createdDate = conf.created_at ? new Date(conf.created_at).toLocaleString() : '-';
        const updatedDate = conf.updated_at ? new Date(conf.updated_at).toLocaleString() : '-';

        tr.innerHTML = `
            <td style="border-right: 1px solid var(--border); padding: 8px 10px; font-size: 0.8rem;"><code style="font-weight:700; color:var(--primary); word-break: break-all;">${conf.key}</code></td>
            <td style="border-right: 1px solid var(--border); padding: 8px 10px; font-size: 0.8rem;"><div style="max-width: 100%; white-space: normal; word-break: break-all;" title="${conf.value?.replace(/"/g, '&quot;')}">${conf.value || '-'}</div></td>
            <td style="border-right: 1px solid var(--border); padding: 8px 10px; font-size: 0.75rem; color: var(--text);">${conf.project}</td>
            <td style="border-right: 1px solid var(--border); padding: 8px 10px; font-size: 0.75rem; color: var(--text-light);">${conf.category || 'General'}</td>
            <td style="border-right: 1px solid var(--border); padding: 8px 10px; font-size: 0.75rem; color: var(--text-light);"><div style="max-width: 100%; white-space: normal; font-style: italic;" title="${conf.description?.replace(/"/g, '&quot;')}">${conf.description || '-'}</div></td>
            <td style="border-right: 1px solid var(--border); padding: 8px 10px; font-size: 0.75rem;">
                <div style="font-weight:600; color:var(--text);">${conf.created_by || 'System'}</div>
                <div style="font-size: 0.65rem; color: var(--text-light);">${createdDate}</div>
            </td>
            <td style="border-right: 1px solid var(--border); padding: 8px 10px; font-size: 0.75rem;">
                <div style="font-weight:600; color:var(--text);">${conf.updated_by || 'System'}</div>
                <div style="font-size: 0.65rem; color: var(--text-light);">${updatedDate}</div>
            </td>
            <td style="text-align: center; padding: 8px 10px;">
                <div style="display: flex; gap: 4px; justify-content: center;">
                    <button onclick="editRpaConfigFromBase64('${confEncoded}')" class="btn-secondary" style="padding: 2px 6px; font-size: 0.75rem;">Edit</button>
                    <button onclick="deleteRpaConfig(${conf.id})" class="btn-danger" style="padding: 2px 6px; background: #ef4444; border:none; color:white; font-size: 0.75rem;">Del</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function editRpaConfig(conf) {
    const form = document.getElementById('rpaForm');
    if (form) form.style.display = 'block';
    const idField = document.getElementById('rpaId');
    if (idField) idField.value = conf.id;
    const keyField = document.getElementById('rpaKey');
    if (keyField) keyField.value = conf.key;
    const valField = document.getElementById('rpaValue');
    if (valField) valField.value = conf.value;
    const catField = document.getElementById('rpaCategory');
    if (catField) catField.value = conf.category;
    const projField = document.getElementById('rpaProject');
    if (projField) projField.value = conf.project;
    const descField = document.getElementById('rpaDescription');
    if (descField) descField.value = conf.description || '';
}

async function saveRpaConfig() {
    const data = {
        id: document.getElementById('rpaId').value,
        key: document.getElementById('rpaKey').value.trim(),
        value: document.getElementById('rpaValue').value.trim(),
        category: document.getElementById('rpaCategory').value.trim(),
        project: document.getElementById('rpaProject').value.trim(),
        description: document.getElementById('rpaDescription').value.trim()
    };

    if (!data.key || !data.project) return alert('Key and Project are required');

    try {
        const response = await fetch(getApiUrl('rpa_config_api.php?action=' + (data.id ? 'update' : 'create')), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.status === 'success') {
            const form = document.getElementById('rpaForm');
            if (form) form.style.display = 'none';
            loadRpaConfigs();
        } else { alert(result.message); }
    } catch (e) { console.error(e); }
}

async function deleteRpaConfig(id) {
    if (!confirm('Are you sure you want to delete this configuration?')) return;
    try {
        const response = await fetch(getApiUrl('rpa_config_api.php?action=delete'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        if ((await response.json()).status === 'success') loadRpaConfigs();
    } catch (e) { console.error(e); }
}

function editRpaConfigFromBase64(base64) {
    try {
        const conf = JSON.parse(decodeURIComponent(escape(atob(base64))));
        editRpaConfig(conf);
    } catch (e) { console.error('Failed to parse config data', e); }
}

// --- Permissions Management ---
let currentPermUserId = null;
let currentPermUserFullName = "";
let currentPermUserRole = "";
let currentPermUserPermissions = {};
window.permissionsModalDirty = false;

function openPermissionsModalFromBase64(base64) {
    try {
        const user = JSON.parse(decodeURIComponent(escape(atob(base64))));
        openPermissionsModal(user.id, user.full_name, user.role, user.permissions);
    } catch (e) {
        console.error('Failed to parse user data from base64', e);
    }
}

function openPermissionsModal(userId, fullName, role, permissions) {
    if (typeof permissions === 'string') {
        try {
            permissions = JSON.parse(permissions);
        } catch (e) {
            console.error('Failed to parse permissions string', e);
            permissions = {};
        }
    }
    
    // Save state for real-time refresh
    currentPermUserId = userId;
    currentPermUserFullName = fullName;
    currentPermUserRole = role;
    currentPermUserPermissions = permissions;
    window.permissionsModalDirty = false;

    // Ensure global viewer permissions are parsed
    if (typeof window.currentUserPermissions === 'string') {
        try { window.currentUserPermissions = JSON.parse(window.currentUserPermissions); } catch(e) {}
    }

    const modal = document.getElementById('permissionsModal');
    const title = document.getElementById('permUserTitle');
    const container = document.getElementById('permissionsChecklist');
    
    if (!modal || !container) return;
    
    title.innerText = `${fullName} (${role})`;
    modal.style.display = 'flex';
    
    const permConfig = [
        { key: 'view_my_task', label: 'My Task View', desc: 'Can view only tasks created by themselves on dashboard' },
        { key: 'view_all_task', label: 'All Task View', desc: 'Can view all tasks on dashboard' },
        { key: 'manage_users', label: 'User List', desc: 'Can view and browse the system user directory' },
        { key: 'create_user', label: 'Create User', desc: 'Can manually register or create new system users' },
        { key: 'manage_roles', label: 'Manage Permissions', desc: 'Can edit other users permission flags' },
        { key: 'manage_actions', label: 'User Management', desc: 'Can Block, Edit, or Delete system users' },
        { key: 'view_user_activity', label: 'View User Activity', desc: 'Can see real-time user online status and activity' },
        { key: 'manage_employees', label: 'Manage Employees', desc: 'Can view and search employee master data' },
        { key: 'manage_employee_actions', label: 'Employee Master Actions', desc: 'Can Create, Edit, or Delete records in employee master' },
        { key: 'manage_statuses', label: 'Manage Statuses', desc: 'Can add/delete/reorder job statuses' },
        { key: 'manage_sources', label: 'Manage Sources', desc: 'Can add/delete job sources (BDJobs, Manual, etc.)' },
        { key: 'manage_rpa', label: 'RPA Config', desc: 'Can edit system backend/RPA keys' },
        { key: 'manage_server_allocation', label: 'Server Allocation', desc: 'Can manage server pool count and audit logs' },
        { key: 'db_control', label: 'Database Control', desc: 'Full DB access: CRUD, Truncate, Drop, Custom Query' },
        { key: 'manage_global_layouts', label: 'Global Layouts', desc: 'Can push dashboard column templates to all users globally' },
        { key: 'add_task', label: 'Create Tasks', desc: 'Can create new screening tasks' },
        { key: 'trigger_screening', label: 'Trigger AI', desc: 'Can start/stop AI screening process' },
        { key: 'export_data', label: 'Export Data', desc: 'Can download candidate data as Excel/CSV' },
        { key: 'send_mail_to_concern', label: 'Mail to Concern', desc: 'Can send filtered candidate lists to concern persons via email' },
        { key: 'access_chat', label: 'AI Chat Agent', desc: 'Can use the AI Chat bot for data analysis' },
        { key: 'manage_tasks', label: 'Manage Tasks', desc: 'Can view and delete screening tasks' },
        { key: 'manage_task_limits', label: 'Task Limits', desc: 'Can manage daily/monthly system and user task quotas' },
        { key: 'manage_theme_settings', label: 'Theme Settings', desc: 'Can toggle dark mode and adjust system zoom' },
        { key: 'reset_password', label: 'Reset Password', desc: 'Can reset password via email if forgotten' }
    ];

    container.innerHTML = permConfig
        .filter(p => {
            // ONLY show permissions the viewer ALREADY HAS.
            // Super Admin can see/delegate anything.
            return window.isSuperAdmin || 
                   (window.currentUserPermissions && (
                      window.currentUserPermissions[p.key] === true || 
                      window.currentUserPermissions[p.key] === "true" || 
                      window.currentUserPermissions[p.key] === 1 || 
                      window.currentUserPermissions[p.key] === "1" || 
                      window.currentUserPermissions[p.key] === "on"
                   ));
        })
        .map(p => `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8fafc; border-radius: 10px; margin-bottom: 8px; border: 1px solid var(--border);">
                <div style="display: flex; flex-direction: column;">
                    <span style="font-weight: 600; color: #1e293b;">${p.label}</span>
                    <span style="font-size: 0.75rem; color: #64748b;">${p.desc}</span>
                </div>
                <label class="switch">
                    <input type="checkbox" data-perm="${p.key}" ${currentPermUserPermissions && (currentPermUserPermissions[p.key] === true || currentPermUserPermissions[p.key] === "on" || currentPermUserPermissions[p.key] === "1" || currentPermUserPermissions[p.key] === 1) ? 'checked' : ''}>
                    <span class="slider round"></span>
                </label>
            </div>
        `).join('');

    // Mark as dirty when user interacts
    container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.onchange = () => { window.permissionsModalDirty = true; };
    });
}

window.refreshPermissionsModal = function() {
    if (currentPermUserId) {
        openPermissionsModal(currentPermUserId, currentPermUserFullName, currentPermUserRole, currentPermUserPermissions);
    }
};

// --- User Creation & Inline Editing ---
window.editingUserRoleId = null;

window.toggleAddUserForm = function() {
    const form = document.getElementById('addUserForm');
    if (!form) return;
    const isShowing = form.style.display !== 'none';
    
    if (!isShowing) {
        form.style.display = 'block';
        document.getElementById('userBtnText').innerText = "Cancel Form";
    } else {
        form.style.display = 'none';
        document.getElementById('userBtnText').innerText = "Create New User";
    }

    // Security: Only a Super Admin can create another Super Admin
    if (!isShowing) {
        const roleSelect = document.getElementById('newRole');
        if (roleSelect) {
            const isSuper = window.isSuperAdmin || (window.currentUser && window.currentUser.role === 'super-admin');
            
            // First, remove it if it exists to avoid duplicates
            const existingOpt = roleSelect.querySelector('option[value="super-admin"]');
            if (existingOpt) existingOpt.remove();

            if (isSuper) {
                const opt = document.createElement('option');
                opt.value = 'super-admin';
                opt.text = 'Super Admin';
                roleSelect.appendChild(opt);
            }
        }
    }
};

window.startInlineEdit = function(userId) {
    window.editingUserRoleId = userId;
    loadUsers(); // Refresh to show dropdown
};

window.cancelInlineEdit = function() {
    window.editingUserRoleId = null;
    loadUsers();
};

window.saveInlineRole = async function(userId) {
    const select = document.getElementById(`inlineRole_${userId}`);
    if (!select) return;
    const newRole = select.value;

    try {
        const response = await fetch(getApiUrl('user_api.php?action=update_role'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                user_id: userId, 
                role: newRole 
            })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showNotification(result.message, 'success');
            window.editingUserRoleId = null;
            loadUsers();
        } else {
            showNotification(result.message, 'error');
        }
    } catch (e) {
        showNotification('Failed to update user.', 'error');
    }
};

function togglePermissionsModal() {
    const modal = document.getElementById('permissionsModal');
    if (!modal) return;
    const isOpening = modal.style.display !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    
    // If opening from sidebar (no specific user context), 
    // we should ideally show the user manager or a specific UI.
    // For now, if no user is selected, we prompt the user to use User Manager.
    if (isOpening && !currentPermUserId) {
        alert('Please open "Manage Users" and click "Perms" for a specific user to edit their permissions.');
        modal.style.display = 'none';
        toggleUserManager();
    }
}

async function savePermissions() {
    if (!currentPermUserId) return;
    
    const checkboxes = document.querySelectorAll('#permissionsChecklist input[type="checkbox"]');
    const permissions = {};
    checkboxes.forEach(cb => {
        permissions[cb.getAttribute('data-perm')] = cb.checked;
    });

    try {
        const response = await fetch(getApiUrl('user_api.php?action=update_permissions'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: currentPermUserId,
                permissions: permissions
            })
        });
        const result = await response.json();
        if (result.status === 'success') {
            const isSelf = window.currentUser && currentPermUserId == window.currentUser.db_id;
            
            if (isSelf) {
                // UPDATE LOCAL STATE INSTANTLY
                window.currentUserPermissions = permissions;
                // Update global management flags based on new perms
                const isRoot = (window.currentUser && window.currentUser.id === (window.rootAdminId || "097727"));
                window.canManageUsers = isRoot || permissions['manage_users'] || permissions['create_user'];
                
                // Refresh Modal Button Visibility immediately
                const createBtn = document.getElementById('toggleUserFormBtn');
                if (createBtn) {
                    const isSuper = isRoot || (window.currentUser && window.currentUser.role === 'super-admin');
                    const canCreate = isSuper || permissions['create_user'];
                    createBtn.style.display = canCreate ? 'flex' : 'none';
                }
            }

            alert('Permissions updated successfully!');
            togglePermissionsModal();
            if (typeof loadUsers === 'function') loadUsers();
        } else {
            alert(result.message || 'Error updating permissions');
        }
    } catch (error) {
        console.error('Error saving permissions:', error);
        alert('An unexpected error occurred.');
    } finally {
        // if (btn) { // Uncomment if btn is guaranteed to be defined
        //     btn.innerHTML = originalText;
        //     btn.disabled = false;
        // }
    }
}

function updateDynamicUI(perms) {
    // 1. Update Chat Widget Visibility
    const chatWidget = document.getElementById('aiChatWidget');
    if (chatWidget) {
        const hasAccess = perms.access_chat === true || perms.access_chat === 'true';
        chatWidget.style.display = hasAccess ? 'block' : 'none';
        
        // If access removed, ensure window is closed
        if (!hasAccess) {
            const chatWindow = document.getElementById('aiChatWindow');
            if (chatWindow) chatWindow.style.display = 'none';
        }
    }

    window.canAccessChat = perms.access_chat === true || perms.access_chat === 'true';
    window.canManageUsers = perms.manage_users === true || perms.manage_users === 'true';
    window.currentUserPermissions = perms;
}
// --- Database Control Management ---
let dbTablesList = [];
let activeDbTable = '';
let activeDbQuery = '';

function toggleDbControl() {
    const modal = document.getElementById('dbControlModal');
    if (!modal) return;
    const isOpening = modal.style.display !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    if (isOpening) loadDbTables();
}

async function loadDbTables() {
    try {
        const res = await fetch(getApiUrl('db_control_api.php?action=list_tables'));
        const result = await res.json();
        if (result.status === 'success') {
            dbTablesList = result.data;
            renderDbTableList();
        }
    } catch (e) { console.error(e); }
}

function renderDbTableList() {
    const container = document.getElementById('dbTableList');
    if (!container) return;
    const search = (document.getElementById('dbTableSearch')?.value || '').toLowerCase();
    
    container.innerHTML = dbTablesList.filter(t => t.toLowerCase().includes(search)).map(table => `
        <div class="db-table-item ${activeDbTable === table ? 'active' : ''}" onclick="selectDbTable('${table}')">
            <span class="material-icons" style="font-size: 18px;">grid_view</span>
            ${table}
        </div>
    `).join('');
}

function filterDbTables() { renderDbTableList(); }

function switchDbTab(tab) {
    document.querySelectorAll('.db-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.db-content-area').forEach(v => v.style.display = 'none');
    
    const clickedTab = document.querySelector(`.db-tab[onclick*="'${tab}'"]`);
    if (clickedTab) clickedTab.classList.add('active');
    
    if (tab === 'data') document.getElementById('dbDataView').style.display = 'flex';
    else document.getElementById('dbQueryView').style.display = 'flex';
}

async function selectDbTable(table) {
    activeDbTable = table;
    renderDbTableList();
    
    // Auto-switch to Data Browser when a table is selected
    switchDbTab('data');
    
    document.getElementById('dbTableActions').style.display = 'flex';
    document.getElementById('activeTableName').innerText = table;
    document.getElementById('dbDataTableContainer').innerHTML = '<div style="padding: 20px;">Loading data...</div>';
    
    try {
        // Get columns first
        const detailRes = await fetch(getApiUrl(`db_control_api.php?action=table_details&table=${table}`));
        const details = await detailRes.json();
        
        // Get data
        const dataRes = await fetch(getApiUrl(`db_control_api.php?action=list_data&table=${table}`));
        const data = await dataRes.json();
        
        if (details.status === "success" && data.status === "success") {
            renderDbGrid(details.data.columns, data.data, details.data.total_records);
        }
    } catch (e) { console.error(e); }
}

function renderDbGrid(columns, rows, total) {
    const container = document.getElementById('dbDataTableContainer');
    document.getElementById('tableRecordCount').innerText = `${total} records`;
    
    if (rows.length === 0) {
        container.innerHTML = '<div style="padding: 40px; text-align: center; color: #94a3b8;">Table is empty.</div>';
        return;
    }

    let html = `<table class="db-grid"><thead><tr>`;
    columns.forEach(col => {
        html += `<th>${col.Field} <br><small style="color:#94a3b8; font-weight:normal;">${col.Type}</small></th>`;
    });
    html += `<th style="text-align:center;">Action</th></tr></thead><tbody>`;
    
    // Find PK field
    const pkCol = columns.find(c => c.Key === 'PRI')?.Field;

    rows.forEach(row => {
        html += `<tr>`;
        columns.forEach(col => {
            const val = row[col.Field];
            const displayVal = val === null ? '<i style="color:#cbd5e1;">NULL</i>' : (typeof val === 'string' ? val.replace(/</g, '&lt;') : val);
            html += `<td title="${displayVal}">${displayVal}</td>`;
        });
        
        // Action buttons
        const rowData = btoa(unescape(encodeURIComponent(JSON.stringify(row))));
        html += `<td style="text-align:center;">
            <button class="db-action-btn edit" onclick="openEditRecordModal('${rowData}')" title="Edit Record">
                <span class="material-icons" style="font-size:18px;">edit</span>
            </button>
            ${pkCol ? `
            <button class="db-action-btn delete" onclick="deleteDbRecord('${pkCol}', '${row[pkCol]}')" title="Delete Record">
                <span class="material-icons" style="font-size:18px;">delete</span>
            </button>` : ''}
        </td>`;
        html += `</tr>`;
    });
    
    html += `</tbody></table>`;
    container.innerHTML = html;
}

async function executeDbQuery() {
    const query = document.getElementById('dbQueryInput').value.trim();
    if (!query) return;
    
    const resultContainer = document.getElementById('dbQueryResult');
    resultContainer.innerHTML = '<div style="padding: 20px;">Executing...</div>';
    
    try {
        const res = await fetch(getApiUrl('db_control_api.php?action=execute_query'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ query })
        });
        const result = await res.json();
        
        if (result.status === 'success') {
            if (result.type === 'select') {
                if (result.data.length === 0) {
                    resultContainer.innerHTML = '<div style="padding: 20px; color: #64748b;">Query success: 0 rows returned.</div>';
                } else {
                    const cols = Object.keys(result.data[0]);
                    let html = `<table class="db-grid"><thead><tr>`;
                    cols.forEach(c => html += `<th>${c}</th>`);
                    html += `</tr></thead><tbody>`;
                    result.data.forEach(row => {
                        html += `<tr>`;
                        cols.forEach(c => html += `<td>${row[c]}</td>`);
                        html += `</tr>`;
                    });
                    html += `</tbody></table>`;
                    resultContainer.innerHTML = html;
                }
            } else {
                resultContainer.innerHTML = `
                    <div style="padding: 20px; color: #10b981; display:flex; align-items:center; gap:10px;">
                        <span class="material-icons">check_circle</span>
                        <div>
                            <strong>Success!</strong><br>
                            ${result.message} (Affected: ${result.affected_rows})
                        </div>
                    </div>`;
                loadDbTables(); // Refresh table list in case of CREATE/DROP
            }
        } else {
            resultContainer.innerHTML = `
                <div style="padding: 20px; color: #ef4444; display:flex; align-items:center; gap:10px;">
                    <span class="material-icons">error</span>
                    <div><strong>Error:</strong><br>${result.message}</div>
                </div>`;
        }
    } catch (e) { console.error(e); }
}

function clearDbQuery() {
    document.getElementById('dbQueryInput').value = '';
    document.getElementById('dbQueryResult').innerHTML = '<div style="padding: 20px; color: #94a3b8;">Result will appear here...</div>';
}

async function dbTableOp(op) {
    if (!activeDbTable) return;
    const confirmMsg = op === 'drop' 
        ? `🚨 DANGER: Are you sure you want to DROP the table "${activeDbTable}"? This will permanently delete all data and the table structure!`
        : `Are you sure you want to TRUNCATE "${activeDbTable}"? This will delete all records but keep the structure.`;
        
    if (!confirm(confirmMsg)) return;
    
    try {
        const res = await fetch(getApiUrl('db_control_api.php?action=table_op'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ table: activeDbTable, op })
        });
        const result = await res.json();
        if (result.status === 'success') {
            alert(result.message);
            if (op === 'drop') {
                activeDbTable = '';
                document.getElementById('dbTableActions').style.display = 'none';
                document.getElementById('dbDataTableContainer').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8; flex-direction: column;"><span class="material-icons" style="font-size: 48px; margin-bottom: 10px;">table_chart</span>Select a table</div>';
            }
            loadDbTables();
            if (activeDbTable) selectDbTable(activeDbTable);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) { console.error(e); }
}

// --- NEW DB RECORD OPERATIONS ---

function toggleCreateTableModal() {
    const modal = document.getElementById('createTableModal');
    if (modal) modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
}

function openCreateTableModal() {
    document.getElementById('newTableName').value = '';
    document.getElementById('createTableColsContainer').innerHTML = ''; // Clear previous dynamic rows
    addCreateTableColRow(true); // Add the first required row
    toggleCreateTableModal();
}

function addCreateTableColRow(isRequired = false) {
    const container = document.getElementById('createTableColsContainer');
    const rowId = 'col_row_' + Date.now();
    
    // We create a wrapper that has the column name and column type inputs
    const html = `
        <div id="${rowId}" style="display: flex; gap: 10px; align-items: flex-end;">
            <div style="flex: 1;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Column Name ${isRequired ? '<small style="color:#ef4444;">*</small>' : ''}</label>
                <input type="text" class="new_table_col_name" required="${isRequired}" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px;" placeholder="${isRequired ? 'id' : 'col_name'}">
            </div>
            <div style="flex: 1;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Data Type</label>
                <select class="new_table_col_type" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 6px;">
                    ${isRequired ? '<option value="INT AUTO_INCREMENT PRIMARY KEY">INT AUTO_INCREMENT PRIMARY KEY</option>' : ''}
                    <option value="VARCHAR(255)">VARCHAR(255)</option>
                    <option value="INT">INT</option>
                    <option value="TEXT">TEXT</option>
                    <option value="DATE">DATE</option>
                    <option value="BOOLEAN">BOOLEAN</option>
                </select>
            </div>
            ${!isRequired ? `
            <button type="button" class="btn-secondary" onclick="document.getElementById('${rowId}').remove()" style="padding: 8px; color: #ef4444; border-color: #fecaca; background: #fff;">
                <span class="material-icons" style="font-size: 18px;">delete</span>
            </button>
            ` : '<div style="width: 36px;"></div>'}
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
}

async function createDbTable() {
    const tableName = document.getElementById('newTableName').value.trim();
    
    if (!tableName) {
        alert("Table Name is required.");
        return;
    }

    // Gather all columns
    const columns = [];
    const nameInputs = document.querySelectorAll('.new_table_col_name');
    const typeSelects = document.querySelectorAll('.new_table_col_type');
    
    let hasError = false;
    nameInputs.forEach((input, index) => {
        const name = input.value.trim();
        const type = typeSelects[index].value;
        if (!name) {
            hasError = true;
        } else {
            columns.push({ name, type });
        }
    });

    if (hasError || columns.length === 0) {
        alert("All column name fields must be filled out.");
        return;
    }

    try {
        const res = await fetch(getApiUrl('db_control_api.php?action=create_table'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                table: tableName,
                columns: columns
            })
        });
        const result = await res.json();
        
        if (result.status === 'success') {
            alert(result.message);
            toggleCreateTableModal();
            loadDbTables(); // Refresh the table list in the sidebar
            setTimeout(() => selectDbTable(tableName), 500); // Auto-select the new table
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        console.error(e);
        alert('Failed to create table. Check console.');
    }
}

function toggleDbColumnModal() {
    const modal = document.getElementById('dbColumnModal');

    if (modal) modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
}

function openAddColumnModal() {
    if (!activeDbTable) return;
    document.getElementById('newColName').value = '';
    document.getElementById('newColDefault').value = '';
    toggleDbColumnModal();
}

async function addDbColumn() {
    if (!activeDbTable) return;
    const colName = document.getElementById('newColName').value.trim();
    const colType = document.getElementById('newColType').value;
    const colDefault = document.getElementById('newColDefault').value.trim();
    
    if (!colName) {
        alert("Column name is required");
        return;
    }

    try {
        const res = await fetch(getApiUrl('db_control_api.php?action=add_column'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                table: activeDbTable,
                name: colName,
                type: colType,
                default_val: colDefault
            })
        });
        const result = await res.json();
        
        if (result.status === 'success') {
            alert(result.message);
            toggleDbColumnModal();
            selectDbTable(activeDbTable); // refresh the table structure and data
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        console.error(e);
        alert('Failed to add column. Check console.');
    }
}

let currentTableColumns = []; // Store columns for the active table

function toggleDbRecordModal() {
    const modal = document.getElementById('dbRecordModal');
    if (modal) modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
}

async function openAddRecordModal() {
    if (!activeDbTable) return;
    document.getElementById('dbRecordModalTitle').innerText = `Add New Record: ${activeDbTable}`;
    generateDbRecordForm(null);
    toggleDbRecordModal();
}

function openEditRecordModal(encodedData) {
    if (!activeDbTable) return;
    try {
        const row = JSON.parse(decodeURIComponent(escape(atob(encodedData))));
        document.getElementById('dbRecordModalTitle').innerText = `Edit Record: ${activeDbTable}`;
        generateDbRecordForm(row);
        toggleDbRecordModal();
    } catch (e) { console.error('Error decoding row data:', e); }
}

async function generateDbRecordForm(rowData = null) {
    const form = document.getElementById('dbRecordForm');
    form.innerHTML = '';
    
    // We need columns metadata. If it's not held globally, we fetch it
    if (currentTableColumns.length === 0 || rowData === null) {
        const res = await fetch(getApiUrl(`db_control_api.php?action=table_details&table=${activeDbTable}`));
        const result = await res.json();
        if (result.status === 'success') {
            currentTableColumns = result.data.columns;
        }
    }

    currentTableColumns.forEach(col => {
        const field = col.Field;
        const type = col.Type;
        const isPk = col.Key === 'PRI';
        const value = rowData ? rowData[field] : '';
        
        const div = document.createElement('div');
        div.className = 'form-group';
        div.innerHTML = `
            <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #475569;">
                ${field} <small style="color: #94a3b8; font-weight: normal;">(${type})</small>
                ${isPk ? '<span style="font-size: 0.7rem; background: #fef9c3; color: #854d0e; padding: 2px 5px; border-radius: 4px; margin-left: 5px;">Primary Key</span>' : ''}
            </label>
            ${type.includes('text') || type.includes('longtext') || type.includes('varchar(255)') 
                ? `<textarea name="${field}" class="db-field-input" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; min-height: 80px;">${value || ''}</textarea>`
                : `<input type="text" name="${field}" value="${value || ''}" class="db-field-input" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;" ${isPk && rowData ? 'readonly style="background:#f1f5f9; cursor:not-allowed;"' : ''}>`
            }
        `;
        form.appendChild(div);
    });
    
    // Store PK info in form data attributes for saving
    const pkCol = currentTableColumns.find(c => c.Key === 'PRI');
    form.dataset.pkField = pkCol ? pkCol.Field : '';
    form.dataset.pkValue = (rowData && pkCol) ? rowData[pkCol.Field] : '';
}

async function saveDbRecord() {
    const form = document.getElementById('dbRecordForm');
    const inputs = form.querySelectorAll('.db-field-input');
    const data = {};
    
    inputs.forEach(input => {
        data[input.name] = input.value;
    });

    const pkField = form.dataset.pkField;
    const pkValue = form.dataset.pkValue;

    try {
        const res = await fetch(getApiUrl('db_control_api.php?action=save_record'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                table: activeDbTable,
                data: data,
                pk_field: pkField,
                pk_value: pkValue || null
            })
        });
        const result = await res.json();
        if (result.status === 'success') {
            alert(result.message);
            toggleDbRecordModal();
            selectDbTable(activeDbTable); // Refresh grid
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) { 
        console.error(e);
        alert('Failed to save record. Check console.');
    }
}

async function deleteDbRecord(pkField, pkValue) {
    if (!confirm(`Are you sure you want to delete this record (${pkField}=${pkValue})?`)) return;
    
    try {
        const res = await fetch(getApiUrl('db_control_api.php?action=delete_record'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                table: activeDbTable,
                pk_field: pkField,
                pk_value: pkValue
            })
        });
        const result = await res.json();
        if (result.status === 'success') {
            alert(result.message);
            selectDbTable(activeDbTable); // Refresh
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) { console.error(e); }
}

function exportDbTable() {
    if (!activeDbTable) return;
    window.location.href = getApiUrl(`db_control_api.php?action=export_csv&table=${activeDbTable}`);
}

// --- AI Chat Agent Integration ---

function toggleAiChat() {
    const chatWindow = document.getElementById('aiChatWindow');
    const chatButton = document.getElementById('aiChatButton');
    
    if (!chatWindow || !chatButton) {
        console.error("Chat elements not found");
        return;
    }
    
    // Get computed style if inline style is empty
    const isHidden = window.getComputedStyle(chatWindow).display === 'none';
    
    if (isHidden) {
        chatWindow.style.display = 'flex';
        chatButton.style.display = 'none'; // Ensure it hides
        setTimeout(() => {
            const input = document.getElementById('aiChatInput');
            if (input) input.focus();
        }, 100);
    } else {
        chatWindow.style.display = 'none';
        chatButton.style.display = 'flex';
    }
}

function handleChatKeyPress(event) {
    if (event.key === 'Enter') {
        sendChatMessage();
    }
}

async function sendChatMessage() {
    const inputField = document.getElementById('aiChatInput');
    const message = inputField.value.trim();
    if (!message) return;

    // Clear input
    inputField.value = '';
    
    // Add user message to UI
    appendChatMessage('user', message);

    // Show typing indicator
    const typingId = showTypingIndicator();

    try {
        const res = await fetch(getApiUrl('chat_agent.php'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                message: message,
                user: window.currentUser || {} 
            })
        });
        
        const result = await res.json();
        
        // Remove typing indicator
        removeTypingIndicator(typingId);
        
        if (result.status === 'success') {
            appendChatMessage('bot', result.data);
        } else {
            appendChatMessage('bot', 'Sorry, I encountered an error: ' + result.message);
        }
        
    } catch (e) {
        console.error(e);
        removeTypingIndicator(typingId);
        appendChatMessage('bot', 'Connection error. Could not reach the AI agent.');
    }
}

function appendChatMessage(sender, text) {
    const chatBody = document.getElementById('aiChatMessages');
    const newDiv = document.createElement('div');
    newDiv.className = `chat-message ${sender}`;
    
    // Convert basic markdown to HTML
    let formattedText = text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // Bold: **text**
        .replace(/^\s*[-*]\s+(.*)$/gm, '• $1')            // Bullet points
        .replace(/\n/g, '<br>');                           // Newlines
    
    newDiv.innerHTML = `<div class="msg-bubble">${formattedText}</div>`;
    chatBody.appendChild(newDiv);
    
    // Scroll to bottom
    chatBody.scrollTop = chatBody.scrollHeight;
}

function showTypingIndicator() {
    const chatBody = document.getElementById('aiChatMessages');
    const id = 'typing_' + Date.now();
    const newDiv = document.createElement('div');
    newDiv.className = `chat-message bot`;
    newDiv.id = id;
    newDiv.innerHTML = `
        <div class="typing-indicator">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        </div>
    `;
    chatBody.appendChild(newDiv);
    chatBody.scrollTop = chatBody.scrollHeight;
    return id;
}

function removeTypingIndicator(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}
// --- Server Allocation Management ---
function toggleServerAllocation() {
    const modal = document.getElementById('serverAllocationModal');
    if (!modal) return;
    const isOpening = modal.style.display !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    if (isOpening) {
        // Reset to disabled state when opening
        const input = document.getElementById('serverCountInput');
        const btn = document.getElementById('serverUpdateBtn');
        if (input) {
            input.disabled = true;
            input.style.background = '#f8fafc';
        }
        if (btn) btn.innerText = 'Update';
        loadServerConfig();
    }
}

let originalServerCount = 0;

function toggleServerEdit() {
    const input = document.getElementById('serverCountInput');
    const btn = document.getElementById('serverUpdateBtn');
    const cancelBtn = document.getElementById('serverCancelBtn');
    
    if (input.disabled) {
        // Store original value
        originalServerCount = input.value;
        
        // Switch to EDIT mode
        input.disabled = false;
        input.style.background = 'var(--card-bg)';
        input.focus();
        btn.innerText = 'Apply';
        if (cancelBtn) cancelBtn.style.display = 'block';
    } else {
        // Switch to SAVE mode (Apply)
        saveServerAllocation();
    }
}

function cancelServerEdit() {
    const input = document.getElementById('serverCountInput');
    const btn = document.getElementById('serverUpdateBtn');
    const cancelBtn = document.getElementById('serverCancelBtn');
    
    input.value = originalServerCount;
    input.disabled = true;
    input.style.background = 'var(--bg)';
    btn.innerText = 'Update';
    if (cancelBtn) cancelBtn.style.display = 'none';
}

async function loadServerConfig() {
    try {
        const res = await fetch(getApiUrl('get_server_config.php'));
        const result = await res.json();
        if (result.status === 'success') {
            const data = result.data;
            document.getElementById('serverCountInput').value = data.server_count;
            document.getElementById('serverLastUpdatedBy').innerText = data.last_updated_by || 'System';
            document.getElementById('serverLastUpdatedId').innerText = data.last_updated_id || 'N/A';
            document.getElementById('serverLastUpdatedAt').innerText = data.updated_at || '-';
        }
    } catch (e) {
        console.error('Failed to load server config', e);
    }
}

async function saveServerAllocation() {
    const input = document.getElementById('serverCountInput');
    const count = parseInt(input.value);
    if (isNaN(count) || count < 1 || count > 99) {
        alert('Please enter a valid server count between 1 and 99.');
        return;
    }

    const btn = document.getElementById('serverUpdateBtn');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Saving...';

    try {
        const res = await fetch(getApiUrl('update_server_config.php'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ server_count: count })
        });
        const result = await res.json();
        if (result.status === 'success') {
            // success
            input.disabled = true;
            input.style.background = 'var(--bg)';
            btn.innerText = 'Update';
            const cancelBtn = document.getElementById('serverCancelBtn');
            if (cancelBtn) cancelBtn.style.display = 'none';
            loadServerConfig(); // Refresh metadata
        } else {
            alert('Error: ' + result.message);
            btn.innerText = 'Apply'; // Keep in apply mode if error
        }
    } catch (e) {
        console.error('Failed to save server config', e);
        alert('An unexpected error occurred.');
        btn.innerText = 'Apply';
    } finally {
        btn.disabled = false;
    }
}

// --- Task Limit Management ---
function toggleTaskLimitsModal() {
    const modal = document.getElementById('taskLimitsModal');
    if (!modal) return;
    const isVisible = modal.style.display === 'flex';
    modal.style.display = isVisible ? 'none' : 'flex';
    if (!isVisible) {
        loadTaskLimits();
        resetLimitForm();
    }
}

function toggleLimitUserField() {
    const type = document.getElementById('limitType').value;
    const section = document.getElementById('limitUserSection');
    if (type === 'specific_user' || type === 'specific_user_monthly') {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
    }
}

async function loadTaskLimits() {
    try {
        const res = await fetch(getApiUrl('task_limit_api.php?action=list'));
        const result = await res.json();
        if (result.status === 'success') {
            renderTaskLimits(result.data);
        }
        loadTaskStats();
    } catch (e) {
        console.error('Failed to load task limits', e);
    }
}

async function loadTaskStats() {
    try {
        const res = await fetch(getApiUrl('task_limit_api.php?action=get_stats'));
        const result = await res.json();
        if (result.status === 'success') {
            document.getElementById('statDailyCount').innerText = result.data.daily;
            document.getElementById('statMonthlyCount').innerText = result.data.monthly;
            document.getElementById('statTotalCount').innerText = result.data.total;
        }
    } catch (e) { console.error(e); }
}

function renderTaskLimits(limits) {
    const tbody = document.getElementById('taskLimitTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (limits.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #94a3b8;">No limits configured.</td></tr>';
        return;
    }

    limits.forEach(limit => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border)';
        
        const typeLabels = {
            'daily': 'Daily (System)',
            'monthly': 'Monthly (System)',
            'total': 'Total (All Time)',
            'per_user': 'Per User (Daily)',
            'specific_user': 'Specific User (Daily)',
            'per_user_monthly': 'Per User (Monthly)',
            'specific_user_monthly': 'Specific User (Monthly)'
        };

        const isActive = limit.is_active == 1;
        const badgeBg = isActive ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)';
        const badgeText = isActive ? 'var(--success)' : '#ef4444';

        tr.innerHTML = `
            <td style="padding: 12px 15px; font-size: 0.85rem; font-weight: 600; color: var(--text);">${typeLabels[limit.limit_type] || limit.limit_type}</td>
            <td style="padding: 12px 15px; font-size: 0.85rem; color: var(--text-light);">${limit.user_id || '<span style="color: var(--secondary);">Global</span>'}</td>
            <td style="padding: 12px 15px; font-size: 0.85rem; text-align: center; font-weight: 700; color: var(--primary);">${limit.limit_value}</td>
            <td style="padding: 12px 15px; text-align: center;">
                <span style="padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; background: ${badgeBg}; color: ${badgeText};">
                    ${isActive ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td style="padding: 12px 15px; text-align: center;">
                <div style="display: flex; gap: 8px; justify-content: center;">
                    <button onclick='editTaskLimit(${JSON.stringify(limit)})' class="btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;">Edit</button>
                    <button onclick="deleteTaskLimit(${limit.id})" class="btn-danger" style="padding: 4px 8px; font-size: 0.75rem; background: #ef4444; border: none; color: white;">Del</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function editTaskLimit(limit) {
    document.getElementById('limitId').value = limit.id;
    document.getElementById('limitType').value = limit.limit_type;
    document.getElementById('limitUserId').value = limit.user_id || '';
    document.getElementById('limitValue').value = limit.limit_value;
    document.getElementById('limitIsActive').checked = limit.is_active == 1;
    toggleLimitUserField();
}

function resetLimitForm() {
    document.getElementById('limitId').value = '';
    document.getElementById('limitType').value = 'daily';
    document.getElementById('limitUserId').value = '';
    document.getElementById('limitValue').value = '';
    document.getElementById('limitIsActive').checked = true;
    toggleLimitUserField();
}

async function saveTaskLimit() {
    const data = {
        id: document.getElementById('limitId').value,
        limit_type: document.getElementById('limitType').value,
        user_id: document.getElementById('limitUserId').value.trim() || null,
        limit_value: parseInt(document.getElementById('limitValue').value),
        is_active: document.getElementById('limitIsActive').checked ? 1 : 0
    };

    if (data.limit_type === 'specific_user' && !data.user_id) {
        alert('User ID is required for Specific User limit.');
        return;
    }

    try {
        const res = await fetch(getApiUrl('task_limit_api.php?action=save'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.status === 'success') {
            loadTaskLimits();
            resetLimitForm();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        console.error('Failed to save task limit', e);
    }
}

async function deleteTaskLimit(id) {
    if (!confirm('Are you sure you want to delete this limit?')) return;
    try {
        const res = await fetch(getApiUrl('task_limit_api.php?action=delete'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.status === 'success') {
            loadTaskLimits();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        console.error('Failed to delete task limit', e);
    }
}

let userSearchTimeout = null;
async function searchUsersForLimit(q) {
    if (userSearchTimeout) clearTimeout(userSearchTimeout);
    const container = document.getElementById('userSuggestions');
    if (!q || q.length < 1) {
        container.style.display = 'none';
        return;
    }

    userSearchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(getApiUrl(`user_api.php?action=search&q=${q}`));
            const result = await res.json();
            if (result.status === 'success') {
                renderUserSuggestions(result.data);
            }
        } catch (e) { console.error(e); }
    }, 300);
}

function renderUserSuggestions(users) {
    const container = document.getElementById('userSuggestions');
    if (!container) return;
    container.innerHTML = '';
    
    if (users.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    users.forEach(user => {
        const div = document.createElement('div');
        div.style.padding = '8px 12px';
        div.style.cursor = 'pointer';
        div.style.fontSize = '0.85rem';
        div.style.borderBottom = '1px solid #f1f5f9';
        const fullName = user.full_name || 'N/A';
        const dept = user.department || 'N/A';
        div.innerHTML = `<strong>${fullName}</strong> | ${user.username} <span style="color:#64748b;">(${user.employee_id})</span> | <span style="color:#6366f1;">${dept}</span>`;
        div.onclick = () => {
            document.getElementById('limitUserId').value = user.username;
            container.style.display = 'none';
        };
        div.onmouseover = () => div.style.background = '#f8fafc';
        div.onmouseout = () => div.style.background = 'white';
        container.appendChild(div);
    });
}

// --- Theme & Zoom Logic ---
function toggleTheme() {
    const html = document.documentElement;
    const body = document.body;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeUI(newTheme === 'dark');
}

function updateThemeUI(isDark) {
    const toggle = document.getElementById('theme-toggle');
    if (toggle) toggle.checked = isDark;
}

let currentZoom = parseFloat(localStorage.getItem('zoom')) || 1.0;
function adjustZoom(delta) {
    currentZoom = Math.max(0.5, Math.min(1.5, currentZoom + delta));
    document.documentElement.style.setProperty('--zoom', currentZoom);
    const percent = Math.round(currentZoom * 100) + '%';
    const label = document.getElementById('zoom-percent');
    if (label) label.innerText = percent;
    localStorage.setItem('zoom', currentZoom);
}

// Initialize Theme & Zoom on load
document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.body.setAttribute('data-theme', savedTheme);
    updateThemeUI(savedTheme === 'dark');

    const savedZoom = parseFloat(localStorage.getItem('zoom')) || 1.0;
    currentZoom = savedZoom;
    document.documentElement.style.setProperty('--zoom', savedZoom);
    const label = document.getElementById('zoom-percent');
    if (label) label.innerText = Math.round(savedZoom * 100) + '%';
});

// --- User Activity & Tracking ---
let userActivityInterval = null;

window.toggleUserActivity = function() {
    const modal = document.getElementById('userActivityModal');
    if (!modal) return;
    const isOpening = modal.style.display !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    
    if (isOpening) {
        loadUserActivity();
        // Start real-time refresh every 5 seconds
        if (userActivityInterval) clearInterval(userActivityInterval);
        userActivityInterval = setInterval(loadUserActivity, 5000);
    } else {
        // Stop refresh when modal is closed
        if (userActivityInterval) {
            clearInterval(userActivityInterval);
            userActivityInterval = null;
        }
    }
};

window.loadUserActivity = async function() {
    const tbody = document.getElementById('userActivityBody');
    if (!tbody) return;

    // Only show loading on the first load
    if (tbody.innerHTML === '' || tbody.innerText.includes('Loading')) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-light);"><div class="loading-spinner" style="margin-bottom: 10px;"></div> Loading user activity...</td></tr>`;
    }

    try {
        const response = await fetch(getApiUrl(`user_activity_api.php?action=list&t=${Date.now()}`));
        const result = await response.json();

        if (result.status === 'success') {
            if (result.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-light);">No user activity data found.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            result.data.forEach(user => {
                const tr = document.createElement('tr');
                tr.style.transition = 'background 0.2s';
                
                // Status Badge
                const isOnline = user.online_status === 'online';
                const statusColor = isOnline ? '#10b981' : '#94a3b8';
                const statusBg = isOnline ? '#dcfce7' : '#f1f5f9';
                
                let sessionBadge = '';
                if (isOnline && user.session_count > 1) {
                    sessionBadge = `<span title="${user.session_count} devices active" style="margin-left: 5px; padding: 2px 6px; background: #3b82f6; color: white; border-radius: 10px; font-size: 10px; vertical-align: middle;">${user.session_count} devices</span>`;
                }

                const statusBadge = `<div><span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: ${statusBg}; color: ${statusColor}; text-transform: uppercase;">${user.online_status}</span>${sessionBadge}</div>`;

                // Time formatting
                let lastActiveStr = user.last_activity || 'Never';
                if (user.last_activity) {
                    const secs = parseInt(user.seconds_ago);
                    if (secs < 60) lastActiveStr = 'Just now';
                    else if (secs < 3600) lastActiveStr = Math.floor(secs / 60) + 'm ago';
                    else if (secs < 86400) lastActiveStr = Math.floor(secs / 3600) + 'h ago';
                    else {
                        const lastDate = new Date(user.last_activity);
                        lastActiveStr = lastDate.toLocaleDateString();
                    }
                }

                tr.innerHTML = `
                    <td style="padding: 15px; border-bottom: 1px solid var(--border);">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 600; color: var(--text);">${user.full_name || 'System User'}</span>
                            <span style="font-size: 0.75rem; color: var(--text-light);">${user.username} (${user.employee_id})</span>
                        </div>
                    </td>
                    <td style="padding: 15px; border-bottom: 1px solid var(--border); text-align: center;">${statusBadge}</td>
                    <td style="padding: 15px; border-bottom: 1px solid var(--border); text-align: center;">
                        <span style="font-size: 0.8rem; color: var(--text-light);">${user.role}</span>
                    </td>
                    <td style="padding: 15px; border-bottom: 1px solid var(--border); color: var(--text); font-size: 0.9rem;">${lastActiveStr}</td>
                    <td style="padding: 15px; border-bottom: 1px solid var(--border); font-family: monospace; color: var(--text-light); font-size: 0.85rem;">${user.last_ip || 'N/A'}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 40px; color: #ef4444;">${result.message}</td></tr>`;
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 40px; color: #ef4444;">Failed to connect to activity server.</td></tr>`;
    }
};

// --- File Upload Dynamic Count ---
function setupDynamicPdfCount() {
    function updateCount(e) {
        const files = e.target.files;
        let pdfCount = 0;
        for (let i = 0; i < files.length; i++) {
            if (files[i].name.toLowerCase().endsWith('.pdf')) {
                pdfCount++;
            }
        }
        const countSpan = document.getElementById('cvValidCount');
        if (countSpan) {
            if (files.length > 0) {
                countSpan.style.display = 'block';
                countSpan.innerText = `✓ Found ${pdfCount} valid PDF(s) out of ${files.length} file(s).`;
                if (pdfCount === 0) {
                    countSpan.style.color = '#ef4444'; // Red if no PDFs
                    countSpan.innerText = `⚠ Found 0 PDF files. Please select a different folder.`;
                } else {
                    countSpan.style.color = '#10b981'; // Green otherwise
                }
            } else {
                countSpan.style.display = 'none';
            }
        }
    }

    const cvFilesInput = document.getElementById('cvUploadFiles');
    const cvFolderInput = document.getElementById('cvUploadFolder');
    if (cvFilesInput) cvFilesInput.addEventListener('change', updateCount);
    if (cvFolderInput) cvFolderInput.addEventListener('change', updateCount);
}

// --- Source Management ---
window.toggleSourceManager = function() {
    const modal = document.getElementById('sourceManagerModal');
    if (!modal) return;
    const isOpening = modal.style.display !== 'flex';
    modal.style.display = isOpening ? 'flex' : 'none';
    if (isOpening) window.loadSources();
};

window.loadSources = async function() {
    const list = document.getElementById('sourceList');
    if (!list) return;
    list.innerHTML = '<div style="padding:10px;text-align:center;">Loading...</div>';
    try {
        const res = await fetch(getApiUrl('source_api.php?action=list'));
        const result = await res.json();
        if (result.status === 'success') {
            list.innerHTML = result.data.map(s => `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--bg);border-radius:8px;border:1px solid var(--border);">
                    <div style="font-weight:600;">${s.source_name}</div>
                    <button onclick="deleteSource(${s.id})" style="background:none;border:none;color:#ef4444;cursor:pointer;">
                        <span class="material-icons" style="font-size:18px;">delete</span>
                    </button>
                </div>
            `).join('');
            // Also update the Add Task modal if it's open
            if (document.getElementById('sourceOptionsContainer')) {
                window.renderSourceOptions(result.data);
            }
        }
    } catch(e) { console.error(e); }
};

window.addSource = async function() {
    const input = document.getElementById('newSourceInput');
    const name = input.value.trim();
    if (!name) return;
    try {
        const res = await fetch(getApiUrl('source_api.php?action=add'), {
            method: 'POST',
            body: JSON.stringify({ source_name: name })
        });
        const result = await res.json();
        if (result.status === 'success') {
            input.value = '';
            window.loadSources();
        } else {
            alert(result.message);
        }
    } catch(e) { console.error(e); }
};

window.deleteSource = async function(id) {
    if (!confirm('Are you sure you want to delete this source?')) return;
    try {
        const res = await fetch(getApiUrl(`source_api.php?action=delete&id=${id}`));
        const result = await res.json();
        if (result.status === 'success') {
            window.loadSources();
        } else {
            alert(result.message);
        }
    } catch(e) { console.error(e); }
};

window.loadSourcesForAddTask = async function() {
    try {
        const res = await fetch(getApiUrl('source_api.php?action=list'));
        const result = await res.json();
        if (result.status === 'success') {
            window.renderSourceOptions(result.data);
        }
    } catch(e) { console.error(e); }
};

window.renderSourceOptions = function(sources) {
    const container = document.getElementById('sourceOptionsContainer');
    if (!container) return;
    
    container.innerHTML = sources.map((s, index) => `
        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
            <input type="radio" name="taskSource" value="${s.source_name.toLowerCase()}" ${index === 0 ? 'checked' : ''} onchange="toggleUploadOptions()"> ${s.source_name}
        </label>
    `).join('');
    
    // Trigger the upload options logic once
    window.toggleUploadOptions();
};

// DOMContentLoaded and click listeners continue here...

document.addEventListener('DOMContentLoaded', () => {
    setupDynamicPdfCount();
    // Pre-load departments for autocomplete on all pages (Not for public mode)
    if (!window.isPublicMode && typeof window.loadEmployeeFilters === 'function') {
        window.loadEmployeeFilters();
    }
    
    // Initialize task view mode based on permissions
    initTaskViewMode();
});

function initTaskViewMode() {
    let perms = window.currentUserPermissions;
    if (typeof perms === 'string') {
        try { perms = JSON.parse(perms); } catch(e) { perms = {}; }
    }
    if (!perms) perms = {};

    const isRoot = (window.currentUser?.id && window.currentUser.id === window.rootAdminId);

    const hasAll = isRoot || 
                   perms['view_all_task'] === true || perms['view_all_task'] === "true" || 
                   perms['view_all_task'] === 1 || perms['view_all_task'] === "1" || perms['view_all_task'] === "on";
    const hasMy = isRoot || 
                  perms['view_my_task'] === true || perms['view_my_task'] === "true" || 
                  perms['view_my_task'] === 1 || perms['view_my_task'] === "1" || perms['view_my_task'] === "on";

    if (hasMy) {
        window.setTaskViewMode('my');
    } else {
        window.setTaskViewMode('all');
    }
}

// Close task suggestions on click outside
window.addEventListener('click', function(e) {
    const deptSugg = document.getElementById('deptSuggestionsTask');
    const deptInput = document.getElementById('publicDept');
    if (deptSugg && !deptSugg.contains(e.target) && deptInput && !deptInput.contains(e.target)) {
        deptSugg.style.display = 'none';
    }

    const concernSugg = document.getElementById('concernSuggestionsTask');
    const concernInput = document.getElementById('publicConcern');
    if (concernSugg && !concernSugg.contains(e.target) && concernInput && !concernInput.contains(e.target)) {
        concernSugg.style.display = 'none';
    }
});
