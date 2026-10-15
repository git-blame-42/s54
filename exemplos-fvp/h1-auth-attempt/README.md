# H1. Auth::attempt(): autenticação encapsulada em Auth

Pedido, como o professor faria na hora: "A verificação de e-mail e
senha e a criação da sessão estão espalhadas no controller. Encapsulem
isso na classe `Auth`."

Chance: média (feedback 2025-2: "A criação da sessão, remoção e
checagem deve estar encapsulada na classe auth"; a issue #9 do
repositório público avalia "OO", reutilização e encapsulamento).
Tempo estimado: 15 min. Catálogo: `fvp-possiveis-modificacoes.md`,
seção H1.

Branch no clone: `fvp/h1-auth-attempt`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `9c78829` | `refactor: Encapsulate login attempt in Auth::attempt` |

## O que mudou

- `lib/Authentication/Auth.php`: novo método estático
  `attempt(string $email, string $password): bool`. Faz
  `User::findByEmail`, confere `$user->authenticate($password)` e, se
  der certo, chama `self::login($user)` e devolve `true`; senão devolve
  `false` sem tocar na sessão.
- `app/Controllers/AuthenticationsController.php`: `authenticate` deixa
  de buscar o usuário e chamar `Auth::login`; agora é
  `if (Auth::attempt($params['email'], $params['password']))` com os
  mesmos flashes e redirecionamentos. Sai o `use App\Models\User`.
- `tests/Unit/Lib/Authentication/AuthTest.php`: três testes novos de
  `attempt`.

`User::findByEmail` e `User::authenticate` continuam como estavam;
`Auth::attempt` só os orquestra. Resultado: 3 arquivos, +40 / -4 linhas.

## Por que assim

- Feedback 2025-2 (`grading-path.md`): criação, remoção e checagem da
  sessão encapsuladas na classe `Auth`. Antes, `Auth` tinha
  `login`/`check`/`logout`, mas a decisão "credencial válida, então cria
  sessão" ficava no controller.
- Issue #9 (título "OO"): o professor avalia encapsulamento e
  reutilização; `attempt` pode ser chamado por qualquer outro ponto de
  entrada (ex.: cadastro, API) sem repetir a lógica.
- Controller fino: só lê parâmetros (via `$request->getParam()`), chama
  o domínio e decide flash e destino.
- Refatoração sem mudança de comportamento: o teste de controller
  existente (`AuthenticationsControllerTest`) e os de aceitação passam
  sem alteração.
- PSR-12 e PHPStan nível 6 limpos; identificadores em inglês; commit em
  inglês `refactor: ...`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Sem mudança de schema; não precisa de `db:reset`.

Resultado obtido: Unit 74 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos, em
`tests/Unit/Lib/Authentication/AuthTest.php`:

- `test_attempt_with_valid_credentials_should_login`: devolve `true`,
  `Auth::check()` verdadeiro e `Auth::user()` é o usuário criado.
- `test_attempt_with_wrong_password_should_not_login`: devolve `false`,
  `Auth::check()` falso e `$_SESSION` sem a chave `user`.
- `test_attempt_with_unknown_email_should_not_login`: idem para e-mail
  que não existe.

No navegador: `http://localhost/login` — entrar com
`fulano@example.com` / `123456` leva ao painel com "Login realizado com
sucesso!"; senha errada volta ao login com "E-mail ou senha inválidos!".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/h1-auth-attempt`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-h1 s50/exemplos-fvp/h1-auth-attempt/projeto
git fetch fvp-h1 fvp/h1-auth-attempt
git cherry-pick 9c78829
git remote remove fvp-h1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/h1-auth-attempt/solucao.bundle fvp/h1-auth-attempt:fvp-h1
git cherry-pick 9c78829        # o bundle traz o objeto; o cherry-pick cria um commit novo
git branch -D fvp-h1           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/h1-auth-attempt/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/h1-auth-attempt/solucao.patch   # só o diff, sem commit
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

- `app/Controllers/AuthenticationsController.php`: quase todo cenário
  de login mexe em `authenticate` (A4, A9, A10, A11, A13, B5, B9, C3).
  Resolva mantendo `Auth::attempt(...)` como condição e reaplicando o
  que o outro cenário acrescenta: com A9, use as variáveis `$email` e
  `$password` já validadas; com A4, depois do `attempt` use
  `Auth::user()->isAdmin()` para escolher o destino; com C3, mantenha
  os textos novos dos flashes.
- `lib/Authentication/Auth.php`: A13 (regenerar sessão) mexe em
  `login`; o `attempt` é um método à parte, então em geral basta manter
  os dois blocos. B5 (inativo) e B9 (bloqueio por tentativas) põem
  condições extras na autenticação; a forma OO é levá-las para dentro
  de `attempt` (ou de `User::authenticate`), não de volta ao controller.
- `tests/Unit/Lib/Authentication/AuthTest.php`: testes acrescentados no
  fim da classe; se outro cenário também acrescentar, mantenha os dois
  grupos.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Esquecer de tirar o `use App\Models\User` do controller não quebra
  nada, mas o PHPCS/PHPStan não acusa import sobrando; deixe limpo.
- `attempt` com `string $email`: se o POST vier sem `user[...]`, o
  controller ainda responde 500 (mesmo comportamento da base). O
  tratamento disso é o cenário A9.
- `AuthTest::tearDown` zera `$_SESSION`; os testes de falha conferem
  que `attempt` não grava nada na sessão (`assertArrayNotHasKey`).
- Não mova `findByEmail` nem `authenticate` para `Auth`: eles são do
  model; `Auth` só orquestra e cuida da sessão.
