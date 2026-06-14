<?php
header("Content-Type: application/json");
include("../config/db.php");
include("../config/auth.php");
include("../config/mail_sender.php");

// Basic Auth Check
if (session_status() === PHP_SESSION_NONE) session_start();

$data = json_decode(file_get_contents("php://input"), true);
$jd_id = $data['jd_id'] ?? '';
$token = $data['token'] ?? '';

if (empty($jd_id)) {
    echo json_encode(["status" => "error", "message" => "JD ID is required."]);
    exit;
}

// Security: Check if user is logged in or has a valid token for this JD
if (!isset($_SESSION['user_id'])) {
    $stmt_v = $conn->prepare("SELECT id FROM Job_List WHERE jd_id = ? AND view_token = ?");
    $stmt_v->bind_param("ss", $jd_id, $token);
    $stmt_v->execute();
    if ($stmt_v->get_result()->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
        exit;
    }
    $stmt_v->close();
}

// Fetch Job and Concern Details
$stmt_job = $conn->prepare("SELECT * FROM Job_List WHERE jd_id = ?");
$stmt_job->bind_param("s", $jd_id);
$stmt_job->execute();
$job_details = $stmt_job->get_result()->fetch_assoc();
$stmt_job->close();

// Auto-generate a token for old JDs that were created before the token system
if (!empty($job_details) && empty($job_details['view_token'])) {
    $new_token = bin2hex(random_bytes(16));
    $stmt_tok = $conn->prepare("UPDATE Job_List SET view_token = ? WHERE jd_id = ?");
    $stmt_tok->bind_param("ss", $new_token, $jd_id);
    $stmt_tok->execute();
    $stmt_tok->close();
    $job_details['view_token'] = $new_token;
}

$override_email = $data['override_email'] ?? null;
$target_email = $override_email ?: ($job_details['concern_email'] ?? '');

if (empty($target_email)) {
    echo json_encode(["status" => "error", "message" => "Target email recipient not found. Please select or assign a concern person."]);
    exit;
}

// Fetch Filter Params
$search = $data['search'] ?? '';
$shortlisted_filter = $data['shortlisted'] ?? '';
$confirmation_filter = $data['confirmation'] ?? '';
$top_n = isset($data['top_n']) ? (int)$data['top_n'] : 0;

// Build Query (Similar to get_candidates.php)
$where_clauses = ["jd_id = ?"];
$params = [$jd_id];
$types = "s";

if ($search !== '') {
    $where_clauses[] = "(name LIKE ? OR email_id LIKE ? OR skills LIKE ? OR organization LIKE ? OR phone LIKE ? OR location LIKE ? OR education LIKE ? OR educational_institute LIKE ?)";
    $search_param = "%$search%";
    for($i=0; $i<8; $i++) { $params[] = $search_param; $types .= "s"; }
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

$query = "SELECT * FROM candidates WHERE " . implode(" AND ", $where_clauses);
if ($top_n > 0) {
    $query .= " ORDER BY `rating` DESC, `match` DESC LIMIT $top_n";
} else {
    $query .= " ORDER BY `rating` DESC, `match` DESC";
}

$stmt_c = $conn->prepare($query);
$stmt_c->bind_param($types, ...$params);
$stmt_c->execute();
$candidates = $stmt_c->get_result();

if ($candidates->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "No candidates found with current filters."]);
    exit;
}

// Fetch Mail Config
$mail_res = $conn->query("SELECT * FROM mail_config LIMIT 1");
$mail_config = $mail_res->fetch_assoc();
if (!$mail_config) {
    echo json_encode(["status" => "error", "message" => "Mail server configuration not found."]);
    exit;
}

$mailer = new SmtpMailer($mail_config);

// Format Email Content
$filter_info = [];
if ($shortlisted_filter !== '') $filter_info[] = ($shortlisted_filter == '1' ? 'Shortlisted' : 'Not Shortlisted');
if ($confirmation_filter !== '') $filter_info[] = ($confirmation_filter == '1' ? 'Confirmed' : 'Not Confirmed');
if ($search) $filter_info[] = "Search: '$search'";
if ($top_n) $filter_info[] = "Top $top_n";
$filter_str = !empty($filter_info) ? implode(", ", $filter_info) : "All Candidates";

$subject = "Filtered Candidate List: " . $job_details['job_title'] . " (" . $jd_id . ")";

