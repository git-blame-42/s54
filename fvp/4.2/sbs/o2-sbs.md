# O2 passo a passo: Listagem e detalhe de Eventos para o Participante

Pedido típico: "o Participante precisa ver os Eventos" (`/events` e
`/events/{id}`, só leitura, paginado, sem Novo/Editar/Remover, link na
navbar). Chance: média-alta. Tempo: 25-35 min. Pré-requisito: nenhum
(parte de `main`). É a base de P2 (inscrição).

Exemplo pronto: `fvp/4.2/exemplos/o2-eventos-para-participante/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Ordem = fluxo da requisição: rota -> controller -> views -> navbar ->
testes. Sem banco, sem model (schema não muda). Na prova, prioridade:
funcionar no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `config/routes.php`
2. `app/Controllers/EventsController.php` (novo)
3. `app/views/events/index.phtml` (novo)
4. `app/views/events/show.phtml` (novo)
5. `app/views/layouts/_navbar.phtml`
6. `tests/Integration/Controllers/EventsControllerTest.php` (novo)
7. `tests/Integration/Access/ParticipantEventsAccessTest.php` (novo)
8. `tests/Acceptance/events/BaseParticipantEventsCest.php` (novo)
9. `tests/Acceptance/events/ParticipantListEventsCest.php` (novo)

Depois: `./run composer dump-autoload` (classe nova), suíte, `./run phpcs`.

## 1. `config/routes.php`

a) `use` no topo, entre `DashboardController` e `HomeController`:

```php
use App\Controllers\Admin\EventsController as AdminEventsController;
use App\Controllers\AuthenticationsController;
use App\Controllers\DashboardController;
use App\Controllers\EventsController;
use App\Controllers\HomeController;
```

b) Dentro de `Route::middleware('auth')->group(...)`, depois da rota
`/dashboard` e **antes** do grupo `admin`:

```php
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Eventos do Participante (só leitura). 'page' vem antes de '{id}'.
    Route::get('/events', [EventsController::class, 'index'])->name('events.index');
    Route::get('/events/page/{page}', [EventsController::class, 'index'])->name('events.paginate');
    Route::get('/events/{id}', [EventsController::class, 'show'])->name('events.show');

    // Área administrativa (somente autenticados com perfil admin)
    Route::middleware('admin')->group(function () {
```

Por quê:

- Grupo `auth` = qualquer logado (Participante e admin). Grupo `admin`
  aninhado barra quem não é admin. Rota do Participante tem que ficar
  só no `auth`.
- `{page}` e `{id}` viram parâmetros do `Request`
  (`$request->getParam('page')`).
- Nome `events.paginate` é o padrão que o `Paginator` usa
  (`"$table.paginate"`); declare com esse nome.
- `page/{page}` antes de `{id}`: mesma convenção do admin (`new` antes
  de `{id}`), rota literal antes da genérica.
- Dois `EventsController`: o admin já é importado com alias
  `AdminEventsController`; o novo entra sem alias.

## 2. `app/Controllers/EventsController.php` (novo)

Cópia de `index`/`show` do `app/Controllers/Admin/EventsController.php`,
namespace `App\Controllers` (não `Admin`), outra rota e outra view:

```php
<?php

namespace App\Controllers;

use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Eventos na área do usuário (Participante): só listagem e detalhe, sem ações de administração.
 */
class EventsController extends Controller
{
    private const PER_PAGE = 5;

    public function index(Request $request): void
    {
        $paginator = Event::paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: self::PER_PAGE,
            route: 'events.paginate'
        );
        $events = $paginator->registers();

        $title = 'Eventos';
        $this->render('events/index', compact('events', 'paginator', 'title'));
    }

    public function show(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('events.index'));
            return;
        }

        $title = htmlspecialchars($event->title);
        $this->render('events/show', compact('event', 'title'));
    }
}
```

Por quê:

- Controller separado: o middleware `admin` protege o controller do
  admin inteiro; misturar ações de Participante ali obrigaria a tirar a
  proteção ou duplicar rotas.
- Namespace `App\Controllers` + arquivo em `app/Controllers/` = PSR-4.
  Classe nova -> `dump-autoload`.
- `route: 'events.paginate'`: o `_pages.phtml` monta os links com
  `$paginator->getRouteName()`. Copiar o `route: 'admin.events.paginate'`
  do admin sem trocar: os links da paginação vão para
  `/admin/events/page/N` e o Participante cai em `/dashboard`. (Sem
  `route:` nenhum, o padrão `"$table.paginate"` já daria
  `events.paginate`.)
- `$title` no `compact`: o layout imprime `<?= $title ?>` no `<h1>`.
  Esquecer -> 500.
- `return` depois do `redirectTo`: em produção ele já dá `exit`, mas no
  `ControllerTestCase` o `redirectTo` só imprime `Location: ...`; sem o
  `return`, o teste segue e renderiza com `null`.
- Controller fino: só `getParam`, `FlashMessage`, `redirectTo(route())`
  e `render`.

## 3. `app/views/events/index.phtml` (novo)

Cópia de `app/views/admin/events/index.phtml` **sem** o bloco "Novo
Evento" do topo e sem "Editar"/"Remover"; "Ver" aponta para
`events.show`:

```php
<?php if (empty($events)) : ?>
    <p>Nenhum evento cadastrado.</p>
