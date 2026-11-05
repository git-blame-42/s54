# O1 passo a passo: Rota RESTful nova com página (lotados)

Pedido típico: "uma página na área administrativa que liste só os
Eventos lotados, em `GET /admin/events/full` (`admin.events.full`),
com link na listagem". Chance: média-alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/o1-rota-eventos-lotados/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: sai de `main`. Schema
não muda. O teste de acesso por HTTP da rota nova é o cenário S2
(`s2-teste-acesso-rota-nova`), empilhado sobre este.

Ordem = fluxo da requisição: rota -> model -> controller -> views ->
testes. Na prova, prioridade: funcionar no navegador, depois testar,
depois `phpcs`.

## Arquivos, em ordem

1. `config/routes.php`
2. `app/Models/Event.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/full.phtml` (novo)
5. `app/views/admin/events/index.phtml`
6. `tests/Unit/Models/Events/EventTest.php`
7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
8. `tests/Acceptance/admin/events/ListFullEventsCest.php` (novo)

Depois: suíte, `./run phpcs`, `./run phpstan`, `./run test:browser`.

## 1. `config/routes.php`

No grupo `admin` (linhas ~25-35), logo depois da rota `new` e
**antes** de `/admin/events/{id}`:

```php
        Route::get('/admin/events/new', [AdminEventsController::class, 'new'])->name('admin.events.new');
        Route::get('/admin/events/full', [AdminEventsController::class, 'full'])->name('admin.events.full');
        Route::post('/admin/events', [AdminEventsController::class, 'create'])->name('admin.events.create');
```

E o comentário do bloco passa a citar as duas rotas fixas:

```php
        // Eventos (CRUD RESTful). 'new' e 'full' vêm antes de '{id}', senão seriam capturados como id.
```

Por quê:

- Router casa na ordem de declaração. Depois de `{id}`, `full` vira
  id e cai em "Evento não encontrado!".
- Dentro de `middleware('auth')` + `middleware('admin')`: herda os
  dois middlewares, nada a fazer no controller.
- Nome no padrão `admin.events.*`: é o que a view usa em `route()`.
- Só existem `get`, `post`, `put`, `delete` em
  `core/Router/Route.php` (não há `patch`).

Armadilha: o teste de controller chama a ação direto, sem Router, e
**não pega** rota depois de `{id}`. Só teste por HTTP pega (S2).

## 2. `app/Models/Event.php`

Método estático novo depois de `availableSpots()` e antes de
`hasEnrollments()` (linha ~142):

```php
        return max(0, (int) $this->capacity - $this->enrollmentsCount());
    }

    /**
     * Eventos lotados (sem vagas disponíveis), na ordem do banco.
     *
     * @return array<static>
     */
    public static function full(): array
    {
        return array_values(array_filter(
            static::all(),
            fn(Event $event) => $event->availableSpots() === 0
        ));
    }
```

Por quê:

- "Lotado" é regra de negócio do Evento: mora no model; controller
  fica com uma linha.
- Reaproveita `availableSpots()`: mesma conta da tela de detalhes e
  da validação de capacidade. Sem SQL novo, sem duplicar a regra.
- `array_values`: `array_filter` mantém as chaves; sem ele
  `$events[0]` pode não existir.
- `@return array<static>`: `phpstan` sabe o tipo dos itens.

Obs: o catálogo põe o mesmo `array_filter` direto na ação `full()`.
Funciona igual; model é a resposta segura pra "regra no model".

## 3. `app/Controllers/Admin/EventsController.php`

Ação nova depois de `index()` e antes de `show()` (linha ~28):

```php
    public function full(): void
    {
        $events = Event::full();

        $title = 'Eventos lotados';
        $this->render('admin/events/full', compact('events', 'title'));
    }
```

Por quê:

- Controller só busca e entrega à view. Sem `Request`: a ação não lê
  parâmetro nenhum.
- `render('admin/events/full', ...)` acha
  `app/views/admin/events/full.phtml`; `compact` vira variáveis na
  view (`extract`).
- `$title` no `compact`: o layout usa no `<h1>` (o `<title>` da
  aba é fixo, "Eventos do Câmpus").

Armadilha: esquecer `$title` no `compact` quebra o layout.

## 4. `app/views/admin/events/full.phtml` (novo)

Cópia da tabela de `index.phtml` sem paginador e sem
editar/remover. Arquivo inteiro:

```php
<?php if (empty($events)) : ?>
    <p>Nenhum evento lotado.</p>
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
                           href="<?= route('admin.events.show', ['id' => $event->id]) ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<a class="btn btn-secondary" href="<?= route('admin.events.index') ?>">Voltar</a>
```

Por quê:

- View burra: só itera `$events` que o controller passou; nenhuma
  regra, nenhum SQL.
- `htmlspecialchars` em texto (XSS, cobrado na rubrica); `(int)` em
  número.
- Tipo pelo rótulo de `Event::KINDS` (nome completo da classe, como
  em `index.phtml`).
- Lista vazia com mensagem própria; "Voltar" volta à listagem.

## 5. `app/views/admin/events/index.phtml`

No `<div class="mb-3">` do topo, depois do botão "Novo Evento":

