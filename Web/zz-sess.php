<?php
// TEMP DEBUG - apagar depois
require_once __DIR__ . '/global.php';
header('Content-Type: text/plain; charset=utf-8');
echo "sess_id=" . session_id() . "\n";
if (isset($_GET['set'])) { $_SESSION['probe'] = (string)$_GET['set']; echo "set=" . $_GET['set'] . "\n"; }
else { echo "probe=" . (isset($_SESSION['probe']) ? (string)$_SESSION['probe'] : '(none)') . "\n"; }
$ud = isset($_SESSION['UserData']) ? (string)$_SESSION['UserData'] : '';
echo "UserData_len=" . strlen($ud) . "\n";
if ($ud !== '') {
  $obj = @unserialize($ud, array('allowed_classes' => array('MemberData', 'DateTime')));
  echo "unserialize=" . (($obj instanceof MemberData) ? 'MemberData' : gettype($obj)) . "\n";
  if ($obj instanceof MemberData) {
    echo "LowerName=" . $obj->LowerName . "\n";
    echo "PassEncrypted_len=" . strlen((string)$obj->PassEncrypted) . "\n";
  }
}
