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

## 6. Complemento: cenários adicionais

Avaliação aditiva feita em 15/10, cruzando o catálogo acima com: o
código entregue (`86c81ca`), a issue #9 do repositório público
("Avaliação da Entrega 4.1", aberta pelo professor em 14/10), os
comentários 2025-2, as rubricas 4.1 e 4.2, a página "Segurança" do
Moodle e o problem-track (controllers, views e testes). Nada acima foi
alterado; os itens abaixo continuam a numeração de cada letra.

O que a issue #9 diz, na íntegra:

- Vídeo: "A explicação dos testes do usuário foi feita mostrando a
  classe User ao invés da classe que testa o User. Essa parte da
  explicação ficou toda errada." e "Faltou a explicação dos
  conceitos."
- OO, reutilização: "os middlewares `Admin` e `Authenticate` possuem
  lógica duplicada de redirecionamento. Avaliem como centralizar esse
  comportamento."

Leitura para o FVP: A1 continua o item mais provável. O professor leu
`UserTest` e achou a explicação errada; espere algo em cima dele (F4).
O título "OO" indica que ele avalia reutilização e encapsulamento, não
só funcionalidade (seção H).

Lacunas reais do código entregue que um pedido "pontual" pode mirar:

- O formulário de login tem `required` e `type="email"` e não tem
  `novalidate`. Feedback 2025-2: "Não usar validações do HTML5.
  Adicionar novalidate no form". (A9)
- `authenticate` responde 500 se o POST vier sem `user[...]`. (A9)
- Nenhuma view escapa saída; um nome com `<b>` vira HTML. (C5)
- `UserTest` não cobre e-mail duplicado nem o hash da senha. (F4)
- `LoginHelper::logout()` depende do texto `fulano@example.com`. (F3)
- `Model::where` não é chamado em nenhum código da equipe; a rubrica
  4.2 cobra explicá-lo. (D5)

Se só houver 30 minutos para revisar: A9, D5, B8, F4, C5, nessa ordem.

### A9. Login com campos vazios: novalidate e controller robusto

Chance: alta (feedback 2025-2 literal). Tempo: 15 min.

Problema: o navegador bloqueia o envio com campo vazio
(`required`/`type="email"`), então a demonstração "dados incorretos"
nem chega ao servidor. E, se chegar um POST sem `user[email]`,
`$params['email']` em `null` gera warning; o `ErrorsHandler` trata
qualquer warning como erro fatal e responde 500. `findByEmail(null)`
lançaria `TypeError` (parâmetro `string`).

Arquivos: `app/views/authentications/new.phtml`,
`app/Controllers/AuthenticationsController.php`, testes.

```php
<!-- new.phtml: novalidate no form; tire o required dos inputs -->
<form action="<?= route('users.authenticate') ?>" method="post" novalidate>
```

```php
public function authenticate(Request $request): void
{
    $params = $request->getParam('user', []);
    $email = trim($params['email'] ?? '');
    $password = $params['password'] ?? '';

    $user = $email !== '' ? User::findByEmail($email) : null;

    if ($user && $user->authenticate($password)) {
        // igual ao que já existe
```

Testes. Controller:

```php
public function test_authenticate_without_params_should_not_login(): void
{
    $response = $this->post(
        action: 'authenticate',
        controllerName: 'App\Controllers\AuthenticationsController'
    );

    $this->assertFalse(Auth::check());
    $this->assertStringContainsString('Location: /login', $response);
}
```

Aceitação, em `LoginCest`:

```php
public function loginWithEmptyFields(AcceptanceTester $page): void
{
    $page->login('', '');

    $page->seeCurrentUrlEquals('/login');
    $page->see('E-mail ou senha inválidos!');
}
```

Armadilha: `findByEmail('')` não quebra (consulta sem resultado); o
que quebra é `null`. `trim` resolve e-mail com espaço no fim; letras
maiúsculas já funcionam porque o MySQL 8 compara strings sem
diferenciar caixa (collation `utf8mb4_0900_ai_ci`). Com `novalidate`
o `required` vira só estilo do Bootstrap; o problem-track mantém os
dois juntos.

### A10. Manter o e-mail digitado depois de uma falha

Chance: média (rubrica 4.2 cobra "re-render mantendo valores").
Tempo: 15 min. Pressupõe A9 (variável `$email`).

Em vez de redirecionar para `/login`, renderize o formulário de novo:

```php
} else {
    FlashMessage::danger('E-mail ou senha inválidos!');
    $title = 'Login';
    $this->render('authentications/new', compact('title', 'email'));
}
```

```php
<!-- new.phtml -->
<input type="email" class="form-control" id="user_email" name="user[email]"
       value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>">
```

