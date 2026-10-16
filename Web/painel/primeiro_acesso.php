<?php
/* =====================================================================
   primeiro_acesso.php
   Define a senha da conta e (opcionalmente) promove a admin do painel.
   Exige um token de uso unico guardado FORA do docroot, em
   C:\DDTank4.1-main\secrets\painel_token.txt
   ===================================================================== */
require_once __DIR__ . '/_lib.php';

const TOKEN_FILE = __DIR__ . '/../../secrets/painel_token.txt';

$erro = null; $ok = null;

if (login_bloqueado('__primeiro_acesso__')) {
    painel_cabecalho('Primeiro acesso');
    painel_aviso('Muitas tentativas. Feche e abra o painel mais tarde.');
    painel_rodape();
    exit;
}

function token_disponivel(): bool {
    return is_file(TOKEN_FILE) && trim((string)file_get_contents(TOKEN_FILE)) !== '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    registrar_tentativa('__primeiro_acesso__', false); // conta toda tentativa

    $token   = trim((string)($_POST['token'] ?? ''));
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $senha1  = (string)($_POST['senha'] ?? '');
    $senha2  = (string)($_POST['senha2'] ?? '');
    $promover= !empty($_POST['promover']);

    $esperado = token_disponivel() ? trim((string)file_get_contents(TOKEN_FILE)) : '';

    if ($esperado === '' || !hash_equals($esperado, $token)) {
        $erro = 'Token invalido ou ja utilizado.';
    } elseif (strlen($senha1) < SENHA_MIN_CARACTERES) {
        $erro = 'A senha precisa de pelo menos ' . SENHA_MIN_CARACTERES . ' caracteres.';
    } elseif ($senha1 !== $senha2) {
        $erro = 'As duas senhas nao conferem.';
    } else {
        $portal = db_um(
            "SELECT u.UserId, u.UserName FROM Db_Membership.dbo.Mem_Users u
             WHERE LOWER(u.UserName) = LOWER(?)",
            [$usuario]
        );
        $jogo = $portal ? usuario_por_nome($portal['UserName']) : null;

        if (!$portal)      $erro = 'Conta nao encontrada no portal.';
        elseif (!$jogo)    $erro = 'Conta nao encontrada no jogo.';
        else {
            try {
                db_tx("BEGIN TRAN");
                // Formato de senha do site: MD5 em maiusculas, sem salt.
                db_exec(
                    "UPDATE Db_Membership.dbo.Mem_UserInfo
                     SET Password = ?, PasswordFormat = 1, PasswordSalt = 'MD5'
                     WHERE UserId = ?",
                    [strtoupper(md5($senha1)), $portal['UserId']]
                );
                // Senha de login do jogo tambem (Sys_Users_Detail.Password)
                db_exec("UPDATE Db_Tank41.dbo.Sys_Users_Detail SET Password = ? WHERE UserID = ?",
                        [strtoupper(md5($senha1)), $jogo['UserID']]);

                $msg = 'Senha de ' . $usuario . ' definida. ';
                if ($promover) {
                    db_exec(
                        "IF EXISTS (SELECT 1 FROM Db_Tank41.dbo.Admin_Usuarios WHERE UserID = ?)
                             UPDATE Db_Tank41.dbo.Admin_Usuarios SET Ativo = 1, Papel = 'admin' WHERE UserID = ?
                         ELSE
                             INSERT INTO Db_Tank41.dbo.Admin_Usuarios (UserID, UserName, Papel) VALUES (?, ?, 'admin')",
                        [$jogo['UserID'], $jogo['UserID'], $jogo['UserID'], $usuario]
                    );
                    $msg .= 'Conta promovida a admin do painel.';
                }
                db_tx("COMMIT TRAN");

                // Consome o token: uso unico.
                @file_put_contents(TOKEN_FILE, '');
                $ok = $msg . ' Agora faca login em entrar.php. Este token nao funciona de novo.';
            } catch (Throwable $ex) {
                try { db_tx("ROLLBACK TRAN"); } catch (Throwable $ignore) {}
                $erro = 'Falha ao gravar: ' . $ex->getMessage();
            }
        }
    }
}

painel_cabecalho('Primeiro acesso');

/* Ordem importa: num POST bem-sucedido o token JA foi consumido,
   entao testar "token disponivel" primeiro esconderia a mensagem de sucesso. */
if ($ok) {
    painel_aviso($ok, 'bom');
    echo '<p><a href="entrar.php">Ir para o login &rarr;</a></p>';
} elseif ($erro) {
    painel_aviso($erro);
} elseif (!token_disponivel()) {
    painel_aviso('Nenhum token disponivel. Rode novamente o script que gera o token.', 'aviso');
}
?>
<form method="post" class="card">
  <?= csrf_campo() ?>
  <label>Token
    <input name="token" required autocomplete="off">
    <small>Em C:\DDTank4.1-main\secrets\painel_token.txt</small>
  </label>
  <label>Conta do jogo
    <input name="usuario" value="<?= e($_POST['usuario'] ?? 'usaimbot') ?>" required>
  </label>
  <label>Nova senha
    <input type="password" name="senha" minlength="<?= SENHA_MIN_CARACTERES ?>" required autocomplete="new-password">
  </label>
  <label>Repetir senha
    <input type="password" name="senha2" minlength="<?= SENHA_MIN_CARACTERES ?>" required autocomplete="new-password">
  </label>
  <label class="check">
    <input type="checkbox" name="promover" value="1" checked> Promover a admin do painel
  </label>
  <button type="submit">Definir senha</button>
</form>
<p class="nota">O token e de uso unico e se invalida depois do primeiro uso.</p>
<?php painel_rodape();