<?php else : ?>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Título</th>
                <th>Tipo</th>
                <th>Data</th>
                <th>Local</th>
                <th>Vagas</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event) : ?>
                <tr>
                    <td><?= htmlspecialchars($event->title) ?></td>
                    <td><?= \App\Models\Event::KINDS[$event->kind] ?? htmlspecialchars($event->kind) ?></td>
                    <td><?= date('d/m/Y', strtotime($event->starts_on)) ?></td>
                    <td><?= htmlspecialchars($event->location) ?></td>
                    <td><?= $event->enrollmentsCount() ?>/<?= (int) $event->capacity ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary"
                            href="<?= route('events.show', ['id' => $event->id]) ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php $paginator->renderPagesNavigation(); ?>
<?php endif; ?>
```

Por quê:

- View própria em vez de `if isAdmin()` na do admin: o Participante
  nunca recebe HTML com link de edição; testes do admin intactos.
- View burra: só exibe `$events`/`$paginator` vindos do `render`.
- `htmlspecialchars` em texto (XSS); `(int)` em número.
- `renderPagesNavigation()` usa o `_pages.phtml`, que só enxerga
  `$paginator`; a rota vem do `route:` do controller.

## 4. `app/views/events/show.phtml` (novo)

Cópia de `app/views/admin/events/show.phtml` sem "Editar"/"Remover";
"Voltar" para `events.index`:

```php
<?php
use App\Models\Event;

$audience = array_map(fn(string $value) => Event::AUDIENCES[$value] ?? $value, $event->audienceList());
?>
<dl class="row">
    <dt class="col-sm-3">Título</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($event->title) ?></dd>

    <dt class="col-sm-3">Descrição</dt>
    <dd class="col-sm-9"><?= nl2br(htmlspecialchars($event->description)) ?></dd>

    <dt class="col-sm-3">Tipo</dt>
    <dd class="col-sm-9"><?= Event::KINDS[$event->kind] ?? htmlspecialchars($event->kind) ?></dd>

    <dt class="col-sm-3">Modalidade</dt>
    <dd class="col-sm-9"><?= Event::MODALITIES[$event->modality] ?? htmlspecialchars($event->modality) ?></dd>

    <dt class="col-sm-3">Público-alvo</dt>
    <dd class="col-sm-9"><?= htmlspecialchars(implode(', ', $audience)) ?></dd>

    <dt class="col-sm-3">Data</dt>
    <dd class="col-sm-9"><?= date('d/m/Y', strtotime($event->starts_on)) ?></dd>

    <dt class="col-sm-3">Local</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($event->location) ?></dd>

    <dt class="col-sm-3">Vagas</dt>
    <dd class="col-sm-9">
        <?= $event->enrollmentsCount() ?>/<?= (int) $event->capacity ?>
        (<?= $event->availableSpots() ?> disponíveis)
    </dd>
