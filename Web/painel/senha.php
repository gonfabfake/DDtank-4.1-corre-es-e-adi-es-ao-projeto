<?php
require_once __DIR__ . '/_lib.php';
admin_exigir();

$erro = null; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $atual = (string)($_POST['atual'] ?? '');
    $nova  = (string)($_POST['nova'] ?? '');
    $rep   = (string)($_POST['rep'] ?? '');
    $adm   = admin_dados();

    if (strlen($nova) < SENHA_MIN_CARACTERES) {
        $erro = 'A nova senha precisa de pelo menos ' . SENHA_MIN_CARACTERES . ' caracteres.';
    } elseif ($nova !== $rep) {
        $erro = 'A confirmacao nao confere.';
    } else {
        // Confere a senha atual
        $portal = db_um(
            "SELECT u.UserId FROM Db_Membership.dbo.Mem_Users u
             JOIN Db_Membership.dbo.Mem_UserInfo i ON i.UserId = u.UserId
             WHERE LOWER(u.UserName) = LOWER(?)",
            [$adm['UserName']]
        );
        if (!$portal) {
            $erro = 'Conta nao encontrada no portal.';
        } else {
            $guard = db_valor(
                "SELECT Password FROM Db_Membership.dbo.Mem_UserInfo WHERE UserId = ?",
                [$portal['UserId']]
            );
            if (!$guard || !hash_equals(strtoupper($guard), strtoupper(md5($atual)))) {
                $erro = 'Senha atual incorreta.';
            } else {
                try {
                    db_tx('BEGIN TRAN');
                    db_exec("UPDATE Db_Membership.dbo.Mem_UserInfo SET Password = ? WHERE UserId = ?",
                            [strtoupper(md5($nova)), $portal['UserId']]);
                    db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Password = ? WHERE UserID = ?",
                            [strtoupper(md5($nova)), $adm['UserID']]);
                    db_tx('COMMIT TRAN');
                    db_exec("INSERT INTO Db_Tank41.dbo.Admin_Auditoria (AdminID, AdminNome, Acao, Detalhe, IP)
                             VALUES (?, ?, 'trocar_senha', 'senha alterada', ?)",
                            [$adm['UserID'], $adm['UserName'], painel_ip_cliente()]);
                    $ok = 'Senha alterada. Ela passa a valer tambem no portal.';
                } catch (Throwable $ex) {
                    try { db_tx('ROLLBACK TRAN'); } catch (Throwable $ignore) {}
                    $erro = $ex->getMessage();
                }
            }
        }
    }
}

painel_cabecalho('Trocar senha');
if ($erro) painel_aviso($erro);
if ($ok)   painel_aviso($ok, 'bom');
?>
<form method="post" class="card login">
  <?= csrf_campo() ?>
  <label>Senha atual
    <input type="password" name="atual" required autocomplete="current-password">
  </label>
  <label>Nova senha
    <input type="password" name="nova" minlength="<?= SENHA_MIN_CARACTERES ?>" required autocomplete="new-password">
  </label>
  <label>Repetir nova senha
    <input type="password" name="rep" minlength="<?= SENHA_MIN_CARACTERES ?>" required autocomplete="new-password">
  </label>
  <button type="submit">Alterar senha</button>
</form>
<p class="nota">
  A conta e a mesma do portal: a nova senha passa a valer em
  <code>http://127.0.0.1/</code> e no painel.
</p>
<?php painel_rodape(); ?>