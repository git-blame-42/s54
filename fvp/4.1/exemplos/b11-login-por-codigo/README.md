# B11. Login por código de 4 dígitos gerado pelo backend

Pedido, como o professor faria na hora: "Em vez da senha, o usuário informa o
e-mail, o sistema gera um código entre 1000 e 9999 e ele entra com esse código.
O que me interessa é a validação do login, não o sorteio."

Chance: alta (foi o desafio do FVP da Entrega 4.1). Tempo estimado: 40 min.
Catálogo: `fvp/4.1/catalogo.md`, seção B11.

Branch no clone: `fvp/b11-login-por-codigo`. `main` continua equivalente ao
`86c81ca` do público (aqui `aa2b3be`; ver README raiz). **Um commit por
arquivo, na ordem em que as edições são necessárias**; cada arquivo editado
tem um comentário no topo com a lógica daquela peça e o próximo passo.

| Commit | Mensagem |
|--------|----------|
| `e4709a6` | `feat: Add login code columns to users table` |
| `5b04a04` | `feat: Generate and validate login codes in User` |
| `c7a1d5b` | `test: Cover login code generation and validation in UserTest` |
| `bc9d6af` | `feat: Keep the pending login code request in the session` |
| `191b037` | `test: Cover the login code session helpers in AuthTest` |
| `4081615` | `feat: Add login code routes` |
| `17b2528` | `feat: Add LoginCodesController` |
| `0396b5d` | `feat: Add the login code request form` |
| `f2f36e3` | `feat: Add the login code verification form` |
| `43274da` | `feat: Link the login page to the login by code` |
| `c4d9964` | `test: Cover LoginCodesController actions` |
| `45e9097` | `test: Cover login code routes access` |
| `062920e` | `test: Cover login by code in the browser` |

## O que mudou

Ordem de dentro para fora: banco, model, sessão, rotas, controller, views,
testes. Cada passo fecha no teste da sua camada.

1. `database/schema.sql`: três colunas em `users`:
   `login_code_digest VARCHAR(255) NULL` (hash do código, nunca o código),
   `login_code_expires_at DATETIME NULL` (validade) e
   `login_code_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0` (tentativas erradas).
   "Não há pedido" é `digest NULL`.
2. `app/Models/User.php`: as três colunas em `$columns` e no docblock
   `@property`; `LOGIN_CODE_TTL = 300` e `LOGIN_CODE_MAX_ATTEMPTS = 3`; o
   construtor zera `login_code_attempts` (coluna `NOT NULL` em `$columns`,
   mesma lição do `role`); `generateLoginCode()` (`random_int(1000, 9999)`,
   grava `password_hash` + validade, devolve o código em texto puro),
   `hasPendingLoginCode()` (há código, dentro da validade, tentativas sobrando),
   `authenticateWithLoginCode(string)` (a validação, abaixo) e
   `clearLoginCode()` (privado).
3. `tests/Unit/Models/Users/UserTest.php`: 6 testes, um por ponto de
   validação.
