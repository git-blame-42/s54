# C6. Miudezas que o professor pede como "alteração pontual"

Pedido, como o professor faria na hora: "Faz o logout voltar para a
home", "Mostra no painel quando o usuário se cadastrou", "Troca o nome
do perfil `participant` para `user`" (uma de cada vez).

Chance: alta (cada uma é trivial). Tempo estimado: 5 a 10 min cada.
Catálogo: `fvp-possiveis-modificacoes.md`, seção C6.

Branch no clone: `fvp/c6-miudezas`. `main` continua igual ao
repositório público (`86c81ca`).

Este cenário tem UM COMMIT POR MIUDEZA, de propósito: o professor pede
uma só, e o README mostra como trazer só aquele item. A suíte inteira
ficou verde depois de cada commit.

| # | Commit | Mensagem | Unit / Integration / Acceptance depois do commit |
|---|--------|----------|------|
| 1 | `ed929f8` | `feat: Redirect to home after logout` | 71 / 9 / 9 |
| 2 | `d6b53ab` | `feat: Distinguish unknown email from wrong password on login` | 71 / 10 / 10 |
| 3 | `6b5b91e` | `feat: Use page title in the document title` | 71 / 11 / 10 |
| 4 | `05bc3fb` | `feat: Show admin-only content on user dashboard` | 71 / 13 / 11 |
| 5 | `1e6ea64` | `feat: Redirect authenticated users from home to dashboard` | 71 / 14 / 12 |
| 6 | `4aa27c0` | `refactor: Rename participant role to user` | 71 / 14 / 12 |
| 7 | `ee55056` | `feat: Show member since date on profile` | 75 / 15 / 12 |

Total: 14 arquivos, +249 / -17 linhas.

## O que mudou

Commit 1, logout volta para a home:

- `app/Controllers/AuthenticationsController.php`: `destroy` redireciona
  para `route('root')` em vez de `route('users.login')`.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  `test_destroy_should_logout` espera `Location: /` (e confere que não é
  mais `/login`).
- `tests/Acceptance/authentications/LoginCest.php`: `logout` confere
  `seeCurrentUrlEquals('/')`; o `see('Login')` segue valendo pela navbar.

Commit 2, mensagens distintas para e-mail inexistente e senha errada:

- `app/Controllers/AuthenticationsController.php`: o `if` único virou
  dois, com `return` em cada ramo: `E-mail não cadastrado!` quando
  `findByEmail` devolve `null`, `Senha incorreta!` quando `authenticate`
  falha.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  `test_authenticate_with_invalid_credentials_should_not_login` virou
  `test_authenticate_with_wrong_password_should_not_login` e ganhou o
  irmão `test_authenticate_with_unknown_email_should_not_login`; os dois
  conferem o flash com `FlashMessage::get()`. `tearDown()` novo limpa
  `$_SESSION['flash']` (o `ControllerTestCase` só limpa
  `$_SESSION['user']`, e o flash do login válido vazava para o teste
  seguinte).
- `tests/Acceptance/authentications/LoginCest.php`:
  `loginWithInvalidCredentials` virou `loginWithWrongPassword` e ganhou
  `loginWithUnknownEmail`.

Commit 3, `<title>` dinâmico:

- `app/views/layouts/application.phtml`:
  `<title><?= $title ?> - Eventos do Câmpus</title>`. Todo `render` do
  projeto já passa `$title`.
- `AuthenticationsControllerTest`:
  `test_render_login_page_should_use_page_title_in_document_title`
  procura `<title>Login - Eventos do Câmpus</title>`.
- `tests/Acceptance/home/HomeIndexCest.php`: `seeHomePage` acrescenta
  `seeInTitle('Home Page - Eventos do Câmpus')`.

Commit 4, conteúdo só para admin no painel do usuário:

- `app/views/dashboard/index.phtml`: bloco
  `<?php if ($this->currentUser()->isAdmin()) : ?>` com "Você é
  administrador(a)." e link para `route('admin.dashboard.index')`.
- `tests/Integration/Controllers/DashboardControllerTest.php` (novo):
  `test_index_as_admin_should_show_admin_only_content` e
  `test_index_as_regular_user_should_hide_admin_only_content` (este usa o
  perfil padrão, sem escrever `participant`, para não depender do
  commit 6).
- `tests/Acceptance/dashboard/DashboardCest.php`:
  `accessDashboardAfterLogin` confere `dontSee` do bloco; novo
  `accessDashboardAsAdminShowsAdminOnlyContent` loga como admin, vê o
  bloco e segue o link até `/admin`.

Commit 5, raiz manda o usuário logado para o painel:

- `app/Controllers/HomeController.php`: `if (Auth::check())` redireciona
  para `route('dashboard.index')` e dá `return`.
