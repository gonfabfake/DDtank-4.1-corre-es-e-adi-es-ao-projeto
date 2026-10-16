# Painel de administracao DDTank 4.1

Painel web em **React** (sem build step) para administrar contas do seu servidor local.

**URL:** http://127.0.0.1/painel/

---

## Arquitetura

```
navegador  ──app.php──>  casca + window.BOOT (sessao, csrf, limites)
     │
     ├──GET  api.php?a=...        leitura
     └──POST api.php?a=acao       escrita (csrf + papel)
                │
                └──_lib.php  IP, sessao, CSRF, prepared statements, auth
                   _itens.php  regra de slot e SP_Users_Items_Add
```

O PHP cuida de **sessão, permissão, CSRF e dados**; o React cuida de **toda a
interface**. O React 18 e o htm estão **vendorizados localmente** em
`assets/vendor/` — nada é baixado de CDN em tempo de execucao, entao o painel
funciona offline.

`htm` permite escrever template literals Tagged em vez de JSX, o que dispensa
compilacao. Componente e `html\`<div>...</div>\``, fechamento `<\//>`.

### Paginas

| Arquivo | Papel |
|---|---|
| `app.php` | casca que monta o React e injeta `window.BOOT` |
| `index.php` | redireciona para `app.php` |
| `api.php` | API JSON (GET leitura, POST escrita) |
| `_lib.php` | nucleo: config, allowlist de IP, sessao, CSRF, `db_query` preparado, auth, dominio do jogo |
| `_itens.php` | regra de bolsa/slot + `conceder_item` |
| `assets/app.js` | a aplicacao React |
| `assets/app.css` | layout |
| `assets/vendor/` | react, react-dom, htm |

As paginas antigas server-rendered (`conta.php`, `itens.php`, `auditoria.php`,
`senha.php`) continuam funcionando e sao uteis como fallback/cURL.

### Endpoints

```
GET  api.php?a=bootstrap            limites da UI
GET  api.php?a=contas&q=            lista
GET  api.php?a=conta&q=             ficha completa (itens, mensagens, acoes, pets, partidas)
GET  api.php?a=catalogo&q=&cat=     catalogo
GET  api.php?a=auditoria            log + tentativas
POST api.php?a=acao                 nivel | cupons | moedas | admin | chat
                                    conceder | remover
```

---

## Imagens dos itens

O jogo **nao expoe as sprites como arquivo**: `Shop_Goods.Pic` esta preenchido
em 11.714 dos 11.715 itens, mas guarda a **chave do recurso dentro do SWF**
(`20prize`, `Bindingvoucher`, `maya`...). As imagens reais vivem no
`ResourceCache`, com nome SHA-1 e sem extensao — impossivel mapear por
TemplateID sem extrair o SWF.

Entao os icones sao **desenhados em SVG** a partir de dados reais
(`CategoryID` + `Quality`), com uma silhueta por categoria e cor/borda por
qualidade. Funciona para os 11.715 itens, sem nenhum asset externo. A chave
`Pic` aparece no tooltip do item.

As 36 imagens que existem de fato (`Web\Assets\images\img\*`, 6 por pasta) sao
decorativas do template do portal e nao mapeiam para itens.

Qualidade usa a mesma convencao do proprio jogo (`Web\function.php`):
1 Thô · 2 Thường · 3 Bom · 4 Exelente · 5 Perfeita · 6 Ouro.

---

## Primeiro acesso

1. Abra http://127.0.0.1/painel/primeiro_acesso.php
2. Cole o token de `C:\DDTank4.1-main\secrets\painel_token.txt`
3. Escolha a conta e a senha (marque "Promover a admin do painel")
4. Depois entre em http://127.0.0.1/painel/

O token e de **uso unico** e se invalida depois. Para gerar outro:

```powershell
.\Tools\ContaBoost\gera-token-painel.ps1
.\Tools\ContaBoost\gera-token-painel.ps1 -Apagar   # cancela
```

A conta e a **mesma do portal**: a senha definida vale em `http://127.0.0.1/` e
no painel. A aba **Senha** troca as duas de uma vez.

---

## O que da para fazer

### Ficha da conta
Digite nome, apelido ou UserID. Abre a ficha com 6 abas:

| Aba | Origem |
|---|---|
| **Resumo** | `Sys_Users_Detail`, `Sys_VIP_Info`, `Consortia`, `Sys_User_Rank`, `Sys_User_Match_Info`, `Sys_Users_Fight`, `AchievementData`, portal |
| **Itens** | `Sys_Users_Goods` x `Shop_Goods` — icone SVG, slot, atributos, +forja |
| **Mensagens** | `User_Messages` — 300 mais recentes, com conteudo, anexos, Gold e Cupons |
| **Acoes** | `Sys_Users_Log` |
| **Pets** | `Sys_Users_Pet` |
| **Partidas** | `Fight_Record` e `Db_Count.Log_Fight` |

### Contas
Lista com filtro e contagem de itens por conta. Clique em **ficha** para abrir.

### Itens
Busca com debounce nos 11.715 itens. Concede item (move o anterior para a
mochila, nunca duplica) e remove do inventario.

### Auditoria
Tudo que foi feito, por quem, quando e de qual IP + tentativas de login.

---

## Regras do jogo respeitadas

- **Nivel** e derivado de GP via `Db_Tank.dbo.LevelInfo`. O painel le o GP da
  faixa oficial; nunca digita GP na mao.
