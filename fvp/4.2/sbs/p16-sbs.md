# P16 passo a passo: Remover só com código de confirmação gerado no backend

Pedido típico: "para remover um Evento o sistema mostra um código de 4
dígitos gerado na hora (finja que foi por e-mail); o admin digita o
código e só então a remoção acontece. Errado ou em branco: avise e não
remova". É a FVP real da 4.1 (P12) aplicada à remoção. Chance:
média-alta. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p16-remover-com-codigo-confirmacao/`
(patch, bundle e árvore `projeto/`). Um commit, sobre `main`. Sem
pré-requisito de outro cenário.

Ordem = fluxo da requisição: classe de apoio (`lib/`) -> rota ->
controller -> views -> testes. Schema não muda. Na prova, prioridade:
funcionar no navegador, depois testar, depois `phpcs`.

Obs: o catálogo esboça `issue()`, ação `delete` e chave `event-{id}`;
o patch usa `generate()`, ação `confirmDestroy` e chave `event.{id}`.
Abaixo vale o patch.

## Arquivos, em ordem

1. `lib/ConfirmationCode.php` (novo)
2. `config/routes.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/delete.phtml` (novo)
5. `app/views/admin/events/index.phtml`
6. `tests/Unit/Lib/ConfirmationCodeTest.php` (novo)
7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
8. `tests/Integration/Access/EventsAccessTest.php`
9. `tests/Acceptance/admin/events/DeleteEventCest.php`

Depois: `./run composer dump-autoload -o --strict-psr`, suíte,
`./run phpcs`, `./run phpstan`.

## 1. `lib/ConfirmationCode.php` (novo)

```php
<?php

namespace Lib;

/**
 * Código de confirmação de 4 dígitos para ações destrutivas (ex.: remover um Evento).
 *
 * O código é gerado no servidor e guardado em $_SESSION, um por chave (ex.: "event.7"), para que
 * só quem viu a página de confirmação consiga concluir a ação. Estática como FlashMessage; nos
 * testes basta usar o array $_SESSION, sem sessão real.
 */
class ConfirmationCode
{
    public const MIN = 1000;
    public const MAX = 9999;

    private const SESSION_KEY = 'confirmation_codes';

    /**
     * Gera um código novo para a chave (substitui o anterior) e o devolve.
     */
    public static function generate(string $key): int
    {
        $code = random_int(self::MIN, self::MAX);
        $_SESSION[self::SESSION_KEY][$key] = $code;

        return $code;
    }

    public static function current(string $key): ?int
    {
        $code = $_SESSION[self::SESSION_KEY][$key] ?? null;

        return is_int($code) ? $code : null;
    }

    /**
     * true só se existe código para a chave e o valor enviado é igual a ele.
     * Aceita string (vinda do formulário) ou int; qualquer outro tipo é recusado.
     */
    public static function check(string $key, mixed $code): bool
    {
        $expected = self::current($key);

        if ($expected === null || !(is_string($code) || is_int($code))) {
            return false;
        }

        return trim((string) $code) === (string) $expected;
    }

    public static function clear(string $key): void
    {
        unset($_SESSION[self::SESSION_KEY][$key]);
    }
}
```

Por quê:

- Classe pequena em `lib/`, estática como `FlashMessage`: lógica fora
  do controller, testável só com o array `$_SESSION` (sem
  `session_start`).
- `random_int` no **servidor**, guardado na sessão. O form nunca leva o
  valor certo (nem em `hidden`): `DELETE` forjado sem passar pela tela
  é recusado.
- Uma chave por Evento: duas abas não misturam códigos; código do
  Evento A não remove o B.
- `check` aceita só string/int: `confirmation_code[]=x` vira `false`,
  sem `TypeError`. Compara `===` entre strings (nada de `==`).
- Namespace `Lib` em `lib/`: PSR-4. Classe nova ->
  `composer dump-autoload`.
- `lib/` fora do `phpstan`: tipe tudo; o teste unitário cobre.

## 2. `config/routes.php`

No grupo `admin`, depois de `admin.events.create` e **antes** de
`GET /admin/events/{id}`:

```php
        Route::post('/admin/events', [AdminEventsController::class, 'create'])->name('admin.events.create');
        // Confirmação de remoção: antes de '{id}', como 'new'.
        Route::get('/admin/events/{id}/delete', [AdminEventsController::class, 'confirmDestroy'])
            ->name('admin.events.delete');
        Route::get('/admin/events/{id}', [AdminEventsController::class, 'show'])->name('admin.events.show');
