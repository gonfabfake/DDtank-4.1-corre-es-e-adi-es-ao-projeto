<?php
/* =====================================================================
   index.php - contas: nivel, cupons e moedas
   ===================================================================== */
require_once __DIR__ . '/_lib.php';
$adm = admin_exigir();

$maxGrade = grade_maximo();
$erro = null; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (!admin_pode_escrever()) {
        painel_aviso('Seu papel e somente leitura.');
    } else {
        $acao  = (string)($_POST['acao'] ?? '');
        $alvo  = (int)($_POST['userid'] ?? 0);
        $antes = usuario_por_id($alvo);

        if (!$antes) {
            $erro = 'Conta nao encontrada.';
        } else {
            try {
                db_exec('BEGIN TRAN');

                /* ---- nivel: grava GP oficial da faixa ---- */
                if ($acao === 'nivel') {
                    $grade = (int)($_POST['grade'] ?? 0);
                    $gp = gp_para_grade($grade);
                    if ($gp === null) {
                        $erro = "Nivel invalido (1 a $maxGrade).";
                    } else {
                        db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Grade = ?, GP = ? WHERE UserID = ?",
                                [$grade, $gp, $alvo]);
                        $ok = "{$antes['NickName']}: nivel $antes[Grade] -> $grade (GP $gp).";
                        auditoria('set_nivel', $alvo, "grade {$antes['Grade']} -> $grade, GP = $gp");
                    }
                }

                /* ---- cupons (Money) ---- */
                elseif ($acao === 'cupons') {
                    $v = (int)($_POST['valor'] ?? 0);
                    if ($v < 0 || $v > CUPOM_MAX) {
                        $erro = 'Valor fora de 0 a ' . numero_br((string)CUPOM_MAX) . '.';
                    } else {
                        db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Money = ? WHERE UserID = ?", [$v, $alvo]);
                        $ok = "{$antes['NickName']}: cupons " . numero_br($antes['Money']) . ' -> ' . numero_br((string)$v) . '.';
                        auditoria('set_cupons', $alvo, "Money {$antes['Money']} -> $v");
                    }
                }

                /* ---- demais moedas ---- */
                elseif ($acao === 'moedas') {
                    $gold  = max(0, min(CUPOM_MAX, (int)($_POST['gold']  ?? 0)));
                    $offer = max(0, min(CUPOM_MAX, (int)($_POST['offer'] ?? 0)));
                    $medal = max(0, min(CUPOM_MAX, (int)($_POST['medal'] ?? 0)));
                    db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Gold = ?, Offer = ?, Medal = ? WHERE UserID = ?",
                            [$gold, $offer, $medal, $alvo]);
                    $ok = "{$antes['NickName']}: Gold/Offer/Medal atualizados.";
                    auditoria('set_moedas', $alvo, "Gold=$gold Offer=$offer Medal=$medal");
                }

                /* ---- promover / rebaixar admin do painel ---- */
                elseif ($acao === 'admin') {
                    $ativar = (int)($_POST['ativar'] ?? 0) === 1;
                    $papel  = $_POST['papel'] ?? 'leitura';
                    if (!in_array($papel, ['admin', 'leitura'], true)) $papel = 'leitura';

                    if (!$ativar) {
                        db_exec("DELETE FROM Db_Tank41.dbo.Admin_Usuarios WHERE UserID = ?", [$alvo]);
                        $ok = "{$antes['NickName']}: acesso ao painel removido.";
                        auditoria('revogar_admin', $alvo, 'removido de Admin_Usuarios');
                    } else {
                        db_exec(
                            "IF EXISTS (SELECT 1 FROM Db_Tank41.dbo.Admin_Usuarios WHERE UserID = ?)
                                 UPDATE Db_Tank41.dbo.Admin_Usuarios SET Ativo = 1, Papel = ? WHERE UserID = ?
                             ELSE
                                 INSERT INTO Db_Tank41.dbo.Admin_Usuarios (UserID, UserName, Papel) VALUES (?, ?, ?)",
                            [$alvo, $papel, $alvo, $alvo, $antes['NickName'], $papel]
                        );
                        $ok = "{$antes['NickName']}: admin do painel ($papel).";
                        auditoria('conceder_admin', $alvo, "papel = $papel");
                    }
                }

                /* ---- comandos de chat dentro do jogo ---- */
                elseif ($acao === 'chat') {
                    $ativar = (int)($_POST['ativar'] ?? 0) === 1;
                    if (!$ativar) {
                        db_exec("DELETE FROM Db_Tank41.dbo.Game_Commands WHERE UserID = ?", [$alvo]);
                        $ok = "{$antes['NickName']}: comandos de chat removidos.";
                        auditoria('chat_admin_off', $alvo, 'removido de Game_Commands');
                    } else {
                        db_exec(
                            "IF NOT EXISTS (SELECT 1 FROM Db_Tank41.dbo.Game_Commands WHERE UserID = ?)
                                 INSERT INTO Db_Tank41.dbo.Game_Commands (UserID, Commands) VALUES (?, 'AllCommands')",
                            [$alvo, $alvo]
                        );
                        $ok = "{$antes['NickName']}: comandos de chat liberados.";
                        auditoria('chat_admin_on', $alvo, "Game_Commands = AllCommands");
                    }
                }

                if ($erro) db_exec('ROLLBACK TRAN'); else db_exec('COMMIT TRAN');
            } catch (Throwable $ex) {
                try { db_exec('ROLLBACK TRAN'); } catch (Throwable $ignore) {}
                $erro = $ex->getMessage();
            }
        }
    }
}

