# Q5 passo a passo: Token CSRF nos formulários de Evento

Pedido típico: "protejam criar, editar e remover Evento contra CSRF: todo
POST/PUT/DELETE da área logada só pode ser aceito com um token gerado pelo
servidor. Usem um middleware" (variante do A15 da 4.1, agora no CRUD).
Chance: média-baixa. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/q5-token-csrf/` (patch, bundle e árvore
`projeto/`). Este arquivo é o mesmo conteúdo em formato "um arquivo por
vez, com o motivo".

Pré-requisito: O9 (`fvp/4.2/exemplos/o9-method-so-via-post/`) aplicado.
O patch traz dois commits: o 1º é a base O9 (`fix: Accept _method override
only from POST body`); este guia cobre só o 2º. O que o O9 deixa pronto:

- `core/Http/Request.php`: `_method` só troca o método se a requisição
  real é `POST` e o valor vem de `$_POST` (lista fechada `PUT`/`DELETE`).
  `GET /admin/events/16?_method=DELETE` cai no `show`, não no `destroy`.
- `EventsAccessTest` tem
  `test_admin_should_destroy_event_with_method_delete_in_post_body`
  (este guia passa a mandar o token nele).

O9 fecha o CSRF por `<img>`/link; Q5 fecha o form forjado em outro site.
Schema não muda.

Ordem = fluxo da requisição: lib (token) -> helper -> middleware -> alias
-> rotas -> views -> testes. Na prova, prioridade: funcionar no navegador,
depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `lib/Csrf.php` (novo)
2. `core/helpers.php`
3. `app/Middleware/VerifyCsrf.php` (novo)
4. `config/App.php`
5. `config/routes.php`
6. `app/views/admin/events/_form.phtml`
7. `app/views/admin/events/index.phtml`
8. `tests/Unit/Lib/CsrfTest.php` (novo)
9. `tests/Unit/App/Middleware/VerifyCsrfDouble.php` (novo)
10. `tests/Unit/App/Middleware/VerifyCsrfTest.php` (novo)
11. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
12. `tests/Integration/Access/EventsAccessTest.php`

Depois: `./run composer dump-autoload -o --strict-psr` (classes novas),
suíte, `./run phpcs`.

## 1. `lib/Csrf.php` (novo)

```php
<?php

namespace Lib;

/**
 * Token CSRF por sessão.
 *
 * token() gera 32 bytes aleatórios (64 caracteres hex) na primeira chamada e guarda em
 * $_SESSION['_token']; as chamadas seguintes devolvem o mesmo valor. check() compara com
 * hash_equals (tempo constante).
 */
class Csrf
{
    public const FIELD = '_token';

