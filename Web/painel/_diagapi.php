<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_error_handler(function ($no, $str, $file, $line) {
    echo "[WARN $no] $str  em " . basename($file) . ":$line\n"; return true;
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true))
        echo "\n=== FATAL ===\n{$e['message']}\nem " . basename($e['file']) . ":{$e['line']}\n";
});
session_name('DDTPANEL');
@session_start();
$_SESSION['painel_admin'] = ['UserID' => 25, 'UserName' => 'usaimbot', 'Papel' => 'admin'];
$_GET['a'] = 'catalogo';
$_GET['q'] = 'Bast';
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/api.php';