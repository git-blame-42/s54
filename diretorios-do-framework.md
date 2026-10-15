# Para que serve cada diretório do framework

Resposta direta, pasta por pasta, da raiz para dentro. Conferido no
template (`SI-DABE/tsi34d-framework-template`), no `problem-track` e
no nosso projeto (Eventos do Câmpus, Entrega 4.1). Quando uma pasta
só existe num deles, está dito.

Legenda de "dono":

- FRAMEWORK: do professor. Não edite; leia para explicar.
- EQUIPE: seu código. É aqui que as entregas acontecem.
- INFRA: Docker, nginx, CI. Raramente muda.
- GERADO: criado por ferramenta. Não vai para o git.

## 1. Visão geral

```
project/
  public/      INFRA+EQUIPE  raiz do servidor web; index.php e assets
  config/      EQUIPE        bootstrap, rotas, apelidos de middleware
  core/        FRAMEWORK     o framework: roteador, request, controller
                             base, Active Record, erros, env, debug
  lib/         FRAMEWORK*    utilitarios: Auth, FlashMessage,
                             Paginator, Validations (*pode estender)
  app/         EQUIPE        controllers, middlewares, models, views
  database/    EQUIPE        schema.sql e populate
  tests/       EQUIPE        unit, integration (controller/acesso),
                             acceptance, suporte
  server/      INFRA         nginx.conf
  php/         INFRA         php.ini extra (limites de upload)
  log/         GERADO        logs do nginx
  .github/     INFRA         CI e template de PR
  vendor/      GERADO        dependencias do Composer
  .cache/      GERADO        cache do Composer e do PHPUnit
  wiki/        EQUIPE        (so no nosso projeto) paginas da wiki
```

## 2. public/

Dono: INFRA (estrutura) e EQUIPE (assets).

O nginx aponta `root` para `public/`. É a única pasta visível pela
web; tudo fora dela (código, `.env`, `vendor/`) fica inacessível por
URL. Qualquer caminho que não seja css/js/imagem é reescrito para
`public/index.php`.

- `public/index.php`: Front Controller. Uma linha:
  `require config/bootstrap.php`. Não cresce.
- `public/assets/css/`: `application.css` (geral) e `login.css`
  (tela de login). Bootstrap vem de CDN, não daqui.
- `public/assets/js/`: `application.js`. Hoje só tem o preview de
  avatar (herdado do problem-track). JS do Ajax da 4.4 entra aqui.
- `public/assets/images/defaults/`: `avatar.png`, imagem padrão
  quando o usuário não tem foto (usada pelo `ProfileAvatar`).
- `public/assets/uploads/`: destino dos arquivos enviados
  (`uploads/<tabela>/<id>/arquivo`). Conteúdo ignorado pelo git;
  só o `.gitkeep` é versionado. Precisa de dono `www-data` no Linux
  (`sudo chown -R www-data:www-data public/assets/uploads`).

Não entra: PHP de aplicação, views, configuração.

## 3. config/

Dono: EQUIPE.

- `bootstrap.php`: a sequência de partida, sempre na mesma ordem:
  `session_start()`, autoload do Composer, `ErrorsHandler::init()`,
  `EnvLoader::init()`, `Router::init()`. Usado pelo `index.php` e
  pelo `populate.php`. Só mexa para registrar algo global novo.
- `routes.php`: todas as rotas da aplicação, com nomes e grupos de
  middleware. Arquivo mais editado do projeto. Ordem importa.
- `App.php`: classe `Config\App` com `$middlewareAliases`
  (`'auth' => Authenticate::class`, `'admin' => Admin::class`).
  Middleware novo precisa de uma linha aqui.

Não entra: lógica. Só declaração e partida.

## 4. core/

Dono: FRAMEWORK. Idêntico no template, no problem-track e no nosso
projeto. Mudar aqui conta como "funcionalidade extra do framework"
(bônus) e precisa de motivo forte.

- `core/helpers.php`: funções globais `route()` e `dd()`. Carregado
  pelo autoload (`files` no `composer.json`), por isso existe em
  qualquer lugar sem `use`.

- `core/Router/`: roteamento.
  - `Route.php`: uma rota (método, URI, controller, ação, nome,
    middlewares). Métodos estáticos `Route::get/post/put/delete`
    e `Route::middleware()`. `match()` compara segmento a segmento.
  - `Router.php`: singleton que guarda as rotas, acha a primeira
    que casa, roda middlewares e chama a ação (`dispatch`). Gera URL
    por nome (`getRoutePathByName`). Lança 404 se nada casa.
  - `RouteWrapperMiddleware.php`: implementa `->group()`: anexa o
    middleware a todas as rotas declaradas dentro do callback.

