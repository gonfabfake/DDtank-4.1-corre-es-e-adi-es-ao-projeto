# Jogar pelo celular (Wi-Fi) — 16/10/2026

O PC fica ligado como servidor silencioso (nenhuma janela aparece) e o celular
joga pelo navegador, na mesma rede Wi-Fi. Testado de ponta a ponta a partir de
http://192.168.3.58/ (o endereço que o celular usa é o mesmo).

## No celular
1. Wi-Fi ligado na MESMA rede do PC.
2. Abra no navegador (Chrome recomendado): http://192.168.3.58/
3. Entre com seu usuário/senha do portal e abra o jogo
   (ou vá direto a http://192.168.3.58/play.php se a sessão já existir).
4. No primeiro acesso o jogo mostra "Sugestões Oficiais": toque em Aceitar.
5. Dica: gire o celular na horizontal — o jogo é 1000x600 (paisagem).

## No PC — configuração da rede (uma vez; refazer se o IP do PC mudar)
- Com o PC ligado e os serviços rodando, execute como administrador:
  `powershell -ExecutionPolicy Bypass -File C:\DDTank4.1-main\Setup-Mobile-LAN.ps1`
  (adiciona o binding do IIS para o IP da placa Wi-Fi e as regras de firewall
  "DDTank LAN TCP 80"/"TCP 8765", restritas à sub-rede local).
- Se o roteador trocar o IP do PC: rode o script de novo e use o novo endereço.

## Dia a dia
- Ligar o servidor (invisível): `powershell -ExecutionPolicy Bypass -File C:\DDTank4.1-main\Start-Server.ps1`
- Conferir: mesmo comando com `-Action status` · Parar: `-Action stop`
- O computador precisa ficar ligado e sem suspensão automática (o jogo não
  acorda o PC sozinho). O IIS já sobe com o Windows.

## Desempenho do carregamento
- Os recursos ficam em cache local (C:\DDTank4.1-main\ResourceCache). Depois da
  primeira visita de cada recurso, o carregamento é praticamente instantâneo
  (módulos de UI em ~6 s).
- A primeira visita a um mapa/painel novo ainda baixa alguns arquivos do CDN
  legado — só na primeira vez.
- Evite manter o console do navegador aberto no celular; o restante do atraso
  (~20-25 s) é a sequência de login do próprio servidor do jogo, igual ao
  comportamento do cliente original.

## Limites
- Não exponha esta instalação na internet; o firewall libera só a rede local e o
  proxy recusa qualquer origem/peer fora de 127.0.0.1 e 192.168.3.0/24.
- Batalha ainda não foi validada pelo celular — teste e reporte em caso de falha.
- Erros não fatais conhecidos: ver "Limitações restantes" em RUFFLE_LOCAL.md.
