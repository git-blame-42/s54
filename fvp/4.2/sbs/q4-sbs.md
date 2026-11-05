# Q4 passo a passo: Perfil Organizador com CRUD só dos próprios Eventos

Pedido típico (como no exemplo): "crie o perfil Organizador. Ele entra
numa área própria, `/organizer/events`, e faz o CRUD só dos Eventos que
criou. Não entra na Área Administrativa, e o Administrador não entra na
dele. No login, o Organizador vai direto para os Eventos dele" (A7 da
4.1 aplicado ao CRUD; junta Q1/O5 com um papel novo). Chance: média.
Tempo: 35-40 min.

Exemplo pronto: `fvp/4.2/exemplos/q4-perfil-organizador/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: um commit só, a partir
da `main` (`f31006b`). Schema muda (ENUM). Duas classes novas em `app/`
(`dump-autoload`).

Variante: o catálogo sugere middleware `Staff` (admin ou organizer) nas
mesmas rotas `/admin/events`, admin vendo tudo. O exemplo dá ao
Organizador área separada, `/organizer/events`, e o admin segue igual em
`/admin/events`. ENUM, `isOrganizer()`, middleware com alias, restrição
por `events()`, navbar e populate valem nos dois caminhos. Se o pedido
for "mesma tela do admin", siga o catálogo (ver Armadilhas).

Ordem = fluxo da requisição: banco -> model -> middleware -> rotas ->
controllers -> views -> dados -> testes. Na prova, prioridade: funcionar
no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `database/schema.sql`
2. `app/Models/User.php`
3. `app/Middleware/Organizer.php` (novo)
4. `config/App.php`
5. `config/routes.php`
6. `app/Controllers/Admin/EventsController.php`
7. `app/Controllers/Organizer/EventsController.php` (novo)
8. `app/Controllers/AuthenticationsController.php`
9. `app/views/admin/events/index.phtml`
10. `app/views/admin/events/new.phtml`
11. `app/views/admin/events/edit.phtml`
12. `app/views/admin/events/_form.phtml`
13. `app/views/admin/events/show.phtml`
14. `app/views/layouts/_navbar.phtml`
15. `database/Populate/populate.php`
16. `tests/Unit/Models/Users/UserTest.php`
17. `tests/Integration/Controllers/AuthenticationsControllerTest.php`
18. `tests/Integration/Controllers/Organizer/EventsControllerTest.php` (novo)
19. `tests/Integration/Access/OrganizerEventsAccessTest.php` (novo)
20. `tests/Acceptance/organizer/OrganizerEventsCest.php` (novo)

Depois: `./run db:populate`, `./run composer dump-autoload -o
--strict-psr`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE users` (linha ~12), coluna `role`:

```sql
    encrypted_password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'organizer', 'participant') NOT NULL DEFAULT 'participant',
```

Por quê:

- Perfil mora no ENUM `role`, mesmo padrão do `admin` (A7 da 4.1).
- O model `User` não valida `role`: valor fora do ENUM só falha no
  banco.

Armadilha: ENUM sem `organizer` -> `save()` falha com `Data truncated`.
Banco de dev não se recria sozinho: `./run db:populate` no fim.

## 2. `app/Models/User.php`

a) Docblock de `events()` (linha ~50), só o texto:

```php
     * Eventos criados por este usuário (Administrador ou Organizador).
```

b) Depois de `isAdmin()` (linha ~71-74), antes de `registerLogin()`:

```php
        return $this->role === 'admin';
    }

    /**
     * Organizador: cadastra e gerencia só os próprios Eventos, em /organizer/events.
     */
    public function isOrganizer(): bool
    {
        return $this->role === 'organizer';
    }
```

Por quê:

- Pergunta sobre o perfil fica no model; middleware, controller e
  navbar só chamam `isOrganizer()`, nunca comparam string.
- `events(): HasMany` já existe (dono = `user_id`); o Organizador
  reaproveita a mesma relação.
- Sem constante `ROLES` nem `inclusion` na base: nada mais muda aqui.
  Se B10 da 4.1 estiver aplicado, acrescente `'organizer'` na lista.

## 3. `app/Middleware/Organizer.php` (novo)

Cópia de `app/Middleware/Admin.php`, trocando classe, método e
mensagem:

```php
<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class Organizer implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        // O grupo de rotas mais interno roda primeiro: sem usuário logado este middleware não faz nada e
        // o Authenticate (grupo 'auth', externo, roda depois) redireciona para /login.
        if ($user !== null && !$user->isOrganizer()) {
            FlashMessage::danger('Acesso restrito a organizadores');
            $this->redirectTo(route('dashboard.index'));
        }
    }

    private function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
```

Por quê:

