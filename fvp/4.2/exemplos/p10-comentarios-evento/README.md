# P10. Comentários do Evento (recurso filho 1xN)

Pedido, como o professor faria na hora: "O Participante pode deixar um
comentário no Evento, de até 500 caracteres. A tela do Evento lista os
comentários com o autor e a data. O Administrador também vê (e pode
responder) pelo detalhe da Área Administrativa."

Chance: média. Tempo estimado: 35-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P10. É uma prévia da Entrega 4.3 (relação
1xN).

Branch no clone: `fvp/p10-comentarios-evento`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                       |
|-----------|--------------------------------|
| `fb39589` | `feat: Add comments to events` |

## O que mudou

- `database/schema.sql`: tabela `comments(id, event_id, user_id,
  body, created_at)`, FK `event_id` com `ON DELETE CASCADE` e
  `user_id` com `ON DELETE RESTRICT`; `DROP TABLE IF EXISTS comments`
  no topo, antes de `event_user`/`events`.
- `lib/Validations.php`: `maxLength($attribute, $obj, int $max)`, com
  `mb_strlen` (conta caracteres, não bytes). Mensagem: "deve ter no
  máximo 500 caracteres!".
- `app/Models/Comment.php` (novo): `$columns = ['event_id', 'user_id',
  'body', 'created_at']`; o construtor preenche `created_at` com
  `date('Y-m-d H:i:s')` quando vazio; `validates()` com `notEmpty` em
  `event_id`, `user_id` e `body` e `maxLength('body', 500)`;
  `event()` e `user()` (`BelongsTo`); `authorName()` para a view.
- `app/Models/Event.php`: `comments(): HasMany`; o PHPDoc de
  `safeDestroy()` registra que comentários não bloqueiam a remoção (o
  banco apaga em cascata).
- `config/routes.php`, no grupo `auth` (fora do `admin`):
  - `GET /events/{id}` -> `EventsController::show` (`events.show`)
  - `POST /events/{id}/comments` -> `CommentsController::create`
    (`events.comments.create`)
- `app/Controllers/EventsController.php` (novo, namespace
  `App\Controllers`, não confundir com `Admin\EventsController`): tela
  mínima do Evento para qualquer logado; Evento inexistente volta para
  `/dashboard` com "Evento não encontrado!".
- `app/Controllers/CommentsController.php` (novo): `create` acha o
  Evento, monta o comentário com `$event->comments()->new([...])`,
  `user_id` da sessão (nunca do form), `body` de `comment[body]` só se
  for string (com `trim`). Sucesso: flash "Comentário publicado!".
  Inválido: flash `danger` "Comentário " + `errors('body')`. Redireciona
  o Participante para `/events/{id}` e o Administrador para
  `/admin/events/{id}`.
- Views:
  - `app/views/events/_comments.phtml` (novo, parcial): título
    "Comentários (N)", lista com autor, data `d/m/Y H:i` e
    `nl2br(htmlspecialchars($comment->body))`, form com
    `<textarea name="comment[body]">` e botão "Comentar".
  - `app/views/events/show.phtml` (novo): dados do Evento (cópia do
    show do admin, sem Editar) + parcial.
  - `app/views/admin/events/show.phtml`: inclui a mesma parcial no fim.
- `database/Populate/populate.php`: 5 comentários em 3 Eventos (um
  deles é resposta do Administrador).
- Testes: `CommentTest` (Unit, 9), `ValidationsTest::test_max_length`,
  `CommentsControllerTest` (Integration, 11), `CommentsAccessTest`
  (Integration, 4) e `CommentsCest` (Acceptance, 3).

## Por que assim

- Recurso filho no padrão da Inscrição (`Enrollment`): model com
  `BelongsTo`, pai com `HasMany`, SQL só no model base. A criação
  usa `$event->comments()->new(...)`, que já põe o `event_id`.
- `created_at` em `$columns`: o `save()` grava todas as colunas e o
  `DEFAULT` do banco não vale; preencher no construtor permite exibir
  a data com o `findById`/`where` do framework, sem SQL próprio.
- `maxLength` em `lib/Validations.php` (cenário M2): validação só no
  model, reaproveitável; `mb_strlen` para "á" contar 1.
- Tela do Participante: a base não tem tela pública do Evento, então
  foi criada a mínima (`GET /events/{id}`) no grupo `auth`. Não há
  listagem de Eventos para o Participante (é o O2 / Entrega 4.3): ele
  chega pela URL. O Administrador vê e comenta no show que já existe.
- Rota de comentário no grupo `auth` e não em `admin`: quem comenta é
  o Participante. Redirecionar conforme o perfil mantém cada um na sua
  tela; o form é o mesmo (uma parcial).
- Comentário inválido redireciona com flash em vez de renderizar o
  form de novo: o form é um campo só e a mensagem diz o motivo.
- `ON DELETE CASCADE`: comentário não é motivo para impedir a remoção
  (diferente da Inscrição: regra P03 do código-base,
  `Event::safeDestroy`). Se o professor quiser bloquear, faça como
  Inscrições em `safeDestroy()` e troque para `RESTRICT`.
