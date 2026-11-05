# FVP 4.2: possíveis modificações no CRUD de Eventos

Catálogo das alterações que o professor pode pedir na validação
prática (FVP) sobre o código da Entrega 4.2 (CRUD de Eventos). Para
cada cenário: pedido típico, chance, tempo, arquivos com linha, passos,
armadilhas e prova. Complementa o catálogo da 4.1 (`fvp/4.1/`), cujos
cenários de login, middleware e perfil continuam valendo.

Base das linhas: `main` do repositório público em `f31006b`
(`git show f31006b:<arquivo>` ou `grep -n` no clone). Se a `main`
avançar, refaça as linhas com `grep -n` antes de confiar nelas.

Lição da FVP da 4.1: o pedido real foi uma **feature nova pequena**
("login por código": o backend gera um número aleatório de 1000 a
9999 e o login só vale depois de validar esse código), não um
micro-ajuste. Rota + controller + model + view + teste em 15 a 40
minutos. A seção P deste catálogo é dedicada a pedidos desse tipo;
P12 descreve o pedido real da 4.1.

Siglas dos arquivos (todos relativos à raiz do projeto):

| Sigla | Arquivo |
|---|---|
| `ROUTES` | `config/routes.php` |
| `EC` | `app/Controllers/Admin/EventsController.php` |
| `EV` | `app/Models/Event.php` |
| `EN` | `app/Models/Enrollment.php` |
| `US` | `app/Models/User.php` |
| `VAL` | `lib/Validations.php` |
| `PAG` | `lib/Paginator.php` |
| `FLASH` | `lib/FlashMessage.php` |
| `MODEL` | `core/Database/ActiveRecord/Model.php` |
| `HASMANY` | `core/Database/ActiveRecord/HasMany.php` |
| `CTRL` | `core/Http/Controllers/Controller.php` |
| `SCH` | `database/schema.sql` |
| `POP` | `database/Populate/populate.php` |
| `FORM` | `app/views/admin/events/_form.phtml` |
| `IDX` | `app/views/admin/events/index.phtml` |
| `SHOW` | `app/views/admin/events/show.phtml` |
| `PAGES` | `app/views/paginator/_pages.phtml` |
| `NAV` | `app/views/layouts/_navbar.phtml` |
| `LAYOUT` | `app/views/layouts/application.phtml` |
| `ECT` | `tests/Integration/Controllers/Admin/EventsControllerTest.php` |
| `EVT` | `tests/Unit/Models/Events/EventTest.php` |
| `ACC` | `tests/Integration/Access/EventsAccessTest.php` |
| `ACT/` | `tests/Acceptance/admin/events/` |
| `CTC` | `tests/Integration/Controllers/ControllerTestCase.php` |

## 1. O que vale saber

- FVP: individual, no laboratório, sobre o código entregue. O fator
  (0.0 a 1.0) multiplica a nota da 4.2; 0.0 zera. Tempo esperado do
  pedido: 15 a 40 minutos. Detalhes em `fvp/4.1/catalogo.md`, seção 1.
- Funcionalidade funcionando no navegador vale mais que teste sem
  funcionalidade. Ordem: funcionar, testar, `phpcs`.
- Credenciais do populate: `admin@example.com` (admin) e
  `user@example.com`, `mariana@`, `carlos@`, `fernanda@example.com`
  (Participantes), senha `123456` (`POP:12-42`).
- Populate: 16 Eventos (4 páginas de 5), 14 Inscrições. O primeiro
  Evento está lotado (3/3). Eventos 1, 2, 3, 4, 6 e 9 têm Inscrições e
  não podem ser removidos (`POP:156-163`).
- Rubrica da 4.2: `save`, `all`, `findById`, `where`, `Validations`,
  `Paginator`, rotas RESTful (nomes, não só verbos), testes de
  aceitação dos 7 cenários, de acesso e unitários, bônus
  "Funcionalidades Extras no Framework".
- Issues de avaliação da 4.2 (03/11, grupos 3, 5 e 6): SQL só no
  model; `render` renderiza, `redirectTo` redireciona; controller
  enxuto com *early return*; nomes `index/new/create/edit/update/destroy`;
  validações de tamanho, formato e valores numéricos no model, sem
  conversão silenciosa (`(int)` que vira 0); autorização no servidor
  (`readonly` no form não protege); recurso pai deve pertencer ao
  usuário; manter valores digitados após erro; partial para os campos;
  CSS e JS em arquivos próprios; testes de borda; regra explícita para
  excluir pai com filhos.

## 2. Preparo da máquina (10 minutos)