O flash aparece na mesma resposta: `_flash_message.phtml` chama
`FlashMessage::get()` durante o `render`.

Testes: em `AuthenticationsControllerTest`,
`test_authenticate_with_invalid_credentials_should_not_login` deixa de
ver `Location: /login`; troque por
`assertStringContainsString('E-mail ou senha inválidos!', $response)`
e `assertStringContainsString('value="fulano@example.com"', $response)`.
`LoginCest::loginWithInvalidCredentials` continua válido (a URL depois
do POST é `/login`); acrescente
`$page->seeInField('user[email]', 'fulano@example.com')`.

Armadilha: isso abandona o Post/Redirect/Get que o problem-track usa
no login (ele redireciona). Faça só se o professor pedir para manter
o valor; se ele perguntar, diga a troca que está fazendo.

### A11. Voltar para a página pedida depois do login

Chance: média. Tempo: 20 min.

Arquivos: `app/Middleware/Authenticate.php`,
`app/Controllers/AuthenticationsController.php`, testes.

```php
// Authenticate::handle, antes de negar
if (!Auth::check()) {
    if ($request->getMethod() === 'GET') {
        $_SESSION['redirect_to'] = $request->getUri();
    }
    $this->deny('Você deve estar logado para acessar essa página',
        'users.login');
}
```

```php
// authenticate(), no caminho de sucesso
Auth::login($user);
FlashMessage::success('Login realizado com sucesso!');

$target = $_SESSION['redirect_to'] ?? route('dashboard.index');
unset($_SESSION['redirect_to']);
$this->redirectTo($target);
```

Teste de controller:

```php
public function test_authenticate_should_redirect_to_requested_page(): void
{
    $_SESSION['redirect_to'] = '/admin';

    $response = $this->post(
        action: 'authenticate',
        controllerName: 'App\Controllers\AuthenticationsController',
        params: ['user' => ['email' => 'fulano@example.com',
                            'password' => '123456']]
    );

    $this->assertStringContainsString('Location: /admin', $response);
    $this->assertArrayNotHasKey('redirect_to', $_SESSION);
}
```

Aceitação: `amOnPage('/admin')` (cai no login), `login(admin)`,
`seeCurrentUrlEquals('/admin')`.

Armadilhas: guarde só em GET; um POST reproduzido como GET dá 404.
Participante que tentou `/admin` volta para `/admin` e o `Admin` o
manda para `/dashboard` com o aviso; comportamento aceitável, explique.
`ControllerTestCase::tearDown` só limpa `$_SESSION['user']`; limpe
`redirect_to` no `tearDown` do seu teste para não vazar entre casos.
Combinando com A4: `redirect_to` tem prioridade; sem ele, decide pelo
perfil.

### A12. Middleware Admin autossuficiente (ordem dos middlewares)

Chance: média-baixa (pergunta de quem leu o comentário no `Admin`).
Tempo: 10 min. Pressupõe A1 (`deny`).

Fato verificado no `core/`: `RouteWrapperMiddleware::group` adiciona
o middleware às rotas depois de rodar o callback; o grupo interno
adiciona primeiro. Para `/admin`, a lista é `[Admin, Authenticate]`,
e `Admin` roda antes. Por isso ele tolera usuário nulo. Se o professor
pedir "proteja /admin só com o middleware admin" ou "garanta que o
Authenticate rode antes", faça o `Admin` bastar sozinho:

```php
public function handle(Request $request): void
{
    $user = Auth::user();

    if ($user === null) {
        $this->deny('Você deve estar logado para acessar essa página',
            'users.login');
        return;
    }

    if (!$user->isAdmin()) {
        $this->deny('Acesso restrito a administradores',
            'dashboard.index');
    }
}
```

O `return` depois do primeiro `deny` é o que permite o teste de
middleware (F2) sem `exit`. O grupo `admin` pode continuar aninhado em
`auth` (redundante, inofensivo) ou virar irmão dele. Testes: nenhum
muda; `accessAdminWithoutLogin` espera `/login` e a mesma mensagem.

Alternativa que o professor pode citar: inverter o aninhamento
(`admin` por fora, `auth` por dentro) faz `Authenticate` rodar
primeiro; fica estranho de ler. Mudar `group` no `core/` para
`array_unshift` é alteração de framework; combine antes.

### A13. Regenerar o id da sessão no login (session hijacking)

Chance: baixa-média (tema da página "Segurança" do Moodle).
Tempo: 10 min.

```php
// lib/Authentication/Auth.php
public static function login(User $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $user->registerLogin();
    $_SESSION['user']['id'] = $user->id;
}
```

