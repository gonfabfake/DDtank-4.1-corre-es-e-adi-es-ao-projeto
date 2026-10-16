# Painel administrativo — DDTank 4.1

**Documentação atualizada em 16/10/2026.** Esta pasta contém um painel em **React 18 + htm**, com PHP no IIS e dados do SQL Server. Este guia substitui instruções antigas sobre ícones exclusivamente SVG ou URLs construídas apenas com `TemplateID`.

> O painel **não** é o jogo. Corrigir a tela administrativa não prova que batalha, disparo, Ruffle ou os serviços de jogo estejam homologados.

## Acesso e permissões

- URL de referência na máquina servidora: `http://127.0.0.1/painel/`.
- `index.php` redireciona para `app.php`, que exige autenticação e entrega a aplicação React.
- `_lib.php` aplica a lista `PAINEL_IP_LIBERADOS`, sessão, regras de autorização e CSRF.
- Por padrão, apenas IPs de loopback estão autorizados. **Abrir o jogo no celular não libera automaticamente o painel administrativo no celular.**
- Requisições de leitura usam `GET api.php?a=...`; gravações exigem `POST api.php?a=acao`, token `_csrf` e perfil de escrita. Todas as alterações sensíveis devem ser auditadas.

## Organização

| Arquivo | Uso |
| --- | --- |
| `index.php`, `app.php` | Entrada e bootstrap: `window.BOOT`, usuário, permissões, configuração inicial de imagens |
| `assets/app.js` | Interface: contas, ficha, itens, imagens, auditoria e fallback SVG |
| `assets/app.css` | Tema administrativo responsivo |
| `assets/vendor/` | React, ReactDOM e htm locais, sem necessidade de CDN para a interface |
| `api.php` | API JSON de contas, inventário, catálogo, imagens e auditoria |
| `_lib.php` | Autenticação, banco, CSRF e controles de acesso |
| `_itens.php` | Normalização do catálogo e lógica administrativa de inventário |
| `_imgconfig.php` | Opções de recursos, segurança de URLs e padrões por categoria |
| `img.php` | Proxy de imagem com cache em `resource/`, validação de tipo e limite de tamanho |
| `tests/test_imagens.php` | Testes de caminhos, `TemplateID` e URLs protegidas |
| `tests/test_img_paths.cjs` | Casos de teste do caminho montado no React |
| `README.md` | Este guia |

## 🖼️ Como as imagens são identificadas

O catálogo consultado em `Db_Tank41.dbo.Shop_Goods` fornece os campos:

| Coluna | Significado | Uso no painel |
| --- | --- | --- |
| `TemplateID` | ID numérico do modelo de item | Identificação; tentativa de caminho alternativa |
| `Name` | Nome exibido do produto | Nome e busca |
| `CategoryID` | Categoria do equipamento/item | Define pasta e formato do arquivo |
| `Pic` | Chave textual do recurso (p. ex., `guidname`) | **Primeira chave tentada para construir o caminho** |
| `Quality` | Qualidade do item | Cor do ícone alternativo |

O SQL identifica o registro; a imagem é buscada na resource. **`Pic` não é necessariamente uma URL completa, e `TemplateID` não é obrigatoriamente o nome da pasta.** A convenção original está em `Web/function.php::loadimage($image, $loaivp, $sex)`. Nessa função, `$image` é um parâmetro textual e `$loaivp` determina a categoria. Alguns equipamentos usam subpastas `m` e `f` conforme o sexo; o painel testa ambos os caminhos enquanto o sexo não estiver disponível no catálogo.

Exemplo fornecido para uma resource:

```text
https://resbr51-myddt-ofc.ddtankswordgames.com/image/unfrightprop/guidname/icon.png
                                                  └── prefixo ─┘ └chave─┘
```

Para um registro **hipotético** com `CategoryID=11`, `Pic=guidname` e `TemplateID=71843`, as tentativas principais são:

```text
/image/unfrightprop/guidname/icon.png   ← Pic (prioridade)
/image/unfrightprop/71843/icon.png      ← alternativa TemplateID
```

Não se deve aplicar o mesmo ícone a todos os produtos só porque uma URL isolada respondeu. Verifique exemplos reais de **armas, roupas, acessórios e itens consumíveis** com seus valores de `Pic`.

### Ordem de resolução no painel

1. Lê `Pic`, `TemplateID`, `CategoryID` da API JSON.
2. Seleciona o modelo de caminho conforme `CategoryID`.
3. Testa o caminho montado com **`Pic`**; para categorias com sexo, testa também `equip/f/`.
4. Se não houver imagem, tenta o **`TemplateID`** como fallback.
5. Normaliza a base para incluir `/image/` quando foi informada apenas a raiz do host.
6. No modo `espelho`, solicita `img.php?p=<caminho-relativo>`; se houver arquivo válido, armazena em `Web/painel/resource/`.
7. Se nenhuma tentativa funcionar, exibe o SVG da categoria. O SVG é fallback visual, não prova que a resource existe.

