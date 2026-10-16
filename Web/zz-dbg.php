<?php
// TEMP DEBUG - apagar depois
require_once __DIR__ . '/global.php';
header('Content-Type: text/plain; charset=utf-8');
$u = isset($_GET['u']) ? $_GET['u'] : '';
$p = isset($_GET['p']) ? $_GET['p'] : '';
if ($u === '' || $p === '') { echo "usage: ?u=&p=\n"; exit; }
$m = new MemberData($u, $p);
$ok = $m->isAuthenticated();
echo "auth=" . ($ok ? 'true' : 'false') . "\n";
$errs = sqlsrv_errors();
if ($errs) { foreach ($errs as $e) { echo "sqlsrv: " . print_r($e, true) . "\n"; } }
else { echo "sqlsrv_errors=(none)\n"; }