- Middleware = filtro antes da ação. Barra quem está logado e não é
  Organizador (Participante **e** Administrador) -> `/dashboard`.
- `RouteWrapperMiddleware::group` roda o callback antes de acrescentar
  o próprio middleware: o grupo **interno** roda primeiro. Aqui o
  `Organizer` roda antes do `Authenticate` (A17 da 4.1).
- Por isso o `$user !== null`: sem login ele deixa passar e o
  `Authenticate`, logo depois, manda para `/login`.

Armadilha: chamar `$user->isOrganizer()` sem checar `null` -> erro 500
para visitante, em vez do redirect para `/login`.

## 4. `config/App.php`

Em `$middlewareAliases`, depois de `'admin'`:

```php
        'admin' => \App\Middleware\Admin::class,
        'organizer' => \App\Middleware\Organizer::class,
    ];
```

Por quê: `Route::middleware('organizer')` procura o nome neste array e
instancia a classe. Esquecer -> `Undefined array key "organizer"` (500)
ao carregar o `routes.php`: quebra todas as páginas, não só o grupo.

## 5. `config/routes.php`

a) `use`, depois de `HomeController`:

```php
use App\Controllers\HomeController;
use App\Controllers\Organizer\EventsController as OrganizerEventsController;
```

b) Dentro do grupo `auth`, depois do `});` que fecha o grupo `admin`
(antes do `});` final):

```php
        Route::delete('/admin/events/{id}', [AdminEventsController::class, 'destroy'])
            ->name('admin.events.destroy');
    });

    // Área do Organizador (autenticado com perfil organizer): CRUD só dos próprios Eventos.
    // Fica dentro do grupo 'auth'. O middleware do grupo interno roda antes do externo: sem login o
    // Organizer deixa passar e o Authenticate redireciona para /login.
    Route::middleware('organizer')->group(function () {
        Route::get('/organizer/events', [OrganizerEventsController::class, 'index'])->name('organizer.events.index');
        Route::get('/organizer/events/page/{page}', [OrganizerEventsController::class, 'index'])
            ->name('organizer.events.paginate');
        Route::get('/organizer/events/new', [OrganizerEventsController::class, 'new'])->name('organizer.events.new');
        Route::post('/organizer/events', [OrganizerEventsController::class, 'create'])->name('organizer.events.create');
        Route::get('/organizer/events/{id}', [OrganizerEventsController::class, 'show'])->name('organizer.events.show');
        Route::get('/organizer/events/{id}/edit', [OrganizerEventsController::class, 'edit'])
            ->name('organizer.events.edit');
        Route::put('/organizer/events/{id}', [OrganizerEventsController::class, 'update'])
            ->name('organizer.events.update');
        Route::delete('/organizer/events/{id}', [OrganizerEventsController::class, 'destroy'])
            ->name('organizer.events.destroy');
    });
});
```

Por quê:

- Cópia das 8 rotas `admin.events.*` trocando URL, nome e controller.
  Os nomes `organizer.events.<acao>` são o que o `eventRoute()` monta.
- `/new` antes de `/{id}`: senão `new` vira id.
- Grupo dentro do `auth`: precisa de login. Fora dele o `Organizer`
  não barra visitante (`Auth::user()` é `null`) e a ação roda sem
  usuário.
- `/admin` continua só `admin`: Organizador barrado lá de graça pelo
  `Admin.php`. Não "libere" o Organizador dentro do `Admin.php`.

## 6. `app/Controllers/Admin/EventsController.php`

Refatoração sem mudar o comportamento do admin: abre ganchos para a
subclasse do Organizador.

a) `use` e topo da classe (linha ~6-20):

```php
use Lib\FlashMessage;
use Lib\Paginator;

/**
 * CRUD de Eventos do Administrador (todos os Eventos). O Organizador reaproveita esta classe
 * (App\Controllers\Organizer\EventsController) trocando o prefixo das rotas e a busca dos Eventos.
 */
class EventsController extends Controller
{
    protected const PER_PAGE = 5;

    /** Prefixo dos nomes de rota (admin.events.index, admin.events.show...), usado também nas views. */
    protected const ROUTE_PREFIX = 'admin.events';

    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_on', 'location', 'capacity'];
```

b) `index()`: troque o `Event::paginate(...)` por:

```php
        $paginator = $this->paginateEvents(max(1, (int) $request->getParam('page', 1)));
        $events = $paginator->registers();
```

c) `new()` e `create()`: `new Event()` vira `$this->newEvent()`. Em
`create()`, **apague** a linha do `user_id` (agora vem no
`newEvent()`):

```php
        $event = $this->newEvent();
        $this->fill($event, $request);
-       $event->user_id = $this->currentUser()?->id;
```

d) Redirects: `route('admin.events.x', ...)` vira
`$this->eventRoute('x', ...)`. São 4: `create` e `update` (`show`),
`destroy` e `eventNotFound()` (`index`):