</dl>

<a class="btn btn-secondary" href="<?= route('events.index') ?>">Voltar</a>
```

Por quê:

- `show` recebe só `$event` (via `findById`) e exibe.
- "Voltar" copiado sem trocar aponta para `admin.events.index` e
  devolve o Participante para `/dashboard`.
- É aqui que P2 põe o botão "Inscrever-se" e P4 o form de presença.

## 5. `app/views/layouts/_navbar.phtml`

No `if ($this->currentUser()->isAdmin())`, depois do `<li>` do link
`admin.events.index`, antes do `endif`:

```php
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('admin.events.index') ?>">Eventos</a>
                    </li>
                <?php else : ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('events.index') ?>">Eventos</a>
                    </li>
                <?php endif; ?>
```

Por quê:

- `else`: Participante vê "Eventos" -> `/events`; admin continua com o
  "Eventos" dele (`/admin/events`), sem dois links iguais. Admin ainda
  acessa `/events` pela URL.
- Alternativa válida (catálogo): link fora do `if`, para todo logado.
- `route('nome')` gera a URL; nunca escreva `/events` à mão.

## 6. `tests/Integration/Controllers/EventsControllerTest.php` (novo)

Namespace `Tests\Integration\Controllers` (não colide com o do admin,
que está em `...\Controllers\Admin`). `setUp` loga um **Participante**:

```php
<?php

namespace Tests\Integration\Controllers;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;

/**
 * Eventos na área do Participante (/events): listagem paginada e detalhe, sem ações de administração.
 */
class EventsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\EventsController';

    private User $admin;
    private User $participant;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Participante', 'participante@example.com', 'participant');
        Auth::login($this->participant);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_index_lists_five_events_per_page_without_admin_actions(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertMatchesRegularExpression('/<h1>\s*Eventos\s*<\/h1>/', $page1);
        $this->assertStringContainsString('Evento 1', $page1);
        $this->assertStringContainsString('Evento 5', $page1);
        $this->assertStringNotContainsString('Evento 6', $page1);
        $this->assertStringContainsString('href="/events/page/2"', $page1);
        $this->assertStringContainsString('Evento 6', $page2);
        $this->assertStringNotContainsString('Evento 5', $page2);

        $this->assertStringNotContainsString('Novo Evento', $page1);
        $this->assertStringNotContainsString('Editar', $page1);
        $this->assertStringNotContainsString('Remover', $page1);
        $this->assertStringNotContainsString('/admin/events', $page1);
    }

    public function test_index_shows_enrollments_over_capacity_and_link_to_show(): void
    {
        $event = $this->createEvent(['capacity' => 10]);
        (new Enrollment(['event_id' => $event->id, 'user_id' => $this->participant->id]))->save();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('1/10', $response);
        $this->assertStringContainsString("href=\"/events/{$event->id}\"", $response);
    }

    public function test_show_renders_event_without_admin_actions(): void
    {
        $event = $this->createEvent(['title' => 'Palestra Final', 'capacity' => 4]);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertMatchesRegularExpression('/<h1>\s*Palestra Final\s*<\/h1>/', $response);
        $this->assertStringContainsString('0/4', $response);
        $this->assertStringContainsString('(4 disponíveis)', $response);
        $this->assertStringContainsString('href="/events"', $response);
        $this->assertStringNotContainsString('Editar', $response);
        $this->assertStringNotContainsString('/admin/events', $response);
    }

    public function test_show_with_unknown_id_redirects_to_index(): void
    {
        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
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
     */
    private function createEvent(array $overrides = []): Event
    {
        $event = new Event(array_merge([
            'user_id' => $this->admin->id,
            'title' => 'Oficina de PHP',
            'description' => 'Oficina prática.',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Laboratório B203',
            'capacity' => 20,
        ], $overrides));
        $event->save();

        return $event;
    }
}
```

Por quê:

- `ControllerTestCase` chama a ação direto (sem HTTP, sem middleware) e
  devolve o HTML; redirect volta como string `Location: ...`.
- Prova: 5 por página, link da paginação em `/events/page/2` (não
  `/admin`), nenhum botão de admin, "não encontrado" -> `/events`.
- `user_id` do Evento é do admin: Participante não cria Evento.

## 7. `tests/Integration/Access/ParticipantEventsAccessTest.php` (novo)

Cópia do padrão de `tests/Integration/Access/DashboardAccessTest.php`:
Guzzle por HTTP contra `http://web:8080`, `allow_redirects => false`.