4. `lib/Authentication/Auth.php`: `startLoginCode(User)`, `loginCodeUser()`
   e `clearLoginCode()`. O pedido pendente ("este navegador pediu código para o
   Usuário X") vive em `$_SESSION['login_code']['user_id']`; só o id, nunca o
   código.
5. `tests/Unit/Lib/Authentication/AuthTest.php`: 1 teste.
6. `config/routes.php`: quatro rotas públicas `login_codes.*`
   (`GET`/`POST /login/code`, `GET`/`POST /login/code/verify`), fora dos grupos
   de middleware.
7. `app/Controllers/LoginCodesController.php` (novo): `new`, `create`,
   `verify`, `authenticate` e o helper privado `field()`.
8. `app/views/login_codes/new.phtml` (novo): formulário de e-mail.
9. `app/views/login_codes/verify.phtml` (novo): formulário do código.
10. `app/views/authentications/new.phtml`: link "Entrar com código".
11. `tests/Integration/Controllers/LoginCodesControllerTest.php` (novo): 6 testes.
12. `tests/Integration/Access/LoginCodesAccessTest.php` (novo): 2 testes.
13. `tests/Acceptance/authentications/LoginByCodeCest.php` (novo): 2 cenários.

Resultado: 13 arquivos, 7 novos; sem mudança em `core/`.

## A validação do login (o que explicar ao professor)

| # | Verificação | Onde | Se falhar |
|---|-------------|------|-----------|
| 1 | existe código pendente (`digest !== null`) | `hasPendingLoginCode` | recusa, limpa |
| 2 | dentro da validade (`expires_at > agora`, relógio do PHP) | `hasPendingLoginCode` | recusa, limpa |
| 3 | tentativas < 3 | `hasPendingLoginCode` | recusa, limpa |
| 4 | exatamente 4 dígitos (`/^\d{4}$/`) | `authenticateWithLoginCode` | conta tentativa |
| 5 | hash confere (`password_verify`, tempo constante) | `authenticateWithLoginCode` | conta tentativa; na 3ª, limpa |
| 6 | contador persistido no banco (`update`) | `authenticateWithLoginCode` | — |
| 7 | uso único: limpa no sucesso, antes de devolver `true` | `clearLoginCode` | — |
| 8 | o código é de quem pediu neste navegador | `Auth::loginCodeUser()` | controller manda pedir de novo |

Fluxo: `POST /login/code` → `User::findByEmail` → `generateLoginCode()` +
`Auth::startLoginCode()` → flash com o código → `302 /login/code/verify` →
`POST /login/code/verify` → `Auth::loginCodeUser()` → `authenticateWithLoginCode()`
→ `Auth::clearLoginCode()` + `Auth::login()` → `302 /dashboard`.

## Por que assim

- Regra no model, controller fino: toda a decisão "este código vale?" é
  `User::authenticateWithLoginCode()`; o controller só traduz em flash +
  redirect (`grading-path.md`, "Controllers thin").
- Sessão só em `Auth` (feedback 2025-2: lógica de sessão em
  `lib/Authentication/Auth.php`), mesmo padrão de `login/user/logout`.
- Hash em vez do código: mesmo idioma de `encrypted_password`
  (`password_hash`/`password_verify`); dump do banco não entrega códigos
  válidos; comparação em tempo constante (nunca `==`, que converte `'1e3'`).
- Validade curta + 3 tentativas + uso único + vínculo com o navegador: 4
  dígitos são 9000 combinações; sem limite, um script acerta em minutos.
- Contador no banco, não na sessão: descartar o cookie não zera as tentativas.
- `update()`, não `save()`: muda só as colunas do código, sem revalidar
  `uniqueness` nem tocar na senha.
- `random_int`: gerador criptográfico. É a única linha sobre geração.
- Validade gerada e comparada em PHP: um só relógio; nada de `NOW()` do MySQL.
- `field()` lê `getParam('login_code', [])` sem warning em form vazio (evita o
  `$params['email']` sobre `null` do login por senha; cf. A9/A16).
- Rotas públicas fora dos grupos, nomeadas `login_codes.*`; `return` depois
  de cada `redirectTo` porque nos testes de controller ele não encerra.
- Mensagens em pt-BR; identificadores, colunas e commits em inglês.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # o schema mudou
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado esperado: Unit 78 testes, Integration 17, Acceptance 11 (eram
71 / 9 / 9 na base). Testes novos:

- `UserTest`: `test_generate_login_code_should_return_four_digits_and_store_only_a_digest`,
  `test_authenticate_with_login_code_should_accept_the_code_only_once`,
  `test_authenticate_with_login_code_should_reject_a_wrong_code_and_count_the_attempt`,
  `test_authenticate_with_login_code_should_lock_after_three_wrong_attempts`,
  `test_authenticate_with_login_code_should_reject_an_expired_code`,
  `test_authenticate_with_login_code_should_reject_malformed_input_and_no_pending_code`.
- `AuthTest`: `test_login_code_request_should_live_in_the_session_until_cleared`.
- `LoginCodesControllerTest`: `new` renderiza o form; `create` gera e
  redireciona para `/login/code/verify` (e-mail desconhecido volta para
  `/login/code`); `authenticate` com o código certo loga e vai para
  `/dashboard`; errado não loga e volta para `/login/code/verify`; sem pedido
  pendente volta para `/login/code`.
- `LoginCodesAccessTest`: `GET /login/code` 200; `GET /login/code/verify` sem
  pedido 302 `/login/code`.
- `LoginByCodeCest::loginWithAValidCode` (lê o código do flash na tela) e
  `loginWithAWrongCode` (`0000`, fora do intervalo).

No navegador: `http://localhost/login` → "Entrar com código" → e-mail
`user@example.com` → "Receber código". O flash mostra o código; em
`./run db:console`, `SELECT email, login_code_digest, login_code_expires_at,
login_code_attempts FROM users;` mostra o hash, a validade e 0 (o código não
está no banco). Código errado: "Código inválido!" e `attempts = 1`. Código
certo: `/dashboard` logado; as colunas voltam a `NULL` e `last_login_at` é
preenchido. Abrir `/login/code/verify` de novo volta para pedir outro.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`fvp/recriar.sh fvp/4.1/exemplos/<cenario>`, a partir da raiz do s50
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.1/exemplos/b11-login-por-codigo`. São 13 commits: pegue o
intervalo inteiro, na ordem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b11 <caminho>/projeto
git fetch fvp-b11 fvp/b11-login-por-codigo
git cherry-pick e4709a6^..062920e
git remote remove fvp-b11
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b11-login-por-codigo:fvp-b11
git cherry-pick e4709a6^..062920e   # SHAs do bundle como argumento; o cherry-pick cria commits novos
git branch -D fvp-b11              # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # 13 commits novos, mesmas mensagens
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `database/schema.sql`: B3, B5, B9, A7 e D3 também acrescentam linhas depois
  de `last_login_at`; mantenha todas.
- `app/Models/User.php`: os mesmos cenários (e B10, A18, H2) mexem em
  `$columns`, no docblock e no construtor; junte as listas e mantenha os dois
  padrões do construtor.
- `lib/Authentication/Auth.php`: A13 (regenerar sessão), A14 (expirar) e H1
  (`Auth::attempt`) acrescentam métodos; mantenha os dois lados.
- `config/routes.php`: A2, A3, A5, A6, A7 e D1 mexem perto das rotas de
  login; as quatro rotas `login_codes.*` continuam fora dos grupos (A5
  `guest` pode envolvê-las, se quiser).
- `app/views/authentications/new.phtml`: A9, A10, A15 e C4 mexem no form;
  mantenha o link "Entrar com código" depois do botão.
- `UserTest`, `AuthTest`: métodos novos no fim da classe; mantenha os dois
  lados.

Depois de aplicar: `./run db:reset` (o schema mudou); rode os testes,
`./run phpcs` e `./run phpstan` de novo.

## Armadilhas

- Sem `./run db:reset`, o pedido de código dá `Unknown column 'login_code_digest'`.
- Sem o zero no construtor, `save()` manda `NULL` para `login_code_attempts`
  (`NOT NULL`) e o populate e todos os `new User([...])` dos testes falham.
- `update([...])` não valida e devolve `false` quando nada mudou; os testes
  conferem o valor relido com `findById`, não o retorno.
- Nunca `$code == $user->login_code`: além de o código não estar no banco,
  `==` converte strings numéricas (`'1e3' == '1000'`).
- `GET` para verificar o código poria o código na URL (histórico, logs); os
  dois formulários são `POST`.
- Nos testes de controller o `redirectTo` não encerra a ação: sem os `return`
  do controller, o teste veria dois `Location:`.
- O código aparece na tela porque o projeto não envia e-mail; diga isso em
  voz alta no FVP. Em produção, a mensagem de e-mail desconhecido seria
  neutra e o envio sairia por um `Service`.
- Variações do pedido: senha **e** código (2FA) = chamar `generateLoginCode`
  + `Auth::startLoginCode` no sucesso da senha, sem `Auth::login`, e
  redirecionar para `/login/code/verify`; outro tamanho/validade/tentativas =
  `random_int(min, max)`, a regex `\d{N}` e as duas constantes.
