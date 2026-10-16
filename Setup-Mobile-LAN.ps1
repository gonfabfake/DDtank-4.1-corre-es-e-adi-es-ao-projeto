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
$siteName = 'Default Web Site'
$bindingInfo = "${Ip}:80:"
try {
  Add-Type -AssemblyName 'Microsoft.Web.Administration' -ErrorAction Stop
} catch {
  Add-Type -Path (Join-Path $env:windir 'system32\inetsrv\Microsoft.Web.Administration.dll')
}
$sm = New-Object Microsoft.Web.Administration.ServerManager
$site = $sm.Sites[$siteName]
if (-not $site) { throw "Site do IIS '$siteName' nao encontrado." }
$exists = $site.Bindings | Where-Object { $_.BindingInformation -eq $bindingInfo }
if ($exists) {
  Info "IIS: binding ja existe ($bindingInfo)"
} else {
  $site.Bindings.Add($bindingInfo, 'http') | Out-Null
  $sm.CommitChanges()
  Info "IIS: binding adicionado ($bindingInfo)"
}
$allB = ($site.Bindings | ForEach-Object { $_.BindingInformation }) -join ' | '
Info ("IIS: bindings atuais -> " + $allB)

# --- 2) Firewall (somente sub-rede local) ----------------------------------
foreach ($port in 80, 8765) {
  $name = "DDTank LAN TCP $port"
  $rule = Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue
  if ($rule) {
    Set-NetFirewallRule -DisplayName $name -Enabled True -Profile Any -Action Allow | Out-Null
    Set-NetFirewallAddressFilter -AssociatedNetFirewallRule $rule -RemoteAddress LocalSubnet | Out-Null
    Info "Firewall: regra atualizada ($name)"
  } else {
    New-NetFirewallRule -DisplayName $name -Direction Inbound -Action Allow -Protocol TCP -LocalPort $port -RemoteAddress LocalSubnet -Profile Any | Out-Null
    Info "Firewall: regra criada ($name)"
  }
}
$profiles = Get-NetConnectionProfile -ErrorAction SilentlyContinue | Select-Object InterfaceAlias, NetworkCategory
foreach ($p in $profiles) { Info ("Perfil de rede: {0} = {1}" -f $p.InterfaceAlias, $p.NetworkCategory) }

Info ''
Info '=== pronto ==='
Info "Portal (celular):  http://$Ip/"
Info "Jogo (celular):    http://$Ip/play.php"
Info "WebSocket do jogo: ws://${Ip}:8765/game"
Info 'Mantenha o PC ligado com o servidor em execucao (Start-Server.ps1).'
