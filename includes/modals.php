<?php if (!defined('MODALS_INCLUDED')) define('MODALS_INCLUDED', true); 
$path_prefix = (strpos($_SERVER['PHP_SELF'], '/view/') !== false) ? '../' : '';
?>

    <!-- Redesigned Profile Modal -->
    <div id="profileModal" class="modal-overlay">
        <div class="modal-content" style="background: var(--card-bg); border-radius: 24px; width: 100%; max-width: 850px; overflow: hidden; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: row; min-height: 500px; border: 1px solid var(--border);">
            
            <!-- Left Side: Profile Hero -->
            <div style="width: 300px; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); padding: 40px 30px; display: flex; flex-direction: column; align-items: center; color: white; position: relative; border-right: 1px solid rgba(255,255,255,0.05);">
                <div style="position: relative; margin-bottom: 25px;">
                    <div style="width: 150px; height: 150px; border-radius: 50%; padding: 5px; background: rgba(255,255,255,0.2); backdrop-filter: blur(4px);">
                        <img id="profileModalPic" src="<?php echo $path_prefix . ($_SESSION['profile_pic'] ?? ''); ?>" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; background: var(--bg); border: 4px solid rgba(255,255,255,0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.2); <?php echo empty($_SESSION['profile_pic']) ? 'display:none;' : ''; ?>">
                        <div id="profileModalFallback" style="width: 100%; height: 100%; border-radius: 50%; background: var(--bg); display: <?php echo !empty($_SESSION['profile_pic']) ? 'none' : 'flex'; ?>; align-items: center; justify-content: center; border: 4px solid rgba(255,255,255,0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
                            <span class="material-icons" style="font-size: 80px; color: var(--text-light);">person</span>
                        </div>
                    </div>
                    <label for="profileUpload" style="position: absolute; bottom: 5px; right: 5px; width: 42px; height: 42px; background: var(--card-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.15); transition: transform 0.2s; border: 2px solid var(--border);">
                        <span class="material-icons" style="font-size: 22px;">camera_alt</span>
                    </label>
                    <input type="file" id="profileUpload" style="display: none;" accept="image/*" onchange="uploadProfilePic()">
                </div>
                
                <h2 id="profileModalName" style="margin: 0; font-size: 1.5rem; font-weight: 700; text-align: center; line-height: 1.2;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></h2>
                <div id="profileModalRoleBadge" style="margin-top: 10px; padding: 4px 12px; background: rgba(255,255,255,0.2); border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo ucfirst($_SESSION['role']); ?></div>
                
                <div style="margin-top: auto; width: 100%; padding-top: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; margin-bottom: 12px; opacity: 0.9;">
                        <span class="material-icons" style="font-size: 18px;">fingerprint</span>
                        <span>Employee ID: <strong id="profileModalEmpId"><?php echo $_SESSION['employee_id'] ?? 'N/A'; ?></strong></span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Details -->
            <div style="flex: 1; padding: 40px; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.25rem; color: var(--text); font-weight: 700;">Account Details</h3>
                        <p style="margin: 5px 0 0 0; color: var(--text-light); font-size: 0.9rem;">View and manage your professional information.</p>
                    </div>
                    <span class="material-icons" onclick="toggleProfileModal()" style="cursor: pointer; color: var(--text-light); transition: color 0.2s; padding: 8px; border-radius: 50%; background: var(--bg);">close</span>
                </div>
                
                <div id="profileDetailsGrid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <!-- Details will be injected here -->
                    <div style="grid-column: span 2; text-align: center; padding: 50px;">
                        <div class="loading-spinner" style="margin: 0 auto 15px auto;"></div>
                        <p style="color: #64748b;">Fetching your profile...</p>
                    </div>
                </div>
                
                <div style="margin-top: auto; padding-top: 30px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
                    <button onclick="openChangePassFromProfile();" class="btn-secondary" style="display: flex; align-items: center; gap: 8px; padding: 10px 24px; font-weight: 600;">
                        <span class="material-icons" style="font-size: 18px;">lock</span>
                        Change Password
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div id="changePassModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; background: var(--card-bg); border: 1px solid var(--border);">
            <div class="modal-header" style="background: var(--bg); border-bottom: 1px solid var(--border);">
                <h3 style="margin: 0; color: var(--text);">Change Password</h3>
                <span class="material-icons close-modal" onclick="toggleChangePass()" style="color: var(--text-light);">close</span>
            </div>
            <div class="modal-body">
                <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                    </div>
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">New Password</label>
                        <input type="password" name="new_password" placeholder="Enter new password" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 5px;">
                        <button type="button" onclick="toggleChangePass()" class="btn-secondary" style="padding: 10px 20px;">Cancel</button>
                        <button type="submit" class="btn-primary" style="padding: 10px 25px;">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <?php if (has_permission('manage_users')): ?>
    <!-- User Management Modal -->
    <div id="userManagerModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1400px; width: 95%; height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="background: var(--bg); border-bottom: 1px solid var(--border);">
                <h3 style="display:flex;align-items:center;gap:10px; margin: 0; color: var(--text);"><span class="material-icons" style="color: var(--primary);">manage_accounts</span> System Users</h3>
                <span class="material-icons close-modal" onclick="toggleUserManager()" style="color: var(--text-light);">close</span>
            </div>
            <div class="modal-body" style="flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; padding: 12px 15px;">
                <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 12px; flex-shrink: 0;">
                    <button id="toggleUserFormBtn" onclick="toggleAddUserForm()" class="btn-primary" style="padding: 6px 15px; background: #10b981; display: flex; align-items: center; gap: 8px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);">
                        <span class="material-icons" style="font-size: 18px;">add</span>
                        <span id="userBtnText">Create New User</span>
                    </button>
                </div>
                <!-- Form section (hidden) -->
                <div id="addUserForm" style="display: none; margin-bottom: 20px; padding: 15px; background: var(--bg); border-radius: 12px; border: 1px solid var(--border); flex-shrink: 0;">
                     <div style="display: flex; gap: 12px; align-items: flex-end;">
                        <div class="form-group" style="flex: 2; position: relative; min-width: 0;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 5px; text-transform: uppercase;">Employee Reference</label>
                            <input type="text" id="userEmpSearch" autocomplete="off" placeholder="Emp ID or Name..." 
                                style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.85rem;" 
                                oninput="searchEmployees(this.value)">
                            <input type="hidden" id="userEmpRef">
                            <div id="empSuggestions" class="suggestions-container" style="display: none; position: absolute; z-index: 1000; width: 100%; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); color: var(--text);"></div>
                        </div>
                        <div class="form-group" style="flex: 1.2; min-width: 0;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 5px; text-transform: uppercase;">Role</label>
                            <select id="newRole" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.85rem;">
                                <option value="user">Standard User</option>
                                <option value="sub-admin">Sub Admin</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 0;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 5px; text-transform: uppercase;">Password</label>
                            <input type="password" id="newPassword" placeholder="Min 6 chars" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.85rem;">
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 0;">
                            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 5px; text-transform: uppercase;">Confirm</label>
                            <input type="password" id="newConfirmPassword" placeholder="Retype" style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.85rem;">
                        </div>
                        <div style="display: flex; gap: 8px; flex-shrink: 0;">
                            <button id="userActionBtn" onclick="createUser()" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem; font-weight: 600;">Create</button>
                            <button onclick="toggleAddUserForm()" class="btn-secondary" style="padding: 8px 16px; font-size: 0.85rem; font-weight: 600;">Cancel</button>
                        </div>
                    </div>
                </div>
                <!-- Search box just before table -->
                <div style="margin-bottom: 12px; position: relative; max-width: 100%; flex-shrink: 0;">
                    <span class="material-icons" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 18px; color: var(--text-light);">search</span>
                    <input type="text" id="userSearchInput" oninput="loadUsers()" placeholder="Search by Name, Username or ID..." style="width: 100%; padding: 6px 10px 6px 40px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; background: var(--bg);">
                </div>

                <div class="table-container" style="flex-grow: 1; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; background: var(--card-bg); min-height: 0;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 10px; padding-left: 20px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Name & Username</th>
                                <th style="text-align: left; padding: 10px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Designation</th>
                                <th style="text-align: left; padding: 10px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Department</th>
                                <th style="text-align: center; padding: 10px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">IP / Phone</th>
                                <th style="text-align: center; padding: 10px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Role</th>
                                <th style="text-align: center; padding: 10px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Status</th>
                                <th style="text-align: center; padding: 10px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Joined</th>
                                <th style="text-align: center; padding: 10px; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="userListBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('manage_employees')): ?>
    <!-- Employee Management Modal -->
    <div id="employeeManagerModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1400px; width: 95%; height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="background: var(--bg); border-bottom: 1px solid var(--border); flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="material-icons" style="color: var(--primary); font-size: 28px;">badge</span>
                    <h3 style="margin: 0; color: var(--text); font-weight: 700; letter-spacing: -0.5px;">MGI Employees</h3>
                </div>
                <span class="material-icons close-modal" onclick="toggleEmployeeManager()" style="color: var(--text-light); transition: transform 0.2s;">close</span>
            </div>
            <div class="modal-body" style="flex-grow: 1; overflow: hidden; display: flex; flex-direction: column; padding: 25px;">
                
                <!-- Search & Filter Bar (NEW) -->
                <div style="background: var(--card-bg); padding: 15px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="flex: 2; min-width: 250px;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 6px; text-transform: uppercase;">Search Employee</label>
                        <div style="position: relative;">
                            <span class="material-icons" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 18px; color: var(--text-light);">search</span>
                            <input type="text" id="employeeSearchInput" oninput="loadEmployees()" placeholder="Search by Name, ID, or IP..." style="width: 100%; padding: 10px 10px 10px 40px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg);">
                        </div>
                    </div>
                    
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 6px; text-transform: uppercase;">Department</label>
                        <select id="employeeDeptFilter" onchange="loadEmployees()" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg);">
                            <option value="">All Departments</option>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 6px; text-transform: uppercase;">Location</label>
                        <select id="employeeLocFilter" onchange="loadEmployees()" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg);">
                            <option value="">All Locations</option>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-light); margin-bottom: 6px; text-transform: uppercase;">Floor</label>
                        <select id="employeeFloorFilter" onchange="loadEmployees()" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg);">
                            <option value="">All Floors</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button onclick="clearEmployeeFilters()" class="btn-secondary" title="Reset Filters" style="width: 42px; height: 42px; padding: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                            <span class="material-icons" style="font-size: 20px;">refresh</span>
                        </button>
                        <button id="toggleEmployeeFormBtn" onclick="toggleAddEmployeeForm()" class="btn-primary" title="Add New Employee" style="width: 42px; height: 42px; padding: 0; border-radius: 50%; background: #10b981; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                            <span class="material-icons" style="font-size: 24px;">add</span>
                        </button>
                    </div>
                </div>

                <!-- Form section (hidden) -->
                <div id="addEmployeeForm" style="display: none; margin-bottom: 20px; padding: 25px; background: var(--card-bg); border-radius: 16px; border: 1px solid var(--primary); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
                     <h4 style="margin: 0 0 20px 0; color: var(--primary); display: flex; align-items: center; gap: 8px;">
                        <span class="material-icons">edit_note</span>
                        Record Details
                     </h4>
                     <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Employee ID <small style="color:#ef4444;">*</small></label>
                            <input type="text" id="empId" placeholder="e.g. 174235" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; transition: border-color 0.2s;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Full Name <small style="color:#ef4444;">*</small></label>
                            <input type="text" id="empFullName" placeholder="Full name as in HR" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Email Address</label>
                            <input type="email" id="empEmail" placeholder="example@mgi.org" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Mobile No</label>
                            <input type="text" id="empMobile" placeholder="e.g. 017..." style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Designation</label>
                            <input type="text" id="empDesignation" placeholder="Job title" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Department</label>
                            <input type="text" id="empDepartment" placeholder="e.g. IT, ERP" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Sub Department</label>
                            <input type="text" id="empSubDepartment" placeholder="e.g. RPA, Cash" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">IP Address</label>
                            <input type="text" id="empIpNo" placeholder="Network IP" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Office Location</label>
                            <input type="text" id="empOfficeLocation" placeholder="e.g. Fresh House" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 8px; font-size: 0.85rem;">Floor / Unit</label>
                            <input type="text" id="empFloor" placeholder="e.g. 7th Floor" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                        </div>
                    </div>
                    <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 20px;">
                        <button onclick="saveEmployee()" class="btn-primary" style="padding: 12px 30px; font-weight: 700; border-radius: 10px;">Save Record</button>
                        <button onclick="toggleAddEmployeeForm()" class="btn-secondary" style="padding: 12px 30px; font-weight: 700; border-radius: 10px;">Cancel</button>
                    </div>
                </div>

                <!-- Table section -->
                <div style="flex-grow: 1; overflow-y: auto; background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; min-height: 0;">
                    <table style="width:100%; border-collapse: separate; border-spacing: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: #f8fafc; box-shadow: 0 1px 0 var(--border);">
                            <tr>
                                <th style="text-align: left; padding: 15px 20px; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Basic Info</th>
                                <th style="text-align: left; padding: 15px 20px; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Role & Dept</th>
                                <th style="text-align: left; padding: 15px 20px; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Workspace</th>
                                <th style="text-align: left; padding: 15px 20px; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Contact Info</th>
                                <th style="text-align: center; padding: 15px 20px; font-size: 0.75rem; text-transform: uppercase; color: #64748b;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeeListBody">
                            <!-- Rows loaded via JS -->
                        </tbody>
                    </table>
                    <div id="employeeEmptyState" style="display: none; padding: 60px; text-align: center;">
                        <span class="material-icons" style="font-size: 64px; color: #e2e8f0; margin-bottom: 15px;">person_search</span>
                        <h4 style="margin: 0; color: #94a3b8;">No employees found matching your criteria</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('manage_statuses')): ?>
    <!-- Status Manager Modal -->
    <div id="statusManagerModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Manage Job Statuses</h3>
                <span class="material-icons close-modal" onclick="toggleStatusManager()">close</span>
            </div>
            <div class="modal-body">
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <input type="text" id="newStatusInput" placeholder="New status name..." style="flex:1;padding:10px;border:1px solid var(--border);border-radius:8px;">
                    <button onclick="addStatus()" class="btn-primary">Add</button>
                </div>
                <div id="statusList" style="display:flex;flex-direction:column;gap:8px;max-height:350px;overflow-y:auto;"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('manage_sources')): ?>
    <!-- Source Manager Modal -->
    <div id="sourceManagerModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Manage Job Sources</h3>
                <span class="material-icons close-modal" onclick="toggleSourceManager()">close</span>
            </div>
            <div class="modal-body">
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <input type="text" id="newSourceInput" placeholder="New source name..." style="flex:1;padding:10px;border:1px solid var(--border);border-radius:8px;">
                    <button onclick="addSource()" class="btn-primary">Add</button>
                </div>
                <div id="sourceList" style="display:flex;flex-direction:column;gap:8px;max-height:350px;overflow-y:auto;"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- Add Task Modal -->
    <div id="addTaskModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1000px; width: 95vw;">
            <div class="modal-header">
                <h3>Add New Screening Task</h3>
                <span class="material-icons close-modal" onclick="toggleAddTask()">close</span>
            </div>
            <div class="modal-body">
                <div id="addTaskFormContainer">
                    <!-- Source Selection -->
                    <div style="margin-bottom: 5px; background: #f8fafc; padding: 8px 15px; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <label style="display: block; font-weight: 700; margin-bottom: 5px; color: var(--text);">Task Source</label>
                            <div id="sourceOptionsContainer" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                <!-- Sources loaded via JS -->
                                <div style="font-size: 0.8rem; color: #64748b;">Loading sources...</div>
                            </div>
                        </div>
