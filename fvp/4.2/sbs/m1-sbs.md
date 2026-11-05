# M1 passo a passo: campo novo em `events` com validação

Pedido típico: "acrescente a carga horária (`workload_hours`, inteiro >= 1,
obrigatório) ao Evento". Chance: alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m1-campo-novo-workload-hours/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Ordem = fluxo da requisição: banco -> model -> controller -> views -> dados
-> testes. Na prova, prioridade: funcionar no navegador, depois testar,
depois `phpcs`.

## Arquivos, em ordem

1. `database/schema.sql`
2. `app/Models/Event.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/_form.phtml`
5. `app/views/admin/events/index.phtml`
6. `app/views/admin/events/show.phtml`
7. `database/Populate/populate.php`
8. `tests/Unit/Models/Events/EventTest.php`
9. `tests/Unit/Models/Events/EnrollmentTest.php`
10. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
11. `tests/Integration/Access/EventsAccessTest.php`
12. `tests/Acceptance/admin/events/BaseEventsCest.php`
13. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`
14. `tests/Acceptance/admin/events/CreateWithInvalidDataCest.php`

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE events`, logo após `capacity`:

```sql
    capacity    INT NOT NULL,
    workload_hours INT NOT NULL,
```

Por quê:

- ActiveRecord do framework não cria coluna sozinho. `save()` insere
  **todas** as chaves de `$columns`; coluna ausente -> `Unknown column`.
- `NOT NULL` sem `DEFAULT`: a validação fica no model, não no banco.
  `DEFAULT` não ajuda: o framework manda `NULL` quando falta valor.

Armadilha: os testes recriam o schema sozinhos; o banco de
desenvolvimento não. No fim, `./run db:populate`, senão o erro aparece
só no navegador.

## 2. `app/Models/Event.php`

Três pontos.

a) Docblock da classe, depois de `@property int|string $capacity`:

```php
 * @property int|string $workload_hours carga horária, em horas
```

Por quê: atributos são dinâmicos (`__get`/`__set`). Sem o `@property`
o `phpstan` reclama de propriedade desconhecida. `int|string` porque o
form manda string.

b) `$columns`, depois de `'capacity',`:

```php
        'workload_hours',
```

Por quê: é a lista que o `Model` usa pra montar `INSERT`/`UPDATE` e pra
aceitar atribuição. Esquecer -> `Property workload_hours not found` e a
coluna nunca vai ao banco.

c) `validates()`, no fim do método, depois do bloco `if` de `capacity`:

```php
        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }

        Validations::notEmpty('workload_hours', $this) &&
            Validations::integerMin('workload_hours', $this, 1);
    }
```

Por quê:

- Validação mora no model, nunca no controller.
- `&&` = curto-circuito: só testa `>= 1` se não estiver vazio; senão
  duplica erro no mesmo campo.
- Cada `Validations::x` grava erro em `$this->errors` quando falha;
  `save()` só persiste sem erros.
- Mesmo padrão das linhas de `kind`/`modality`. Não precisa de `if`
  porque não há regra extra depois (`capacity` só tem `if` por causa do
  `validatesCapacityAgainstEnrollments()`).

## 3. `app/Controllers/Admin/EventsController.php`

Constante `FIELDS` (linha ~14):

```php
    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_on', 'location', 'capacity', 'workload_hours'];
```

Por quê:

- `create`/`update` não leem `$_POST`. Um método privado faz
  `foreach (self::FIELDS as $field)` e copia `$params[$field]` pro model,
  como string.
- É uma allowlist: só o que está em `FIELDS` sai do form e chega ao
  model. Protege contra `user_id=1` injetado no POST (mass assignment).

Armadilhas:

- Esquecer aqui: form envia, controller ignora, model recebe `null`,
  `notEmpty` reclama de vazio "sem motivo".
- Não faça `(int) $params['workload_hours']` no controller. Conversão
  silenciosa: `"abc"` viraria `0`. Deixe a string ir e o `integerMin`
  rejeitar.

## 4. `app/views/admin/events/_form.phtml`

