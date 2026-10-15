# Framework da disciplina: guia completo

Como o framework MVC do professor funciona por dentro, como ele espera
que uma aplicação seja escrita e como alterar uma feature sem quebrar
o resto. Leia antes o `framework-resumo.md`: ele define os termos.

Fonte: https://github.com/SI-DABE/problem-track (aplicação de
referência; o `core/` dela é o framework). Os exemplos vêm do nosso
projeto (Eventos do Câmpus, Entrega 4.1) e do problem-track (CRUD,
upload, NxN, Ajax). Tudo foi conferido contra o código.

Por que importa: no FVP cada membro, sozinho, recebe uma alteração
para fazer na hora. A meta é responder "quero mudar X, então mexo em
Y" sem precisar abrir o código antes.

Sumário

 1. O que o framework é
 2. Mapa de pastas
 3. Fluxo de uma requisição
 4. As peças, uma a uma
 5. Testes: as quatro camadas
 6. Como construir uma feature
 7. Como alterar uma feature
 8. Erros comuns
 9. Armadilhas verificadas no código
10. Padrões de projeto, com livro
11. Template, problem-track e nosso projeto

## 1. O que o framework é

Mini-framework MVC em PHP 8 puro. O `composer.json` só tem ferramentas
de desenvolvimento (PHPUnit, Codeception, PHPCS, PHPStan). Nenhuma
dependência em produção. Imita Rails e Laravel em escala reduzida.

- Front Controller: `public/index.php` recebe toda requisição; o
  nginx reescreve tudo para ele.
- Roteamento: `config/routes.php` declara
  `Route::get('/x', [Controller::class, 'acao'])`.
- Middleware: classes em `app/Middleware/`, aplicadas com
  `Route::middleware('auth')->group(...)`.
- Controller: classe em `app/Controllers/`, um método público por
  ação.
- View: arquivos `.phtml` em `app/views/`, injetados num layout.
- Model: Active Record. Uma classe por tabela, estende
  `Core\Database\ActiveRecord\Model`.
- Banco: MySQL via PDO. Sem migrations: `database/schema.sql` é a
  verdade absoluta.
- Sessão: `$_SESSION` do PHP, encapsulada em
  `Lib\Authentication\Auth`.
- Ambiente: Docker Compose com `web` (nginx), `php`, `db` (MySQL),
  `php_test` e `selenium`.

Não existe e você não deve esperar: injeção de dependência, query
builder, migrations, template engine, CSRF, validação declarativa.

## 2. Mapa de pastas

```
project/
  public/index.php         unico ponto de entrada (Front Controller)
  config/
    bootstrap.php          sessao, autoload, erros, .env, Router::init
    routes.php             SUAS rotas
    App.php                apelidos de middleware ('auth' => classe)
  core/                    O FRAMEWORK. Nao editar; ler para explicar.
    Router/                Route, Router (singleton),
                           RouteWrapperMiddleware
    Http/                  Request, Controller base, interface
                           Middleware
    Database/              Database (PDO), ActiveRecord/ (Model,
                           BelongsTo, HasMany, BelongsToMany)
    Errors/ Exceptions/ Env/ Debug/ Constants/
    helpers.php            funcoes globais: route(), dd()
  lib/                     Utilitarios. Pode ESTENDER.
    Authentication/Auth.php  FlashMessage.php  Paginator.php
    Validations.php  StringUtils.php
  app/                     SEU CODIGO
    Controllers/           um arquivo por recurso; subpasta Admin/
    Middleware/            Authenticate, Admin
    Models/                User, ...
    Services/              (problem-track) logica fora de model,
                           ex.: upload de avatar
    views/
      layouts/             application.phtml, _navbar.phtml,
                           _flash_message.phtml
      <recurso>/           index, new, show, edit (.phtml),
                           index.json.php
      paginator/_pages.phtml
  database/
    schema.sql             DDL completo (DROP + CREATE)
    Populate/populate.php  dados iniciais (./run db:populate)
  tests/
    Unit/                  models, lib, core
    Integration/Controllers/  acoes chamadas direto, sem HTTP
    Integration/Access/    HTTP real via Guzzle (rotas protegidas)
    Acceptance/            Codeception + Selenium (navegador real)
  run                      atalhos: up, test, test:browser, phpcs,
                           phpstan, db:reset, db:populate
  docker-compose.yml  Dockerfile  server/nginx.conf  .env
  .github/workflows/ci.yml  o que precisa passar na PR
```

Regra de ouro: `core/` é do professor, `app/` é seu, `lib/` é
compartilhado. Mudar `core/` conta como "funcionalidade extra do
framework" (bônus), mas só com motivo forte.

