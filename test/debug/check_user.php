<?php
$conn = mysqli_connect('localhost', 'root', '', 'cv_sorting');
$res = $conn->query("SELECT id, username, role FROM users WHERE username='097727'");
if ($row = $res->fetch_assoc()) {
    print_r($row);
} else {
    echo "User not found";
}
?>
