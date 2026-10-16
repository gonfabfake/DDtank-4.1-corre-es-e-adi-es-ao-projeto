<#
    boost.ps1 - Orquestra backup -> alteracao -> verificacao.

    Uso:
        .\boost.ps1                     # executa tudo
        .\boost.ps1 -SomenteVerificar    # so a verificacao
        .\boost.ps1 -Alvo 70 -Cupons 1000000 -SkipBackup
#>
[CmdletBinding()]
param(
    [int]    $Alvo        = 60,
    [int]    $Cupons      = 2000000000,
    [string] $UserName    = 'usaimbot',
    [switch] $SomenteVerificar,
    [switch] $SkipBackup
)

$ErrorActionPreference = 'Stop'
$dir    = Split-Path -Parent $MyInvocation.MyCommand.Path
$server = '.'

function Invoke-Sql {
    param([string]$File, [string[]]$Var)
    $tmp = [System.IO.Path]::GetTempFileName()
    try {
        $sql = Get-Content -Raw -LiteralPath $File
        foreach ($v in $Var) { $sql = [regex]::Replace($sql, $v.Name, $v.Value) }
        Set-Content -LiteralPath $tmp -Value $sql -Encoding UTF8 -NoNewline
        $out = & sqlcmd -S $server -E -b -i $tmp 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "sqlcmd falhou em $(Split-Path -Leaf $File):`n$($out -join "`n")"
        }
        $out
    } finally {
        Remove-Item -LiteralPath $tmp -ErrorAction SilentlyContinue
    }
}

Write-Host "`n=== DDTank 4.1 - boost de conta ===" -ForegroundColor Cyan
Write-Host "Servidor : $server"
Write-Host "Conta    : $UserName"
Write-Host "Alvo     : nivel $Alvo / $Cupons cupons"
Write-Host "ATENCAO  : a conta precisa estar OFFLINE (o jogo sobrescreve GP/Grade/Money ao salvar)`n" -ForegroundColor Yellow

if (-not $SomenteVerificar) {
    if (-not $SkipBackup) {
        Write-Host '[1/3] Backup...' -ForegroundColor Cyan
        Invoke-Sql -File (Join-Path $dir '01_backup_usaimbot.sql') | Out-Host
    }

    Write-Host '[2/3] Aplicando nivel, cupons e itens...' -ForegroundColor Cyan
    Invoke-Sql -File (Join-Path $dir '02_boost_usaimbot.sql') `
               -Var @(
                   @{ Name = '@TargetGrade\s+int\s*=\s*\d+'; Value = "@TargetGrade int = $Alvo" },
                   @{ Name = '@TargetMoney\s+int\s*=\s*\d+'; Value = "@TargetMoney int = $Cupons" }
               ) | Out-Host
} else {
    Write-Host '[1/3] Backup (ignorado)' -ForegroundColor DarkGray
    Write-Host '[2/3] Alteracao (ignorado)' -ForegroundColor DarkGray
}

Write-Host '[3/3] Verificacao...' -ForegroundColor Cyan
Invoke-Sql -File (Join-Path $dir '03_verificar_usaimbot.sql') `
           -Var @(@{ Name = '@TargetGrade\s+int\s*=\s*\d+'; Value = "@TargetGrade int = $Alvo" }) | Out-Host