- `core/Http/`: requisição, controller base, contrato de middleware.
  - `Request.php`: embrulha `$_SERVER` e `$_REQUEST`: método (lê
    `_method`), URI, parâmetros, cabeçalhos, `acceptJson()`.
  - `Controllers/Controller.php`: classe-mãe de todo controller:
    `current_user`, `render`, `renderJson`, `redirectTo`,
    `redirectBack`, `$layout`.
  - `Middleware/Middleware.php`: interface com um método,
    `handle(Request $request): void`.

- `core/Database/`: acesso a dados.
  - `Database.php`: conexões PDO a partir do `$_ENV`; `create`,
    `drop`, `migrate` (executa `schema.sql`), `exec`.
  - `ActiveRecord/Model.php`: classe-mãe dos models: atributos
    mágicos, `save`, `update`, `destroy`, `findById`, `all`,
    `where`, `findBy`, `exists`, `paginate`, validação, errors,
    fábricas de relacionamento.
  - `ActiveRecord/BelongsTo.php`, `HasMany.php`,
    `BelongsToMany.php`: os três relacionamentos.

- `core/Errors/ErrorsHandler.php`: liga `ob_start` e transforma
  exceções e avisos do PHP numa página HTML com stack trace e o
  status HTTP certo.

- `core/Exceptions/HTTPException.php`: exceção com status HTTP
  (`new HTTPException('x', 404)`).

- `core/Env/EnvLoader.php`: lê `.env` para `$_ENV`; variável do
  sistema operacional tem prioridade (é assim que o `php_test` usa
  outro banco).

- `core/Debug/Debugger.php`: implementação do `dd()`, com
  `highlight_string` quando a requisição é HTML.

- `core/Constants/`: `Constants::rootPath()` e `databasePath()`
  (caminhos absolutos) e `StringPath` (concatena caminhos). Todo
  `require` de view ou schema passa por aqui.

Não entra: regra de negócio, nada específico do seu domínio.

## 5. lib/

