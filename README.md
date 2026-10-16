# DDTank 4.1 — correções e melhorias do projeto

> **Situação em 16/10/2026:** projeto em desenvolvimento. Portal e carregamento do cliente foram relatados/testados em diferentes etapas, mas a **jogabilidade completa não está homologada**. Este README descreve o código e os registros do repositório — não substitui testes com o servidor Windows, SQL Server e clientes reais.

Adaptação de DDTank 4.1 com serviços C#/.NET, portal PHP, banco SQL Server, cliente ActionScript/Flash, execução no navegador com **Ruffle**, proxy WebSocket e painel administrativo em React/PHP. Esta documentação substitui o README inicial, que citava apenas a compilação do servidor no Visual Studio.

## O que já existe no projeto

| Componente | Implementado no código | Validação/limitações |
| --- | --- | --- |
| Serviços de jogo (Center, Road e Fighting) | Projetos C#/.NET e inicialização PowerShell | Requer binários, banco e serviços operacionais |
| Portal e autenticação | PHP/IIS e endpoints ASP.NET em `Request/` | Conferir sessão, login e segurança no ambiente instalado |
| Cliente Flash no navegador | Ruffle local, `Web/play.php`, `Web/config.php` | Abrir a cidade ou tela de combate **não comprova uma partida concluída** |
| Transporte de jogo | Proxy WebSocket em `Tools/ruffle-proxy/` | Conferir portas, limites de rede e logs |
| Acesso por celular (LAN) | Scripts de IIS/firewall e controles móveis em ActionScript | Toque, carregamento de força e batalhas precisam de testes no aparelho |
| Painel administrativo | React + htm + PHP, contas, itens, imagens e auditoria | Testes automatizados cobrem parte do código; teste com SQL/IIS continua necessário |
| Catálogo e imagens | Campos `TemplateID`, `Pic` e `CategoryID`; cache local e recurso remoto | Recursos podem faltar no servidor de origem; caminho não é sempre baseado em ID numérico |
| Build de Flash | `Build-Flash.ps1` como tentativa de compilação | **Não é o pipeline original dos vários SWFs.** O relatório registra bloqueios |

## Arquitetura resumida

```text
Navegador (PC/celular)
  ├─ HTTP :80 → IIS / Web (PHP + Ruffle + SWFs)
  │            ├─ /request → serviços ASP.NET
  │            └─ /painel  → React/htm + API PHP
  └─ WS :8765/game → proxy Node.js → TCP do servidor local
Servidor Windows
  ├─ Center.Service / Road.Service / Fighting.Service
  ├─ SQL Server (bancos do DDTank)
  └─ ResourceCache e recursos do cliente
```

Os esquemas, portas e endereços exatos devem ser conferidos na configuração **da instalação atual**. O projeto contém caminhos absolutos para a estrutura de testes do Windows; podem exigir ajuste em outra máquina.

## Pastas e arquivos essenciais

| Caminho | Finalidade |
| --- | --- |
| `Source Server/` | Projetos e serviços do servidor C# |
| `Source Flash/` | Fontes ActionScript do cliente |
| `Web/` | Portal, `play.php`, Ruffle, configurações e painel |
| `Request/` | Endpoints ASP.NET para o cliente |
| `Tools/ruffle-proxy/` | Ponte WebSocket/TCP do jogo |
| `Start-Server.ps1` | Iniciar, parar e verificar serviços |
| `Setup-Mobile-LAN.ps1` | Configurar acesso do celular na mesma rede local |
| `Build-Flash.ps1` | Build de diagnóstico **não equivalente ao cliente original** |
| `Web/painel/README.md` | Funcionamento e correções específicas do painel |

## Execução — instalação Windows já configurada

Este roteiro **não instala automaticamente** SQL Server, IIS, .NET, Node.js, Ruffle ou compila os serviços. Consulte os documentos técnicos para preparar esses componentes.

```powershell
# Abra na pasta-raiz do projeto, com a instalação já configurada.
.\Start-Server.ps1 -Action status
.\Start-Server.ps1 -Action start
.\Start-Server.ps1 -Action status

# Quando quiser encerrar os serviços iniciados pelo script:
.\Start-Server.ps1 -Action stop
```

Portal local: `http://127.0.0.1/`. Painel de administração: `http://127.0.0.1/painel/` (autenticação e lista de IPs permitidos, por padrão somente a própria máquina).

Para acesso pelo celular na **mesma LAN**, consulte [ACESSO-MOBILE.md](ACESSO-MOBILE.md) e revise `Setup-Mobile-LAN.ps1` antes de executá-lo como administrador. O endereço IP depende da rede atual; **não** reutilize cegamente o IP fixo dos relatórios antigos. Não publique as portas do jogo nem o banco SQL diretamente na internet.