```

Por quê:

- `GET .../delete` só mostra a tela (não muda nada); a ação continua no
  `DELETE /admin/events/{id}` (RESTful).
- Grupo `admin`: middleware já protege.
- Rotas fixas antes do `{id}`: padrão do projeto (mesmo cuidado de
  `new`). Hoje `1/delete` não casaria com `{id}`, mas mantenha a ordem.
- Nome `admin.events.delete` segue o padrão `admin.events.*`.

## 3. `app/Controllers/Admin/EventsController.php`

a) `use`, depois de `use Core\Http\Request;`:

```php
use Lib\ConfirmationCode;
```

b) Método novo, logo antes de `destroy` (depois do fim de `update`):

```php
    /**
     * Página de confirmação da remoção: gera um código novo, guardado na sessão, e o mostra
     * na tela (simula o envio por e-mail).
     */
    public function confirmDestroy(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $code = ConfirmationCode::generate($this->confirmationKey($event));

        $title = 'Remover Evento';
        $this->render('admin/events/delete', compact('event', 'code', 'title'));
    }
```

c) Em `destroy`, depois do `if ($event === null) { ... }` e antes de
`if ($event->safeDestroy())`:

```php
        // O código vale para uma única tentativa, certa ou errada: sem isso, DELETEs repetidos
        // poderiam testar os 9000 valores. Depois de um erro, a página de confirmação gera outro.
        $key = $this->confirmationKey($event);
        $valid = ConfirmationCode::check($key, $request->getParam('confirmation_code'));
        ConfirmationCode::clear($key);

        if (!$valid) {
            FlashMessage::danger('Código de confirmação inválido! O evento não foi removido.');
            $this->redirectTo(route('admin.events.delete', ['id' => $event->id]));
            return;
        }

        if ($event->safeDestroy()) {
```

d) Helper privado, depois de `findEvent` e antes de `eventNotFound`:

```php
    private function confirmationKey(Event $event): string
    {
        return "event.{$event->id}";
    }
```

Por quê:

- `confirmDestroy` reusa `findEvent`/`eventNotFound` (id inexistente
  -> flash + listagem). Cada visita gera código novo.
- `$title` no `compact`: o layout imprime o `<h1>`; faltando, 500.
- `clear` **sempre** depois do `check`: uso único. Se o errado não
  invalidasse, um script testaria os 9000 valores com o mesmo código.
- Errado -> PRG de volta para `admin.events.delete` (gera outro).
- `return` depois do `redirectTo`: em produção ele dá `exit`, mas no
  teste de controller só imprime e seguiria para o `safeDestroy`.
- Certo -> fluxo antigo: `safeDestroy` mantém a regra P03 (Evento com
  Inscrições não sai).
- `confirmationKey` num lugar só: gerar e conferir usam a mesma chave.

## 4. `app/views/admin/events/delete.phtml` (novo)

```php
<?php
/**
 * Confirmação da remoção do Evento.
 *
 * @var \App\Models\Event $event
 * @var int $code código gerado no servidor e guardado na sessão
 */
?>
<p>
    Você está removendo o evento <strong><?= htmlspecialchars($event->title) ?></strong>
    (<?= date('d/m/Y', strtotime($event->starts_on)) ?>). Esta ação não pode ser desfeita.
</p>

<div class="alert alert-warning">
    Código de confirmação: <strong id="confirmation_code_value"><?= (int) $code ?></strong>
    <div class="small">Em produção este código seria enviado por e-mail; aqui ele aparece na tela.</div>
</div>

<form action="<?= route('admin.events.destroy', ['id' => $event->id]) ?>" method="post" novalidate>
    <input type="hidden" name="_method" value="DELETE">

    <div class="mb-3">
        <label for="confirmation_code" class="form-label">Digite o código para confirmar</label>
        <input type="text" class="form-control" id="confirmation_code" name="confirmation_code"
            inputmode="numeric" maxlength="4" autocomplete="off">
    </div>

    <button type="submit" class="btn btn-danger">Remover</button>
    <a class="btn btn-secondary" href="<?= route('admin.events.show', ['id' => $event->id]) ?>">Cancelar</a>
</form>
```

Por quê:

- Nome do arquivo = `render('admin/events/delete')`.
- View burra: só mostra `$event` e `$code` recebidos.
- Código num `<strong id="confirmation_code_value">`: o Cest lê com
  `grabTextFrom`. Campo digitável vazio; valor certo **não** vai em
  `hidden`.
- `_method=DELETE`: form HTML só faz POST; o framework troca o verbo.
- `htmlspecialchars` no título; `(int)` no código.
- Mostrar o código na tela não protege contra o próprio usuário: é
  prova de leitura antes de ação irreversível (pedido da 4.1).

## 5. `app/views/admin/events/index.phtml`

Célula de ações, depois do link "Editar": o form `DELETE` vira link.

Sai:

```php
                        <form class="d-inline" action="<?= route('admin.events.destroy', ['id' => $event->id]) ?>" method="post">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remover</button>
                        </form>
```

Entra:

```php
                        <a class="btn btn-sm btn-outline-danger"
                           href="<?= route('admin.events.delete', ['id' => $event->id]) ?>">Remover</a>
```

Por quê: o `DELETE` direto da listagem passa a ser recusado (sem
código). "Remover" só abre a tela de confirmação (GET).

## 6. `tests/Unit/Lib/ConfirmationCodeTest.php` (novo)

```php
<?php

namespace Tests\Unit\Lib;

use Lib\ConfirmationCode;
use PHPUnit\Framework\TestCase;

class ConfirmationCodeTest extends TestCase
{
    public function setUp(): void
    {
        unset($_SESSION['confirmation_codes']);
    }

    public function tearDown(): void
    {
        unset($_SESSION['confirmation_codes']);
    }

    public function test_generate_should_return_a_four_digit_code(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = ConfirmationCode::generate('event.1');

            $this->assertGreaterThanOrEqual(1000, $code);
            $this->assertLessThanOrEqual(9999, $code);
        }
    }

    public function test_generate_should_store_the_code_in_the_session(): void
    {
        $code = ConfirmationCode::generate('event.1');

        $this->assertSame($code, ConfirmationCode::current('event.1'));
        $this->assertSame($code, $_SESSION['confirmation_codes']['event.1']);
    }

    public function test_current_should_be_null_without_a_code(): void
    {
        $this->assertNull(ConfirmationCode::current('event.1'));
    }

    public function test_each_key_should_have_its_own_code(): void
    {
        $first = ConfirmationCode::generate('event.1');
        ConfirmationCode::generate('event.2');

        $this->assertSame($first, ConfirmationCode::current('event.1'));
        $this->assertTrue(ConfirmationCode::check('event.1', (string) $first));
        $this->assertNotNull(ConfirmationCode::current('event.2'));
    }

    public function test_check_should_accept_the_right_code_as_string_or_int(): void
    {
        $code = ConfirmationCode::generate('event.1');

        $this->assertTrue(ConfirmationCode::check('event.1', (string) $code));
        $this->assertTrue(ConfirmationCode::check('event.1', " {$code} "));
        $this->assertTrue(ConfirmationCode::check('event.1', $code));
    }

    public function test_check_should_reject_a_wrong_code(): void
    {
        $code = ConfirmationCode::generate('event.1');
        $wrong = $code === 9999 ? 1000 : $code + 1;

        $this->assertFalse(ConfirmationCode::check('event.1', (string) $wrong));
        $this->assertFalse(ConfirmationCode::check('event.1', ''));
        $this->assertFalse(ConfirmationCode::check('event.1', null));
        $this->assertFalse(ConfirmationCode::check('event.1', [(string) $code]));
    }

    public function test_check_should_reject_the_code_of_another_key(): void
    {
        $code = ConfirmationCode::generate('event.1');

        $this->assertFalse(ConfirmationCode::check('event.2', (string) $code));
    }

    public function test_check_without_a_generated_code_should_be_false(): void
    {
        $this->assertFalse(ConfirmationCode::check('event.1', '1234'));
    }

    public function test_generate_again_should_replace_the_previous_code(): void
    {
        ConfirmationCode::generate('event.1');
        $second = ConfirmationCode::generate('event.1');

        $this->assertSame($second, ConfirmationCode::current('event.1'));
    }

    public function test_clear_should_remove_only_the_given_key(): void
    {
        $code = ConfirmationCode::generate('event.1');
        ConfirmationCode::generate('event.2');

        ConfirmationCode::clear('event.2');

        $this->assertNull(ConfirmationCode::current('event.2'));
        $this->assertFalse(ConfirmationCode::check('event.2', '1234'));
        $this->assertTrue(ConfirmationCode::check('event.1', (string) $code));
    }
}
```

Por quê:

- Estende `PHPUnit\Framework\TestCase` puro: não precisa de banco.
- `$_SESSION` sobrevive entre testes: limpe no `setUp`/`tearDown`.
- Cobre faixa, sessão, chave por Evento, string/int/espaços, errado,
  vazio, `null`, array, outra chave, sem código, substituição, `clear`.
- `$code === 9999 ? 1000 : $code + 1`: errado garantido, sem sair da
  faixa.

## 7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) `use`, depois de `use Lib\Authentication\Auth;`:

```php
use Lib\ConfirmationCode;
```

b) `tearDown`, depois de `unset($_SESSION['flash']);`:

```php
        unset($_SESSION['confirmation_codes']);
