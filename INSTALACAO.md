# DDTank 4.1 — Estado final e como operar

Máquina: DESKTOP-M0TALU5 · Windows 11 Pro (10.0.26200)

---

## 1. Um comando sobe tudo

```powershell
C:\DDTank4.1-main\Start-Server.ps1 -Action start    # sobe tudo
C:\DDTank4.1-main\Start-Server.ps1 -Action status   # verifica
C:\DDTank4.1-main\Start-Server.ps1 -Action stop     # derruba tudo
```

O `-Action start` sobe: 3 servidores de jogo + front-end PHP (porta 8080).
O IIS é serviço do Windows, então basta estar ligado (inicia automaticamente).

**Ordem obrigatória:** Center precisa estar no ar antes de Road — o servidor de jogo
liga para a porta 9202 durante a inicialização e aborta se ninguém responder. O script já faz isso.

### URLs

| O quê | Endereço |
|---|---|
| Front-end (login/portal) | **http://127.0.0.1:8080/** |
| Handlers ASP.NET (o que o Flash chama) | **http://127.0.0.1/request/** |
| WCF CenterService (mex/wsdl) | http://127.0.0.1:2008/CenterService/ |

---

## 2. Estado verificado

```
[1] SERVIDORES DE JOGO
    Road     -> GameServer is now open for connections!   (porta 9200)
    Center   -> GameServer is now open for connections!   (porta 9202 + WCF)
    Fighting -> GameServer is now open for connections!   (porta 9208)
    Road -> conexoes reais: 1433 (SQL), 9202 (Center), 9208 (Fighting)

[2] BANCOS:  Db_Tank41, Db_Tank, Db_Membership, Db_Count

[3] WCF:     http://127.0.0.1:2008/CenterService/ -> HTTP 200

[4] HANDLERS ASP.NET (consultando o SQL de verdade)
    /request/ServerList.ashx      -> <Result value="true" message="Success!" agentId="1001" .../>
    /request/MapServerList.ashx   -> Build:MapServerList.xml,Success!
    /request/BallList.ashx        -> Build:BallList.xml,Success!
    /request/ShopItemList.ashx    -> Build:ShopItemList.xml,Success!

[5] FRONT-END PHP: http://127.0.0.1:8080/ -> HTTP 200, 25308 bytes (formulario de login)
```

---

## 3. Instalações feitas

| Item | Versão / detalhe |
|---|---|
| Visual Studio Build Tools 2022 | MSBuild 17.14 + .NET Framework 4.8 targeting pack |
| SQL Server 2022 Express | instância padrão `MSSQL`, porta **1433**, mixed mode, `sa`/`<senha-local>` |
| 7-Zip 26.04 | extração do pacote de release |
| PHP 8.1.34 (ZTS x64) | + `sqlsrv`/`pdo_sqlsrv` (msphpsql 5.12.0) |
| IIS | WebServerRole, ASPNET45, NetFxExtensibility45, CGI/FastCGI |

---

## 4. Decisões que exigiram diagnóstico

### Build
* **`<Analyzer>` inválidos** em `Fighting.Service.csproj` e `Game.Server.csproj` — apontavam para DLLs
  comuns (resíduo de decompilação). Com build paralelo o CSC recebia-as como *source files* →
  `CS0006`. Removidos; as dependências já eram `ProjectReference`.
* **Retarget `v4.7.2` → `v4.8`**: só existe targeting pack 4.8 no Windows 11
  (o README do projeto diz 4.5.2 — desatualizado).

### SQL Server
* O instalador deixou o listener numa **porta dinâmica (62569)**. Fixei em **1433** na chave
  `MSSQL16.MSSQL\MSSQLServer\SuperSocketNetLib\Tcp\IPAll` — que é **um nível abaixo** do caminho
  óbvio (`MSSQL16.MSSQL\SuperSocketNetLib\...`), onde a alteração não tem efeito.

### WCF / HTTP.sys
* `AddressAccessDeniedException` em `http://+:2008/CenterService/` → reservas de URL criadas.
* Detalhe do ambiente: Windows **em português**, então `netsh http add urlacl user=Everyone`
  falha com *erro 1332*. Usei a conta real.