    public static function token(): string
    {
        $token = $_SESSION[self::FIELD] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::FIELD] = $token;
        }

        return $token;
    }

    /**
     * true só se há token na sessão e o valor enviado é uma string igual a ele.
     */
    public static function check(mixed $token): bool
    {
        $expected = $_SESSION[self::FIELD] ?? null;

        if (!is_string($expected) || $expected === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($expected, $token);
    }
}
```

Por quê:

- `lib/` = namespace `Lib\` (PSR-4), mesmo lugar de `FlashMessage` e
  `Validations`. Regra reaproveitável sai do controller.
- Token por **sessão**, gerado uma vez: várias abas funcionam. Gerar a
  cada `token()` invalida a aba anterior.
- `random_bytes` (criptográfico), não `rand`/`uniqid`.
- `hash_equals`: comparação em tempo constante. `===` funciona, mas vaza
  tempo; é o que o professor espera ver.
- `check(mixed)`: o POST pode trazer array (`_token[]=x`); `is_string`
  barra antes do `hash_equals` (que exige string).
- Sem token na sessão -> `false`: nunca aceita `'' === ''`.
- `FIELD` constante: helper, middleware e testes usam o mesmo nome.

## 2. `core/helpers.php`

Depois do bloco `if (!function_exists('dd')) { ... }`, antes do de
`route`:

```php
if (!function_exists('csrf_field')) {
    /**
     * Campo oculto com o token CSRF da sessão, para os forms que fazem POST/PUT/DELETE.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="' . \Lib\Csrf::FIELD . '" value="'
            . htmlspecialchars(\Lib\Csrf::token()) . '">';
    }
}
```

Por quê:

- `core/helpers.php` está em `autoload.files` do `composer.json`: função
  global, disponível em toda view (igual a `route()`).
- View burra: só `<?= csrf_field() ?>`, sem montar HTML nem chamar sessão.
- `htmlspecialchars` no `value`: escape sempre (rubrica cobra).
- `function_exists`: padrão do arquivo, evita redeclarar.

## 3. `app/Middleware/VerifyCsrf.php` (novo)

Modelo: `app/Middleware/Admin.php` (mesma interface).

```php
<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Csrf;

/**
 * Requisições que alteram estado (POST, PUT, DELETE) precisam do campo _token igual ao da
 * sessão. GET passa direto. Sem token ou com token errado: 403.
 */
class VerifyCsrf implements Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD'];

    public function handle(Request $request): void
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        // Só do corpo do POST (como o _method, que também só vale no corpo): token na query string vaza em log e Referer.
        if (!Csrf::check($_POST[Csrf::FIELD] ?? null)) {
            $this->deny();
        }
    }

    /**
     * protected para o teste unitário trocar o exit por um registro (dublê).
     */
    protected function deny(): void
    {
        http_response_code(403);
        echo '<h1>403 - Formulário expirado ou inválido</h1>'
            . '<p>Volte à página anterior, recarregue e envie de novo.</p>';
        exit;
    }
}
```

Por quê:

- Middleware e não `if` em cada ação: vale para toda rota do grupo,
  inclusive as que vierem depois (P5, P6, P16...).
- `GET`/`HEAD` passam: barrar `GET` quebra listagem e links.
- `$request->getMethod()` já vem resolvido pelo O9 (`POST` + `_method`
  no corpo -> `PUT`/`DELETE`): PUT e DELETE também exigem token.
- Token lido de `$_POST`, não `getParam` (que lê `$_REQUEST`, com query
  string). Exceção à regra "parâmetro só via `getParam`".
- 403 e `exit`, não redirect: falha de segurança, não de preenchimento;
  o `Referer` pode faltar. Alternativa aceita: `FlashMessage::danger(
  'Sessão expirada, tente de novo.')` + redirect (padrão de `Admin.php`).
- `deny()` `protected`: o dublê do teste troca o `exit` por um flag.

Armadilha: `deny()` `private` com `exit` -> o teste unitário do
middleware não roda (o `exit` mata o PHPUnit).

## 4. `config/App.php`

Em `$middlewareAliases`, depois de `'admin'`:

```php
        'admin' => \App\Middleware\Admin::class,
        'csrf' => \App\Middleware\VerifyCsrf::class,
    ];
```

Por quê: `Route::middleware('csrf')` procura o nome aqui
(`RouteWrapperMiddleware::middlewareInstance()` faz `new $class()`).
Sem o alias, `App::$middlewareAliases['csrf']` não existe e a carga das
rotas falha.

## 5. `config/routes.php`

Envolva o grupo `auth` inteiro (que já contém o `admin`) com um grupo
`csrf` **por fora**. Login e logout ficam fora (antes do bloco). Todo o
miolo ganha um nível de indentação; nenhuma rota muda.

```php
Route::get('/logout', [AuthenticationsController::class, 'destroy'])->name('users.logout');

// Token CSRF em todo POST/PUT/DELETE da área logada (GET passa). Fica POR FORA do 'auth': o
// RouteWrapperMiddleware acrescenta os middlewares quando cada grupo fecha, então o grupo interno roda
// primeiro. Assim auth e admin decidem antes (sem sessão -> /login; Participante -> /dashboard) e o
// csrf só confere quem já pode estar ali.
Route::middleware('csrf')->group(function () {
    // Área do usuário (somente autenticados)
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        // Área administrativa (somente autenticados com perfil admin)
        Route::middleware('admin')->group(function () {
            Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard.index');

            // ... rotas admin.events.* iguais, só reindentadas ...
            Route::delete('/admin/events/{id}', [AdminEventsController::class, 'destroy'])
                ->name('admin.events.destroy');
        });
    });
});
```

Por quê:

- `RouteWrapperMiddleware::group()` roda o callback e **depois** faz
  `addMiddleware` nas rotas criadas. O grupo interno fecha primeiro, então
  seus middlewares entram (e rodam) primeiro.
- Rota admin roda `admin` -> `auth` -> `csrf`. Sem sessão continua indo
  para `/login`; Participante para `/dashboard`; testes de acesso
  existentes não mudam. Só depois o token é conferido.
- Login fora: o A15 da 4.1 cobre; logout é `GET`.

Armadilhas:

- `csrf` por dentro do `auth` (ou só no `admin`), no core atual: roda
  antes da autenticação; POST sem sessão vira 403 em vez de `/login` e os
  testes de acesso (`ACC:130-137`) quebram.
- Com o A17 da 4.1 aplicado (grupo externo roda primeiro): inverta,
  `csrf` como grupo **mais interno**. Confira a ordem do seu core antes.

## 6. `app/views/admin/events/_form.phtml`

Logo depois da abertura do `<form>` (linha ~16), antes do `_method`:

```php
<form action="<?= $action ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <?php if ($isUpdate) : ?>
        <input type="hidden" name="_method" value="PUT">
