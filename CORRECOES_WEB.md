# Correções aplicadas na camada web

Este registro existe porque os problemas **não são óbvios** e voltam a aparecer
se alguém mexer no `Tank.Request.dll`, no `Web.config` ou no `applicationHost.config`.

---

## 1. Arquitetura final (e por que ela é essa)

| URL | Quem serve | Como |
|---|---|---|
| `http://127.0.0.1/` | IIS (porta 80) | `Web\index.htm` → redireciona para `:8080` |
| `http://127.0.0.1:8080/` | **servidor embutido do PHP** | portal/login — `C:\php\php.exe -S 127.0.0.1:8080 -t Web` |
| `http://127.0.0.1/request/` | IIS (porta 80) | handlers ASP.NET do cliente Flash |
| `http://127.0.0.1/Flash/...` | IIS (porta 80) | assets do cliente |

O SWF é carregado da **porta 80** e chama `/request/` na **porta 80** — mesma origem,
então não há problema de cross-domain para o cliente Flash. Só o portal PHP fica em 8080.

Dois *application pools* separados, porque os dois runtimes são de arquiteturas diferentes:

| Pool | Bits | Serve | Motivo |
|---|---|---|---|
| `DDTankWebPool` | **64-bit** | raiz `/` | o `php-cgi` é build x64 |
| `DDTankPool` | **32-bit** | `/request` | `Request\bin\LuaInterface.dll` é **nativo 32-bit** |

Um pool só não funciona: worker de 32-bit não carrega `php-cgi` x64 (dá 500), e o
`/request` em 64-bit dá `BadImageFormatException`.

---

## 2. O FastCGI do IIS não aceitou o registro do php-cgi

**Este item está em aberto.** Tentativas, na ordem:

1. `appcmd set config /section:system.webServer/fastcgi /applicationSettings ...`
   → `Atributo desconhecido "applicationSettings"`.
2. `appcmd add config ...` → `Não há suporte para o comando 'ADD' no objeto 'CONFIG'`.
3. Editar `applicationHost.config` com XML → `Elemento não reconhecido 'add'`.
4. O mesmo no `Web\web.config` → `Elemento não reconhecido 'applicationSettings'`.
5. Módulo `WebAdministration` → travou em `Get-WebConfigurationProperty`.

O que **está** correto e foi verificado:

* `MODULE "FastCgiModule"` carregado e habilitado;
* handler `PHP_via_FastCGI` presente no `<handlers>` apontando para `C:\php\php-cgi.exe`;
* `php-cgi -v` responde (`PHP 8.1.34 cgi-fcgi`);
* PHP de `C:\php` conecta nos 4 bancos e serve o portal (25.308 bytes) pelo servidor embutido;
* ACL de leitura concedida a `IIS AppPool\DDTankWebPool`, `DDTankPool` e `DefaultAppPool` em `C:\php`.

**PHP foi movido para `C:\php`** de propósito: estava em `C:\Users\gonfa\AppData\...`,
que o pool de aplicações não enxerga (identidade de outro usuário).

### ⚠️ Não mexa em `applicationHost.config`

Duas vezes ele derrubou o IIS inteiro (HTTP 503) e corrompeu
`C:\inetpub\temp\apppools\DDTankPool\DDTankPool.config`, deixando o pool ser
**desabilitado automaticamente**.

**Sequência de recuperação que funciona:**

```powershell
Stop-Service W3SVC -Force
Get-Process w3wp | Stop-Process -Force
Get-ChildItem 'C:\inetpub\temp\apppools' -Force | Remove-Item -Recurse -Force
Start-Service W3SVC
appcmd delete apppool DDTankPool
appcmd add apppool /name:DDTankPool
appcmd set apppool DDTankPool /managedRuntimeVersion:v4.0 /managedPipelineMode:Integrated /enable32BitAppOnWin64:True
appcmd set vdir "Default Web Site/" /physicalPath:"C:\DDTank4.1-main\Web"
appcmd add app /site.name:"Default Web Site" /path:/request /physicalPath:"C:\DDTank4.1-main\Request"
appcmd set app "Default Web Site/" /applicationPool:DDTankPool
appcmd set app "Default Web Site/request" /applicationPool:DDTankPool
appcmd start apppool /apppool.name:DDTankPool
```

