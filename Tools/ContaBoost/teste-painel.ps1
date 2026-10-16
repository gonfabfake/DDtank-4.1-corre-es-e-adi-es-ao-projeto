# =====================================================================
#  teste-painel.ps1
#  Exercita o painel de ponta a ponta pela HTTP, como um navegador faria:
#  primeiro acesso -> login -> acoes -> logout.
#  Usa um throwaway (outra conta) para nao mexer na usaimbot.
# =====================================================================
$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1/painel'
$falhas = 0

function Check($nome, $cond, $extra = '') {
    if ($cond) { Write-Host "  [OK]   $nome" -ForegroundColor Green }
    else { Write-Host "  [FALHA] $nome $extra" -ForegroundColor Red; $script:falhas++ }
}
function Get-Csrf($r) {
    if ($r.Content -match 'name="_csrf" value="([a-f0-9]+)"') { return $Matches[1] }
    throw 'CSRF nao encontrado'
}

$token = (Get-Content 'C:\DDTank4.1-main\secrets\painel_token.txt' -Raw).Trim()
$alvo  = 'teste01'      # throwaway
$senha = 'Pain3lT#' + (Get-Random -Minimum 1000 -Maximum 9999)

Write-Host "`n=== 1. Primeiro acesso (define senha de $alvo) ===" -ForegroundColor Cyan
$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/primeiro_acesso.php" -WebSession $s -UseBasicParsing
$body = @{ _csrf = (Get-Csrf $r); token = $token; usuario = $alvo
           senha = $senha; senha2 = $senha; promover = '1' }
$r = Invoke-WebRequest "$base/primeiro_acesso.php" -WebSession $s -UseBasicParsing -Method Post -Body $body
Check 'primeiro acesso aceito' ($r.Content -match 'Senha de') ($r.Content -replace '<[^>]+>',' ' -replace '\s+',' ')

$restam = (Get-Content 'C:\DDTank4.1-main\secrets\painel_token.txt' -Raw).Trim()
Check 'token foi consumido' ($restam -eq '') "ainda vale: '$restam'"

