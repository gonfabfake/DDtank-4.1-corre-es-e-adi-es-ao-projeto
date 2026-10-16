# ContaBoost — nivel, cupons e itens (DDTank 4.1)

Ferramenta para ajustar uma conta no **seu servidor local** (`C:\DDTank4.1-main`,
SQL Server `Db_Tank41` / `Db_Tank` / `Db_Membership`). Feita para a conta
`usaimbot` (UserID 25), mas o UserID e facil de trocar.

## Arquivos

| Arquivo | Funcao |
|---|---|
| `01_backup_usaimbot.sql` | Serializa a conta e o inventario em `ZZ_Bk_UsersDetail` / `ZZ_Bk_UsersGoods` |
| `02_boost_usaimbot.sql` | Aplica nivel, cupons e o kit de itens (transacional) |
| `03_verificar_usaimbot.sql` | Verifica resultado e integridade, retorna `TUDO OK` |
| `restore_itens_usaimbot.sql` | Devolve os 5 itens originais (uso pontual, antes da 1a execucao) |
| `boost.ps1` | Orquestra backup -> alteracao -> verificacao |

## Uso

```powershell
.\boost.ps1                                  # padrao: nivel 60, 2 bilhoes de cupons
.\boost.ps1 -Alvo 70 -Cupons 500000          # outros valores
.\boost.ps1 -SomenteVerificar                # so leitura
```

Mudando o alvo: edite `@UserID` no inicio dos tres `.sql`.

## Como o sistema funciona (nao e "magica")

**Nivel.** `Sys_Users_Detail.Grade` e *derivado* de `Sys_Users_Detail.GP`
(`LevelMgr.GetLevel`, `Source Server\Game.Server\Server\Managers\LevelMgr.cs:49-63`).
O script le o GP necessario direto de `Db_Tank.dbo.LevelInfo` — nao existe
tabela de XP no `Db_Count`, e `StrengThenExp` e tabela de *upgrade de item*,
nao de jogador. Maximo do servidor: grade 70 (GP 857.997.577).

**Cupons.** "Cupom" neste servidor e a coluna `Sys_Users_Detail.Money` —
confirmado por `GamePlayer.AddMoney` ("Limite de cupons excedido") e por
`Web\Models\Pacote.php` ("BRL = X Cupons"). **E `INT`**: o teto real e
2.147.483.647. O script usa `SET` (nao soma), entao rodar de novo nao acumula.

**Itens.** Inseridos via `SP_Users_Items_Add`, a mesma procedure que o servidor
chama em `PlayerBussiness.AddGoods`. Regras respeitadas:

- `ItemID` e IDENTITY — nunca informado, capturado por `@ItemID OUTPUT`;
- `ValidDate = 0` = nunca expira (`ItemInfo.cs:244`);
- `Hole1..6 = -1` = furos fechados (`ItemInfo.cs:249-254`);
- `BagType 0` (equipado) usa `Place` 0..30; 31..79 e mochila; `>=80` e overflow;
- `BagType 1` (PropBag) usa `Place` 0..48.

Slot de equipamento por `CategoryID`: 0 chapeu, 2 cabelo, 4 roupa, 5 olhos,
**6 arma**, 7/8 pulseira, 9/10/16 anel, 11 terno, 12 colar.

**Nada e destruido.** Ao ocupar um slot, o item antigo vai para a mochila
(`RemoveType 22`). So cai para `IsExist = 0` se a mochila estiver lotada.

## Idempotente

Rodar varias vezes converge para o mesmo estado: nenhum item duplicado,
nenhum cupom acumulado. Reaproveita item ja existente em outro slot.

## ⚠️ A conta precisa estar OFFLINE

`GamePlayer.SaveIntoDatabase()` chama `SP_Users_Update`, que **sobrescreve**
`Grade`, `GP` e `Money` com os valores em memoria. Se o jogador estiver online
quando voce rodar, a alteracao e perdida no proximo save. Saia do jogo primeiro.

## Dois bugs de T-SQL que ja quebraram este script

Registrados aqui porque sao armadilhas silenciosas:

1. `SELECT @var = col ...` **nao zera** `@var` quando a consulta nao retorna
   linhas — mantem o valor anterior. Um `IF @ItemID IS NULL` depois disso testa
   lixo. Fix: `SET @var = NULL` antes de cada `SELECT @var = ...`.
2. `MIN(Place)` sobre os slots **ocupados** devolve `NULL` justamente quando a
   mochila esta vazia. Fix: gerar os slots 31..79 e filtrar com `NOT EXISTS`.

## Limitacoes conhecidas

- Nao ha comando de chat/admin para setar level. O `CommandsMgr.CheckAdmin`
  ignora o nome do comando e so testa `Game_Commands` (hoje so `UserID=1`).
- As tabelas `Webshop_Giftcode` / `Webshop_Giftcode_Item` (cupons do portal)
  estao **vazias e sem codigo que as leia** — nao ha como resgatar cupom no
  jogo sem implementar esse fluxo. O item `11408` (L.Coupon) e a moeda do
 Ew Shop** — deve ser "Web Shop". O item `11408` (L.Coupon) e a moeda do
  Web Shop, nao o giftcode.
- `Sys_Users_Detail.Cash` e coluna morta: `SP_Update_Cash` nao existe.
- Fontes `Source Server` leem `Db_Tank41` e `Db_Tank` via `Request\Web.config`;
  o C# **nao conecta** no `Db_Membership` (so o front PHP usa).