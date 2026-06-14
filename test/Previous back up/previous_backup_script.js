// --- Global Utility Functions (Defined First) ---

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
        item.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 15px;background:#f8fafc;border:1px solid var(--border);border-radius:10px;';
        item.innerHTML = `
            <span class="material-icons" style="color:#94a3b8;font-size:18px;">drag_indicator</span>
            <span style="flex:1;font-weight:600;font-size:0.9rem;">${s.status_name}</span>
            <button onclick="reorderStatus(${s.id},-1)" style="background:none;border:none;cursor:pointer;padding:2px;" title="Move Up"><span class="material-icons" style="font-size:18px;color:#64748b;">arrow_upward</span></button>
            <button onclick="reorderStatus(${s.id},1)" style="background:none;border:none;cursor:pointer;padding:2px;" title="Move Down"><span class="material-icons" style="font-size:18px;color:#64748b;">arrow_downward</span></button>
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

function toggleProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        const isOpening = modal.style.display !== 'flex';
        modal.style.display = isOpening ? 'flex' : 'none';
        if (isOpening) loadProfileData();
    }
}

async function loadProfileData() {
    const detailsDiv = document.getElementById('profileDetails');
    try {
        const response = await fetch(getApiUrl('user_api.php?action=get_profile'));
        const result = await response.json();
        if (result.status === 'success') {
            const d = result.data;
            const picEl = document.getElementById('profileModalPic');
            const fallbackEl = document.getElementById('profileModalFallback');
            
            if (d.profile_pic) {
                if (picEl) {
                    picEl.src = d.profile_pic;
                    picEl.style.display = 'inline-block';
                }
                if (fallbackEl) fallbackEl.style.display = 'none';
            } else {
                if (picEl) picEl.style.display = 'none';
                if (fallbackEl) fallbackEl.style.display = 'inline-flex';
            }

            detailsDiv.innerHTML = `
                <div style="grid-column: span 2; width: 100%; overflow-x: hidden; text-align: left;">
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0 10px; font-size: 0.95rem; color: #334155;">
                        <tbody>
                            <tr>
                                <th style="width: 15%; color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">Full Name:</th>
                                <td style="width: 35%; padding: 0 5px; text-align: left; white-space: nowrap;">${d.full_name || 'N/A'}</td>
                                <th style="width: 15%; color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">Emp ID:</th>
                                <td style="width: 35%; padding: 0 5px; text-align: left; white-space: nowrap;">${d.employee_id || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th style="color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">Designation:</th>
                                <td style="padding: 0 5px; text-align: left; white-space: nowrap;">${d.designation || 'N/A'}</td>
                                <th style="color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">Department:</th>
                                <td style="padding: 0 5px; text-align: left; white-space: nowrap;">${d.department || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th style="color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">Email:</th>
                                <td style="padding: 0 5px; text-align: left; white-space: nowrap;">${d.email || 'N/A'}</td>
                                <th style="color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">IP No:</th>
                                <td style="padding: 0 5px; text-align: left; white-space: nowrap;">${d.ip_no || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th style="color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">Mobile:</th>
                                <td style="padding: 0 5px; text-align: left; white-space: nowrap;">${d.mobile_no || 'N/A'}</td>
                                <th style="color: #0f172a; padding: 0 5px; vertical-align: top; text-align: left; white-space: nowrap;">Floor:</th>
                                <td style="padding: 0 5px; text-align: left; white-space: nowrap;">${d.floor || 'N/A'}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            detailsDiv.innerHTML = `<div style="grid-column: span 2; text-align: center; color: #dc2626; padding: 20px;">
                <span class="material-icons" style="font-size: 32px; display: block; margin-bottom: 10px;">error_outline</span>
                ${result.message || 'Error loading profile'}
            </div>`;
        }
    } catch (e) { 
        console.error(e); 
        detailsDiv.innerHTML = `<div style="grid-column: span 2; text-align: center; color: #dc2626;">Failed to connect to server.</div>`;
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
            const picEl = document.getElementById('profileModalPic');
            if (picEl) {
                picEl.src = getMediaUrl(result.path);
                picEl.style.display = 'inline-block';
                const fallbackEl = document.getElementById('profileModalFallback');
                if (fallbackEl) fallbackEl.style.display = 'none';
            }
            
            const headerPicEl = document.getElementById('headerProfilePic');
            if (headerPicEl) {
                headerPicEl.src = getMediaUrl(result.path);
                headerPicEl.style.display = 'inline-block';
                const headerFallbackEl = document.getElementById('headerProfileFallback');
                if (headerFallbackEl) headerFallbackEl.style.display = 'none';
            }
            alert('Profile picture updated!');
        } else {
            alert(result.message);
        }
    } catch (e) { console.error(e); }
}

