# D8. Só o dono vê e edita (prévia 4.2)

Pedido, como o professor faria na hora: "Provem que um usuário não
consegue ver, editar, atualizar nem apagar o evento de outro usuário."

Chance: baixa hoje, alta na 4.2. Tempo estimado: 10 min por ação.
Catálogo: `fvp-possiveis-modificacoes.md`, seção D8.

Branch no clone: `fvp/d8-so-dono-ve-edita`. `main` continua igual ao
repositório público (`86c81ca`).

Este cenário é EMPILHADO no D3 (`d3-crud-events`, CRUD de `events`):
os três primeiros commits são os do D3, trazidos por fast-forward (mesmo
SHA); o quarto é o do D8.

| Commit | Mensagem |
|--------|----------|
| `5293195` | `feat: Add events table and Event model` (D3) |
| `34a4191` | `feat: Add events CRUD for authenticated users` (D3) |
| `8f658a5` | `test: Cover events CRUD with controller, access and acceptance tests` (D3) |
| `30a59e0` | `test: Prove event ownership rules` (D8) |

O commit do D8 tem 3 arquivos, +130/-9 linhas, só testes.

## O que mudou

O código do D3 já segue o padrão que o catálogo descreve: o
`EventsController` busca o evento em `findEvent()` com
`$this->current_user->events()->findById((int) $request->getParam('id'))`
e lança `HTTPException('Evento não encontrado', 404)` quando volta
`null`. `show`, `edit`, `update` e `destroy` passam todos por esse método;
`index` usa `current_user->events()->paginate(...)`; `new` e `create`
usam `current_user->events()->new(...)` (o `user_id` do POST é
ignorado). Não há `Event::findById` global no controller. Por isso o
commit do D8 é `test:` e não muda código de produção: o trabalho é
PROVAR a regra em cada ação.

- `tests/Unit/Models/Users/UserTest.php`: +1 teste,
  `test_events_find_by_id_should_return_null_for_another_user_event`
  (a relação `events()->findById` acha o meu, devolve `null` para o de
  outro, e o de outro existe no banco).
- `tests/Integration/Controllers/EventsControllerTest.php`: +3 testes
  (`edit`, `update`, `destroy` de evento alheio) e dois auxiliares
  privados: `createAnotherUserEvent()` (segundo usuário com evento
  próprio) e `catchHttpException()` (captura a exceção para o teste
  conferir o banco depois, o que `expectException` não deixa). O teste
  de `show` que o D3 já tinha passou a usar o auxiliar.
- `tests/Acceptance/events/EventCest.php`: +2 cenários
  (`cannotSeeAnotherUserEvent`, `cannotEditAnotherUserEvent`) e o
  auxiliar `createAnotherUser()`.

Tabela ação × proteção × teste:

| Ação | Proteção | Teste |
|------|----------|-------|
| `index` | `current_user->events()->paginate()` | `EventsControllerTest::test_index_should_list_only_the_user_events` (D3); `EventCest::listOnlyMyEvents` (D3) |
| `new` / `create` | `current_user->events()->new()`; `eventParams()` descarta `user_id` | `test_create_should_ignore_user_id_from_params` (D3); `UserTest::test_events_new_should_set_user_id` (D3) |
| `show` | `findEvent()` → 404 | `test_show_should_respond_404_for_another_user_event` (D3); `EventCest::cannotSeeAnotherUserEvent` (D8) |
| `edit` | `findEvent()` → 404 | `test_edit_should_respond_404_for_another_user_event` (D8); `EventCest::cannotEditAnotherUserEvent` (D8) |
| `update` | `findEvent()` → 404 antes de ler o formulário | `test_update_should_respond_404_and_keep_another_user_event_intact` (D8): título, data e dono iguais depois |
| `destroy` | `findEvent()` → 404 antes do `destroy()` | `test_destroy_should_respond_404_and_keep_another_user_event` (D8): evento continua no banco |
| (base) | `HasMany::findById` filtra por `user_id` e `id` | `UserTest::test_events_find_by_id_should_return_null_for_another_user_event` (D8) |
| sem sessão | middleware `auth` → 302 `/login` | `EventsAccessTest` (D3, 8 rotas) |

## Por que assim

- Padrão do problem-track citado no catálogo D8 e na rubrica 4.2
  (`grading-path.md`): `current_user->events()->findById($id)` e
  `HTTPException('Evento não encontrado', 404)`; nada de buscar pelo id
  global e comparar o dono depois.
- 404 e não 403: não revela que o id existe para outro usuário.
- Controller fino: a regra mora na relação `HasMany`; o controller só
  decide o 404.
- Testes nas três camadas: unit (relação), controller (cada ação, com o
  banco conferido depois da tentativa), aceitação (navegador logado como
  outro usuário).
- `ControllerTestCase` relança a exceção (catálogo D8); por isso o teste
  de controller espera `HTTPException`, e o de aceitação vê a página de
  erro do `ErrorsHandler` (`<h1>Evento não encontrado</h1>`).
