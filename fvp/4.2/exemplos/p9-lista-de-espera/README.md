# P9. Lista de espera quando lotado

Pedido, como o professor faria na hora: "Se o Evento estiver lotado, a
inscrição entra numa lista de espera em vez de ser recusada. Quando
alguém confirmado sai, o primeiro da fila é promovido. Mostre na tela
que o Participante está na espera e em que posição."

Chance: média-baixa. Tempo estimado: 35-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P9.

Branch no clone: `fvp/p9-lista-de-espera`, empilhada sobre
`fvp/p2-inscricao-participante` (P2), que está sobre
`fvp/o2-eventos-para-participante` (O2), que sai de `main`
(`f31006b`). P9 depende do P2 (`Enrollment::validates()`,
`EnrollmentsController::create`, `events/show.phtml`). O catálogo
cita também o P3; aqui a promoção fica no model
(`Enrollment::destroy()`), então funciona sem o P3 e passa a valer
sozinha quando o P3 for aplicado (ver "Relação com outros cenários").

Commits (os dois primeiros são a base):

- `0669a19` `feat: Add events listing and details for participants`
  (base, O2)
- `458a05b` `feat: Allow participants to enroll in events` (base, P2)
- `5df6940` `feat: Add waiting list for full events`

## O que mudou

- `database/schema.sql`: `event_user.status ENUM('confirmed',
  'waiting') NOT NULL DEFAULT 'confirmed'`.
- `app/Models/Enrollment.php`:
  - `STATUSES` (rótulos), `'status'` em `$columns` e construtor que
    põe `'confirmed'` quando vazio (o `save()` grava todas as colunas;
    o `DEFAULT` do schema não vale);
  - `validates()`: `inclusion` de `status`; ao criar, Evento sem vaga
    (`availableSpots() === 0`) muda `status` para `'waiting'` em vez
    de recusar (sai o erro "não há vagas neste evento!" do P2);
  - `isWaiting()`, `waitingPosition()` (1 = próximo da fila);
  - `destroy()` sobrescrito: se a Inscrição removida era `confirmed`,
    chama `Event::promoteFirstWaiting()`;
  - `Event::findById($this->event_id)` sem `(int)`: o `event_id` vem
    do banco ou do controller, não do usuário, e o `@property int`
    satisfaz o PHPStan.
- `app/Models/Event.php`:
  - `enrollmentsCount()` conta só `confirmed` (é o que ocupa vaga; o
    "3/3", `availableSpots()` e a regra de capacidade na edição usam
    ele);
  - `waitingCount()`; `waitingList()` com SQL próprio
    (`ORDER BY created_at, id`); `promoteFirstWaiting()` confirma o
    primeiro da fila se houver vaga;
  - `hasEnrollments()` e `safeDestroy()` passam a contar todas as
    linhas (confirmadas + espera), porque a FK `RESTRICT` enxerga
    todas.
- `app/Models/User.php`: `enrollmentIn(Event): ?Enrollment`.
- `EnrollmentsController::create`: Inscrição na espera recebe a flash
  "Evento lotado: você entrou na lista de espera (posição N).".
- `events/show.phtml`: badge "Lista de espera: N"; quem está na fila
  vê "Você está na lista de espera (posição N)."; com o Evento lotado,
  o botão vira "Entrar na lista de espera".
- Testes:
  - `EnrollmentTest`: padrão `confirmed`; lotado → `waiting` com
    posição 1 e `enrollmentsCount()` sem a espera; posições em ordem de
    chegada; remover confirmada promove o primeiro (e o terceiro vira
    posição 1); remover da espera não promove; `promoteFirstWaiting`
    sem fila ou sem vaga não faz nada; `status` inválido recusado;
    `safeDestroy` conta a espera ("inscrições (2)").
  - `EnrollmentsControllerTest`: o teste "lotado recusa" do P2 virou
    "lotado entra na espera".
  - `EventsControllerTest`: posição e tamanho da fila no `show`; botão
    "Entrar na lista de espera" com o Evento lotado.
  - `ParticipantEnrollCest`: o cenário "lotado recusa" do P2 virou
    "Participante novo no Evento 01 (3/3) entra na espera, posição 1".

## Por que assim

- `status` na própria `event_user` em vez de outra tabela: uma
  Inscrição é uma linha só; o `UNIQUE (event_id, user_id)` continua
  impedindo estar confirmado e na espera ao mesmo tempo.
- A decisão confirmada/espera fica no `validates()` do P2, onde já
  estava a regra de vagas: controller continua fino.
- `where` só faz igualdade e não ordena; a fila precisa de
  `ORDER BY created_at, id` (desempate por `id`, porque `created_at`
  tem precisão de segundo), então `waitingList()` tem SQL próprio no
  model, com `prepare` + `bindValue`.
- Promoção em `Enrollment::destroy()`: qualquer caminho que remova uma
  Inscrição confirmada (o `destroy` do P3, um futuro "remover inscrito"
  do admin) promove a fila sem o controller precisar lembrar.
- `enrollmentsCount()` só com confirmadas: as telas (admin e
  Participante) continuam mostrando "ocupadas/capacidade" sem mudança
  de view.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
