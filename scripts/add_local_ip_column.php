<?php
include '../config/db.php';

echo "<pre>\n";

$sqls = [
    "ALTER TABLE users ADD COLUMN local_ip VARCHAR(100) NULL DEFAULT NULL AFTER last_ip",
];

foreach ($sqls as $sql) {
    try {
        $conn->query($sql);
        echo "OK: $sql\n";
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "SKIP (already exists): $sql\n";
        } else {
            echo "ERROR: {$e->getMessage()}\n  SQL: $sql\n";
        }
    }
}

echo "\nDone.\n</pre>";
?>
