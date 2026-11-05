# P7 passo a passo: "Meus eventos" do Participante

Pedido típico: "uma página com os Eventos em que o Participante logado
está inscrito, com link na navbar". Chance: média-alta. Tempo: 20-30
min.

Exemplo pronto: `fvp/4.2/exemplos/p7-meus-eventos/` (patch, bundle e
árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato "um
arquivo por vez, com o motivo".

Pré-requisitos: P2 (inscrição do Participante), que está sobre O2
(Eventos para o Participante). O P2 já traz `User::enrollments()`
(`HasMany`), `Enrollment::event()`, o `EnrollmentsController` com
`create` e os testes-base; o O2 traz a rota `events.show`. O
`solucao.patch` tem 3 commits (O2 + P2 + P7); aqui só o último.

Ordem = fluxo da requisição: rota -> controller -> views -> testes.
Schema e model não mudam. Na prova, prioridade: funcionar no navegador,
depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `config/routes.php`
2. `app/Controllers/EnrollmentsController.php`
3. `app/views/enrollments/index.phtml` (novo)
4. `app/views/layouts/_navbar.phtml`
5. `tests/Integration/Controllers/EnrollmentsControllerTest.php`
6. `tests/Integration/Access/ParticipantEventsAccessTest.php`
7. `tests/Acceptance/events/ParticipantMyEventsCest.php` (novo)

Depois: suíte, `./run phpcs`, `./run phpstan`.

## 1. `config/routes.php`

No grupo `Route::middleware('auth')`, depois de
`events.show` e antes do comentário da inscrição (`events.enrollments.create`):

```php
    Route::get('/events/{id}', [EventsController::class, 'show'])->name('events.show');

    // Inscrições do usuário logado ("Meus eventos").
    Route::get('/enrollments', [EnrollmentsController::class, 'index'])->name('enrollments.index');
```

Por quê:

- Grupo `auth`: middleware manda quem não tem sessão para `/login`.
  Admin e Participante entram; o link só aparece para o Participante.
- `/enrollments` + `index` = listagem RESTful do recurso Inscrições do
  logado, no mesmo controller do `create`. Nada de `/my-events`.
- `use` do `EnrollmentsController` já existe (veio do P2).

## 2. `app/Controllers/EnrollmentsController.php`

Novo método no topo da classe, antes de `create`:

```php
class EnrollmentsController extends Controller
{
    /**
     * "Meus eventos": Inscrições do usuário logado, com o Evento de cada uma.
     */
    public function index(): void
    {
        /** @var array<int, Enrollment> $enrollments */
        $enrollments = $this->currentUser()?->enrollments()->get() ?? [];

        $title = 'Meus eventos';
        $this->render('enrollments/index', compact('enrollments', 'title'));
    }

    public function create(Request $request): void
```

Por quê:

- Consulta mora no model: `User::enrollments()` (`HasMany` por
  `user_id`, do P2). Controller só pede; nada de
  `Enrollment::where(...)` aqui.
- `HasMany::get()` devolve `array<Model>`. O `@var array<int,
  Enrollment>` diz ao `phpstan` o tipo real; sem ele, `->event()` na
  view vira erro.
- `currentUser()` é `?User`: `?->` + `?? []`. A rota é `auth`, mas o
  `phpstan` não sabe.
- Não use `User::events()`: são os Eventos **criados** pelo admin
  (`events.user_id`), não as Inscrições.
- `$title` no `compact`: o layout imprime `<h1><?= $title ?></h1>`;
  faltando, página 500.

## 3. `app/views/enrollments/index.phtml` (novo)

```php
<?php if (empty($enrollments)) : ?>
    <p>Você não está inscrito em nenhum evento.</p>
    <a class="btn btn-primary" href="<?= route('events.index') ?>">Ver eventos</a>
<?php else : ?>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Título</th>
                <th>Data</th>
                <th>Local</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($enrollments as $enrollment) : ?>
                <?php $event = $enrollment->event()->get(); ?>
                <tr>
                    <td><?= htmlspecialchars($event->title) ?></td>
                    <td><?= date('d/m/Y', strtotime($event->starts_on)) ?></td>
                    <td><?= htmlspecialchars($event->location) ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary"
                            href="<?= route('events.show', ['id' => $event->id]) ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
```

Por quê:

- Pasta `enrollments/` = nome passado ao `render('enrollments/index')`.
- `$enrollment->event()->get()`: `BelongsTo` do `Enrollment` (P2)
  devolve o Evento. Um SELECT por linha (N+1): aceitável aqui; saiba
  dizer.
- Sem Inscrições: mensagem + link. Sem o `if`, a página fica em branco.
- `htmlspecialchars` em texto (XSS); `date()` só formata.
- Só título, data e local: resumo. Detalhe e vagas ficam no
  `events.show` (O2), via botão "Ver".
- Catálogo cita botão "Cancelar inscrição" (P3): não está neste patch.
  Com P3 aplicado, ponha na célula de ações o mesmo form do `show`
  (`events.enrollments.destroy`, `_method=DELETE`).

## 4. `app/views/layouts/_navbar.phtml`

No ramo `else` do `isAdmin()` (não-admin), depois do `<li>` de
"Eventos":

```php
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('events.index') ?>">Eventos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('enrollments.index') ?>">Meus eventos</a>
                    </li>
                <?php endif; ?>
```

Por quê:

- Pedido exige link na navbar. `route('enrollments.index')` gera
  `/enrollments` pelo nome: mudar a URI não quebra o link.
- Ramo não-admin: admin não se inscreve.

## 5. `tests/Integration/Controllers/EnrollmentsControllerTest.php`

a) Docblock da classe:

