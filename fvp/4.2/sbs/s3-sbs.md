# S3 passo a passo: teste unitário do método novo do model

Pedido típico: "você criou o `Event::duplicate()`; escreva os testes
unitários dele no `EventTest`: título, data, inscrições, dono e se a
cópia é gravada". Chance: alta. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/s3-teste-unitario-model/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Pré-requisito: P6 (`p6-duplicar-evento`) aplicado. O patch traz dois
commits: o 1º é a base P6 (`feat: Duplicate an Event from the listing`);
este guia cobre só o 2º. O que o `duplicate()` do P6 faz (é o que os
testes conferem):

- devolve `new Event()` com todas as `$columns` copiadas, **sem salvar**
  (o controller chama `save()`);
- título: `mb_substr($this->title, 0, 247) . ' (cópia)'` (cabe em
  `VARCHAR(255)`);
- `starts_on`: hoje + 7 dias;
- Inscrições não são copiadas (ficam em `event_user`).

Ordem: só teste, nenhum código de aplicação muda. Testa o model isolado
(sem rota, controller, view). Na prova, prioridade: teste passa, depois
`phpcs`.

## Arquivos, em ordem

1. `tests/Unit/Models/Events/EventTest.php`

Depois: `./run test ... --filter duplicate`, `./run test tests/Unit`,
`./run phpcs`.

## 1. `tests/Unit/Models/Events/EventTest.php`

Seção nova depois do teste
`test_available_spots_should_be_capacity_minus_enrollments` (linha
~490) e antes do comentário
`/* ---...--- starts_on: "não passada" só no create e quando a data muda ---...--- */`.

Helpers que já existem no topo e serão usados:

- `createUser($name, $email, $role = 'participant')`;
- `params([...])`: Evento válido com sobrescritas;
- `createEvent([...])`: `new Event(params)` + `assertTrue(save())`;
- `enroll($event, N)`: cria N Participantes e Inscrições;
- `createPastEvent()` (linha ~499): Evento salvo e movido para -10 dias.

a) Cabeçalho da seção e teste "não salva":

```php
    /* ------------------- duplicate ------------------- */

    public function test_duplicate_should_return_a_new_unsaved_event(): void
    {
        $event = $this->createEvent();

        $copy = $event->duplicate();

        $this->assertTrue($copy->newRecord());
        $this->assertCount(1, Event::all());
    }
```

Por quê: `duplicate()` não grava. `newRecord()` = sem `id`; `all()` com
1 = só o original no banco. Contrato do método documentado em teste.

b) Título:

```php
    public function test_duplicate_should_append_copy_to_the_title(): void
    {
        $copy = $this->createEvent(['title' => 'Oficina de Git'])->duplicate();

        $this->assertEquals('Oficina de Git (cópia)', $copy->title);
    }

    public function test_duplicate_should_keep_the_title_within_255_characters(): void
    {
        $copy = $this->createEvent(['title' => str_repeat('a', 255)])->duplicate();

        $this->assertEquals(255, mb_strlen($copy->title));
        $this->assertStringEndsWith(' (cópia)', $copy->title);
        $this->assertTrue($copy->save());
    }
```

Por quê:

- Caso comum + caso de borda (título no limite da coluna).
- `mb_strlen`, não `strlen`: "ó" tem 2 bytes.
- `save()` no fim: prova que a validação de tamanho do model aceita.

c) Data:

```php
    public function test_duplicate_should_move_starts_on_to_seven_days_from_today(): void
    {
        $copy = $this->createEvent(['starts_on' => date('Y-m-d', strtotime('+60 days'))])->duplicate();

        $this->assertEquals(date('Y-m-d', strtotime('+7 days')), $copy->starts_on);
    }

    public function test_duplicate_of_a_past_event_should_be_valid(): void
    {
        $copy = $this->createPastEvent()->duplicate();

        $this->assertTrue($copy->isValid());
    }
```

Por quê:

- Original em +60 dias: se a cópia mantivesse a data, o teste falharia
  (com +7 no original passaria por acaso).
- Datas relativas (`strtotime`), nunca fixas: teste não apodrece.
- Evento passado: cópia nasce em data futura e passa no "não passada"
  do model.

d) Demais colunas e dono:

```php
    public function test_duplicate_should_keep_the_other_columns_and_the_user(): void
    {
        $other = $this->createUser('Outro Admin', 'other@example.com', 'admin');
        $event = $this->createEvent([
            'user_id' => $other->id,
            'kind' => 'workshop',
            'modality' => 'hybrid',
            'audience' => 'staff,community',
            'location' => 'Laboratório B203',
            'capacity' => 25,
        ]);

        $copy = $event->duplicate();

        $this->assertEquals($other->id, $copy->user_id);
        $this->assertEquals('workshop', $copy->kind);
        $this->assertEquals('hybrid', $copy->modality);
        $this->assertEquals(['staff', 'community'], $copy->audienceList());
        $this->assertEquals($event->description, $copy->description);
        $this->assertEquals('Laboratório B203', $copy->location);
        $this->assertEquals(25, $copy->capacity);
    }
```

Por quê:

- **Outro** admin como dono: com o admin do `setUp` o teste passaria
  mesmo se `duplicate()` pusesse o usuário logado.
- Valores diferentes do padrão de `params()`: prova cópia real, não
  coincidência com a fábrica.
- `audienceList()`: confere o SET pelo método do model, como o resto do
  arquivo.

e) Persistência sem Inscrições:

```php
    public function test_duplicate_should_be_persisted_without_enrollments(): void
    {
        $event = $this->createEvent(['capacity' => 5]);
        $this->enroll($event, 3);

        $copy = $event->duplicate();
        $this->assertTrue($copy->save());

        $found = Event::findById($copy->id);
        $this->assertNotNull($found);
        $this->assertNotEquals($event->id, $found->id);
        $this->assertEquals("{$event->title} (cópia)", $found->title);
        $this->assertSame(0, $found->enrollmentsCount());
        $this->assertSame(3, $event->enrollmentsCount());
        $this->assertCount(2, Event::all());
    }
```

Por quê:

- "Persistido" pede `save()` + `findById` (lê do banco); `isValid()`
  sozinho não prova gravação.
- Confere os dois lados (cópia 0, original 3): Inscrições nem movidas
  nem copiadas.
- `assertSame(0, ...)`: tipo e valor (inteiro).

f) Original intacto:

```php
    public function test_duplicate_should_not_change_the_original(): void
    {
        $event = $this->createEvent(['title' => 'Original']);

        $event->duplicate()->save();

        $original = Event::findById($event->id);
        $this->assertEquals('Original', $original->title);
        $this->assertEquals($event->starts_on, $original->starts_on);
    }
```

Por quê: pega o erro clássico de alterar `$this` em vez de `$copy` (ou
salvar em cima do original).

Regras gerais (valem para qualquer método novo):

- Nome `test_<metodo>_should_<efeito>`, inglês, `snake_case`; sem
  `test_` o PHPUnit não roda.
- Arranje (`createEvent`), aja (chame o método), confira (uma asserção
  principal; mais só se forem do mesmo efeito).
- Cada teste recria o schema: não há dados do populate; crie tudo.
- Mensagens de validação: `errorsOf($event)` ou `errors('campo')`.

Armadilhas:

- Se o seu `duplicate()` salva a cópia, troque o 1º teste por
  `assertFalse($copy->newRecord())` e tire os `save()` dos outros.
- Com P1: `assertNotEquals($event->checkin_code, $copy->checkin_code)`
  depois do `save()`; com P5: `assertEquals('scheduled', $copy->status)`.

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php --filter duplicate
./run test tests/Unit
./run test                  # suíte toda
./run phpcs
./run phpstan
```

Sem mudança de schema: não precisa `./run db:populate`. Esperado no
`--filter duplicate`: 8 testes, 37 asserções.

No navegador: nada novo (só teste). Opcional: duplicar um Evento na
listagem e ver "(cópia)" com data de hoje + 7 dias.

Commit (inglês): `test: Cover Event::duplicate() with unit tests`.

## Armadilhas (resumo)

- Só `isValid()` não prova gravação (M3): `save()` + `findById`.
- Datas `+N days` relativas, nunca fixas (`'2026-12-01'` apodrece).
- Schema recriado por teste: sem dados do populate.
- Nome em inglês, `snake_case`, prefixo `test_`.
- Admin do `setUp` para checar `user_id` não distingue dono original de
  usuário logado: use outro.
- `strlen` em vez de `mb_strlen`: "ó" conta 2.
