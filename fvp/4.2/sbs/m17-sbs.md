# M17 passo a passo: Campo sim/não por checkbox (certificado)

Pedido típico: "marque se o Evento emite certificado. Checkbox no form;
listagem e detalhe mostram Sim ou Não. Cadastrar ou editar com a caixa
desmarcada não pode dar erro." Chance: média-alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m17-checkbox-certificado/` (patch,
bundle e árvore `projeto/`). Pré-requisitos: nenhum (base `main`).

Forma do exemplo: coluna `has_certificate`; hidden `value="0"` antes do
checkbox; no fim de `validates()`, `normalizeHasCertificate()` (nulo/vazio
-> `'0'`, int do banco -> string) e depois `inclusion(['0', '1'])`. Sem
padrão no construtor. Desmarcado nunca dá erro, nem em POST sem o campo.

Ordem = fluxo da requisição: banco -> model -> controller -> views ->
dados -> testes. Na prova, prioridade: funcionar no navegador, depois
testar, depois `phpcs`.

## Arquivos, em ordem

1. `database/schema.sql`
2. `app/Models/Event.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/_form.phtml`
5. `app/views/admin/events/index.phtml`
6. `app/views/admin/events/show.phtml`
7. `database/Populate/populate.php`
8. `tests/Unit/Models/Events/EventTest.php`
9. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
10. `tests/Acceptance/admin/events/BaseEventsCest.php`
11. `tests/Acceptance/admin/events/CertificateCheckboxCest.php` (novo)

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE events`, logo após `capacity` (antes de `created_at`):

```sql
    capacity    INT NOT NULL,
    -- Sim/não do checkbox "Emite certificado". O DEFAULT não socorre o Model::save(), que grava NULL
    -- quando o atributo é nulo; quem garante 0/1 é a normalização em Event::validates().
    has_certificate TINYINT(1) NOT NULL DEFAULT 0,
```

Por quê:

- MySQL não tem boolean de verdade; `TINYINT(1)` guarda 0/1.
- `save()` insere **todas** as chaves de `$columns`
  (`core/Database/ActiveRecord/Model.php:139-184`). Atributo `null` ->
  `NULL` explícito -> `1048 Column cannot be null` -> 500. O
  `DEFAULT 0` nunca é usado.

Armadilha: testes recriam o schema; o banco de dev não. No fim,
`./run db:populate`, senão `Unknown column` só no navegador.

## 2. `app/Models/Event.php`

Quatro pontos.

a) Docblock da classe, depois de `@property int|string $capacity`:

```php
 * @property int|string|null $has_certificate '0'/'1' vindo do formulário; o banco (TINYINT) devolve int 0/1
```

b) `$columns`, depois de `'capacity',`:

```php
        'has_certificate',
```

c) `validates()`, no fim, depois do bloco `if` de `capacity`:

```php
        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }

        $this->normalizeHasCertificate();
        Validations::inclusion('has_certificate', $this, ['0', '1']);
    }
```

d) Dois métodos novos, logo depois de `validates()`:

```php
    /**
     * Deixa `has_certificate` como '0' ou '1' (string), o formato que vem do formulário.
     *
     * - Checkbox desmarcado não vai no POST: o controller recebe null; sem isso o INSERT manda NULL
     *   para uma coluna NOT NULL e a página quebra com erro 500. Nulo e vazio viram '0'.
     * - Evento lido do banco: o TINYINT volta como int (0 ou 1), e o inclusion() compara com ===;
     *   sem converter, editar e salvar um Evento já gravado seria recusado.
     *
     * Outros valores ('2', 'on', 'sim') ficam como estão para o inclusion() recusar.
     */
    private function normalizeHasCertificate(): void
    {
        $value = $this->has_certificate;

        if ($value === null || $value === '') {
            $this->has_certificate = '0';
        } elseif (is_int($value)) {
            $this->has_certificate = (string) $value;
        }
    }

    /**
     * O Evento emite certificado? Compara como string: o banco devolve int 1, o formulário envia '1'.
     */
    public function hasCertificate(): bool
    {
        return (string) $this->has_certificate === '1';
    }
