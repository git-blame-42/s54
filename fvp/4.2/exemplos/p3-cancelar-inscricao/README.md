# P3. Cancelar inscrição (`DELETE /events/{id}/enrollments`)

Pedido, como o professor faria na hora: "O Participante pode cancelar
a própria inscrição. No detalhe do Evento, quem está inscrito vê
'Cancelar inscrição' no lugar de 'Inscrever-se'."

Chance: média. Tempo estimado: 15-20 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P3.

Branch no clone: `fvp/p3-cancelar-inscricao`, empilhada sobre
`fvp/p2-inscricao-participante` (P2), que está sobre
`fvp/o2-eventos-para-participante` (O2), que sai de `main`
(`f31006b`). P3 depende do P2 (rota `create`, `EnrollmentsController`,
`User::isEnrolledIn`, bloco do botão no `show`).

Commits (os dois primeiros são a base):

- `9d7c82f` `feat: Add events listing and details for participants`
  (base, O2)
- `95e5e82` `feat: Allow participants to enroll in events` (base, P2)
- `484022c` `feat: Allow participants to cancel their enrollment`

## O que mudou

- `config/routes.php`: no grupo `auth`,
  `DELETE /events/{id}/enrollments` → `EnrollmentsController::destroy`,
  nome `events.enrollments.destroy` (mesma URI do `create`, outro
  verbo).
- `app/Controllers/EnrollmentsController.php`: `destroy` busca
  `Enrollment::findBy(['event_id' => <id da rota>, 'user_id' =>
  <logado>])`. `null` → flash `danger` "Você não está inscrito neste
  evento!"; senão `destroy()` e `success` "Inscrição cancelada!".
  Volta para `/events/{id}`.
- `app/views/events/show.phtml`: quando inscrito, além do texto "Você
  está inscrito neste evento.", um form `POST` com
  `<input type="hidden" name="_method" value="DELETE">` e o botão
  "Cancelar inscrição". Quando não inscrito, continua "Inscrever-se".
- Testes:
  - `EnrollmentsControllerTest` (+3): cancela a própria inscrição e
    libera a vaga; `user_id` do form é ignorado (a do outro
    Participante fica); sem inscrição → `danger`, nada removido.
  - `EventsControllerTest`: o form de cancelar (com `_method=DELETE`)
    só aparece para o inscrito.
  - `ParticipantEventsAccessTest` (+2): `DELETE` e `POST` com
    `_method=DELETE` sem sessão → `/login`; Participante logado
    cancela pelo `_method` e volta para `/events/{id}`.
  - `ParticipantCancelEnrollmentCest` (novo, 2): cancelar no Evento 03
    (1/23 → 0/23, volta "Inscrever-se"); no Evento 01 lotado, cancelar
    tira o "Lotado" (2/3) e inscrever de novo volta a 3/3.

## Por que assim

- Recurso aninhado: `POST` e `DELETE` em `/events/{id}/enrollments`
  são `create` e `destroy` do mesmo recurso. Não há id de Inscrição na
  URI porque cada usuário tem no máximo uma por Evento (`UNIQUE`).
- A busca usa o usuário da sessão, nunca um `enrollment_id` ou
  `user_id` do form: ninguém cancela a inscrição de outro.
- `findBy` + `destroy()` do `Model`: SQL só no model, controller fino.
- `_method=DELETE` no form: o navegador só envia GET/POST; o `Request`
  troca o verbo a partir do campo oculto.

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

Resultado obtido: Unit 164 testes, Integration 73, Acceptance 31
(eram 159 / 45 / 23 na base `f31006b`; 164 / 68 / 29 no P2). PHPCS e
PHPStan sem erros. Schema não mudou; nenhuma classe nova.

Na tela: `user@example.com` / `123456` está inscrito no Evento 1
(populate). "Eventos" → "Ver" no Evento 1 → "Cancelar inscrição":
aparece "Inscrição cancelada!" e o contador cai para 2/3. Sem porta
no host:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=user@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -i -b /tmp/c \
    -d '_method=DELETE' http://localhost/events/1/enrollments
```

## Relação com outros cenários

- Depende do P2 (`p2-inscricao-participante`), que depende do O2.
- P7 ("Meus eventos") é irmão deste (também sobre o P2). Aplicando os
  dois, dá para pôr o mesmo form de cancelar em cada linha de
  `enrollments/index.phtml`.
- P9 (lista de espera): com ele, cancelar uma inscrição confirmada
  precisa promover o primeiro da espera; este cenário só remove.
- P1/P4 (código de check-in e confirmação de presença, agente B): se
  aplicados, decida se quem já confirmou presença ainda pode cancelar
  (o P3 não impede). Não estão implementados aqui.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p3-cancelar-inscricao`.

Pré-requisito: o repositório alvo descende de `f31006b`. Este cenário
é empilhado: o `solucao.patch` e o range `main..fvp/...` trazem 3
commits (O2 + P2 + P3). Se o seu repositório já tem O2 e P2, aplique
só o topo. `<caminho>` abaixo:
`s50/fvp/4.2/exemplos/p3-cancelar-inscricao`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p3 \
    s50/fvp/4.2/exemplos/p3-cancelar-inscricao/projeto
git fetch fvp-p3 fvp/p3-cancelar-inscricao
# O2 + P2 + P3 (range)
git cherry-pick 9d7c82f^..484022c
# ou só o topo (P3), se já tem O2 e P2
git cherry-pick 484022c
git remote remove fvp-p3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p3-cancelar-inscricao/solucao.bundle \
    fvp/p3-cancelar-inscricao:fvp-p3
# range com a base (só se o seu main for o f31006b)
git cherry-pick main..fvp-p3
# ou só o topo
git cherry-pick 484022c
# opcional, limpa a ref
git branch -D fvp-p3
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os 3 commits (O2 + P2 + P3)
git am s50/fvp/4.2/exemplos/p3-cancelar-inscricao/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p3-cancelar-inscricao/solucao.patch
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

- `events/show.phtml`: o bloco `if isEnrolledIn` também muda no P9
  (texto de espera) e no P4 (form de presença). Mantenha os forms
  dentro do ramo "inscrito".
- `EnrollmentsController.php`: P7 acrescenta `index` no mesmo arquivo;
  fique com os dois métodos.
- `config/routes.php`: P7 e P1/P4 acrescentam rotas no `auth`.
- Só o topo sem P2: falta `EnrollmentsController`, `isEnrolledIn` e a
  rota `create`; aplique o P2 antes.

Depois de aplicar: testes e `./run phpcs` de novo.

## Armadilhas

- Form sem `<input type="hidden" name="_method" value="DELETE">`:
  chega como `POST` e cai no `create` (mesma URI), que responde "você
  já está inscrito neste evento!" em vez de cancelar.
- Buscar só por `event_id`: `findBy` devolve a primeira Inscrição do
  Evento, de qualquer Participante.
- `Model::destroy()` devolve `false` se nada foi apagado; o ramo
  `danger` cobre isso.
- Com a mesma URI para `create` e `destroy`, o teste de acesso precisa
  cobrir `DELETE` direto e `POST` com `_method`.
- Evento inexistente no `destroy`: cai em "Você não está inscrito
  neste evento!" e o `show` depois manda para `/events` com "Evento
  não encontrado!". Aceitável; um `findById` antes deixaria a mensagem
  mais precisa.
