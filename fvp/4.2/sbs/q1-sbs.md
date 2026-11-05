# Q1 passo a passo: só o dono edita e remove

Pedido típico: "só o Administrador que criou o Evento pode editar ou
remover; os outros podem ver, mas não alterar. Esconda os botões e
barre também a URL" (D8 da 4.1 aplicado a Eventos). Chance:
média-alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/q1-so-dono-edita-remove/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: parte da `main`.
Schema não muda.

Ordem = fluxo da requisição: model -> controller -> views -> dados ->
testes. Na prova, prioridade: funcionar no navegador, depois testar,
depois `phpcs`.

## Arquivos, em ordem

1. `app/Models/Event.php`
2. `app/Controllers/Admin/EventsController.php`
3. `app/views/admin/events/index.phtml`
4. `app/views/admin/events/show.phtml`
5. `database/Populate/populate.php`
6. `tests/Unit/Models/Events/EventTest.php`
7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
8. `tests/Acceptance/admin/events/OwnerOnlyCest.php` (novo)

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `app/Models/Event.php`

Depois do método `user(): BelongsTo` (linha ~117-120) e antes de
`enrollmentsCount()`:

```php
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * O Evento pertence ao usuário (foi criado por ele)? Usado para mostrar Editar/Remover só ao dono.
     */
    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }
```

Por quê:

- Regra num lugar só: usada pelas duas views e testável sem HTTP.
- `?User`: `currentUser()` pode ser `null`; sem usuário -> `false`.
- `(int)` nos dois lados: do banco vem `int`, mas pode vir string; sem
  normalizar, `===` dá falso negativo.
- Não precisa de `use`: `User` já é usado no `user()`.

## 2. `app/Controllers/Admin/EventsController.php`

Dois pontos.

a) `findEvent` (linha ~132-135), busca pelos Eventos do admin logado:

```php
    /**
     * Busca o Evento entre os criados pelo Administrador logado: Evento de outro Administrador
     * vira null e cai em eventNotFound (edit, update e destroy).
     */
    private function findEvent(Request $request): ?Event
    {
        $event = $this->currentUser()?->events()->findById((int) $request->getParam('id'));

        return $event instanceof Event ? $event : null;
    }
```

b) `show` (linha ~31): deixa de usar `findEvent`:

```php
    public function show(Request $request): void
    {
        // Ver continua liberado para qualquer Administrador; só editar e remover exigem ser o dono.
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
```

Por quê:

- Um ponto (`findEvent`) protege as três ações (`edit`, `update`,
  `destroy`). Evento alheio vira `null` -> `eventNotFound()` (flash
  "Evento não encontrado!", `Location: /admin/events`), fluxo que já
  existia.
- `User::events()` é `HasMany`; `HasMany::findById` filtra por
  `user_id` no SQL do framework. Controller não compara ids à mão.
- `instanceof Event`: `HasMany::findById` devolve `?Model`, `findEvent`
  declara `?Event`; sem a checagem o `phpstan` (nível 6) acusa.
- Mesma mensagem de "não encontrado": não revela que o Evento existe.
  Mensagem própria, se pedirem: `Event::findById` + `isOwnedBy` +
  `FlashMessage::danger('Você não pode alterar este Evento!')`.
- `show` amplo: o pedido restringe alterar, não ver. Se "ver" também
  for só do dono, volte o `show` para `$this->findEvent($request)`.
  Pergunte ao professor.

## 3. `app/views/admin/events/index.phtml`

Coluna de ações (linhas ~28-33): "Ver" fica; envolva "Editar" e o form
de "Remover" no `if`:

```php
                        <a class="btn btn-sm btn-outline-secondary" href="<?= route('admin.events.show', ['id' => $event->id]) ?>">Ver</a>
                        <?php if ($event->isOwnedBy($this->currentUser())) : ?>
                            <a class="btn btn-sm btn-outline-primary"
                                href="<?= route('admin.events.edit', ['id' => $event->id]) ?>">Editar</a>
                            <form class="d-inline"
                                action="<?= route('admin.events.destroy', ['id' => $event->id]) ?>" method="post">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remover</button>
                            </form>
                        <?php endif; ?>
                    </td>
```

Por quê:

- View só pergunta ao model (`isOwnedBy`); sem comparar ids na view.
- `$this->currentUser()` está disponível na view (render do
  controller).
- Esconder o botão é só conforto; a proteção real é o `findEvent`.
- Quebra de linha nos atributos: só legibilidade (o `phpcs` confere só
  `.php`, limite 130; `.phtml` fica de fora).
- Versão rápida do catálogo, sem método novo:
  `<?php if ((int) $event->user_id === (int) $this->currentUser()?->id) : ?>`.

