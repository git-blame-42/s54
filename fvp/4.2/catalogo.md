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

### O1. Rota RESTful nova com página (lotados)

Chance: média-alta. Tempo: 20-30 min.

Pedido típico: "uma página que lista os Eventos lotados"
(`GET /admin/events/full`, `admin.events.full`). Só existem `get`,
`post`, `put` e `delete` em `core/Router/Route.php:111-141` (não há
`patch`).

Arquivos: `ROUTES:26-35` (rota fixa `new` em `:29`, `{id}` em `:31`),
`EC` (ação nova), `app/views/admin/events/full.phtml` (novo), `IDX:1-3`
(link), `EV:125-145` (`enrollmentsCount`, `availableSpots`,
`hasEnrollments`), `ACC:58-78` (`newClient`, `loggedClient`) e
`ACC:162-173` (admin acessa e vê o conteúdo), `ACC:85-97`
(`eventRoutes`).

Passos:

1. Teste de controller: `get(action: 'full', ...)` com um Evento lotado
   (`enroll`, `ECT:302-313`, `capacity => '1'`) e um não.
2. `ROUTES`: `Route::get('/admin/events/full', [AdminEventsController::class, 'full'])->name('admin.events.full');`
   **antes** de `/admin/events/{id}` (`:31`), junto de `new` (`:29`).
3. `EC::full(): void`:
   `$events = array_filter(Event::all(), fn(Event $e) => $e->availableSpots() === 0);`,
   `$title = 'Eventos lotados';`,
   `$this->render('admin/events/full', compact('events', 'title'));`.
4. `full.phtml`: tabela (copie `IDX:8-38` sem o paginador e sem o
   botão Remover). Link em `IDX:1-3`.
5. `ACC`: `'admin.events.full' => ['GET', '/admin/events/full']` em
   `eventRoutes()` e um teste com admin logado (cópia de `:162-173`)
   esperando 200 e "Eventos lotados".

Armadilhas:

- O `CTC` chama a ação direto (`CTC:70-84`), sem Router: o teste de
  controller **não pega** rota declarada depois de `{id}`. Só um teste
  por HTTP pega (se a rota estiver depois, vem 302 de "Evento não
  encontrado!").
- Sem sessão a resposta é 302 nos dois casos: o teste HTTP tem que
  logar o admin (`loggedClient`).
- `$title` fora do `compact`: o layout quebra (`LAYOUT:26`).
- Nome fora do padrão `admin.events.*`.
- Para `POST .../duplicate` (P6) o form precisa de `method="post"`;
  PUT/DELETE exigem `_method` (`core/Http/Request.php:18`).

Prova: teste de controller + HTTP; no navegador a página lista só o
primeiro Evento do populate (3/3).

### O2. Listagem e detalhe de Eventos para o Participante

Chance: média-alta. Tempo: 25-35 min.

Pedido típico: "o Participante precisa ver os Eventos" (`/events` e
`/events/{id}`, só leitura). Variante: pública, sem login. É a base de
P2 (inscrição).

Arquivos: `ROUTES:18-20` (grupo `auth`, hoje só o dashboard; as rotas
novas entram aqui, fora de `admin`), `ROUTES:4` (o admin já é importado
com alias; `use App\Controllers\EventsController;` fica livre),
`EC:16-39` (modelo de `index` e `show`), `IDX`, `SHOW`, `NAV:10-12`
(link para todos os logados), `MODEL:284-294` (`paginate` com nome de
rota), `ECT:17-57` (modelo de teste), `tests/Integration/Access/DashboardAccessTest.php`
(modelo de acesso sem admin), `app/views/dashboard/index.phtml`.

Passos:

1. Testes: `tests/Integration/Controllers/EventsControllerTest.php`
   (namespace `Tests\Integration\Controllers`, não colide com o admin),
   `setUp` logando um **Participante**; `index` lista títulos; `show`
   mostra descrição e não mostra "Editar"/"Remover".
2. `ROUTES`, dentro de `auth`, antes do grupo `admin`:

```php
Route::get('/events', [EventsController::class, 'index'])->name('events.index');
Route::get('/events/page/{page}', [EventsController::class, 'index'])->name('events.paginate');
Route::get('/events/{id}', [EventsController::class, 'show'])->name('events.show');
```

3. `app/Controllers/EventsController.php` (namespace `App\Controllers`):
   `index` igual a `EC:16-27` com `route: 'events.paginate'` e
   `render('events/index', ...)`; `show` igual a `EC:29-39` com
   "não encontrado" redirecionando para `events.index`.
4. `app/views/events/index.phtml` e `show.phtml`: copie `IDX` e `SHOW`
   sem "Novo", "Editar", "Remover"; links para `events.show`.
5. `NAV:10-12`: `<li><a href="<?= route('events.index') ?>">Eventos</a></li>`
   para todo logado (o link admin de `NAV:17-19` continua).
6. Acesso: teste HTTP (cópia de `DashboardAccessTest`): sem sessão 302
   `/login`; Participante 200; admin 200.

Armadilhas:

- Rota de Participante dentro do grupo `admin` por engano
  (`ROUTES:22-36`): Participante cai em `/dashboard` com "Acesso
  restrito".
- Dois controllers com o mesmo nome curto: no `routes.php` importe
  `App\Controllers\EventsController` sem alias e mantenha
  `AdminEventsController` (`ROUTES:4`).
- `paginate` sem `route:` usa `events.paginate` por padrão
  (`PAG:99-102`); declare a rota com esse nome.
- Versão pública (fora de `auth`): `currentUser()` é `null`; as views
  copiadas não podem chamar `$this->currentUser()->name`.
- Esquecer `$title` no `compact`: 500.

Prova: testes; no navegador, logado como `user@example.com`, abrir
`/events`, paginar e abrir um Evento.

### O3. Inscritos no `show` (`participants()`)

Chance: média. Tempo: 20-30 min.

Pedido típico: "no detalhe do Evento, liste quem está inscrito (nome e
e-mail)".

Arquivos: `EV:117-120` (`user()`, modelo de relação), `MODEL:365`
(`BelongsToMany(string $related, string $pivot_table, string $from_foreign_key, string $to_foreign_key)`,
com B maiúsculo), `core/Database/ActiveRecord/BelongsToMany.php:22-57`
(`get()`) e `:59-82` (`count()`), `EN:20-28`, `SHOW:28-32`, `EVT:67-76`
(`enroll`), `ECT:151-161` e `ECT:302-313`.

Passos:

1. Teste em `EVT`: `createEvent()`, `enroll($event, 2)`,
   `assertCount(2, $event->participants()->get())`.
2. `EV`:

