# P2. Inscrição do Participante (`POST /events/{id}/enrollments`)

Pedido, como o professor faria na hora: "O Participante logado se
inscreve em um Evento, se houver vaga e se ainda não estiver inscrito.
Ponha o botão 'Inscrever-se' no detalhe do Evento e mostre a mensagem
de sucesso ou do motivo da recusa."

Chance: alta. Tempo estimado: 30-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P2.

Branch no clone: `fvp/p2-inscricao-participante`, empilhada sobre
`fvp/o2-eventos-para-participante` (O2), que sai de `main`
(`f31006b`). P2 depende do O2: o botão fica na tela do Participante
(`events/show.phtml`) e o redirecionamento usa `events.show`.

Commits (o primeiro é a base, O2):

- `9d7c82f` `feat: Add events listing and details for participants`
  (base, O2)
- `95e5e82` `feat: Allow participants to enroll in events`

## O que mudou

- `app/Models/Enrollment.php`: `validates()` novo, com
  `lib/Validations.php`:
  - `notEmpty` em `event_id` e `user_id`;
  - `Validations::uniqueness(['event_id', 'user_id'], $this)`; se
    falhar, `errors('base')` = "você já está inscrito neste evento!";
  - só ao criar: Evento inexistente marca `errors('event_id')`; Evento
    com `availableSpots() <= 0` marca `errors('base')` = "não há vagas
    neste evento!".
- `app/Models/User.php`: `enrollments(): HasMany` (Inscrições do
  Participante; `events()` continua sendo os Eventos criados pelo
  admin) e `isEnrolledIn(Event $event): bool` (`Enrollment::exists`
  com `event_id` + `user_id`).
- `config/routes.php`: no grupo `auth`,
  `POST /events/{id}/enrollments` → `EnrollmentsController::create`,
  nome `events.enrollments.create`.
- `app/Controllers/EnrollmentsController.php` (novo): acha o Evento
  (senão flash "Evento não encontrado!" e `/events`), cria o
  `Enrollment` com `user_id` de `currentUser()` (nunca do form),
  flash `success` "Inscrição realizada com sucesso!" ou `danger` com
  `errors('base')`, e volta para `/events/{id}`.
- `app/views/events/show.phtml`: badge "Lotado" quando
  `availableSpots() === 0`; form `POST` com o botão "Inscrever-se"
  quando o usuário não está inscrito; senão "Você está inscrito neste
  evento.".
- Testes:
  - `EnrollmentTest`: a duplicata agora dá `save()` falso com
    `errors('base')` (antes esperava `PDOException`); o índice
    `UNIQUE` continua barrando sem validação (classe anônima com
    `validates()` vazio); Evento lotado recusa; última vaga aceita;
    Evento inexistente e `user_id` vazio recusam.
  - `UserTest`: `enrollments()` e `isEnrolledIn()`.
  - `EnrollmentsControllerTest` (novo, 5): grava e redireciona; ignora
    `user_id` do form; duplicata; lotado; Evento inexistente.
  - `EventsControllerTest`: botão quando não inscrito, texto quando
    inscrito, badge "Lotado".
  - `ParticipantEventsAccessTest`: `POST` sem sessão → `/login`;
    Participante logado → 302 para `/events/{id}` e Inscrição gravada.
  - `ParticipantEnrollCest` (novo, 3) + helper
    `loginAsNewParticipant` no `BaseParticipantEventsCest`: inscrição
    com vaga, já inscrito sem botão, Evento 01 lotado recusado.

## Por que assim

- Regra de negócio no model (`validates()`), não no controller: o
  `save()` já chama `isValid()`, e o populate e os testes passam pela
  mesma regra.
- `uniqueness` composto da `lib/Validations.php` em vez de esperar a
  `PDOException` do `UNIQUE`: o erro vira mensagem para o usuário, não
  500. O índice continua no schema como última barreira.
- Vagas só em registro novo: `availableSpots()` já conta a própria
  Inscrição depois de gravada.
- Recurso aninhado `enrollments` com `create` (e `destroy` no P3): a
  rubrica cobra nomes RESTful; `/enroll` (verbo na URI) funciona, mas
  é criticado.
- `isEnrolledIn` no `User`: a view pergunta "o usuário está inscrito
  neste Evento?" sem SQL fora do model.