## 4. `app/views/admin/events/show.phtml`

Botão "Editar", depois do `</dl>`:

```php
<?php if ($event->isOwnedBy($this->currentUser())) : ?>
    <a class="btn btn-primary" href="<?= route('admin.events.edit', ['id' => $event->id]) ?>">Editar</a>
<?php endif; ?>
<a class="btn btn-secondary" href="<?= route('admin.events.index') ?>">Voltar</a>
```

Por quê: o `show` agora abre para todo admin; o link de edição só
aparece ao dono. "Voltar" fica para todos.

## 5. `database/Populate/populate.php`

a) Depois do `echo "Usuários criados: ..."` (linha ~29):

```php
// Segundo Administrador, dono dos dois últimos Eventos. Logado como admin@, esses dois aparecem
// sem Editar/Remover; logado como admin2@, só eles têm os botões.
$otherAdmin = new User([
    'name' => 'Outra Administradora',
    'email' => 'admin2@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'role' => 'admin'
]);
$otherAdmin->save();

echo "Segundo Administrador criado: {$otherAdmin->email} (admin), senha 123456\n";
```

b) No `foreach` dos Eventos (linha ~133), pegue o índice e escolha o
dono:

```php
foreach ($events as $index => [$title, $description, $kind, $modality, $audience, $daysAhead, $location, $capacity]) {
    $owner = $index >= count($events) - 2 ? $otherAdmin : $admin;
    $event = new Event([
        'user_id' => $owner->id,
```

c) `echo` depois do `foreach`:

```php
echo count($eventModels) . " Eventos criados (os dois últimos pela segunda Administradora)\n";
```

Por quê:

- O populate tinha um admin só: no navegador ninguém via a diferença.
- Os dois últimos (15, fim da página 3; 16, página 4) são da
  `admin2@example.com`. Inscrições e Evento 1 lotado não mudam.
- `$index =>` antes da destruturação: não mexa nas linhas posicionais
  do array de dados.
- Alternativa sem mexer no populate: promover no banco
  (`UPDATE users SET role = 'admin' WHERE email = 'user@example.com'`).

## 6. `tests/Unit/Models/Events/EventTest.php`

No fim da classe:

```php
    public function test_is_owned_by_should_be_true_only_for_the_creator(): void
    {
        $event = $this->createEvent();
        $other = $this->createUser('Outro Administrador', 'outro@example.com', 'admin');

        $this->assertTrue($event->isOwnedBy($this->admin));
        $this->assertFalse($event->isOwnedBy($other));
        $this->assertFalse($event->isOwnedBy(null));
    }

    public function test_is_owned_by_should_work_for_event_loaded_from_the_database(): void
    {
        $event = Event::findById($this->createEvent()->id);

        $this->assertNotNull($event);
        $this->assertTrue($event->isOwnedBy(User::findById($this->admin->id)));
    }
```

Por quê: dono, outro e `null`; e com os dois lidos do banco (tipos
vindos do PDO), que é como o controller usa.

## 7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois do último teste de `destroy` e antes de `validParams()`. `User`
e `Auth` já estão importados.

```php
    public function test_show_of_another_admins_event_is_allowed_without_edit_button(): void
    {
        $event = $this->createEvent(['title' => 'Evento do Primeiro']);
        Auth::login($this->otherAdmin());

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('Evento do Primeiro', $response);
        $this->assertStringNotContainsString("/admin/events/{$event->id}/edit", $response);
    }

    public function test_index_hides_edit_and_remove_of_another_admins_event(): void
    {
        $mine = $this->createEvent(['title' => 'Evento do Primeiro']);
        $other = $this->otherAdmin();
        $theirs = $this->createEvent(['title' => 'Evento do Outro', 'user_id' => $other->id]);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('Evento do Outro', $response);
        $this->assertStringContainsString("/admin/events/{$mine->id}/edit", $response);
        $this->assertStringNotContainsString("/admin/events/{$theirs->id}/edit", $response);
        $this->assertStringContainsString("action=\"/admin/events/{$mine->id}\"", $response);
        $this->assertStringNotContainsString("action=\"/admin/events/{$theirs->id}\"", $response);
    }

    public function test_edit_of_another_admins_event_redirects_to_index(): void
    {
        $event = $this->createEvent();
        Auth::login($this->otherAdmin());

        $response = $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_update_of_another_admins_event_keeps_it_intact(): void
    {
        $event = $this->createEvent(['title' => 'Original']);
        Auth::login($this->otherAdmin());

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Invadido'])]
        );

        $intact = Event::findById($event->id);
        $this->assertEquals('Original', $intact->title);
        $this->assertEquals($this->admin->id, $intact->user_id);
        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_destroy_of_another_admins_event_keeps_it(): void
    {
        $event = $this->createEvent();
        Auth::login($this->otherAdmin());

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
        $this->assertArrayNotHasKey('success', $_SESSION['flash']);
    }

    private function otherAdmin(): User
    {
        $other = new User([
            'name' => 'Outro Admin',
            'email' => 'outro.admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $other->save();

        return $other;
    }
```

