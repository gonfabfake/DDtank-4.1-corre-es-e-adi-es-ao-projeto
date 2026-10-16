<?php
// Host-dynamic view of config.xml: the same XML works on the PC (127.0.0.1)
// and on phones accessing the PC over the LAN (e.g. http://<ip-do-pc>/).
$host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '127.0.0.1';
$hostname = $host;
if (preg_match('~^([a-z0-9.\-]+)(:\d+)?$~', $host, $hm)) {
    $hostname = $hm[1];
} else {
    $host = $hostname = '127.0.0.1';
}
// Only accept names/IPs that really belong to this machine (see play.php).
$trustedHosts = array('127.0.0.1', 'localhost');
$serverAddr = isset($_SERVER['SERVER_ADDR']) ? strtolower($_SERVER['SERVER_ADDR']) : '';
if ($serverAddr !== '') { $trustedHosts[] = $serverAddr; }
$ownIp = strtolower((string)@gethostbyname(@gethostname()));
if ($ownIp !== '') { $trustedHosts[] = $ownIp; }
if (!in_array($hostname, $trustedHosts, true)) {
    $host = $hostname = '127.0.0.1';
}
$xml = @file_get_contents(__DIR__ . '/config.xml');
if ($xml === false) {
    http_response_code(500);
    exit('config unavailable');
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-store');
echo str_replace('127.0.0.1', $hostname, $xml);
