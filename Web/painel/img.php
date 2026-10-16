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
if ($realDir === false || $realCache === false || !($realDir === $realCache || str_starts_with($realDir, $realCache . DIRECTORY_SEPARATOR))) {
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

try {
    $download = img_consulta_publica(img_resource_base($cfg['base']) . $p, false, 2097152);
} catch (Throwable $e) {
    http_response_code(400);
    exit('resource invalida');
}
if (!$download['achou'] || $download['body'] === '') {
    http_response_code(404);
    exit('imagem nao encontrada');
}
$imagem = @getimagesizefromstring($download['body']);
$mime = is_array($imagem) ? ($imagem['mime'] ?? '') : '';
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
    http_response_code(415);
    exit('arquivo remoto nao e imagem raster valida');
}

/* Grava de modo atomico, evitando cache parcial ou arquivos malformados. */
$tmp = @tempnam($realDir, 'img_');
if ($tmp === false || @file_put_contents($tmp, $download['body'], LOCK_EX) === false
    || !@rename($tmp, $destino)) {
    if ($tmp !== false && is_file($tmp)) @unlink($tmp);
    http_response_code(503);
    exit('falha ao gravar cache local');
}
@chmod($destino, 0644);
enviar($destino, true);