Autoload PSR-4 (`composer.json`): `App\` em `app/`, `Core\` em
`core/`, `Lib\` em `lib/`, `Config\` em `config/`, `Database\` em
`database/`, `Tests\` em `tests/`. A classe
`App\Controllers\Admin\DashboardController` tem que estar em
`app/Controllers/Admin/DashboardController.php`. O CI roda
`composer dump-autoload --strict-psr` e falha se não bater.

## 3. Fluxo de uma requisição

```
navegador: GET /dashboard
  |
nginx (server/nginx.conf): qualquer caminho -> public/index.php
  |                        so css/js/png/jpg saem direto de public/
public/index.php: require config/bootstrap.php
  |
config/bootstrap.php, nesta ordem:
  session_start()
  vendor/autoload.php          (PSR-4 + core/helpers.php)
  ErrorsHandler::init()        (ob_start + handlers de erro)
  EnvLoader::init()            (.env -> $_ENV; SO tem prioridade)
  Router::init()               (se ha REQUEST_METHOD: carrega
  |                             config/routes.php e despacha)
config/routes.php: cada Route::get(...) cria um Route no Router
  |
Router::dispatch(): cria Request; percorre as rotas na ordem;
  |                 primeira que casa vence
  |-- nenhuma casa -> HTTPException 404 -> pagina de erro
  |
Route::runMiddlewares($request): ex. Authenticate -> Auth::check()
  |-- barrado -> FlashMessage + header('Location') + exit
  |
new Controller(): construtor carrega current_user = Auth::user()
  |
$controller->acao($request)
  |-- Model (Active Record) -> SELECT/INSERT/UPDATE via PDO
  |
termina com UM destes:
  render(view, dados)       -> layout + view .phtml -> HTML
  renderJson(view, dados)   -> view .json.php -> JSON
  redirectTo(route('x'))    -> header Location + exit
```

Detalhes que valem explicar no vídeo:

1. `Router::init()` só despacha se `$_SERVER['REQUEST_METHOD']`
   existe. Na CLI (populate, testes) as rotas não são carregadas
   automaticamente.
2. `Router` é singleton (`getInstance()`); `reset()` existe para os
   testes recriarem o estado.
3. Casar rota = mesmo método HTTP e mesma quantidade de segmentos;
   `{id}` aceita qualquer valor e vira parâmetro do Request. A query
   string é ignorada no casamento.
4. Um middleware que barra faz `exit`; o controller nunca roda.
5. `render` faz `extract($data)`, monta o caminho
   `app/views/<view>.phtml` e inclui o layout, que inclui navbar,
   flash e a view. Dentro da view, `$this` é o controller.

Sequência do login no nosso projeto:

1. `POST /login` com `user[email]` e `user[password]`.
2. `AuthenticationsController::authenticate` lê
   `$request->getParam('user')`.
3. `User::findByEmail($email)` devolve `User` ou `null`.
4. `$user->authenticate($password)` faz `password_verify`.
5. Válido: `Auth::login($user)` grava `$_SESSION['user']['id']` e
   `last_login_at`; flash de sucesso; 302 para `dashboard.index`.
6. Inválido: flash de perigo; 302 para `users.login`.
7. `GET /dashboard`: `Authenticate::handle` chama `Auth::check()`;
   passa; `DashboardController::index` renderiza o layout com a flash.

## 4. As peças, uma a uma

### 4.1 Rotas (config/routes.php, core/Router/*)

Nosso projeto:

```php
Route::get('/', [HomeController::class, 'index'])->name('root');

Route::get('/login', [AuthenticationsController::class, 'new'])
    ->name('users.login');
Route::post('/login', [AuthenticationsController::class, 'authenticate'])
    ->name('users.authenticate');
Route::get('/logout', [AuthenticationsController::class, 'destroy'])
    ->name('users.logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard.index');

    Route::middleware('admin')->group(function () {
        Route::get('/admin', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard.index');
    });
});
```

CRUD RESTful do problem-track (padrão cobrado na 4.2):

```php
Route::get('/problems/new', [ProblemsController::class, 'new'])
    ->name('problems.new');
Route::post('/problems', [ProblemsController::class, 'create'])
    ->name('problems.create');
Route::get('/problems', [ProblemsController::class, 'index'])
    ->name('problems.index');
Route::get('/problems/page/{page}', [ProblemsController::class, 'index'])
    ->name('problems.paginate');
Route::get('/problems/{id}', [ProblemsController::class, 'show'])
    ->name('problems.show');
