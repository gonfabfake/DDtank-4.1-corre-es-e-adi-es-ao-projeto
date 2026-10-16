<?php
/* =====================================================================
   itens.php - catalogo e inventario
   ===================================================================== */
require_once __DIR__ . '/_lib.php';
$adm = admin_exigir();

$erro = null; $ok = null;

/* ---------------------------------------------------------------------
   Menor slot LIVRE. Filtrar pelos slots OCUPADOS devolveria NULL
   justamente quando a bolsa esta vazia -- por isso a lista e gerada.
   --------------------------------------------------------------------- */
function primeiro_slot_livre(int $userID, int $bagType, int $ini, int $fim): ?int {
    $ocupados = array_map('intval', array_column(db_query(
        "SELECT Place FROM Db_Tank41.dbo.Sys_Users_Goods
         WHERE UserID = ? AND BagType = ? AND IsExist = 1 AND Place >= ? AND Place <= ?",
        [$userID, $bagType, $ini, $fim]
    ), 'Place'));
    for ($p = $ini; $p <= $fim; $p++) {
        if (!in_array($p, $ocupados, true)) return $p;
    }
    return null;
}

/* ---------------------------------------------------------------------
   Concede um item. Devolve [mensagem] ou [null, erro].
   Usa a SP_Users_Items_Add, a mesma procedure de PlayerBussiness.AddGoods.
   Regras: ItemID e IDENTITY, ValidDate 0 = nunca expira,
          Hole1..6 = -1 = furos fechados.
   --------------------------------------------------------------------- */
function conceder_item(int $userID, int $templateID, int $bagType, int $place, int $qtd): array {
    global $conn;

    $tpl = db_um(
        "SELECT TemplateID, Name, CategoryID, NeedLevel FROM Db_Tank41.dbo.Shop_Goods WHERE TemplateID = ?",
        [$templateID]
    );
    if (!$tpl) return [null, "TemplateID $templateID nao existe no catalogo."];

    if (!db_um("SELECT UserID FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$userID])) {
        return [null, 'Conta nao encontrada.'];
    }

    $msgs = [];

    if ($bagType === 0 && $place < 31) {
        // Slot de equipamento: quem decide a casa e o CategoryID.
        $esperado = (int)$tpl['CategoryID'] - 1;
        if ($esperado !== $place) {
            return [null, "Item de categoria {$tpl['CategoryID']} pertence ao slot $esperado ("
                       . slot_nome($esperado) . "), nao $place."];
        }
    }

    // Ja existe este template na conta? reaproveita em vez de duplicar.
    $existente = db_um(
        "SELECT ItemID FROM Db_Tank41.dbo.Sys_Users_Goods
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
        return ['reaproveitado o ItemID ' . $existente['ItemID'] . ' (nao duplicou)'];
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
            db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Goods
                     SET Place = ?, RemoveDate = GETDATE(), RemoveType = 22 WHERE ItemID = ?",
                    [$livre, $ocupante['ItemID']]);
            $msgs[] = "item anterior do slot $place foi para a mochila (slot $livre)";
        } else {
            db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Goods
                     SET IsExist = 0, RemoveDate = GETDATE(), RemoveType = 30 WHERE ItemID = ?",
                    [$ocupante['ItemID']]);
            $msgs[] = 'mochila cheia: item anterior do slot ' . $place . ' foi descartado';
        }
    }

    $agora   = date('Y-m-d H:i:s');
    $itemID  = 0;

    $sql = '{call Db_Tank41.dbo.SP_Users_Items_Add ('
         . str_repeat('?,', 34) . '?)}';

    $params = [
        [$itemID, SQLSRV_PARAM_OUT],  //  1 @ItemID
        $userID,                      //  2 @UserID
        $templateID,                  //  3 @TemplateID
        $place,                       //  4 @Place
        $qtd,                         //  5 @Count
        1,                            //  6 @IsJudge
        '',                           //  7 @Color
        1,                            //  8 @IsExist
        0,                            //  9 @StrengthenLevel
        0,                            // 10 @AttackCompose
        0,                            // 11 @DefendCompose
        0,                            // 12 @LuckCompose
        0,                            // 13 @AgilityCompose
        0,                            // 14 @IsBinds
        $agora,                       // 15 @BeginDate
        0,                            // 16 @ValidDate  (0 = nunca expira)
        $bagType,                     // 17 @BagType
        '',                           // 18 @Skin
        0,                            // 19 @IsUsed
        0,                            // 20 @RemoveType
        -1, -1, -1, -1, -1, -1,       // 21-26 @Hole1..6  (-1 = fechados)
        0,                            // 27 @StrengthenTimes
        0,                            // 28 @Hole5Level
        0,                            // 29 @Hole5Exp
        0,                            // 30 @Hole6Level
        0,                            // 31 @Hole6Exp
        0,                            // 32 @IsGold
        0,                            // 33 @goldValidDate
        0,                            // 34 @StrengthenExp
        $agora,                       // 35 @goldBeginTime
    ];

    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if ($stmt === false) {
        return [null, 'prepare: ' . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE)];
    }
    if (!sqlsrv_execute($stmt)) {
        $err = json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE);
        sqlsrv_free_stmt($stmt);
        return [null, 'execute: ' . $err];
    }
    sqlsrv_free_stmt($stmt);

    $msgs[] = "novo ItemID $itemID";
    return [implode('; ', $msgs)];
}