```

c) Os dois testes de `destroy` que já existiam
(`test_destroy_removes_event_without_enrollments` e o seguinte, com
`$this->enroll($event)`) geram o código e mandam no `params`:

```php
        $code = ConfirmationCode::generate("event.{$event->id}");

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $code]
        );
```

d) Testes novos, depois desses e antes do helper com
`@param array<string, mixed> $overrides`:

```php
    /* ------------------- remover com código de confirmação ------------------- */

    public function test_index_remove_button_links_to_the_confirmation_page(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString("href=\"/admin/events/{$event->id}/delete\">Remover</a>", $response);
        $this->assertStringNotContainsString('value="DELETE"', $response);
    }

    public function test_confirm_destroy_shows_a_code_stored_in_the_session(): void
    {
        $event = $this->createEvent(['title' => 'Evento a Remover']);

        $response = $this->get(action: 'confirmDestroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $code = ConfirmationCode::current("event.{$event->id}");
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/<h1>\s*Remover Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('Evento a Remover', $response);
        $this->assertStringContainsString("<strong id=\"confirmation_code_value\">{$code}</strong>", $response);
        $this->assertStringContainsString("action=\"/admin/events/{$event->id}\" method=\"post\"", $response);
        $this->assertStringContainsString('name="_method" value="DELETE"', $response);
        $this->assertStringContainsString('name="confirmation_code"', $response);
    }

    public function test_confirm_destroy_with_unknown_id_redirects_to_index(): void
    {
        $response = $this->get(action: 'confirmDestroy', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_destroy_without_code_keeps_the_event(): void
    {
        $event = $this->createEvent();
        ConfirmationCode::generate("event.{$event->id}");

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}/delete", $response);
        $this->assertEquals('Código de confirmação inválido! O evento não foi removido.', $_SESSION['flash']['danger']);
    }

    public function test_destroy_with_wrong_code_keeps_the_event_and_invalidates_the_code(): void
    {
        $event = $this->createEvent();
        $code = ConfirmationCode::generate("event.{$event->id}");
        $wrong = $code === 9999 ? 1000 : $code + 1;

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $wrong]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}/delete", $response);
        $this->assertEquals('Código de confirmação inválido! O evento não foi removido.', $_SESSION['flash']['danger']);
        // O código errado gasta a tentativa: nem o código certo vale mais.
        $this->assertNull(ConfirmationCode::current("event.{$event->id}"));
        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $code]
        );
        $this->assertNotNull(Event::findById($event->id));
    }

    public function test_destroy_without_visiting_the_confirmation_page_keeps_the_event(): void
    {
        $event = $this->createEvent();

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '1234']
        );

        $this->assertNotNull(Event::findById($event->id));
    }

    public function test_destroy_with_the_code_of_another_event_keeps_the_event(): void
    {
        $event = $this->createEvent(['title' => 'Alvo']);
        $other = $this->createEvent(['title' => 'Outro']);
        $otherCode = ConfirmationCode::generate("event.{$other->id}");

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $otherCode]
        );

        $this->assertNotNull(Event::findById($event->id));
    }

    public function test_destroy_with_right_code_removes_and_clears_the_code(): void
    {
        $event = $this->createEvent();
        $code = ConfirmationCode::generate("event.{$event->id}");

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $code]
        );

        $this->assertNull(Event::findById($event->id));
        $this->assertNull(ConfirmationCode::current("event.{$event->id}"));
        $this->assertEquals('Evento removido com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_destroy_with_right_code_still_keeps_event_with_enrollments(): void
    {
        $event = $this->createEvent();
        $this->enroll($event);
        $code = ConfirmationCode::generate("event.{$event->id}");

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $code]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertNull(ConfirmationCode::current("event.{$event->id}"));
        $this->assertEquals('não é possível remover um evento com inscrições (1)!', $_SESSION['flash']['danger']);
    }