Explicação de uma frase: o id do cookie muda no login, então um id
obtido antes (fixation) não vale depois. Não use `session_destroy()`
no logout: o flash "Logout realizado" precisa da sessão.

Testes: `AuthTest` passa sem mudar (no CLI não há sessão ativa, o
`if` pula). `LoginCest` cobre o caminho real.

Armadilha: sem o `session_status()`, `session_regenerate_id` fora de
sessão ativa gera warning nos testes.

### A14. Expirar a sessão por inatividade

Chance: baixa. Tempo: 15 min.

```php
// Auth.php
public const TIMEOUT = 1800; // segundos

public static function user(): ?User
{
    if (!isset($_SESSION['user']['id'])) {
        return null;
    }

    $lastActivity = $_SESSION['user']['last_activity'] ?? time();

    if (time() - $lastActivity > self::TIMEOUT) {
        self::logout();
        return null;
    }

    $_SESSION['user']['last_activity'] = time();

    return User::findById($_SESSION['user']['id']);
}

public static function logout(): void
{
    unset($_SESSION['user']);
}
```

Teste em `AuthTest`:

```php
public function test_check_should_be_false_after_timeout(): void
{
    Auth::login($this->user);
    $_SESSION['user']['last_activity'] = time() - Auth::TIMEOUT - 1;

    $this->assertFalse(Auth::check());
}
```

Armadilha: `Auth::check()` chama `user()`; o `logout()` precisa
apagar `$_SESSION['user']` inteiro, não só `['id']`.

### A15. Token CSRF no formulário de login

Chance: baixa (página "Segurança"; é bônus de framework). Tempo:
15 min na versão mínima, 40 min com middleware.

Versão mínima, sem middleware:

```php
<?php
// lib/Csrf.php

namespace Lib;

class Csrf
{
    public static function token(): string
    {
        $_SESSION['_token'] ??= bin2hex(random_bytes(32));
        return $_SESSION['_token'];
    }

    public static function check(?string $token): bool
    {
        return $token !== null && isset($_SESSION['_token'])
            && hash_equals($_SESSION['_token'], $token);
    }
}
```

```php
<!-- new.phtml, dentro do form -->
<input type="hidden" name="_token" value="<?= \Lib\Csrf::token() ?>">
```

```php
// authenticate(), primeira linha
if (!Csrf::check($request->getParam('_token'))) {
    FlashMessage::danger('Sessão expirada, tente de novo.');
    $this->redirectTo(route('users.login'));
}
```

Testes de controller: defina `$_SESSION['_token'] = 'abc'` no `setUp`
e mande `'_token' => 'abc'` nos params dos testes de `authenticate`.
`LoginHelper` não muda (o campo oculto vai no form). Versão geral:
middleware `VerifyCsrf` (alias `csrf`) que checa só POST/PUT/DELETE e
lança `HTTPException(..., 403)`; aí todo form (inclusive o logout de
A2) precisa do campo, e testes de acesso com POST passam a receber
403 em vez de 302. Só com escopo combinado.

### B8. Editar o próprio perfil (nome e e-mail) com save()

Chance: média-alta (ponte natural para a 4.2: formulário, validação,
re-render com erros). Tempo: 25 min. Pressupõe A6 (`/profile`).

Arquivos: `config/routes.php` (dentro de `auth`),
`app/Controllers/ProfileController.php`,
`app/views/profile/edit.phtml`, link em `profile/show.phtml`, testes.

```php
Route::get('/profile/edit', [ProfileController::class, 'edit'])
    ->name('profile.edit');
Route::put('/profile', [ProfileController::class, 'update'])
    ->name('profile.update');
```

```php
public function edit(): void
{
    $user = $this->current_user;
    $title = 'Editar Perfil';
    $this->render('profile/edit', compact('user', 'title'));
}

public function update(Request $request): void
{
    $params = $request->getParam('user', []);
    $user = $this->current_user;
    $user->name = $params['name'] ?? null;
    $user->email = $params['email'] ?? null;

    if ($user->save()) {
        FlashMessage::success('Perfil atualizado com sucesso!');
        $this->redirectTo(route('profile.show'));
    } else {
        FlashMessage::danger('Existem dados incorretos!');
        $title = 'Editar Perfil';
        $this->render('profile/edit', compact('user', 'title'));
    }
}
```