```php
            $this->redirectTo($this->eventRoute('show', ['id' => $event->id]));
...
        $this->redirectTo($this->eventRoute('index'));
```

e) Depois de `fill()`, troque `private function findEvent` por
`eventRoute()` + os ganchos:

```php
    /**
     * URL da ação do CRUD com o prefixo deste controller. As views chamam `$this->eventRoute('edit', [...])`.
     *
     * @param array<string, mixed> $params
     */
    public function eventRoute(string $action, array $params = []): string
    {
        return route(static::ROUTE_PREFIX . ".{$action}", $params);
    }

    /** Página da listagem: o Administrador vê todos os Eventos. */
    protected function paginateEvents(int $page): Paginator
    {
        return Event::paginate(page: $page, per_page: static::PER_PAGE, route: static::ROUTE_PREFIX . '.paginate');
    }

    /** Evento novo, já com o usuário logado como dono. */
    protected function newEvent(): Event
    {
        return new Event(['user_id' => $this->currentUser()?->id]);
    }

    protected function findEvent(Request $request): ?Event
    {
        return Event::findById((int) $request->getParam('id'));
    }
```

Por quê:

- O CRUD (validação, flash, `fill`, P02/P03) é o mesmo; muda só de
  onde vêm os Eventos e o prefixo. Herança evita copiar 150 linhas
  que divergiriam na primeira correção.
- `private` não pode ser sobrescrito: ganchos e constantes viram
  `protected`, e a leitura usa `static::` (late static binding), não
  `self::`. `FIELDS` fica `private`: só o `fill()` do pai usa.
- `eventRoute()` é `public` porque a view chama: a view roda dentro do
  `render()` do controller (`require`), então `$this` é o controller.
- `user_id` no construtor: não vem do form (`FIELDS` é allowlist), então
  `user_id` injetado no POST é ignorado.

Armadilha: conflita com quase todo cenário que mexe em `index`,
`create`, `findEvent` ou redirects. A lógica do outro cenário entra
dentro dos ganchos.

## 7. `app/Controllers/Organizer/EventsController.php` (novo)

```php
<?php

namespace App\Controllers\Organizer;

use App\Controllers\Admin\EventsController as AdminEventsController;
use App\Models\Event;
use App\Models\User;
use Core\Http\Request;
use LogicException;
use Lib\Paginator;

/**
 * CRUD de Eventos do Organizador: o mesmo do Administrador (ações, views e validações), mas
 * só com os Eventos dele (user_id = id do logado), pela relação User::events().
 */
class EventsController extends AdminEventsController
{
    protected const ROUTE_PREFIX = 'organizer.events';

    protected function paginateEvents(int $page): Paginator
    {
        return $this->owner()->events()->paginate(
            page: $page,
            per_page: static::PER_PAGE,
            route: static::ROUTE_PREFIX . '.paginate'
        );
    }

    protected function newEvent(): Event
    {
        $event = $this->owner()->events()->new();

        return $event instanceof Event ? $event : new Event();
    }

    /**
     * Evento de outro usuário vira null e cai em "Evento não encontrado!" (show, edit, update, destroy).
     */
    protected function findEvent(Request $request): ?Event
    {
        $event = $this->owner()->events()->findById((int) $request->getParam('id'));

        return $event instanceof Event ? $event : null;
    }

    private function owner(): User
    {
        $user = $this->currentUser();
        if ($user === null) {
            // Não acontece: as rotas do Organizador ficam dentro do grupo 'auth'.
            throw new LogicException('Área do Organizador acessada sem usuário logado.');
        }

        return $user;
    }
}
```

Por quê:

- Pasta `Organizer/` = namespace `App\Controllers\Organizer` (PSR-4).
  Classe nova -> `dump-autoload -o --strict-psr`.
- Troca só o prefixo e os 3 ganchos; ações e views vêm do pai.
- Restrição pela relação (`events()->findById`), como Q1/O5: Evento
  alheio nem é carregado e a resposta não revela que existe.
- `HasMany::new()`/`findById()` devolvem `Model`: o `instanceof Event`
  acalma o `phpstan`.
- `events()->new()` já põe `user_id` do dono.
- `owner()` não devolve `null`: `phpstan` sem `?->` e erro claro se a
  rota sair do `auth`.

Armadilha: `HasMany::paginate` usa `per_page` (com `_`) como nome do
argumento.

## 8. `app/Controllers/AuthenticationsController.php`

`authenticate()`, depois de `FlashMessage::success('Login realizado...')`:

```php
            // Organizador cai direto nos próprios Eventos; os demais perfis, no painel.
            $this->redirectTo($user->isOrganizer() ? route('organizer.events.index') : route('dashboard.index'));
```

