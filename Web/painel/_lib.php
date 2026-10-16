<?php
/* =====================================================================
   _lib.php - nucleo do painel de administracao do DDTank 4.1

   Herda a conexao de global.php, que ja usa o login ddt_web
   (menos privilegios) em vez do sa.
   ===================================================================== */

require_once __DIR__ . '/../global.php';

/* ---------------------------------------------------------------------
   1. Enderecos autorizados a acessar o painel.
      Por padrao SOMENTE esta maquina. Para liberar a rede, adicione o IP
      aqui e reinicie o IIS nao e necessario (PHP le a cada requisicao).
   --------------------------------------------------------------------- */
const PAINEL_IP_LIBERADOS = [
    '127.0.0.1',
    '::1',
    // '10.141.8.243',   <- descomente para acessar de outro aparelho na rede
];

/* ---------------------------------------------------------------------
   2. Limite de forca bruta no login.
   --------------------------------------------------------------------- */
const LOGIN_MAX_TENTATIVAS   = 5;      // falhas liberadas...
const LOGIN_JANELA_MINUTOS   = 15;     // ...nesta janela
const SENHA_MIN_CARACTERES   = 6;

/* ---------------------------------------------------------------------
   3. Guarda de IP
   --------------------------------------------------------------------- */
function painel_ip_cliente(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Nao confie em X-Forwarded-For: o painel nao deve ficar atras de proxy.
    return $ip;
}

function painel_ip_permitido(): bool {
    return in_array(painel_ip_cliente(), PAINEL_IP_LIBERADOS, true);
}

function painel_bloquear_ip(string $motivo): void {
    http_response_code(403);
    $host = htmlspecialchars(painel_ip_cliente(), ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><meta charset='utf-8'><title>403</title>"
       . "<style>body{background:#1b1d22;color:#e6e6e6;font:15px/1.6 'Segoe UI',sans-serif;padding:48px}"
       . "h1{color:#e05c5c;font-size:20px}code{background:#2a2d34;padding:2px 7px;border-radius:4px}</style>"
       . "<h1>Acesso negado</h1><p>$motivo</p>"
       . "<p>Origem: <code>$host</code></p>"
       . "<p>Para liberar outro endereco, edite <code>PAINEL_IP_LIBERADOS</code> em "
       . "<code>_lib.php</code>.</p>";
    exit;
}

if (!painel_ip_permitido()) {
    painel_bloquear_ip('O painel so aceita conexoes da maquina local (127.0.0.1).');
}

/* ---------------------------------------------------------------------
   4. Sessao endurecida
   --------------------------------------------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/painel/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('DDTPANEL');
    @session_start();
}

/* ---------------------------------------------------------------------
   5. Utilidades de saida (escapar SEMPRE na hora de imprimir)
   --------------------------------------------------------------------- */
function e($v): string {
    // sqlsrv devolve colunas datetime como objetos DateTime, nao string.
    if ($v instanceof DateTimeInterface) {
        $v = $v->format('Y-m-d H:i:s');
    } elseif (is_bool($v)) {
        $v = $v ? '1' : '0';
    } elseif ($v === null) {
        $v = '';
    }
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirecionar(string $url): void {
    header('Location: ' . $url);
    exit;
}

/* ---------------------------------------------------------------------
   6. Banco: sempre com parametros vinculados (prepared statements).
      O codigo legado do site concatena string e e injectionavel --
      nao repita isso aqui.
   --------------------------------------------------------------------- */
function db_query(string $sql, array $params = []): array {
    global $conn;
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if ($stmt === false) {
        throw new RuntimeException('SQL: ' . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE));
    }
    if (!sqlsrv_execute($stmt)) {
        throw new RuntimeException('SQL: ' . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE));
    }
    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

/* Controle de transacao.
   Nao usar "BEGIN TRAN" como texto: com MARS habilitado o driver reclama
   ("A transaction that was started in a MARS batch is still active at the
   end of the batch") e a transacao morre no fim do batch.
   Use a API propria do driver. */
function db_tx(string $cmd): void {
    global $conn;
    $cmd = strtoupper(trim($cmd));
    $ok  = match ($cmd) {
        'BEGIN TRAN',    'BEGIN',   => sqlsrv_begin_transaction($conn),
        'COMMIT TRAN',   'COMMIT'   => sqlsrv_commit($conn),
        'ROLLBACK TRAN', 'ROLLBACK' => sqlsrv_rollback($conn),
        default          => throw new InvalidArgumentException("comando de transacao desconhecido: $cmd"),
    };
    if ($ok === false) {
        throw new RuntimeException('TX ' . $cmd . ': ' . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE));
    }
}

function db_um(string $sql, array $params = []): ?array {
    $r = db_query($sql, $params);
    return $r[0] ?? null;
}

function db_exec(string $sql, array $params = []): void {
    global $conn;
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if ($stmt === false || !sqlsrv_execute($stmt)) {
        throw new RuntimeException('SQL: ' . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE));
    }
    sqlsrv_free_stmt($stmt);
}

function db_valor(string $sql, array $params = []) {
    $r = db_um($sql, $params);
    return $r ? reset($r) : null;
}