### Banco
* `Db_Count` **não tem backup no repositório**. Reconstruí o schema a partir de
  `Game.Server\Statics\LogMgr.cs`, `Center.Server\Statics\LogMgr.cs` e dos mapeamentos
  `SqlBulkCopy` de `Bussiness\ItemRecordBussiness.cs` → `Db\Query\Create Db_Count.sql`.
* `Edition` no banco já era `2612558`, o valor que o código exige — não precisou ajuste.

### Recursos ausentes do repositório
Baixados do release oficial (`DDTank4.1.0.0.0.1.rar`, 138 MB):
489 **mapas** (`map\<ID>\fore.map` — sem eles o servidor de jogo não sobe),
`Languages\Language-vn.txt` real (656 KB), `datas\`, `xmls\`, `bomb\`, `battle.xml`.
Copiei **só os dados** — `.exe`/`.dll`/`.config` do release foram ignorados.

### PHP
* **`sqlsrv.WarningsReturnAsErrors = Off`** em `php.ini`. Esse era o bloqueio real:
  o driver devolvia **apenas** mensagens informativas `SQLSTATE 01000`
  (*"Changed database context"*) e, com a diretiva ligada, `sqlsrv_connect()` retornava `false` —
  a conexão **era** bem-sucedida, só reportada como falha.
* `Web\Models\index.php` tinha caminho absoluto do PC do autor
  (`C:\Dev\DDTank\DDTank4.1\Web\Models\`) → substituído por `__DIR__`.
* `<authentication mode="Anonymous">` que eu havia colocado era **inválido** — o ASP.NET só aceita
  `None`, `Windows`, `Passport`, `Forms`. Corrigido para `None`.

### IIS
* `LuaInterface.dll` é **nativo 32-bit** → os pools precisam rodar em 32-bit
  (`enable32BitAppOnWin64:True`), senão `BadImageFormatException`.
* `IIS-CGI` (módulo FastCGI) **não estava instalado** — habilitei.
* ⚠️ **Não edite mais o `applicationHost.config`**: uma tentativa minha duplicou a seção `fastCgi`,
  derrubou o IIS (503) e corrompeu `C:\inetpub\temp\apppools\`. Recuperei com o backup
  (`applicationHost.config.ddtank.bak`) + limpeza do temp. Por isso o **front-end PHP roda pelo
  servidor embutido do PHP na porta 8080**, e não via FastCGI no IIS — mais simples e sem risco.

---

## 5. Limitações conhecidas

* **Front-end PHP na porta 8080**, não na 80. A origem é diferente da do `/request` (porta 80).
  O Flash pode lidar via `crossdomain.xml`, mas se o cliente reclamar de política de segurança,
  é aqui que se olha.
* **Tabelas de recharge ausentes**: `Pacotes`, `PersonalData`, `PicPayPayment` não existem em
  nenhum dos três `.BAK` e não foram publicadas. As páginas de recarga/CPF/PicPay falham.
  Não há como recuperá-las.
* `AttRank.csproj` (v4.0, fora da solução) **não compila** — declara `download.ico`, que não existe.
* Build **só em `Debug`**: vários `HintPath` apontam para `bin\Debug`; `Release` quebraria.
* Erros nos logs são **só** artefatos opcionais ausentes (`bomb\*.bomb`). Nenhum impacto no gameplay —
  `BallMgr Init: True`.
* `Db\Query\Reset all Server.sql` é destrutivo (apaga jogadores e webaccounts e **derruba** a tabela
  `Sys_VIP_Info2`). Não foi executado.
* **Segurança**: SQL em mixed mode com `sa`/`<senha-local>`, tudo em `127.0.0.1`. Adequado para uso local;
  **não** exponha em rede.

---

## 6. Patch Flash v2

Aplicado e testado. Detalhes, evidências e o material que falta para gerar o SWF em
**`RELATORIO_PATCH_FLASH.md`**. Resumo: os 4 testes do pacote passam, o patch é idempotente e o
teclado do PC está preservado (as flags mobile foram somadas com `||`, não substituíram nada).
**O SWF não foi compilado** — o repositório não traz projeto de build, e os binários existentes têm
os corpos ABC criptografados, então a partição de fontes por módulo é irrecuperável.