> **IIS/FastCGI:** [IIS_VALIDADO.md](IIS_VALIDADO.md) descreve a instalação que foi testada. Alguns roteiros/scripts antigos contêm caminhos ou ajustes que já não correspondem a essa configuração. Faça backup e compare com o IIS atual antes de alterar pools, bindings ou handlers.

## Imagens dos itens: como o painel tenta encontrá-las

No catálogo SQL (`Db_Tank41.dbo.Shop_Goods`), `TemplateID` identifica o modelo do item, `CategoryID` indica sua categoria e `Pic` guarda uma **chave de recurso textual**, quando preenchida.

A função original `Web/function.php::loadimage($image, $loaivp, $sex)` combina **chave de imagem**, categoria e, para roupas/acessórios relevantes, `m`/`f`. Portanto, não se deve presumir que `$image` é sempre o `TemplateID`.

Exemplo de formato informado para a resource:
`https://resbr51-myddt-ofc.ddtankswordgames.com/image/unfrightprop/guidname/icon.png`

Na atualização do painel (branch da proposta de mudança), o leitor tenta primeiro `Pic`, depois `TemplateID`, preserva `/image/`, testa variações de sexo quando aplicável, e usa o SVG local caso nenhum recurso seja encontrado. **O nome `guidname` é apenas um exemplo de caminho:** não prova a correspondência de todos os itens. Consulte [o guia do painel](Web/painel/README.md) para diagnóstico e testes.

## Build e controles do cliente Flash

O cliente original é composto por múltiplos módulos; a recompilação de um SWF de teste isolado **não garante** o funcionamento do Ruffle nem do combate completo. `RELATORIO_PATCH_FLASH.md` registra a tentativa de build, erros de bibliotecas decompiladas e o patch para controles no PC/celular. Antes de publicar novos SWFs, reproduza a compilação adequada, preserve os controles por teclado e valide os módulos carregados no navegador.

## O que ainda precisa ser homologado

1. Fluxo completo: login → cidade → sala → batalha PVP/PVE → ângulo/força → disparo/dano → turnos → vitória/derrota → persistência no banco.
2. Controles do PC (teclado) e celular (toque, dois dedos, cancelamento, perda de foco).
3. Compatibilidade dos SWFs publicados com o cliente e o Ruffle.
4. Imagens reais de várias categorias (`Pic`, sexo, `TemplateID`, cache e falhas do CDN).
5. Segurança e robustez de autenticação, proxy, permissões e serviços após instalar alterações.

Não use apenas HTTP 200, tela aberta ou testes de sintaxe como evidência de que o jogo está 100% funcional.

## Segurança e manutenção

- Guarde senhas e arquivos sensíveis **fora da raiz pública**; nunca versionar segredos.
- Se credenciais estiveram expostas no histórico Git, trate-as como comprometidas e faça a rotação.
- Prefira a configuração de menor privilégio descrita em [SEGURANCA-SQL.md](SEGURANCA-SQL.md).
- O painel usa autenticação, checagem de IP/permissões, CSRF para alterações, consultas SQL parametrizadas e registra ações administrativas. **Verifique essas proteções no ambiente real**.
- Faça backup antes de atualizar o servidor ou o banco; teste mudanças em branch separada.

## Outros documentos técnicos

- [RUFFLE_LOCAL.md](RUFFLE_LOCAL.md) — fluxo web e limitações do Ruffle.
- [IIS_VALIDADO.md](IIS_VALIDADO.md) — configuração de IIS/PHP validada no ambiente registrado.
- [ACESSO-MOBILE.md](ACESSO-MOBILE.md) — acesso via Wi-Fi, portas e limitações.
- [RELATORIO_PATCH_FLASH.md](RELATORIO_PATCH_FLASH.md) — patch ActionScript e compilação pendente.
- [SEGURANCA-SQL.md](SEGURANCA-SQL.md) — proteção do SQL Server.
- [Web/painel/README.md](Web/painel/README.md) — painel, imagens, API e diagnósticos.

## Origem e créditos

Este repositório deriva do trabalho original DDTank 4.1, anteriormente atribuído a **SkelletonX** e **amrmostafa800**. Referências históricas: [repositório/wiki original](https://github.com/SkelletonX/DDTank4.1/wiki) e [releases do projeto de origem](https://github.com/SkelletonX/DDTank4.1/releases). As correções e integrações desta cópia foram desenvolvidas posteriormente. Consulte os arquivos de licença e os direitos dos recursos antes de redistribuir componentes de terceiros.

---

**Atualização documental:** 16 de outubro de 2026. O estado descrito é uma fotografia técnica deste repositório, incluindo alterações da branch de manutenção do painel; não é declaração de homologação em produção.
