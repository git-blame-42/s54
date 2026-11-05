# P23 passo a passo: código gravado no registro, exigido para remover ou editar

Pedido típico: "ao cadastrar, o backend gera um código de 4 dígitos
(1000 a 9999) que fica gravado no Evento; o admin vê o código no
detalhe e só remove o Evento se digitar esse código". Chance: alta.
Tempo: 30-40 min. Variante (não implementada no exemplo): exigir o
código para salvar uma edição.

Exemplo pronto: `fvp/4.2/exemplos/p23-codigo-do-registro-para-remover/`
(patch, bundle e árvore `projeto/`). Sem pré-requisito: parte da `main`.

Ordem = fluxo da requisição: banco -> model -> controller -> views ->
testes. Populate não muda (o construtor gera o código). Na prova,
prioridade: funcionar no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `database/schema.sql`
2. `app/Models/Event.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/show.phtml`
5. `app/views/admin/events/index.phtml`
6. `tests/Unit/Models/Events/EventTest.php`
7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
8. `tests/Integration/Access/EventsAccessTest.php`
9. `tests/Acceptance/admin/events/DeleteEventCest.php`

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE events`, logo após `capacity` (linha ~31):

```sql
    capacity    INT NOT NULL,
    -- Código de 4 dígitos gerado pelo model ao criar; o admin digita para remover o Evento.
    confirmation_code SMALLINT UNSIGNED NOT NULL,
```

Por quê:

- ActiveRecord não cria coluna. `save()` manda todas as chaves de
  `$columns`; coluna ausente -> `Unknown column`.
- `SMALLINT UNSIGNED` cabe 0-65535: sobra para 9999.
- Sem `UNIQUE`: o código é conferido junto com o `id` (difere de P1).
- `NOT NULL` sem `DEFAULT`: quem garante o valor é o model.

Armadilha: banco de dev não se recria sozinho. `./run db:populate`.

## 2. `app/Models/Event.php`

Quatro pontos.

a) Docblock da classe, depois de `@property int|string $capacity`:

```php
 * @property int|string|null $confirmation_code gerado no construtor quando vazio; nunca vem do formulário
```

b) Constantes, antes de `protected static string $table = 'events';`:

```php
    /** Faixa do código de confirmação (4 dígitos). */
    public const CODE_MIN = 1000;
    public const CODE_MAX = 9999;
```

c) `$columns`, depois de `'capacity',`:

```php
        'capacity',
        'confirmation_code',
    ];
```

d) Construtor e `confirmsCode`, logo após o `];` de `$columns` e antes
de `validates()`:

```php
    /**
     * Sorteia o código de confirmação só se ele estiver vazio. O construtor também roda a cada leitura do
     * banco (findById, where, Paginator); com o "só se vazio", o código lido nunca é trocado.
     *
     * @param array<string, mixed> $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        $code = $this->confirmation_code;
        if ($code === null || $code === '') {
            $this->confirmation_code = random_int(self::CODE_MIN, self::CODE_MAX);
        }
    }

    /**
     * Confere o código digitado sem conversão: só string de dígitos igual ao código ('04321', ' 4321',
     * null e array não valem). hash_equals compara strings, sem as conversões do ==.
     */
    public function confirmsCode(mixed $input): bool
    {
        if (!is_string($input) || !ctype_digit($input)) {
            return false;
        }

        return hash_equals((string) $this->confirmation_code, $input);
    }