```
git clone <repositorio publico> && cd <pasta>
cp .env.example .env
./run composer install
./run up -d --build
./run db:populate
./run codecept build
./run test tests/Unit
```

`--build` garante o fuso `America/Sao_Paulo` (`Dockerfile:10`); sem
ele os testes de data falham. `db:populate` roda `Database::migrate()`
(`POP:10`), que executa o `schema.sql` inteiro (`DROP` + `CREATE`):
sozinho ele já refaz o schema. Nos testes o schema é recriado a cada
teste (`tests/TestCase.php:10-14`).

Comandos: `./run test <caminho>`, `./run test:browser <caminho>`
(precisa do `selenium`, que o `up -d` sobe), `./run phpcs`
(`./run phpcbf` arruma), `./run phpstan`, `./run db:populate`.

## 3. Método de sempre

1. Repita o pedido como "quero mudar X" e ache o cenário:
   `./fvp.sh 4.2 <termo>` ou `./fvp.sh -s <ID>`.
2. Localize os arquivos (tabela do cenário) e abra o exemplo em
   `fvp/4.2/exemplos/<id>-*/` se existir.
3. Escreva ou ajuste o teste e veja falhar.
4. Mude na ordem schema -> model -> rota -> controller -> view.
5. `./run db:populate` se tocou no schema; teste; navegador; `phpcs`.
6. Commit em inglês (`feat: Add ...`) na branch indicada.

## 4. Resumo

| ID | Modificação | Chance | Tempo |
|---|---|---|---|
| M1 | Campo novo em `events` com validação | alta | 25-35 min |
| M2 | Validação nova em `lib/Validations.php` | alta | 15-20 min |
| M3 | Valor novo em ENUM/SET | média | 10-15 min |
| M4 | Segunda data (`ends_on`) não anterior a `starts_on` | média-alta | 25-35 min |
| M5 | Validação condicional por modalidade | média | 15-25 min |
| M6 | Unicidade (título único na mesma data) | média | 15-20 min |
| N1 | Filtro por tipo com `where`, mantido na paginação | média-alta | 25-35 min |
| N2 | Busca por título (`LIKE`) | média | 20-30 min |
| N3 | Ordenar por data no `Paginator` | média-alta | 15-25 min |
| N4 | Mudar `per_page` ou deixar o usuário escolher | alta | 5-10 min |
| N5 | Só Eventos futuros ou passados | média | 20-30 min |
| N6 | Dado calculado na tela (badge "Lotado", total) | média | 10-15 min |
| O1 | Rota RESTful nova com página (lotados) | média-alta | 20-30 min |
| O2 | Listagem e detalhe de Eventos para o Participante | média-alta | 25-35 min |
| O3 | Inscritos no `show` (`participants()`) | média | 20-30 min |
| O4 | Resposta JSON da listagem | baixa | 20 min |
| P1 | Código de check-in aleatório (1000-9999) por Evento | alta | 25-35 min |
| P2 | Inscrição do Participante (`POST /events/{id}/enroll`) | alta | 30-40 min |
| P3 | Cancelar inscrição (`DELETE /events/{id}/enroll`) | média | 15-20 min |
| P4 | Confirmar presença pelo código do Evento | média-alta | 30-40 min |
| P5 | Status do Evento: cancelar e encerrar | alta | 25-35 min |
| P6 | Duplicar Evento | média-alta | 20-30 min |
| P7 | "Meus eventos" do Participante | média-alta | 20-30 min |
| P8 | Exportar a listagem em CSV | média | 20-30 min |
| P9 | Lista de espera quando lotado | média-baixa | 35-40 min |
| P10 | Comentários do Evento (recurso filho 1xN) | média | 35-40 min |
| P11 | Soft delete (arquivar Evento) | baixa | 30-40 min |
| P12 | Código aleatório no login (a FVP real da 4.1) | média-baixa | 30-40 min |
| Q1 | Só o dono edita e remove | média-alta | 20-30 min |
| Q2 | Bloquear edição e remoção de Evento passado ou cancelado | média | 15-25 min |
| R1 | Flash: texto, título do Evento, tipo `warning` | média-alta | 10-15 min |
| R2 | Nome do criador no `show` e na listagem | média-alta | 10-15 min |
| R3 | Formatação na view com helper num só lugar | média | 10-15 min |
| R4 | Confirmação antes de remover, JS em arquivo próprio | média | 10-15 min |
| S1 | Teste de aceitação da modificação | alta | 20-30 min |
| S2 | Teste de acesso por HTTP da rota nova | alta | 15-20 min |
| S3 | Teste unitário do método novo do model | alta | 10-15 min |
| S4 | Testes de borda pedidos pelo professor | média-alta | 15-25 min |
| T1 | Populate: campo novo, mais Eventos, segundo admin | alta | 5-10 min |
| T2 | `phpcs`, `phpstan` e CI verdes | alta | 5 min |