```php
public function participants(): BelongsToMany
{
    return $this->BelongsToMany(User::class, 'event_user', 'event_id', 'user_id');
}
```

   Import `Core\Database\ActiveRecord\BelongsToMany`.
3. `SHOW`: depois de Vagas, `<ul>` com
   `<?php foreach ($event->participants()->get() as $participant) : ?>`
   e `htmlspecialchars($participant->name)` / `->email`.
4. Teste em `ECT`: `show` de um Evento com `enroll()` contém
   "Participante" (nome criado em `ECT:305`).

Armadilhas:

- `get()` devolve `array<Model>`: `$participant->name` funciona pelo
  `__get`, mas o `phpstan` não conhece `name` em `Model`; na view não
  importa (views fora do `phpstan`), em código `app/` use
  `/** @var User $participant */` ou `instanceof User`.
- O `__get` mágico (`MODEL:55-68`) também permite `$event->participants`
  (sem parênteses) devolvendo o array; use a forma explícita.
- Alternativa sem `BelongsToMany`: `Enrollment::where(['event_id' => $this->id])`
  e `->user()->get()` por linha (N+1).
- `count()` da relação (`BelongsToMany:59-82`) pode substituir
  `enrollmentsCount()`; não troque sem pedido (testes conferem).

Prova: `EventTest` e `EventsControllerTest`; o primeiro Evento do
populate mostra 3 inscritos.

### O4. Resposta JSON da listagem

Chance: baixa. Tempo: 20 min.

Pedido típico: "a listagem deve responder JSON quando o cliente pede
`Accept: application/json`" (preparo para o Ajax da 4.4).

Arquivos: `CTRL:44-55` (`renderJson`: exige a view
`app/views/<view>.json.php` que preenche `$json`),
`core/Http/Request.php:52-55` (`acceptJson()`: compara `HTTP_ACCEPT`
com `application/json` exato), `EC:16-27`, `ECT:39-57`.

Passos:

1. Teste em `ECT`: `$_SERVER['HTTP_ACCEPT'] = 'application/json';`
   antes do `get('index', ...)`; `json_decode($response, true)` tem a
   chave `events` com os títulos. Limpe `$_SERVER['HTTP_ACCEPT']` no
   `tearDown`.
2. `EC::index`, depois de montar `$events`:

```php
if ($request->acceptJson()) {
    $this->renderJson('admin/events/index', compact('events'));
    return;
}
```

3. `app/views/admin/events/index.json.php`:

```php
<?php
$json['events'] = array_map(fn(\App\Models\Event $event) => [
    'id' => $event->id,
    'title' => $event->title,
    'starts_on' => $event->starts_on,
    'available_spots' => $event->availableSpots(),
], $events);
```

Armadilhas:

- `acceptJson()` exige igualdade exata: `Accept: application/json, */*`
  não casa. Teste com `curl -H 'Accept: application/json'`.
- `renderJson` não passa pelo layout e tem o header `chartset` com
  erro de grafia (`CTRL:52`); não é seu problema, mas saiba.
- Rota com extensão (`/admin/events.json`) não é o padrão do
  framework; use o header.
- `$json` precisa existir na view (`CTRL:50` inicializa `[]`).

Prova: teste de controller; `curl -H 'Accept: application/json' -b <cookie> http://localhost/admin/events`.

### P1. Código de check-in aleatório (1000-9999) por Evento

Chance: alta. Tempo: 25-35 min.

Pedido típico: "cada Evento recebe, ao ser criado, um código numérico
aleatório de 4 dígitos (1000 a 9999), único, gerado pelo backend, que
aparece no detalhe e na listagem". É a transposição direta do pedido
real da FVP 4.1 (P12) para o CRUD: campo gerado no servidor, não
editável pelo form, validado no model.