**Regra:** parar o serviço **antes** de limpar o temp. Limpar com o serviço no ar
corrompe o artefato de novo.

Backups: `applicationHost.config.ddtank.bak`, `.pre-fcgi.bak`, `.pre-fcgi2.bak`,
`.pre-fastcgi2.bak` … `.pre-fastcgi5.bak`.

---

## 3. `Tank.Request.dll` precisa estar em `Request\bin\` — mas envenena 5 handlers

O site `Request\` é uma **ASP.NET Website clássica**: os handlers são compilados em
runtime a partir de `*.ashx.cs`. A biblioteca `Source Server\Tank.Request\Tank.Request\`
define as **mesmas** classes, e o `bin\` tem precedência sobre o code-behind.

**Teste decisivo:** renomear `Request\bin\Tank.Request.dll` → **todos** os handlers
passam a dar HTTP 500. O DLL é obrigatório.

Cinco dos fontes da biblioteca perderam a interface na decompilação:

| Fonte | Antes | Depois |
|---|---|---|
| `Tank.Request\ActiveList.cs` | `public class ActiveList` | `... : IHttpHandler` |
| `Tank.Request\AccountRegister.cs` | `public class AccountRegister` | `... : IHttpHandler` |
| `Tank.Request\commitweeklyuserrecord.cs` | `public class commitweeklyuserrecord` | `... : IHttpHandler` |
| `Tank.Request\ConsortiaAllyList.cs` | `public class ConsortiaAllyList` | `... : IHttpHandler` |
| `Tank.Request\runetemplatelist.cs` | `public class runetemplatelist` | `... : IHttpHandler` |

Todos já tinham `using System.Web;`, `IsReusable` e `ProcessRequest` — só a lista de
interfaces faltava. Sem a correção: `Tipo 'Tank.Request.X' não herda de 'System.Web.IHttpHandler'`.

> Os outros 109 tipos duplicados **funcionam** porque no DLL já implementam `IHttpHandler`.
> Por isso o bug parecia intermitente e afetava só 5.

---

## 4. `<authentication mode="Anonymous">` é inválido

`Web\web.config` e `Request\Web.config` tinham `mode="Windows"`, que exigiria Windows
Authentication. Troquei primeiro para `mode="Anonymous"` — **isso não existe**. Os
valores aceitos são `None`, `Windows`, `Passport`, `Forms`. O certo para acesso
anônimo é **`None`**. Ambos os arquivos estão com `mode="None"`.

---

## 5. Handlers que exigem parâmetro (não são bug)

4 handlers em 99 dão 500 sem query string, porque leem `context.Request[...]`:

| Handler | Parâmetros |
|---|---|
| `ActivePullDown.ashx` | `selfid`, `activeID`, `key`, `activeKey` |
| `VisualizeItemLoad.ashx` | `sex` |
| `MapWeekList.ashx`, `click.aspx` | `selfid` |

Chamados com o parâmetro certo, respondem (`VisualizeItemLoad.ashx?sex=true` → 200 com
dados reais do banco).

Outros 4 dão timeout por redirecionarem para `16wan.com` / `51wan.com` (inexistentes),
e 2 (`LoginTest.aspx`, `SendItemTest.aspx`) são páginas de teste do projeto.

**Baseline: 89 de 99 respondem 200 sem parâmetro; os outros 10 são isso acima.**

---

## 6. Verificação dos handlers

```powershell
$root='C:\DDTank4.1-main\Request'
$eps = Get-ChildItem "$root\*.ashx","$root\*.aspx" -File | ForEach-Object { $_.Name }
foreach ($e in $eps) {
  try { $r=Invoke-WebRequest "http://127.0.0.1/request/$e" -UseBasicParsing -TimeoutSec 60
        if ($r.StatusCode -ne 200) { "  {0,-32} HTTP {1}" -f $e,$r.StatusCode } }
  catch { "  {0,-32} HTTP {1}" -f $e, $(if ($_.Exception.Response){$_.Exception.Response.StatusCode.value__}else{'timeout'}) }
}
```

Varredura em subpastas (`/Tank.Request/`, `/Service References/`, `/Illegalcharacters/`)
não vale: são cópias de fontes e pastas de apoio, não endpoints.