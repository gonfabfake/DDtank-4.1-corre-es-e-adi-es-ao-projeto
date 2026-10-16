<#
    DDTank 4.1 — acesso mobile na LAN (celular no Wi-Fi, PC como servidor)
    ==========================================================================
    Prepara o PC para servir o portal/jogo para o CELULAR sem expor nada
    fora da rede local:
      1) binding do IIS na porta 80 para o IP da placa Wi-Fi;
      2) regras de firewall liberando TCP 80 e 8765 somente para LocalSubnet.
    Idempotente: pode ser executado quantas vezes quiser.
    Requer administrador (o ele é chamado automaticamente pelo Setup-IIS se preciso).
#>
param(
  [string]$Ip = ''
)
$ErrorActionPreference = 'Stop'
function Info($m) { Write-Output $m }

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { throw 'Execute este script como administrador.' }

# --- IP da rede local -------------------------------------------------------
if (-not $Ip) {
  $cand = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
    Sort-Object -Property @{Expression = { if ($_.InterfaceAlias -like 'Wi-Fi*') { 0 } else { 1 } }}, InterfaceIndex |
    Select-Object -First 1
  if (-not $cand) { throw 'Nenhum IP de rede local encontrado. Use -Ip <endereco>.' }
  $Ip = $cand.IPAddress
}
Info "IP local: $Ip"

# --- 1) Binding do IIS ------------------------------------------------------
# O IP do Wi-Fi muda com frequencia (DHCP). Para nao depender dele, o site
# responde em QUALQUER IP local (binding curinga *:80:). O firewall continua
# limitando o acesso a LocalSubnet.
$siteName = 'Default Web Site'
try {
  Add-Type -AssemblyName 'Microsoft.Web.Administration' -ErrorAction Stop
} catch {
  Add-Type -Path (Join-Path $env:windir 'system32\inetsrv\Microsoft.Web.Administration.dll')
}
$sm = New-Object Microsoft.Web.Administration.ServerManager
$site = $sm.Sites[$siteName]
if (-not $site) { throw "Site do IIS '$siteName' nao encontrado." }

try {
  $wildcard = '*:80:'
  $hasWildcard = $site.Bindings | Where-Object { $_.BindingInformation -eq $wildcard }
  if ($hasWildcard) {
    Info 'IIS: binding curinga ja existe (*:80:)'
  } else {
    # remove o binding host-header 'localhost' (pode conflitar com o curinga)
    $lh = $site.Bindings | Where-Object { $_.BindingInformation -eq '*:80:localhost' }
    if ($lh) { $site.Bindings.Remove($lh) | Out-Null }
    $site.Bindings.Add($wildcard, 'http') | Out-Null
    $sm.CommitChanges()
    Info 'IIS: binding curinga adicionado (*:80: responde em qualquer IP local)'
  }
} catch {
  Info ('IIS: curinga falhou (' + $_.Exception.Message + '); usando binding do IP atual ' + $Ip)
  if (-not ($site.Bindings | Where-Object { $_.BindingInformation -eq "${Ip}:80:" })) {
    $site.Bindings.Add("${Ip}:80:", 'http') | Out-Null
    $sm.CommitChanges()
    Info "IIS: binding adicionado (${Ip}:80:)"
  }
}

# limpeza: remove bindings de IPs que nao existem mais no PC (DHCP antigo)
$aliveIps = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | ForEach-Object { $_.IPAddress })
foreach ($b in @($site.Bindings | Where-Object { $_.BindingInformation -match '^([0-9.]+):80:$' })) {
  $bip = [regex]::Match($b.BindingInformation, '^([0-9.]+):80:$').Groups[1].Value
  $binfo = $b.BindingInformation
  if ($bip -ne '127.0.0.1' -and ($aliveIps -notcontains $bip)) {
    # referencia fresca: apos CommitChanges() o objeto anterior fica readonly
    $sm2 = New-Object Microsoft.Web.Administration.ServerManager
    $site2 = $sm2.Sites[$siteName]
    $b2 = $site2.Bindings | Where-Object { $_.BindingInformation -eq $binfo }
    if ($b2) { $site2.Bindings.Remove($b2) | Out-Null; $sm2.CommitChanges() }
    Info ("IIS: binding de IP morto removido (" + $bip + ":80:)")
  }
}
$sm = New-Object Microsoft.Web.Administration.ServerManager
$site = $sm.Sites[$siteName]
$allB = ($site.Bindings | ForEach-Object { $_.BindingInformation }) -join ' | '
Info ("IIS: bindings atuais -> " + $allB)

# --- 2) Firewall (somente sub-rede local) ----------------------------------
foreach ($port in 80, 8765) {
  $name = "DDTank LAN TCP $port"
  # Recria sempre: garante Enabled/Allow/Profile Any/LocalSubnet em qualquer
  # versao do modulo NetSecurity (o setter do filtro de endereco varia).
  Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue | Remove-NetFirewallRule -ErrorAction SilentlyContinue
  New-NetFirewallRule -DisplayName $name -Direction Inbound -Action Allow -Protocol TCP -LocalPort $port -RemoteAddress LocalSubnet -Profile Any | Out-Null
  Info "Firewall: regra garantida ($name)"
}
$profiles = Get-NetConnectionProfile -ErrorAction SilentlyContinue | Select-Object InterfaceAlias, NetworkCategory
foreach ($p in $profiles) { Info ("Perfil de rede: {0} = {1}" -f $p.InterfaceAlias, $p.NetworkCategory) }

Info ''
Info '=== pronto ==='
Info "Portal (celular):  http://$Ip/"
Info "Jogo (celular):    http://$Ip/play.php"
Info "WebSocket do jogo: ws://${Ip}:8765/game"
Info 'Mantenha o PC ligado com o servidor em execucao (Start-Server.ps1).'
