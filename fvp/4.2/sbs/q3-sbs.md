# Q3 passo a passo: Ação só para Participante

Pedido típico: "inscrever-se é coisa de participante. O administrador
não pode se inscrever em Evento nenhum: se tentar, volta para a área
administrativa com um aviso. E esconda o botão 'Inscrever-se' para
ele". Chance: média-baixa. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/q3-acao-so-participante/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Pré-requisitos: O2 e P2, empilhados (`main` -> O2 -> P2 -> Q3).

- O2 (`exemplos/o2-eventos-para-participante`, guia
  [`sbs/o2-sbs.md`](o2-sbs.md)): área `/events`,
  `app/views/events/show.phtml`, testes de Participante.
- P2 (`exemplos/p2-inscricao-participante`, guia
  [`sbs/p2-sbs.md`](p2-sbs.md)): rota `POST /events/{id}/enrollments`,
  `EnrollmentsController`, `Enrollment::validates()`, botão
  "Inscrever-se".

O patch traz os 3 commits (O2 + P2 + Q3); aqui só o do Q3.

Duas camadas: middleware `participant` na rota (barra na porta,
redirect para `/admin` + flash) e regra no model (`Enrollment` recusa
admin em qualquer caminho). Botão escondido é só conforto.

Ordem = fluxo da requisição: model -> middleware -> alias -> rota ->
view -> testes. Schema não muda. Na prova, prioridade: funcionar no
navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `app/Models/Enrollment.php`
2. `app/Middleware/Participant.php` (novo)
3. `config/App.php`
4. `config/routes.php`
5. `app/views/events/show.phtml`
6. `tests/Unit/App/Middleware/ParticipantDouble.php` (novo)
7. `tests/Unit/App/Middleware/ParticipantTest.php` (novo)
8. `tests/Unit/Models/Events/EnrollmentTest.php`
9. `tests/Integration/Controllers/EnrollmentsControllerTest.php`
10. `tests/Integration/Controllers/EventsControllerTest.php`
11. `tests/Integration/Access/ParticipantEventsAccessTest.php`
12. `tests/Acceptance/events/AdminCannotEnrollCest.php` (novo)

Depois: `./run composer dump-autoload -o --strict-psr`, suíte,
`./run phpcs`.

## 1. `app/Models/Enrollment.php`

Três pontos.

a) Docblock da classe, `@property` de `user_id`:

```php
 * @property int $event_id
 * @property int|string|null $user_id
```

Por quê: o valor pode vir de formulário (string) ou faltar. `phpstan`
precisa saber disso para aceitar o `filter_var` abaixo.

b) `validates()`, entre o `if` dos `notEmpty` e o da `uniqueness`:

```php
        if (!Validations::notEmpty('event_id', $this) || !Validations::notEmpty('user_id', $this)) {
            return;
        }

        if (!$this->validatesParticipant()) {
            return;
        }

        if (!Validations::uniqueness(['event_id', 'user_id'], $this)) {
```

c) Método privado novo, logo depois de `validates()` (antes do
`validatesAvailableSpots()`):

```php
    /**
     * Só Participante se inscreve. Segunda camada, além do middleware participant da rota:
     * vale também para populate, console e qualquer outro caminho que crie Inscrição.
     */
    private function validatesParticipant(): bool
    {
        // filter_var e não (int): '1abc' viraria o usuário 1.
        $userId = filter_var($this->user_id, FILTER_VALIDATE_INT);
        $user = $userId === false ? null : User::findById($userId);

        if ($user !== null && $user->isAdmin()) {
            $this->addError('base', 'administradores não podem se inscrever em eventos!');
            return false;
        }

        return true;
    }
```

Por quê:

- Regra de negócio mora no model: vale para populate, console, outro
  controller futuro e teste que chama a ação sem router. Se alguém
  esquecer o middleware numa rota nova, o model ainda recusa.
- Depois dos `notEmpty` (precisa de `user_id`) e antes da
  `uniqueness`/vagas: admin não deve ver "já inscrito" nem "sem vagas".
