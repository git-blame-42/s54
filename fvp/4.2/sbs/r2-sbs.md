# R2 passo a passo: nome do criador no `show` e na listagem

Pedido típico: "mostre quem cadastrou o Evento, nos detalhes e na
listagem; use a relação com o usuário" (`BelongsTo`, que a rubrica da
4.3 cobra). Chance: média-alta. Tempo: 10-15 min.

Exemplo pronto: `fvp/4.2/exemplos/r2-nome-do-criador/` (patch, bundle
e árvore `projeto/`). Sem pré-requisito: parte da `main`. Schema,
rotas, controller e populate não mudam.

Ordem = fluxo da requisição: model -> views -> testes. Na prova,
prioridade: funcionar no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `app/Models/Event.php`
2. `app/views/admin/events/show.phtml`
3. `app/views/admin/events/index.phtml`
4. `tests/Unit/Models/Events/EventTest.php`
5. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
6. `tests/Acceptance/admin/events/ListEventsCest.php`

Depois: suíte, `./run phpcs`.

## 1. `app/Models/Event.php`

Depois do método `user(): BelongsTo` (linhas ~117-120) e antes de
`enrollmentsCount()`:

```php
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Nome de quem cadastrou o Evento (relação BelongsTo com users).
     * A FK fk_events_user garante que o usuário existe.
     */
    public function creatorName(): string
    {
        $creator = $this->user()->get();

        return $creator instanceof User ? $creator->name : '';
    }
```

Por quê:

- A relação já existe (`user()` -> `BelongsTo`). Só usa: sem SQL novo,
  sem `JOIN` à mão.
- `BelongsTo::get()` devolve `Model`, não `User`
  (`core/Database/ActiveRecord/BelongsTo.php:14`). Em `app/` o
  `phpstan` exige `instanceof User` antes de `->name`.
- Método no model: regra num só lugar; as duas views só chamam
  `creatorName()`.
- `''` só satisfaz o tipo: `user_id NOT NULL` + FK (`SCH:35-36`)
  garantem o usuário.

## 2. `app/views/admin/events/show.phtml`

Dentro do `<dl>`, depois do `</dd>` de "Vagas" e antes de `</dl>`:

```php
        (<?= $event->availableSpots() ?> disponíveis)
    </dd>

    <dt class="col-sm-3">Criado por</dt>
    <dd class="col-sm-9"><?= htmlspecialchars($event->creatorName()) ?></dd>
</dl>
```

Forma direta, se o professor pedir exatamente ela (na view `Model`
basta, o `__get` resolve `name`):

```php
<dd class="col-sm-9">
    <?= htmlspecialchars($event->user()->get()->name) ?>
</dd>
```

Por quê:

- View burra: chama o model e escapa. Controller não muda: o `show`
  já passa `$event`.
- `htmlspecialchars`: o nome vem do cadastro de usuário (XSS).
- Par `dt`/`dd` segue o padrão dos outros campos.

## 3. `app/views/admin/events/index.phtml`

a) `<thead>`, depois de `<th>Vagas</th>`:

```php
                <th>Vagas</th>
                <th>Criado por</th>
                <th>Ações</th>
```

b) `<tbody>`, depois do `<td>` de vagas e **antes** do `<td>` de
ações:

```php
                    <td><?= $event->enrollmentsCount() ?>/<?= (int) $event->capacity ?></td>
                    <td><?= htmlspecialchars($event->creatorName()) ?></td>
                    <td>
```

Por quê:

- Ordem dos `<td>` tem que bater com a dos `<th>`.
- N+1: uma consulta por linha (5 por página), igual a
  `enrollmentsCount()`. Aceitável aqui; com muitas linhas, `JOIN` num
  método do model.
- Catálogo marca a coluna como opcional; o pedido do exemplo cobra.

## 4. `tests/Unit/Models/Events/EventTest.php`

Depois de `test_event_should_belong_to_its_creator` (bloco "relações",
linhas ~443-449):

