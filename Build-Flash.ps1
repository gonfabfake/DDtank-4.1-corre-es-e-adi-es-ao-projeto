<#
    Build do cliente Flash DDTank 4.1 — SCRIPT DE TESTE
    ===========================================================================
    Compila a árvore de ActionScript decompilada em um SWF único de TESTE.
    NÃO reproduz a estrutura de módulos do build original (ver RELATORIO_PATCH_FLASH.md):
    o repositório não traz projeto de build e os SWFs publicados têm os corpos ABC
    criptografados, então a partição fontes->SWF é irrecuperável.

    Estado atual: 15 erros restantes, todos em libraries de terceiros
    decompiladas para forma não-compilável. Ver seção "BLOQUEIOS" no relatório.

    Uso:
      powershell -ExecutionPolicy Bypass -File Build-Flash.ps1
#>
param(
  [string]$EntryPoint = 'game/view/GameView.as',
  [string]$OutputName = 'game-test.swf'
)

$ErrorActionPreference = 'Stop'
$TK  = 'C:\Users\gonfa\AppData\Local\Temp\opencode\ddtank'
$java  = (Get-ChildItem "$TK\jdk" -Recurse -Filter java.exe -EA SilentlyContinue | Select-Object -First 1).DirectoryName
$flex  = "$TK\flex"
$src   = 'C:\DDTank4.1-main\Source Flash\scripts'
$build = "$TK\asbuild\scripts"
$out   = "$TK\swfout"

