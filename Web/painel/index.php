<?php
/* O painel agora e uma aplicacao React (app.php). Este arquivo permanece
   so para o IIS resolver /painel/ e /painel/index.php. */
require_once __DIR__ . '/_lib.php';
admin_exigir();
redirecionar('app.php');