Chance é estimativa a partir da rubrica, das issues de avaliação de
2026-2 (4.1 e 4.2), do pedido real da FVP 4.1 e do que falta no
código. Letras: M campo/validação, N listagem, O rotas e ações, P
features novas, Q acesso, R views e flash, S testes, T populate e
infra. A–H são da 4.1.

## 5. Cenários

### M1. Campo novo em `events` com validação

Chance: alta. Tempo: 25-35 min.

Pedido típico: "acrescente a carga horária (`workload_hours`, inteiro
>= 1, obrigatório) ao Evento". Variantes: `speaker` (texto, opcional),
`price` (decimal >= 0), `registration_deadline` (data).

Arquivos (todos mudam):

- `SCH:21-38` (`CREATE TABLE events`): a coluna nova.
- `EV:10-21` (`@property`), `EV:48-58` (`$columns`), `EV:60-82`
  (`validates()`; `EV:79-81` é o modelo com `notEmpty` + `integerMin`).
- `EC:14` (`FIELDS`: só o que está aqui é copiado do form, `EC:123-126`).
- `FORM:84-89` (bloco de `capacity`, copiar), `IDX:11-16` e `IDX:22-26`
  (coluna), `SHOW:28-32` (par `dt`/`dd`).
- `POP:134-143` (array do `new Event`).
- Testes: `EVT:41-54` (`params()`), `ECT:272-284` (`validParams()`),
  `ACT/BaseEventsCest.php:84-94` (`seedEvents`) e `:163-175`
  (`validEventData`), `ACC:28-40` (Evento do `setUp`).

Passos:

1. Teste primeiro: em `EVT`, copie `test_capacity_should_be_at_least_one`
   (`EVT:196-208`) para o campo novo.
2. `SCH`: `workload_hours INT NOT NULL` depois de `capacity` (`SCH:31`).
3. `EV`: `@property int|string $workload_hours`, a chave em `$columns`,
   e em `validates()`:
   `Validations::notEmpty('workload_hours', $this) && Validations::integerMin('workload_hours', $this, 1);`
4. `EC:14`: acrescente `'workload_hours'` em `FIELDS`.
5. `FORM`: copie o `<div class="mb-3">` de `capacity` trocando `id`,
   `name="event[workload_hours]"` e o `value`; mantenha `$invalid()` e
   `$feedback()` (`FORM:11-14`). Coluna na `IDX`, `dt`/`dd` na `SHOW`.
6. `POP:134-143`: `'workload_hours' => 4,` (igual para os 16; não mexa
   nas linhas posicionais de `POP:49-130`).
7. Testes: a chave em `params()`, `validParams()`, `seedEvents` e no
   `setUp` do `ACC`, senão quase tudo falha por campo vazio.
8. `./run db:populate` e a suíte.

Armadilhas:

- Esquecer `$columns`: `Property workload_hours not found`
  (`MODEL:86`) ao atribuir, e a coluna nunca vai ao banco.
- Esquecer `FIELDS`: o form envia, o controller ignora, "nunca salva"
  e a validação reclama de vazio.
- Não refazer o schema no banco de desenvolvimento: `Unknown column`
  só no navegador; os testes passam porque recriam o schema.
- `DEFAULT` na coluna não ajuda: `save()` insere **todas** as colunas
  de `$columns` (`MODEL:143-158`), com `NULL` quando falta valor.
- `seedEvents` não confere o retorno de `save()` (`BaseEventsCest.php:95`):
  sem o campo os Cests falham com "0 linhas", sem mensagem clara.
- Campo opcional: não use `notEmpty`; para tipo numérico opcional,
  `if ($this->speaker !== null && $this->speaker !== '')` antes.
- O form tem `novalidate` (`FORM:16`): o erro vem do servidor.
- `(int) $value` no controller é "conversão silenciosa" (issue do
  grupo 5): deixe a string ir ao model e o `integerMin` rejeitar.

Prova: `./run test tests/Unit/Models/Events/EventTest.php` e
`tests/Integration/Controllers/Admin/EventsControllerTest.php`; no
navegador, enviar vazio e `0`: erro embaixo do campo, demais valores
mantidos (`ECT:114-138` é o modelo do teste).

### M2. Validação nova em `lib/Validations.php`

