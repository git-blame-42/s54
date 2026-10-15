# D3. CRUD mínimo de um recurso novo (events)

Pedido, como o professor faria na hora: "Criem um cadastro de eventos
do usuário logado: listar, ver, criar, editar e excluir, com validação."

Chance: média (é o tema da Entrega 4.2). Tempo estimado: 40 min para o
esqueleto. Catálogo: `fvp-possiveis-modificacoes.md`, seção D3.

Branch no clone: `fvp/d3-crud-events`. `main` continua igual ao
repositório público (`86c81ca`).

Este cenário tem TRÊS commits de propósito: a solução se aplica em
partes (schema e model -> rotas, controller e views -> testes) e o
README mostra o cherry-pick de um intervalo.

| Commit | Mensagem |
|--------|----------|
| `5293195` | `feat: Add events table and Event model` |
| `34a4191` | `feat: Add events CRUD for authenticated users` |
| `8f658a5` | `test: Cover events CRUD with controller, access and acceptance tests` |

Total: 18 arquivos, +873 linhas.

## O que mudou

Commit 1, banco e model:

- `database/schema.sql`: tabela `events` (`id`, `title`, `starts_at`
  DATETIME, `user_id` com `FOREIGN KEY ... REFERENCES users(id) ON DELETE
  CASCADE`, `created_at`, `updated_at`). `DROP TABLE IF EXISTS events`
  vem antes do `DROP` de `users`.
- `app/Models/Event.php` (novo): `$table`, `$columns = ['title',
  'starts_at', 'user_id']`, `user(): BelongsTo`, `validates()` com
  `notEmpty` em `title` e `starts_at`, e dois formatadores da data
  (`startsAtFormatted()` para a tela, `startsAtForInput()` para o
  `datetime-local`).
- `app/Models/User.php`: `events(): HasMany` e `@property Event[] $events`.
- `tests/Unit/Models/Events/EventTest.php` (novo, 11 testes) e
  `UserTest` (+2: relação e `events()->new()`).

Commit 2, rotas, controller e views:

- `config/routes.php`: oito rotas RESTful dentro do grupo `auth`
  (`events.index`, `events.paginate`, `events.new`, `events.create`,
  `events.show`, `events.edit`, `events.update`, `events.destroy`).
  `/events/new` declarada antes de `/events/{id}`.
- `app/Controllers/EventsController.php` (novo): sete ações finas. Tudo
  passa por `$this->current_user->events()` (`paginate`, `new`,
  `findById`), então um usuário nunca vê ou edita evento de outro:
  `findEvent()` responde `HTTPException` 404. `eventParams()` lista os
  campos aceitos (`title`, `starts_at`); `user_id` vindo do POST é
  ignorado.
- `app/views/events/_form.phtml` (parcial usada por `new` e `edit`:
  `novalidate`, `is-invalid` + `invalid-feedback` com
  `$event->errors(...)`, valores mantidos no re-render), `index.phtml`
  (tabela, botão "Excluir" com `_method=DELETE`, paginação), `show.phtml`,
  `new.phtml`, `edit.phtml` (`_method=PUT`).
- `app/views/layouts/_navbar.phtml`: link "Eventos".
- `database/Populate/populate.php`: três eventos para `user@example.com`.

Commit 3, testes:

- `tests/Integration/Controllers/EventsControllerTest.php` (13 testes:
  as sete ações, criar/atualizar inválido, paginação, 404 para evento de
  outro usuário, `user_id` do POST ignorado).
- `tests/Integration/Access/EventsAccessTest.php` (8 casos por
  `#[DataProvider]`: cada rota sem sessão -> 302 `/login`).
- `tests/Acceptance/events/EventCest.php` (8 cenários: sem login, criar
  inválido, criar válido, listar só os meus, ver, editar válido e
  inválido, excluir).
- `tests/Integration/Controllers/ControllerTestCase.php`: helper
  `delete()` (o mesmo que A2 e D2 acrescentam).

## Por que assim

- Rotas RESTful com nomes (`new/create/index/show/edit/update/destroy` e
  `GET /events/page/{page}`), iguais ao `problem-track/config/routes.php`
  citado na rubrica 4.2 (`grading-path.md`).
- Rubrica 4.2 pede `save`, `all`, `findById`, `where`, `Validations` e
  `Paginator`: o model e os testes usam todos; a listagem é paginada com
  `HasMany::paginate`, que já filtra por `user_id`.
- Controller fino: regra de negócio no model (`validates`) e na relação
  (`events()`); nada de SQL no controller.
- Parâmetros só pelo `Request` (`getParam`), lista explícita de campos
  (nunca `new Event($params)` direto), feedback 2025-2.
- Formulário com `novalidate`, erro por campo e valores preservados no
  re-render; flash de sucesso e de erro, como a rubrica 4.2 exige na demo
  "criar inválido / criar válido / editar inválido / editar válido".
- Tabela e colunas em inglês, plural (`events`, `user_id`, `starts_at`);
  FK com `ON DELETE CASCADE` (apagar usuário apaga eventos; coberto no
  teste `test_destroying_user_should_cascade_events`). Note que o
  `REFERENCES` escrito dentro da coluna, como no problem-track, é ignorado
  pelo MySQL; use `CONSTRAINT ... FOREIGN KEY`.
- Testes nas quatro camadas (unit, controller, acesso, aceitação), nomes
  que descrevem o comportamento, commits em inglês `type: Title`.