```

Por quê:

- Testes antigos de `destroy` (`ECT:242-270`) quebram sem código:
  gere com `ConfirmationCode::generate(...)` antes do `delete`.
- `tearDown` limpa `confirmation_codes`: `$_SESSION` sobrevive entre
  testes.
- Novos cobrem: link na listagem, tela com código da sessão, id
  inexistente, sem código, errado (e invalida o certo), sem visitar a
  tela, código de outro Evento, certo (remove e limpa), certo mas com
  Inscrições (P03 continua).

## 8. `tests/Integration/Access/EventsAccessTest.php`

a) Docblock e array de `eventRoutes()`, depois de `admin.events.destroy`:

```php
     * As 9 rotas admin.events.* (index, paginate, new, create, show, edit, update, destroy, delete).
```

```php
            'admin.events.destroy' => ['DELETE', '/admin/events/1'],
            'admin.events.delete' => ['GET', '/admin/events/1/delete'],
```

b) Testes novos, antes de
`test_admin_should_be_redirected_to_index_when_event_does_not_exist`:

```php
    public function test_admin_should_access_the_delete_confirmation_page(): void
    {
        $response = $this->loggedClient($this->admin)->get("/admin/events/{$this->event->id}/delete");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Remover Evento', (string) $response->getBody());
        $this->assertMatchesRegularExpression('/id="confirmation_code_value">\d{4}</', (string) $response->getBody());
    }

    public function test_admin_should_remove_only_with_the_code_shown_on_the_page(): void
    {
        $client = $this->loggedClient($this->admin);
        $uri = "/admin/events/{$this->event->id}";

        $wrong = $client->post($uri, ['form_params' => ['_method' => 'DELETE', 'confirmation_code' => '0']]);
        $this->assertEquals("{$uri}/delete", $wrong->getHeaderLine('Location'));
        $this->assertNotNull(Event::findById($this->event->id));

        $page = (string) $client->get("{$uri}/delete")->getBody();
        $this->assertSame(1, preg_match('/id="confirmation_code_value">(\d{4})</', $page, $matches));

        $right = $client->post($uri, ['form_params' => ['_method' => 'DELETE', 'confirmation_code' => $matches[1]]]);
        $this->assertEquals('/admin/events', $right->getHeaderLine('Location'));
        $this->assertNull(Event::findById($this->event->id));
    }
