<?php
/* =====================================================================
   api.php - API JSON do painel
   GET  ?a=contas&q=&cat=      -> lista de contas
   GET  ?a=conta&q=            -> ficha completa (com ?itens/mensagens/acoes/pets)
   GET  ?a=catalogo&q=&cat=    -> catalogo de itens
   GET  ?a=auditoria           -> log
   POST ?a=acao  {acao, ...}   -> executa acao (exige CSRF + papel admin)
   ===================================================================== */
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_itens.php';

admin_exigir();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $d, int $status = 200): void {
    http_response_code($status);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function falha(string $msg, int $status = 400): void {
    responder(['ok' => false, 'erro' => $msg], $status);
}

// Aceita 'a' na query string ou no corpo do POST.
$a = (string)($_GET['a'] ?? $_POST['a'] ?? '');

/* ============================ LEITURAS ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if ($a === 'bootstrap') {
        responder(['ok' => true, 'maxGrade' => grade_maximo(), 'cupomMax' => CUPOM_MAX,
                   'csrf' => csrf_token()]);
    }

    if ($a === 'contas') {
        $q    = trim((string)($_GET['q'] ?? ''));
        $like = '%' . $q . '%';
        responder(['ok' => true, 'contas' => db_query(
            "SELECT TOP 80 d.UserID, d.UserName, d.NickName, d.Grade, d.Money, d.Gold,
                    d.Offer, d.LoginCount, d.LastDate,
                    a.Papel, a.Ativo,
                    CASE WHEN c.UserID IS NULL THEN 0 ELSE 1 END AS ChatAdmin,
                    (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Goods g
                      WHERE g.UserID = d.UserID AND g.IsExist = 1) AS Itens
             FROM Db_Tank41.dbo.Sys_Users_Detail d
             LEFT JOIN Db_Tank41.dbo.Admin_Usuarios a ON a.UserID = d.UserID
             LEFT JOIN Db_Tank41.dbo.Game_Commands  c ON c.UserID = d.UserID
             WHERE (? = '' OR d.UserName LIKE ? OR d.NickName LIKE ?)
             ORDER BY d.UserID",
            [$q, $like, $like])]);
    }

    if ($a === 'conta') {
        $q = trim((string)($_GET['q'] ?? ''));
        $achado = db_um(
            "SELECT TOP 1 UserID, UserName, NickName FROM Db_Tank41.dbo.Sys_Users_Detail
             WHERE UserName = ? OR NickName = ? OR CAST(UserID AS varchar(20)) = ?",
            [$q, $q, $q]);
        if (!$achado) {
            $p = db_um("SELECT TOP 1 UserName FROM Db_Membership.dbo.Mem_Users WHERE UserName = ?", [$q]);
            if ($p) $achado = db_um("SELECT UserID, UserName, NickName FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserName = ?", [$p['UserName']]);
        }
        if (!$achado) falha("Nenhuma conta chamada \"$q\" foi encontrada.", 404);

        $uid = (int)$achado['UserID'];
        $d   = db_um("SELECT * FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$uid]);
        $d['Date']      = data_txt($d['Date']);
        $d['LastDate']  = data_txt($d['LastDate']);
        $d['ForbidDate']= data_txt($d['ForbidDate']);

        // Sys_Users_Detail nao tem estas colunas: vem de outras tabelas.
        $admRow = db_um("SELECT Papel FROM Db_Tank41.dbo.Admin_Usuarios WHERE UserID = ? AND Ativo = 1", [$uid]);
        $d['Papel']     = $admRow['Papel'] ?? null;
        $d['ChatAdmin'] = (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Game_Commands WHERE UserID = ?", [$uid]) > 0;

        $itens = array_map('linha_item', db_query(
            "SELECT g.ItemID, g.BagType, g.Place, g.TemplateID, g.Count, g.StrengthenLevel,
                    s.Name, s.CategoryID, s.Attack, s.Defence, s.Agility, s.Luck, s.Quality, s.Pic
             FROM Db_Tank41.dbo.Sys_Users_Goods g
             LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = g.TemplateID
             WHERE g.UserID = ? AND g.IsExist = 1 ORDER BY g.BagType, g.Place", [$uid]));

        $msgs = db_query(
            "SELECT TOP 300 m.ID, m.SenderID, m.Sender, m.ReceiverID, m.Receiver, m.Title,
                    m.Content, m.SendTime, m.Type, m.Gold, m.Money, m.Annex1Name, m.IsRead
             FROM Db_Tank41.dbo.User_Messages m
             WHERE m.ReceiverID = ? OR m.SenderID = ? ORDER BY m.ID DESC", [$uid, $uid]);
        foreach ($msgs as &$m) { $m['SendTime'] = data_txt($m['SendTime']); }
        unset($m);

        $acoes = db_query(
            "SELECT TOP 300 l.ID, l.Type, l.Content, l.TimeCreate
             FROM Db_Tank41.dbo.Sys_Users_Log l WHERE l.UserID = ? ORDER BY l.ID DESC", [$uid]);
        foreach ($acoes as &$x) { $x['TimeCreate'] = data_txt($x['TimeCreate']); }
        unset($x);

        $pets = db_query(
            "SELECT ID, TemplateID, Name, Level, Attack, Defence, Agility, Luck, Blood, Place, Hunger
             FROM Db_Tank41.dbo.Sys_Users_Pet WHERE UserID = ? AND IsExit = 0 ORDER BY Place", [$uid]);

        $guilda = db_um(
            "SELECT cu.DutyID, c.ConsortiaName AS Guilda, c.Level AS NivelGuilda
             FROM Db_Tank41.dbo.Consortia_Users cu
             LEFT JOIN Db_Tank41.dbo.Consortia c ON c.ConsortiaID = cu.ConsortiaID
             WHERE cu.UserID = ? AND cu.IsExist = 1", [$uid]);

        responder(['ok' => true, 'conta' => [
            'detalhe'    => $d,
            'vip'        => db_um("SELECT * FROM Db_Tank41.dbo.Sys_VIP_Info WHERE UserID = ?", [$uid]),
            'guilda'     => $guilda,
            'rank'       => db_um("SELECT * FROM Db_Tank41.dbo.Sys_User_Rank WHERE UserID = ?", [$uid]),
            'match'      => db_um("SELECT * FROM Db_Tank41.dbo.Sys_User_Match_Info WHERE UserID = ?", [$uid]),
            'fight'      => db_um("SELECT * FROM Db_Tank41.dbo.Sys_Users_Fight WHERE UserID = ?", [$uid]),
            'conquistas' => (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.AchievementData WHERE UserID = ? AND IsComplete = 1", [$uid]),
            'conqTotal'  => (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.AchievementData WHERE UserID = ?", [$uid]),
            'cartoes'    => (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Card WHERE UserID = ?", [$uid]),
            'gemas'      => (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_User_Gemstone WHERE UserID = ?", [$uid]),
            'portal'     => db_um(
                "SELECT u.UserId, i.Email, i.CreateDate, i.LastLoginDate
                 FROM Db_Membership.dbo.Mem_Users u
                 LEFT JOIN Db_Membership.dbo.Mem_UserInfo i ON i.UserId = u.UserId
                 WHERE u.UserName = ?", [$achado['UserName']]),
            'partidas'   => [
                'fightRecord' => (int) db_valor("SELECT COUNT(*) FROM Db_Tank41.dbo.Fight_Record"),
                'logFight'    => (int) db_valor("SELECT COUNT(*) FROM Db_Count.dbo.Log_Fight"),
                'registros'   => db_query(
                    "SELECT TOP 100 * FROM Db_Tank41.dbo.Fight_Record
                     WHERE TeamA LIKE ? OR TeamB LIKE ? ORDER BY ID DESC", ["%$uid%", "%$uid%"]),
            ],
            'itens'      => $itens,
            'mensagens'  => $msgs,
            'acoes'      => $acoes,
            'pets'       => $pets,
        ]]);
    }

    if ($a === 'catalogo') {
        $q   = trim((string)($_GET['q'] ?? ''));
        $cat = trim((string)($_GET['cat'] ?? ''));
        $like = '%' . $q . '%';
        $catInt = (int)$cat;
        $sql = "SELECT TOP 60 TemplateID, Name, CategoryID, Attack, Defence, Agility, Luck,
                       Level, Quality, NeedLevel, Pic
                FROM Db_Tank41.dbo.Shop_Goods
                WHERE (? = '' OR Name LIKE ? OR CAST(TemplateID AS varchar(20)) = ?)
                  AND (? = 0 OR CategoryID = ?)
                ORDER BY Attack DESC, TemplateID";
        responder(['ok' => true, 'itens' => array_map('linha_item',
            db_query($sql, [$q, $like, $q, $catInt, $catInt]))]);
    }

    if ($a === 'auditoria') {
        responder(['ok' => true, 'acoes' => db_query(
            "SELECT TOP 200 a.ID, a.Quando, a.AdminNome, a.Acao, a.AlvoUser,
                    ISNULL(d.NickName,'-') AS AlvoNome, a.Detalhe, a.IP
             FROM Db_Tank41.dbo.Admin_Auditoria a
             LEFT JOIN Db_Tank41.dbo.Sys_Users_Detail d ON d.UserID = a.AlvoUser
             ORDER BY a.ID DESC"),
            'tentativas' => db_query(
            "SELECT TOP 40 Quando, UserName, IP, Sucesso FROM Db_Tank41.dbo.Admin_Tentativas
             ORDER BY ID DESC")]);
    }

    falha("endpoint desconhecido: $a", 404);
}

/* ============================ ESCRITA ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a === 'acao') {
    csrf_validar();
    if (!admin_pode_escrever()) falha('Seu papel e somente leitura.', 403);

    $acao   = (string)($_POST['acao'] ?? '');
    $userID = (int)($_POST['userid'] ?? 0);
    $antes  = db_um("SELECT UserID, UserName, NickName, Grade, GP, Money, Gold, Offer, Medal
                     FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?", [$userID]);
    if (!$antes) falha('Conta nao encontrada.', 404);

    $detalhe = '';
    try {
        db_tx('BEGIN TRAN');

        switch ($acao) {
            case 'nivel':
                $grade = (int)($_POST['grade'] ?? 0);
                $gp = gp_para_grade($grade);
                if ($gp === null) { db_tx('ROLLBACK TRAN'); falha("Nivel invalido (1 a " . grade_maximo() . ').'); }
                db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Grade = ?, GP = ? WHERE UserID = ?",
                        [$grade, $gp, $userID]);
                $detalhe = "grade {$antes['Grade']} -> $grade (GP = $gp, lido de Db_Tank.dbo.LevelInfo)";
                break;

            case 'cupons':
                $v = (int)($_POST['valor'] ?? 0);
                if ($v < 0 || $v > CUPOM_MAX) { db_tx('ROLLBACK TRAN'); falha('Valor fora de 0 a ' . numero_br((string)CUPOM_MAX) . '.'); }
                db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Money = ? WHERE UserID = ?", [$v, $userID]);
                $detalhe = "Money {$antes['Money']} -> $v";
                break;

            case 'moedas':
                $gold  = max(0, min(CUPOM_MAX, (int)($_POST['gold']  ?? 0)));
                $offer = max(0, min(CUPOM_MAX, (int)($_POST['offer'] ?? 0)));
                $medal = max(0, min(CUPOM_MAX, (int)($_POST['medal'] ?? 0)));
                db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Gold=?, Offer=?, Medal=? WHERE UserID=?",
                        [$gold, $offer, $medal, $userID]);
                $detalhe = "Gold=$gold Offer=$offer Medal=$medal";
                break;

            case 'admin':
                $ativar = (int)($_POST['ativar'] ?? 0) === 1;
                $papel  = in_array($_POST['papel'] ?? '', ['admin','leitura'], true) ? $_POST['papel'] : 'leitura';
                if (!$ativar) {
                    db_exec("DELETE FROM Db_Tank41.dbo.Admin_Usuarios WHERE UserID = ?", [$userID]);
                    $detalhe = 'removido de Admin_Usuarios';
                } else {
                    db_exec("IF EXISTS (SELECT 1 FROM Db_Tank41.dbo.Admin_Usuarios WHERE UserID = ?)
                                UPDATE Db_Tank41.dbo.Admin_Usuarios SET Ativo = 1, Papel = ? WHERE UserID = ?
                            ELSE
                                INSERT INTO Db_Tank41.dbo.Admin_Usuarios (UserID, UserName, Papel) VALUES (?, ?, ?)",
                            [$userID, $papel, $userID, $userID, $antes['NickName'], $papel]);
                    $detalhe = "papel = $papel";
                }
                break;

            case 'chat':
                if ((int)($_POST['ativar'] ?? 0) !== 1) {
                    db_exec("DELETE FROM Db_Tank41.dbo.Game_Commands WHERE UserID = ?", [$userID]);
                    $detalhe = 'removido de Game_Commands';
                } else {
                    db_exec("IF NOT EXISTS (SELECT 1 FROM Db_Tank41.dbo.Game_Commands WHERE UserID = ?)
                                INSERT INTO Db_Tank41.dbo.Game_Commands (UserID, Commands) VALUES (?, 'AllCommands')",
                            [$userID, $userID]);
                    $detalhe = "Game_Commands = AllCommands (\$ban\$ \$kick\$ \$mute\$ \$unban\$ \$bugbot\$)";
                }
                break;

            case 'conceder': {
                $tid = (int)($_POST['templateid'] ?? 0);
                $bag = (int)($_POST['bagtype'] ?? 0);
                $plc = (int)($_POST['place'] ?? -1);
                $qtd = max(1, min(9999, (int)($_POST['qtd'] ?? 1)));
                if (!in_array($bag, [0, 1], true)) { db_tx('ROLLBACK TRAN'); falha('Bolsa invalida.'); }
                $livre = $plc >= 0 ? $plc
                       : ($bag === 1 ? primeiro_slot_livre($userID, 1, 0, 48)
                                     : primeiro_slot_livre($userID, 0, 31, 79));
                if ($livre === null) { db_tx('ROLLBACK TRAN'); falha('Sem espaco livre nessa bolsa.'); }
                if ($bag === 1 && $livre > 48) { db_tx('ROLLBACK TRAN'); falha('Bolsa de itens vai do slot 0 ao 48.'); }
                if ($bag === 0 && $livre >= 80) { db_tx('ROLLBACK TRAN'); falha('Slot fora do limite.'); }
                [$msg, $falha] = conceder_item($userID, $tid, $bag, $livre, $qtd);
                if ($falha) { db_tx('ROLLBACK TRAN'); falha($falha); }
                $detalhe = "TemplateID=$tid BagType=$bag Place=$livre Qtd=$qtd ($msg)";
                break;
            }

            case 'remover':
                $itemID = (int)($_POST['itemid'] ?? 0);
                db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Goods
                         SET IsExist = 0, RemoveDate = GETDATE(), RemoveType = 1
                         WHERE ItemID = ? AND UserID = ?", [$itemID, $userID]);
                $detalhe = "ItemID = $itemID";
                break;

            default:
                db_tx('ROLLBACK TRAN');
                falha("Acao desconhecida: $acao");
        }

        db_tx('COMMIT TRAN');
        auditoria($acao, $userID, $detalhe);
        responder(['ok' => true, 'msg' => "{$antes['NickName']}: $detalhe"]);
    } catch (Throwable $ex) {
        try { db_tx('ROLLBACK TRAN'); } catch (Throwable $i) {}
        falha($ex->getMessage(), 500);
    }
}

falha('metodo nao permitido.', 405);