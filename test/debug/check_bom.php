<?php
$files = ['config/db.php', 'api/user_api.php', 'config/auth.php'];
foreach ($files as $f) {
    $c = file_get_contents($f);
    $bom = pack('H*', 'EFBBBF');
    if (substr($c, 0, 3) === $bom) {
        echo "$f: BOM DETECTED\n";
    } else {
        echo "$f: NO BOM\n";
    }
}
?>
