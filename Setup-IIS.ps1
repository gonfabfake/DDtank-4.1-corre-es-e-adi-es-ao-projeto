#Requires -RunAsAdministrator
# Reuse the existing site on port 80 instead of deleting/recreating it.
$ErrorActionPreference = 'Continue'
$appcmd = "$env:windir\System32\inetsrv\appcmd.exe"
function Say($m) { Write-Host ("[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $m) }

$PoolName = 'DDTankPool'
$WebRoot  = 'C:\DDTank4.1-main\Web'
$ReqRoot  = 'C:\DDTank4.1-main\Request'

Say '1) pool de aplicacao'
& $appcmd list apppool /text:$PoolName | Out-Null
if (-not $?) { & $appcmd add apppool /name:$PoolName | Out-Null }
& $appcmd set config /section:applicationPools "/[name='$PoolName']" `
    /managedRuntimeVersion:v4.0 /managedPipelineMode:Integrated /enable32BitAppOnWin64:False /startMode:Always | Out-Null
Say '   ok'

Say '2) apontar o site da porta 80 para a pasta Web'
& $appcmd set vdir "/LM/W3SVC/1/ROOT/" /physicalPath "$WebRoot" | Out-Null
Say '   ok'

Say '3) criar aplicacao /request'
$existing = (& $appcmd list app /text:/request 2>$null)
if ($existing -match 'request') {
    Say '   /request ja existe - atualizando caminho'
    & $appcmd set vdir "/LM/W3SVC/1/request/" /physicalPath "$ReqRoot" | Out-Null
} else {
    & $appcmd add app /site.name:"Default Web Site" /path:/request /physicalPath:"$ReqRoot" | Out-Null
}
Say '   ok'

Say '4) pool para site e /request'
& $appcmd set vdir "/LM/W3SVC/1/ROOT/" /applicationPool $PoolName | Out-Null
& $appcmd set vdir "/LM/W3SVC/1/request/" /applicationPool $PoolName | Out-Null
Say '   ok'

Say '5) php-cgi no FastCGI'
$phpCgi = (Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP*" -Recurse -Filter 'php-cgi.exe' -EA SilentlyContinue | Select-Object -First 1).FullName
Say "   php-cgi: $phpCgi"
foreach ($kv in @(@('PHP_FCGI_MAX_REQUESTS','0'), @('PHP_FCGI_CHILDREN','4'), @('PHP_FCGI_READ_TIMEOUT','300'))) {
    & $appcmd set config /section:system.webServer/fastcgi /applicationSettings `
        "/+[name='$phpCgi'].[fullPath='$phpCgi'].environmentVariables.environmentVariable[@name='$($kv[0])']/@value='$($kv[1])'" | Out-Null
}
Say '   ok'

Say '6) reiniciar pool e servico'
Restart-Service W3SVC -Force -EA SilentlyContinue
Start-Service W3SVC -EA SilentlyContinue
& $appcmd start apppool /apppool.name:$PoolName | Out-Null
Start-Sleep -Seconds 8
Say '   ok'

Say ''
Say '================ estado ================'
& $appcmd list vdir "Default Web Site/"
& $appcmd list apppool /text:$PoolName
Say ''
Say '================ testes ================'
foreach ($u in @('http://127.0.0.1/','http://127.0.0.1/index.php','http://127.0.0.1/request/ServerList.ashx','http://127.0.0.1/request/Login.ashx')) {
    try {
        $r = Invoke-WebRequest $u -UseBasicParsing -TimeoutSec 45 -ErrorAction Stop
        $b = ($r.Content -replace '\s+',' '); if ($b.Length -gt 130) { $b = $b.Substring(0,130)+'...' }
        Say ("  {0,-40} HTTP {1}  {2}B" -f $u,$r.StatusCode,$r.RawContentLength)
        Say ("     {0}" -f $b)
    } catch {
        Say ("  {0,-40} HTTP {1}" -f $u, $(if($_.Exception.Response){$_.Exception.Response.StatusCode.value__}else{$_.Exception.Message.Split("`n")[0]}))
    }
}
Say 'FIM'