Depois do `</div>` do bloco de `capacity`, antes do
`<button type="submit">`:

```php
    <div class="mb-3">
        <label for="event_workload_hours" class="form-label">Carga horária (horas)</label>
        <input type="number" class="form-control<?= $invalid('workload_hours') ?>" id="event_workload_hours"
            name="event[workload_hours]" min="1" value="<?= htmlspecialchars((string) $event->workload_hours) ?>">
        <?= $feedback('workload_hours') ?>
    </div>
```

Cópia do bloco de `capacity` trocando `id`, `name`, `label` e `value`.

Por quê cada parte:

- `name="event[workload_hours]"`: chega como
  `$request->getParam('event')['workload_hours']`. O `[...]` agrupa; o
  controller lê o array `event` e passa por `FIELDS`.
- `$invalid(...)`: adiciona `is-invalid` (Bootstrap, borda vermelha) se
  `$event->errors('workload_hours')` existir.
- `$feedback(...)`: imprime a mensagem de erro embaixo do campo. Os dois
  helpers estão no topo do arquivo (`FORM:11-14`).
- `value="...$event->workload_hours"`: o form serve a `new` e `edit`.
  Em `new` vem vazio; em `edit` vem o salvo; em erro de validação vem o
  que o usuário digitou.
- `htmlspecialchars`: rubrica cobra escape (XSS).
- O form tem `novalidate`: `min="1"` é só UX; o erro real vem do
  servidor.

## 5. `app/views/admin/events/index.phtml`

a) `<thead>`, após `<th>Vagas</th>`:

```php
                <th>Carga horária</th>
```

b) `<tbody>`, após o `<td>` de vagas e **antes** do `<td>` de ações:

```php
                    <td><?= $event->enrollmentsCount() ?>/<?= (int) $event->capacity ?></td>
                    <td><?= (int) $event->workload_hours ?> h</td>
                    <td>
                        <a class="btn btn-sm btn-outline-secondary" ...>Ver</a>
```

Por quê:

- A view só exibe o que o controller passou (`$events` do `index`).
  Nenhuma lógica, nenhum SQL.
- Ordem dos `<td>` tem que bater com a dos `<th>`.
- `(int)` é o escape: inteiro não carrega HTML, dispensa
  `htmlspecialchars` (texto precisa, número não).
- Nada muda no controller: o `paginate()` já traz todas as colunas de
  `$columns`.

Obs: aviso `Undefined variable '$paginator'` é só a IDE (a view recebe
variáveis via `extract` no `render`). Ignore.

## 6. `app/views/admin/events/show.phtml`

Dentro do `<dl>`, depois do `</dd>` de vagas:

```php
    <dt class="col-sm-3">Carga horária</dt>
    <dd class="col-sm-9"><?= (int) $event->workload_hours ?> h</dd>
```

Por quê: mesmo motivo do `index`. `show` recebe um `$event` (via
`Event::findById`) e só exibe. Par `dt`/`dd` segue o padrão dos outros
campos.

## 7. `database/Populate/populate.php`

No array do `new Event([...])` dentro do `foreach` (linhas ~134-143),
depois de `'capacity' => $capacity,`:

```php
        'workload_hours' => 4,
```

Por quê:

- O populate cria 16 Eventos. Sem o campo, `save()` falha na validação
  (`notEmpty`) e o banco de dev fica vazio.
- Valor fixo (`4`) serve para os 16. Não mexa nas linhas posicionais do
  array de dados (`POP:49-130`): o `foreach` destrutura por posição.

Depois de salvar: `./run db:populate` (recria schema + dados).

## 8. `tests/Unit/Models/Events/EventTest.php`

a) `params()` (linhas ~41-54), depois de `'capacity' => 10,`:

```php
            'workload_hours' => 4,
```

b) Listas de campos em `test_empty_event_should_have_error_in_every_field` e
`errorsOf()`: acrescente `'workload_hours'` no fim (quebre em
`$fields = [...]` se a linha passar de 130 colunas, limite do `phpcs.xml`).

