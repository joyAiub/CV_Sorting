<?php
ob_start();
error_reporting(E_ERROR | E_PARSE);
header("Content-Type: application/json");
include("../config/db.php");
include("../config/auth.php");
include("../config/mail_sender.php");

if (session_status() === PHP_SESSION_NONE) session_start();

function send_json($data) {
    ob_end_clean();
    echo json_encode($data);
    exit;
}

$data  = json_decode(file_get_contents("php://input"), true);
$jd_id = isset($data['jd_id']) ? trim($data['jd_id']) : '';

if (empty($jd_id)) {
    send_json(["status" => "error", "message" => "JD ID is required."]);
}

// Feedback submission only available through public review link
if (isset($_SESSION['user_id'])) {
    send_json(["status" => "error", "message" => "Feedback submission is only available through the public review link."]);
}

// Fetch job details + creator email (email lives in employees table, not users)
$stmt_job = $conn->prepare(
    "SELECT j.*, e.email AS creator_email, e.full_name AS creator_name
     FROM Job_List j
     LEFT JOIN users u ON j.created_by = u.username
     LEFT JOIN employees e ON u.employee_id = e.employee_id
     WHERE j.jd_id = ?"
);
$stmt_job->bind_param("s", $jd_id);
$stmt_job->execute();
$job = $stmt_job->get_result()->fetch_assoc();
$stmt_job->close();

if (!$job) {
    send_json(["status" => "error", "message" => "Task not found."]);
}

$creator_email = $job['creator_email'] ?? '';
if (empty($creator_email)) {
    send_json(["status" => "error", "message" => "Task creator email not found. Ensure the creator's employee profile has an email address."]);
}

// Fetch all candidates for this task (feedback columns must exist — run migration first)
try {
    $stmt_c = $conn->prepare(
        "SELECT id, name, email_id, total_experience, `match`, organization,
                feedback_comment, feedback_recommended, feedback_updated_at
         FROM candidates WHERE jd_id = ? ORDER BY rating DESC, `match` DESC"
    );
    $stmt_c->bind_param("s", $jd_id);
    $stmt_c->execute();
    $candidates_result = $stmt_c->get_result();
    $candidates = [];
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt_c->close();
} catch (Exception $e) {
    send_json(["status" => "error", "message" => "DB error fetching candidates. Run the migration script first: " . $e->getMessage()]);
}

if (empty($candidates)) {
    send_json(["status" => "error", "message" => "No candidates found for this task."]);
}

// Reviewer identity passed from the front-end (confirmed via welcome modal)
$reviewer_name  = isset($data['reviewer_name'])  ? trim($data['reviewer_name'])  : '';
$reviewer_email = isset($data['reviewer_email']) ? trim($data['reviewer_email']) : '';

// Record submission — prefer confirmed reviewer name, then concern person from job
$submitted_by = $reviewer_name ?: ($job['concern_person'] ?? 'Concern Person');
$submitted_at = date('Y-m-d H:i:s');
$new_count    = (int)($job['feedback_submission_count'] ?? 0) + 1;

try {
    $stmt_upd = $conn->prepare(
        "UPDATE Job_List SET feedback_submitted_at = ?, feedback_submitted_by = ?, feedback_submission_count = ? WHERE jd_id = ?"
    );
    $stmt_upd->bind_param("ssis", $submitted_at, $submitted_by, $new_count, $jd_id);
    $stmt_upd->execute();
    $stmt_upd->close();
} catch (Exception $e) {
    send_json(["status" => "error", "message" => "DB error recording submission. Run the migration script: " . $e->getMessage()]);
}

