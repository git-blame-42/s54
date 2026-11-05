# P15. Inscrição via JSON (prévia do Ajax da 4.4)

Pedido, como o professor faria na hora: "O `POST
/events/{id}/enrollments` deve responder JSON quando o cliente pedir
`Accept: application/json`, sem redirect nem flash. O HTML continua
igual. Se der tempo, faça o botão 'Inscrever-se' usar `fetch()`."

Chance: média-baixa. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, P15.

Branch no clone: `fvp/p15-inscricao-json`, empilhada sobre
`fvp/p2-inscricao-participante` (P2), que está sobre
`fvp/o2-eventos-para-participante` (O2), que sai de `main`
(`f31006b`). Pilha: O2 → P2 → P15. P15 depende do P2
(`EnrollmentsController::create`, `Enrollment::validates()`).

Commits (os dois primeiros são a base):

- `0669a19` `feat: Add events listing and details for participants`
  (base, O2)
- `458a05b` `feat: Allow participants to enroll in events` (base, P2)
- `8984b86` `feat: Answer enrollment requests with JSON`

## O que mudou

- `app/Controllers/EnrollmentsController.php`, `create`:
  - com `$request->acceptJson()`: `http_response_code(201)` se gravou,
    `422` se não, e `renderJson('enrollments/create', ...)`; `return`
    logo depois. Sem flash, sem redirect;
  - Evento inexistente com JSON: `404` com
    `{"ok": false, "errors": ["Evento não encontrado!"]}`
    (`respondJson()`, sem view, porque não há Inscrição);
  - sem o header: o fluxo HTML do P2, igual;
  - `findEvent()` novo: o `{id}` passa por
    `filter_var(..., FILTER_VALIDATE_INT, min_range 1)` em vez de
    `(int)`; "abc", "2x", "-1" e vazio viram "Evento não encontrado".
- `app/views/enrollments/create.json.php` (nova): preenche `$json`
  com `ok`, `message`, `enrollment` (`id`, `event_id`, `user_id`) no
  sucesso ou `errors` na falha, mais `enrollments_count`, `capacity`
  e `available_spots`.
- `app/Models/Enrollment.php`: `errorMessages(): array<int, string>`.
  Se há erro em `base`, devolve só ele (a duplicata também marca
  `event_id`/`user_id` com o texto genérico de
  `Validations::uniqueness`).
- Botão com `fetch()` (opcional do pedido, feito):
  - `public/assets/js/enrollments.js` (novo, sem JS inline): intercepta
    o form `[data-enrollment-form]`, faz `POST` com
    `Accept: application/json`, atualiza `#event_spots` ("N/C") e
    `#event_available` ("(N disponíveis)"), mostra a mensagem em
    `#enrollment_feedback` e troca o form por "Você está inscrito
    neste evento.". Se a resposta não for JSON (ex.: sessão expirou e
    veio o `/login`), envia o form do jeito normal.
  - `app/views/events/show.phtml`: `span`s com esses ids, a `div`
    `#enrollment_feedback`, o atributo `data-enrollment-form` e o
    `<script src="/assets/js/enrollments.js" defer>`.
- Testes:
  - `EnrollmentsControllerTest` (+5): JSON de sucesso (corpo
    completo, sem `Location:`, flash vazia); duplicata e lotado com
    `errors`; Evento inexistente; ids não numéricos no fluxo HTML.
  - `EnrollmentTest` (+1): `errorMessages()`.
  - `ParticipantEventsAccessTest` (+4, HTTP real): `201` com
    `Content-Type: application/json`; `422` na duplicata; `404`;
    sem sessão continua `302` para `/login`.
  - `ParticipantEnrollWithFetchCest` (novo, 2): inscrever sem
    recarregar (marcador em `window` sobrevive, contador vira 1/22);
    Evento lotado mostra o erro sem recarregar.
  - `ParticipantEnrollCest` (P2): `waitForText` depois do clique,
    porque agora a resposta chega por `fetch()`.

## Por que assim

- Mesmo endpoint e mesmo controller, decidido pelo header: é o padrão
  do framework (`acceptJson()` + `renderJson()` com view `.json.php`,
  como no O4). A regra de negócio continua só no `validates()`.
- `201`/`422`/`404`: o cliente JS distingue sucesso de erro de
  validação sem ler a mensagem. O corpo também traz `ok`.
- Sem flash no JSON: ninguém lê a sessão nessa resposta; a mensagem
  ficaria para a próxima página HTML.
- Sem sessão: **mantive o middleware `auth`** como está. O pedido JSON
  sem login recebe o mesmo `302` para `/login` (testado). Um `401`
  exigiria mudar o middleware para todas as rotas; fica fora do
  escopo. O `enrollments.js` cobre isso caindo no envio normal.
- `filter_var` no id: nada de `(int)`, que transforma "2x" em 2 e
  "abc" em 0 sem avisar.
