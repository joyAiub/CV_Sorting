<?php
include('config/db.php');
$res = $conn->query("SELECT jd_id FROM Job_List WHERE jd_id LIKE 'JD79208%'");
while($row = $res->fetch_assoc()) {
    echo "JD: '" . $row['jd_id'] . "' (Length: " . strlen($row['jd_id']) . ")\n";
}