Arquivos: `SCH:21-38`, `EV:10-21`, `EV:48-58`, `EV:60-82`, `MODEL:139-187`
(`save()`, que `Event` vai sobrescrever), `MODEL:347` (`exists()`),
`VAL:78-94` (`integerMin`) e `VAL:155-205` (`uniqueness`), `EC:14`
(`FIELDS`: o código **não** entra), `EC:49-63`, `IDX:11-26`, `SHOW:6-33`,
`EVT:302-308`, `ECT:85-112` (`:101-112` é o modelo de "ignora campo
enviado pelo form").

Passos:

1. Testes em `EVT`: depois de `createEvent()`, `checkin_code` entre
   1000 e 9999; salvar de novo não troca o código; dois Eventos não
   compartilham código (`Event::exists(['checkin_code' => ...])` só
   acha um). Em `ECT`: `create` com `'checkin_code' => '1'` no form
   gera outro valor (cópia de `:101-112`).
2. `SCH`: `checkin_code SMALLINT UNSIGNED NOT NULL,` e
   `UNIQUE KEY uq_events_checkin_code (checkin_code)`.
3. `EV`: `@property int|string $checkin_code`, `'checkin_code'` em
   `$columns`, e:

```php
public function save(): bool
{
    if ($this->newRecord() && ($this->checkin_code === null || $this->checkin_code === '')) {
        $this->checkin_code = self::newCheckinCode();
    }

    return parent::save();
}

public static function newCheckinCode(): int
{
    do {
        $code = random_int(self::CHECKIN_CODE_MIN, self::CHECKIN_CODE_MAX);
    } while (self::exists(['checkin_code' => $code]));

    return $code;
}
```

   com `public const CHECKIN_CODE_MIN = 1000;` e `MAX = 9999`. Em
   `validates()`:
   `Validations::integerMin('checkin_code', $this, self::CHECKIN_CODE_MIN) && $this->validatesCheckinCodeMax() && Validations::uniqueness('checkin_code', $this);`
   (método privado: `(int) $this->checkin_code > self::CHECKIN_CODE_MAX`
   -> `addError('checkin_code', 'deve ser menor ou igual a 9999!')`;
   ou crie `integerMax` em `VAL`, cenário M2).
4. `EC:14`: **não** acrescente em `FIELDS`. `IDX` e `SHOW`: mostre
   `<?= (int) $event->checkin_code ?>` (coluna "Código").
5. `./run db:populate`: os 16 Eventos ganham código sozinhos (o
   `save()` gera).

Armadilhas:

- Gerar no controller: "regra de negócio no model"; o professor
  recusa. Gerar na view: nunca.
- `rand()`/`mt_rand()` servem, mas `random_int()` é o correto para
  valor imprevisível; diga isso se perguntarem.
- Sem o laço de unicidade há colisão (9000 valores); com `UNIQUE KEY`
  no schema a colisão vira `PDOException` no `INSERT`. O `uniqueness`
  em `validates()` cobre quem tenta forçar um código repetido via
  `update`.
- Como o código é gerado em `save()`, `isValid()` chamado antes de
  `save()` em um registro novo acusa `checkin_code` vazio: o teste
  `test_empty_event_should_have_error_in_every_field` (`EVT:86-99`)
  pode passar a listar `checkin_code`; ajuste a expectativa. Se
  preferir, gere no construtor (padrão `US:28-35`) e não em `save()`.
- `exists()` (`MODEL:347`) usa `findBy`: funciona com uma condição.
- Código **não** vai ao form nem ao `FIELDS`; se o professor quiser
  "regenerar", é uma ação `PUT /admin/events/{id}/checkin_code` (O1).
- `ECT:101-112` e os `validParams()` não precisam mudar.

Prova: `EventTest`, `EventsControllerTest`; no navegador, criar um
Evento e ver o código no `show` e na listagem; `db:console` com
`SELECT id, checkin_code FROM events` mostra 16 valores distintos.

### P2. Inscrição do Participante (`POST /events/{id}/enrollments`)

Chance: alta. Tempo: 30-40 min.

Pedido típico: "o Participante logado se inscreve em um Evento, se
houver vaga e se ainda não estiver inscrito". O model `Enrollment` e a
tabela `event_user` já existem (`EN`, `SCH:43-53`); falta a rota, o
controller, a validação e a tela. Pressupõe O2 (tela do Participante)
ou um botão no `show` do admin para demonstrar.

Arquivos: `EN:15-29` (sem `validates()`), `SCH:43-53` (`UNIQUE
(event_id, user_id)` em `:48`), `EV:125-140` (`availableSpots`),
`US:52-55` (`events()`, modelo de `HasMany`), `ROUTES:18-20`,
`CTRL:20-27` (`currentUser()`), `EC:96-111` (modelo de ação que
redireciona com flash), `MODEL:347` (`exists`),
`tests/Unit/Models/Events/EnrollmentTest.php:70-77` (hoje espera
`PDOException` na duplicata), `ECT:17-31`, `ACC:67-78`.

Passos:

1. Testes: `EnrollmentTest`: inscrição válida; duplicata agora
   `save()` falso com `errors('base')` (troque o `expectException` de
   `:70-77`); Evento lotado (`capacity => 1` + uma Inscrição) recusa.
   `EnrollmentsControllerTest` (novo, logando um Participante):
   `create` grava e redireciona para `events.show`; duplicata não
   grava e flash `danger`.
2. `EN::validates()`:

```php
public function validates(): void
{
    Validations::notEmpty('event_id', $this);
    Validations::notEmpty('user_id', $this);

    if (!$this->newRecord()) {
        return;
    }

    if (Enrollment::exists(['event_id' => $this->event_id, 'user_id' => $this->user_id])) {
        $this->addError('base', 'você já está inscrito neste evento!');
        return;
    }

    $event = Event::findById((int) $this->event_id);
    if ($event !== null && $event->availableSpots() === 0) {
        $this->addError('base', 'não há vagas neste evento!');
    }
}
```

   Import `Lib\Validations`. `exists()` com duas condições usa
   `findBy` (`MODEL:335`), que aceita várias.
3. `ROUTES`, dentro de `auth`:
   `Route::post('/events/{id}/enrollments', [EnrollmentsController::class, 'create'])->name('events.enrollments.create');`
4. `app/Controllers/EnrollmentsController.php`:

```php
public function create(Request $request): void
{
    $event = Event::findById((int) $request->getParam('id'));
    if ($event === null) {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo(route('events.index'));
        return;
    }

    $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $this->currentUser()?->id]);

    if ($enrollment->save()) {
        FlashMessage::success('Inscrição realizada com sucesso!');
    } else {
        FlashMessage::danger($enrollment->errors('base') ?? 'Não foi possível realizar a inscrição!');
    }

    $this->redirectTo(route('events.show', ['id' => $event->id]));
}
```

5. View `events/show.phtml` (O2): form `method="post"` para
   `route('events.enrollments.create', ['id' => $event->id])` com o
   botão "Inscrever-se"; mostre "Você já está inscrito" quando
   `Enrollment::exists([...])` (ou `User::isEnrolledIn(Event)` no
   model) e "Lotado" quando `availableSpots() === 0`.
6. `US`: `public function enrollments(): HasMany { return $this->hasMany(Enrollment::class, 'user_id'); }`
   e `isEnrolledIn(Event $event): bool`.
7. Acesso: teste HTTP `POST /events/1/enrollments` sem sessão -> 302
   `/login`.

Armadilhas:

- `user_id` vindo do form: nunca; sempre `currentUser()->id`
  (`ECT:101-112` mostra o padrão para Evento).
- Concorrência: duas inscrições simultâneas podem passar na contagem;
  o `UNIQUE` do schema barra a duplicata com `PDOException`, trate como
  em `EV:166-172`. Vaga a mais por corrida é aceitável aqui, saiba dizer.
- `EnrollmentTest:70-77` espera exceção: com a validação ele passa a
  falhar; atualize.
- `enrollmentsCount()` e `safeDestroy()` (`EV:125-173`) já consideram
  as Inscrições: Evento com inscritos continua não removível.
- Rota com verbo na URI (`/events/{id}/enroll`) funciona, mas a
  rubrica cobra nomes RESTful: recurso aninhado `enrollments` com
  `create`/`destroy` (P3).
- `redirectTo` sem `return` depois (no "não encontrado") segue
  executando no teste (o `CTC` só ecoa o `Location`).
- Admin também pode se inscrever? Decida com o professor; a
  validação não impede.

Prova: testes; no navegador, `user@example.com` abre `/events/<id>`,
inscreve-se, vê a mensagem; tentar de novo dá "já está inscrito"; no
Evento 1 (3/3) dá "não há vagas".

### P3. Cancelar inscrição (`DELETE /events/{id}/enrollments`)

Chance: média. Tempo: 15-20 min.

Pedido típico: "o Participante pode cancelar a própria inscrição".
Depende de P2.

Arquivos: os de P2, `MODEL:215-231` (`destroy()`), `MODEL:335`
(`findBy`), `IDX:30-33` (form com `_method=DELETE`, modelo),
`core/Http/Request.php:18`.

Passos:

1. Teste de controller: inscrito cancela, `Enrollment::all()` vazio,
   flash `success`; não inscrito recebe `danger`.
2. `ROUTES` (grupo `auth`):
   `Route::delete('/events/{id}/enrollments', [EnrollmentsController::class, 'destroy'])->name('events.enrollments.destroy');`
3. `EnrollmentsController::destroy`: `Enrollment::findBy(['event_id' => $id, 'user_id' => $this->currentUser()?->id])`;
   `null` -> `danger('Você não está inscrito neste evento!')`; senão
   `->destroy()` e `success('Inscrição cancelada!')`; redireciona para
   `events.show`.
4. View: form `method="post"` + `<input type="hidden" name="_method" value="DELETE">`
   (como `IDX:30-33`) no lugar do botão "Inscrever-se" quando inscrito.

Armadilhas:

- Só a própria inscrição: a busca inclui `user_id` do logado; nunca
  `enrollment_id` do form.
- `DELETE` sem `_method` no POST: 404 (`Request.php:18`).
- Após P9 (lista de espera), cancelar deve promover o primeiro da
  fila.

Prova: testes; no navegador, inscrever e cancelar.

### P4. Confirmar presença pelo código do Evento

Chance: média-alta. Tempo: 30-40 min.

Pedido típico: "o Participante inscrito confirma presença digitando o
código do Evento (o número de 4 dígitos); o backend valida e registra
a confirmação". Reúne P1 (código gerado) e P2 (inscrição). É o padrão
"número gerado pelo backend + validação do número" da FVP real da 4.1.

Arquivos: `EN:17-18` (`$columns`), `SCH:43-53`, `EV` (P1), P2,
`MODEL:189-213` (`update()`, grava sem validar), `core/Http/Request.php:57-60`
(`getParam`), `tests/Unit/Models/Events/EnrollmentTest.php`.

Passos:

1. Testes: `EnrollmentTest`: `confirm('1234')` com o código certo grava
   `confirmed_at`; errado devolve `false` e não grava; chamar duas vezes
   mantém a primeira data. Controller: não inscrito recebe `danger`;
   código em formato errado (`'12a'`, array) recebe `danger` sem 500.
2. `SCH:43-53`: `confirmed_at TIMESTAMP NULL,` em `event_user`;
   `EN:18`: `'confirmed_at'` em `$columns`; `@property string|null $confirmed_at`.
3. `EN`:

```php
public function confirm(string $code): bool
{
    $event = Event::findById((int) $this->event_id);

    if ($event === null || $this->confirmed_at !== null) {
        return false;
    }

    if (!ctype_digit($code) || (int) $code !== (int) $event->checkin_code) {
        $this->addError('base', 'código inválido!');
        return false;
    }

    return $this->update(['confirmed_at' => date('Y-m-d H:i:s')]);
}

public function isConfirmed(): bool
{
    return $this->confirmed_at !== null;
}
```

4. `ROUTES` (`auth`):
   `Route::post('/events/{id}/enrollments/confirmation', [EnrollmentsController::class, 'confirm'])->name('events.enrollments.confirm');`
5. `EnrollmentsController::confirm`: acha a inscrição do logado
   (`findBy`, como P3); `$code = $request->getParam('code'); $code = is_string($code) ? trim($code) : '';`;
   `confirm($code)` -> flash `success('Presença confirmada!')` ou
   `danger($enrollment->errors('base') ?? 'Não foi possível confirmar!')`;
   redireciona para `events.show`.
6. View do Participante: form com `<input name="code" inputmode="numeric" maxlength="4">`
   visível só para inscrito não confirmado; "Presença confirmada em
   dd/mm" quando confirmado.
7. Admin: no `SHOW`, "confirmados/inscritos" (`Enrollment::where(['event_id' => $id])`
   filtrando `isConfirmed()`).

Armadilhas:

- Comparar `'1234' === 1234` (string com int) é sempre falso; converta
  os dois para `int` depois de `ctype_digit`.
- Não revele o código no erro nem na página do Participante; o código
  o admin fornece no local.
- `update()` não valida (`MODEL:189`): bom aqui (não repete a checagem
  de vagas), mas lembre de dizer que é proposital.
- Limite de tentativas: se o professor pedir, guarde
  `$_SESSION['checkin_attempts'][$event->id]` (padrão do cenário B9 da
  4.1).
- `getParam('code')` pode vir array: `is_string` antes do `trim`.
- `TIMESTAMP NULL` precisa estar em `$columns`, senão `save()` grava
  `NULL` por omissão: ok, mas `confirmed_at` nunca persistiria via
  `save()` sem a coluna na lista.

Prova: testes; no navegador, inscrever-se, digitar código errado
(mensagem), digitar o certo (lido no `show` do admin) e ver a
confirmação.

### P5. Status do Evento: cancelar e encerrar

Chance: alta. Tempo: 25-35 min.

Pedido típico: "o administrador pode cancelar um Evento; cancelado
aparece com destaque, não aceita inscrição nem edição". Variante:
"encerrar" (`finished`) manualmente ou por data.

Arquivos: `SCH:21-38`, `EV:25-45` (constantes com rótulo, modelo),
`EV:48-58`, `EV:60-82` (`inclusion`, `EV:66-67`), `US:28-35`
(construtor com valor padrão, modelo), `ROUTES:26-35`, `EC:96-111`
(ação curta com flash, modelo), `IDX:22-33`, `SHOW:6-36`, `ECT:242-251`,
`ACC:85-97`.

Passos:

1. Testes: `EVT`: Evento novo nasce `scheduled`; `cancel()` muda para
   `canceled` e persiste; `status` fora da lista é inválido
   (`inclusion`). `ECT`: `put('cancel', ...)` com `id` -> `Location:
   /admin/events/{id}` e status cancelado; cancelar de novo dá `danger`.
2. `SCH`: `status ENUM('scheduled', 'canceled', 'finished') NOT NULL DEFAULT 'scheduled',`.
3. `EV`: `public const STATUSES = ['scheduled' => 'Agendado', 'canceled' => 'Cancelado', 'finished' => 'Encerrado'];`,
   `'status'` em `$columns`, construtor (cópia de `US:28-35`) com
   `if ($this->status === null) { $this->status = 'scheduled'; }`, em
   `validates()`:
   `Validations::notEmpty('status', $this) && Validations::inclusion('status', $this, array_keys(self::STATUSES));`
   e:

```php
public function cancel(): bool
{
    if ($this->status !== 'scheduled') {
        $this->addError('base', 'só um evento agendado pode ser cancelado!');
        return false;
    }

    $this->status = 'canceled';

    return $this->save();
}

public function isCanceled(): bool
{
    return $this->status === 'canceled';
}
```

4. `ROUTES` (grupo admin, depois de `update`):
   `Route::put('/admin/events/{id}/cancel', [AdminEventsController::class, 'cancel'])->name('admin.events.cancel');`
5. `EC::cancel(Request $request)`: `findEvent` (`EC:132-135`), não
   achou -> `eventNotFound()`; `$event->cancel()` -> `success('Evento cancelado!')`
   ou `danger($event->errors('base') ?? ...)`; `redirectTo(route('admin.events.show', ...))`.
6. `IDX`: badge `<span class="badge text-bg-secondary"><?= Event::STATUSES[$event->status] ?></span>`
   ao lado do título; botão "Cancelar" como form `post` + `_method=PUT`
   (não confundir com o link "Cancelar" do form, `FORM:92`: renomeie
   para "Cancelar Evento"). `SHOW`: linha "Situação".
7. Bloqueios: `EN::validates()` (P2) recusa inscrição em cancelado
   (`addError('base', 'evento cancelado!')`); edição/remoção em Q2.
8. `ACC:85-97`: `'admin.events.cancel' => ['PUT', '/admin/events/1/cancel']`.
9. `./run db:populate`; `POP` não precisa mudar (construtor dá o
   padrão).

Armadilhas:

- `DEFAULT` do schema não vale no `save()` (insere todas as colunas,
  `MODEL:143-158`): por isso o padrão no construtor.
- `status` **não** entra em `FIELDS` (`EC:14`): só a ação muda.
- `cancel()` chama `save()`, que roda `validates()` inteiro: um Evento
  com capacidade abaixo das inscrições ou data inválida não cancela.
  Se o professor quiser cancelar mesmo assim, use
  `update(['status' => 'canceled'])` (`MODEL:189`) e explique.
- Rota PUT com `{id}` e sufixo: ordem não importa (`:31` só casa
  `GET`), mas mantenha junto das outras.
- `finished` por data: `isFinished(): bool` comparando `starts_on <
  date('Y-m-d')` sem gravar; ou tarefa `./run` que atualiza (T do 4.1,
  G3).
- Textos dos testes: `ECT` compara `Location` exato.

Prova: testes; no navegador, cancelar um Evento sem inscrições, ver o
badge, tentar inscrever-se (P2) e receber a recusa.

### P6. Duplicar Evento

Chance: média-alta. Tempo: 20-30 min.

Pedido típico: "um botão 'Duplicar' cria uma cópia do Evento (título
com '(cópia)', data daqui a 7 dias, sem inscrições) e abre a edição".