```php
<!-- app/views/profile/edit.phtml -->
<form action="<?= route('profile.update') ?>" method="post"
      class="<?= $user->hasErrors() ? 'was-validated' : '' ?>" novalidate>
    <input type="hidden" name="_method" value="PUT">
    <div class="mb-3">
        <label for="user_name" class="form-label">Nome</label>
        <input type="text" class="form-control" id="user_name"
               name="user[name]" value="<?= $user->name ?>" required>
        <div class="invalid-feedback"><?= $user->errors('name') ?></div>
    </div>
    <div class="mb-3">
        <label for="user_email" class="form-label">E-mail</label>
        <input type="email" class="form-control" id="user_email"
               name="user[email]" value="<?= $user->email ?>" required>
        <div class="invalid-feedback"><?= $user->errors('email') ?></div>
    </div>
    <button type="submit" class="btn btn-primary">Salvar</button>
</form>
```

Testes de controller (`ProfileControllerTest`, `Auth::login($this->user)`
no `setUp`):

```php
public function test_update_with_valid_data_should_redirect_to_profile(): void
{
    $response = $this->put(
        action: 'update',
        controllerName: 'App\Controllers\ProfileController',
        params: ['user' => ['name' => 'Novo Nome',
                            'email' => 'fulano@example.com']]
    );

    $this->assertStringContainsString('Location: /profile', $response);
    $this->assertEquals('Novo Nome', User::findById($this->user->id)->name);
}

public function test_update_with_empty_name_should_render_errors(): void
{
    $response = $this->put(
        action: 'update',
        controllerName: 'App\Controllers\ProfileController',
        params: ['user' => ['name' => '', 'email' => 'fulano@example.com']]
    );

    $this->assertStringContainsString('não pode ser vazio!', $response);
}
```

Terceiro caso: outro usuário salvo no `setUp`; mandar o e-mail dele;
esperar `'já existe um registro com esse dado'`. Acesso: `GET
/profile/edit` e `PUT /profile` sem sessão dão 302 para `/login`
(Guzzle tem `put`). Aceitação: logar, `amOnPage('/profile/edit')`,
`fillField('user[name]', 'Outro')`, `click('Salvar')`,
`see('Perfil atualizado com sucesso!')`.

Armadilhas: `save()` valida; `update()` não. Em registro existente,
`save()` grava todas as colunas de `$columns` com os valores do
objeto; funciona porque `$this->current_user` veio do banco inteiro.
`passwordConfirmation` não roda (só em `newRecord()`); senha é B6.
`uniqueness('email')` ignora o próprio registro se o e-mail não mudou
e acusa se mudou para um que existe. Escape os `value=""` (C5) se o
nome puder ter `"`.

### B9. Bloquear a conta depois de N tentativas erradas

Chance: baixa. Tempo: 30 min. Variante de B5 com contador.

Passos: `schema.sql` ganha `failed_logins INT NOT NULL DEFAULT 0`;
`'failed_logins'` em `$columns` e `@property int $failed_logins`;
`db:reset`.

```php
// User.php
public const MAX_FAILED_LOGINS = 3;

public function isLocked(): bool
{
    return (int) $this->failed_logins >= self::MAX_FAILED_LOGINS;
}

public function registerFailedLogin(): bool
{
    return $this->update(['failed_logins' => (int) $this->failed_logins + 1]);
}

public function registerLogin(): bool
{
    return $this->update([
        'last_login_at' => date('Y-m-d H:i:s'),
        'failed_logins' => 0,
    ]);
}
```

```php
// authenticate()
if ($user && $user->isLocked()) {
    FlashMessage::danger('Conta bloqueada por excesso de tentativas.');
    $this->redirectTo(route('users.login'));
}

if ($user && $user->authenticate($password)) {
    // ...
} else {
    $user?->registerFailedLogin();
    FlashMessage::danger('E-mail ou senha inválidos!');
    $this->redirectTo(route('users.login'));
}
```

Testes: unit (`isLocked` falso por padrão; três `registerFailedLogin`
e recarregar com `findById`: verdadeiro; `registerLogin` zera);
controller (três POSTs errados e um certo: `Auth::check()` falso e
`Location: /login`).

Armadilha: o PDO devolve números como string (prepared statements
emulados); por isso o `(int)`. Nos testes de controller o `redirectTo`
sobrescrito não interrompe a execução: o `if` do bloqueio precisa
vir antes e o resto ficar no `else`, senão o teste vê dois
`Location:`.

### C5. Escapar saída nas views (XSS)

Chance: média (página "Segurança" lista Cross-Site Scripting).
Tempo: 15 min.

Demonstração que o professor pode fazer: usuário com nome
`<b>Fulano</b>` (ou `<script>`) aparece renderizado na navbar e no
painel. Onde: `_navbar.phtml` (nome ou e-mail), `dashboard/index.phtml`,
`admin/dashboard/index.phtml`, e todo `value="..."` de formulário
(A10, B8).

```php
<?= htmlspecialchars($this->currentUser()->name, ENT_QUOTES, 'UTF-8') ?>
```

