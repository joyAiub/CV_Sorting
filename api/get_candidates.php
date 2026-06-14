<?php
header("Content-Type: application/json");
include("../config/db.php");
include("../config/auth.php");

// Security check: Either logged in OR valid token provided
$is_public = false;
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    $jd_id = isset($_GET['jd_id']) ? $_GET['jd_id'] : '';
    
    if (!empty($jd_id)) {
        $stmt_v = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
        $stmt_v->bind_param("s", $jd_id);
        $stmt_v->execute();
        $res_v = $stmt_v->get_result();
        if ($res_v->num_rows > 0) {
            $is_public = true;
        } else {
            echo json_encode(["status" => "error", "message" => "Unauthorized access: Invalid JD ID.", "auth_failed" => true]);
            exit;
        }
        $stmt_v->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Unauthorized access: Login required.", "auth_failed" => true]);
        exit;
    }
}

$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$shortlisted_filter = isset($_GET['shortlisted']) ? $_GET['shortlisted'] : '';
$confirmation_filter = isset($_GET['confirmation']) ? $_GET['confirmation'] : '';
$job_title_filter = isset($_GET['job_title']) ? $_GET['job_title'] : '';
$jd_id_filter = isset($_GET['jd_id']) ? $_GET['jd_id'] : '';

$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';
$top_n = isset($_GET['top_n']) ? (int)$_GET['top_n'] : 0;

// Validate sort field and order to prevent SQL injection
$allowed_sort_fields = ['id', 'total_experience', 'expected_salary', 'rating', 'match', 'created_at'];
if ($top_n > 0) {
    // Top N always sorts by rating and match
    $sort_by = "`rating` DESC, `match`";
    $sort_order = "DESC";
    $limit = $top_n;
    $offset = 0;
} else {
    if (!in_array($sort_by, $allowed_sort_fields)) {
        $sort_by = 'created_at';
    }
    if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
        $sort_order = 'ASC';
    }
    // Only wrap by backticks if it's a single field name (no commas)
    if (strpos($sort_by, ',') === false) {
        $sort_by = "`$sort_by`";
    }
}

$where_clauses = [];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(name LIKE ? OR email_id LIKE ? OR skills LIKE ? OR organization LIKE ? OR phone LIKE ? OR location LIKE ? OR education LIKE ? OR educational_institute LIKE ? OR Previous_Companies LIKE ? OR Current_Position LIKE ? OR n8n_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sssssssssss";
}

if ($shortlisted_filter !== '') {
    $where_clauses[] = "shortlisted = ?";
    $params[] = (int)$shortlisted_filter;
    $types .= "i";
}

if ($confirmation_filter !== '') {
    $where_clauses[] = "confirmation = ?";
    $params[] = (int)$confirmation_filter;
    $types .= "i";
}

if ($job_title_filter !== '') {
    $where_clauses[] = "job_title = ?";
    $params[] = $job_title_filter;
    $types .= "s";
}

if ($jd_id_filter !== '') {
    $where_clauses[] = "jd_id = ?";
    $params[] = $jd_id_filter;
    $types .= "s";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM candidates" . $where_sql;
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get paginated data
$query = "SELECT * FROM candidates" . $where_sql . " ORDER BY $sort_by $sort_order LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

$final_params = array_merge($params, [$limit, $offset]);
$final_types = $types . "ii";

$stmt->bind_param($final_types, ...$final_params);
$stmt->execute();
$result = $stmt->get_result();

$candidates = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $candidates,
    "pagination" => [
        "current_page" => $page,
        "total_pages" => $total_pages,
        "total_records" => $total_rows,
        "limit" => $limit
    ]
]);

$stmt->close();
$conn->close();
?>
