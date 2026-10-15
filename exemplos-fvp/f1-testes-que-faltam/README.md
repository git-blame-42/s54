# F1. Escrever os testes que faltam (Entrega 4.1, autenticação)

Pedido, como o professor faria na hora: "Mostrem os testes que faltam
para a entrega 4.1, autenticação."

Chance: alta (a rubrica pesa testes). Tempo estimado: 40 min (cerca de
10 min por arquivo de teste). Catálogo: `fvp-possiveis-modificacoes.md`,
seção F1 (molde do teste de middleware na seção F2 do complemento).

Branch no clone: `fvp/f1-testes-que-faltam`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `f573233` | `test: Add missing controller, access and middleware tests for authentication` |

## O que mudou

- `app/Middleware/Authenticate.php`: `private function redirectTo` virou
  `protected`. Única mudança fora de `tests/`; comportamento igual.
- `app/Middleware/Admin.php`: mesma troca, `private` para `protected`.
- `tests/Unit/App/Middleware/AuthenticateDouble.php` (novo): dublê que
  estende `Authenticate` e guarda o destino em `$redirectedTo` em vez de
  chamar `header()` + `exit`.
- `tests/Unit/App/Middleware/AdminDouble.php` (novo): mesmo dublê para
  `Admin`.
- `tests/Unit/App/Middleware/AuthenticateTest.php` (novo): sem sessão
  redireciona para `/login`; sem sessão grava o flash `danger`; com
  sessão não redireciona.
- `tests/Unit/App/Middleware/AdminTest.php` (novo): participante vai
  para `/dashboard`; participante recebe o flash `danger`; admin passa;
  sem sessão passa (quem barra é o `Authenticate`).
- `tests/Unit/Lib/Authentication/AuthTest.php`: quatro testes novos
  (`logout` tira o id da sessão; `user()` nulo sem sessão; `check()`
  falso sem sessão; `check()` falso com id inexistente).
- `tests/Integration/Controllers/DashboardControllerTest.php` (novo):
  usuário logado vê o título "Painel do Usuário" e "Bem-vindo(a),
  Fulano!".
- `tests/Integration/Controllers/Admin/DashboardControllerTest.php`
  (novo): admin logado vê o título "Painel Administrativo" e "Olá,
  Admin!".
- `tests/Acceptance/authentications/LoginCest.php`: caso
  `accessDashboardAfterLogout` (login, logout, abrir `/dashboard`, voltar
  para `/login` com "Você deve estar logado para acessar essa página").

Resultado: 10 arquivos, +320 / -2 linhas.

### Inventário: cenário da rubrica 4.1 × camada

"existia" = já estava em `86c81ca`; "adicionado" = entra neste commit;
"—" = a camada não se aplica ou não foi coberta (motivo na coluna).

| Cenário 4.1 | Aceitação | Acesso (Guzzle) | Controller | Unit |
|-------------|-----------|-----------------|------------|------|
| Área do usuário sem login | existia: `DashboardCest::accessDashboardWithoutLogin` | existia: `DashboardAccessTest::test_authenticated_route_should_redirect_to_login_without_session` | — (barrado antes, no middleware) | adicionado: `AuthenticateTest` (destino `/login` e flash) |
| Área admin sem login | existia: `AdminDashboardCest::accessAdminWithoutLogin` | existia: `DashboardAccessTest::test_admin_route_should_redirect_to_login_without_session` | — | adicionado: `AdminTest::test_handle_without_session_should_not_redirect` |
| Participante tenta `/admin` | existia: `AdminDashboardCest::accessAdminAsCommonUser` | — (Guzzle sem cookie de sessão) | — | adicionado: `AdminTest` (destino `/dashboard` e flash) |
| Credenciais erradas | existia: `LoginCest::loginWithInvalidCredentials` | — | existia: `AuthenticationsControllerTest::test_authenticate_with_invalid_credentials_should_not_login` | existia: `UserTest::test_authenticate_should_return_false` |
| Login com sucesso (usuário) | existia: `LoginCest::loginWithValidCredentials`, `DashboardCest::accessDashboardAfterLogin` | — | existia: `AuthenticationsControllerTest::test_authenticate_with_valid_credentials_should_login`; adicionado: `DashboardControllerTest` | existia: `AuthTest::test_login`, `test_user`, `test_check`; adicionado: `AuthenticateTest::test_handle_with_session_should_not_redirect` |
| Login com sucesso (admin) | existia: `AdminDashboardCest::accessAdminAsAdmin` | — | adicionado: `Admin\DashboardControllerTest` | existia: `UserTest::test_is_admin_should_return_true_for_admin_role`; adicionado: `AdminTest::test_handle_with_admin_should_not_redirect` |
| Logout | existia: `LoginCest::logout`; adicionado: `LoginCest::accessDashboardAfterLogout` | — | existia: `AuthenticationsControllerTest::test_destroy_should_logout` | existia: `AuthTest::test_logout`; adicionado: `AuthTest::test_logout_should_remove_user_id_from_session` |
| Sessão ausente ou inválida | — | — | — | adicionado: `AuthTest::test_user_without_session_should_return_null`, `test_check_without_session_should_return_false`, `test_check_with_nonexistent_user_id_should_return_false` |
| Rotas públicas (`/`, `/login`) | existia: `HomeIndexCest::seeHomePage` | existia: `HomeAccessTest::test_should_access_home_route` (`GET /` → 200), `DashboardAccessTest::test_public_route_should_be_accessible_without_session` | existia: `HomeControllerTest`, `AuthenticationsControllerTest::test_render_login_page` | — |

