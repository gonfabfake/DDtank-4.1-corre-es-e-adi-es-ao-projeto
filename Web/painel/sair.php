<?php
require_once __DIR__ . '/_lib.php';

if (admin_logado()) {
    db_exec("INSERT INTO Db_Tank41.dbo.Admin_Auditoria (AdminID, AdminNome, Acao, Detalhe, IP)
             VALUES (?, ?, 'logout', 'saiu do painel', ?)",
            [admin_dados()['UserID'], admin_dados()['UserName'], painel_ip_cliente()]);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
redirecionar('entrar.php');