```php
/**
 * Inscrição do Participante logado (POST /events/{id}/enrollments) e "Meus eventos" (GET /enrollments).
 */
```

b) Testes novos, antes do helper privado `createUser`:

```php
    public function test_index_lists_only_the_logged_user_events(): void
    {
        $first = $this->createEvent(['title' => 'Palestra Inscrita', 'location' => 'Auditório']);
        $second = $this->createEvent(['title' => 'Oficina Inscrita']);
        $notMine = $this->createEvent(['title' => 'Minicurso de Outro']);
        $other = $this->createUser('Outro', 'outro@example.com', 'participant');
        (new Enrollment(['event_id' => $first->id, 'user_id' => $this->participant->id]))->save();
        (new Enrollment(['event_id' => $second->id, 'user_id' => $this->participant->id]))->save();
        (new Enrollment(['event_id' => $notMine->id, 'user_id' => $other->id]))->save();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Meus eventos\s*<\/h1>/', $response);
        $this->assertStringContainsString('Palestra Inscrita', $response);
        $this->assertStringContainsString('Oficina Inscrita', $response);
        $this->assertStringContainsString('Auditório', $response);
        $this->assertStringContainsString(date('d/m/Y', strtotime('+10 days')), $response);
        $this->assertStringContainsString("href=\"/events/{$first->id}\"", $response);
        $this->assertStringNotContainsString('Minicurso de Outro', $response);
    }

    public function test_index_without_enrollments_shows_empty_message(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Você não está inscrito em nenhum evento.', $response);
        $this->assertStringNotContainsString('<table', $response);
    }
```

Por quê:

- `setUp` (do P2) já faz `Auth::login($this->participant)`; o
  `createEvent` usa `starts_on` = `+10 days`, daí o assert da data.
- Primeiro teste prova o filtro: dois títulos meus aparecem, o do
  outro Participante não. Também `<h1>` (prova do `$title`) e link.
- Segundo cobre a mensagem de vazio (sem `<table>`).

## 6. `tests/Integration/Access/ParticipantEventsAccessTest.php`

No fim da classe:

