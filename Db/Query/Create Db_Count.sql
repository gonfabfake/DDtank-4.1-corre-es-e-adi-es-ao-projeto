/* ============================================================================
   Db_Count bootstrap script  -  DDTank 4.1
   ----------------------------------------------------------------------------
   The repository ships Db_Tank41.BAK, Db_Tank_20200702.BAK and
   Db_Membership_20200702.BAK, but there is NO backup for Db_Count.
   Schema below was reconstructed from the code that consumes it:

     Source Server\Game.Server\Server\Statics\LogMgr.cs   (in-memory DataTable)
     Source Server\Center.Server\Statics\LogMgr.cs       (in-memory DataTable)
     Source Server\Bussiness\ItemRecordBussiness.cs       (SqlBulkCopy mappings)
     stored procedures recovered from Db_Tank41.BAK       (Count_* + f_IP2Int)

   The Log_* tables are written on a timer (every 60s / 300s depending on
   SaveRecordInterval). Without them the servers log an error per batch.
   ========================================================================== */

IF DB_ID('Db_Count') IS NULL
BEGIN
    CREATE DATABASE [Db_Count];
END
GO
USE [Db_Count];
GO

/* ---------------------------------------------------------------- Log_Server */
IF OBJECT_ID('dbo.Log_Server', 'U') IS NULL
CREATE TABLE dbo.Log_Server
(
    ApplicationId INT      NOT NULL,
    SubId         INT      NOT NULL,
    EnterTime     DATETIME NOT NULL,
    Online        INT      NOT NULL,
    Reg           INT      NOT NULL
);
GO

/* ----------------------------------------------------------------- Log_Item */
IF OBJECT_ID('dbo.Log_Item', 'U') IS NULL
CREATE TABLE dbo.Log_Item
(
    ApplicationId  INT           NOT NULL,
    SubId          INT           NOT NULL,
    LineId         INT           NOT NULL,
    EnterTime      DATETIME      NOT NULL,
    UserId         INT           NOT NULL,
    Operation      INT           NOT NULL,
    ItemName       NVARCHAR(MAX) NULL,
    ItemID         INT           NOT NULL,
    AddItem        NVARCHAR(MAX) NULL,
    BeginProperty  NVARCHAR(MAX) NULL,
    EndProperty    NVARCHAR(MAX) NULL,
    Result         INT           NOT NULL
);
GO

/* ---------------------------------------------------------------- Log_Money */
/* NOTE: column names follow ItemRecordBussiness.LogMoneyDb() SqlBulkCopy
   mappings (…OtherPay, GoodId, ShopId, Datas), NOT the DataTable column list
   in LogMgr.Setup(), which still carries the older SpareMoney / Medal /
   GoodsType names. The two disagree upstream; the bulk-copy destination is
   what decides whether the insert succeeds, so it is what is created here. */
IF OBJECT_ID('dbo.Log_Money', 'U') IS NULL
CREATE TABLE dbo.Log_Money
(
    ApplicationId INT           NOT NULL,
    SubId         INT           NOT NULL,
    LineId        INT           NOT NULL,
    MastType      INT           NOT NULL,
    SonType       INT           NOT NULL,
    UserId        INT           NOT NULL,
    EnterTime     DATETIME      NOT NULL,
    Moneys        INT           NOT NULL,
    Gold          INT           NOT NULL,
    GiftToken     INT           NOT NULL,
    Offer         INT           NOT NULL,
    OtherPay      NVARCHAR(MAX) NULL,
    GoodId        NVARCHAR(MAX) NULL,
    ShopId        NVARCHAR(MAX) NULL,
    Datas         NVARCHAR(MAX) NULL
);
GO

/* --------------------------------------------------------------- Log_Fight */
IF OBJECT_ID('dbo.Log_Fight', 'U') IS NULL
CREATE TABLE dbo.Log_Fight
(
    ApplicationId INT           NOT NULL,
    SubId         INT           NOT NULL,
    LineId        INT           NOT NULL,
    RoomId        INT           NOT NULL,
    RoomType      INT           NOT NULL,
    FightType     INT           NOT NULL,
    ChangeTeam    INT           NOT NULL,
    PlayBegin     DATETIME      NOT NULL,
    PlayEnd       DATETIME      NOT NULL,
    UserCount     INT           NOT NULL,
    MapId         INT           NOT NULL,
    TeamA         NVARCHAR(MAX) NULL,
    TeamB         NVARCHAR(MAX) NULL,
    PlayResult    NVARCHAR(MAX) NULL,
    WinTeam       INT           NOT NULL,
    Detail        NVARCHAR(MAX) NULL
);
GO

/* ------------------------------------------------------------ Log_DropItem */
IF OBJECT_ID('dbo.Log_DropItem', 'U') IS NULL
CREATE TABLE dbo.Log_DropItem
(
    ApplicationId INT      NOT NULL,
    SubId         INT      NOT NULL,
    LineId        INT      NOT NULL,
    UserId        INT      NOT NULL,
    ItemId        INT      NOT NULL,
    TemplateID    INT      NOT NULL,
    DropId        INT      NOT NULL,
    DropData      INT      NOT NULL,
    EnterTime     DATETIME NOT NULL
);
GO

/* ============================================================================
   Count_* tables referenced by the stored procedures that live in Db_Tank41.
   Bussiness\CountBussiness is currently inert (SetConfig() is never called),
   but these keep the instance healthy if that code is ever re-enabled.
   ========================================================================== */
