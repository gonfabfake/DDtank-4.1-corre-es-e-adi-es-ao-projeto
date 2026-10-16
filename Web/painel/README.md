# Painel de administracao DDTank 4.1

Painel web em PHP para administrar contas do seu servidor local.

**URL:** http://127.0.0.1/painel/

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

A conta e a **mesma do portal**: a senha definida vale em
`http://127.0.0.1/` e no painel. A aba **Senha** troca as duas de uma vez.

---

## O que da para fazer

### Aba **Conta** (`conta.php`)

Digite o nome do usuario (ou apelido, ou UserID) e aparecem 6 abas:

| Aba | Origem |
|---|---|
| **Resumo** | `Sys_Users_Detail` (nivel, GP, moedas, combate, acesso), `Sys_VIP_Info`, `Consortia`, `Sys_User_Rank`, `Sys_User_Match_Info`, `Sys_Users_Fight`, contagem de `AchievementData`, dados do portal |
| **Itens** | `Sys_Users_Goods` x `Shop_Goods`: slot, atributos, +forja |
| **Mensagens** | `User_Messages` — as 300 mais recentes enviadas/recebidas, com conteudo, anexos, Gold e Cupons |
| **Acoes** | `Sys_Users_Log` — logins, abertura de baus, etc. |
| **Pets** | `Sys_Users_Pet` |
| **Partidas** | `Fight_Record` e `Db_Count.Log_Fight` |

### Aba **Contas** (`index.php`)
Lista com filtro, e edita: **nivel**, **cupons (Money)**, Gold/Offer/Medal,
acesso ao painel e **comandos de chat no jogo**.

### Aba **Itens** (`itens.php`)
Busca no catalogo (11.715 itens) por nome, TemplateID ou categoria.
Concede item (com logica de slot, move o antigo para a mochila, nunca
duplica) e remove item do inventario.

### Aba **Auditoria** (`auditoria.php`)
Tudo que foi feito, por quem, quando e de qual IP + tentativas de login.

---

## Regras do jogo respeitadas

- **Nivel** e derivado de GP via `Db_Tank.dbo.LevelInfo`. O painel le o GP
  da faixa oficial; nunca digita GP na mao.
- **Cupons** = coluna `Money` (e `INT`: teto 2.147.483.647).
- **Itens** entram por `SP_Users_Items_Add`, a mesma procedure que o
  servidor usa em `PlayerBussiness.AddGoods`. `ItemID` e IDENTITY,
  `ValidDate = 0` (nunca expira), `Hole1..6 = -1` (fechados).
- **Slot** de equipamento e `CategoryID - 1`: 0 chapeu, 2 cabelo, 4 roupa,
  5 olhos, **6 arma**, 7 pulseira, 9 anel, 11 terno, 12 colar.
  O painel **rejeita** item em slot incompativel.
- **Bolsa 0**: equipado 0..30, mochila 31..79. **Bolsa 1**: 0..48.

> **A conta precisa estar OFFLINE.** `GamePlayer.SaveIntoDatabase()` chama
> `SP_Users_Update`, que sobrescreve `Grade`/`GP`/`Money` com os valores em
> memoria. Com o jogador online, a mudanca e perdida no proximo save.

---

## Sobre replay de partida

**Este servidor nao grava replay, e o painel nao tem como mostrar o que
nao existe.** Nao e falha do painel: o codigo que registraria as partidas
esta desconectado no proprio servidor C#.

- `m_saveRecordTimer` e declarado mas nunca instanciado
  (`GameServer.cs:49, 205-206`) — `LogMgr.Save()` nunca roda;
- `LogFightAdd` (`Statics/LogMgr.cs:60`) **nao tem nenhum chamador**;
- o evento `GameOverLog` (`Game.Logic/BaseGame.cs:84`) nunca e assinado,
  entao o `return` de `BaseGame.cs:1513` aborta sempre;
- `LogMoneyAdd` — a sobrecarga realmente chamada — tem um
  `lock (m_LogMoney) ;` **vazio** em `LogMgr.cs:213-214`;
- `CountBussiness.SetConfig()` nunca e chamado, logo `CountRecord` fica
  `false` e os 4 metodos de `Db_Count.Count_*` ficam inertes.

Resultado: `Fight_Record` = 0 linhas, `Db_Count.Log_Fight` = 0,
`Log_Money` = 0, `Log_Item` = 0.