```

Por quê:

- `@property`: atributos são dinâmicos (`__get`/`__set`); sem ele o
  `phpstan` reclama.
- `$columns`: lista que monta `INSERT`/`UPDATE`. Sem ela o código não
  vai ao banco e a coluna `NOT NULL` lança `PDOException`.
- Construtor (molde: `User`, `US:28-35`): `MODEL:32-42` zera as
  colunas e copia `$params`; depois disso o código é sorteado.
  Populate, `EventsAccessTest` e `BaseEventsCest` fazem `new Event` +
  `save()` sem passar pelo controller: o construtor cobre todos.
- "Só se vazio" é obrigatório: o construtor roda também em toda leitura
  (`MODEL:255`, `:278`, `:327` e `PAG:143` fazem `new static($row)`).
  Sortear sempre = código novo a cada carga; `show` mostra um,
  `destroy` confere outro.
- `confirmsCode(mixed)`: `getParam` devolve `mixed` (`null`, array).
  Tipo `string` no parâmetro -> `TypeError` (500).
- `ctype_digit` + `hash_equals`: sem `(int)` (`(int) '04321'` e
  `(int) ' 4321'` viram `4321`, `(int) '12a'` vira `12`). `hash_equals`
  só aceita string: `(string)` no valor do banco.
- Regra no model: controller só pergunta "confere?".

## 3. `app/Controllers/Admin/EventsController.php`

`destroy` (linhas ~96-111): depois do `if ($event === null) {...}` e
**antes** de `if ($event->safeDestroy())`:

```php
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        // Confere o código antes do safeDestroy: código errado não revela se o Evento tem Inscrições.
        if (!$event->confirmsCode($request->getParam('confirmation_code'))) {
            FlashMessage::danger('Código de confirmação incorreto!');
            $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
            return;
        }

        if ($event->safeDestroy()) {
```

Por quê:

- *Early return*: código errado ou ausente -> flash + PRG para o
  `show` (onde estão o código e o campo para tentar de novo) e para.
- Antes do `safeDestroy()`: com código errado não revela se há
  Inscrições; com código certo a mensagem de Inscrições continua.
- `confirmation_code` vem solto no POST, fora do array `event`.
- `FIELDS` **não** muda: o `fill` poria `NULL` quando o campo não vem
  e deixaria o form escolher o código (no `create` e no `update`).

## 4. `app/views/admin/events/show.phtml`

a) Dentro do `<dl>`, depois do `</dd>` de "Vagas" e antes de `</dl>`:

```php
        (<?= $event->availableSpots() ?> disponíveis)
    </dd>

    <dt class="col-sm-3">Código de confirmação</dt>
    <dd class="col-sm-9">
        <span id="confirmation_code_value"><?= htmlspecialchars((string) $event->confirmation_code) ?></span>
    </dd>
</dl>
```

b) No fim do arquivo, depois do link "Voltar":

```php
<a class="btn btn-secondary" href="<?= route('admin.events.index') ?>">Voltar</a>

<form class="mt-3" method="post" action="<?= route('admin.events.destroy', ['id' => $event->id]) ?>">
    <input type="hidden" name="_method" value="DELETE">
    <label for="confirmation_code" class="form-label">Digite o código para remover</label>
    <input type="text" class="form-control" id="confirmation_code" name="confirmation_code" inputmode="numeric"
        maxlength="4" autocomplete="off">
    <button type="submit" class="btn btn-danger mt-2">Confirmar remoção</button>
</form>
```

Por quê:

- View burra: só exibe o que o model já tem.
- `id="confirmation_code_value"`: âncora para teste e Cest lerem o
  código.
- `htmlspecialchars((string) ...)`: escape de toda saída, sem `(int)`
  cego (conversão silenciosa).
- `_method=DELETE`: HTML só faz GET/POST; o roteador lê o campo oculto
  e chama `destroy`.
- Campo sem `value`: o admin digita; nunca pré-preenchido.

## 5. `app/views/admin/events/index.phtml`

Na coluna de ações, troque o `<form>` de "Remover" por um link para o
`show`:

```php
                        <a class="btn btn-sm btn-outline-primary" href="<?= route('admin.events.edit', ['id' => $event->id]) ?>">Editar</a>
                        <a class="btn btn-sm btn-outline-danger" href="<?= route('admin.events.show', ['id' => $event->id]) ?>">Remover</a>
```

(sai o bloco `<form class="d-inline" ...>` com `_method=DELETE` e o
botão "Remover").

Por quê: o form da listagem não tem o campo do código; sempre cairia
em "Código de confirmação incorreto!". O link leva ao `show`, que tem
código e campo.

## 6. `tests/Unit/Models/Events/EventTest.php`

No fim da classe, depois do último teste (`safeDestroy`):

```php
    /* ------------------- código de confirmação ------------------- */

    public function test_new_event_should_get_a_confirmation_code_in_range(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = (new Event($this->params()))->confirmation_code;

            $this->assertIsInt($code);
            $this->assertGreaterThanOrEqual(Event::CODE_MIN, $code);
            $this->assertLessThanOrEqual(Event::CODE_MAX, $code);
        }
    }

    public function test_confirmation_code_given_to_the_constructor_should_be_kept(): void
    {
        $event = new Event($this->params(['confirmation_code' => 4321]));
        $this->assertTrue($event->save());

        $this->assertSame(4321, Event::findById($event->id)->confirmation_code);
    }

    public function test_confirmation_code_should_be_kept(): void
    {
        $event = $this->createEvent();
        $code = Event::findById($event->id)->confirmation_code;

        $this->assertIsInt($code);
        $this->assertGreaterThanOrEqual(1000, $code);
        $this->assertLessThanOrEqual(9999, $code);

        $event->title = 'Outro título';
        $this->assertTrue($event->save());
        $found = Event::findById($event->id);
        $this->assertSame($code, $found->confirmation_code);
        $this->assertSame($code, Event::where(['id' => $event->id])[0]->confirmation_code);
        $this->assertSame($code, Event::paginate(1, 5)->registers()[0]->confirmation_code);
    }

    public function test_confirms_code_should_accept_only_the_code(): void
    {
        $event = new Event($this->params(['confirmation_code' => 4321]));

        $this->assertTrue($event->confirmsCode('4321'));
        foreach (['1234', '12a', ' 4321', '4321 ', '04321', '', '-4321', '4321.0', '4e3'] as $bad) {
            $this->assertFalse($event->confirmsCode($bad), $bad);
        }
        $this->assertFalse($event->confirmsCode(null));
        $this->assertFalse($event->confirmsCode(['4321']));
        $this->assertFalse($event->confirmsCode(4321));
    }

    public function test_confirms_code_should_work_with_the_code_read_from_the_database(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $this->assertTrue(Event::findById($event->id)->confirmsCode('4321'));
    }
