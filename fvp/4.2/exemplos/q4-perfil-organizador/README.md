# Q4. Perfil Organizador

Pedido, como o professor faria na hora: "Crie o perfil Organizador. Ele
entra numa área própria, `/organizer/events`, e faz o CRUD só dos
Eventos que ele criou. Não entra na Área Administrativa, e o
Administrador não entra na área dele. No login, o Organizador vai
direto para os Eventos dele."

Chance: média. Tempo estimado: 35-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, Q4.

Branch no clone: `fvp/q4-perfil-organizador`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                            |
|-----------|-----------------------------------------------------|
| `b6e1df0` | `feat: Add organizer role with its own events area` |

## O que mudou

- `database/schema.sql`: `role ENUM('admin', 'organizer',
  'participant')`.
- `app/Models/User.php`: `isOrganizer()`. Não há constante de perfis
  no model (nem validação de `role`), então nada mais muda nele.
- `app/Middleware/Organizer.php` (novo): cópia do `Admin`; usuário
  logado que não é Organizador recebe "Acesso restrito a
  organizadores" e vai para `/dashboard`. Sem usuário, não faz nada:
  quem manda para `/login` é o `Authenticate`.
- `config/App.php`: alias `'organizer' => Organizer::class`.
- `config/routes.php`: grupo `Route::middleware('organizer')`
  **dentro** do grupo `auth`, com as 8 rotas `organizer.events.*`
  (`/organizer/events`, `/page/{page}`, `/new` antes de `{id}`,
  `PUT`/`DELETE` em `{id}`).
- `app/Controllers/Admin/EventsController.php` (refatoração sem mudar
  o comportamento do admin):
  - `ROUTE_PREFIX = 'admin.events'` e `eventRoute($acao, $params)`,
    usado nos redirects e nas views;
  - ganchos `protected`: `paginateEvents()`, `newEvent()` e
    `findEvent()` (o admin continua vendo todos os Eventos).
- `app/Controllers/Organizer/EventsController.php` (novo): estende o
  do admin e troca só o prefixo (`organizer.events`) e os ganchos,
  que usam `currentUser()->events()`: `paginate(...)`, `new()` e
  `findById()` + `instanceof Event`. Evento de outro usuário vira
  "Evento não encontrado!".
- Views de `app/views/admin/events/` reaproveitadas pelos dois:
  `route('admin.events.x', ...)` virou `$this->eventRoute('x', ...)`
  (a view roda dentro do controller, então `$this` é ele).
- `AuthenticationsController::authenticate`: Organizador vai para
  `/organizer/events`; os demais perfis, para `/dashboard` como antes.
- `_navbar.phtml`: link "Meus Eventos" para o Organizador.
- `populate.php`: `organizador@example.com` (senha `123456`, perfil
  `organizer`), dono dos Eventos 10 e 13 (sem Inscrições). Continuam
  16 Eventos, 14 Inscrições e o Evento 1 lotado; o Administrador
  segue vendo os 16.
- Testes:
  - `UserTest` (2): `isOrganizer()` falso por padrão; verdadeiro só
    para `organizer` (gravado com `save()`, conferindo o ENUM).
  - `AuthenticationsControllerTest` (1): login do Organizador →
    `Location: /organizer/events`.
  - `Organizer\EventsControllerTest` (novo, 9): listagem e
    paginação só dos dele com links `/organizer/...`; form posta em
    `/organizer/events`; `create` grava o Organizador como dono
    (ignora `user_id` do form); erro de validação; `show`, `edit`,
    `update` e `destroy` dos próprios; Evento do admin redireciona e
    não muda.
  - `OrganizerEventsAccessTest` (novo, por HTTP): provider com as 8
    rotas; sem sessão → `/login`; Participante e Administrador →
    `/dashboard`; Organizador acessa os dele; Organizador não entra
    em `/admin`; login redireciona para `/organizer/events`.
  - `OrganizerEventsCest` (novo, 3): login e listagem só dos dele,
    cadastro de Evento, `/admin/events` barrado.

## Por que assim

- Herança em vez de copiar o controller: o CRUD (validação, flash,
  `fill`, P02/P03) é o mesmo; o que muda é de onde vêm os Eventos e o
  prefixo das rotas. Cópia de 150 linhas divergiria na primeira
  correção.
