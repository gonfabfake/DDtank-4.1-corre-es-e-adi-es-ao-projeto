param(
  [ValidateSet('start','stop','status')] [string]$Action = 'start'
)
$ErrorActionPreference = 'Continue'

$bin    = 'C:\DDTank4.1-main\Source Server'
# Portal servido pelo IIS/FastCGI na porta 80.

$svcs = @(
  @{ Name='Center';   Dir="$bin\Center.Service\bin\Debug";   Exe='Center.Service.exe'   },
  @{ Name='Fighting'; Dir="$bin\Fighting.Service\bin\Debug"; Exe='Fighting.Service.exe' },
  @{ Name='Road';     Dir="$bin\Road.Service\bin\Debug";     Exe='Road.Service.exe'     }
)

function Is-Running([string]$procName) { [bool](Get-Process $procName -EA SilentlyContinue) }
function Is-Listening([int]$port) { [bool](Get-NetTCPConnection -State Listen -LocalPort $port -EA SilentlyContinue) }

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
  Write-Output ("  IIS  /            {0}" -f $(try { (Invoke-WebRequest 'http://127.0.0.1/' -UseBasicParsing -TimeoutSec 30).StatusCode } catch { 'indisponivel' }))
  Write-Output ("  IIS  /request/    {0}" -f $(try { (Invoke-WebRequest 'http://127.0.0.1/request/ServerList.ashx' -UseBasicParsing -TimeoutSec 30).StatusCode } catch { 'indisponivel' }))
}

if ($Action -eq 'status') { Show-Status; exit 0 }

if ($Action -eq 'stop') {
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
  $p = Start-Process -FilePath $exePath -ArgumentList '--start' -WorkingDirectory $s.Dir -WindowStyle Minimized -PassThru
  Write-Output "$($s.Name): iniciado pid=$($p.Id)"
  if ($s.Name -eq 'Center')   { Start-Sleep -Seconds 12 }
  if ($s.Name -eq 'Fighting') { Start-Sleep -Seconds 5 }
}
Start-Sleep -Seconds 12
Show-Status