Por quê: o pedido inclui "no login, vai direto para os Eventos dele"
(A9/A18 da 4.1). Sem isso o Organizador cai em `/dashboard`.

## 9. `app/views/admin/events/index.phtml`

Botão do topo e os 3 links da linha da tabela:

```php
    <a class="btn btn-primary" href="<?= $this->eventRoute('new') ?>">Novo Evento</a>
...
                    <td>
                        <a class="btn btn-sm btn-outline-secondary"
                            href="<?= $this->eventRoute('show', ['id' => $event->id]) ?>">Ver</a>
                        <a class="btn btn-sm btn-outline-primary"
                            href="<?= $this->eventRoute('edit', ['id' => $event->id]) ?>">Editar</a>
                        <form class="d-inline" action="<?= $this->eventRoute('destroy', ['id' => $event->id]) ?>" method="post">
```

Por quê:

- Mesmas views para os dois controllers. `route('admin.events.x')` fixo
  mandaria o Organizador para `/admin`, onde o middleware barra.
- `$this->eventRoute('x')` usa o `ROUTE_PREFIX` de quem renderizou.
- A paginação já sai certa: o `Paginator` recebe a rota
  `<prefixo>.paginate` no `paginateEvents()`.

## 10. `app/views/admin/events/new.phtml`

```php
$action = $this->eventRoute('create');
```

## 11. `app/views/admin/events/edit.phtml`

```php
$action = $this->eventRoute('update', ['id' => $event->id]);
```

Por quê (10 e 11): `$action` é o `action` do `<form>` no `_form.phtml`;
o Organizador tem que postar em `/organizer/events`.

## 12. `app/views/admin/events/_form.phtml`

Botão "Cancelar", no fim do form:

```php
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a class="btn btn-secondary" href="<?= $this->eventRoute('index') ?>">Cancelar</a>
```

## 13. `app/views/admin/events/show.phtml`

Botões do fim (linha ~35):

```php
<a class="btn btn-primary" href="<?= $this->eventRoute('edit', ['id' => $event->id]) ?>">Editar</a>
<a class="btn btn-secondary" href="<?= $this->eventRoute('index') ?>">Voltar</a>
```

Por quê (12 e 13): mesmo motivo do `index`. Procure todo
`route('admin.events` nas views: nenhum pode sobrar.

## 14. `app/views/layouts/_navbar.phtml`

Depois do `<?php endif; ?>` do bloco `isAdmin()` (linha ~20), antes do
`dropdown`:

```php
                <?php endif; ?>
                <?php if ($this->currentUser()->isOrganizer()) : ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('organizer.events.index') ?>">Meus Eventos</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
```

Por quê:

- Link "Meus Eventos" só para Organizador; "Administração" e "Eventos"
  seguem só para admin.
- Aqui é `route(...)` fixo, não `eventRoute()`: a navbar é do layout,
  renderizada por qualquer controller.

Armadilha: `isAdmin()`/`isOrganizer()` espalhados (navbar, login) =
custo de não centralizar o perfil (H2 da 4.1).

## 15. `database/Populate/populate.php`

a) Depois de `$user->save();` (Participante), troque o `echo`:

```php
$organizer = new User([
    'name' => 'Organizadora',
    'email' => 'organizador@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'role' => 'organizer'
]);
$organizer->save();

echo "Usuários criados: {$admin->email} (admin), {$organizer->email} (organizer) e {$user->email} (participant), "
    . "senha 123456\n";
```

b) Antes do `foreach ($events ...)` (linha ~132), e no próprio `foreach`
troque `'user_id' => $admin->id`:

```php
// Eventos 10 e 13 (índices 9 e 12, sem Inscrições) pertencem ao Organizador; os demais, ao Administrador.
// O total continua 16, e o Administrador continua vendo todos na listagem dele.
$organizerEventIndexes = [9, 12];

$eventModels = [];
foreach ($events as $index => [$title, $description, $kind, $modality, $audience, $daysAhead, $location, $capacity]) {
    $owner = in_array($index, $organizerEventIndexes, true) ? $organizer : $admin;
    $event = new Event([
        'user_id' => $owner->id,
```

c) `echo` depois do `foreach`:

```php
echo count($eventModels) . ' Eventos criados (' . count($organizerEventIndexes) . " do Organizador)\n";
```

Por quê:

- Login de teste no navegador: `organizador@example.com` / `123456`.
- Eventos sem Inscrições: não mexe nas Inscrições (14) nem no Evento 1
  lotado, que outros testes e cenários usam.
- `$index =>` no `foreach`: o array é destruturado por posição; não
  mexa nas linhas de dados.

## 16. `tests/Unit/Models/Users/UserTest.php`

