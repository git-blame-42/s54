# P12. Código aleatório no login (a FVP real da 4.1)

Pedido, como o professor fez na FVP da 4.1 (29/10): "Depois de validar
e-mail e senha, o backend gera um número aleatório de 1000 a 9999 e o
devolve; o usuário informa esse número e só então o login é
concluído. O foco é a validação do login pelo código."

Chance: média-baixa (já caiu na 4.1; o padrão "valor gerado e
validado no servidor" volta em P1 e P4). Tempo estimado: 30-40 min.
Catálogo: `s50/fvp/4.2/catalogo.md`, P12.

Branch no clone: `fvp/p12-codigo-aleatorio-login`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

Commit:

- `a6c901c` `feat: Require a random access code after the password on
  login`

## O que mudou

- `database/schema.sql`: em `users`, `login_code SMALLINT UNSIGNED
  NULL` e `login_code_expires_at TIMESTAMP NULL`.
- `app/Models/User.php`: as duas colunas em `$columns` e `@property`;
  `LOGIN_CODE_TTL = 300` (5 min);
  - `generateLoginCode(): int`: `random_int(1000, 9999)`, grava código
    e validade com `update()` e devolve o código;
  - `verifyLoginCode(string $code): bool`: falso sem código, vencido,
    não numérico (`ctype_digit`) ou diferente (`(int)` dos dois
    lados); certo apaga o código (uso único) e devolve verdadeiro.
- `app/Controllers/AuthenticationsController.php`:
  - `authenticate`: com senha certa **não** chama `Auth::login`; gera
    o código, guarda `$_SESSION['pending_user_id']`, põe o flash
    "Seu código de acesso é 1234" e redireciona para `/login/code`.
    Senha errada: igual a antes (o código não aparece).
  - `code()` (`GET /login/code`): sem login pendente volta para
    `/login`; senão mostra o form.
  - `verifyCode()` (`POST /login/code`): sem pendente -> `/login`;
    código (só string, com `trim`) certo -> tira o pendente,
    `Auth::login`, "Login realizado com sucesso!", `/dashboard`;
    errado -> "Código inválido!" e volta para `/login/code`.
  - `pendingUser()` (privado): `User::findById` do id da sessão.
- `config/routes.php`: `users.code` e `users.verify_code`, públicas
  (fora do grupo `auth`), ao lado de `/login`.
- `app/views/authentications/code.phtml` (novo): campo `code`
  (`inputmode="numeric"`, `maxlength="4"`) e botão "Confirmar".
- Testes que dependiam do login em um passo:
  - `tests/Support/Helper/LoginHelper.php`: `login()` agora chama
    `submitCredentials()` e, se cair em `/login/code`, lê o código do
    alerta (`grabLoginCode()`, regex de 4 dígitos) e confirma. Os
    Cests que usam `$page->login(...)` não mudaram.
  - `tests/Support/AcceptanceTester.php`: `@method` dos novos.
  - `EventsAccessTest::loggedClient`: espera `/login/code` e faz o
    segundo POST com o código lido do banco.
  - `AuthenticationsControllerTest`: o teste de senha certa virou
    "pede o código" (`Auth::check()` falso, `Location: /login/code`,
    código entre 1000 e 9999 no banco e no flash).

## Por que assim

- Versão do catálogo, com o código no model: "valor gerado e validado
  no model" é o que o professor cobra; o controller só orquestra. A
  sessão guarda apenas quem está no meio do login.
- Alternativa mais rápida: guardar `$_SESSION['login_code']` e
  `$_SESSION['login_user_id']`, sem schema. Funciona, mas a regra fica
  no controller e não há validade. Dá para fazer em ~20 min se o tempo
  apertar; diga ao professor qual escolheu e por quê.
- Mostrar o código no flash: o projeto não envia e-mail; o pedido diz
  que "o backend devolve o número". É a simulação do envio e permite
  que o Cest e o professor leiam o código na tela. Só aparece depois
  da senha certa.
- `update()` (não `save()`): não valida, então não exige
  `password_confirmation` e não toca na senha.
- Validade de 5 minutos e uso único vieram do código do catálogo
  (custam duas linhas). Limite de tentativas ficou de fora (extra).
- Senha errada continua com a mesma mensagem e o mesmo destino.

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
```

Resultado obtido: Unit 164 testes, Integration 51, Acceptance 26
(base: 159 / 45 / 23). PHPCS e PHPStan sem erros. Sem classe nova.

Testes novos:

- `UserTest`:
  - `test_generate_login_code_should_store_a_four_digit_code_with_expiration`
  - `test_verify_login_code_should_accept_the_right_code_only_once`
  - `test_verify_login_code_should_reject_wrong_or_malformed_codes`
  - `test_verify_login_code_without_code_should_be_false`
  - `test_verify_login_code_should_reject_an_expired_code`
- `AuthenticationsControllerTest`:
  - `test_authenticate_with_valid_credentials_should_ask_for_the_code`
    (substitui `..._should_login`)
  - `test_code_page_renders_form_when_there_is_a_pending_login`
  - `test_code_page_without_pending_login_redirects_to_login`
  - `test_verify_code_with_right_code_should_login`
  - `test_verify_code_with_wrong_code_should_not_login`
  - `test_verify_code_with_array_code_should_not_login`
  - `test_verify_code_without_pending_login_redirects_to_login`
- `LoginCest`:
  - `loginAsksForTheCodeAfterThePassword`
  - `loginWithWrongCodeThenRightCode`
  - `codePageWithoutPasswordRedirectsToLogin`

No navegador: entre com `user@example.com` / `123456`. Abre a tela
"Código de acesso" com o alerta "Seu código de acesso é NNNN". Digite
outro número: "Código inválido!". Digite o certo: painel com "Login
realizado com sucesso!". Pelo terminal (clone sem porta):

```
docker compose exec web sh -c "curl -s -c /tmp/k -b /tmp/k -i \
  -d 'user[email]=user@example.com&user[password]=123456' \
  http://localhost/login | grep -i location; \
  curl -s -b /tmp/k http://localhost/login/code \
  | grep -o 'Seu código de acesso é [0-9]*'"
# Location: /login/code
# Seu código de acesso é 6360
```

## Relação com outros cenários

- P1 (`p1-codigo-checkin-aleatorio`) e P4
  (`p4-confirmar-presenca-codigo`): mesma ideia (`random_int` no model,
  conferência no servidor), aplicada ao Evento. Este é o modelo.
- Q1, R1, P10: qualquer cenário com Cest usa `$page->login(...)`; com
  este aplicado, o helper já faz o segundo passo. Testes de acesso com
  `loggedClient` próprio (ex.: `CommentsAccessTest` do P10) precisam
  da mesma mudança do `EventsAccessTest` daqui.
- R1: se o R1 estiver aplicado, o flash do código passa pelo partial
  escapado (só dígitos, sem efeito).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/p12-codigo-aleatorio-login`
(devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `f31006b` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/fvp/4.2/exemplos/p12-codigo-aleatorio-login`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-p12 \
    s50/fvp/4.2/exemplos/p12-codigo-aleatorio-login/projeto
git fetch fvp-p12 fvp/p12-codigo-aleatorio-login
git cherry-pick a6c901c
git remote remove fvp-p12
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/p12-codigo-aleatorio-login/solucao.bundle \
    fvp/p12-codigo-aleatorio-login:fvp-p12
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick a6c901c
# opcional, limpa a ref
git branch -D fvp-p12
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/p12-codigo-aleatorio-login/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/p12-codigo-aleatorio-login/solucao.patch
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

- `AuthenticationsController::authenticate`: cenários de login (A9,
  A16 da 4.1) mudam a leitura dos parâmetros. Fique com a validação
  deles e troque o `Auth::login($user)` pelo bloco do código daqui.
- `User.php` (`$columns`) e `schema.sql` (`users`): outros cenários
  acrescentam colunas; fique com todas.
- `LoginHelper.php` e `loggedClient` de testes de acesso: se o outro
  lado criou testes de acesso novos com login próprio, aplique neles o
  segundo POST (`/login/code` com o código lido do banco).

Depois de aplicar: `./run db:populate` (schema mudou), os testes e
`./run phpcs`.

## Armadilhas

- `Auth::login` antes do código anula a feature: o login só acontece
  em `verifyCode`.
- Gerar o código na view ou em JavaScript não é "o backend devolve".
- `'1234' == 1234` passa e `===` não: converta os dois com `(int)` e
  confira `ctype_digit` antes (senão `"1234x"` viraria 1234).
- `code` pode chegar como array: o controller só aceita string.
- O código não pode aparecer para quem errou a senha (o teste de
  senha errada confere que `login_code` continua `NULL`).
- Sem ajustar `LoginHelper` e `loggedClient`, todos os Cests e os
  testes de acesso quebram (esperam `/dashboard` logo após a senha).
  Avise o professor que vai ajustar e faça logo após a feature.
- Depois de errar, o alerta com o código não aparece de novo (o flash
  é lido uma vez); o código continua valendo até vencer. Para um novo
  código, volte a `/login`.
- `db:populate` refaz o schema: sem ele, o banco de desenvolvimento
  não tem as colunas novas e o login dá 500.