- **Cupons** = coluna `Money` (`INT`: teto 2.147.483.647).
- **Itens** entram por `SP_Users_Items_Add`, a mesma procedure de
  `PlayerBussiness.AddGoods`. `ItemID` e IDENTITY, `ValidDate = 0`,
  `Hole1..6 = -1`.
- **Slot** de equipamento e `CategoryID - 1`: 0 chapeu, 2 cabelo, 4 roupa,
  5 olhos, **6 arma**, 7 pulseira, 9 anel, 11 terno, 12 colar. O painel
  **rejeita** item em slot incompativel.
- **Bolsa 0**: equipado 0..30, mochila 31..79. **Bolsa 1**: 0..48.

> **A conta precisa estar OFFLINE.** `GamePlayer.SaveIntoDatabase()` chama
> `SP_Users_Update`, que sobrescreve `Grade`/`GP`/`Money` com os valores em
> memoria. Online, a mudanca e perdida.

---

## Sobre replay de partida

**Este servidor nao grava replay, e o painel nao tem como mostrar o que nao
existe.** Nao e falha do painel: o codigo que registraria as partidas esta
desconectado no proprio servidor C#.

- `m_saveRecordTimer` e declarado mas nunca instanciado
  (`GameServer.cs:49, 205-206`);
- `LogFightAdd` (`Statics/LogMgr.cs:60`) **nao tem nenhum chamador**;
- o evento `GameOverLog` (`Game.Logic/BaseGame.cs:84`) nunca e assinado, entao
  o `return` de `BaseGame.cs:1513` aborta sempre;
- `LogMoneyAdd` — a sobrecarga realmente chamada — tem um
  `lock (m_LogMoney) ;` **vazio** (`LogMgr.cs:213-214`);
- `CountBussiness.SetConfig()` nunca e chamado, logo `CountRecord` fica
  `false`.

Resultado: `Fight_Record` = 0 linhas, `Log_Fight` = 0, `Log_Money` = 0.

E mesmo ligado, `Log_Fight.Detail` guardaria so um texto de atributos dos
jogadores em partida de **boss** (`PVEGame.cs:1048-1071`) — nao a sequencia de
tiros. **O projeto nao tem gravacao de acoes.**

### Ligar o registro (patch de codigo, requer recompilar)

| # | Arquivo | O que fazer |
|---|---|---|
| 1 | `Game.Server\Server\GameServer.cs:205` | instanciar `m_saveRecordTimer` antes do `.Change()` |
| 2 | `GameServer.cs` / `BaseGame.cs` | assinar `game.GameOverLog += LogMgr.LogFightAdd` |
| 3 | `Game.Server\Server\Statics\LogMgr.cs:213` | `lock (...) ;` -> `lock (...) m_LogMoney.Rows.Add(objArray);` |
| 4 | `CountBussiness.cs` | chamar `SetConfig(...)` no startup |
| 5 | `ItemRecordBussiness.cs:151-165` | alinhar os 15 `ColumnMappings` as 16 colunas reais |

---

## Seguranca

| Medida | Onde |
|---|---|
| So `127.0.0.1` / `::1` | `PAINEL_IP_LIBERADOS` em `_lib.php` |
| Login pela conta real do jogo | `entrar.php` (MD5, igual ao portal) |
| Autorizacao por `Admin_Usuarios` | papel `admin` ou `leitura` |
| CSRF em todo POST | `csrf_validar()`; o token vai no `window.BOOT` |
| Limite de forca bruta | 5 falhas / 15 min por conta |
| Sessao | `HttpOnly`, `SameSite=Strict`, regenerada no login |
| SQL | **sempre** prepared statement |
| Saida | React escapa por padrao (o `dangerouslySetInnerHTML` nao e usado) |
| Cabecalhos | `nosniff`, `DENY` frame, `no-store` |
| Auditoria | toda acao gravada com IP |

Liberar a rede: descomente o IP em `_lib.php`. **Nao recomendo.**

> **Armadilhas ja vistas aqui:**
> - `sqlsrv` devolve `datetime` como objeto `DateTime` — a API converte com
>   `data_txt()` antes do `json_encode`.
> - Controle de transacao tem que usar `sqlsrv_begin_transaction/commit/rollback`;
>   `"BEGIN TRAN"` como texto **nao funciona** (MARS).
> - Ao escrever PHP pelo PowerShell, grave **sem BOM**
>   (`UTF8Encoding($false)`) — senao o FastCGI responde 500.0 sem nenhuma pista.

---

## Testes

```powershell
.\Tools\ContaBoost\teste-api.ps1     # 35 verificacoes: SPA, vendor, API, CSRF, validacoes
.\Tools\ContaBoost\teste-conta.ps1   # 27 verificacoes da ficha server-rendered
.\Tools\ContaBoost\teste-painel.ps1  # fluxo completo: primeiro acesso -> login -> acoes
```

---

## Banco

Criado em `Db_Tank41`:

- `Admin_Usuarios` — quem acessa o painel
- `Admin_Auditoria` — log de acoes
- `Admin_Tentativas` — tentativas de login

Script: `Tools\ContaBoost\painel_01_schema.sql` (idempotente).
`usaimbot` (UserID 25) ja esta como `admin` e em `Game_Commands` com
`AllCommands` (libera `$ban$`, `$kick$`, `$mute$`, `$unban$`, `$bugbot$`).