```

Por quê:

- Faixa: 200 sorteios pegam `random_int` com limites errados.
- "Kept": prova o "só se vazio" lendo por `findById`, `where` e
  `paginate` (os três fazem `new` com a linha do banco).
- `confirmsCode`: cada string ruim prova que não há conversão.
- Testes de `safeDestroy` não mudam: a conferência fica fora dele.

## 7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) Os dois testes antigos de `destroy` (`ECT:242-266`) viram o caso
"código certo". Renomeie e mande o código em `params`:

```php
    public function test_destroy_with_right_code_removes_event_without_enrollments(): void
    {
        $event = $this->createEvent();

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $event->confirmation_code]
        );
        // asserts de antes, sem mudança
```

Mesmo ajuste em `test_destroy_keeps_event_with_enrollments` ->
`test_destroy_with_right_code_keeps_event_with_enrollments` (só o
`delete(...)` muda; asserts iguais).

b) Antes deles, depois de
`test_update_capacity_below_enrollments_is_rejected`:

```php
    public function test_index_remove_button_links_to_the_show_page(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression(
            "#<a [^>]*href=\"/admin/events/{$event->id}\">Remover</a>#",
            $response
        );
        $this->assertStringNotContainsString('value="DELETE"', $response);
    }

    public function test_show_displays_the_confirmation_code_and_the_removal_form(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('id="confirmation_code_value">4321<', $response);
        $this->assertStringContainsString("action=\"/admin/events/{$event->id}\"", $response);
        $this->assertStringContainsString('name="_method" value="DELETE"', $response);
        $this->assertStringContainsString('name="confirmation_code"', $response);
        $this->assertDoesNotMatchRegularExpression('/name="confirmation_code"[^>]*value=/', $response);
    }

    public function test_create_ignores_confirmation_code_sent_by_the_form(): void
    {
        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['confirmation_code' => '42'])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertGreaterThanOrEqual(Event::CODE_MIN, $event->confirmation_code);
        $this->assertLessThanOrEqual(Event::CODE_MAX, $event->confirmation_code);
    }

    public function test_update_keeps_the_confirmation_code(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: [
                'id' => $event->id,
                'event' => $this->validParams(['title' => 'Título Novo', 'confirmation_code' => '1234']),
            ]
        );

        $updated = Event::findById($event->id);
        $this->assertEquals('Título Novo', $updated->title);
        $this->assertSame(4321, $updated->confirmation_code);
    }