Route::get('/problems/{id}/edit', [ProblemsController::class, 'edit'])
    ->name('problems.edit');
Route::put('/problems/{id}', [ProblemsController::class, 'update'])
    ->name('problems.update');
Route::delete('/problems/{id}', [ProblemsController::class, 'destroy'])
    ->name('problems.destroy');
```

Regras:

- Ações RESTful: `index`, `show`, `new`, `create`, `edit`, `update`,
  `destroy`. Paginação em `GET /x/page/{page}` com nome `x.paginate`
  (o Paginator assume esse nome por padrão).
- Nome de rota `recurso.acao`. Gere URLs com
  `route('problems.show', ['id' => 3])`. Parâmetro que não existe na
  URI vira query string: `route('x', ['page' => 2])` dá `/x?page=2`.
- Ordem importa: `/problems/new` vem antes de `/problems/{id}`, senão
  `new` é capturado como `id`.
- PUT e DELETE: o formulário envia `method="POST"` com
  `<input type="hidden" name="_method" value="DELETE">`. O Request lê
  `_method` de `$_REQUEST`.
- Parâmetro de URI só aceita `{letras_minusculas}` (regex
  `^{[a-z,_]+}$`).
- Grupos: `RouteWrapperMiddleware::group` conta quantas rotas existiam
  antes do callback, roda o callback e anexa o middleware às rotas
  novas. Em grupos aninhados o interno termina primeiro, então
  `/admin` recebe `[Admin, Authenticate]` e `Admin` roda ANTES de
  `Authenticate`. Por isso o nosso `Admin` só age quando há usuário
  logado; o "não logado" é tratado pelo `Authenticate`.

### 4.2 Request (core/Http/Request.php)

- `getParam('user')`: array `['email' => ..., 'password' => ...]`
  quando o form usa `name="user[email]"`.
- `getParam('page', 1)`: com valor padrão.
- `getParams()`: tudo (`$_REQUEST` + parâmetros da URI).
- `getMethod()`: já considera `_method`.
- `getUri()`: URI completa, com query string.
- `getHeaders()`: `getallheaders()` quando existe.
- `acceptJson()`: `true` se `Accept` é exatamente `application/json`.
- `addParams([...])`: usado pelo Router (parâmetros da URI) e pelos
  testes de controller.

Regra do professor (feedback 2025-2): nunca `$_POST['x'] ?? ''` no
controller. Sempre `getParam` / `getParams`.

### 4.3 Middleware (app/Middleware/*, config/App.php)

Interface única: `handle(Request $request): void`. Para barrar,
redirecione e dê `exit`. Para liberar, não faça nada. Para existir,
precisa de apelido em `Config\App::$middlewareAliases`:

```php
public static array $middlewareAliases = [
    'auth'  => \App\Middleware\Authenticate::class,
    'admin' => \App\Middleware\Admin::class,
];
```

Os dois do nosso projeto:

```php
class Authenticate implements Middleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            FlashMessage::danger('Você deve estar logado...');
            $this->redirectTo(route('users.login'));
        }
    }

    private function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}

