<?php
/* =====================================================================
   img.php - espelho com cache das imagens de item

   Uso:  img.php?p=arm/71843/00.png

   Fluxo:
     1. Se ja existe copia local em  Web\resource\<p>  -> serve do disco.
     2. Se nao, busca na resource online configurada; se vier imagem,
        grava a copia local e serve.
     3. Se nao vier, 404 (o React cai no SVG).

   SEGURANCA: este endpoint e um proxy de fetch. O caminho `p` e
   validado com regex estrita e nunca pode ser URL absoluta, nem
   conter "..", nem sair da pasta de cache.
   ===================================================================== */
require_once __DIR__ . '/_lib.php';          // checa o allowlist de IP
require_once __DIR__ . '/_imgconfig.php';

const CACHE_DIR = __DIR__ . '/resource';

$p = (string)($_GET['p'] ?? '');

/* ---- validacao estrita do caminho ---- */
function caminho_valido(string $p): bool {
    if ($p === '' || strlen($p) > 200) return false;
    if (str_contains($p, '..') || str_contains($p, "\0")) return false;
    if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9_\-./]*$~', $p)) return false;
    if (!preg_match('~\.(png|jpg|jpeg|gif|webp|svg)$~i', $p)) return false;
    return true;
}

function enviar(string $arquivo, bool $doCache): void {
    $ct = '';
    $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    if ($fi) { $ct = (string) finfo_file($fi, $arquivo); finfo_close($fi); }
    if (!$ct || $ct === 'application/octet-stream') {
        $ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
        $ct = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
               'gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml'][$ext] ?? 'application/octet-stream';
    }
    header('Content-Type: ' . $ct);
    header('Content-Length: ' . filesize($arquivo));
    header($doCache
        ? 'Cache-Control: public, max-age=31536000, immutable'
        : 'Cache-Control: no-store');
    readfile($arquivo);
    exit;
}

if (!caminho_valido($p)) { http_response_code(400); exit('caminho invalido'); }

$destino = CACHE_DIR . '/' . str_replace('/', DIRECTORY_SEPARATOR, $p);
$dir = dirname($destino);
if (!is_dir($dir)) @mkdir($dir, 0777, true);

/* Defence-in-depth: mesmo com a regex ja barrando "..", URL absoluta e
   extensão estranha, conferimos que o destino ficou dentro da pasta cache. */
$realDir   = realpath($dir);
$realCache = realpath(CACHE_DIR);
if ($realDir === false || $realCache === false || !str_starts_with($realDir, $realCache)) {
    http_response_code(400); exit('destino fora da pasta de cache');
}
$destino = $realDir . DIRECTORY_SEPARATOR . basename($p);

/* ---- 1) ja esta em cache? ---- */
if (is_file($destino) && filesize($destino) > 0) {
    enviar($destino, true);
}

/* ---- 2) buscar na resource online e espelhar ---- */
$cfg = img_config_ler();
if ($cfg['base'] === '') { http_response_code(404); exit('sem resource configurada'); }

$ch = curl_init($cfg['base'] . $p);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => false,      // nao segue redirect para fora do host
    CURLOPT_USERAGENT      => 'Mozilla/5.0 painel-ddt',
    // http e https: bloqueia file://, ftp://, gopher:// etc.
    // (HTTP-only quebraria espelhos locais e resources em http)
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
]);
$body  = curl_exec($ch);
$code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ct    = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$erro  = curl_error($ch);
curl_close($ch);

if ($body === false || $code !== 200 || !is_string($body) || $body === '') {
    http_response_code(404);
    exit('nao encontrado na resource');
}
if (stripos($ct, 'image/') !== 0) {
    http_response_code(404);
    exit('a resource devolveu ' . $ct . ', nao imagem');
}

/* ---- 3) grava a copia local ---- */
if (@file_put_contents($destino, $body, LOCK_EX) !== false) {
    @chmod($destino, 0644);
}
enviar($destino, true);