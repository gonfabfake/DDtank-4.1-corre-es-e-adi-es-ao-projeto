# Segurança — SQL Server e acesso do front-end

Data: 2026-10-16

## O que estava exposto

| Problema | Antes | Depois |
|---|---|---|
| Porta 1433 (SQL Server) escutando | `0.0.0.0` — alcançável da rede | regra de firewall bloqueia inbound TCP 1433 |
| Login usado pelo PHP | `sa` / `123456` (sysadmin) | `ddt_web`, sem nenhum papel de servidor |
| Credencial no banco de código | `Web\global.php` (dentro do docroot) | `C:\DDTank4.1-main\secrets\db.php` (fora do docroot) |
| Leitura de `master..syslogins` | liberada | negada para `ddt_web` |

## Login `ddt_web`

Senha em `C:\DDTank4.1-main\secrets\db.php` (32 caracteres aleatórios, fora do docroot).
Arquivo tem guarda anti-acesso-direto: se for aberto pelo navegador/web server sem a
constante `DDT_CREDENTIALS_LOADED`, responde **403** e sai.

Permissões — `db_datareader` + `db_datawriter` em `Db_Membership`, `Db_Tank41`,
`Db_Tank`, `Db_Count`. **Sem** papéis de servidor, **sem** `db_owner`, **sem**
`db_ddladmin`.

Testado (bloqueado, como esperado):

- `ALTER LOGIN sa` → bloqueado
- `CREATE DATABASE` → bloqueado
- `CREATE LOGIN` → bloqueado
- `DROP TABLE` / `ALTER TABLE` → bloqueado
- `xp_cmdshell` → bloqueado
- `SELECT master..syslogins` → bloqueado
- `TRUNCATE` → bloqueado (exige `ALTER`, não é `datawriter` — irrelevante para o site)

Testado (permitido, como esperado):

- `SELECT` nos 4 bancos, inclusive cruzado `Db_Tank41` → `Db_Tank.dbo.LevelInfo`
- `INSERT` / `UPDATE` / `DELETE` (usado pelo cadastro, login e recarga)
- `EXECUTE` de procedures (padrão do `public`)

## Firewall

Regra: `DDT - BLOQUEAR SQL Server 1433 (somente local)`
`Inbound` · `Block` · `TCP` · `LocalPort 1433` · `Profile Any` · `RemoteAddress Any`

O tráfego em `127.0.0.1` **não passa** pelo Firewall do Windows, então site,
PHP e serviços do jogo seguem funcionando sem alterar nada.

> ⚠️ **Não dá para validar essa regra a partir da própria máquina:** uma conexão
> para o próprio IP (`10.141.8.243`) é tratada como local e ignora o filtro
> inbound, então `Test-NetConnection 10.141.8.243 -Port 1433` continua
> retornando `True` mesmo bloqueado. **Teste de verdade:** de outro dispositivo
> na mesma rede, `Test-NetConnection 10.141.8.243 -Port 1433` deve falhar.

Reverter, se precisar:
```powershell
Remove-NetFirewallRule -DisplayName 'DDT - BLOQUEAR SQL Server 1433 (somente local)'
```
(elevado). O script que aplica isso: `Tools\ContaBoost\aplica-seguranca-sql.ps1`.

## Ainda usa `sa` (não alterado — exige reiniciar serviços)

Estes pontos continuam com `User ID=sa;Password=123456` em texto plano:

- `Request\Web.config` (linhas 16-17) e `Request\Tank.Request\Web.config` (15-16)
- `Web\web.config` (linhas 17-19, 22)
- `Source Server\Center.Service\App.config` (5-7)
- `Source Server\Fighting.Service\app.config` (15-16)
- `Source Server\Road.Service\App.config` (44-46; a linha 5 aponta para outra máquina, `DESKTOP-SVB8MA9\DIEGO4458`, e parece não ser a usada)

Agora isso só é alcançável de dentro da máquina (firewall), o que já reduz muito
o risco. Migrar cada serviço para um login próprio exige reiniciá-los — diga se
quer que eu faça.

## Armadilha encontrada durante a troca

O PowerShell 5.1 escreve `Set-Content -Encoding UTF8` com **BOM**. O arquivo
`secrets/db.php` foi criado assim e o PHP passou a ecoar os bytes do BOM antes de
qualquer saída — o `FastCgiModule` derrubava a requisição com **500.0 / código
`0x00000000`** e o IIS só exibia "erro fatal", sem nenhuma pista.

Correção: gravar com `UTF8Encoding($false)` (sem BOM). Ao escrever PHP a partir
do PowerShell, use sempre:

```powershell
$enc = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($caminho, $conteudo, $enc)
```

## Diagnóstico

- `php.ini`: `display_errors = Off`, `log_errors = On`, sem `error_log` definido.
  Para depurar PHP pela web, ligue `display_errors` temporariamente **ou** aponte
  `error_log` para um arquivo.
- `C:\Windows\System32\inetsrv\config\applicationHost.config` e `appcmd.exe`
  exigem elevação — sem isso não dá para ver sites/pools do IIS.