- `return false` + `return` no `validates()`: para no primeiro erro,
  como o resto do método.
- `addError('base', ...)`: o `EnrollmentsController` (P2) já põe
  `errors('base')` no flash `danger`. Controller não muda.
- `filter_var(..., FILTER_VALIDATE_INT)`: `'1abc'` dá `false`; `(int)`
  daria `1`, que pode ser o admin.
- `User` está no mesmo namespace (`App\Models`): sem `use` novo.

Armadilha: P4/P9 mexem na mesma sequência; mantenha
`validatesParticipant()` logo depois dos `notEmpty`.

## 2. `app/Middleware/Participant.php` (novo)

Cópia de `app/Middleware/Admin.php` com a condição invertida e outro
destino:

```php
<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

/**
 * Ações exclusivas do Participante (ex.: inscrever-se). O Administrador é barrado e vai
 * para a área administrativa. Espelho do middleware Admin.
 */
class Participant implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        // Sem usuário logado, quem redireciona é o middleware Authenticate.
        if ($user !== null && $user->isAdmin()) {
            FlashMessage::danger('Ação disponível apenas para participantes!');
            $this->redirectTo(route('admin.dashboard.index'));
        }
    }

    /**
     * protected (e não private como no Admin) para o teste unitário trocar o exit por um registro.
     */
    protected function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
```

Por quê:

- Middleware barra pela rota, antes do controller. Controller fica sem
  `if` de perfil. Mesmo mecanismo do `admin`.
- `implements Middleware`: o router chama `handle()` de cada
  middleware da rota antes da ação.
- `$user !== null && ...`: sem sessão, deixa passar. O grupo interno
  roda antes do externo, então `Participant` roda **antes** do
  `Authenticate`; quem manda para `/login` é o `Authenticate`, logo
  em seguida.
- Destino `admin.dashboard.index` (`/admin`): a "casa" do admin. O
  `Admin` faz o simétrico com o Participante.
- `redirectTo` `protected`: o dublê do teste sobrescreve e troca
  `header` + `exit` por um registro.

Armadilhas:

- Redirecionar sem sessão: visitante iria para `/admin`, não `/login`.
- `redirectTo` `private` com `exit` (cópia literal do `Admin`): sem
  teste unitário possível.
- `redirectTo` duplicado em `Admin` e `Participant`: crítica A1 da 4.1.
  Se cobrarem, extraia para um `BaseMiddleware` e faça os dois herdarem.

## 3. `config/App.php`

`$middlewareAliases`, depois de `'admin' => ...`:

```php
        'auth' => \App\Middleware\Authenticate::class,
        'admin' => \App\Middleware\Admin::class,
        'participant' => \App\Middleware\Participant::class,
```

Por quê: `Route::middleware('participant')` só conhece o nome. O
`RouteWrapperMiddleware` busca a classe neste array e faz `new`.

Armadilha: esquecer o alias -> `Route::middleware('participant')` falha
ao resolver o nome.

## 4. `config/routes.php`

Dentro do grupo `auth`, troque a rota de inscrição do P2 por um grupo
`participant`:

```php
    // Inscrição do usuário logado no Evento: só Participante (admin vai para /admin).
    Route::middleware('participant')->group(function () {
        Route::post('/events/{id}/enrollments', [EnrollmentsController::class, 'create'])
            ->name('events.enrollments.create');
    });

    // Área administrativa (somente autenticados com perfil admin)
```

Por quê:

- `RouteWrapperMiddleware::group()` roda o callback e só depois anexa o
  middleware às rotas criadas. Grupo interno fecha primeiro -> seu
  middleware entra primeiro -> roda primeiro (A7/A17 da 4.1).
- Dentro de `auth`: a rota continua exigindo sessão.
- `events.index`/`events.show` ficam fora: admin pode ver a página,
  só não se inscreve.
- P3 (`DELETE /events/{id}/enrollments`), quando entrar, vai para o
  mesmo grupo.

Armadilha: grupo `participant` fora do `auth`: sem sessão, o
`Participant` vê `null`, deixa passar, e a ação roda sem usuário.

