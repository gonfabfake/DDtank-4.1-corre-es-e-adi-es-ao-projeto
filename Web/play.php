<?php
require_once __DIR__ . '/global.php';
header('Cache-Control: no-store');
$custom = isset($_SESSION['UserData']) && is_string($_SESSION['UserData'])
    ? @unserialize($_SESSION['UserData'], ['allowed_classes' => ['MemberData', 'DateTime']]) : false;
if (!$custom instanceof MemberData || empty($custom->LowerName) || empty($custom->PassEncrypted)) {
    header('Location: /index.php');
    exit;
}
// Revalidate the authenticated session before issuing a game ticket.
if (!$custom->isAuthenticated()) {
    unset($_SESSION['UserData']);
    header('Location: /index.php');
    exit;
}
$error = null;
$key = bin2hex(random_bytes(16));
$name = strtolower($custom->LowerName);
$time = (string)time();
// Use the deployed request application's signing key (never send it to the browser).
$requestConfig = simplexml_load_file(dirname(__DIR__) . '/Request/Web.config');
$loginKey = '';
foreach ($requestConfig->appSettings->add as $setting) {
    if ((string)$setting['key'] === 'LoginKey') $loginKey = (string)$setting['value'];
}
if ($loginKey === '') {
    $error = 'A chave de integracao do servidor nao esta configurada.';
} else {
    $content = implode('|', [$name, $key, $time, md5($name . $key . $time . $loginKey)]);
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 15,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['content' => $content])
    ]]);
    $response = @file_get_contents('http://127.0.0.1/request/CreateLogin.aspx', false, $context);
    if ($response === false || trim($response) !== '0') {
        error_log('DDTank: CreateLogin failed; response=' . substr((string)$response, 0, 100));
        $error = 'O servidor nao conseguiu emitir a chave de entrada. Tente novamente.';
    }
}
session_write_close();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DDTank — jogo local</title>
<style>
body{margin:0;background:#111a25;color:#eee;font:16px Arial;text-align:center}
#game{width:min(100vw,1000px);height:600px;margin:16px auto;background:#000}
ruffle-player{width:100%;height:100%}a{color:#78ccff}#status{padding:12px}
</style>
<script src="/ruffle/ruffle.js"></script>
</head>
<body>
<div id="status"><?php echo $error ? htmlspecialchars($error, ENT_QUOTES, 'UTF-8') : 'Carregando o cliente Flash com Ruffle local...'; ?></div>
<div id="game"></div>
<p><a href="/">Voltar ao portal</a> · <button onclick="location.reload()">Tentar novamente</button></p>
<?php if (!$error): ?>
<script>
window.addEventListener('DOMContentLoaded', async () => {
    const status = document.getElementById('status');
    try {
        const player = window.RufflePlayer.newest().createPlayer();
        document.getElementById('game').appendChild(player);
        await player.ruffle().load({
            url: '/Flash/Loading.swf',
            parameters: <?php echo json_encode(['user'=>$name, 'key'=>$key, 'config'=>'http://127.0.0.1/config.xml'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            autoplay: 'on', unmuteOverlay: 'visible',
            socketProxy: [{host:'127.0.0.1',port:9200,proxyUrl:'ws://127.0.0.1:8765/game'}]
        });
        status.textContent = 'Cliente iniciado no Ruffle. Aguarde o carregamento do jogo.';
    } catch (error) {
        console.error(error);
        status.textContent = 'Falha ao carregar o Flash: ' + error.message;
    }
});
</script>
<?php endif; ?>
</body>
</html>