Depois de `test_is_admin_should_return_true_for_admin_role` (linha
~141-146):

```php
    public function test_is_organizer_should_return_false_by_default(): void
    {
        $this->assertFalse($this->user->isOrganizer());
    }

    public function test_is_organizer_should_return_true_only_for_organizer_role(): void
    {
        $organizer = new User([
            'name' => 'Organizadora',
            'email' => 'organizador@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'organizer',
        ]);
        $this->assertTrue($organizer->save());

        $found = User::findById($organizer->id);
        $this->assertNotNull($found);
        $this->assertSame('organizer', $found->role);
        $this->assertTrue($found->isOrganizer());
        $this->assertFalse($found->isAdmin());

        $this->user->role = 'admin';
        $this->assertFalse($this->user->isOrganizer());
    }
```

Por quê: `save()` + `findById` prova que o ENUM aceita `organizer`
(sem isso, `Data truncated`). Padrão = testes de `isAdmin()`.

## 17. `tests/Integration/Controllers/AuthenticationsControllerTest.php`

Depois de `test_authenticate_with_valid_credentials_should_login`
(linha ~33-42):

```php
    public function test_authenticate_organizer_should_redirect_to_organizer_events(): void
    {
        $organizer = new User([
            'name' => 'Organizadora',
            'email' => 'organizador@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'organizer',
        ]);
        $organizer->save();

        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'organizador@example.com', 'password' => '123456']]
        );

        $this->assertTrue(Auth::check());
        $this->assertSame('Location: /organizer/events', $response);
    }
```

Por quê: o `ControllerTestCase` captura o `header('Location: ...')` e
devolve como string; prova o redirect por perfil do item 8.

## 18. `tests/Integration/Controllers/Organizer/EventsControllerTest.php` (novo)

```php
<?php

namespace Tests\Integration\Controllers\Organizer;

use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class EventsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\Organizer\EventsController';

    private User $organizer;
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $this->admin = $this->createUser('Admin', 'admin@example.com', 'admin');
        $this->organizer = $this->createUser('Organizadora', 'organizador@example.com', 'organizer');
        Auth::login($this->organizer);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_index_lists_only_the_organizer_events_with_organizer_links(): void
    {
        $mine = $this->createEvent($this->organizer, ['title' => 'Meu Evento']);
        $this->createEvent($this->admin, ['title' => 'Evento do Admin']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Meu Evento', $response);
        $this->assertStringNotContainsString('Evento do Admin', $response);
        $this->assertStringContainsString('Mostrando 1 - 1 de 1', $response);
        $this->assertStringContainsString('href="/organizer/events/new"', $response);
        $this->assertStringContainsString("href=\"/organizer/events/{$mine->id}\"", $response);
        $this->assertStringContainsString("href=\"/organizer/events/{$mine->id}/edit\"", $response);
        $this->assertStringContainsString("action=\"/organizer/events/{$mine->id}\"", $response);
        $this->assertStringNotContainsString('/admin/events', $response);
    }

    public function test_index_paginates_only_the_organizer_events(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createEvent($this->admin, ['title' => "Alheio {$i}"]);
        }
        for ($i = 1; $i <= 6; $i++) {
            $this->createEvent($this->organizer, ['title' => "Próprio {$i}"]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertStringContainsString('Mostrando 1 - 5 de 6', $page1);
        $this->assertStringContainsString('/organizer/events/page/2', $page1);
        $this->assertStringContainsString('Mostrando 6 - 6 de 6', $page2);
        $this->assertStringContainsString('Próprio 6', $page2);
        $this->assertStringNotContainsString('Alheio', $page1 . $page2);
    }

    public function test_new_renders_form_posting_to_organizer_route(): void
    {
        $response = $this->get(action: 'new', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('action="/organizer/events"', $response);
        $this->assertStringContainsString('href="/organizer/events">Cancelar', $response);
    }

    public function test_create_saves_event_owned_by_the_organizer(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['user_id' => (string) $this->admin->id])]
        );

        $event = Event::findBy(['title' => 'Oficina do Organizador']);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals($this->organizer->id, $event->user_id);
        $this->assertSame("Location: /organizer/events/{$event->id}", $response);
        $this->assertEquals('Evento cadastrado com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_create_with_invalid_data_renders_form_again(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['title' => ''])]
        );

        $this->assertCount(0, Event::all());
        $this->assertStringContainsString('action="/organizer/events"', $response);
        $this->assertStringContainsString('não pode ser vazio!', $response);
    }

    public function test_show_edit_and_update_of_own_event(): void
    {
        $event = $this->createEvent($this->organizer, ['title' => 'Antes']);

        $show = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $edit = $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $update = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Depois'])]
        );

        $this->assertStringContainsString("href=\"/organizer/events/{$event->id}/edit\"", $show);
        $this->assertStringContainsString("action=\"/organizer/events/{$event->id}\"", $edit);
        $this->assertSame("Location: /organizer/events/{$event->id}", $update);
        $this->assertEquals('Depois', Event::findById($event->id)?->title);
    }

    public function test_destroy_of_own_event_removes_it(): void
    {
        $event = $this->createEvent($this->organizer);

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNull(Event::findById($event->id));
        $this->assertSame('Location: /organizer/events', $response);
        $this->assertEquals('Evento removido com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_show_and_edit_of_another_user_event_redirect_to_index(): void
    {
        $event = $this->createEvent($this->admin, ['title' => 'Evento do Admin']);

        foreach (['show', 'edit'] as $action) {
            $response = $this->get(action: $action, controllerName: self::CONTROLLER, params: ['id' => $event->id]);

            $this->assertSame('Location: /organizer/events', $response, $action);
            $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger'], $action);
        }
    }

    public function test_update_and_destroy_of_another_user_event_do_not_change_it(): void
    {
        $event = $this->createEvent($this->admin, ['title' => 'Evento do Admin']);

        $update = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Invadido'])]
        );
        $destroy = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame('Location: /organizer/events', $update);
        $this->assertSame('Location: /organizer/events', $destroy);
        $this->assertEquals('Evento do Admin', Event::findById($event->id)?->title);
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validParams(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Oficina do Organizador',
            'description' => 'Oficina criada pelo Organizador.',
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => ['students'],
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Bloco A',
            'capacity' => '20',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(User $owner, array $overrides = []): Event
    {
        $params = array_merge($this->validParams(), $overrides);
        $audience = $params['audience'];
        unset($params['audience']);

        $event = new Event($params + ['user_id' => $owner->id]);
        $event->setAudienceList($audience);
        $event->save();

        return $event;
    }
}
```

