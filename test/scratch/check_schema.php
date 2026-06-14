<?php
$c = new mysqli("localhost", "root", "", "cv_sorting");
$r = $c->query("DESCRIBE candidates");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . "\n";
}
echo "\n--- Job_List ---\n";
$r2 = $c->query("DESCRIBE Job_List");
while ($row = $r2->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . "\n";
}
echo "\n--- users ---\n";
$r3 = $c->query("DESCRIBE users");
while ($row = $r3->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . "\n";
}