- Escape na saída (`htmlspecialchars` + `nl2br`). Os flashes deste
  cenário só têm textos fixos (nada digitado pelo usuário).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload -o --strict-psr
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
```

Resultado obtido: Unit 169 testes, Integration 60, Acceptance 26
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros; PSR-4 estrito ok;
`db:populate` cria "5 Comentários criados em 3 Eventos".

Testes novos:

- `CommentTest`: salvar válido; `created_at` preenchido; `body` vazio;
  500 válido e 501 inválido; `event_id`/`user_id` obrigatórios;
  `event()`/`user()`/`authorName()`; `Event::comments()` em ordem;
  remoção do Evento apaga os comentários.
- `ValidationsTest::test_max_length`.
- `CommentsControllerTest`: Participante comenta (com `trim`) e volta
  para `/events/{id}`; Administrador volta para `/admin/events/{id}`;
  `user_id` do form ignorado; vazio, 501 caracteres, ausente/array
  rejeitados; Evento inexistente; tela do Evento escapa `<script>` e
  mostra o form; tela sem comentários; show do admin com a lista.
- `CommentsAccessTest`: sem sessão (GET e POST) -> `/login`;
  Participante vê e comenta; Administrador comenta.
- `CommentsCest`: `participantCommentsOnEvent`,
  `emptyCommentIsRejected`, `adminSeesAndRepliesInAdminShow`.

No navegador, depois do `./run db:populate`: entre como
`user@example.com` e abra `/events/1` (dois comentários, um do
Administrador). Comente; o texto aparece literal mesmo com `<b>`.
Entre como `admin@example.com` e abra `/admin/events/1`: a mesma
lista e o form no fim da página. Pelo terminal (clone sem porta):

```
docker compose exec web sh -c "curl -s -c /tmp/u \
  -d 'user[email]=user@example.com&user[password]=123456' \
  http://localhost/login >/dev/null; \
  curl -s -b /tmp/u -d 'comment[body]=Oi' \
  http://localhost/events/2/comments -i | grep -i location; \
  curl -s -b /tmp/u http://localhost/events/2" | grep -A3 comment-body
```

## Relação com outros cenários

- Q1 (`q1-so-dono-edita-remove`): também mexe em
  `admin/events/show.phtml` (botão Editar). Conflito de vizinhança no
  fim do arquivo: fique com o `if isOwnedBy` do Q1 e com o `require`
  da parcial deste.
- R1 (`r1-flash-titulo-warning`): escapa o flash no partial; os flashes
  deste cenário não têm HTML, então funcionam com ou sem o R1.
- P12 (`p12-codigo-aleatorio-login`): independente; se aplicado, os
  logins dos Cests passam pelo código.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p10-comentarios-evento`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/p10-comentarios-evento`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p10 \
    s50/fvp/4.2/exemplos/p10-comentarios-evento/projeto
git fetch fvp-p10 fvp/p10-comentarios-evento
git cherry-pick fb39589
git remote remove fvp-p10
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p10-comentarios-evento/solucao.bundle \
    fvp/p10-comentarios-evento:fvp-p10
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick fb39589
# opcional, limpa a ref
git branch -D fvp-p10
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p10-comentarios-evento/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p10-comentarios-evento/solucao.patch
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

Arquivos que costumam conflitar neste cenário e por quê:

- `database/schema.sql`: outros cenários acrescentam colunas/tabelas.
  Mantenha os dois; o `DROP TABLE IF EXISTS comments` precisa ficar
  antes do `DROP` de `events`.
- `config/routes.php`: rotas novas no grupo `auth`. Junte as rotas
  diferentes, mas com O2 aplicado a rota `GET /events/{id}`
  (`events.show`) existe nos dois lados: mantenha UMA só, não
  duplique. Rota fixa antes de `{id}`.
- `app/Models/Event.php`: métodos novos lado a lado (fique com os
  dois).
- `lib/Validations.php`: se o M2 já criou `maxLength`, fique com um só
  e confira a mensagem esperada nos testes.
- `populate.php` e `admin/events/show.phtml`: acréscimos no fim.
- Se o repositório alvo já tiver `App\Controllers\EventsController`
  (tela pública do O2), junte: mantenha o `show` de lá e inclua a
  parcial `events/_comments.phtml` na view dele.
- `app/views/events/show.phtml`: com O2 aplicado, o arquivo existe nos
  dois lados. Não escolha um lado: junte os trechos (detalhes do
  Evento e botão de Inscrição do O2 + a parcial de comentários).

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classes novas), `./run db:populate` (schema novo), testes e
`./run phpcs`.

## Armadilhas

- Sem `DROP TABLE IF EXISTS comments` antes de `events`, o
  `migrate()` falha por FK na segunda execução.
- `created_at` em `$columns` sem preencher: o `save()` grava `NULL` e
  quebra o `NOT NULL`. Por isso o construtor do `Comment`.
- `comment[body]` pode chegar como array ou faltar: o controller só
  aceita string; sem isso, `trim` de array dá erro 500.
- Espaços só passam no `notEmpty`: o `trim` no controller faz "   "
  virar vazio.
- `user_id` sempre da sessão; o teste confere que o do form é
  ignorado.
- Escape do `body` na view (`htmlspecialchars` antes do `nl2br`, nessa
  ordem; ao contrário o `<br />` sai escapado).
- Duas classes `EventsController` (uma em `Admin\`): no `routes.php`
  a do admin já é importada como `AdminEventsController`.
- `HasMany::get()` não ordena; a ordem é a do id (cronológica aqui).
- Classe nova: rode `./run composer dump-autoload -o --strict-psr`
  (o autoload otimizado não acha classe que não estava no mapa).
