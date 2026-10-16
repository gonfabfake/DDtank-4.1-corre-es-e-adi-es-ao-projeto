<?php
$m = substr((string)($_GET['m'] ?? ''), 0, 500);
$line = date('[H:i:s] ') . $m . "\n";
$targets = ['C:/Users/gonfa/AppData/Local/Temp/opencode/ddtank/dbg.log', __DIR__ . '/zz-dbg.log', 'C:/DDTank4.1-main/zz-dbg.log'];
$used = 'none';
if ($m !== '') {
    foreach ($targets as $t) {
        if (@file_put_contents($t, $line, FILE_APPEND | LOCK_EX) !== false) { $used = $t; break; }
    }
}
header('Content-Type: text/plain');
echo 'ok ' . $used;