Chance: alta. Tempo: 15-20 min.

Pedido típico: "o título não pode passar de 100 caracteres"
(`maxLength`), "descrição com no mínimo 10" (`minLength`), "vagas no
máximo 500" (`integerMax`), "local só letras, números e espaços"
(`format` com regex).

Arquivos: `VAL:78-94` (`integerMin`, o modelo), `EV:62-64` e `EV:79`
(onde chamar), `tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php:13-48`
(modelo do teste; o model anônimo está em `:15-17`), `EVT:185-208`.

Passos:

1. Teste: copie `test_integer_min_should_reject_values_below_the_minimum`
   (`:30`) e adapte.
2. O model anônimo do teste só tem colunas numéricas (`:16`): para
   `maxLength` acrescente uma coluna de texto em `$columns`; sem ela,
   `$this->model->name = ...` lança `Property name not found`
   (`MODEL:86`). Mantenha o `// @phpstan-ignore-line` como em `:23`.
3. Em `VAL`, método estático com o formato de `integerMin`:

```php
public static function maxLength($attribute, $obj, int $max)
{
    $value = $obj->$attribute;

    if (is_string($value) && mb_strlen($value) > $max) {
        $obj->addError($attribute, "deve ter no máximo {$max} caracteres!");
        return false;
    }

    return true;
}
```

4. `EV:62`: `Validations::notEmpty('title', $this) && Validations::maxLength('title', $this, 100);`
   (encadeamento com `&&`, padrão de `EV:66-77`).
5. Teste em `EVT` (cópia de `EVT:185-194`) com título de 101 caracteres
   (`str_repeat('a', 101)`).

Armadilhas:

- `strlen` conta bytes: `mb_strlen` para acentos.
- Valor `null`: `notEmpty` primeiro, por isso o `&&`.
- Mensagem em pt-BR terminada em `!`, como as demais.
- Validação no controller: recusada (regra de negócio no model).
- `lib/` não é analisado pelo `phpstan` (`phpstan.neon` cobre `app`,
  `core`, `tests`); tipe os parâmetros novos mesmo assim, o `phpcs`
  roda em tudo.
- `format`: use `preg_match('/^[\p{L}\p{N} ]+$/u', $value) !== 1`
  (flag `u` para acentos).

Prova: teste novo do `Validations` e do `Event`; no navegador, erro
sob o campo e o resto do form preenchido.

### M3. Valor novo em ENUM/SET

Chance: média. Tempo: 10-15 min.

Pedido típico: "acrescente o tipo `seminar` (Seminário)" ou "público
`alumni` (Egressos)".

Arquivos: `SCH:26` (`kind`), `SCH:27` (`modality`), `SCH:28`
(`audience`), `EV:25-30` (`KINDS`), `EV:33-37` (`MODALITIES`),
`EV:40-45` (`AUDIENCES`), `EVT:59-65` (`createEvent`, chama `save()`),
`EVT:140-146`.

Passos:

1. Teste em `EVT`: `$event = $this->createEvent(['kind' => 'seminar']);`
   (ele faz `assertTrue($event->save())`) e
   `assertSame('seminar', Event::findById($event->id)->kind)`.
2. `SCH`: o valor no ENUM/SET. `EV`: chave e rótulo na constante.
3. `./run db:populate` (refaz o schema).
4. `FORM:39-41`, `:48-54`, `:60-66`, `IDX:23`, `SHOW:4,14,17` percorrem
   as constantes: não mudam.

Armadilhas:

- Teste só com `isValid()` passa com o schema velho (`EVT:140-146`);
  é o `INSERT` que falha com `Data truncated`. Chame `save()`.
- Só a constante: `INSERT` falha. Só o schema: `inclusion`
  (`VAL:34-44`) rejeita.
- Valor em inglês sem acento; rótulo em pt-BR.
- SET guarda `students,staff`; `audienceList()`/`setAudienceList()`
  (`EV:180-206`) já tratam.

Prova: `EventTest` com `save()`; criar um Evento com o valor novo no
navegador e ver o rótulo na listagem e no `show`.

### M4. Segunda data (`ends_on`) não anterior a `starts_on`

Chance: média-alta. Tempo: 25-35 min.

Pedido típico: "o Evento tem data de término, que não pode ser antes
do início". Variante: "prazo de inscrição (`registration_deadline`)
até a data do Evento".