function toggleAddTask() {
    const modal = document.getElementById('addTaskModal');
    if (modal) modal.style.display = (modal.style.display === 'flex') ? 'none' : 'flex';
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

async function generateJdId() {
    const icon = document.querySelector('.material-icons[title="Generate Unique ID"]');
    if (icon) { icon.style.opacity = '0.5'; icon.style.pointerEvents = 'none'; }
    try {
        const response = await fetch(getApiUrl('generate_jd_id.php'));
        const result = await response.json();
        if (result.status === 'success') document.getElementById('publicJdId').value = result.jd_id;
    } catch (e) { console.error(e); }
    finally { if (icon) { icon.style.opacity = '1'; icon.style.pointerEvents = 'auto'; } }
}

// --- User Management Functions ---
function toggleUserManager() {
    const modal = document.getElementById('userManagerModal');
    if (modal) {
        const isOpening = modal.style.display !== 'flex';
        modal.style.display = isOpening ? 'flex' : 'none';
        if (isOpening) {
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) addUserForm.style.display = 'none';
            updateUserBtnText(false);
            loadUsers();
            loadEmployeeRefs();
        }
    }
}

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
        const response = await fetch(getApiUrl('employee_api.php?action=list'));
        const result = await response.json();
        if (result.status === 'success') {
            allEmployees = result.data;
        }
    } catch (e) { console.error(e); }
}