```

Por quê:

- `@property`: atributo dinâmico; sem ele o `phpstan` reclama.
  `int|string|null` cobre form (string), banco (int) e ausente (null).
- `$columns`: lista que monta `INSERT`/`UPDATE`. Esquecer -> coluna
  nunca vai ao banco.
- `validates()` roda em `isValid()` e `save()`: normalizar ali vale para
  create, edit, populate, testes, qualquer `save()`.
- Normaliza **antes** do `inclusion()`: `inclusion` usa `in_array`
  estrito (`lib/Validations.php:38`). `TINYINT` volta do PDO como
  **int** `1`; sem converter, Evento lido do banco e salvo de novo
  reprova contra `['0', '1']`.
- Só nulo/vazio viram `'0'`. Lixo (`'2'`, `'on'`, `'sim'`) segue para o
  `inclusion()` recusar com "não é uma opção válida!".
- `hasCertificate()`: comparação estrita num lugar só, com cast. View e
  testes usam o método, nunca `== 1` solto.

## 3. `app/Controllers/Admin/EventsController.php`

Constante `FIELDS` (linha ~14), quebrada em linhas (em uma linha ficaria
com 128 col, rente ao limite de 130 do `phpcs.xml`):

```php
    private const FIELDS = [
        'title', 'description', 'kind', 'modality', 'starts_on', 'location', 'capacity', 'has_certificate',
    ];
```

Por quê:

- Allowlist: o método privado copia `$params[$field] ?? null` de cada
  chave de `FIELDS` pro model, como string. Fora da lista não chega.
- Checkbox desmarcado sem hidden -> chave ausente -> `?? null` -> nulo
  no model. Por isso o hidden (form) e a normalização (model).

Armadilha: não converta no controller (`(bool)`, `(int)`, `?? '0'`):
`true` não está na lista e `'abc'` viraria `true`. Regra fica no model.

## 4. `app/views/admin/events/_form.phtml`

Depois do `</div>` do bloco de `capacity`, antes do
`<button type="submit">`:

```php
    <div class="mb-3 form-check">
        <?php /* Checkbox desmarcado não é enviado: o hidden vem ANTES e manda '0'; marcado, o '1' sobrescreve. */ ?>
        <input type="hidden" name="event[has_certificate]" value="0">
        <input type="checkbox" class="form-check-input<?= $invalid('has_certificate') ?>" id="event_has_certificate"
            name="event[has_certificate]" value="1" <?= $event->hasCertificate() ? 'checked' : '' ?>>
        <label class="form-check-label" for="event_has_certificate">Emite certificado</label>
        <?= $feedback('has_certificate') ?>
    </div>
```

Por quê:

- Checkbox desmarcado não vai no POST. Hidden com o mesmo `name` garante
  que a chave sempre chega.
- PHP fica com o **último** valor de nome repetido: hidden antes ->
  marcado manda `1` por cima do `0`.
- `checked` via `hasCertificate()`: no `edit` o valor vem do banco como
  int `1`; `=== '1'` abriria desmarcado.
- `form-check`/`form-check-input`: classes Bootstrap de checkbox
  (`is-invalid` + `$feedback` funcionam igual aos outros campos).

Armadilha: hidden **depois** do checkbox -> sempre `0`, a caixa nunca
fica marcada ao salvar.

## 5. `app/views/admin/events/index.phtml`

a) `<thead>`, depois de `<th>Vagas</th>`:

```php
                <th>Certificado</th>
```

b) `<tbody>`, depois do `<td>` de vagas e **antes** do `<td>` de ações:

```php
                    <td><?= $event->enrollmentsCount() ?>/<?= (int) $event->capacity ?></td>
                    <td><?= $event->hasCertificate() ? 'Sim' : 'Não' ?></td>
```

Por quê: view só exibe; a decisão sim/não é do model. Ordem dos `<td>`
bate com a dos `<th>`. Texto fixo, sem dado do usuário: dispensa
`htmlspecialchars`.

## 6. `app/views/admin/events/show.phtml`

Dentro do `<dl>`, depois do `</dd>` de vagas (o que fecha com
"disponíveis)"), antes do `</dl>`:

```php

    <dt class="col-sm-3">Certificado</dt>
    <dd class="col-sm-9 certificate"><?= $event->hasCertificate() ? 'Sim' : 'Não' ?></dd>
