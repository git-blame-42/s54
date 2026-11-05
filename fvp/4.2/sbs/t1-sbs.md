# T1 passo a passo: Populate: campo novo, mais Eventos, segundo admin

Pedido típico: "atualize o populate", "crie 30 Eventos para ver a
paginação", "um segundo Administrador". Chance: alta (sempre junto de
M1, M3, P1, P5). Tempo: 5-10 min.

Exemplo pronto: `fvp/4.2/exemplos/t1-populate-campo-eventos-admin/`
(patch, bundle e árvore `projeto/`). Pré-requisito: nenhum, parte de
`main`. O patch faz segundo admin + Eventos 17 a 30 + teste; o campo
novo (com M1) está no passo 1d.

Ordem = fluxo dos dados: populate (usuários -> Eventos -> mensagens)
-> teste. Na prova, prioridade: `./run db:populate` sem erro, depois
teste, depois `phpcs`.

## Arquivos, em ordem

1. `database/Populate/populate.php`
2. `tests/Integration/Database/PopulateTest.php` (novo)

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/Populate/populate.php`

a) Segundo admin: logo depois de `$admin->save();` (linha ~19), antes
de `$user = new User([`:

```php
$admin->save();

// Segundo Administrador: dono dos Eventos 17 a 30 (permite testar "só quem criou edita e remove").
$admin2 = new User([
    'name' => 'Administradora Dois',
    'email' => 'admin2@example.com',
    'password' => '123456',
    'password_confirmation' => '123456',
    'role' => 'admin'
]);
$admin2->save();
```

Por quê:

- Cópia do bloco do `$admin`. `User` valida senha e confirmação no
  model; `role => 'admin'` dá acesso a `/admin/events` (middleware).
- Dono dos Eventos novos: deixa pronto Q1 ("só quem criou edita") e R2
  ("Criado por").
- `email` é único: dois `admin2@` abortam o `save()`.

b) Mensagem dos usuários (linha ~29), troque o `echo`:

```php
echo "Usuários criados: {$admin->email} e {$admin2->email} (admin), {$user->email} (participant), senha 123456\n";
```

Por quê: a saída do `db:populate` é o único feedback; o teste confere
esse texto.

c) Eventos 17 a 30: depois de
`echo count($eventModels) . " Eventos criados pelo Administrador\n";`
(linha ~151), antes do comentário `// Inscrições (event_user): ...`:

```php
// Eventos 17 a 30, do segundo Administrador: mais páginas na listagem (6 páginas de 5).
// Datas depois do último Evento fixo (+82 dias), então a ordem de id continua igual à de data.
// Ficam depois dos 16 fixos em $eventModels: os índices das Inscrições abaixo não mudam.
$kinds = array_keys(Event::KINDS);
$modalities = array_keys(Event::MODALITIES);
for ($number = 17; $number <= 30; $number++) {
    $title = sprintf('Evento %02d', $number);
    $event = new Event([
        'user_id' => $admin2->id,
        'title' => $title,
        'description' => "Evento gerado pelo populate para testar a paginação ({$title}).",
        'kind' => $kinds[$number % count($kinds)],
        'modality' => $modalities[$number % count($modalities)],
        'starts_on' => date('Y-m-d', strtotime('+' . (80 + $number) . ' days')),
        'location' => 'Bloco A, Sala A' . (100 + $number),
        'capacity' => 10 + $number,
    ]);
    $event->setAudienceList($number % 2 === 0 ? ['students'] : ['students', 'community']);
    if (!$event->save()) {
        throw new RuntimeException("Evento inválido no populate: {$title}");
    }
    $eventModels[] = $event;
}

echo (count($eventModels) - count($events)) . " Eventos criados pela Administradora Dois\n";
```

Por quê:

- **Depois** dos 16 fixos: as Inscrições usam índices de
  `$eventModels` (`0 => [0, 1, 2]`, ...). Inserir no meio muda a qual
  Evento cada Inscrição aponta (e o lotado deixa de ser o primeiro).
- Datas `+97` a `+110`, depois do último fixo (`+82`): ordem por `id`
  = ordem por data (contrato da listagem) e nada no passado (validação
  barraria o `save()`).
- `array_keys(Event::KINDS)`/`MODALITIES`: valor novo no ENUM (M3)
  entra sem mexer no populate.
- `setAudienceList(...)`: `audience` é `SET`, o model monta a string;
  mesmo jeito do laço fixo.
- `save()` + `throw`: mesmo padrão do laço fixo (~145-147). Evento
  inválido aborta com o título na mensagem, em vez de sumir calado.
- `sprintf('Evento %02d', ...)`: títulos distintos e ordenáveis.

d) Campo novo (só quando vier junto de M1; o patch não tem, a base não
tem coluna nova). No `new Event([...])` dos **dois** laços (fixo
~134-143 e o do item c), depois de `'capacity' => ...,`:

```php
        'workload_hours' => 4,
```

Por quê:

- `Model::save()` grava todas as chaves de `$columns`; campo esquecido
  vai `NULL` e quebra `NOT NULL` (ou a validação aborta o populate).
- Valor fixo serve para todos. Para variar por Evento: 9ª posição em
  **todas** as linhas de `$events` (~49-130) e no
  `foreach ($events as [..., $capacity, $hours])`. O `foreach`
  destrutura por posição.

Armadilha: `db:populate` apaga tudo (`migrate()` faz `DROP`). Não
existe "acrescentar só os novos".

## 2. `tests/Integration/Database/PopulateTest.php` (novo)

Pasta `tests/Integration/Database/` nova. Arquivo inteiro:

```php
<?php

namespace Tests\Integration\Database;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Core\Constants\Constants;
use Tests\TestCase;

/**
 * Roda o populate de verdade (outro processo PHP, no banco de teste: o phpunit.xml define
 * DB_DATABASE=eventos_test com putenv, e o processo filho herda) e confere o que ele criou.
 * Pega Evento inválido no populate, que só apareceria no `./run db:populate`.
 */
class PopulateTest extends TestCase
{
    /** @var array<int, string> */
    private array $output = [];

    public function setUp(): void
    {
        parent::setUp();

        $script = Constants::rootPath()->join('database/Populate/populate.php');
        exec(PHP_BINARY . ' ' . escapeshellarg($script) . ' 2>&1', $this->output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $this->output));
    }

    public function test_populate_creates_two_admins_and_four_participants(): void
    {
        $admin = User::findByEmail('admin@example.com');
        $admin2 = User::findByEmail('admin2@example.com');

        $this->assertNotNull($admin);
        $this->assertNotNull($admin2);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin2->isAdmin());
        $this->assertTrue($admin2->authenticate('123456'));
        $this->assertCount(6, User::all());
        $this->assertCount(2, User::where(['role' => 'admin']));
    }

    public function test_populate_creates_thirty_events_split_between_the_admins(): void
    {
        $admin = User::findByEmail('admin@example.com');
        $admin2 = User::findByEmail('admin2@example.com');
        $this->assertNotNull($admin);
        $this->assertNotNull($admin2);

        $this->assertCount(30, Event::all());
        $this->assertCount(16, Event::where(['user_id' => $admin->id]));
        $this->assertCount(14, Event::where(['user_id' => $admin2->id]));
        $this->assertNotNull(Event::findBy(['title' => 'Evento 17']));
        $this->assertNotNull(Event::findBy(['title' => 'Evento 30']));
        $this->assertSame(6, Event::paginate(1, 5)->totalOfPages());
    }

    public function test_new_events_come_after_the_fixed_ones_and_are_in_the_future(): void
    {
        $events = Event::all();
        $previous = '';
        foreach ($events as $event) {
            $this->assertGreaterThanOrEqual($previous, $event->starts_on);
            $this->assertGreaterThan(date('Y-m-d'), $event->starts_on);
            $previous = $event->starts_on;
        }
    }

    public function test_populate_keeps_the_enrollments_and_the_full_event(): void
    {
        $this->assertCount(14, Enrollment::all());

        $first = Event::all()[0];
        $this->assertSame(0, $first->availableSpots());
        $this->assertSame(3, $first->enrollmentsCount());
    }

    public function test_populate_reports_what_it_created(): void
    {
        $text = implode("\n", $this->output);

        $this->assertStringContainsString('admin@example.com e admin2@example.com (admin)', $text);
        $this->assertStringContainsString('16 Eventos criados pelo Administrador', $text);
        $this->assertStringContainsString('14 Eventos criados pela Administradora Dois', $text);
        $this->assertStringContainsString('14 Inscrições criadas em 6 Eventos', $text);
    }
}
```

Por quê:

- Pega erro de populate antes de alguém rodar `./run db:populate`
  (ex.: campo novo esquecido no `new Event`).
- `exec` em processo separado: o populate é script solto (`require`
  do bootstrap + `migrate()`), não classe. O filho herda
  `DB_DATABASE=eventos_test` do `phpunit.xml` (`putenv`): não toca no
  banco de dev.
- `assertSame(0, $exitCode, ...)`: se o populate lançar, a mensagem
  dele aparece na falha.
- Namespace = pasta (PSR-4 `Tests\` = `tests/`); `@var array<int,
  string>` para o `phpstan`.
- Confere o contrato: 2 admins, 30 Eventos (16 + 14), 6 páginas de 5,
  datas crescentes e futuras, 14 Inscrições, Evento 1 lotado.

## Fechamento

```sh
./run db:populate
./run test tests/Integration/Database/PopulateTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

Saída esperada do `db:populate` (trecho):

```sh
Usuários criados: admin@example.com e admin2@example.com (admin), user@example.com (participant), senha 123456
16 Eventos criados pelo Administrador
14 Eventos criados pela Administradora Dois
14 Inscrições criadas em 6 Eventos
```

Esperado: Unit 159, Integration 50 (base 159 / 45). `database/` fica
fora do `phpcs`. Schema não mudou. Nenhum Cest muda: criam os próprios
dados no `_before`.

No navegador: login `admin2@example.com`/`123456` entra; listagem com 6
páginas; última mostra "Mostrando 26 - 30 de 30".

Commit (inglês): `chore: Add second admin and more events to the populate`.

## Armadilhas (resumo)

- `db:populate` apaga tudo (`DROP`): não há "adicionar só os novos".
- Evento inválido aborta com `RuntimeException` (~145-147): leia a
  mensagem; geralmente campo novo faltando.
- Inscrições usam índices do array de Eventos: não reordene nem
  insira no meio.
- Data `+14` em diante; nunca passado (validação barra).
- Campo novo esquecido em um dos laços: `NULL` no `INSERT`.
- Dois `admin2@` (outro cenário também criou): `email` único, aborta.
- `123456` é senha de demonstração; não invente outra nem cite além do
  que já está.
- O populate não tem caso de borda (nenhum Evento passado, nenhum com
  1 vaga). Para Q2/N5: salve com data futura e depois
  `update(['starts_on' => ...])`, que não valida; para R13, um Evento
  com `capacity` = inscritos + 1.