<?php if (basename($_SERVER['PHP_SELF']) === 'tasks.php'): ?>
                        <div style="display: flex; align-items: center; gap: 8px; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.2); padding: 6px 12px; border-radius: 8px;">
                            <input type="checkbox" id="publicIsTestTask" style="width: 16px; height: 16px; cursor: pointer;">
                            <label for="publicIsTestTask" style="font-size: 0.85rem; font-weight: 700; color: #ef4444; cursor: pointer; display: flex; align-items: center; gap: 4px; user-select: none;">
                                <span class="material-icons" style="font-size: 16px;">bug_report</span> Test Purpose
                            </label>
                        </div>
<?php endif; ?>
                    </div>

                    <!-- Job Entry Form -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; align-items: start; margin-bottom: 5px;">
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 4px; color: var(--text); font-size: 0.85rem;">Job Title</label>
                            <input type="text" id="publicJobTitle" placeholder="e.g. Software Engineer" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; margin-bottom: 4px;">
                            <p style="font-size: 0.7rem; color: var(--text-light); margin: 0; line-height: 1.2;">Should match exactly if using BDJobs.</p>
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-weight: 700; margin-bottom: 4px; color: var(--text); font-size: 0.85rem;">JD ID (Mandatory)</label>
                            <div style="position: relative;">
                                <span class="material-icons" onclick="generateJdId()" style="position: absolute; left: 8px; top: 8px; cursor: pointer; color: var(--primary); font-size: 18px;" title="Generate Unique ID">autorenew</span>
                                <input type="text" id="publicJdId" placeholder="e.g. JD10326" maxlength="20" style="width: 100%; padding: 8px 12px; padding-left: 35px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; margin-bottom: 4px;">
                            </div>
                            <p style="font-size:0.7rem; color: var(--text-light); margin: 0; line-height: 1.2;">Required for all sources. Max 20 char.</p>
                        </div>
                        <div class="form-group" style="position: relative;">
                            <label style="display: block; font-weight: 700; margin-bottom: 4px; color: var(--text); font-size: 0.85rem;">Department (Optional)</label>
                            <input type="text" id="publicDept" autocomplete="off" placeholder="e.g. IT, HR" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; margin-bottom: 4px;" oninput="searchDepartmentsForTask(this.value)">
                            <div id="deptSuggestionsTask" class="suggestions-container" style="display: none; position: absolute; z-index: 1000; width: 100%; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); color: var(--text);"></div>
                        </div>
                        <div class="form-group" style="position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <label style="font-weight: 700; color: var(--text); font-size: 0.85rem;">Concern Person</label>
                                <label style="display: flex; align-items: center; gap: 4px; font-size: 0.7rem; color: var(--primary); cursor: pointer; font-weight: 600;">
                                    <input type="checkbox" id="sendEmailCheckbox" style="cursor: pointer;"> Mail
                                </label>
                            </div>
                            <input type="text" id="publicConcern" autocomplete="off" placeholder="Search by name or ID..." style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; margin-bottom: 4px;" oninput="searchEmployeesForTask(this.value)">
                            <input type="hidden" id="publicConcernEmail">
                            <div id="concernSuggestionsTask" class="suggestions-container" style="display: none; position: absolute; z-index: 1000; width: 100%; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; max-height: 350px; overflow-y: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); color: var(--text);"></div>
                        </div>
                    </div>

                    <!-- Dynamic Upload Section -->
                    <div id="uploadSection" style="display: none; background: #f0fdf4; padding: 8px 15px; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 5px;">
                        <h4 style="margin: 0 0 5px 0; color: #166534; font-size: 0.95rem;">Manual Upload Requirements</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
                            <div id="jdUploadGroup" style="display: none;">
                                <label style="display: block; font-weight: 700; margin-bottom: 5px; color: var(--text);">Job Description (JD) Upload</label>
                                <!-- Spacer to align with CV upload radio buttons -->
                                <div style="display: flex; gap: 15px; margin-bottom: 5px; visibility: hidden;">
                                    <label style="display: flex; align-items: center; gap: 5px; font-size: 0.85rem;"><input type="radio"> Spacer</label>
                                </div>
                                <input type="file" id="jdUploadFile" accept=".pdf,.doc,.docx" style="width: 100%; padding: 6px; background: white; border: 1px solid var(--border); border-radius: 6px;">
                                <p style="font-size: 0.75rem; color: #64748b; margin: 4px 0 0 0;">Upload a single JD file.</p>
                            </div>

                            <div id="cvUploadGroup" style="display: none;">
                                <label style="display: block; font-weight: 700; margin-bottom: 5px; color: var(--text);">CV Upload</label>
                                <div style="display: flex; gap: 15px; margin-bottom: 5px;">
                                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.85rem;">
                                        <input type="radio" name="cvUploadMode" value="files" checked onchange="toggleCvInputMode()"> Select Files
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.85rem;">
                                        <input type="radio" name="cvUploadMode" value="folder" onchange="toggleCvInputMode()"> Select Folder
                                    </label>
                                </div>
                                <input type="file" id="cvUploadFiles" multiple accept=".pdf" style="width: 100%; padding: 6px; background: white; border: 1px solid var(--border); border-radius: 6px;">
                                <input type="file" id="cvUploadFolder" webkitdirectory directory multiple accept=".pdf" style="display: none; width: 100%; padding: 6px; background: white; border: 1px solid var(--border); border-radius: 6px;">
                                <p id="cvValidCount" style="font-size: 0.85rem; font-weight: bold; color: #10b981; margin: 4px 0 0 0; display: none;"></p>
                                <p style="font-size: 0.75rem; color: #64748b; margin: 4px 0 0 0;">Upload multiple PDFs or an entire folder.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 5px; margin-bottom: 5px;">
                        <span onclick="toggleMandatoryReq()" style="color: var(--primary); font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 0.95rem;">
                            <span class="material-icons" id="reqToggleIcon" style="font-size: 18px;">add</span> Add Mandatory Requirement (Optional)
                        </span>
                    </div>

                    <!-- AI Prompt Section (Hidden Initially) -->
                    <div id="mandatoryReqSection" class="prompt-card" style="display: none; background: var(--bg); padding: 10px 15px; border-radius: 12px; margin-bottom: 10px; border: 1px solid var(--border);">
                        <textarea id="aiPrompt" class="prompt-textarea" placeholder="Enter your mandatory requirements here (e.g. Min 3 years exp, Python knowledge)..." style="min-height: 100px; margin-bottom: 0; background: var(--card-bg); color: var(--text); border: 1px solid var(--border);"></textarea>
                    </div>

                    <!-- Upload Progress Bar (Hidden Initially) -->
                    <div id="uploadProgressContainer" style="display: none; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 700; color: var(--primary); margin-bottom: 8px;">
                            <span id="uploadProgressText">Uploading...</span>
                            <span id="uploadProgressStats">0% [0/0]</span>
                        </div>
                        <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                            <div id="uploadProgressBar" style="height: 100%; width: 0%; background: #10b981; transition: width 0.2s ease;"></div>
                        </div>
                    </div>

                    <div style="padding-top: 10px; border-top: 1px solid #e2e8f0; display: flex; justify-content: center; gap: 20px;">
                        <button onclick="toggleAddTask()" class="btn-secondary" style="padding: 10px 0; width: 220px; font-weight: 600; text-align: center;">Cancel</button>
                        <button id="addJobBtnPublic" class="btn-primary" style="padding: 10px 0; width: 220px; font-weight: 700; font-size: 1.05rem; border-radius: 8px; transition: all 0.2s; text-align: center;">Create Task</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Permissions Management Modal -->
    <div id="permissionsModal" class="modal-overlay">
        <div class="modal-content" style="width: 500px; max-width: 95%; padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 650px; max-height: 90vh; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
            <div class="modal-header" style="padding: 24px; background: var(--card-bg); border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; color: var(--text); font-weight: 700;">User Permissions</h3>
                    <p id="permUserTitle" style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-light); font-weight: 500;"></p>
                </div>
                <span class="material-icons close-modal" onclick="togglePermissionsModal()" style="cursor: pointer; color: var(--text-light); transition: color 0.2s;">close</span>
            </div>
            
            <div id="permissionsChecklist" style="padding: 20px; overflow-y: auto; flex-grow: 1; background: var(--card-bg);">
                <!-- Permissions items dynamicly loaded here -->
            </div>

            <div class="modal-footer" style="padding: 20px 24px; background: var(--bg); border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0;">
                <button onclick="togglePermissionsModal()" class="btn-secondary" style="padding: 10px 20px; border-radius: 10px; font-weight: 600;">Cancel</button>
                <button onclick="savePermissions()" class="btn-primary" style="padding: 10px 25px; border-radius: 10px; font-weight: 600; background: var(--primary); color: #fff; border: none; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);">Save Changes</button>
            </div>
        </div>
    </div>


    <!-- Task Sharing & Access Permission Modal -->
    <div id="taskSharingModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; z-index: 1100;">
        <div class="modal-content" style="width: 550px; max-width: 95%; padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 600px; max-height: 90vh; border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); background: var(--card-bg);">
            
            <!-- Modal Header -->
            <div class="modal-header" style="padding: 24px 28px; background: var(--bg); border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center;">
                        <span class="material-icons" style="font-size: 24px;">person_add</span>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.2rem; color: var(--text); font-weight: 750; letter-spacing: -0.3px;">Grant Task Permission</h3>
                        <p style="margin: 3px 0 0 0; font-size: 0.8rem; color: var(--text-light); font-weight: 500;">Authorize staff members to view this task.</p>
                    </div>
                </div>
                <span class="material-icons close-modal" onclick="window.closeTaskSharingModal()" style="cursor: pointer; color: var(--text-light); padding: 8px; border-radius: 50%; background: var(--bg); transition: all 0.2s;">close</span>
            </div>

            <!-- Modal Body -->
            <div class="modal-body" style="padding: 24px 28px; overflow-y: auto; flex-grow: 1; display: flex; flex-direction: column; gap: 20px; background: var(--card-bg);">
                
                <!-- Hidden inputs -->
                <input type="hidden" id="sharingTaskJdId">

                <!-- Task Description Card -->
                <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 16px; padding: 16px 20px; flex-shrink: 0;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-light); letter-spacing: 0.5px; margin-bottom: 4px;">Selected Task</div>
                    <div id="sharingTaskTitle" style="font-weight: 700; font-size: 1rem; color: var(--text); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; line-height: 1.3;">
                        <!-- Injected via JS -->
                    </div>
                </div>

                <!-- Add User Form -->
                <div style="display: flex; flex-direction: column; gap: 8px; position: relative; flex-shrink: 0;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.5px;">Search Employee</label>
                    <div style="display: flex; gap: 10px; align-items: center; width: 100%;">
                        <div style="position: relative; flex: 1; min-width: 0;">
                            <span class="material-icons" style="position: absolute; left: 12px; top: 11px; font-size: 20px; color: var(--text-light);">search</span>
                            <input type="text" id="sharingEmpSearch" autocomplete="off" placeholder="Search by name, designation, or ID..." 
                                style="width: 100%; padding: 10px 10px 10px 42px; border: 1px solid var(--border); border-radius: 12px; font-size: 0.9rem; background: var(--bg); color: var(--text); outline: none; transition: border-color 0.2s;"
                                oninput="window.searchSharingEmployees(this.value)">
                            
                            <!-- Search Suggestions Dropdown -->
                            <div id="sharingSuggestions" class="suggestions-container" style="display: none; position: absolute; z-index: 1050; left: 0; right: 0; top: calc(100% + 5px); background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; max-height: 220px; overflow-y: auto; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); color: var(--text);"></div>
                        </div>
                        <button onclick="window.addSharingUser()" class="btn-primary" style="padding: 10px 20px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px; flex-shrink: 0; background: var(--primary); box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);">
                            <span class="material-icons" style="font-size: 18px;">add</span> Add
                        </button>
                    </div>
                </div>

                <!-- Shared Users List Header -->
                <div style="display: flex; flex-direction: column; gap: 12px; flex-grow: 1; min-height: 0;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Authorized Staff Members</label>
                    <div id="sharingAllowedList" style="flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding-right: 4px; min-height: 120px;">
                        <!-- Loaded dynamically via JS -->
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="padding: 20px 28px; background: var(--bg); border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0;">
                <button onclick="window.closeTaskSharingModal()" class="btn-secondary" style="padding: 10px 20px; border-radius: 12px; font-weight: 600;">Cancel</button>
                <button onclick="window.saveTaskSharing()" class="btn-primary" style="padding: 10px 25px; border-radius: 12px; font-weight: 600; background: var(--primary); color: #fff; border: none; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);">Save Permissions</button>
            </div>
        </div>
    </div>


    <?php if (has_permission('db_control')): ?>
    <!-- Database Control Modal -->
    <div id="dbControlModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1500px; width: 98%; height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
            <div class="modal-header" style="flex-shrink: 0; background: var(--bg); border-bottom: 1px solid var(--border);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="material-icons" style="color: var(--primary);">storage</span>
                    <h3 style="margin: 0; color: var(--text);">Database Control Center</h3>
                </div>
                <span class="material-icons close-modal" onclick="toggleDbControl()" style="color: var(--text-light);">close</span>
            </div>
            <div class="modal-body" style="flex-grow: 1; display: flex; overflow: hidden; padding: 0;">
                <!-- Tables Sidebar -->
                <div style="width: 250px; min-width: 250px; flex-shrink: 0; background: var(--bg); border-right: 1px solid var(--border); display: flex; flex-direction: column;">
                    <div style="padding: 15px; border-bottom: 1px solid var(--border); display: flex; gap: 8px;">
                        <input type="text" id="dbTableSearch" placeholder="Search tables..." style="flex: 1; min-width: 0; padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem;" oninput="filterDbTables()">
                        <button class="btn-primary" onclick="openCreateTableModal()" style="padding: 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" title="Create New Table"><span class="material-icons" style="font-size: 18px;">add_box</span></button>
                    </div>

                    <div id="dbTableList" style="flex-grow: 1; overflow-y: auto; padding: 10px;">
                        <!-- Table list items dynamically -->
                    </div>
                </div>
                <!-- Main Workspace -->
                <div style="flex-grow: 1; display: flex; flex-direction: column; position: relative;">
                    <!-- Area Tabs -->
                    <div style="display: flex; background: var(--bg); padding: 10px 20px 0 20px; border-bottom: 1px solid var(--border); gap: 5px;">
                        <button class="db-tab active" onclick="switchDbTab('data')">
                            <span class="material-icons" style="font-size: 18px;">table_chart</span> Data Browser
                        </button>
                        <button class="db-tab" onclick="switchDbTab('query')">
                            <span class="material-icons" style="font-size: 18px;">terminal</span> SQL Console
                        </button>
                    </div>
                    
                    <!-- Data Browser View -->
                    <div id="dbDataView" class="db-content-area" style="display: flex; flex-direction: column; flex-grow: 1; overflow: hidden;">
                        <div id="dbTableActions" style="padding: 15px 20px; background: var(--card-bg); border-bottom: 1px solid var(--border); display: none; justify-content: space-between; align-items: center;">
                            <div style="display: flex; gap: 10px;">
                                <h4 id="activeTableName" style="margin: 0; color: var(--text);"></h4>
                                <span id="tableRecordCount" style="font-size: 0.8rem; color: var(--text-light); background: var(--bg); padding: 2px 8px; border-radius: 12px; align-self: center;"></span>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button onclick="openAddColumnModal()" class="btn-secondary" style="display: flex; align-items: center; gap: 5px;">
                                    <span class="material-icons" style="font-size: 18px;">view_column</span> New Column
                                </button>
                                <button onclick="openAddRecordModal()" class="btn-primary" style="background: var(--success); border: none; display: flex; align-items: center; gap: 5px;">
                                    <span class="material-icons" style="font-size: 18px;">add</span> Add Record
                                </button>
                                <button onclick="exportDbTable()" class="btn-secondary" style="color: var(--primary); display: flex; align-items: center; gap: 5px;">
                                    <span class="material-icons" style="font-size: 18px;">file_download</span> Export CSV
                                </button>
                                <button onclick="dbTableOp('truncate')" class="btn-secondary" style="color: #ef4444;">Truncate</button>
                                <button onclick="dbTableOp('drop')" class="btn-danger" style="background: #ef4444;">Drop Table</button>
                            </div>
                        </div>
                        <div id="dbDataTableContainer" style="flex-grow: 1; overflow: auto; padding: 0;">
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8; flex-direction: column;">
                                <span class="material-icons" style="font-size: 48px; margin-bottom: 10px;">table_chart</span>
                                Select a table from the list to browse records
                            </div>
                        </div>
                    </div>

                    <!-- Query Console View -->
                    <div id="dbQueryView" class="db-content-area" style="display: none; flex-direction: column; padding: 20px; background: var(--bg); flex-grow: 1; overflow: hidden;">
                        <div style="margin-bottom: 20px; background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); flex-shrink: 0;">
                            <label style="display: block; font-weight: 600; margin-bottom: 10px; color: var(--text); display: flex; align-items: center; gap: 8px;">
                                <span class="material-icons" style="color: var(--text-light);">code</span> Custom SQL Query
                            </label>
                            <textarea id="dbQueryInput" placeholder="SELECT * FROM users LIMIT 10;" style="width: 100%; height: 120px; font-family: 'Courier New', Courier, monospace; padding: 15px; border: 1px solid var(--border); border-radius: 8px; background: #0f172a; color: #38bdf8; font-size: 1.05rem; resize: vertical; line-height: 1.5;"></textarea>
                            <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                                <button onclick="clearDbQuery()" class="btn-secondary" style="padding: 10px 20px;">Clear Console</button>
                                <button onclick="executeDbQuery()" class="btn-primary" style="background: #3b82f6; padding: 10px 25px; display: flex; align-items: center; gap: 8px;">
                                    <span class="material-icons" style="font-size: 18px;">play_circle</span> Execute Query
                                </button>
                            </div>
                        </div>
                        <div style="font-weight: 600; margin-bottom: 10px; color: #475569;">Query Results</div>
                        <div id="dbQueryResult" style="flex-grow: 1; overflow: auto; border: 1px solid var(--border); border-radius: 12px; background: var(--card-bg); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8; flex-direction: column;">
                                <span class="material-icons" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;">data_object</span>
                                Results will appear here...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Table Modal -->
    <div id="createTableModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px; width: 95%;">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="material-icons" style="color: var(--primary);">table_chart</span>
                    <h3 style="margin: 0;">Create New Table</h3>
                </div>
                <span class="material-icons close-modal" onclick="toggleCreateTableModal()">close</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 15px; color: #64748b; font-size: 0.9rem;">
                    MySQL requires at least one column to create a table. We'll set up the primary key or first column here.
                </div>
                <form id="createTableForm" style="display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Table Name</label>
                        <input type="text" id="newTableName" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;" placeholder="e.g., invoices">
                    </div>
                    
                    <div id="createTableColsContainer" style="display: flex; flex-direction: column; gap: 10px; border-top: 1px solid var(--border); padding-top: 15px;">
                        <!-- Dynamic columns will be added here -->
                    </div>
                    
                    <button type="button" class="btn-secondary" onclick="addCreateTableColRow()" style="align-self: flex-start; display: flex; align-items: center; gap: 5px; padding: 6px 12px; font-size: 0.85rem;">
                        <span class="material-icons" style="font-size: 16px;">add</span> Add Column
                    </button>
                </form>
            </div>
            <div class="modal-footer" style="padding: 15px 25px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px;">
                <button class="btn-secondary" onclick="toggleCreateTableModal()">Cancel</button>
                <button class="btn-primary" onclick="createDbTable()">Create Table</button>
            </div>
        </div>
    </div>

    <!-- Add Column Modal -->
    <div id="dbColumnModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px; width: 95%;">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="material-icons" style="color: var(--primary);">view_column</span>
                    <h3 style="margin: 0;">Add New Column</h3>
                </div>
                <span class="material-icons close-modal" onclick="toggleDbColumnModal()">close</span>
            </div>
            <div class="modal-body">
                <form id="dbColumnForm" style="display: flex; flex-direction: column; gap: 15px;">
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Column Name</label>
                        <input type="text" id="newColName" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;" placeholder="e.g., status">
                    </div>
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Data Type</label>
                        <select id="newColType" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                            <option value="VARCHAR(255)">VARCHAR (255 chars)</option>
                            <option value="INT">INT (Number)</option>
                            <option value="TEXT">TEXT (Long string)</option>
                            <option value="BOOLEAN">BOOLEAN (True/False)</option>
                            <option value="DATE">DATE</option>
                            <option value="DATETIME">DATETIME</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Default Value (Optional)</label>
                        <input type="text" id="newColDefault" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;" placeholder="e.g., 0, 'pending', NULL">
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="padding: 15px 25px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px;">
                <button class="btn-secondary" onclick="toggleDbColumnModal()">Cancel</button>
                <button class="btn-primary" onclick="addDbColumn()">Create Column</button>
            </div>
        </div>
    </div>

    <!-- Database Record Form Modal -->
    <div id="dbRecordModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 650px; width: 95%; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid var(--border);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="material-icons" style="color: var(--primary);">edit_note</span>
                    <h3 id="dbRecordModalTitle" style="margin: 0;">Manage Record</h3>
                </div>
                <span class="material-icons close-modal" onclick="toggleDbRecordModal()">close</span>
            </div>
            <div class="modal-body" style="padding: 25px; overflow-y: auto;">
                <form id="dbRecordForm" style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                    <!-- form fields injected here -->
                </form>
            </div>
            <div class="modal-footer" style="padding: 15px 25px; background: #f8fafc; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px;">
                <button class="btn-secondary" onclick="toggleDbRecordModal()" style="padding: 10px 20px;">Cancel</button>
                <button class="btn-primary" onclick="saveDbRecord()" style="padding: 10px 25px; background: var(--primary);">Save Progress</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('manage_rpa')): ?>
    <!-- RPA Configuration Modal -->
    <div id="rpaConfigModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1500px; width: 98%; height: 95vh; display: flex; flex-direction: column; overflow: hidden; border-radius: 20px;">
            <div class="modal-header" style="padding: 15px 25px; border-bottom: 1px solid var(--border); background: var(--bg);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="material-icons" style="color: var(--primary); font-size: 28px;">settings_remote</span>
                    <h3 style="margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--text);">RPA System Configuration</h3>
                </div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="position: relative; width: 350px;">
                        <span class="material-icons" style="position: absolute; left: 12px; top: 10px; color: #94a3b8; font-size: 20px;">search</span>
                        <input type="text" id="rpaSearchInput" placeholder="Quick search configs..." 
                            style="width: 100%; padding: 10px 10px 10px 40px; border: 1px solid var(--border); border-radius: 12px; font-size: 0.9rem; background: var(--card-bg); color: var(--text);"
                            oninput="filterRpaConfigs()">
                    </div>
                    <button onclick="showAddRpaForm()" class="btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 12px;">
                        <span class="material-icons" style="font-size: 20px;">add</span> Add Config
                    </button>
                    <span class="material-icons close-modal" onclick="toggleRpaConfig()" style="cursor: pointer; color: #94a3b8; margin-left: 10px;">close</span>
                </div>
            </div>
            <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 25px;">
                <div id="rpaForm" style="display: none; margin-bottom: 25px; padding: 15px; background: rgba(79, 70, 229, 0.05); border-radius: 16px; border: 1px solid var(--border);">
                    <input type="hidden" id="rpaId">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #4338ca;">Config Key</label>
                            <input type="text" id="rpaKey" placeholder="e.g. API_URL" style="width: 100%; padding: 8px 12px; border: 1px solid #c7d2fe; border-radius: 10px; font-size: 0.9rem;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #4338ca;">Project Name</label>
                            <input type="text" id="rpaProject" placeholder="e.g. CV_Sorting" style="width: 100%; padding: 8px 12px; border: 1px solid #c7d2fe; border-radius: 10px; font-size: 0.9rem;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #4338ca;">Category</label>
                            <input type="text" id="rpaCategory" placeholder="e.g. System" style="width: 100%; padding: 8px 12px; border: 1px solid #c7d2fe; border-radius: 10px; font-size: 0.9rem;">
                        </div>
                        <div style="grid-column: span 1;">
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #4338ca;">Description</label>
                            <input type="text" id="rpaDescription" placeholder="What is this config for?" style="width: 100%; padding: 8px 12px; border: 1px solid #c7d2fe; border-radius: 10px; font-size: 0.9rem;">
                        </div>
                        <div style="grid-column: span 1;">
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.85rem; color: #4338ca;">Value</label>
                            <input type="text" id="rpaValue" placeholder="Config value..." style="width: 100%; padding: 8px 12px; border: 1px solid #c7d2fe; border-radius: 10px; font-size: 0.9rem;">
                        </div>
                        <div style="display: flex; gap: 8px; align-items: flex-end;">
                            <button onclick="saveRpaConfig()" class="btn-primary" style="flex: 1; padding: 10px; background: #4f46e5; font-size: 0.9rem; border-radius: 10px;">Save Config</button>
                            <button onclick="document.getElementById('rpaForm').style.display='none'" class="btn-secondary" style="padding: 10px; font-size: 0.9rem; border-radius: 10px;">Cancel</button>
                        </div>
                    </div>
                </div>

                <div class="table-container" style="border: 1px solid var(--border); border-radius: 16px; background: var(--card-bg); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: var(--bg);">
                            <tr>
                                <th style="cursor:pointer; text-align:left; padding:8px 10px; width: 14%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;" onclick="sortRpaConfigs('key')">Key <span class="material-icons" style="font-size:12px; vertical-align:middle;">sort</span></th>
                                <th style="text-align:left; padding:8px 10px; width: 22%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;">Value</th>
                                <th style="cursor:pointer; text-align:left; padding:8px 10px; width: 7%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;" onclick="sortRpaConfigs('project')">Project <span class="material-icons" style="font-size:12px; vertical-align:middle;">sort</span></th>
                                <th style="cursor:pointer; text-align:left; padding:8px 10px; width: 7%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;" onclick="sortRpaConfigs('category')">Category <span class="material-icons" style="font-size:12px; vertical-align:middle;">sort</span></th>
                                <th style="cursor:pointer; text-align:left; padding:8px 10px; width: 15%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;" onclick="sortRpaConfigs('description')">Description <span class="material-icons" style="font-size:12px; vertical-align:middle;">sort</span></th>
                                <th style="cursor:pointer; text-align:left; padding:8px 10px; width: 13%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;" onclick="sortRpaConfigs('created_at')">Created By | At <span class="material-icons" style="font-size:12px; vertical-align:middle;">sort</span></th>
                                <th style="cursor:pointer; text-align:left; padding:8px 10px; width: 13%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;" onclick="sortRpaConfigs('updated_at')">Last Updated By | At <span class="material-icons" style="font-size:12px; vertical-align:middle;">sort</span></th>
                                <th style="text-align:center; padding:8px 10px; width: 9%; background:var(--bg); border-bottom:2px solid var(--border); font-size: 0.75rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="rpaConfigListBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (has_permission('manage_server_allocation')): ?>
    <!-- Server Allocation Modal -->
    <div id="serverAllocationModal" class="modal-overlay">
        <div class="modal-content" style="background: var(--card-bg); padding: 30px; border-radius: 20px; width: 100%; max-width: 450px; position: relative; border: 1px solid var(--border);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding: 20px 25px; background: var(--bg); border-radius: 20px 20px 0 0;">
                <h3 style="margin: 0; color: var(--primary); display: flex; align-items: center; gap: 10px;">
                    <span class="material-icons">vibration</span> Server Allocation
                </h3>
                <span class="material-icons close-modal" onclick="toggleServerAllocation()" style="cursor: pointer; color: var(--text-light);">close</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 10px; color: var(--text-light);">Active Server Count</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="number" id="serverCountInput" min="1" max="99" disabled style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 10px; font-size: 1.1rem; text-align: center; font-weight: 700; color: var(--primary); background: var(--bg);">
                        <div style="display: flex; gap: 8px;">
                            <button id="serverCancelBtn" onclick="cancelServerEdit()" class="btn-secondary" style="display: none; padding: 12px 20px;">Cancel</button>
                            <button id="serverUpdateBtn" onclick="toggleServerEdit()" class="btn-primary" style="padding: 12px 25px;">Update</button>
                        </div>
                    </div>
                </div>
                
                <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px;">
                    <h4 style="margin: 0 0 15px 0; font-size: 0.9rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 8px;">
                        <span class="material-icons" style="font-size: 18px;">history</span> Audit Logs
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span style="color: var(--text-light);">Last Updated By:</span>
                            <span id="serverLastUpdatedBy" style="font-weight: 600; color: var(--text);">-</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span style="color: var(--text-light);">Employee ID:</span>
                            <span id="serverLastUpdatedId" style="font-weight: 600; color: var(--text);">-</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span style="color: var(--text-light);">Updated At:</span>
                            <span id="serverLastUpdatedAt" style="font-weight: 600; color: var(--text);">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Task Limits Modal -->
    <?php if (has_permission('manage_task_limits')): ?>
    <div id="taskLimitsModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 900px; width: 95%; height: 85vh; border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); display: flex; flex-direction: column; overflow: hidden; padding: 0;">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 20px 24px; color: white; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="background: rgba(255, 255, 255, 0.2); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <span class="material-icons">block</span>
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; letter-spacing: -0.025em;">Task Creation Limits</h2>
                        <p style="margin: 0; font-size: 0.8rem; opacity: 0.9;">Manage system and user task quotas</p>
                    </div>
                </div>
                <button onclick="toggleTaskLimitsModal()" style="background: rgba(255, 255, 255, 0.1); border: none; color: white; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                    <span class="material-icons" style="font-size: 20px;">close</span>
                </button>
            </div>

            <div style="flex: 1; display: flex; overflow: hidden;">
                <!-- Left Sidebar: Form -->
                <div style="width: 320px; border-right: 1px solid var(--border); background: var(--bg); padding: 24px; overflow-y: auto;">
                    <h4 style="margin: 0 0 16px 0; font-size: 0.95rem; color: var(--text); font-weight: 600;">Add/Edit Limit</h4>
                    <form id="taskLimitForm" onsubmit="event.preventDefault(); saveTaskLimit();">
                        <input type="hidden" id="limitId">
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <div>
                                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 6px;">Limit Type</label>
                                <select id="limitType" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;" onchange="toggleLimitUserField()">
                                    <option value="daily">Daily (System Total)</option>
                                    <option value="monthly">Monthly (System Total)</option>
                                    <option value="total">Total (All Time)</option>
                                    <option value="per_user">Per User (Daily)</option>
                                    <option value="specific_user">Specific User (Daily)</option>
                                    <option value="per_user_monthly">Per User (Monthly)</option>
                                    <option value="specific_user_monthly">Specific User (Monthly)</option>
                                </select>
                            </div>
                            <div id="limitUserSection" style="display: none; position: relative;">
                                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 6px;">User ID</label>
                                <input type="text" id="limitUserId" autocomplete="off" placeholder="Type username or emp ID..." style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;" oninput="searchUsersForLimit(this.value)">
                                <div id="userSuggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 100; max-height: 200px; overflow-y: auto; margin-top: 4px; color: var(--text);"></div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 6px;">Limit Value (Max Tasks)</label>
                                <input type="number" id="limitValue" min="1" placeholder="e.g., 5" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem;" required>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                <input type="checkbox" id="limitIsActive" checked style="width: 16px; height: 16px;">
                                <label for="limitIsActive" style="font-size: 0.85rem; color: #475569; font-weight: 500;">Active</label>
                            </div>
                            <div style="display: flex; gap: 8px; margin-top: 10px;">
                                <button type="submit" class="btn-primary" style="flex: 1; padding: 10px; border-radius: 8px; font-weight: 600;">Save Limit</button>
                                <button type="button" onclick="resetLimitForm()" class="btn-secondary" style="padding: 10px; border-radius: 8px;"><span class="material-icons" style="font-size: 18px;">refresh</span></button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Right Side: Table -->
                <div style="flex: 1; padding: 24px; display: flex; flex-direction: column; overflow: hidden; background: var(--card-bg);">
                    <!-- Stats Dashboard -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
                        <div style="background: rgba(79, 70, 229, 0.1); padding: 16px; border-radius: 12px; border: 1px solid var(--primary);">
                            <span style="display: block; font-size: 0.75rem; color: var(--primary); font-weight: 600; text-transform: uppercase;">Today</span>
                            <span id="statDailyCount" style="font-size: 1.5rem; font-weight: 700; color: var(--text);">0</span>
                        </div>
                        <div style="background: rgba(139, 92, 246, 0.1); padding: 16px; border-radius: 12px; border: 1px solid #8b5cf6;">
                            <span style="display: block; font-size: 0.75rem; color: #8b5cf6; font-weight: 600; text-transform: uppercase;">This Month</span>
                            <span id="statMonthlyCount" style="font-size: 1.5rem; font-weight: 700; color: var(--text);">0</span>
                        </div>
                        <div style="background: rgba(16, 185, 129, 0.1); padding: 16px; border-radius: 12px; border: 1px solid var(--success);">
                            <span style="display: block; font-size: 0.75rem; color: var(--success); font-weight: 600; text-transform: uppercase;">All Time</span>
                            <span id="statTotalCount" style="font-size: 1.5rem; font-weight: 700; color: var(--text);">0</span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h4 style="margin: 0; font-size: 1rem; color: var(--text); font-weight: 600;">Active Quotas</h4>
                    </div>
                    <div style="flex: 1; overflow-y: auto; background: var(--bg); border: 1px solid var(--border); border-radius: 12px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="position: sticky; top: 0; background: var(--card-bg); z-index: 10;">
                                <tr>
                                    <th style="text-align:left; padding:12px 15px; border-bottom:1px solid var(--border); font-size: 0.8rem; color: var(--text-light);">Type</th>
                                    <th style="text-align:left; padding:12px 15px; border-bottom:1px solid var(--border); font-size: 0.8rem; color: var(--text-light);">User</th>
                                    <th style="text-align:center; padding:12px 15px; border-bottom:1px solid var(--border); font-size: 0.8rem; color: var(--text-light);">Value</th>
                                    <th style="text-align:center; padding:12px 15px; border-bottom:1px solid var(--border); font-size: 0.8rem; color: var(--text-light);">Status</th>
                                    <th style="text-align:center; padding:12px 15px; border-bottom:1px solid var(--border); font-size: 0.8rem; color: var(--text-light);">Action</th>
                                </tr>
                            </thead>
                            <tbody id="taskLimitTableBody">
                                <!-- Data populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- AI Chatbot Widget -->
    <div id="aiChatWidget" class="chat-widget-container" style="display: <?php echo has_permission('access_chat') ? 'block' : 'none'; ?>;">
        <!-- Chat Button -->
        <button id="aiChatButton" onclick="toggleAiChat()" class="chat-button">
            <span class="material-icons chat-icon">smart_toy</span>
        </button>

        <!-- Chat Window -->
        <div id="aiChatWindow" class="chat-window" style="display: none;">
            <div class="chat-header" onclick="toggleAiChat()" style="cursor: pointer;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="material-icons" style="color: #fff; font-size: 20px;">smart_toy</span>
                    <span>Chat Agent</span>
                </div>
                <span class="material-icons close-chat" style="cursor: pointer;">close</span>
            </div>
            <div class="chat-body" id="aiChatMessages">
                <div class="chat-message bot">
                    <div class="msg-bubble">Hi there! I am your AI Chat Agent. I can help answer questions about your database including Employees, Candidates, and Jobs. How can I help you today?</div>
                </div>
            </div>
            <div class="chat-footer">
                <input type="text" id="aiChatInput" placeholder="Ask a question..." onkeypress="handleChatKeyPress(event)">
                <button onclick="sendChatMessage()" class="send-btn">
                    <span class="material-icons">send</span>
                </button>
            </div>
        </div>
    </div>

    <!-- User Activity Modal -->
    <div id="userActivityModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header" style="background: var(--bg); border-bottom: 1px solid var(--border);">
                <h3 style="display:flex;align-items:center;gap:10px; margin: 0; color: var(--text);"><span class="material-icons" style="color: var(--primary);">history</span> User Activity & Status</h3>
                <span class="material-icons close-modal" onclick="toggleUserActivity()" style="color: var(--text-light);">close</span>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="color: var(--text-light); font-size: 0.9rem;">
                            Users active within the last 5 minutes are shown as <span style="color: #10b981; font-weight: 600;">Online</span>.
                        </div>
                        <div id="live-indicator" style="display: flex; align-items: center; gap: 6px; background: #ecfdf5; color: #10b981; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; border: 1px solid #10b981;">
                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulse-green 2s infinite;"></span>
                            LIVE
                        </div>
                    </div>
                    <button onclick="loadUserActivity()" class="btn-secondary" style="padding: 8px 15px; display: flex; align-items: center; gap: 8px; border-radius: 8px; font-size: 0.85rem;">
                        <span class="material-icons" style="font-size: 18px;">refresh</span> Refresh
                    </button>
                </div>

                <style>
                    @keyframes pulse-green {
                        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
                        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
                        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
                    }
                </style>

                <div class="table-responsive" style="max-height: 600px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="position: sticky; top: 0; background: var(--bg-alt); z-index: 10;">
                            <tr>
                                <th style="text-align: left; padding: 15px; border-bottom: 2px solid var(--border); color: var(--text-light); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">User</th>
                                <th style="text-align: center; padding: 15px; border-bottom: 2px solid var(--border); color: var(--text-light); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Status</th>
                                <th style="text-align: center; padding: 15px; border-bottom: 2px solid var(--border); color: var(--text-light); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Role</th>
                                <th style="text-align: left; padding: 15px; border-bottom: 2px solid var(--border); color: var(--text-light); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Last Active</th>
                                <th style="text-align: left; padding: 15px; border-bottom: 2px solid var(--border); color: var(--text-light); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Last IP</th>
                            </tr>
                        </thead>
                        <tbody id="userActivityBody">
                            <!-- Data populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