```

c) Depois dos dois testes de "código certo", antes de `validParams()`:

```php
    public function test_destroy_without_code_keeps_event_and_goes_back_to_show(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Código de confirmação incorreto!', $_SESSION['flash']['danger']);
        $this->assertArrayNotHasKey('success', $_SESSION['flash']);
    }

    public function test_destroy_with_wrong_code_keeps_event_and_goes_back_to_show(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '1234']
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Código de confirmação incorreto!', $_SESSION['flash']['danger']);
    }

    public function test_destroy_with_code_as_array_keeps_event(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => ['4321']]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
    }

    public function test_destroy_with_wrong_code_does_not_reveal_enrollments(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);
        $this->enroll($event);

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '1234']
        );

        $this->assertEquals('Código de confirmação incorreto!', $_SESSION['flash']['danger']);
    }

    public function test_destroy_with_code_of_another_event_keeps_both(): void
    {
        $event = $this->createEvent(['title' => 'Alvo', 'confirmation_code' => 1111]);
        $this->createEvent(['title' => 'Outro', 'confirmation_code' => 2222]);

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '2222']
        );

        $this->assertCount(2, Event::all());
    }
```

Por quê:

- Integração chama a ação com `Request` montado e lê HTML/`Location`:
  prova form -> controller -> model -> redirect.
- Antigos falham sem o código (`Location` vira `/admin/events/{id}`).
- `'42'` no `create`: fora da faixa, nunca sorteado. Com `'1111'` o
  teste falharia 1 vez em 9000.
- `update` com `'1234'`: prova que `FIELDS` não deixa o form trocar.
- Array: prova o `mixed` + `is_string` (sem `TypeError`).
- Um caso por teste: `CTC:22` usa um `Request` só e `addParams` faz
  `array_merge`; um "sem código" depois de um "com código" ainda
  mandaria o código.

## 8. `tests/Integration/Access/EventsAccessTest.php`

Teste novo, antes de
`test_admin_should_be_redirected_to_index_when_event_does_not_exist`:

```php
    public function test_admin_should_remove_only_with_the_confirmation_code(): void
    {
        $client = $this->loggedClient($this->admin);
        $uri = "/admin/events/{$this->event->id}";

        $response = $client->post($uri, ['form_params' => ['_method' => 'DELETE', 'confirmation_code' => '0000']]);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals($uri, $response->getHeaderLine('Location'));
        $this->assertNotNull(Event::findById($this->event->id));

        $show = (string) $client->get($uri)->getBody();
        $this->assertStringContainsString('Código de confirmação incorreto!', $show);
        $this->assertSame(1, preg_match('/id="confirmation_code_value">(\d{4})</', $show, $matches));

        $response = $client->post($uri, ['form_params' => ['_method' => 'DELETE', 'confirmation_code' => $matches[1]]]);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/events', $response->getHeaderLine('Location'));
        $this->assertNull(Event::findById($this->event->id));
    }