```

Por quê: mesmo padrão `dt`/`dd`. Classe `certificate` dá um seletor fixo
pros testes (`'.certificate'` no Cest, `<dd class="col-sm-9
certificate">Sim</dd>` no de integração).

## 7. `database/Populate/populate.php`

No `new Event([...])` do `foreach` (linhas ~134-143), depois de
`'capacity' => $capacity,`:

```php
        // Palestras não emitem certificado; oficinas, minicursos e semanas acadêmicas emitem.
        'has_certificate' => $kind === 'lecture' ? '0' : '1',
```

Por quê:

- Dá variedade (Sim/Não) na listagem sem mexer nas linhas posicionais do
  array de dados (o `foreach` destrutura por posição).
- Mesmo sem a linha o populate funcionaria (normalização vira `'0'`); a
  linha é pra tela mostrar os dois casos.

## 8. `tests/Unit/Models/Events/EventTest.php`

Bloco novo depois de `test_audience_should_survive_a_round_trip_in_the_database`
(linha ~297), antes de `/* --- save / update / destroy / busca --- */`:

```php
    /* ------------------- has_certificate (checkbox sim/não) ------------------- */

    public function test_has_certificate_should_accept_zero_and_one(): void
    {
        foreach (['0', '1'] as $value) {
            $this->assertTrue((new Event($this->params(['has_certificate' => $value])))->isValid(), $value);
        }
    }

    public function test_unchecked_has_certificate_should_be_saved_as_zero(): void
    {
        // Checkbox desmarcado sem o hidden: o atributo chega nulo (ou vazio) e não pode virar NULL no INSERT.
        foreach ([null, ''] as $value) {
            $event = new Event($this->params(['has_certificate' => $value]));

            $this->assertTrue($event->save());
            $this->assertSame('0', $event->has_certificate);
            $this->assertSame(0, Event::findById($event->id)->has_certificate);
        }
    }

    public function test_has_certificate_outside_zero_and_one_should_be_invalid(): void
    {
        foreach (['2', 2, 'on', 'sim', '-1', ' 1'] as $value) {
            $event = new Event($this->params(['has_certificate' => $value]));

            $this->assertFalse($event->isValid(), (string) $value);
            $this->assertEquals('não é uma opção válida!', $event->errors('has_certificate'), (string) $value);
        }
    }

    public function test_has_certificate_from_the_database_is_int_and_event_stays_valid(): void
    {
        $event = $this->createEvent(['has_certificate' => '1']);

        $found = Event::findById($event->id);

        // O TINYINT volta como int; o inclusion() usa ===, por isso o model normaliza antes de validar.
        $this->assertSame(1, $found->has_certificate);
        $this->assertTrue($found->isValid());
        $this->assertSame('1', $found->has_certificate);
        $found->title = 'Título editado';
        $this->assertTrue($found->save());
        $this->assertSame(1, Event::findById($event->id)->has_certificate);
    }

    public function test_has_certificate_can_be_unchecked_on_edit(): void
    {
        $event = $this->createEvent(['has_certificate' => '1']);

        $found = Event::findById($event->id);
        $found->has_certificate = '0';
        $this->assertTrue($found->save());

        $this->assertSame(0, Event::findById($event->id)->has_certificate);
    }

    public function test_has_certificate_helper_should_compare_as_string(): void
    {
        foreach (['1', 1] as $value) {
            $this->assertTrue((new Event(['has_certificate' => $value]))->hasCertificate(), var_export($value, true));
        }

        foreach (['0', 0, null, '', '2', 2] as $value) {
            $this->assertFalse((new Event(['has_certificate' => $value]))->hasCertificate(), var_export($value, true));
        }
    }
```

Por quê:

- `params()` não muda: sem `has_certificate`, a normalização vira `'0'`.
  Todos os testes antigos seguem válidos.
- Cobre os quatro riscos: nulo (500), lixo (`inclusion`), int do banco
  (`===`), desmarcar na edição.
- `assertSame(1, ...)`: confere o tipo (int do PDO), não só o valor.
- Prova do exemplo: comentar `normalizeHasCertificate()` derruba 2 dos 6
  (nulo e lido do banco).

## 9. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) `validParams()` (linhas ~272-284), depois de `'capacity' => '20',`:

```php
            // O hidden do formulário sempre envia '0'; com o checkbox marcado, chega '1'.
            'has_certificate' => '0',
```

