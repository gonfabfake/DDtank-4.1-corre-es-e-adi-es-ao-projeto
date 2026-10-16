<?php
/* =====================================================================
   conta.php - ficha completa de uma conta
   Abas: Resumo | Itens | Mensagens | Acoes | Pets | Partidas
   ===================================================================== */
require_once __DIR__ . '/_lib.php';
admin_exigir();

/* ---------------------------------------------------------------------
   Busca por nome (jogo) ou apelido
   --------------------------------------------------------------------- */
$termo   = trim($_GET['q'] ?? '');
$conta   = null;
$buscaEm = 'game';

if ($termo !== '') {
    $achado = db_um(
        "SELECT TOP 1 UserID, UserName, NickName FROM Db_Tank41.dbo.Sys_Users_Detail
         WHERE UserName = ? OR NickName = ? OR CAST(UserID AS varchar(20)) = ?",
        [$termo, $termo, $termo]
    );
    if (!$achado) {
        // nao achou no jogo: tenta no portal (pode ser conta so do portal)
        $p = db_um(
            "SELECT TOP 1 UserId AS UserID, UserName FROM Db_Membership.dbo.Mem_Users
             WHERE UserName = ?", [$termo]);
        if ($p) {
            $achado = db_um(
                "SELECT UserID, UserName, NickName FROM Db_Tank41.dbo.Sys_Users_Detail
                 WHERE UserName = ?", [$p['UserName']]);
        }
    }
    $conta = $achado ?: null;
}

$uid = $conta ? (int)$conta['UserID'] : 0;
$aba = $_GET['t'] ?? 'resumo';
$abas = ['resumo'=>'Resumo','itens'=>'Itens','mensagens'=>'Mensagens',
         'acoes'=>'Acoes','pets'=>'Pets','partidas'=>'Partidas'];

/* --------------------------------------------------------------------- */
$d    = null; $vip = null; $guilda = null; $casamento = null;
$rank = null; $match = null; $ach = null; $fight = null; $conq = null;
$portal = null; $cartoes = 0; $gemas = 0;

if ($uid) {
    $d = db_um("SELECT * FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$uid]);
    $vip = db_um("SELECT * FROM Db_Tank41.dbo.Sys_VIP_Info WHERE UserID = ?", [$uid]);
    $guilda = db_um(
        "SELECT cu.DutyID, c.ConsortiaName AS Guilda, c.Level AS NivelGuilda
         FROM Db_Tank41.dbo.Consortia_Users cu
         LEFT JOIN Db_Tank41.dbo.Consortia c ON c.ConsortiaID = cu.ConsortiaID
         WHERE cu.UserID = ? AND cu.IsExist = 1", [$uid]);
    $casamento = db_um(
        "SELECT * FROM Db_Tank41.dbo.Marry_Info WHERE UserID = ?", [$uid]);
    $rank = db_um("SELECT * FROM Db_Tank41.dbo.Sys_User_Rank WHERE UserID = ?", [$uid]);
    $match = db_um("SELECT * FROM Db_Tank41.dbo.Sys_User_Match_Info WHERE UserID = ?", [$uid]);
    $fight = db_um("SELECT * FROM Db_Tank41.dbo.Sys_Users_Fight WHERE UserID = ?", [$uid]);
    $ach  = db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.AchievementData WHERE UserID = ? AND IsComplete = 1", [$uid]);
    $conq = db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.AchievementData WHERE UserID = ?", [$uid]);
    $cartoes = (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Card WHERE UserID = ?", [$uid]);
    $gemas   = (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_User_Gemstone WHERE UserID = ?", [$uid]);
    $portal  = db_um(
        "SELECT u.UserId, u.UserName, i.Email, i.CreateDate, i.LastLoginDate, i.PasswordSalt
         FROM Db_Membership.dbo.Mem_Users u
         LEFT JOIN Db_Membership.dbo.Mem_UserInfo i ON i.UserId = u.UserId
         WHERE u.UserName = ?", [$conta['UserName']]);
}

$abaAtual = isset($abas[$aba]) ? $aba : 'resumo';

/* --------------------------------------------------------------------- */
painel_cabecalho('Conta');
?>
<form method="get" class="barra busca">
  <input name="q" value="<?= e($termo) ?>" placeholder="digite o nome do usuario" autofocus>
  <button type="submit">Procurar</button>
  <?php if ($conta): ?>
    <a class="links" href="index.php?u=<?= $uid ?>">editar</a>
    <a class="links" href="itens.php?u=<?= $uid ?>">itens</a>
  <?php endif; ?>
</form>

<?php if ($termo !== '' && !$conta): ?>
  <div class="aviso">Nenhuma conta chamada "<?= e($termo) ?>" foi encontrada no jogo nem no portal.</div>
<?php endif; ?>

<?php if ($d): ?>
<div class="abas">
  <?php foreach ($abas as $k => $rot): ?>
    <a class="<?= $abaAtual === $k ? 'on' : '' ?>"
       href="?q=<?= urlencode($termo) ?>&t=<?= e($k) ?>"><?= e($rot) ?></a>
  <?php endforeach; ?>
</div>

<?php /* ============================ RESUMO ============================ */ ?>
<?php if ($abaAtual === 'resumo'): ?>
  <h2><?= e($d['NickName']) ?> <small>UserID <?= (int)$d['UserID'] ?> &middot; <?= e($d['UserName']) ?></small></h2>
  <div class="grade2">
    <div class="card">
      <h3>Progresso</h3>
      <table class="kv">
        <tr><td>Nivel</td><td><b><?= (int)$d['Grade'] ?></b> / <?= grade_maximo() ?></td></tr>
        <tr><td>GP (experiencia)</td><td><?= numero_br($d['GP']) ?></td></tr>
        <tr><td>Evolucao</td><td><?= (int)$d['evolutionGrade'] ?> (<?= numero_br($d['evolutionExp']) ?>)</td></tr>
        <tr><td>Honra</td><td><?= e($d['Honor']) ?></td></tr>
        <tr><td>Conquistas</td><td><?= (int)$ach ?> / <?= (int)$conq ?></td></tr>
        <tr><td>Emblemas</td><td><?= (int)$d['badgeID'] ?></td></tr>
        <tr><td>Pontos de湖区</td><td><?= (int)$d['AchievementPoint'] ?></td></tr>
      </table>
    </div>
    <div class="card">
      <h3>Moedas</h3>
      <table class="kv">
        <tr><td>Cupons (Money)</td><td><b><?= numero_br($d['Money']) ?></b></td></tr>
        <tr><td>Cupons bloqueados</td><td><?= numero_br($d['MoneyLock']) ?></td></tr>
        <tr><td>Gold</td><td><?= numero_br($d['Gold']) ?></td></tr>
        <tr><td>Merito (Offer)</td><td><?= numero_br($d['Offer']) ?></td></tr>
        <tr><td>Medal</td><td><?= numero_br($d['Medal']) ?></td></tr>
        <tr><td>GiftToken</td><td><?= numero_br($d['GiftToken']) ?></td></tr>
        <tr><td>hardCurrency</td><td><?= numero_br($d['hardCurrency']) ?></td></tr>
        <tr><td>myHonor</td><td><?= numero_br($d['myHonor']) ?></td></tr>
        <tr><td>Cash</td><td><?= numero_br($d['Cash']) ?> <small>(coluna morta)</small></td></tr>
      </table>
    </div>
    <div class="card">
      <h3>Combate</h3>
      <table class="kv">
        <tr><td>Vitórias / Total</td><td><?= (int)$d['Win'] ?> / <?= (int)$d['Total'] ?></td></tr>
        <tr><td>Escapou</td><td><?= (int)$d['Escape'] ?></td></tr>
        <tr><td>Fuga / Derrota</td><td><?= (int)$d['Escape'] ?> / <?= (int)$d['Total'] - (int)$d['Win'] ?></td></tr>
        <tr><td>FightPower</td><td><?= numero_br($d['FightPower']) ?></td></tr>
        <tr><td>EliteScore</td><td><?= numero_br($d['EliteScore']) ?></td></tr>
        <tr><td>Attack / Defence</td><td><?= $rank ? numero_br($rank['Attack']) . ' / ' . numero_br($rank['Defence']) : '-' ?></td></tr>
        <tr><td>Luck / Agility</td><td><?= $rank ? numero_br($rank['Luck']) . ' / ' . numero_br($rank['Agility']) : '-' ?></td></tr>
        <tr><td>HP / Dano / Guarda</td><td><?= $rank ? numero_br($rank['HP']) . ' / ' . numero_br($rank['Damage']) . ' / ' . numero_br($rank['Guard']) : '-' ?></td></tr>
        <tr><td>Liga (semana)</td><td><?= $match ? (int)$match['weeklyScore'] . ' pts, #' . (int)$match['weeklyRanking'] : '-' ?></td></tr>
      </table>
    </div>
    <div class="card">
      <h3>Conta e acesso</h3>
      <table class="kv">
        <tr><td>Criada em</td><td><?= e($d['Date']) ?></td></tr>
        <tr><td>Ultimo acesso</td><td><?= e($d['LastDate']) ?></td></tr>
        <tr><td>Logins</td><td><?= (int)$d['LoginCount'] ?></td></tr>
        <tr><td>Tempo online</td><td><?= numero_br($d['OnlineTime']) ?> min</td></tr>
        <tr><td>Ultimo IP</td><td><?= e($d['ActiveIP']) ?></td></tr>
        <tr><td>Bloqueado ate</td><td><?= e($d['ForbidDate']) ?> <small><?= e($d['ForbidReason']) ?></small></td></tr>
        <tr><td>VIP</td><td><?= $vip ? 'niv ' . (int)$vip['VIPLevel'] . ', exp ' . numero_br($vip['VIPExp']) : 'sem VIP' ?></td></tr>
        <tr><td>Guilda</td><td><?= $guilda ? e($guilda['Guilda']) . ' (cargo ' . (int)$guilda['DutyID'] . ')' : 'sem guilda' ?></td></tr>
        <tr><td>Casamento</td><td><?= $d['IsMarried'] ? 'com ' . e($d['SpouseName']) : 'solteiro' ?></td></tr>
        <tr><td>Cartoes / Gemas</td><td><?= $cartoes ?> / <?= $gemas ?></td></tr>
      </table>
    </div>
    <?php if ($portal): ?>
    <div class="card">
      <h3>Portal</h3>
      <table class="kv">
        <tr><td>UserID do portal</td><td><?= (int)$portal['UserId'] ?></td></tr>
        <tr><td>E-mail</td><td><?= e($portal['Email']) ?></td></tr>
        <tr><td>Criada</td><td><?= e($portal['CreateDate']) ?></td></tr>
        <tr><td>Ultimo login</td><td><?= e($portal['LastLoginDate']) ?></td></tr>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php /* ============================ ITENS ============================ */ ?>
<?php elseif ($abaAtual === 'itens'):
    $itens = db_query(
        "SELECT g.ItemID, g.BagType, g.Place, g.TemplateID, g.Count, g.StrengthenLevel,
                g.StrengthenExp, g.IsBinds, g.IsGold,
                s.Name, s.CategoryID, s.Attack, s.Defence, s.Agility, s.Luck, s.Quality
         FROM Db_Tank41.dbo.Sys_Users_Goods g
         LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = g.TemplateID
         WHERE g.UserID = ? AND g.IsExist = 1
         ORDER BY g.BagType, g.Place", [$uid]);
?>
  <h2>Itens de <?= e($d['NickName']) ?> <small><?= count($itens) ?> item(ns)</small></h2>
  <table class="lista">
    <tr><th>ItemID</th><th>Bolsa</th><th>Slot</th><th>Item</th><th>Qtd</th>
        <th>ATQ</th><th>DEF</th><th>AGI</th><th>LUK</th><th>Q</th><th>+Forja</th></tr>
    <?php foreach ($itens as $i): ?>
    <tr>
      <td><?= (int)$i['ItemID'] ?></td>
      <td><?= (int)$i['BagType'] ?></td>
      <td><?= (int)$i['Place'] ?> <small><?= e(slot_nome((int)$i['Place'])) ?></small></td>
      <td><?= e($i['Name'] ?? ('#' . $i['TemplateID'])) ?> <small>#<?= (int)$i['TemplateID'] ?></small></td>
      <td><?= (int)$i['Count'] ?></td>
      <td><?= $i['Attack'] !== null ? (int)$i['Attack'] : '-' ?></td>
      <td><?= $i['Defence'] !== null ? (int)$i['Defence'] : '-' ?></td>
      <td><?= $i['Agility'] !== null ? (int)$i['Agility'] : '-' ?></td>
      <td><?= $i['Luck'] !== null ? (int)$i['Luck'] : '-' ?></td>
      <td><?= $i['Quality'] !== null ? (int)$i['Quality'] : '-' ?></td>
      <td>+<?= (int)$i['StrengthenLevel'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

<?php /* ========================== MENSAGENS ========================== */ ?>
<?php elseif ($abaAtual === 'mensagens'):
    $msgs = db_query(
        "SELECT TOP 300 m.ID, m.SenderID, m.Sender, m.ReceiverID, m.Receiver,
                m.Title, m.Content, m.SendTime, m.Type, m.Gold, m.Money, m.GiftToken,
                m.IsRead, m.Annex1, m.Annex1Name, m.IsDelete, m.IsDelR
         FROM Db_Tank41.dbo.User_Messages m
         WHERE m.ReceiverID = ? OR m.SenderID = ?
         ORDER BY m.ID DESC", [$uid, $uid]);
    $soEnviadas  = db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.User_Messages WHERE ReceiverID = ?", [$uid]);
    $soRecebidas = db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.User_Messages WHERE SenderID = ?", [$uid]);
?>
  <h2>Mensagens de <?= e($d['NickName']) ?>
      <small><?= (int)$soEnviadas ?> recebidas &middot; <?= (int)$soRecebidas ?> enviadas</small></h2>
  <?php if (!$msgs): ?>
    <p class="mini">Nenhuma mensagem encontrada.</p>
  <?php else: ?>
  <table class="lista">
    <tr><th>#</th><th>Direcao</th><th>De / Para</th><th>Assunto</th><th>Conteudo</th>
        <th>Data</th><th>Tipo</th><th>Anexo</th></tr>
    <?php foreach ($msgs as $m): ?>
    <tr>
      <td><?= (int)$m['ID'] ?></td>
      <td><span class="tag <?= (int)$m['ReceiverID'] === $uid ? 'sim' : 'nao' ?>">
          <?= (int)$m['ReceiverID'] === $uid ? 'recebida' : 'enviada' ?></span></td>
      <td><b><?= e($m['Sender']) ?></b><small>para <?= e($m['Receiver']) ?></small></td>
      <td><?= e($m['Title']) ?></td>
      <td class="mini"><?= e(mb_substr((string)$m['Content'], 0, 220)) ?></td>
      <td class="mini"><?= e($m['SendTime']) ?></td>
      <td><?= (int)$m['Type'] ?></td>
      <td class="mini">
        <?php if ($m['Annex1Name']): ?><?= e($m['Annex1Name']) ?> (<?= e($m['Annex1']) ?>)<?php endif; ?>
        <?php if ((int)$m['Gold']): ?>Gold <?= (int)$m['Gold'] ?><?php endif; ?>
        <?php if ((int)$m['Money']): ?>Cupons <?= (int)$m['Money'] ?><?php endif; ?>
        <?php if (!$m['Annex1Name'] && !(int)$m['Gold'] && !(int)$m['Money']): ?>-<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

<?php /* ============================ ACOES ============================ */ ?>
<?php elseif ($abaAtual === 'acoes'):
    $logs = db_query(
        "SELECT TOP 300 l.ID, l.Type, l.Content, l.TimeCreate
         FROM Db_Tank41.dbo.Sys_Users_Log l
         WHERE l.UserID = ? ORDER BY l.ID DESC", [$uid]);
    $total = db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Log WHERE UserID = ?", [$uid]);
?>
  <h2>Acoes registradas <small><?= numero_br((string)$total) ?> no total</small></h2>
  <table class="lista">
    <tr><th>#</th><th>Tipo</th><th>Conteudo</th><th>Quando</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= (int)$l['ID'] ?></td>
      <td><b><?= e($l['Type']) ?></b></td>
      <td class="mini"><?= e($l['Content']) ?></td>
      <td class="mini"><?= e($l['TimeCreate']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

<?php /* ============================= PETS ============================ */ ?>
<?php elseif ($abaAtual === 'pets'):
    $pets = db_query(
        "SELECT * FROM Db_Tank41.dbo.Sys_Users_Pet WHERE UserID = ? AND IsExit = 0 ORDER BY Place", [$uid]);
?>
  <h2>Pets de <?= e($d['NickName']) ?> <small><?= count($pets) ?> pet(s)</small></h2>
  <?php if (!$pets): ?>
    <p class="mini">Nenhum pet.</p>
  <?php else: ?>
  <table class="lista">
    <tr><th>#</th><th>Template</th><th>Nome</th><th>Nivel</th><th>ATK</th><th>DEF</th>
        <th>AGI</th><th>LUK</th><th>Sangue</th><th>Slot</th><th>Fome</th></tr>
    <?php foreach ($pets as $p): ?>
    <tr>
      <td><?= (int)$p['ID'] ?></td>
      <td><?= (int)$p['TemplateID'] ?></td>
      <td><?= e($p['Name']) ?></td>
      <td><?= (int)$p['Level'] ?></td>
      <td><?= (int)$p['Attack'] ?></td>
      <td><?= (int)$p['Defence'] ?></td>
      <td><?= (int)$p['Agility'] ?></td>
      <td><?= (int)$p['Luck'] ?></td>
      <td><?= numero_br($p['Blood']) ?></td>
      <td><?= (int)$p['Place'] ?></td>
      <td><?= (int)$p['Hunger'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

<?php /* =========================== PARTIDAS ========================== */ ?>
<?php elseif ($abaAtual === 'partidas'):
    $fr = db_query(
        "SELECT TOP 100 * FROM Db_Tank41.dbo.Fight_Record
         WHERE TeamA LIKE ? OR TeamB LIKE ? ORDER BY ID DESC", ["%$uid%", "%$uid%"]);
    $lf = db_query(
        "SELECT TOP 100 * FROM Db_Count.dbo.Log_Fight
         WHERE TeamA LIKE ? OR TeamB LIKE ?", ["%$uid%", "%$uid%"]);
    $nFr = (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Fight_Record");
    $nLf = (int) db_valor("SELECT COUNT(*) FROM Db_Count.dbo.Log_Fight");
?>
  <h2>Partidas e resultados</h2>

  <div class="aviso aviso">
    <b>Este servidor nao grava replay.</b><br>
    Nao e falha do painel: o codigo que registraria as partidas esta desconectado
    no proprio servidor C#. As tabelas existem e estao vazias
    (<code>Fight_Record</code>: <?= $nFr ?> linhas, <code>Db_Count.Log_Fight</code>: <?= $nLf ?> linhas):
    <ul>
      <li><code>m_saveRecordTimer</code> e declarado mas nunca instanciado, entao <code>LogMgr.Save()</code> nunca roda;</li>
      <li><code>LogFightAdd</code> nao tem nenhum chamador;</li>
      <li>o evento <code>GameOverLog</code> nunca e assinado, entao o <code>return</code> inicial aborta sempre;</li>
      <li><code>LogMoneyAdd</code> (a sobrecarga realmente chamada) tem um <code>lock</code> vazio.</li>
    </ul>
    Alem disso, mesmo ligado, <code>Log_Fight.Detail</code> guardaria so atributos dos
    jogadores em partidas de boss &mdash; nao a sequencia de tiros. O projeto nao tem
    gravacao de acoes em lugar nenhum.
  </div>

  <h3>Db_Tank41.Fight_Record <small><?= count($fr) ?> registro(s) desta conta</small></h3>
  <?php if (!$fr): ?>
    <p class="mini">Nenhum registro.</p>
  <?php else: ?>
  <table class="lista">
    <tr><th>#</th><th>Inicio</th><th>Fim</th><th>Time A</th><th>Time B</th>
        <th>Mapa</th><th>Tipo</th><th>Nome</th><th>Vencedor</th></tr>
    <?php foreach ($fr as $f): ?>
    <tr>
      <td><?= (int)$f['ID'] ?></td>
      <td class="mini"><?= e($f['PlayBegin']) ?></td>
      <td class="mini"><?= e($f['PlayEnd']) ?></td>
      <td class="mini"><?= e($f['TeamA']) ?></td>
      <td class="mini"><?= e($f['TeamB']) ?></td>
      <td><?= (int)$f['MapID'] ?></td>
      <td><?= (int)$f['RoomType'] ?></td>
      <td class="mini"><?= e($f['FightName']) ?></td>
      <td><?= (int)$f['WinTeam'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <h3>Db_Count.Log_Fight <small><?= count($lf) ?> registro(s) desta conta</small></h3>
  <?php if (!$lf): ?>
    <p class="mini">Nenhum registro.</p>
  <?php else: ?>
  <table class="lista">
    <tr><th>Sala</th><th>Inicio</th><th>Fim</th><th>Jogadores</th><th>Mapa</th><th>Vencedor</th></tr>
    <?php foreach ($lf as $f): ?>
    <tr>
      <td><?= (int)$f['RoomId'] ?></td>
      <td class="mini"><?= e($f['PlayBegin']) ?></td>
      <td class="mini"><?= e($f['PlayEnd']) ?></td>
      <td class="mini"><?= e($f['TeamA']) ?> <?= e($f['TeamB']) ?></td>
      <td><?= (int)$f['MapId'] ?></td>
      <td><?= (int)$f['WinTeam'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
<?php endif; ?>

<?php endif; /* fim if($d) */ ?>
<?php painel_rodape(); ?>