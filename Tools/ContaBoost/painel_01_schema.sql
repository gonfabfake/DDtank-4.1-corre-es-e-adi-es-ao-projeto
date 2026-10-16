/* =====================================================================
   painel_01_schema.sql
   Estrutura do painel de administracao + promocao da usaimbot a admin.

   Rodar como sa (ou qualquer login com permissao de DDL).
   Idempotente.
   ===================================================================== */
USE Db_Tank41;
SET NOCOUNT ON;

/* ---------- Quem pode usar o painel ---------- */
IF OBJECT_ID('dbo.Admin_Usuarios', 'U') IS NULL
CREATE TABLE dbo.Admin_Usuarios (
    UserID     int           NOT NULL PRIMARY KEY,   -- Sys_Users_Detail.UserID
    UserName   nvarchar(50)  NOT NULL,
    Papel      nvarchar(20)  NOT NULL DEFAULT 'admin', -- 'admin' | 'leitura'
    Ativo      bit           NOT NULL DEFAULT 1,
    CriadoEm   datetime      NOT NULL DEFAULT GETDATE(),
    UltimoAcesso datetime    NULL,
    CONSTRAINT CK_Admin_Usuarios_Papel CHECK (Papel IN ('admin','leitura'))
);

/* ---------- Trilha de auditoria ---------- */
IF OBJECT_ID('dbo.Admin_Auditoria', 'U') IS NULL
CREATE TABLE dbo.Admin_Auditoria (
    ID        int IDENTITY(1,1) NOT NULL PRIMARY KEY,
    Quando    datetime     NOT NULL DEFAULT GETDATE(),
    AdminID   int          NULL,
    AdminNome nvarchar(50) NULL,
    Acao      nvarchar(40) NOT NULL,
    AlvoUser  int          NULL,
    Detalhe   nvarchar(1000) NULL,
    IP        nvarchar(50) NULL
);

/* ---------- Loginsque会被gravados: tentativas ---------- */
IF OBJECT_ID('dbo.Admin_Tentativas', 'U') IS NULL
CREATE TABLE dbo.Admin_Tentativas (
    ID        int IDENTITY(1,1) NOT NULL PRIMARY KEY,
    Quando    datetime     NOT NULL DEFAULT GETDATE(),
    UserName  nvarchar(50) NULL,
    IP        nvarchar(50) NULL,
    Sucesso   bit          NOT NULL DEFAULT 0
);

CREATE INDEX IX_Admin_Auditoria_Quando ON dbo.Admin_Auditoria(Quando DESC);
CREATE INDEX IX_Admin_Tentativas_User  ON dbo.Admin_Tentativas(UserName, Quando DESC);

/* =====================================================================
   PROMOCAO DA usaimbot A ADMIN
   - Admin_Usuarios: libera o painel web
   - Game_Commands : libera os comandos de chat dentro do jogo
     ($ban$, $kick$, $mute$, $unban$, $bugbot$)
     O jogo le Game_Commands via SP_GetAllCommands em
     PlayerBussiness.LoadCommands() e checa em CommandsMgr.CheckAdmin().
     ATENCAO: CheckAdmin ignora o nome do comando e so testa se o UserID
     esta na tabela -- ou seja, estar em Game_Commands = admin do chat.
   ===================================================================== */
DECLARE @GameID int = 25, @Nome nvarchar(50) = N'usaimbot';

IF NOT EXISTS (SELECT 1 FROM dbo.Admin_Usuarios WHERE UserID = @GameID)
    INSERT INTO dbo.Admin_Usuarios (UserID, UserName, Papel) VALUES (@GameID, @Nome, 'admin');
ELSE
    UPDATE dbo.Admin_Usuarios SET Ativo = 1, UserName = @Nome WHERE UserID = @GameID;

IF NOT EXISTS (SELECT 1 FROM dbo.Game_Commands WHERE UserID = @GameID)
    INSERT INTO dbo.Game_Commands (UserID, Commands) VALUES (@GameID, 'AllCommands');

/* Verificacao */
SELECT 'Admin_Usuarios' AS Tabela, UserID, UserName, Papel, Ativo FROM dbo.Admin_Usuarios;
SELECT 'Game_Commands'  AS Tabela, UserID, Commands        FROM dbo.Game_Commands;