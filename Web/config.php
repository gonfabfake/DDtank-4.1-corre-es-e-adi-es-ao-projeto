<?php
// Host-dynamic view of config.xml: the same XML works on the PC (127.0.0.1)
// and on phones accessing the PC over the LAN (e.g. http://192.168.3.58/).
$host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '127.0.0.1';
if (!preg_match('~^[a-z0-9.\-]+(:\d+)?$~', $host)) {
    $host = '127.0.0.1';
}
$xml = @file_get_contents(__DIR__ . '/config.xml');
if ($xml === false) {
    http_response_code(500);
    exit('config unavailable');
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-store');
echo str_replace('127.0.0.1', $host, $xml);