$table_rows = "";
while ($c = $candidates->fetch_assoc()) {
    $match_color = $c['match'] >= 80 ? '#10b981' : ($c['match'] >= 50 ? '#f59e0b' : '#ef4444');
    $match_display = str_replace('%', '', $c['match']);
    $table_rows .= "
        <tr style='border-bottom: 1px solid #e2e8f0;'>
            <td style='padding: 10px; font-size: 0.85rem;'>
                <strong>" . htmlspecialchars($c['name']) . "</strong><br>
                <span style='color: #64748b; font-size: 0.75rem;'>" . htmlspecialchars($c['email_id']) . "</span>
            </td>
            <td style='padding: 10px; font-size: 0.8rem;'>" . htmlspecialchars($c['location']) . "</td>
            <td style='padding: 10px; font-size: 0.8rem;'>" . htmlspecialchars($c['total_experience']) . " Yrs</td>
            <td style='padding: 10px; font-size: 0.8rem; font-weight: 700; color: $match_color;'>" . $match_display . "%</td>
            <td style='padding: 10px; font-size: 0.8rem;'>" . htmlspecialchars($c['organization']) . "</td>
            <td style='padding: 10px; font-size: 0.8rem;'>" . htmlspecialchars($c['skills']) . "</td>
        </tr>
    ";
}

$base_url = "http://10.201.26.53:8080";

// Look up the invited reviewer's employee_id by their email address
// This is embedded in the link so validate_reviewer.php can enforce strict identity
$stmt_re = $conn->prepare("SELECT employee_id, full_name FROM employees WHERE LOWER(email) = LOWER(?) LIMIT 1");
$stmt_re->bind_param("s", $target_email);
$stmt_re->execute();
$re_row = $stmt_re->get_result()->fetch_assoc();
$stmt_re->close();
$reviewer_emp_id   = $re_row['employee_id'] ?? '';
$concern_name_for_url = $re_row['full_name'] ?? ($job_details['concern_person'] ?? '');

// Sign the invited employee_id with HMAC so the URL token cannot be reversed or forged
$reid_token = !empty($reviewer_emp_id)
    ? hash_hmac('sha256', strtoupper($reviewer_emp_id) . '|' . $jd_id, REVIEWER_HMAC_SECRET)
    : '';

$view_url = $base_url . "/view/dashboard.php?jd_id=" . urlencode($jd_id)
    . "&job_title=" . urlencode($job_details['job_title'])
    . "&token="     . urlencode($job_details['view_token'])
    . "&rname="     . urlencode($concern_name_for_url)
    . "&reid="      . urlencode($reid_token);

$message = "
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
        <h2 style='color: #4f46e5; margin-bottom: 5px;'>Filtered Candidate List</h2>
        <p style='color: #64748b; margin-top: 0;'>Job: <strong>" . htmlspecialchars($job_details['job_title']) . "</strong> (" . $jd_id . ")</p>
        
        <div style='background: #f8fafc; padding: 12px; border-radius: 8px; margin: 15px 0; border: 1px solid #e2e8f0;'>
            <span style='font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;'>Filters Applied:</span>
            <span style='font-size: 0.9rem; color: #1e293b; margin-left: 10px; font-weight: 700;'>$filter_str</span>
        </div>

        <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
            <thead>
                <tr style='background: #f1f5f9; text-align: left;'>
                    <th style='padding: 10px; font-size: 0.75rem; color: #475569;'>Candidate</th>
                    <th style='padding: 10px; font-size: 0.75rem; color: #475569;'>Location</th>
                    <th style='padding: 10px; font-size: 0.75rem; color: #475569;'>Exp</th>
                    <th style='padding: 10px; font-size: 0.75rem; color: #475569;'>Match</th>
                    <th style='padding: 10px; font-size: 0.75rem; color: #475569;'>Organization</th>
                    <th style='padding: 10px; font-size: 0.75rem; color: #475569;'>Skills</th>
                </tr>
            </thead>
            <tbody>
                $table_rows
            </tbody>
        </table>

        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;'>
            <p style='font-size: 0.9rem; color: #475569;'>Click the button below to view the full interactive dashboard:</p>
            <a href='$view_url' style='display: inline-block; background: #4f46e5; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 10px;'>Open Full Dashboard</a>
        </div>
        
        <p style='font-size: 0.8rem; color: #94a3b8; margin-top: 30px; border-top: 1px dotted #e2e8f0; padding-top: 10px;'>This is an automated report from the CV Sorting System.</p>
    </div>
";

$res = $mailer->send($target_email, $subject, $message);

if ($res) {
    echo json_encode(["status" => "success", "message" => "Mail sent successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to send mail. Please check SMTP settings."]);
}