```

Por quê:

- `eventRoutes()` alimenta "sem sessão -> `/login`" e "Participante ->
  `/dashboard`": incluir a rota já testa o middleware.
- Teste HTTP com sessão real: errado volta à tela; lê o código do HTML
  e remove. Prova o fluxo de ponta a ponta.

## 9. `tests/Acceptance/admin/events/DeleteEventCest.php`

a) Cenário novo, antes do helper `clickRemove`:

```php
    public function deleteWithWrongCodeKeepsTheEvent(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Remoção do registro com código de confirmação errado');
        $page->amOnPage('/admin/events');
        $page->click('Remover', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/delete");
        $code = (int) $page->grabTextFrom('#confirmation_code_value');
        $page->fillField('#confirmation_code', (string) ($code === 9999 ? 1000 : $code + 1));
        $page->click('Remover', 'form');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/delete");
        $page->see('Código de confirmação inválido! O evento não foi removido.');
        $page->see('Remover Evento', 'h1');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }
```

b) `clickRemove` (substitui o corpo e o comentário antigo):

```php
    /**
     * "Remover" da listagem leva à página de confirmação; o código gerado no servidor
     * aparece na tela e é digitado no campo antes de enviar o DELETE.
     */
    private function clickRemove(AcceptanceTester $page, string $title): void
    {
        $page->click('Remover', $this->rowOf($title));

        $page->see('Remover Evento', 'h1');
        $page->see($title);
        $code = $page->grabTextFrom('#confirmation_code_value');
        Assert::assertMatchesRegularExpression('/^\d{4}$/', $code);

        $page->fillField('#confirmation_code', $code);
        $page->click('Remover', 'form');
    }
```

Por quê:

- Os Cests de remoção que já existiam usam `clickRemove`: mudar só o
  helper adapta todos ao fluxo novo.
- `click('Remover', 'form')`: o contexto `form` garante o clique no
  botão de envio da confirmação.
- Cenário novo: código errado mantém o Evento e volta à tela.

## Fechamento

```sh
./run composer dump-autoload -o --strict-psr   # classe nova em lib/
./run test tests/Unit/Lib/ConfirmationCodeTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Schema não mudou: `./run db:populate` não é obrigatório.

No navegador: admin, listagem, "Remover" -> tela com código -> digitar
errado -> flash "Código de confirmação inválido!" e código novo ->
digitar certo -> listagem sem o Evento.

Commit (inglês): `feat: Require a confirmation code to remove an Event`.

## Armadilhas (resumo)

- Código na sessão **por Evento** (`event.{id}`): sem a chave,
  confirmar o Evento 2 removeria o 3.
- Apague o código depois de conferir, certo **ou** errado (uso único):
  evita força bruta trivial. Próxima visita gera outro.
- Código certo em `<input type="hidden">` ou na URL: form "confirma
  sozinho". O valor certo só existe na sessão.
- `destroy` antigo (form direto da listagem) deixa de funcionar: testes
  de `ECT:242-270` precisam gerar o código antes.
- Mostrar o código na tela é o pedido da 4.1 ("o backend devolve"): não
  é segurança contra o usuário, é prova de que ele leu. Diga isso.
- `return` depois do `redirectTo` no código errado.
- `==` em vez de `===`: comparação frouxa.
- `getParam('confirmation_code')` pode vir `null` ou array: `check`
  trata.
- `$_SESSION` sobrevive entre testes: limpe `confirmation_codes` no
  `tearDown`.
- `lib/` fora do `phpstan`: tipe tudo; teste unitário cobre.
- Com P12 na base, reaproveite a mesma classe para o login (chave
  `login.{user_id}`).