```php
    public function test_creator_name_should_return_the_name_of_the_creator(): void
    {
        $other = $this->createUser('Outra Administradora', 'other@example.com', 'admin');
        $mine = $this->createEvent(['title' => 'Evento 1']);
        $theirs = $this->createEvent(['title' => 'Evento 2', 'user_id' => $other->id]);

        $this->assertSame('Administrador', $mine->creatorName());
        $this->assertSame('Outra Administradora', $theirs->creatorName());
    }
```

Por quê: dois criadores provam que o nome vem de `user_id` de cada
Evento, não do admin logado. `Administrador` é o nome do admin do
`setUp` deste teste.

## 5. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) Depois de `test_index_shows_date_and_enrollments_over_capacity`:

```php
    public function test_index_shows_creator_name(): void
    {
        $this->createEvent(['title' => 'Evento do Admin']);
        $other = $this->createAdmin('Outra Administradora', 'outra.admin@example.com');
        $this->createEvent(['title' => 'Evento da Outra', 'user_id' => $other->id]);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('<th>Criado por</th>', $response);
        $this->assertMatchesRegularExpression('/Evento do Admin.*?<td>Admin<\/td>/s', $response);
        $this->assertMatchesRegularExpression('/Evento da Outra.*?<td>Outra Administradora<\/td>/s', $response);
    }

    public function test_show_renders_creator_name(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertMatchesRegularExpression('/<dt[^>]*>Criado por<\/dt>\s*<dd[^>]*>Admin<\/dd>/', $response);
    }

    public function test_show_escapes_creator_name(): void
    {
        $creator = $this->createAdmin('Ana <b>Admin</b>', 'ana@example.com');
        $event = $this->createEvent(['user_id' => $creator->id]);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('Ana &lt;b&gt;Admin&lt;/b&gt;', $response);
        $this->assertStringNotContainsString('<b>Admin</b>', $response);
    }
```

b) Helper, depois de `createEvent()` e antes de `enroll()`:

```php
    private function createAdmin(string $name, string $email): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $user->save();

        return $user;
    }
```

Por quê:

- `Admin` é o nome do admin do `setUp` deste teste (`ECT:23`).
- Regex com `.*?` e `/s`: casa o nome na mesma linha da tabela do
  título certo.
- Teste de escape: prova o `htmlspecialchars` (rubrica cobra XSS).
- `User` já está importado.

## 6. `tests/Acceptance/admin/events/ListEventsCest.php`

a) `listAllEvents`, depois de
`$page->seeNumberOfElements('table tbody tr', self::PER_PAGE);`:

```php
        $page->see('Criado por', 'table thead');
```

b) No laço das linhas, depois de
`$this->assertContains($event->location, $row);`:

```php
            $this->assertContains($this->admin->name, $row);
```

c) `listEventsHasLinksToShowAndEdit`, depois de
`$page->see($event->description);`:

```php
        $page->see('Criado por');
        $page->see($this->admin->name);
```

Por quê: confere no navegador real o cabeçalho, o nome em cada linha e
no `show`. `$this->admin` vem do `BaseEventsCest` (dono dos 16
Eventos semeados).

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser    # ListEventsCest (aceitação)
docker compose stop selenium
```

Sem `./run db:populate`: schema não mudou.

No navegador (populate): `/admin/events/1` mostra "Criado por
Administrador"; listagem tem a coluna com o mesmo nome em todas as
linhas (populate tem um só admin).

Commit (inglês): `feat: Show event creator name in listing and show`.

## Armadilhas (resumo)

- `get()` devolve `Model`, não `User`: na view tudo bem; em `app/` use
  `instanceof User` ou `creatorName()` no model (melhor).
- `__get` mágico permite `$event->user->name` (`MODEL:55-68`); prefira
  a forma explícita `user()->get()`.
- `user_id` sem usuário é impossível (FK `SCH:35-36`); não precisa
  tratar.
- Esquecer `htmlspecialchars` no nome.
- `<td>` fora de ordem em relação ao `<th>`.