```

Por quê: o mesmo partial serve `new` (POST) e `edit` (PUT via
`_method`). Uma linha cobre os dois.

## 7. `app/views/admin/events/index.phtml`

No form "Remover" de cada linha (linhas ~30-33), antes do `_method`:

```php
                        <form class="d-inline" action="<?= route('admin.events.destroy', ['id' => $event->id]) ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_method" value="DELETE">
```

Por quê: remover é `DELETE` (altera estado). Sem o campo, o botão passa
a dar 403.

Armadilha: todo form `method="post"` precisa do `csrf_field()`, inclusive
os de outros cenários (P5, P6, P16, P17, P2...).

## 8. `tests/Unit/Lib/CsrfTest.php` (novo)

```php
<?php

namespace Tests\Unit\Lib;

use Lib\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    public function setUp(): void
    {
        unset($_SESSION['_token']);
    }

    public function tearDown(): void
    {
        unset($_SESSION['_token']);
    }

    public function test_token_should_be_generated_and_stored_in_session(): void
    {
        $token = Csrf::token();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $_SESSION['_token']);
    }

    public function test_token_should_be_stable_within_session(): void
    {
        $this->assertSame(Csrf::token(), Csrf::token());
    }

    public function test_check_with_session_token_should_return_true(): void
    {
        $this->assertTrue(Csrf::check(Csrf::token()));
    }

    public function test_check_with_wrong_token_should_return_false(): void
    {
        $token = Csrf::token();

        $this->assertFalse(Csrf::check(strrev($token)));
        $this->assertFalse(Csrf::check(substr($token, 0, 63)));
    }

    public function test_check_without_token_should_return_false(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::check(null));
        $this->assertFalse(Csrf::check(''));
        $this->assertFalse(Csrf::check([Csrf::token()]));
    }

    public function test_check_without_session_token_should_return_false(): void
    {
        $this->assertFalse(Csrf::check(''));
        $this->assertFalse(Csrf::check('abc'));
    }

    public function test_csrf_field_should_render_a_hidden_input_with_the_token(): void
    {
        $this->assertSame(
            '<input type="hidden" name="_token" value="' . Csrf::token() . '">',
            csrf_field()
        );
    }
}
```

Por quê:

- 7 testes: gera e guarda; estável; certo -> `true`; errado/truncado,
  `null`/vazio/array, sem sessão -> `false`; helper renderiza o campo.
- `setUp`/`tearDown` limpam `$_SESSION['_token']`: sem isso um teste
  herda o token do anterior.
- "Estável na sessão" pega quem gera token novo a cada chamada.

## 9. `tests/Unit/App/Middleware/VerifyCsrfDouble.php` (novo)

```php
<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\VerifyCsrf;

/**
 * Dublê do VerifyCsrf: registra a recusa em vez de responder 403 e dar exit.
 */
class VerifyCsrfDouble extends VerifyCsrf
{
    public bool $denied = false;

    protected function deny(): void
    {
        $this->denied = true;
    }
}
```

Por quê: o `deny()` real dá `exit`. O dublê (padrão do F2 da 4.1) troca
por um flag que o teste lê. Só funciona porque `deny()` é `protected`.
Arquivo próprio: PSR-4 estrito exige uma classe por arquivo.

## 10. `tests/Unit/App/Middleware/VerifyCsrfTest.php` (novo)

```php
<?php

namespace Tests\Unit\App\Middleware;

use Core\Http\Request;
use Lib\Csrf;
use PHPUnit\Framework\TestCase;

class VerifyCsrfTest extends TestCase
{
    public function setUp(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/events';
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        unset($_SESSION['_token']);
    }