function usuario_por_id(int $id): ?array {
    return db_um(
        "SELECT UserID, UserName, NickName, Grade, GP, Money, MoneyLock, Gold, Offer, Medal,
                LoginCount, LastDate
         FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = ?",
        [$id]
    );
}

$filtro = trim($_GET['q'] ?? '');
$busca = '%' . $filtro . '%';
$contas = db_query(
    "SELECT TOP 60 d.UserID, d.UserName, d.NickName, d.Grade, d.Money, d.Gold, d.Offer,
            d.LoginCount, d.LastDate,
            a.Papel, a.Ativo, CASE WHEN c.UserID IS NULL THEN 0 ELSE 1 END AS EhChatAdmin
     FROM Db_Tank41.dbo.Sys_Users_Detail d
     LEFT JOIN Db_Tank41.dbo.Admin_Usuarios a ON a.UserID = d.UserID
     LEFT JOIN Db_Tank41.dbo.Game_Commands  c ON c.UserID = d.UserID
     WHERE (? = '%%' OR d.UserName LIKE ? OR d.NickName LIKE ?)
     ORDER BY d.UserID",
    [$filtro, $busca, $busca]
);

$sel = null;
if (isset($_GET['u'])) $sel = usuario_por_id((int)$_GET['u']);

painel_cabecalho('Contas', 'index.php');
if ($erro) painel_aviso($erro);
if ($ok)   painel_aviso($ok, 'bom');
?>
<form method="get" class="barra">
  <input name="q" value="<?= e($filtro) ?>" placeholder="filtrar por usuario ou apelido">
  <button type="submit">Buscar</button>
  <?php if ($filtro): ?><a class="limpar" href="index.php">limpar</a><?php endif; ?>
</form>

<table class="lista">
  <tr><th>ID</th><th>Conta</th><th>Nivel</th><th>Cupons</th><th>Painel</th><th>Chat</th><th></th></tr>
  <?php foreach ($contas as $c): ?>
  <tr<?= (isset($sel) && $sel && $sel['UserID'] == $c['UserID']) ? ' class="sel"' : '' ?>>
    <td><?= (int)$c['UserID'] ?></td>
    <td><b><?= e($c['NickName']) ?></b><small><?= e($c['UserName']) ?></small></td>
    <td><?= (int)$c['Grade'] ?></td>
    <td><?= numero_br($c['Money']) ?></td>
    <td><?= $c['Ativo'] ? '<span class="tag sim">' . e($c['Papel']) . '</span>' : '<span class="nao">-</span>' ?></td>
    <td><?= $c['EhChatAdmin'] ? '<span class="tag sim">sim</span>' : '<span class="nao">-</span>' ?></td>
    <td><a href="?u=<?= (int)$c['UserID'] ?>&amp;q=<?= e($filtro) ?>">editar</a></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if ($sel): ?>
