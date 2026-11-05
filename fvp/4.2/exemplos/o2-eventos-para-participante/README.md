# O2. Listagem e detalhe de Eventos para o Participante

Pedido, como o professor faria na hora: "Hoje só o Administrador vê os
Eventos. Crie `/events` e `/events/{id}` para qualquer usuário logado
ver a lista (paginada) e o detalhe, sem os botões de Novo, Editar e
Remover. Coloque um link na navbar."

Chance: média-alta. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O2.

Branch no clone: `fvp/o2-eventos-para-participante`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit | Mensagem |
|--------|----------|
| `9d7c82f` | `feat: Add events listing and details for participants` |

## O que mudou

- `config/routes.php`: três rotas novas no grupo `auth`, fora do grupo
  `admin`: `events.index` (`GET /events`), `events.paginate`
  (`GET /events/page/{page}`) e `events.show` (`GET /events/{id}`).
  `page/{page}` vem antes de `{id}`.
- `app/Controllers/EventsController.php` (novo, namespace
  `App\Controllers`, não `Admin`): `index` reaproveita
  `Event::paginate(..., route: 'events.paginate')` com 5 por página;
  `show` usa `Event::findById` e, se não achar, flash "Evento não
  encontrado!" e redireciona para `/events`.
- `app/views/events/index.phtml` e `show.phtml` (novas): cópia das
  views do admin sem "Novo Evento", "Editar" e "Remover"; o único botão
  da listagem é "Ver" e o "Voltar" do detalhe aponta para `/events`.
- `app/views/layouts/_navbar.phtml`: no `if isAdmin()` entra um
  `else` com o link "Eventos" para `/events`. O Administrador continua
  com o "Eventos" dele (`/admin/events`), sem dois links iguais.
- Testes novos:
  - `tests/Integration/Controllers/EventsControllerTest.php` (4):
    paginação de 5 sem ações de admin, "1/10" e link para o detalhe,
    detalhe sem "Editar", id inexistente → `Location: /events`.
  - `tests/Integration/Access/ParticipantEventsAccessTest.php` (9):
    sem sessão → `/login` nas 3 rotas, Participante → 200 nas 3,
    Administrador também acessa, navbar do Participante com
    `href="/events"` e sem `/admin/events`, id inexistente → `/events`.
  - `tests/Acceptance/events/BaseParticipantEventsCest.php`: mesma
    massa do `BaseEventsCest`, mas faz `/logout` e entra como
    `participante1@example.com`.
  - `tests/Acceptance/events/ParticipantListEventsCest.php` (3):
    navbar → listagem → página 2; "Ver" → detalhe → "Voltar";
    `/admin/events` continua barrado (vai para `/dashboard`).

## Por que assim

- Controller separado do `Admin\EventsController`: o grupo `admin`
  protege o controller inteiro pelo middleware; misturar ações de
  Participante ali obrigaria a tirar a proteção ou duplicar rotas.
- `Event::paginate` com `route: 'events.paginate'`: o `_pages.phtml`
  monta os links com `$paginator->getRouteName()`. Sem trocar a rota,
  os links da paginação levariam o Participante para `/admin/...`.
- Views próprias em vez de `if isAdmin()` nas do admin: o Participante
  nunca recebe HTML com links de edição, e os testes do admin ficam
  intactos.
- Controller fino: só `Request::getParam`, `FlashMessage`,
  `redirectTo(route(...))` e `render` com `$title` no `compact`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
# classe nova: confira o PSR-4
./run composer dump-autoload -o --strict-psr
```

Resultado obtido: Unit 159 testes, Integration 58, Acceptance 26 (eram
159 / 45 / 23 na base). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela: entre como `user@example.com` / `123456`, clique em "Eventos"
na navbar. Sem porta no host:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=user@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c http://localhost/events
```

## Relação com outros cenários

- P2 (`p2-inscricao-participante`) é feito sobre este: o botão
  "Inscrever-se" entra em `events/show.phtml`. P3, P7 e P9 vêm
  depois do P2.
- P1/P4 (código de check-in e confirmação de presença, agente B) também
  usam a área do Participante; se aplicados juntos, o `show` deste
  cenário é o lugar natural do formulário de presença do P4.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/o2-eventos-para-participante`.

Pré-requisito: o repositório alvo descende de `f31006b` (CRUD de
Eventos do público). `<caminho>` abaixo:
`s50/fvp/4.2/exemplos/o2-eventos-para-participante`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o2 \
    s50/fvp/4.2/exemplos/o2-eventos-para-participante/projeto
git fetch fvp-o2 fvp/o2-eventos-para-participante
git cherry-pick 9d7c82f
git remote remove fvp-o2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/o2-eventos-para-participante/solucao.bundle \
    fvp/o2-eventos-para-participante:fvp-o2
git cherry-pick 9d7c82f
# opcional, limpa a ref
git branch -D fvp-o2
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/o2-eventos-para-participante/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/o2-eventos-para-participante/solucao.patch
```

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

Arquivos que costumam conflitar e por quê:

- `config/routes.php`: outros cenários também acrescentam rotas no
  grupo `auth` ou no `admin`. Fique com todas; confira só que
  `/events/page/{page}` continua antes de `/events/{id}`.
- `_navbar.phtml`: outro cenário que mexe no `if isAdmin()` (ex.: link
  novo de admin) conflita no `else`. Mantenha os dois ramos.

Depois de aplicar: `./run composer dump-autoload` (classe nova) e
rode os testes e o `./run phpcs` de novo.

## Armadilhas

- Rotas dentro do grupo `admin` barram o Participante (vai para
  `/dashboard`); precisam ficar só no `auth`.
- Esquecer `route: 'events.paginate'` no `paginate`: a página 1
  funciona, mas os links da paginação vão para `/admin/events/page/N`.
- `_pages.phtml` só enxerga `$paginator`; não tente passar variável do
  controller para ele.
- Copiar a view do admin e esquecer o "Voltar": ele aponta para
  `admin.events.index` e devolve o Participante para `/dashboard`.
- Duas classes `EventsController` em namespaces diferentes: no
  `routes.php` use alias (`AdminEventsController`) para não colidir.
- Os testes de acesso usam Guzzle contra `http://web:8080` com
  `allow_redirects => false`; o redirecionamento do middleware é
  `302` com `Location: /login`.
