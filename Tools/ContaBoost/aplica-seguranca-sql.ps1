# =====================================================================
#  aplica-seguranca-sql.ps1
#  Executar como ADMINISTRADOR (ele mesmo pede a elevacao).
#
#  1. Cria regra de firewall que BLOQUEIA o SQL Server (TCP 1433) de fora.
#     O trafego em 127.0.0.1 nao passa pelo Firewall do Windows, entao
#     o site, o PHP e os servicos do jogo continuam funcionando.
#  2. Testa antes/depois e grava o resultado no log.
# =====================================================================
$ErrorActionPreference = 'Stop'
$log = 'C:\DDTank4.1-main\Tools\ContaBoost\seguranca-sql.log'
function Say($m) {
    $line = ('[{0}] {1}' -f (Get-Date -Format 'HH:mm:ss'), $m)
    Write-Host $line
    Add-Content -LiteralPath $log -Value $line
}

$principal = New-Object Security.Principal.WindowsPrincipal(
    [Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host 'Este script precisa ser executado como Administrador.' -ForegroundColor Red
    Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File',"`"$PSCommandPath`""
    exit
}

Set-Content -LiteralPath $log -Value ("===== {0} =====" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
Say 'Executando como administrador. OK'

$regra = 'DDT - BLOQUEAR SQL Server 1433 (somente local)'
if (-not (Get-NetFirewallRule -DisplayName $regra -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $regra `
        -Description 'Bloqueia SQL Server (TCP 1433) de fora da maquina. O trafego em 127.0.0.1 nao passa pelo Firewall do Windows.' `
        -Direction Inbound -Action Block -Protocol TCP -LocalPort 1433 `
        -Profile Any -RemoteAddress Any -Enabled True | Out-Null
    Say "Regra de firewall criada: $regra"
} else {
    Say "Regra ja existia: $regra"
}

Start-Sleep -Seconds 3
$local = (Test-NetConnection 127.0.0.1 -Port 1433 -WarningAction SilentlyContinue).TcpTestSucceeded
$lanIp = (Get-NetIPAddress -AddressFamily IPv4 |
          Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
          Select-Object -First 1).IPAddress

Say ("127.0.0.1:1433  -> {0}  (esperado True : o jogo e o site continuam ok)" -f $local)
if ($lanIp) {
    $lan = (Test-NetConnection $lanIp -Port 1433 -WarningAction SilentlyContinue).TcpTestSucceeded
    Say ("{0}:1433  -> {1}  (esperado False : bloqueado na rede)" -f $lanIp, $lan)
}

Say '--- fim ---'
Write-Host ''
Write-Host "Log em: $log" -ForegroundColor Cyan
Write-Host 'Pressione Enter para fechar.' -ForegroundColor Yellow
[void](Read-Host)