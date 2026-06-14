<?php
header("Content-Type: application/json");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../config/db.php");
include("../config/auth.php");
check_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'mail_list':
        $query = "SELECT employee_id, full_name, email, designation, department FROM employees WHERE email != '' ORDER BY full_name ASC";
        $res = $conn->query($query);
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(["status" => "success", "data" => $data]);
        break;

    case 'list':
        // Allow any authenticated user to view the list (for searching/sharing)
        if (!isset($_SESSION['username'])) {
            echo json_encode(["status" => "error", "message" => "Permission denied to view employees."]);
            exit;
        }
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $dept = isset($_GET['dept']) ? trim($_GET['dept']) : '';
        $loc = isset($_GET['loc']) ? trim($_GET['loc']) : '';
        $floor = isset($_GET['floor']) ? trim($_GET['floor']) : '';

        $query = "SELECT * FROM employees WHERE 1=1";
        $params = [];
        $types = "";

        if ($search !== '') {
            $query .= " AND (full_name LIKE ? OR employee_id LIKE ? OR ip_no LIKE ? OR sub_department LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ssss";
        }

        if ($dept !== '') {
            // Search in both department and sub_department for better matching
            $query .= " AND (department LIKE ? OR sub_department LIKE ?)";
            $deptParam = "%$dept%";
            $params[] = $deptParam;
            $params[] = $deptParam;
            $types .= "ss";
        }

        if ($loc !== '') {
            $query .= " AND office_location = ?";
            $params[] = $loc;
            $types .= "s";
        }

        if ($floor !== '') {
            $query .= " AND floor = ?";
            $params[] = $floor;
            $types .= "s";
        }

        $query .= " ORDER BY full_name ASC LIMIT 500"; // Limit for performance

        $stmt = $conn->prepare($query);
        if ($types !== "") {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode(["status" => "success", "data" => $data]);
        break;

    case 'get_filters':
        // Get unique depts, locations, and floors for filter dropdowns
        $depts = [];
        $locs = [];
        $floors = [];
        
        $res = $conn->query("SELECT DISTINCT department FROM employees WHERE department != '' AND department IS NOT NULL ORDER BY department ASC");
        while($row = $res->fetch_assoc()) $depts[] = $row['department'];

        // Optionally add sub-departments to the list if they are distinct
        $res = $conn->query("SELECT DISTINCT sub_department FROM employees WHERE sub_department != '' AND sub_department IS NOT NULL ORDER BY sub_department ASC");
        while($row = $res->fetch_assoc()) {
            if (!in_array($row['sub_department'], $depts)) {
                // We could add them, but maybe it's better to keep them separate in UI
            }
        }
        
        $res = $conn->query("SELECT DISTINCT office_location FROM employees WHERE office_location != '' AND office_location IS NOT NULL ORDER BY office_location ASC");
        while($row = $res->fetch_assoc()) $locs[] = $row['office_location'];

        $res = $conn->query("SELECT DISTINCT floor FROM employees WHERE floor != '' AND floor IS NOT NULL ORDER BY floor ASC");
        while($row = $res->fetch_assoc()) $floors[] = $row['floor'];
        
        echo json_encode(["status" => "success", "departments" => $depts, "locations" => $locs, "floors" => $floors]);
        break;

    case 'create':
    case 'update':
    case 'delete':
        // ONLY allow manage_employee_actions to create, update or delete
        if (!has_permission('manage_employee_actions')) {
            echo json_encode(["status" => "error", "message" => "Permission denied to perform employee actions."]);
            exit;
        }

        if ($action === 'delete') {
            $data = json_decode(file_get_contents("php://input"), true);
            $emp_id = $data['employee_id'];
    
            // Check if user exists for this employee
            $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmt->bind_param("s", $emp_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(["status" => "error", "message" => "Cannot delete employee as a user account is linked to it."]);
                exit;
            }
            $stmt->close();
    
            $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
            $stmt->bind_param("s", $emp_id);
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Employee deleted."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Delete failed."]);
            }
            $stmt->close();
            break;
        }

        // Handle Create/Update
        $data = json_decode(file_get_contents("php://input"), true);
        $emp_id = trim($data['employee_id']);
        $full_name = trim($data['full_name']);
        $email = trim($data['email']);
        $mobile = trim($data['mobile_no']);
        $designation = trim($data['designation']);
        $department = trim($data['department']);
        $sub_department = trim($data['sub_department'] ?? '');
        $ip_no = trim($data['ip_no']);
        $office_location = trim($data['office_location'] ?? '');
        $floor = trim($data['floor']);

        if (empty($emp_id) || empty($full_name)) {
            echo json_encode(["status" => "error", "message" => "Employee ID and Full Name are required."]);
            exit;
        }

        if ($action === 'create') {
            // Check if already exists
            $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ?");
            $stmt->bind_param("s", $emp_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(["status" => "error", "message" => "Employee ID already exists."]);
                exit;
            }
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO employees (employee_id, full_name, email, mobile_no, designation, department, sub_department, ip_no, office_location, floor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $emp_id, $full_name, $email, $mobile, $designation, $department, $sub_department, $ip_no, $office_location, $floor);
        } else {
            $stmt = $conn->prepare("UPDATE employees SET full_name = ?, email = ?, mobile_no = ?, designation = ?, department = ?, sub_department = ?, ip_no = ?, office_location = ?, floor = ? WHERE employee_id = ?");
            $stmt->bind_param("ssssssssss", $full_name, $email, $mobile, $designation, $department, $sub_department, $ip_no, $office_location, $floor, $emp_id);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Employee saved successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
        break;
}

$conn->close();
?>
