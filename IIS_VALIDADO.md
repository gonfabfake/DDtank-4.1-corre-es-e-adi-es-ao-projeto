# IIS validado em 16/10/2026

Este registro substitui as conclusoes sobre FastCGI de CORRECOES_WEB.md.

- Portal: http://127.0.0.1/ servido diretamente pelo IIS/FastCGI.
- PHP: C:\php\php-cgi.exe, pool DDTankWebPool.
- /request: pool DDTankPool, 32 bits.
- Binding local: 127.0.0.1:80.
- Servidor PHP 8080 encerrado; inicializacao removida de Start-Server.ps1.
- Pagina index.htm de redirecionamento removida; documento padrao index.php.

O schema real em Windows\System32\inetsrv\config\schema\IIS_schema.xml
exige fastCgi/application e environmentVariables/environmentVariable.
Nao aceita os elementos add ou applicationSettings usados anteriormente.
Registro realizado pela API Microsoft.Web.Administration no applicationHost.config,
com PHPRC=C:\php e PHP_FCGI_MAX_REQUESTS=10000.
O handler e associado pelo caminho do executavel, nao pelo nome do handler.
PHP-CGI e um processo externo: a alegacao anterior de incompatibilidade obrigatoria
entre a arquitetura do worker e do executavel CGI nao foi demonstrada.

Durante a verificacao nao existia site cadastrado no IIS; Default Web Site foi
recriado com raiz Web e aplicativo /request apontando para Request.
Backup IIS: DDTank-before-fastcgi-20261016083546.
Backup web.config: web.config.20261016083546.bak.

Validacao HTTP apos desligar 8080:
- GET /: 200, 25308 bytes, portal PHP real.
- GET /index.php: 200, 25308 bytes.
- GET /request/ServerList.ashx: 200, 96 bytes.
- POST / com senha incorreta: 200, alerta explicito de login/senha incorretos.
- Nenhum listener TCP na porta 8080.

Ainda nao validado: autenticacao com senha correta, entrada no cliente Flash e
batalha. HTTP 200 de ServerList nao demonstra disponibilidade de servidor jogavel.
Nao executar as antigas receitas de recuperacao que apagam pools/arquivos temporarios.
