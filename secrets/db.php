<?php
// Credenciais do SQL do front-end web.
// FICA DE PROPOSITO FORA DO DOCROOT (C:\DDTank4.1-main\Web) para que,
// mesmo que a raiz web vaze, a senha do banco nao vaze junto.
// Gerado em 2026-10-16 10:19:13
if (!defined('DDT_CREDENTIALS_LOADED')) {
    http_response_code(403);
    exit('Acesso negado.');
}
define('DDT_CREDENTIALS_LOADED', true);
return array(
    'UID'         => 'ddt_web',
    'PWD'         => 'oHM47=vHJ4maB@bAzNhQKQcwxKA+kB2n',
    'Database'    => 'Db_Membership',
    'CharacterSet'=> 'UTF-8',
);
