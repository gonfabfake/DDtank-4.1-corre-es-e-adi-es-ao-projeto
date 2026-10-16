# =====================================================================
#  gera-token-painel.ps1
#  Gera (ou apaga) o token de uso unico do primeiro acesso do painel.
#
#    .\gera-token-painel.ps1            -> gera um token novo
#    .\gera-token-painel.ps1 -Apagar    -> invalida o token
#
#  O token fica FORA do docroot:
#    C:\DDTank4.1-main\secrets\painel_token.txt
# =====================================================================
param([switch]$Apagar)

$arquivo = 'C:\DDTank4.1-main\secrets\painel_token.txt'
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $arquivo) | Out-Null

if ($Apagar) {
    Set-Content -LiteralPath $arquivo -Value '' -NoNewline
    Write-Host 'Token invalido.' -ForegroundColor Yellow
    exit
}

$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
$rng   = [System.Security.Cryptography.RandomNumberGenerator]::Create()
# 4 grupos de 5 caracteres -> 20 caracteres, sem ambiguidade visual
$grupos = 1..4 | ForEach-Object {
    -join (1..5 | ForEach-Object { $b = New-Object byte[] 1; $rng.GetBytes($b); $chars[$b[0] % $chars.Length] })
}
$token = $grupos -join '-'

# Grava sem BOM (PHP ecoaria o BOM e o FastCGI cairia com 500).
$enc = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($arquivo, $token, $enc)

Write-Host ''
Write-Host '  TOKEN DO PRIMEIRO ACESSO' -ForegroundColor Green
Write-Host "  -----------------------"
Write-Host "  $token"
Write-Host ''
Write-Host "  Use em http://127.0.0.1/painel/primeiro_acesso.php" -ForegroundColor Cyan
Write-Host "  Arquivo: $arquivo" -ForegroundColor DarkGray
Write-Host '  O token se invalida depois do primeiro uso.' -ForegroundColor DarkGray