Arquivos: os de M1 mais `EV:75-77` (bloco de `starts_on`), `EV:88-99`
(`validatesStartsOnNotPast`, modelo de método privado de validação),
`VAL:99-107` (`validDate`), `VAL:140-153` (`parseDate`, privado),
`FORM:70-75` (campo `date`), `ACT/BaseEventsCest.php:148-154` (a data é
preenchida por `executeJS`), `EVT:210-233`.

Passos:

1. Testes em `EVT`: `ends_on` igual a `starts_on` válido; um dia antes
   inválido com mensagem; `ends_on` inválida marcada.
2. M1 completo para a coluna `ends_on DATE NOT NULL` (ou `NULL` se
   opcional).
3. Em `validates()`:
   `Validations::notEmpty('ends_on', $this) && Validations::validDate('ends_on', $this) && $this->validatesEndsOnAfterStartsOn();`
4. Método privado:

```php
private function validatesEndsOnAfterStartsOn(): void
{
    $start = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->starts_on);
    $end = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->ends_on);

    if ($start !== false && $end !== false && $end < $start) {
        $this->addError('ends_on', 'não pode ser anterior à data de início!');
    }
}
```

5. Form, listagem, show, populate (`'ends_on' => date('Y-m-d', strtotime("+{$daysAhead} days"))`
   ou +1 dia), testes de controller e Cests (`fillEventForm` precisa de
   um `executeJS` para o campo novo, cópia de `:148-154`).

Armadilhas:

- Comparar strings de data funciona para `Y-m-d`, mas só se as duas
  forem válidas: valide cada uma antes (`validDate`) e saia se alguma
  for inválida, senão aparecem dois erros contraditórios.
- `parseDate` de `VAL` é privado: não dá para reaproveitar de fora;
  copie a chamada `createFromFormat('!Y-m-d', ...)`.
- `starts_on` tem a regra "não no passado" só quando muda
  (`EV:88-99`); não copie essa regra para `ends_on` sem pedido.
- Selenium: `type="date"` não aceita `fillField` em pt-BR; use o
  padrão do `executeJS` da base.

Prova: `EventTest` novo; no navegador, término antes do início mostra
o erro sob o campo e mantém os valores.

### M5. Validação condicional por modalidade

Chance: média. Tempo: 15-25 min.

Pedido típico: "Evento online não precisa de local" ou "Evento online
precisa de um link (URL) no campo local".

Arquivos: `EV:64` (`notEmpty('location')`), `EV:33-37` (`MODALITIES`),
`EV:60-82`, `FORM:77-82`, `EVT:86-126` (os testes de campo obrigatório
assumem `location` sempre obrigatório: `requiredFieldsProvider`,
`EVT:101-114`).

Passos:

1. Testes em `EVT`: online sem local válido; presencial sem local
   inválido; (variante) online com local que não é URL inválido.
2. `validates()`:

```php
if ($this->modality === 'online') {
    Validations::notEmpty('location', $this) && $this->validatesLocationIsUrl();
} else {
    Validations::notEmpty('location', $this);
}
```

   com `filter_var($this->location, FILTER_VALIDATE_URL) === false` no
   método privado e mensagem "deve ser um link (URL) válido!".
3. Rótulo do campo no form pode virar "Local ou link".
4. Ajuste `requiredFieldsProvider` (`EVT:101-114`): `location` deixa de
   ser sempre obrigatório.

Armadilhas:

- A coluna `location` é `NOT NULL` (`SCH:30`): se o local pode ficar
  vazio, grave `''`, ou mude para `NULL` no schema.
- `modality` inválida ou vazia cai no `else`; é o esperado.
- Populate: os Eventos online já têm texto de link (`POP:58,88,108`);
  se exigir URL real, troque para `https://...`.

Prova: `EventTest`; no navegador, criar online sem local e presencial
sem local.

### M6. Unicidade (título único na mesma data)

Chance: média. Tempo: 15-20 min.

Pedido típico: "não pode haver dois Eventos com o mesmo título" ou
"com o mesmo título na mesma data".

Arquivos: `VAL:155-205` (`uniqueness`, aceita um campo ou vários:
`:160-162`), `US:42` (uso em `User`), `EV:60-82`,
`tests/Unit/Lib/Validations/UniquenessValidationsTest.php:111-125`
(dois campos), `EVT:302-308`, `ACT/BaseEventsCest.php:84-94` (títulos
`Evento 01..16` já são únicos).

Passos:

1. Teste em `EVT`: dois Eventos iguais; o segundo `save()` falha e
   `errors('title')` é "já existe um registro com esse dado".
2. `validates()`: `Validations::uniqueness(['title', 'starts_on'], $this);`
   (ou só `'title'`).
3. Teste de controller: `create` duplicado renderiza o form com o erro.

