/* ============================================================================
   Tabelas do front-end PHP ausentes nos backups
   ----------------------------------------------------------------------------
   Nenhum dos tres .BAK do repositorio contem estas tabelas e elas nao foram
   publicadas em lugar nenhum. Sem elas as telas de recarga, CPF e PicPay do
   portal caem com "Invalid object name".

   Os schemas foram deduzidos do proprio codigo PHP, que usa INSERT posicional
   (sem lista de colunas), portanto a ORDEM das colunas e obrigatoria:

     Web\Models\Pacote.php          -> Value, Coupons, Link, ID
     Web\Models\PersonalData.php   -> UserID, CPF, FirstName, LastName, Phone
     Web\Models\PicPayPayment.php   -> AuthID, referenceId, status,
                                      UserID, Value, expiresAt, PacID

   A conexao padrao do front-end e Db_Membership (Web\global.php).
   ========================================================================== */

USE [Db_Membership];
GO

/* ------------------------------------------------------------- Pacotes */
/* Web\Models\Pacote.php: select * from Pacotes where ID = ?            */
/* Web\function.php:328 PegarPacotes(): select * from Pacotes           */
IF OBJECT_ID('dbo.Pacotes', 'U') IS NULL
CREATE TABLE dbo.Pacotes
(
    ID       INT            NOT NULL,
    Value    DECIMAL(18, 2) NOT NULL,   -- lido como (float) em Pacote::Get()
    Coupons  NVARCHAR(255)  NULL,
    Link     NVARCHAR(1000) NULL,
    CONSTRAINT PK_Pacotes PRIMARY KEY (ID)
);
GO

/* -------------------------------------------------------- PersonalData */
/* Web\Models\PersonalData.php:185
   insert into PersonalData values(UserID, CPF, FirstName, LastName, Phone) */
IF OBJECT_ID('dbo.PersonalData', 'U') IS NULL
CREATE TABLE dbo.PersonalData
(
    UserID    INT          NOT NULL,
    CPF       NVARCHAR(20)  NULL,
    FirstName NVARCHAR(100) NULL,
    LastName  NVARCHAR(100) NULL,
    Phone     NVARCHAR(30)  NULL,
    CONSTRAINT PK_PersonalData PRIMARY KEY (UserID)
);
GO

/* ------------------------------------------------------ PicPayPayment */
/* Web\Models\PicPayPayment.php:65
   insert into PicPayPayment values(AuthId, ReferenceId, Status, UserID,
                                    Value, Convert(varchar(33), ExpiresAt, 126),
                                    PackID)                                   */
IF OBJECT_ID('dbo.PicPayPayment', 'U') IS NULL
CREATE TABLE dbo.PicPayPayment
(
    AuthID      NVARCHAR(255)    NULL,
    referenceId NVARCHAR(100)    NOT NULL,   -- chave de busca: where referenceId = ?
    status      NVARCHAR(50)     NULL,
    UserID      INT              NOT NULL,
    Value       DECIMAL(18, 2)   NOT NULL,
    expiresAt   VARCHAR(33)      NULL,       -- o PHP grava com CONVERT(...,126)
    PacID       INT              NULL,
    CONSTRAINT PK_PicPayPayment PRIMARY KEY (referenceId)
);
GO

/* Indices de apoio para as consultas frequentes do painel */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PicPayPayment_UserID' AND object_id = OBJECT_ID('dbo.PicPayPayment'))
    CREATE INDEX IX_PicPayPayment_UserID ON dbo.PicPayPayment (UserID);
GO

PRINT 'Tabelas do front-end criadas em Db_Membership.';
GO