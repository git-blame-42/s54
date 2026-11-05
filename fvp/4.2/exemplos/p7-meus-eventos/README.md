# P7. "Meus eventos" do Participante

Pedido, como o professor faria na hora: "Crie uma página com os
Eventos em que o Participante logado está inscrito, com link na
navbar."

Chance: média-alta. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P7.

Branch no clone: `fvp/p7-meus-eventos`, empilhada sobre
`fvp/p2-inscricao-participante` (P2), que está sobre
`fvp/o2-eventos-para-participante` (O2), que sai de `main`
(`f31006b`). P7 depende do P2 (`User::enrollments()`,
`EnrollmentsController`) e usa a rota `events.show` do O2. Não inclui
o P3 (é irmão dele).

Commits (os dois primeiros são a base):

- `9d7c82f` `feat: Add events listing and details for participants`
  (base, O2)
- `95e5e82` `feat: Allow participants to enroll in events` (base, P2)
- `1138a53` `feat: Add my events page for participants`

## O que mudou

- `config/routes.php`: no grupo `auth`,
  `GET /enrollments` → `EnrollmentsController::index`, nome
  `enrollments.index`.
- `app/Controllers/EnrollmentsController.php`: `index` pega
  `$this->currentUser()?->enrollments()->get() ?? []` (o
  `enrollments(): HasMany` veio no P2), com
  `/** @var array<int, Enrollment> $enrollments */` para o PHPStan, e
  renderiza `enrollments/index` com `$title = 'Meus eventos'`.
- `app/views/enrollments/index.phtml` (nova): tabela com título, data
  e local de `$enrollment->event()->get()` e botão "Ver" para
  `/events/{id}`. Sem Inscrições: "Você não está inscrito em nenhum
  evento." e um link para `/events`.
- `app/views/layouts/_navbar.phtml`: link "Meus eventos" ao lado de
  "Eventos", no ramo do não-admin.
- Testes:
  - `EnrollmentsControllerTest` (+2): logado com duas Inscrições e
    uma de outro Participante, a resposta tem os dois títulos, local,
    data e link, e não o terceiro; sem Inscrições, mensagem de vazio.
  - `ParticipantEventsAccessTest` (+2): `/enrollments` sem sessão →
    `/login`; Participante → 200, com o Evento e o link na navbar.
  - `ParticipantMyEventsCest` (novo, 2): participante1 vê Evento 01 e
    03 (não o 02), "Ver" leva ao detalhe; Participante novo vê a
    mensagem de vazio, inscreve-se no Evento 02 e ele aparece.

## Por que assim

- URI `/enrollments` (`enrollments.index`): é o `index` do recurso
  Inscrições do usuário logado, no mesmo controller do `create`. Nome
  RESTful em vez de `/my-events`.
- `User::enrollments()` (HasMany) em vez de `Enrollment::where(...)`
  no controller: a consulta fica no model; o controller só pede as
  Inscrições do usuário.
- `User::events()` já existe e significa "Eventos criados pelo admin";
  por isso a relação nova não se chama `events()`.
- Mostrar só título, data e local: a página é um resumo; o detalhe
  (vagas, botão) continua no `show` do O2.

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
```

Resultado obtido: Unit 164 testes, Integration 72, Acceptance 31
(eram 159 / 45 / 23 na base `f31006b`; 164 / 68 / 29 no P2). PHPCS e
PHPStan sem erros. Schema não mudou; nenhuma classe nova.

Na tela: `user@example.com` / `123456`, "Meus eventos" na navbar:
aparecem os 4 Eventos do populate (1, 2, 4 e 9). Sem porta no host:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=user@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c http://localhost/enrollments
```

## Relação com outros cenários

- Depende do P2 (`p2-inscricao-participante`), que depende do O2.
- P3 (`p3-cancelar-inscricao`) é irmão (também sobre o P2). Aplicando
  os dois, ponha em cada linha o mesmo form do `show`:
  `action="<?= route('events.enrollments.destroy', ['id' =>
  $event->id]) ?>"` com `_method=DELETE` e o botão "Cancelar
  inscrição".
- P9 (lista de espera): com ele, mostre o status (confirmada/espera)
  em uma coluna nova.
- P1/P4 (código de check-in e confirmação de presença, agente B): esta
  página é um bom lugar para mostrar "presença confirmada". Não estão
  implementados aqui.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p7-meus-eventos`.

Pré-requisito: o repositório alvo descende de `f31006b`. Este cenário
é empilhado: o `solucao.patch` e o range `main..fvp/...` trazem 3
commits (O2 + P2 + P7). Se o seu repositório já tem O2 e P2, aplique
só o topo. `<caminho>` abaixo: `s50/fvp/4.2/exemplos/p7-meus-eventos`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p7 s50/fvp/4.2/exemplos/p7-meus-eventos/projeto
git fetch fvp-p7 fvp/p7-meus-eventos
# O2 + P2 + P7 (range)
git cherry-pick 9d7c82f^..1138a53
# ou só o topo (P7), se já tem O2 e P2
git cherry-pick 1138a53
git remote remove fvp-p7
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/p7-meus-eventos/solucao.bundle \
    fvp/p7-meus-eventos:fvp-p7
# range com a base (só se o seu main for o f31006b)
git cherry-pick main..fvp-p7
# ou só o topo
git cherry-pick 1138a53
# opcional, limpa a ref
git branch -D fvp-p7
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os 3 commits (O2 + P2 + P7)
git am s50/fvp/4.2/exemplos/p7-meus-eventos/solucao.patch
# só o diff, sem commit
git apply --3way s50/fvp/4.2/exemplos/p7-meus-eventos/solucao.patch
```

O patch não aplica só o topo; para isso use o bundle.

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

- `EnrollmentsController.php`: P3 acrescenta `destroy` no mesmo
  arquivo (aqui o `index` entra no topo da classe, o `destroy` no
  fim); fique com os dois métodos.
- `config/routes.php`: P3 e P1/P4 acrescentam rotas no `auth`. Fique
  com todas.
- `tests/.../EnrollmentsControllerTest.php` e
  `ParticipantEventsAccessTest.php`: P3 também acrescenta testes no
  fim; mantenha os dois blocos.
- `_navbar.phtml`: outro cenário com link novo no mesmo ramo; mantenha
  os dois `<li>`.
- Só o topo sem P2: falta `User::enrollments()` e o
  `EnrollmentsController`; aplique o P2 antes.

Depois de aplicar: testes e `./run phpcs` de novo.

## Armadilhas

- `HasMany::get()` devolve `array<Model>`: sem o `@var array<int,
  Enrollment>` o PHPStan nível 6 reclama ao chamar `->event()` na
  view/controller.
- `currentUser()` é `?User`: use `?->` e `?? []` (a rota é `auth`,
  mas o PHPStan não sabe).
- N+1: um `event()->get()` por linha. Aceitável aqui; saiba dizer. A
  alternativa é `BelongsToMany` em `User` via `event_user`, com outro
  nome (`enrolledEvents()`), porque `events()` já é dos criados.
- Tabela vazia sem mensagem: o `foreach` não quebra, mas a página fica
  em branco; o teste cobre "Você não está inscrito em nenhum evento.".
- `$title` fora do `compact`: página 500.
- Não use `User::events()` aqui: ele lista os Eventos criados pelo
  admin (`events.user_id`), não as Inscrições.