c) Data provider de campos (`'capacity' => ['capacity'],`):

```php
            'workload_hours' => ['workload_hours'],
```

d) Testes novos, depois de `test_capacity_should_be_at_least_one`:

```php
    public function test_workload_hours_should_not_be_empty(): void
    {
        $event = new Event($this->params(['workload_hours' => '']));

        $this->assertFalse($event->isValid());
        $this->assertFalse($event->save());
        $this->assertEquals('não pode ser vazio!', $event->errors('workload_hours'));
        $this->assertCount(0, Event::all());
    }

    public function test_workload_hours_should_be_at_least_one(): void
    {
        $event = new Event($this->params(['workload_hours' => 0]));

        $this->assertFalse($event->isValid());
        $this->assertEquals('deve ser maior ou igual a 1!', $event->errors('workload_hours'));

        $event->workload_hours = -2;
        $this->assertFalse($event->isValid());

        $event->workload_hours = 'quatro';
        $this->assertFalse($event->isValid());
        $this->assertEquals('deve ser um número inteiro!', $event->errors('workload_hours'));

        $event->workload_hours = '1';
        $this->assertTrue($event->isValid());
    }

    public function test_workload_hours_should_be_saved(): void
    {
        $event = $this->createEvent(['workload_hours' => 12]);

        $this->assertEquals(12, Event::findById($event->id)?->workload_hours);
    }
```

Por quê:

- `params()` é a fábrica de Evento válido de todos os testes. Sem o
  campo novo, todo teste que salva Evento quebra por "vazio".
- Os três testes novos são cópia de `test_capacity_should_be_at_least_one`:
  vazio, abaixo do mínimo, não-inteiro, válido, e persistência.
- Mensagens vêm de `lib/Validations.php`; copie o texto exato.

## 9. `tests/Unit/Models/Events/EnrollmentTest.php`

No `setUp` (linhas ~37-49), no `new Event([...])`, depois de
`'capacity' => 5,`:

```php
            'workload_hours' => 2,
```

Por quê: o `setUp` salva um Evento para testar inscrições. Sem o campo o
`save()` retorna `false` e todos os testes de Inscrição caem.

## 10. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) `validParams()` (linhas ~272-284), depois de `'capacity' => '20',`:

```php
            'workload_hours' => '8',
```

b) Testes novos, depois de `test_create_with_invalid_data...`
(`ECT:114-138` é o molde):

```php
    public function test_create_with_invalid_workload_hours_shows_error_below_the_field(): void
    {
        foreach (['', '0'] as $workloadHours) {
            $response = $this->post(
                action: 'create',
                controllerName: self::CONTROLLER,
                params: ['event' => $this->validParams(['workload_hours' => $workloadHours, 'location' => 'Bloco C'])]
            );

            $message = $workloadHours === '' ? 'não pode ser vazio!' : 'deve ser maior ou igual a 1!';
            $this->assertCount(0, Event::all());
            $this->assertMatchesRegularExpression(
                '/id="event_workload_hours"[^>]*value="' . $workloadHours . '">\s*'
                    . '<div class="invalid-feedback">' . $message . '<\/div>/',
                $response
            );
            $this->assertMatchesRegularExpression('/class="form-control is-invalid" id="event_workload_hours"/', $response);
            $this->assertStringContainsString('value="Oficina de Testes"', $response);
            $this->assertStringContainsString('value="Bloco C"', $response);
            $this->assertDoesNotMatchRegularExpression('/is-invalid" id="event_(title|location|capacity)"/', $response);
        }
    }

    public function test_create_saves_workload_hours_and_show_displays_it(): void
    {
        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['workload_hours' => '16'])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(16, $event->workload_hours);

        $show = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $this->assertStringContainsString('Carga horária', $show);
        $this->assertStringContainsString('16 h', $show);

        $index = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $this->assertStringContainsString('16 h', $index);
    }
```

Por quê:

- Teste de integração chama a ação do controller com `Request` montado
  e lê o HTML. Prova o caminho form -> `FIELDS` -> model -> view.
