# Q3. Ação só para Participante: admin não se inscreve

Pedido, como o professor faria na hora: "Inscrever-se é coisa de
participante. O administrador não pode se inscrever em Evento nenhum:
se tentar, volta para a área administrativa com um aviso. E esconda o
botão 'Inscrever-se' para ele."

Chance: média-baixa. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, Q3.

Branch no clone: `fvp/q3-acao-so-participante`, empilhada sobre P2
(`fvp/p2-inscricao-participante`, mesmos SHAs do exemplo do P2), que
por sua vez está sobre O2. Base: `main` (`f31006b`) → O2 → P2 → Q3.

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `0669a19` | (base, O2) `feat: Add events listing and details for ...` |
| `458a05b` | (base, P2) `feat: Allow participants to enroll in events` |
| `d0bd242` | `feat: Restrict enrollment to participants`              |

A mensagem completa do commit de O2 é `feat: Add events listing and
details for participants`.

## O que mudou

- `app/Middleware/Participant.php` (novo): espelho do `Admin`. Com
  usuário logado e `isAdmin()`, flash `danger` "Ação disponível apenas
  para participantes!" e redirect para `route('admin.dashboard.index')`
  (`/admin`). Sem usuário, não faz nada (quem barra é o
  `Authenticate`). O `redirectTo` é `protected` (no `Admin` é
  `private`) para o teste unitário trocar o `exit` por um registro.
- `config/App.php`: alias `'participant' =>
  \App\Middleware\Participant::class`, ao lado de `auth` e `admin`.
- `config/routes.php`: `POST /events/{id}/enrollments` passou para
  `Route::middleware('participant')->group(...)`, dentro do grupo
  `auth`. P2 não tem `DELETE` de inscrição; quando P3 entrar, a rota
  dele vai para o mesmo grupo.
- `app/Models/Enrollment.php`: `validates()` chama o privado
  `validatesParticipant()` antes da unicidade. Ele converte `user_id`
  com `filter_var($this->user_id, FILTER_VALIDATE_INT)` (não `(int)`:
  `'1abc'` viraria o usuário 1), busca o `User` e, se for admin,
  `addError('base', 'administradores não podem se inscrever em
  eventos!')` e devolve `false`. O `@property` de `user_id` passou a
  `int|string|null` (o valor pode vir de formulário).
- `app/views/events/show.phtml`: para admin, no lugar do botão,
  "Inscrições são feitas pelos participantes.".
- `tests/Integration/Controllers/EventsControllerTest.php` (de O2/P2):
  `test_show_marks_full_event` lotava o Evento inscrevendo o admin;
  agora inscreve outro Participante.

## Por que assim (duas camadas)

- Middleware: barra na porta, pela rota, com a resposta pedida
  (redirect para `/admin` + flash). É o mesmo mecanismo do `admin`, e
  o controller continua sem `if` de perfil.
- Model: a regra de negócio "admin não tem inscrição" vale para
  qualquer caminho que crie `Enrollment` (populate, console, outro
  controller futuro, teste de controller que chama a ação direto, sem
  passar pelo router). Defesa em profundidade: se alguém esquecer o
  middleware numa rota nova, o model ainda recusa.
- Esconder o botão é só conforto; não é segurança. As duas camadas de
  cima é que garantem.
- Ordem dos middlewares (A7/A17 da 4.1): o `RouteWrapperMiddleware`
  acrescenta o middleware às rotas quando o grupo fecha, e o grupo
  mais interno fecha primeiro. Com `participant` dentro de `auth`, o
  `Participant` roda **antes** do `Authenticate`. Por isso ele trata
  usuário `null` deixando passar: sem sessão, quem barra é o
  `Authenticate`, logo em seguida, mandando para `/login`. (Se o
  `Participant` redirecionasse sem sessão, o visitante iria para
  `/admin` em vez de `/login`.)
- Admin vai para `/admin` (e não `/dashboard`): é a "casa" dele; o
  `Admin` faz o simétrico com o Participante.

## Como verificar

