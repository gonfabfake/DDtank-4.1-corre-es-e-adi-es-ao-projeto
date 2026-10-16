/* =====================================================================
   01_backup_usaimbot.sql
   Backup do estado da conta antes de qualquer alteracao.
   Idempotente: cada execucao cria um conjunto novo (suffixo de data).
   ===================================================================== */
SET NOCOUNT ON;

DECLARE @UserID int = 25;
DECLARE @Stamp  varchar(20) = CONVERT(varchar(8), GETDATE(), 112) + '_' + CONVERT(varchar(6), GETDATE(), 114);
DECLARE @sql    nvarchar(max);

IF NOT EXISTS (SELECT 1 FROM Db_Tank41.INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ZZ_Bk_UsersDetail')
BEGIN
    CREATE TABLE Db_Tank41.dbo.ZZ_Bk_UsersDetail (
        BkStamp varchar(20) NOT NULL,
        BkAt    datetime     NOT NULL DEFAULT GETDATE(),
        Payload varbinary(max) NOT NULL
    );
END;

IF NOT EXISTS (SELECT 1 FROM Db_Tank41.INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ZZ_Bk_UsersGoods')
BEGIN
    CREATE TABLE Db_Tank41.dbo.ZZ_Bk_UsersGoods (
        BkStamp varchar(20) NOT NULL,
        BkAt    datetime     NOT NULL DEFAULT GETDATE(),
        Payload varbinary(max) NOT NULL
    );
END;

-- Serializa a linha completa da conta (todas as colunas) em XML e guarda o binario.
SET @sql = N'
INSERT INTO Db_Tank41.dbo.ZZ_Bk_UsersDetail (BkStamp, Payload)
SELECT @s, CAST((SELECT * FROM Db_Tank41.dbo.Sys_Users_Detail WHERE UserID = @u FOR XML PATH(''det''), BINARY BASE64) AS varbinary(max));';
EXEC sp_executesql @sql, N'@s varchar(20), @u int', @Stamp, @UserID;

SET @sql = N'
INSERT INTO Db_Tank41.dbo.ZZ_Bk_UsersGoods (BkStamp, Payload)
SELECT @s, CAST((SELECT * FROM Db_Tank41.dbo.Sys_Users_Goods WHERE UserID = @u FOR XML PATH(''g''), BINARY BASE64) AS varbinary(max));';
EXEC sp_executesql @sql, N'@s varchar(20), @u int', @Stamp, @UserID;

SELECT BkStamp, BkAt, DATALENGTH(Payload) AS Bytes,
       'Sys_Users_Detail' AS Tabela
FROM Db_Tank41.dbo.ZZ_Bk_UsersDetail WHERE BkStamp = @Stamp
UNION ALL
SELECT BkStamp, BkAt, DATALENGTH(Payload), 'Sys_Users_Goods'
FROM Db_Tank41.dbo.ZZ_Bk_UsersGoods WHERE BkStamp = @Stamp;