Dono: FRAMEWORK, com permissão de ESTENDER. É onde o professor
colocou utilitários que a aplicação usa e pode aumentar (nova
validação, novo tipo de flash). Namespace `Lib\`.

- `lib/Authentication/Auth.php`: sessão de login: `login`, `user`,
  `check`, `logout`. Único lugar que escreve `$_SESSION['user']`.
  Nosso projeto alterou `login()` para chamar `registerLogin()`.
- `lib/FlashMessage.php`: mensagens de uma exibição na sessão:
  `success`, `danger`, `get`. Tipo novo = método novo.
- `lib/Paginator.php`: paginação: totais, página atual, anterior,
  próxima, `registers()`, `renderPagesNavigation()`.
- `lib/Validations.php`: regras de validação: `notEmpty`,
  `passwordConfirmation`, `uniqueness`. Regra nova entra aqui.
- `lib/StringUtils.php`: `camelToSnakeCase`, `lowerSnakeToCamelCase`,
  `snakeToCamelCase`. Usado pelo Model para ligar
  `$user->reinforced_problems` ao método `reinforcedProblems()`.

Cuidado: o PHPStan do projeto não analisa `lib/`. Código novo aqui
não passa pela análise estática do CI; só pelo PHPCS.

## 6. app/

Dono: EQUIPE. Namespace `App\`. É o MVC da sua aplicação.

- `app/Controllers/`: um controller por recurso, no plural
  (`UsersController`, `EventsController`), com as ações RESTful.
  Subpasta = sub-namespace: `app/Controllers/Admin/` contém
  `App\Controllers\Admin\DashboardController`. Use subpastas para
  áreas (admin, api), não para agrupar por entrega.
  Hoje: `HomeController`, `AuthenticationsController`,
  `DashboardController`, `Admin/DashboardController`.

- `app/Middleware/`: classes que implementam
  `Core\Http\Middleware\Middleware`. Uma por regra de acesso.
  Hoje: `Authenticate` (logado?), `Admin` (é admin?). Cada uma
  precisa de apelido em `config/App.php`.

- `app/Models/`: um model por tabela, no singular (`User` para
  `users`, `Event` para `events`), estendendo
  `Core\Database\ActiveRecord\Model`. Declara `$table`, `$columns`,
  `validates()`, relacionamentos e métodos de domínio (`isAdmin`,
  `authenticate`). Subpastas são permitidas, mas o problem-track não
  usa. Hoje: `User`.

- `app/Services/`: só no problem-track (`ProfileAvatar`). Lógica que
  não pertence a uma tabela: upload de arquivo, envio de e-mail,
  cálculo. Na 4.3 a rubrica cita "`ProfileAvatar` ou equivalente";
  crie esta pasta quando chegar lá. Teste em `tests/Unit/Services/`.

- `app/views/`: templates `.phtml` e `.json.php`.
  - `app/views/layouts/`: `application.phtml` (moldura de todas as
    páginas), parciais `_navbar.phtml` e `_flash_message.phtml`.
    Layout alternativo (`login.phtml`) entra aqui e é escolhido por
    `$layout` no controller.
  - `app/views/<recurso>/`: uma pasta por controller, com um
    arquivo por ação que renderiza: `index.phtml`, `show.phtml`,
    `new.phtml`, `edit.phtml`; `index.json.php` para Ajax. Hoje:
    `home/`, `authentications/`, `dashboard/`, `admin/dashboard/`.
    A subpasta `admin/` espelha o namespace do controller.
  - `app/views/paginator/_pages.phtml`: navegação de páginas,
    incluída por `Paginator::renderPagesNavigation()`. Mude aqui
    para trocar a aparência da paginação em todo o sistema.

Não entra em `app/`: SQL de criação de tabela (vai em `database/`),
configuração, testes.

## 7. database/

Dono: EQUIPE. Namespace `Database\` (PSR-4 em `composer.json`).

- `database/schema.sql`: o banco inteiro, com `DROP TABLE IF EXISTS`
  + `CREATE TABLE` de cada tabela. Não há migrations: toda mudança de
  estrutura é editar aqui e rodar `./run db:reset` (apaga dados).
  Executado também por `Database::migrate()` nos testes e no
  populate. Ordem: tabelas-pai antes das filhas (ou
  `SET foreign_key_checks = 0` no início, como está).
- `database/Populate/`: dados de demonstração.
  - `populate.php`: script de linha de comando
    (`./run db:populate`): inclui o bootstrap, chama
    `Database::migrate()` e cria registros pelos models.
  - No problem-track há classes por tabela (`UsersPopulate`,
    `ProblemsPopulate`, namespace `Database\Populate`) chamadas pelo
    `populate.php`. Adote isso quando o populate crescer.

O PHPCS ignora `database/`. O PHPStan não o inclui.

## 8. tests/

Dono: EQUIPE. Namespace `Tests\`. A estrutura espelha o que é
testado e a camada do teste.

- `tests/TestCase.php`: base dos testes que usam banco: `setUp`
  cria e migra `eventos_test`, `tearDown` apaga. Estenda sempre que o
  teste tocar em model.

- `tests/Unit/`: testes sem HTTP.
  - `Unit/Core/`: testes do próprio framework (`Http/RequestTest`,
    `Router/RouterTest`, `RouteTest`, `RouteWrapperMiddlewareTest`,
    `MockController`, `header_mock.php`). Vieram com o template;
    você não precisa mexer.
  - `Unit/Lib/`: testes dos utilitários (`Authentication/AuthTest`,
    `FlashMessageTest`, `StringUtilsTest`, `Validations/*`).
    Validação nova ganha teste em `Unit/Lib/Validations/`.
  - `Unit/Models/<Recurso>/`: um arquivo por model
    (`Users/UserTest.php`). A rubrica cobra "métodos dos models".
  - `Unit/Services/`: (problem-track) testes dos services.

- `tests/Integration/`: testes que precisam de mais de uma peça.
  - `Integration/Controllers/`: `ControllerTestCase` (base que troca
    `redirectTo` por `echo 'Location: ...'`) e um teste por
    controller (`AuthenticationsControllerTest`,
    `HomeControllerTest`). Chama a ação direto, sem nginx.
  - `Integration/Access/`: testes de acesso por HTTP real (Guzzle em
    `http://web:8080`): rota protegida redireciona, pública abre
    (`DashboardAccessTest`, `HomeAccessTest`). A rubrica chama de
    "testes de acesso (rotas)".

- `tests/Acceptance/`: Codeception com navegador real.
  - `Acceptance/BaseAcceptanceCest.php`: `_before` migra o banco,
    `_after` apaga.
  - `Acceptance/<recurso>/XCest.php`: um Cest por área, um método
    por cenário da rubrica (`authentications/LoginCest`,
    `dashboard/DashboardCest`, `admin/AdminDashboardCest`,
    `home/HomeIndexCest`).
  - `Acceptance/_output/`: GERADO. HTML e screenshots de falhas.
    Ignorado pelo git.

- `tests/Support/`: infraestrutura do Codeception.
  - `AcceptanceTester.php`: o ator `$page`. Não edite; estenda via
    helpers.
  - `Helper/LoginHelper.php`: ações customizadas (`login`,
    `logout`). Helper novo entra aqui e é registrado em
    `tests/acceptance.suite.yml`.
  - `_generated/`: GERADO por `./run codecept build`
    (`AcceptanceTesterActions.php`, com todas as ações e docblocks).
    Ignorado pelo git. Serve de documentação offline.
  - `Data/`: fixtures do Codeception. Vazia; só `.gitkeep`.

- `tests/files/`: só no template e no problem-track
  (`avatar_test.jpg`), arquivo usado no teste de upload. Nosso
  projeto não o copiou; volta na 4.3.

- `tests/acceptance.suite.yml`: configuração da suíte de aceitação
  (WebDriver em `selenium:4444`, URL `http://web:8080`, headless,
  helper de login).

## 9. server/, php/, log/

Dono: INFRA.

- `server/nginx.conf`: dois `server`: porta 80 encaminha para o
  container `php`; porta 8080 para o `php_test`. Ambos com `root`
  em `/var/www/public` e `client_max_body_size 40M`. Montado no
  container `web` pelo `docker-compose.yml`.
- `php/uploads.ini`: `upload_max_filesize` e `post_max_size` (40M),
  montado em `/usr/local/etc/php/conf.d/` dos containers PHP. Limite
  de upload da 4.3 (ex.: 2 MB) é validado no código, não aqui.
- `log/nginx/`: `access.log` e `error.log` do nginx (GERADO,
  `*.log` ignorado; `.gitkeep` versionado). Primeiro lugar para
  olhar quando a página dá 502/500 sem stack trace.

## 10. .github/

Dono: INFRA.

- `.github/workflows/ci.yml`: três jobs a cada push: `tests`
  (PHPUnit `tests/Unit` e `tests/Integration`), `acceptance_tests`
  (`./run test:browser`), `code_quality` (checador PSR-4, PHPCS,
  PHPStan). PR verde = os três verdes.
- `.github/pull_request_template.md`: corpo padrão da PR (issue,
  descrição, tipo de mudança, checklist). A rubrica avalia título e
  descrição da PR.

## 11. vendor/ e .cache/

Dono: GERADO. Nunca versionar; nunca editar.

- `vendor/`: dependências instaladas por `./run composer install`
  a partir de `composer.lock`. Só ferramentas de desenvolvimento:
  PHPUnit, Codeception (+ módulos WebDriver, PhpBrowser, Asserts),
  PHPCS, PHPStan e o que elas puxam (Guzzle, Symfony, PsySH).
  `vendor/bin/` tem os executáveis (`phpunit`, `codecept`, `phpcs`,
  `phpcbf`, `phpstan`, `psysh`). `vendor/autoload.php` é o autoload
  PSR-4 carregado pelo bootstrap.
- `.cache/composer/`: cache de pacotes do Composer (o `run` aponta
  `COMPOSER_CACHE_DIR` para cá). `.cache/phpunit/`: cache de
  resultados do PHPUnit (`cacheDirectory` no `phpunit.xml`).

## 12. wiki/ (só no nosso projeto)

Dono: EQUIPE. Cópia das páginas da wiki do GitHub (`Home.md`,
`_Sidebar.md`, `Mantenedores.md`, `Descrição-do-Projeto.md`)
versionada junto com o código. Não faz parte do framework; a wiki
oficial é o repositório `<projeto>.wiki.git`.

## 13. Arquivos da raiz

- `run`: script bash com as tarefas (`up`, `down`, `ps`, `composer`,
  `test`, `codecept`, `test:browser`, `phpcs`, `phpcbf`, `phpstan`,
  `db:console`, `db:reset`, `db:populate`, `php:console`,
  `nginx:*`). Faz `source .env` no início: o `.env` precisa existir.
  Tarefa nova = função bash nova.
- `composer.json`: autoload PSR-4 (`App\`, `Core\`, `Lib\`,
  `Config\`, `Database\`, `Tests\`), `files` (`core/helpers.php`) e
  `require-dev`. `composer.lock`: versões exatas; versionado.
- `docker-compose.yml`: cinco serviços (`web`, `php`, `db`,
  `selenium`, `php_test`), rede, volume `dbdata`. O `php_test`
  recebe `DB_DATABASE=eventos_test` por variável de ambiente.
- `Dockerfile`: `php:8.3.4-fpm` + `pdo_mysql` + `zip`. Usado por
  `php` e `php_test`.
- `phpunit.xml`: variáveis do banco de teste e cache. Aponta para o
  schema do PHPUnit 10, mas o instalado é o 11; funciona.
- `codeception.yml`: caminhos (`tests/`, `tests/Support`,
  `tests/Acceptance/_output`), namespace `Tests`, sufixo `Tester`.
- `phpcs.xml`: PSR-12, linha até 130, snake_case liberado em
  `*Test.php` e `*Cest.php`, ignora `database/`, `vendor/`,
  `_generated/`.
- `phpstan.neon`: nível 6 em `app`, `core`, `tests`; exclui views,
  `_output`, `_generated`, `Data`; lista de erros ignorados.
- `.env.example`: modelo versionado. `.env`: cópia local com as
  variáveis reais; ignorado pelo git.
- `.editorconfig`: UTF-8, LF, 2 espaços por padrão. Para PHP vale o
  PHPCS (4 espaços, PSR-12); configure o editor para 4 em `.php`.
- `.gitignore`: ver seção 14.
- `README.md`: descrição, como executar, como contribuir, licença
  (itens cobrados no Enunciado).

## 14. O que o git ignora

`vendor/`, `.cache/*`, `.env`, `*.log`, `*.temp`,
`public/assets/uploads/*` (menos `.gitkeep`),
`tests/Acceptance/_output/*`, `tests/Support/_generated/*`,
`.vscode/`, `.idea/`, `.DS_Store`, `html/`.

Regra: se uma ferramenta gera, não versione. Se precisa da pasta
vazia no clone, versione um `.gitkeep` dentro dela.

## 15. Índice reverso: onde vai um X novo

- Rota: `config/routes.php`.
- Apelido de middleware: `config/App.php`.
- Controller: `app/Controllers/` (ou `app/Controllers/Admin/`).
- Middleware: `app/Middleware/`.
- Model: `app/Models/`.
- Service (upload, e-mail): `app/Services/` (crie).
- View de ação: `app/views/<recurso>/<acao>.phtml`.
- View JSON: `app/views/<recurso>/<acao>.json.php`.
- Layout ou parcial: `app/views/layouts/`.
- Tabela ou coluna: `database/schema.sql`.
- Dados de demonstração: `database/Populate/populate.php`.
- Regra de validação: `lib/Validations.php`.
- Tipo de flash: `lib/FlashMessage.php`.
- Função global: `core/helpers.php` (framework; evite).
- CSS, JS, imagem estática: `public/assets/`.
- Arquivo enviado pelo usuário: `public/assets/uploads/` (runtime).
- Teste de model: `tests/Unit/Models/<Recurso>/`.
- Teste de validação: `tests/Unit/Lib/Validations/`.
- Teste de service: `tests/Unit/Services/`.
- Teste de controller: `tests/Integration/Controllers/`.
- Teste de acesso: `tests/Integration/Access/`.
- Teste de aceitação: `tests/Acceptance/<recurso>/`.
- Helper do Codeception: `tests/Support/Helper/` + registro em
  `tests/acceptance.suite.yml`.
- Arquivo de apoio a teste (imagem de upload): `tests/files/`.
- Tarefa de terminal: `run`.
- Limite de upload do PHP: `php/uploads.ini`.
- Porta, rota do nginx: `server/nginx.conf`.
- Passo do CI: `.github/workflows/ci.yml`.

## 16. Namespace e pasta (PSR-4)

- `App\`       -> `app/`        (`App\Models\User` em `app/Models/User.php`)
- `Core\`      -> `core/`
- `Lib\`       -> `lib/`
- `Config\`    -> `config/`     (`Config\App`)
- `Database\`  -> `database/`   (`Database\Populate\UsersPopulate`)
- `Tests\`     -> `tests/`      (`Tests\Unit\Models\Users\UserTest`)

Cada segmento do namespace depois do prefixo é uma pasta, com a
mesma caixa de letras. Views não têm namespace: são incluídas por
caminho (`app/views/...`), por isso a pasta das views é minúscula.
O CI roda `composer dump-autoload --strict-psr` e reprova qualquer
classe cujo caminho não bata com o namespace.
