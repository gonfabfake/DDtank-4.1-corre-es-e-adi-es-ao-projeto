<?php
/* =====================================================================
   itens.php - catalogo e inventario
   ===================================================================== */
require_once __DIR__ . '/_lib.php';
$adm = admin_exigir();

$erro = null; $ok = null;

/* ---------------------------------------------------------------------
   Conceder item.
   Regras do servidor (Source Server\Game.Server):
     - SP_Users_Items_Add e a mesma procedure de PlayerBussiness.AddGoods
     - ItemID e IDENTITY, nunca informado
     - ValidDate 0 = nunca expira; Hole1..6 = -1 = furos fechados
     - BagType 0 (equipado) Place 0..30, 31..79 mochila, >=80 overflow
     - BagType 1 (PropBag) Place 0..48
   --------------------------------------------------------------------- */
function item_da_grade_para_ou_cabeca(int $grade): array {
    $gp = gp_para_grade($grade);
    return ['grade' => $grade, 'gp' => $gp ?? 0];
}

function primeiro_slot_livre(int $userID, int $bagType, int $ini, int $fim): ?int {
    $ocupados = array_column(db_query(
        "SELECT Place FROM Db_Tank41.dbo.Sys_Users_Goods
         WHERE UserID = ? AND BagType = ? AND IsExist = 1 AND Place >= ? AND Place <= ?",
        [$userID, $bagType, $ini, $fim]
    ), 'Place');
    for ($p = $ini; $p <= $fim; $p++) {
        if (!in_array($p, $ocupados, true)) return $p;
    }
    return null;
}

