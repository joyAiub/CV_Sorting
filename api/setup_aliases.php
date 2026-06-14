<?php
// Setup script - run this once to create column_aliases table
include("../config/db.php");
include("../config/auth.php");
check_auth();

// Only allow super-admin
if ($_SESSION['role'] !== 'super-admin') {
    die(json_encode(["status" => "error", "message" => "Only super-admin can run this."]));
}

try {
    // Create table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS column_aliases (
        id INT PRIMARY KEY AUTO_INCREMENT,
        table_name VARCHAR(100) NOT NULL,
        actual_column_name VARCHAR(100) NOT NULL,
        alias VARCHAR(100) NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_alias (table_name, actual_column_name, alias),
        INDEX idx_table (table_name),
        INDEX idx_actual (actual_column_name)
    )
    ";

    $conn->query($createTableSQL);
    echo json_encode(["status" => "success", "message" => "Table created. Inserting aliases..."]);
    echo "<br>";

    // Insert aliases
    $aliases = [
        // employees table
        ["employees", "mobile_no", "phone_no", "Employee phone number"],
        ["employees", "mobile_no", "phone", "Employee phone"],
        ["employees", "mobile_no", "contact_no", "Employee contact number"],
        ["employees", "employee_id", "emp_id", "Employee ID"],
        ["employees", "employee_id", "emp_no", "Employee number"],
        ["employees", "full_name", "name", "Full name"],

        // candidates table
        ["candidates", "email_id", "email", "Candidate email"],
        ["candidates", "email_id", "mail", "Candidate email"],
        ["candidates", "total_experience", "experience", "Years of experience"],
        ["candidates", "total_experience", "exp", "Experience"],
        ["candidates", "jd_id", "job_id", "Job ID"],
        ["candidates", "jd_id", "job_code", "Job code"],
        ["candidates", "rating", "score", "Rating/score"],
        ["candidates", "rating", "mark", "Mark"],
        ["candidates", "name", "candidate_name", "Candidate name"],
        ["candidates", "organization", "company", "Company name"],

        // Job_List table
        ["Job_List", "task_no", "task_number", "Task number"],
        ["Job_List", "task_no", "task_id", "Task ID"],
        ["Job_List", "jd_id", "job_id", "Job ID"],
        ["Job_List", "jd_id", "job_code", "Job code"],
        ["Job_List", "status", "state", "Job state"],
        ["Job_List", "job_title", "title", "Job title"],
        ["Job_List", "created_at", "created_date", "Creation date"],
    ];

    $stmt = $conn->prepare("INSERT INTO column_aliases (table_name, actual_column_name, alias, description)
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE description=VALUES(description)");

    $inserted = 0;
    foreach ($aliases as $alias) {
        $stmt->bind_param("ssss", $alias[0], $alias[1], $alias[2], $alias[3]);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    $stmt->close();

    echo json_encode([
        "status" => "success",
        "message" => "Setup complete!",
        "aliases_inserted" => $inserted,
        "next_step" => "The chat agent will now use these aliases for semantic understanding"
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>
