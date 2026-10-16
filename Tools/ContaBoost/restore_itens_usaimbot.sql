/* Restaura o estado original dos itens da conta (UserID 25).
   Usado para desfazer uma execucao com defeito antes de rodar o script
   corrigido. Os ItemID originais sao reinseridos literalmente.          */
SET NOCOUNT ON;
SET IDENTITY_INSERT Db_Tank41.dbo.Sys_Users_Goods ON;

DELETE FROM Db_Tank41.dbo.Sys_Users_Goods WHERE UserID = 25;

INSERT INTO Db_Tank41.dbo.Sys_Users_Goods
    (ItemID, UserID, BagType, TemplateID, Place, Count, IsJudge, Color, IsExist,
     StrengthenLevel, StrengthenExp, IsBinds, IsUsed, BeginDate, ValidDate,
     RemoveDate, RemoveType, Hole1, Hole2, Hole3, Hole4, Hole5, Hole6, Skin)
VALUES
    (3337, 25, 0, 7008, 6, 1, 1, '', 1, 0, 0, 0, 0, '2008-10-11', 0, '2026-10-16 09:26:22.640', 0, -1,-1,-1,-1,-1,-1, ''),
    (3338, 25, 0, 3158, 2, 1, 1, '', 1, 0, 0, 0, 0, '2008-10-11', 0, '2026-10-16 09:26:22.640', 0, -1,-1,-1,-1,-1,-1, ''),
    (3339, 25, 0, 6103, 5, 1, 1, '', 1, 0, 0, 0, 0, '2008-10-11', 0, '2026-10-16 09:26:22.640', 0, -1,-1,-1,-1,-1,-1, ''),
    (3340, 25, 0, 5160, 4, 1, 1, '', 1, 0, 0, 0, 0, '2008-10-11', 0, '2026-10-16 09:26:22.640', 0, -1,-1,-1,-1,-1,-1, ''),
    (3341, 25, 0, 1142, 0, 1, 1, '', 1, 0, 0, 0, 0, '2008-10-11', 0, '2026-10-16 09:26:22.640', 0, -1,-1,-1,-1,-1,-1, '');

SET IDENTITY_INSERT Db_Tank41.dbo.Sys_Users_Goods OFF;

SELECT ItemID, BagType, TemplateID, Place, Count, IsExist FROM Db_Tank41.dbo.Sys_Users_Goods
WHERE UserID = 25 ORDER BY Place;