# M7 passo a passo: Horário do Evento

Pedido típico: "o Evento precisa ter horário de início, não só a data.
Não deixe cadastrar para um horário que já passou, nem hoje mais cedo.
Mostre data e hora no form, na listagem e no detalhe." Chance:
média-alta. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/m7-horario-do-evento/` (patch, bundle e
árvore `projeto/`). Pré-requisitos: nenhum (base `main`).

Forma do exemplo: (B) do catálogo, `starts_on DATE` vira
`starts_at DATETIME` (formato `Y-m-d H:i`). Renomeia a coluna em todos os
pontos de M1 e a regra "não no passado" passa a considerar a hora. A
forma (A), menor (`starts_time TIME` ao lado de `starts_on`), está no fim.

Ordem = fluxo da requisição: banco -> model -> controller -> views ->
dados -> testes. Na prova, prioridade: funcionar no navegador, depois
testar, depois `phpcs`.

Dica: no fim, `grep -rn starts_on app database tests` tem que voltar
vazio.

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
15. `tests/Acceptance/admin/events/ListEventsCest.php`
16. `tests/Acceptance/admin/events/UpdateSuccessfullyCest.php`
17. `tests/Acceptance/admin/events/UpdateWithInvalidDataCest.php`

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE events`, troque a coluna e o índice:

```sql
    audience    SET('students', 'professors', 'staff', 'community') NOT NULL,
    starts_at   DATETIME NOT NULL,
    location    VARCHAR(255) NOT NULL,
```

```sql
    INDEX index_events_on_starts_at (starts_at)
);
```

Por quê:

- Uma coluna guarda "quando": "não no passado" compara um valor só;
  ordenar/filtrar (N3, N5, N9) segue sendo uma coluna.
- `DATETIME`, não `TIMESTAMP`: sem fuso. `TIMESTAMP` converte pelo fuso
  da sessão do MySQL.
- Índice renomeado acompanha a coluna (convenção
  `index_<tabela>_on_<coluna>`).

Armadilha: testes recriam o schema; o banco de dev não. No fim,
`./run db:populate`, senão `Unknown column` só no navegador.

## 2. `app/Models/Event.php`

Cinco pontos.

a) Docblock da classe, no lugar de `@property string $starts_on`:

```php
 * @property string $starts_at data e hora no formato Y-m-d H:i (o __set normaliza)
```

b) `$columns`: `'starts_on',` vira:

```php
        'starts_at',
```

c) `validates()`, bloco da data:

```php
        Validations::notEmpty('starts_at', $this) &&
            Validations::validDate('starts_at', $this, 'Y-m-d H:i') &&
            $this->validatesStartsAtNotPast();
```

d) Logo depois de `validates()`, `__set` novo (antes do método "não no
passado"):

```php
    /**
     * Normaliza `starts_at` para Y-m-d H:i. O `<input type="datetime-local">` envia "2030-05-10T14:30"
     * e o MySQL devolve a coluna DATETIME como "2030-05-10 14:30:00"; os dois viram "2030-05-10 14:30".
     * Valor fora desses formatos fica como veio, para a validação rejeitar.
     */
    public function __set(string $property, mixed $value): void
    {
        if ($property === 'starts_at' && is_string($value)) {
            $value = preg_replace('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?$/', '$1 $2', $value);
        }

        parent::__set($property, $value);
    }
```

e) `validatesStartsOnNotPast()` vira `validatesStartsAtNotPast()`, com
comparação por minuto; e `startsAtInputValue()` novo logo abaixo:

```php
    /**
     * Data e hora não podem estar no passado ao criar (comparação por minuto: um horário de hoje que já
     * passou é rejeitado). Na edição, só é cobrada quando `starts_at` muda: assim um Evento que já
     * aconteceu ainda pode ter a descrição, o local etc. corrigidos.
     */
    private function validatesStartsAtNotPast(): void
    {
        if (!$this->newRecord()) {
            $original = self::findById($this->id);

            if ($original !== null && $original->starts_at === $this->starts_at) {
                return;
            }
        }

        // Os dois lados estão em Y-m-d H:i (já passou pelo validDate), então a comparação de strings é cronológica.
        if ($this->starts_at < date('Y-m-d H:i')) {
            $this->addError('starts_at', 'não pode estar no passado!');
        }
    }

    /**
     * Valor para o `<input type="datetime-local">` (Y-m-d\TH:i). Mantém o texto digitado se for inválido.
     */
    public function startsAtInputValue(): string
    {
        return (string) preg_replace('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', '$1T$2', (string) $this->starts_at);
    }
```

