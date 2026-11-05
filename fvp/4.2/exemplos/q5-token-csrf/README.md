# Q5. Token CSRF nos forms de Evento (middleware + alias; ordem dos grupos)

Pedido, como o professor faria na hora: "Protejam criar, editar e
remover Evento contra CSRF: todo POST/PUT/DELETE da área logada só
pode ser aceito com um token gerado pelo servidor. Usem um middleware."

Chance: média-baixa. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, Q5. Versão geral do A15 da 4.1 (token CSRF
só no login).

Branch no clone: `fvp/q5-token-csrf`, empilhada sobre O9
(`fvp/o9-method-so-via-post`, mesmo SHA do exemplo do O9), que parte de
`main` (`f31006b`). Base: `main` → O9 → Q5.

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `7b2351f` | (base, O9) `fix: Accept _method override only from ...`  |
| `41fd8ba` | `feat: Protect Event forms with a CSRF token`            |

A mensagem completa do commit de O9 é `fix: Accept _method override
only from POST body`.

## O que mudou

- `lib/Csrf.php` (novo, como no A15): `Csrf::token()` gera 32 bytes
  (`random_bytes`, 64 hex) na primeira chamada e guarda em
  `$_SESSION['_token']`; depois devolve o mesmo. `Csrf::check(mixed
  $token)`: `true` só se há token na sessão e o valor é string igual,
  com `hash_equals`.
- `core/helpers.php`: `csrf_field()` devolve `<input type="hidden"
  name="_token" value="...">` (as views fazem `<?= csrf_field() ?>`).
- `app/Middleware/VerifyCsrf.php` (novo): `GET`/`HEAD` passam; os
  demais métodos (já resolvidos pelo `_method` do O9) exigem
  `Csrf::check($_POST['_token'] ?? null)`; senão `deny()`: HTTP 403
  com uma página curta e `exit`. `deny()` é `protected` para o dublê
  do teste.
- `config/App.php`: alias `'csrf' => \App\Middleware\VerifyCsrf::class`.
- `config/routes.php`: `Route::middleware('csrf')->group(...)`
  envolvendo o grupo `auth` inteiro (que contém o `admin`).
- Views: `_form.phtml` (novo e editar) e o form "Remover" de
  `index.phtml` ganharam `<?= csrf_field() ?>`.
- Login e logout ficaram fora do grupo (login: A15; logout é `GET`).

## Por que assim

- Ordem dos grupos: o `RouteWrapperMiddleware` acrescenta o middleware
  às rotas quando o grupo **fecha**; o grupo mais interno fecha
  primeiro, então os middlewares dele rodam primeiro. Com `csrf` por
  fora do `auth`, a sequência numa rota admin é `admin` → `auth` →
  `csrf`: sem sessão continua indo para `/login`, Participante
  continua indo para `/dashboard` (os testes de acesso existentes não
  mudam) e só depois o token é conferido. Com `csrf` por dentro, um
  POST sem sessão daria 403 em vez de `/login`.
- 403 (e não redirect com flash): é falha de segurança/sessão, não erro
  de preenchimento; não há para onde "voltar" com segurança (o
  `Referer` pode faltar). O formulário normal sempre traz o token, então
  o usuário comum não vê a página. Alternativa, se o professor
  preferir: `FlashMessage::danger('Sessão expirada, tente de novo.')`
  e redirect para `admin.events.index`, como o A15 faz no login.
- Token lido só de `$_POST`: igual ao `_method` do O9 (base). Token na
  query string vaza em log, histórico e `Referer`. É a exceção ao
  "parâmetro só via `getParam`", porque o `Request` mistura GET e POST
  em `$_REQUEST`.
- Um token por sessão (não por formulário): várias abas funcionam e
  basta para CSRF; o atacante não lê a página da vítima.
- Middleware e não `if` em cada ação: vale para toda rota que altera
  estado no grupo, inclusive as que entrarem depois (P5, P6, P16...).
- Testes de controller não passam pelo router (não rodam middleware),
  então não precisam do token; os testes de acesso (HTTP real) sim.

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

Resultado obtido: Unit 178, Integration 55, Acceptance 23 (com O9:
165 / 49 / 23; `main`: 159 / 45 / 23). PHPCS e PHPStan sem erros;
PSR-4 estrito ok. Os Cests passam sem mudança: o form já traz o campo.

Testes novos:

- `tests/Unit/Lib/CsrfTest.php` (7): gera e guarda; estável na sessão;
  certo → `true`; errado/truncado → `false`; `null`/vazio/array →
  `false`; sem token na sessão → `false`; `csrf_field()` renderiza o
  campo.
- `tests/Unit/App/Middleware/VerifyCsrfTest.php` (6, dublê
  `VerifyCsrfDouble`, como no F2 da 4.1): GET passa; POST sem token e
  com token errado recusados; POST com o token passa; PUT/DELETE via
  `_method` exigem o token; token só na query string recusado.
- `EventsAccessTest`: provider `stateChangingRoutes()` (create,
  update, destroy sem token → 403, nada muda); token errado → 403;
  create com o token lido da página → 302 e criado; token de outra
  sessão → 403. O teste do O9
  `test_admin_should_destroy_event_with_method_delete_in_post_body`
  passou a enviar o token (helper `csrfToken()`, que lê o campo de
  `/admin/events/new`).