Arquivos: `EV:47-58` (`$columns`), `MODEL:20-44` (`$attributes` é
**privado** em `Model`: copie via `$this->$column`), `MODEL:139-187`,
`EC:49-63` (`create`, modelo de redirect), `ROUTES:26-35`, `IDX:28-33`,
`ECT:85-99`, `ACC:85-97`.

Passos:

1. Testes: `EVT`: `duplicate()` devolve Evento novo (`newRecord()`),
   mesmo `kind`/`audience`/`user_id`, título com "(cópia)", data +7
   dias, `enrollmentsCount() === 0` após salvar. `ECT`:
   `post('duplicate', ...)` com `id` -> `Event::all()` tem 2 e `Location:
   /admin/events/{novo}/edit`.
2. `EV`:

```php
public function duplicate(): Event
{
    $copy = new Event();

    foreach (static::$columns as $column) {
        $copy->$column = $this->$column;
    }

    $copy->title = "{$this->title} (cópia)";
    $copy->starts_on = date('Y-m-d', strtotime('+7 days'));

    return $copy;
}
```

   Com P1, zere `checkin_code` (`$copy->checkin_code = null;`) para
   gerar outro; com P5, `status = 'scheduled'`.
3. `ROUTES` (admin):
   `Route::post('/admin/events/{id}/duplicate', [AdminEventsController::class, 'duplicate'])->name('admin.events.duplicate');`
