/* =====================================================================
   02_boost_usaimbot.sql
   Sobe nivel + cupons (Money) e equipa o melhor gear da conta usaimbot.

   PRE-REQUISITOS (verificado antes de executar):
     - Rodar 01_backup_usaimbot.sql antes.
     - A conta precisa estar OFFLINE. O GamePlayer.SaveIntoDatabase()
       chama SP_Users_Update e sobrescreve Grade/GP/Money com os valores
       em memoria. Se o jogador estiver online, a alteracao e perdida.

   REGRAS DO SERVIDOR (Source Server\Game.Server):
     - Sys_Users_Goods.ItemID e IDENTITY -> nunca informar, usar @@IDENTITY.
     - ValidDate = 0 significa "nunca expira" (ItemInfo.cs:244).
     - Hole1..Hole6 = -1 significa "fechado" (ItemInfo.cs:249-254).
     - BagType 0 (EquipBag): Place 0..30 equipado, 31..79 mochila, >=80 overflow.
     - BagType 1 (PropBag): Place 0..48.
     - Grade e derivado de GP via Db_Tank.dbo.LevelInfo.

   IDEMPOTENTE: pode rodar quantas vezes quiser, sempre converge para
   o mesmo estado final sem duplicar item nem acumular cupom.
   ===================================================================== */
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @UserID      int = 25;
DECLARE @TargetGrade int = 60;
DECLARE @TargetMoney int = 2000000000;   -- Money e INT: teto 2147483647

/* ---------- Resolve GP a partir da tabela oficial de niveis ---------- */
DECLARE @GP int = (SELECT GP FROM Db_Tank.dbo.LevelInfo WHERE Grade = @TargetGrade);
IF @GP IS NULL
    RAISERROR('Grade %d nao existe em Db_Tank.dbo.LevelInfo.', 16, 1, @TargetGrade);

/* ---------- Kit de itens ----------
   BagType 0 = equipado,  BagType 1 = inventario (PropBag)
   Place  = slot de equipamento ou posicao na mochila                     */
CREATE TABLE #Kit (Seq int IDENTITY(1,1), BagType int, Place int, TemplateID int, ItemCount int, [Label] varchar(60));

INSERT INTO #Kit (BagType, Place, TemplateID, ItemCount, [Label]) VALUES
    (0,  0, 6000041, 1, 'Chapeu  - Luz Intensa do Bakufu (Q6)'),
    (0,  2, 3511,    1, 'Cabelo  - Thuga (Q4)'),
    (0,  4, 6000061, 1, 'Roupa   - Luz Intensa do Bakufu (Q6)'),
    (0,  5, 6367,    1, 'Rosto   - Ceu deserto (Q4)'),
    (0,  6, 71843,   1, 'Arma    - Bastao Magico (Atq 1209, Q5)'),
    (0,  7, 85184,   1, 'Bracele - Pulseira 9o aniversario +10 (Q6)'),
    (0,  9, 95294,   1, 'Anel    - Anel 9o aniversario +10 (Q6)'),
    (0, 11, 13686,   1, 'Terno   - Gelo Juvenil (Q4)'),
    (0, 12, 14046,   1, 'Colar   - 10o nivel (Q5)'),
    (0, 31, 71842,   1, 'Mochila - Bastao Magico +12 (Atq 990)'),
    (1,  0, 11150,  50, 'Mochila - Pedra Avancada x50 (upgrade)'),
    (1,  1, 11408,  50, 'Mochila - L.Coupon x50');

