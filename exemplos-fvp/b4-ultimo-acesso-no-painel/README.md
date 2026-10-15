# B4. Mostrar o último acesso no painel

Pedido, como o professor faria na hora: "Mostre no painel do usuário
quando foi o último acesso dele."

Chance: média (a coluna `last_login_at` já existe). Tempo estimado:
10 min. Catálogo: `fvp-possiveis-modificacoes.md`, seção B4.

Branch no clone: `fvp/b4-ultimo-acesso-no-painel`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `ad66fa0` | `feat: Show previous login on user dashboard` |

## O que mudou

- `lib/Authentication/Auth.php`: `login()` guarda
  `$user->last_login_at` em `$_SESSION['user']['previous_login_at']`
  ANTES de chamar `registerLogin()`; novo `previousLoginAt(): ?string`;
  `logout()` limpa a chave nova também.
- `app/Controllers/DashboardController.php`: lê
  `Auth::previousLoginAt()` e passa `$previousLoginAt` para a view no
  `compact`.
- `app/views/dashboard/index.phtml`: se `null`, "Este é o seu primeiro
  acesso."; senão "Último acesso: dd/mm/aaaa hh:mm" (`date('d/m/Y
  H:i', strtotime(...))`).
- `tests/Unit/Lib/Authentication/AuthTest.php`: +3 testes
  (`previousLoginAt` nulo no primeiro login; devolve o login anterior e
  não o atual; `logout` limpa).
- `tests/Integration/Controllers/DashboardControllerTest.php` (novo):
  +2 testes (mensagem de primeiro acesso; data formatada).
- `tests/Acceptance/dashboard/DashboardCest.php`: `accessDashboardAfterLogin`
  também vê "Este é o seu primeiro acesso."; novo
  `accessDashboardShowsPreviousLogin` grava `last_login_at` antes de
  logar e vê "Último acesso: 01/10/2026 08:30".

6 arquivos, +103 / -1.

## Por que assim

- Escolhi a variante "acesso anterior", não "login atual". O catálogo
  avisa: `Auth::login` grava `last_login_at` antes de abrir a sessão,
  então ler `$this->currentUser()->last_login_at` na view mostra o
  horário de agora, o que não serve para nada na demo. Guardar o valor
  antigo na sessão custa uma linha e dá um dado real.
- Lógica de sessão fica em `lib/Authentication/Auth.php`, como o
  feedback 2025-2 exige (`grading-path.md`); o controller só passa o
  valor; a view só formata.
- `previousLoginAt()` tem tipo de retorno `?string` e docblock em pt-BR
  (PHPStan nível 6).
- Texto em pt-BR, formato brasileiro de data; identificadores em
  inglês; commit `type: Title` em inglês (Code Standards).
- Testes nas três camadas que a rubrica cobra: unitário (lib), controller
  e aceitação.

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

Resultado obtido: Unit 74 testes, Integration 11, Acceptance 10 (eram
71 / 9 / 9 na base). Testes novos:

- `AuthTest::test_previous_login_at_should_be_null_on_first_login`
- `AuthTest::test_previous_login_at_should_return_the_last_login_before_this_one`
- `AuthTest::test_logout_should_clear_previous_login_at`
- `DashboardControllerTest::test_index_should_show_first_access_message_on_first_login`
- `DashboardControllerTest::test_index_should_show_previous_login_date_and_time`
- `DashboardCest::accessDashboardShowsPreviousLogin`

No navegador: `http://localhost`. `./run db:reset && ./run db:populate`
para zerar; logue como `user@example.com` / `123456`: o painel diz
"Este é o seu primeiro acesso.". Saia e logue de novo: "Último acesso:
<data e hora do login anterior>".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/b4-ultimo-acesso-no-painel` (relativo ao clone do
s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b4 <caminho>/projeto
git fetch fvp-b4 fvp/b4-ultimo-acesso-no-painel
git cherry-pick ad66fa0
git remote remove fvp-b4
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/b4-ultimo-acesso-no-painel:fvp-b4
git cherry-pick ad66fa0
git branch -D fvp-b4         # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O commit
novo tem outro SHA (cherry-pick sempre cria um commit novo); a árvore
fica idêntica à da branch do bundle.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo sobre `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário:

- `app/views/dashboard/index.phtml` e `DashboardCest.php`: se o alvo
  já mexeu no painel (B3 mostra o telefone no mesmo arquivo). Mantenha
  as duas linhas; na Cest, mantenha os dois `see`.
- `tests/Integration/Controllers/DashboardControllerTest.php`: B3 e F1
  também criam esse arquivo. Fique com um só e junte os métodos de teste
  (o `setUp` é igual).
- `lib/Authentication/Auth.php`: só se o alvo alterou `login()`/`logout()`.

Depois de aplicar: rode os testes e o `./run phpcs` de novo. Não há
mudança de schema.

## Armadilhas

- Ler `last_login_at` direto na view mostra o login atual, não o
  anterior; é a pegadinha do catálogo. Se o professor quiser
  literalmente "a data do último login registrado", aí sim é
  `$this->currentUser()->last_login_at` sem mexer no `Auth`.
- `Auth::login` recebe o objeto `User` que o controller acabou de
  buscar; o `last_login_at` nele é o valor do banco antes do
  `registerLogin()`. Nos testes, depois de `update(['last_login_at' =>
  ...])`, recarregue com `User::findById` antes de chamar `Auth::login`.
- `strtotime` de uma string `Y-m-d H:i:s` do MySQL funciona; não passe
  `null` para ele (por isso o `if` na view).
- `ControllerTestCase::tearDown` apaga `$_SESSION['user']` inteiro, então
  a chave nova não vaza entre testes.
