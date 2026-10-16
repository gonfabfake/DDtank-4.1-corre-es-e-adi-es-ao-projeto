<?php
// Resolvido em relacao ao proprio arquivo, para funcionar em qualquer instalacao.
$thispath = __DIR__ . DIRECTORY_SEPARATOR;

$diretorio = dir($thispath);

while($arquivo = $diretorio -> read()){
if($arquivo != "index.php" && $arquivo != "." && $arquivo != "..")
{
    include($diretorio->path.$arquivo);
}
}
$diretorio -> close();
?>