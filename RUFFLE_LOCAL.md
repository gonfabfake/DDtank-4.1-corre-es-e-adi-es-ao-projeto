# Ruffle local — 16/10/2026

Entrada: http://localhost/ (normalizado para http://127.0.0.1/ para manter uma sessao).
Portal e Flash servidos pelo IIS na porta 80. Porta 8080 nao utilizada.

## Alteracoes
- Ruffle oficial v0.7.1 self-hosted em Web/ruffle, incluindo WASM e licencas.
- play.php substituido: valida sessao, reautentica e emite ticket assinado via
  /request/CreateLogin.aspx; nao usa checkuser.ashx/logingame.aspx sem implementacao.
- Eliminadas referencias a variaveis indefinidas e tratamento de chave antes da sessao.
  A causa exata do HTTP 500 da captura anterior nao foi reproduzida sem a sessao do usuario.
- WebSocket local 127.0.0.1:8765/game encaminha exclusivamente para 127.0.0.1:9200.
  Start-Server.ps1 gerencia o proxy Node; origens e mensagens sao restritas.
- Web/asset.php resolve CORS do servidor de recursos legado, buscando apenas caminhos
  permitidos no host fixo ddt-a.akamaihd.net. Cache fora da raiz em ResourceCache.
  Recursos ainda nao armazenados exigem internet. Nao e instalacao offline completa.
- Integracoes sociais externas e estatisticas desativadas no config.xml.

## Validacao real
Chrome headless com Playwright: cadastro e login por HTTP, ticket aceito, SWF carregado
pelo Ruffle, conexao ao servidor e cidade exibida. Avisos iniciais fechados por clique.
Captura: C:\Users\gonfa\AppData\Local\Temp\opencode\ddtank\ruffle-runtime.png
Log: mesmo diretorio, ruffle-runtime.log.
Foram criadas contas de teste com prefixos ruffletest/rtest; contas existentes nao alteradas.
Lint PHP e testes de seguranca/echo da ponte passaram.

## Limitacoes restantes
- Batalha nao validada.
- Log ainda aponta recurso SWF com erro de descompressao e erro em achievementInit.
- Recursos opcionais weeklyinfo.xml, cmfriendicon/icon.png e SavePlayerAction ausentes.
- SWFs publicados sao os originais; patch mobile permanece apenas nos fontes.
- Servidor legado deve permanecer local; nao publicar esta instalacao na internet.
