<?php
require 'c:/wamp64/www/CV_Sorting/config/db.php';

// PASTE YOUR SERPER.DEV API KEY HERE
$serper_key = 'YOUR_SERPER_API_KEY_HERE';

if ($serper_key === 'YOUR_SERPER_API_KEY_HERE') {
    die("Please edit this file and replace 'YOUR_SERPER_API_KEY_HERE' with your actual Serper.dev API key.\n");
}

$stmt = $conn->prepare("UPDATE config SET serper_api_key = ? WHERE id = 1");
$stmt->bind_param("s", $serper_key);

if ($stmt->execute()) {
    echo "Serper API key updated successfully! You can now delete this file.\n";
} else {
    echo "Error updating key: " . $conn->error . "\n";
}
?>