4. `EC::duplicate(Request $request)`: `findEvent`; `$copy = $event->duplicate();`
   `if ($copy->save())` -> `success('Evento duplicado! Revise os dados.')`
   e `redirectTo(route('admin.events.edit', ['id' => $copy->id]))`;
   senão `danger` e volta ao `show`.
5. `IDX:28-33`: form `method="post"` com botão "Duplicar".
6. `ACC`: a rota em `eventRoutes()`.

Armadilhas:

- `clone $this` copia `id` e o objeto continua "existente"
  (`newRecord()` falso): `save()` faria `UPDATE` no original. Construa
  um `new Event()`.
- Título com 255 caracteres + " (cópia)" estoura o `VARCHAR(255)`:
  `mb_substr($this->title, 0, 247)`.
- Data: se copiar `starts_on` de um Evento passado, a validação
  (`EV:75-77`) recusa o novo; por isso `+7 days`.
- `user_id`: o original ou o admin logado? Mantenha o original salvo
  pedido contrário; com Q1 use o logado.
- Redirecionar para `edit` sem flash deixa o usuário sem saber que
  foi criado.

Prova: testes; no navegador, duplicar, ver a edição aberta com
"(cópia)" e a listagem com 17 Eventos.

### P7. "Meus eventos" do Participante

Chance: média-alta. Tempo: 20-30 min.

Pedido típico: "uma página com os Eventos em que o Participante logado
está inscrito". Depende de P2 (ou do populate, que já tem Inscrições).

Arquivos: `US:52-55` (`events()`, modelo de `HasMany`), `HASMANY:19-22`
(`get()`), `EN:20-23` (`event()`), `ROUTES:18-20`, `POP:156-177`
(Inscrições do populate: `user@example.com` está nos Eventos 1, 2, 4 e
9), `tests/Integration/Access/DashboardAccessTest.php`, `ECT:17-31`.

Passos:

1. Testes: controller logando `user@` com duas Inscrições e uma de
   outro Participante: a resposta tem os dois títulos e não o terceiro.
   Acesso: sem sessão -> `/login`.
2. `US`: `public function enrollments(): HasMany { return $this->hasMany(Enrollment::class, 'user_id'); }`.
3. `ROUTES` (`auth`):
   `Route::get('/enrollments', [EnrollmentsController::class, 'index'])->name('enrollments.index');`
4. `EnrollmentsController::index`:
   `$enrollments = $this->currentUser()?->enrollments()->get() ?? [];`
   `$title = 'Meus eventos';` `render('enrollments/index', ...)`.
5. `app/views/enrollments/index.phtml`: tabela com
   `$enrollment->event()->get()` (título, data, local), link para
   `events.show` (O2) e botão cancelar (P3). `NAV`: link "Meus eventos".

Armadilhas:

- `get()` devolve `array<Model>`; para o `phpstan` em `app/`, declare
  `/** @var array<int, Enrollment> $enrollments */`.
- N+1 (`event()->get()` por linha): aceitável, saiba dizer; a
  alternativa é `BelongsToMany` em `User` para `events` via
  `event_user` (`MODEL:365`), mas o nome `events()` já é usado para os
  Eventos criados (`US:52-55`): chame de `enrolledEvents()`.
