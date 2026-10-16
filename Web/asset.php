<?php
// Fixed upstream only: not a general-purpose proxy. Cache is outside the web root.
$path = explode('?', (string)($_GET['path'] ?? ''), 2)[0];
if ($path === '' || strlen($path) > 240 || strpos($path, '..') !== false
    || !preg_match('~^[a-zA-Z0-9_./-]+\.(png|jpg|jpeg|gif|swf|xml|mp3|flv)$~i', $path)) {
    http_response_code(400); exit('Invalid resource');
}
$types = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
    'swf'=>'application/x-shockwave-flash','xml'=>'application/xml','mp3'=>'audio/mpeg','flv'=>'video/x-flv'];
$cache = dirname(__DIR__) . '/ResourceCache';
$file = $cache . '/' . hash('sha256', $path);
$data = false;
if (is_file($file)) {
    $data = file_get_contents($file);
} else {
    // follow_location=0 + explicit 200 check: a redirect or error body must never
    // be cached as if it were the resource (poisons the cache for later runs).
    $ctx = stream_context_create(['http'=>['timeout'=>20, 'follow_location'=>0, 'ignore_errors'=>true]]);
    $data = @file_get_contents('http://ddt-a.akamaihd.net/' . $path, false, $ctx, 0, 33554433);
    $is200 = false;
    if (is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('~^HTTP/\S+\s+200\b~', $h)) { $is200 = true; break; }
        }
    }
    if ($data === false || $data === '' || !$is200 || strlen($data) > 33554432) {
        http_response_code(502); exit('Resource unavailable upstream');
    }
    if (!is_dir($cache)) @mkdir($cache, 0750, true);
    // Atomic publish: write to a private temp name, then rename into place.
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $data, LOCK_EX) !== false) {
        @rename($tmp, $file);
    }
    if (is_file($tmp)) @unlink($tmp);
}
if ($data === false || $data === '') {
    http_response_code(502); exit('Resource unavailable');
}
header('Content-Type: ' . $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
echo $data;
