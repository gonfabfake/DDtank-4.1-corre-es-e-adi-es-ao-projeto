<?php
/* =====================================================================
   _imgconfig.php - configuracao da resource online de imagens
   Guardada FORA do docroot, em C:\DDTank4.1-main\secrets\painel_img.json
   ===================================================================== */

const IMG_CONFIG_FILE = __DIR__ . '/../../secrets/painel_img.json';

/* Caminhos por categoria. Padrao tirado de Web\function.php::loadimage(),
   que monta exatamente estes caminhos a partir do TemplateID.
   (Funcao hoje morta no portal, mas e a convencao real do jogo.) */
function img_caminhos(): array {
    $m = 'equip/m';
    return [
        1  => "$m/head/{id}/icon_1.png",
        2  => "$m/glass/{id}/icon_1.png",
        3  => "$m/hair/{id}/icon_1.png",
        5  => "$m/cloth/{id}/icon_1.png",
        6  => "$m/face/{id}/icon_1.png",
        7  => 'arm/{id}/00.png',
        8  => 'equip/armlet/{id}/icon.png',
        9  => 'equip/ring/{id}/icon.png',
        11 => 'unfrightprop/{id}/icon.png',
        13 => "$m/suits/{id}/icon_1.png",
        14 => 'necklace/{id}/icon.png',
        15 => 'equip/wing/{id}/icon.png',
        16 => 'specialprop/chatBall/{id}/icon.png',
        17 => 'equip/offhand/{id}/icon.png',
        19 => 'prop/{id}/icon.png',
        20 => 'prop/{id}/icon.png',
        34 => 'prop/{id}/icon.png',
        35 => 'unfrightprop/{id}/icon.png',
        50 => 'petequip/arm/{id}/icon.png',
        51 => 'petequip/hat/{id}/icon.png',
        52 => 'petequip/cloth/{id}/icon.png',
    ];
}

function img_config_padrao(): array {
    return [
        'base'    => 'https://resbr51-myddt-ofc.ddtankswordgames.com/',
        'modo'    => 'espelho',  // espelho | padrao | template | pic
        'caminho' => 'padrao',   // usado quando modo = espelho
        'mostrar' => true,
    ];
}

function img_config_ler(): array {
    $c = img_config_padrao();
    if (is_file(IMG_CONFIG_FILE)) {
        $b = file_get_contents(IMG_CONFIG_FILE);
        if ($b !== false && $b !== '') {
            $j = json_decode($b, true);
            if (is_array($j)) $c = array_merge($c, $j);
        }
    }
    // normaliza: base sempre termina com /
    $c['base'] = rtrim((string)$c['base'], '/') . ($c['base'] !== '' ? '/' : '');
    if (!in_array($c['modo'], ['padrao', 'template', 'pic'], true)) $c['modo'] = 'padrao';
    return $c;
}

function img_config_gravar(array $novo): array {
    $c = img_config_ler();
    if (isset($novo['base']))   $c['base'] = rtrim(trim((string)$novo['base']), '/') . (trim((string)$novo['base']) !== '' ? '/' : '');
    if (isset($novo['modo']) && in_array($novo['modo'], ['espelho','padrao','template','pic'], true)) $c['modo'] = $novo['modo'];
    if (isset($novo['caminho']) && in_array($novo['caminho'], ['padrao','template','pic'], true)) $c['caminho'] = $novo['caminho'];
    if (isset($novo['mostrar'])) $c['mostrar'] = (int)$novo['mostrar'] === 1;
    $dir = dirname(IMG_CONFIG_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    file_put_contents(IMG_CONFIG_FILE, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $c;
}