Por quê:

- Chama as ações direto (sem HTTP, sem middleware): testa os ganchos e
  o `eventRoute()`.
- Listagem e paginação só dos dele, com links `/organizer/...` e
  nenhum `/admin/events` no HTML (prova a troca nas views).
- `create` com `user_id` do admin no POST: grava o Organizador como
  dono (`FIELDS` ignora).
- Evento do admin: `show`/`edit`/`update`/`destroy` -> "Evento não
  encontrado!" e nada muda (Q1).
- Classe nova em `tests/`: sem `dump-autoload`.

## 19. `tests/Integration/Access/OrganizerEventsAccessTest.php` (novo)

```php
<?php

namespace Tests\Integration\Access;

use App\Models\Event;
use App\Models\User;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rotas organizer.events.*: só o Organizador entra. Sem sessão vai para /login; Participante e
 * Administrador são barrados e vão para /dashboard. O Organizador também não entra em /admin.
 */
class OrganizerEventsAccessTest extends TestCase
{
    private const PASSWORD = '123456';

    /** @var array<string, User> */
    private array $users = [];
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->users['admin'] = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->users['participant'] = $this->createUser('Participante', 'participante@example.com', 'participant');
        $this->users['organizer'] = $this->createUser('Organizadora', 'organizador@example.com', 'organizer');

        $this->event = new Event([
            'user_id' => $this->users['organizer']->id,
            'title' => 'Evento da Organizadora',
            'description' => 'Evento usado nos testes de acesso.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Microsoft Teams',
            'capacity' => 10,
        ]);
        $this->event->save();
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    private function newClient(): Client
    {
        return new Client(['allow_redirects' => false, 'base_uri' => 'http://web:8080', 'cookies' => true]);
    }

    private function loggedClient(User $user): Client
    {
        $client = $this->newClient();
        $client->post('/login', [
            'form_params' => ['user' => ['email' => $user->email, 'password' => self::PASSWORD]],
        ]);

        return $client;
    }

    /**
     * As 8 rotas organizer.events.* (PUT e DELETE com _method, como o navegador envia).
     *
     * @return array<string, array{string, string, array<string, string>}>
     */
    public static function organizerRoutes(): array
    {
        return [
            'organizer.events.index' => ['GET', '/organizer/events', []],
            'organizer.events.paginate' => ['GET', '/organizer/events/page/2', []],
            'organizer.events.new' => ['GET', '/organizer/events/new', []],
            'organizer.events.create' => ['POST', '/organizer/events', []],
            'organizer.events.show' => ['GET', '/organizer/events/1', []],
            'organizer.events.edit' => ['GET', '/organizer/events/1/edit', []],
            'organizer.events.update' => ['POST', '/organizer/events/1', ['_method' => 'PUT']],
            'organizer.events.destroy' => ['POST', '/organizer/events/1', ['_method' => 'DELETE']],
        ];
    }

    /**
     * @param array<string, string> $form
     */
    #[DataProvider('organizerRoutes')]
    public function test_organizer_route_should_redirect_to_login_without_session(string $method, string $uri, array $form): void
    {
        $response = $this->newClient()->request($method, $uri, ['form_params' => $form]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, string> $form
     */
    #[DataProvider('organizerRoutes')]
    public function test_organizer_route_should_redirect_participant_and_admin_to_dashboard(
        string $method,
        string $uri,
        array $form
    ): void {
        foreach (['participant', 'admin'] as $role) {
            $response = $this->loggedClient($this->users[$role])->request($method, $uri, ['form_params' => $form]);

            $this->assertEquals(302, $response->getStatusCode(), $role);
            $this->assertEquals('/dashboard', $response->getHeaderLine('Location'), $role);
        }

        $this->assertEquals('Evento da Organizadora', Event::findById($this->event->id)?->title);
        $this->assertCount(1, Event::all());
    }

    public function test_organizer_should_access_own_events(): void
    {
        $client = $this->loggedClient($this->users['organizer']);

        $index = $client->get('/organizer/events');
        $this->assertEquals(200, $index->getStatusCode());
        $this->assertStringContainsString('Evento da Organizadora', (string) $index->getBody());
        $this->assertStringContainsString('Meus Eventos', (string) $index->getBody());

        $show = $client->get("/organizer/events/{$this->event->id}");
        $this->assertEquals(200, $show->getStatusCode());
    }

    public function test_organizer_should_not_access_admin_area(): void
    {
        $client = $this->loggedClient($this->users['organizer']);

        foreach (['/admin', '/admin/events', "/admin/events/{$this->event->id}"] as $uri) {
            $response = $client->get($uri);

            $this->assertEquals(302, $response->getStatusCode(), $uri);
            $this->assertEquals('/dashboard', $response->getHeaderLine('Location'), $uri);
        }
    }

    public function test_organizer_login_should_redirect_to_organizer_events(): void
    {
        $response = $this->newClient()->post('/login', [
            'form_params' => ['user' => ['email' => 'organizador@example.com', 'password' => self::PASSWORD]],
        ]);

        $this->assertEquals('/organizer/events', $response->getHeaderLine('Location'));
    }
}
```

