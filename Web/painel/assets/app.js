/* =====================================================================
   app.js - painel DDTank 4.1 em React (sem build step, via htm)
   ===================================================================== */
'use strict';

const { useState, useEffect, useCallback, useMemo, useRef } = React;
const html = htm.bind(React.createElement);

/* ------------------------------------------------------------------ */
/* API                                                                  */
/* ------------------------------------------------------------------ */
async function api(acao, params = {}, metodo = 'GET') {
  const url = new URL('api.php', location.href);
  url.searchParams.set('a', acao);
  let opt = { method: metodo, headers: { 'X-Requested-With': 'fetch' } };
  if (metodo === 'POST') {
    const fd = new URLSearchParams();
    fd.set('_csrf', BOOT.csrf);
    Object.entries(params).forEach(([k, v]) => fd.set(k, v));
    opt.body = fd;
  } else {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== '' && v != null) url.searchParams.set(k, v);
    });
  }
  const r = await fetch(url, opt);
  const txt = await r.text();
  let d;
  try { d = JSON.parse(txt); } catch { throw new Error('resposta invalida: ' + txt.slice(0, 200)); }
  if (!d.ok) throw new Error(d.erro || 'erro ' + r.status);
  return d;
}

/* ------------------------------------------------------------------ */
/* Icones SVG dos itens: desenhados a partir da categoria real.         */
/* O jogo nao expoe as sprites como arquivo (Shop_Goods.Pic e chave de */
/* recurso do Flash dentro do SWF), entao o icone e gerado.              */
/* ------------------------------------------------------------------ */
const QUALIDADE = {
  0: { c: '#6b7280', n: '—' },
  1: { c: '#9aa3af', n: 'Thô' },
  2: { c: '#22c55e', n: 'Thường' },
  3: { c: '#3b82f6', n: 'Bom' },
  4: { c: '#c084fc', n: 'Exelente' },
  5: { c: '#f59e0b', n: 'Perfeita' },
  6: { c: '#eab308', n: 'Ouro' },
};

const CAT_NOME = {
  1: 'Chapéu', 2: 'Óculos', 3: 'Cabelo', 4: 'Face', 5: 'Roupa', 6: 'Olhos',
  7: 'Arma', 8: 'Pulseira', 9: 'Anel', 10: 'Item de combate', 11: 'Item de auxílio',
  12: 'Tarefa', 13: 'Terno', 14: 'Colar', 15: 'Decoração', 16: 'Bolha',
  17: 'Auxiliar', 25: 'Presente', 26: 'Cartão', 27: 'Arma especial',
  32: 'Semente', 34: 'Alimento', 35: 'Ovo de pet', 36: 'Colheita',
  40: 'Emblema', 50: 'Arma mascote', 51: 'Chapéu mascote', 52: 'Roupa mascote',
  60: 'Espírito', 61: 'Pedra mágica',
};

/* caminhos 24x24 por categoria */
const CAT_PATH = {
  1:  'M4 15h16l-1 2H5z M6 15V9l3-3 3 2 3-2 3 3v6',        // chapeu
  2:  'M2 12h8M14 12h8M10 10h4v4h-4z',                        // oculos
  3:  'M5 19c0-6 3-14 7-14s7 8 7 14c-3 1-4-3-7-3s-4 4-7 3z',  // cabelo
  4:  'M6 6h12v7a6 6 0 0 1-12 0z M9 10h.01M15 10h.01',       // face/mascara
  5:  'M8 4l4 2 4-2 4 3v14H4V7z M4 11h16',                   // roupa
  6:  'M2 12s4-6 10-6 10 6-4 6-10 6-10-6z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z', // olho
  7:  'M3 9h11l3 3-3 3H3z M6 12h8 M16 10v4',                  // arma
  8:  'M6 8a6 6 0 0 0 0 8M18 8a6 6 0 0 1 0 8 M9 11h6v2H9z',   // pulseira
  9:  'M9 15a5 5 0 1 1 6 0 M9 15h6',                           // anel
  13: 'M9 4h6l1 5-4 2-4-2z M9 11l3 4 3-4 1 9H8z',            // terno
  14: 'M7 4a3 3 0 1 0 6 0 M7 4v3c0 4 10 4 10 0V4 M12 13v6',  // colar
  15: 'M12 4v8 M12 12c-5 0-7-4-7-6 4 0 6 2 7 4 1-2 3-4 7-4 0 2-2 6-7 6z', // asa
  16: 'M12 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14z M9 9a2 2 0 1 0 0 4', // bolha
  17: 'M5 6h14v12H5z M9 9h6v6H9z',                            // auxiliar
  25: 'M3 9h18v11H3z M3 9l4-5h10l4 5 M12 9v11',               // presente
  26: 'M7 3h10v18H7z M9 7h6v3H9z',                            // cartao
  27: 'M12 2l3 6 6 3-6 3-3 6-3-6-6-3 6-3z',                   // arma especial (estrela)
  32: 'M12 21c5-4 7-8 7-12-6 0-9 3-11 9-2 6 1 8 4 3z',       // semente
  34: 'M6 4h12l-1 16H7z M9 1h6v3H9z',                         // alimento
  35: 'M12 3c4 4 6 7 6 10a6 6 0 0 1-12 0c0-3 2-6 6-10z',      // ovo de pet
  36: 'M12 21V11 M12 11c0-3 2-5 5-5 0 3-2 5-5 5z M12 14c0-3-2-5-5-5 0 3 2 5 5 5z', // planta
  40: 'M12 2l8 4v6c0 5-4 8-8 9-4-1-8-4-8-9V6z M9 12l2 2 4-4', // emblema
  50: 'M4 8l8-4 8 4v8l-8 4-8-4z M12 8v8',                      // equip mascote
  51: 'M4 15h16l-1 2H5z M6 15V9l3-3 3 2 3-2 3 3v6',           // chapeu mascote
  52: 'M8 4l4 2 4-2 4 3v14H4V7z',                             // roupa mascote
  60: 'M12 22c4-4 6-8 6-12a6 6 0 1 0-12 0c0 4 2 8 6 12z',    // espirito
  61: 'M12 2l6 9-6 11-6-11z',                                   // pedra
};

