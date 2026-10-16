<?php
/* =====================================================================
   _itens.php - regras de concessao de item (usado pela API JSON)
   ===================================================================== */

/* Menor slot LIVRE. Filtrar pelos slots OCUPADOS devolveria NULL
   justamente quando a bolsa esta vazia -- por isso a lista e gerada. */
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

/* Concede um item. Devolve [mensagem] ou [null, erro].
   Usa a SP_Users_Items_Add, a mesma procedure de PlayerBussiness.AddGoods. */
function conceder_item(int $userID, int $templateID, int $bagType, int $place, int $qtd): array {
    global $conn;

    $tpl = db_um(
        "SELECT TemplateID, Name, CategoryID FROM Db_Tank41.dbo.Shop_Goods WHERE TemplateID = ?",
        [$templateID]
    );
    if (!$tpl) return [null, "TemplateID $templateID nao existe no catalogo."];
    if (!db_um("SELECT UserID FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$userID])) {
        return [null, 'Conta nao encontrada.'];
    }

    $msgs = [];

    if ($bagType === 0 && $place < 31) {
        $esperado = (int)$tpl['CategoryID'] - 1;
        if ($esperado !== $place) {
            return [null, "Item de categoria {$tpl['CategoryID']} pertence ao slot $esperado ("
                       . slot_nome($esperado) . "), nao $place."];
        }
    }

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

    $agora  = date('Y-m-d H:i:s');
    $itemID = 0;

    $sql = '{call Db_Tank41.dbo.SP_Users_Items_Add (' . str_repeat('?,', 34) . '?)}';

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
    if ($stmt === false) return [null, 'prepare: ' . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE)];
    if (!sqlsrv_execute($stmt)) {
        $err = json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE);
        sqlsrv_free_stmt($stmt);
        return [null, 'execute: ' . $err];
    }
    sqlsrv_free_stmt($stmt);

    $msgs[] = "novo ItemID $itemID";
    return [implode('; ', $msgs)];
}

/* Normaliza datas: sqlsrv devolve datetime como DateTime, e JSON
   precisa de texto (o React nao sabe formatar Date). */
function data_txt($v): ?string {
    if ($v instanceof DateTimeInterface) return $v->format('Y-m-d H:i:s');
    return ($v === null || $v === '') ? null : (string)$v;
}

/* Normaliza uma linha de item para JSON.
   O catalogo nao tem ItemID/BagType/Place/Count (item ainda nao pertence a
   ninguem), entao esses campos ficam null em vez de virar 0 enganoso. */
function linha_item(array $g): array {
    $tem = fn($k) => array_key_exists($k, $g);
    return [
        'itemID'   => $tem('ItemID')   ? (int)$g['ItemID']   : null,
        'bag'      => $tem('BagType')  ? (int)$g['BagType']  : null,
        'slot'     => $tem('Place')    ? (int)$g['Place']    : null,
        'slotNome' => $tem('Place')    ? slot_nome((int)$g['Place']) : null,
        'template' => $tem('TemplateID') ? (int)$g['TemplateID'] : 0,
        'nome'     => $g['Name'] ?? null,
        'cat'      => isset($g['CategoryID']) ? (int)$g['CategoryID'] : 0,
        'qtd'      => $tem('Count')    ? (int)$g['Count']    : null,
        'forja'    => $tem('StrengthenLevel') ? (int)$g['StrengthenLevel'] : 0,
        'q'        => isset($g['Quality']) ? (int)$g['Quality'] : 0,
        'atq'      => isset($g['Attack'])      ? (int)$g['Attack']      : null,
        'def'      => isset($g['Defence'])     ? (int)$g['Defence']     : null,
        'agi'      => isset($g['Agility'])     ? (int)$g['Agility']     : null,
        'luk'      => isset($g['Luck'])        ? (int)$g['Luck']        : null,
        'pic'      => $g['Pic'] ?? null,
    ];
}