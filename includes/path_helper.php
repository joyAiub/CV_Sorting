<?php
/**
 * Path Helper - Centralized logic for file path resolution
 */
include_once(__DIR__ . "/../config/db.php");

if (!function_exists('getRpaConfigValue')) {
    function getRpaConfigValue($conn, $key, $default) {
        $stmt = $conn->prepare("SELECT value FROM rpa_config_app_system WHERE `key` = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $value = null;
        $stmt->bind_result($value);
        if ($stmt->fetch()) {
            $stmt->close();
            return $value;
        }
        $stmt->close();
        return $default;
    }
}

/**
 * Returns the absolute path to a processed file (CV or JD)
 * Handles case variations (TASK1, Task1, etc.)
 */
function get_processed_path($conn, $task_no, $sub_folder = "CV", $filename = null) {
    if (empty($task_no)) return null;

    $primary_base = getRpaConfigValue($conn, 'CV_PROCESSED_BASE_PATH', "C:\\Users\\admin\\.n8n-files\\Processed");
    $backup_base = "D:\\CV Data Backup (Don't Delete)\\CV Sorting Project\\Completed";
    
    $base_paths = [$primary_base, $backup_base];
    
    // Variations to check (useful for viewing)
    $folders_to_try = [
        $task_no,
        ucfirst(strtolower($task_no)),
        strtoupper($task_no),
        strtolower($task_no)
    ];

    foreach ($base_paths as $base_path) {
        if (empty($base_path)) continue;
        
        foreach (array_unique($folders_to_try) as $folder) {
            $dir_path = $base_path . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $sub_folder;
            
            // If we just need the directory (e.g. for moving/mkdir)
            if ($filename === null) {
                if (is_dir($dir_path)) return $dir_path;
            } else {
                // If we need a specific file (for viewing)
                $file_path = $dir_path . DIRECTORY_SEPARATOR . $filename;
                if (file_exists($file_path)) return $file_path;
            }
        }
    }

    // Default for creation if none exist
    return $base_path . DIRECTORY_SEPARATOR . $task_no . DIRECTORY_SEPARATOR . $sub_folder;
}

/**
 * Returns the absolute path for Manual CV/JD uploads.
 * e.g., C:\Users\admin\.n8n-files\Manual\TASK1\CVs
 */
function get_manual_upload_path($conn, $task_no, $sub_folder = "CVs") {
    if (empty($task_no)) return null;
    
    $base_path = getRpaConfigValue($conn, 'MANUAL_UPLOAD_BASE_PATH', "C:\\Users\\admin\\.n8n-files\\Manual");
    
    return $base_path . DIRECTORY_SEPARATOR . $task_no . DIRECTORY_SEPARATOR . $sub_folder;
}
?>
