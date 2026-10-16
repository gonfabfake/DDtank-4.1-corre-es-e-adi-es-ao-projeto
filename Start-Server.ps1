param(
  [ValidateSet('start','stop','status')] [string]$Action = 'start'
)
$ErrorActionPreference = 'Continue'

$bin    = 'C:\DDTank4.1-main\Source Server'
$proxyDir = Join-Path $PSScriptRoot 'Tools\ruffle-proxy'
$proxyScript = Join-Path $proxyDir 'proxy.cjs'
$proxyNode = 'C:\Program Files\nodejs\node.exe'
# Portal servido pelo IIS/FastCGI na porta 80.

$svcs = @(
  @{ Name='Center';   Dir="$bin\Center.Service\bin\Debug";   Exe='Center.Service.exe'   },
  @{ Name='Fighting'; Dir="$bin\Fighting.Service\bin\Debug"; Exe='Fighting.Service.exe' },
  @{ Name='Road';     Dir="$bin\Road.Service\bin\Debug";     Exe='Road.Service.exe'     }
)

function Is-Running([string]$procName) { [bool](Get-Process $procName -EA SilentlyContinue) }
function Is-Listening([int]$port) { [bool](Get-NetTCPConnection -State Listen -LocalPort $port -EA SilentlyContinue) }

function Get-RuffleProxy {
  # Match the executable and complete script argument, never all node processes.
  Get-CimInstance Win32_Process -Filter "Name = 'node.exe'" -ErrorAction SilentlyContinue | Where-Object {
    $_.ExecutablePath -eq $proxyNode -and
    $_.CommandLine -match ('^\s*(?:"' + [regex]::Escape($proxyNode) + '"|' + [regex]::Escape($proxyNode) + ')\s+"' + [regex]::Escape($proxyScript) + '"\s*$')
  }
}

function Show-Status {
  Write-Output '=== servidores de jogo ==='
  foreach ($s in $svcs) {
    $n = [IO.Path]::GetFileNameWithoutExtension($s.Exe)
    Write-Output ("  {0,-9} {1}" -f $s.Name, $(if (Is-Running $n) { 'RODANDO' } else { 'parado' }))
  }
  Write-Output '=== portas do jogo ==='
  foreach ($pt in 9200,9202,9208,2008,2009,1109) {
    Write-Output ("  {0,-6} {1}" -f $pt, $(if (Is-Listening $pt) { 'OUVINDO' } else { '-' }))
  }
  Write-Output '=== camada web ==='
  Write-Output ("  Ruffle 8765/game  {0}" -f $(if (Get-RuffleProxy) { 'RODANDO' } else { 'parado' }))
  Write-Output ("  IIS  /            {0}" -f $(try { (Invoke-WebRequest 'http://127.0.0.1/' -UseBasicParsing -TimeoutSec 30).StatusCode } catch { 'indisponivel' }))
  Write-Output ("  IIS  /request/    {0}" -f $(try { (Invoke-WebRequest 'http://127.0.0.1/request/ServerList.ashx' -UseBasicParsing -TimeoutSec 30).StatusCode } catch { 'indisponivel' }))
}

if ($Action -eq 'status') { Show-Status; exit 0 }

if ($Action -eq 'stop') {
  Get-RuffleProxy | ForEach-Object {
    Write-Output "parando Ruffle proxy ($($_.ProcessId))"
    Stop-Process -Id $_.ProcessId -Force
  }
  foreach ($s in $svcs) {
    Get-Process ([IO.Path]::GetFileNameWithoutExtension($s.Exe)) -EA SilentlyContinue | ForEach-Object {
      Write-Output "parando $($_.ProcessName) ($($_.Id))"; Stop-Process -Id $_.Id -Force
    }
  }
  Show-Status; exit 0
}

# O IIS gerencia php-cgi; nao iniciar servidor PHP separado.

# ------------------------------------------------------------------ servidores
# Center.Service MUST be up before Road.Service: the game server dials the login
# server on 127.0.0.1:9202 while initialising and aborts if nothing answers.
foreach ($s in $svcs) {
  $exePath = Join-Path $s.Dir $s.Exe
  if (-not (Test-Path $exePath)) { Write-Output "AUSENTE: $exePath"; continue }
  $proc = [IO.Path]::GetFileNameWithoutExtension($s.Exe)
  if (Is-Running $proc) { Write-Output "$($s.Name): ja rodando"; continue }
  foreach ($sub in 'logs','RecordLog','Languages') {
    $d = Join-Path $s.Dir $sub
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Path $d -Force | Out-Null }
  }
  $p = Start-Process -FilePath $exePath -ArgumentList '--start' -WorkingDirectory $s.Dir -WindowStyle Hidden -PassThru
  Write-Output "$($s.Name): iniciado pid=$($p.Id)"
  if ($s.Name -eq 'Center')   { Start-Sleep -Seconds 12 }
  if ($s.Name -eq 'Fighting') { Start-Sleep -Seconds 5 }
}
Start-Sleep -Seconds 12
if (Get-RuffleProxy) {
  Write-Output 'Ruffle proxy: ja rodando'
} elseif (Is-Listening 8765) {
  Write-Error 'Porta 8765 ocupada por outro processo; Ruffle proxy nao iniciado.'
} elseif (-not (Test-Path $proxyNode) -or -not (Test-Path (Join-Path $proxyDir 'node_modules\ws'))) {
  Write-Error 'Ruffle proxy: instale Node.js e execute npm install em Tools\ruffle-proxy.'
} else {
  $p = Start-Process -FilePath $proxyNode -ArgumentList ('"' + $proxyScript + '"') -WorkingDirectory $proxyDir -WindowStyle Minimized -PassThru
  Start-Sleep -Seconds 1
  if (Get-RuffleProxy) { Write-Output "Ruffle proxy: iniciado pid=$($p.Id)" }
  else { Write-Error 'Ruffle proxy encerrou durante a inicializacao.' }
}
Show-Status