Se pedir um helper: `lib/helpers.php` com `function e(?string $value):
string` fazendo o mesmo; registrar em `composer.json`, em
`autoload.files`, ao lado de `core/helpers.php`, e rodar
`./run composer dump-autoload`. Não edite `core/helpers.php`.

Teste de controller (`DashboardControllerTest`):

```php
public function test_index_should_escape_user_name(): void
{
    $this->user->update(['name' => '<b>Fulano</b>']);
    Auth::login($this->user);

    $response = $this->get(action: 'index',
        controllerName: 'App\Controllers\DashboardController');

    $this->assertStringContainsString('&lt;b&gt;Fulano&lt;/b&gt;', $response);
    $this->assertStringNotContainsString('<b>Fulano</b>', $response);
}
```

Armadilha: PHPCS só olha `.php` e o PHPStan exclui `app/views`; erro
em `.phtml` só aparece no teste ou no navegador. Não escape duas
vezes (helper dentro de `htmlspecialchars`).

### C6. Miudezas que o professor pede como "alteração pontual"

Chance: alta (cada uma é trivial). Tempo: 5 a 10 min cada.

- Logout volta para a home: em `destroy`, `route('root')`. Ajuste
  `test_destroy_should_logout` (`Location: /`) e `LoginCest::logout`
  (`seeCurrentUrlEquals('/')`; o `see('Login')` continua valendo pela
  navbar).
- Mensagens distintas para e-mail inexistente e senha errada: separe
  o `if` em dois. `LoginCest::loginWithInvalidCredentials` confere
  "E-mail ou senha inválidos!"; ajuste. Se perguntarem, a mensagem
  única é a prática recomendada (não revela quais e-mails existem).
- `<title>` dinâmico: em `application.phtml`,
  `<title><?= $title ?> - Eventos do Câmpus</title>`. `$title` existe
  em todo `render` do projeto.
- Conteúdo por perfil na view: `<?php if
  ($this->currentUser()->isAdmin()) : ?>` em `dashboard/index.phtml`.
  Teste de controller com admin logado procurando o texto.
- Raiz manda logado para o painel: em `HomeController::index`,
  `if (Auth::check()) { $this->redirectTo(route('dashboard.index'));
  return; }`. Novo caso em `HomeControllerTest` com `Auth::login`
  esperando `Location: /dashboard`; `HomeAccessTest` e `HomeIndexCest`
  (sem sessão) não mudam.
- Renomear o perfil (`participant` para `user`): ENUM e `DEFAULT` no
  `schema.sql`, padrão no construtor de `User`,
  `test_is_admin_should_return_false_by_default`, mensagem do
  populate; `db:reset`.
- "Membro desde": `created_at` NÃO está em `$columns` de propósito.
  Se entrar, `save()` manda `NULL` explícito para uma coluna `NOT
  NULL` e o MySQL recusa. Para exibir, método no model com SELECT
  próprio (modelo em D4) ou `Database::getDatabaseConn()` direto.

### D5. Contadores com all() e where() no painel administrativo

Chance: alta (trivial, e `where` é item da rubrica 4.2 que o código
da equipe ainda não usa). Tempo: 15 min.

Arquivos: `app/Controllers/Admin/DashboardController.php`,
`app/views/admin/dashboard/index.phtml`, testes.

```php
public function index(): void
{
    $totalUsers = count(User::all());
    $totalAdmins = count(User::where(['role' => 'admin']));

    $title = 'Painel Administrativo';
    $this->render('admin/dashboard/index',
        compact('title', 'totalUsers', 'totalAdmins'));
}
```

```php
<!-- index.phtml: mantenha o "Olá, ...!" (AdminDashboardCest confere) -->
<ul>
    <li>Usuários cadastrados: <?= $totalUsers ?></li>
    <li>Administradores: <?= $totalAdmins ?></li>
</ul>
```

Se o professor quiser controller magro: `User::countByRole(string
$role): int` com `count(static::where(['role' => $role]))`, ou um
`SELECT COUNT(*)` próprio.

Teste de controller, arquivo novo
`tests/Integration/Controllers/Admin/DashboardControllerTest.php`
(namespace `Tests\Integration\Controllers\Admin`, `use
Tests\Integration\Controllers\ControllerTestCase`):

```php
public function test_index_should_show_totals(): void
{
    $admin = new User([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => '123456',
        'password_confirmation' => '123456',
        'role' => 'admin',
    ]);
    $admin->save();
    (new User([
        'name' => 'Fulano',
        'email' => 'fulano@example.com',
        'password' => '123456',
        'password_confirmation' => '123456',
    ]))->save();
    Auth::login($admin);

    $response = $this->get(action: 'index',
        controllerName: 'App\Controllers\Admin\DashboardController');

    $this->assertStringContainsString('Usuários cadastrados: 2', $response);
    $this->assertStringContainsString('Administradores: 1', $response);
}
```

