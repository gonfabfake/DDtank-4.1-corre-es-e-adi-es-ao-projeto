<?php
// DIAGNOSTICO TEMPORARIO - remover apos o uso.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_error_handler(function ($no, $str, $file, $line) {
    echo "PHP-WARNING: $str  em $file:$line\n";
    return true;
});
echo "php.ini em uso: " . php_ini_loaded_file() . "\n";
echo "ext sqlsrv carregada: " . (extension_loaded('sqlsrv') ? 'SIM' : 'NAO') . "\n";
echo "caminho secrets existe: " . (is_file(__DIR__ . '/../secrets/db.php') ? 'SIM' : 'NAO') . "\n";
echo "--- incluindo global.php ---\n";
try {
    require __DIR__ . '/global.php';
    echo "global.php OK\n";
    echo "conn resource: " . (is_resource($conn) ? 'recurso ok' : var_export($conn, true)) . "\n";
    echo "UID em uso: " . (isset($config['UID']) ? $config['UID'] : '?') . "\n";
    echo "--- testando SELECT simples ---\n";
    $r = q("SELECT TOP 1 UserName FROM Mem_Users");
    if ($r === false) { echo "QUERY FALHOU\n"; print_r(sqlsrv_errors()); }
    else { $row = sqlsrv_fetch_array($r); echo "SELECT OK -> " . $row['UserName'] . "\n"; }
} catch (Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}