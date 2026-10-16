<?php
// DIAGNOSTICO TEMPORARIO 2 - remover apos o uso.
ini_set('display_errors', 1);
error_reporting(E_ALL);
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        echo "\n=== ERRO FATAL ===\n{$e['message']}\nem {$e['file']}:{$e['line']}\n";
    }
});
set_error_handler(function ($no, $str, $file, $line) {
    echo "[WARN $no] $str  em $file:$line\n";
    return true;
});
echo "CWD antes: " . getcwd() . "\n";
echo "--- inclui index.php ---\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require __DIR__ . '/index.php';
$out = ob_get_clean();
echo "\n--- index.php terminou, " . strlen($out) . " bytes de saida ---\n";
echo "primeiros 300: " . substr(preg_replace('/\s+/', ' ', $out), 0, 300) . "\n";