Por quê:

- `@property` + `$columns`: mesmo roteiro de M1 (atributo dinâmico,
  lista do `INSERT`/`UPDATE`).
- `validDate` já aceita `$format` (`lib/Validations.php:99-107`);
  `'Y-m-d H:i'` recusa `2030-02-31 10:00`, `24:00`, `9:00`, data sem
  hora.
- `notPastDate` não serve: zera a hora de hoje (`setTime(0, 0)`) e
  aceitaria 08:00 às 15:00. Em vez de `notPastDateTime` no lib, a regra
  fica no método privado que já existia (uma linha).
- Comparar strings funciona porque os dois lados estão em `Y-m-d H:i`
  (ordem lexicográfica = cronológica). Minuto atual é aceito.
- Edição só cobra quando `starts_at` muda (regra da base mantida).
- `__set` no model, não no controller: o construtor do `Model` chama
  `__set` para cada param, então vale pro form, pro que vem do banco
  (`findById`/`all` usam `new static($row)`; `lib/Paginator.php:143`
  usa `new $this->class($row)`) e pros testes. Controller segue só
  copiando `FIELDS`.
- Texto fora do formato fica como veio: a validação recusa e o form
  devolve o que foi digitado (`startsAtInputValue()`).
- `startsAtInputValue()`: o `datetime-local` só aceita `value` com `T`.
  Formatação de input pertence ao model, view só chama.

Armadilhas:

- MySQL devolve `DATETIME` como `Y-m-d H:i:s`. Sem o `__set`, editar
  sem mexer na hora dá "não é uma data válida!".
- `datetime-local` envia `2030-05-10T14:30` (com `T`), que `'Y-m-d H:i'`
  recusa. Por isso a troca do `T` no `__set`.

## 3. `app/Controllers/Admin/EventsController.php`

Constante `FIELDS` (linha ~14): `'starts_on'` vira `'starts_at'`:

```php
    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_at', 'location', 'capacity'];
```

Por quê: allowlist; só o que está aqui sai do form e chega ao model.
Esquecer -> "não pode ser vazio!" sem motivo aparente. Nada de converter
data no controller.

## 4. `app/views/admin/events/_form.phtml`

Bloco da data (linhas ~70-75), inteiro:

```php
    <div class="mb-3">
        <label for="event_starts_at" class="form-label">Data e hora</label>
        <input type="datetime-local" class="form-control<?= $invalid('starts_at') ?>" id="event_starts_at"
            name="event[starts_at]" value="<?= htmlspecialchars($event->startsAtInputValue()) ?>">
        <?= $feedback('starts_at') ?>
    </div>
```

Por quê:

- `type="datetime-local"`: seletor de data + hora; envia `Y-m-d\TH:i`.
- `value` via `startsAtInputValue()` (com `T`); em erro, volta o texto
  digitado. `htmlspecialchars`: escape (XSS), rubrica cobra.
- `id`/`name` novos: `$invalid`/`$feedback` e os testes procuram
  `event_starts_at`.

## 5. `app/views/admin/events/index.phtml`

a) `<thead>`: `<th>Data</th>` vira:

```php
                <th>Data e hora</th>
```

b) `<tbody>` (linha ~24):

```php
                    <td><?= date('d/m/Y H:i', strtotime($event->starts_at)) ?></td>
```

Por quê: só formatação. View exibe o que veio do banco (já validado),
então `strtotime` não cai em 1970 aqui. `paginate()` já traz a coluna.

## 6. `app/views/admin/events/show.phtml`

Par `dt`/`dd` da data (linhas ~22-23):

```php
    <dt class="col-sm-3">Data e hora</dt>
    <dd class="col-sm-9"><?= date('d/m/Y H:i', strtotime($event->starts_at)) ?></dd>
```

Por quê: mesmo formato da listagem; `show` só exibe o `$event`.

## 7. `database/Populate/populate.php`

a) Comentário acima de `$events = [` (linha ~48): `starts_on` vira
`starts_at` e, logo antes de `$events = [`:

```php
// O horário de início alterna entre os de $startTimes (manhã, tarde e noite).
$startTimes = ['08:00', '10:30', '14:00', '19:00'];
```

b) `foreach` (linha ~133) ganha `$index =>` e a chave da data muda:

```php
foreach ($events as $index => [$title, $description, $kind, $modality, $audience, $daysAhead, $location, $capacity]) {
```

```php
        'starts_at' => date('Y-m-d', strtotime("+{$daysAhead} days")) . ' ' . $startTimes[$index % count($startTimes)],
```

Por quê:

- `save()` valida; chave antiga `starts_on` -> "não pode ser vazio!" ->
  `RuntimeException`, banco de dev vazio.
- Datas relativas (+14 a +82 dias) continuam: nunca ficam no passado.
- `$index % count(...)` varia a hora sem mexer nas linhas posicionais.

## 8. `tests/Unit/Models/Events/EventTest.php`

a) Troca mecânica em todo o arquivo: chave/campo `starts_on` ->
`starts_at` e `date('Y-m-d', strtotime(X))` -> `date('Y-m-d H:i',
strtotime(X))`. Pontos: `params()`, as duas listas de campos
(`test_empty_event_should_have_error_in_every_field` e `errorsOf()`), o data provider
(`'starts_at' => ['starts_at'],`), `createPastEvent()` e o bloco
"não passada só no create e quando data/hora muda". Os testes desse
bloco trocam `starts_on` por `starts_at` no nome também
(`test_past_event_can_be_edited_when_starts_at_does_not_change` etc.).
Em `test_invalid_starts_at_should_still_be_rejected_on_edit_of_a_past_event`:

```php
        $event->starts_at = '2030-02-30 10:00';
```

b) `test_starts_on_should_be_a_valid_date` vira:

```php
    public function test_starts_at_should_be_a_valid_date(): void
    {
        $values = [
            'amanhã', '2030-02-31 10:00', '2030-13-01 10:00', '30/12/2030 10:00', '2030-1-5 10:00',
            '2030-05-10', '2030-05-10 24:00', '2030-05-10 10:60', '2030-05-10 9:00',
        ];

        foreach ($values as $value) {
            $event = new Event($this->params(['starts_at' => $value]));

            $this->assertFalse($event->isValid(), $value);
            $this->assertEquals('não é uma data válida!', $event->errors('starts_at'), $value);
        }
    }
```

c) `test_starts_on_should_not_be_in_the_past` vira
`test_starts_at_should_not_be_in_the_past` (troca mecânica).
`test_starts_on_today_should_be_valid` **sai** (com hora, "hoje" não é
sempre válido). No lugar:

```php
    public function test_starts_at_one_hour_ago_should_be_in_the_past(): void
    {
        $event = new Event($this->params(['starts_at' => date('Y-m-d H:i', strtotime('-1 hour'))]));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não pode estar no passado!', $event->errors('starts_at'));
    }

    public function test_starts_at_in_one_hour_should_be_valid(): void
    {
        $event = new Event($this->params(['starts_at' => date('Y-m-d H:i', strtotime('+1 hour'))]));

        $this->assertTrue($event->isValid());
    }

    public function test_starts_at_should_accept_the_datetime_local_format(): void
    {
        $event = new Event($this->params(['starts_at' => '2030-05-10T14:30']));

        $this->assertEquals('2030-05-10 14:30', $event->starts_at);
        $this->assertEquals('2030-05-10T14:30', $event->startsAtInputValue());
        $this->assertTrue($event->save());

        $found = Event::findById($event->id);
        $this->assertEquals('2030-05-10 14:30', $found->starts_at);
    }

    public function test_starts_at_input_value_keeps_invalid_text(): void
    {
        $event = new Event($this->params(['starts_at' => 'amanhã cedo']));

        $this->assertEquals('amanhã cedo', $event->startsAtInputValue());
    }
```

Por quê:

- `params()` é a fábrica de Evento válido; sem a troca todo teste que
  salva quebra.
- `-1 hour`: prova a regra por hora (com `notPastDate` passaria).
- `+1 hour`, nunca "agora": o minuto pode virar entre montar e validar.
- `datetime_local_format`: `T` normalizado, `value` com `T`, grava e
  relê do banco (`:00` dos segundos cortado pelo `__set`).
- `test_past_event_can_be_edited_when_starts_at_does_not_change` é a
  prova do `HH:MM:SS`: carrega do banco, muda a descrição, salva.

## 9. `tests/Unit/Models/Events/EnrollmentTest.php`

`setUp` (linha ~45), no `new Event([...])`:

