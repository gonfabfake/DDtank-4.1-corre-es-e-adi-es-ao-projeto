# DDTank 4.1 — Patch Flash v2 e tentativa real de compilação

Aplicado em `C:\DDTank4.1-main` a partir de `DDTank41_Controles_PC_Mobile_v2.zip`.

> **Não houve branch git.** `C:\DDTank4.1-main` não é repositório git (o `.git` não
> veio no pacote). O patch gravou backups `.mobile-original.bak` dos três arquivos
> alterados, que substituem a proteção que a branch teria dado.

---

## 1. O patch — aplicado e testado

Integridade: os 11 arquivos conferem com `SHA256SUMS.txt`. **`python -m unittest discover -s tests -v` → 4/4 passaram.**
Re-execução do instalador → 0 alterações (idempotente confirmado).

| Arquivo | Alteração |
|---|---|
| `Source Flash\scripts\game\view\arrow\ArrowView.as` | aceita `MobileControls.angleUp/angleDown` **em paralelo** ao teclado |
| `Source Flash\scripts\game\view\EnergyView.as` | aceita `forceDown`; trata `forceCancelSerial` **antes** do fim-depressão (cancelar ≠ atirar) |
| `Source Flash\scripts\game\view\GameViewBase.as` | cria o HUD só na batalha; `dispose()` ao sair |
| `Source Flash\scripts\game\view\mobile\MobileControls.as` | classe nova (326 linhas) |
| `Source Flash\scripts\game\view\mobile\assets\*.png` | 3 imagens |

**PC não regride:** as flags mobile foram somadas com `||`, não substituíram nada.
Conferido no código instalado: as 4 chamadas a `KeyboardManager.isDown` seguem intactas.
`DESKTOP_BUTTONS = false` por padrão → os botões gráficos não aparecem no PC.

---

## 2. Toolchain — montei e está funcionando

Não era Skipp. Instalei e **verifiquei o compilador de ponta a ponta**:

| Item | Versão | Observação |
|---|---|---|
| Eclipse Temurin JDK | 17.0.20.1 (ZTS não; build x64) | o `amxmlc.bat` do Flex chama `java` do PATH — invoquei o `mxmlc.jar` direto |
| Apache Flex SDK | 4.16.1 build 20171115 | o binário **não traz `playerglobal.swc`** |
| `playerglobal.swc` | **11.1** (espelho `nexussays/playerglobal`) | versão alvo correta — o jogo é da era Flash Player 11.1 |
| `mx.swc` | do próprio Flex SDK | satisfaz o `mx.events.PropertyChangeEvent` de 6 arquivos |

**Prova de que o compilador funciona:** `Smoke.swf` (476 bytes) gerado a partir de um
arquivo AS3 trivial, antes de qualquer coisa do projeto.

Detalhe que custou tempo: `flex-config.xml` referencia `{playerglobalHome}` e **essa
variável não é definida em lugar nenhum** do SDK — nem em `env.properties`, nem por
`-D`. A solução foi apontar o caminho literal no `flex-config.xml`.

---

## 3. Do 73 ao 15 — correções aplicadas com evidência

Todas reproduzíveis por `Build-Flash.ps1`. Cada uma tem justificativa no próprio código:

| Correção | Arquivo | Evidência |
|---|---|---|
| excluir `cmodule\` | 7 arquivos | é o runtime **Flirt que descriptografa** os SWFs publicados; a única referência é `ModuleLoader`. Não é código de jogo. **−51 erros** |
| `§§pop()` → `this` | `FZipFile.as`, `StringUtils.as` | `§§pop()._implodeDictSize = …` só faz sentido se a expressão equivaler a `this`. **−6** |
| `buffInfo#2`/`#8` → `buffInfo` | `PlayerInfo.as` | em AS3 getter e setter do mesmo par precisam do mesmo nome. Únicos 2 membros `#N` da árvore. **−4** |
| `private` → `internal` | `org\aswing\KeyMap.as` | AS3 não tem package-private; `KeyMap` não conseguia ler `KeyAction.action`. **−1** |
| declarar `_loc3_`/`_loc4_`/`_loc5_` | `com\hurlant\crypto\prng\Random.as` | a linha válida (`_loc2_`) mostra o padrão exato: `var _locN_:* = pptr++;`. **−3** |

### Bugs meus no caminho (registrados porque voltariam)

* Escrevi `§§` literal no `.ps1` e o PowerShell leu como mojibake → os patches "aplicavam" sem efeito. Resolvido com `[char]0x00A7`.
* Troquei só `§§` e sobrou `thispop()`. O token inteiro é `§§pop()`.
* Checagem de idempotência comparava com `'this'` — que existe em qualquer arquivo — dando falso positivo.
* Recriando a árvore a cada execução, o build *regrediu* de 15 para 21 erros por causa dos patches que não casavam. O script agora é idempotente de verdade.

---

## 4. Onde parou — 15 erros, 3 arquivos, irrecuperáveis

```
com\hurlant\math\BigInteger.as        x6
com\wirelust\as3zlib\InfCodes.as      x4
com\wirelust\as3zlib\Tree.as         x5
```

**Todos são libraries de terceiros decompiladas para forma não-compilável:**

**`BigInteger.as:818`** — o decompilador emitiu
```actionscript
for(; §§hasnext(y,_loc6_); })
{
   with(_loc9_)
   {
      y.t++;
      if(0) { _loc5_[_loc6_] = _loc8_; }
   }
}
```
É resto de opcode `hasnext2` vazado. O corpo real só existe no ABC original.

**`Tree.as` / `InfCodes.as`** — "Acesso de propriedade indefinida `_loc9_`", `_loc15_`, `_loc25_`:
declarações de variáveis **perdidas** dentro do inflate do zlib. Inferir os tipos e os
pontos de escrita seria reimplementar a descompressão.

**Por que não continuei:** estas duas são *crypto* (assinatura de pagamento) e
*decompressão* (o arquivo de configuração `xml.png`). Um zlib inflate sutilmente errado
corrompe a configuração do jogo **em silêncio** — falha muito pior do que não compilar.
Ambas têm fonte público conhecido (**TCP/TcpLib** de Andrea Giammarchi; **as3zlib** de
Russell Hather), mas trocar a implementação original por uma reimplementação minha seria
justamente o "não invente um build bem-sucedido" que o prompt proíbe.

---

## 5. O que eu precisaria para terminar

1. `BigInteger.as` e `Tree.as`/`InfCodes.as` originais (TCP/TcpLib e as3zlib das versões
   que o projeto usava) — ou o `.as3proj` do build original.
2. **O projeto de build**, que continua sendo o bloqueador de arquitetura: os SWFs
   publicados têm os corpos ABC criptografados, então a partição fontes→SWF é irrecuperável.
   Qualquer SWF que eu gerar é **um único blob**, não os 77 módulos que o cliente carrega.

> Compilar **não** garante funcionamento. Sem o `game.swf` original para comparar e sem
> um runtime Flash (Ruffle/AIR) para executar, não existe como homologar.

---

## 6. Reproduzir

```powershell
C:\DDTank4.1-main\Build-Flash.ps1
```

Copia as fontes, exclui `cmodule\`, instala o stub `flash-stubs\com\pickgliss\debug\DebugStats.as`
(`com.pickgliss.debug` é importado por `ChatManager.as` mas não existe no repositório),
aplica as 8 correções e compila. Log em `%TEMP%\opencode\ddtank\compile.log`.

## 7. O que não toquei

Nada de protocolo de batalha, servidor .NET, banco, loja, login, economia, autenticação
ou credenciais foi alterado por este patch.