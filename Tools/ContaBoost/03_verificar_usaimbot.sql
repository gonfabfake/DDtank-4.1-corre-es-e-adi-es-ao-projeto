/* =====================================================================
   03_verificar_usaimbot.sql
   Confere o resultado: nivel coerente com GP, cupons e inventario.
   Deve retornar 1 linha "TUDO OK" no final.
   ===================================================================== */
SET NOCOUNT ON;

DECLARE @UserID      int = 25;
DECLARE @TargetGrade int = 60;
DECLARE @TargetMoney int = 2000000000;

PRINT '=== 1. NIVEL E CUPONS ===';
SELECT d.UserID, d.UserName, d.NickName,
       d.Grade, d.GP,
       (SELECT li.GP FROM Db_Tank.dbo.LevelInfo li WHERE li.Grade = d.Grade) AS GP_Exigido_Para_Esse_Grade,
       d.Money AS Cupons, d.Gold, d.Offer
FROM Db_Tank41.dbo.Sys_Users_Detail d
WHERE d.UserID = @UserID;

PRINT '';
PRINT '=== 2. EQUIPADO (BagType 0, Place 0..30) ===';
SELECT g.Place,
       CASE g.Place WHEN 0 THEN 'Chapeu' WHEN 1 THEN 'Oculos'  WHEN 2 THEN 'Cabelo'
                    WHEN 3 THEN 'Face'  WHEN 4 THEN 'Roupa'  WHEN 5 THEN 'Olhos'
                    WHEN 6 THEN 'ARMA'  WHEN 7 THEN 'Bracele' WHEN 8 THEN 'Bracele 2'
                    WHEN 9 THEN 'Anel'  WHEN 10 THEN 'Anel 2'  WHEN 11 THEN 'Terno'
                    WHEN 12 THEN 'Colar' WHEN 13 THEN 'Decoracao' WHEN 14 THEN 'Bolha'
                    WHEN 15 THEN 'Auxiliar' ELSE 'slot ' + CAST(g.Place AS varchar(10)) END AS Slot,
       g.TemplateID, s.Name, s.CategoryID,
       s.Attack, s.Defence, s.Agility, s.Luck, s.Quality
FROM Db_Tank41.dbo.Sys_Users_Goods g
LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = g.TemplateID
WHERE g.UserID = @UserID AND g.IsExist = 1 AND g.BagType = 0 AND g.Place >= 0 AND g.Place <= 30
ORDER BY g.Place;

PRINT '';
PRINT '=== 3. MOCHILA E INVENTARIO ===';
SELECT g.BagType, g.Place, g.TemplateID, s.Name, s.CategoryID, g.Count,
       g.StrengthenLevel, g.StrengthenExp, g.ValidDate
FROM Db_Tank41.dbo.Sys_Users_Goods g
LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = g.TemplateID
WHERE g.UserID = @UserID AND g.IsExist = 1 AND g.Place >= 31
ORDER BY g.BagType, g.Place;

PRINT '';
PRINT '=== 4. INTEGRIDADE (esperado: tudo 0) ===';
SELECT
  (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Goods
    WHERE UserID = @UserID AND IsExist = 1 AND Place >= 0
      AND ((BagType = 0 AND Place > 79) OR (BagType = 1 AND Place > 48)))       AS Slots_Fora_Do_Limite,
  (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Goods a
    JOIN Db_Tank41.dbo.Sys_Users_Goods b
      ON a.UserID = b.UserID AND a.Place = b.Place AND a.BagType = b.BagType
     AND a.ItemID <> b.ItemID
    WHERE a.UserID = @UserID AND a.IsExist = 1 AND b.IsExist = 1 AND a.Place >= 0) AS Slots_Duplicados,
  (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Goods g
    LEFT JOIN Db_Tank41.dbo.Shop_Goods s ON s.TemplateID = g.TemplateID
    WHERE g.UserID = @UserID AND g.IsExist = 1 AND s.TemplateID IS NULL)         AS Templates_Invalidos,
  (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Goods
    WHERE UserID = @UserID AND IsExist = 1 AND ValidDate <> 0)                  AS Itens_Com_Expiracao,
  (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Detail
    WHERE UserID = @UserID AND Money < 0)                                       AS Cupons_Negativos;

PRINT '';
PRINT '=== 5. VEREDITO ===';
SELECT CASE WHEN d.Grade = @TargetGrade
             AND d.Money = @TargetMoney
             AND d.GP = (SELECT li.GP FROM Db_Tank.dbo.LevelInfo li WHERE li.Grade = @TargetGrade)
             AND (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Goods
                   WHERE UserID = @UserID AND IsExist = 1 AND BagType = 0 AND Place = 6) = 1
            THEN 'TUDO OK'
            ELSE 'DIVERGENTE' END AS Status,
       d.Grade, d.GP, d.Money,
       (SELECT COUNT(*) FROM Db_Tank41.dbo.Sys_Users_Goods
        WHERE UserID = @UserID AND IsExist = 1 AND Place >= 0) AS Total_Itens
FROM Db_Tank41.dbo.Sys_Users_Detail d
WHERE d.UserID = @UserID;