class Admin implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();
        if ($user !== null && !$user->isAdmin()) {
            FlashMessage::danger('Acesso restrito a administradores');
            $this->redirectTo(route('dashboard.index'));
        }
    }
    // mesmo redirectTo() privado, duplicado
}
```

O professor apontou essa duplicação na avaliação da 4.1 ("avaliem
como centralizar"). Solução no `fvp-possiveis-modificacoes.md`, item 1.

Autorização mora aqui, nunca dentro da ação.

### 4.4 Controller (core/Http/Controllers/Controller.php)

O que você herda ao estender `Core\Http\Controllers\Controller`:

- `$this->current_user` / `currentUser()`: `User` logado ou `null`.
  Na view: `$this->currentUser()`.
- `protected string $layout = 'application'`: troque para `'login'`
  se criar `app/views/layouts/login.phtml` (o problem-track faz isso
  no `AuthenticationsController`).
- `render('problems/index', compact('problems', 'title'))`: HTML.
  Cada chave vira variável na view. O layout exige `$title`.
- `renderJson('problems/index', $data)`: inclui
  `app/views/problems/index.json.php`, que preenche `$json`; responde
  `json_encode($json)` com `Content-Type: application/json`.
- `redirectTo(route('x'))`: `header('Location')` + `exit`.
- `redirectBack()`: volta para `HTTP_REFERER` (ou `/`).

Assinatura: `public function index(Request $request): void`. Se a
ação não usa o request, pode omitir o parâmetro; o Router passa mesmo
assim e o PHP ignora.

Padrão de `create` (problem-track):

```php
public function create(Request $request): void
{
    $params = $request->getParams();
    $problem = $this->current_user->problems()->new($params['problem']);

    if ($problem->save()) {
        FlashMessage::success('Problema registrado com sucesso!');
        $this->redirectTo(route('problems.index'));
    } else {
        FlashMessage::danger('Existem dados incorretos!');
        $title = 'Novo Problema';
        $this->render('problems/new', compact('problem', 'title'));
    }
}
```

Sucesso: PRG (redireciona). Erro: re-render com o mesmo objeto, para
o form voltar preenchido e com `$problem->errors('title')`.

### 4.5 Views (app/views/**)

- Caminho: `app/views/<pasta>/<arquivo>.phtml`. Pasta = recurso no
  plural, arquivo = ação.
- Layout `application.phtml`: `<html>`, Bootstrap via CDN, navbar,
  `_flash_message`, `<h1><?= $title ?></h1>`, `require $view`.
- Parciais começam com `_`. Incluídas com `require '_navbar.phtml'`
  (relativo ao layout).
- Dentro da view: variáveis do `compact`, `$this` (controller),
  `route()`, `\Lib\FlashMessage::get()`.
- View JSON: `index.json.php` é PHP puro que monta `$json`. Nada de
  `echo`.

Formulário com validação (problem-track, `problems/new.phtml`):

```php
<form action="<?= route('problems.create') ?>" method="POST"
      class="<?= $problem->hasErrors() ? 'was-validated' : '' ?>"
      novalidate>
  <input type="text" name="problem[title]" class="form-control"
         value="<?= $problem->title ?>" required>
  <div class="invalid-feedback">
    <?= $problem->errors('title') ?? '' ?>
  </div>
  <button type="submit" class="btn btn-primary">Registrar</button>
</form>
```

`novalidate` desliga a validação do navegador: a rubrica quer ver a
validação do servidor.

### 4.6 Model e Active Record (core/Database/ActiveRecord/Model.php)

Declaração mínima:

```php
class Problem extends Model
{
    protected static string $table = 'problems';
    protected static array $columns = ['title', 'user_id']; // sem id

    public function validates(): void
    {
        Validations::notEmpty('title', $this);
    }
}
```

Atributos: o construtor cria uma entrada em `$attributes` para cada
coluna de `$columns`. `__get`/`__set` aceitam só (1) propriedades
reais da classe (`id`, `password`...), (2) colunas listadas, (3)
relacionamentos. Qualquer outra coisa lança
`Property x not found`. Coluna fora de `$columns` nunca vai para o
banco e dá exceção ao atribuir.

Operações e o SQL que geram:

- `new Problem([...])`: nenhum SQL. `newRecord()` é `true` até salvar.
- `$p->save()`: INSERT ou UPDATE de todas as colunas. Chama
  `isValid()` antes; retorna `false` se inválido.
- `$p->update(['title' => 'x'])`: UPDATE só das chaves. NÃO valida.
  Para mudanças pontuais (ex.: avatar, `last_login_at`).
- `$p->destroy()`: DELETE WHERE id.
- `Problem::findById(3)`: SELECT WHERE id; `null` se não existe.
- `Problem::all()`: SELECT tudo, sem ordenação.
- `Problem::where(['user_id' => 1, 'status' => 'open'])`:
  WHERE a = :a AND b = :b. Só igualdade e AND.
- `Problem::findBy([...])`: idem, primeiro resultado ou `null`.
- `Problem::exists([...])`: idem, `bool`.
- `Problem::paginate(page: 2, per_page: 10)`: COUNT + LIMIT/OFFSET.
  Devolve `Lib\Paginator`.

Validação: `isValid()` zera `$errors`, chama `validates()`, e cada
`Validations::*` chama `$obj->addError('campo', 'mensagem')`. Na view:
`errors('campo')`, `hasErrors()`. Prontas em `lib/Validations.php`:
`notEmpty`, `passwordConfirmation`, `uniqueness` (campo ou lista de
campos; ignora o próprio registro se o valor não mudou). Regra nova =
método estático novo nessa classe, mesmo contrato.

Relacionamentos: um método com tipo de retorno `BelongsTo`, `HasMany`
ou `BelongsToMany` vira propriedade mágica em snake_case.

```php
// User
public function problems(): HasMany
{
    return $this->hasMany(Problem::class, 'user_id');
}
public function reinforcedProblems(): BelongsToMany
{
    return $this->belongsToMany(
        Problem::class, 'problem_user_reinforce', 'user_id', 'problem_id'
    );
}
// Problem
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}
```

Uso:

- `$user->problems`: array (via `__get`, chama `problems()->get()`).
- `$problem->user`: objeto.
- `$user->reinforced_problems`: array.
- Chamando o método você ganha mais: `$user->problems()->new($params)`
  (já preenche `user_id`), `->findById($id)` (escopado ao dono),
  `->paginate(...)`; `->count()` no BelongsToMany.
- NxN: o framework só lê (`get`, `count`). Para inserir/remover na
  pivô use um model da pivô (`ProblemUserReinforce` com
  `uniqueness(['problem_id', 'user_id'])`).

Atributos virtuais: `User` declara `protected ?string $password` (não
é coluna) e sobrescreve `__set` para, ao receber `password` num
registro NOVO, gravar `encrypted_password = password_hash(...)`. Em
registro existente, `password` é ignorado (há teste para isso).

### 4.7 Banco (database/schema.sql, database/Populate/populate.php)

Nosso schema na 4.1:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    encrypted_password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'participant') NOT NULL DEFAULT 'participant',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;
```

