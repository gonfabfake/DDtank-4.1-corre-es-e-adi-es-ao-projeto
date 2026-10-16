'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const js = fs.readFileSync(path.join(__dirname, '../assets/app.js'), 'utf8');
const end = js.indexOf('function ItemCell(');
assert(end > 0, 'Não foi encontrado o bloco de geração de imagens');
const React = {createElement(){}, useState(){}, useEffect(){}, useCallback(){},
  useMemo(){}, useRef(){}};
const sandbox = {React, htm:{bind:()=>()=>{}}, window:{BOOT:{}}, URL};
vm.runInNewContext(js.slice(0, end) + '\nthis.helpers = { candidatosImagem, baseResource, PATHS };', sandbox);
const {candidatosImagem, baseResource} = sandbox.helpers;
const base = 'https://resbr51-myddt-ofc.ddtankswordgames.com/';
const cfg = {base, modo:'espelho', caminho:'padrao'};
function rel(paths) {
  return Array.from(paths, u => decodeURIComponent(u.split('p=')[1] || ''));
}
let paths = rel(candidatosImagem({template:71843,pic:'guidname',cat:11},cfg));
assert.equal(paths[0], 'unfrightprop/guidname/icon.png');
assert.equal(paths[1], 'unfrightprop/71843/icon.png');
assert.equal(baseResource(base), base + 'image/');
assert.equal(baseResource(base + 'image/'), base + 'image/');
paths = rel(candidatosImagem({template:444,pic:'testcloth',cat:5},cfg));
assert.deepEqual(paths.slice(0,4), [
  'equip/m/cloth/testcloth/icon_1.png', 'equip/f/cloth/testcloth/icon_1.png',
  'equip/m/cloth/444/icon_1.png', 'equip/f/cloth/444/icon_1.png'
]);
paths = rel(candidatosImagem({template:55,pic:'abc',cat:34},cfg));
assert.equal(paths[0], 'unfrightprop/abc/icon.png');
paths = rel(candidatosImagem({template:71843,pic:'guidname',cat:11},
  {...cfg,caminho:'custom',template_custom:'image/unfrightprop/{id}/icon.png'}));
assert.equal(paths[0], 'unfrightprop/guidname/icon.png');
assert(paths.every(p => !p.startsWith('image/image/')));
const direct = candidatosImagem({template:71843,pic:'guidname',cat:11},
  {...cfg,modo:'padrao'});
assert.equal(direct[0], base + 'image/unfrightprop/guidname/icon.png');
paths = rel(candidatosImagem({template:71843,pic:'../../malicioso',cat:11},cfg));
assert.equal(paths[0], 'unfrightprop/71843/icon.png');
console.log('OK: casos de Pic, TemplateID, categoria, sexo, base /image e segurança');