- `tests/Integration/Controllers/HomeControllerTest.php`:
  `test_index_when_authenticated_should_redirect_to_dashboard` (com
  `Auth::login`, espera `Location: /dashboard`).
- `tests/Acceptance/home/HomeIndexCest.php`:
  `authenticatedUserIsRedirectedToDashboard`. `HomeAccessTest` e
  `seeHomePage` (sem sessão) não mudaram.

Commit 6, perfil `participant` vira `user`:

- `database/schema.sql`: `ENUM('admin', 'user') ... DEFAULT 'user'`.
- `app/Models/User.php`: padrão do construtor `'user'`.
- `tests/Unit/Models/Users/UserTest.php`:
  `test_is_admin_should_return_false_by_default` espera `'user'`.
- `database/Populate/populate.php`: mensagem final diz `(user)`.

Commit 7, "Membro desde" no painel:

- `app/Models/User.php`: `createdAt(string $format = 'Y-m-d H:i:s'):
  ?string` com SELECT próprio (`SELECT created_at FROM users WHERE id =
  :id`); `null` para registro novo. `created_at` continua FORA de
  `$columns`.
- `app/views/dashboard/index.phtml`: `<p>Membro desde <?=
  $this->currentUser()->createdAt('d/m/Y') ?>.</p>`.
- `UserTest` (+4): `test_created_at_should_be_filled_by_the_database`,
  `test_created_at_should_use_the_given_format`,
  `test_created_at_should_be_null_for_new_record`,
  `test_save_should_keep_working_without_created_at_in_columns`.
- `DashboardControllerTest`: `test_index_should_show_member_since_date`
  (fixa `created_at` em 2024-03-15 e procura `Membro desde 15/03/2024.`).
- `DashboardCest::accessDashboardAfterLogin`: `see('Membro desde')`.

## Por que assim

- Um commit por item, mensagem em inglês `type: Title` (`grading-path.md`):
  dá para mostrar o histórico ao professor e trazer só o item pedido.
- Controllers continuam finos: o SELECT de `created_at` está no model
  (padrão de SQL próprio da seção D4), não no controller nem na view.
- `created_at` fora de `$columns` de propósito (armadilha da seção 7):
  lá dentro, `save()` mandaria `NULL` explícito para uma coluna
  `NOT NULL` e o MySQL recusaria. Há teste que garante isso.
- Mensagem única no login ("E-mail ou senha inválidos!") é a prática
  recomendada: mensagens distintas revelam quais e-mails existem
  (enumeração de usuários, OWASP). O commit 2 existe porque o professor
  pode pedir; se pedir, implemente e cite o risco.
- Nos dois casos de falha do login há `return` depois de `redirectTo`:
  no teste de controller o `redirectTo` não interrompe a ação (seção 7).
- Rotas nomeadas sempre (`route('root')`, `route('dashboard.index')`),
  nada de URL fixa no código.
- Testes nas camadas que fazem sentido: unit (model), controller
  (resposta e redirect), aceitação (navegador). Textos pt-BR na
  interface, identificadores e valores do banco em inglês (`user`).
- PSR-12, PHPStan nível 6 e PSR-4 (`dump-autoload --strict-psr`) sem
  avisos.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # o schema mudou no commit 6
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido (no topo da branch): Unit 75 testes, Integration 15,
Acceptance 12 (eram 71 / 9 / 9 na base). Contagem por commit na tabela
lá em cima. Testes novos: `UserTest` (+4, commit 7),
`AuthenticationsControllerTest` (+2: e-mail desconhecido no commit 2,
`<title>` no commit 3), `DashboardControllerTest` (novo, 2 no commit 4 e
1 no commit 7), `HomeControllerTest` (+1, commit 5), `LoginCest` (+1,
commit 2), `DashboardCest` (+1, commit 4), `HomeIndexCest` (+1,
commit 5).

No navegador: `http://localhost`.

1. Logue como `user@example.com` / `123456`: a aba do navegador mostra
   "Painel do Usuário - Eventos do Câmpus" (3), o painel mostra "Membro
   desde dd/mm/aaaa" (7) e não mostra o bloco de admin (4).
2. Clique em "Eventos do Câmpus" na navbar: volta para `/dashboard` (5).
3. Menu do e-mail > "Sair": cai na home `/` com "Logout realizado com
   sucesso!" (1).
4. Tente logar com `ninguem@example.com`: "E-mail não cadastrado!";
   com `user@example.com` e senha errada: "Senha incorreta!" (2).
5. Logue como `admin@example.com` / `123456` e abra "Painel": aparece
   "Você é administrador(a)." com link para a área administrativa (4).