- Sem migrations. `./run db:reset` executa o `schema.sql` inteiro
  (DROP + CREATE) e apaga os dados. Mudança de coluna = editar o
  schema + `db:reset` + `db:populate`.
- `db:populate` chama `Database::migrate()`, que também executa o
  schema: popular já recria as tabelas.
- Os testes fazem `Database::create()` + `migrate()` em todo `setUp`
  e `drop()` no `tearDown`, no banco `eventos_test`. O schema é
  testado a cada teste.
- Convenções (rubrica da E3): inglês, tabelas no plural, pivô no
  singular em ordem alfabética (`problem_user`), snake_case, PK `id`,
  FK `user_id`, senha em `encrypted_password`.
- `populate.php` inclui o bootstrap, chama `Database::migrate()` e
  cria registros com os models (passa por validação e hash). Nosso
  populate cria `admin@example.com` (admin) e `user@example.com`
  (participant), senha `123456`.

### 4.8 Autenticação (lib/Authentication/Auth.php, App\Models\User)

```
Auth::login($user)   -> $user->registerLogin(); $_SESSION['user']['id']
Auth::user()         -> User::findById($_SESSION['user']['id']) ou null
Auth::check()        -> bool (sessão tem id E o usuário existe)
Auth::logout()       -> unset($_SESSION['user']['id'])
$user->authenticate('senha') -> password_verify(senha, hash)
$user->isAdmin()     -> role === 'admin'
$user->registerLogin() -> update(['last_login_at' => agora])
```

Sessão = cookie `PHPSESSID` no navegador + dados no servidor com o
`id`. Nada de senha ou objeto inteiro na sessão. O Controller base
carrega o usuário a cada request, então mudanças no banco (ex.:
`role`) valem no próximo clique.

O que o nosso projeto adicionou sobre o template: colunas `role`,
`last_login_at`, `created_at`, `updated_at`; `User::isAdmin()` e
`registerLogin()`; `role` padrão `participant` no construtor;
`Auth::login` chamando `registerLogin()`; middleware `Admin`; rotas
`/login`, `/logout`, `/dashboard`, `/admin`; navbar com Painel,
Administração e Sair; populate com dois usuários.

### 4.9 FlashMessage, Paginator, erros, debug

- `FlashMessage::success('...')` / `danger('...')` gravam em
  `$_SESSION['flash'][tipo]`; `get()` lê e apaga. O layout imprime
  como `alert-success` / `alert-danger`. Um texto por tipo; o segundo
  `danger` sobrescreve o primeiro. Tipo novo (`warning`) = método
  estático novo.
- `Paginator`: `registers()`, `renderPagesNavigation()` (inclui
  `app/views/paginator/_pages.phtml`), `entriesInfo()`,
  `hasNextPage()`, `getPage()`, `totalOfPages()`. `getRouteName()`
  devolve `<tabela>.paginate` se você não passou `route:`.
- `HTTPException($msg, 404)`: o ErrorsHandler responde com esse
  status. Rota não encontrada já lança 404.
- `dd($a, $b)`: imprime e para. Tire antes do commit.

## 5. Testes: as quatro camadas

O CI (`.github/workflows/ci.yml`) roda tudo isso e mais `phpcs`
(PSR-12), `phpstan` (nível 6) e o checador PSR-4.

Unitário

