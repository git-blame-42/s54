# FVP: possíveis modificações no código entregue

Catálogo das alterações que o professor pode pedir na validação
prática (FVP) sobre o código do repositório público. Para cada uma:
chance, arquivos, passos, código quando ajuda, teste e armadilha.
Pressupõe o `framework-completo.md`.

## 1. O que é o FVP

Texto do Enunciado 2026-2 ("Validação Prática das Entregas de
Código"):

- Dinâmica: individualmente, cada aluno baixa o projeto da sua
  equipe nos computadores do laboratório.
- Tarefa: o professor solicita, na hora, a implementação de uma nova
  funcionalidade ou alteração pontual no código entregue.
- Critério: a nota da entrega só é mantida integralmente se o aluno
  demonstrar domínio ao resolver a tarefa.

Fator que multiplica a nota da entrega:

- 1.0: atividade perfeita, todos os requisitos.
- 0.75: maior parte correta, pequenos erros.
- 0.5: compreensão parcial, erros relevantes ou incompleto.
- 0.25: pouca compreensão, diversos erros.
- 0.0: não implementou. Zera a entrega ("caracteriza que não foi o
  aluno quem realizou a atividade").

Quando: na quinta-feira seguinte a cada entrega 4.x (15/10, 29/10,
12/11, 26/11), junto com as apresentações.

De onde vêm os palpites deste catálogo:

- O código entregue na 4.1 (autenticação, áreas de usuário e admin).
- A avaliação do professor sobre a nossa 4.1: "os middlewares Admin e
  Authenticate possuem lógica duplicada de redirecionamento. Avaliem
  como centralizar". Pedido explícito; chance máxima.
- Feedback 2025-2 do professor para outras equipes: logout via
  DELETE, parâmetros só pelo Request, autorização em middleware,
  ação de login chamada `new`, nomes de teste descritivos.
- Rubrica da próxima entrega (4.2 CRUD): `save`, `all`, `findById`,
  `where`, `Validations`, `Paginator`, rotas RESTful.
- Tamanho: algo que cabe em 15 a 40 minutos para quem conhece o
  projeto.

## 2. Preparo antes do laboratório

Faça uma vez em casa e repita no laboratório (10 minutos):

```
git clone <repositorio publico> && cd <pasta>
cp .env.example .env
./run composer install
./run up -d
./run db:reset
./run db:populate
./run codecept build
./run test tests/Unit
```

Decore: `admin@example.com` e `user@example.com`, senha `123456`.
Aplicação em `http://localhost`. Testes em `./run test <caminho>`.

Método, sempre o mesmo:

1. Localizar: em que arquivos X mora (seção 7 do guia completo).
2. Testar: escreva ou ajuste o teste; rode; deve falhar.
3. Mudar: o menor conjunto de arquivos, na ordem
   schema -> model -> rota -> controller -> view.
4. Verificar: teste passa; `db:reset` se mudou schema; navegador;
   `./run phpcs`.
5. Entregar: commit em inglês (`feat: ...`), na branch que o professor
   indicar.

Se o tempo apertar: entregue a funcionalidade funcionando no
navegador antes do teste. Funcionalidade sem teste vale 0.75;
teste sem funcionalidade vale 0.

## 3. Como ler o catálogo

- Chance: alta, média ou baixa. Estimativa nossa, pelas fontes acima.
- Tempo: para quem já fez o preparo.
- "Guia 4.x": seção do `framework-completo.md`.

## A. Rotas e middleware

### A1. Centralizar o redirecionamento dos middlewares

Chance: alta (o professor pediu por escrito). Tempo: 15 min.

Problema: `Authenticate` e `Admin` têm o mesmo `redirectTo()`
privado, e os dois fazem `FlashMessage::danger` + redirecionar.

Arquivos: `app/Middleware/BaseMiddleware.php` (novo),
`app/Middleware/Authenticate.php`, `app/Middleware/Admin.php`.

```php
<?php
// app/Middleware/BaseMiddleware.php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Lib\FlashMessage;

abstract class BaseMiddleware implements Middleware
{
    protected function deny(string $message, string $routeName): void
    {
        FlashMessage::danger($message);
        $this->redirectTo(route($routeName));
    }

    protected function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
```

```php
class Authenticate extends BaseMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            $this->deny(
                'Você deve estar logado para acessar essa página',
                'users.login'
            );
        }
    }
}

class Admin extends BaseMiddleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        if ($user !== null && !$user->isAdmin()) {
            $this->deny('Acesso restrito a administradores',
                'dashboard.index');
        }
    }
}
```

Remova os `redirectTo()` privados e os `use` que sobraram
(`FlashMessage` sai dos dois; `Middleware` sai dos dois).

Alternativas, se o professor pedir outra forma: trait
`App\Middleware\RedirectsTo`; ou mover o helper para
`core/Http/Middleware/` (conta como mudança no framework; combine).

Teste: nada novo é obrigatório; `DashboardAccessTest`,
`DashboardCest::accessDashboardWithoutLogin` e
`AdminDashboardCest::accessAdminAsCommonUser` cobrem os dois
caminhos. Rode `./run test tests/Integration/Access` e
`./run test:browser`.

Armadilha: classe abstrata que implementa interface sem definir
`handle` é válida. PSR-4: o arquivo tem que se chamar
`BaseMiddleware.php`. PHPCS: linhas até 130.

### A2. Logout via DELETE

Chance: alta (feedback 2025-2; hoje é GET). Tempo: 15 min.

Arquivos: `config/routes.php`, `app/views/layouts/_navbar.phtml`.

```php
// routes.php
Route::delete('/logout', [AuthenticationsController::class, 'destroy'])
    ->name('users.logout');
```

```php
<!-- _navbar.phtml: o link vira formulário -->
<li>
    <form action="<?= route('users.logout') ?>" method="post">
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit" class="dropdown-item">Sair</button>
    </form>
</li>
```

O Request lê `_method` e o Router casa a rota DELETE.
`LoginHelper::logout()` clica em "Sair"; continua funcionando porque
agora é um botão com o mesmo texto. `test_destroy_should_logout`
chama a ação direto; não muda.

Armadilha: `GET /logout` passa a dar 404. Se algum teste ou link usa
`amOnPage('/logout')`, ajuste.

### A3. Logout só para quem está logado

Chance: alta, junto com A2. Tempo: 10 min.

Mova a rota de logout para dentro de `Route::middleware('auth')`.
Teste de acesso novo em `DashboardAccessTest` (ou arquivo próprio):

```php
public function test_logout_should_redirect_to_login_without_session(): void
{
    $response = $this->client->delete('/logout');

    $this->assertEquals(302, $response->getStatusCode());
    $this->assertEquals('/login', $response->getHeaderLine('Location'));
}
```

Se o logout continuar GET, use `$this->client->get('/logout')`.

### A4. Admin cai em /admin depois do login

Chance: alta. Tempo: 15 min.

Arquivo: `app/Controllers/AuthenticationsController.php`.

```php
if ($user && $user->authenticate($params['password'])) {
    Auth::login($user);
    FlashMessage::success('Login realizado com sucesso!');

    $target = $user->isAdmin()
        ? route('admin.dashboard.index')
        : route('dashboard.index');
    $this->redirectTo($target);
}
```

Teste novo em `AuthenticationsControllerTest`:

```php
public function test_authenticate_admin_should_redirect_to_admin(): void
{
    $admin = new User([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => '123456',
        'password_confirmation' => '123456',
        'role' => 'admin',
    ]);
    $admin->save();

    $response = $this->post(
        action: 'authenticate',
        controllerName: 'App\Controllers\AuthenticationsController',
        params: ['user' => ['email' => 'admin@example.com',
                            'password' => '123456']]
    );

    $this->assertStringContainsString('Location: /admin', $response);
}
```

Armadilha: `AdminDashboardCest::accessAdminAsAdmin` faz login e depois
`amOnPage('/admin')`; continua válido. `LoginCest` usa usuário comum;
continua válido.

### A5. Middleware guest: logado não vê /login

Chance: média. Tempo: 20 min.

Arquivos: `app/Middleware/Guest.php`, `config/App.php`,
`config/routes.php`.

```php
class Guest extends BaseMiddleware   // ou implements Middleware
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            $this->redirectTo(route('dashboard.index'));
        }
    }
}
```

```php
// config/App.php
'guest' => \App\Middleware\Guest::class,

// routes.php
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticationsController::class, 'new'])
        ->name('users.login');
    Route::post('/login', [AuthenticationsController::class,
        'authenticate'])->name('users.authenticate');
});
```

Teste: aceitação (logar, `amOnPage('/login')`,
`seeCurrentUrlEquals('/dashboard')`). Teste de acesso com Guzzle
precisaria de cookie de sessão; evite.

Armadilha: não coloque `/logout` no grupo `guest`.

### A6. Nova página restrita (ex.: /profile)

Chance: alta. Tempo: 20 min.

Arquivos: `config/routes.php` (dentro de `auth`),
`app/Controllers/ProfileController.php`,
`app/views/profile/show.phtml`, `_navbar.phtml`.

```php
// routes.php, dentro do grupo auth
Route::get('/profile', [ProfileController::class, 'show'])
    ->name('profile.show');
```

```php
class ProfileController extends Controller
{
    public function show(): void
    {
        $title = 'Meu Perfil';
        $this->render('profile/show', compact('title'));
    }
}
```

```php
<!-- app/views/profile/show.phtml -->
<ul>
    <li>Nome: <?= $this->currentUser()->name ?></li>
    <li>E-mail: <?= $this->currentUser()->email ?></li>
    <li>Perfil: <?= $this->currentUser()->role ?></li>
</ul>
```

Testes: acesso (`GET /profile` sem sessão -> 302 `/login`);
controller (`Auth::login($user)` no `setUp`, `get('show', ...)`,
assert com o nome); aceitação (login, `amOnPage('/profile')`,
`see('Meu Perfil', '//h1')`).

### A7. Novo perfil (ex.: organizer) com área própria

Chance: média. Tempo: 30 min.

Arquivos: `database/schema.sql` (ENUM ganha `'organizer'`),
`app/Models/User.php` (`isOrganizer()`),
`app/Middleware/Organizer.php`, `config/App.php` (`'organizer'`),
`config/routes.php` (grupo dentro de `auth`), controller e view,
`_navbar.phtml`, `populate.php`.

Passos: schema -> `db:reset` -> model -> middleware (copie `Admin`,
troque o teste de perfil e a mensagem) -> alias -> grupo -> página ->
navbar -> populate -> testes (unit `isOrganizer`, acesso, aceitação).

Armadilha: o grupo do organizer é irmão do grupo `admin`, não filho.
Se ficar dentro de `admin`, o middleware `Admin` barra o organizer.

### A8. Responder 403 em vez de redirecionar

Chance: baixa. Tempo: 10 min.

No `Admin::handle`, troque o `deny(...)` por:

```php
throw new HTTPException('Acesso restrito a administradores', 403);
```

O `ErrorsHandler` responde com o status da exceção. Ajuste
`AdminDashboardCest::accessAdminAsCommonUser` (não há mais
redirecionamento para `/dashboard`).

## B. Model, validação e banco

### B1. Senha com no mínimo N caracteres

Chance: alta. Tempo: 15 min.

Arquivos: `lib/Validations.php`, `app/Models/User.php`, testes.

```php
// lib/Validations.php
public static function minLength(string $attribute, int $min, $obj): bool
{
    $value = $obj->$attribute;

    if ($value !== null && mb_strlen($value) < $min) {
        $obj->addError($attribute, "deve ter pelo menos {$min} caracteres!");
        return false;
    }

    return true;
}
```

```php
// User::validates(), dentro do if ($this->newRecord())
Validations::minLength('password', 6, $this);
Validations::passwordConfirmation($this);
```

Teste em `UserTest`:

```php
public function test_errors_should_return_short_password_error(): void
{
    $user = new User([
        'name' => 'User 4',
        'email' => 'fulano4@example.com',
        'password' => '123',
        'password_confirmation' => '123',
    ]);

    $this->assertFalse($user->isValid());
    $this->assertEquals(
        'deve ter pelo menos 6 caracteres!',
        $user->errors('password')
    );
}
```

Armadilha: `passwordConfirmation` também escreve em
`errors['password']`; a última regra que falha sobrescreve. A ordem
das chamadas decide a mensagem. `password` é protegido em `User`; o
`__get` do Model devolve propriedades reais, e o `phpstan.neon` já
ignora o aviso.

### B2. E-mail em formato válido

Chance: alta. Tempo: 15 min.

```php
// lib/Validations.php
public static function email(string $attribute, $obj): bool
{
    $value = $obj->$attribute;

    if ($value === null || $value === '') {
        return true; // notEmpty cuida do vazio
    }

    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        $obj->addError($attribute, 'deve ser um e-mail válido!');
        return false;
    }

    return true;
}
```

Chame em `User::validates()` logo após `notEmpty('email', ...)`.
Teste: `UserTest` com `'email' => 'nao-e-email'` esperando a
mensagem; e um teste em `tests/Unit/Lib/Validations/ValidationsTest.php`
seguindo o padrão dos existentes.

Armadilha: sem o `return true` para vazio, a mensagem de e-mail
inválido sobrescreve "não pode ser vazio!" e
`test_errors_should_return_errors` quebra.

### B3. Nova coluna no usuário (ex.: phone)

Chance: alta. Tempo: 20 min.

Arquivos: `database/schema.sql`, `app/Models/User.php`,
`database/Populate/populate.php`, views que mostram o usuário.

```sql
-- schema.sql, dentro de CREATE TABLE users
phone VARCHAR(20) NULL,
```

```php
// User.php
protected static array $columns = [
    'name', 'email', 'encrypted_password', 'role', 'last_login_at',
    'phone',
];
```

Depois: `./run db:reset && ./run db:populate` (o banco de
desenvolvimento não se atualiza sozinho). Os testes migram o schema a
cada `setUp`. Teste em `UserTest`:

```php
public function test_phone_should_be_persisted(): void
{
    $this->user->phone = '(46) 99999-0000';
    $this->user->save();

    $this->assertEquals(
        '(46) 99999-0000',
        User::findById($this->user->id)->phone
    );
}
```

Armadilha: se a coluna for `NOT NULL` sem default, todos os
`new User([...])` dos testes e do populate precisam do campo.
Acrescente `@property string|null $phone` no docblock do model para o
PHPStan.

### B4. Mostrar o último acesso no painel

Chance: média (a coluna já existe). Tempo: 10 min.

Arquivo: `app/views/dashboard/index.phtml`.

```php
<?php $last = $this->currentUser()->last_login_at; ?>
<p>Último acesso:
   <?= $last ? date('d/m/Y H:i', strtotime($last)) : 'primeiro acesso' ?>
</p>
```

Teste de controller novo (`DashboardControllerTest`): `Auth::login`
no `setUp`, `get('index', ...)`, `assertStringContainsString('Último
acesso', $response)`.

Armadilha: `Auth::login` grava `last_login_at` antes de abrir a
sessão. O valor mostrado é o login atual, não o anterior. Se o
professor quiser o anterior, leia o valor antes de `registerLogin()`
e guarde na sessão.

### B5. Usuário inativo não consegue logar

Chance: média. Tempo: 25 min.

Arquivos: `schema.sql` (`active TINYINT(1) NOT NULL DEFAULT 1`),
`User` (`'active'` em `$columns`, método `isActive()`),
`AuthenticationsController::authenticate`, populate, testes.

```php
// User.php
public function isActive(): bool
{
    return (bool) $this->active;
}

// authenticate(), troque a condição
if ($user && $user->isActive() && $user->authenticate($params['password'])) {
```

Mensagem distinta para inativo é opcional; se pedir, separe o `if`.
Testes: unit (`isActive` padrão `true`; `update(['active' => 0])`
e recarregar); controller (inativo -> `Location: /login` e
`Auth::check()` falso).

Armadilha: `TINYINT` chega do PDO como string `"1"`/`"0"`; por isso
o cast.

### B6. Trocar a senha de um usuário existente

Chance: média. Tempo: 25 min.

Pegadinha: `User::__set` só gera o hash quando `newRecord()`. Para
usuário salvo, atribuir `password` não muda nada.

```php
// User.php
public function changePassword(string $password, string $confirmation): bool
{
    $this->password = $password;
    $this->password_confirmation = $confirmation;

    if (!Validations::passwordConfirmation($this)) {
        return false;
    }

    return $this->update([
        'encrypted_password' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}
```

Teste: criar usuário, `changePassword('654321', '654321')`,
recarregar com `findById`, `authenticate('654321')` verdadeiro e
`authenticate('123456')` falso. Com formulário: rota
`PUT /profile/password` dentro de `auth`, ação lê
`getParam('user')`, chama o método, flash, redireciona.

### B7. Cadastro de usuário (sign up)

Chance: média. Tempo: 35 min.

Arquivos: `config/routes.php` (fora de `auth`),
`app/Controllers/UsersController.php`, `app/views/users/new.phtml`,
link na tela de login, testes.

```php
// routes.php (publicas). 'users.login' ja existe; nao repita o nome.
Route::get('/users/new', [UsersController::class, 'new'])
    ->name('users.new');
Route::post('/users', [UsersController::class, 'create'])
    ->name('users.create');
```

```php
class UsersController extends Controller
{
    public function new(): void
    {
        $user = new User();
        $title = 'Cadastro';
        $this->render('users/new', compact('user', 'title'));
    }

    public function create(Request $request): void
    {
        $params = $request->getParam('user');
        $user = new User([
            'name' => $params['name'] ?? null,
            'email' => $params['email'] ?? null,
            'password' => $params['password'] ?? null,
            'password_confirmation' => $params['password_confirmation'] ?? null,
        ]);

        if ($user->save()) {
            FlashMessage::success('Cadastro realizado! Faça login.');
            $this->redirectTo(route('users.login'));
        } else {
            FlashMessage::danger('Existem dados incorretos!');
            $title = 'Cadastro';
            $this->render('users/new', compact('user', 'title'));
        }
    }
}
```

View: form `POST route('users.create')`, campos `user[name]`,
`user[email]`, `user[password]`, `user[password_confirmation]`,
`novalidate`, `invalid-feedback` com `$user->errors('campo')`.

Testes: controller (válido -> `Location: /login`; inválido -> "não
pode ser vazio!"); acesso (`GET /users/new` -> 200); aceitação
(preencher, enviar, ver flash, logar).

Armadilha: NÃO faça `new User($params)` direto. `__set` aceita
qualquer coluna, e um `user[role]=admin` no POST criaria um admin.
Liste os campos permitidos, como acima.

## C. Views, flash e navbar

### C1. Novo tipo de flash (warning, info)

Chance: média. Tempo: 10 min.

```php
// lib/FlashMessage.php
public static function warning(string $value): void
{
    self::message('warning', $value);
}
```

A parcial já imprime `alert-<?= $type ?>`; `alert-warning` e
`alert-info` existem no Bootstrap. Teste em
`tests/Unit/Lib/FlashMessageTest.php`, copiando `test_danger`.

### C2. Nome em vez de e-mail na navbar; link Perfil

Chance: alta (trivial). Tempo: 10 min.

Arquivo: `app/views/layouts/_navbar.phtml`. Troque
`$this->currentUser()->email` por `->name`.

Armadilha: `LoginHelper::logout()` clica em `'fulano@example.com'` e
`LoginCest::loginWithValidCredentials` faz `see('fulano@example.com')`.
Atualize para o nome (`'Fulano'`). Antes de mexer:
`grep -rn "example.com" tests/`.

### C3. Trocar textos de mensagens

Chance: alta (trivial). Tempo: 5 min.

Onde: `FlashMessage::...` nos controllers e middlewares;
`lib/Validations.php` para mensagens de validação. Depois:
`grep -rn "<texto antigo>" tests/` e atualize os asserts
(`LoginCest`, `DashboardCest`, `AdminDashboardCest`, `UserTest`,
`ValidationsTest`).

### C4. Layout próprio para a tela de login

Chance: média. Tempo: 20 min.

Crie `app/views/layouts/login.phtml` (modelo: o do problem-track,
sem navbar, com `_flash_message` e `require $view`). No
`AuthenticationsController`: `protected string $layout = 'login';`.

Armadilha: `test_render_login_page` procura `<h1>Login</h1>`. Se o
layout novo não tem `<h1><?= $title ?></h1>`, ajuste o teste ou
mantenha o `<h1>`. O `login.css` já é carregado pelo layout atual.

## D. Prévia do CRUD (rubrica 4.2)

### D1. Listar usuários no admin com paginação

Chance: alta. Tempo: 30 min.

Arquivos: `config/routes.php` (grupo `admin`),
`app/Controllers/Admin/UsersController.php`,
`app/views/admin/users/index.phtml`, `_navbar.phtml`, testes.

```php
// routes.php, dentro do grupo admin
Route::get('/admin/users', [AdminUsersController::class, 'index'])
    ->name('admin.users.index');
Route::get('/admin/users/page/{page}', [AdminUsersController::class,
    'index'])->name('admin.users.paginate');
```

```php
<?php

namespace App\Controllers\Admin;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;

class UsersController extends Controller
{
    public function index(Request $request): void
    {
        $paginator = User::paginate(
            page: (int) $request->getParam('page', 1),
            per_page: 10,
            route: 'admin.users.paginate'
        );
        $users = $paginator->registers();

        $title = 'Usuários';
        $this->render('admin/users/index',
            compact('users', 'paginator', 'title'));
    }
}
```

```php
<!-- app/views/admin/users/index.phtml -->
<table class="table">
  <thead><tr><th>#</th><th>Nome</th><th>E-mail</th><th>Perfil</th></tr>
  </thead>
  <tbody>
  <?php foreach ($users as $user) : ?>
    <tr>
      <td><?= $user->id ?></td>
      <td><?= $user->name ?></td>
      <td><?= $user->email ?></td>
      <td><?= $user->role ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php $paginator->renderPagesNavigation(); ?>
```

`route:` é necessário porque o padrão seria `users.paginate`, que não
existe. Testes: acesso (`GET /admin/users` sem sessão -> 302
`/login`); controller (admin logado, HTML contém o e-mail);
aceitação. Para ver a paginação, gere 15 usuários no populate.

### D2. Admin remove um usuário

Chance: média. Tempo: 25 min.

```php
// routes.php, grupo admin
Route::delete('/admin/users/{id}', [AdminUsersController::class,
    'destroy'])->name('admin.users.destroy');
```

```php
public function destroy(Request $request): void
{
    $user = User::findById((int) $request->getParam('id'));

    if ($user === null || $user->id === $this->current_user->id) {
        FlashMessage::danger('Não foi possível remover o usuário.');
    } else {
        $user->destroy();
        FlashMessage::success('Usuário removido com sucesso!');
    }

    $this->redirectTo(route('admin.users.index'));
}
```

View: na linha da tabela, form `POST` com `_method=DELETE` para
`route('admin.users.destroy', ['id' => $user->id])`. Testes:
controller (admin logado remove outro; `User::all()` diminui);
acesso (`DELETE /admin/users/1` sem sessão -> 302).

### D3. CRUD mínimo de um recurso novo (ex.: events)

Chance: média (é o tema da 4.2). Tempo: 40 min para o esqueleto.

1. `schema.sql`: `events` com `id`, `title`, `starts_at`, `user_id`
   + FK para `users`.
2. `app/Models/Event.php`: `$table`, `$columns = ['title',
   'starts_at', 'user_id']`, `user(): BelongsTo`, `validates()` com
   `notEmpty('title')`.
3. `User::events(): HasMany`.
4. `tests/Unit/Models/Events/EventTest.php`: cria, valida, lista,
   destrói, relação.
5. Rotas RESTful dentro de `auth` (8 linhas, `new` antes de `{id}`),
   copiadas do problem-track (guia 4.1).
6. `EventsController` com 7 ações, usando
   `$this->current_user->events()->...`.
7. Views `events/index|show|new|edit.phtml`; `edit` envia
   `_method=PUT`; botão excluir envia `_method=DELETE`.
8. `EventsControllerTest`, `EventsAccessTest`, `EventCest`.
9. `populate.php` com alguns eventos; `db:reset` + `db:populate`.

### D4. Busca por nome ou e-mail

Chance: baixa. Tempo: 20 min.

`Model::where` só faz igualdade. Escreva SQL próprio no model:

```php
// User.php
/** @return array<User> */
public static function searchByName(string $term): array
{
    $columns = implode(', ', static::$columns);
    $sql = "SELECT id, {$columns} FROM users WHERE name LIKE :term";

    $stmt = Database::getDatabaseConn()->prepare($sql);
    $stmt->bindValue(':term', "%{$term}%");
    $stmt->execute();

    return array_map(
        fn ($row) => new static($row),
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}
```

Imports: `Core\Database\Database`, `PDO`. Teste unitário com dois
usuários e um termo que casa só um.

## E. JSON e Ajax

### E1. Resposta JSON para uma listagem

Chance: baixa agora, alta na 4.4. Tempo: 20 min.

```php
// no controller
if ($request->acceptJson()) {
    $this->renderJson('admin/users/index', compact('users', 'paginator'));
} else {
    $this->render('admin/users/index',
        compact('users', 'paginator', 'title'));
}
```

```php
<?php
// app/views/admin/users/index.json.php
$json['users'] = array_map(
    fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email],
    $users
);
$json['pagination'] = [
    'page' => $paginator->getPage(),
    'total_of_pages' => $paginator->totalOfPages(),
];
```

Teste de controller: `$_SERVER['HTTP_ACCEPT'] = 'application/json';`
antes do `get(...)`; `json_decode` da resposta. O `tearDown` já
limpa esse header. No navegador: `fetch('/admin/users', {headers:
{Accept: 'application/json'}})`.

## F. Testes

### F1. Escrever o teste que falta para um cenário

Chance: alta (a rubrica pesa testes). Tempo: 10 min por teste.

Acesso (`tests/Integration/Access/XAccessTest.php`):

```php
public function test_x_should_redirect_to_login_without_session(): void
{
    $response = $this->client->get('/x');

    $this->assertEquals(302, $response->getStatusCode());
    $this->assertEquals('/login', $response->getHeaderLine('Location'));
}
```

Controller (`tests/Integration/Controllers/XControllerTest.php`,
estende `ControllerTestCase`):

```php
public function test_index_should_render(): void
{
    Auth::login($this->user);   // criado no setUp

    $response = $this->get(action: 'index',
        controllerName: 'App\Controllers\XController');

    $this->assertStringContainsString('Texto esperado', $response);
}
```

Unitário (`tests/Unit/Models/X/XTest.php`, estende `Tests\TestCase`):
crie o objeto no `setUp`, salve, e teste um método por função.

Aceitação (`tests/Acceptance/x/XCest.php`, estende
`BaseAcceptanceCest`): crie os dados no `_before`, use
`$page->login(...)`, `amOnPage`, `see`, `seeCurrentUrlEquals`.

Nome do teste descreve o comportamento. Rode o arquivo sozinho:
`./run test tests/Integration/Access/XAccessTest.php`.

## G. Populate e infra

### G1. Novo usuário de demonstração ou outras credenciais

Chance: alta (trivial). Tempo: 5 min.

`database/Populate/populate.php`: copie o bloco de um usuário.
Depois `./run db:populate` (já recria as tabelas). Atualize a página
da wiki com as credenciais se mudou.

### G2. Gerar vários usuários (para ver paginação)

Chance: média. Tempo: 5 min.

```php
for ($i = 1; $i <= 15; $i++) {
    (new User([
        'name' => "Participante {$i}",
        'email' => "participante{$i}@example.com",
        'password' => '123456',
        'password_confirmation' => '123456',
    ]))->save();
}
```

### G3. Nova tarefa no script run

Chance: baixa. Tempo: 5 min.

Em `run`, adicione uma função bash com o nome da tarefa
(ex.: `function db:seed { ... }`). O `time ${@}` no fim chama a
função pelo primeiro argumento.

## 4. Armadilhas que custam tempo no laboratório

- Mudou `schema.sql` e esqueceu `./run db:reset`: erro
  `Unknown column` só no navegador; testes passam.
- Coluna nova fora de `$columns`: `Property x not found` ao atribuir
  e a coluna nunca vai para o banco.
- Rota `/x/new` depois de `/x/{id}`: `new` vira `id`.
- Formulário de PUT/DELETE sem `_method`: 404.
- `render` sem `$title` no `compact`: página 500.
- `role` aceita só `admin` e `participant` (ENUM). Valor novo exige
  mudar o schema.
- Testes que conferem strings (`LoginCest`, `DashboardCest`,
  `AdminDashboardCest`, `UserTest`): mudou texto, mude o teste.
- `LoginHelper::logout()` clica no e-mail `fulano@example.com`.
- Em `/admin`, `Admin` roda antes de `Authenticate`. Middleware novo
  precisa tolerar usuário nulo.
- `update([...])` não valida; `save()` valida.
- `password` em usuário existente é ignorado (sem hash).
- PHPCS: linha até 130; chaves PSR-12; `./run phpcbf` arruma.
- PHPStan nível 6: tipos em parâmetros e retornos; `@return
  array<...>`; `@property` no docblock do model para coluna nova.
- PSR-4: `App\Controllers\Admin\UsersController` em
  `app/Controllers/Admin/UsersController.php`.
- Commit em inglês, `type: Title`.

## 5. Roteiro de 5 minutos ao receber o pedido

1. Repita o pedido em voz alta como "quero mudar X".
2. Ache X neste catálogo ou na seção 7 do guia completo.
3. Anote os arquivos, na ordem schema -> model -> rota -> controller
   -> view -> teste.
4. Abra o teste existente mais parecido e copie.
5. Implemente, rode o teste, confira no navegador, `./run phpcs`.
6. Commit. Mostre funcionando antes de explicar.