- Nomes `test_<acao>_should_<resultado>`, textos pt-BR, PSR-12, PHPStan
  nível 6 (`/** @var Event $found */` para acessar `title` no resultado
  de `findById`), commit em inglês `test: Prove event ownership rules`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # o D3 cria a tabela events
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 86 testes, Integration 33, Acceptance 19 (eram
71 / 9 / 9 na base `86c81ca` e 85 / 30 / 17 no D3). Testes novos:

- `UserTest::test_events_find_by_id_should_return_null_for_another_user_event`
  (`tests/Unit/Models/Users/UserTest.php`).
- `EventsControllerTest::test_edit_should_respond_404_for_another_user_event`,
  `test_update_should_respond_404_and_keep_another_user_event_intact`,
  `test_destroy_should_respond_404_and_keep_another_user_event`
  (`tests/Integration/Controllers/EventsControllerTest.php`).
- `EventCest::cannotSeeAnotherUserEvent`,
  `EventCest::cannotEditAnotherUserEvent`
  (`tests/Acceptance/events/EventCest.php`).

No navegador: `http://localhost`, entre como `user@example.com`, abra
"Eventos" e anote o id de um evento. Saia, entre como outro usuário
(`admin@example.com`, que não tem eventos no populate) e abra
`/events/<id>` e `/events/<id>/edit`: a página mostra "Evento não
encontrado" (status 404) e não mostra o título.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/d8-so-dono-ve-edita`.

Primeiro veja se o alvo já tem o D3 (`git log --oneline | grep "events
CRUD"`). Se tem, pegue só `30a59e0`. Se não tem, pegue os quatro, na
ordem (intervalo `main..fvp/d8-so-dono-ve-edita`).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-d8 <caminho>/projeto
git fetch fvp-d8 fvp/d8-so-dono-ve-edita

git cherry-pick 30a59e0                                    # alvo já tem o D3
git cherry-pick main..fvp-d8/fvp/d8-so-dono-ve-edita       # alvo sem o D3: os quatro

git remote remove fvp-d8
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/d8-so-dono-ve-edita:fvp-d8
git cherry-pick 30a59e0        # alvo já tem o D3
git cherry-pick main..fvp-d8   # alvo sem o D3: aplica os quatro commits
git branch -D fvp-d8           # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick 5293195^..30a59e0`
aplica os quatro sem conflito, e `git cherry-pick 5293195 34a4191
8f658a5` seguido de `git cherry-pick 30a59e0` (simula alvo que já tem o
D3) também. O cherry-pick cria commits novos; o SHA no seu repositório
vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

O patch contém os quatro commits (formato mbox). Em alvo sem o D3:

```
git am <caminho>/solucao.patch       # cria os quatro commits, mesmas mensagens
git apply --3way <caminho>/solucao.patch   # só o diff, sem commit
```

Em alvo que já tem o D3, os três primeiros patches falham ("already
exists in index"). Pule-os com `git am --skip` três vezes, ou gere um
patch só do D8 no clone `projeto/`:
`git format-patch -1 30a59e0 --stdout > d8.patch` e aplique esse.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `tests/Integration/Controllers/EventsControllerTest.php` e
  `tests/Acceptance/events/EventCest.php`: se o alvo tem um D3 próprio
  (escrito na hora, não o deste diretório), os arquivos diferem. Nesse
  caso copie só os métodos novos e os auxiliares, ajustando nomes de
  usuário e textos à versão do alvo.
- `tests/Unit/Models/Users/UserTest.php`: vários cenários (B1, B5, B6,
  B9, F4, D3) acrescentam testes no fim da classe. Mantenha os dois
  lados; o teste do D8 usa `$this->user2`, que já existe no `setUp` da
  base.
- Se o alvo tiver mudado a mensagem do 404 (por exemplo, com um
  `findEvent` diferente), troque `'Evento não encontrado'` nos testes
  pela mensagem real.

Depois de aplicar: `./run db:reset` se o D3 veio junto (tabela
`events`); rode os testes e o `./run phpcs` de novo.

## Armadilhas

- `expectException` encerra o teste no lançamento: nada depois da
  chamada roda. Para provar que o evento continuou intacto, capture a
  exceção (`catchHttpException()`) e confira o banco depois.
- `findById` da relação devolve `?Model`; acessar `->title` direto
  quebra o PHPStan nível 6 (`property.notFound`). Use
  `/** @var Event $found */`.
- Se o controller do alvo usar `Event::findById($id)` global, os testes
  de `show/edit/update/destroy` alheios falham (renderizam o evento ou
  apagam). A correção é a do catálogo:
  `$this->current_user->events()->findById($id)` + `HTTPException` 404,
  num método privado usado pelas quatro ações; aí o commit vira
  `feat: Restrict events to their owner`.
- Na aceitação não dá para mandar PUT/DELETE sem formulário; a prova de
  `update`/`destroy` fica no teste de controller, e a aceitação cobre
  `show` e `edit` (a tela de edição alheia nem renderiza o formulário).
- O `ErrorsHandler` mostra trace na página de erro; serve para a
  demonstração, mas o professor pode pedir uma página 404 amigável.
