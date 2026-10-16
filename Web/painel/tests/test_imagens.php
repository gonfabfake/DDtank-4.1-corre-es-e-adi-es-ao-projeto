<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_imgconfig.php';

function confere(bool $ok, string $nome): void {
    if (!$ok) { fwrite(STDERR, "FALHOU: $nome\n"); exit(1); }
    echo "OK: $nome\n";
}
function rejeita(callable $callback, string $nome): void {
    try { $callback(); } catch (InvalidArgumentException $ex) {
        echo "OK: rejeitou $nome\n"; return;
    }
    fwrite(STDERR, "FALHOU: aceitou $nome\n"); exit(1);
}

confere(img_config_padrao()['modo'] === 'espelho', 'modo espelho como padrao');
confere(in_array('espelho', ['espelho', 'padrao', 'template', 'pic'], true), 'modo permitido');
$r = img_derivar_template('https://cdn.exemplo.com/image/arm/71843/00.png', '71843');
confere($r['base'] === 'https://cdn.exemplo.com/', 'base correta sem truncar caminho');
confere($r['template'] === 'image/arm/{id}/00.png', 'template mantem diretorios');
confere(str_replace('{id}', '71844', $r['template']) === 'image/arm/71844/00.png',
    'troca TemplateID em itens diferentes');
rejeita(static fn() => img_derivar_template('https://cdn.exemplo.com/arm/718430/00.png', '71843'),
    'identificador parcial');
rejeita(static fn() => img_derivar_template('https://cdn.exemplo.com/arm/71843/00.png?token=abc', '71843'),
    'URL com query');
rejeita(static fn() => img_curl_seguro('http://127.0.0.1/admin.png'), 'destino loopback');
rejeita(static fn() => img_curl_seguro('http://169.254.169.254/latest/'), 'destino link local');

$exemploNomeado = 'https://resbr51-myddt-ofc.ddtankswordgames.com/image/unfrightprop/guidname/icon.png';
rejeita(static fn() => img_derivar_template($exemploNomeado, '71843'),
    'URL com guidname nao vira template numerico');
confere(str_contains(parse_url($exemploNomeado, PHP_URL_PATH), '/image/unfrightprop/guidname/icon.png'),
    'caminho de referencia preservado integralmente');

confere(img_resource_base('https://resbr51-myddt-ofc.ddtankswordgames.com/')
    === 'https://resbr51-myddt-ofc.ddtankswordgames.com/image/',
    'prefixo image/ em resource com raiz de site');
confere(img_resource_base('https://resbr51-myddt-ofc.ddtankswordgames.com/image/')
    === 'https://resbr51-myddt-ofc.ddtankswordgames.com/image/',
    'sem duplicar o prefixo image/');
confere(str_contains(img_caminhos()[34], 'unfrightprop/'),
    'categoria 34 segue convencao de loadimage');
echo "Testes locais do painel concluidos.\n";