E mesmo ligado, `Log_Fight.Detail` guardaria so um texto de atributos dos
jogadores em partidas de **boss** (`PVEGame.cs:1048-1071`) — nao a sequencia
de tiros. **O projeto nao tem gravacao de acoes em lugar nenhum**, entao
"replay" no sentido de ver a partida de novo nao e possivel aqui.

O que a aba **Partidas** mostra hoje: os registros de `Fight_Record` e
`Db_Count.Log_Fight` (quando existirem) + esse aviso.

### Ligar o registro de partidas (para o futuro)

Nao e config, e patch de codigo. Sao 5 pontos:

| # | Arquivo | O que fazer |
|---|---|---|
| 1 | `Game.Server\Server\GameServer.cs:205` | instanciar `m_saveRecordTimer = new Timer(new TimerCallback(this.SaveRecordProc), null, num3, num3)` antes do `.Change()` |
| 2 | `GameServer.cs` / `BaseGame.cs` | assinar `game.GameOverLog += LogMgr.LogFightAdd` |
| 3 | `Game.Server\Server\Statics\LogMgr.cs:213` | `lock (...) ;` -> `lock (...) LogMgr.m_LogMoney.Rows.Add(objArray);` |
| 4 | `CountBussiness.cs` | chamar `SetConfig(connStr, AppID, SubId, ServerID, Configuration.CountRecord)` no startup |
| 5 | `ItemRecordBussiness.cs:151-165` | alinhar os 15 `ColumnMappings` as 16 colunas reais (`LogMgr.cs:373-389`) — hoje grava `SpareMoney` em `Gold`, `Medal` em `Offer`, etc. |

Requer recompilar os servicos e reiniciar. Fale se quiser que eu faca.

---

## Seguranca

| Medida | Onde |
|---|---|
| So `127.0.0.1` / `::1` | `PAINEL_IP_LIBERADOS` em `_lib.php` |
| Login pela conta real do jogo | `entrar.php` (MD5, igual ao portal) |
| Autorizacao por `Admin_Usuarios` | conta precisa estar na tabela |
| CSRF em todo POST | `csrf_validar()` |
| Limite de forca bruta | 5 falhas / 15 min por conta |
| Sessao | cookie `HttpOnly`, `SameSite=Strict`, regenerada no login |
| SQL | **sempre** prepared statement (o codigo legado concatena e e injectionavel — nao copie) |
| Saida | sempre `htmlspecialchars` via `e()` |
| Cabecalhos | `nosniff`, `DENY` frame, `no-store` (`painel\web.config`) |
| Todas as acoes | gravadas em `Admin_Auditoria` com IP |

Liberar a rede: descomente a linha do IP em `_lib.php`. **Nao recomendo** —
o painel da poder total sobre as contas.

> **Armadilha ja vista aqui:** `sqlsrv` devolve colunas `datetime` como
> objetos `DateTime`, nao string. `e()` trata isso. E controle de transacao
> tem que usar `sqlsrv_begin_transaction/commit/rollback` — `"BEGIN TRAN"`
> como texto **nao funciona** (MARS).

---

## Arquivos

```
Web\painel\
  _lib.php            nucleo: config, IP, sessao, CSRF, SQL, auth, dominio
  entrar.php  sair.php  senha.php
  primeiro_acesso.php  token de uso unico
  index.php           lista + edicao (nivel, cupons, moedas, acesso)
  conta.php           ficha completa com 6 abas
  itens.php           catalogo + conceder/remover
  auditoria.php       historico
  assets\painel.css
  web.config

Tools\ContaBoost\
  gera-token-painel.ps1   gera/cancela o token
  teste-conta.ps1         27 verificacoes da ficha
  teste-painel.ps1        10 verificacoes do fluxo completo
```

## Banco

Criado em `Db_Tank41`:

- `Admin_Usuarios` — quem acessa o painel (`UserID`, `Papel` admin/leitura)
- `Admin_Auditoria` — log de acoes
- `Admin_Tentativas` — tentativas de login

Script: `Tools\ContaBoost\painel_01_schema.sql` (idempotente).
`usaimbot` (UserID 25) ja esta como `admin` e em `Game_Commands` com
`AllCommands` (libera `$ban$`, `$kick$`, `$mute$`, `$unban$`, `$bugbot$`).