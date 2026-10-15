# A15. Token CSRF no formulário de login

Pedido, como o professor faria na hora: "Protejam o formulário de login
contra CSRF: o POST só deve ser aceito com um token gerado pelo
servidor."

Chance: baixa (página "Segurança" da wiki; é bônus de framework). Tempo
estimado: 15 min. Catálogo: `fvp-possiveis-modificacoes.md`, seção A15.

Branch no clone: `fvp/a15-token-csrf-login`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `862b31d` | `feat: Protect login form with CSRF token` |

## O que mudou

- `lib/Csrf.php` (novo): `Lib\Csrf::token()` gera 32 bytes aleatórios
  (64 hex) na primeira chamada e guarda em `$_SESSION['_token']`;
  chamadas seguintes devolvem o mesmo valor. `Csrf::check(mixed $token)`
  devolve `true` só se o valor for string e igual ao da sessão, com
  `hash_equals` (comparação em tempo constante).
- `app/views/authentications/new.phtml`: campo oculto
  `<input type="hidden" name="_token" value="<?= \Lib\Csrf::token() ?>">`
  dentro do form.
- `app/Controllers/AuthenticationsController.php`: primeira coisa do
  `authenticate` é `Csrf::check($request->getParam('_token'))`; se
  falhar, `FlashMessage::danger('Sessão expirada, tente de novo.')`,
  redirect para `users.login` e `return` (não chega a procurar o
  usuário).
- `tests/Unit/Lib/CsrfTest.php` (novo): 6 testes da classe.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  `setUp` grava `$_SESSION['_token'] = 'abc'`, `tearDown` limpa `_token`
  e `flash`; os testes de `authenticate` existentes mandam
  `'_token' => 'abc'`; 2 testes novos e uma asserção a mais na página
  de login (campo oculto renderizado).
- `tests/Acceptance/authentications/LoginCest.php`: cenário novo que
  adultera o token pelo navegador e confere a recusa.

Resultado: 6 arquivos, +133 / -2 linhas. `LoginHelper` não muda: o
campo oculto vai junto quando o Selenium clica em "Entrar".

## Por que assim

- Versão mínima do catálogo (sem middleware): protege só o login, que
  é o pedido; a versão geral (middleware `VerifyCsrf` com 403 em todo
  POST/PUT/DELETE) muda testes de acesso e outros forms e só vale com
  escopo combinado.
- Parâmetro lido só via `$request->getParam('_token')` (`grading-path.md`,
  feedback 2025-2).
- Controller fino: geração e conferência ficam em `Lib\Csrf`; o
  controller só decide o redirect.
- `hash_equals` evita ataque de tempo; `random_bytes` é fonte
  criptográfica.
- Testes em três camadas: unit (classe), controller (com e sem token),
  aceitação (login normal continua e token adulterado é recusado).
- Mensagem de interface em pt-BR; identificadores e commit em inglês
  (`type: Title`); PSR-12, PSR-4 (`Lib\Csrf` em `lib/Csrf.php`) e
  PHPStan nível 6 sem avisos.

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

Resultado obtido: Unit 77 testes, Integration 11, Acceptance 10 (eram
71 / 9 / 9 na base). Testes novos:

- `tests/Unit/Lib/CsrfTest.php`:
  `test_token_should_be_generated_and_stored_in_session`,
  `test_token_should_be_stable_within_session`,
  `test_check_with_session_token_should_return_true`,
  `test_check_with_wrong_token_should_return_false`,
  `test_check_without_token_should_return_false` (nulo, vazio e array),
  `test_check_without_session_token_should_return_false`.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  `test_authenticate_without_csrf_token_should_not_login` (resposta
  exatamente `Location: /login`, flash "Sessão expirada, tente de
  novo.") e `test_authenticate_with_invalid_csrf_token_should_not_login`.
- `tests/Acceptance/authentications/LoginCest.php`:
  `loginWithTamperedCsrfToken`.

No navegador: `http://localhost/login`. Veja o código-fonte: há um
`<input type="hidden" name="_token">` com 64 caracteres hex. Logue com
`user@example.com` / `123456`: entra normalmente. Para ver a recusa,
no DevTools troque o `value` do campo oculto e envie: volta para
`/login` com "Sessão expirada, tente de novo.".

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a15-token-csrf-login` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a15 <caminho>/projeto
git fetch fvp-a15 fvp/a15-token-csrf-login
git cherry-pick 862b31d
git remote remove fvp-a15
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a15-token-csrf-login:fvp-a15
git cherry-pick 862b31d        # o bundle preserva o objeto 862b31d
git branch -D fvp-a15          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo (SHA diferente de `862b31d`).

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo num clone de `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `AuthenticationsController.php`: A9 (campos vazios), A10 (manter
  e-mail), A11 (voltar à página pedida), A13 (regenerar sessão), B5
  (usuário inativo), B9 (bloqueio por tentativas) e H1 (`Auth::attempt`)
  mexem no começo de `authenticate`. Resolva deixando o bloco do CSRF
  como primeira verificação, com `return`, e o resto do alvo depois.
- `new.phtml`: A10 e C4 mexem no form de login. Mantenha a versão do
  alvo e só acrescente a linha do campo oculto `_token` logo após o
  `<form>`.
- `AuthenticationsControllerTest.php`: testes de `authenticate` que o
  alvo acrescentou precisam de `'_token' => 'abc'` nos params, senão
  passam a cair no redirect do CSRF. O mesmo vale para testes de
  controller de outros cenários que chamam `authenticate`.
- `LoginCest.php`: só acréscimo de método; conflito trivial, fique com
  os dois.

Depois de aplicar: não há mudança de schema; rode `./run phpcs`,
`./run phpstan` e os testes de novo.

## Armadilhas

- Nos testes de controller o `redirectTo` não interrompe a ação: sem o
  `return` depois do redirect do CSRF, a ação segue, loga o usuário e
  imprime um segundo `Location:`. O teste usa `assertSame` justamente
  para pegar isso.
- `ControllerTestCase::tearDown` só limpa `$_SESSION['user']`; `_token`
  e `flash` vazariam entre testes, por isso o `tearDown` próprio.
- `getParam` devolve `mixed`: um POST com `_token[]=x` mandaria array.
  Por isso `check` recebe `mixed` e testa `is_string` (o catálogo usa
  `?string`, que daria `TypeError` e resposta 500 nesse caso).
- `fillField` não serve para campo oculto no Selenium; para adulterar o
  token no teste de aceitação use `executeJS`.
- Se o professor pedir a versão geral (middleware `csrf` em todo POST),
  o form de logout (A2) e os testes de acesso com POST também mudam
  (403 em vez de 302). Combine o escopo antes.