## 5. `app/views/events/show.phtml`

Depois do `</dl>`, o bloco do botão ganha um ramo `isAdmin()` na frente:

```php
<?php if ($this->currentUser()?->isAdmin()) : ?>
    <p class="text-muted">Inscrições são feitas pelos participantes.</p>
<?php elseif ($this->currentUser()?->isEnrolledIn($event)) : ?>
    <p class="text-success">Você está inscrito neste evento.</p>
<?php else : ?>
    <form class="d-inline" action="<?= route('events.enrollments.create', ['id' => $event->id]) ?>" method="post">
```

Por quê:

- View burra: só pergunta ao model (`isAdmin()`), sem regra própria.
- Ramo admin vem primeiro: admin nunca vê o form.
- `?->`: não quebra se a página abrir sem sessão (hoje o `auth`
  impede, mas a view não deve contar com isso).

Armadilha: esconder o botão não é segurança. `POST` feito à mão
continua chegando; quem barra é middleware + model.

## 6. `tests/Unit/App/Middleware/ParticipantDouble.php` (novo)

```php
<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Participant;

/**
 * Dublê do middleware Participant: guarda o destino do redirect em vez de enviar o header e dar exit.
 */
class ParticipantDouble extends Participant
{
    public ?string $location = null;

    protected function redirectTo(string $location): void
    {
        $this->location = $location;
    }
}
```

Por quê: `exit` mataria o PHPUnit. O dublê herda o `handle()` real e
só troca o efeito do redirect (padrão F2 da 4.1).

## 7. `tests/Unit/App/Middleware/ParticipantTest.php` (novo)

```php
<?php

namespace Tests\Unit\App\Middleware;

use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Lib\Authentication\Auth;
use Lib\FlashMessage;
use Tests\TestCase;

class ParticipantTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // route() precisa das rotas carregadas.
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/events/1/enrollments';
        unset($_SESSION['user'], $_SESSION['flash']);
    }

    public function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SESSION['user'], $_SESSION['flash']);
        Router::reset();
        parent::tearDown();
    }

    private function createUser(string $role): User
    {
        $user = new User([
            'name' => 'Usuário',
            'email' => "{$role}@example.com",
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    public function test_handle_with_admin_session_should_redirect_to_admin_with_flash(): void
    {
        Auth::login($this->createUser('admin'));
        $middleware = new ParticipantDouble();

        $middleware->handle(new Request());

        $this->assertSame('/admin', $middleware->location);
        $this->assertSame(['danger' => 'Ação disponível apenas para participantes!'], FlashMessage::get());
    }

    public function test_handle_with_participant_session_should_not_redirect(): void
    {
        Auth::login($this->createUser('participant'));
        $middleware = new ParticipantDouble();

        $middleware->handle(new Request());

        $this->assertNull($middleware->location);
        $this->assertSame([], FlashMessage::get());
    }

    public function test_handle_without_session_should_not_redirect(): void
    {
        $middleware = new ParticipantDouble();

        $middleware->handle(new Request());

        // Sem sessão o Participant deixa passar. Ele roda ANTES do Authenticate (o grupo interno
        // roda primeiro), e é o Authenticate, em seguida, que manda para /login.
        $this->assertNull($middleware->location);
        $this->assertSame([], FlashMessage::get());
    }
}
```

Por quê:

- `route('admin.dashboard.index')` precisa do `Router` com as rotas:
  `Router::reset()` + `require` de `config/routes.php`.
- `new Request()` lê `$_SERVER`: método e URI setados no `setUp`.
- Três casos: admin -> `/admin` + flash; Participante passa; sem
  sessão passa.

## 8. `tests/Unit/Models/Events/EnrollmentTest.php`

Testes novos antes do helper `createParticipant()` (fim da classe). O
`setUp` (P2) já cria `$this->admin`, `$this->participant` e
`$this->event`.