    public function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SESSION['_token']);
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     */
    private function handle(string $method, array $post = [], array $get = []): VerifyCsrfDouble
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = $post;
        $_GET = $get;
        $_REQUEST = array_merge($get, $post);

        $middleware = new VerifyCsrfDouble();
        $middleware->handle(new Request());

        return $middleware;
    }

    public function test_get_should_pass_without_token(): void
    {
        $this->assertFalse($this->handle('GET')->denied);
    }

    public function test_post_without_token_should_be_denied(): void
    {
        Csrf::token();

        $this->assertTrue($this->handle('POST')->denied);
    }

    public function test_post_with_wrong_token_should_be_denied(): void
    {
        Csrf::token();

        $this->assertTrue($this->handle('POST', ['_token' => 'errado'])->denied);
    }

    public function test_post_with_session_token_should_pass(): void
    {
        $this->assertFalse($this->handle('POST', ['_token' => Csrf::token()])->denied);
    }

    public function test_put_and_delete_via_method_override_should_require_the_token(): void
    {
        $token = Csrf::token();

        $this->assertTrue($this->handle('POST', ['_method' => 'PUT'])->denied);
        $this->assertTrue($this->handle('POST', ['_method' => 'DELETE'])->denied);
        $this->assertFalse($this->handle('POST', ['_method' => 'DELETE', '_token' => $token])->denied);
    }

    public function test_token_only_in_query_string_should_be_denied(): void
    {
        $this->assertTrue($this->handle('POST', [], ['_token' => Csrf::token()])->denied);
    }
}
```

Por quê:

- `handle()` monta `$_SERVER`/`$_POST`/`$_GET`/`$_REQUEST` como o PHP
  faria e cria um `Request` real: testa o middleware sem servidor.
- `$_REQUEST = array_merge($get, $post)`: simula a mistura real; o último
  teste prova que token só na query string é recusado.
- PUT/DELETE via `_method` dependem do `Request` do O9.
- `tearDown` limpa tudo: superglobais vazam entre testes.

## 11. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Três asserts, um por form:

a) `test_index_lists_five_events_per_page`, depois de
`assertStringContainsString('Evento 6', $page2);`:

```php
        // O form "Remover" de cada linha traz o token CSRF.
        $this->assertStringContainsString('name="_token" value="' . \Lib\Csrf::token() . '"', $page1);
```

b) `test_new_renders_form`, depois de
`assertStringNotContainsString('name="_method"', $response);`:

```php
        // O form traz o token CSRF da sessão.
        $this->assertStringContainsString('name="_token" value="' . \Lib\Csrf::token() . '"', $response);
```

c) `test_edit_renders_form_filled`, depois de
`assertStringContainsString('name="_method" value="PUT"', $response);`:

```php
        $this->assertStringContainsString('name="_token" value="' . \Lib\Csrf::token() . '"', $response);
```

Por quê:

- Teste de controller chama a ação direto, **sem Router**
  (`ControllerTestCase:70-84`): não roda middleware e não precisa de
  token para POST. Serve só para provar que as views trazem o campo.
- Mesma sessão do teste: `Csrf::token()` devolve o mesmo valor que a view
  imprimiu.

## 12. `tests/Integration/Access/EventsAccessTest.php`

a) Teste do O9
`test_admin_should_destroy_event_with_method_delete_in_post_body`: passa a
mandar o token (senão agora dá 403):

```php
        $response = $client->post("/admin/events/{$this->event->id}", ['form_params' => [
            '_method' => 'DELETE',
            '_token' => $this->csrfToken($client),
        ]]);