- Pasta `tests/Unit/`. Base `Tests\TestCase`.
- `setUp` cria e migra `eventos_test`; `tearDown` dropa.
- Testa model, lib, core. Comando: `./run test tests/Unit`.

Controller

- Pasta `tests/Integration/Controllers/`. Base `ControllerTestCase`.
- Cria por `eval` uma subclasse do controller em que `redirectTo`
  vira `echo 'Location: ...'`; chama a ação direto com params e
  captura o HTML. Não passa por nginx nem Router.
- Logar: `Auth::login($user)` ou `$_SESSION['user']['id'] = ...` no
  `setUp`. JSON: `$_SERVER['HTTP_ACCEPT'] = 'application/json'`.
- Comando: `./run test tests/Integration/Controllers`.

Acesso

- Pasta `tests/Integration/Access/`. Base PHPUnit + Guzzle
  (dependência transitiva do Codeception).
- HTTP real em `http://web:8080` (nginx -> `php_test`), com
  `allow_redirects => false`. Verifica 302 para `/login` sem sessão e
  200 em rota pública.
- Comando: `./run test tests/Integration/Access`.

Aceitação

- Pasta `tests/Acceptance/`. Base `BaseAcceptanceCest` + Codeception
  WebDriver.
- Chrome headless no container `selenium`, navegando em `web:8080`.
  Um cenário por método. Helpers `$page->login(email, senha)` e
  `$page->logout()` em `tests/Support/Helper/LoginHelper.php`.
- Comando: `./run test:browser`. Antes da primeira vez:
  `./run codecept build`.

Convenções:

- Nome descreve o comportamento:
  `test_authenticate_with_invalid_credentials_should_not_login`.
- Cada cenário do vídeo tem um Cest correspondente (a rubrica conta).
- Um arquivo: `./run test tests/Unit/Models/Users/UserTest.php`.
- A porta 8080 e o `php_test` existem para os testes usarem outro
  banco (`DB_DATABASE=eventos_test`, variável de ambiente do
  container).

## 6. Como construir uma feature

Ordem recomendada, de dentro para fora. Cada passo tem um teste que o
fecha.

 1. `schema.sql`: tabela, FK, índices.
 2. Model: `$table`, `$columns`, `validates()`, relações.
 3. Teste unitário do model (`tests/Unit/Models`).
 4. Rotas: RESTful, nomeadas, no grupo de middleware certo.
 5. Controller: `index/show/new/create/edit/update/destroy`.
 6. Views: `app/views/recurso/*.phtml`, form com
    `name="recurso[campo]"`.
 7. Teste de controller e de acesso.
 8. Teste de aceitação, um por cenário da rubrica.
 9. `populate.php` com dados de demonstração.
10. `./run phpcs`, `./run phpstan`.
11. Commit em inglês; PR feature -> epic.

Checklist de "feito":

- [ ] Tabela segue as convenções de nome.
- [ ] Model lista todas as colunas em `$columns` e tem `validates()`.
- [ ] Rotas nomeadas, RESTful, no grupo certo, `new` antes de `{id}`.
- [ ] Controller magro; sucesso redireciona com flash, erro
      re-renderiza com o objeto.
- [ ] View usa `route()`, `name="x[campo]"`, mostra `errors('campo')`.
- [ ] Testes: unit do model, controller/acesso, aceitação por cenário.
- [ ] `./run test`, `./run test:browser`, `./run phpcs`,
      `./run phpstan` verdes.
- [ ] Testado no navegador em `http://localhost` (sucesso e erro).
- [ ] `populate.php` atualizado se a demo precisa de dados.

## 7. Como alterar uma feature

Serve para o FVP e para tarefa da equipe. Sempre:
localizar -> testar -> mudar -> verificar -> entregar.

1. Localizar: onde X mora (lista abaixo).
2. Testar: escreva ou ajuste o teste que descreve o novo
   comportamento; rode; deve FALHAR.
3. Mudar: o menor conjunto de arquivos, na ordem
   schema -> model -> rota -> controller -> view.
4. Verificar: `./run test caminho/do/teste` deve PASSAR. Mudou
   schema? `./run db:reset && ./run db:populate`. Confira no
   navegador. Depois `./run test`, `./run phpcs`, `./run phpstan` e
   `./run test:browser` se tocou em view ou rota.
5. Entregar: `git commit -m "feat: ..."` (inglês); PR feature -> epic.

Quero mudar X, então mexo em Y:

- Nova página/URL: `config/routes.php` + controller +
  `app/views/<x>/<acao>.phtml`. Teste: controller, aceitação.
- Proteger uma URL: mover a rota para dentro de
  `Route::middleware('auth')->group`. Teste: acesso.