Armadilhas:

- `uniqueness` ignora o próprio registro ao editar sem mudar os
  campos (`VAL:164-179`): editar continua funcionando.
- A mensagem vem sem `!` e aparece em todos os campos da lista
  (`VAL:197-202`); se o professor pedir outra, mude em `VAL`.
- O populate tem títulos únicos; o `ECT:39-57` cria "Evento 1..7" com a
  mesma data, também únicos.
- Não há índice único no schema para isso; se pedirem, acrescente
  `UNIQUE KEY` em `SCH` e trate a `PDOException` (padrão `EV:166-172`).

Prova: `EventTest`; no navegador, cadastrar o mesmo título duas vezes.

### N1. Filtro por tipo com `where`, mantido na paginação

Chance: média-alta. Tempo: 25-35 min.

Pedido típico: "na listagem, filtre por tipo: `/admin/events?kind=workshop`".
A rubrica pede `where`.

Arquivos: `EC:16-27` (`index`), `IDX:1-3` e `IDX:40`, `MODEL:284-294`
(`paginate` não repassa condições), `PAG:18-26` (construtor já aceita
`conditions`), `PAG:93-97` (`renderPagesNavigation`), `PAG:147-158`
(`buildConditions`, só igualdade), `PAGES:6,16,22` (links só com
`page`), `HASMANY:44-55` (modelo de `paginate` com `conditions:`),
`EVT:366-376` (teste de `where`), `ECT:39-57`.

Passos (forma 1, segura; forma 2, completa):

1. `index()`: `$kind = $request->getParam('kind');` e
   `$kind = is_string($kind) && array_key_exists($kind, Event::KINDS) ? $kind : null;`.
2. Forma 1 (sem paginação quando há filtro): com `$kind`,
   `$events = Event::where(['kind' => $kind]); $paginator = null;`;
   sem, o caminho atual. Na `IDX:40`, só chame
   `renderPagesNavigation()` se `$paginator !== null`. `$kind` no
   `compact`.
3. Forma 2 (com paginação, 15 min a mais):
   - `PAG:18-26`: `private array $queryParams = []` no construtor e
     getter `queryParams(): array` com `@return array<string, mixed>`.
   - `MODEL:284-294`: parâmetros `array $conditions = []` e
     `array $queryParams = []` repassados (modelo `HASMANY:44-55`), com
     `@param array<string, mixed>` em cada um.
   - `EC:18-22`: `conditions: $kind !== null ? ['kind' => $kind] : [], queryParams: ...`.
   - `PAGES:6,16,22`: `array_merge(['page' => X], $paginator->queryParams())`;
     `route()` põe o que não está na URI como query string.
4. `IDX`: `<form method="get" action="<?= route('admin.events.index') ?>">`
   com `<select name="kind">` sobre `Event::KINDS` e `selected`.

Armadilhas:

- Não use `$kind` dentro de `PAGES`: o partial é incluído em
  `Paginator::renderPagesNavigation()` (`PAG:93-97`), onde só existem
  `$paginator` e `$this`. Variável indefinida vira warning, e o
  `ErrorsHandler` transforma em 500.
- `?kind[]=x` chega como array: `array_key_exists` com array lança
  `TypeError`; por isso `is_string` antes.
- `array` sem PHPDoc em `app/`, `core/`: `phpstan` nível 6,
  `missingType.iterableValue`.
- Sem `queryParams`, a página 2 perde o filtro.
- `$paginator` nulo na `IDX:40` dá 500 (forma 1).
- `where` só faz igualdade (`MODEL:309-311`): `LIKE` é N2.

Prova: teste de model (cópia de `EVT:366-376`) e de controller (cópia
de `ECT:39-57` com `params: ['kind' => 'lecture']`, só Palestras na
resposta; na forma 2, `/admin/events/page/2?kind=lecture` nos links);
no navegador, filtrar e ir à página 2. O `BaseEventsCest` distribui os
tipos em rodízio (`:79,87`), útil para um Cest.

### N2. Busca por título (`LIKE`)

Chance: média. Tempo: 20-30 min.

Pedido típico: "campo de busca por título na listagem".

Arquivos: `EV` (método novo depois de `hasEnrollments()`, `EV:142-145`),
`EC:16-27`, `IDX:1-3`, `EVT:366-376`, `ECT:39-57`, `MODEL:300-333`
(`where`, modelo de SQL com `prepare`).

Passos:

1. Teste de model: dois Eventos, um termo que casa só um.
2. `EV`:

```php
/** @return array<static> */
public static function searchByTitle(string $term): array
{
    $columns = implode(', ', static::$columns);
    $stmt = Database::getDatabaseConn()->prepare(
        "SELECT id, {$columns} FROM events WHERE title LIKE :term"
    );
    $stmt->bindValue(':term', "%{$term}%");
    $stmt->execute();

    return array_map(fn($row) => new static($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
}
```

   Imports: `Core\Database\Database`, `PDO`.
3. `EC::index`: `$q = $request->getParam('q'); $q = is_string($q) ? trim($q) : '';`.
   Se `$q !== ''`, `$events = Event::searchByTitle($q); $paginator = null;`
   (como N1 forma 1); senão o caminho atual.
4. `$q` **sempre** no `compact`. `IDX`: `<form method="get">` com
   `<input name="q" value="<?= htmlspecialchars($q) ?>">`.

Armadilhas:

- `$q` fora do `compact`: variável indefinida na view, 500.
- `array` sem `@return array<static>`: `phpstan` nível 6.
- Concatenar `$term` no SQL: injeção; sempre `prepare` + `bindValue`.
- Ecoar `$q` sem `htmlspecialchars`: XSS.
- `%` e `_` digitados viram curinga; aceitável, saiba dizer.
- `Model::where` não serve (só igualdade). SQL no controller: recusado.

Prova: teste unitário e de controller com `params: ['q' => ...]`; no
navegador, busca vazia mostra a listagem normal.

### N3. Ordenar por data no `Paginator`

Chance: média-alta. Tempo: 15-25 min.

Pedido típico: "ordene os Eventos pela data (mais próximos primeiro)".
Hoje a listagem segue o `id`; o `Paginator` não tem `ORDER BY`.

Arquivos: `PAG:18-26` (construtor) e `PAG:124-128` (SQL sem `ORDER BY`),
`MODEL:284-294`, `EC:18-22`, `ECT:39-57`, `EVT:390-413`, e os Cests que
assumem a ordem de `id`: `ACT/CreateSuccessfullyCest.php`,
`ACT/ListEventsCest.php:19-33`, `ACT/PaginateEventsCest.php`,
`ACT/BaseEventsCest.php:73-76`.

Passos:

1. Teste em `ECT`: Eventos com datas fora de ordem; `strpos` do título
   mais próximo antes do outro na resposta.
2. `PAG`: parâmetro `private ?string $orderBy = null` no construtor;
   em `loadRegisters()`:
   `$order = $this->orderBy !== null ? " ORDER BY {$this->orderBy}" : '';`
   inserido antes de `LIMIT :limit OFFSET :offset` (`PAG:127`).
3. `MODEL::paginate`: `?string $orderBy = null` repassado.
4. `EC:18-22`: `orderBy: 'starts_on, id'`.
5. Rode os Cests: `CreateSuccessfullyCest` cadastra para amanhã
   (`validEventData`, `BaseEventsCest.php:171`) e espera encontrá-lo na
   última página; com a ordenação ele vai para a página 1.

Armadilhas:

- `usort` no controller ordena só a página de 5: errado, o professor
  percebe na página 2. Faça no SQL.
- Desempate por `id`: sem ele, datas iguais trocam de página entre
  requisições; `ECT:39-57` cria tudo com a mesma data e depende da
  ordem de `id`.
- Nunca interpole `?sort=` do usuário em `ORDER BY`: lista fechada
  (`['starts_on', 'title']`) e `in_array(..., true)`.
- `Event::all()` (`MODEL:261-282`) também não ordena; o pedido pode ser
  sobre ele: método `Event::allOrderedByDate()` com SQL no model.

Prova: teste de controller novo. No navegador, o populate sozinho não
mostra nada (datas crescem com o `id`): cadastre um Evento para
amanhã e veja-o subir para a página 1.

### N4. Mudar `per_page` ou deixar o usuário escolher

Chance: alta. Tempo: 5-10 min (mais 10 com a escolha pelo usuário).

Pedido típico: "mostre 10 por página", "3 por página", "deixe o
usuário escolher".

Arquivos: `EC:12` (`PER_PAGE = 5`), `EC:18-22`, `ECT:39-57` (assume 5),
`PAG:113-114` (divisão por `per_page`), `ACT/BaseEventsCest.php:23`
(`PER_PAGE = 5`), `ACT/ListEventsCest.php:17,43`,
`ACT/PaginateEventsCest.php` (textos "Mostrando 1 - 5 de 16", número de
itens da paginação).

Passos:

1. `EC:12`: troque o valor.
2. `ECT:39-57` cria 7 Eventos e espera "Evento 6" só na página 2: com
   10 falha. Ajuste (ex.: 12 Eventos) e rode.
