# Testa a API JSON do painel autenticado.
$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1/painel'
$senha = 'Pain3lT#Teste2026'
function Csrf($c) { if ($c -match 'name="_csrf" value="([a-f0-9]+)"') { return $Matches[1] } }

sqlcmd -S . -E -d Db_Tank41 -Q "DELETE FROM Admin_Tentativas WHERE UserName='teste01';" | Out-Null
$tok = ''
& powershell -NoProfile -ExecutionPolicy Bypass -File 'C:\DDTank4.1-main\Tools\ContaBoost\gera-token-painel.ps1' | Out-Null
if (Test-Path 'C:\DDTank4.1-main\secrets\painel_token.txt') {
  $b = Get-Content 'C:\DDTank4.1-main\secrets\painel_token.txt' -Raw -ErrorAction SilentlyContinue
  if ($null -ne $b) { $tok = ([string]$b).Trim() }
}
$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/primeiro_acesso.php" -WebSession $s -UseBasicParsing
Invoke-WebRequest "$base/primeiro_acesso.php" -WebSession $s -UseBasicParsing -Method Post `
    -Body @{ _csrf=(Csrf $r.Content); token=$tok; usuario='teste01'; senha=$senha; senha2=$senha; promover='1' } | Out-Null

$s2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r  = Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing
Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing -Method Post `
    -Body @{ _csrf=(Csrf $r.Content); usuario='teste01'; senha=$senha } | Out-Null

$falhas = 0
function Chk($n, $c, $x = '') {
  if ($c) { Write-Host "  [OK]   $n" -ForegroundColor Green }
  else { Write-Host "  [FALHA] $n $x" -ForegroundColor Red; $script:falhas++ }
}

Write-Host "`n=== shell React ===" -ForegroundColor Cyan
$r = Invoke-WebRequest "$base/app.php" -WebSession $s2 -UseBasicParsing
Chk 'app.php carrega' ($r.StatusCode -eq 200)
Chk 'injeta window.BOOT' ($r.Content -match 'window\.BOOT')
Chk 'carrega react'       ($r.Content -match 'react\.production\.min\.js')
Chk 'carrega react-dom'   ($r.Content -match 'react-dom\.production\.min\.js')
Chk 'carrega htm'         ($r.Content -match 'vendor/htm\.js')
Chk 'carrega app.js'      ($r.Content -match 'assets/app\.js')
Chk 'carrega app.css'     ($r.Content -match 'assets/app\.css')

Write-Host "`n=== vendor local (sem CDN) ===" -ForegroundColor Cyan
foreach ($v in @('react.production.min.js','react-dom.production.min.js','htm.js')) {
  try { $r = Invoke-WebRequest "$base/assets/vendor/$v" -UseBasicParsing
        Chk $v ($r.StatusCode -eq 200 -and $r.RawContentLength -gt 500) "$($r.RawContentLength)b" }
  catch { Chk $v $false $_.Exception.Message }
}

Write-Host "`n=== endpoints GET ===" -ForegroundColor Cyan
foreach ($e in @('bootstrap','contas&q=','conta&q=usaimbot','conta&q=25','conta&q=naoexiste_zz',
                 'catalogo&q=Bast','catalogo&q=','catalogo&q=71843','auditoria',
                 'imgconfig','imgteste&base=&tpl=arm/{id}/00.png&id=71843')) {
  $nome = ($e -split '&')[0]
  try {
    $r = Invoke-WebRequest "$base/api.php?a=$e" -WebSession $s2 -UseBasicParsing -TimeoutSec 30
    $d = $r.Content | ConvertFrom-Json
    if ($e -like '*naoexiste*') { Chk $e ($d.ok -eq $false) 'deveria dar 404' }
    else { Chk $e ($d.ok -eq $true) "$($r.RawContentLength)b" }
  } catch {
    $sc = $_.Exception.Response.StatusCode.value__
    if ($e -like '*naoexiste*') { Chk $e ($sc -eq 404) "HTTP $sc" }
    else { Chk $e $false "HTTP $sc" }
  }
}

Write-Host "`n=== conteudo dos endpoints ===" -ForegroundColor Cyan
$d = (Invoke-WebRequest "$base/api.php?a=conta&q=usaimbot" -WebSession $s2 -UseBasicParsing).Content | ConvertFrom-Json
Chk 'detalhe da conta'      ($d.conta.detalhe.NickName -eq 'usaimbot')
Chk '17 itens'              ($d.conta.itens.Count -eq 17) "achou $($d.conta.itens.Count)"
Chk 'item tem atributos'    ($d.conta.itens[0].atq -ne $null -or $d.conta.itens[0].def -ne $null)
Chk 'item tem categoria'    ($d.conta.itens[0].cat -gt 0)
Chk 'item tem qualidade'    ($d.conta.itens[0].q -gt 0)
Chk 'item tem resource Pic' ($null -ne $d.conta.itens[0].pic)
Chk 'armas nao viram chapeu' (($d.conta.itens | Where-Object { $_.template -eq 71843 }).slot -eq 6)