b) Bloco novo depois de `test_destroy_keeps_event_with_enrollments`
(linha ~265), antes do docblock de `validParams()`:

```php
    /* ------------------- has_certificate (checkbox sim/não) ------------------- */

    public function test_form_sends_zero_in_a_hidden_before_the_certificate_checkbox(): void
    {
        $response = $this->get(action: 'new', controllerName: self::CONTROLLER);

        // O hidden precisa vir antes: com nomes repetidos, o PHP fica com o último valor enviado.
        $this->assertMatchesRegularExpression(
            '/name="event\[has_certificate\]" value="0">\s*<input type="checkbox"[^>]*'
                . 'id="event_has_certificate"\s+name="event\[has_certificate\]" value="1"/',
            $response
        );
        $this->assertDoesNotMatchRegularExpression('/id="event_has_certificate"[^>]*checked/', $response);
        $this->assertStringContainsString('Emite certificado', $response);
    }

    public function test_create_with_certificate_checked_saves_one_and_show_displays_yes(): void
    {
        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['has_certificate' => '1'])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertSame(1, $event->has_certificate);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $this->assertStringContainsString('<dd class="col-sm-9 certificate">Sim</dd>', $response);
    }

    public function test_create_with_certificate_unchecked_saves_zero(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['has_certificate' => '0'])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertSame(0, $event->has_certificate);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
    }

    public function test_create_without_the_certificate_field_does_not_break(): void
    {
        // POST de um formulário sem o hidden: o checkbox desmarcado simplesmente não vem.
        $params = $this->validParams();
        unset($params['has_certificate']);

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['event' => $params]);

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertSame(0, $event->has_certificate);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
    }

    public function test_create_with_invalid_certificate_value_shows_error_below_the_field(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['has_certificate' => '2'])]
        );

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression(
            '/class="form-check-input is-invalid" id="event_has_certificate".*?'
                . '<div class="invalid-feedback">não é uma opção válida!<\/div>/s',
            $response
        );
    }

    public function test_edit_checks_the_certificate_of_a_saved_event(): void
    {
        $event = $this->createEvent(['has_certificate' => '1']);

        $response = $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertMatchesRegularExpression('/id="event_has_certificate"[^>]*checked/', $response);
    }

    public function test_update_with_certificate_unchecked_clears_it(): void
    {
        $event = $this->createEvent(['has_certificate' => '1']);

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['has_certificate' => '0'])]
        );

        $this->assertSame(0, Event::findById($event->id)->has_certificate);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
    }

    public function test_index_shows_whether_each_event_has_certificate(): void
    {
        $this->createEvent(['title' => 'Com certificado', 'has_certificate' => '1']);
        $this->createEvent(['title' => 'Sem certificado', 'has_certificate' => '0']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('<th>Certificado</th>', $response);
        $this->assertMatchesRegularExpression('/Com certificado<\/td>.*?<td>Sim<\/td>/s', $response);
        $this->assertMatchesRegularExpression('/Sem certificado<\/td>.*?<td>Não<\/td>/s', $response);
    }
```

Por quê:

- `validParams()` imita o form real: o hidden sempre manda `'0'`.
- Integração prova form -> `FIELDS` -> model -> banco -> view:
  ordem hidden/checkbox, marcado grava `1` e `show` diz Sim, POST sem o
  campo não quebra (PRG: `Location:`), `'2'` mostra erro embaixo do
  campo, `edit` abre marcado, `update` desmarca, `index` mostra Sim/Não.

## 10. `tests/Acceptance/admin/events/BaseEventsCest.php`

a) `seedEvents` (linhas ~84-94), depois de `'capacity' => ...,`:

```php
                // Eventos pares emitem certificado (Evento 02 sim, Evento 01 não).
                'has_certificate' => $number % 2 === 0 ? '1' : '0',
```

b) `fillEventForm`: docblock, acrescente à lista de chaves:

```php
     * online, hybrid), audience (lista de valores), starts_on (Y-m-d), location, capacity, has_certificate
     * (true marca o checkbox, false desmarca).
```

c) `fillEventForm`, antes do `if (array_key_exists('starts_on', $data))`:

```php
        if (array_key_exists('has_certificate', $data)) {
            if ($data['has_certificate']) {
                $page->checkOption('#event_has_certificate');
            } else {
                $page->uncheckOption('#event_has_certificate');
            }
        }
```

Por quê:

- `seedEvents` cria os 16 Eventos do navegador; pares/ímpares dão os dois
  casos na listagem.
- `fillEventForm` só mexe no que está em `$data`. `checkOption`/
  `uncheckOption` clicam no checkbox de verdade (o hidden segue junto).

Armadilha: `seedEvents` não confere o `save()`; valor inválido aqui faz
os Cests falharem sem mensagem clara.

## 11. `tests/Acceptance/admin/events/CertificateCheckboxCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Checkbox "Emite certificado": marcado grava 1, desmarcado grava 0 (o checkbox desmarcado não vai no
 * POST; o hidden com value=0 antes dele garante o envio).
 */
class CertificateCheckboxCest extends BaseEventsCest
{
    public function createEventWithCertificate(AcceptanceTester $page): void
    {
        $data = $this->validEventData(['has_certificate' => true]);

        $page->wantTo('Cadastrar Evento marcando "Emite certificado"');
        $page->amOnPage('/admin/events/new');
        $page->dontSeeCheckboxIsChecked('#event_has_certificate');
        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $page->see('Sim', '.certificate');

        $stored = Event::findBy(['title' => $data['title']]);
        Assert::assertInstanceOf(Event::class, $stored);
        Assert::assertSame(1, $stored->has_certificate);
    }

    public function createEventWithoutCertificate(AcceptanceTester $page): void
    {
        $data = $this->validEventData();

        $page->wantTo('Cadastrar Evento com "Emite certificado" desmarcado');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $page->see('Não', '.certificate');

        $stored = Event::findBy(['title' => $data['title']]);
        Assert::assertInstanceOf(Event::class, $stored);
        Assert::assertSame(0, $stored->has_certificate);
    }

    public function uncheckCertificateOnEdit(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Desmarcar "Emite certificado" ao editar um Evento');
        $page->amOnPage('/admin/events');
        $page->see('Sim', $this->rowOf('Evento 02'));
        $page->see('Não', $this->rowOf('Evento 01'));

        $page->click('Editar', $this->rowOf('Evento 02'));
        $page->seeCheckboxIsChecked('#event_has_certificate');
        $this->fillEventForm($page, ['has_certificate' => false]);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento atualizado com sucesso!');
        $page->see('Não', '.certificate');

        $stored = Event::findById((int) $event->id);
        Assert::assertInstanceOf(Event::class, $stored);
        Assert::assertSame(0, $stored->has_certificate);
    }
}
```

Por quê:

- Herda `BaseEventsCest`: login admin, `seedEvents`, `fillEventForm`,
  `rowOf`, `$this->events`.
- O terceiro caso é o que morde no navegador: editar, desmarcar, salvar,
  ver "Não". Hidden e normalização cobrem isso; sem os dois, dá 500.

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

No navegador (`admin@example.com` / `123456`): `/admin/events` tem a
coluna "Certificado" (palestras Não, demais Sim). Criar marcado -> Sim no
`show`. Editar, desmarcar, salvar -> Não.

Commit (inglês): `feat: Add certificate checkbox to events`.

## Armadilhas (resumo)

- Sem hidden e sem normalização, desmarcar dá 500: chave some do POST,
  `?? null` põe `null` e o `INSERT`/`UPDATE` manda `NULL` numa coluna
  `NOT NULL`.
- Hidden depois do checkbox: PHP fica com o último valor, sempre `0`.
- `DEFAULT 0` do schema não é usado: `save()` grava todas as colunas.
  Sem normalizar, testes (`params()`) e POSTs que não mandam o campo
  quebram.
- `TINYINT` volta do PDO como **int**; `inclusion(['0', '1'])` é estrito:
  sem normalizar, Evento lido e salvo de novo reprova.
- `checked` com `=== '1'` (ou `== 1` solto) na view: use o método do
  model (`hasCertificate()`).
- Não converta no controller (`(bool)`, `?? '0'`); regra no model.
- Normalizar tudo que não é `'1'` para `'0'` esconde lixo (`'on'`,
  `'sim'`): deixe o `inclusion()` recusar.
- Esquecer `db:populate`: `Unknown column` só no navegador.