- Restrição pela relação (`events()->findById`), como Q1 e O5: o
  Evento alheio nem é carregado, e a resposta não revela que existe.
- Administrador fora de `/organizer`: ele já tem o CRUD completo de
  todos os Eventos em `/admin/events`; a área do Organizador é para
  quem só gerencia os próprios. Organizador fora de `/admin` vem de
  graça do middleware `Admin`.
- O grupo `organizer` dentro do `auth` (ordem dos middlewares): os
  dois middlewares de perfil não tratam "sem login" e contam com o
  `Authenticate` antes (A17 da 4.1).

## Como verificar

```
./run up -d
# refaz o schema (o populate roda migrate()) e popula
./run db:populate
# classes novas (middleware e controller do Organizador)
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium
./run test:browser
```

Resultado obtido: Unit 161 testes, Integration 74, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; `db:populate` sem erro.

No navegador: login com `organizador@example.com` / `123456` cai em
`/organizer/events` com 2 Eventos ("Mostrando 1 - 2 de 2") e o link
"Meus Eventos"; `/admin/events` volta ao painel com "Acesso restrito
a administradores". Com `admin@example.com`, `/organizer/events` volta
com "Acesso restrito a organizadores" e `/admin/events` mostra os 16.

## Relação com outros cenários

- Q1 (`q1-so-dono-edita-remove`) e O5 (`o5-admin-lista-so-proprios`):
  mesma restrição por `events()->findById` + `instanceof`, aqui só no
  controller do Organizador. Com O5 aplicado, o admin também passa a
  ver só os dele; aí o `paginateEvents`/`findEvent` do admin recebem a
  mudança do O5 e o do Organizador pode até herdar sem sobrescrever.
- A7 (4.1, perfis e área administrativa): mesmo padrão de perfil no
  ENUM `role`, método `isX()` e middleware com alias.
- T1 (`t1-populate-campo-eventos-admin`): também cria usuário novo no
  populate; mantenha os dois.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/q4-perfil-organizador`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/q4-perfil-organizador`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-q4 \
    s50/fvp/4.2/exemplos/q4-perfil-organizador/projeto
git fetch fvp-q4 fvp/q4-perfil-organizador
git cherry-pick b6e1df0
git remote remove fvp-q4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/q4-perfil-organizador/solucao.bundle \
    fvp/q4-perfil-organizador:fvp-q4
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick b6e1df0
# opcional, limpa a ref
git branch -D fvp-q4
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/q4-perfil-organizador/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/q4-perfil-organizador/solucao.patch
```

Depois de aplicar: `./run db:populate` (schema mudou),
`./run composer dump-autoload -o --strict-psr` (classes novas), os
testes e o `./run phpcs`.

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
# ou: git am --continue
git cherry-pick --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `Admin/EventsController.php`: quase todo cenário mexe em `index`,
  `create`, `findEvent` ou nos redirects. Mantenha os ganchos
  (`paginateEvents`, `newEvent`, `findEvent`) e o `eventRoute()`; a
  lógica do outro cenário entra dentro deles.
- Views `admin/events/*.phtml`: outro cenário que acrescente link com
  `route('admin.events.x')` deve usar `$this->eventRoute('x')`, senão
  o Organizador é mandado para `/admin` (e barrado).
- `config/routes.php`, `_navbar.phtml`, `populate.php`: rotas, links
  e usuários de outros cenários; fique com os dois.

## Armadilhas

- Grupo `organizer` fora do `auth`: sem login, o `Organizer` não
  redireciona (`Auth::user()` é `null`) e a ação roda sem usuário.
- Esquecer o alias em `config/App.php`: "middleware não encontrado"
  em todas as rotas do grupo.
- ENUM sem `organizer`: `save()` falha com `Data truncated` (o model
  não valida `role`).
- Reaproveitar as views sem trocar `route('admin.events...')`: os
  links do Organizador apontam para `/admin` e o middleware barra.
- Métodos `private` no controller do admin não podem ser
  sobrescritos: os ganchos precisam ser `protected`, e a constante
  lida com `static::`, não `self::`.
- `HasMany::paginate` usa `per_page` (com `_`) como nome do
  argumento.
- Esquecer o `dump-autoload -o` depois de criar as classes.