- JS em arquivo próprio, carregado com `defer`, e o form continua
  funcionando sem JavaScript.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:populate
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 165 testes, Integration 77, Acceptance 31
(eram 159 / 45 / 23 na base `f31006b`; 164 / 68 / 29 no P2). PHPCS e
PHPStan sem erros. Schema não mudou; nenhuma classe nova.

No terminal, sem porta no host (`user@example.com` não está no
Evento 3 e já está no Evento 1):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=user@example.com&user[password]=123456' \
    http://localhost/login
# 201 {"ok":true,...,"available_spots":23}
docker compose exec web curl -s -i -X POST \
    -H 'Accept: application/json' -b /tmp/c \
    http://localhost/events/3/enrollments
# 422 {"ok":false,...,"errors":["você já está inscrito..."]}
docker compose exec web curl -s -i -X POST \
    -H 'Accept: application/json' -b /tmp/c \
    http://localhost/events/1/enrollments
```

Na tela: entre como `user@example.com`, abra um Evento em que não
está inscrito e clique "Inscrever-se": a mensagem e o contador mudam
sem recarregar a página.

## Relação com outros cenários

- Depende do P2 (`p2-inscricao-participante`), que depende do O2.
- O4 (`o4-resposta-json`): mesmo mecanismo (`acceptJson()` +
  `renderJson()`) na listagem do admin.
- P3 (cancelar) e P9 (lista de espera), irmãos sobre o P2: com P9, o
  JSON pode trazer `status` e a posição na fila; com P3, o mesmo
  padrão vale para o `DELETE` (o `enrollments.js` trocaria o botão de
  volta).
- P1/P4 (código de check-in e confirmação de presença, agente B): a
  confirmação de presença é outra boa candidata a responder JSON. Não
  estão implementados aqui.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p15-inscricao-json`.

Pré-requisito: o repositório alvo descende de `f31006b`. Este cenário
é empilhado: o `solucao.patch` e o range `main..fvp/...` trazem 3
commits (O2 + P2 + P15). Se o seu repositório já tem O2 e P2, aplique
só o topo. `<caminho>` abaixo:
`s50/fvp/4.2/exemplos/p15-inscricao-json`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p15 s50/fvp/4.2/exemplos/p15-inscricao-json/projeto
git fetch fvp-p15 fvp/p15-inscricao-json
# O2 + P2 + P15 (range)
git cherry-pick 0669a19^..8984b86
# ou só o topo (P15), se já tem O2 e P2
git cherry-pick 8984b86
git remote remove fvp-p15
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/p15-inscricao-json/solucao.bundle \
    fvp/p15-inscricao-json:fvp-p15
# range com a base (só se o seu main for o f31006b)
git cherry-pick main..fvp-p15
# ou só o topo
git cherry-pick 8984b86
# opcional, limpa a ref
git branch -D fvp-p15
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os 3 commits (O2 + P2 + P15)
git am s50/fvp/4.2/exemplos/p15-inscricao-json/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p15-inscricao-json/solucao.patch
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

- `EnrollmentsController::create`: P9 muda o `if` do sucesso (espera
  ou confirmada). Mantenha o bloco `if ($request->acceptJson())` logo
  depois do `save()` e o `if` do P9 no ramo HTML; no JSON acrescente
  `status`.
- `events/show.phtml`: P3 e P9 mudam o bloco "inscrito/não
  inscrito"; mantenha `data-enrollment-form` só no form de
  inscrição e os `span`s do contador.
- `ParticipantEnrollCest`: com o `fetch()`, os cliques em
  "Inscrever-se" precisam de `waitForText` antes das asserções.
- Só o topo sem P2: falta `EnrollmentsController` e a rota; aplique
  o P2 antes.

Depois de aplicar: testes e `./run phpcs` de novo.

## Armadilhas

- `acceptJson()` compara o header inteiro: `Accept: application/json,
  */*` não casa. No `fetch()`, defina `Accept` explicitamente.
- `return` depois do `renderJson()`: sem ele o método segue para a
  flash e o redirect.
- `http_response_code()` não tem efeito nos testes de controller (CLI);
  ali confira o corpo. O status é testado no teste de acesso (HTTP
  real, Guzzle com `http_errors => false` para o 4xx não virar
  exceção).
- O header do `renderJson` tem `chartset` (erro de grafia do
  framework, em `core/`); não é seu. `Content-Type` continua começando
  por `application/json`.
- `json_encode` escapa acentos (`ç`); é JSON válido.
- `ControllerTestCase::tearDown` limpa `HTTP_ACCEPT`; sem isso o
  próximo teste receberia JSON.
- Cest com `fetch()`: `see()` logo após o clique pode correr antes da
  resposta; use `waitForText`.
- `Validations::uniqueness` marca `event_id` e `user_id` com "já existe
  um registro com esse dado"; mandar esses textos no `errors` confunde
  o usuário, daí o `errorMessages()` preferir `base`.