$d = (Invoke-WebRequest "$base/api.php?a=catalogo&q=Bast" -WebSession $s2 -UseBasicParsing).Content | ConvertFrom-Json
Chk 'catalogo traz itens'   ($d.itens.Count -gt 0) "$($d.itens.Count) itens"
Chk 'catalogo sem slot'     ($null -eq $d.itens[0].slot) "slot=$($d.itens[0].slot)"
Chk 'catalogo com stats'    ($d.itens[0].atq -ne $null)

$d = (Invoke-WebRequest "$base/api.php?a=contas&q=" -WebSession $s2 -UseBasicParsing).Content | ConvertFrom-Json
Chk 'lista de contas'       ($d.contas.Count -gt 0) "$($d.contas.Count) contas"
Chk 'contas com contagem'   ($null -ne $d.contas[0].Itens)

Write-Host "`n=== resource de imagens ===" -ForegroundColor Cyan
$d = (Invoke-WebRequest "$base/api.php?a=imgconfig" -WebSession $s2 -UseBasicParsing).Content | ConvertFrom-Json
Chk 'imgconfig responde'    ($d.ok -eq $true)
Chk 'modelos por categoria' ($d.modelos.'7' -eq 'arm/{id}/00.png') "cat7=$($d.modelos.'7')"
Chk 'cat 5 = cloth'         ($d.modelos.'5' -match 'cloth')
Chk 'cat 14 = necklace'     ($d.modelos.'14' -match 'necklace')

$d = (Invoke-WebRequest "$base/api.php?a=imgteste&base=&tpl=arm/{id}/00.png&id=71843" -WebSession $s2 -UseBasicParsing).Content | ConvertFrom-Json
Chk 'imgteste sem base'     ($d.ok -eq $true -and $d.achou -eq $false) "motivo=$($d.motivo)"

$d = (Invoke-WebRequest "$base/api.php?a=imgteste&base=https://exemplo.invalido/x/&tpl=arm/{id}/00.png&id=1" -WebSession $s2 -UseBasicParsing -TimeoutSec 40).Content | ConvertFrom-Json
Chk 'imgteste host invalido' ($d.achou -eq $false) "motivo=$($d.motivo)"

$d = (Invoke-WebRequest "$base/api.php?a=bootstrap" -WebSession $s2 -UseBasicParsing).Content | ConvertFrom-Json
Chk 'bootstrap traz img'    ($null -ne $d.img)

Write-Host "`n=== acoes POST (CSRF exigido) ===" -ForegroundColor Cyan
$uid = 25
# o CSRF vem do BOOT injetado pelo app.php
$appHtml = (Invoke-WebRequest "$base/app.php" -WebSession $s2 -UseBasicParsing).Content
if ($appHtml -match '"csrf":"([a-f0-9]+)"') { $csrf = $Matches[1] } else { $csrf = '' }
Chk 'CSRF disponivel no BOOT' ($csrf -ne '')
function Post($fields) {
  return Invoke-WebRequest "$base/api.php?a=acao" -WebSession $s2 -UseBasicParsing -Method Post -Body $fields
}
# CSRF errado -> 400
try { Post @{ _csrf='falso'; acao='nivel'; userid=$uid; grade=60 } | Out-Null; Chk 'rejeita CSRF falso' $false }
catch { Chk 'rejeita CSRF falso' ($_.Exception.Response.StatusCode.value__ -eq 400) $_.Exception.Response.StatusCode.value__ }

# nivel invalido -> 400
$body = "_csrf=$csrf&acao=nivel&userid=$uid&grade=999"
try { Invoke-WebRequest "$base/api.php?a=acao" -WebSession $s2 -UseBasicParsing -Method Post -Body $body | Out-Null; Chk 'rejeita nivel invalido' $false }
catch { Chk 'rejeita nivel invalido' ($_.Exception.Response.StatusCode.value__ -eq 400) $_.Exception.Response.StatusCode.value__ }

# slot incompativel -> 400
$body = "_csrf=$csrf&acao=conceder&userid=$uid&templateid=71843&bagtype=0&place=12&qtd=1"
try { Invoke-WebRequest "$base/api.php?a=acao" -WebSession $s2 -UseBasicParsing -Method Post -Body $body | Out-Null; Chk 'rejeita slot incompativel' $false }
catch { Chk 'rejeita slot incompativel' ($_.Exception.Response.StatusCode.value__ -eq 400) $_.Exception.Response.StatusCode.value__ }

Write-Host ''
if ($falhas -eq 0) { Write-Host 'API + SPA: TUDO OK' -ForegroundColor Green }
else { Write-Host "$falhas FALHA(S)" -ForegroundColor Red }
exit $falhas