```php
    public function test_my_events_should_redirect_to_login_without_session(): void
    {
        $response = $this->newClient()->get('/enrollments');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_participant_should_see_my_events_and_navbar_link(): void
    {
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]))->save();

        $response = $this->loggedClient($this->participant)->get('/enrollments');
        $body = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Palestra de Acesso', $body);
        $this->assertStringContainsString('href="/enrollments"', $body);
    }
```

Por quê:

- Teste de acesso bate por HTTP no servidor `web` (Guzzle): prova o middleware
  `auth` da rota (sem sessão -> `/login`), coisa que o teste de
  controller não vê.
- Segundo: Participante logado -> 200, Evento na tela e link na navbar.

## 7. `tests/Acceptance/events/ParticipantMyEventsCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\events;

use Tests\Support\AcceptanceTester;

class ParticipantMyEventsCest extends BaseParticipantEventsCest
{
    public function listOnlyMyEventsFromNavbar(AcceptanceTester $page): void
    {
        $page->wantTo('"Meus eventos" lista só as Inscrições do Participante logado');
        $page->amOnPage('/dashboard');
        $page->click('Meus eventos', 'nav');

        $page->seeCurrentUrlEquals('/enrollments');
        $page->see('Meus eventos', 'h1');
        $page->see('Evento 01', 'table');
        $page->see('Evento 03', 'table');
        $page->see('Sala 3', $this->rowOf('Evento 03'));
        $page->see(date('d/m/Y', strtotime($this->startsOn(3))), $this->rowOf('Evento 03'));
        $page->dontSee('Evento 02', 'table');
        $page->seeNumberOfElements('table tbody tr', 2);

        $page->click('Ver', $this->rowOf('Evento 03'));
        $page->seeCurrentUrlEquals("/events/{$this->events[3]->id}");
        $page->see('Você está inscrito neste evento.');
    }

    public function newEnrollmentShowsUpInMyEvents(AcceptanceTester $page): void
    {
        $this->loginAsNewParticipant($page, 'novo@example.com');

        $page->wantTo('Inscrição nova aparece em "Meus eventos"');
        $page->amOnPage('/enrollments');
        $page->see('Você não está inscrito em nenhum evento.');

        $page->amOnPage("/events/{$this->events[2]->id}");
        $page->click('Inscrever-se');
        $page->click('Meus eventos', 'nav');

        $page->see('Evento 02', 'table');
        $page->seeNumberOfElements('table tbody tr', 1);
    }
}
```

Por quê:

- Herda de `BaseParticipantEventsCest` (base O2/P2, sobre
  `BaseEventsCest`): 16 Eventos, Participante 1 inscrito nos Eventos
  01 e 03, sessão dele; helpers `rowOf`, `startsOn`,
  `loginAsNewParticipant`.
- Primeiro: navbar -> lista certa -> "Ver" leva ao detalhe.
- Segundo: lista vazia, inscreve pelo `show`, aparece na lista.

## Fechamento

```sh
./run test tests/Integration/Controllers/EnrollmentsControllerTest.php
./run test tests/Integration/Access/ParticipantEventsAccessTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Schema não mudou: `./run db:populate` não é obrigatório (só se o banco
de dev estiver sem as Inscrições do populate).

No navegador: `user@example.com` / `123456`, "Meus eventos" na navbar
-> 4 Eventos do populate (1, 2, 4 e 9). "Ver" abre o detalhe.

Commit (inglês): `feat: Add my events page for participants`.

## Armadilhas (resumo)

- `get()` devolve `array<Model>`: declare
  `/** @var array<int, Enrollment> $enrollments */` para o `phpstan`.
- `currentUser()` é `?User`: `?->` e `?? []`.
- N+1 (`event()->get()` por linha): aceitável, saiba dizer. Alternativa:
  `BelongsToMany` em `User` via `event_user` (`MODEL:365`), com nome
  `enrolledEvents()` (`events()` já é dos Eventos criados).
- Não use `User::events()` aqui: são os Eventos criados pelo admin.
- Tabela vazia: mensagem "Você não está inscrito em nenhum evento.".
- `$title` fora do `compact`: página 500.