```
./run db:populate
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 170, Integration 72, Acceptance 31 (com P2:
164 / 68 / 29; O2: 159 / 58 / 26; `main`: 159 / 45 / 23). PHPCS e
PHPStan sem erros; PSR-4 estrito ok.

Testes novos:

- `tests/Unit/App/Middleware/ParticipantTest.php` (+ dublê
  `ParticipantDouble.php`, como no F2 da 4.1): admin → `/admin` com
  flash; Participante passa; sem sessão passa (o `Participant` roda
  antes, e quem barra em seguida é o `Authenticate`).
- `EnrollmentTest`: `test_admin_cannot_enroll_in_an_event`,
  `test_admin_check_should_not_cast_a_malformed_user_id` (`'1abc'`
  não vira o admin), `test_participant_can_still_enroll`.
- `EnrollmentsControllerTest`:
  `test_create_does_not_enroll_a_logged_admin` (sem router, quem barra
  é o model; nenhuma inscrição criada).
- `ParticipantEventsAccessTest`: provider `participantOnlyRoutes()`
  com `test_participant_only_route_should_redirect_admin_to_admin_area`
  (302 → `/admin`, flash na página seguinte, nenhuma inscrição) e
  `..._should_redirect_to_login_without_session`;
  `test_admin_should_not_see_the_enroll_button`. O
  `test_participant_should_enroll_and_return_to_the_event` (P2) cobre
  o Participante passando.
- `AdminCannotEnrollCest` (novo): admin abre `/events/{id}` sem o
  botão; Participante continua vendo.

## Relação com outros cenários

- O2 e P2: base obrigatória (área `/events` e a inscrição).
- P3 (cancelar inscrição, `DELETE /events/{id}/enrollments`): ponha a
  rota no mesmo grupo `participant`.
- Q1 (só o dono edita): outra restrição por perfil/dono, mas no
  controller/model, não por middleware.
- A7/A17 da 4.1 (ordem dos middlewares; `admin` dentro de `auth`):
  mesma regra para `participant`.
- F2 da 4.1 (teste unitário de middleware): mesmo dublê com
  `redirectTo` sobrescrito.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/q3-acao-so-participante`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/q3-acao-so-participante`.

Escolha o que aplicar:

- Sem O2 nem P2: aplique o range inteiro `5dedab5..d0bd242` (O2 + P2 +
  Q3, três commits, nessa ordem).
- Com O2 e P2 já aplicados: aplique só o topo, `d0bd242`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-q3 \
    s50/fvp/4.2/exemplos/q3-acao-so-participante/projeto
git fetch fvp-q3 fvp/q3-acao-so-participante
# range (O2 + P2 + Q3):
git cherry-pick 5dedab5..d0bd242
# ou só o topo, se já tem O2 e P2:
# git cherry-pick d0bd242
git remote remove fvp-q3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/q3-acao-so-participante/solucao.bundle \
    fvp/q3-acao-so-participante:fvp-q3
git cherry-pick 5dedab5..d0bd242
# ou só o topo: git cherry-pick d0bd242
# opcional, limpa a ref
git branch -D fvp-q3
```

### 3. A partir do `solucao.patch` (`git am`)

O patch traz os três commits (O2, P2 e Q3), em ordem.

```
git am s50/fvp/4.2/exemplos/q3-acao-so-participante/solucao.patch
# já tem O2/P2? quando o commit vier vazio ou conflitar:
# git am --skip
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(middleware novo) e os testes.

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

- `config/routes.php`: P3, P4 e outros acrescentam rotas de inscrição
  perto da de P2. As que são ação de Participante vão para dentro do
  grupo `participant`.
- `config/App.php`: outro alias novo na mesma lista; fique com todos.
- `Enrollment::validates()`: P4/P9 mexem na mesma sequência. Mantenha
  `validatesParticipant()` logo depois dos `notEmpty`.
- `events/show.phtml`: o bloco do botão também muda em P3/P4; o ramo
  `isAdmin()` vem primeiro.
- Testes que inscrevem o admin como "enchimento" (como o
  `test_show_marks_full_event`) passam a falhar: troque por um
  Participante.

## Armadilhas

- Só esconder o botão: um `POST` feito à mão continua inscrevendo.
- Só o middleware: populate, console e testes de controller (que
  chamam a ação sem router) ainda criam inscrição de admin.
- Pôr o grupo `participant` fora do `auth`: sem sessão, o
  `Participant` vê `null`, deixa passar, e a ação roda sem usuário.
- `redirectTo` `private` com `exit` (cópia literal do `Admin`): o
  teste unitário do middleware não tem como rodar; deixe `protected`.
- `redirectTo` duplicado: `Admin` e `Participant` passam a ter o mesmo
  método `header` + `exit`. É o problema que o A1 da 4.1 resolve com
  um `BaseMiddleware` (com `redirectTo()`/`deny()` protegidos); se o
  professor cobrar repetição, extraia para lá e faça os dois herdarem.
- Achar que o `auth` roda antes do `participant` por estar "por fora":
  é o contrário (o grupo interno roda primeiro). Um `Participant` que
  redirecionasse sem sessão mandaria o visitante para `/admin`.
- Converter `user_id` com `(int)` no model: `'1abc'` vira `1`, que pode
  ser o admin. Use `filter_var(..., FILTER_VALIDATE_INT)`.
- Esquecer o alias em `config/App.php`: `Route::middleware(
  'participant')` falha ao resolver o nome.
- `$this->currentUser()->isAdmin()` sem `?->` na view: quebra se a
  página for aberta sem sessão (hoje o `auth` impede, mas a view não
  deve contar com isso).