- Primeiro teste: vazio e `0` dão erro embaixo do campo e os demais
  valores ficam preenchidos (sem perder o que o usuário digitou).
- Segundo: salva e aparece em `show` e `index`.

## 11. `tests/Integration/Access/EventsAccessTest.php`

a) `setUp` (linhas ~28-40), no `new Event([...])`, depois de
`'capacity' => 10,`:

```php
            'workload_hours' => 2,
```

b) No teste que faz POST de criação via HTTP (linha ~150), no array
`event`, depois de `'capacity' => '5',`:

```php
            'workload_hours' => '2',
```

Por quê: teste de acesso sobe o servidor e bate nas rotas por HTTP. O
`setUp` precisa de um Evento salvo; o POST precisa passar na validação.

## 12. `tests/Acceptance/admin/events/BaseEventsCest.php`

a) `seedEvents` (linhas ~84-94), depois de `'capacity' => ...,`:

```php
                'workload_hours' => 4,
```

b) `fillEventForm` (linhas ~122-158), lista de campos de texto:

```php
        foreach (['title', 'description', 'location', 'capacity', 'workload_hours'] as $field) {
```

e no docblock acima, acrescente `workload_hours` à lista de chaves
aceitas.

c) `validEventData` (linhas ~163-175), depois de `'capacity' => '40',`:

```php
            'workload_hours' => '8',
```

Por quê:

- `seedEvents` cria os 16 Eventos do navegador; não confere o retorno de
  `save()`. Sem o campo os Cests falham com "0 linhas", sem mensagem
  clara.
- `fillEventForm` só preenche o que está na lista. Sem o campo o Cest
  nunca envia `workload_hours` e todo cadastro falha.
- `validEventData` é a fábrica de dados válidos dos Cests.

## 13. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`

a) Depois de `$page->see($data['location']);`:

```php
        $page->see('8 h');
```

b) Depois de `Assert::assertEquals(40, $event->capacity);`:

```php
        Assert::assertEquals(8, $event->workload_hours);
```

Por quê: o Cest de sucesso confere a tela (`show`) e o banco. Dois
asserts novos provam que o campo chegou nos dois.

## 14. `tests/Acceptance/admin/events/CreateWithInvalidDataCest.php`

a) Lista de campos obrigatórios (teste de form vazio):

```php
        foreach (['title', 'description', 'kind', 'starts_on', 'location', 'capacity', 'workload_hours'] as $field) {
```

b) No teste de valores inválidos, depois de
`$page->fillField('#event_capacity', '0');`:

```php
        $page->fillField('#event_workload_hours', '0');
```

c) Depois dos asserts de `capacity`:

```php
        $page->seeElement('#event_workload_hours.is-invalid');
        $page->see('deve ser maior ou igual a 1!', '#event_workload_hours ~ .invalid-feedback');
```

Por quê: espelha o teste de integração, mas no navegador real
(Selenium). Vazio -> "não pode ser vazio!"; `0` -> "deve ser maior ou
igual a 1!", embaixo do campo, com borda vermelha.

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # Unit + Integration (o PHPUnit não roda os Cests)
docker compose up -d selenium && sleep 5
./run test:browser      # Acceptance
docker compose stop selenium
./run phpcs
./run phpstan
```

No navegador: criar Evento com carga vazia e com `0` -> erro embaixo do
campo, demais valores mantidos. Com `8` -> salva, aparece em `show`
("8 h") e na listagem.

Commit (inglês): `feat: Add workload hours to events`.

## Armadilhas (resumo)

- Esquecer `$columns`: `Property workload_hours not found`.
- Esquecer `FIELDS`: "nunca salva", erro de vazio.
- Não refazer o schema no banco de dev: `Unknown column` só no
  navegador.
- `DEFAULT` na coluna não ajuda (framework manda `NULL`).
- Campo opcional: não use `notEmpty`; teste
  `if ($this->x !== null && $this->x !== '')` antes da regra numérica.
- `(int)` no controller: conversão silenciosa, penalizada.