# schema mudou: recria o banco de desenvolvimento
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 171 testes, Integration 70, Acceptance 29
(eram 159 / 45 / 23 na base `f31006b`; 164 / 68 / 29 no P2). PHPCS e
PHPStan sem erros. `db:populate` cria as 14 Inscrições como
`confirmed`; o Evento 1 continua 3/3.

Na tela: `fernanda@example.com` / `123456` não está no Evento 1.
Abra `/events/1`, clique "Entrar na lista de espera": flash com a
posição 1, badge "Lista de espera: 1", contador ainda 3/3. Sem porta
no host:

```
docker compose exec web curl -s -c /tmp/f \
    -d 'user[email]=fernanda@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/f \
    -X POST http://localhost/events/1/enrollments
docker compose exec web curl -s -b /tmp/f http://localhost/events/1 \
    | grep espera
```

## Relação com outros cenários

- Depende do P2 (`p2-inscricao-participante`), que depende do O2.
- P3 (`p3-cancelar-inscricao`, irmão sobre o P2): o `destroy` do P3
  chama `$enrollment->destroy()`, que aqui promove o primeiro da fila.
  Aplicando os dois, cancelar uma confirmada já promove; cancelar da
  espera só sai da fila. Ajuste o texto do botão para quem está na
  espera ("Sair da lista de espera"), se quiser.
- P7 (`p7-meus-eventos`, irmão): acrescente uma coluna com
  `Enrollment::STATUSES[$enrollment->status]`.
- P1 e P4 (código de check-in e confirmação de presença):
  confirmar presença deve exigir `status === 'confirmed'` (quem está
  na espera não tem vaga). Não estão implementados aqui.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p9-lista-de-espera`.

Pré-requisito: o repositório alvo descende de `f31006b`. Este cenário
é empilhado: o `solucao.patch` e o range `main..fvp/...` trazem 3
commits (O2 + P2 + P9). Se o seu repositório já tem O2 e P2, aplique
só o topo. `<caminho>` abaixo:
`s50/fvp/4.2/exemplos/p9-lista-de-espera`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p9 s50/fvp/4.2/exemplos/p9-lista-de-espera/projeto
git fetch fvp-p9 fvp/p9-lista-de-espera
# O2 + P2 + P9 (range)
git cherry-pick 0669a19^..5df6940
# ou só o topo (P9), se já tem O2 e P2
git cherry-pick 5df6940
git remote remove fvp-p9
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/p9-lista-de-espera/solucao.bundle \
    fvp/p9-lista-de-espera:fvp-p9
# range com a base (só se o seu main for o f31006b)
git cherry-pick main..fvp-p9
# ou só o topo
git cherry-pick 5df6940
# opcional, limpa a ref
git branch -D fvp-p9
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os 3 commits (O2 + P2 + P9)
git am s50/fvp/4.2/exemplos/p9-lista-de-espera/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p9-lista-de-espera/solucao.patch
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

- `events/show.phtml`: o bloco "inscrito/não inscrito" também muda no
  P3 (form de cancelar) e no P4. Fique com os três ramos deste
  cenário (espera, confirmado, não inscrito) e ponha o form do P3 nos
  dois primeiros.
- `EnrollmentsController.php`: P3 e P7 acrescentam métodos; este só
  muda o `if` do `create`.
- `Event.php`: outros cenários com campo novo (M1, P1, P5) mexem em
  `$columns` e no `validates()`; os métodos de contagem deste ficam
  mais abaixo. Mantenha os dois.
- `database/schema.sql`: só a tabela `event_user`; outro cenário que
  acrescente coluna em `events` não conflita.
- Só o topo sem P2: falta `Enrollment::validates()` e o
  `EnrollmentsController`; aplique o P2 antes.

Depois de aplicar: `./run db:populate` (schema mudou), testes e
`./run phpcs` de novo.

## Armadilhas

- Sem o construtor com padrão, `save()` grava `status = NULL` e o
  `NOT NULL` quebra (o `DEFAULT 'confirmed'` do schema não vale,
  porque o `INSERT` lista todas as colunas). O populate quebraria.
- Esquecer `'status'` em `$columns`: `where`/`findById` não carregam a
  coluna e tudo parece `confirmed`.
- `enrollmentsCount()` contando a espera faz o Evento ficar "5/3" e
  bloqueia a regra de capacidade na edição.
- `safeDestroy()` com `enrollmentsCount()` (só confirmadas) deixaria
  passar um Evento com fila e cairia na `PDOException` da FK; por isso
  ele conta todas as linhas.
- Promover sem checar a vaga: aumentar a fila depois de diminuir a
  capacidade promoveria além do limite; `promoteFirstWaiting()` olha
  `availableSpots()` antes.
- `created_at` em segundos: duas inscrições no mesmo segundo empatam;
  o desempate por `id` mantém a ordem de chegada.
- O admin aumentar a capacidade não promove ninguém automaticamente
  (fora do escopo); dá para chamar `promoteFirstWaiting()` em laço no
  `update` do admin, se o professor pedir.
- Teste unitário do P2 "lotado recusa" passa a falhar: atualize-o
  para "lotado entra na espera" (feito aqui).