<hr>
<h2><?= e($sel['NickName']) ?> <small>UserID <?= (int)$sel['UserID'] ?></small></h2>
<div class="grade2">

<form method="post" class="card">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="nivel">
  <input type="hidden" name="userid" value="<?= (int)$sel['UserID'] ?>">
  <h3>Nivel</h3>
  <p class="mini">Atual: <b><?= (int)$sel['Grade'] ?></b> &middot; GP: <b><?= numero_br($sel['GP']) ?></b></p>
  <label>Novo nivel (1 a <?= $maxGrade ?>)
    <input type="number" name="grade" min="1" max="<?= $maxGrade ?>" value="<?= (int)$sel['Grade'] ?>">
  </label>
  <p class="mini">O GP e lido da tabela oficial <code>Db_Tank.dbo.LevelInfo</code>, nunca digitado a mao.</p>
  <button type="submit">Aplicar nivel</button>
</form>

<form method="post" class="card">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="cupons">
  <input type="hidden" name="userid" value="<?= (int)$sel['UserID'] ?>">
  <h3>Cupons (Money)</h3>
  <p class="mini">Atual: <b><?= numero_br($sel['Money']) ?></b> &middot; teto <?= numero_br((string)CUPOM_MAX) ?></p>
  <label>Valor
    <input type="number" name="valor" min="0" max="<?= CUPOM_MAX ?>" value="<?= (int)$sel['Money'] ?>">
  </label>
  <button type="submit">Aplicar cupons</button>
</form>

<form method="post" class="card">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="moedas">
  <input type="hidden" name="userid" value="<?= (int)$sel['UserID'] ?>">
  <h3>Outras moedas</h3>
  <label>Gold <input type="number" name="gold"  min="0" max="<?= CUPOM_MAX ?>" value="<?= (int)$sel['Gold'] ?>"></label>
  <label>Offer <input type="number" name="offer" min="0" max="<?= CUPOM_MAX ?>" value="<?= (int)$sel['Offer'] ?>"></label>
  <label>Medal <input type="number" name="medal" min="0" max="<?= CUPOM_MAX ?>" value="<?= (int)$sel['Medal'] ?>"></label>
  <button type="submit">Aplicar</button>
</form>

<form method="post" class="card">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="admin">
  <input type="hidden" name="userid" value="<?= (int)$sel['UserID'] ?>">
  <h3>Acesso</h3>
  <label class="check"><input type="checkbox" name="ativar" value="1"
      <?= ($contas && array_filter($contas, fn($c)=>$c['UserID']==$sel['UserID'] && $c['Ativo'])) ? 'checked' : '' ?>>
      Admin do painel</label>
  <label>Papel
    <select name="papel">
      <option value="admin">admin (pode alterar)</option>
      <option value="leitura">leitura</option>
    </select>
  </label>
  <button type="submit">Aplicar acesso</button>
</form>

<form method="post" class="card">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="chat">
  <input type="hidden" name="userid" value="<?= (int)$sel['UserID'] ?>">
  <h3>Comandos de chat no jogo</h3>
  <p class="mini">Insere em <code>Game_Commands</code> e libera
     <code>$ban$</code>, <code>$kick$</code>, <code>$mute$</code>,
     <code>$unban$</code>, <code>$bugbot$</code>.</p>
  <label class="check"><input type="checkbox" name="ativar" value="1"
      <?= ($contas && array_filter($contas, fn($c)=>$c['UserID']==$sel['UserID'] && $c['EhChatAdmin'])) ? 'checked' : '' ?>>
      Liberar comandos</label>
  <button type="submit">Aplicar</button>
</form>

</div>
<p><a href="itens.php?u=<?= (int)$sel['UserID'] ?>">Gerenciar itens desta conta &rarr;</a></p>
<?php endif; ?>

<?php painel_rodape(); ?>