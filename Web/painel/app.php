<?php
/* =====================================================================
   app.php - casca da aplicacao React do painel
   O PHP cuida de sessao, permissao e injeta os dados iniciais.
   ===================================================================== */
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_imgconfig.php';
$adm = admin_exigir();

$boot = [
    'user' => [
        'id'    => (int)$adm['UserID'],
        'nome'  => $adm['UserName'],
        'papel' => $adm['Papel'],
    ],
    'csrf'     => csrf_token(),
    'maxGrade' => grade_maximo(),
    'cupomMax' => CUPOM_MAX,
    'img'      => img_config_ler(),
];

// JSON seguro dentro de <script>
$bootJson = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Painel DDTank 4.1</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div id="root">
  <div class="boot">Carregando&hellip;</div>
</div>

<script src="assets/vendor/react.production.min.js"></script>
<script src="assets/vendor/react-dom.production.min.js"></script>
<script src="assets/vendor/htm.js"></script>
<script>window.BOOT = <?= $bootJson ?>;</script>
<script src="assets/app.js"></script>
</body>
</html>