```php
    <a class="btn btn-primary" href="<?= route('admin.events.new') ?>">Novo Evento</a>
    <a class="btn btn-outline-secondary" href="<?= route('admin.events.full') ?>">Eventos lotados</a>
```

Por quê: `route('admin.events.full')` monta a URL pelo nome; mudar o
caminho em `routes.php` não quebra o link.

## 6. `tests/Unit/Models/Events/EventTest.php`

Testes novos depois de
`test_available_spots_should_be_capacity_minus_enrollments`:

```php
    public function test_full_should_return_only_events_without_available_spots(): void
    {
        $full = $this->createEvent(['title' => 'Lotado', 'capacity' => 2]);
        $partial = $this->createEvent(['title' => 'Com vagas', 'capacity' => 3]);
        $this->createEvent(['title' => 'Vazio']);
        $this->enroll($full, 2);
        $this->enroll($partial, 2);

        $events = Event::full();

        $this->assertCount(1, $events);
        $this->assertEquals($full->id, $events[0]->id);
    }

    public function test_full_should_be_empty_without_full_events(): void
    {
        $this->createEvent();

        $this->assertSame([], Event::full());
    }
```

Por quê:

- Testa a regra no model, sem HTTP: lotado, com vagas e vazio.
- `createEvent` e `enroll($event, $total)` já existem no arquivo.
- `$events[0]` prova o `array_values`.

## 7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Testes novos depois de
`test_index_shows_date_and_enrollments_over_capacity` (termina em
`assertStringContainsString('1/10', $response);`):

```php
    public function test_full_lists_only_full_events(): void
    {
        $full = $this->createEvent(['title' => 'Evento Lotado', 'capacity' => 1]);
        $this->createEvent(['title' => 'Evento Com Vagas', 'capacity' => 5]);
        $this->enroll($full);

        $response = $this->get(action: 'full', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Eventos lotados\s*<\/h1>/', $response);
        $this->assertStringContainsString('Evento Lotado', $response);
        $this->assertStringContainsString('1/1', $response);
        $this->assertStringNotContainsString('Evento Com Vagas', $response);
    }

    public function test_full_without_full_events_shows_empty_message(): void
    {
        $this->createEvent(['title' => 'Evento Com Vagas']);

        $response = $this->get(action: 'full', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Nenhum evento lotado.', $response);
        $this->assertStringNotContainsString('Evento Com Vagas', $response);
    }

    public function test_index_links_to_full_events(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('href="/admin/events/full"', $response);
    }
```

Por quê:

- Chama a ação `full` direto e lê o HTML: prova controller + view +
  `$title` no `<h1>`.
- `enroll($full)` (helper do arquivo, um participante) lota o Evento
  de capacidade 1.
- Terceiro teste prova o link na listagem.
- Não prova a rota: `ControllerTestCase` não passa pelo Router.

## 8. `tests/Acceptance/admin/events/ListFullEventsCest.php` (novo)

Arquivo inteiro:

```php
<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class ListFullEventsCest extends BaseEventsCest
{
    public function listOnlyFullEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Listagem dos Eventos lotados (sem vagas)');
        $page->amOnPage('/admin/events');

        $page->click('Eventos lotados');

        $page->seeCurrentUrlEquals('/admin/events/full');
        $page->see('Eventos lotados', 'h1');
        // Na base só o Evento 01 está lotado (3/3); o Evento 03 tem Inscrição, mas ainda tem vagas.
        $page->seeNumberOfElements('table tbody tr', 1);
        $page->see('3/3', $this->rowOf('Evento 01'));
        $page->dontSee('Evento 02', 'table');
        $page->dontSee('Evento 03', 'table');

        $page->click('Voltar');
        $page->seeCurrentUrlEquals('/admin/events');
    }
}
```

Por quê:

- Navegador real, passando pelo Router: pega rota declarada depois de
  `{id}` (o clique cairia em "Evento não encontrado!").
- Estende `BaseEventsCest`: login de admin e 16 Eventos semeados;
  `rowOf()` dá o XPath da linha pelo título.

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser      # ListFullEventsCest
docker compose stop selenium
```

Sem `db:populate` (schema não mudou). No navegador (populate, logado
como `admin@example.com` / `123456`): `/admin/events` -> "Eventos
lotados" -> só "Semana Acadêmica de Informática 2026" (3/3);
"Voltar" volta à listagem.

Commit (inglês): `feat: List full events in admin area`.

## Armadilhas (resumo)

- Rota depois de `/admin/events/{id}`: `full` vira id, 302 com
  "Evento não encontrado!". Teste de controller não pega (chama a
  ação direto); só HTTP com admin logado (S2) ou Cest.
- Sem sessão a resposta é 302 nos dois casos: teste HTTP tem de
  logar o admin (`loggedClient`).
- `$title` fora do `compact`: layout quebra.
- Nome fora do padrão `admin.events.*`.
- `array_filter` sem `array_values`: `$events[0]` pode não existir.
- `availableSpots()` usa `max(0, ...)`: Evento com mais Inscrições
  que vagas também conta como lotado (desejado).
- Tabela de ligação é `event_user` (model `Enrollment`), não
  `enrollments`.
- Variante `POST .../duplicate` (P6): form com `method="post"`;
  PUT/DELETE exigem `_method` (`core/Http/Request.php`).
