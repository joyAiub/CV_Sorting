<?php
include '../config/db.php';

$indexes = [
    // candidates: every query filters by jd_id — critical
    "ALTER TABLE candidates ADD INDEX idx_jd_id (jd_id)",
    // candidates: filter/sort by shortlisted status
    "ALTER TABLE candidates ADD INDEX idx_shortlisted (shortlisted)",
    // candidates: combined filter used in get_candidates.php
    "ALTER TABLE candidates ADD INDEX idx_jd_shortlisted (jd_id, shortlisted)",
    // candidates: search by name
    "ALTER TABLE candidates ADD INDEX idx_name (name(50))",
    // candidates: default sort column
    "ALTER TABLE candidates ADD INDEX idx_created_at (created_at)",
    // job_list: lookup by jd_id on every dashboard open
    "ALTER TABLE job_list ADD INDEX idx_jd_id (jd_id)",
    // job_list: filter by status on index page
    "ALTER TABLE job_list ADD INDEX idx_status (status)",
    // job_list: filter by created_by
    "ALTER TABLE job_list ADD INDEX idx_created_by (created_by)",
    // prompts: GROUP BY jd_id in get_jobs.php derived table
    "ALTER TABLE prompts ADD INDEX idx_jd_id (jd_id)",
];

echo "<pre>\n";
foreach ($indexes as $sql) {
    try {
        $conn->query($sql);
        echo "OK: $sql\n";
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate key name')) {
            echo "SKIP (already exists): $sql\n";
        } else {
            echo "ERROR: {$e->getMessage()}\n  SQL: $sql\n";
        }
    }
}
echo "\nDone.\n</pre>";