- Tabela vazia: mensagem "Você não está inscrito em nenhum evento".
- `$title` no `compact`.

Prova: testes; no navegador, `user@example.com` vê 4 Eventos.

### P8. Exportar a listagem em CSV

Chance: média. Tempo: 20-30 min.

Pedido típico: "um botão que baixa os Eventos em CSV" (`GET
/admin/events/export`).

Arquivos: `ROUTES:26-35` (antes de `{id}`, `:31`), `EC`, `CTRL:32-39`
(`render`, que **não** será usado), `CTC:70-84` (captura a saída com
`ob_start`), `IDX:1-3`, `ACC:85-97`.

Passos:

1. Teste em `ECT`: `get('export', ...)` com dois Eventos: a resposta
   começa com a linha de cabeçalho e contém os títulos.
2. `ROUTES`: `Route::get('/admin/events/export', [AdminEventsController::class, 'export'])->name('admin.events.export');`
   antes de `/admin/events/{id}`.
3. `EC::export(): void`:

```php
public function export(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="events.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'title', 'kind', 'starts_on', 'location', 'capacity', 'enrollments']);

    foreach (Event::all() as $event) {
        fputcsv($out, [
            $event->id, $event->title, $event->kind, $event->starts_on,
            $event->location, $event->capacity, $event->enrollmentsCount(),
        ]);
    }

    fclose($out);
}
```

4. Botão "Exportar CSV" em `IDX:1-3`. `ACC`: a rota em `eventRoutes()`.

Armadilhas:

- Não chame `render()` (layout HTML dentro do CSV) nem `exit` (mata o
  teste; o `CTC` precisa que a ação retorne).
- Rota depois de `{id}`: `export` vira um `id` e cai em "Evento não
  encontrado" (o teste de controller não pega; o HTTP sim).
- Excel em pt-BR espera `;`: `fputcsv($out, $row, ';')` se o
  professor abrir no Excel; diga a diferença.
- BOM UTF-8 (`"\xEF\xBB\xBF"`) ajuda o Excel com acentos; opcional.
- `header()` em teste CLI não falha, mas se já houve saída dá
  "headers already sent" no navegador: a ação não pode ecoar antes.
- Versão JSON: O4.

Prova: teste; no navegador, clicar e abrir o arquivo.

### P9. Lista de espera quando lotado

Chance: média-baixa. Tempo: 35-40 min.

Pedido típico: "se o Evento estiver lotado, a inscrição entra numa
lista de espera; quando alguém cancela, o primeiro da fila é
promovido". Depende de P2 e P3.

Arquivos: `SCH:43-53`, `EN:17-18`, `EN::validates()` (P2), `EV:125-140`
(`enrollmentsCount` deve contar só confirmados), `EV:60-82`
(`validatesCapacityAgainstEnrollments`, `EV:104-115`), `POP:156-177`,
`MODEL:300-333`.

Passos:

1. Testes: Evento com 1 vaga: 1ª inscrição `confirmed`, 2ª `waiting`;
   cancelar a 1ª promove a 2ª; `enrollmentsCount()` ignora `waiting`.
2. `SCH`: `status ENUM('confirmed', 'waiting') NOT NULL DEFAULT 'confirmed'`
   em `event_user`; `EN`: `'status'` em `$columns`, construtor com
   padrão (`US:28-35`), `STATUSES`.
3. `EN::validates()` (P2): em vez de recusar sem vaga, defina
   `$this->status = 'waiting'` quando `availableSpots() === 0`.
4. `EV:131`: `Enrollment::where(['event_id' => $this->id, 'status' => 'confirmed'])`.
5. `EV::promoteFirstWaiting(): void`: SQL no model,
   `SELECT id FROM event_user WHERE event_id = :id AND status = 'waiting' ORDER BY created_at, id LIMIT 1`,
   depois `Enrollment::findById(...)->update(['status' => 'confirmed'])`.
   Chamado por `EnrollmentsController::destroy` (P3) após remover uma
   inscrição `confirmed`.
6. Views: "Você está na lista de espera (posição N)".

Armadilhas:

- `where` só faz igualdade e sem `ORDER BY`: a promoção precisa de SQL
  próprio no model.
- `created_at` tem precisão de segundo: desempate por `id`.
- `safeDestroy()` (`EV:151-173`) conta todas as Inscrições: um Evento
  só com fila continua não removível; decida se é o desejado.
- Populate: as 14 Inscrições ganham `confirmed` pelo construtor; o
  Evento 1 (3/3) fica lotado como antes.

Prova: testes; no navegador, inscrever um 4º Participante no Evento 1
e ver "lista de espera".

### P10. Comentários do Evento (recurso filho 1xN)

Chance: média. Tempo: 35-40 min.

Pedido típico: "o Participante pode deixar um comentário no Evento;
o detalhe lista os comentários". É um CRUD mínimo aninhado (prévia da
4.3: relação 1xN) e o cenário mais longo do catálogo.

Arquivos: `SCH:40-53` (tabela filha com FKs, modelo), `EN:1-29` (model
filho com `BelongsTo`, modelo), `US:52-55` (`HasMany`), `EV:142-173`
(`safeDestroy`: regra para filhos), `ROUTES:18-20`, P2 (controller
aninhado), `tests/Unit/Models/Events/EnrollmentTest.php` (modelo de
teste de model filho), `ACC`.

Passos:

1. Testes: `CommentTest` (válido; `body` vazio inválido; 501
   caracteres inválido; `event()->get()`); controller `create` grava e
   redireciona para `events.show`; acesso sem sessão.
2. `SCH`:

```sql
CREATE TABLE comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    event_id   INT NOT NULL,
    user_id    INT NOT NULL,
    body       TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
);
```

   e `DROP TABLE IF EXISTS comments;` no topo (`SCH:3-5`).
3. `app/Models/Comment.php`: `$table = 'comments'`,
   `$columns = ['event_id', 'user_id', 'body']`, `event()` e `user()`
   (`BelongsTo`), `validates()` com `notEmpty('body')` e `maxLength`
   (M2). `EV`: `public function comments(): HasMany { return $this->hasMany(Comment::class, 'event_id'); }`.
4. `ROUTES` (`auth`): `Route::post('/events/{id}/comments', [CommentsController::class, 'create'])->name('events.comments.create');`
5. `CommentsController::create`: acha o Evento; `new Comment(['event_id' => ..., 'user_id' => $this->currentUser()?->id, 'body' => $body])`
   com `$body = $request->getParam('comment')['body'] ?? null` validado
   como escalar (`is_array($params)`, `is_string`); `save()` -> flash e
   redirect para `events.show`; inválido -> `danger` com
   `errors('body')`.