// Insert audit history record (non-fatal if table missing)
$snapshot_json = json_encode($candidates);
$task_id       = (int)$job['id'];
try {
    $stmt_hist = $conn->prepare(
        "INSERT INTO task_feedback_submission_history (task_id, submission_no, submitted_by, submitted_at, feedback_snapshot_json) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt_hist->bind_param("iisss", $task_id, $new_count, $submitted_by, $submitted_at, $snapshot_json);
    $stmt_hist->execute();
    $stmt_hist->close();
} catch (Exception $e) {
    // Non-fatal — submission counter already updated
}

// Fetch SMTP config
$mail_res    = $conn->query("SELECT * FROM mail_config LIMIT 1");
$mail_config = $mail_res ? $mail_res->fetch_assoc() : null;
if (!$mail_config) {
    send_json([
        "status"        => "warning",
        "message"       => "Mail config not found. Submission recorded but email not sent.",
        "submission_no" => $new_count,
        "submitted_at"  => $submitted_at
    ]);
}

// Override from address to system sender
$mail_config['from_email'] = 'rpa@mgi.org';
$mail_config['from_name']  = 'CV Sorting System';

// Aggregate stats
$total             = count($candidates);
$recommended_count = count(array_filter($candidates, function($c) { return $c['feedback_recommended'] == 1; }));
$with_feedback     = count(array_filter($candidates, function($c) { return !empty($c['feedback_comment']); }));

// Build candidate table rows
$table_rows = '';
foreach ($candidates as $i => $c) {
    $is_recommended = $c['feedback_recommended'] == 1;
    $badge = $is_recommended
        ? "<span style='background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:99px;font-size:0.7rem;font-weight:700;white-space:nowrap;'>&#10003; Recommended</span>"
        : "<span style='background:#f1f5f9;color:#94a3b8;padding:2px 10px;border-radius:99px;font-size:0.7rem;font-weight:700;'>—</span>";

    $match_val   = $c['match'] ?? '0';
    $match_clean = str_replace('%', '', $match_val);
    $match_color = (float)$match_clean >= 70 ? '#16a34a' : '#f59e0b';
    $match_disp  = (strpos($match_val, '%') !== false) ? $match_val : $match_val . '%';

    $feedback_text = nl2br(htmlspecialchars($c['feedback_comment'] ?? ''));
    $feedback_cell = $feedback_text ?: "<em style='color:#94a3b8;'>No feedback provided</em>";

    $updated = $c['feedback_updated_at']
        ? date('d M Y, h:i A', strtotime($c['feedback_updated_at']))
        : '—';

    $row_bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
    $table_rows .= "
        <tr style='border-bottom:1px solid #e2e8f0;background:{$row_bg};'>
            <td style='padding:10px 12px;font-size:0.8rem;'>
                <strong style='color:#1e293b;display:block;'>" . htmlspecialchars($c['name']) . "</strong>
                <span style='color:#64748b;font-size:0.7rem;'>" . htmlspecialchars($c['email_id']) . "</span>
            </td>
            <td style='padding:10px 12px;font-size:0.8rem;font-weight:700;color:{$match_color};'>{$match_disp}</td>
            <td style='padding:10px 12px;font-size:0.8rem;'>" . htmlspecialchars($c['total_experience']) . "y</td>
            <td style='padding:10px 12px;'>{$badge}</td>
            <td style='padding:10px 12px;font-size:0.8rem;color:#374151;max-width:280px;word-break:break-word;'>{$feedback_cell}</td>
            <td style='padding:10px 12px;font-size:0.7rem;color:#94a3b8;white-space:nowrap;'>{$updated}</td>
        </tr>";
}

$base_url = "http://10.201.26.53:8080";
$view_url = $base_url . "/view/dashboard.php?jd_id=" . urlencode($jd_id) . "&job_title=" . urlencode($job['job_title'] ?? '');

$subject = "Candidate Feedback Submitted: " . ($job['job_title'] ?? $jd_id) . " — Submission #{$new_count}";

$creator_name_display = htmlspecialchars($job['creator_name'] ?? $job['created_by'] ?? '');

$message = "
<div style='font-family:Arial,sans-serif;max-width:900px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:12px;'>
    <div style='background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:22px 24px;border-radius:10px;margin-bottom:20px;'>
        <h2 style='color:#fff;margin:0 0 4px;font-size:1.3rem;'>Candidate Feedback Submitted</h2>
        <p style='color:#c7d2fe;margin:0;font-size:0.83rem;'>
            Submission #{$new_count} &nbsp;|&nbsp; " . htmlspecialchars($job['job_title'] ?? '') . "
            &nbsp;|&nbsp; <strong style='color:#fff;'>" . htmlspecialchars($jd_id) . "</strong>
        </p>
    </div>

    <div style='display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;'>
        <div style='flex:1;min-width:110px;background:#f0fdf4;border:1px solid #bbf7d0;padding:14px;border-radius:10px;text-align:center;'>
            <div style='font-size:1.8rem;font-weight:900;color:#16a34a;line-height:1;'>{$recommended_count}</div>
            <div style='font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.05em;margin-top:4px;'>Recommended</div>
        </div>
        <div style='flex:1;min-width:110px;background:#eff6ff;border:1px solid #bfdbfe;padding:14px;border-radius:10px;text-align:center;'>
            <div style='font-size:1.8rem;font-weight:900;color:#2563eb;line-height:1;'>{$with_feedback}</div>
            <div style='font-size:0.68rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:0.05em;margin-top:4px;'>With Feedback</div>
        </div>
        <div style='flex:1;min-width:110px;background:#f8fafc;border:1px solid #e2e8f0;padding:14px;border-radius:10px;text-align:center;'>
            <div style='font-size:1.8rem;font-weight:900;color:#475569;line-height:1;'>{$total}</div>
            <div style='font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-top:4px;'>Total Candidates</div>
        </div>
    </div>

    <table style='width:100%;font-size:0.8rem;border-collapse:collapse;margin-bottom:20px;background:#f8fafc;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;'>
        <tr>
            <td style='padding:8px 14px;color:#64748b;font-weight:600;width:130px;'>Submitted By</td>
            <td style='padding:8px 14px;font-weight:700;color:#1e293b;'>" . htmlspecialchars($submitted_by) . "</td>
            <td style='padding:8px 14px;color:#64748b;font-weight:600;width:130px;'>Submitted At</td>
            <td style='padding:8px 14px;font-weight:700;color:#1e293b;'>" . date('d M Y, h:i A', strtotime($submitted_at)) . "</td>
        </tr>
        <tr style='border-top:1px solid #e2e8f0;'>
            <td style='padding:8px 14px;color:#64748b;font-weight:600;'>Task Creator</td>
            <td style='padding:8px 14px;font-weight:700;color:#4f46e5;'>{$creator_name_display}</td>
            <td style='padding:8px 14px;color:#64748b;font-weight:600;'>Submission #</td>
            <td style='padding:8px 14px;font-weight:700;color:#1e293b;'>{$new_count}</td>
        </tr>
    </table>

    <table style='width:100%;border-collapse:collapse;'>
        <thead>
            <tr style='background:#f1f5f9;'>
                <th style='padding:10px 12px;font-size:0.7rem;color:#475569;text-align:left;font-weight:700;'>Candidate</th>
                <th style='padding:10px 12px;font-size:0.7rem;color:#475569;text-align:left;font-weight:700;'>Match</th>
                <th style='padding:10px 12px;font-size:0.7rem;color:#475569;text-align:left;font-weight:700;'>Exp.</th>
                <th style='padding:10px 12px;font-size:0.7rem;color:#475569;text-align:left;font-weight:700;'>Recommendation</th>
                <th style='padding:10px 12px;font-size:0.7rem;color:#475569;text-align:left;font-weight:700;'>Feedback</th>
                <th style='padding:10px 12px;font-size:0.7rem;color:#475569;text-align:left;font-weight:700;'>Last Updated</th>
            </tr>
        </thead>
        <tbody>{$table_rows}</tbody>
    </table>

    <div style='margin-top:24px;padding-top:20px;border-top:1px solid #e2e8f0;text-align:center;'>
        <p style='font-size:0.85rem;color:#475569;margin-bottom:14px;'>Click below to open the full interactive dashboard:</p>
        <a href='{$view_url}' style='display:inline-block;background:#4f46e5;color:#ffffff;padding:12px 28px;text-decoration:none;border-radius:8px;font-weight:700;font-size:0.9rem;'>Open Dashboard</a>
    </div>

    <p style='font-size:0.72rem;color:#94a3b8;margin-top:20px;border-top:1px dotted #e2e8f0;padding-top:10px;text-align:center;'>
        Automated notification from CV Sorting System &bull; Submission #{$new_count} of {$jd_id}
    </p>
</div>";

$mailer = new SmtpMailer($mail_config);
$res    = $mailer->send($creator_email, $subject, $message);

if ($res) {
    send_json([
        "status"        => "success",
        "message"       => "Feedback submitted. Notification sent to {$creator_email}.",
        "submission_no" => $new_count,
        "submitted_at"  => $submitted_at
    ]);
} else {
    send_json([
        "status"        => "warning",
        "message"       => "Feedback recorded as submission #{$new_count}, but the email notification failed. Check SMTP settings.",
        "submission_no" => $new_count,
        "submitted_at"  => $submitted_at
    ]);
}