# P1 passo a passo: Código de check-in aleatório (1000-9999) por Evento

Pedido típico: "cada Evento recebe, ao ser criado, um código numérico
aleatório de 4 dígitos (1000 a 9999), único, gerado pelo backend, que
não vem do form e aparece no detalhe e na listagem". Chance: alta.
Tempo: 25-35 min. Pré-requisito: nenhum (parte de `main`). É a
transposição do pedido real da FVP 4.1 (P12) para o CRUD.

Exemplo pronto: `fvp/4.2/exemplos/p1-codigo-checkin-aleatorio/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Ordem = fluxo da requisição: banco -> model -> views -> testes.
Controller e populate **não** mudam. Na prova, prioridade: funcionar no
navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `database/schema.sql`
2. `app/Models/Event.php`
3. `app/views/admin/events/index.phtml`
4. `app/views/admin/events/show.phtml`
5. `tests/Unit/Models/Events/EventTest.php`
6. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
7. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`

Não mexa: `app/Controllers/Admin/EventsController.php` (`FIELDS` fica
sem `checkin_code`) e `database/Populate/populate.php` (o `save()` gera
o código dos 16 Eventos).

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE events`:

a) Logo após `capacity`:

```sql
    capacity    INT NOT NULL,
    -- Código de check-in (1000 a 9999), gerado pelo Event::save() no cadastro; nunca vem do formulário.
    checkin_code SMALLINT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
```

b) Depois do `CONSTRAINT check_events_capacity ...`:

```sql
    CONSTRAINT check_events_capacity CHECK (capacity >= 1),
    UNIQUE KEY uq_events_checkin_code (checkin_code),
```

Por quê:

- `save()` do framework insere **todas** as colunas de `$columns`;
  coluna tem que existir.
- `SMALLINT UNSIGNED` (0-65535) cabe a faixa; 1000-9999 é cobrado no
  model.
- `DEFAULT` não resolve: o `INSERT` mandaria `NULL`. O código precisa
  estar no objeto antes do `INSERT`.
- `UNIQUE KEY`: garante a regra no banco mesmo em corrida entre dois
  cadastros simultâneos; o model dá a mensagem amigável.

Armadilha: banco de dev não recria sozinho. No fim, `./run db:populate`.

## 2. `app/Models/Event.php`

Cinco pontos.

a) Docblock da classe, depois de `@property int|string $capacity`:

```php
 * @property int|string|null $checkin_code gerado por save() via newCheckinCode(), nunca pelo formulário
```

Por quê: atributo dinâmico (`__get`/`__set`); sem `@property` o
`phpstan` reclama. `null` porque Evento novo nasce sem código.

b) Constantes, depois do array `AUDIENCES` e antes de `$table`:

```php
    /** Faixa do código de check-in. */
    public const CHECKIN_CODE_MIN = 1000;
    public const CHECKIN_CODE_MAX = 9999;
```

Por quê: um lugar só para a faixa; testes e Cest usam as constantes.

c) `$columns`, depois de `'capacity',`:

```php
        'capacity',
        'checkin_code',
    ];
```

Por quê: sem isso o valor fica no objeto mas não vai ao `INSERT`, e o
MySQL recusa (`NOT NULL`).

d) `validates()`, no fim, depois do `if` de `capacity`:

```php
        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }

        // Evento novo sem código é válido: o save() gera o código antes de gravar.
        if (!$this->newRecord() || !$this->isCheckinCodeBlank()) {
            Validations::notEmpty('checkin_code', $this) &&
                Validations::integerMin('checkin_code', $this, self::CHECKIN_CODE_MIN) &&
                $this->validatesCheckinCodeMax() &&
                Validations::uniqueness('checkin_code', $this);
        }
    }