```php
    public function test_admin_cannot_enroll_in_an_event(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);

        $this->assertFalse($enrollment->isValid());
        $this->assertFalse($enrollment->save());
        $this->assertEquals('administradores não podem se inscrever em eventos!', $enrollment->errors('base'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_admin_check_should_not_cast_a_malformed_user_id(): void
    {
        // Com (int), "1abc" viraria o id do admin e a mensagem de admin apareceria.
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => "{$this->admin->id}abc"]);

        $enrollment->isValid();

        $this->assertNotEquals('administradores não podem se inscrever em eventos!', $enrollment->errors('base'));
    }

    public function test_participant_can_still_enroll(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->isValid());
        $this->assertNull($enrollment->errors('base'));
    }
```

Por quê: prova a regra no model sem router. Admin recusado e nada
gravado; `'1abc'` não vira o admin (prova do `filter_var`);
Participante continua passando.

## 9. `tests/Integration/Controllers/EnrollmentsControllerTest.php`

Teste novo depois de `test_create_enrolls_the_logged_user`:

```php
    public function test_create_does_not_enroll_a_logged_admin(): void
    {
        Auth::login($this->admin);
        $event = $this->createEvent();

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        // O controller é chamado direto (sem o middleware da rota): quem barra aqui é o model.
        $this->assertSame("Location: /events/{$event->id}", $response);
        $this->assertEquals('administradores não podem se inscrever em eventos!', $_SESSION['flash']['danger']);
        $this->assertFalse($this->admin->isEnrolledIn($event));
        $this->assertCount(0, Enrollment::all());
    }
```

Por quê:

- Teste de controller chama a ação direto, sem router: o middleware
  não roda. Prova a segunda camada (model) e o flash do controller.
- `Auth::login($this->admin)` sobrescreve o login de Participante do
  `setUp`.

## 10. `tests/Integration/Controllers/EventsControllerTest.php`

Em `test_show_marks_full_event`, troque a linha que inscrevia o admin:

```php
        $full = $this->createEvent(['capacity' => 1]);
        // Admin não se inscreve; quem ocupa a vaga é outro Participante.
        $other = $this->createUser('Outro Participante', 'outro@example.com', 'participant');
        (new Enrollment(['event_id' => $full->id, 'user_id' => $other->id]))->save();
        $open = $this->createEvent(['capacity' => 2]);
```

Por quê: o teste de O2/P2 lotava o Evento inscrevendo o admin. Com a
regra no model, o `save()` falha calado e o Evento não lota.

Armadilha: todo teste que usa o admin como "enchimento" de vaga passa
a falhar; troque por um Participante.

## 11. `tests/Integration/Access/ParticipantEventsAccessTest.php`

Depois do último teste de inscrição de P2 (o que termina com
`assertCount(0, Enrollment::all())`) e antes de
`test_participant_should_enroll_and_return_to_the_event`:

```php
    /**
     * Ações só de Participante (middleware participant).
     *
     * @return array<string, array{string, string}>
     */
    public static function participantOnlyRoutes(): array
    {
        return [
            'events.enrollments.create' => ['POST', '/events/1/enrollments'],
        ];
    }

    #[DataProvider('participantOnlyRoutes')]
    public function test_participant_only_route_should_redirect_admin_to_admin_area(string $method, string $uri): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->request($method, $uri);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin', $response->getHeaderLine('Location'));
        $this->assertCount(0, Enrollment::all());
        $this->assertStringContainsString(
            'Ação disponível apenas para participantes!',
            (string) $client->get('/admin')->getBody()
        );
    }

    #[DataProvider('participantOnlyRoutes')]
    public function test_participant_only_route_should_redirect_to_login_without_session(string $method, string $uri): void
    {
        $response = $this->newClient()->request($method, $uri);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_admin_should_not_see_the_enroll_button(): void
    {
        $body = (string) $this->loggedClient($this->admin)->get("/events/{$this->event->id}")->getBody();

        $this->assertStringNotContainsString('Inscrever-se', $body);
        $this->assertStringContainsString('Inscrições são feitas pelos participantes.', $body);
    }
```

Por quê:

- Teste de acesso sobe o servidor e bate por HTTP: passa pelo router,
  então prova o middleware de verdade (URL direta, não só botão).