**Observação:** o modo `custom` pode gerar tentativas adicionais; configurações antigas são lidas de `secrets/painel_img.json`. Se ele contiver padrões incorretos, atualize na aba Imagens e teste novamente.

### Configuração

Em **Imagens**, ajuste a URL base da resource, o modo e o formato de caminho. `espelho` utiliza cache local; os modos diretos tentam o recurso externo. O painel também oferece:

- **Testar URL direta:** examina o endereço completo informado, sem alterar a configuração.
- **Validar e salvar padrão por TemplateID:** exige exemplo que tenha um `TemplateID` numérico real na URL e usa POST + CSRF.
- **Testar candidatos:** consulta alguns caminhos usuais para inspeção. Resultado negativo não significa que todas as imagens estejam ausentes.
- **Cache local e prévia:** mostra quantos arquivos foram guardados e usa o catálogo para exibir exemplos.

A configuração do painel fica em `secrets/painel_img.json` **fora de `Web/`**, no arranjo de pastas deste projeto. Não coloque credenciais nesse arquivo. O cache `resource/` fica dentro da árvore do IIS: confira as permissões e as regras de publicação de arquivos estáticos do seu servidor.

### Consulta de diagnóstico no banco (somente leitura)

Acesse o **seu SQL Server** com um cliente autorizado e execute, em ambiente apropriado:

```sql
SELECT TOP (40)
    TemplateID, Name, CategoryID, Pic, Quality
FROM Db_Tank41.dbo.Shop_Goods
WHERE Pic IS NOT NULL AND LTRIM(RTRIM(Pic)) <> ''
ORDER BY CategoryID, TemplateID;
```

Essa consulta **não modifica dados**. Compare os valores de `Pic` com a pasta gerada para cada categoria e teste uma URL existente. O ChatGPT não tem conexão com a instância de produção do seu SQL Server; conclusões sobre registros específicos precisam do retorno da consulta ou de testes na instalação.

## Proteção das imagens remotas

- O proxy verifica esquemas HTTP(S), resolve host público, bloqueia IPs privados/reservados, impede redirecionamentos e limita o tamanho do download.
- Apenas imagens raster identificáveis (PNG, JPEG, GIF e WebP) entram no cache do `img.php`. SVG gerado pelo painel é local.
- Os controles são uma camada de proteção; ainda é necessário configurar as permissões do IIS, monitorar logs e validar casos reais.
- `img.php` não é um substituto para os recursos do cliente SWF; o cache aqui é do **painel administrativo**.

## Testes e homologação

No GitHub Actions, o workflow `.github/workflows/painel-qualidade.yml` executa análise de sintaxe PHP, `node --check` e casos de regressão de imagens. Isso **não acessa seu banco ou IIS**.

Quando estiver com o ambiente Windows:

1. Abra `http://127.0.0.1/painel/` com conta administrativa permitida.
2. Vá em **Itens** e confira pelo menos um item com `Pic` válido por categoria.
3. Em **Imagens**, teste a URL direta de `guidname` e uma URL real de arma/roupa.
4. Verifique a aba de rede do navegador: a sequência deve tentar a chave `Pic` e, se necessário, o `TemplateID`.
5. Verifique a existência dos arquivos no cache e confirme que o painel permanece funcional em contas e auditoria.
6. Se necessário, faça backup do JSON de configuração e execute os testes de sintaxe antes de reiniciar serviços.

### Problemas conhecidos e limites

- **Sem imagem:** host pode estar indisponível; chave `Pic` pode representar sprite de SWF e não arquivo online; a categoria, sexo ou pasta podem divergir.
- **Cache antigo:** uma imagem baixada anteriormente pode ocultar mudanças da resource; investigue o arquivo específico antes de removê-lo.
- **Falha ao salvar:** confirme permissões em `secrets/` e configuração de CSRF, papel e IP.
- **Compilação do cliente Flash e combate:** estão fora do escopo do painel e **não** foram solucionados por estas alterações.

## Fontes de verdade

- [README principal](../../README.md) — panorama atual da instalação e da jogabilidade.
- [`Web/function.php`](../function.php) — convenções originais dos caminhos de imagens.
- [`IIS_VALIDADO.md`](../../IIS_VALIDADO.md) — configuração do IIS verificada no ambiente registrado.
- [`SEGURANCA-SQL.md`](../../SEGURANCA-SQL.md) — restrições de acesso ao banco.

Este documento descreve a versão do painel da branch de manutenção de outubro de 2026. A integração na branch principal deve ocorrer apenas depois da revisão dos testes e da verificação no servidor Windows.