```php
<?php

namespace Tests\Integration\Access;

use App\Models\Event;
use App\Models\User;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rotas events.* (área do usuário): qualquer usuário autenticado acessa; sem sessão vai para /login.
 */
class ParticipantEventsAccessTest extends TestCase
{
    private const PASSWORD = '123456';

    private User $admin;
    private User $participant;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Participante', 'participante@example.com', 'participant');

        $this->event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Palestra de Acesso',
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
        return new Client([
            'allow_redirects' => false,
            'base_uri' => 'http://web:8080',
            'cookies' => true,
        ]);
    }

    private function loggedClient(User $user): Client
    {
        $client = $this->newClient();
        $response = $client->post('/login', [
            'form_params' => ['user' => ['email' => $user->email, 'password' => self::PASSWORD]],
        ]);

        $this->assertEquals(302, $response->getStatusCode());

        return $client;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function participantRoutes(): array
    {
        return [
            'events.index' => ['/events'],
            'events.paginate' => ['/events/page/1'],
            'events.show' => ['/events/1'],
        ];
    }

    #[DataProvider('participantRoutes')]
    public function test_events_route_should_redirect_to_login_without_session(string $uri): void
    {
        $response = $this->newClient()->get($uri);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    #[DataProvider('participantRoutes')]
    public function test_participant_should_access_events_route(string $uri): void
    {
        $response = $this->loggedClient($this->participant)->get($uri);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Palestra de Acesso', (string) $response->getBody());
    }

    public function test_admin_should_also_access_participant_events(): void
    {
        $response = $this->loggedClient($this->admin)->get('/events');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Palestra de Acesso', (string) $response->getBody());
    }

    public function test_navbar_should_link_participant_to_events(): void
    {
        $body = (string) $this->loggedClient($this->participant)->get('/dashboard')->getBody();

        $this->assertStringContainsString('href="/events"', $body);
        $this->assertStringNotContainsString('href="/admin/events"', $body);
    }

    public function test_unknown_event_should_redirect_participant_to_events(): void
    {
        $response = $this->loggedClient($this->participant)->get('/events/999999');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/events', $response->getHeaderLine('Location'));
    }
}
```

Por quê:

- Teste de controller não passa pelo middleware. Só o HTTP prova que a
  rota está no `auth` (sem sessão -> 302 `/login`) e fora do `admin`
  (Participante -> 200).
- `DataProvider` roda o mesmo teste nas 3 rotas.
- Navbar: Participante tem `/events` e não tem `/admin/events`.

## 8. `tests/Acceptance/events/BaseParticipantEventsCest.php` (novo)

Reaproveita a massa do `BaseEventsCest` (16 Eventos) e troca a sessão:

```php
<?php

namespace Tests\Acceptance\events;

use Tests\Acceptance\admin\events\BaseEventsCest;
use Tests\Support\AcceptanceTester;

/**
 * Base dos cenários da área do Participante (/events): mesmos dados de BaseEventsCest (16 Eventos,
 * Evento 01 lotado 3/3, Evento 03 com uma Inscrição do Participante 1), mas a sessão é do
 * Participante 1 (participante1@example.com) em vez do Administrador.
 */
class BaseParticipantEventsCest extends BaseEventsCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        $page->amOnPage('/logout');
        $page->login($this->participants[1]->email, self::PASSWORD);
    }
}
```

Por quê: `parent::_before` semeia e loga o admin; aqui sai e entra como
Participante. Herdar evita duplicar o seed.

## 9. `tests/Acceptance/events/ParticipantListEventsCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\events;

use Tests\Support\AcceptanceTester;

class ParticipantListEventsCest extends BaseParticipantEventsCest
{
    public function listEventsFromNavbarWithoutAdminActions(AcceptanceTester $page): void
    {
        $page->wantTo('Listagem de Eventos para o Participante');
        $page->amOnPage('/dashboard');
        $page->click('Eventos', 'nav');

        $page->seeCurrentUrlEquals('/events');
        $page->see('Eventos', 'h1');
        $page->see('Evento 01', 'table');
        $page->see('3/3', $this->rowOf('Evento 01'));
        $page->dontSee('Evento 06', 'table');
        $page->see('Mostrando 1 - 5 de 16');
        $page->dontSee('Novo Evento');
        $page->dontSee('Editar');
        $page->dontSee('Remover');
        $page->dontSee('Administração', 'nav');

        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/events/page/2');
        $page->see('Evento 06', 'table');
        $page->dontSee('Evento 01', 'table');
    }

    public function showEventDetails(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Detalhe do Evento para o Participante');
        $page->amOnPage('/events');
        $page->click('Ver', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Evento 02', 'h1');
        $page->see('Descrição do Evento 02.');
        $page->see('Sala 2');
        $page->see('0/22');
        $page->dontSee('Editar');

        $page->click('Voltar');
        $page->seeCurrentUrlEquals('/events');
    }

    public function adminAreaStaysBlocked(AcceptanceTester $page): void
    {
        $page->wantTo('Participante continua sem acesso à listagem administrativa');
        $page->amOnPage('/admin/events');

        $page->seeCurrentUrlEquals('/dashboard');
    }
}
```

Por quê: navegador real. Golden path navbar -> listagem -> página 2;
"Ver" -> detalhe -> "Voltar"; `/admin/events` continua barrado
(middleware `admin` manda para `/dashboard`).

## Fechamento

```sh
./run composer dump-autoload
./run test tests/Integration/Controllers/EventsControllerTest.php
./run test tests/Integration/Access/ParticipantEventsAccessTest.php
./run test              # suíte toda
./run test:browser      # com selenium no ar
./run phpcs
./run phpstan
```

Sem `db:populate`: schema e populate não mudam.

No navegador: logado como `user@example.com` / `123456`, clicar
"Eventos" na navbar -> `/events`, paginar, abrir um Evento, "Voltar".
Sem botões Novo/Editar/Remover. `/admin/events` -> `/dashboard`.

Commit (inglês): `feat: Add events listing and details for participants`.

## Armadilhas (resumo)

- Rota dentro do grupo `admin`: Participante cai em `/dashboard` com
  "Acesso restrito".
- Dois `EventsController`: importe o novo sem alias e mantenha
  `AdminEventsController`.
- `paginate` com o `route: 'admin.events.paginate'` copiado do admin:
  página 1 funciona, links vão para `/admin/events/page/N`. Use
  `events.paginate` e declare a rota com esse nome.
- "Voltar" copiado do admin aponta para `admin.events.index`.
- Versão pública (fora de `auth`): `currentUser()` é `null`; views não
  podem chamar `$this->currentUser()->name`.
- Esquecer `$title` no `compact`: 500.
- `_pages.phtml` só enxerga `$paginator`.