Por quê:

- `createEvent()` usa o admin do `setUp`; `Auth::login($outro)` troca
  a sessão para o segundo admin (mesmo molde do login em `ECT:17-31`).
- Testa a URL direta das três ações: esconder botão não protege.
- `assertSame('Location: /admin/events', ...)`: mesmo formato dos
  testes de "não encontrado" existentes.
- `update`: confere título **e** `user_id` intactos.

## 8. `tests/Acceptance/admin/events/OwnerOnlyCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Só o Administrador que criou o Evento edita e remove. Os Eventos da base são do
 * admin@example.com; aqui o login é trocado para um segundo Administrador.
 */
class OwnerOnlyCest extends BaseEventsCest
{
    private const OTHER_EMAIL = 'outro.admin@example.com';

    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        $this->createUser('Outro Administrador', self::OTHER_EMAIL, 'admin');
        $page->amOnPage('/logout');
        $page->login(self::OTHER_EMAIL, self::PASSWORD);
    }

    public function otherAdminSeesEventsWithoutEditAndRemove(AcceptanceTester $page): void
    {
        $page->wantTo('Listar Eventos de outro Administrador sem os botões Editar e Remover');
        $page->amOnPage('/admin/events');

        $page->see('Evento 02', $this->rowOf('Evento 02'));
        $page->see('Ver', $this->rowOf('Evento 02'));
        $page->dontSee('Editar', $this->rowOf('Evento 02'));
        $page->dontSee('Remover', $this->rowOf('Evento 02'));

        $page->click('Ver', $this->rowOf('Evento 02'));
        $page->seeCurrentUrlEquals("/admin/events/{$this->events[2]->id}");
        $page->see('Evento 02', 'h1');
        $page->dontSee('Editar', 'main');
    }

    public function otherAdminCannotOpenEditByUrl(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Abrir a edição de Evento alheio pela URL e ser barrado');
        $page->amOnPage("/admin/events/{$event->id}/edit");

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento não encontrado!');
        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertSame('Evento 02', $stored->title);
    }

    public function ownEventShowsEditAndRemove(AcceptanceTester $page): void
    {
        $page->wantTo('Ver Editar e Remover no Evento criado pelo próprio Administrador');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $this->validEventData(['title' => 'Evento do Outro']));
        $page->click('Salvar');
        $page->see('Evento cadastrado com sucesso!');

        $page->amOnPage('/admin/events/page/4');
        $page->see('Editar', $this->rowOf('Evento do Outro'));
        $page->see('Remover', $this->rowOf('Evento do Outro'));
    }
}
```

Por quê:

- Herda `BaseEventsCest`: `_before` semeia os 16 Eventos do admin e
  loga; aqui troca o login para outro admin (`createUser`, `/logout`,
  `login`).
- Três casos: botões escondidos, URL direta barrada, Evento próprio
  com botões (página 4: o 17º Evento).
- Classe nova em `tests/`, não em `app/`: sem `dump-autoload`.

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

`EventsAccessTest` (sem sessão e Participante) segue passando sem
mudança.

No navegador: entrar como `admin@example.com`, abrir
`/admin/events/page/3` e `/admin/events/page/4`: Eventos 15 e 16 só têm
"Ver". Abrir `/admin/events/16/edit` direto -> volta à listagem com
"Evento não encontrado!". Entrar como `admin2@example.com` (senha
`123456`): só esses dois têm "Editar" e "Remover".

Commit (inglês): `feat: Restrict event edit and removal to its owner`.

## Armadilhas (resumo)

- Sem `instanceof`, `phpstan` nível 6 acusa (`findById` devolve
  `?Model`, `findEvent` declara `?Event`).
- Populate com um admin só: não dá para ver no navegador. Crie outro
  (populate ou G1 da 4.1) ou promova `user@example.com` no banco.
- Comparar `user_id`: normalize os dois lados (`(int)` ou `(string)`)
  antes do `===`.
- Esconder o botão não protege: teste a URL direta.
- Não confiar em `user_id` do form (`ECT:101-112` já cobre).
- `show` usa `findEvent`: decidir se "ver" é amplo ou só do dono.