```

Por quê:

- Validação mora no model. `&&` = curto-circuito: um erro por campo.
- `lib/Validations.php` não tem `integerMax`: método privado (abaixo).
- `uniqueness` na edição compara com o próprio registro: salvar sem
  mudar o código continua válido; cobre quem força código repetido.
- O `if`: Evento novo sem código pula o bloco. Validar `notEmpty`
  sempre quebraria `isValid()` antes do `save()` e os testes que o
  chamam num Evento novo (`test_valid_event_should_be_valid`...).

e) Métodos novos, logo depois do fim de `validates()`:

```php
    /**
     * Ao criar o Evento, gera o código de check-in se ele ainda não foi definido.
     * Na edição o código nunca muda.
     */
    public function save(): bool
    {
        if ($this->newRecord() && $this->isCheckinCodeBlank()) {
            $this->checkin_code = self::newCheckinCode();
        }

        return parent::save();
    }

    /**
     * Sorteia um código de check-in na faixa 1000–9999 que nenhum outro Evento usa.
     * Repete o sorteio enquanto o código já existir no banco.
     */
    public static function newCheckinCode(): int
    {
        do {
            $code = random_int(self::CHECKIN_CODE_MIN, self::CHECKIN_CODE_MAX);
        } while (self::exists(['checkin_code' => $code]));

        return $code;
    }

    private function isCheckinCodeBlank(): bool
    {
        return $this->checkin_code === null || $this->checkin_code === '';
    }

    private function validatesCheckinCodeMax(): bool
    {
        if ((int) $this->checkin_code > self::CHECKIN_CODE_MAX) {
            $this->addError('checkin_code', 'deve ser menor ou igual a ' . self::CHECKIN_CODE_MAX . '!');
            return false;
        }

        return true;
    }
```

Por quê:

- `save()` sobrescrito: `Model::save()` chama `isValid()` e depois
  `INSERT`. Gerando antes do `parent::save()`, o código já está no
  objeto na validação e no `INSERT`.
- Todo caminho que cria Evento (controller, populate, testes, Cests)
  ganha código sem mudar nada. Regra de negócio no model; gerar no
  controller o professor recusa; na view, nunca.
- `newRecord()` (`id === null`): só gera ao criar. Sem isso, cada
  edição trocaria um código já divulgado.
- `random_int`: gerador criptograficamente seguro (imprevisível).
  `rand()`/`mt_rand()` funcionam, mas é o que se espera ver.
- Laço `do/while exists`: sem ele, colisão (9000 valores) -> `save()`
  falha no `uniqueness` e o admin vê "dados incorretos" sem ter
  errado. `exists()` usa `where` com igualdade (prepare/bind).
- `static`: sortear não depende de um Evento carregado. Reaproveitável
  (ex. ação "regenerar", O1).
- `validatesCheckinCodeMax` devolve `bool` para entrar na cadeia `&&`.

## 3. `app/views/admin/events/index.phtml`

a) `<thead>`, depois de `<th>Vagas</th>`:

```php
                <th>Vagas</th>
                <th>Código</th>
                <th>Ações</th>
```

b) `<tbody>`, depois do `<td>` de vagas, antes do `<td>` de ações:

```php
                    <td><?= $event->enrollmentsCount() ?>/<?= (int) $event->capacity ?></td>
                    <td><?= (int) $event->checkin_code ?></td>
                    <td>
```

Por quê:

- View só exibe; `paginate()` já traz todas as colunas de `$columns`.
- `(int)` é o escape de número (dispensa `htmlspecialchars`).
- Ordem dos `<td>` = ordem dos `<th>`.

## 4. `app/views/admin/events/show.phtml`

Dentro do `<dl>`, depois do `</dd>` de vagas, antes do `</dl>`:

```php
        (<?= $event->availableSpots() ?> disponíveis)
    </dd>

    <dt class="col-sm-3">Código de check-in</dt>
    <dd class="col-sm-9" id="event_checkin_code"><?= (int) $event->checkin_code ?></dd>
</dl>
```

Por quê: par `dt`/`dd` no padrão dos outros campos. `id` serve de
seletor para o Cest (`#event_checkin_code`).

Não mexa em `_form.phtml`: código não tem `<input>`.

## 5. `tests/Unit/Models/Events/EventTest.php`