```php
            'starts_at' => date('Y-m-d H:i', strtotime('+10 days')),
```

Por quê: `setUp` salva um Evento; chave antiga -> `save()` falso -> todos
os testes de Inscrição caem.

## 10. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) `test_index_shows_date_and_enrollments_over_capacity` (linha ~59):

```php
        $event = $this->createEvent(['capacity' => 10, 'starts_at' => date('Y-m-d', strtotime('+10 days')) . ' 14:30']);
```

```php
        $this->assertStringContainsString(date('d/m/Y', strtotime('+10 days')) . ' 14:30', $response);
```

b) `test_new_renders_form`: `'type="date"'` vira:

```php
        $this->assertStringContainsString('type="datetime-local"', $response);
```

c) Teste novo depois de
`test_create_with_valid_data_saves_event_for_logged_admin`:

```php
    public function test_create_accepts_starts_at_from_datetime_local_input(): void
    {
        $day = date('Y-m-d', strtotime('+10 days'));

        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['starts_at' => "{$day}T08:45"])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertEquals("{$day} 08:45", $event->starts_at);
    }
```

d) `test_edit_renders_form_filled`: no `createEvent([...])`, depois de
`'audience' => ['professors', 'staff'],`:

```php
            'starts_at' => date('Y-m-d', strtotime('+10 days')) . ' 14:30',
```

e no fim do teste:

```php
        $this->assertStringContainsString('type="datetime-local"', $response);
        $this->assertStringContainsString('value="' . date('Y-m-d', strtotime('+10 days')) . 'T14:30"', $response);
```

e) `validParams()` (linha ~280):

```php
            'starts_at' => date('Y-m-d H:i', strtotime('+10 days')),
```

Por quê:

- Integração prova form -> `FIELDS` -> `__set` -> banco -> view.
- `T08:45` é exatamente o que o navegador manda; gravar `08:45` prova a
  normalização no caminho real do POST.
- `edit` com `value="...T14:30"`: o form reabre preenchido no formato do
  input (caminho do `HH:MM:SS` ao contrário).

## 11. `tests/Integration/Access/EventsAccessTest.php`

a) `setUp` (linha ~37):

```php
            'starts_at' => date('Y-m-d H:i', strtotime('+30 days')),
```

b) POST de criação via HTTP (linha ~150):

```php
            'starts_at' => date('Y-m-d H:i', strtotime('+10 days')),
```

Por quê: teste de acesso bate nas rotas por HTTP; `setUp` precisa de
Evento salvo e o POST precisa passar na validação.

## 12. `tests/Acceptance/admin/events/BaseEventsCest.php`

a) `startsOn()` vira `startsAt()` (linha ~68), às 19:00:

```php
    protected function startsAt(int $number): string
    {
        return date('Y-m-d', strtotime('+' . (13 + $number) . ' days')) . ' 19:00';
    }
```

b) `seedEvents`: `'starts_at' => $this->startsAt($number),` (e
`starts_on` -> `starts_at` no docblock acima).

c) `fillEventForm`: docblock `starts_on (Y-m-d)` vira
`starts_at (Y-m-d H:i)`; bloco do `executeJS`:

```php
        if (array_key_exists('starts_at', $data)) {
            // input[type=datetime-local] depende do idioma do navegador ao digitar; definir o valor ISO
            // (Y-m-d\TH:i) é determinístico.
            $page->executeJS(
                "document.getElementById('event_starts_at').value = arguments[0];",
                [str_replace(' ', 'T', (string) $data['starts_at'])]
            );
        }
```

d) `validEventData`:

```php
            'starts_at' => date('Y-m-d H:i', strtotime('+1 day')),
```

Por quê:

- `seedEvents` não confere `save()`: chave errada -> Cests falham com
  "0 linhas", sem mensagem.
- `fillField` não digita em `datetime-local` de forma confiável (depende
  do idioma). `executeJS` põe o valor ISO direto, como a base fazia com
  o `date`. Troca o espaço por `T` porque os dados dos Cests estão em
  `Y-m-d H:i`.

## 13. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`

Assert do banco (linha ~43):

```php
        Assert::assertSame($data['starts_at'], $event->starts_at);
```

Por quê: `assertSame` com `Y-m-d H:i` só passa porque o `__set` corta os
`:00` que o MySQL devolve.

## 14. `tests/Acceptance/admin/events/CreateWithInvalidDataCest.php`

a) Lista de obrigatórios (linha ~23):