- Nova restrição (só admin, só dono): classe em `app/Middleware/` +
  apelido em `config/App.php` + grupo em `routes.php`. Teste: acesso.
- Texto de mensagem: controller (`FlashMessage::...`) ou
  `lib/Validations.php`. Teste: o que conferia a string antiga.
- Destino após login/logout: `AuthenticationsController`. Teste:
  `AuthenticationsControllerTest` (`Location: /...`), `LoginCest`.
- Nova regra de validação: método em `lib/Validations.php` + chamada
  em `validates()`. Teste: `tests/Unit/Lib/Validations/*`, model.
- Nova coluna: `schema.sql` + `$columns` + form + `populate.php`.
  Teste: unit do model (o schema é migrado no `setUp`).
- Nova tabela/recurso: schema + model + rotas + controller + views.
  Teste: tudo.
- Relacionar tabelas: FK no schema + métodos `belongsTo`/`hasMany`/
  `belongsToMany` nos dois models. Teste: unit dos models.
- Campo no form de login: `app/views/authentications/new.phtml` +
  leitura em `authenticate`. Teste: `LoginHelper`, `LoginCest`.
- Item na navbar: `app/views/layouts/_navbar.phtml`. Teste: aceitação.
- Logo, título, CSS: `application.phtml`, `public/assets/css/`.
- Resposta JSON: `if ($request->acceptJson()) renderJson(...)` +
  `app/views/x/acao.json.php`. Teste: controller com
  `HTTP_ACCEPT = application/json`.
- Usuários de demonstração: `database/Populate/populate.php`.
- Lógica fora de model (upload, e-mail, cálculo):
  `app/Services/Nome.php`. Teste: `tests/Unit/Services/*`.

Regras da equipe: branch `epic/<entrega>` sai da `main`; feature sai
da epic. Commits em inglês, `type: Title`. PR feature -> epic via
`scripts/ensaio.sh` (repositório privado). Registre uso de IA no log
da entrega.

## 8. Erros comuns

- `Property x not found in App\Models\Y`: coluna fora de `$columns`,
  ou nome do relacionamento não bate com o método (snake_case vs
  camelCase). Olhe o model.
- `Route with name x not found`: falta `->name('x')` ou o texto é
  outro. Olhe `config/routes.php`.
- `URI /x not found.` (404): rota não declarada, método diferente
  (`_method` faltando) ou número de segmentos diferente.
- Página de erro `Undefined variable $title`: faltou `$title` no
  `compact`. O layout exige e o ErrorsHandler vira o aviso em 500.
- `Failed opening required '.../app/views/x/y.phtml'`: nome da view no
  `render` não bate com o caminho.
- Loop de redirecionamento para `/login`: `auth` numa rota que o
  próprio `Authenticate` usa (`users.login`).
- `SQLSTATE ... Unknown column` em dev, testes passam: mudou o schema
  e não rodou `./run db:reset`.
- `SQLSTATE ... Unknown column` nos testes: `$columns` e `schema.sql`
  divergem.
- Validação não acontece: usou `update([...])` em vez de `save()`.
- Senha nova não funciona: `__set('password')` só faz hash em registro
  novo.
- `Admin` redireciona antes de `Authenticate`: ordem dos grupos
  aninhados (interno primeiro). Middlewares independentes.
- CI `PSR-4 autoloading ... does not comply`: namespace ou nome de
  classe não bate com o caminho.
- CI PHPCS: linha > 130, chaves fora do lugar, snake_case em método
  fora de `*Test.php`/`*Cest.php`. `./run phpcbf` corrige a maioria.
- CI PHPStan nível 6: falta tipo em parâmetro/retorno ou
  `@return array<...>`.
- `move_uploaded_file` falha: permissão em `public/assets/uploads`
  (`chown www-data`).

## 9. Armadilhas verificadas no código

Comportamentos reais do framework que surpreendem. Conferidos linha a
linha no `core/` e no `lib/`.

- `BelongsTo::get()` declara retorno `Model`, não `?Model`. FK nula ou
  apontando para registro apagado gera `TypeError`, não `null`.
- `Model::where([])` com condições vazias monta `WHERE ` sem nada e
  quebra no SQL. Sempre passe ao menos uma condição.
- `Auth::user()` faz um SELECT a cada chamada. `Auth::check()` chama
  `user()`. Numa requisição em `/admin` há três consultas em `users`
  (Admin, Authenticate, construtor do Controller). O Controller
  guarda o resultado em `$current_user`; use-o nas views.
