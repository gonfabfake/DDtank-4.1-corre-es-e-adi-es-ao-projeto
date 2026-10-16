<?php
require_once __DIR__ . '/_lib.php';
admin_exigir();

$linhas = db_query(
    "SELECT TOP 200 a.ID, a.Quando, a.AdminNome, a.Acao, a.AlvoUser,
            ISNULL(d.NickName, '-') AS AlvoNome, a.Detalhe, a.IP
     FROM Db_Tank41.dbo.Admin_Auditoria a
     LEFT JOIN Db_Tank41.dbo.Sys_Users_Detail d ON d.UserID = a.AlvoUser
     ORDER BY a.ID DESC"
);

$tentativas = db_query(
    "SELECT TOP 40 Quando, UserName, IP, Sucesso
     FROM Db_Tank41.dbo.Admin_Tentativas ORDER BY ID DESC"
);

painel_cabecalho('Auditoria', 'auditoria.php');
?>
<h2>Historico de acoes</h2>
<table class="lista">
  <tr><th>Quando</th><th>Admin</th><th>Acao</th><th>Alvo</th><th>Detalhe</th><th>IP</th></tr>
  <?php foreach ($linhas as $l): ?>
  <tr>
    <td class="mini"><?= e($l['Quando']) ?></td>
    <td><?= e($l['AdminNome'] ?? '-') ?></td>
    <td><b><?= e($l['Acao']) ?></b></td>
    <td><?= e($l['AlvoNome']) ?><?= $l['AlvoUser'] ? ' <small>#' . (int)$l['AlvoUser'] . '</small>' : '' ?></td>
    <td class="mini"><?= e($l['Detalhe']) ?></td>
    <td class="mini"><?= e($l['IP']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<h2>Tentativas de login</h2>
<table class="lista">
  <tr><th>Quando</th><th>Conta</th><th>IP</th><th>Resultado</th></tr>
  <?php foreach ($tentativas as $t): ?>
  <tr>
    <td class="mini"><?= e($t['Quando']) ?></td>
    <td><?= e($t['UserName'] ?? '-') ?></td>
    <td class="mini"><?= e($t['IP']) ?></td>
    <td><?= $t['Sucesso'] ? '<span class="tag sim">ok</span>' : '<span class="tag nao">falhou</span>' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php painel_rodape();