Não adicionado de propósito: teste de acesso `GET /` → 200 (já existe em
`HomeAccessTest`); aceitação de logout do admin (o
`LoginHelper::logout()` clica no e-mail fixo `fulano@example.com`; ver
cenário F3).

## Por que assim

- Testes nas quatro camadas que a rubrica cobra: aceitação dos 4
  cenários, acesso a rotas autenticadas e públicas, unit de models e da
  lógica de sessão (`grading-path.md`, seção "4.1 — Autenticação").
- Autorização vive nos middlewares (feedback 2025-2); por isso eles
  ganharam teste unitário próprio, em vez de só aparecerem por tabela
  na aceitação.
- `redirectTo` `private` para `protected`: sem isso a subclasse de teste
  não consegue trocar `header()` + `exit` (o `exit` mataria o PHPUnit).
  É a mesma técnica do `ControllerTestCase`, que sobrescreve o
  `redirectTo` (já `protected`) do `Controller`. Nenhuma rota, mensagem
  ou fluxo muda.
- Dublê em classe nomeada (`AuthenticateDouble`, `AdminDouble`) em vez
  de classe anônima: a anônima numa variável tipada faz o PHPStan 6
  acusar `$redirectedTo` como propriedade inexistente, e o
  `--strict-psr` quer um arquivo por classe.
- Testes de middleware estendem `Tests\TestCase` (cria o banco), porque
  `Auth::check()` consulta `users`; `Router::reset()` + `require
  config/routes.php` fazem `route('users.login')` funcionar fora do
  nginx.
- Um comportamento por teste; nomes `test_<acao>_should_<resultado>`;
  asserts de interface com os textos pt-BR reais ("Painel do Usuário",
  "Você deve estar logado para acessar essa página").
- PSR-12 (linha ≤ 130), PHPStan nível 6, PSR-4 estrito sem avisos;
  identificadores em inglês; commit em inglês `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

O schema não muda; `db:reset` não é necessário.

Resultado obtido: Unit 82 testes, Integration 13, Acceptance 10 (eram
71 / 9 / 9 na base). PHPCS sem erros, PHPStan `[OK] No errors`. Testes
novos:

- `tests/Unit/App/Middleware/AuthenticateTest.php`:
  `test_handle_without_session_should_redirect_to_login`,
  `test_handle_without_session_should_set_danger_flash_message`,
  `test_handle_with_session_should_not_redirect`.
- `tests/Unit/App/Middleware/AdminTest.php`:
  `test_handle_with_participant_should_redirect_to_dashboard`,
  `test_handle_with_participant_should_set_danger_flash_message`,
  `test_handle_with_admin_should_not_redirect`,
  `test_handle_without_session_should_not_redirect`.
- `tests/Unit/Lib/Authentication/AuthTest.php`:
  `test_logout_should_remove_user_id_from_session`,
  `test_user_without_session_should_return_null`,
  `test_check_without_session_should_return_false`,
  `test_check_with_nonexistent_user_id_should_return_false`.
- `tests/Integration/Controllers/DashboardControllerTest.php`:
  `test_index_should_render_user_dashboard_title`,
  `test_index_should_greet_logged_user_by_name`.
- `tests/Integration/Controllers/Admin/DashboardControllerTest.php`:
  `test_index_should_render_admin_dashboard_title`,
  `test_index_should_greet_admin_by_name`.
- `tests/Acceptance/authentications/LoginCest.php`:
  `accessDashboardAfterLogout`.

No navegador: `http://localhost`. Entre como `fulano@example.com` /
`123456`, clique no e-mail e em "Sair", depois abra
`http://localhost/dashboard`: a página volta para `/login` com "Você
deve estar logado para acessar essa página". O resto é só teste; nada
muda na interface.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/f1-testes-que-faltam`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-f1 <caminho>/projeto
git fetch fvp-f1 fvp/f1-testes-que-faltam
git cherry-pick f573233
git remote remove fvp-f1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/f1-testes-que-faltam:fvp-f1
git cherry-pick f573233        # o bundle traz o objeto f573233
git branch -D fvp-f1           # opcional, limpa a ref
```

