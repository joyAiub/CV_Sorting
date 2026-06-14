<?php
include("../config/db.php");
include("../config/auth.php");

// Security check: Either logged in OR valid jd_id provided (for public mode)
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    $jd_id_param = isset($_GET['jd_id']) ? $_GET['jd_id'] : '';
    
    if (!empty($jd_id_param)) {
        $stmt_v = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ?");
        $stmt_v->bind_param("s", $jd_id_param);
        $stmt_v->execute();
        $res_v = $stmt_v->get_result();
        if ($res_v->num_rows === 0) {
            http_response_code(403);
            die("Unauthorized access: Invalid JD ID.");
        }
        $stmt_v->close();
    } else {
        http_response_code(401);
        die("Unauthorized access: Login required.");
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$shortlisted_filter = isset($_GET['shortlisted']) ? $_GET['shortlisted'] : '';
$confirmation_filter = isset($_GET['confirmation']) ? $_GET['confirmation'] : '';
$jd_id = isset($_GET['jd_id']) ? $_GET['jd_id'] : '';
$job_title = isset($_GET['job_title']) ? $_GET['job_title'] : '';

$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = (isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'ASC') ? 'ASC' : 'DESC';
$top_n = isset($_GET['top_n']) ? (int)$_GET['top_n'] : 0;

$where_clauses = [];
$params = [];
$types = "";

if ($jd_id !== '') {
    $where_clauses[] = "jd_id = ?";
    $params[] = $jd_id;
    $types .= "s";
} elseif ($job_title !== '') {
    $where_clauses[] = "job_title = ?";
    $params[] = $job_title;
    $types .= "s";
}

if ($search !== '') {
    $where_clauses[] = "(name LIKE ? OR email_id LIKE ? OR skills LIKE ? OR organization LIKE ? OR Previous_Companies LIKE ? OR Current_Position LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssssss";
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

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// Whitelist sorting fields
$allowed_sort = ['id', 'name', 'total_experience', 'expected_salary', 'rating', 'match', 'created_at'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'created_at';
}

$limit_sql = ($top_n > 0) ? " LIMIT " . (int)$top_n : "";

$query = "SELECT * FROM candidates" . $where_sql . " ORDER BY `$sort_by` $sort_order" . $limit_sql;
$stmt = $conn->prepare($query);

if ($types) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$filename = "candidates_export_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Set CSV headers
fputcsv($output, [
    'ID', 'N8N ID', 'Name', 'Location', 'DOB', 'Previous Companies', 'Current Position', 'Organization', 'Education', 'Institute',
    'Experience', 'Expected Salary', 'Phone',
    'Email', 'Skills', 'Strength', 'Weakness', 'Rating', 'Match', 'Reason', 'Shortlisted', 'Confirmation', 'Created At'
]);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'], $row['n8n_id'], $row['name'], $row['location'], $row['date_of_birth'],
        $row['Previous_Companies'], $row['Current_Position'], $row['organization'], $row['education'],
        $row['educational_institute'], $row['total_experience'], $row['expected_salary'],
        $row['phone'], $row['email_id'],
        $row['skills'], $row['strength'], $row['weakness'], $row['rating'], $row['match'],
        $row['reason_for_rating'], $row['shortlisted'] ? 'Yes' : 'No',
        $row['confirmation'] ? 'Yes' : 'No', $row['created_at']
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
?>