/* ---------------------------------------------------------------------
   7. CSRF
   --------------------------------------------------------------------- */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_campo(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_validar(): void {
    $enviado = $_POST['_csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        die('Token CSRF invalido. Recarregue a pagina.');
    }
}

/* ---------------------------------------------------------------------
   8. Autenticacao
      Login usa a conta do JOGO (Db_Membership) e exige que o
      UserID esteja em Admin_Usuarios (Db_Tank41).
   --------------------------------------------------------------------- */
function admin_dados(): ?array {
    return $_SESSION['painel_admin'] ?? null;
}

function admin_logado(): bool {
    return admin_dados() !== null;
}

function admin_exigir(): array {
    if (!admin_logado()) {
        redirecionar('entrar.php');
    }
    return admin_dados();
}

function admin_pode_escrever(): bool {
    $a = admin_dados();
    return $a && $a['Papel'] === 'admin';
}

/* Resolve UserID do portal -> UserID do jogo pelo UserName */
function usuario_por_nome(string $login): ?array {
    return db_um(
        "SELECT d.UserID, d.UserName, d.NickName, d.Grade, d.GP, d.Money, d.Gold, d.Offer,
                d.LoginCount, d.LastDate
         FROM Db_Tank41.dbo.Sys_Users_Detail d
         WHERE LOWER(d.UserName) = LOWER(?)",
        [$login]
    );
}

function login_bloqueado(string $login): bool {
    $n = (int) db_valor(
        "SELECT COUNT(*) FROM Db_Tank41.dbo.Admin_Tentativas
         WHERE UserName = ? AND Sucesso = 0
           AND Quando > DATEADD(MINUTE, -?, GETDATE())",
        [$login, LOGIN_JANELA_MINUTOS]
    );
    return $n >= LOGIN_MAX_TENTATIVAS;
}

function registrar_tentativa(string $login, bool $ok): void {
    db_exec(
        "INSERT INTO Db_Tank41.dbo.Admin_Tentativas (UserName, IP, Sucesso) VALUES (?, ?, ?)",
        [$login, painel_ip_cliente(), $ok ? 1 : 0]
    );
}

function auditoria(string $acao, ?int $alvoUser, string $detalhe = ''): void {
    $a = admin_dados();
    db_exec(
        "INSERT INTO Db_Tank41.dbo.Admin_Auditoria (AdminID, AdminNome, Acao, AlvoUser, Detalhe, IP)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$a['UserID'] ?? null, $a['UserName'] ?? null, $acao, $alvoUser,
         mb_substr($detalhe, 0, 1000), painel_ip_cliente()]
    );
}

/* ---------------------------------------------------------------------
   9. Dominio do jogo
   --------------------------------------------------------------------- */
/* Grade e DERIVADO de GP via Db_Tank.dbo.LevelInfo (LevelMgr.GetLevel).
   Nunca inventar GP: sempre ler a faixa da tabela oficial. */
function gp_para_grade(int $grade): ?int {
    if ($grade < 1 || $grade > 70) return null;
    return db_valor("SELECT GP FROM Db_Tank.dbo.LevelInfo WHERE Grade = ?", [$grade]);
}

function grade_maximo(): int {
    return (int) db_valor("SELECT MAX(Grade) FROM Db_Tank.dbo.LevelInfo");
}

/* Money e INT: teto 2147483647 */
const CUPOM_MAX = 2147483647;

function slot_nome(int $place): string {
    $m = [0=>'Chapeu',1=>'Oculos',2=>'Cabelo',3=>'Face',4=>'Roupa',5=>'Olhos',
           6=>'ARMA',7=>'Bracele',8=>'Bracele 2',9=>'Anel',10=>'Anel 2',11=>'Terno',
           12=>'Colar',13=>'Decoracao',14=>'Bolha',15=>'Auxiliar'];
    return $m[$place] ?? ('Mochila ' . $place);
}

function numero_br(?string $n): string {
    return number_format((float)$n, 0, ',', '.');
}

/* ---------------------------------------------------------------------
   10. Layout
   --------------------------------------------------------------------- */
function painel_cabecalho(string $titulo, string $aba = ''): void {
    $a = admin_dados();
    $nav = '';
    foreach ([['conta.php','Conta'],['index.php','Contas'],['itens.php','Itens'],
              ['auditoria.php','Auditoria'],['senha.php','Senha']] as [$url,$rot]) {
        $nav .= '<a class="' . ($aba === $url ? 'on' : '') . '" href="' . e($url) . '">' . e($rot) . '</a>';
    }
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($titulo) . ' - Painel DDTank</title>'
       . '<link rel="stylesheet" href="assets/painel.css"></head><body>'
       . '<header><b>Painel DDTank 4.1</b><nav>' . $nav . '</nav>'
       . '<span class="user">' . e($a['UserName'] ?? '') . ' <em>(' . e($a['Papel'] ?? '') . ')</em></span>'
       . '<a class="sair" href="sair.php">sair</a></header><main>'
       . '<h1>' . e($titulo) . '</h1>';
}

function painel_rodape(): void {
    echo '</main><footer>DDTank 4.1 &middot; localhost &middot; '
       . 'todas as acoes ficam registradas em Auditoria</footer></body></html>';
}

function painel_aviso(string $msg, string $tipo = 'erro'): void {
    echo '<div class="aviso ' . e($tipo) . '">' . e($msg) . '</div>';
}