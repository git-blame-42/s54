# O5 passo a passo: Admin vê e lista só os próprios Eventos

Pedido típico: "cada Administrador só enxerga e cadastra os Eventos
dele; paginação contando só os dele; use a relação `events()` do
usuário logado". Chance: média-alta. Tempo: 15-25 min. Pré-requisito:
nenhum (parte de `main`). O exemplo já inclui a restrição de
`show`/`edit`/`update`/`destroy` (ideia do Q1) e o 2º admin no
populate (ideia do T1).

Exemplo pronto: `fvp/4.2/exemplos/o5-admin-lista-so-proprios/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Ordem = fluxo da requisição: controller -> dados -> testes. Sem schema,
sem rota, sem view: a relação `User::events()` e o `HasMany::paginate`
já existem no core. Na prova, prioridade: funcionar no navegador,
depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `app/Controllers/Admin/EventsController.php`
2. `database/Populate/populate.php`
3. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
4. `tests/Integration/Access/EventsAccessTest.php`
5. `tests/Acceptance/admin/events/ListEventsCest.php`

Depois: `./run db:populate`, suíte, `./run phpcs`.

Antes de começar, leia (não muda):

- `app/Models/User.php` (~52-55): `events(): HasMany` =
  `$this->hasMany(Event::class, 'user_id')`.
- `core/Database/ActiveRecord/HasMany.php`: `new()` já põe o
  `user_id`; `findById()` busca por `user_id` **e** `id`; `paginate()`
  monta o `Paginator` com `conditions: ['user_id' => id]`.

## 1. `app/Controllers/Admin/EventsController.php`

Quatro pontos.

a) `index` (linha ~16): trate o `null` e troque `Event::paginate` por
`$owner->events()->paginate`:

```php
    /**
     * Lista só os Eventos do Administrador logado (user_id = id dele), paginados.
     */
    public function index(Request $request): void
    {
        $owner = $this->currentUser();
        if ($owner === null) {
            $this->redirectTo(route('users.login'));
            return;
        }

        $paginator = $owner->events()->paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: self::PER_PAGE,
            route: 'admin.events.paginate'
        );
