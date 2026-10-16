# Testa a ficha da conta (conta.php) autenticado.
$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1/painel'
$senha = 'Pain3lT#Teste2026'

function Csrf($c) { if ($c -match 'name="_csrf" value="([a-f0-9]+)"') { return $Matches[1] } }

# Zera o rate-limit da conta de teste
sqlcmd -S . -E -d Db_Tank41 -Q "DELETE FROM Admin_Tentativas WHERE UserName='teste01';" | Out-Null

# Gera um token novo (o script assume controle do estado)
& powershell -NoProfile -ExecutionPolicy Bypass -File 'C:\DDTank4.1-main\Tools\ContaBoost\gera-token-painel.ps1' | Out-Null
$token = ''
if (Test-Path 'C:\DDTank4.1-main\secrets\painel_token.txt') {
    $bruto = Get-Content 'C:\DDTank4.1-main\secrets\painel_token.txt' -Raw -ErrorAction SilentlyContinue
    if ($null -ne $bruto) { $token = ([string]$bruto).Trim() }
}
if ($token -ne '') {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest "$base/primeiro_acesso.php" -WebSession $s -UseBasicParsing
    Invoke-WebRequest "$base/primeiro_acesso.php" -WebSession $s -UseBasicParsing -Method Post `
        -Body @{ _csrf=(Csrf $r.Content); token=$token; usuario='teste01'; senha=$senha; senha2=$senha; promover='1' } | Out-Null
}

$s2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$r  = Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing
$r  = Invoke-WebRequest "$base/entrar.php" -WebSession $s2 -UseBasicParsing -Method Post `
        -Body @{ _csrf=(Csrf $r.Content); usuario='teste01'; senha=$senha }
$r  = Invoke-WebRequest "$base/index.php" -WebSession $s2 -UseBasicParsing
if ($r.Content -notmatch 'teste01<') { Write-Host "LOGIN FALHOU" -ForegroundColor Red; exit 1 }
Write-Host "login OK" -ForegroundColor Green

$falhas = 0
foreach ($conta in @('usaimbot','teste01','25')) {
    foreach ($t in @('resumo','itens','mensagens','acoes','pets','partidas')) {
        try {
            $r = Invoke-WebRequest "$base/conta.php?q=$conta&t=$t" -WebSession $s2 -UseBasicParsing -TimeoutSec 25
            $erro = ''
            if ($r.Content.Length -lt 1600 -and $erro -eq '')    { $erro = 'pouco conteudo' }
            # Pagina legitimamente vazia (conta sem mensagens/pets): o aviso
            # de "nada encontrado" precisa estar la, senao e truncamento.
            if ($r.Content -match 'Fatal error|Parse error|Uncaught|SQLSTATE') { $erro = 'ERRO PHP' }
            if ($r.Content -match 'name="usuario"')                { $erro = 'CAIU NO LOGIN' }
            if ($erro -eq '') { Write-Host ("  [OK]   {0,-9} {1,-10} {2,6}b" -f $conta,$t,$r.RawContentLength) }
            else { Write-Host ("  [FALHA] {0,-9} {1,-10} {2}" -f $conta,$t,$erro) -ForegroundColor Red; $falhas++ }
        } catch {
            Write-Host ("  [FALHA] {0,-9} {1,-10} HTTP {2}" -f $conta,$t,$_.Exception.Response.StatusCode.value__) -ForegroundColor Red
            $falhas++
        }
    }
}

# conteudo especifico
$r = Invoke-WebRequest "$base/conta.php?q=usaimbot&t=resumo" -WebSession $s2 -UseBasicParsing
foreach ($t in @('GP \(experiencia\)','Cupons \(Money\)','Guilda','VIP')) {
    if ($r.Content -match $t) { Write-Host "  [OK]   resumo contem: $t" }
    else { Write-Host "  [FALHA] resumo sem: $t" -ForegroundColor Red; $falhas++ }
}
$r = Invoke-WebRequest "$base/conta.php?q=usaimbot&t=itens" -WebSession $s2 -UseBasicParsing
if ($r.Content -match 'Bast') { Write-Host "  [OK]   itens mostram o nome do item" }
else { Write-Host "  [FALHA] itens sem nome" -ForegroundColor Red; $falhas++ }
$r = Invoke-WebRequest "$base/conta.php?q=diego4458&t=mensagens" -WebSession $s2 -UseBasicParsing
if ($r.Content -match 'Leil') { Write-Host "  [OK]   mensagens do diego4458 aparecem" }
else { Write-Host "  [FALHA] mensagens vazias" -ForegroundColor Red; $falhas++ }
$r = Invoke-WebRequest "$base/conta.php?q=usaimbot&t=partidas" -WebSession $s2 -UseBasicParsing
if ($r.Content -match 'nao grava replay') { Write-Host "  [OK]   aviso de replay ausente presente" }
else { Write-Host "  [FALHA] sem aviso de replay" -ForegroundColor Red; $falhas++ }
$r = Invoke-WebRequest "$base/conta.php?q=naoexiste_zzz" -WebSession $s2 -UseBasicParsing
if ($r.Content -match 'Nenhuma conta chamada') { Write-Host "  [OK]   busca sem resultado avisada" }
else { Write-Host "  [FALHA] busca sem resultado" -ForegroundColor Red; $falhas++ }

Write-Host ''
if ($falhas -eq 0) { Write-Host 'FICHA DA CONTA: TUDO OK' -ForegroundColor Green }
else { Write-Host "$falhas FALHA(S)" -ForegroundColor Red }
exit $falhas