- `FlashMessage` guarda um texto por tipo. Duas chamadas a `danger`
  na mesma requisição mostram só a última.
- `renderJson` envia `Content-Type: application/json; chartset=utf-8`
  (com erro de digitação no `charset`). Funciona nos navegadores,
  mas não corrija em `core/` sem combinar.
- `_flash_message.phtml` fecha o loop com `<? endforeach; ?>` (tag
  curta). Funciona porque a imagem `php:8.3-fpm` não traz `php.ini`
  e o padrão compilado de `short_open_tag` é ligado. Se alguém
  copiar `php.ini-production`, a tag curta para de funcionar.
- `Request::acceptJson()` exige o cabeçalho `Accept` exatamente igual
  a `application/json`. `application/json, text/plain` não casa.
- `Route::isSameUri` compara segmento a segmento. `/problems/` (barra
  final) tem um segmento a mais que `/problems` e não casa.
- `Router::getRoutePathByName` lança `Exception` genérica (status 500
  na mensagem, mas o ErrorsHandler responde 500 de qualquer jeito).
- `Database::migrate()` executa o `schema.sql` inteiro num único
  `exec`. Erro de SQL no meio aborta sem rollback de DDL.
- `phpstan.neon` analisa `app`, `core` e `tests`, mas não `lib/`.
  Código novo em `lib/` não é checado pelo PHPStan.
- `phpcs.xml` exclui `database/`. O populate não passa pelo lint.
- No nosso projeto, `/logout` é `GET` e está fora do grupo `auth`.
  O professor pediu em 2025-2 logout via `DELETE`.
- `ControllerTestCase` usa `eval` para sobrescrever `redirectTo`.
  Middlewares não rodam nesses testes; o que eles testam é a ação.
- O `php_test` lê `DB_DATABASE=eventos_test` do `docker-compose.yml`;
  o `phpunit.xml` repete as mesmas variáveis. Mudou o nome do banco de
  teste? Mude nos dois.
- `Validations::uniqueness` em registro existente só ignora o próprio
  registro se o valor do campo não mudou. Trocar o e-mail para um que
  já existe dá erro, como deve.

## 10. Padrões de projeto, com livro

A rubrica pede referência de livro para cada conceito.

- MVC (`app/Controllers`, `app/Models`, `app/views`): Fowler,
  Patterns of Enterprise Application Architecture (2002), cap. 14.
- Front Controller (`public/index.php` + `Router::dispatch`): Fowler,
  PoEAA, "Front Controller".
- Active Record (`Core\Database\ActiveRecord\Model`): Fowler, PoEAA,
  "Active Record".
- Singleton (`Router::getInstance()`): Gamma, Helm, Johnson,
  Vlissides, Design Patterns (1994), "Singleton".
- Template View (`.phtml` com PHP embutido): Fowler, PoEAA,
  "Template View".
- Intercepting Filter / middleware (`App\Middleware\*`,
  `Route::runMiddlewares`): Alur, Crupi, Malks, Core J2EE Patterns
  (2003), "Intercepting Filter".
- Post/Redirect/Get (`create` -> `redirectTo`): Fowler, PoEAA,
  "Page Controller" (discussão do redirecionamento após POST).
- Builder (citado pelo professor como bônus): Gamma et al.,
  "Builder". Implementação própria do Problem Track ligada no Moodle.

Para autenticação, sessões, cookies e 2FA (rubrica 4.1), ver
`docs/entregas/e4.1-cl/e4.1-conceitos-referencias.md` no repositório
privado.

## 11. Template, problem-track e nosso projeto

Conferido por diff em 2026-10-15.

- `core/`: idêntico nos três (problem-track `8130d35`, template
  `d280222`, projeto da equipe `86c81ca`).
- `lib/`: idêntico, exceto `Auth::login`, que no nosso projeto recebe
  `User` tipado e chama `registerLogin()`.
- Template = problem-track sem `Problem`, `ProblemUserReinforce`,
  `ProfileAvatar`, controllers de problems/profile/reinforce e seus
  testes. Mantém `User` (com `avatar_name`), `Authenticate`,
  `HomeController`, layouts, paginator e os testes de core/lib.
- Nosso projeto sobre o template: ver seção 4.8. Além disso, o
  `User` perdeu `avatar_name` (volta na 4.3, com a galeria) e ganhou
  `role` e `last_login_at`.
- Infra idêntica à do problem-track (`run`, `Dockerfile`, nginx,
  CI). Diferenças: nome do banco de teste (`eventos_test`) e o passo
  de testes de integração do CI, que no nosso tolera pasta vazia.