- O botão aparece mesmo com o Evento lotado: quem decide é o model (a
  tela mostra "Lotado" e a flash explica). Isso também deixa o P9
  (lista de espera) trocar só a regra.

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
# o populate passa pela validação nova
./run db:populate
```

Resultado obtido: Unit 164 testes, Integration 68, Acceptance 29
(eram 159 / 45 / 23 na base `f31006b`; 159 / 58 / 26 no O2). PHPCS e
PHPStan sem erros. Schema não mudou.

Na tela: `user@example.com` / `123456`, "Eventos", "Ver" num Evento,
"Inscrever-se": aparece "Inscrição realizada com sucesso!". No Evento
1 (3/3, "Lotado") a flash é "não há vagas neste evento!". Sem porta no
host, o `POST` direto:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=user@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -i -b /tmp/c \
    -X POST http://localhost/events/2/enrollments
```

## Relação com outros cenários

- Depende do O2 (`o2-eventos-para-participante`).
- P3 (cancelar, `DELETE /events/{id}/enrollments`), P7 ("Meus
  eventos", usa `User::enrollments()`) e P9 (lista de espera, troca a
  regra de vagas do `validates()`) são feitos sobre este.
- P1/P4 (código de check-in por Evento e confirmação de presença,
  agente B): o P4 só faz sentido para quem tem Inscrição; com este
  cenário aplicado, a confirmação pode exigir `isEnrolledIn($event)`.
  Não estão implementados aqui.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p2-inscricao-participante`.

Pré-requisito: o repositório alvo descende de `f31006b`. Este cenário
é empilhado: o `solucao.patch` e o range `main..fvp/...` trazem os 2
commits (O2 + P2). Se o seu repositório já tem algo equivalente ao O2
(rotas `events.*` e `events/show.phtml`), aplique só o topo.
`<caminho>` abaixo: `s50/fvp/4.2/exemplos/p2-inscricao-participante`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p2 \
    s50/fvp/4.2/exemplos/p2-inscricao-participante/projeto
git fetch fvp-p2 fvp/p2-inscricao-participante
# O2 + P2 (range: do commit depois de main até o topo)
git cherry-pick 9d7c82f^..95e5e82
# ou só o topo (P2), se já tem o O2
git cherry-pick 95e5e82
git remote remove fvp-p2
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p2-inscricao-participante/solucao.bundle \
    fvp/p2-inscricao-participante:fvp-p2
# range com a base
git cherry-pick main..fvp-p2
# ou só o topo
git cherry-pick 95e5e82
# opcional, limpa a ref
git branch -D fvp-p2
```

Use `main..fvp-p2` só se o seu `main` for o `f31006b`; senão prefira
`9d7c82f^..95e5e82`.

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os 2 commits (O2 + P2) com as mesmas mensagens
git am s50/fvp/4.2/exemplos/p2-inscricao-participante/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p2-inscricao-participante/solucao.patch
```

O patch não tem como aplicar só o topo; para isso use o bundle.

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

- `config/routes.php`: rotas novas no grupo `auth` (P3, P7, P1/P4).
  Fique com todas.
- `events/show.phtml`: P3 troca o bloco do botão; P4 pode pôr o form
  de presença no mesmo ponto. Mantenha os blocos.
- `tests/Unit/Models/Events/EnrollmentTest.php`: se o seu repositório
  ainda espera `PDOException` na duplicata, fique com a versão deste
  cenário.
- Só o topo sem O2: falham `route('events.show')` e a view
  `events/show.phtml`; aplique o O2 antes.

Depois de aplicar: `./run composer dump-autoload`, testes, `./run
phpcs` e `./run db:populate` (o populate passa pela validação nova).

## Armadilhas

- `EnrollmentTest` da base espera `PDOException` na duplicata; com a
  validação o `save()` só devolve `false`. Atualize o teste.
- `user_id` vindo do form: nunca. Sempre `currentUser()->id` (há
  teste para isso).
- `Validations::uniqueness` marca erro em `event_id` e `user_id`
  ("já existe um registro com esse dado"); a flash usa `base`, por
  isso o `addError('base', ...)` explícito.
- Validar vagas também na edição faria a Inscrição já gravada contar
  contra ela mesma; por isso só em `newRecord()`.
- Concorrência: duas inscrições ao mesmo tempo podem passar na
  contagem de vagas; o `UNIQUE` só barra a duplicata do mesmo usuário.
  Uma vaga a mais por corrida é aceitável aqui; saiba explicar.
- `redirectTo` sem `return` no "não encontrado": no teste de
  controller ele só ecoa `Location:` e o método seguiria.
- No teste de controller, zerar `$_SESSION['flash'] = []` no meio do
  teste faz o PHPStan inferir `array{}` e acusar
  `offsetAccess.notFound` na asserção seguinte.
- O Administrador também consegue se inscrever (a rota é só `auth`);
  decida com o professor se deve barrar.