- `EventsControllerTest`: os forms de novo, editar e "Remover" trazem
  o token da sessão.

Na tela, sem token (dentro da rede do compose):

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -o /dev/null -w '%{http_code}\n' \
    -b /tmp/c -d '_method=DELETE' http://localhost/admin/events/16
```

Resposta: `403`. Pelo navegador, remover pela listagem continua
funcionando.

## Relação com outros cenários

- O9 (`o9-method-so-via-post`): base. O9 impede que um `GET` vire
  `DELETE`; Q5 impede que um `POST` vindo de outro site seja aceito.
  Juntos fecham o CSRF por `<img>` e por form forjado.
- A15 da 4.1 (token CSRF no login): mesmo `Lib\Csrf`; ali sem
  middleware, só no `authenticate`. Se os dois forem aplicados, mantenha
  uma classe só e ponha o login no grupo `csrf` (fora do `auth`) ou
  mantenha a conferência do A15 no controller.
- A17 da 4.1 (ordem dos middlewares corrigida no core): o A17 troca o
  `addMiddleware` por um *prepend*, e o grupo **externo** passa a rodar
  primeiro. Com o A17 aplicado, este `csrf` por fora rodaria **antes**
  do `auth`: um POST sem sessão daria 403 em vez de `/login`, e os
  testes de acesso de "sem sessão → /login" para POST/PUT/DELETE
  quebram. Com A17, ponha o `csrf` como o grupo **mais interno**
  (dentro do `admin`), para que rode por último.
- P5, P6, P16, P17, P2...: cada form novo (cancelar, duplicar, remover
  com código, inscrever-se) precisa de `<?= csrf_field() ?>`; sem ele,
  o botão passa a dar 403.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/q5-token-csrf`.

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público. `<caminho>` abaixo é `s50/fvp/4.2/exemplos/q5-token-csrf`.

Escolha o que aplicar:

- Sem O9: aplique o range inteiro `5dedab5..41fd8ba` (O9 + Q5, dois
  commits, nessa ordem).
- Com O9 já aplicado: aplique só o topo, `41fd8ba`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-q5 s50/fvp/4.2/exemplos/q5-token-csrf/projeto
git fetch fvp-q5 fvp/q5-token-csrf
# range (O9 + Q5):
git cherry-pick 5dedab5..41fd8ba
# ou só o topo, se já tem O9:
# git cherry-pick 41fd8ba
git remote remove fvp-q5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/fvp/4.2/exemplos/q5-token-csrf/solucao.bundle \
    fvp/q5-token-csrf:fvp-q5
git cherry-pick 5dedab5..41fd8ba
# ou só o topo: git cherry-pick 41fd8ba
# opcional, limpa a ref
git branch -D fvp-q5
```

### 3. A partir do `solucao.patch` (`git am`)

O patch traz os dois commits (O9 e Q5), em ordem.

```
git am s50/fvp/4.2/exemplos/q5-token-csrf/solucao.patch
# já tem O9? quando o primeiro commit vier vazio ou conflitar:
# git am --skip
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classes novas) e os testes.

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

- `config/routes.php`: o grupo `auth` inteiro ganhou um nível de
  indentação. Qualquer rota nova de outro cenário conflita no texto;
  junte as rotas e mantenha o `Route::middleware('csrf')` por fora.
- `config/App.php`: outro alias novo (ex.: `participant`, Q3).
- `index.phtml` e `_form.phtml`: botões e campos de outros cenários;
  todo form `method="post"` precisa do `csrf_field()`.
- `EventsAccessTest`: o teste do O9 e testes novos de outros
  cenários que façam POST/PUT/DELETE como admin precisam do token
  (`csrfToken($client)`).
- Se o A15 também estiver aplicado: duas versões de `lib/Csrf.php`;
  fique com uma (a API é a mesma).

## Armadilhas

- `csrf` por dentro do `auth` (ou só no grupo `admin`), no core atual:
  roda antes da autenticação e um POST sem sessão vira 403 em vez de
  `/login`; os testes de acesso existentes quebram.
- Com o A17 da 4.1 aplicado, é o inverso: o grupo externo roda
  primeiro, então o `csrf` tem de ser o grupo mais interno. Confira
  sempre em que ordem o seu core roda os grupos antes de decidir.
- Barrar `GET` também: a listagem e os links deixam de funcionar.
  Só métodos que alteram estado.
- Ler `_token` com `getParam` (que usa `$_REQUEST`): aceita token na
  query string.
- `===` em vez de `hash_equals`: funciona, mas vaza tempo de
  comparação; é o que o professor espera ver.
- Gerar um token novo a cada `token()`: a segunda aba invalida a
  primeira, e o teste "estável na sessão" pega.
- Esquecer o campo num form (inclusive os de outros cenários): o
  botão dá 403. Os Cests da listagem/edição pegam isso.
- `deny()` `private` com `exit`: o teste unitário do middleware não
  roda; deixe `protected` e use o dublê.
- Os testes de controller continuam passando sem token: eles chamam a
  ação direto. A garantia de verdade vem dos testes de acesso (HTTP).
