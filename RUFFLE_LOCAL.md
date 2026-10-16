# Ruffle local — 16/10/2026

Entrada: http://localhost/ (normalizado para http://127.0.0.1/ para manter uma sessão).
Acesso pelo CELULAR na mesma rede Wi-Fi: http://192.168.3.58/ (ver ACESSO-MOBILE.md).
Portal e Flash servidos pelo IIS na porta 80. Porta 8080 não utilizada.

## Alterações
- Ruffle oficial v0.7.1 self-hosted em Web/ruffle, incluindo WASM e licenças.
- play.php: valida sessão, reautentica e emite ticket assinado via
  /request/CreateLogin.aspx; não usa checkuser.ashx/logingame.aspx sem implementação.
- play.php deriva o host da requisição (HTTP_HOST): configura o Flash com
  `config` = http://<host>/config.php e o WebSocket ws://<host>:8765/game.
  Nada fixo em 127.0.0.1 chega ao navegador; funciona igual no PC e no celular.
- Web/config.php serve o config.xml aplicando o host atual; Web/config.xml usa URLs
  relativas à raiz (/asset.php?path=, /flash/, /request/), então vale em qualquer host.
- WebSocket 8765/game: aceita somente loopback e 192.168.3.0/24 (origem e peer),
  destino fixo 127.0.0.1:9200. Ouve em todas as interfaces para o celular atender;
  o PC segue usando 127.0.0.1. Só o caminho /game é aceito.
- Web/asset.php: busca apenas caminhos permitidos no host fixo ddt-a.akamaihd.net,
  valida HTTP 200 antes de gravar (nunca cacheia corpo de erro/redirect), grava de
  forma atômica (tmp + rename) e mantém o cache fora da raiz em ResourceCache.
  Aceita os prefixos reais usados pelo cliente (image/, sound/, flash/, swf/ e
  caminhos raiz como M/... e md5*).
- Integrações sociais externas e estatísticas desativadas no config.xml.
- Start-Server.ps1 inicia serviços e proxy com -WindowStyle Hidden (nada aparece no PC).
- "Sugestões Oficiais" no primeiro acesso é do próprio jogo (ensina o
  armazenamento local do Flash); fecha com "Aceitar".

## Mobile
- Binding IIS 192.168.3.58:80 + firewall TCP 80/8765 restrito a LocalSubnet:
  Setup-Mobile-LAN.ps1 (idempotente, requer administrador).
- Teste headless garante que a página servida em 192.168.3.58 não faz nenhuma
  requisição para 127.0.0.1 (checagem automática) — nada quebra no celular.
- Web/cmfriendicon/icon.png (1x1) existe para eliminar uma tentativa 404 do cliente.

## Validação real
Chrome headless com Playwright (PC em 127.0.0.1 e via 192.168.3.58): cadastro e login
por HTTP, ticket aceito, SWF carregado pelo Ruffle, conexão ao servidor e cidade
exibida. Módulos de UI (hall/toolbar) carregados em ~6 s com o cache aquecido
(antes: ~35-45 s por causa de downloads frios e do antigo CORS do CDN).
Logs/capturas: C:\Users\gonfa\AppData\Local\Temp\opencode\ddtank\ (lan-*.png/log).
Contas de teste com prefixos ruffletest/rtest; contas existentes não alteradas.

## Limitações restantes
- Batalha ainda não validada (nem no PC nem no celular).
- Erros não fatais no console (não impedem o jogo e existiam antes das mudanças):
  1) um loadBytes interno do loader acusa SWF corrompido; os 77 módulos de UI foram
  verificados byte a byte e estão íntegros — é uma checagem interna que falha e o
  jogo segue; 2) achievementInit dispara Error #1009 no EffortManager.
- weekly/weeklyinfo.xml responde 404 (4 tentativas rápidas) e o painel semanal fica
  ausente; esse é o comportamento silencioso aceito. NÃO criar esse arquivo sem
  conteúdo real: com dados vazios o cliente abre o painel e dispara RangeError.
- SavePlayerAction.ashx não existe no /request e responde 404 (1x por sessão).
- Primeira carga de um recurso ainda não cacheado depende da internet (CDN legado
  ddt-a.akamaihd.net). O cache aquece sozinho; visitas seguintes são locais.
- SWFs publicados são os originais; o patch mobile permanece apenas nos fontes.
- Servidor legado deve permanecer local; não publicar esta instalação na internet.
