<?php
$_GET['jd_id'] = 'JD79208';
$_GET['token'] = 'c267dce4c9104cda3b1959a288431688';
$_SESSION = [];

// Move to view directory so includes work
chdir('view');

ob_start();
include('dashboard_modern.php');
$out = ob_get_clean();

if (preg_match('/DASHBOARD_DEBUG: is_public = (true|false)/', $out, $m)) {
    echo "RESULT: " . $m[0] . "\n";
} else {
    echo "JS LOG NOT FOUND\n";
    // Check if check_auth was called (it might have exited)
    if (empty($out)) {
         echo "OUTPUT IS EMPTY. Possible redirect and exit occurred.\n";
    } else {
         echo "FIRST 200 CHARS:\n" . substr($out, 0, 200) . "\n";
    }
}
