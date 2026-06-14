<?php
header("Content-Type: application/json");
include("../config/db.php");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "No data provided"]);
    exit;
}

// Extract all fields
$n8n_id = $data['n8n_id'] ?? null;
$job_title = $data['job_title'] ?? null;
$jd_id = $data['jd_id'] ?? null;
$name = $data['name'] ?? null;
$previous_companies = $data['previous_companies'] ?? null;
$current_position = $data['current_position'] ?? null;
$organization = $data['organization'] ?? null;
$education = $data['education'] ?? null;
$educational_institute = $data['educational_institute'] ?? null;
$total_experience = $data['total_experience'] ?? 0;
$expected_salary = $data['expected_salary'] ?? 0;
$date_of_birth = $data['date_of_birth'] ?? null;
$location = $data['location'] ?? null;
$phone = $data['phone'] ?? null;
$email_id = $data['email_id'] ?? null;
$skills = $data['skills'] ?? null;
$strength = $data['strength'] ?? null;
$weakness = $data['weakness'] ?? null;
$rating = $data['rating'] ?? 0;
$reason_for_rating = $data['reason_for_rating'] ?? null;
$shortlisted = isset($data['shortlisted']) ? ($data['shortlisted'] ? 1 : 0) : 0;
$confirmation = isset($data['confirmation']) ? ($data['confirmation'] ? 1 : 0) : 0;
$match = $data['match'] ?? null;
$language_proficiency = $data['language_proficiency'] ?? null;
$gender = $data['gender'] ?? null;
$blood_group = $data['blood_group'] ?? null;
$linkedin_link = $data['linkedin_link'] ?? null;
$github_link = $data['github_link'] ?? null;
$present_address = $data['present_address'] ?? null;
$permanent_address = $data['permanent_address'] ?? null;
$marital_status = $data['marital_status'] ?? null;
$father_name = $data['father_name'] ?? null;
$mother_name = $data['mother_name'] ?? null;
$reference_info = $data['reference_info'] ?? null;

if (!$n8n_id) {
    echo json_encode(["status" => "error", "message" => "n8n_id is required"]);
    exit;
}

// Prepare SQL (Total 32 Columns)
$sql = "INSERT INTO candidates 
    (
        n8n_id, job_title, jd_id, name, Previous_Companies, Current_Position, organization, 
        education, educational_institute, total_experience, expected_salary, date_of_birth, 
        location, phone, email_id, skills, strength, 
        weakness, rating, reason_for_rating, shortlisted, confirmation, 
        `match`, language_proficiency, gender, blood_group, linkedin_link, 
        github_link, present_address, permanent_address, marital_status, father_name, 
        mother_name, reference_info
    ) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    job_title = VALUES(job_title), jd_id = VALUES(jd_id), name = VALUES(name), Previous_Companies = VALUES(Previous_Companies), Current_Position = VALUES(Current_Position), organization = VALUES(organization), education = VALUES(education),
    educational_institute = VALUES(educational_institute), total_experience = VALUES(total_experience), expected_salary = VALUES(expected_salary), date_of_birth = VALUES(date_of_birth), location = VALUES(location),
    phone = VALUES(phone), email_id = VALUES(email_id), skills = VALUES(skills), strength = VALUES(strength), weakness = VALUES(weakness),
    rating = VALUES(rating), reason_for_rating = VALUES(reason_for_rating), shortlisted = VALUES(shortlisted), confirmation = VALUES(confirmation), `match` = VALUES(`match`),
    language_proficiency = VALUES(language_proficiency), gender = VALUES(gender), blood_group = VALUES(blood_group), linkedin_link = VALUES(linkedin_link), github_link = VALUES(github_link),
    present_address = VALUES(present_address), permanent_address = VALUES(permanent_address), marital_status = VALUES(marital_status), father_name = VALUES(father_name), mother_name = VALUES(mother_name), reference_info = VALUES(reference_info)";

$stmt = $conn->prepare($sql);

$types = "sssssssssddsssssssdsiissssssssssss";
$bind_args = [
    $n8n_id, $job_title, $jd_id, $name, $previous_companies, $current_position, $organization, 
    $education, $educational_institute, $total_experience, $expected_salary, $date_of_birth, 
    $location, $phone, $email_id, $skills, $strength, 
    $weakness, $rating, $reason_for_rating, $shortlisted, $confirmation, 
    $match, $language_proficiency, $gender, $blood_group, $linkedin_link, 
    $github_link, $present_address, $permanent_address, $marital_status, $father_name, 
    $mother_name, $reference_info
];

$stmt->bind_param($types, ...$bind_args);

if ($stmt->execute()) {
    $inserted_id = $stmt->insert_id;
    if ($inserted_id === 0) {
        $id_stmt = $conn->prepare("SELECT id FROM candidates WHERE n8n_id = ?");
        $id_stmt->bind_param("s", $n8n_id);
        $id_stmt->execute();
        $id_stmt->bind_result($existing_id);
        $id_stmt->fetch();
        $inserted_id = $existing_id;
        $id_stmt->close();
    }

    if ($jd_id) {
        $update_job_sql = "UPDATE Job_List 
                          SET total_candidate = (SELECT COUNT(*) FROM candidates WHERE jd_id = ?),
                              status = CASE 
                                  WHEN (status = 'Pending' OR status = '' OR status IS NULL) THEN 'In Progress' 
                                  ELSE status 
                              END
                          WHERE jd_id = ?";
        $count_stmt = $conn->prepare($update_job_sql);
        $count_stmt->bind_param("ss", $jd_id, $jd_id);
        $count_stmt->execute();
        $count_stmt->close();
    }

    // Fetch the complete record to return everything
    $fetch_stmt = $conn->prepare("SELECT * FROM candidates WHERE id = ?");
    $fetch_stmt->bind_param("i", $inserted_id);
    $fetch_stmt->execute();
    $full_data = $fetch_stmt->get_result()->fetch_assoc();
    $fetch_stmt->close();

    echo json_encode([
        "status" => "success",
        "data" => $full_data
    ]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}
$stmt->close();
$conn->close();
?>
