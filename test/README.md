# Test & Debug Files

This folder contains all test, debug, and utility scripts that were removed from the production root directory.

**Status:** ✓ All files verified as NON-CRITICAL and safely moved.  
**Date Moved:** 2026-06-14  
**Total Files:** 27 (25 test/debug files + 2 old backup files)

---

## Folder Structure

### 📁 `testing-versions/`
Contains duplicate/test versions of main application files.
- **index_test.php** - Test version of main dashboard
- **index_clone.php** - Clone copy for testing
- **test_dashboard.php** - Dashboard testing utility (simulates public JD access)

**Use Case:** Testing without affecting production code  
**Safe to Delete:** YES (duplicates of production files)

---

### 📁 `debug/`
Contains utilities for inspecting database schema and data.

**Database Inspection:**
- **check_bom.php** - Detects BOM (Byte Order Mark) issues
- **check_db.php** - Tests database connection
- **check_job_schema.php** - Validates Job_List table structure
- **check_candidates_data.php** - Validates candidates table data
- **check_jobs_list.php** - Lists all jobs (debugging)
- **check_statuses.php** - Lists all job statuses
- **check_user.php** - Lists users for validation

**Data Debugging:**
- **debug_data.php** - General data debugging utility
- **debug_jd.php** - Job Description debugging
- **debug_titles.php** - Job titles debugging
- **debug_user.php** - Single user debugging
- **debug_users.php** - Multi-user debugging

**Other:**
- **tmp_describe.php** - One-line utility to describe users table schema

**Use Case:** Troubleshooting database issues  
**Safe to Delete:** YES (development utilities only)

---

### 📁 `migrations/`
Contains one-time setup scripts and data migrations.

**Setup Scripts:**
- **init_root_admin.php** - Initializes root admin account (run after schema setup)
- **update_serper_key.php** - Updates Serper API key in config table

**Data Migrations:**
- **migrate_remember_token.php** - Migrates remember-me tokens
- **sync_counts.php** - Synchronizes candidate/job counts
- **update_db.php** - General database updates

**Data Fixes:**
- **fix_candidate_titles.php** - Corrects candidate job titles
- **fix_status.php** - Fixes job status values
- **fix_status_order.php** - Reorders job statuses

**Use Case:** Database setup and one-time data corrections  
**Safe to Delete:** PARTIALLY
- Safe to delete after initial setup (keep for reference)
- Keep if you need to re-run migrations

---

### 📁 `backups/`
Contains backup files.

- **index.php.bak** - Backup of original index.php

**Use Case:** Version control/fallback  
**Safe to Delete:** YES (kept in git history)

---

### 📁 `Previous back up/`
Contains old backup versions of application files.

- **previous_backup_index.php** - Old version of index.php
- **previous_backup_script.js** - Old version of main JavaScript

**Use Case:** Historical reference  
**Safe to Delete:** YES (old backups, current versions are in git)

---

### 📁 `utilities/` (Empty)
Reserved for future utility scripts.

---

## Safety Verification

✓ **All files verified as non-critical:**
- No references found in production code
- Not imported via include/require statements
- Not called via JavaScript fetch/AJAX
- Not referenced in configuration files
- Not linked in navigation/sidebars

**Conclusion:** Moving these files to `/test/` has NO impact on application functionality.

---

## How to Use

### Running Debug Scripts
```bash
# From root CV_Sorting directory:
php test/debug/check_db.php          # Test database connection
php test/debug/check_candidates_data.php  # Check candidate data
php test/debug/debug_user.php        # Debug user info
```

### Running Migrations (One-Time Setup Only)
```bash
php test/migrations/init_root_admin.php   # Initialize admin (after schema)
php test/migrations/sync_counts.php       # Sync counts
```

### Testing Versions
Open in browser:
```
http://localhost/CV_Sorting/test/testing-versions/index_test.php
http://localhost/CV_Sorting/test/testing-versions/test_dashboard.php
```

---

## Cleanup Recommendations

**Can be safely deleted:**
- `testing-versions/` (duplicate files)
- `debug/` (development utilities)
- `backups/index.php.bak` (version in git)

**Keep for reference:**
- `migrations/` (document setup steps)

---

## Statistics

| Category | Count | Status |
|----------|-------|--------|
| Testing Versions | 3 | Can delete |
| Debug Utilities | 13 | Can delete |
| Migrations/Setup | 8 | Keep for reference |
| Old Backups | 2 | Can delete |
| Recent Backups | 1 | Can delete |
| **Total** | **27** | **Safely moved** |

---

**Last Updated:** 2026-06-14  
**Moved By:** Claude Code  
**Verification Status:** ✓ COMPLETE