function Say($m) { Write-Host ("[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $m) }

foreach ($p in @($java, $flex)) {
    if (-not (Test-Path $p)) { Say "FALTA: $p  (rode o setup do toolchain abaixo)"; exit 1 }
}
New-Item -ItemType Directory -Path $out -Force | Out-Null

# --------------------------------------------------------------------------
# 1) Montar a arvore de build a partir dos fontes do repo
# --------------------------------------------------------------------------
if ($Args -notcontains '-skip-copy') {
    Say 'copiando fontes para a arvore de build...'
    Remove-Item "$TK\asbuild" -Recurse -Force -EA SilentlyContinue
    New-Item -ItemType Directory -Path $build -Force | Out-Null
    robocopy $src $build /E /XF "*.bak" /NFL /NDL /NJH /NJS /NP | Out-Null
    Say "  $((Get-ChildItem $build -Recurse -Filter *.as).Count) arquivos .as"

    # cmodule\decry = runtime Flirt que DESCRIPTOGRAFA os SWFs publicados.
    # Nao faz parte do jogo; e a unica referencia a ele esta em ModuleLoader.
    if (Test-Path "$build\cmodule") {
        Remove-Item "$build\cmodule" -Recurse -Force
        Say '  removido cmodule\ (runtime Flirt de descriptografia)'
    }

    # com.pickgliss.debug.DebugStats: importado por ddt\manager\ChatManager.as
    # mas o pacote nao existe no repo. Stub isolado para permitir o build.
    New-Item -ItemType Directory -Path "$build\com\pickgliss\debug" -Force | Out-Null
    Copy-Item "$TK\flash-stubs\com\pickgliss\debug\DebugStats.as" "$build\com\pickgliss\debug\" -Force
    Say '  instalado stub DebugStats.as'
}

# --------------------------------------------------------------------------
# 2) Aplicar as correcoes de decompilacao (idempotente)
# --------------------------------------------------------------------------
Say 'aplicando correcoes de decompilacao...'

function Patch([string]$rel, [string]$old, [string]$new, [string]$label) {
    $f = Join-Path $build $rel
    if (-not (Test-Path $f)) { Say "  AVISO: $rel nao existe"; return }
    $c = [IO.File]::ReadAllText($f)
    # Idempotencia: so reaplica se o padrao VELHO ainda existir.
    # (comparar com $new nao funciona quando $new e uma palavra comum como 'this')
    if (-not $c.Contains($old)) { Say "  ja aplicado: $label"; return }
    [IO.File]::WriteAllText($f, $c.Replace($old, $new))
    Say "  aplicado: $label"
}

# '§§pop()' e 'this' renderizado errado pelo decompilador. O contexto
# '§§pop()._implodeDictSize = ...' prova que a expressao toda equivale a 'this'.
# Trocar apenas '§§' geraria 'thispop()' — precisa remover o 'pop()' tambem.
# O caractere e montado por codigo: escrever U+00A7 literal num .ps1 depende da
# codificacao com que o PowerShell le o arquivo.
$sec = [string][char]0x00A7
Patch 'deng\fzip\FZipFile.as' "$sec$sec" 'this' 'FZipFile: pop() -> this (parte 1)'
Patch 'deng\fzip\FZipFile.as' 'thispop()' 'this' 'FZipFile: pop() -> this (parte 2)'
Patch 'com\pickgliss\utils\StringUtils.as' "$sec$sec" 'this' 'StringUtils: pop() -> this'

# getters/setters desduplicados pelo decompilador: em AS3 o par precisa
# do mesmo nome
Patch 'ddt\data\player\PlayerInfo.as' 'get buffInfo#2()' 'get buffInfo()' 'PlayerInfo: get buffInfo#2 -> buffInfo'
Patch 'ddt\data\player\PlayerInfo.as' 'set buffInfo#8(' 'set buffInfo(' 'PlayerInfo: set buffInfo#8 -> buffInfo'

# AS3 nao tem package-private; 'private' impede KeyMap de ler KeyAction.action
Patch 'org\aswing\KeyMap.as' '   private var key:KeyType;' '   internal var key:KeyType;' 'KeyMap: key -> internal'
Patch 'org\aswing\KeyMap.as' '   private var action:Function;' '   internal var action:Function;' 'KeyMap: action -> internal'

# o decompilador perdeu as declaracoes de _loc3_/_loc4_/_loc5_ no seed().
# A linha valida (_loc2_) mostra o padrao: "var _locN_:* = pptr++;"
$r  = 'com\hurlant\crypto\prng\Random.as'
$rf = Join-Path $build $r
if (Test-Path $rf) {
    $c = [IO.File]::ReadAllText($rf)
    if ($c.Contains('var _loc3_:* = pptr++;')) {
        Say '  ja aplicado: Random: _loc3_/_loc4_/_loc5_'
    } else {
        # tres substituicoes simples, cada uma espelhando a linha valida (_loc2_)
        $map = @(
            @{ n='_loc3_'; sh='8'  },
            @{ n='_loc4_'; sh='16' },
            @{ n='_loc5_'; sh='24' }
        )
        $ok = $true
        foreach ($m in $map) {
            $pat = "pool[pptr++] = pool[$($m.n)] ^ x >> $($m.sh) & 255;"
            $rep = "var $($m.n):* = pptr++;`r`n         pool[$($m.n)] = pool[$($m.n)] ^ x >> $($m.sh) & 255;"
            if ($c.Contains($pat)) {
                $c = $c.Replace($pat, $rep)
            } else { $ok = $false; Say "    (nao achou: $pat)" }
        }
        if ($ok) { [IO.File]::WriteAllText($rf, $c); Say '  aplicado: Random: _loc3_/_loc4_/_loc5_' }
        else      { Say '  AVISO: padrao de Random.as nao reconhecido' }
    }
}

# --------------------------------------------------------------------------
# 3) Compilar
# --------------------------------------------------------------------------
Say "compilando $EntryPoint -> $OutputName ..."
# O compilador escreve em stderr; no PowerShell isso vira NativeCommandError e
# aborta o script. Redireciono para arquivo e leio depois.
$logf = "$TK\compile.log"
$ErrorActionPreference = 'Continue'
& "$java\java.exe" "-Dflex.home=$flex" -Xmx2048m -jar "$flex\lib\mxmlc.jar" `
    "+configname=flex" "+flexlib=$flex/frameworks" `
    "-source-path=$build" "-output=$out/$OutputName" `
    "-library-path+=$flex/frameworks/libs/mx/mx.swc" `
    "-target-player=11.1" "-swf-version=11" "-debug=false" `
    "$build/$EntryPoint" 1> $logf 2>&1
$ErrorActionPreference = 'Stop'

$txt = Get-Content $logf -Raw
$nE = ([regex]::Matches($txt, 'Erro:')).Count
$nW = ([regex]::Matches($txt, 'Aviso:')).Count
Say "ERROS: $nE   AVISOS: $nW"
Say "log completo: $logf"

if ($nE -gt 0) {
    Say 'arquivos com erro:'
    Select-String -Path $logf -Pattern 'Erro: ' | ForEach-Object {
        $m = [regex]::Match($_.Line, 'scripts\\(.+\.as)\(')
        if ($m.Success) { "  " + $m.Groups[1].Value }
    } | Sort-Object -Unique | ForEach-Object { Say $_ }
}

if (Test-Path "$out/$OutputName") {
    $f = Get-Item "$out/$OutputName"
    Say ("SWF GERADO: {0} KB  {1}" -f [math]::Round($f.Length/1KB,1), $f.FullName)
    Say ("SHA256: " + (Get-FileHash $f.FullName -Algorithm SHA256).Hash)
} else {
    Say 'SWF nao gerado. Erros acima.'
    exit 2
}