6. O populate imprime `user@example.com (user)` (6).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/c6-miudezas` (relativo ao clone do s50).

Pegue só o item que o professor pediu. Exemplo, só o logout para a home
(item 1):

```
git cherry-pick ed929f8
```

Itens 1 a 6 aplicam sozinhos e limpos em `86c81ca`, em qualquer ordem
(testado: cada um isolado, e os sete em ordem invertida). O item 7
depende do 4 (mesma view e mesmo `DashboardControllerTest`); traga os
dois, nesta ordem:

```
git cherry-pick 05bc3fb ee55056
```

Tudo de uma vez: `git cherry-pick main..fvp-c6` (com a branch buscada
como `fvp-c6`, veja abaixo). Cada cherry-pick cria um commit novo (SHA
diferente, mesma mensagem e mesmo conteúdo).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-c6 <caminho>/projeto
git fetch fvp-c6 fvp/c6-miudezas
git cherry-pick ed929f8        # ou o SHA do item pedido
git remote remove fvp-c6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/c6-miudezas:fvp-c6
git cherry-pick ed929f8        # o bundle traz os objetos dos sete commits
git branch -D fvp-c6           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

O patch tem os sete commits em sequência. Para todos:

```
git am <caminho>/solucao.patch       # sete commits novos, mesmas mensagens
git apply --3way <caminho>/solucao.patch   # só o diff, sem commit
```

Para um item só, prefira o bundle (seção 2). Com patch, gere o do item
a partir do clone: `git -C <caminho>/projeto format-patch -1 ed929f8
--stdout > item1.patch` e depois `git am item1.patch`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê (testado com o
bundle de cada cenário aplicado antes do item):

- Item 7 sem o item 4: conflita em `app/views/dashboard/index.phtml`,
  `DashboardCest.php` e `DashboardControllerTest.php` (este não existe
  sem o 4). Traga o 4 antes.
- Itens 1 e 5 mexem no fluxo de login/logout e nos testes de
  login/home, mas em arquivos diferentes; aplicam juntos sem conflito
  em qualquer ordem. Juntos, o logout cai em `/` e mostra a home
  porque a sessão já acabou; se algum dia o logout deixar a sessão
  viva, o item 5 joga o usuário de volta para `/dashboard`.
- Item 2 com C3 (textos), A9 (campos vazios) ou A10 (manter e-mail):
  todos reescrevem o `authenticate` e o
  `LoginCest::loginWithInvalidCredentials`. Resolva mantendo os dois
  `if` deste item e acrescentando a regra do outro cenário (validação
  de campos vazios antes, e-mail mantido em cada ramo de falha); com
  C3, use os textos novos de C3 em cada mensagem.
- Item 6 com A7 (perfil `organizer`): os dois mudam o `ENUM` do
  `schema.sql` e a mensagem do populate. Resultado:
  `ENUM('admin', 'user', 'organizer') NOT NULL DEFAULT 'user'`.
- Item 6 com H2 (constantes de perfil): H2 cria
  `ROLE_PARTICIPANT = 'participant'` e usa a constante no construtor,
  no populate e no `UserTest`. Resolva mantendo o código de H2 e
  trocando só o valor: `ROLE_USER = 'user'` (ou
  `ROLE_PARTICIPANT = 'user'`, se não quiser renomear a constante).
- Item 4 com B4 (último acesso no painel) ou C2 (nome na navbar): ambos
  criam `DashboardControllerTest.php` (e B4 também edita
  `dashboard/index.phtml` e `DashboardCest`). Fique com os testes e o
  HTML dos dois lados.
- A2 (logout via DELETE) e A3 (logout só logado) não conflitam com o
  item 1.

Depois de aplicar: `./run db:reset` se trouxe o item 6 (o schema
mudou); rode os testes e o `./run phpcs` de novo.

## Armadilhas

- Item 6 sem `./run db:reset`: o banco de desenvolvimento ainda tem o
  `ENUM` antigo e `new User()` sem `role` falha no `save()` com
  "Data truncated for column 'role'" só no navegador; os testes passam
  porque recriam o banco.
- Item 7: colocar `created_at` em `$columns` parece mais simples e
  quebra todo `save()` (MySQL recusa `NULL` em coluna `NOT NULL`).
- Item 2: sem o `return` depois de cada `redirectTo`, o teste de
  controller recebe dois `Location:` na mesma resposta.
- Item 2: o `ControllerTestCase` não limpa o flash; conferir
  `FlashMessage::get()` sem limpar no `tearDown` dá falso negativo
  dependendo da ordem dos testes.
- Item 3: um `render` novo sem `$title` no `compact` agora quebra também
  o `<title>`, não só o `<h1>`.
- Item 1: `assertStringContainsString('Location: /', ...)` também casa
  com `Location: /login`; por isso o teste confere a ausência de
  `/login`.
- Item 4: `$this->currentUser()` dentro da view do painel nunca é `null`
  porque a rota está no grupo `auth`; não copie o bloco para uma view
  pública sem checar `null`.