/* ---------- Valida o kit contra o catalogo antes de gravar ---------- */
IF EXISTS (SELECT 1 FROM #Kit k LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = k.TemplateID WHERE s.TemplateID IS NULL)
BEGIN
    SELECT k.TemplateID, k.[Label] AS Item_Invalido
    FROM #Kit k LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = k.TemplateID
    WHERE s.TemplateID IS NULL;
    RAISERROR('Kit contem TemplateID inexistente em Shop_Goods. Nada foi gravado.', 16, 1);
END

/* ---------- Confere se algum item exige level acima do alvo ---------- */
IF EXISTS (SELECT 1 FROM #Kit k JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = k.TemplateID WHERE s.NeedLevel > @TargetGrade)
BEGIN
    SELECT k.TemplateID, k.[Label], s.Name, s.NeedLevel
    FROM #Kit k JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = k.TemplateID
    WHERE s.NeedLevel > @TargetGrade;
    RAISERROR('Item do kit exige NeedLevel acima do grade alvo. Nada foi gravado.', 16, 1);
END

DECLARE @SegA varchar(60) = 'mochila livre';

BEGIN TRAN;

/* ================= 1) NIVEL ================= */
UPDATE Db_Tank41.dbo.Sys_Users_Detail
SET Grade = @TargetGrade,
    GP    = @GP
WHERE UserID = @UserID;

IF @@ROWCOUNT = 0
BEGIN
    ROLLBACK TRAN;
    RAISERROR('UserID %d nao encontrado em Sys_Users_Detail.', 16, 1, @UserID);
END

/* ================= 2) CUPONS (Money) ================= */
-- SET (nao soma) para ser idempotente, com clamp no teto do INT.
UPDATE Db_Tank41.dbo.Sys_Users_Detail
SET Money = CASE WHEN @TargetMoney > 2147483647 THEN 2147483647 ELSE @TargetMoney END
WHERE UserID = @UserID;

/* ================= 3) ITENS ================= */
DECLARE @Seq int, @BagType int, @Place int, @TemplateID int, @ItemCount int, @Label varchar(60);
DECLARE @ItemID int, @FreeSlot int, @OccupantItemID int;

DECLARE kit_cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT Seq, BagType, Place, TemplateID, ItemCount, [Label] FROM #Kit ORDER BY Seq;
OPEN kit_cur;
FETCH NEXT FROM kit_cur INTO @Seq, @BagType, @Place, @TemplateID, @ItemCount, @Label;

WHILE @@FETCH_STATUS = 0
BEGIN
    /* 3.1 Libera o slot de destino: manda o ocupante para a mochila.
           Nada e destruido -- o gear antigo continua utilizavel.           */
    SELECT @OccupantItemID = ItemID
    FROM Db_Tank41.dbo.Sys_Users_Goods
    WHERE UserID = @UserID AND BagType = @BagType AND Place = @Place
      AND IsExist = 1 AND TemplateID <> @TemplateID;

    IF @OccupantItemID IS NOT NULL
    BEGIN
        SELECT @FreeSlot = MIN(Place)
        FROM Db_Tank41.dbo.Sys_Users_Goods
        WHERE UserID = @UserID AND BagType = 0 AND IsExist = 1 AND Place >= 31 AND Place <= 79;

        IF @FreeSlot IS NOT NULL
        BEGIN
            UPDATE Db_Tank41.dbo.Sys_Users_Goods
            SET Place = @FreeSlot, RemoveDate = GETDATE(), RemoveType = 22
            WHERE ItemID = @OccupantItemID;
            PRINT CONCAT('  [mochila] ', @SegA, ' -> ', @FreeSlot, ' (slot ', @Place, ' liberado)');
        END
        ELSE
        BEGIN
            -- Mochila cheia: descarta o ocupante (soft delete).
            UPDATE Db_Tank41.dbo.Sys_Users_Goods
            SET IsExist = 0, RemoveDate = GETDATE(), RemoveType = 30
            WHERE ItemID = @OccupantItemID;
            PRINT CONCAT('  [descartado] mochila cheia, ocupante do slot ', @Place, ' removido');
        END
    END

    /* 3.2 Reaproveita o template se ele ja existir na conta em outro slot
           (evita duplicar o mesmo item a cada execucao).                  */
    SELECT @ItemID = ItemID
    FROM Db_Tank41.dbo.Sys_Users_Goods
    WHERE UserID = @UserID AND TemplateID = @TemplateID
      AND IsExist = 1 AND BagType = @BagType AND Place = @Place;

    IF @ItemID IS NULL
        SELECT @ItemID = ItemID
        FROM Db_Tank41.dbo.Sys_Users_Goods
        WHERE UserID = @UserID AND TemplateID = @TemplateID
          AND IsExist = 1 AND BagType = @BagType AND Place <> @Place;

    IF @ItemID IS NOT NULL
    BEGIN
        UPDATE Db_Tank41.dbo.Sys_Users_Goods
        SET Place = @Place, Count = @ItemCount, IsExist = 1,
            BagType = @BagType, ValidDate = 0, RemoveDate = GETDATE(), RemoveType = 0
        WHERE ItemID = @ItemID;
        PRINT CONCAT('  [reaproveitado] slot ', @BagType, ':', @Place, '  ', @Label);
    END
    ELSE
    BEGIN
        SET @ItemID = 0;
        EXEC Db_Tank41.dbo.SP_Users_Items_Add
             @ItemID           = @ItemID OUTPUT,
             @UserID           = @UserID,
             @TemplateID       = @TemplateID,
             @Place            = @Place,
             @Count            = @ItemCount,
             @IsJudge          = 1,
             @Color            = '',
             @IsExist          = 1,
             @StrengthenLevel  = 0,
             @AttackCompose    = 0,
             @DefendCompose    = 0,
             @LuckCompose      = 0,
             @AgilityCompose   = 0,
             @IsBinds          = 0,
             @BeginDate        = GETDATE(),
             @ValidDate        = 0,
             @BagType          = @BagType,
             @Skin             = '',
             @IsUsed           = 0,
             @RemoveType       = 0,
             @Hole1            = -1,
             @Hole2            = -1,
             @Hole3            = -1,
             @Hole4            = -1,
             @Hole5            = -1,
             @Hole6            = -1,
             @StrengthenTimes  = 0,
             @Hole5Level       = 0,
             @Hole5Exp         = 0,
             @Hole6Level       = 0,
             @Hole6Exp         = 0,
             @IsGold           = 0,
             @goldValidDate    = 0,
             @StrengthenExp    = 0,
             @goldBeginTime    = GETDATE();
        PRINT CONCAT('  [inserido ItemID=', @ItemID, '] slot ', @BagType, ':', @Place, '  ', @Label);
    END

    FETCH NEXT FROM kit_cur INTO @Seq, @BagType, @Place, @TemplateID, @ItemCount, @Label;
END
CLOSE kit_cur;
DEALLOCATE kit_cur;

COMMIT TRAN;
DROP TABLE #Kit;

PRINT '';
PRINT 'OK - conta atualizada. Rode 03_verificar_usaimbot.sql para conferir.';