3. `BaseEventsCest::PER_PAGE` e reescreva o `PaginateEventsCest`: com
   10 por página e 16 Eventos há só 2 páginas (ele clica na 3 e na 4).
4. Variante `?per_page=`: `$perPage = (int) $request->getParam('per_page', self::PER_PAGE);`
   e `in_array($perPage, [5, 10, 20], true) ? $perPage : self::PER_PAGE;`
   repassado aos links (mecanismo `queryParams` de N1 forma 2).

Armadilhas:

- `per_page` 0 dá divisão por zero em `PAG:114`: nunca o valor cru.
- Com 10 ou mais Eventos nos testes, `'Evento 1'` casa com
  `'Evento 10'`: use `sprintf('Evento %02d', $i)` como o
  `BaseEventsCest.php:63-66`.
- `EVT:390-413` usa 5 explícito; não depende de `PER_PAGE`.

Prova: `EventsControllerTest`; no navegador, 16 Eventos em 2 páginas e
`Mostrando 1 - 10 de 16`.

### N5. Só Eventos futuros ou passados

Chance: média. Tempo: 20-30 min.

Pedido típico: "mostre só os Eventos que ainda vão acontecer" ou
"uma aba de Eventos passados".

Arquivos: `EV` (métodos novos, modelo N2), `MODEL:300-333` (`where` só
faz igualdade), `EC:16-27`, `IDX:1-3`, `EVT:499-509` (`createPastEvent`,
grava data passada direto no banco com `update`), `ECT:39-57`.

Passos:

1. Teste de model: um Evento futuro e um passado (criado com
   `createPastEvent`, `EVT:499-509`); `Event::upcoming()` traz só o
   futuro.
2. `EV`: `upcoming()` e `past()` com SQL no model:
   `WHERE starts_on >= CURDATE() ORDER BY starts_on, id` (e `<` para
   passados), `@return array<static>`.
3. `EC::index`: `$scope = $request->getParam('scope');` validado em
   `['upcoming', 'past']`; com escopo, `$events = Event::upcoming()` e
   `$paginator = null`; sem, o caminho atual. `$scope` no `compact`.
4. `IDX`: dois links (`?scope=upcoming`, `?scope=past`) e "Todos".

Armadilhas:

- `CURDATE()` do MySQL usa o fuso do contêiner `db`; o PHP usa
  `America/Sao_Paulo` (`Dockerfile:10`). Para evitar divergência à
  noite, passe a data do PHP: `bindValue(':today', date('Y-m-d'))`.
- A validação não deixa criar Evento passado (`EV:75-77`): nos testes
  use `update(['starts_on' => ...])`, que não valida (`MODEL:189`).
- `$paginator` nulo na `IDX:40`: proteja (N1 forma 1).

Prova: teste de model; no navegador, o populate só tem futuros, então
crie um passado pelo `db:console` ou pelo teste.

### N6. Dado calculado na tela (badge "Lotado", total)

Chance: média. Tempo: 10-15 min.

Pedido típico: "mostre 'Lotado' quando não houver vagas" ou "quantos
Eventos existem no painel administrativo".

Arquivos: `IDX:26` (`inscritos/capacidade`), `EV:125-140`
(`enrollmentsCount`, `availableSpots`), `SHOW:28-32`,
`app/Controllers/Admin/DashboardController.php:9-13`,
`app/views/admin/dashboard/index.phtml:1-5`, `ECT:59-68`, `EVT:483-497`,
`ACT/ListEventsCest.php:36-38` (conferem `3/3`, `0/22`, `1/23`).

Passos:

1. Teste em `ECT`: copie `:59-68` e confira `Lotado` para um Evento
   com `capacity` igual às Inscrições (`enroll`, `ECT:302-313`).
2. `IDX:26`: mantenha `x/y` e acrescente
   `<?php if ($event->availableSpots() === 0) : ?><span class="badge text-bg-danger">Lotado</span><?php endif; ?>`.
3. Contador: `DashboardController::index` com
   `$total = count(Event::all());` (importar `App\Models\Event`) e
   `compact('title', 'total')`; imprima na view.

Armadilhas:

- Mudar o formato `1/10`: `ECT:67` e o `ListEventsCest` conferem a
  string.
- `enrollmentsCount()` faz uma consulta por linha (N+1); aceitável.
- `$total` fora do `compact`: 500.
- O Evento lotado do populate é o primeiro (3/3), na página 1.

Prova: teste de controller; o primeiro Evento mostra "Lotado".