Unit em `UserTest`: `test_where_should_filter_by_role` (um admin, um
participante, `count(User::where(['role' => 'admin']))` igual a 1).

Armadilha: `where([])` monta `WHERE ` vazio e quebra (guia 9). `where`
só faz igualdade com `AND`; intervalo ou `LIKE` é SQL próprio (D4).

### D6. Admin altera o perfil de um usuário (promover/rebaixar)

Chance: média. Tempo: 25 min. Pressupõe D1 (listagem).

```php
// routes.php, grupo admin
Route::put('/admin/users/{id}', [AdminUsersController::class, 'update'])
    ->name('admin.users.update');
```

```php
public function update(Request $request): void
{
    $user = User::findById((int) $request->getParam('id'));
    $role = $request->getParam('user')['role'] ?? null;

    if ($user === null || !in_array($role, ['admin', 'participant'], true)) {
        FlashMessage::danger('Não foi possível alterar o perfil.');
    } elseif ($user->id === $this->current_user->id) {
        FlashMessage::danger('Você não pode alterar o próprio perfil.');
    } else {
        $user->update(['role' => $role]);
        FlashMessage::success('Perfil alterado com sucesso!');
    }

    $this->redirectTo(route('admin.users.index'));
}
```

View (linha da tabela de D1): form `POST` com `_method=PUT`, um
`<select name="user[role]">` com as duas opções e botão "Salvar".

Testes: controller (admin logado; `put('update', ..., ['id' =>
$outro->id, 'user' => ['role' => 'admin']])`; `Location:
/admin/users`; `User::findById($outro->id)->isAdmin()` verdadeiro);
acesso (`PUT /admin/users/1` sem sessão: 302 `/login`); unit
(`update(['role' => 'admin'])` e recarregar).

Armadilhas: `update()` não valida; valor fora do ENUM vira
`PDOException` (500), daí o `in_array`. Admin que rebaixa a si mesmo
perde o acesso na hora (o `Admin` o manda para `/dashboard`); por isso
o bloqueio. Use as constantes de H2 se já existirem.

### D7. Ordenar a listagem

Chance: baixa. Tempo: 10 min.

`Model::all()` não tem `ORDER BY`. Rápido: `usort($users, fn ($a, $b)
=> strcmp($a->name, $b->name))` no controller. Certo: método no model
com SQL próprio e `ORDER BY name` (modelo em D4), e teste unitário
com dois nomes fora de ordem.

### D8. Só o dono vê e edita (prévia 4.2)

Chance: baixa hoje, alta na 4.2. Tempo: 10 min por ação.

Padrão do problem-track: `$this->current_user->events()->findById($id)`
só acha registros do usuário logado; para os outros devolve `null`.
No controller:

```php
$event = $this->current_user->events()->findById((int) $request->getParam('id'));

if ($event === null) {
    throw new HTTPException('Evento não encontrado', 404);
}
```

Teste de controller: logar outro usuário, pedir o `id` alheio,
`$this->expectException(HTTPException::class)` (o `ControllerTestCase`
relança exceções). No nosso domínio quem gerencia eventos é o
Administrador; o dono importa para Inscrição (4.4).

### F2. Teste unitário de middleware