/* --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_pode_escrever()) {
    csrf_validar();
    try {
        db_tx('BEGIN TRAN');
        $acao = (string)($_POST['acao'] ?? '');

        if ($acao === 'conceder') {
            $userID = (int)($_POST['userid'] ?? 0);
            $tid    = (int)($_POST['templateid'] ?? 0);
            $bag    = (int)($_POST['bagtype'] ?? 0);
            $place  = (int)($_POST['place'] ?? -1);
            $qtd    = max(1, min(9999, (int)($_POST['qtd'] ?? 1)));

            if (!in_array($bag, [0, 1], true)) {
                $erro = 'Bolsa invalida.';
            } else {
                $livre = $place >= 0 ? $place
                       : ($bag === 1 ? primeiro_slot_livre($userID, 1, 0, 48)
                                     : primeiro_slot_livre($userID, 0, 31, 79));
                if ($livre === null) {
                    $erro = 'Sem espaco livre nessa bolsa.';
                } elseif ($bag === 1 && $livre > 48) {
                    $erro = 'A bolsa de itens vai do slot 0 ao 48.';
                } elseif ($bag === 0 && $livre >= 80) {
                    $erro = 'Slot fora do limite (80 em diante vira overflow).';
                } else {
                    [$msg, $falha] = conceder_item($userID, $tid, $bag, $livre, $qtd);
                    if ($falha) {
                        $erro = $falha;
                    } else {
                        $ok = "Item $tid (x$qtd) no slot $livre - $msg";
                        auditoria('conceder_item', $userID, "TemplateID=$tid BagType=$bag Place=$livre Qtd=$qtd");
                    }
                }
            }
        }
        elseif ($acao === 'remover') {
            $itemID = (int)($_POST['itemid'] ?? 0);
            $userID = (int)($_POST['userid'] ?? 0);
            db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Goods
                     SET IsExist = 0, RemoveDate = GETDATE(), RemoveType = 1
                     WHERE ItemID = ? AND UserID = ?", [$itemID, $userID]);
            $ok = "Item $itemID removido do inventario.";
            auditoria('remover_item', $userID, "ItemID=$itemID");
        }

        if ($erro) db_tx('ROLLBACK TRAN'); else db_tx('COMMIT TRAN');
    } catch (Throwable $ex) {
        try { db_tx('ROLLBACK TRAN'); } catch (Throwable $ignore) {}
        $erro = $ex->getMessage();
    }
}

/* --------------------------------------------------------------------- */
$alvo  = (int)($_GET['u'] ?? $_POST['userid'] ?? 0);
$conta = $alvo ? db_um("SELECT UserID, NickName, Grade FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$alvo]) : null;

$busca = trim($_GET['q'] ?? '');
$cat   = trim($_GET['cat'] ?? '');
$itens = [];

if ($busca !== '' || $cat !== '') {
    $like = '%' . $busca . '%';
    if ($cat !== '') {
        $catInt = (int)$cat;
        $itens = db_query(
            "SELECT TOP 40 TemplateID, Name, CategoryID, Attack, Defence, Agility, Luck, Level, Quality
             FROM Db_Tank41.dbo.Shop_Goods
             WHERE (Name LIKE ? OR CAST(TemplateID AS varchar(20)) = ?) AND CategoryID = ?
             ORDER BY Attack DESC, TemplateID",
            [$like, $busca, $catInt]
        );
    } else {
        $itens = db_query(
            "SELECT TOP 40 TemplateID, Name, CategoryID, Attack, Defence, Agility, Luck, Level, Quality
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
    $cats     = db_query("SELECT ID, Name FROM Db_Tank41.dbo.Shop_Goods_Categorys ORDER BY ID");
    $selCat   = (string)$cat;
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

<?php if (admin_pode_escrever()): ?>
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
<?php endif; ?>

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