```

Resto do método igual (`registers()`, `$title`, `render`).

Por quê:

- `HasMany::paginate` passa `conditions` ao `Paginator`: o `COUNT` e o
  `LIMIT` já saem filtrados por `user_id`. "Mostrando 1 - 5 de N" e as
  páginas contam só os do logado. Sem SQL novo.
- Argumento nomeado é `per_page` (com `_`); `perPage:` dá erro.
- `currentUser()` é `?User`: o `phpstan` exige tratar `null` mesmo com
  o middleware garantindo sessão. `return` depois do `redirectTo`: o
  `redirectTo` é `void` (o `phpstan` não sabe que ele dá `exit`), e no
  `ControllerTestCase` ele só imprime `Location: ...`; sem o `return`,
  o código seguiria e chamaria `->events()` em `null`.

b) `new` (linha ~41):

```php
    public function new(): void
    {
        $event = $this->newEvent();
```

c) `create` (linha ~49): troque `new Event()` e **apague** a linha do
`user_id` manual:

```php
    public function create(Request $request): void
    {
        $event = $this->newEvent();
        $this->fill($event, $request);

        if ($event->save()) {
```

Linha removida:

```php
        $event->user_id = $this->currentUser()?->id;
```

d) Métodos privados, perto do fim (depois de `fill`, no lugar do
`findEvent` antigo):

```php
    /**
     * Evento novo já com user_id do Administrador logado (events()->new()).
     */
    private function newEvent(): Event
    {
        $event = $this->currentUser()?->events()->new();

        return $event instanceof Event ? $event : new Event();
    }

    /**
     * Só acha Eventos do Administrador logado: o de outro admin vira null e cai em "Evento não encontrado!"
     * (vale para show, edit, update e destroy, sem revelar que o Evento existe).
     */
    private function findEvent(Request $request): ?Event
    {
        $event = $this->currentUser()?->events()->findById((int) $request->getParam('id'));

        return $event instanceof Event ? $event : null;
    }
```

Antes era `return Event::findById((int) $request->getParam('id'));`.

Por quê:

- `events()->new()` preenche `user_id` pela relação; dono nunca vem do
  form (fora de `FIELDS`).
- `findEvent` é o único ponto usado por `show`, `edit`, `update` e
  `destroy`: uma troca fecha os quatro. Evento alheio vira `null` ->
  "Evento não encontrado!" -> `/admin/events`, sem revelar que existe.
- Só filtrar a listagem não basta: a URL `/admin/events/{id}` ainda
  abriria o Evento alheio.
- `instanceof Event`: `HasMany::new`/`findById` devolvem
  `Model`/`?Model`; o `phpstan` (nível 6) exige converter para `Event`.
- `?->`: se `currentUser()` for `null`, a expressão vira `null` e cai
  no fallback.
- Para deixar o `show` aberto a todo admin: troque só no `show` por
  `Event::findById(...)`.

## 2. `database/Populate/populate.php`

a) Depois de `$admin->save();`, segundo admin:

```php
// Segundo Administrador: dono dos próprios Eventos; cada admin lista só os seus.
$admin2 = new User([
    'name' => 'Administrador 2',
    'email' => 'admin2@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'role' => 'admin'
]);
$admin2->save();
```

b) `echo` de usuários criados:

```php
echo "Usuários criados: {$admin->email} e {$admin2->email} (admin), {$user->email} (participant), senha 123456\n";
```

c) Depois do fim do array `$events = [...];`, troque o
`$eventModels = []; foreach (... as [$title, ...]) { new Event([...]) }`
por: array do 2º admin, closure e dois laços.

```php
// Eventos do Administrador 2 (mesmo formato): não aparecem na listagem do admin@example.com.
$admin2Events = [
    [
        'Oficina de Laravel para Iniciantes (Administrador 2)',
        'Rotas, controllers, Eloquent e Blade em uma aplicação de exemplo, passo a passo.',
        'workshop', 'in_person', 'students', 17, 'Bloco B, Laboratório de Informática B202', 25,
    ],
    [
        'Palestra: Software Livre na Universidade (Administrador 2)',
        'Como projetos de software livre ajudam na formação e na pesquisa dentro da universidade.',
        'lecture', 'online', 'students,professors,community', 31, 'Google Meet (link enviado aos inscritos)', 200,
    ],
    [
        'Minicurso de JavaScript Moderno (Administrador 2)',
        'Módulos, promises, async/await e manipulação do DOM sem frameworks.',
        'short_course', 'hybrid', 'students', 45, 'Bloco C, Laboratório C101 e transmissão online', 30,
    ],
];

/**
 * @param array<int, mixed> $data uma linha de $events ou $admin2Events
 */
$createEvent = function (User $owner, array $data): Event {
    [$title, $description, $kind, $modality, $audience, $daysAhead, $location, $capacity] = $data;

    $event = $owner->events()->new([
        'title' => $title,
        'description' => $description,
        'kind' => $kind,
        'modality' => $modality,
        'starts_on' => date('Y-m-d', strtotime("+{$daysAhead} days")),
        'location' => $location,
        'capacity' => $capacity,
    ]);
    if (!$event instanceof Event) {
        throw new RuntimeException('events()->new() deveria criar um Event');
    }
    $event->setAudienceList(explode(',', $audience));
    if (!$event->save()) {
        throw new RuntimeException("Evento inválido no populate: {$title}");
    }

    return $event;
};

$eventModels = [];
foreach ($events as $data) {
    $eventModels[] = $createEvent($admin, $data);
}

echo count($eventModels) . " Eventos criados pelo Administrador\n";

foreach ($admin2Events as $data) {
    $createEvent($admin2, $data);
}

echo count($admin2Events) . " Eventos criados pelo Administrador 2\n";
```

O corpo do `new Event([...])` antigo (title ... capacity) é o mesmo;
só muda `'user_id' => $admin->id` -> `$owner->events()->new([...])`.

Por quê:

- Sem 2º admin com Eventos próprios não dá para ver a diferença no
  navegador (só "Nenhum evento cadastrado.").
- 2º admin ganha Eventos novos em vez de "roubar" do primeiro: números
  do `admin@example.com` (16 Eventos, 4 páginas, Evento 1 lotado,
  Inscrições por índice de `$eventModels`) continuam valendo.
- Closure evita duplicar o laço para os dois donos.
- Mantém a destruturação posicional das linhas (mesmo formato).

Armadilha: T1 também cria `admin2@example.com`. Aplicando os dois, fique
com um bloco só (o segundo `save()` falha no `uniqueness` do e-mail).

## 3. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois do último teste existente, antes de `validParams()` (imports
`Event`, `User`, `Auth` já existem):

```php
    /* ------------------- cada Administrador vê só os próprios Eventos ------------------- */

    public function test_index_lists_only_the_logged_admin_events(): void
    {
        $other = $this->createOtherAdmin();
        $this->createEvent(['title' => 'Meu Evento']);
        $this->createEvent(['title' => 'Evento do Outro', 'user_id' => $other->id]);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Meu Evento', $response);
        $this->assertStringNotContainsString('Evento do Outro', $response);
        $this->assertStringContainsString('Mostrando 1 - 1 de 1', $response);
    }

    public function test_index_paginates_using_only_the_logged_admin_count(): void
    {
        $other = $this->createOtherAdmin();
        for ($i = 1; $i <= 4; $i++) {
            $this->createEvent(['title' => "Alheio {$i}", 'user_id' => $other->id]);
        }
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Próprio {$i}"]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $page1);
        $this->assertStringContainsString('/admin/events/page/2', $page1);
        $this->assertStringNotContainsString('/admin/events/page/3', $page1);
        $this->assertStringContainsString('Próprio 1', $page1);
        $this->assertStringContainsString('Próprio 5', $page1);
        $this->assertStringContainsString('Mostrando 6 - 7 de 7', $page2);
        $this->assertStringContainsString('Próprio 7', $page2);
        $this->assertStringNotContainsString('Alheio', $page1 . $page2);
    }

    public function test_the_other_admin_sees_only_his_events(): void
    {
        $other = $this->createOtherAdmin();
        $this->createEvent(['title' => 'Do Primeiro']);
        $this->createEvent(['title' => 'Do Segundo', 'user_id' => $other->id]);

        Auth::login($other);
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Do Segundo', $response);
        $this->assertStringNotContainsString('Do Primeiro', $response);
    }

    public function test_index_without_logged_user_redirects_to_login(): void
    {
        Auth::logout();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertSame('Location: /login', $response);
    }

    public function test_new_and_create_use_the_logged_admin_as_owner(): void
    {
        $other = $this->createOtherAdmin();
        Auth::login($other);

        $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['event' => $this->validParams()]);

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals($other->id, $event->user_id);
    }

    public function test_show_and_edit_of_another_admin_event_redirect_to_index(): void
    {
        $other = $this->createOtherAdmin();
        $event = $this->createEvent(['title' => 'Evento Alheio', 'user_id' => $other->id]);

        foreach (['show', 'edit'] as $action) {
            $response = $this->get(action: $action, controllerName: self::CONTROLLER, params: ['id' => $event->id]);

            $this->assertSame('Location: /admin/events', $response, $action);
            $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger'], $action);
        }
    }

    public function test_update_and_destroy_of_another_admin_event_do_not_change_it(): void
    {
        $other = $this->createOtherAdmin();
        $event = $this->createEvent(['title' => 'Evento Alheio', 'user_id' => $other->id]);

        $update = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Invadido'])]
        );
        $destroy = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame('Location: /admin/events', $update);
        $this->assertSame('Location: /admin/events', $destroy);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
        $stored = Event::findById($event->id);
        $this->assertNotNull($stored);
        $this->assertEquals('Evento Alheio', $stored->title);
        $this->assertEquals($other->id, $stored->user_id);
    }

    private function createOtherAdmin(): User
    {
        $other = new User([
            'name' => 'Outro Admin',
            'email' => 'admin2@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $other->save();

        return $other;
    }
```

Por quê:

- `createEvent` existente usa `$this->admin` (logado no `setUp`); o
  override `'user_id' => $other->id` cria Evento alheio.
- Paginação com 7 próprios + 4 alheios prova que o `COUNT` também é
  filtrado ("de 7", sem página 3).
- `Auth::login($other)` troca a sessão no meio do teste.
- `update`/`destroy` alheios: confere no banco que nada mudou.
- Testes antigos (`test_index_lists_five_events_per_page` etc.) criam
  tudo com `$this->admin`: continuam verdes.

## 4. `tests/Integration/Access/EventsAccessTest.php`

Novo teste antes de
`test_admin_should_be_redirected_to_index_when_event_does_not_exist`
(usa `createUser` e `loggedClient` que já existem no arquivo):

```php
    public function test_another_admin_should_not_see_or_open_the_event(): void
    {
        $other = $this->createUser('Administrador 2', 'admin2@example.com', 'admin');
        $client = $this->loggedClient($other);

        $index = $client->get('/admin/events');
        $this->assertEquals(200, $index->getStatusCode());
        $this->assertStringNotContainsString('Palestra de Acesso', (string) $index->getBody());

        $show = $client->get("/admin/events/{$this->event->id}");
        $this->assertEquals(302, $show->getStatusCode());
        $this->assertEquals('/admin/events', $show->getHeaderLine('Location'));
    }
```

Por quê: por HTTP real, com middleware e sessão. O Evento do `setUp` é
do `$this->admin`; o 2º admin recebe 200 na listagem sem ele e 302 no
`show`.

## 5. `tests/Acceptance/admin/events/ListEventsCest.php`

Novo Cest depois de `listEventsHasLinksToShowAndEdit` (antes do
`assertContains` privado). `createUser`, `startsOn` e `TOTAL_EVENTS`
vêm do `BaseEventsCest`:

```php
    public function listShowsOnlyTheLoggedAdminEvents(AcceptanceTester $page): void
    {
        $other = $this->createUser('Administrador 2', 'admin2@example.com', 'admin');
        $foreign = new Event([
            'user_id' => $other->id,
            'title' => 'Evento de Outro Administrador',
            'description' => 'Não deve aparecer para admin@example.com.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => $this->startsOn(1),
            'location' => 'Sala 99',
            'capacity' => 10,
        ]);
        \PHPUnit\Framework\Assert::assertTrue($foreign->save());

        $page->wantTo('Listagem só com os Eventos do Administrador logado');
        $page->amOnPage('/admin/events/page/4');
        $page->dontSee('Evento de Outro Administrador');
        $page->see(sprintf('Mostrando 16 - 16 de %d', self::TOTAL_EVENTS));

        $page->amOnPage("/admin/events/{$foreign->id}");
        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento não encontrado!');
    }
```

Por quê:

- `seedEvents` do `BaseEventsCest` cria tudo com o admin logado: os
  Cests antigos não pegam a mudança. Precisa de um Evento de outro
  admin.
- Contagem continua 16 (o 17º é alheio); URL direta -> flash.

## Fechamento

```sh
./run db:populate
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test              # suíte toda
./run test:browser      # com selenium no ar
./run phpcs
./run phpstan
```

No navegador: `admin@example.com` -> "Mostrando 1 - 5 de 16", nenhum
Evento "(Administrador 2)". `admin2@example.com` / `123456` -> só os 3
dele ("1 - 3 de 3"). Como `admin2`, abrir `/admin/events/1` -> volta
para a listagem com "Evento não encontrado!".

Commit (inglês): `feat: List and manage only the logged admin events`.

## Armadilhas (resumo)

- `currentUser()` é `?User`: trate `null` (redirect + `return`) ou use
  `?->`; senão `phpstan` reclama / 500.
- `per_page` com `_` no argumento nomeado.
- Sem `instanceof Event`, `phpstan` acusa retorno `?Model`.
- Só filtrar a listagem não basta: `findEvent` com `Event::findById`
  ainda abre o Evento alheio por URL.
- `HasMany::paginate` não recebe `queryParams`/`orderBy` (N1-N4): se
  esses cenários existirem, monte o `Paginator` à mão com
  `conditions: ['user_id' => $owner->id, ...]`.
- Painel (N6/N7): "meus" ou "todos"? Decida e nomeie.
- Cests antigos não pegam a mudança (seed é todo do admin logado).
- 2º admin sem Eventos: "Nenhum evento cadastrado.". Com T1, um só
  `admin2@example.com`.