Chance: média (depois de A1, "teste o middleware" é a continuação
natural; a rubrica 4.2 chama o teste de acesso de "teste de
controller"). Tempo: 20 min. Pressupõe A1 (`redirectTo` protegido).

O `redirectTo` original é privado e dá `exit`; não há como testar
`handle` sem subclasse. Com A1, uma classe anônima sobrescreve o
redirecionamento. Arquivo `tests/Unit/Middleware/AuthenticateTest.php`:

```php
<?php

namespace Tests\Unit\Middleware;

use App\Middleware\Authenticate;
use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Lib\Authentication\Auth;
use Lib\FlashMessage;
use Tests\TestCase;

class AuthenticateTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SESSION = [];
    }

    public function tearDown(): void
    {
        Router::reset();
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_handle_should_redirect_to_login_without_session(): void
    {
        $middleware = new class extends Authenticate {
            public ?string $location = null;

            protected function redirectTo(string $location): void
            {
                $this->location = $location;
            }
        };

        $middleware->handle(new Request());

        $this->assertEquals('/login', $middleware->location);
        $this->assertEquals(
            'Você deve estar logado para acessar essa página',
            FlashMessage::get()['danger']
        );
    }

    public function test_handle_should_not_redirect_with_session(): void
    {
        $user = new User([
            'name' => 'User 1',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $user->save();
        Auth::login($user);

        $middleware = new class extends Authenticate {
            public ?string $location = null;

            protected function redirectTo(string $location): void
            {
                $this->location = $location;
            }
        };

        $middleware->handle(new Request());

        $this->assertNull($middleware->location);
    }
}
```

Para `Admin`: mesmo molde, logando um participante e esperando
`/dashboard`; com A12, sem sessão espera `/login`.

Armadilhas: `Tests\TestCase` (cria o banco) porque `Auth::check()`
consulta `users`. `Router::reset()` + `require` das rotas é o que
faz `route('users.login')` funcionar fora do nginx. Mantenha a classe
anônima dentro do método: numa variável tipada como `Authenticate`, o
PHPStan acusa `$location` como propriedade inexistente. Depois de A12,
o `return` após o primeiro `deny` evita que o segundo `if` rode com
usuário nulo no teste.

### F3. LoginHelper::logout() sem e-mail fixo

Chance: média-baixa (infra de teste; pré-requisito de C2). Tempo:
10 min.

```php
<!-- _navbar.phtml: id no toggle do menu -->
<a class="nav-link dropdown-toggle" id="user_menu" href="#" role="button"
   data-bs-toggle="dropdown">
```

```php
// tests/Support/Helper/LoginHelper.php
public function logout(): void
{
    /** @var \Tests\Support\AcceptanceTester $page */
    $page = $this->getModule('WebDriver');
    $page->click('#user_menu');
    $page->click('Sair');
}
```

`click()` aceita seletor CSS. A assinatura não mudou, então não
precisa de `./run codecept build`. Rode `./run test:browser
tests/Acceptance/authentications/LoginCest.php`.

### F4. Testes que faltam no UserTest

Chance: alta (a issue #9 aponta o `UserTest` como o ponto fraco do
vídeo). Tempo: 10 min. Dois casos que o model já suporta e ninguém
testa:

```php
public function test_save_should_fail_with_duplicated_email(): void
{
    $user = new User([
        'name' => 'User 3',
        'email' => 'fulano@example.com', // já existe, criado no setUp
        'password' => '123456',
        'password_confirmation' => '123456',
    ]);

    $this->assertFalse($user->save());
    $this->assertEquals('já existe um registro com esse dado',
        $user->errors('email'));
    $this->assertCount(2, User::all());
}

public function test_password_should_be_stored_as_hash(): void
{
    $this->assertNotEquals('123456', $this->user->encrypted_password);
    $this->assertTrue(
        password_verify('123456', $this->user->encrypted_password)
    );
}
```

Se o professor pedir "um teste para o método X": cada método público
de `User` tem um `test_x_should_...` em `UserTest`; copie o mais
parecido. Fale sempre do arquivo de teste na tela, não do model.

### G4. Populate em classes, como no problem-track

Chance: média-baixa. Tempo: 10 min.

O problem-track tem `database/Populate/UsersPopulate.php` e
`ProblemsPopulate.php`, chamados pelo `populate.php`. O `composer.json`
já mapeia `Database\` para `database/`.

```php
<?php
// database/Populate/UsersPopulate.php

namespace Database\Populate;

use App\Models\User;

class UsersPopulate
{
    public static function populate(): void
    {
        $admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $admin->save();

        $user = new User([
            'name' => 'Usuário',
            'email' => 'user@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $user->save();

        echo "Usuários criados: {$admin->email} (admin) e {$user->email}"
            . " (participant), senha 123456\n";
    }
}
```

```php
<?php
// database/Populate/populate.php

require __DIR__ . '/../../config/bootstrap.php';

use Core\Database\Database;
use Database\Populate\UsersPopulate;

Database::migrate();
UsersPopulate::populate();
```

Depois: `./run db:populate`. Armadilha: PHPCS e PHPStan ignoram
`database/`, mas o checador PSR-4 do CI não: `Database\Populate\X` tem
que estar em `database/Populate/X.php`, com a mesma caixa. Para
eventos na 4.2, `EventsPopulate` ao lado.

## H. Orientação a objetos (a issue #9 avalia "OO")

### H1. Auth::attempt(): autenticação encapsulada em Auth

Chance: média (feedback 2025-2: "A criação da sessão, remoção e
checagem deve estar encapsulada na classe auth"). Tempo: 15 min.

```php
// lib/Authentication/Auth.php
public static function attempt(string $email, string $password): bool
{
    $user = User::findByEmail($email);

    if ($user === null || !$user->authenticate($password)) {
        return false;
    }

    self::login($user);
    return true;
}
```

```php
// authenticate(), com as variáveis de A9
if (Auth::attempt($email, $password)) {
    FlashMessage::success('Login realizado com sucesso!');
    $this->redirectTo(route('dashboard.index'));
} else {
    FlashMessage::danger('E-mail ou senha inválidos!');
    $this->redirectTo(route('users.login'));
}
```

Testes em `AuthTest`: `test_attempt_with_valid_credentials_should_login`
(`assertTrue`, `Auth::check()`) e
`test_attempt_with_invalid_credentials_should_not_login`. Os testes de
controller não mudam. Com A4, use `Auth::user()->isAdmin()` depois do
`attempt` para escolher o destino.

### H2. Constantes para o perfil (sem strings mágicas)

Chance: média-baixa. Tempo: 10 min.

```php
// User.php
public const ROLE_ADMIN = 'admin';
public const ROLE_PARTICIPANT = 'participant';

// construtor: $this->role = self::ROLE_PARTICIPANT;
// isAdmin(): return $this->role === self::ROLE_ADMIN;

public function isParticipant(): bool
{
    return $this->role === self::ROLE_PARTICIPANT;
}
```

Use nas views que comparam `role`, no populate (`User::ROLE_ADMIN`) e
no `in_array` de D6. Os testes podem continuar com as strings; o ENUM
do banco não muda. Teste unitário: `isParticipant` verdadeiro por
padrão.

### H3. Como justificar a escolha em A1

Se o professor perguntar "por que classe abstrata e não trait":
classe abstrata diz que `Authenticate` e `Admin` SÃO middlewares que
negam acesso (hierarquia, um lugar para `handle` abstrato no futuro);
trait só empresta código, sem relação entre as classes. As duas
atendem "centralizar". Mover para `core/Http/Middleware/` é a terceira
opção e conta como mudança no framework (bônus da rubrica), mas o
`core/` é do professor; combine antes.

## 7. Armadilhas adicionais (do complemento)

- POST sem `user[...]`: warning em `$params['email']`; o
  `ErrorsHandler` responde 500 para qualquer warning (não só erros).
- `created_at` e `updated_at` ficam fora de `$columns` de propósito;
  dentro, `save()` manda `NULL` explícito e o MySQL recusa.
- `ControllerTestCase::tearDown` limpa só `$_SESSION['user']`.
  Chaves novas (`redirect_to`, `_token`, `last_activity`) vazam entre
  testes do mesmo processo; limpe no seu `tearDown`.
- PDO devolve números como string (prepared statements emulados):
  `(int)` antes de somar ou comparar.
- MySQL 8 compara strings sem diferenciar caixa (`utf8mb4_0900_ai_ci`):
  `findByEmail('ADMIN@EXAMPLE.COM')` acha o usuário; só espaços
  atrapalham (`trim`).
- `session_regenerate_id()` sem sessão ativa (CLI do PHPUnit) gera
  warning; guarde com `session_status()`.
- PHPStan nível 6 não reclama de método chamado em valor possivelmente
  nulo (isso é nível 8), mas reclama de propriedade que não existe na
  classe declarada da variável (classe anônima atribuída a variável
  tipada com a classe pai).
- `htmlspecialchars` também nos `value=""` de formulários; nunca
  escape duas vezes.
- `novalidate` desliga `required` e `type="email"` no navegador; sem
  ele a demonstração "dados incorretos" com campo vazio nem chega ao
  servidor.
- Nos testes de controller o `redirectTo` sobrescrito não interrompe
  a ação: dois caminhos que redirecionam na mesma chamada deixam dois
  `Location:` na resposta. Use `return` ou `else`.
- Codeception: `click('#id')` aceita CSS; `seeInField('user[email]',
  'x')` confere o valor de um campo.

## 8. Fontes do complemento

- Issue #9 do repositório público (professor, 14/10): citada na
  íntegra na abertura da seção 6.
- Comentários 2025-2: `novalidate`; "Testes de acesso deve ser teste
  de controller"; sessão encapsulada em `Auth`; `getParams` em vez de
  `$_POST`.
- Rubrica 4.2: `where`; re-render mantendo valores válidos; "rotas no
  padrão RESTful, não só os verbos".
- Moodle, página 15 "Segurança": session hijacking, XSS, CSRF.
- Problem-track `8130d35`: `ProfileController`, `tests/Unit/Access/
  ProblemsAccessTest.php` (Guzzle), `PaginatorTest`,
  `database/Populate/*Populate.php`.
- Código da equipe em `86c81ca`: tudo que a seção 6 afirma sobre o
  estado atual foi conferido linha a linha (`required` sem
  `novalidate`, `redirectTo` privado duplicado, `where` sem uso,
  `UserTest` sem caso de e-mail duplicado, helper de logout com e-mail
  fixo).