- PSR-12, PHPStan nível 6 e PSR-4 sem avisos.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # o schema mudou
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 85 testes, Integration 30, Acceptance 17 (eram
71 / 9 / 9 na base). Testes novos: `EventTest` (11), `UserTest` (+2),
`EventsControllerTest` (13), `EventsAccessTest` (8), `EventCest` (8).

No navegador: `http://localhost`, logue como `user@example.com` /
`123456`, clique em "Eventos": três eventos do populate. "Novo Evento"
vazio -> erros "não pode ser vazio!" nos dois campos; preenchido ->
"Evento criado com sucesso!" e a linha na tabela. "Editar" -> altere o
título -> "Evento atualizado com sucesso!". "Excluir" -> "Evento removido
com sucesso!". Abra `/events/999` -> 404. Logue como
`admin@example.com`: a lista vem vazia (eventos são por usuário).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/d3-crud-events` (relativo ao clone do s50).

São três commits em sequência. Pegue os três, nesta ordem. Formas
equivalentes de dizer "do primeiro ao terceiro":

```
git cherry-pick 5293195^..8f658a5      # intervalo: do pai do 1º até o 3º
git cherry-pick 5293195 34a4191 8f658a5 # os três, explícitos, na ordem
git cherry-pick main..fvp-d3           # tudo que a branch tem além da main
```

O `^` depois do primeiro SHA é obrigatório no intervalo: `A..C` exclui
o `A`. Cada cherry-pick cria commits novos (SHAs diferentes, mesma
mensagem e mesmo conteúdo).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d3 <caminho>/projeto
git fetch fvp-d3 fvp/d3-crud-events
git cherry-pick 5293195^..8f658a5
git remote remove fvp-d3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/d3-crud-events:fvp-d3
git cherry-pick 5293195^..8f658a5
git branch -D fvp-d3           # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito e a árvore
final é idêntica à da branch do bundle.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch       # aplica os 3 commits, na ordem
git apply --3way <caminho>/solucao.patch   # só o diff, sem commits
```

Testado: `git am` aplica os três limpo.

### Pegar só uma parte

- Só o 1º (`5293195`): tabela, model e testes unitários. Útil se o
  professor pedir "só o model com testes". Funciona sozinho.
- Só o 2º (`34a4191`): aplica sem conflito, mas quebra em execução
  (`Class "App\Models\Event" not found`): as rotas e o controller
  dependem do model do 1º. Nunca pegue o 2º sem o 1º.
- 1º + 2º sem o 3º: funcionalidade completa sem testes de controller,
  acesso e aceitação. Na rubrica, vale menos que com testes.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `config/routes.php`: qualquer cenário que mexe nas rotas (A2, A3, A5,
  A6, A7, B6, B7, D1, D2). Resolução: manter as duas listas de rotas;
  o bloco de events fica dentro de `auth`.
- `app/views/layouts/_navbar.phtml`: A6, A7, B6, C2, D1 (links novos).
  Mantenha todos os `<li>`.
- `database/schema.sql`: A7 (ENUM), B3 (`phone`), B5 (`active`).
  Mantenha as colunas novas em `users` e acrescente a tabela `events`.
- `database/Populate/populate.php`: A7, B3, B5, G1, G2. Mantenha os
  usuários dos dois lados; os eventos usam `$user`.
- `app/Models/User.php`: A7, B1, B2, B3, B5, B6, D4 (métodos novos).
  Mantenha `events()` junto com o que o alvo já tem.
- `tests/Integration/Controllers/ControllerTestCase.php`: A2 e D2
  acrescentam o mesmo `delete()`. Se o alvo já tem, fique com uma cópia.
- `tests/Unit/Models/Users/UserTest.php`: vários cenários adicionam
  testes no fim do arquivo; mantenha todos.

Depois de aplicar: `./run db:reset && ./run db:populate` (o schema
mudou), depois testes, `./run phpcs` e `./run phpstan`.

## Armadilhas

- Mudou o `schema.sql` e esqueceu `./run db:reset`: `Table 'events'
  doesn't exist` só no navegador; os testes passam porque migram o
  schema a cada `setUp`.
- `/events/new` depois de `/events/{id}`: o Router casa `new` como `id`
  e a página de novo evento vira 404. A ordem no `routes.php` importa.
- `REFERENCES users(id)` inline na coluna não cria FK no MySQL. Sem FK,
  apagar usuário deixa eventos órfãos e o teste de cascade falha.
- `datetime-local` envia `2026-10-20T19:30`; o MySQL aceita o `T` ao
  gravar e devolve `2026-10-20 19:30:00`. Por isso o model tem os dois
  formatadores. No Codeception, `fillField` não preenche esse input no
  Chrome; o `EventCest` usa `executeJS` para atribuir o valor.
- `HasMany::findById` devolve `?Model`; para o PHPStan 6 anote
  `/** @var Event|null $event */` (ou `Event $event`) onde usar
  propriedades de `Event`.
- PHPUnit 11: `@dataProvider` em docblock gera "PHPUnit Deprecations";
  use o atributo `#[DataProvider('metodo')]`.
- Paginação: `HasMany::paginate` monta o nome da rota como
  `events.paginate` a partir da tabela; se a rota tiver outro nome,
  passe `route:`.
- `./run test` e `./run test:browser` usam o banco `eventos_test`; o que
  você cadastra no navegador fica em `eventos_development` e vice-versa.