6. View do Participante (O2): lista `$event->comments()->get()` (nome
   via `->user()->get()->name`, `nl2br(htmlspecialchars($comment->body))`)
   e form `<textarea name="comment[body]">`.
7. Remoção do pai: `ON DELETE CASCADE` apaga comentários; documente em
   `safeDestroy()` ou bloqueie como Inscrições (regra explícita, pedida
   pelo professor ao grupo 6).

Armadilhas:

- Sem `DROP TABLE IF EXISTS comments` antes de `events`, o `migrate()`
  falha por FK na 2ª execução.
- `created_at` não está em `$columns`: o banco preenche; para exibir
  a data, acrescente a coluna em `$columns` **só se** aceitar que
  `save()` grave `NULL`... não: deixe fora e leia com SQL próprio, ou
  inclua e preencha `date('Y-m-d H:i:s')` no construtor.
- Escape do `body` na view: XSS.
- `comment[body]` pode vir array: valide antes do `new Comment`.

Prova: testes; no navegador, comentar e ver na lista.

### P11. Soft delete (arquivar Evento)

Chance: baixa. Tempo: 30-40 min.

Pedido típico: "em vez de remover, arquive o Evento; arquivados não
aparecem na listagem e podem ser restaurados".

Arquivos: `SCH:21-38`, `EV:48-58`, `EV:151-173` (`safeDestroy`),
`MODEL:261-282` (`all()` sem filtro), `PAG:147-158` (`buildConditions`,
só igualdade: `IS NULL` não dá), `EC:96-111`, `IDX`, `ECT:242-251`.

Passos:

1. Testes: `archive()` marca e `Event::active()` não traz; `restore()`
   volta; `destroy` do controller arquiva em vez de apagar.
2. `SCH`: `archived TINYINT(1) NOT NULL DEFAULT 0` (um flag de
   igualdade, para o `Paginator` filtrar com `conditions: ['archived' => 0]`,
   N1 forma 2) ou `archived_at TIMESTAMP NULL` (precisa de SQL
   próprio no model).
3. `EV`: `'archived'` em `$columns`, padrão `0` no construtor,
   `archive(): bool` (`update(['archived' => 1])`), `restore()`,
   `isArchived()`, `public static function active(): array` (SQL
   `WHERE archived = 0`, `@return array<static>`) e `archived()`.
4. `EC::destroy`: `$event->archive()` em vez de `safeDestroy()`;
   `index` usa `conditions: ['archived' => 0]` (N1 forma 2) e uma
   rota `GET /admin/events/archived` (O1) com `PUT .../restore`.

Armadilhas:

- `Event::all()` e `findById` do core continuam vendo arquivados;
  cada uso em `app/` precisa decidir. Não altere o core sem pedido.
- `DeleteEventCest` e `ECT:242-261` esperam remoção real: adapte.
- Evento arquivado com Inscrições: as Inscrições continuam; defina a
  regra.

Prova: testes; no navegador, arquivar e restaurar.

### P12. Código aleatório no login (a FVP real da 4.1)

Chance: média-baixa (já foi pedido na 4.1; o padrão pode voltar em
outro lugar, ver P1 e P4). Tempo: 30-40 min.

Pedido real da FVP da 4.1 (29/10): "depois de validar e-mail e senha,
o backend gera um número aleatório de 1000 a 9999 e o devolve; o
usuário informa esse número e só então o login é concluído; o foco é a
validação do login pelo código". Não estava no catálogo da 4.1; a
lição é que o professor pede uma feature pequena com **valor gerado no
servidor e validado no servidor**.

Arquivos: `app/Controllers/AuthenticationsController.php:19-32`
(`authenticate`: `:24` valida a senha, `:25` chama `Auth::login`, `:27`
redireciona), `lib/Authentication/Auth.php:9-13` (`login`), `ROUTES:13-15`,
`app/views/authentications/new.phtml`, `US:19-20` (`$columns`),
`SCH:7-17`, `tests/Integration/Controllers/AuthenticationsControllerTest.php:32-54`
(`test_authenticate_with_valid_credentials_should_login` espera
`Auth::check()` verdadeiro e `Location: /dashboard`),
`tests/Acceptance/authentications/LoginCest.php:31-38` e
`tests/Support/Helper/LoginHelper.php:11-19` (`login()` clica em
"Entrar" e os Cests esperam `/dashboard`), `ACC:67-78` (`loggedClient`
espera 302 para `/dashboard` logo após o POST).

Passos (versão com o código no model, a mais defensável):

1. Testes em `AuthenticationsControllerTest`: senha certa ->
   `Auth::check()` **falso**, `Location: /login/code`, e
   `User::findByEmail(...)->login_code` entre 1000 e 9999; `POST
   /login/code` com o código certo -> `Auth::check()` verdadeiro e
   `Location: /dashboard`; errado -> falso, `Location: /login/code`;
   sem código pendente -> `Location: /login`.
2. `SCH:7-17`: `login_code SMALLINT UNSIGNED NULL, login_code_expires_at TIMESTAMP NULL`;
   `US:20`: as duas colunas em `$columns` e `@property`.
3. `US`:

```php
public function generateLoginCode(): int
{
    $code = random_int(1000, 9999);
    $this->update([
        'login_code' => $code,
        'login_code_expires_at' => date('Y-m-d H:i:s', time() + 300),
    ]);

    return $code;
}

public function verifyLoginCode(string $code): bool
{
    if ($this->login_code === null || $this->login_code_expires_at === null) {
        return false;
    }

    if (strtotime($this->login_code_expires_at) < time() || !ctype_digit($code)) {
        return false;
    }

    if ((int) $code !== (int) $this->login_code) {
        return false;
    }

    $this->update(['login_code' => null, 'login_code_expires_at' => null]);

    return true;
}
```

4. `AuthenticationsController::authenticate` (`:24-27`): no lugar de
   `Auth::login($user)`, `$code = $user->generateLoginCode();`
   `$_SESSION['pending_user_id'] = $user->id;` e
   `FlashMessage::success("Seu código de acesso é {$code}")` (é assim
   que "o backend devolve o número" sem e-mail), `redirectTo(route('users.code'))`.
5. Rotas públicas (fora de `auth`), ao lado de `:13-15`:
   `Route::get('/login/code', [AuthenticationsController::class, 'code'])->name('users.code');`
   `Route::post('/login/code', [AuthenticationsController::class, 'verifyCode'])->name('users.verify_code');`
6. `code()`: sem `pending_user_id` -> `redirectTo(route('users.login'))`;
   senão `render('authentications/code', ['title' => 'Código de acesso'])`
   com `<input name="code" inputmode="numeric" maxlength="4">`.
