<?php
$conn = new mysqli('localhost', 'root', '', 'cv_sorting');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Add view_my_task and view_all_task permissions to existing users
$result = $conn->query("SELECT id, role, permissions FROM users");

if ($result) {
    $updated_count = 0;
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $role = $row['role'];
        $perms = $row['permissions'] ? json_decode($row['permissions'], true) : [];
        if (!is_array($perms)) {
            $perms = [];
        }
        
        // Define default values for new permissions based on role
        if ($role === 'super-admin' || $role === 'admin' || $role === 'sub-admin') {
            $perms['view_my_task'] = true;
            $perms['view_all_task'] = true;
        } else {
            $perms['view_my_task'] = true;
            $perms['view_all_task'] = false;
        }
        
        $json_perms = json_encode($perms);
        $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE id = ?");
        $stmt->bind_param("si", $json_perms, $id);
        $stmt->execute();
        $stmt->close();
        $updated_count++;
    }
    echo "Successfully updated permissions for $updated_count users!\n";
} else {
    echo "Error querying users: " . $conn->error . "\n";
}

// Add allowed_viewers column to Job_List if not exists
$res = $conn->query("SHOW COLUMNS FROM Job_List LIKE 'allowed_viewers'");
if ($res && $res->num_rows > 0) {
    echo "Column allowed_viewers already exists in Job_List!\n";
} else {
    if ($conn->query("ALTER TABLE Job_List ADD COLUMN allowed_viewers TEXT DEFAULT NULL")) {
        echo "Column allowed_viewers successfully added to Job_List!\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}

$conn->close();
?>