function searchEmployees(query) {
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

    const filtered = allEmployees.filter(emp => 
        emp.employee_id.toLowerCase().includes(query.toLowerCase()) || 
        emp.full_name.toLowerCase().includes(query.toLowerCase())
    ).slice(0, 10); // Limit to top 10 results

    if (filtered.length > 0) {
        suggestions.innerHTML = filtered.map(emp => `
            <div class="suggestion-item" onclick="selectEmployee('${emp.employee_id}', '${emp.full_name.replace(/'/g, "\\'")}')">
                <span class="suggestion-name">${emp.full_name}</span>
                <span class="suggestion-id">ID: ${emp.employee_id}</span>
            </div>
        `).join('');
        suggestions.style.display = 'block';
    } else {
        suggestions.innerHTML = '<div style="padding: 12px 15px; color: #64748b; font-size: 0.85rem; background: white;">No matching employee found</div>';
        suggestions.style.display = 'block';
    }
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
    try {
        const response = await fetch(getApiUrl('user_api.php?action=list'));
        const result = await response.json();
        if (result.status === 'success') {
            tbody.innerHTML = '';
            result.data.forEach(user => {
                const tr = document.createElement('tr');
                const isPending = user.status === 'pending';
                const statusBadge = `<span class="badge ${user.status === 'active' ? 'badge-success' : (isPending ? 'badge-warning' : 'badge-danger')}">${user.status}</span>`;
                
                tr.innerHTML = `
                    <td style="text-align: center;">
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <span style="font-weight: 600;">${user.full_name || 'In-Progress Reg'}</span>
                            <span style="font-size: 0.75rem; color: #64748b;">ID: ${user.employee_id}</span>
                        </div>
                    </td>
                    <td style="text-align:center;"><span style="text-transform: capitalize; background:#f1f5f9; padding:2px 8px; border-radius:4px;">${user.role}</span></td>
                    <td style="text-align:center;">${statusBadge}</td>
                    <td style="text-align:center;"><small>${new Date(user.created_at).toLocaleDateString()}</small></td>
                    <td style="text-align: center; display: flex; gap: 5px; justify-content: center;">
                        ${isPending ? `
                            <button onclick="approveUser(${user.id})" class="btn-primary" style="padding: 5px 10px; background: #10b981; border:none; font-size: 0.75rem;" title="Approve Registration">Approve</button>
                        ` : ''}
                        <button onclick="updateUserStatus(${user.id}, '${user.status === 'active' ? 'blocked' : 'active'}')" class="btn-secondary" style="padding: 5px 10px; font-size: 0.75rem;">
                            ${user.status === 'active' ? 'Block' : 'Unblock'}
                        </button>
                        <button onclick="deleteUser(${user.id})" class="btn-danger" style="padding: 5px 10px; background: #ef4444; border:none; color:white; font-size: 0.75rem;">Delete</button>
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
            alert('User created successfully');
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
function toggleEmployeeManager() {
    const modal = document.getElementById('employeeManagerModal');
    if (modal) {
        modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        if (modal.style.display === 'flex') loadEmployees();
    }
}

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
    if (!tbody) return;
    try {
        const response = await fetch(getApiUrl('employee_api.php?action=list'));
        const result = await response.json();
        if (result.status === 'success') {
            currentEmployeeData = result.data;
            tbody.innerHTML = '';
            result.data.forEach(emp => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align: center;">
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <span style="font-weight: 600;">${emp.full_name}</span>
                            <span style="font-size: 0.75rem; color: #64748b;">ID: ${emp.employee_id}</span>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <small>${emp.designation} | ${emp.department}</small><br>
                        <small>Floor: ${emp.floor} | IP: ${emp.ip_no}</small>
                    </td>
                    <td style="text-align: center;">
                        <small>${emp.email}</small><br>
                        <small>${emp.mobile_no}</small>
                    </td>
                    <td style="text-align: center; display: flex; gap: 5px; justify-content: center; align-items: center;">
                        <button onclick="editEmployee('${emp.employee_id}')" class="btn-secondary" style="padding: 5px 10px; font-size: 0.75rem;">Edit</button>
                        <button onclick="deleteEmployee('${emp.employee_id}')" class="btn-danger" style="padding: 5px 10px; background: #ef4444; border:none; color:white; font-size: 0.75rem;">Delete</button>
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
        ip_no: document.getElementById('empIpNo').value,
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
    document.getElementById('empIpNo').value = emp.ip_no;
    document.getElementById('empFloor').value = emp.floor;
}

function toggleChangePass() {
    const modal = document.getElementById('changePassModal');
    if (modal) modal.style.display = (modal.style.display === 'flex') ? 'none' : 'flex';
}

// Basic API Path Helper
const getApiUrl = (endpoint) => {
    const isViewPage = window.location.pathname.includes('/view/');
    return isViewPage ? `../api/${endpoint}` : `api/${endpoint}`;
};

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

async function loadCandidates(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput')?.value.trim() || '';
    const shortlisted = document.getElementById('shortlistedFilter')?.value || '';
    const confirmation = document.getElementById('confirmationFilter')?.value || '';

    let url = `${getApiUrl('get_candidates.php')}?page=${page}&search=${encodeURIComponent(search)}&shortlisted=${shortlisted}&confirmation=${confirmation}&sort_by=${currentSortBy}&sort_order=${currentSortOrder}`;

    if (selectedJdId) {
        url += `&jd_id=${encodeURIComponent(selectedJdId)}`;
    } else if (selectedJobTitle) {
        url += `&job_title=${encodeURIComponent(selectedJobTitle)}`;
    }

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const result = await response.json();

        if (result.status === 'success') {
            renderTable(result.data);
            renderPagination(result.pagination);
            updateSortUI();
            updateDashboardTitle(result.data);

            // Toggle Export button visibility
            const exportBtn = document.getElementById('exportCsv');
            if (exportBtn) {
                exportBtn.style.display = (result.data && result.data.length > 0) ? 'flex' : 'none';
            }
        }
    } catch (error) {
        console.error('Error loading candidates:', error);
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

function renderTable(data) {
    const tbody = document.getElementById('candidateBody');
    if (!tbody) return;

    tbody.innerHTML = '';
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="20" style="text-align:center; padding: 20px;">No candidates found.</td></tr>';
        return;
    }

    data.forEach(candidate => {
        const tr = document.createElement('tr');
        tr.id = `row-${candidate.id}`;
        tr.innerHTML = `
            <td>${candidate.id}</td>
            <td><strong>${candidate.name}</strong><br><small>${candidate.email_id}</small></td>
            <td><small>${candidate.phone || '-'}</small></td>
            <td><small>${candidate.location || '-'}</small></td>
            <td style="white-space: nowrap;"><small>${candidate.date_of_birth ? new Date(candidate.date_of_birth).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'}</small></td>
            <td>${candidate.organization || '-'}</td>
            <td><div class="col-text" title="${candidate.education || ''}">${candidate.education || '-'}</div></td>
            <td><div class="col-text" title="${candidate.educational_institute || ''}">${candidate.educational_institute || '-'}</div></td>
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
            <td style="text-align:center;">
                <input type="checkbox" class="status-checkbox" data-field="shortlisted" ${candidate.shortlisted == 1 ? 'checked' : ''} disabled
                    onchange="updateCandidateStatus(${candidate.id}, 'shortlisted', this.checked)">
            </td>
            <td style="text-align:center;">
                <input type="checkbox" class="status-checkbox" data-field="confirmation" ${candidate.confirmation == 1 ? 'checked' : ''} disabled
                    onchange="updateCandidateStatus(${candidate.id}, 'confirmation', this.checked)">
            </td>
            <td><small>${new Date(candidate.created_at).toLocaleDateString()}</small></td>
            <td style="text-align:center;">
                <button class="btn-primary edit-toggle-btn" onclick="toggleRowEdit(${candidate.id})" style="width:36px; height:36px; padding: 0; display: flex; align-items: center; justify-content: center; background: var(--secondary);" title="Edit Status">
                    <span class="material-icons" style="font-size: 18px;">edit</span>
                </button>
            </td>
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
            icon.innerText = currentSortOrder === 'ASC' ? '↑' : '↓';
        } else {
            icon.innerText = '↕';
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

let allJobsData = [];

async function loadJobList() {
    const container = document.getElementById('jobList');
    if (!container) return;

    try {
        const response = await fetch(getApiUrl('get_jobs.php'));
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            allJobsData = result.data;
            applyJobFilters();
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

function applyJobFilters() {
    const jdFilter = (document.getElementById('filterJdId')?.value || '').toLowerCase().trim();
    const statusFilter = document.getElementById('filterStatus')?.value || 'all';
    const userFilter = (document.getElementById('filterCreatedBy')?.value || '').toLowerCase().trim();

    console.log(`Filtering: JD="${jdFilter}", Status="${statusFilter}", User="${userFilter}"`);

    const filtered = allJobsData.filter(job => {
        const jd = String(job.jd_id || "").toLowerCase();
        const user = String(job.created_by || 'System').toLowerCase();

        const matchesJd = !jdFilter || jd.includes(jdFilter);
        const matchesUser = !userFilter || user.includes(userFilter);

        let matchesStatus = true;
        if (statusFilter !== 'all') {
            const totalCandidates = parseInt(job.total_candidate || 0);
            const rawStatus = (job.status || '').toLowerCase().trim();
            let derivedStatus = 'Pending';

            if (rawStatus === 'completed' || rawStatus === 'completd') derivedStatus = 'Completed';
            else if (totalCandidates > 0) derivedStatus = 'In Progress';

            matchesStatus = (derivedStatus.toLowerCase() === statusFilter.toLowerCase());
        }

        return matchesJd && matchesUser && matchesStatus;
    });

    renderJobList(filtered);
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

    container.innerHTML = '';

    if (data.length === 0) {
        container.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 40px; color: #888; white-space: nowrap;">No jobs found matching filters.</td></tr>';
        return;
    }

    data.forEach((job, index) => {
        const tr = document.createElement('tr');
        const dashboardUrl = `view/dashboard.php?jd_id=${encodeURIComponent(job.jd_id)}&job_title=${encodeURIComponent(job.job_title)}`;
        const jobTitleHtml = `<a href="${dashboardUrl}" style="text-decoration: none; color: var(--primary); font-weight: 600;">${job.job_title}</a>`;

        // Build status cell - always static badge, same for all users
        const currentStatus = (job.status || 'Pending').trim();
        const lc = currentStatus.toLowerCase();
        let statusClass = 'status-pending';
        if (lc === 'completed') statusClass = 'status-completed';
        else if (lc === 'screening' || lc === 'downloading') statusClass = 'status-progress';
        const statusCellHtml = `<span class="badge-status ${statusClass}">${currentStatus}</span>`;

        tr.innerHTML = `
            <td style="text-align: center;">${index + 1}</td>
            <td style="text-align: left; padding-left: 20px;">
                ${jobTitleHtml}
            </td>
            <td style="text-align: center;"><span class="badge-jd">${job.jd_id || 'N/A'}</span></td>
            <td style="text-align: center;">${statusCellHtml}</td>
            <td style="text-align: center;"><span style="font-weight: 600; color: var(--primary);">${job.total_candidate || 0}</span></td>
            <td style="text-align: center; font-size: 0.9rem; font-weight: 500;">${job.creator_name || job.created_by || 'System'}</td>
            <td style="text-align: center; color: #64748b; font-size: 0.8rem;">${formatDateTime(job.created_at)}</td>
            <td style="text-align: center; font-weight: 700; color: #475569;">${job.task_no || 'N/A'}</td>
        `;
        container.appendChild(tr);
    });
}

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
            
            const jobTitle = jobTitleInput ? jobTitleInput.value.trim() : "";
            const jdId = jdIdInput ? jdIdInput.value.trim() : "";

            if (!jobTitle || !jdId) {
                alert('Please fill in both Job Title and JD ID');
                return;
            }

            addJobBtn.innerText = 'Creating...';
            addJobBtn.disabled = true;

            try {
                const response = await fetch(getApiUrl('insert_job.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        job_title: jobTitle,
                        jd_id: jdId
                    })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    // Check if there's a mandatory requirement
                    const aiPromptArea = document.getElementById('aiPrompt');
                    if (aiPromptArea && aiPromptArea.value.trim()) {
                        addJobBtn.innerText = 'Applying Req...';
                        try {
                            const promptResponse = await fetch(getApiUrl('prompt_api.php'), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ prompt_text: aiPromptArea.value.trim() })
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
                    toggleAddTask(); // Close modal
                    loadJobList(); // Refresh the list
                } else {
                    alert('Error: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error adding job:', error);
                alert('Connection error while adding job.');
            } finally {
                addJobBtn.innerText = 'Create';
                addJobBtn.disabled = false;
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

        loadStatuses().then(() => {
            if (document.getElementById('jobList')) loadJobList();
            if (document.getElementById('candidateBody')) loadCandidates(1);
        });
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
        
        let url = `${getApiUrl('export_candidates.php')}?search=${encodeURIComponent(search)}&shortlisted=${shortlisted}&confirmation=${confirmation}`;
        
        if (selectedJdId) {
            url += `&jd_id=${encodeURIComponent(selectedJdId)}`;
        } else if (selectedJobTitle) {
            url += `&job_title=${encodeURIComponent(selectedJobTitle)}`;
        }
        
        window.location.href = url;
    });

    // Auto-refresh logic (Every 10 seconds)
    let refreshInProgress = false;
    setInterval(async () => {
        if (refreshInProgress) return;

        // Refresh Job List if on index
        if (document.getElementById('jobList')) {
            refreshInProgress = true;
            try {
                await loadJobList();
            } finally {
                refreshInProgress = false;
            }
        }

        // Refresh Dashboard if on dashboard and not manual sorting/searching recently
        if (document.getElementById('candidateBody')) {
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
    }, 10000);
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
    if (projField) projField.value = '';
}

let currentRpaSortField = 'project';
let currentRpaSortOrder = 'asc';
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
        let valA = (a[currentRpaSortField] || '').toLowerCase();
        let valB = (b[currentRpaSortField] || '').toLowerCase();
        if (valA < valB) return currentRpaSortOrder === 'asc' ? -1 : 1;
        if (valA > valB) return currentRpaSortOrder === 'asc' ? 1 : -1;
        return 0;
    });

    if (filteredData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #94a3b8;">No matching configurations found.</td></tr>`;
        return;
    }

    filteredData.forEach(conf => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #e2e8f0';
        const confEncoded = btoa(unescape(encodeURIComponent(JSON.stringify(conf))));
        tr.innerHTML = `
            <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 12px; font-size: 1rem;">${conf.project}</td>
            <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 12px; font-size: 1rem; color: #64748b;">${conf.category || 'General'}</td>
            <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 12px; font-size: 1.05rem;"><code style="font-weight:600;">${conf.key}</code></td>
            <td style="border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 12px; font-size: 1rem;"><div style="max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${conf.value?.replace(/"/g, '&quot;')}">${conf.value || '-'}</div></td>
            <td style="border-bottom: 1px solid #e2e8f0; text-align: center; padding: 12px;">
                <div style="display: flex; gap: 8px; justify-content: center;">
                    <button onclick="editRpaConfigFromBase64('${confEncoded}')" class="btn-secondary" style="padding: 6px 12px; font-size: 0.9rem; min-width: 60px;">Edit</button>
                    <button onclick="deleteRpaConfig(${conf.id})" class="btn-danger" style="padding: 6px 12px; background: #ef4444; border:none; color:white; font-size: 0.9rem; min-width: 60px;">Delete</button>
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
}

async function saveRpaConfig() {
    const data = {
        id: document.getElementById('rpaId').value,
        key: document.getElementById('rpaKey').value.trim(),
        value: document.getElementById('rpaValue').value.trim(),
        category: document.getElementById('rpaCategory').value.trim(),
        project: document.getElementById('rpaProject').value.trim()
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