7. `verifyCode(Request $request)`: `$user = User::findById((int) ($_SESSION['pending_user_id'] ?? 0));`
   `$code = $request->getParam('code'); $code = is_string($code) ? trim($code) : '';`
   certo -> `unset($_SESSION['pending_user_id'])`, `Auth::login($user)`,
   `success('Login realizado com sucesso!')`, `redirectTo(route('dashboard.index'))`;
   errado -> `danger('Código inválido!')`, `redirectTo(route('users.code'))`.
8. Testes existentes: `AuthenticationsControllerTest:32-42` passa a
   esperar `/login/code`; `LoginHelper::login()` precisa ler o código
   da página (`$page->grabTextFrom('.alert-success')` + regex `/\d{4}/`)
   e preencher o form; `ACC:67-78` (`loggedClient`) faz o mesmo em dois
   POSTs. Sem isso todos os Cests e testes de acesso falham.

Armadilhas:

- Fazer `Auth::login` antes do código anula a feature: o login só
  acontece em `verifyCode`.
- Gerar o código na view ou em JavaScript: não é "backend devolve".
- Guardar só na sessão (`$_SESSION['login_code']`) funciona e é mais
  rápido (sem schema), mas "campo gerado e validado no model" é o que
  o professor cobra; cite as duas e justifique.
- Comparação `'1234' == 1234` passa, `===` não: converta.
- Expiração e limite de tentativas são extras; faça só se pedirem.
- `update()` não valida (`MODEL:189`): bom aqui (não exige
  `password_confirmation`).
- O código **não** deve aparecer para quem não acertou a senha.
- Os Cests de login e todos os testes de acesso dependem do fluxo
  antigo: avise que vai ajustar `LoginHelper` e `loggedClient` e faça
  isso logo após a funcionalidade.

Prova: testes; no navegador, logar, ler o código no alerta, errar uma
vez e acertar.

### Q1. Só o dono edita e remove

Chance: média-alta. Tempo: 20-30 min.

Pedido típico: "só o Administrador que criou o Evento pode editar ou
remover" (cenário D8 da 4.1 aplicado a Eventos). Variante: "o `show`
continua aberto a todo admin".

Arquivos: `EC:132-135` (`findEvent`), `EC:137-141` (`eventNotFound`),
`EC:65-111` (edit, update, destroy), `US:52-55` (`events(): HasMany`),
`HASMANY:34-42` (`findById(int): ?Model`), `IDX:28-33` (botões),
`app/Middleware/Admin.php:12-21`, `ECT:17-31` (login do admin no
teste), `ECT:163-168`, `ACC:67-78`, `POP:12-19` (um só admin).

Passos:

1. Teste em `ECT`: crie um segundo admin (cópia de `:22-30`),
   `Auth::login($outro)`, e peça `edit`, `update` e `destroy` de um
   Evento do primeiro: Evento intacto e `Location: /admin/events`
   (`assertSame`, como `:167`).
2. `findEvent` (`EC:132-135`):

```php
private function findEvent(Request $request): ?Event
{
    $event = $this->currentUser()?->events()->findById((int) $request->getParam('id'));

    return $event instanceof Event ? $event : null;
}
```

   Evento alheio vira `null` e cai em "Evento não encontrado!".
   Mensagem própria: compare `(int) $event->user_id` com
   `(int) $this->currentUser()?->id` e use
   `FlashMessage::danger('Você não pode alterar este Evento!')`.
3. O `show` (`EC:31`) também usa `findEvent`: para manter o `show`
   amplo, troque `EC:31` por `Event::findById((int) $request->getParam('id'))`
   e deixe `findEvent` para edit/update/destroy. Pergunte ao professor.
4. `IDX:28-33`: `<?php if ((int) $event->user_id === (int) $this->currentUser()?->id) : ?>`
   em volta de Editar e Remover.

Armadilhas:

- Sem `instanceof`, `phpstan` nível 6 acusa: `findById` devolve
  `?Model` e `findEvent` declara `?Event`.
- O populate tem um admin só (`POP:12-19`): para ver no navegador
  crie outro (cenário G1 da 4.1) ou promova `user@example.com` no
  banco (`UPDATE users SET role = 'admin' WHERE email = 'user@example.com'`).
- Comparar `user_id` sem `(int)`: o PDO devolve string.
- Esconder o botão não protege: teste a URL direta.
- Não confiar em `user_id` do form (`ECT:101-112` já cobre).

Prova: o teste com dois admins; `ACC:112-128` (sem sessão e
Participante) continua passando.

### Q2. Bloquear edição e remoção de Evento passado ou cancelado

Chance: média. Tempo: 15-25 min.

Pedido típico: "Evento que já aconteceu (ou cancelado, P5) não pode
ser editado nem removido".

Arquivos: `EV:84-99` (hoje a regra permite editar Evento passado sem
mudar a data), `EC:65-111`, `EC:32-35` (padrão de *early return*),
`IDX:28-33`, `SHOW:35`, `EVT:499-547` (testes que **garantem** a edição
de Evento passado: `test_past_event_can_be_edited_when_starts_on_does_not_change`
em `:511`), `ECT:191-207`.

Passos:

1. Testes: `EVT`: `isEditable()` falso para passado (use
   `createPastEvent`, `EVT:499-509`) e para cancelado; `ECT`: `update`
   de Evento passado não altera e redireciona com `danger`.
2. `EV`:

```php
public function isEditable(): bool
{
    return !$this->isCanceled() && $this->starts_on >= date('Y-m-d');
}
```

   (`isCanceled()` de P5; sem P5, só a data).
3. `EC` edit/update/destroy, logo após o `findEvent`:
   `if (!$event->isEditable()) { FlashMessage::danger('Evento encerrado não pode ser alterado!'); $this->redirectTo(route('admin.events.show', ['id' => $event->id])); return; }`
4. `IDX:28-33` e `SHOW:35`: botões só se `isEditable()`.

Armadilhas:

- Contradiz a decisão atual (`EV:84-87`: Evento passado pode ter
  descrição corrigida) e os testes `EVT:511-547`: remova ou inverta
  esses testes com o professor ciente.
- Comparação de datas `Y-m-d` como string funciona só com o formato
  garantido pelo banco (`DATE`).
- Regra no controller em vez do model: coloque a decisão em
  `isEditable()` e deixe o controller só perguntar.
- O populate não tem Evento passado: crie via `db:console`
  (`UPDATE events SET starts_on = '2020-01-01' WHERE id = 16`).

Prova: testes; no navegador, o Evento 16 "passado" perde os botões e a
URL direta `/admin/events/16/edit` redireciona.