Nova seção depois de
`test_audience_should_survive_a_round_trip_in_the_database`, antes do
comentário `/* --- save / update / destroy / busca --- */`. `params()`
e `createEvent()` já existem e não mudam:

```php
    /* ------------------- código de check-in ------------------- */

    public function test_new_checkin_code_should_be_between_1000_and_9999(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = Event::newCheckinCode();

            $this->assertGreaterThanOrEqual(1000, $code);
            $this->assertLessThanOrEqual(9999, $code);
        }
    }

    public function test_new_checkin_code_should_not_return_a_code_in_use(): void
    {
        $codes = [];
        for ($i = 1; $i <= 20; $i++) {
            $codes[] = (int) $this->createEvent(['title' => "Evento {$i}"])->checkin_code;
        }

        $this->assertCount(20, array_unique($codes));
        for ($i = 0; $i < 200; $i++) {
            $this->assertNotContains(Event::newCheckinCode(), $codes);
        }
    }

    public function test_save_should_generate_the_checkin_code_of_a_new_event(): void
    {
        $event = new Event($this->params());
        $this->assertNull($event->checkin_code);
        $this->assertTrue($event->isValid());

        $this->assertTrue($event->save());

        $this->assertGreaterThanOrEqual(1000, $event->checkin_code);
        $this->assertLessThanOrEqual(9999, $event->checkin_code);
        $this->assertEquals($event->checkin_code, Event::findById($event->id)->checkin_code);
        $this->assertCount(1, Event::where(['checkin_code' => $event->checkin_code]));
    }

    public function test_existing_event_with_blank_checkin_code_should_be_invalid(): void
    {
        $event = $this->createEvent();

        $event->checkin_code = '';

        $this->assertFalse($event->save());
        $this->assertEquals('não pode ser vazio!', $event->errors('checkin_code'));
    }

    public function test_checkin_code_should_be_unique(): void
    {
        $existing = $this->createEvent();

        $event = new Event($this->params(['title' => 'Outro', 'checkin_code' => $existing->checkin_code]));

        $this->assertFalse($event->isValid());
        $this->assertFalse($event->save());
        $this->assertEquals('já existe um registro com esse dado', $event->errors('checkin_code'));
        $this->assertCount(1, Event::all());
    }

    public function test_checkin_code_outside_the_range_should_be_invalid(): void
    {
        $messages = [
            '999' => 'deve ser maior ou igual a 1000!',
            '10000' => 'deve ser menor ou igual a 9999!',
            'abcd' => 'deve ser um número inteiro!',
        ];

        foreach ($messages as $code => $message) {
            $event = new Event($this->params(['checkin_code' => (string) $code]));

            $this->assertFalse($event->isValid(), (string) $code);
            $this->assertEquals($message, $event->errors('checkin_code'), (string) $code);
        }

        $this->assertTrue((new Event($this->params(['checkin_code' => 1000])))->isValid());
        $this->assertTrue((new Event($this->params(['checkin_code' => 9999])))->isValid());
    }

    public function test_existing_event_should_keep_its_checkin_code_when_updated(): void
    {
        $event = $this->createEvent();
        $code = $event->checkin_code;

        $event->title = 'Título novo';

        $this->assertTrue($event->save());
        $this->assertEquals($code, Event::findById($event->id)->checkin_code);
    }
```

Por quê:

- Faixa: 200 sorteios. Unicidade: 20 Eventos com códigos distintos e o
  sorteio nunca devolve um em uso.
- Evento novo: `null` -> `isValid()` ok -> `save()` gera e grava.
- Existente com código vazio, repetido, fora da faixa ou não inteiro:
  cada um com a mensagem do `Validations`. Limites 1000 e 9999 valem.
- Edição não troca o código (`newRecord()` falso).

## 6. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois de `test_create_ignores_user_id_sent_by_the_form` (é o modelo de
"ignora campo enviado pelo form"):