```php
        foreach (['title', 'description', 'kind', 'starts_at', 'location', 'capacity'] as $field) {
```

b) Teste de valores inválidos: data de `-1 day` vira `-1 hour`, e os
asserts usam `#event_starts_at`:

```php
        $this->fillEventForm($page, ['starts_at' => date('Y-m-d H:i', strtotime('-1 hour'))]);
```

```php
        $page->seeElement('#event_starts_at.is-invalid');
        $page->see('não pode estar no passado!', '#event_starts_at ~ .invalid-feedback');
```

Por quê: `-1 hour` prova a regra por hora no navegador real (hoje mais
cedo é recusado).

## 15. `tests/Acceptance/admin/events/ListEventsCest.php`

Linha ~28:

```php
            $this->assertContains(date('d/m/Y H:i', (int) strtotime($event->starts_at)), $row);
```

Por quê: listagem agora mostra `d/m/Y H:i`.

## 16. `tests/Acceptance/admin/events/UpdateSuccessfullyCest.php`

a) Dados (linha ~19):

```php
            'starts_at' => date('Y-m-d H:i', strtotime('+90 days')),
```

b) Campo preenchido (linha ~37):

```php
        $page->seeInField('#event_starts_at', $event->startsAtInputValue());
```

c) Assert do banco (linha ~55):

```php
        Assert::assertSame($data['starts_at'], $stored->starts_at);
```

Por quê: o input mostra o valor com `T`; `startsAtInputValue()` é a
fonte da verdade. Obs.: este Cest **troca** a data, então não prova o
caso "editar sem mexer na hora" (quem prova é o `EventTest`).

## 17. `tests/Acceptance/admin/events/UpdateWithInvalidDataCest.php`

a) Dados (linha ~27):

```php
            'starts_at' => date('Y-m-d H:i', strtotime('-2 days')),
```

b) Asserts (linha ~38):

```php
        $page->seeElement('#event_starts_at.is-invalid');
        $page->see('não pode estar no passado!', '#event_starts_at ~ .invalid-feedback');
```

Por quê: só renomeia; regra e mensagem são as mesmas.

## Forma (A), alternativa menor

Mantém `starts_on DATE` e soma `starts_time TIME NOT NULL` (e
`ends_time TIME NULL`) depois dela. Mesmo roteiro de M1:

- model: `@property`, `$columns`, e
  `Validations::notEmpty('starts_time', $this) &&
  Validations::validDate('starts_time', $this, 'H:i') && ...`;
  `ends_time > starts_time` comparando strings `H:i`;
- cortar segundos antes de validar
  (`substr((string) $this->starts_time, 0, 5)`): MySQL devolve
  `14:00:00`;
- `FIELDS`, `<input type="time">` (Cest com `executeJS`), view com
  `date('d/m/Y', ...) . ' ' . substr($event->starts_time, 0, 5)`;
- "não no passado" por hora: juntar `starts_on . ' ' . starts_time`.

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
grep -rn starts_on app database tests   # tem que voltar vazio
```

No navegador (admin): listagem mostra `19/11/2026 08:00` etc. "Editar"
abre o `datetime-local` preenchido. Salvar **sem mexer na hora** funciona
(é onde o `HH:MM:SS` morde). Hoje, uma hora atrás -> "não pode estar no
passado!" sob o campo.

Commit (inglês): `feat: Replace event date with starts_at datetime`.

## Armadilhas (resumo)

- MySQL devolve `TIME`/`DATETIME` com segundos (`14:30:00`): sem
  normalizar, editar sem mexer na hora falha em `validDate`.
- `datetime-local` envia `T` (`2026-11-20T19:00`): troque por espaço
  antes de validar ou use `'Y-m-d\TH:i'`.
- `type="time"`/`datetime-local` no Selenium: `fillField` não digita; use
  `executeJS` como a base faz para `date`.
- `notPastDate` compara só o dia (zera a hora): teste com `-1 hour`, não
  só com dia.
- Não use "agora" como valor válido nos testes; use `+1 hour`.
- Fuso: PHP em `America/Sao_Paulo`, `DATETIME` sem fuso. Não troque por
  `TIMESTAMP`.
- `strtotime('')` vira 1970 na view: proteja se o valor puder vir vazio.
- Forma B mexe em tudo que cita `starts_on`: M4 conflita; N3, N5, N9 e
  Q2 precisam trocar para `starts_at`.