```

b) Logo depois desse teste, antes de
`test_admin_should_access_events_index_and_new`, helpers e testes novos
(`Client` e `DataProvider` já estão nos `use`):

```php
    /* ------------------- token CSRF ------------------- */

    /**
     * Lê o token CSRF da sessão do cliente na página do formulário (como o navegador faria).
     */
    private function csrfToken(Client $client): string
    {
        $body = (string) $client->get('/admin/events/new')->getBody();
        $this->assertSame(1, preg_match('/name="_token" value="([0-9a-f]{64})"/', $body, $matches));

        return $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function newEventParams(): array
    {
        return ['event' => [
            'title' => 'Evento via POST',
            'description' => 'Criado no teste de CSRF.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => ['students'],
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => '5',
        ]];
    }

    /**
     * Rotas que alteram estado, como o navegador as envia (POST, com _method quando é PUT/DELETE).
     *
     * @return array<string, array{string, array<string, string>}>
     */
    public static function stateChangingRoutes(): array
    {
        return [
            'admin.events.create' => ['/admin/events', []],
            'admin.events.update' => ['/admin/events/1', ['_method' => 'PUT']],
            'admin.events.destroy' => ['/admin/events/1', ['_method' => 'DELETE']],
        ];
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('stateChangingRoutes')]
    public function test_admin_request_without_csrf_token_should_be_forbidden(string $uri, array $params): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->post($uri, ['form_params' => $params + $this->newEventParams(), 'http_errors' => false]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertCount(1, Event::all());
        $this->assertEquals('Palestra de Acesso', Event::findById((int) $this->event->id)?->title);
    }

    public function test_admin_request_with_wrong_csrf_token_should_be_forbidden(): void
    {
        $client = $this->loggedClient($this->admin);
        $this->csrfToken($client);

        $response = $client->post("/admin/events/{$this->event->id}", [
            'form_params' => ['_method' => 'DELETE', '_token' => str_repeat('0', 64)],
            'http_errors' => false,
        ]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNotNull(Event::findById((int) $this->event->id));
    }

    public function test_admin_should_create_event_with_csrf_token(): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->post('/admin/events', [
            'form_params' => ['_token' => $this->csrfToken($client)] + $this->newEventParams(),
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNotNull(Event::findBy(['title' => 'Evento via POST']));
    }

    public function test_csrf_token_from_another_session_should_be_forbidden(): void
    {
        $other = $this->loggedClient($this->admin);
        $token = $this->csrfToken($other);
        $client = $this->loggedClient($this->admin);

        $response = $client->post("/admin/events/{$this->event->id}", [
            'form_params' => ['_method' => 'DELETE', '_token' => $token],
            'http_errors' => false,
        ]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNotNull(Event::findById((int) $this->event->id));
    }
```

Por quê:

- Teste de acesso sobe HTTP real (Guzzle -> `web:8080`) e passa pelo
  Router: é a única prova de que o middleware está ligado na rota certa.
- `csrfToken()` lê o token da página `new`, como o navegador. O cliente
  tem cookie (`'cookies' => true`): mesma sessão.
- Provider: create, update, destroy sem token -> 403 e nada muda.
- `'http_errors' => false`: sem isso o Guzzle lança exceção no 403.
- Token de outra sessão -> 403: prova que o token é por sessão.
- Testes "sem sessão -> /login" já existentes continuam verdes: prova de
  que o `csrf` roda depois do `auth`.

Armadilha: `loggedClient` posta `/login`. Se o login também exigir token
(A15), o helper precisa buscar a página antes.

## Fechamento

```sh
./run composer dump-autoload -o --strict-psr
./run test tests/Unit/Lib/CsrfTest.php
./run test tests/Unit/App/Middleware/VerifyCsrfTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser      # Cests passam sem mudança: o form já leva o token
docker compose stop selenium
./run phpcs
./run phpstan
```

Sem mudança de schema: não precisa `./run db:populate`. Esperado: Unit
178, Integration 55, Acceptance 23 (com só O9: 165 / 49 / 23).

Sem token, de dentro da rede do compose:

```sh
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -o /dev/null -w '%{http_code}\n' \
    -b /tmp/c -d '_method=DELETE' http://localhost/admin/events/16
```

Resposta: `403`.

No navegador (admin): criar, editar e remover pela listagem continuam
funcionando. Ver código-fonte do form: `<input type="hidden"
name="_token" value="...64 hex...">`.

Commit (inglês): `feat: Protect Event forms with a CSRF token`.

## Armadilhas (resumo)

- Ordem: `csrf` por dentro do `auth`, no core atual, roda antes da
  autenticação; POST anônimo dá 403 em vez de `/login`.
- Com o A17 da 4.1 (grupo de fora roda primeiro): `csrf` como grupo mais
  interno.
- `EventsControllerTest` não passa pelo Router (`CTC:70-84`): não vê o
  middleware; a prova é por HTTP (`EventsAccessTest`) e por Cest.
- `loggedClient` (`ACC:67-78`) posta `/login`: se o login exigir token,
  buscar a página antes (A15 da 4.1).
- Token por sessão, não por requisição: abas múltiplas funcionam.
- Ler `_token` de `$_POST`; `getParam` lê `$_REQUEST` (aceita query
  string). Combine com O9.
- Barrar `GET` também: listagem e links quebram.
- `===` em vez de `hash_equals`: vaza tempo de comparação.
- Esquecer `csrf_field()` em algum form: o botão dá 403.
- `deny()` `private` com `exit`: teste unitário do middleware não roda.