```php
    public function test_create_generates_the_checkin_code(): void
    {
        $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['event' => $this->validParams()]);

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertGreaterThanOrEqual(1000, $event->checkin_code);
        $this->assertLessThanOrEqual(9999, $event->checkin_code);
    }

    public function test_create_ignores_checkin_code_sent_by_the_form(): void
    {
        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['checkin_code' => '1'])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertNotEquals(1, $event->checkin_code);
        $this->assertGreaterThanOrEqual(1000, $event->checkin_code);
    }

    public function test_update_does_not_change_the_checkin_code(): void
    {
        $event = $this->createEvent();
        $code = $event->checkin_code;

        $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['checkin_code' => '1234'])]
        );

        $this->assertEquals($code, Event::findById($event->id)->checkin_code);
    }

    public function test_index_and_show_display_the_checkin_code(): void
    {
        $event = $this->createEvent();

        $index = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $show = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('<th>Código</th>', $index);
        $this->assertStringContainsString("<td>{$event->checkin_code}</td>", $index);
        $this->assertStringContainsString('Código de check-in', $show);
        $this->assertStringContainsString(">{$event->checkin_code}</dd>", $show);
    }
```

Por quê:

- Prova a allowlist `FIELDS`: `checkin_code` no POST (create e update)
  é ignorado; o `fill` só copia o que está em `FIELDS`.
- Código gerado também pelo caminho do controller.
- `index`/`show` exibem o valor salvo.
- `validParams()` e `createEvent()` não mudam.

## 7. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`

a) Depois de `$page->see($data['location']);`:

```php
        $page->see($data['location']);
        // O código de check-in foi gerado pelo sistema e aparece no show.
        $page->see('Código de check-in');
        $page->see((string) $event->checkin_code, '#event_checkin_code');
```

b) Depois de `Assert::assertSame(0, $event->enrollmentsCount());`:

```php
        Assert::assertGreaterThanOrEqual(Event::CHECKIN_CODE_MIN, (int) $event->checkin_code);
        Assert::assertLessThanOrEqual(Event::CHECKIN_CODE_MAX, (int) $event->checkin_code);
```

Por quê: navegador real. `$event` já é buscado no banco antes (linha
`Event::findBy(['title' => ...])`); confere o código na tela (`show`) e
a faixa no banco. `fillEventForm`/`validEventData` não mudam (código
não está no form).

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run test:browser      # com selenium no ar
./run phpcs
./run phpstan
```

Conferir no banco (`./run db:console`):

```sql
SELECT id, checkin_code FROM events;
SELECT COUNT(DISTINCT checkin_code) FROM events;  -- 16
```

No navegador: criar um Evento (form sem campo de código) -> `show`
mostra "Código de check-in" com valor 1000-9999; coluna "Código" na
listagem. Editar e salvar -> código igual.

Commit (inglês): `feat: Generate a random check-in code for each Event`.

## Armadilhas (resumo)

- Gerar no controller ("regra de negócio no model") ou na view.
- `checkin_code` em `FIELDS` ou num `<input>`: pedido diz que não vem
  do form; `test_create_ignores_checkin_code_sent_by_the_form` pega.
- Esquecer `$columns`: valor não vai ao `INSERT`, MySQL recusa.
- Gerar sem `newRecord()`: troca o código a cada edição.
- `rand()`/`mt_rand()` servem, mas `random_int()` é o correto.
- Sem laço de unicidade: colisão; o `uniqueness` barra, o `save()`
  devolve `false` e o admin vê "dados incorretos" sem ter errado.
- `notEmpty('checkin_code')` sempre: `isValid()` antes do `save()`
  acusa vazio e quebra `test_valid_event_should_be_valid`
  e outros. Por isso o `if`; alternativa: gerar no construtor (padrão
  `app/Models/User.php:28-35`).
- `exists()` usa `where` com igualdade.
- "Regenerar código" é ação nova (`PUT /admin/events/{id}/checkin_code`,
  O1), nunca campo no form.
- P6 (duplicar): zere `$copy->checkin_code = null;` para gerar outro.
