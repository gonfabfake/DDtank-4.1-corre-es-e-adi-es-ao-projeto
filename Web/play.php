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
// Host used by the browser and by the Flash client: works for 127.0.0.1 (PC)
// and for LAN access from a phone (e.g. 192.168.x.x). Only names/IPs that
// really belong to this machine are accepted; anything else falls back to
// 127.0.0.1 (defense against Host-header spoofing in the URLs we build).
$host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '127.0.0.1';
$portSuffix = '';
$hostname = $host;
if (preg_match('~^([a-z0-9.\-]+)(:\d+)?$~', $host, $hm)) {
    $hostname = $hm[1];
    $portSuffix = isset($hm[2]) ? $hm[2] : '';
} else {
    $hostname = '127.0.0.1';
}
$trustedHosts = array('127.0.0.1', 'localhost');
$serverAddr = isset($_SERVER['SERVER_ADDR']) ? strtolower($_SERVER['SERVER_ADDR']) : '';
if ($serverAddr !== '') { $trustedHosts[] = $serverAddr; }
$ownIp = strtolower((string)@gethostbyname(@gethostname()));
if ($ownIp !== '') { $trustedHosts[] = $ownIp; }
if (!in_array($hostname, $trustedHosts, true)) {
    $hostname = '127.0.0.1';
    $portSuffix = '';
}
$host = $hostname . $portSuffix;
$origin = 'http://' . $host;
$error = null;
$key = bin2hex(random_bytes(16));
$name = strtolower($custom->LowerName);
$time = (string)time();
// Use the deployed request application's signing key (never send it to the
// browser). The current value lives in Request/WebPrivate.config, which is not
// versioned; Request/Web.config stays as a fallback for older layouts.
$loginKey = '';
foreach (array('WebPrivate.config', 'Web.config') as $cfgName) {
    $cfgPath = dirname(__DIR__) . '/Request/' . $cfgName;
    if (!is_file($cfgPath)) { continue; }
    $requestConfig = @simplexml_load_file($cfgPath);
    if ($requestConfig === false) { continue; }
    // Aceita tanto raiz <appSettings> (WebPrivate.config) quanto <configuration> (Web.config).
    $found = $requestConfig->xpath('//add[@key="LoginKey"]');
    if ($found && isset($found[0])) { $loginKey = (string)$found[0]['value']; break; }
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
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="#0b1118">
<title>DDTank — jogo local</title>
<style>
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;height:100%;overflow:hidden;background:#0b1118;color:#eee;font:16px/1.35 Arial,Helvetica,sans-serif;overscroll-behavior:none;-webkit-text-size-adjust:100%;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
  #gamewrap{position:fixed;inset:0;background:#000;overflow:hidden;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
  #game{width:100%;height:100%}
  ruffle-player{display:block;width:100%;height:100%;touch-action:none}
  #hud{position:fixed;z-index:31;top:calc(6px + env(safe-area-inset-top));left:calc(6px + env(safe-area-inset-left));display:flex;gap:8px}
  .chip{width:42px;height:42px;border:0;border-radius:11px;background:rgba(10,16,24,.66);color:#cfe6ff;font-size:21px;line-height:42px;text-align:center;cursor:pointer;padding:0;opacity:.85;-webkit-appearance:none;appearance:none}
  .chip:active{background:rgba(45,80,120,.9);opacity:1}
  #panel{position:fixed;z-index:30;top:calc(56px + env(safe-area-inset-top));left:calc(6px + env(safe-area-inset-left));min-width:224px;background:rgba(10,16,24,.94);border:1px solid #2b3d52;border-radius:12px;padding:6px;display:none}
  #panel.open{display:block}
  #panel a{display:block;color:#cfe6ff;text-decoration:none;padding:12px 14px;border-radius:8px;font-size:15px}
  #panel a:active{background:rgba(45,80,120,.7)}
  #statusbar{position:fixed;z-index:29;left:50%;transform:translateX(-50%);bottom:calc(10px + env(safe-area-inset-bottom));max-width:92vw;background:rgba(10,16,24,.86);border-radius:10px;padding:9px 14px;font-size:13px;color:#bcd6f2;display:none;text-align:center}
  #statusbar.show{display:block}
  #rotate{position:fixed;inset:0;z-index:40;background:#0b1118;display:none;align-items:center;justify-content:center;padding:26px;text-align:center}
  #rotate.show{display:flex}
  .rot-ico{font-size:54px;line-height:1;margin-bottom:14px}
  #rotate b{display:block;font-size:20px;margin-bottom:8px}
  #rotate p{color:#9fb6cf;font-size:14px;margin:0 0 18px}
  .btn{display:inline-block;border:0;border-radius:10px;background:#2f6db3;color:#fff;font-size:16px;padding:12px 18px;text-decoration:none;cursor:pointer;-webkit-appearance:none;appearance:none}
  .btn:active{background:#3d84d4}
  #errbox{position:fixed;inset:0;z-index:35;display:none;align-items:center;justify-content:center;padding:22px;background:#0b1118}
  #errbox.show{display:flex}
  #errbox .card{max-width:420px;background:#16222f;border:1px solid #2b3d52;border-radius:14px;padding:22px;text-align:center}
  #errbox p{color:#ffd9a8;font-size:15px;margin:0 0 16px}
</style>
<script src="/ruffle/ruffle.js"></script>
</head>
<body>
<div id="gamewrap"><div id="game"></div></div>
<div id="hud">
  <button type="button" class="chip" id="btn-menu" title="Menu">☰</button>
  <button type="button" class="chip" id="btn-fs" title="Tela cheia / sair da tela cheia">⛶</button>
</div>
<div id="panel">
  <a href="/">Voltar ao portal</a>
  <a href="#" id="lnk-reload">Recarregar o jogo</a>
</div>
<div id="statusbar" role="status"></div>
<div id="rotate">
  <div class="rot-card">
    <div class="rot-ico">&#128241;&#8635;</div>
    <b>Gire o celular na horizontal</b>
    <p>O jogo foi feito para paisagem (1000&times;600). Na horizontal ele usa a tela cheia do aparelho.</p>
    <button type="button" class="btn" id="rotate-dismiss">Continuar assim mesmo</button>
  </div>
</div>
<?php if ($error): ?>
<div id="errbox" class="show">
  <div class="card">
    <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <a class="btn" href="/">Voltar ao portal</a>
  </div>
</div>
<?php else: ?>
<script>
(function () {
  'use strict';
  var GAME = document.getElementById('game');
  var statusbar = document.getElementById('statusbar');
  var panel = document.getElementById('panel');
  var rotate = document.getElementById('rotate');
  var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || window.matchMedia('(pointer: coarse)').matches;

  var statusTimer = null;
  function setStatus(text, seconds) {
    statusbar.textContent = text;
    statusbar.classList.add('show');
    if (statusTimer) { clearTimeout(statusTimer); statusTimer = null; }
    if (seconds) { statusTimer = setTimeout(function () { statusbar.classList.remove('show'); }, seconds * 1000); }
  }

  function isFs() { return document.fullscreenElement || document.webkitFullscreenElement; }
  function enterFs() {
    var el = document.documentElement;
    var fn = el.requestFullscreen || el.webkitRequestFullscreen;
    if (!fn) { return; }
    var p;
    try { p = fn.call(el); } catch (e) { return; }
    if (p && p.then) {
      p.then(function () {
        // Only valid while fullscreen; ignore failures (iOS Safari has no API).
        try {
          if (screen.orientation && screen.orientation.lock) {
            screen.orientation.lock('landscape').catch(function () {});
          }
        } catch (e) {}
      }).catch(function () {});
    }
  }
  // First touch anywhere: enter fullscreen on touch devices (best effort).
  var fsTried = false;
  document.addEventListener('pointerdown', function () {
    if (fsTried) { return; }
    fsTried = true;
    if (isTouch && !isFs()) { enterFs(); }
  }, true);

  document.getElementById('btn-fs').addEventListener('click', function (ev) {
    ev.stopPropagation();
    if (isFs()) {
      var x = document.exitFullscreen || document.webkitExitFullscreen;
      if (x) { try { x.call(document); } catch (e) {} }
    } else {
      enterFs();
    }
  });
  document.getElementById('btn-menu').addEventListener('click', function (ev) {
    ev.stopPropagation();
    panel.classList.toggle('open');
  });
  document.addEventListener('pointerdown', function (ev) {
    if (panel.classList.contains('open') && !panel.contains(ev.target) &&
        !document.getElementById('btn-menu').contains(ev.target)) {
      panel.classList.remove('open');
    }
  }, true);
  document.getElementById('lnk-reload').addEventListener('click', function (ev) {
    ev.preventDefault();
    location.reload();
  });
  // No long-press / right-click browser popup over the game.
  document.getElementById('gamewrap').addEventListener('contextmenu', function (ev) { ev.preventDefault(); });

  // Portrait hint (phones and small tablets only; dismissible per tab).
  var mqPortrait = window.matchMedia('(orientation: portrait)');
  var KEY = 'ddt.rot.dismiss';
  function updRotate() {
    var small = Math.min(screen.width, screen.height) <= 900;
    var dismissed = false;
    try { dismissed = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
    rotate.classList.toggle('show', !!(isTouch && small && mqPortrait.matches && !dismissed));
  }
  document.getElementById('rotate-dismiss').addEventListener('click', function () {
    try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
    rotate.classList.remove('show');
  });
  if (mqPortrait.addEventListener) { mqPortrait.addEventListener('change', updRotate); }
  else if (mqPortrait.addListener) { mqPortrait.addListener(updRotate); }
  window.addEventListener('orientationchange', function () { setTimeout(updRotate, 250); });
  if (window.visualViewport) { window.visualViewport.addEventListener('resize', updRotate); }
  document.addEventListener('fullscreenchange', updRotate);
  document.addEventListener('webkitfullscreenchange', updRotate);
  updRotate();

  function boot() {
    setStatus('Carregando o cliente Flash com Ruffle local\u2026', 12);
    try {
      var player = window.RufflePlayer.newest().createPlayer();
      GAME.appendChild(player);
      player.ruffle().load({
        url: '/Flash/Loading.swf',
        parameters: <?php echo json_encode(['user'=>$name, 'key'=>$key, 'config'=>$origin . '/config.php'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        autoplay: 'on',
        unmuteOverlay: 'visible',
        logLevel: 'error',
        socketProxy: [{host:'127.0.0.1', port:9200, proxyUrl:'ws://<?php echo $hostname; ?>:8765/game'}]
      }).then(function () {
        setStatus('Cliente iniciado. No 1\u00ba acesso o carregamento pode levar at\u00e9 ~1 minuto.', 10);
      }).catch(function (err) {
        setStatus('Falha ao carregar o Flash: ' + (err && err.message ? err.message : err), 0);
      });
    } catch (e) {
      setStatus('Falha ao carregar o Flash: ' + e.message, 0);
    }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
</script>
<?php endif; ?>
</body>
</html>