Por quê:

- Teste de acesso bate por HTTP (Guzzle em `http://web:8080`): é o
  único do PHPUnit que passa pelos middlewares de verdade.
- Provider com as 8 rotas: sem sessão -> `/login` (prova a ordem
  `Organizer` antes de `Authenticate` + checagem de `null`);
  Participante e admin -> `/dashboard`, Evento intacto.
- Organizador entra nos dele (200, link "Meus Eventos") e é barrado em
  `/admin` (middleware `Admin`, sem mudança).
- `allow_redirects => false`: lê o `Location` em vez de seguir.

## 20. `tests/Acceptance/organizer/OrganizerEventsCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\organizer;

use App\Models\Event;
use App\Models\User;
use PHPUnit\Framework\Assert;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class OrganizerEventsCest extends BaseAcceptanceCest
{
    private User $organizer;

    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        $admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->organizer = $this->createUser('Organizadora', 'organizador@example.com', 'organizer');

        $this->createEvent($this->organizer, 'Oficina da Organizadora');
        $this->createEvent($admin, 'Palestra do Administrador');

        $page->resizeWindow(1280, 2400);
    }

    public function organizerLoginListsOnlyOwnEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Login do Organizador e listagem só dos Eventos dele');
        $page->login('organizador@example.com', '123456');

        $page->seeCurrentUrlEquals('/organizer/events');
        $page->see('Login realizado com sucesso!');
        $page->seeLink('Meus Eventos', '/organizer/events');
        $page->dontSeeLink('Administração');
        $page->see('Oficina da Organizadora', 'table');
        $page->dontSee('Palestra do Administrador');
        $page->see('Mostrando 1 - 1 de 1');
    }

    public function organizerCreatesEvent(AcceptanceTester $page): void
    {
        $page->wantTo('Organizador cadastra um Evento e ele fica como dono');
        $page->login('organizador@example.com', '123456');

        $page->click('Novo Evento');
        $page->seeCurrentUrlEquals('/organizer/events/new');
        $page->fillField('#event_title', 'Minicurso da Organizadora');
        $page->fillField('#event_description', 'Criado pela Organizadora.');
        $page->selectOption('#event_kind', Event::KINDS['short_course']);
        $page->checkOption('#event_modality_online');
        $page->checkOption('#event_audience_students');
        $page->executeJS(
            "document.getElementById('event_starts_on').value = arguments[0];",
            [date('Y-m-d', strtotime('+5 days'))]
        );
        $page->fillField('#event_location', 'Google Meet');
        $page->fillField('#event_capacity', '30');
        $page->click('Salvar');

        $event = Event::findBy(['title' => 'Minicurso da Organizadora']);
        Assert::assertNotNull($event);
        Assert::assertEquals($this->organizer->id, $event->user_id);
        $page->seeCurrentUrlEquals("/organizer/events/{$event->id}");
        $page->see('Evento cadastrado com sucesso!');

        $page->click('Voltar');
        $page->seeCurrentUrlEquals('/organizer/events');
        $page->see('Mostrando 1 - 2 de 2');
    }

    public function organizerCannotOpenAdminArea(AcceptanceTester $page): void
    {
        $page->wantTo('Organizador não entra na Área Administrativa');
        $page->login('organizador@example.com', '123456');
        $page->amOnPage('/admin/events');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Acesso restrito a administradores');
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    private function createEvent(User $owner, string $title): void
    {
        $event = new Event([
            'user_id' => $owner->id,
            'title' => $title,
            'description' => "Descrição de {$title}.",
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+20 days')),
            'location' => 'Sala 1',
            'capacity' => 20,
        ]);
        Assert::assertTrue($event->save());
    }
}
```

Por quê:

- Herda `BaseAcceptanceCest`, não `BaseEventsCest`: este loga
  `admin@` e semeia 16 Eventos; aqui o login é do Organizador
  (`LoginHelper::login`) e a base é mínima (1 dele, 1 do admin).
- Três casos no navegador real: login cai na lista só dos dele, sem
  "Administração"; cadastro grava o dono e volta para
  `/organizer/events`; `/admin/events` barrado.
- Os Cests do admin seguem válidos (logam `admin@`).

## Fechamento

```sh
./run db:populate                               # ENUM novo + organizador@
./run composer dump-autoload -o --strict-psr    # Organizer.php e Organizer/EventsController.php
./run test tests/Unit/Models/Users/UserTest.php
./run test tests/Integration/Controllers/Organizer/EventsControllerTest.php
./run test tests/Integration/Access/OrganizerEventsAccessTest.php
./run test              # Unit + Integration (o PHPUnit não roda os Cests)
docker compose up -d selenium && sleep 5
./run test:browser      # Acceptance
docker compose stop selenium
./run phpcs
./run phpstan
```

Resultado do exemplo: Unit 161, Integration 74, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros.

No navegador:

- `organizador@example.com` / `123456` -> cai em `/organizer/events`,
  2 Eventos ("Mostrando 1 - 2 de 2"), link "Meus Eventos", sem
  "Administração". Ver/Editar/Remover/Novo ficam em `/organizer/...`.
- Como Organizador, `/admin/events` -> painel com "Acesso restrito a
  administradores".
- `admin@example.com`: `/organizer/events` -> "Acesso restrito a
  organizadores"; `/admin/events` mostra os 16.

Commit (inglês): `feat: Add organizer role with its own events area`.

## Armadilhas (resumo)

- ENUM novo exige `./run db:populate`; ENUM sem `organizer` -> `save()`
  falha com `Data truncated` (model não valida `role`). Com B10 da 4.1
  (`ROLES` + `inclusion`), ponha o valor na lista ou o populate falha.
- Esquecer o alias em `config/App.php`: `Undefined array key` (500)
  em todas as páginas (o `routes.php` roda em toda requisição).
- Grupo `organizer` fora do `auth`: visitante passa e a ação roda sem
  usuário.
- Grupo interno roda primeiro: `Organizer` sem checar `null` -> erro
  500 em vez de `/login`.
- Não "libere" o Organizador dentro do `Admin.php`: mistura
  responsabilidades; crie o middleware.
- Views reaproveitadas com `route('admin.events...')`: links do
  Organizador apontam para `/admin` e o middleware barra. Use
  `$this->eventRoute(...)`.
- Ganchos `private` não são sobrescritos: `protected` + `static::`.
- `HasMany::paginate` usa `per_page` (com `_`).
- Esquecer `dump-autoload -o` depois das classes novas.
- Login sem ajuste (`AuthenticationsController::authenticate`):
  Organizador cai em `/dashboard`.
- Cest de Organizador precisa de login próprio (`LoginHelper::login`);
  os do admin logam `admin@` (`BaseEventsCest:46`).
- `isAdmin()`/`isOrganizer()` espalhados (navbar, login): custo de não
  centralizar o perfil (H2 da 4.1); `manageEvents()` ou
  `User::homeRoute()` concentrariam a decisão.
- Variante do catálogo (mesma tela do admin): middleware `Staff` com
  `!$user->manageEvents()` (`isAdmin() || isOrganizer()`), rotas de
  Eventos fora do grupo `admin`, e no `EventsController` admin ->
  `Event::paginate`/`new Event`, organizer -> `events()->...`.