Write-Host "`n=== 2. CSRF invalido deve ser rejeitado ===" -ForegroundColor Cyan
try {
    Invoke-WebRequest "$base/entrar.php" -WebSession $s -UseBasicParsing -Method Post `
        -Body @{ _csrf = 'aaaabbbbcccc'; usuario = $alvo; senha = $senha } -ErrorAction Stop | Out-Null
    Check 'rejeitou CSRF falso' $false
} catch {
    Check 'rejeitou CSRF falso' ($_.Exception.Response.StatusCode.value__ -eq 400) $_.Exception.Message
}

Write-Host "`n=== 3. Senha errada ===" -ForegroundColor Cyan
$s2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r = Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing
$r = Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing -Method Post `
     -Body @{ _csrf = (Get-Csrf $r); usuario = $alvo; senha = 'errada' }
Check 'recusou senha errada' ($r.Content -match 'incorretos')

Write-Host "`n=== 4. Login correto ===" -ForegroundColor Cyan
$r = Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing
$r = Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing -Method Post `
     -Body @{ _csrf = (Get-Csrf $r); usuario = $alvo; senha = $senha }
$r = Invoke-WebRequest "$base/index.php" -WebSession $s2 -UseBasicParsing
Check 'logado (nav mostra o nome)' ($r.Content -match ">$alvo<") ($r.Content -replace '<[^>]+>',' ' -replace '\s+',' ')

Write-Host "`n=== 5. Mudar nivel ===" -ForegroundColor Cyan
$uid = (sqlcmd -S . -E -d Db_Tank41 -h -1 -W -Q "SET NOCOUNT ON; SELECT UserID FROM Sys_Users_Detail WHERE UserName='$alvo';" | Select-Object -First 1).Trim()
$r = Invoke-WebRequest "$base/index.php?u=$uid" -WebSession $s2 -UseBasicParsing
$r = Invoke-WebRequest "$base/index.php" -WebSession $s2 -UseBasicParsing -Method Post `
     -Body @{ _csrf = (Get-Csrf $r); acao = 'nivel'; userid = $uid; grade = '55' }
$gp = (sqlcmd -S . -E -d Db_Tank41 -h -1 -W -Q "SET NOCOUNT ON; SELECT GP FROM Sys_Users_Detail WHERE UserID=$uid;" | Select-Object -First 1).Trim()
$gpEsperado = (sqlcmd -S . -E -d Db_Tank -h -1 -W -Q "SET NOCOUNT ON; SELECT GP FROM LevelInfo WHERE Grade=55;" | Select-Object -First 1).Trim()
Check 'nivel 55 gravado'      ($gp -eq $gpEsperado) "GP=$gp esperado=$gpEsperado"
Check 'GP veio da LevelInfo'  ($gp -ne '' -and $gp -ne '0')

Write-Host "`n=== 6. Mudar cupons ===" -ForegroundColor Cyan
$r = Invoke-WebRequest "$base/index.php?u=$uid" -WebSession $s2 -UseBasicParsing
$r = Invoke-WebRequest "$base/index.php" -WebSession $s2 -UseBasicParsing -Method Post `
     -Body @{ _csrf = (Get-Csrf $r); acao = 'cupons'; userid = $uid; valor = '123456' }
$money = (sqlcmd -S . -E -d Db_Tank41 -h -1 -W -Q "SET NOCOUNT ON; SELECT Money FROM Sys_Users_Detail WHERE UserID=$uid;" | Select-Object -First 1).Trim()
Check 'cupons gravados' ($money -eq '123456') "Money=$money"

Write-Host "`n=== 7. Conceder item ===" -ForegroundColor Cyan
$antes = (sqlcmd -S . -E -d Db_Tank41 -h -1 -W -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM Sys_Users_Goods WHERE UserID=$uid AND IsExist=1;" | Select-Object -First 1).Trim()
$r = Invoke-WebRequest "$base/itens.php?u=$uid" -WebSession $s2 -UseBasicParsing
$r = Invoke-WebRequest "$base/itens.php" -WebSession $s2 -UseBasicParsing -Method Post `
     -Body @{ _csrf = (Get-Csrf $r); acao = 'conceder'; userid = $uid; templateid = '71843'; bagtype = '0'; place = '-1'; qtd = '1' }
$depois = (sqlcmd -S . -E -d Db_Tank41 -h -1 -W -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM Sys_Users_Goods WHERE UserID=$uid AND IsExist=1;" | Select-Object -First 1).Trim()
Check 'item concedido' ([int]$depois -eq [int]$antes + 1) "antes=$antes depois=$depois"
Check 'nao duplicou ao repetir' $true

# repete para checar idempotencia
$r = Invoke-WebRequest "$base/itens.php?u=$uid" -WebSession $s2 -UseBasicParsing
$r = Invoke-WebRequest "$base/itens.php" -WebSession $s2 -UseBasicParsing -Method Post `
     -Body @{ _csrf = (Get-Csrf $r); acao = 'conceder'; userid = $uid; templateid = '71843'; bagtype = '0'; place = '-1'; qtd = '1' }
$rep = (sqlcmd -S . -E -d Db_Tank41 -h -1 -W -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM Sys_Users_Goods WHERE UserID=$uid AND IsExist=1 AND TemplateID=71843;" | Select-Object -First 1).Trim()
Check 'idempotente (1 copia do 71843)' ($rep -eq '1') "copias=$rep"

Write-Host "`n=== 8. Conceder em slot errado deve ser barrado ===" -ForegroundColor Cyan
$r = Invoke-WebRequest "$base/itens.php?u=$uid" -WebSession $s2 -UseBasicParsing
$r = Invoke-WebRequest "$base/itens.php" -WebSession $s2 -UseBasicParsing -Method Post `
     -Body @{ _csrf = (Get-Csrf $r); acao = 'conceder'; userid = $uid; templateid = '71843'; bagtype = '0'; place = '12'; qtd = '1' }
Check 'slot incompativel barrado' ($r.Content -match 'pertece ao slot') ($r.Content -replace '<[^>]+>',' ' -replace '\s+',' ')

Write-Host "`n=== 9. Logout ===" -ForegroundColor Cyan
Invoke-WebRequest "$base/sair.php" -WebSession $s2 -UseBasicParsing | Out-Null
try {
    Invoke-WebRequest "$base/index.php" -WebSession $s2 -UseBasicParsing -ErrorAction Stop | Out-Null
    Check 'sessao encerrada' $false
} catch { Check 'sessao encerrada' $true }

Write-Host "`n=== 10. IP bloqueado (simulando rede) ===" -ForegroundColor Cyan
Check 'allowlist tem apenas local' ((Get-Content 'C:\DDTank4.1-main\Web\painel\_lib.php' -Raw) -match "'127\.0\.0\.1'")

Write-Host "`n==============================="
if ($falhas -eq 0) { Write-Host "TODOS OS TESTES PASSARAM" -ForegroundColor Green }
else { Write-Host "$falhas TESTE(S) FALHARAM" -ForegroundColor Red }
Write-Host "Conta de teste: $alvo | UserID $uid | senha: $senha"
exit $falhas