function conceder_item(int $userID, int $templateID, int $bagType, int $place, int $qtd): array {
    global $erro;

    $tpl = db_um(
        "SELECT TemplateID, Name, CategoryID, NeedLevel FROM Db_Tank41.dbo.Shop_Goods WHERE TemplateID = ?",
        [$templateID]
    );
    if (!$tpl) { $erro = "TemplateID $templateID nao existe no catalogo."; return []; }

    $conta = db_um("SELECT UserID, NickName, Grade FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$userID]);
    if (!$conta) { $erro = 'Conta nao encontrada.'; return []; }

    if ($bagType === 0 && $place < 31) {
        // Slot de equipamento: o CategoryID decide a casa, o slot vem do painel.
        $cat = (int)$tpl['CategoryID'];
        $esperado = $cat - 1;
        if ($esperado !== $place) {
            $erro = "Item de categoria $cat pertence ao slot $esperado (" . slot_nome($esperado) . '), nao ' . $place . '.';
            return [];
        }
    }

    // Ja existe este template na conta? reaproveita em vez de duplicar.
    $existente = db_um(
        "SELECT ItemID, Place FROM Db_Tank41.dbo.Sys_Users_Goods
         WHERE UserID = ? AND TemplateID = ? AND IsExist = 1 AND BagType = ?",
        [$userID, $templateID, $bagType]
    );

    if ($existente) {
        db_exec(
            "UPDATE Db_Tank41.dbo.Sys_Users_Goods
             SET Place = ?, Count = ?, ValidDate = 0, RemoveDate = GETDATE(), RemoveType = 0
             WHERE ItemID = ?",
            [$place, $qtd, $existente['ItemID']]
        );
        return [[$place, "reaproveitado o ItemID {$existente['ItemID']}"]];
    }

    // Libera o slot de destino: manda o ocupante para a mochila.
    $ocupante = db_um(
        "SELECT ItemID FROM Db_Tank41.dbo.Sys_Users_Goods
         WHERE UserID = ? AND BagType = ? AND Place = ? AND IsExist = 1",
        [$userID, $bagType, $place]
    );
    if ($ocupante) {
        $livre = primeiro_slot_livre($userID, 0, 31, 79);
        if ($livre !== null) {
            db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Goods SET Place = ?, RemoveDate = GETDATE(), RemoveType = 22
                     WHERE ItemID = ?", [$livre, $ocupante['ItemID']]);
            $ok .= "Item anterior do slot $place foi para a mochila (slot $livre). ";
        } else {
            db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Goods SET IsExist = 0, RemoveDate = GETDATE(), RemoveType = 30
                     WHERE ItemID = ?", [$ocupante['ItemID']]);
            $ok .= "Mochila cheia: item anterior do slot $place foi descartado. ";
        }
    }

    $novoId = 0;
    $params = [
        [$novoId, SQLSRV_PARAM_OUT],
        $userID, $templateID, $place, $qtd,
        1, '', 1,          // IsJudge, Color, IsExist
        0, 0, 0, 0, 0,     // StrengthenLevel, Attack/Defend/Luck/Agility Compose
        0,                 // IsBinds
    ];
    // Monta a chamada com parametros posicionais na ordem da procedure.
    $stmt = sqlsrv_prepare($conn, "{call Db_Tank41.dbo.SP_Users_Items_Add (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)}",
        $params);
    return $novoId;
}

/* --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_pode_escrever()) {
    csrf_validar();
    try {
        db_exec('BEGIN TRAN');
        $acao = (string)($_POST['acao'] ?? '');

        if ($acao === 'conceder') {
            $userID = (int)($_POST['userid'] ?? 0);
            $tid    = (int)($_POST['templateid'] ?? 0);
            $bag    = (int)($_POST['bagtype'] ?? 0);
            $place  = (int)($_POST['place'] ?? -1);
            $qtd    = max(1, min(9999, (int)($_POST['qtd'] ?? 1)));

            $livre = $place >= 0 ? $place : ($bag === 1 ? primeiro_slot_livre($userID, 1, 0, 48)
                                                       : primeiro_slot_livre($userID, 0, 31, 79));
            if ($livre === null) {
                $erro = 'Sem espaco livre nessa bolsa.';
            } else {
                [$itemID, $msg] = conceder_item($userID, $tid, $bag, $livre, $qtd);
                if (!$erro) {
                    $ok = "Item $tid (x$qtd) colocado no slot $livre. $msg";
                    auditoria('conceder_item', $userID, "TemplateID=$tid BagType=$bag Place=$livre Qtd=$qtd");
                }
            }
        }
        elseif ($acao === 'remover') {
            $itemID = (int)($_POST['itemid'] ?? 0);
            $userID = (int)($_POST['userid'] ?? 0);
            db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Goods
                     SET IsExist = 0, RemoveDate = GETDATE(), RemoveType = 1 WHERE ItemID = ? AND UserID = ?",
                    [$itemID, $userID]);
            $ok = "Item $itemID removido do inventario.";
            auditoria('remover_item', $userID, "ItemID=$itemID");
        }

        if ($erro) db_exec('ROLLBACK TRAN'); else db_exec('COMMIT TRAN');
    } catch (Throwable $ex) {
        try { db_exec('ROLLBACK TRAN'); } catch (Throwable $ignore) {}
        $erro = $ex->getMessage();
    }
}

/* --------------------------------------------------------------------- */
$alvo = (int)($_GET['u'] ?? $_POST['userid'] ?? 0);
$conta = $alvo ? db_um("SELECT UserID, NickName, Grade FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$alvo]) : null;

$busca = trim($_GET['q'] ?? '');
$cat   = trim($_GET['cat'] ?? '');
$itens = [];
if ($busca !== '' || $cat !== '') {
    $like = '%' . $busca . '%';
    if ($cat !== '') {
        $itens = db_query(
            "SELECT TOP 40 TemplateID, Name, CategoryID, Attack, Defence, Agility, Luck, Level, Quality, NeedLevel
             FROM Db_Tank41.dbo.Shop_Goods
             WHERE (Name LIKE ? OR CAST(TemplateID AS varchar(20)) = ?) AND (? = '' OR CategoryID = ?)
             ORDER BY Attack DESC, TemplateID",
            [$like, $busca, $cat, $cat === '' ? 0 : $cat]
        );
    } else {
        $itens = db_query(
            "SELECT TOP 40 TemplateID, Name, CategoryID, Attack, Defence, Agility, Luck, Level, Quality, NeedLevel
             FROM Db_Tank41.dbo.Shop_Goods
             WHERE Name LIKE ? OR CAST(TemplateID AS varchar(20)) = ?
             ORDER BY Attack DESC, TemplateID",
            [$like, $busca]
        );
    }
}

$inv = [];
if ($conta) {
    $inv = db_query(
        "SELECT g.ItemID, g.BagType, g.Place, g.TemplateID, g.Count, g.StrengthenLevel,
                s.Name, s.CategoryID, s.Quality
         FROM Db_Tank41.dbo.Sys_Users_Goods g
         LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = g.TemplateID
         WHERE g.UserID = ? AND g.IsExist = 1 AND g.Place >= 0
         ORDER BY g.BagType, g.Place",
        [$alvo]
    );
}

painel_cabecalho('Itens', 'itens.php');
if ($erro) painel_aviso($erro);
if ($ok)   painel_aviso($ok, 'bom');
?>
<form method="get" class="barra">
  <input name="q" value="<?= e($busca) ?>" placeholder="nome ou TemplateID do item">
  <select name="cat">
    <option value="">qualquer categoria</option>
    <?php
    $cats = db_query("SELECT ID, Name FROM Db_Tank41.dbo.Shop_Goods_Categorys ORDER BY ID");
    $selCat = (string)$cat;
    foreach ($cats as $c) {
        $id = (int)$c['ID'];
        echo '<option value="' . $id . '"' . ($selCat === (string)$id ? ' selected' : '') . '>'
           . $id . ' - ' . e($c['Name']) . '</option>';
    }
    ?>
  </select>
  <button type="submit">Buscar</button>
</form>

<?php if ($itens): ?>
<table class="lista">
  <tr><th>TemplateID</th><th>Nome</th><th>Cat</th><th>ATQ</th><th>DEF</th><th>AGI</th><th>LUK</th><th>Q</th></tr>
  <?php foreach ($itens as $i): ?>
  <tr>
    <td><b><?= (int)$i['TemplateID'] ?></b></td>
    <td><?= e($i['Name']) ?></td>
    <td><?= (int)$i['CategoryID'] ?></td>
    <td><?= (int)$i['Attack'] ?></td>
    <td><?= (int)$i['Defence'] ?></td>
    <td><?= (int)$i['Agility'] ?></td>
    <td><?= (int)$i['Luck'] ?></td>
    <td><?= (int)$i['Quality'] ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php elseif ($busca !== '' || $cat !== ''): ?>
<p class="mini">Nada encontrado.</p>
<?php endif; ?>

<?php if ($conta): ?>
<hr>
<h2>Inventario de <?= e($conta['NickName']) ?> <small>nivel <?= (int)$conta['Grade'] ?></small></h2>

<form method="post" class="card linha">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="conceder">
  <input type="hidden" name="userid" value="<?= (int)$conta['UserID'] ?>">
  <h3>Conceder item</h3>
  <label>TemplateID <input type="number" name="templateid" required></label>
  <label>Bolsa
    <select name="bagtype">
      <option value="0">Equipamento / mochila</option>
      <option value="1">Mochila de itens</option>
    </select>
  </label>
  <label>Slot <input type="number" name="place" value="-1" min="-1" max="79"><small>-1 = proximo livre</small></label>
  <label>Qtd <input type="number" name="qtd" value="1" min="1" max="9999"></label>
  <button type="submit">Conceder</button>
</form>

<table class="lista">
  <tr><th>ItemID</th><th>Bolsa</th><th>Slot</th><th>Item</th><th>Qtd</th><th>+Forja</th><th></th></tr>
  <?php foreach ($inv as $g): ?>
  <tr>
    <td><?= (int)$g['ItemID'] ?></td>
    <td><?= (int)$g['BagType'] ?></td>
    <td><?= (int)$g['Place'] ?> <small><?= e(slot_nome((int)$g['Place'])) ?></small></td>
    <td><?= e($g['Name'] ?? ('#' . $g['TemplateID'])) ?> <small>#<?= (int)$g['TemplateID'] ?></small></td>
    <td><?= (int)$g['Count'] ?></td>
    <td><?= (int)$g['StrengthenLevel'] ?></td>
    <td><?php if (admin_pode_escrever()): ?>
      <form method="post" class="inline"
            onsubmit="return confirm('Remover este item do inventario?')">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="remover">
        <input type="hidden" name="itemid" value="<?= (int)$g['ItemID'] ?>">
        <input type="hidden" name="userid" value="<?= (int)$conta['UserID'] ?>">
        <button class="mini-btn" type="submit">remover</button>
      </form>
    <?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p><a href="index.php?u=<?= (int)$conta['UserID'] ?>">&larr; Voltar para a conta</a></p>
<?php endif; ?>

<?php painel_rodape(); ?>