- Provider: P3/P4 só acrescentam uma linha quando entrarem.
- Admin -> 302 `/admin`, flash na página seguinte, nada gravado.
- Sem sessão -> `/login`: prova a ordem (`Participant` deixa passar,
  `Authenticate` barra).
- `DataProvider` já está importado (P2).
- Participante passando já é coberto por
  `test_participant_should_enroll_and_return_to_the_event` (P2).

## 12. `tests/Acceptance/events/AdminCannotEnrollCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Acceptance\admin\events\BaseEventsCest;
use Tests\Support\AcceptanceTester;

/**
 * O Administrador vê a página do Evento na área do Participante, mas sem o botão de inscrição.
 * (BaseEventsCest já faz login como Administrador.)
 */
class AdminCannotEnrollCest extends BaseEventsCest
{
    public function adminSeesNoEnrollButton(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Administrador não vê o botão Inscrever-se');
        $page->amOnPage("/events/{$event->id}");

        $page->see($event->title, 'h1');
        $page->see('Inscrições são feitas pelos participantes.');
        $page->dontSee('Inscrever-se');
        Assert::assertCount(0, Enrollment::where(['event_id' => $event->id]));
    }

    public function participantStillSeesTheEnrollButton(AcceptanceTester $page): void
    {
        $page->wantTo('Participante continua vendo o botão Inscrever-se');
        $page->amOnPage('/logout');
        $page->login($this->participants[2]->email, self::PASSWORD);
        $page->amOnPage("/events/{$this->events[2]->id}");

        $page->see('Inscrever-se');
        $page->dontSee('Inscrições são feitas pelos participantes.');
    }
}
```

Por quê:

- Navegador real (Selenium). Estende o `BaseEventsCest` do admin:
  seed de Eventos e Participantes e login de admin prontos.
- Admin vê a página sem botão; Participante (logout + login) ainda vê.

## Fechamento

```sh
./run composer dump-autoload -o --strict-psr   # middleware novo
./run db:populate       # populate passa pela validação nova
./run test tests/Unit/App/Middleware/ParticipantTest.php
./run test tests/Unit/Models/Events/EnrollmentTest.php
./run test tests/Integration/Access/ParticipantEventsAccessTest.php
./run test              # Unit + Integration (o PHPUnit não roda os Cests)
docker compose up -d selenium && sleep 5
./run test:browser      # Acceptance
docker compose stop selenium
./run phpcs
./run phpstan
```

Schema não muda. No navegador: `admin@example.com` / `123456`, abrir
`/events/2`: sem botão, "Inscrições são feitas pelos participantes.".
`POST` à mão em `/events/2/enrollments` -> volta para `/admin` com
"Ação disponível apenas para participantes!". Participante continua
vendo "Inscrever-se" e se inscrevendo.

Commit (inglês): `feat: Restrict enrollment to participants`.

## Armadilhas (resumo)

- Só esconder o botão: `POST` feito à mão continua inscrevendo. Teste
  a URL direta.
- Só o middleware: populate, console e teste de controller (ação sem
  router) ainda criam inscrição de admin. Middleware e model não são
  excludentes; o model protege caminhos futuros (P13, P15).
- `Participant` redirecionando com usuário nulo: o grupo interno roda
  primeiro, então ele vê sessão vazia antes do `auth`; visitante iria
  para `/admin`. Copie `$user !== null && ...` de `Admin.php`.
- Grupo `participant` fora do `auth`: sem sessão, a ação roda sem
  usuário.
- `redirectTo` `private` + `exit`: middleware sem teste unitário;
  deixe `protected`.
- `redirectTo` duplicado em três middlewares: crítica A1 da 4.1
  ("centralizar"); se já houver base comum, use-a.
- `(int)` no `user_id` do model: `'1abc'` vira `1` (o admin). Use
  `filter_var(..., FILTER_VALIDATE_INT)`.
- Esquecer o alias em `config/App.php`: nome `participant` não resolve.
- `currentUser()->isAdmin()` sem `?->` na view: quebra sem sessão.
- Testes que inscrevem o admin como enchimento quebram; troque por
  Participante.