function ItemIcon({ cat = 0, q = 0, size = 40 }) {
  const cor = (QUALIDADE[q] || QUALIDADE[0]).c;
  const d = CAT_PATH[cat] || 'M4 8h16v11H4z M4 8l4-4h8l4 4';     // caixa padrao
  return html`
    <svg className="ico" width=${size} height=${size} viewBox="0 0 24 24" aria-hidden="true">
      <defs>
        <linearGradient id=${'g' + cat + '_' + q} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor=${cor} stopOpacity=".95"/>
          <stop offset="100%" stopColor=${cor} stopOpacity=".25"/>
        </linearGradient>
      </defs>
      <rect x="1.5" y="1.5" width="21" height="21" rx="5"
            fill=${`url(#g${cat}_${q})`} stroke=${cor} strokeWidth="1.2"/>
      <path d=${d} fill="none" stroke=${cor} strokeWidth="1.6"
            strokeLinecap="round" strokeLinejoin="round"/>
    </svg>`;
}

/* Caminhos por categoria: mesma convencao de Web\function.php::loadimage()
   (que monta estes caminhos a partir do TemplateID). */
const PATHS = {
  1:'equip/m/head/{id}/icon_1.png', 2:'equip/m/glass/{id}/icon_1.png',
  3:'equip/m/hair/{id}/icon_1.png', 5:'equip/m/cloth/{id}/icon_1.png',
  6:'equip/m/face/{id}/icon_1.png', 7:'arm/{id}/00.png',
  8:'equip/armlet/{id}/icon.png',   9:'equip/ring/{id}/icon.png',
  11:'unfrightprop/{id}/icon.png', 13:'equip/m/suits/{id}/icon_1.png',
  14:'necklace/{id}/icon.png',     15:'equip/wing/{id}/icon.png',
  16:'specialprop/chatBall/{id}/icon.png', 17:'equip/offhand/{id}/icon.png',
  19:'prop/{id}/icon.png', 20:'prop/{id}/icon.png',
  34:'prop/{id}/icon.png', 35:'unfrightprop/{id}/icon.png',
  50:'petequip/arm/{id}/icon.png', 51:'petequip/hat/{id}/icon.png',
  52:'petequip/cloth/{id}/icon.png',
};

/* URL da resource online. BOOT.img = {base, modo, mostrar}
   modo: padrao (convencao do jogo) | template (id.png) | pic (chave.png) */
function urlImagem(it) {
  const cfg = (window.BOOT && window.BOOT.img) || {};
  if (!cfg.base) return null;
  const id = it.template;
  if (cfg.modo === 'template') return cfg.base + id + '.png';
  if (cfg.modo === 'pic')      return it.pic ? cfg.base + it.pic + '.png' : null;
  const t = PATHS[it.cat] || PATHS[19];
  return cfg.base + t.replace('{id}', id);
}

/* Imagem online com fallback para o SVG gerado. */
function ItemImg({ it, size = 44 }) {
  const [falhou, setFalhou] = React.useState(false);
  const cfg = (window.BOOT && window.BOOT.img) || {};
  const url = urlImagem(it);
  const usarOnline = url && !falhou && cfg.base;

  if (usarOnline) {
    return html`<img className="ico img-on" src=${url} width=${size} height=${size}
        loading="lazy" decoding="async"
        alt=${it.nome || ('item ' + it.template)}
        onError=${() => setFalhou(true)} />`;
  }
  return html`<${ItemIcon} cat=${it.cat} q=${it.q} size=${size}/>`;
}

function ItemCell({ it, children }) {
  const q = QUALIDADE[it.q] || QUALIDADE[0];
  return html`
    <div className=${'item q' + it.q} title=${`${it.nome || it.template} — ${CAT_NOME[it.cat] || 'cat ' + it.cat} — qualidade ${q.n}${it.forja ? ' (+' + it.forja + ')' : ''}${it.pic ? '\nrecurso: ' + it.pic : ''}`}>
      <${ItemImg} it=${it} size=${44}/>
      <div className="item-txt">
        <b>${it.nome || ('#' + it.template)}</b>
        <small>#${it.template}${it.cat ? ' · ' + (CAT_NOME[it.cat] || it.cat) : ''}</small>
        ${it.atq || it.def || it.agi || it.luk ? html`
          <small className="st">
            ${it.atq != null ? html`<i className="atq">${it.atq}</i>` : null}
            ${it.def != null ? html`<i className="def">${it.def}</i>` : null}
            ${it.agi != null ? html`<i className="agi">${it.agi}</i>` : null}
            ${it.luk != null ? html`<i className="luk">${it.luk}</i>` : null}
          </small>` : null}
      </div>
      ${children}
    </div>`;
}

const num = n => (n == null ? '—' : Number(n).toLocaleString('pt-BR'));
const dt = s => (s ? String(s).replace('T', ' ').slice(0, 19) : '—');

/* ------------------------------------------------------------------ */
/* Componentes de apoio                                                */
/* ------------------------------------------------------------------ */
const Card = ({ titulo, acoes, children }) => html`
  <section className="card">
    <header>${titulo ? html`<h3>${titulo}</h3>` : null}<div className="card-acoes">${acoes}</div></header>
    <div className="card-corpo">${children}</div>
  </section>`;

const KV = ({ linhas }) => html`
  <table className="kv">
    <tbody>${linhas.filter(Boolean).map(([k, v]) => html`
      <tr key=${k}><td>${k}</td><td>${v}</td></tr>`)}</tbody>
  </table>`;

function Aviso({ msg, tipo }) {
  if (!msg) return null;
  return html`<div className=${'aviso ' + (tipo || 'erro')}>${msg}</div>`;
}

function Campo({ label, children, dica }) {
  return html`<label>${label}${children}${dica ? html`<small>${dica}</small>` : null}</label>`;
}

/* ------------------------------------------------------------------ */
/* App                                                                  */
/* ------------------------------------------------------------------ */
function App() {
  const [view, setView] = useState('conta');
  const [alvo, setAlvo] = useState('');   // termo de busca da ficha (vinda de outras telas)
  const [msg, setMsg] = useState(null);
  const [tipoMsg, setTipoMsg] = useState('erro');

  const avisar = useCallback((t, m = null) => { setTipoMsg(t); setMsg(m); }, []);

  const VIEWS = [
    ['conta', 'Ficha da conta'],
    ['contas', 'Contas'],
    ['itens', 'Itens'],
    ['imagens', 'Imagens'],
    ['auditoria', 'Auditoria'],
  ];

  return html`
    <div className="app">
      <header className="topo">
        <div className="marca"><span className="logo">DD</span> Painel DDTank <em>4.1</em></div>
        <nav>${VIEWS.map(([k, r]) => html`
          <button key=${k} className=${view === k ? 'on' : ''}
                  onClick=${() => { setView(k); avisar('erro', null); }}>${r}</button>`)}
        </nav>
        <div className="topo-dir">
          <span className="user">${BOOT.user.nome}<em>${BOOT.user.papel}</em></span>
          <a href="senha.php">senha</a>
          <a className="sair" href="sair.php">sair</a>
        </div>
      </header>
      <${Aviso} msg=${msg} tipo=${tipoMsg}/>
      <main>
        ${view === 'conta'      && html`<${ViewConta} avisar=${avisar} irItens=${() => setView('itens')} alvo=${alvo} setAlvo=${setAlvo}/>`}
        ${view === 'contas'     && html`<${ViewContas} avisar=${avisar} irConta=${nome => { setAlvo(nome); setView('conta'); }}/>`}
        ${view === 'itens'      && html`<${ViewItens} avisar=${avisar}/>`}
        ${view === 'imagens'    && html`<${ViewImagens} avisar=${avisar}/>`}
        ${view === 'auditoria'  && html`<${ViewAuditoria} avisar=${avisar}/>`}
      </main>
      <footer>
        localhost apenas · todas as ações ficam em Auditoria ·
        documentação em <code>C:\DDTank4.1-main\Web\painel\README.md</code>
      </footer>
    </div>`;
}

/* ------------------------------------------------------------------ */
/* Ficha da conta                                                      */
/* ------------------------------------------------------------------ */
function ViewConta({ avisar, irItens, alvo, setAlvo }) {
  const [q, setQ] = useState(alvo || '');
  const [c, setC] = useState(null);
  const [aba, setAba] = useState('resumo');
  const [carregando, setCarregando] = useState(false);

  const buscar = useCallback(async (termo) => {
    const t = (termo != null ? termo : q).trim();
    if (!t) return;
    setCarregando(true);
    try {
      const d = await api('conta', { q: t });
      setC(d.conta); setAba('resumo');
    } catch (e) { avisar('erro', e.message); setC(null); }
    setCarregando(false);
  }, [q, avisar]);

  // Se outra tela mandou um nome (ex.: "ficha" na lista de contas), busca sozinho.
  useEffect(() => {
    if (alvo && alvo !== q) { setQ(alvo); buscar(alvo); }
  }, [alvo]);

  const ABAS = [['resumo', 'Resumo'], ['itens', 'Itens'], ['mensagens', 'Mensagens'],
                ['acoes', 'Ações'], ['pets', 'Pets'], ['partidas', 'Partidas']];

  return html`
    <div className="busca-grande">
      <${ItemIcon} cat=${7} q=${5} size=${38}/>
      <input
        value=${q}
        placeholder="digite o nome do usuário, apelido ou ID"
        onChange=${e => setQ(e.target.value)}
        onKeyDown=${e => { if (e.key === 'Enter') buscar(); }}
        autoFocus />
      <button className="primario" onClick=${buscar} disabled=${carregando}>
        ${carregando ? 'procurando…' : 'Procurar'}
      </button>
      ${c ? html`<a className="limpar" onClick=${() => { setC(null); setQ(''); }}>limpar</a>` : null}
    </div>

    ${c ? html`
      <div className="cabeca-conta">
        <div>
          <h1>${c.detalhe.NickName}</h1>
          <div className="sub">
            ${c.detalhe.UserName} · ID ${c.detalhe.UserID} ·
            nível ${c.detalhe.Grade} · ${num(c.detalhe.Money)} cupons · ${c.itens.length} itens
          </div>
        </div>
        <div className="nivel-badge">
          <span>${c.detalhe.Grade}</span><small>/ ${BOOT.maxGrade}</small>
        </div>
      </div>

      <div className="abas">${ABAS.map(([k, r]) => html`
        <button key=${k} className=${aba === k ? 'on' : ''} onClick=${() => setAba(k)}>
          ${r}${k === 'itens' ? html` <i>${c.itens.length}</i>` : null}
          ${k === 'mensagens' ? html` <i>${c.mensagens.length}</i>` : null}
        </button>`)}
      </div>

      ${aba === 'resumo'    && html`<${AbaResumo}   c=${c} avisar=${avisar} recarregar=${buscar}/>`}
      ${aba === 'itens'     && html`<${AbaItens}    c=${c} avisar=${avisar} irItens=${irItens}/>`}
      ${aba === 'mensagens' && html`<${AbaMensagens} c=${c}/>`}
      ${aba === 'acoes'     && html`<${AbaAcoes}     c=${c}/>`}
      ${aba === 'pets'      && html`<${AbaPets}      c=${c}/>`}
      ${aba === 'partidas'  && html`<${AbaPartidas}  c=${c}/>`}
    ` : html`
      <div className="vazio">
        <${ItemIcon} cat=${11} q=${1} size=${64}/>
        <p>${carregando ? 'Procurando…' : 'Digite um nome de usuário acima para ver a ficha completa.'}</p>
      </div>`}`;
}

function AbaResumo({ c, avisar, recarregar }) {
  const d = c.detalhe;
  const [nivel, setNivel] = useState(d.Grade);
  const [cupons, setCupons] = useState(d.Money);
  const [salvo, setSalvo] = useState(false);

  const agir = async (acao, extra) => {
    try {
      const r = await api('acao', Object.assign({ acao, userid: d.UserID }, extra), 'POST');
      avisar('bom', r.msg); setSalvo(true); setTimeout(() => setSalvo(false), 1800);
      recarregar();
    } catch (e) { avisar('erro', e.message); }
  };

  return html`
    <div className="grade">
      <${Card} titulo="Progresso"><${KV} linhas=${[
        ['Nível', html`<b>${d.Grade}</b> / ${BOOT.maxGrade}`],
        ['GP (experiência)', num(d.GP)],
        ['Evolução', `${d.evolutionGrade} (${num(d.evolutionExp)})`],
        ['Honra', d.Honor],
        ['Conquistas', `${c.conquistas} / ${c.conqTotal}`],
        ['Emblemas', d.badgeID],
        ['Pontos de Cassino', d.AchievementPoint],
      ]}/></${Card}>

      <${Card} titulo="Moedas"><${KV} linhas=${[
        ['Cupons (Money)', html`<b>${num(d.Money)}</b>`],
        ['Cupons bloqueados', num(d.MoneyLock)],
        ['Gold', num(d.Gold)],
        ['Mérito (Offer)', num(d.Offer)],
        ['Medal', num(d.Medal)],
        ['GiftToken', num(d.GiftToken)],
        ['hardCurrency', num(d.hardCurrency)],
        ['myHonor', num(d.myHonor)],
        ['Cash', html`${num(d.Cash)} <small>coluna morta</small>`],
      ]}/></${Card}>

      <${Card} titulo="Combate"><${KV} linhas=${[
        ['Vitórias / Total', `${d.Win} / ${d.Total}`],
        ['Escapou', d.Escape],
        ['Derrotas', d.Total - d.Win],
        ['FightPower', num(d.FightPower)],
        ['EliteScore', num(d.EliteScore)],
        ['Ataque / Defesa', c.rank ? `${num(c.rank.Attack)} / ${num(c.rank.Defence)}` : '—'],
        ['Sorte / Agilidade', c.rank ? `${num(c.rank.Luck)} / ${num(c.rank.Agility)}` : '—'],
        ['Vida / Dano / Guarda', c.rank ? `${num(c.rank.HP)} / ${num(c.rank.Damage)} / ${num(c.rank.Guard)}` : '—'],
        ['Liga (semana)', c.match ? `${c.match.weeklyScore} pts · #${c.match.weeklyRanking}` : '—'],
      ]}/></${Card}>

      <${Card} titulo="Conta e acesso"><${KV} linhas=${[
        ['Criada em', dt(d.Date)],
        ['Último acesso', dt(d.LastDate)],
        ['Logins', d.LoginCount],
        ['Tempo online', `${num(d.OnlineTime)} min`],
        ['Último IP', d.ActiveIP],
        ['Banido até', `${dt(d.ForbidDate)} ${d.ForbidReason || ''}`],
        ['VIP', c.vip ? `nível ${c.vip.VIPLevel} · exp ${num(c.vip.VIPExp)}` : 'sem VIP'],
        ['Guilda', c.guilda ? `${c.guilda.Guilda} (cargo ${c.guilda.DutyID})` : 'sem guilda'],
        ['Casamento', d.IsMarried ? `com ${d.SpouseName}` : 'solteiro'],
        ['Cartões / Gemas', `${c.cartoes} / ${c.gemas}`],
        ['Portal', c.portal ? `${c.portal.Email || 'sem e-mail'}` : '—'],
      ]}/></${Card}>

      <${Card} titulo="Editar" acoes=${salvo ? html`<span className="ok-flash">salvo ✓</span>` : null}>
        <div className="edit">
          <${Campo} label=${'Nível'} dica="o GP é lido de Db_Tank.dbo.LevelInfo">
            <input type="number" min="1" max=${BOOT.maxGrade} value=${nivel}
                   onChange=${e => setNivel(e.target.value)}/>
          <//>
          <button className="primario" onClick=${() => agir('nivel', { grade: nivel })}>Aplicar nível</button>

          <${Campo} label="Cupons (Money)" dica=${`teto ${num(BOOT.cupomMax)}`}>
            <input type="number" min="0" max=${BOOT.cupomMax} value=${cupons}
                   onChange=${e => setCupons(e.target.value)}/>
          <//>
          <button className="primario" onClick=${() => agir('cupons', { valor: cupons })}>Aplicar cupons</button>

          <${Campo} label="Gold / Offer / Medal">
            <div className="tres">
              <input type="number" defaultValue=${d.Gold}  id="ed-gold"/>
              <input type="number" defaultValue=${d.Offer} id="ed-offer"/>
              <input type="number" defaultValue=${d.Medal} id="ed-medal"/>
            </div>
          <//>
          <button onClick=${() => agir('moedas', {
            gold:  document.getElementById('ed-gold').value,
            offer: document.getElementById('ed-offer').value,
            medal: document.getElementById('ed-medal').value })}>Aplicar moedas</button>
        </div>
      <//>

      <${Card} titulo="Permissões"><${Permissoes} c=${c} avisar=${avisar} recarregar=${recarregar}/><//>
    </div>`;
}

function Permissoes({ c, avisar, recarregar }) {
  const [ehAdmin, setEhAdmin] = useState(!!(c.detalhe.Papel));
  const [chat, setChat] = useState(!!(c.detalhe.ChatAdmin));
  const agir = async (a, extra) => {
    try { const r = await api('acao', Object.assign({ acao: a, userid: c.detalhe.UserID }, extra), 'POST');
          avisar('bom', r.msg); recarregar(); }
    catch (e) { avisar('erro', e.message); }
  };
  return html`
    <div className="edit">
      <label className="chk">
        <input type="checkbox" checked=${ehAdmin}
               onChange=${e => { setEhAdmin(e.target.checked); agir('admin', { ativar: e.target.checked ? 1 : 0, papel: 'admin' }); }}/>
        Acesso ao painel
      </label>
      <label className="chk">
        <input type="checkbox" checked=${chat}
               onChange=${e => { setChat(e.target.checked); agir('chat', { ativar: e.target.checked ? 1 : 0 }); }}/>
        Comandos de chat no jogo <small>$ban$ $kick$ $mute$ $unban$ $bugbot$</small>
      </label>
    </div>`;
}

function AbaItens({ c, avisar, irItens }) {
  const d = c.detalhe;
  const equip = c.itens.filter(i => i.bag === 0 && i.slot <= 30);
  const resto = c.itens.filter(i => !(i.bag === 0 && i.slot <= 30));
  const [tid, setTid] = useState('');
  const [bag, setBag] = useState('0');
  const [slot, setSlot] = useState(-1);
  const [qtd, setQtd] = useState(1);

  const conceder = async () => {
    try {
      const r = await api('acao', { acao: 'conceder', userid: d.UserID,
        templateid: tid, bagtype: bag, place: slot, qtd }, 'POST');
      avisar('bom', r.msg);
    } catch (e) { avisar('erro', e.message); }
  };
  const remover = async (it) => {
    if (!confirm(`Remover "${it.nome || it.template}" do inventário?`)) return;
    try { const r = await api('acao', { acao: 'remover', userid: d.UserID, itemid: it.itemID }, 'POST');
          avisar('bom', r.msg); } catch (e) { avisar('erro', e.message); }
  };

  return html`
    <div className="grade-larga">
      <${Card} titulo=${`Equipado (${equip.length})`}>
        <div className="itens">${equip.length ? equip.map(i => html`
          <${ItemCell} key=${i.itemID} it=${i}>
            <button className="mini" title="remover" onClick=${() => remover(i)}>×</button>
          <//>`) : html`<p className="mini">Nada equipado.</p>`}</div>
      <//>
      <${Card} titulo=${`Mochila e inventário (${resto.length})`}>
        <div className="itens">${resto.length ? resto.map(i => html`
          <${ItemCell} key=${i.itemID} it=${i}>
            <button className="mini" title="remover" onClick=${() => remover(i)}>×</button>
          <//>`) : html`<p className="mini">Vazio.</p>`}</div>
      <//>
      <${Card} titulo="Conceder item">
        <div className="edit">
          <${Campo} label="TemplateID"
                    dica=${html`<a onClick=${irItens} style="cursor:pointer;text-decoration:none">buscar no catálogo →</a>`}>
            <input type="number" value=${tid} onChange=${e => setTid(e.target.value)} placeholder="ex.: 71843"/>
          <//>
          <${Campo} label="Bolsa">
            <select value=${bag} onChange=${e => setBag(e.target.value)}>
              <option value="0">Equipamento / mochila</option>
              <option value="1">Mochila de itens</option>
            </select>
          <//>
          <${Campo} label="Slot" dica="-1 = próximo livre">
            <input type="number" value=${slot} onChange=${e => setSlot(e.target.value)}/>
          <//>
          <${Campo} label="Qtd"><input type="number" min="1" max="9999" value=${qtd}
                   onChange=${e => setQtd(e.target.value)}/><//>
          <button className="primario" onClick=${conceder} disabled=${!tid}>Conceder</button>
        </div>
      <//>
    </div>`;
}

function AbaMensagens({ c }) {
  return html`
    <${Card} titulo=${`Mensagens (${c.mensagens.length})`}>
      ${c.mensagens.length ? html`
        <table className="tab">
          <thead><tr><th>#</th><th>Direção</th><th>De / Para</th><th>Assunto</th>
            <th>Conteúdo</th><th>Data</th><th>Anexo</th></tr></thead>
          <tbody>${c.mensagens.map(m => html`
            <tr key=${m.ID}>
              <td>${m.ID}</td>
              <td><span className=${'tag ' + (m.ReceiverID === c.detalhe.UserID ? 'verde' : 'verm')}>
                ${m.ReceiverID === c.detalhe.UserID ? 'recebida' : 'enviada'}</span></td>
              <td><b>${m.Sender}</b><small>para ${m.Receiver}</small></td>
              <td>${m.Title}</td>
              <td className="mini">${String(m.Content || '').slice(0, 200)}</td>
              <td className="mini">${dt(m.SendTime)}</td>
              <td className="mini">${m.Annex1Name || ''}${m.Gold ? ` Gold ${m.Gold}` : ''}${m.Money ? ` Cupons ${num(m.Money)}` : ''}</td>
            </tr>`)}
          </tbody>
        </table>` : html`<p className="mini">Nenhuma mensagem.</p>`}
    <//>`;
}

function AbaAcoes({ c }) {
  return html`
    <${Card} titulo=${`Ações registradas (${c.acoes.length})`}>
      ${c.acoes.length ? html`
        <table className="tab">
          <thead><tr><th>#</th><th>Tipo</th><th>Conteúdo</th><th>Quando</th></tr></thead>
          <tbody>${c.acoes.map(a => html`
            <tr key=${a.ID}>
              <td>${a.ID}</td><td><b>${a.Type}</b></td>
              <td className="mini">${a.Content}</td><td className="mini">${dt(a.TimeCreate)}</td>
            </tr>`)}
          </tbody>
        </table>` : html`<p className="mini">Nada registrado.</p>`}
    <//>`;
}

function AbaPets({ c }) {
  return html`
    <${Card} titulo=${`Pets (${c.pets.length})`}>
      ${c.pets.length ? html`
        <table className="tab">
          <thead><tr><th>#</th><th>Nome</th><th>Nível</th><th>ATQ</th><th>DEF</th>
            <th>AGI</th><th>LUK</th><th>Sangue</th><th>Slot</th><th>Fome</th></tr></thead>
          <tbody>${c.pets.map(p => html`
            <tr key=${p.ID}>
              <td>${p.ID}</td><td>${p.Name}</td><td>${p.Level}</td>
              <td>${num(p.Attack)}</td><td>${num(p.Defence)}</td>
              <td>${num(p.Agility)}</td><td>${num(p.Luck)}</td>
              <td>${num(p.Blood)}</td><td>${p.Place}</td><td>${p.Hunger}</td>
            </tr>`)}
          </tbody>
        </table>` : html`<p className="mini">Nenhum pet.</p>`}
    <//>`;
}

function AbaPartidas({ c }) {
  const p = c.partidas;
  return html`
    <div className="aviso aviso">
      <b>Este servidor não grava replay.</b> Não é falha do painel: o código que
      registraria as partidas está desconectado no próprio servidor C#.
      <ul>
        <li><code>m_saveRecordTimer</code> é declarado mas nunca instanciado → <code>LogMgr.Save()</code> nunca roda;</li>
        <li><code>LogFightAdd</code> não tem nenhum chamador;</li>
        <li>o evento <code>GameOverLog</code> nunca é assinado → o <code>return</code> inicial aborta sempre;</li>
        <li><code>LogMoneyAdd</code> (a sobrecarga chamada) tem um <code>lock</code> vazio.</li>
      </ul>
      E mesmo ligado, <code>Log_Fight.Detail</code> guardaria só atributos dos jogadores
      em partida de <b>boss</b> — não a sequência de tiros. O projeto não grava ações.
    </div>
    <div className="grade">
      <${Card} titulo="Db_Tank41.Fight_Record">
        <${KV} linhas=${[['Total no banco', num(p.fightRecord)],
                         ['Registros desta conta', p.registros.length]]}/>
        ${p.registros.length ? html`
          <table className="tab mini-t">
            <thead><tr><th>Início</th><th>Time A</th><th>Time B</th><th>Vencedor</th></tr></thead>
            <tbody>${p.registros.map(r => html`
              <tr key=${r.ID}><td className="mini">${dt(r.PlayBegin)}</td>
                <td className="mini">${r.TeamA}</td><td className="mini">${r.TeamB}</td>
                <td>${r.WinTeam}</td></tr>`)}
            </tbody></table>` : html`<p className="mini">Nenhum registro.</p>`}
      <//>
      <${Card} titulo="Db_Count.Log_Fight">
        <${KV} linhas=${[['Total no banco', num(p.logFight)],
                         ['Gravador', 'LogMgr.Save() nunca executa']]}/>
        <p className="mini">A tabela existe e está vazia. Ver README.md para os 5 pontos de patch.</p>
      <//>
    </div>`;
}

/* ------------------------------------------------------------------ */
/* Lista de contas                                                     */
/* ------------------------------------------------------------------ */
function ViewContas({ avisar, irConta }) {
  const [q, setQ] = useState('');
  const [lista, setLista] = useState([]);

  const carregar = useCallback(async (busca) => {
    try { const d = await api('contas', { q: busca || '' }); setLista(d.contas); }
    catch (e) { avisar('erro', e.message); }
  }, [avisar]);

  useEffect(() => { carregar(''); }, [carregar]);

  return html`
    <div className="busca-grande">
      <input value=${q} placeholder="filtrar por usuário ou apelido"
             onChange=${e => setQ(e.target.value)}
             onKeyDown=${e => { if (e.key === 'Enter') carregar(q); }}/>
      <button className="primario" onClick=${() => carregar(q)}>Buscar</button>
      ${q ? html`<a className="limpar" onClick=${() => { setQ(''); carregar(''); }}>limpar</a>` : null}
    </div>
    <${Card} titulo=${`${lista.length} conta(s)`}>
      <table className="tab">
        <thead><tr><th>ID</th><th>Conta</th><th>Nível</th><th>Cupons</th>
          <th>Itens</th><th>Painel</th><th>Chat</th><th>Último acesso</th><th></th></tr></thead>
        <tbody>${lista.map(c => html`
          <tr key=${c.UserID}>
            <td>${c.UserID}</td>
            <td><b>${c.NickName}</b><small>${c.UserName}</small></td>
            <td>${c.Grade}</td>
            <td>${num(c.Money)}</td>
            <td>${c.Itens}</td>
            <td>${c.Ativo ? html`<span className="tag verde">${c.Papel}</span>` : html`<span className="tag cinza">—</span>`}</td>
            <td>${c.ChatAdmin ? html`<span className="tag verde">sim</span>` : html`<span className="tag cinza">—</span>`}</td>
            <td className="mini">${dt(c.LastDate)}</td>
            <td><a href="#ficha" onClick=${e => { e.preventDefault(); irConta(c.NickName); }}>ficha</a></td>
          </tr>`)}
        </tbody>
      </table>
    <//>`;
}

/* ------------------------------------------------------------------ */
/* Catálogo de itens                                                   */
/* ------------------------------------------------------------------ */
function ViewItens({ avisar }) {
  const [q, setQ] = useState('');
  const [itens, setItens] = useState([]);
  const [carregou, setCarregou] = useState(false);

  const buscar = useCallback(async () => {
    try { const d = await api('catalogo', { q }); setItens(d.itens); setCarregou(true); }
    catch (e) { avisar('erro', e.message); }
  }, [q, avisar]);

  useEffect(() => { const t = setTimeout(buscar, 350); return () => clearTimeout(t); }, [q, buscar]);

  return html`
    <div className="busca-grande">
      <input value=${q} placeholder="nome ou TemplateID do item"
             onChange=${e => setQ(e.target.value)} autoFocus/>
      <span className="mini">${carregou ? itens.length + ' resultado(s)' : 'digite para buscar'}</span>
    </div>
    <${Card} titulo="Catálogo">
      <div className="itens grade-itens">
        ${itens.map(i => html`<${ItemCell} key=${i.template} it=${i}/>`)}
        ${carregou && !itens.length ? html`<p className="mini">Nada encontrado.</p>` : null}
      </div>
    <//>
    <p className="mini">
      Os ícones são desenhados em SVG a partir da categoria e qualidade reais: as sprites do
      jogo ficam dentro do SWF (a coluna <code>Shop_Goods.Pic</code> guarda só a chave do
      recurso), não como arquivos soltos.
    </p>`;
}

/* ------------------------------------------------------------------ */
/* Auditoria                                                           */
/* ------------------------------------------------------------------ */
function ViewAuditoria({ avisar }) {
  const [d, setD] = useState(null);
  useEffect(() => { api('auditoria').then(x => setD(x)).catch(e => avisar('erro', e.message)); }, [avisar]);
  if (!d) return html`<p className="mini">Carregando…</p>`;

  return html`
    <div className="grade">
      <${Card} titulo=${`Ações (${d.acoes.length})`}>
        <table className="tab">
          <thead><tr><th>Quando</th><th>Admin</th><th>Ação</th><th>Alvo</th><th>Detalhe</th><th>IP</th></tr></thead>
          <tbody>${d.acoes.map(a => html`
            <tr key=${a.ID}>
              <td className="mini">${dt(a.Quando)}</td>
              <td>${a.AdminNome || '—'}</td>
              <td><b>${a.Acao}</b></td>
              <td>${a.AlvoNome}${a.AlvoUser ? html` <small>#${a.AlvoUser}</small>` : null}</td>
              <td className="mini">${a.Detalhe}</td>
              <td className="mini">${a.IP}</td>
            </tr>`)}
          </tbody>
        </table>
      <//>
      <${Card} titulo="Tentativas de login">
        <table className="tab">
          <thead><tr><th>Quando</th><th>Conta</th><th>IP</th><th>Resultado</th></tr></thead>
          <tbody>${d.tentativas.map((t, i) => html`
            <tr key=${i}>
              <td className="mini">${dt(t.Quando)}</td>
              <td>${t.UserName || '—'}</td>
              <td className="mini">${t.IP}</td>
              <td>${t.Sucesso ? html`<span className="tag verde">ok</span>`
                             : html`<span className="tag verm">falhou</span>`}</td>
            </tr>`)}
          </tbody>
        </table>
      <//>
    </div>`;
}

/* ------------------------------------------------------------------ */
const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(html`<${App}/>`);