O cherry-pick cria um commit novo no alvo, com SHA diferente e a mesma
mensagem. Testado num clone limpo de `86c81ca`: aplica sem conflito.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch   # só o diff, sem commit
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

- `tests/Integration/Controllers/DashboardControllerTest.php`: B3, B4 e
  C2 também criam esse arquivo (add/add). Junte os métodos de teste das
  duas versões numa classe só e mantenha um `setUp` que crie o usuário e
  faça `Auth::login`.
- `tests/Unit/App/Middleware/AdminTest.php`: A8 também cria esse arquivo
  (e muda o `Admin` para responder 403); fique com os testes do A8 para o
  participante e mantenha daqui os casos admin e sem sessão, ajustando o
  dublê ao `redirectTo`/resposta que o A8 usar.
- `app/Middleware/Admin.php` e `Authenticate.php`: A1 move o
  `redirectTo` para `BaseMiddleware` (já `protected`) e A8 mexe no
  `Admin`. Fique com a versão do A1/A8; os dublês continuam válidos
  enquanto existir um `redirectTo` `protected` herdado.
- `tests/Unit/App/Middleware/`: A5 cria `GuestDouble.php`/`GuestTest.php`
  na mesma pasta; não há conflito de arquivo, só a pasta em comum.
- `tests/Acceptance/authentications/LoginCest.php`: A3, A5, B5 e C2
  acrescentam casos no fim da classe; conflito de bloco, fique com
  todos os métodos.
- `tests/Unit/Lib/Authentication/AuthTest.php`: B4 acrescenta testes no
  fim; fique com todos.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (schema não
muda).

## Armadilhas

- Sem trocar `private` por `protected` no `redirectTo` dos middlewares,
  o dublê não sobrescreve nada: o `header()` + `exit` real roda e o
  PHPUnit termina no meio, sem relatório.
- Faltou `Router::reset()` + `require config/routes.php` no `setUp` do
  teste de middleware: `route('users.login')` lança "Route with name
  users.login not found".
- Teste de middleware estendendo `PHPUnit\Framework\TestCase` em vez de
  `Tests\TestCase`: não há banco, e `Auth::check()` com sessão quebra.
- `FlashMessage::get()` apaga o flash ao ler; chame uma vez por teste.
- `$_SESSION` vaza entre testes; zere no `setUp` e no `tearDown` (o
  `ControllerTestCase` já faz `unset($_SESSION['user'])`).
- No `ControllerTestCase`, faça `Auth::login` antes do `$this->get(...)`:
  o construtor do controller lê `Auth::user()` na criação.
- `LoginHelper::logout()` clica em `fulano@example.com`; aceitação de
  logout com outro e-mail falha (cenário F3).
- `AuthTest::tearDown` original chama `parent::setUp()` em vez de
  `parent::tearDown()` (o banco não é apagado ao fim). Não foi corrigido
  aqui para não misturar assuntos; se o professor perguntar, é uma linha.
- Em `/admin`, `Admin` roda antes de `Authenticate`; por isso o teste
  "sem sessão passa" do `Admin` é proposital, não lacuna.