```

Por quê:

- Sobe o servidor e faz HTTP de verdade (rota + middleware + sessão).
- `'0000'` nunca é sorteado: sempre errado. Depois lê o código do HTML
  do `show` e acerta.
- Teste do Participante (`ACC:155`) não muda: é barrado antes.

## 9. `tests/Acceptance/admin/events/DeleteEventCest.php`

a) `clickRemove` (linhas ~50-54): troque o corpo e o comentário:

```php
    /**
     * "Remover" na listagem leva ao show do Evento; lá o código aparece em #confirmation_code_value e é
     * digitado em #confirmation_code antes de "Confirmar remoção" (form com _method=DELETE).
     */
    private function clickRemove(AcceptanceTester $page, string $title): void
    {
        $page->click('Remover', $this->rowOf($title));
        $page->see($title, 'h1');

        $code = $page->grabTextFrom('#confirmation_code_value');
        $page->fillField('#confirmation_code', $code);
        $page->click('Confirmar remoção');
    }
```

b) Cenário novo, antes de `clickRemove`:

```php
    public function deleteWithWrongCodeGoesBackToShow(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $wrong = $event->confirmation_code === 1000 ? '1001' : '1000';

        $page->wantTo('Remoção com código de confirmação errado');
        $page->amOnPage('/admin/events');
        $page->click('Remover', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->fillField('#confirmation_code', $wrong);
        $page->click('Confirmar remoção');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Código de confirmação incorreto!');
        $page->dontSee('Evento removido com sucesso!');
        $page->see((string) $event->confirmation_code, '#confirmation_code_value');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }
```

Por quê:

- Os dois Cests antigos (`:11-28`, `:30-48`) usam `clickRemove`; mudar
  só o helper conserta os dois.
- `grabTextFrom` lê o código da tela, como o usuário faria.
- `$wrong` escolhe um valor garantidamente diferente do código.

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

No navegador: criar Evento; no `show`, ver o código; "Remover" na
listagem leva ao `show`; digitar código errado -> flash "Código de
confirmação incorreto!", mesma tela, Evento existe; digitar o certo ->
removido.

Commit (inglês): `feat: Require the Event confirmation code to remove it`.

Variante (editar), se pedirem: mesmo `input` dentro do
`if ($isUpdate)` de `app/views/admin/events/_form.phtml` (`FORM:17-19`);
em `update`, depois do `fill`, código errado -> `FlashMessage::danger`,
`render('admin/events/edit', ...)` e `return` (padrão de `EC:90-92`).
Testes de update e Cests de update passam a mandar o código. Código
errado + dados inválidos mostra só o erro do código: proposital.

## Armadilhas (resumo)

- Esquecer `$columns`: código não vai ao banco, `PDOException`
  (`NOT NULL`); com coluna `NULL`, `confirmsCode` sempre falha.
- Gerar no `create` do controller: populate, `EventsAccessTest` e
  `BaseEventsCest` gravam `NULL`. Gerar em `save()` depois do
  `isValid()` e validar faixa em `validates()`: populate lança
  `RuntimeException`. Construtor
  cobre todos.
- Gerar sempre (sem "só se vazio"): troca a cada leitura; nada é
  removido. O teste "não muda ao salvar" pega.
- Nunca em `FIELDS`: `fill` zeraria e o admin escolheria o código.
- `confirmsCode(string $input)`: `null`/array -> `TypeError` (500).
  Use `mixed` + `is_string`. Sem `(int)`.
- `hash_equals` só aceita strings: `(string)` no valor do banco.
- Testes antigos de `destroy` e `DeleteEventCest` quebram sem o código.
- Um caso por teste (`Request` único + `array_merge`).
- Conferir antes do `safeDestroy()`.
- Form "Remover" da listagem sem o campo sempre falha: troque por link.
- Não precisa ser único. Com P1 na base, não reuse `checkin_code`
  (aparece na listagem).