IF OBJECT_ID('dbo.Count_Application_Sub', 'U') IS NULL
CREATE TABLE dbo.Count_Application_Sub
( ApplicationId INT IDENTITY(1,1) PRIMARY KEY, SubId INT NOT NULL, AppName NVARCHAR(100) NULL );
GO
IF OBJECT_ID('dbo.Count_Application_Server', 'U') IS NULL
CREATE TABLE dbo.Count_Application_Server
( ApplicationId INT NOT NULL, SubId INT NOT NULL, LineId INT NOT NULL, ServerName NVARCHAR(100) NULL, IP NVARCHAR(50) NULL, State INT NULL );
GO
IF OBJECT_ID('dbo.Count_Register', 'U') IS NULL
CREATE TABLE dbo.Count_Register
( Id INT IDENTITY(1,1) PRIMARY KEY, ApplicationId INT NOT NULL, SubId INT NOT NULL, RegDateTime DATETIME NOT NULL, UserId INT NOT NULL, Sex INT NOT NULL, UserIp NVARCHAR(50) NULL );
GO
IF OBJECT_ID('dbo.Count_Login', 'U') IS NULL
CREATE TABLE dbo.Count_Login
( Id INT IDENTITY(1,1) PRIMARY KEY, ApplicationId INT NOT NULL, SubID INT NOT NULL, ServerName NVARCHAR(100) NULL,
  LoginDateTime DATETIME NOT NULL, IsSex INT NULL, OwnAddress NVARCHAR(100) NULL, OwnNet NVARCHAR(100) NULL,
  OnSite INT NULL, UserID NVARCHAR(100) NULL, IPAddress NVARCHAR(50) NULL, LoginTime DATETIME NULL );
GO
IF OBJECT_ID('dbo.Count_FightInfo', 'U') IS NULL
CREATE TABLE dbo.Count_FightInfo
( Id INT IDENTITY(1,1) PRIMARY KEY, ApplicationId INT NOT NULL, SubId INT NOT NULL, LineId INT NOT NULL,
  PlayBegin DATETIME NOT NULL, PlayEnd DATETIME NULL, UserCount INT NULL, MapId INT NULL,
  PlayPoint BIGINT NULL, PlayGold BIGINT NULL, Users NVARCHAR(MAX) NULL );
GO
IF OBJECT_ID('dbo.Count_Shop', 'U') IS NULL
CREATE TABLE dbo.Count_Shop
( Id INT IDENTITY(1,1) PRIMARY KEY, ApplicationId INT NOT NULL, SubId INT NOT NULL, UserId INT NOT NULL,
  Money INT NULL, Gold INT NULL, EnterTime DATETIME NOT NULL, GoodId INT NULL, GoodsType INT NULL );
GO
IF OBJECT_ID('dbo.Count_SystemPay', 'U') IS NULL
CREATE TABLE dbo.Count_SystemPay
( Id INT IDENTITY(1,1) PRIMARY KEY, ApplicationId INT NOT NULL, SubId INT NOT NULL, PayUserId INT NOT NULL,
  Point INT NULL, Gold INT NULL, MastType INT NULL, SonType INT NULL, PayTime DATETIME NOT NULL );
GO
IF OBJECT_ID('dbo.Count_Money', 'U') IS NULL
CREATE TABLE dbo.Count_Money
( Id INT IDENTITY(1,1) PRIMARY KEY, ApplicationId INT NOT NULL, SubId INT NOT NULL, UserId INT NOT NULL,
  MoneyType INT NULL, Money BIGINT NULL, Gold BIGINT NULL, MoneyDateTime DATETIME NOT NULL );
GO
IF OBJECT_ID('dbo.Count_ChargeRecord', 'U') IS NULL
CREATE TABLE dbo.Count_ChargeRecord
( OrderId NVARCHAR(100) PRIMARY KEY, WanbaOId NVARCHAR(100) NULL, Amount DECIMAL(18,2) NULL,
  Currency NVARCHAR(20) NULL, WanbaResult INT NULL, DDTResult INT NULL,
  DateTimes DATETIME NULL, UserName NVARCHAR(100) NULL, NickName NVARCHAR(100) NULL );
GO

/* ------------------------------------------------------------------ f_IP2Int */
/* scalar function used by procs such as SP_Charge_Record / active-IP maths */
IF OBJECT_ID('dbo.f_IP2Int', 'FN') IS NULL
    EXEC('
        CREATE FUNCTION dbo.f_IP2Int(@ip NVARCHAR(50))
        RETURNS BIGINT
        AS
        BEGIN
            DECLARE @r BIGINT = 0;
            DECLARE @p1 INT, @p2 INT, @p3 INT, @p4 INT;
            SET @p1 = CAST(SUBSTRING(@ip, 1, CHARINDEX(''.'', @ip) - 1) AS INT);
            SET @ip  = SUBSTRING(@ip, CHARINDEX(''.'', @ip) + 1, 255);
            SET @p2 = CAST(SUBSTRING(@ip, 1, CHARINDEX(''.'', @ip) - 1) AS INT);
            SET @ip  = SUBSTRING(@ip, CHARINDEX(''.'', @ip) + 1, 255);
            SET @p3 = CAST(SUBSTRING(@ip, 1, CHARINDEX(''.'', @ip) - 1) AS INT);
            SET @p4 = CAST(SUBSTRING(@ip, CHARINDEX(''.'', @ip) + 1, 255) AS INT);
            SET @r  = @p1 * 16777216 + @p2 * 65536 + @p3 * 256 + @p4;
            RETURN @r;
        END');
GO

PRINT 'Db_Count created successfully.';
GO