<?php
// Fixed upstream only: not a general-purpose proxy. Cache is outside the web root.
$path = explode('?', (string)($_GET['path'] ?? ''), 2)[0];
if (strlen($path) > 240 || !preg_match('~^(image|sound|flash)/[a-zA-Z0-9_./-]+\.(png|jpg|jpeg|gif|swf|xml|mp3|flv)$~i', $path)
    || strpos($path, '..') !== false) {
    http_response_code(400); exit('Invalid resource');
}
$types = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
    'swf'=>'application/x-shockwave-flash','xml'=>'application/xml','mp3'=>'audio/mpeg','flv'=>'video/x-flv'];
$cache = dirname(__DIR__) . '/ResourceCache';
$file = $cache . '/' . hash('sha256', $path);
if (!is_file($file)) {
    $ctx = stream_context_create(['http'=>['timeout'=>20,'follow_location'=>0]]);
    $data = @file_get_contents('http://ddt-a.akamaihd.net/' . $path, false, $ctx, 0, 33554433);
    if ($data === false || strlen($data) > 33554432) {
        http_response_code(502); exit('Resource unavailable upstream');
    }
    if (!is_dir($cache)) @mkdir($cache, 0750, true);
    @file_put_contents($file, $data, LOCK_EX);
} else {
    $data = file_get_contents($file);
}
header('Content-Type: ' . $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
echo $data;
