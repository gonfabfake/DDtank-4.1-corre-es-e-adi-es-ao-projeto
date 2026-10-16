<?php
/* =====================================================================
   entrar.php - login do painel com a conta do JOGO
   A senha e a mesma do portal (Db_Membership), formato MD5 maiusculo,
   que e o que Models/MemberData.php usa.
   ===================================================================== */
require_once __DIR__ . '/_lib.php';

$erro = null;
$preUser = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $preUser = trim($_POST['usuario'] ?? '');
    $senha   = (string)($_POST['senha'] ?? '');

    if (login_bloqueado($preUser)) {
        $erro = 'Muitas tentativas erradas para esta conta. Aguarde '
              . LOGIN_JANELA_MINUTOS . ' minutos.';
    } elseif ($preUser === '' || $senha === '') {
        $erro = 'Informe usuario e senha.';
    } else {
        // 1) Conta no portal
        $portal = db_um(
            "SELECT u.UserId, u.UserName, i.Password, i.IsLockedOut
             FROM Db_Membership.dbo.Mem_Users u
             LEFT JOIN Db_Membership.dbo.Mem_UserInfo i ON i.UserId = u.UserId
             WHERE LOWER(u.UserName) = LOWER(?)",
            [$preUser]
        );

        // 2) Senha (formato do site: strtoupper(md5(...)))
        $hashEsperado = strtoupper(md5($senha));
        $senhaOk = $portal && $portal['Password'] && hash_equals(strtoupper($portal['Password']), $hashEsperado);

        if (!$senhaOk) {
            registrar_tentativa($preUser, false);
            $erro = 'Usuario ou senha incorretos.';
        } else {
            // 3) Conta do jogo pelo mesmo UserName
            $jogo = usuario_por_nome($portal['UserName']);
            if (!$jogo) {
                registrar_tentativa($preUser, false);
                $erro = 'A conta existe no portal mas nao existe no jogo.';
            } else {
                // 4) Autorizacao no painel
                $adm = db_um(
                    "SELECT UserID, UserName, Papel FROM Db_Tank41.dbo.Admin_Usuarios
                     WHERE UserID = ? AND Ativo = 1",
                    [$jogo['UserID']]
                );
                if (!$adm) {
                    registrar_tentativa($preUser, false);
                    $erro = 'Esta conta nao tem acesso ao painel.';
                } else {
                    // Sucesso
                    session_regenerate_id(true);
                    $_SESSION['painel_admin'] = [
                        'UserID'   => (int)$adm['UserID'],
                        'UserName' => $adm['UserName'],
                        'Papel'    => $adm['Papel'],
                    ];
                    db_exec("UPDATE Db_Tank41.dbo.Admin_Usuarios SET UltimoAcesso = GETDATE() WHERE UserID = ?",
                            [$adm['UserID']]);
                    db_exec("INSERT INTO Db_Tank41.dbo.Admin_Auditoria (AdminID, AdminNome, Acao, Detalhe, IP)
                             VALUES (?, ?, 'login', ?, ?)",
                            [$adm['UserID'], $adm['UserName'], 'entrou no painel', painel_ip_cliente()]);
                    redirecionar('index.php');
                }
            }
        }
    }
}

painel_cabecalho('Entrar');
?>
<form method="post" class="card login">
  <?= csrf_campo() ?>
  <?php if ($erro): painel_aviso($erro); endif; ?>
  <label>Conta do jogo
    <input name="usuario" value="<?= e($preUser) ?>" autofocus autocomplete="username" required>
  </label>
  <label>Senha
    <input type="password" name="senha" autocomplete="current-password" required>
  </label>
  <button type="submit">Entrar</button>
</form>
<p class="nota">
  Use a conta do jogo (a mesma do portal). Se ainda nao definiu a senha,
  use <a href="primeiro_acesso.php">primeiro acesso</a>.
</p>
<?php painel_rodape();